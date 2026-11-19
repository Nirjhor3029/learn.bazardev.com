# Chapter 2 — Firewall: UFW আর nftables, ঠিকভাবে Default-Deny

> **Course:** Linux Server Security & Secrets · **Chapter:** 2 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-security/02-firewalls-ufw-and-nftables)

> **Honesty note:** এই command গুলোও লেখার সময় live server-এ চালানো হয়নি — firewall বদলানোর ঝুঁকি Chapter 1-এর SSH বদলানোর মতোই, এবার শুধু port 22 না, *প্রতিটা* port-এর জন্য। নিচের সবকিছু official UFW manual (`man ufw`), Ubuntu Server firewall documentation, `nftables` wiki, আর Docker-এর নিজের documentation থেকে নেওয়া যেখানে host firewall-এর সাথে এর interaction বলা আছে, সব শেষে link দেওয়া আছে। real machine-এ rule বদলানোর আগে Chapter 1-এর "কাজ করা session খোলা রাখো" discipline মানো — একটা ভুল firewall rule ঠিক সেই SSH session-টাই কেটে দিতে পারে যেটা দিয়ে তুমি সেটা ঠিক করতে চাইছিলে।

Cloud course-এ তুমি `ufw allow 22`, `ufw allow 80`, `ufw allow 443` চালিয়ে এগিয়ে গিয়েছিলে (Chapter 2)। এটা সঠিক প্রথম step ছিল, কিন্তু "তিনটা জিনিস allow করা" একটা firewall যা করে তার অর্ধেক মাত্র। এই chapter বাকি অর্ধেক cover করে — **default-deny**, UFW-এর নিচে আসলে কী enforce করছে, আর একটা real gotcha যা পরে একই server-এ Docker যোগ করলে ধরা পড়ে।

## ১. Model: default-deny, তারপর ঠিক যা লাগবে তা open করা

একটা firewall-এর কাজ হলো, machine-এ আসা প্রতিটা packet-এর জন্য decide করা, এটা যেতে দেওয়া হবে কিনা। UFW (**U**ncomplicated **F**ire**w**all) Ubuntu-র সাথে আসে আর এই decision-এর দুটো possible starting point দেয়:

- **Default allow** — rule block না করলে সবকিছু যেতে দেয়। তুমি ভুলে যাওয়া কিছু expose করে misconfigure হয়ে যাওয়া সহজ।
- **Default deny** — rule allow না করলে কিছুই যায় না। যা reachable করতে চাও তা সচেতনভাবে open করতে হবে; বাকি সব default-এ বন্ধ থাকে, পরে install করা আর firewall করতে ভুলে যাওয়া service-ও।

এই course default-deny ব্যবহার করে, যা UFW-এরই নিজের recommended starting point:

![Default-deny আসলে কীভাবে কাজ করে, আর Docker কীভাবে নিঃশব্দে এর পাশ দিয়ে যায়](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/11-linux-security/images/ufw-default-deny-and-docker-gotcha.svg)

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
```

Outgoing default-এ allow থাকে — তোমার server extra rule ছাড়াই outbound request করতে পারে (composer, npm, curl, SSLCommerz callback); default-deny যেটা থেকে protect করে সেটা হলো *incoming*, unsolicited connection।

## ২. আসলে যা লাগবে তা allow করা

```bash
sudo ufw allow 22/tcp    # বা: sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

Cloud course cover করেনি এমন কিছু জানার মতো জিনিস:

- **UFW service name বোঝে**, যেকোনো জিনিসের জন্য যার `/etc/services` entry বা app profile আছে (`ufw app list` দেখায় installed profile যেমন `OpenSSH`, `Nginx Full`) — `ufw allow OpenSSH` আর `ufw allow 22/tcp` একই কাজ করে; নাম শুধু পরে পড়তে সহজ।
- **একটা rule-কে একটা source-এ scope করা যায়**, যা world-reachable হতে হয় না এমন কিছুর জন্য অনেক গুরুত্বপূর্ণ:
  ```bash
  sudo ufw allow from 203.0.113.50 to any port 3306 proto tcp
  ```
  এটা MySQL-এর port শুধু একটা specific IP (ধরো, তোমার office, বা cluster-এর অন্য server) থেকে allow করে — rule-এ port "খোলা" হলেও বাকি সবাই এখনও denied।
