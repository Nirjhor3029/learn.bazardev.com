# Chapter 3 — fail2ban: পুনরাবৃত্তি করা আক্রমণকারীদের জন্য Automatic Banning

> **Course:** Linux Server Security & Secrets · **Chapter:** 3 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-security/03-fail2ban-and-automatic-banning)

> **Honesty note:** এই chapter-এর জন্য fail2ban লেখার সময় live server-এ install বা চালানো হয়নি — Chapter 1 আর 2-এর মতোই কারণ: যে tool-এর কাজ suspicious দেখতে পাওয়া IP ban করা, সেটা একটা SSH-এর মাধ্যমেই পৌঁছানো যায় এমন server-এ লেখা session-টাকেই ban করতে পারে। নিচের প্রতিটা default, config path, আর command fail2ban-এর official documentation আর `man` page থেকে নেওয়া (শেষে link দেওয়া), আর Section 6-এ একটা truly safe, read-only উপায় দেখানো আছে যেটা দিয়ে কোনো risk ছাড়াই একটা filter test করা যায়।

Chapter 1 password login বন্ধ করে দিয়েছে, তাই brute-force করার মতো কিছু বাকি নেই। Chapter 2 একটা প্রথম, cheap throttle যোগ করেছে `ufw limit` দিয়ে। তাহলে কি একটা সম্পূর্ণ নতুন tool যোগ করার কারণ এখনও আছে? হ্যাঁ — আর এই chapter honestly বলে দেয় fail2ban আগের দুই chapter-এ যা নেই তার মধ্যে ঠিক কী যোগ করে।

## ১. Firewall একা যা করে না, fail2ban আসলে কী করে

`ufw limit` (Chapter 2) প্রতিটা IP-এর raw *connection attempt* count করে, সেটা সফল হয়েছে কিনা তা না জেনেই — একটা login আসলে fail করেছে কিনা সেটা এর কোনো idea নেই। **fail2ban** একটা আলাদা, smarter layer-এ কাজ করে: এটা একটা service-এর **log file পড়ে**, এমন line-এর সাথে একটা regex pattern match করে যার মানে "এই login attempt fail হয়েছে," specifically সেগুলোই count করে, আর একটা time window-এর মধ্যে threshold পার হলে **সেই IP-কে firewall-এ ban করে** — automatically, কোনো human real-time log দেখার দরকার নেই।

এই পার্থক্যটা practice-এ গুরুত্বপূর্ণ:

- এটা *fail* বোঝে, শুধু *connection count* না — একজন সত্যিকারের user কয়েকবার retry করা আর একজন attacker শত শত username guess করা একই না, আর fail2ban-এর log-based matching একটা raw connection-rate limit-এর চেয়ে ভালোভাবে এগুলো আলাদা করতে পারে।
- এটা একটা service-এর সাথে বাঁধা না। একই engine SSH-কে protect করে, আর — configure করা হলে — failed attempt log করা অন্য যেকোনো service: একটা web app-এর login form, Postfix, একটা admin panel। একটা tool, একাধিক jail।
- এটা log-এ যা fail হয়েছে বলে ঠিক তার response দেয় — "invalid user", "wrong password", "wrong key" — যা একটা connection-count limit normal traffic pattern থেকে আলাদা করতে পারে না।

## ২. Vocabulary: jail, filter, আর action

fail2ban-এর config একটা protected service-এর জন্য তিনটা অংশ নিয়ে তৈরি:

- একটা **jail** — একটা service-এর জন্য on/off switch আর setting (কোন log দেখা, কতবার fail, কতক্ষণে, কতক্ষণ ban করা)।
- একটা **filter** — সেই service-এর log format-এ একটা failed attempt চেনার regex (fail2ban আগে থেকেই অনেক common service-এর filter নিয়ে আসে)।
- একটা **action** — একটা banned IP-র সাথে আসলে কী হয় (Debian/Ubuntu-এ default-এ, একটা firewall rule যা তার traffic drop করে; exact mechanism তোমার firewall backend-এর উপর নির্ভর করে)।

```
[jail: sshd]  --uses--> filter (sshd.conf: "Failed password", "Invalid user", ...-এর regex)
              --uses--> action (matched IP-কে firewall-এ ban করা)
              --uses--> backend (log কীভাবে পড়বে: file, বা systemd journal)
```

