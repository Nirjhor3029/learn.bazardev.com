# Chapter 2 — Files আর Directories নিয়ে কাজ করা

> **Course:** Linux & Shell Basics · **Chapter:** 2 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-shell/02-files-and-directories)

Chapter 1 শিখিয়েছে কীভাবে *ঘুরে দেখতে হয়*। এই chapter শেখাবে আসলে কীভাবে কিছু *বানানো, পড়া, সরানো, এবং delete করা* হয় — server-এ তোমার বাকি সময়ে অন্য যেকোনো কিছুর চেয়ে এই command গুলোই বেশি টাইপ করবে।

## ১. File আর folder বানানো

```bash
mkdir my-project
```
একটা নতুন folder বানায়। একসাথে nested folder বানাতে (একটার পর একটা না করে):
```bash
mkdir -p my-project/src/components
```
`-p` path-এর প্রতিটা folder যা এখনো নেই তা বানিয়ে দেয়, কোনো অভিযোগ ছাড়াই।

```bash
touch notes.txt
```
একটা খালি file বানায় (বা, file আগে থেকেই থাকলে, content পরিবর্তন না করেই তার "last modified" time আপডেট করে)।

## ২. File-এর ভেতরে কী আছে পড়া

```bash
cat notes.txt
```
পুরো file সরাসরি স্ক্রিনে দেখায়। ছোট file-এর জন্য ভালো, বড় কিছুর জন্য অকার্যকর — এটা তোমার terminal শুধু ভরে ফেলবে।

```bash
less notes.txt
```
File-টা একবারে এক স্ক্রিন করে খোলে — arrow key বা space দিয়ে scroll করো, `/word` দিয়ে search করো, `q` দিয়ে বের হও। কয়েক লাইনের বেশি কিছুর জন্য এটাই তোমার দরকার।

```bash
head notes.txt           # প্রথম ১০ লাইন
tail notes.txt           # শেষ ১০ লাইন
tail -f /var/log/app.log # নতুন লাইন যোগ হওয়ার সাথে সাথে live দেখতে থাকা
```

শেষের `tail -f`, এটা নিজেই মনে রাখার মতো — কিছু ঘটার সময় একটা log file real time-এ দেখার ঠিক এই উপায়ে, Docker course-এর `docker logs -f`-এর একই ধারণা।

## ৩. Copy আর move করা

```bash
cp notes.txt notes-backup.txt
```
একটা file copy করে — এখন দুটো কপি আছে।

```bash
cp -r my-project my-project-backup
```
একটা **folder** copy করতে `-r` (recursive) লাগবেই — এটা ছাড়া, `cp` অস্বীকার করে, কারণ এটা guess করবে না তুমি ভেতরের সব কিছু copy করতে চেয়েছো কিনা।

```bash
mv notes.txt archive/notes.txt
```
একটা file (বা folder) অন্য কোথাও move করে। `mv` দিয়েই তুমি কিছু **rename** করো — একই folder-এ একটা file-কে নতুন নামে move করা *মানেই* rename করা:
```bash
mv notes.txt final-notes.txt
```

মনে রাখার মূল পার্থক্য: `cp` original জায়গায় রেখে একটা দ্বিতীয় copy বানায়; `mv` তা করে না — পরে file শুধু নতুন জায়গায়ই থাকে।

## ৪. Delete করা — এবং একটা গুরুত্বপূর্ণ warning

```bash
rm notes.txt
```
একটা file delete করে। Permanently — এটা ফিরে পাওয়ার জন্য কোনো recycle bin নেই।

```bash
rm -r my-project
```
`-r` একটা folder আর তার ভেতরের সব কিছু recursively delete করে।

```bash
rm -rf my-project
```
`-f` ("force") confirmation prompt skip করে এবং `-r`-এর উপর error ignore করে।

**এটা দুইবার পড়ো।** `rm -rf` প্রতিটা developer-এর জীবনে সবচেয়ে বেশি বলা "আমি নিজের server destroy করে ফেলেছি" গল্পগুলোর একটা, প্রায় সবসময় একই কারণে: ভুল folder-এ থেকে এটা চালানো, বা একটা typo যা একটা নির্দিষ্ট path-কে আরও বড় কিছুতে পরিণত করে। `rm -rf কিছু` চালানোর আগে:
- প্রথমে `pwd` চালাও, যাতে নিশ্চিত হও তুমি আসলে কোন folder-এ আছো।
- Exact path-এ প্রথমে `ls` চালাও, দেখতে তুমি আসলে কী delete করতে যাচ্ছো।
- কখনো `rm -rf` এমন path দিয়ে চালাবে না যা শুধু `/` দিয়ে শুরু হয়, বা যেখানে একটা extra space আছে (`rm -rf / home/user/old-folder` — `/`-এর পরের space খেয়াল করো — `rm -rf /home/user/old-folder`-এর চেয়ে সম্পূর্ণ আলাদা কিছু delete করে)।

