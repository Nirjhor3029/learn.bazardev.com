# Chapter 8 — SSH আর Remote Access

> **Course:** Linux & Shell Basics · **Chapter:** 8 of 8 (শেষ চ্যাপ্টার!)
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-shell/08-ssh-and-remote-access)

এতদিন প্রতিটা chapter ধরে নিয়েছিল তুমি আগে থেকেই একটা server-এ "আছো"। এই chapter হলো তুমি আসলে কীভাবে সেখানে পৌঁছাও — নিজের laptop থেকে, নিরাপদভাবে।

## ১. SSH আসলে কী

**SSH** (Secure Shell) হলো তোমার সামনে না থাকা একটা মেশিনে কীভাবে একটা terminal session খোলো — encrypted, তাই তুমি যা টাইপ করো (password সহ) connection intercept করা কেউ পড়তে পারবে না।

```bash
ssh dev@167.86.127.108
```
`dev` user হিসেবে সেই server-এ connect করে। Non-default port ব্যবহার করলে:
```bash
ssh -p 2222 dev@167.86.127.108
```

## ২. Password-এর চেয়ে SSH key কেন ভালো

একটা password guess করা, brute-force করা, বা leak হওয়া যায়। একটা SSH **key pair** attack করা অনেক বেশি কঠিন, এবং এটাই real server practically কীভাবে secure করা হয়।

![SSH key authentication diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/02-linux-shell/images/ssh-key-auth.svg)

Idea-টা: তুমি দুটো mathematically linked file বানাও — একটা **private key** (তোমার মেশিনে থাকে, সবসময়, কখনো share করা হয় না) এবং একটা **public key** (তুমি access করতে চাও এমন যেকোনো server-এ রাখা নিরাপদ)। তুমি connect করলে, server এমন একটা challenge পাঠায় যা শুধু তোমার matching private key সঠিকভাবে answer দিতে পারে — এটা আসলে তোমার private key দেখে না বা পায়ও না।

```bash
ssh-keygen -t ed25519 -C "your_email@example.com"
```
একটা নতুন key pair বানায় (`ed25519` modern, recommended key type)। এটা দুটো file বানায়: `~/.ssh/id_ed25519` (private — এটা guard করো) এবং `~/.ssh/id_ed25519.pub` (public — share/copy করা নিরাপদ)।

```bash
ssh-copy-id dev@167.86.127.108
```
তোমার **public** key automatically server-এ copy করে, সেই account-এর `~/.ssh/authorized_keys` file-এ যোগ করে। এটার পর, `ssh dev@167.86.127.108` তোমাকে কোনো password prompt ছাড়াই login করিয়ে দেয়।

`ssh-copy-id` না থাকলে, manual equivalent হলো নিজেই তোমার public key-র content server-এ `~/.ssh/authorized_keys`-এ append করা।

## ৩. SSH দিয়ে file copy করা

```bash
scp localfile.txt dev@167.86.127.108:/home/dev/
```
তোমার মেশিন থেকে `localfile.txt` server-এর সেই path-এ copy করে — `ssh`-এর ঠিক একই connection আর authentication, শুধু terminal খোলার বদলে একটা file move করা।

```bash
scp dev@167.86.127.108:/home/dev/remote-file.txt ./
```
উল্টোটা — server থেকে তোমার মেশিনে একটা file টেনে আনা।

## ৪. একটা config file, যাতে পুরো command আর টাইপ না করতে হয়

**তোমার নিজের মেশিনে** `~/.ssh/config` বানাও (বা edit করো):
```
Host myserver
    HostName 167.86.127.108
    User dev
    Port 22
    IdentityFile ~/.ssh/id_ed25519
```

এখন, প্রতিবার পুরো command মনে রাখার বদলে:
```bash
ssh myserver
```
এতটুকুই। এটা একটা ছোট file, যা বেশিরভাগ developer একবার সেট করে আর কখনো এটা নিয়ে ভাবে না — একই server-এ নিয়মিত connect করলে এটা করার মতো।

## ৫. কাজ না করলে: common SSH error

| Error | সম্ভাব্য কারণ |
|---|---|
| `Permission denied (publickey)` | তোমার public key server-এর `authorized_keys`-এ নেই, বা তোমার private key file-এর permission ভুল |
| `WARNING: UNPROTECTED PRIVATE KEY FILE!` | তোমার private key-র file permission বেশি open — SSH এমন key ব্যবহার করতে অস্বীকার করে যা তোমার মেশিনে অন্য কেউ পড়তে পারে |
| `Host key verification failed` | Server-এর identity fingerprint তুমি last connect করার পর পরিবর্তন হয়ে গেছে (server rebuild-এর পর expected; unexpected হলে alarming — এটা মানে কেউ connection intercept করছে-ও হতে পারে) |

