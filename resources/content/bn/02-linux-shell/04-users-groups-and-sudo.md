# Chapter 4 — Users, Groups, আর sudo

> **Course:** Linux & Shell Basics · **Chapter:** 4 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-shell/04-users-groups-and-sudo)

Chapter 3-এ দেখেছো owner আর group *কী* নিয়ন্ত্রণ করে। এই chapter দেখাবে user আর group আসলে কোথা থেকে আসে, এবং `sudo` টাইপ করলে ঠিক কী ঘটে — সাথে একটা detail, যা Docker course-এর একটা "fix" ব্যাখ্যা করবে যা তখন পুরোপুরি বোঝা না-ও হয়ে থাকতে পারে।

## ১. আমি আসলে কে?

```bash
whoami
```
শুধু তোমার username।

```bash
id
```
আরও বেশি useful — তোমার user ID, primary group, আর তুমি যে **সব** group-এর সদস্য তা দেখায়:
```
uid=1002(dev) gid=1002(dev) groups=1002(dev),27(sudo),100(users)
```
এই `groups=` list খুবই গুরুত্বপূর্ণ: এটা শুধু তোমার main account না, তুমি যে প্রতিটা "team"-এর সদস্য তার সব — আর group membership-ই Chapter 3-এর `chown :www-data` trick যার উপর নির্ভর করে।

```bash
groups
```
একটা ছোট version — শুধু তুমি যে group গুলোর সদস্য তার নাম।

## ২. User account আসলে কোথায় থাকে

```bash
cat /etc/passwd
```
প্রতিটা লাইন একটা user account, colon দিয়ে আলাদা করা:
```
dev:x:1002:1002:,,,:/home/dev:/bin/bash
```
`username : password-placeholder : user-id : group-id : full-name : home-folder : default-shell`

এই file তুমি কখনো হাতে edit করবে না — নিচের command গুলো তোমার জন্য নিরাপদে এটা করে দেয় — কিন্তু এটা আছে জানা "Linux এই তথ্য আসলে কোথায় রাখে" নিয়ে অনেক confusion দূর করে।

## ৩. User বানানো এবং group manage করা

```bash
sudo adduser newperson        # Ubuntu-র friendly, interactive version
sudo useradd -m newperson     # lower-level version (একইভাবে কাজ করতে বেশি flag লাগে)
```

```bash
sudo usermod -aG docker dev
```
Existing user `dev`-কে `docker` group-এ যোগ করে, **ইতিমধ্যে যে group গুলোর সদস্য আছে তা থেকে সরিয়ে না দিয়ে** (`-a` = append — এটা ভুলে যাওয়া একটা classic ভুল যা কারো অন্য সব group membership মুছে দেয়)।

এই command-টা পরিচিত লাগলে, ঠিক লাগারই কথা — এটা Docker course-এর Chapter 2-এর ঠিক সেই লাইন, এবং এই chapter এখন সেই technique-টা ঠিকমতো ব্যাখ্যা করছে।

```bash
sudo passwd newperson
```
সেই user-এর password set বা পরিবর্তন করে।

### Log out করে আবার login করতে হয়েছিল কেন

Docker course-এর Chapter 2-এ সেই `usermod` command-এর পরে "log out করে আবার login করো" বলার কথা মনে আছে? এটার কারণ এখানে: **তোমার current session আগে থেকেই তোমার group list login করার সময় load করে ফেলেছে।** নিজেকে একটা নতুন group-এ যোগ করলে `/etc/group` সাথে সাথে আপডেট হয়, কিন্তু তোমার *ইতিমধ্যে চলা* terminal session automatically সেটা আবার পড়ে না। Log out করে আবার login করা (বা একটা fresh session শুরু করা) shell-কে নতুন group membership নিতে দেয়। এটা প্রথমবার প্রায় সবাইকে confuse করে।

## ৪. `sudo` vs `su` — পার্থক্যটা যা আসলে গুরুত্বপূর্ণ

দুটোই তোমাকে অন্য user (সাধারণত `root`) হিসেবে কাজ করতে দেয়, কিন্তু তারা সম্পূর্ণ আলাদাভাবে কাজ করে:

![su vs sudo diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/02-linux-shell/images/su-vs-sudo.svg)

```bash
su root
```
**Root**-এর password চায়, তারপর তোমাকে সম্পূর্ণভাবে একটা root session-এ পাঠিয়ে দেয় যতক্ষণ না তুমি `exit` টাইপ করো। তার পরে তুমি যা-ই টাইপ করো সব root হিসেবে চলে — তুমি এখনও root এটা ভুলে যাওয়া সহজ, আর ভুলে real ক্ষতি করাও সহজ।

```bash
sudo apt update
```
**তোমার নিজের** password চায়, *শুধু সেই একটা command* root হিসেবে চালায়, এবং সাথে সাথে তোমাকে আবার তুমি নিজে হিসেবে ফিরিয়ে দেয়। কিছুই থেকে যায় না।

