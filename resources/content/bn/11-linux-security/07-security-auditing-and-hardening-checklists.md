# Chapter 7 — Security Auditing: Lynis, unattended-upgrades, আর একটা Hardening Checklist

> **Course:** Linux Server Security & Secrets · **Chapter:** 7 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-security/07-security-auditing-and-hardening-checklists)

> **Honesty note:** Section 3-এর `unattended-upgrades` configuration, service status, আর log line, আর Section 4-এর `ss -tulpn` output আসলেই চালানো হয়েছে, read-only, এই course-এর নিজের writing server-এর বিরুদ্ধে — সেই output real, Section 4-এর একটা genuine finding-সহ (MySQL loopback-এর বেশি কিছুতে listen করছে)। Lynis লেখার সময় এই server-এ install করা ছিল **না়**, তাই Section 2-এর command আর sample output Lynis-এর নিজের official documentation থেকে নেওয়া, এখানে real run না — clearly সেভাবেই marked। কোনো package install করা হয়নি আর কোনো configuration বদলানো হয়নি এই chapter লিখতে।

Chapter 1–6 প্রতিটা কিছু *harden* করেছে — SSH, firewall, repeat offender, login factor, secret, আর file permission। এই chapter একটা আলাদা প্রশ্ন করে: **তুমি কীভাবে জানবে তোমার hardening আসলেই ধরে রেখেছে, আজ, আর তুমি মনে না রাখলেও সামনের মাসেও ধরে রাখবে?** Auditing আর automatic patching হলো যা একটা one-time hardening effort-কে এমন কিছুতে বদলায় যা সময়, নতুন package, আর নতুন ভুলের মধ্যেও টিকে থাকে।

## ১. কেন একটা one-time hardening pass যথেষ্ট না

Day one-এ সঠিকভাবে configure করা একটা server automatically সেভাবে থাকে না। নতুন package install হয় আর নতুন port খোলে। একটা config file অসম্পর্কিত কারণে edit হয় আর নিঃশব্দে Chapter 1-এর একটা setting revert করে দেয়। Kernel বা OpenSSH-এর জন্য একটা security patch upstream-এ release হয়, আর কেউ এটা apply না করলে, server একটা now-public issue-এর কাছে অনির্দিষ্টকাল exposed থাকে। Auditing হলো periodically Chapter 1–6-এর established state recheck করার practice; automatic patching হলো যা "একটা fix exist করে" আর "এই server-এ এটা আছে"-এর মধ্যে gap বন্ধ করে, কোনো human মনে রাখা না লাগিয়েই।

## ২. Lynis: একটা automated hardening audit tool

**Lynis** হলো CISOfy maintain করা একটা well-known open-source security auditing tool Linux-এর জন্য, যা শত শত check-এর বিরুদ্ধে একটা system inspect করে — SSH config, firewall state, installed package, kernel parameter, file permission — আর suggestion-সহ finding report করে। এর install আর run command, Lynis-এর নিজের documentation থেকে:

```bash
sudo apt install lynis
sudo lynis audit system
```

Lynis তার finding-কে group করে **warning** (সম্ভবত real সমস্যা), **suggestion** (consider করা worth, অবশ্যই ভুল না), আর একটা final **hardening index** — overall posture summarize করা একটা 0–100 score। Chapter 1–4 এর মধ্য দিয়ে যাওয়া একটা server-এর জন্য একটা typical finding এরকম দেখতে পারে:

```
[+] SSH Support
------------------------------------
    - OpenSSH option: PermitRootLogin                          [ SUGGESTION ]
      Set PermitRootLogin to 'without-password' or 'no'

Hardening index : 72 [###############     ]
```

সেই specific suggestion Chapter 1-এর `PermitRootLogin no` দিয়ে আগে থেকেই satisfied হবে — এটাই point: Lynis Chapter 1–6-এর কেন-এর understanding replace করে না, এটা recheck করে যে তুমি আগে থেকে যা setup করেছো তা এখনও আসলে আছে, আর এই course এখনও cover করেনি এমন কিছু surface করে (kernel hardening parameter, কম-common service, package-level check)।

