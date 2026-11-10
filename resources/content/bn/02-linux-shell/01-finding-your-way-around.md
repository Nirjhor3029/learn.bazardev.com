# Chapter 1 — কেন Linux, আর নিজের জায়গা খুঁজে নেওয়া

> **Course:** Linux & Shell Basics · **Chapter:** 1 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-shell/01-finding-your-way-around)

Docker course-এর প্রতিটা chapter ধরে নিয়েছিল তুমি আগে থেকেই একটা terminal খুলে server-এ comfortably ঘুরতে পারো। এই course সেই জায়গায় ফিরে গিয়ে সেই skill-টা ঠিকমতো তৈরি করে — কারণ এটাই সেই একটা skill, যার উপর DevOps-এর বাকি সব topic নিঃশব্দে নির্ভর করে।

## ১. Server গুলো কেন Linux চালায়

তুমি developer হিসেবে যত server-এর সাথে কাজ করবে তার প্রায় সবগুলোই — এই course-টাই যেখানে হোস্ট করা, আগের course-এর Docker container গুলো যেখানে চলে, cloud VM, Raspberry Pi — সব Linux চালায়। Windows বা macOS খারাপ তা না, কিন্তু Linux ফ্রি, দীর্ঘসময় unattended চলা মেশিনের জন্য অসম্ভব স্থিতিশীল, আর মেনুতে ক্লিক করার বদলে text command দিয়ে সবকিছু automate করতে দেয়।

আসল কারণ, যা তোমার জন্য গুরুত্বপূর্ণ: **Linux-এ সব কিছু text command দিয়ে করা, script করা, এবং বারবার চালানো যায়।** এটাই server-কে automatable করে — আর repetitive কাজ automate করাটাই আসলে "DevOps" বলতে বেশিরভাগ বোঝায়।

## ২. Shell: ক্লিক করার বদলে টাইপ করা

**Shell** (যাকে terminal বা command line-ও বলে) এমন একটা program, যা তুমি যা টাইপ করো সেই command নেয়, চালায়, এবং result টা text হিসেবে দেখায়। এখানে কোনো desktop নেই, কোনো icon নেই — শুধু তুমি, একটা blink করা cursor, এবং তুমি যা টাইপ করো তাই।

প্রথমে এটা file manager-এ ক্লিক করার চেয়ে ধীর মনে হবে। এই chapter-এর হাতে গোনা কয়েকটা command জানার পর এটা কোনো GUI-এর চেয়ে দ্রুত হয়ে যায়, কারণ তোমাকে কখনো context পরিবর্তন করতে বা menu-তে খুঁজতে হয় না — তুমি যা চাও ঠিক তাই টাইপ করো।

## ৩. Filesystem: একটা গাছ, drive letter না

