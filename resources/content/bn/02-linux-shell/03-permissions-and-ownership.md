# Chapter 3 — Permissions আর Ownership

> **Course:** Linux & Shell Basics · **Chapter:** 3 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-shell/03-permissions-and-ownership)

Docker course-এর শেষ chapter করে থাকলে, তুমি ঠিক এই topic-টার সাথেই দেখা করেছো: Laravel-এর `storage/` ফোল্ডারে PHP-FPM write করার আগে সঠিক ownership দরকার ছিল। এই chapter ব্যাখ্যা করে *কেন* এটা এভাবে কাজ করে — এটা একবার বুঝে গেলে, "permission denied" ভয়ংকর থাকে না, একটা দুই-সেকেন্ডের fix হয়ে যায়।

## ১. Linux কেন তুমি কে তা নিয়ে এত সচেতন

Linux শুরু থেকেই একটা **multi-user** system হিসেবে বানানো — অনেক মানুষ (বা অনেক process, যেমন একটা web server) একটা মেশিন নিরাপদে share করে। এটা কাজ করার জন্য, প্রতিটা file-এর তিনটা প্রশ্নের উত্তর থাকতে হবে: কে এটার মালিক, কোন group এটা touch করতে পারে, এবং বাকি সবাই এটা দিয়ে কী করতে পারে।

## ২. `ls -l` পড়া — permission string decode করা

```bash
ls -l
```
```
-rwxr-xr-- 1 dev www-data 1024 Nov 10 10:00 deploy.sh
```

![Permissions model diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/02-linux-shell/images/permissions-model.svg)

`-rwxr-xr--` কে ভেঙে দেখি:

- **প্রথম character** — type: regular file-এর জন্য `-`, directory-র জন্য `d`, symbolic link-এর জন্য `l`।
- **পরের তিনটা** (`rwx`) — **owner** (`dev`) কী করতে পারে: read, write, execute।
- **পরের তিনটা** (`r-x`) — **group** (`www-data`) কী করতে পারে: read আর execute, কিন্তু write না।
- **শেষ তিনটা** (`r--`) — **বাকি সবাই** কী করতে পারে: শুধু read।

**r**ead, **w**rite, এবং e**x**ecute file আর folder-এর জন্য একটু আলাদা মানে বহন করে:

| | File-এ | Folder-এ |
|---|---|---|
| `r` | এর content দেখা যায় | ভেতরে কী আছে list করা যায় (`ls`) |
| `w` | এটা modify বা delete করা যায় | ভেতরে create/delete/rename করা যায় |
| `x` | এটা একটা program/script হিসেবে চালানো যায় | এর ভেতরে `cd` করা যায় |

শেষের row প্রথমবার সবাইকে surprised করে: **একটা folder-এ execute permission মানে এটার ভেতরে ঢুকতে দেওয়া**, "চালানো" না।

## ৩. `chmod` — কী allowed তা পরিবর্তন করা

### Octal উপায় (যেটা সবচেয়ে বেশি ব্যবহার করবে)

প্রতিটা permission একটা number: `r = 4`, `w = 2`, `x = 1`। প্রতি scope-এ যোগ করলে, একটা ৩-digit code পাও:

```bash
chmod 644 notes.txt     # rw-r--r--  — owner edit করে, বাকি সবাই শুধু read
chmod 755 deploy.sh     # rwxr-xr-x  — owner edit করে, সবাই read/run করতে পারে
chmod 600 secrets.env   # rw-------  — শুধু owner-ই পড়তে পারে
```

- **644** সাধারণ file-এর জন্য standard default।
- **755** folder আর script-এর জন্য standard default (`x` বাকি সবাইকে folder-এ ঢুকতে বা script চালাতে দেয়)।
- **600** বা **400** sensitive কিছুর জন্য, যেমন একটা private SSH key।

**কখনো `777` ব্যবহার করবে না** (`rwxrwxrwx`) permission error-এর একটা casual fix হিসেবে। এটার মানে system-এ *যে কেউ* — ঢোকার একটা উপায় খুঁজে পাওয়া কোনো attacker-ও — সেই file read, modify, এবং execute করতে পারবে। `chmod 644` বা `755` তোমার আসল সমস্যা সমাধান না করলে, real fix প্রায় সবসময়ই একটা **chown** (পরের section), আরও বেশি open করা `chmod` না।

### Symbolic উপায় (ছোট পরিবর্তনের জন্য বেশি readable)

```bash
chmod u+x deploy.sh     # owner-এর জন্য execute যোগ করা (u = user/owner)
chmod g-w notes.txt      # group-এর জন্য write সরানো
chmod o=r notes.txt       # others-কে ঠিক read-only সেট করা
```

`u`, `g`, `o` owner/group/others-এর জন্য; `+`, `-`, `=` যোগ, বাদ, বা ঠিক সেট করার জন্য।