## ৩. unattended-upgrades: মনে রাখা না লাগিয়ে patch করা

একটা schedule-এ manually `apt upgrade` চালানো আর মনে রাখার আশা করার বদলে, Ubuntu specifically automatic security patching-এর জন্য একটা package ship করে। এই course-এর নিজের writing server-এ, এটা আগে থেকেই install করা আর active — একটা real, বর্তমান check, hypothetical না:

```bash
systemctl is-enabled unattended-upgrades
systemctl is-active unattended-upgrades
```

```
enabled
active
```

এর behavior দুটো file নিয়ন্ত্রণ করে। প্রথমটা, এটা আসলে চলে কিনা:

```bash
cat /etc/apt/apt.conf.d/20auto-upgrades
```

```
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
```

দ্বিতীয়টা, এটা *কী* upgrade করতে allowed — default-এ, শুধু security update, available সব package update না:

```
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}";
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};
```

সেই scoping গুরুত্বপূর্ণ: এটা known security issue automatically patch করে, কোনো application যার উপর নির্ভর করে তার একটা major version bump নিঃশব্দে pull না করে। আর এটা theoretical না — এই course-এর নিজের writing server-এর real log দেখায় এটা নিজে নিজে চলেছে, আজ সকালে:

```bash
tail -5 /var/log/unattended-upgrades/unattended-upgrades.log
```

```
2026-11-20 06:02:09,559 INFO Initial blacklist:
2026-11-20 06:02:09,560 INFO Initial whitelist (not strict):
2026-11-20 06:02:25,248 INFO Packages that will be upgraded: cloud-init cloud-init-base libgstreamer1.0-0 openjdk-17-jdk-headless openjdk-17-jre-headless
2026-11-20 06:02:25,248 INFO Writing dpkg log to /var/log/unattended-upgrades/unattended-upgrades-dpkg.log
2026-11-20 06:03:43,242 INFO All upgrades installed
```

এটা ঘটার জন্য কারো login করে `apt upgrade` চালাতে হয়নি। জানার মতো একটা gap: একটা kernel upgrade effect নেওয়ার জন্য সাধারণত একটা reboot লাগে, আর `unattended-upgrades` default-এ তোমার জন্য server reboot করে না — একটা আলাদা setting (`Unattended-Upgrade::Automatic-Reboot`) তা নিয়ন্ত্রণ করে, আর এটা opt-in করার একটা explicit choice, default না, কারণ একটা production server-এর unattended reboot-এর নিজের risk আছে।

## ৪. আসলে কী listen করছে audit করা: ss, আর একটা real finding

Lynis আর unattended-upgrades config আর patch handle করে; periodically নিজেকে একটা সহজ প্রশ্ন করাও সমান গুরুত্বপূর্ণ: **এখন আসলে কী reachable?** `ss` (socket statistics) machine-এর প্রতিটা listening socket list করে:

```bash
ss -tulpn
```

এই course-এর নিজের writing server-এর বিরুদ্ধে এটা চালালে একটা real, জানার মতো line surface করে:

```
tcp   LISTEN 0   300   172.16.0.99:3306   0.0.0.0:*
tcp   LISTEN 0   300   127.0.0.1:3306     0.0.0.0:*
```