এই কারণেই modern system-এ (এটা সহ) `su`-র বদলে `sudo` standard: এটা একবারে একটা command-এর সাথে scope করা, এবং — গুরুত্বপূর্ণভাবে — এটা exactly log করে কে কী চালিয়েছে, যা shared বা production server-এ অনেক গুরুত্বপূর্ণ।

### কে আসলে `sudo` ব্যবহার করতে পারে?

শুধু `/etc/sudoers`-এ list করা user-রা (বা, Ubuntu-তে বেশি common, `sudo` group-এর সদস্যরা) এটা একদমই ব্যবহার করতে পারে। উপরে `id` চালিয়ে যদি তোমার group-এ `27(sudo)` দেখো, এটাই ঠিক কারণ `sudo` তোমার জন্য কাজ করে।

## ৫. নিজে করে দেখো (read-only, সম্পূর্ণ নিরাপদ)

```bash
whoami
id
groups
cat /etc/passwd | head -5
sudo whoami   # "root" প্রিন্ট হবে — তুমি মাত্র একটা command root হিসেবে চালালে, এর বেশি কিছু না
```

## ৬. মূল কথাগুলো (Key Takeaways)

- `id` তোমার সম্পূর্ণ group membership দেখায় — শুধু main account না, তুমি যে প্রতিটা group-এর সদস্য তার সব।
- `/etc/passwd` প্রতিটা user account-কে একটা colon-separated লাইন হিসেবে list করে; তুমি এটা পড়ো system বোঝার জন্য, কিন্তু account manage করো `adduser` আর `usermod`-এর মতো command দিয়ে।
- `usermod -aG <group> <user>` একটা user-কে একটা group-এ যোগ করে — সবসময় `-a` সহ, না হলে তার অন্য membership মুছে যাবে।
- নতুন group-এ যোগ হওয়ার পর, এটা effective হওয়ার আগে তোমাকে একটা নতুন session শুরু করতে হবে (log out/in) — তোমার current shell আগে থেকেই পুরোনো group list load করে রেখেছে।
- `sudo` *তোমার* password দিয়ে একটা command root হিসেবে চালায় এবং log করে; `su` *root*-এর password দিয়ে তোমাকে exit করা পর্যন্ত সম্পূর্ণভাবে একটা root session-এ পাঠিয়ে দেয়। `sudo`-কে preference দাও।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. তুমি `usermod -aG docker dev` চালিয়েছো কিন্তু current terminal-এ "docker" group এখনো কাজ করছে না। কেন?</strong></summary>

তোমার current session login করার সময় তোমার group membership load করেছিল, এবং এটা automatically refresh হয় না। Shell-কে নতুন group নিতে দিতে তোমাকে log out করে আবার login করতে হবে (বা একটা fresh session শুরু করতে হবে)।
</details>

<details>
<summary><strong>প্রশ্ন ২. `usermod -G docker dev`-এ `-a` flag ভুলে যাওয়ার real ঝুঁকি কী?</strong></summary>

`-a` ছাড়া, `-G` user-এর group membership যোগ করার বদলে, শুধু list করা group গুলো দিয়ে *replace* করে দেয় — ফলে সেই user তার আগের অন্য প্রতিটা group থেকে সম্ভবত বাদ পড়ে যেতে পারে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. `su root` আর `sudo some-command`-এর practical পার্থক্য কী?</strong></summary>

`su root` root-এর নিজের password চায় এবং তোমাকে explicitly exit না করা পর্যন্ত একটা সম্পূর্ণ root session-এ পাঠিয়ে দেয় — তারপর যা-ই টাইপ করো সব root হিসেবে চলে। `sudo some-command` তোমার নিজের password চায় এবং শুধু সেই একটা command root হিসেবে চালায়, সাথে সাথে তোমাকে আবার normal user-এ ফিরিয়ে দেয়, action log করা সহ।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `id` যদি `groups=1002(dev),27(sudo)` দেখায়, `27(sudo)` অংশটা কী বলে?</strong></summary>

তোমার user `sudo` group-এর সদস্য, যা Ubuntu-based system-এ ঠিক এটাই দেয় যে `sudo` command ব্যবহার করার permission। এই group-এর সদস্য না হলে (বা `/etc/sudoers`-এ matching entry না থাকলে), `sudo` তোমার জন্য কিছুই চালাতে অস্বীকার করবে।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official reference: [Ubuntu Server Guide — User Management](https://ubuntu.com/server/docs/user-management)
- Official reference: [`sudo` manual](https://www.sudo.ws/docs/man/sudo.man/)

---

**পরের চ্যাপ্টার:** Chapter 5 — Processes আর Services (systemd) *(শীঘ্রই আসছে)*