- **তোমার আসলে কী আছে check করো**, numbered, পরে number দিয়ে delete করার জন্য:
  ```bash
  sudo ufw status numbered
  ```
  ```
       To                         Action      From
       --                         ------      ----
  [ 1] 22/tcp                     ALLOW IN    Anywhere
  [ 2] 80/tcp                     ALLOW IN    Anywhere
  [ 3] 443/tcp                    ALLOW IN    Anywhere
  [ 4] 3306/tcp                   ALLOW IN    203.0.113.50
  ```

## ৩. Stateful, stateless না: কেন তুমি কখনো "reply allow করো" rule লেখেনি

Visitor-কে HTTP response ফিরিয়ে দেওয়ার জন্য তোমাকে কখনো rule লিখতে হয়নি — শুধু inbound request-এর জন্যই লিখেছিলে। কারণ UFW (আসলে প্রায় সব modern firewall-ই) **stateful**: এটা track করে কোন outbound packet কোন আগে approve করা connection-এর reply, Linux kernel-এর **conntrack** (connection tracking) দিয়ে, আর নিজস্ব rule ছাড়াই reply automatically যেতে দেয়। শুধু *নতুন, unsolicited* inbound connection তোমার allow list-এর বিরুদ্ধে check হয় — আগে approve করা connection-এ ride করা একটা reply নতুন connection না।

## ৪. আসলে এটা কে enforce করছে: nftables

UFW একটা **front end** — একটা সহজ syntax যা নিচে kernel-এর real packet-filtering engine-এ rule লিখে দেয়। Current Ubuntu release-এ, সেই engine হলো **nftables** (পুরনো `iptables`-এর modern successor, যা Ubuntu compatibility-র জন্য রেখে দিয়েছে কিন্তু UFW-এর default backend হিসেবে আর ব্যবহার করে না)। তুমি যে প্রতিটা `ufw allow` command চালিয়েছিলে, সেটা চালানোর মুহূর্তেই একটা nftables rule হয়ে গিয়েছিল — UFW নিজে কখনো packet evaluate করে না।

UFW আসলে কী লিখেছে, নিজের সহজ view-এর নিচে, দেখো:

```bash
sudo nft list ruleset
```

Output `ufw status`-এর চেয়ে অনেক বেশি verbose — table, chain, আর explicit `accept`/`drop` verdict-সহ rule — কারণ `ufw status` ঠিক এটারই একটা ইচ্ছাকৃতভাবে simplified summary। এটা জানা গুরুত্বপূর্ণ হয়ে যায় যখন কিছু শুধু একটা `ufw` rule থেকে যা আশা করা যায় তার মতো behave করে না (Section 6-এ একটা real example আছে): real decision সবসময় nftables ruleset-এ নেওয়া হয়, আর UFW শুধু তুমি সাধারণত সেটা লিখতে যে editor ব্যবহার করো।

## ৫. IPv6, আর logging

`/etc/default/ufw`-এর দুটো setting, accident-এ discover করার বদলে জেনে রাখা worth:

- **`IPV6=yes`** (default) তুমি যোগ করা প্রতিটা rule-কে IPv4 আর IPv6 দুটোতেই automatically apply করে — `ufw allow 443/tcp` port-টা দুটোতেই খোলে। তোমার server-এ public IPv6 address থাকলে আর এটা off থাকলে, IPv6 traffic তুমি cover করা মনে করা rule-কে পাশ কাটিয়ে যেত।
- **`ufw logging on`** (medium একটা reasonable level) প্রতিটা blocked packet kernel log-এ লেখে, এভাবে দেখা যায়:
  ```bash
  sudo journalctl -k | grep UFW
  ```
  ```
  kernel: [UFW BLOCK] IN=eth0 ... SRC=198.51.100.9 DST=... DPT=3389 ...
  ```
  এখানে `DPT` (destination port) `3389` মানে কেউ একটা Linux box-এ Windows Remote Desktop scan করছে যেটা কখনো ছিলই না — harmless, কিন্তু Chapter 1-এর SSH scan log-এর মতোই একই রকম background noise, এখন শুধু 22-এর বদলে প্রতিটা port-এর জন্য visible।

## ৬. Firewall-এ rate-limiting: প্রথম সারির defense

```bash
sudo ufw limit ssh
```

`limit` `allow`-এর চেয়ে বেশি strict: UFW একটা IP-কে deny করে যদি সেটা 30 সেকেন্ডের মধ্যে সেই rule-এর port-এ প্রায় 6-এর বেশি নতুন connection try করে। এটা Chapter 1-এর `PasswordAuthentication no` বা Chapter 3-এর fail2ban-এর বদলে না — এটা একটা cheap, built-in প্রথম সারি, যা একটা connection-flood ধরনের scan-কে SSH daemon-এ পৌঁছে reject হওয়ার আগেই ধীর করে দেয়।

