# Chapter 2 — তোমার প্রথম VPS: SSH Key, নিরাপদ শুরু আর Firewall

> **Course:** Cloud (AWS / VPS Basics) · **Chapter:** 2 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cloud/02-your-first-vps)

Public internet-এ নতুন server স্বয়ংক্রিয় bot-রা **কয়েক মিনিটের মধ্যে** খুঁজে পায়। ওরা সারাদিন চেনা username আর password চেষ্টা করে। তাই server-এর জীবনের প্রথম ঘণ্টার একটাই কাজ: **কাউকে ডাকার আগে দরজা বন্ধ করা।**

## ১. Server পাওয়া

প্রতিটা provider-এর web page আলাদা, কিন্তু ধাপগুলো একই:

1. একটা account বানাও (আর payment method যোগ করো)।
2. একটা server বানাও (provider ভেদে নাম *droplet*, *instance*, *VPS*, *cloud server*)।
3. বাছো:
   - **Image / OS:** **LTS Ubuntu** release নাও (যেমন Ubuntu 24.04 LTS)। এই course-এর Linux chapter-গুলো Ubuntu/Debian ধরে চলে।
   - **Size:** সবচেয়ে ছোট plan (1 vCPU, 1 GB RAM) শেখার জন্য যথেষ্ট।
   - **Region:** তোমার user-দের কাছে (Chapter 1)।
   - **Authentication:** page দিলে **SSH key** বাছো (নিচে বানানো শিখবে)।
4. বানাও, আর server-এর **public IP address** copy করো।

অনুশীলন শেষে যেকোনো সময় server মুছে ফেলা যায়। বিল সাধারণত ঘণ্টা বা মাস হিসেবে হয়, তাই যা লাগবে না তা সরিয়ে দাও।

## ২. Password-এর বদলে SSH key

**SSH** দিয়ে তুমি internet-এর ওপর দিয়ে server-এর command line-এ ঢোকো। Password আন্দাজ করা যায়। **SSH key** হলো দুটো ফাইলের জোড়া:

- **private key**: তোমার computer-এ থাকে, **কখনো কাউকে দেবে না**;
- **public key**: ভাগ করা নিরাপদ, এটা তুমি server-এ রাখো।

Server তোমাকে ঢুকতে দেয় শুধু যদি তোমার কাছে public key-র সাথে মেলা private key থাকে। আন্দাজ করার মতো ছোট কিছু নেই।

নিজের computer-এ (Linux, macOS বা Windows PowerShell) key জোড়া বানাও:

```bash
ssh-keygen -t ed25519 -C "you@example.com"
```

Default জায়গা নিতে Enter চাপো, আর **একটা passphrase দাও** (laptop চুরি গেলে এটা key-কে বাঁচায়)। এখন তোমার আছে:

- `~/.ssh/id_ed25519` — **private**। কখনো upload, email বা commit করো না।
- `~/.ssh/id_ed25519.pub` — **public**। এটাই provider-এর page-এ বা server-এ paste করবে।

Public key দেখতে: `cat ~/.ssh/id_ed25519.pub`।

## ৩. প্রথম login

Server বানানোর সময় public key যোগ করে থাকলে:

```bash
ssh root@YOUR_SERVER_IP
```

প্রথমবার SSH জিজ্ঞেস করে server-এর fingerprint বিশ্বাস করবে কিনা। `yes` লেখো। এখন তুমি server-এর command prompt-এ।

`Permission denied (publickey)` পেলে server-এ তোমার public key নেই। Provider-এর web console থেকে যোগ করো, অথবা শুধু এই প্রথম login-এর জন্য তাদের দেওয়া সাময়িক password ব্যবহার করো।

## ৪. নিরাপদ শুরু, ধাপে ধাপে

তুমি এখন `root` হিসেবে ঢুকেছ, সর্বশক্তিমান user। সবসময় root-এ কাজ করা বিপজ্জনক: একটা ভুল টাইপে সব নষ্ট হতে পারে। আমরা `sudo`-সহ একটা সাধারণ user বানাব।

**ধাপ ১ — system update**

```bash
apt update && apt upgrade -y
```

**ধাপ ২ — নিজের user বানাও**