Windows থেকে আসলে, `C:\`, `D:\`, আলাদা drive letter ভুলে যাও। Linux-এ ঠিক **একটা** ফাইল আর ফোল্ডারের গাছ আছে, একটা root থেকে শুরু: `/`।

![Linux filesystem tree diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/02-linux-shell/images/filesystem-tree.svg)

কিছু ফোল্ডার যেগুলোর সাথে প্রায়ই দেখা হবে:

| ফোল্ডার | কী থাকে |
|---|---|
| `/home/<username>` | তোমার personal files — এটাই system-এর মধ্যে "তোমার কোণা" |
| `/etc` | System-এর প্রায় সব কিছুর configuration file |
| `/var` | Logs, database, আর system চলার সময় পরিবর্তন হওয়া অন্য data |
| `/usr` | Install করা program আর তাদের সাহায্যকারী file |
| `/tmp` | Temporary file — এখানে যা আছে reboot-এ মুছে যেতে পারে, তাই এখানে কখনো গুরুত্বপূর্ণ কিছু রাখবে না |

### Absolute vs relative path

একটা **absolute path** সবসময় `/` থেকে শুরু হয় এবং তুমি যেখানেই থাকো না কেন, ঠিক একই জায়গা বোঝায়:
```
/home/dev/projects/shop.bazardev.com
```

একটা **relative path** *তুমি এখন কোথায় আছো তার সাপেক্ষে* একটা জায়গা বোঝায়:
```
projects/shop.bazardev.com
```

তুমি যদি আগে থেকেই `/home/dev`-এর ভেতরে না থাকো, তাহলে এই relative path সম্পূর্ণ আলাদা কোথাও (বা কোথাও না) point করবে। সন্দেহ থাকলে — বিশেষ করে script-এ — absolute path ব্যবহার করো।

## ৪. মূল তিনটা: `pwd`, `ls`, `cd`

```bash
pwd
```
**P**rint **W**orking **D**irectory — তুমি filesystem-এ ঠিক কোথায় আছো তা বলে দেয়। হারিয়ে গেলে অনুভব করলে, এটাই সবার আগে চালানোর command।

```bash
ls
```
**L**i**s**t — তোমার এখনকার ফোল্ডারের ভেতরে কী আছে দেখায়। দুটো flag যা প্রায়ই লাগবে:
```bash
ls -l     # long format: permission, owner, size, modified date — প্রতি entry এক লাইনে
ls -a     # "all" — hidden file-ও দেখায় (dot দিয়ে শুরু হওয়া নাম, যেমন .env বা .gitignore)
ls -la    # দুটো একসাথে, যা তুমি সবচেয়ে বেশি টাইপ করবে
```

```bash
cd some-folder
```
**C**hange **D**irectory — তোমাকে `some-folder`-এর ভেতরে নিয়ে যায়। মনে রাখার মতো কিছু shortcut:

| Command | কোথায় যায় |
|---|---|
| `cd ..` | এক ফোল্ডার **উপরে** |
| `cd ~` | তোমার home ফোল্ডার, যেখান থেকেই চালাও |
| `cd -` | তুমি যেখানে **আগে** ছিলে (দুটো জায়গার মধ্যে দ্রুত যাওয়া-আসার উপায়) |
| `cd /` | সম্পূর্ণভাবে root-এ |

## ৫. নিজে করে দেখো

```bash
pwd                      # আমি কোথায়?
cd /                      # root-এ যাও
ls -la                    # এখানে সব কিছু দেখো, hidden file-ও
cd home                   # /home-এর ভেতরে যাও
ls                        # কোন user-দের home ফোল্ডার আছে দেখো
cd ~                      # সরাসরি নিজের home ফোল্ডারে ফিরে যাও
pwd                       # confirm করো ফিরে এসেছো
```

ধীরে ধীরে করো, আসলেই প্রতিটা command টাইপ করে, প্রতিটার পর কী পরিবর্তন হলো দেখো। এই ছোট loop-টা — `pwd`, `ls`, `cd`, আবার — এই course-এর বাকি অংশে তুমি যেভাবে কোনো server-এ ঘুরবে তার ৮০%।

## ৬. মূল কথাগুলো (Key Takeaways)

- Linux-এ একটা filesystem tree আছে, `/` থেকে শুরু, আলাদা drive letter না।
- Absolute path `/` দিয়ে শুরু হয় এবং সবসময় একই জায়গা বোঝায়; relative path তুমি এখন কোথায় আছো তার উপর নির্ভর করে।
- `pwd` বলে তুমি কোথায়, `ls` দেখায় কী আছে, `cd` তোমাকে অন্য কোথাও নিয়ে যায়।
- `ls -la` হলো `ls`-এর সেই version যা তুমি আসলে সবচেয়ে বেশি ব্যবহার করবে — long format, hidden file সহ।
- `cd ~`, `cd ..`, আর `cd -` — এই তিনটা shortcut এখনই মনে রাখার মতো।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. Absolute path আর relative path-এর পার্থক্য কী?</strong></summary>

Absolute path `/` দিয়ে শুরু হয় এবং তুমি এখন যেখানেই থাকো, সবসময় ঠিক একই জায়গা বোঝায়। Relative path `/` দিয়ে শুরু হয় না এবং তোমার এখনকার ফোল্ডারের সাপেক্ষে interpret করা হয় — একই relative path কোথা থেকে চালাচ্ছো তার উপর নির্ভর করে আলাদা জায়গা বোঝাতে পারে।
</details>

<details>
<summary><strong>প্রশ্ন ২. হারিয়ে গেলে, filesystem-এ কোথায় আছো না জানলে, প্রথমে কোন command চালাবে?</strong></summary>

`pwd` (print working directory) — এটা সাথে সাথেই তোমার এখনকার সঠিক জায়গা একটা absolute path হিসেবে বলে দেয়।
</details>

<details>
<summary><strong>প্রশ্ন ৩. `ls -la` যা দেখায় তা plain `ls` কেন দেখায় না?</strong></summary>

`-l` flag একটা বিস্তারিত, প্রতি-entry-এক-লাইন format-এ পাল্টে দেয়, যেখানে permission, owner, size, আর modified date দেখা যায়। `-a` flag অতিরিক্তভাবে hidden file দেখায় — dot দিয়ে শুরু হওয়া নাম, যেমন `.env` বা `.gitignore` — যা plain `ls` default-এ hide করে।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `cd -` কী করে, আর এটা কখন কাজে লাগে?</strong></summary>

এটা তোমাকে ঠিক আগের ফোল্ডারে ফিরিয়ে নিয়ে যায়, যেখানে তুমি এখনকারটার ঠিক আগে ছিলে — দুটো জায়গার মধ্যে প্রতিবার পুরো path টাইপ না করে দ্রুত toggle করার উপায়।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official reference: [The Linux Filesystem Hierarchy Standard](https://refspecs.linuxfoundation.org/FHS_3.0/fhs/index.html)
- YouTube: **"The 50 Most Popular Linux & Terminal Commands - Full Course for Beginners"** — channel: *freeCodeCamp.org*

---

**পরের চ্যাপ্টার:** Chapter 2 — Files আর Directories নিয়ে কাজ করা *(শীঘ্রই আসছে)*