## ৭. Docker gotcha: একটা published container port পুরো UFW-কে bypass করতে পারে

এই chapter যে real trap নিয়ে সাবধান করার জন্য লেখা, সেটা এটাই, আর এটা দেখা যায় শুধু এই course-এর সাথে Docker course মিলিয়ে নিলে। Docker তার নিজের networking manage করে একই nftables/iptables table-এ **সরাসরি** rule লিখে যা UFW-ও ব্যবহার করে — আর default-এ, একটা published port-এর জন্য Docker-এর rule এমনভাবে insert হয় যা সেই container-এ traffic পৌঁছাতে দিতে পারে **UFW যা-ই বলুক তার তোয়াক্কা না করে**, কারণ published port-এর জন্য Docker-এর নিজের `ACCEPT` rule, UFW-এর default `DROP` সেটার উপর apply হওয়ার আগেই evaluate হয়। Concretely: `docker run -p 5432:5432 postgres` port 5432-কে internet থেকে reachable রাখতে পারে, এমনকি `ufw status` কোনো rule-ই না দেখালেও — "ufw এটা allow করেনি" মানে "এটা block" না, যদি Docker একই ruleset-এ আগে থেকেই উপরে accept করে রাখে।

এটা documented behavior, bug না — Docker নিজের networking documentation-এ (নিচে link দেওয়া) সরাসরি এটা বলে দেয়, কারণ এটা real incident ঘটায়। তিনটা honest mitigation, কতটা বদলায় তার ক্রমে:

1. **Published port localhost-এ bind করো**, আর container-টাকে একটা reverse proxy (Caddy, যেটা এই course আগে থেকেই ব্যবহার করছে) দিয়ে পৌঁছাও যেটা UFW *সত্যিই* govern করে: `docker run -p 127.0.0.1:5432:5432 postgres` — এখন port শুধু host নিজের থেকেই reachable, Chapter 1-এ MySQL-এর জন্য যে pattern দেখেছিলে সেটাই।
2. **Port একদমই publish করো না** যদি Docker-এর নিজের network-এর বাইরে কিছু এটা না লাগে — একই Docker network-এ থাকা container-রা কোনো `-p` flag ছাড়াই service name দিয়ে একে অপরের কাছে পৌঁছায়।
3. যাদের সত্যিই Docker-এর published port UFW respect করা দরকার, `ufw-docker`-এর মতো একটা dedicated tool (community project, নিচে link দেওয়া) integration-টা rewrite করে — এটা জানো যে Docker একই machine-এ আসার পর `ufw status`-কেই পুরো গল্প ধরে নেওয়ার বদলে এটা একটা real, documented gap।

## ৮. মূল কথা

- **Default-deny incoming, default-allow outgoing** হলো model: ঠিক যা reachable হতে হবে তা open করো, বাকি সব পরে accident-এ নিজে থেকে open হয় না।
- একটা firewall **stateful** — আগে approve করা connection-এর reply conntrack দিয়ে automatically pass করে; নতুন inbound connection-এর জন্যই rule লেখো।
- UFW একটা front end; **nftables** (`nft list ruleset` দিয়ে দেখা যায়) আসলে প্রতিটা packet filter করে। `ufw status` সেই real ruleset-এর একটা simplified summary।
- `ufw logging on` আর `journalctl -k | grep UFW` ঠিক কী block হচ্ছে দেখায়, Chapter 1-এর SSH log যেভাবে login attempt দেখিয়েছিল একইভাবে।
- `ufw limit ssh` connection flood-কে service-এ পৌঁছানোর আগেই throttle করে — একটা cheap প্রথম সারি, Chapter 1 বা Chapter 3-এর substitute না।
- **Docker UFW ব্যবহার করা একই underlying table-এ নিজের rule লেখে**, আর একটা published container port কোনো matching UFW rule না থাকলেও internet থেকে reachable হতে পারে। `127.0.0.1`-এ bind করে reverse-proxy করো, বা port একদমই publish করো না, যদি না specifically অন্যভাবে configure করা থাকে।

## ৯. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. "default deny incoming" আর "default allow incoming"-এর পার্থক্য কী?</strong></summary>

Default deny প্রতিটা incoming connection block করে যতক্ষণ না একটা rule explicitly allow করে — পরে install করা আর firewall করতে ভুলে যাওয়া কিছু বন্ধই থাকে। Default allow rule block না করলে সবকিছু যেতে দেয়, যা তুমি firewall করতে ভুলে যাওয়া কিছু নিঃশব্দে expose করে।
</details>

