# Chapter 4 — Multi-Factor SSH আর কে Login Try করতে পারবে সীমিত করা

> **Course:** Linux Server Security & Secrets · **Chapter:** 4 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-security/04-mfa-and-limiting-who-logs-in)

> **Honesty note:** এটাও live server-এ চালানো হয়নি, আগের chapter-গুলোর মতোই কারণে — এই chapter `sshd_config` আর PAM auth stack একসাথে বদলায়, যার blast radius Chapter 1-এর edit-গুলোর চেয়েও বড়। এই course-এর একমাত্র topic এটাই যার আসলেই interactive setup লাগে (phone app দিয়ে একটা QR code scan করা), যা non-interactively demonstrate করাও যায় না। নিচের প্রতিটা directive আর command OpenSSH-এর `sshd_config(5)`, Google Authenticator PAM module-এর নিজের documentation, আর Ubuntu/DigitalOcean-এর লেখা MFA guide থেকে নেওয়া, সব শেষে link দেওয়া। Chapter 1-এর "কাজ করা session খোলা রাখো" নিয়ম এখানে আগের চেয়েও সাবধানে মানো — Section 3-এ বলা আছে এই chapter কেন ভুল হলে আরও ঝুঁকির।

Chapter 1 SSH-কে key-only করে দিয়েছে। একটা key "something you have" — কিন্তু সেই একটা file কখনো চুরি হয়ে গেলে (একটা compromised laptop, একটা careless backup), শুধু এটা দিয়েই login করা যায়। এই chapter একটা দ্বিতীয়, independent factor যোগ করে, আর Chapter 1-এর `AllowUsers`-এর চেয়েও একটা step এগিয়ে গিয়ে ঠিক কে authenticate করার চেষ্টাই করতে পারবে তা নিয়ন্ত্রণ করে।

## ১. "Multi-factor" আসলে এখানে কী দেয়

Authentication factor সাধারণত তিন ধরনে ভাগ করা হয়: তুমি যা **জানো** (একটা password, একটা PIN), তোমার কাছে যা **আছে** (একটা key, একটা phone, একটা hardware token), আর তুমি যা **আছো** (একটা fingerprint)। Chapter 1-এর key login হলো "something you have"। আসল multi-factor মানে **দুই আলাদা ধরনের** factor একসাথে করা, যাতে একটা factor চুরি হলেই যথেষ্ট না হয় — এই chapter "something you know" যোগ করে: একটা TOTP (Time-based One-Time Password) app থেকে আসা 6-digit code, যা প্রতি 30 সেকেন্ডে বদলায়, phone আর server-এর মধ্যে শুধু share করা একটা secret থেকে generate হয়।

## ২. Install আর enroll করা: Google Authenticator-এর PAM module

```bash
sudo apt install libpam-google-authenticator
```

MFA লাগবে এমন প্রতিটা user এটা **একবার, interactively, নিজের হয়ে** চালায় (root হয়ে না, অন্য কারো জন্য না):

```bash
google-authenticator
```

এটা কয়েকটা yes/no প্রশ্ন করে (time-based token: yes; rate-limiting: yes হলো standard recommended উত্তর) আর তারপর একটা QR code আর একটা secret key দেখায়, সাথে **পাঁচটা emergency scratch code**। QR code-টা একটা authenticator app দিয়ে scan করো (Google Authenticator, Authy, বা কোনো standard TOTP app), আর **scratch code-গুলো server ছাড়া অন্য কোথাও লিখে রাখো** — প্রতিটা ঠিক একবার ব্যবহার করা যায়, specifically সেই scenario-র জন্য যখন তোমার phone হারিয়ে গেছে কিন্তু তখনও ঢুকতে হবে।

## ৩. এই chapter এখন পর্যন্ত সবচেয়ে ঝুঁকির config change কেন

আগের প্রতিটা chapter-এর worst case ছিল "disconnect হয়ে গিয়ে key বা password দিয়ে আর ঢুকতে পারছি না।" এই chapter-এর worst case একই, **সাথে** একটা নতুন failure mode: একটা সঠিকভাবে দেওয়া key *আর* একটা সঠিকভাবে টাইপ করা TOTP code তারপরও reject হওয়া, কারণ PAM stack আর `sshd_config` একে অপরের সাথে একমত না। Real server-এ হাত দেওয়ার আগে Section 4–5 পুরো পড়ো, আর Chapter 1, Section 4-এর মতো একদম নতুন session থেকে test করো — একটা fresh login, দুটো factor দিয়েই, সত্যিই সফল না হওয়া পর্যন্ত original session বন্ধ করো না।

