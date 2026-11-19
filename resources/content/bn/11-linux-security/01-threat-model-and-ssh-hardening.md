# Chapter 1 — Threat Model আর SSH Hardening: সদর দরজা লক করা

> **Course:** Linux Server Security & Secrets · **Chapter:** 1 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-security/01-threat-model-and-ssh-hardening)

> **Honesty note:** এই chapter-এর command গুলো লেখার সময় কোনো live server-এ চালানো হয়নি — তুমি যে connection দিয়ে ঢুকেছো, সেই connection-এর SSH setting বদলানো আসলে lock out হয়ে যাওয়ার মতো একটা কাজ, তাই এই chapter সেটা demonstrate না করে সাবধানে শেখায়। এখানে বলা প্রতিটা setting আর default সরাসরি official OpenSSH `sshd_config(5)` manual page আর Ubuntu-এর নিজের documentation থেকে নেওয়া (শেষে reference দেওয়া আছে)। Command গুলো সঠিক এবং ব্যবহারযোগ্য ধরে নিতে পারো, কিন্তু নিজের server-এ হাত দেওয়ার আগে Section 4-এর "দ্বিতীয় session খোলা রাখো" নিয়মটা সবসময় মানো।

Cloud course-এ তুমি firewall-এ port 22 খুলেছো আর password বা key দিয়ে login করেছো (Chapter 2), কিন্তু জিজ্ঞেস করা হয়নি যে port পুরো internet-এর জন্য খোলার *পর* কী হয়। উত্তর: মিনিটের মধ্যে, দিন নয়। এই Security course শুরু হচ্ছে সেই সত্যিটা মেনে নিয়ে, তারপর কোনো server-এর সবচেয়ে বেশি-value-এর target ঠিক করে দিয়ে — SSH login নিজেই।

## ১. Threat model: আসলে কী ঝুঁকিতে আছে

"Threat model" মানে শুধু: *কে* attack করতে পারে, *কীভাবে*, আর জিতলে *কী পায়*। একটা typical Laravel VPS-এর জন্য:

- **কে:** বেশিরভাগই automated bot, পুরো IPv4 address space scan করে খোলা port 22 খুঁজে বের করে — তোমাকে specifically target করা কোনো human না। একটা ছোট অংশ targeted attack, একবার তোমার domain বা company-র নাম জানা হয়ে গেলে।
- **কীভাবে:** brute-force বা credential-stuffing login attempt — সাধারণ username (`root`, `admin`, `ubuntu`) আর সাধারণ বা leaked password দিয়ে বারবার চেষ্টা, হাজার হাজার IP থেকে (প্রায়ই একটা botnet)।
- **জিতলে কী পায়:** typical একটা VPS-এ, SSH access মানে **root-equivalent access**, যে মুহূর্তে একটা sudo-capable account-এ পৌঁছে — তোমার database, তোমার `.env`-এর secret, প্রতিটা customer-এর data, আর এখন একটা machine যা দিয়ে সে অন্যদের attack করতে পারবে।

এটা কল্পনা নয়: নতুন provision করা একটা VPS-এ password SSH login internet-এর জন্য খোলা থাকলে, কয়েক ঘণ্টার মধ্যেই failed-login attempt দেখা যায়। Famous হতে হবে না বা valuable data থাকতে হবে না bot-কে চেষ্টা করানোর জন্য — scanning কাউকে বেছে করে না।

## ২. সবচেয়ে কার্যকর fix: password login সম্পূর্ণ বন্ধ করা

এই chapter-এর বাকি সবকিছুই কাজের। কিন্তু এটাই সবচেয়ে গুরুত্বপূর্ণ fix, কারণ এটা Section 1-এর পুরো attack category-টাই মুছে দেয়: password দিয়ে কখনো login করা না গেলে, brute-force করার মতো কিছুই থাকে না।