এই দ্বিতীয়টা সরাসরি Chapter 3-এর সাথে connect হয়: SSH private key `600`-এ lock করা থাকতে হবে (owner read/write করতে পারে, অন্য কেউ পড়তেও পারে না):
```bash
chmod 600 ~/.ssh/id_ed25519
```

## ৬. Firewall নিয়ে একটা কথা

একটা server সাধারণত internet-এর জন্য ছোট, নির্দিষ্ট কিছু port খোলা লাগে — SSH (22), এবং তোমার app আসলে যা serve করে (web traffic-এর জন্য 80/443)। Ubuntu-তে `ufw` (Uncomplicated Firewall)-এর মতো tool তোমাকে ঠিক এগুলোই allow করতে দেয়, আর কিছু না:
```bash
sudo ufw allow OpenSSH
sudo ufw allow 80,443/tcp
sudo ufw enable
```
এটা নিজেই একটা গভীর topic — এই course থেকে একটাই অভ্যাস নেওয়ার মতো: নির্দিষ্ট কোনো কারণ না থাকলে কখনো একটা port expose করবে না।

## ৭. মূল কথাগুলো (Key Takeaways)

- `ssh user@host` একটা encrypted remote terminal session খোলে; `scp` সেই একই secure connection-এর মাধ্যমে file copy করে।
- SSH key pair password-এর চেয়ে ভালো: তোমার private key তোমার মেশিন থেকে কখনো যায় না, আর server শুধু verify করে তুমি এটা আছে কিনা, এটা কখনো দেখা ছাড়াই।
- `ssh-copy-id` তোমার public key একটা server-এ install করে, যাতে কোনো password prompt ছাড়াই login করতে পারো।
- `~/.ssh/config` তোমাকে একটা দীর্ঘ `ssh -p PORT user@host` command-কে `ssh myserver`-এর মতো একটা ছোট alias-এ পরিণত করতে দেয়।
- `Permission denied (publickey)` প্রায়ই একটা file permission সমস্যা (আবার Chapter 3) — তোমার private key `chmod 600` হওয়া উচিত।

## ৮. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. একটা SSH key pair কেন সাধারণত password-এর চেয়ে বেশি secure ধরা হয়?</strong></summary>

একটা password অনেক চেষ্টায় guess বা brute-force করা যায়। Key pair দিয়ে, server তোমার private key আসলে পায়ই না — এটা শুধু চেক করে তুমি সেটা দিয়ে সঠিকভাবে একটা challenge-এর response দিতে পারো কিনা, যা একটা password guess করার চেয়ে fake করা বা চুরি করা অনেক বেশি কঠিন।
</details>

<details>
<summary><strong>প্রশ্ন ২. `ssh-copy-id` ঠিক কী করে?</strong></summary>

এটা তোমার **public** key (private-টা কখনো না) target server-এ copy করে এবং সেই account-এর `~/.ssh/authorized_keys` file-এ যোগ করে, যাতে পরের login গুলো তোমার matching private key দিয়ে কোনো password prompt ছাড়াই succeed করে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. Connect করার সময় "Permission denied (publickey)" পাচ্ছো। Key server-এ না থাকার পাশাপাশি, Chapter 3-এর ভিত্তিতে আরও কী চেক করা উচিত?</strong></summary>

তোমার private key file-এর permission। SSH এমন একটা private key ব্যবহার করতে অস্বীকার করবে যা owner ছাড়া অন্য কেউ পড়তে পারে — এটা `chmod 600` হওয়া উচিত।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একটা `~/.ssh/config` entry আসলে তোমাকে কী করা থেকে বাঁচায়?</strong></summary>

প্রতিবার সম্পূর্ণ connection detail — hostname, username, port, আর key file — আবার টাইপ করা। একটা `Host` alias-এর নিচে একবার define করলে, শুধু `ssh myserver` automatically সেই সব তথ্য বহন করে।
</details>

## ৯. ফ্রি রিসোর্স (English)

- Official reference: [OpenSSH — `ssh_config` manual](https://man.openbsd.org/ssh_config)
- Official reference: [Ubuntu Server Guide — OpenSSH](https://ubuntu.com/server/docs/openssh-server)

---

**এটাই সম্পূর্ণ Linux & Shell Basics course।** Navigation, files, permissions, users, processes, packages, pipes, এবং এখন remote access — এখন থেকে তুমি comfortably একটা Linux server-এর ভেতরে থাকতে পারবে।

🎉 দুইটা course শেষ। [learn.bazardev.com](https://learn.bazardev.com)-এ পরের topic: **Git & GitHub (Advanced)** — শীঘ্রই আসছে।