## ৪. PAM আর sshd-কে একসাথে, সাবধানে, wire করা

দুটো file একে অপরের সাথে একমত হতে হবে, আর যে subtlety-তে মানুষ ভুল করে সেটা ঠিক নিচে।

**`/etc/pam.d/sshd`** — `@include common-auth` লাইনটা খুঁজো (এটাই `pam_unix.so` pull করে, যে module একটা Unix password check করে) আর এটা comment out করে, বদলে Google Authenticator module দাও:

```
# @include common-auth
auth required pam_google_authenticator.so
```

**`/etc/ssh/sshd_config`** — দুটো directive, একসাথে পড়ো:

```
KbdInteractiveAuthentication yes
AuthenticationMethods publickey,keyboard-interactive
```

এখানে subtlety-টা: **Chapter 1 তোমাকে `KbdInteractiveAuthentication no` সেট করতে বলেছিল**, specifically একটা path বন্ধ করার জন্য যা একটা password চালাতে দিতে পারতো। এই chapter সেটা আবার **on** করে — কিন্তু এটা password login আবার খোলে না, উপরের PAM edit-এর কারণে। "Keyboard-interactive" শুধু একটা *transport*, PAM যা-ই চাক; `common-auth` comment out করে দেওয়ার ফলে, এই path-এ PAM এখন আর যা চায় তা শুধু একটা TOTP code, কখনো Unix password না। `AuthenticationMethods publickey,keyboard-interactive` ঠিক এটাই enforce করে "দুটোই, সেই order-এ": OpenSSH প্রথমে publickey step সফল হওয়া লাগবে, তারপরই keyboard-interactive (এখন TOTP-only) step offer করে — একটা চুরি হওয়া key একা step এক পার করে step দুই-তে পুরো থেমে যায়।

![Two-factor SSH: প্রথমে publickey, তারপর keyboard-interactive দিয়ে একটা TOTP code — একটা non-interactive deploy key-এর জন্য একটা Match exception সহ](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/11-linux-security/images/mfa-ssh-flow.svg)

Chapter 1-এর মতোই restart করো: `sudo sshd -t`, তারপর `sudo systemctl restart ssh`, তারপর একটা নতুন session থেকে fresh login, দুটো factor-ই লাগবে, কিছু বন্ধ করার আগে।

## ৫. এটা automation-এর জন্য যে practical সমস্যা তৈরি করে — আর `Match`

CI/CD (CI/CD course-এর GitHub Actions pipeline) ব্যবহার করা একটা deploy key একটা 6-digit code টাইপ করতে পারে না — এটা একটা non-interactive script, phone হাতে একজন human না। প্রতিটা account-এর জন্য `AuthenticationMethods publickey,keyboard-interactive` চাওয়া এটা on করার মুহূর্তেই প্রতিটা automated deploy ভেঙে দিতো।

`sshd_config` এটা একটা **`Match`** block দিয়ে সমাধান করে — Chapter 6 (least privilege) যে mechanism per-account policy-র জন্য ব্যবহার করে, এখানে MFA-কে conditional করার জন্য apply করা:

```
Match User deploy
    AuthenticationMethods publickey

Match all
    AuthenticationMethods publickey,keyboard-interactive
```

`Match User deploy` শুধু ওই একটা account-এর জন্য apply হয় আর তার জন্য `AuthenticationMethods`-কে key-only-এ override করে; `Match all` (যেটা সবার পরে থাকতে হবে — `Match` block order-এ apply হয়, আর প্রতিটা setting-এর জন্য প্রথম match করা block-টাই জেতে) বাকি প্রতিটা, human-operated account-এর জন্য two-factor requirement ফিরিয়ে দেয়। Deploy key ঠিক Chapter 1 যেমন রেখেছিল তেমনই strong থাকে — একটা চুরি হওয়া deploy key তখনও একটা real risk, এই chapter এটা সমাধান করার claim করছে না; Chapter 6 (least privilege) হলো যা সীমিত করে সেই একটা account misuse হলে আসলে কতটা করতে পারবে।

## ৬. একটা honest dependency: server-এর clock