## ৪. `chown` আর `chgrp` — মালিকানা পরিবর্তন করা

```bash
sudo chown dev notes.txt           # owner পরিবর্তন করো "dev"-এ
sudo chgrp www-data notes.txt      # group পরিবর্তন করো "www-data"-এ
sudo chown dev:www-data notes.txt  # দুটোই একসাথে (owner:group)
```

এটাই Docker course-এর Laravel chapter-এর ঠিক সেই pattern: একটা web server process তার নিজের user হিসেবে চলে (প্রায়ই `www-data`), এবং এটাকে তোমার `storage/` ফোল্ডারে write করার অনুমতি দিতে, সেই ফোল্ডারের **group**-কে `www-data` হতে হবে, group-write permission সহ (`chmod 775`)। এই combination — `chown ... :www-data` সাথে `chmod 775` — server-এ তুমি টাইপ করবে এমন সবচেয়ে common real-world fix-গুলোর একটা।

## ৫. নিজে করে দেখো

```bash
mkdir test-permissions && cd test-permissions
touch file.txt
ls -l file.txt              # default permission দেখো
chmod 600 file.txt
ls -l file.txt               # এখন শুধু তুমিই read/write করতে পারো
chmod 644 file.txt
ls -l file.txt               # normal default-এ ফিরে গেলো
```

## ৬. মূল কথাগুলো (Key Takeaways)

- প্রতিটা file-এর তিনটা permission scope আছে: owner, group, আর others — প্রতিটা read, write, এবং/বা execute করতে পারে।
- `chmod 644` file-এর জন্য normal default; `755` folder আর script-এর জন্য; কখনো `777`-এর দিকে যাবে না।
- একটা **folder**-এ execute permission মানে "ভেতরে ঢুকতে পারা," "চালাতে পারা" না।
- `chown user:group` কে file-এর মালিক এবং কোন group-এর অন্তর্গত তা পরিবর্তন করে — শুধু `chmod` দিয়ে সমাধান না হওয়া বেশিরভাগ "permission denied" সমস্যার real fix।
- Web-server-এর group-write লাগার pattern (`chown :www-data` + `chmod 775`) যেকোনো real server-এ তুমি করবে এমন সবচেয়ে common fix-গুলোর একটা।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. `chmod 755` আসলে কী সেট করে, আর folder-এর জন্য এটা কেন common?</strong></summary>

এটা owner-এর জন্য `rwx` (7) সেট করে, এবং group আর others দুটোর জন্যই `r-x` (5)। একটা folder-এ, `x` bit হলো যা বাকিদের সেটার ভেতরে `cd` করতে আর content list করতে দেয় — যা বেশিরভাগ folder-কে দিতে হবে, owner-এর বাইরে কাউকে ভেতরের কিছু modify করতে না দিয়ে।
</details>

<details>
<summary><strong>প্রশ্ন ২. `chmod 777` কেন বিপজ্জনক বলে ধরা হয়?</strong></summary>

এটা সাহিত্যিকভাবে system-এর সবাইকে — owner, group, এবং others সবাইকে — read, write, এবং execute দেয়। কোনো attacker মেশিনে যেকোনো ধরনের access পেলে, একটা `777` file বা folder এমন কিছু যা তারা অবাধে modify করতে বা তার থেকে code চালাতে পারে। সত্যিকারের permission সমস্যার almost সবসময় একটা narrower, সঠিক fix থাকে (প্রায়ই একটা `chown`), একটা wide-open `chmod` না।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা web server তোমার app-এর `storage/` ফোল্ডারে write করতে পারছে না। সম্ভবত two-step fix কী?</strong></summary>

ফোল্ডারের group-কে web server-এর user/group-এ পরিবর্তন করো (যেমন `sudo chgrp www-data storage`), তারপর group-কে write permission দাও (`chmod 775 storage` বা similar)। এটা web server process-কে group member হিসেবে write করতে দেয়, ফোল্ডারকে world-writable না করেই।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একটা folder-এ, execute (`x`) permission আসলে কী নিয়ন্ত্রণ করে?</strong></summary>

তুমি সেই ফোল্ডারে `cd` করতে পারবে কিনা (বা নামে করে ভেতরের কিছু access করতে পারবে কিনা) — এটার মানে "ফোল্ডার চালানো" না। Folder-এ execute permission না থাকলে, এমনকি read permission থাকলেও তুমি আসলে ভেতরের কিছুতে ঢুকতে বা ব্যবহার করতে পারবে না।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official reference: [GNU Coreutils — `chmod` manual](https://www.gnu.org/software/coreutils/manual/html_node/chmod-invocation.html)
- Official reference: [GNU Coreutils — `chown` manual](https://www.gnu.org/software/coreutils/manual/html_node/chown-invocation.html)

---

**পরের চ্যাপ্টার:** Chapter 4 — Users, Groups, আর sudo *(শীঘ্রই আসছে)*
