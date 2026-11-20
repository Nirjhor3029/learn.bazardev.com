# Chapter 8 — Real Project: একটা Laravel Production Server End to End Harden করা

> **Course:** Linux Server Security & Secrets · **Chapter:** 8 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-security/08-real-project-hardening-a-laravel-server)

> **Honesty note:** এই chapter কোনো hypothetical app ব্যবহার করে না — এটা Chapter 7-এর checklist **এই course-এর নিজের learn.bazardev.com**-এর বিরুদ্ধে চালায়, এই course-এর নিজের writing server-এ একটা real, publicly-reachable production Laravel app। Section 2-এর প্রতিটা permission, ownership, আর config value আসলেই পড়া হয়েছে, read-only, এই chapter লেখার মুহূর্তের আগে — একটা real finding-সহ যা শুরুতে expected ছিল না। এখন পর্যন্ত প্রতিটা chapter-এর মতোই, কোনো `chmod`/`chown`/`sudoers`/SSH/firewall *write* command এটা লিখতে চালানো হয়নি — নিচের একমাত্র real finding report করা হয়েছে আর fix দেওয়া হয়েছে, apply করা হয়নি, একই lockout-risk কারণে, সবসময়ের মতো। SSH, firewall, fail2ban, আর MFA item (Chapter 1-4) এই run-এ re-verify করা হয়নি একই কারণে — ঠিক কোনটা live check করা হয়েছে আর কোনটা হয়নি তা Section 2-এ দেখো।

সাত chapter প্রতিটা একটা করে অংশ harden বা audit করেছে: SSH, firewall, fail2ban, MFA, secrets, permission, আর সব আবার check করার audit tooling। এই chapter যা একজন real admin আসলে করে তা করে: একটা real, চলমান app-এর বিরুদ্ধে Chapter 7-এর checklist চালায়, আসলে কী true তা খুঁজে পায়, আর যা fix করা লাগে তা fix করে — বা এখানে আসলে fix করলে এই shared writing server-এর risk হবে বলে কী লাগবে তা plainly বলে।

## ১. Setup: একটা real app, hypothetical না

![Chapter 7-এর checklist আসলে একটা real app-এর বিরুদ্ধে চালানো: বেশিরভাগ item pass করে, একটা real finding surface করে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/11-linux-security/images/capstone-checklist-run.svg)

`learn.bazardev.com` — এই exact course serve করা Laravel app, যে chapter তুমি এখন পড়ছো সেটাও — subject। এটা একটা real, deployed, publicly reachable Laravel application, একই VPS-এ যেখান থেকে এই পুরো course Chapter 2–7-এ honest `ls`, `umask`, `ss`, আর log output নিয়েছে। এর বিরুদ্ধে Chapter 7-এর checklist চালানো মানে প্রতিটা item ধরে প্রশ্ন করা, "এটা এখন আসলে true?" — "একটা textbook example-এ এটা true হতো?" না।

## ২. learn.bazardev.com-এর বিরুদ্ধে Chapter 7-এর checklist চালানো, item by item

**Secrets (Chapter 5) — live verify করা, pass করে:**

```bash
grep -n "^APP_DEBUG" .env
```

```
4:APP_DEBUG=false
```

Production debug mode সঠিকভাবে off — Chapter 5-এর `APP_DEBUG=true` debug error page-এর মাধ্যমে secret leak করার exact warning এখানে apply করে না।

```bash
git check-ignore -v .env
git log --oneline -- .env
```

```
.gitignore:3:.env	.env
```

`.env` gitignored, আর সেই path-এর জন্য একটা empty `git log` confirm করে এটা কখনো একবারও commit হয়নি — Chapter 5-এর "git history-তে secret permanent" risk genuinely এই specific repo-র এই specific file-এর জন্য apply করে না।

**Least privilege (Chapter 6) — live verify করা, বেশিরভাগ pass করে, একটা real finding-সহ:**

```bash
stat -c "%A %U:%G %n" storage bootstrap/cache
```

```
drwxrwxr-x dev:www-data storage
drwxrwxr-x dev:www-data bootstrap/cache
```

এটা Chapter 6 Section 7-এর ঠিক recommended pattern, genuinely এখানে আছে: `775`, deploy account (`dev`)-এর মালিকানায় আর web server-এর group (`www-data`)-এর সাথে group-shared — `chmod -R 777` না, ভাঙা permission না। এই item pass করে।

```bash
stat -c "%A %U:%G %n" .env
```

```
-rw-rw-r-x dev:dev .env
```

এটা real, unexpected finding। `.env` `664` plus "other"-এর জন্য একটা execute bit (`rw-rw-r-x`) — যার মানে **এই shared box-এর যেকোনো অন্য account এই file পড়তে পারে**, execute-bit-এর অস্বাভাবিকতা ছাড়াও। Chapter 5 ঠিক এই কারণে `chmod 600` recommend করেছিল (environment file database password, API key, app key রাখে) আর সেই recommendation এই specific file-এ কখনো আসলে apply করা হয়নি। এটাই Chapter 7-এর checklist যা ধরতে exist করে: একটা setting যা theory-তে সঠিক ছিল (Chapter 5 তাই বলেছিল) কিন্তু এই chapter check চালানোর আগে real file-এর বিরুদ্ধে কখনো আসলে verify করা হয়নি।