## ৩. দুটো config file, আর একটা rule যা ভাঙা উচিত না

```bash
sudo apt install fail2ban
```

fail2ban `/etc/fail2ban/jail.conf` নিয়ে আসে, এটা জানা প্রতিটা service-এর জন্য sane default নিয়ে — কিন্তু **এই file প্রতিটা package upgrade-এ overwrite হয়ে যায়**, তাই এতে তুমি যা লিখো তা নিঃশব্দে হারিয়ে যায়। Convention, এই shipped file-এর নিজের comment-এই সরাসরি বলা: `jail.conf` কখনো edit করো না; বরং `/etc/fail2ban/jail.local` বানাও, যেটা শুধু তুমি বদলাতে চাওয়া কয়েকটা value সেট করে আর upgrade কখনো touch করে না। `jail.local`-এর setting `jail.conf`-এর matching setting-কে override করে।

```bash
sudo nano /etc/fail2ban/jail.local
```

```ini
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true
```

- **`maxretry = 5`** — এতবার fail হলে `findtime`-এর মধ্যে...
- **`findtime = 10m`** — ...এই time window-এর মধ্যে...
- **`bantime = 1h`** — ...এতক্ষণের একটা ban trigger হয়।
- `[sshd]` আগে থেকে shipped করা SSH jail-কে ওই `[DEFAULT]` value দিয়ে on করে; কোনো service-specific section তিনটার কোনোটাই override করতে পারে প্রতি service, `jail.local` আর `jail.conf`-এর মতোই override logic।

![একটা failed-login timeline: findtime, maxretry, bantime, আর recidive](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/11-linux-security/images/fail2ban-timeline.svg)

Apply করতে restart করো, তারপর jail আসলে active কিনা check করো:

```bash
sudo systemctl restart fail2ban
sudo fail2ban-client status sshd
```

```
Status for the jail: sshd
|- Filter
|  |- Currently failed: 0
|  |- Total failed:     0
|  `- Journal matches:  _SYSTEMD_UNIT=ssh.service + _COMM=sshd
`- Actions
   |- Currently banned: 0
   |- Total banned:     0
   `- Banned IP list:
```

এখানে `Journal matches` দেখায় fail2ban flat log file-এর বদলে systemd journal থেকে পড়ছে — current Ubuntu-তে, এটাই `sshd` jail-এর জন্য fail2ban-এর auto-detect করা default backend, কারণ Chapter 1-এর `journalctl -u ssh` output একই data source।

## ৪. নিজেকে ban না করে একটা filter test করা

তোমার নিজের server-এর বিরুদ্ধে manually পাঁচটা failed SSH login generate করে এটা test করা একটা truly খারাপ idea হবে — এটা তোমার নিজের IP যেটা ban হচ্ছে, আর যদি তুমি সেই IP থেকেই connected থাকো, Chapter 1-এর lock-out ঝুঁকি ফিরে আসে, এবার নিজেরই করা। fail2ban ঠিক এই কাজের জন্য একটা tool নিয়ে আসে: `fail2ban-regex` একটা filter check করে log line-এর বিরুদ্ধে **কাউকে ban না করে বা firewall-এ কিছু touch না করেই** — সম্পূর্ণ read-only একটা regex test:

```bash
fail2ban-regex /var/log/auth.log /etc/fail2ban/filter.d/sshd.conf
```

```
Results
=======
Failregex: 3 total
Ignoreregex: 0 total
```

এটা confirm করে `sshd` filter তোমার system-এ real log format-এর line-এর সাথে আসলে match করছে কিনা — একটা jail real ban-এর উপর trust করার আগে সঠিকভাবে configure করা কিনা validate করার নিরাপদ উপায়।

## ৫. একটা real ban check করা, আর তা থেকে ফিরে আসা

```bash
sudo fail2ban-client status sshd        # দেখো কে currently banned
sudo fail2ban-client set sshd unbanip 203.0.113.44   # manually একটা ban তুলে ফেলো
```

দরকার হওয়ার আগেই জেনে রাখা worth: যদি তুমি কখনো তোমার নিজের server-এর fail2ban দিয়ে ban হয়ে যাও (একটা shared office IP যেখানে একজন colleague কয়েকবার login fail করলে সেই IP-এর পেছনের সবার বিরুদ্ধেই count হয়), fix হলো অন্য একটা network থেকে, বা — বেশিরভাগ provider-এ — VPS-এর web-based console/VNC access যা SSH আর firewall সম্পূর্ণ bypass করে, ঠিক এই ধরনের lockout-এর জন্যই।

## ৬. আরও এগিয়ে: `recidive` jail

fail2ban একটা `recidive` jail নিয়ে আসে যা একটা service log-এর বদলে এর **নিজের ban log** দেখে — যদি একটা IP যেকোনো jail দিয়ে বারবার ban হয়, `recidive` সেটাকে আরও অনেক বেশি সময়ের জন্য ban করে (এর নিজের shipped default হলো এক সপ্তাহ)। এটাই "যে attacker ban শেষ হওয়ার জন্য অপেক্ষা করে আবার try করে তার কী হবে"-এর practical উত্তর — পুনরাবৃত্তি করা আক্রমণকারী automatically escalate হয়:

```ini
[recidive]
enabled  = true
bantime  = 1w
findtime = 1d
maxretry = 3
```

## ৭. Password আগে থেকেই বন্ধ থাকলেও এটা কেন গুরুত্বপূর্ণ

একটা fair প্রশ্ন: Chapter 1 আগে থেকেই password login বাদ দিয়ে দিলে, fail2ban কি আসলে কিছু protect করছে? এটা এখনও করছে তার তিনটা honest কারণ:

1. **Defense in depth** — যদি কখনো `sshd_config` accidentally revert হয়ে যায় (একটা config management mistake, একটা provider image reset), fail2ban সেই setting-এর থেকে independently এখনও থাকে।
2. **অন্য jail, অন্য service** — একবার server-এ অন্য কিছুর login থাকলে (basic auth-এর পেছনে একটা Laravel admin panel, Postfix, নিজের brute-forceable form থাকা একটা app), একই engine নিজের jail আর filter দিয়ে সেটা protect করে।
3. **Noise কমানো যেকোনো ক্ষেত্রেই useful থাকে** — `Invalid user` scanning (Chapter 1, Section 7) একটা key-only server-এর বিরুদ্ধেও হতেই থাকে; কয়েকটা attempt-এর পর ওই source IP ban করা log ছোট রাখে আর automated scanner-কে তোমার server-এর resource-এ লেগে থাকতে দেয় না।

## ৮. মূল কথা

- fail2ban log পড়ে **specifically failed attempt**-এর জন্য, raw connection count না — Chapter 2-এর `ufw limit`-এর একটা smarter complement, তার replacement না।
- একটা protected service-এর জন্য তিনটা অংশ: একটা **jail** (on/off + threshold), একটা **filter** (regex, "এটা fail হয়েছে"), একটা **action** (একটা banned IP-র সাথে কী হয়)।
- **`jail.conf` কখনো edit করো না** — এটা upgrade-এ overwrite হয়ে যায়। সব override `jail.local`-এ রাখো।
- `findtime`-এর মধ্যে `maxretry` বার fail হলে একটা ban trigger হয় যা `bantime` ধরে থাকে।
- `fail2ban-regex` একটা filter real log line-এর বিরুদ্ধে test করে **কাউকে ban না করেই** — একটা jail validate করার নিরাপদ উপায়।
- `recidive` jail *যেকোনো* jail-এর পুনরাবৃত্তি করা আক্রমণকারীকে automatically আরও বেশি সময়ের জন্য ban করে।
- Password login বন্ধ থাকলেও এখনও চালানো worth: defense in depth, অন্য service-এর protection, আর log noise কমানো।

## ৯. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. <code>ufw limit</code>-এর মতো একটা raw connection-count limit যা detect করতে পারে না, fail2ban তার কী detect করে?</strong></summary>

fail2ban service-এর log পড়ে আর specifically failed attempt-এর সাথে match করে (wrong password, invalid user, আর similar কিছু), একজন attacker-এর fail আর একজন legitimate user-এর normal traffic আলাদা করে — একটা connection-count limit শুধু attempt count করে, fail হয়েছে কিনা তা না।
</details>

<details>
<summary><strong>Q2. একটা protected service-এর জন্য fail2ban-এর config-এর তিনটা অংশের নাম বলো।</strong></summary>

একটা jail (সেই service-এর জন্য on/off switch আর threshold), একটা filter (যে regex তার log format-এ একটা failed attempt চেনে), আর একটা action (একবার ban হলে একটা IP-র সাথে কী হয়)।
</details>

<details>
<summary><strong>Q3. <code>/etc/fail2ban/jail.conf</code> কখনো সরাসরি edit করা উচিত না কেন?</strong></summary>

এটা package-এর সাথে ship হয় আর প্রতিটা upgrade-এ overwrite হয়ে যায়, এতে করা যেকোনো change নিঃশব্দে বাতিল হয়ে যায়। Override করার জায়গা `jail.local`, যেটা package কখনো touch করে না।
</details>

<details>
<summary><strong>Q4. <code>maxretry = 5</code>, <code>findtime = 10m</code>, আর <code>bantime = 1h</code> দিয়ে, একটা IP কখন ban হয়, আর কতক্ষণের জন্য?</strong></summary>

একটা 10-মিনিটের window-এর মধ্যে 5টা failed attempt জমা হলেই এটা ban হয়, আর তারপর সেই ban 1 ঘণ্টা থাকে।
</details>

<details>
<summary><strong>Q5. তোমার নিজের server-এর বিরুদ্ধে real failed login generate করার তুলনায় <code>fail2ban-regex</code> কেন test করার একটা নিরাপদ উপায়?</strong></summary>

এটা existing log line-এর বিরুদ্ধে একটা filter-এর regex সম্পূর্ণ read-only উপায়ে check করে — এটা কখনো কোনো IP ban করে না বা firewall touch করে না, তাই test করার সময় তোমার নিজের connection ban হওয়ার কোনো risk থাকে না।
</details>

<details>
<summary><strong>Q6. <code>sshd</code>-এর মতো একটা normal jail থেকে <code>recidive</code> jail কীভাবে আলাদাভাবে কাজ করে?</strong></summary>

এটা একটা service log-এর বদলে fail2ban-এর নিজের ban log দেখে, আর যদি একটা IP আগে থেকেই অন্য jail দিয়ে বারবার ban হয়ে থাকে, সেটাকে আরও অনেক বেশি সময়ের জন্য আবার ban করে — পুনরাবৃত্তি করা আক্রমণকারীর জন্য একটা escalating response।
</details>

<details>
<summary><strong>Q7. Password login আগে থেকেই বন্ধ থাকলে, fail2ban-এর sshd jail কেন তখনও worth চালানো?</strong></summary>

Defense in depth, যদি কখনো SSH config setting accidentally revert হয়ে যায়, সাথে এটা এখনও একটা key-only server-এর বিরুদ্ধে invalid username probe করা scanning bot থেকে log noise আর server load কমায়।
</details>

<details>
<summary><strong>Q8. তোমার নিজের IP যদি তোমার server-এর fail2ban দিয়ে ban হয়ে যায় আর connect করার জন্য অন্য কোনো network না থাকে, ফিরে আসার typical উপায় কী?</strong></summary>

বেশিরভাগ VPS provider server-এর জন্য একটা web-based console বা VNC access দেয় যা SSH আর firewall সম্পূর্ণ bypass করে, ঠিক এই ধরনের lockout থেকে ফিরে আসার জন্যই।
</details>

## ১০. Free resources

- fail2ban: [official GitHub wiki](https://github.com/fail2ban/fail2ban/wiki)
- `man fail2ban-jail`, `man fail2ban-regex`, `man jail.conf`
- Ubuntu Server Documentation: [Security — fail2ban](https://ubuntu.com/server/docs/security-fail2ban)
- DigitalOcean: [How To Protect SSH with fail2ban](https://www.digitalocean.com/community/tutorials/how-to-protect-ssh-with-fail2ban)

---

**Next:** [Chapter 4 — Multi-factor SSH আর কে login try করতে পারবে সীমিত করা](04-mfa-and-limiting-who-logs-in.md)
