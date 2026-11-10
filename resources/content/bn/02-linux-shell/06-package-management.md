# Chapter 6 — Package Management (apt)

> **Course:** Linux & Shell Basics · **Chapter:** 6 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-shell/06-package-management)

Ubuntu বা Debian-এ (server-এর জন্য সবচেয়ে common দুটো Linux distribution) প্রতিবার software install করার সময়, তুমি almost নিশ্চিতভাবে `apt` ব্যবহার করছো। এই chapter এতে সবচেয়ে confusing command-এর জোড়াটা clear করে, আর বাকি যা আসলে ব্যবহার করবে তা দেখায়।

## ১. Package manager কেন, শুধু software download করার বদলে

একটা package manager ছাড়া, software install করা মানে manually একটা download খুঁজে বের করা, এটা তোমার system-এর সাথে compatible কিনা check করা, *এটার* dependency আগে install করা, এবং update নিজে track করা — প্রতিটা software-এর জন্য, সবসময়। একটা **package manager** এটা তোমার জন্য করে: কোনটা কীসের উপর নির্ভর করে জানে, তোমার system-এর জন্য সঠিক version নিয়ে আসে, এবং এক command দিয়ে সব update করতে দেয়।

`apt` (**A**dvanced **P**ackage **T**ool) হলো Ubuntu/Debian-এর package manager। ভিন্ন distribution-এ থাকলে, ধারণাটা একই কিন্তু tool-এর নাম আলাদা (Fedora/RHEL-এ `yum`/`dnf`, Arch-এ `pacman`) — একটা ভালোভাবে শিখলে, বাকিগুলো পরিচিত লাগবে।

## ২. `apt update` vs `apt upgrade` — Linux-এ সবচেয়ে confusing command-এর জোড়া

```bash
sudo apt update
```
এটা **কোনো** software install বা upgrade করে না। এটা শুধু তোমার configure করা repository থেকে কী package (আর কোন version) *available* তার local list refresh করে — একটা catalog refresh করা হিসেবে ভাবো, এটা থেকে কিছু কেনা না।

```bash
sudo apt upgrade
```
এটা আসলেই তোমার কাছে আগে থেকে থাকা package-গুলোর নতুন version install করে — কিন্তু এখন যেই list আছে সেটা দিয়ে। তুমি recently `update` না চালিয়ে থাকলে, `upgrade` একটা stale catalog দিয়ে কাজ করছে এবং হয়তো just release হওয়া কিছু miss করতে পারে।

![apt update vs upgrade diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/02-linux-shell/images/apt-update-vs-upgrade.svg)

**যে অভ্যাস গড়ে তুলতে হবে:** সবসময় দুটো একসাথে চালাও, `update` প্রথমে:
```bash
sudo apt update && sudo apt upgrade
```

## ৩. Software install আর remove করা

```bash
sudo apt install nginx
```
একটা package install করে — আর এটা যা কিছুর উপর নির্ভর করে তাও, automatically।

```bash
sudo apt remove nginx
```
এটা uninstall করে, কিন্তু configuration file রেখে দেয় (পরে আবার install করার plan থাকলে এবং setting রাখতে চাইলে useful)।

```bash
sudo apt purge nginx
```
এটা uninstall করে **এবং** configuration file-ও delete করে দেয় — একটা সম্পূর্ণ, clean removal।

```bash
sudo apt autoremove
```
এমন package পরিষ্কার করে যা শুধু অন্য কিছুর dependency হিসেবে install হয়েছিল, এবং এখন installed কোনো কিছুরই আর লাগে না — মাঝে মাঝে চালানো ভালো অভ্যাস।

## ৪. কী available বা installed আছে খুঁজে বের করা

```bash
apt search redis          # "redis"-এর সাথে মেলা package খোঁজা
apt list --installed      # এই মেশিনে এখন installed সব কিছু
apt show nginx             # একটা নির্দিষ্ট package নিয়ে বিস্তারিত — version, size, description
```

## ৫. Package আসলে কোথা থেকে আসে

তোমার system-এর বিশ্বাস করা **repository**-র একটা list আছে — package host করা server — `/etc/apt/sources.list`-এ (এবং `/etc/apt/sources.list.d/`-এর ভেতরের file গুলোতে)। `apt update` specifically *এই* জায়গাগুলোই আবার পড়ে তার catalog refresh করতে। একটা নতুন, trusted source (Ubuntu-তে একটা PPA, "Personal Package Archive") যোগ করা হলো কীভাবে তুমি default repository-তে নেই এমন নতুন বা extra software install করো:

```bash
sudo add-apt-repository ppa:some/repository
sudo apt update
```

## ৬. apt vs একটা `curl | sh` install script

Docker course-এ, Chapter 2-এ তোমাকে চালাতে বলা হয়েছিল:
```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
```
এটা `apt` **না** — এটা Docker নিজেদের একটা convenience script, যা তাদের নিজের repository সেট করে এবং তারপর (Ubuntu/Debian system-এ) আসলে কিছু install করার জন্য ভেতরে `apt` ব্যবহার করে। কোনো software তোমার distribution-এর official repository-তে available থাকলে, plain `apt install`-কে preference দাও — এটা audit, update, এবং clean remove করা সহজ। সরাসরি `sh`-এ pipe করা script এই safety net skip করে, তাই সেটা শুধু তখনই চালাও যখন source-টা তুমি আসলেই trust করো।

## ৭. মূল কথাগুলো (Key Takeaways)

- `apt update` available package version-এর catalog refresh করে; এটা নিজে কিছু install করে না।
- `apt upgrade` তোমার কাছে আগে থেকে থাকা কিছুর নতুন version install করে, এখন load করা catalog দিয়ে — সবসময় আগে `update` করো।
- `apt remove` software uninstall করে কিন্তু config রেখে দেয়; `apt purge` config-ও remove করে; `apt autoremove` এখন অপ্রয়োজনীয় dependency পরিষ্কার করে।
- Repository `/etc/apt/sources.list`-এ (এবং `sources.list.d/`-এ) configure করা — `update` ঠিক এখান থেকেই refresh করে।
- Software তোমার distribution-এর repository-তে available থাকলে `curl | sh` script-এর বদলে `apt install`-কে preference দাও — এটা audit আর manage করা সহজ।

## ৮. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. কখনো `apt update` না চালিয়ে `apt upgrade` চালালে, ঝুঁকিটা কী?</strong></summary>

`upgrade` এখন locally cache হওয়া package catalog-এর উপর ভিত্তি করে নতুন version install করে। সেই catalog stale থাকলে, `upgrade` শেষ `update`-এর পর release হওয়া package miss করতে পারে, বা outdated version information দিয়ে কাজ করতে পারে।
</details>

<details>
<summary><strong>প্রশ্ন ২. `apt remove` আর `apt purge`-এর পার্থক্য কী?</strong></summary>

`apt remove` package-এর program file uninstall করে কিন্তু configuration file disk-এ রেখে দেয়, পরে আবার install করলে এবং সেই setting ফিরে পেতে চাইলে। `apt purge` program আর configuration file দুটোই remove করে — একটা সম্পূর্ণ removal।
</details>

<details>
<summary><strong>প্রশ্ন ৩. `curl -fsSL https://some-site.com/install.sh | sh` কেন `apt install`-এর চেয়ে বেশি risky হতে পারে?</strong></summary>

`apt install` এমন একটা repository থেকে আনে যা তোমার system আগে থেকেই trust করতে configure করা, এবং এর content inspect করা যায় আর তোমার package manager এটা track করে (তাই পরে cleanly update বা remove করতে পারো)। একটা download করা script সরাসরি `sh`-এ pipe করলে, কোনো review step ছাড়াই, যে source দিয়েছে তা থেকে সাথে সাথে সেই script-এ যা আছে তা চলে।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `apt autoremove` আসলে কী পরিষ্কার করে?</strong></summary>

এমন package যা automatically অন্য কিছুর dependency হিসেবে install হয়েছিল, কিন্তু এখন system-এ installed কোনো কিছুরই আর লাগে না — পড়ে থাকা dependency যা আর কারো লাগে না।
</details>

## ৯. ফ্রি রিসোর্স (English)

- Official reference: [Ubuntu — apt command basics](https://ubuntu.com/server/docs/package-management)
- Official reference: [Debian Wiki — Apt](https://wiki.debian.org/Apt)

---

**পরের চ্যাপ্টার:** Chapter 7 — Pipes, Redirection, আর Text Tools *(শীঘ্রই আসছে)*