**Fix, দেওয়া হলো কিন্তু এখানে apply করা হয়নি (এখন পর্যন্ত প্রতিটা chapter-এর একই reasoning — এই shared writing server-এর বিরুদ্ধে কোনো write command না):**

```bash
chmod 600 .env
```

`600` মানে শুধু file-এর owner এটা পড়তে বা লিখতে পারে — group না, other না। একটা real production server-এ যেটা এই course লেখার box না, এটা একটা one-line fix, পাওয়ার সাথে সাথেই apply করা, আর কোনো discussion লাগে না।

**Auditing (Chapter 7) — সেই chapter-এই আগে live verify করা, এখানে carried forward:**
- `unattended-upgrades`: `enabled` আর `active` confirm করা, একটা real log entry-সহ যা দেখায় সেই একই সকালে পাঁচটা package patch হয়েছে।
- `ss -tulpn`: MySQL loopback-এর বেশি কিছুতে listen করছে confirm করা — firewall-এর বিরুদ্ধে check করা worth একটা fact, নিজে একটা failure না।

**SSH, firewall, fail2ban, MFA (Chapter 1-4) — এই run-এ re-verify করা হয়নি:** এগুলো আসলে check করা মানে `/etc/ssh/sshd_config`, `ufw status`, `fail2ban-client status`, বা PAM config `sudo` দিয়ে পড়া, বা এই course লেখা হচ্ছে যে একই box-এ একটা live SSH/firewall change-এর risk নেওয়া — ওই chapter-গুলোর নিজের honesty note-এ flag করা একই lockout risk। এটা এই specific checklist run-এর একটা genuine, honest gap, ওই item গুলো ঠিক আছে এমন কোনো claim না — একটা real server-এ, একজন real admin এই check-গুলোও real-ভাবে চালায়, একটা দ্বিতীয় open session থেকে, Chapter 1 SSH config touch করার আগে যেমন recommend করেছিল।

## ৩. কেন ঠিক এই ধরনের finding-ই checklist-এর purpose

`.env` permission finding ছোট, boring, আর ঠিক point-টাই। কেউ *decide* করেনি `.env`-কে world-readable বানাতে — এটা almost certainly শুধু default `umask` (Chapter 6, Section 6) যা file তৈরি বা শেষ edit হওয়ার সময় apply হয়েছে, কারো specifically এর permission check না করেই। এটাই Chapter 7-এর checklist-কে একটা one-time setup step না, একটা *recurring* practice হিসেবে exist করার পুরো argument: configuration নিঃশব্দে drift করে, Chapter 5-এ করা একটা recommendation নিজেকে enforce করে না, আর একটা setting এখনও সঠিক আছে কিনা জানার একমাত্র উপায় আসলে পরেও আবার check করা — এটাই ঠিক ঘটলো এখানে, real, এই chapter লেখার সময়।

## ৪. Consolidated runbook, আরেকবার, একটা real app-এ mapped

এটা Chapter 7-এর checklist, মূলত একই, একজন admin একটা real Laravel deployment-এর পাশে রাখবে এমন actual runbook হিসেবে লেখা, periodically re-run করার জন্য:

- [ ] `sshd -t` pass করে; `PasswordAuthentication no`, `PermitRootLogin no`; `AllowUsers`/`AllowGroups` এখনও current staff-এর সাথে match করে।
- [ ] `ufw status verbose` / `nft list ruleset` default-deny incoming দেখায়; কোনো surprise Docker-published port নেই।
- [ ] `fail2ban-client status sshd` jail active দেখায়; override `jail.local`-এ থাকে, `jail.conf`-এ কখনো না।
- [ ] `AuthenticationMethods publickey,keyboard-interactive` এখনও set; CI/CD-এর `Match User` exemption ঠিক তার deploy key-তে scoped।
- [ ] `grep APP_DEBUG .env` production-এ `false`; `git log -- .env` empty; `.env` `600`।
- [ ] `stat storage bootstrap/cache` group ownership + `775` + setgid দেখায়, কখনো `777` না; `sudoers.d` rule narrow, কোনো `NOPASSWD: ALL` নেই।
- [ ] `lynis audit system` সম্প্রতি চালানো, finding review করা; `unattended-upgrades` active; `ss -tulpn` firewall-এর বিরুদ্ধে review করা।

সাতটা checklist item, সাতটা chapter, একটা real app, একটা real finding — আগের একটা recommendation টিকে ছিল বলে trust করার বদলে আসলে চালিয়ে ধরা।

## ৫. মূল কথা