একটা TOTP code valid হয় শুধু কারণ server আর তোমার phone একই shared secret থেকে একই 30-সেকেন্ডের time window compute করে — server-এর clock drift করে থাকলে, তোমার phone থেকে সঠিকভাবে টাইপ করা একটা code "ভুল" হিসেবে reject হতে পারে, এমনকি code নিজে কোনোভাবে ভুল না হলেও। Ubuntu default-এ `systemd-timesyncd` চালায়, clock যথেষ্ট কাছাকাছি রাখে যে practice-এ এটা খুব কম issue হয়, কিন্তু TOTP login কোনো apparent reason ছাড়া fail করতে শুরু করলে একটা quick check worth:

```bash
timedatectl
```

```
               Local time: ...
           NTP service: active
```

`NTP service: active` যা তুমি দেখতে চাও; এটা `inactive` বললে, সেটা একটা খুব plausible, honest explanation code "কাজ করা উচিত" কিন্তু করছে না তার জন্য।

## ৭. Source IP সীমিত করা: সবচেয়ে strong control, আর এর real-world limit

"কে login try করতে পারবে সীমিত করা"-র সবচেয়ে strict version হলো attempt *কোথা থেকে* আসতে পারে সেটাই সীমিত করা, শুধু কোন account বা কোন factor না। `sshd_config` এটা একটা `Match Address` block দিয়ে করতে পারে (একইভাবে, Chapter 2-এর firewall একটা layer আগেই `ufw allow from <ip> to any port 22` দিয়ে একই কাজ করতে পারে):

```
Match Address 203.0.113.50
    PasswordAuthentication no
```

এটা নিয়ে honest হয়ে বলতে গেলে: এটা সবচেয়ে strong possible control — অন্য কোনো IP থেকে একটা attempt কোনো authentication method consider হওয়ার আগেই reject হয় — কিন্তু এটা কাজ করে শুধু একটা **fixed, known source**-এর জন্য: একটা office IP, একটা VPN exit node, একটা bastion host। একজন solo developer যে বাসা থেকে, একটা co-working space থেকে, আর এক সপ্তাহে তার phone-এর hotspot থেকে connect করে, তার restrict করার মতো একটা single IP নেই — ঠিক এই কারণেই এই course এটাকে একটা specific situation-এর জন্য available tool হিসেবে present করে, Chapter 1–3-এর মতো একটা universal recommendation হিসেবে না।

## ৮. মূল কথা

- Multi-factor মানে দুটো *আলাদা ধরনের* factor একসাথে করা — এখানে Chapter 1-এর key ("have") সাথে একটা TOTP code ("know") — যাতে একটা চুরি হলেই যথেষ্ট না হয়।
- `google-authenticator` একজন user-কে interactively enroll করে, একটা QR code আর একবার-ব্যবহারযোগ্য scratch code দিয়ে, যা server ছাড়া অন্য কোথাও নিরাপদে রাখতে হবে।
- `KbdInteractiveAuthentication` আবার **on** করা password login আবার খোলে না, যতক্ষণ `/etc/pam.d/sshd`-এর `common-auth` (যা Unix password check করে) `pam_google_authenticator.so` দিয়ে replace করা থাকে — keyboard-interactive path TOTP-only হয়ে যায়।
- `AuthenticationMethods publickey,keyboard-interactive` দুটো factor-ই enforce করে, সেই order-এ — একটা চুরি হওয়া key একা দ্বিতীয় step-এ থেমে যায়।
- একটা `Match User` block একটা non-interactive automation account-কে (একটা CI/CD deploy key) MFA থেকে exempt করতে পারে, প্রতিটা human account-এর জন্য requirement কমানো ছাড়াই।
- TOTP server-এর clock সঠিক থাকার উপর নির্ভর করে (`timedatectl`) — drift "সঠিক code reject" হওয়ার একটা real, non-obvious কারণ।
- `Match Address` সবচেয়ে strong "কে try করতে পারবে" control, কিন্তু শুধু একটা fixed, known source IP থাকলেই practical — একটা universal recommendation না।

## ৯. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. একটা TOTP code যোগ করা কেন আসলে genuine multi-factor, শুধু একটা দ্বিতীয় password না কেন?</strong></summary>

এটা একটা আলাদা ধরনের factor — "something you know" (TOTP code, time-based আর app-generated) "something you have"-এর (SSH key) সাথে combine করা — একই ধরনের দুটো instance না। শুধু key চুরি হলে আর session authenticate হয় না।
</details>

<details>
<summary><strong>Q2. KbdInteractiveAuthentication আবার "yes" করা কেন password-based login আবার খোলে না, Chapter 1 এটা specifically বন্ধ করার জন্য "no" সেট করেছিল যেখানে?</strong></summary>

