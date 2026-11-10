# Chapter 7 — Pipes, Redirection, আর Text Tools

> **Course:** Linux & Shell Basics · **Chapter:** 7 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-shell/07-pipes-redirection-text-tools)

এই chapter-এ shell "একবারে একটা command টাইপ করা" মনে হওয়া বন্ধ হয়ে যায় এবং আসল power-এর মতো লাগতে শুরু করে: ছোট ছোট, simple tool একসাথে জুড়ে এমন প্রশ্নের উত্তর দেওয়া যা একটা command দিয়ে দেওয়া যায় না।

## ১. Pipe (`|`) — shell-এর সবচেয়ে useful character

```bash
cat app.log | grep "ERROR" | wc -l
```

![Pipes and redirection diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/02-linux-shell/images/pipes-and-redirection.svg)

এই command-টা ঠিক left থেকে right পড়ো: "আমাকে file দেখাও, **তারপর** ERROR থাকা লাইন গুলোই রাখো, **তারপর** কয়টা লাইন বাকি আছে তা গুনো।" প্রতিটা `|` তার বামের command-এর *output* নেয় এবং সেটা ডানের command-এর *input* হিসেবে দিয়ে দেয়। এই তিনটা tool-এর কেউ একে অপরকে জানে না বা care করে না — `grep` জানে না তার input `cat` থেকে এসেছে। এটাই Linux-এর command-line tool-এর পুরো philosophy: ছোট, focused program, যেকোনো order-এ combine করে তোমার আসল সমস্যার সমাধান করা।

## ২. Redirection — স্ক্রিনের বদলে output অন্য কোথাও পাঠানো

```bash
ls -la > filelist.txt
```
`>` একটা command-এর output একটা file-এ পাঠায়, আগে যা ছিল তা **overwrite** করে।

```bash
echo "new entry" >> filelist.txt
```
`>>` overwrite না করে file-এর শেষে **append** করে — আগে থেকে থাকা কিছু ধ্বংস করতে না চাইলে এটা মনে রাখার মতো।

```bash
some-command 2> errors.txt
```
`2>` শুধু **error** output (normal output না) একটা file-এ redirect করে — স্ক্রিনে normal result দেখতে চাইলেও error আলাদা record রাখতে চাইলে কাজে লাগে।

```bash
some-command > output.txt 2>&1
```
Normal output আর error দুটোই একই file-এ পাঠায়।

## ৩. `grep` — মেলা লাইন খুঁজে বের করা

```bash
grep "ERROR" app.log          # "ERROR" থাকা লাইন
grep -i "error" app.log        # -i: case-insensitive, "Error", "ERROR", "error" সব মেলে
grep -n "ERROR" app.log        # -n: line number-ও দেখায়
grep -v "DEBUG" app.log         # -v: invert — যেগুলো মেলে না সেই লাইন দেখায়
grep -r "TODO" .                # -r: প্রতিটা subfolder-এর প্রতিটা file-এ recursively খোঁজা
```

Text খোঁজার সময় `grep` almost সবসময় প্রথম tool, আর প্রায়ই একমাত্র tool যা লাগে।

## ৪. `sed` — editor না খুলেই find এবং replace

```bash
sed 's/old-text/new-text/g' file.txt
```
`old-text`-এর প্রতিটা occurrence (`g` = global) `new-text` দিয়ে replace করে, আর result প্রিন্ট করে (file পরিবর্তন করে না যদি না `-i` যোগ করো in place edit করতে):
```bash
sed -i 's/old-text/new-text/g' file.txt
```
`sed` আরও অনেক কিছু করতে পারে, কিন্তু "একটা file-এ find এবং replace" এটা ব্যবহার করার ৯০% কারণ।

## ৫. `awk` — নির্দিষ্ট column বের করে আনা

```bash
awk '{print $1}' access.log
```
প্রতিটা লাইনের প্রথম "column" (whitespace দিয়ে আলাদা করা field) শুধু প্রিন্ট করে — log file-এর জন্য extremely কাজে লাগে, যেখানে, ধরো, প্রতিটা লাইনের প্রথম word একটা IP address বা timestamp। `$1`, `$2`, `$3`... মানে ১ম, ২য়, ৩য় field।

## ৬. `sort` আর `uniq` — একটা pipeline classic

```bash
cat visitors.txt | sort | uniq -c | sort -rn
```
আবার left থেকে right পড়ো: লাইন গুলো alphabetically sort করো (`uniq` সঠিকভাবে কাজ করতে sorted input চায়), duplicate লাইন গুলো একটা count-সহ একটায় collapse করো (`uniq -c`), তারপর সেই count দিয়ে sort করো, সবচেয়ে বেশি আগে (`sort -rn`)। এই exact pipeline — sort, duplicate count, count দিয়ে sort — "এই file-এ সবচেয়ে বেশি কোন X" এর উত্তর দেওয়ার সবচেয়ে common pattern।

## ৭. Environment variable