- একটা hardening checklist-এর value ঠিক তখন দেখা যায় যখন এটা একটা **real**, আগে থেকে deploy করা app-এর বিরুদ্ধে চালানো হয়, নতুন example না — learn.bazardev.com-এর বিরুদ্ধে এই run একটা genuine gap (.env অন্য account-এর জন্য readable) খুঁজে পেয়েছে যা একটা one-time setup pass miss করে গিয়েছিল।
- Secrets (Chapter 5) আর least-privilege (Chapter 6) item এখানে কোনো risk ছাড়াই verify করা গিয়েছে, কারণ এগুলোর শুধু এই session-এর মালিকানায় থাকা file পড়া লাগতো — SSH/firewall/fail2ban/MFA item-এর `sudo` লাগতো বা lockout risk বহন করতো, আর honestly "এই run-এ re-verify করা হয়নি" রাখা হয়েছে, ঠিক আছে ধরে নেওয়া হয়নি।
- একটা setting *theory-তে সঠিক* হওয়া (একটা আগের chapter-এ করা recommendation) আর এটা *practice-এ verified* হওয়া একই না — ঠিক এই gap-টাই একটা recurring audit ধরে।
- `chmod 600 .env` এই chapter-এর একমাত্র real finding-এর fix — ছোট, specific, আর ঠিক Chapter 6 একটা broad, lazy `chmod -R 777`-স্টাইল fix-এর বদলে যা argue করেছিল।
- এই capstone-এর প্রতিটা অংশ একটা আগের chapter-এর নামে-দেওয়া callback, একটা real app-এর বিরুদ্ধে real-ভাবে চালানো — এই chapter কোনো নতুন concept introduce করে না, শুধু আসলে check করার discipline।

## ৬. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. learn.bazardev.com-এর বিরুদ্ধে এই chapter-এর check চালিয়ে আসলে কী real finding surface হলো?</strong></summary>

.env-এর permission ছিল 664 plus "other"-এর জন্য একটা execute bit (rw-rw-r-x), মানে এই shared box-এর যেকোনো অন্য account এটা পড়তে পারতো - যদিও Chapter 5 আগে থেকেই ঠিক এই ধরনের file-এর জন্য chmod 600 recommend করেছিল।
</details>

<details>
<summary><strong>Q2. .env permission finding এই chapter-এ সাথে সাথে fix করা হলো না কেন?</strong></summary>

আগের প্রতিটা chapter-এর honesty note-এর সাথে consistent, কোনো chmod/chown/sudoers/SSH/firewall write command এই course-এর নিজের shared writing server-এর বিরুদ্ধে চালানো হয়নি, কোনো lockout বা disruption risk এড়াতে - fix (chmod 600 .env) দেওয়া হয়েছে, কিন্তু এখানে apply না করে report করা হয়েছে।
</details>

<details>
<summary><strong>Q3. Secrets (Ch5) আর least-privilege (Ch6) checklist item এই chapter-এ live verify করা গেলো, কিন্তু SSH/firewall/fail2ban/MFA (Ch1-4) item না কেন?</strong></summary>

প্রথমগুলোর শুধু এই session-এর মালিকানায় থাকা file পড়া লাগতো (.env, storage/, bootstrap/cache/) - কোনো elevated access লাগতো না। শেষগুলোর real-ভাবে check করার জন্য sudo লাগে (sshd_config, ufw status, fail2ban-client status, PAM config পড়া), বা একই box-এ SSH/firewall-এ একটা live change-এর risk নেয় - এই course-এ জুড়ে flag করা একই lockout risk।
</details>

<details>
<summary><strong>Q4. এই chapter কেন .env finding-কে Chapter 5-এর একটা failure না বলে "ঠিক checklist-এর purpose" বলে?</strong></summary>

Chapter 5-এর recommendation (chmod 600) theory-তে সঠিক ছিল, কিন্তু পরে কিছুই এটা enforce করেনি - configuration সময়ের সাথে নিঃশব্দে drift করে (সম্ভবত file তৈরির সময় umask default থেকে), আর একটা recommendation এখনও টিকে আছে কিনা জানার একমাত্র উপায় আসলে পরে আবার check করা, যা ঠিক একটা recurring audit (Chapter 7)-এর জন্য।
</details>

<details>
<summary><strong>Q5. এই chapter-এ SSH/firewall/fail2ban/MFA checklist item-এর জন্য "এই run-এ re-verify করা হয়নি" মানে কী, আর একজন real admin কী আলাদা করা উচিত?</strong></summary>

মানে checklist-এর এই specific run honestly ওই item-গুলোর জন্য incomplete - এটা কোনো claim না যে এগুলো ঠিক আছে। একজন real admin তার নিজের server-এ এই একই checklist চালালে ওই item-গুলো real-ভাবেই check করতো, ideally একটা দ্বিতীয় open session থেকে Chapter 1 recommend করা মতো SSH config touch করার আগে, skip না করে।
</details>

## ৭. Free resources

- Laravel Documentation: [Configuration — environment configuration](https://laravel.com/docs/configuration#environment-configuration)
- `man chmod`, `man stat`
- এই course-এর Chapter 1-7-এ আগে থেকে link করা প্রতিটা official resource

---

**Course সম্পূর্ণ।** Certificate পাওয়ার জন্য website-এ course exam দাও।