MySQL (port 3306) এখানে **দুটো** address-এ listen করছে: `127.0.0.1` (loopback — শুধু এই একই machine থেকে reachable, safe default) *আর* `172.16.0.99`, একটা private network interface address। সেই দ্বিতীয় line মানে MySQL অন্য machine থেকে reachable, শুধু এই একটা থেকে না — অবশ্যই ভুল না একটা server-এ যার genuinely অন্য host থেকে database access লাগে, কিন্তু ঠিক যে ধরনের fact একটা audit explicitly surface করা উচিত, কোনোভাবে assume করে রেখে দেওয়া না। এখানেই Chapter 2-এর firewall ফিরে আসে: non-loopback address-এ bound একটা service-ও শুধু firewall যতটা allow করে তত reachable, এই কারণেই একটা real audit-এ `ufw status verbose` বা `nft list ruleset` ঠিক `ss -tulpn`-এর পাশে থাকা উচিত — binding আর firewalling দুটো আলাদা layer, আর একটা audit দুটোই check করা উচিত, একটাকেই trust করা না।

## ৫. একটা consolidated hardening checklist

![Harden, audit, patch, repeat-এর একটা repeating cycle - আগের প্রতিটা chapter-এর সাথে জড়ানো একটা checklist-সহ](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/11-linux-security/images/audit-and-patch-cycle.svg)

Chapter 1–6 একসাথে একটা re-checkable list-এ নিয়ে আসা — একটা audit আসলে chapter by chapter এটাই re-verify করে:

- [ ] **SSH (Ch 1):** `PasswordAuthentication no`, `PermitRootLogin no`, `sshd -t` pass করে, `AllowUsers`/`AllowGroups` এখনও আসলে যার access থাকা উচিত তার সাথে match করে।
- [ ] **Firewall (Ch 2):** `ufw status verbose` default-deny incoming দেখায়; কোনো Docker-published port অপ্রত্যাশিতভাবে এটা bypass করে না; `nft list ruleset` তুমি যা expect করো তার সাথে match করে।
- [ ] **fail2ban (Ch 3):** `fail2ban-client status sshd` jail active দেখায়; `jail.local` (`jail.conf` না) তোমার override রাখে; repeat offender-দের জন্য `recidive` jail enabled।
- [ ] **MFA (Ch 4):** interactive user-দের জন্য `AuthenticationMethods publickey,keyboard-interactive` এখনও set; CI/CD `Match User` exemption ঠিক যে deploy key-তে লাগে তাতে scoped, বেশি wide না।
- [ ] **Secrets (Ch 5):** এই repo-তে কোনো secret কখনো commit হয়নি (spot-check `git log -p`-style search দিয়ে বা `gitleaks`-এর মতো একটা tool দিয়ে); production-এ `APP_DEBUG=false`; `.env` rotation-এর পর `config:cache` আবার চালানো হয়েছে।
- [ ] **Least privilege (Ch 6):** কোনো directory `chmod 777` দিয়ে fix করা হয়নি; `sudoers.d` rule narrowly scoped, কোনো stray `NOPASSWD: ALL` নেই; service account-দের `/usr/sbin/nologin`।
- [ ] **এই chapter:** `lynis audit system` সম্প্রতি চালানো হয়েছে, warning review করা হয়েছে (সব "fix" করা না necessarily — Section 6 দেখো); `unattended-upgrades` enabled আর active; `ss -tulpn` আসলে reachable হওয়া উচিত তার বিরুদ্ধে review করা হয়েছে।

## ৬. Audit output পড়া: একটা perfect score chase করো না

একটা Lynis hardening index 100 goal না, আর একটা chase করা একটা real server-কে actively ক্ষতি করতে পারে। কিছু suggestion security-কে তুমি genuinely যা লাগে তার সাথে trade করে — Lynis unused বলে flag করা একটা service disable করা কেউ নির্ভর করা একটা feature ভেঙে দিতে পারে; একটা suggestion একটা generic server assume করে, তোমার *server*-এর actual constraint না। সঠিক habit হলো প্রতিটা warning আর suggestion পড়া, কেন Lynis এটা তুলেছে তা বোঝা (এর report প্রতিটা finding-এর reasoning explain করে, আর further reading link করে), আর একটা deliberate decision নেওয়া — এটা fix করো, বা explicitly risk accept করে এগিয়ে যাও — mechanically প্রতিটা suggestion apply করার বদলে। এটা Chapter 6-এর `NOPASSWD` trade-off-এর একই judgment call: একটা checklist thinking-এর জন্য একটা tool, thinking-এর replacement না।