Cloud course (Chapter 2) থেকে তোমার কাছে আগে থেকেই একটা SSH key pair আছে — একটা **private key** যা তোমার laptop থেকে কখনো বের হয় না, আর একটা **public key** যা server-এর `~/.ssh/authorized_keys`-এ কপি করা ছিল। Key-based login কাজ করে এভাবে: server তোমার client-কে challenge করে, সেই public key-এর সাথে মিলে এমন private key তোমার কাছে আছে কিনা প্রমাণ করতে বলে; private key নিজে কখনো পাঠানো হয় না। এটা password থেকে সম্পূর্ণ আলাদা: একটা login attempt থেকে attacker চুরি করার মতো reusable কিছু নেই, আর brute force করে guess করার মতো fixed secret-ও নেই যথেষ্ট ছোট (একটা modern Ed25519 key guess করা computationally অসম্ভব)।

### ২.১ কিছুতে হাত দেওয়ার আগে key setup check করা

Password বন্ধ করার আগে, নিশ্চিত হও key login আগে থেকেই কাজ করে আর file permission ঠিক আছে — ভুল permission নিঃশব্দে key auth ভেঙে দেয়, আর এই সমস্যাটা password বন্ধ হওয়ার *আগে* ধরা পড়া দরকার, পরে না:

```bash
ls -la ~/.ssh/
```

```
drwx------  2 dev dev 4096 ... .ssh
-rw-------  1 dev dev  411 ... authorized_keys
```

OpenSSH `authorized_keys` ব্যবহার করতে রাজি হয় না permission বেশি খোলা থাকলে — এটা একটা ইচ্ছাকৃত safety check। Directory-টা `700` (`drwx------`) আর file-টা `600` (`-rw-------`) হতে হবে; আলাদা হলে ঠিক করো:

```bash
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

### ২.২ `sshd_config` edit করা

Server-এর SSH daemon কনফিগার হয় `/etc/ssh/sshd_config` দিয়ে (Ubuntu-তে, এটা `/etc/ssh/sshd_config.d/*.conf` থেকেও extra file পড়তে পারে, filename order-এ apply হয়; সাধারণত main file-এর setting পরে একই directive দেওয়া কিছুর উপর priority পায় — সন্দেহ হলে যে directive edit করছো সেটা দুই জায়গাতেই check করো)। Root privilege দিয়ে খোলো:

```bash
sudo nano /etc/ssh/sshd_config
```

সেট করো, না থাকলে যোগ করো:

```
PubkeyAuthentication yes
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
```

- **`PubkeyAuthentication yes`** — explicit, এটা default হলেও, যাতে file-এ intent স্পষ্ট থাকে।
- **`PasswordAuthentication no`** — আসল fix: password দিয়ে আর কোনো session শুরুই করা যাবে না।
- **`KbdInteractiveAuthentication no`** — keyboard-interactive method-টাও (PAM-based challenge/response) বন্ধ করে; কিছু distribution `PasswordAuthentication no` থাকলেও এই path দিয়ে password চালাতে দেয়, তাই দুটোই বন্ধ করতে হবে।
- **`PermitRootLogin no`** — কেউ কখনো সরাসরি `root` হয়ে SSH-এ login করবে না। একজন admin নিজের user হয়ে login করে, যা root লাগবে তার জন্য `sudo` ব্যবহার করে — এর মানে প্রতিটা privileged action log-এ একটা নামে চিহ্নিত ব্যক্তির সাথে যুক্ত থাকে, anonymous "root" না (Chapter 6-এ, least privilege-এ আরও আসবে)।

## ৩. Root login বন্ধ করা একা কেন গুরুত্বপূর্ণ

Password login আগে থেকেই বন্ধ থাকলেও, `PermitRootLogin no` আলাদাভাবে সেট করা worth, দুটো কারণে যেগুলো password-এর সাথে সম্পর্কিত না:

1. **`root` একটা known, fixed username।** পৃথিবীর প্রতিটা brute-force list সবার আগে এটা try করে। একটা normal admin account, যার username guess করার মতো না এবং publish করা না, প্রতিটা attacker আগে থেকেই try করতে জানে এমন নামের চেয়ে ছোট একটা target।
2. **Accountability।** তিনজন admin সবাই `ssh root@server` করলে, log-এ দেখাবে "root logged in" তিনবার, কে করেছে বোঝার কোনো উপায় নেই। যদি প্রত্যেকে নিজের user হয়ে login করে আর `sudo` করে, log ঠিক দেখাবে কে কী করেছে — কিছু investigate করার সময় এটা অপরিহার্য।

## ৪. নিজেকে lock out করা থেকে বাঁচানোর নিয়ম

**SSH service restart করার আগে, একটা দ্বিতীয় terminal খোলো আর নতুন একটা SSH session শুরু করো — আর সেটা খোলা রাখো।** নতুন setting-এ নতুন session test করে confirm না হওয়া পর্যন্ত বর্তমান, কাজ করা session বন্ধ করো না। এই পুরো course-এর সবচেয়ে গুরুত্বপূর্ণ operational habit এটাই: একটা `sshd_config`-এর typo বা test না করা একটা key তোমাকে একটা machine থেকে বের করে দিতে পারে, অন্য কোনো access path না থাকলে — দুই মিনিটের edit একটা support ticket হয়ে যায় তোমার VPS provider-এর কাছে (বা একটা reinstall)।

```bash
sudo sshd -t
```

`sshd -t` config file-টা syntax error-এর জন্য parse করে, কিছু apply না করেই exit করে — restart করার আগে এটা সবসময় চালাও। Clean run কিছু print না করে exit code 0 দেয়।

```bash
sudo systemctl restart ssh
```

এখন, **তোমার আগের খোলা first session-এ থাকতে থাকতেই**, একটা নতুন তৃতীয় terminal খোলো (বা দ্বিতীয়টা reuse করো) আর নতুনভাবে login করার চেষ্টা করো, একদম যেভাবে disconnect হয়ে গেলে করতে হবে:

```bash
ssh -v youruser@your-server-ip
```

`-v` (verbose) flag ঠিক দেখায় কোন authentication method offer করা হয়েছিল আর কোনটা সফল হয়েছে — confirm করো এটা দেখাচ্ছে `Authenticated using "publickey"`-এর মতো কিছু আর password prompt কখনো আসেনি। নতুন session ঢুকে কাজ করলে, তারপরই original session বন্ধ করো।

## ৫. SSH port বদলানো: এটা আসলে কী দেয়

একটা খুব common advice হলো SSH-কে port 22 থেকে 2222-এর মতো কিছুতে সরিয়ে নেওয়া। এটা আসলে কী করে আর কী করে না, honestly বলতে গেলে:

- **কী করে:** বিশাল volume-এর fully-automated, opportunistic scanner বাদ দিয়ে দেয়, যারা শুধু port 22 check করে। তোমার log quiet হয়ে যায়, real signal দেখা সহজ হয়।
- **কী করে না:** একটা targeted attacker-কে থামায় না। একটা port scan (`nmap -p-`, সব 65535 port scan করে) যে কোনো port-এ তোমার SSH service সেকেন্ডে খুঁজে পায়। এটা **security through obscurity** — একটা ছোট, real noise কমানো, real security boundary না। উপরের fix-গুলোর *পরে* করা worth, কখনো সেগুলোর *বদলে* না।

যদি বদলাও, একসাথে তিন জায়গা update করো, আর বন্ধ করার আগে Section 4-এর নিয়ম দিয়ে test করো: `sshd_config`-এর `Port` directive, তোমার firewall rule (এই course-এর Chapter 2) নতুন port allow করার জন্য 22-এর বদলে, আর তুমি যেখান থেকে connect করো সেখানে `-p` flag (বা `~/.ssh/config`-এর `Port` entry) প্রতিটা client-এ।

## ৬. *কে* login চেষ্টা করতে পারবে, সেটা সীমিত করা

`sshd_config`-এ `AllowUsers` আর `AllowGroups` root বন্ধ করার চেয়েও আরও এগিয়ে নিয়ে যায়: কোন account-গুলো SSH-এ authenticate করতে পারবে তা নির্দিষ্ট করে দেয়, বাকি সবাইকে password বা key check শুরু হওয়ার আগেই reject করে।

```
AllowUsers dev deploy
```

শুধু তালিকায় থাকা user SSH-এ ঢুকতে পারবে — একটা নতুন বা compromised local account (ধরো, box-এ চলা একটা vulnerable application তৈরি করা) SSH দিয়ে ব্যবহার করা যাবে না, এমনকি কোনোভাবে একটা password বা key সেট হয়ে গেলেও। `AllowGroups` একই কাজ করে individual username-এর বদলে Unix group দিয়ে, যা একাধিক admin থাকলে ভালোভাবে scale করে।

## ৭. নিজের server-এর বিরুদ্ধে attempt দেখা

দুটো read-only command দেখায় আসলে কী ঘটছে, কোনো special install লাগে না:

```bash
sudo journalctl -u ssh --since "1 hour ago" | grep -i "fail\|invalid"
```

```
sshd[2041]: Invalid user admin from 203.0.113.44 port 51022
sshd[2055]: Failed password for root from 198.51.100.9 port 44110
```

(কিছু distribution-এ unit-এর নাম `ssh`-এর বদলে `sshd` — `systemctl status ssh sshd` দেখায় তোমার সার্ভারে আসলে কোনটা active।) এখানকার প্রতিটা line ঠিক Section 1-এর threat model বাস্তব রূপে: অচেনা IP `admin` আর `root` try করছে। এই chapter-এর fix apply করার পর, `Failed password` line আর আসবেই না — fail করার মতো কোনো password path বাকি থাকে না — আর `AllowUsers` list-এর বাইরে থাকা কিছুর বিরুদ্ধে `Invalid user` line, authentication চেষ্টা হওয়ার আগেই reject হয়ে যায়।

## ৮. মূল কথা

- প্রতিটা internet-facing SSH port ধরে নাও আগে থেকেই scan হচ্ছে — প্রশ্নটা হলো তোমার server প্রস্তুত কিনা, এটা পাওয়া যাবে কিনা তা নয়।
- **`PasswordAuthentication no`** (সাথে `KbdInteractiveAuthentication no`) brute-force login-কে একটা পুরো attack category হিসেবেই বাদ দেয় — এই chapter-এর সবচেয়ে বেশি-value-এর একটা setting।
- **`PermitRootLogin no`** প্রতিটা attacker আগে থেকেই চেষ্টা করতে জানে এমন একটা username বাদ দেয়, আর `sudo` দিয়ে প্রতিটা privileged action একজন real person-এর সাথে attributable করে।
- **সবসময়** একটা কাজ করা session খোলা রাখো, `sshd -t` চালাও, আর আগের session বন্ধ করার আগে একটা নতুন session-এ fresh login test করো — এটাই নিজেকে lock out হওয়া থেকে বাঁচায়।
- SSH port বদলানো automated noise কমায়; উপরের fix-গুলোর substitute না।
- `AllowUsers` / `AllowGroups` সীমিত করে *কোন account* SSH authentication চেষ্টাই করতে পারবে।

## ৯. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. Password authentication বন্ধ করা brute-force SSH attack-কে threat হিসেবে কেন বাদ দেয়?</strong></summary>

Brute-forcing একটা password guess করার উপর নির্ভর করে। Authenticate করতে কখনো কোনো password ব্যবহার করা না গেলে, guess করার মতো কিছু থাকে না — পুরো attack category-টাই আর প্রযোজ্য না, কোনো account-এর পুরনো password যতই দুর্বল হোক।
</details>

<details>
<summary><strong>Q2. <code>PasswordAuthentication no</code>-এর সাথে <code>KbdInteractiveAuthentication no</code> কেন সেট করতে হয়?</strong></summary>

কিছু system password authentication বন্ধ থাকলেও keyboard-interactive (PAM challenge/response) method দিয়ে password accept করতে পারে। Password path পুরোপুরি বন্ধ করতে দুটোই off থাকতে হবে।
</details>

<details>
<summary><strong>Q3. Password আগে থেকেই বন্ধ থাকলেও <code>PermitRootLogin no</code> সেট করা worth হওয়ার দুটো আলাদা কারণ দাও।</strong></summary>

(১) `root` একটা fixed, known username যা প্রতিটা attacker আগে থেকেই try করে, তাই এটা valid login হিসেবে বাদ দিলে target ছোট হয়ে যায়। (২) এটা admin-দের নিজের user হয়ে login করে `sudo` ব্যবহার করতে বাধ্য করে, তাই privileged action log-এ একজন real person-এর সাথে attributable থাকে, anonymous "root" না।
</details>

<details>
<summary><strong>Q4. <code>sshd_config</code> বদলানোর পর SSH service restart করার আগে কী করতে হবে, আর কেন?</strong></summary>

তোমার বর্তমান, কাজ করা SSH session খোলা রাখো, আর নতুন একটা session থেকে প্রথমে নতুন config test করো। নতুন config ভাঙা হলে, তোমার কাছে original session থাকে সেটা থেকে ঠিক করার জন্য — আগে বন্ধ করলে কোনো access path ছাড়াই lock out হতে পারো।
</details>

<details>
<summary><strong>Q5. <code>sshd -t</code> কী করে?</strong></summary>

এটা `sshd_config` file-টা syntax error-এর জন্য check করে, কিছু apply না করে exit করে — real service restart করার আগে একটা typo ধরার নিরাপদ উপায়।
</details>

<details>
<summary><strong>Q6. SSH-কে non-default port-এ সরানো কি একটা targeted attacker-কে থামায়? কেন বা কেন না?</strong></summary>

না। একটা full port scan সেকেন্ডের মধ্যে যে কোনো port-এ listening SSH service খুঁজে পায়। এটা শুধু automated scanner-দের noise কমায় যারা শুধু port 22 check করে — এটা নিজে কোনো real security boundary না।
</details>

<details>
<summary><strong>Q7. <code>sshd_config</code>-এ <code>AllowUsers dev deploy</code> কী করে?</strong></summary>

শুধু `dev` আর `deploy` account SSH-এ authenticate করার permission পায়; অন্য কোনো account — valid key বা password থাকলেও — authentication চেষ্টা হওয়ার আগেই reject হয়।
</details>

<details>
<summary><strong>Q8. এই chapter-এর fix apply করার পর <code>journalctl -u ssh</code>-এ failed password attempt-এর জন্য তুমি কী expect করবে, আর কেন?</strong></summary>

একদমই কোনো `Failed password` line আসবে না — `PasswordAuthentication no` সেট হয়ে গেলে, কোনো password authentication path বাকি থাকে না যার উপর একটা attempt fail করতে পারে। অচেনা username-এর জন্য `Invalid user` line তখনও আসতে পারে, এখন `AllowUsers` list-এর বাইরে হলে আরও আগেই reject হয়ে যায়।
</details>

## ১০. Free resources

- OpenSSH: [`sshd_config(5)` manual page](https://man.openbsd.org/sshd_config)
- Ubuntu Server Documentation: [OpenSSH](https://ubuntu.com/server/docs/openssh-server)
- DigitalOcean: [SSH Essentials: Working with SSH Servers, Clients, and Keys](https://www.digitalocean.com/community/tutorials/ssh-essentials-working-with-ssh-servers-clients-and-keys)
- `man sshd_config`, `man journalctl`

---

**Next:** [Chapter 2 — Firewall: UFW আর nftables, ঠিকভাবে default-deny](02-firewalls-ufw-and-nftables.md)