<details>
<summary><strong>Q2. একটা visitor-কে HTTP response ফিরিয়ে দেওয়ার জন্য তোমার firewall rule কেন লাগে না?</strong></summary>

Firewall stateful: এটা connection tracking (conntrack) ব্যবহার করে বুঝতে পারে reply-টা একটা আগে থেকে একটা existing allow rule দিয়ে approve করা connection-এর অংশ, আর automatically যেতে দেয়। শুধু নতুন, unsolicited inbound connection allow list-এর বিরুদ্ধে check হয়।
</details>

<details>
<summary><strong>Q3. UFW আর nftables-এর সম্পর্ক কী?</strong></summary>

UFW একটা simplified front end; এটা যে rule লেখে সেটা নিচে একটা real nftables rule হয়ে যায়, যা আসলে packet filter করে। `ufw status` সেই real ruleset-এর একটা simplified summary, যা `nft list ruleset` দিয়ে দেখা যায়।
</details>

<details>
<summary><strong>Q4. <code>ufw limit ssh</code> কী করে, আর কী replace করে না?</strong></summary>

এটা একটা IP-কে deny করে যদি সেটা সেই port-এ অতিরিক্ত বেশি (প্রায় 30 সেকেন্ডে 6) নতুন connection attempt করে, network level-এ connection-flood ধরনের scan throttle করে। এটা password authentication বন্ধ করা (Chapter 1) বা fail2ban (Chapter 3) replace করে না।
</details>

<details>
<summary><strong>Q5. কেন একটা Docker container-এর published port internet থেকে reachable হতে পারে, এমনকি <code>ufw status</code> কোনো allow rule না দেখালেও?</strong></summary>

Docker সরাসরি একই nftables/iptables table-এ নিজের accept rule insert করে যা UFW ব্যবহার করে, আর default-এ সেই rule UFW-এর deny default apply হওয়ার আগেই effect-এ চলে যেতে পারে — তাই UFW-এর নিজের rule list-এ কিছু না দেখালেও practice-এ port open থাকতে পারে।
</details>

<details>
<summary><strong>Q6. একটা Docker container-এর published port UFW bypass করা বন্ধ করার একটা উপায় বলো।</strong></summary>

Published port শুধু localhost-এ bind করো (যেমন <code>-p 127.0.0.1:5432:5432</code>) আর UFW govern করা একটা reverse proxy দিয়ে পৌঁছাও, বা Docker-এর network-এর বাইরে কিছু এটা না লাগলে port একদমই publish করো না।
</details>

<details>
<summary><strong>Q7. <code>/etc/default/ufw</code>-এ <code>IPV6=yes</code> থাকা মানে <code>ufw allow 443/tcp</code>-এর মতো একটা rule-এর জন্য কী?</strong></summary>

Rule-টা IPv4 আর IPv6 দুটোতেই automatically apply হয়। এটা off থাকলে আর server-এর public IPv6 address থাকলে, port 443-এ IPv6 traffic তুমি আগে থেকে open ভাবা একটা rule-এর কভারেজের বাইরে থাকতো।
</details>

<details>
<summary><strong>Q8. <code>[UFW BLOCK] ... DPT=3389</code>-এর মতো একটা UFW log line-এর মানে কী, আর এটা নিজে থেকেই চিন্তার কারণ হওয়া উচিত?</strong></summary>

মানে port 3389-এ (Windows Remote Desktop) একটা inbound connection attempt block হয়েছে — নিজে থেকে এটা routine internet background scanning noise, Chapter 1-এর SSH scan attempt-এর মতোই একই category, কোনো targeted attack-এর প্রমাণ না।
</details>

## ১০. Free resources

- Ubuntu Server Documentation: [Firewalls (UFW)](https://ubuntu.com/server/docs/firewalls)
- `man ufw` (পুরো manual-এর জন্য `man 8 ufw`)
- nftables wiki: [What is nftables?](https://wiki.nftables.org/wiki-nftables/index.php/What_is_nftables%3F)
- Docker Documentation: [Packet filtering and firewalls](https://docs.docker.com/engine/network/packet-filtering-firewalls/)
- `ufw-docker` (community project): [GitHub](https://github.com/chaifeng/ufw-docker)

---

**Next:** [Chapter 3 — fail2ban: পুনরাবৃত্তি করা আক্রমণকারীদের জন্য automatic banning](03-fail2ban-and-automatic-banning.md)