## ৭. Audit-কে একটা schedule-এ রাখা

একটা security audit drift ধরে তখনই যখন এটা আসলে পরেও আবার চলে, এই chapter লেখার সময় একবার না। `cron` (Chapter 1-এর `linux-shell` prerequisite এটা cover করে) একটা recurring Lynis run schedule করার সহজ উপায়:

```bash
# /etc/cron.d/lynis-weekly
0 3 * * 1 root lynis audit system --cronjob > /var/log/lynis-report.log 2>&1
```

`--cronjob` Lynis-এর interactive prompt suppress করে, কারণ উত্তর দেওয়ার জন্য কিছুই নেই। এটাকে এই course-এর নিজের Monitoring & Logging course (`07-monitoring`)-এর সাথে জুড়লে loop বন্ধ হয়: `/var/log/lynis-report.log` সেই course-এর Chapter 6 যেখানে log পাঠায় সেখানে পাঠাও, আর alert করো (সেই course-এর Chapter 6 আবার) hardening index গত সপ্তাহের তুলনায় কমলে — একটা manual "মনে রাখো check করার" কাজটাকে এমন কিছুতে বদলে দেয় যা posture regress করলে automatically কাউকে page করে।

## ৮. মূল কথা

- **Lynis** (`lynis audit system`) শত শত hardening point automatically recheck করে আর একটা hardening index দিয়ে overall posture score করে — Chapter 1–6 re-verify করার একটা tool, এগুলো বোঝার replacement না।
- **unattended-upgrades** security update automatically একটা schedule-এ patch করে, `Allowed-Origins` দিয়ে শুধু security-relevant repo-তে scoped — এই course-এর নিজের server-এ enabled, active, আর genuinely চলছে বলে live verify করা।
- একটা kernel security update effect নেওয়ার জন্য তখনও একটা **reboot** লাগে; `unattended-upgrades` explicitly configure না করা থাকলে automatically reboot করে না।
- `ss -tulpn` আসলে কী listen করছে দেখায়, আর binding আর firewalling দুটো আলাদা layer যা দুটোই check করা লাগে — একটা non-loopback address-এ একটা service শুধু firewall (Chapter 2) যতটা allow করে তত reachable।
- আগের প্রতিটা chapter (SSH, firewall, fail2ban, MFA, secrets, least privilege) revisit করা একটা hardening **checklist** ছয়টা chapter-এর one-time কাজকে কয়েক মিনিটে re-checkable কিছুতে বদলায়।
- একটা perfect audit score chase করো না — প্রতিটা finding কেন তুলেছে তা পড়ো আর একটা deliberate call নাও; কিছু suggestion তোমার specific server genuinely যা লাগে তা trade করে দেয়।
- Audit নিজেকে schedule করো (`cron`), আর ideally এর output এই course-এর Monitoring & Logging course আগে থেকে cover করা alerting-এ feed করো, যাতে drift automatically ধরা পড়ে, শুধু "কেউ মনে রাখলে" না।

## ৯. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. একটা Lynis "hardening index" আসলে কী measure করে, আর goal কি সবসময় 100 হওয়া উচিত?</strong></summary>

এটা একটা 0-100 score Lynis-এর শত শত check কতটা pass করেছে তা summarize করে। এটা সবসময় 100-এ chase করা উচিত না - কিছু suggestion একটা specific server genuinely যা লাগে তা trade away করে, তাই প্রতিটা finding একটা deliberate decision পাওয়া উচিত, blind compliance না।
</details>

<details>
<summary><strong>Q2. Lynis আর unattended-upgrades-এর মধ্যে practical পার্থক্য কী?</strong></summary>