কারণ /etc/pam.d/sshd-এর common-auth লাইন (যা Unix password check করে) comment out করে pam_google_authenticator.so দিয়ে replace করা থাকে — keyboard-interactive path এখন শুধু একটা TOTP code চায়, কখনো password না, sshd_config-এর setting আবার "yes" হলেও।
</details>

<details>
<summary><strong>Q3. AuthenticationMethods publickey,keyboard-interactive আসলে কী enforce করে?</strong></summary>

দুটো factor-ই সফল হতে হবে, সেই specific order-এ: publickey check প্রথমে pass করতে হবে, আর তারপরই keyboard-interactive (TOTP) step offer করা হয়। একটা চুরি হওয়া key একা step এক pass করে কিন্তু step দুই-তে থেমে যায়।
</details>

<details>
<summary><strong>Q4. একটা CI/CD deploy key কেন একজন human account-এর মতো একই AuthenticationMethods simply ব্যবহার করতে পারে না?</strong></summary>

একটা automated script একটা phone app থেকে একটা time-based code টাইপ করতে পারে না — এটা non-interactive। প্রতিটা account-এর জন্য keyboard-interactive চাওয়া MFA enable হওয়ার মুহূর্তেই প্রতিটা automated deploy ভেঙে দিতো।
</details>

<details>
<summary><strong>Q5. একটা Match User block কীভাবে বাকি সবার জন্য MFA দুর্বল না করেই deploy-key সমস্যা সমাধান করে?</strong></summary>

একটা Match User deploy block শুধু ওই account-এর জন্য AuthenticationMethods-কে publickey-only-এ override করে, আর পরের একটা Match all block বাকি প্রতিটা account-এর জন্য two-factor requirement রেখে দেয় — exemption ঠিক একটা account-এ scoped যার এটা দরকার।
</details>

<details>
<summary><strong>Q6. একজন user-এর phone-এ ঠিক দেখানো একটা TOTP code তারপরও server reject করে। একটা honest, non-obvious কারণ বলো।</strong></summary>

Server-এর system clock drift করেছে (timedatectl-এ NTP service inactive দেখানো) - TOTP code valid হয় শুধু কারণ server আর phone একই secret থেকে একই time window compute করে, তাই একটা clock mismatch একটা অন্যভাবে-সঠিক code reject করে।
</details>

<details>
<summary><strong>Q7. google-authenticator-এর scratch code কী জন্য, আর কোথায় রাখা উচিত?</strong></summary>

এগুলো একবার-ব্যবহারযোগ্য emergency code, যখন তোমার TOTP app/phone-এ access হারিয়ে গেছে কিন্তু তখনও login করতে হবে। এগুলো server ছাড়া অন্য কোথাও লিখে রাখা উচিত, কারণ এর পুরো উদ্দেশ্য হলো server-এর নিজের MFA normally satisfy করা না গেলে access ফিরে পাওয়া।
</details>

<details>
<summary><strong>Q8. এই chapter-এ Match Address-কে কেন সবচেয়ে strong control বলা হয়েছে, কিন্তু একটা universal recommendation হিসেবে না?</strong></summary>

এটা একটা specific, known source IP-এর বাইরে থেকে কোনো attempt কোনো authentication method try হওয়ার আগেই reject করে - factor-based কোনো check-এর চেয়ে stronger। কিন্তু এটা শুধু একটা fixed source-এর জন্য কাজ করে (একটা office, একটা VPN exit, একটা bastion host); দিন দিন আলাদা network থেকে connect করা কারো জন্য এটা fit করে না।
</details>

## ১০. Free resources

- Google Authenticator PAM module: [official GitHub repository](https://github.com/google/google-authenticator-libpam)
- OpenSSH: [`sshd_config(5)` manual page](https://man.openbsd.org/sshd_config) (`AuthenticationMethods`, `Match`, `KbdInteractiveAuthentication` দেখো)
- DigitalOcean: [How To Set Up Multi-Factor Authentication for SSH on Ubuntu](https://www.digitalocean.com/community/tutorials/how-to-set-up-multi-factor-authentication-for-ssh-on-ubuntu-20-04)
- Ubuntu Server Documentation: [Time synchronisation](https://ubuntu.com/server/docs/network-ntp)
- `man sshd_config`, `man pam.d`, `man timedatectl`

---

**Next:** [Chapter 5 — Secrets management: কখনো git-এ না, সবসময় rotatable](05-secrets-management.md)