```bash
echo $HOME          # একটা variable-এর মান প্রিন্ট করা
export API_KEY=abc123    # এই session-এর জন্য (এবং এটা থেকে চালু হওয়া যা কিছুর জন্য) একটা সেট করা
```

তোমার terminal-এ `export` করা variable, terminal বন্ধ করলে হারিয়ে যায়। একটাকে permanent করতে, `export` লাইনটা `~/.bashrc`-এ যোগ করো (এটা তুমি যখনই একটা নতুন terminal খোলো automatically চলে)।

নাম দিয়ে জানার মতো একটা environment variable: `$PATH`। এটা ফোল্ডারের একটা list, যা shell তুমি একটা command নাম টাইপ করলে, order অনুযায়ী খোঁজে — `docker` বা `php` টাইপ করলে তুমি পুরো location টাইপ না করেও সঠিক program খুঁজে পাওয়ার এটাই কারণ।

## ৮. একসাথে করে দেখা

```bash
cat access.log | awk '{print $1}' | sort | uniq -c | sort -rn | head -5
```

একটা লাইনে: একটা web server-এর access log-এর প্রতিটা লাইন থেকে IP address (প্রথম column) বের করো, তারপর সবচেয়ে বেশি ৫টা visitor খুঁজে বের করো। প্রতিটা অংশ নিজে নিজে simple; `|` দিয়ে একসাথে জুড়ে, তারা এমন একটা genuinely useful প্রশ্নের উত্তর দেয় যা কোনো একটা command দিতে পারতো না।

## ৯. মূল কথাগুলো (Key Takeaways)

- `|` একটা command-এর output সরাসরি পরের command-এর input-এ feed করে — simple tool-কে কিছু powerful-এ জুড়ে দেওয়ার মূল ধারণা।
- `>` একটা command-এর output দিয়ে একটা file overwrite করে; `>>` এর বদলে append করে।
- `grep` মেলা লাইন খুঁজে বের করে (`-i` case-insensitive, `-v` invert, `-r` recursive); `sed 's/old/new/g'` text find করে replace করে; `awk '{print $N}'` একটা নির্দিষ্ট column বের করে আনে।
- `sort | uniq -c | sort -rn` হলো "এই file-এ সবচেয়ে common value কী" এর জন্য classic pipeline।
- `export VAR=value` তোমার session-এর জন্য একটা environment variable সেট করে; permanent করতে `~/.bashrc`-এ যোগ করো; `$PATH` হলো command-এর জন্য shell খোঁজা ফোল্ডারের list।

## ১০. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. `cat app.log | grep "ERROR" | wc -l`-এ, প্রতিটা pipe দিয়ে ঠিক কী বয়ে যায়?</strong></summary>

`cat app.log` file-এর প্রতিটা লাইন output করে। সেই পুরো output `grep`-এর input হয়ে যায়, যা এটাকে ফিল্টার করে শুধু "ERROR" থাকা লাইনে নামিয়ে আনে। সেই filtered output `wc -l`-এর input হয়ে যায়, যা কতগুলো লাইন পেয়েছে তা গোনে — শেষে প্রিন্ট হওয়া number-টাই মেলা লাইনের count।
</details>

<details>
<summary><strong>প্রশ্ন ২. `>` আর `>>`-এর আসল পার্থক্য কী?</strong></summary>

`>` command-এর output দিয়ে পুরো target file overwrite করে — file-এ আগে যা ছিল সব হারিয়ে যায়। `>>` এর বদলে output file-এর শেষে append করে, আগে থেকে যা ছিল তা রেখে দেয়।
</details>

<details>
<summary><strong>প্রশ্ন ৩. `uniq -c`-এর input কেন আগে sorted থাকতে হবে?</strong></summary>

`uniq` শুধু *পরপর* থাকা duplicate লাইন collapse করে — এটা input-এর যেকোনো জায়গায় match স্ক্যান করে না। একই রকম লাইন একে অপরের পাশে না থাকলে (যা `sort` তাদের একসাথে group করে guarantee করে), `uniq` তাদের duplicate হিসেবে চিনবে না।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `$PATH` environment variable আসলে কী করে?</strong></summary>

এটা ফোল্ডারের একটা list, order অনুযায়ী, যা shell তুমি টাইপ করা command নামের পেছনের program খুঁজতে অনুসন্ধান করে। এই কারণেই `docker` টাইপ করলে, প্রতিবার পুরো file path টাইপ না করেই, actual Docker program চলে।
</details>

## ১১. ফ্রি রিসোর্স (English)

- Official reference: [GNU Grep manual](https://www.gnu.org/software/grep/manual/grep.html)
- Official reference: [GNU Sed manual](https://www.gnu.org/software/sed/manual/sed.html)
- Official reference: [GNU Awk User's Guide](https://www.gnu.org/software/gawk/manual/gawk.html)

---

**পরের চ্যাপ্টার:** Chapter 8 — SSH আর Remote Access *(শীঘ্রই আসছে)*