Lynis বর্তমান configuration আর posture audit করে আর report করে (এটা নিজে কিছু বদলায় না); unattended-upgrades আসলে একটা schedule-এ automatically security patch install করে। একটা সমস্যা খুঁজে পায়, আরেকটা একটা specific category (missing patch) fix করে কোনো human-এর action না লাগিয়ে।
</details>

<details>
<summary><strong>Q3. Default-এ, unattended-upgrades কি available সব package update install করে?</strong></summary>

না - এর Allowed-Origins setting default-এ এটাকে security-relevant repository-তে scope করে, তাই এটা অসম্পর্কিত version bump নিঃশব্দে pull না করে known security issue patch করে যা কিছু ভাঙতে পারতো।
</details>

<details>
<summary><strong>Q4. unattended-upgrades দিয়ে একটা kernel security patch install হয়েছে। Server কি এখন protected?</strong></summary>

Necessarily সাথে সাথেই না - একটা kernel upgrade typically effect নেওয়ার জন্য একটা reboot লাগে, আর unattended-upgrades explicitly configure না করা থাকলে automatically reboot করে না, কারণ একটা unattended reboot-এর নিজের risk আছে।
</details>

<details>
<summary><strong>Q5. ss -tulpn একটা database 127.0.0.1 আর একটা private network address দুটোতেই listen করছে দেখায়। এটা কি automatically একটা security problem?</strong></summary>

Automatically না - এটা depend করে সেই reachability intended কিনা আর firewall (Chapter 2) আসলে সেই network-এ কে এটা reach করতে পারে restrict করে কিনা তার উপর। Audit-এর point হলো fact explicitly surface করা, কোনোভাবে assume করে রাখা না।
</details>

<details>
<summary><strong>Q6. Firewall আর কী listen করছে (ss) দুটোই কেন check করা উচিত, একটা না?</strong></summary>

এগুলো আলাদা layer: একটা service reachable একটা address-এ bound হতে পারে কিন্তু তখনও firewall দিয়ে block হতে পারে, বা firewall একটা port allow করতে পারে যাতে আসলে কিছুই listen করছে না। শুধু একটা check করলে আরেকটা যা ধরতো তা miss হতে পারে।
</details>

<details>
<summary><strong>Q7. একটা hardening checklist কেন Chapter 1-6-এর item explicitly re-list করে, এই chapter-এ নতুন কী আছে তাই cover করার বদলে?</strong></summary>

কারণ configuration সময়ের সাথে drift করে - day one-এ সঠিক একটা setting পরে একটা অসম্পর্কিত বদলে revert হয়ে যেতে পারে। একটা checklist-এর value হলো re-checkable হওয়া, আগের hardening work-এর regression ধরা, শুধু এই chapter-এর নতুন material document করা না।
</details>

<details>
<summary><strong>Q8. Lynis-কে cron দিয়ে schedule করা কেন উচিত, শুধু "মনে থাকলে" manually চালানোর বদলে?</strong></summary>

একটা security audit configuration drift তখনই ধরে যখন এটা আসলে পরেও আবার চলে। একটা manual "মনে রাখো check করার" habit মাসের পর মাস unreliable; একটা scheduled run (ideally alerting-এ feed করা) automatically regression ধরে, কারো memory-র উপর নির্ভর না করে।
</details>

## ১০. Free resources

- Lynis official documentation: [cisofy.com/lynis](https://cisofy.com/lynis/) আর [Lynis GitHub repository](https://github.com/CISOfy/lynis)
- Ubuntu Documentation: [Automatic updates](https://ubuntu.com/server/docs/about-automatic-updates)
- `man ss`, `man cron`, `man crontab`
- DigitalOcean: [How To Keep Ubuntu Server Updated](https://www.digitalocean.com/community/tutorials/how-to-keep-ubuntu-22-04-updated)

---

**Next:** [Chapter 8 — Real project: hardening a Laravel production server end to end](08-real-project-hardening-a-laravel-server.md)