```bash
adduser deploy                # জিজ্ঞেস করলে শক্ত password দাও
usermod -aG sudo deploy       # sudo-র অনুমতি
```

(`deploy`-এর জায়গায় তোমার পছন্দের নাম দিতে পারো।)

**ধাপ ৩ — ওই user-কে তোমার SSH key দাও**

```bash
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys
```

**ধাপ ৪ — নতুন user *দ্বিতীয়* terminal-এ পরীক্ষা করো। প্রথমটা খোলা রাখো।**

```bash
ssh deploy@YOUR_SERVER_IP
sudo whoami                   # ছাপা উচিত: root
```

এটা কাজ করলে তবেই এগোও। খোলা root session তোমার নিরাপত্তা-জাল।

## ৫. দরজা বন্ধ করো: SSH setting

এখন `deploy` হিসেবে SSH server-এর config edit করো:

```bash
sudo nano /etc/ssh/sshd_config
```

এই লাইনগুলো সেট করো (বা বদলাও):

```
PermitRootLogin no
PasswordAuthentication no
```

- `PermitRootLogin no` — কেউ সরাসরি root হিসেবে ঢুকতে পারবে না।
- `PasswordAuthentication no` — শুধু SSH key চলবে। Password আন্দাজ করা bot-রা এখন কোথাও পৌঁছায় না।

ফাইলে ভুল আছে কিনা দেখো, তারপর reload করো:

```bash
sudo sshd -t                           # syntax ঠিক থাকলে কিছুই ছাপে না
sudo systemctl reload ssh
```

**একটা সাধারণ ফাঁদ:** অনেক cloud image-এ `/etc/ssh/sshd_config.d/`-এর ভেতরের একটা ফাইল (প্রায়ই নাম `50-cloud-init.conf`) তখনও `PasswordAuthentication yes` বলে তোমার edit-কে উল্টে দিতে পারে। *আসলে* কী কার্যকর আছে তা দেখো:

```bash
sudo sshd -T | grep -i -E "passwordauthentication|permitrootlogin"
```

তুমি দেখতে চাও `passwordauthentication no` আর `permitrootlogin no`। না হলে অন্য ফাইলটা খুঁজে ঠিক করো।

**নিরাপত্তা-জালের session বন্ধ করার আগে** তৃতীয় একটা terminal খুলে নিশ্চিত হও `ssh deploy@YOUR_SERVER_IP` এখনও কাজ করে। তারপর পুরনো root session বন্ধ করতে পারো।

## ৬. UFW দিয়ে Firewall

**Firewall** ঠিক করে কোন network port বিশ্বের কাছে খোলা থাকবে। আমরা চাই শুধু: SSH (22), HTTP (80), HTTPS (443)। বাকি সব বন্ধ।

![শুধু SSH, HTTP ও HTTPS অনুমোদনকারী firewall](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/06-cloud/images/vps-firewall.svg)