এই কারণেই professional workflow (এটা সহ) destructive command-কে এমন কিছু হিসেবে দেখে যার জন্য থামা লাগে, autopilot-এ চালানোর মতো কিছু না।

## ৫. Wildcard: একসাথে অনেক file-এ কিছু করা

```bash
ls *.log          # .log দিয়ে শেষ হওয়া প্রতিটা file
rm *.tmp           # .tmp দিয়ে শেষ হওয়া প্রতিটা file delete করা
cp *.php backup/   # প্রতিটা .php file backup/-এ copy করা
```

`*` মানে "যা কিছু, যেকোনো length, কিছু না থাকাও সহ।" `?` মানে "ঠিক একটা character":
```bash
ls file?.txt      # file1.txt, fileA.txt-এর সাথে মিলে যায় — কিন্তু file10.txt-এর সাথে না
```

## ৬. কোথায় আছে না জানলে file খুঁজে বের করা

```bash
find . -name "*.log"
```
Current folder (`.`) থেকে শুরু করে নিচের দিকে, প্রতিটা subfolder দিয়ে, pattern-এর সাথে মিলে যাওয়া যা কিছু খোঁজে। এটা অন্য tool-এর চেয়ে ধীর কিন্তু এখনই, ঠিক যেখানে point করো সেখানে live search করে — কোথায় কিছু আছে নিশ্চিত না হলে সবচেয়ে reliable option।

```bash
which php
```
তোমাকে ঠিক বলে দেয় `php` টাইপ করলে কোন installed program চলবে — একাধিক version installed থাকলে এবং কোনটা active নিশ্চিত না হলে কাজে লাগে।

## ৭. মূল কথাগুলো (Key Takeaways)

- `mkdir -p` একটা command-এ nested folder বানায়; `touch` একটা খালি file বানায়।
- `cat` একবারে পুরো file দেখায়; `less` scroll করতে দেয়; `tail -f` একটা file live দেখে, ঠিক `docker logs -f`-এর মতো।
- `cp` original রেখে একটা copy বানায়; `mv` move করে (এবং এটা দিয়েই rename করা হয়)।
- `rm -rf` permanent আর recursive — এটা চালানোর আগে সবসময় তোমার current folder আর exact path confirm করো।
- `*` আর `?` একসাথে একাধিক file match করার wildcard; `find` pattern-এর সাথে মেলা file-এর জন্য filesystem live search করে।

## ৮. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. `cp` আর `mv`-এর পার্থক্য কী?</strong></summary>

`cp` original জায়গায় রেখে একটা file বা folder-এর দ্বিতীয় copy বানায় — পরে দুটো copy থাকে। `mv` file-টা relocate করে — পরে এটা শুধু নতুন জায়গায়ই থাকে। একটা file rename করা আসলে এটাকে একই folder-এ একটা নতুন নামে `mv` করাই।
</details>

<details>
<summary><strong>প্রশ্ন ২. `-r` যোগ না করলে `cp` কেন folder copy করতে অস্বীকার করে?</strong></summary>

একটা folder copy করা মানে তার ভেতরের সব কিছুও copy করা, যা অনেক file হতে পারে। `cp`-এর জন্য explicitly `-r` (recursive) flag লাগে, যাতে ভুল করে একটা বড় copy operation শুরু হয়ে না যায়।
</details>

<details>
<summary><strong>প্রশ্ন ৩. `rm -rf some-folder` চালানোর আগে, প্রথমে কোন দুটো বিষয় চেক করা উচিত?</strong></summary>

`pwd` চালিয়ে নিশ্চিত হও তুমি ঠিক কোন folder-এ আছো, এবং যেই exact path delete করতে যাচ্ছো তাতে `ls` চালিয়ে দেখো ঠিক কী remove হবে। `rm -rf` permanent এবং এর কোনো undo নেই, তাই দুটোই চালানোর আগে confirm করলে সবচেয়ে common "ভুলে ভুল কিছু delete করে ফেলা" ভুল গুলো আটকায়।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `tail -f /var/log/app.log` কী করে, আর এই ধারণা আগে কোথায় দেখেছো?</strong></summary>

এটা file-টা খোলা রাখে এবং নতুন লাইন যোগ হওয়ার সাথে সাথে live প্রিন্ট করে, একটা fixed snapshot দেখানোর বদলে। এটা Docker course-এর `docker logs -f`-এর একই ধারণা — পরে চেক করার বদলে real time-এ output হওয়া দেখা।
</details>

## ৯. ফ্রি রিসোর্স (English)

- Official reference: [GNU Coreutils manual](https://www.gnu.org/software/coreutils/manual/coreutils.html) (এই chapter-এর `cp`, `mv`, `rm`, `cat`, এবং বেশিরভাগ command cover করে)
- YouTube: **"The 50 Most Popular Linux & Terminal Commands - Full Course for Beginners"** — channel: *freeCodeCamp.org*

---

**পরের চ্যাপ্টার:** Chapter 3 — Permissions আর Ownership *(শীঘ্রই আসছে)*