Ubuntu-তে **UFW** ("Uncomplicated Firewall") আসে। **ক্রম গুরুত্বপূর্ণ: আগে SSH অনুমোদন করো, তারপর enable।** SSH অনুমোদন না করে enable করলে তুমি নিজেই তালাবদ্ধ হবে।

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status verbose
```

তোমার provider তাদের web panel-এ **cloud firewall** বা "security group"-ও দিতে পারে। এটা UFW-এর পাশাপাশি server-এর সামনে কাজ করে। দুটোর নিয়ম সামঞ্জস্যপূর্ণ রাখো: একটা কোনো port আটকালে অন্যটা সেটা খুলতে পারে না।

## ৭. আরও দুটো সস্তা সুরক্ষা

- **স্বয়ংক্রিয় security update:** `sudo apt install unattended-upgrades` গুরুত্বপূর্ণ security patch তোমার হয়ে install করে।
- **fail2ban** (ঐচ্ছিক): `sudo apt install fail2ban` বারবার login-এ ব্যর্থ হওয়া IP address সাময়িকভাবে আটকায়। Password login বন্ধ থাকলে এর গুরুত্ব কম, তবে এটা log-এর আবর্জনাও কমায়।

আর কে login করার চেষ্টা করেছে সেটা দেখার অভ্যাস করো:

```bash
sudo journalctl -u ssh --since "1 hour ago" | tail
```

সম্ভবত অচেনা কাউকে `root`, `admin`, `ubuntu` চেষ্টা করতে দেখবে। এটা স্বাভাবিক, আর ঠিক এ কারণেই আমরা দরজা বন্ধ করেছি।

## ৮. নিজেই তালাবদ্ধ হলে

প্রত্যেকের জীবনে একবার হয়। প্রায় প্রতিটা provider-এর panel-এ **web console** (বা "recovery console") থাকে, যা SSH ছাড়াই login prompt দেয়। সেখানে ঢুকে SSH config বা firewall ঠিক করো (`sudo ufw disable` দ্রুত ফিরে আসার উপায়), তারপর আবার চেষ্টা করো। এ কারণেই session বন্ধ করার আগে পরীক্ষা করতে বলা হয়েছে।

## ৯. মূল কথা

- নতুন server কয়েক মিনিটেই আক্রান্ত হয়, তাই **আগে** সুরক্ষিত করো।
- Password নয়, **SSH key** ব্যবহার করো। Private key তোমার computer ছেড়ে যায় না।
- Root-এ কাজ করো না: `sudo`-সহ user বানাও, তারপর `PermitRootLogin no` আর `PasswordAuthentication no` দাও।
- **প্রথম terminal বন্ধ করার আগে নতুন login দ্বিতীয় terminal-এ পরীক্ষা করো।** `sshd -T` দিয়ে কার্যকর setting দেখো।
- **UFW** ব্যবহার করো: `ufw enable`-এর *আগে* `OpenSSH` অনুমোদন করো, তারপর শুধু 80 ও 443 খোলো।
- System update রাখো, আর জানো provider-এর recovery console কোথায়।

## ১০. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. কোন SSH key ফাইল কখনো কাউকে দেবে না?</strong></summary>

Private key (যেমন `id_ed25519`)। Public key (`.pub`) ভাগ করা যায়।
</details>

<details>
<summary><strong>Q2. Root-এ কাজ না করে `sudo`-সহ সাধারণ user বানাই কেন?</strong></summary>

Root যেকোনো কিছু বদলাতে বা মুছতে পারে, তাই একটা ভুল টাইপ বিপর্যয় ঘটাতে পারে। সাধারণ user-কে শক্তিশালী কাজে `sudo` লাগে, যা একটা বিরতি দেয় আর রেকর্ড রাখে।
</details>

<details>
<summary><strong>Q3. নিরাপত্তায় সবচেয়ে বেশি কাজে লাগা দুটো SSH setting কী, আর ওরা কী করে?</strong></summary>

`PermitRootLogin no` সরাসরি root login আটকায়। `PasswordAuthentication no` শুধু SSH key চলতে দেয়, তাই password আন্দাজ করা বৃথা।
</details>

<details>
<summary><strong>Q4. `ufw enable`-এর আগে `ufw allow OpenSSH` চালাতে হয় কেন?</strong></summary>

SSH অনুমোদন না করে firewall চালু করলে port 22 আটকে যায় আর তুমি নিজের server থেকেই বাইরে পড়ো।
</details>

<details>
<summary><strong>Q5. তুমি `sshd_config` বদলেছ কিন্তু password login এখনও চলছে। সম্ভাব্য কারণ কী?</strong></summary>

`/etc/ssh/sshd_config.d/`-এর অন্য কোনো ফাইল setting-টা উল্টে দিচ্ছে। `sudo sshd -T` দিয়ে কার্যকর মান দেখো।
</details>

## ১১. বিনামূল্যের resource

- Ubuntu: [OpenSSH server](https://documentation.ubuntu.com/server/how-to/security/openssh-server/)
- Ubuntu: [Firewall (UFW)](https://documentation.ubuntu.com/server/how-to/security/firewalls/)
- DigitalOcean: [Initial Server Setup with Ubuntu](https://www.digitalocean.com/community/tutorials/initial-server-setup-with-ubuntu)
- GitHub: [Generating a new SSH key](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/generating-a-new-ssh-key-and-adding-it-to-the-ssh-agent)

---

**Next:** Chapter 3 — Domain আর DNS *(coming soon)*
