# Chapter 3 — Merge Conflict সমাধান করা

> **Course:** Git & GitHub (Advanced) · **Chapter:** 3 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/git-github/03-resolving-merge-conflicts)

প্রথম কয়েকবার conflict ভয়ংকর মনে হয়। এটা তুমি কিছু ভেঙে ফেলেছো এমন কোনো signal না — Git সৎভাবে তোমাকে বলছে এটা *একই লাইনে* দুটো পরিবর্তন পেয়েছে, আর কোনটা তুমি চাও তা guess করতে রাজি না। হাতে কয়েকটা resolve করে ফেললে, এটা সম্পূর্ণ mechanical একটা পাঁচ-মিনিটের কাজ হয়ে যায়।

## ১. কেন একটা conflict আসলে হয়

বেশিরভাগ merge-এ কোনো human input লাগে না — তোমার branch যদি একটা file-এর line 10 পরিবর্তন করে আর `main` line 80 পরিবর্তন করে, Git দুটো পরিবর্তনই automatically merge করে ফেলে, কোনো conflict ছাড়াই। একটা conflict তখনই হয় যখন **দুটো branch-ই একই file-এর একই (বা খুব কাছাকাছি) লাইন পরিবর্তন করেছে**, আর Git-এর কোনো উপায় নেই জানার তুমি আসলে কোন version — বা কী combination — চাও।

```bash
git switch main
git merge feature/longer-timeout
# Auto-merging config.js
# CONFLICT (content): Merge conflict in config.js
# Automatic merge failed; fix conflicts and then commit the result.
```

Git **সব** progress থামিয়ে দেয় না — যে file গুলো পারে সেগুলো successfully merge করে ফেলে, আর শুধু যেগুলো আসলেই decide করতে পারে না সেগুলোতে pause করে। Conflict-এর ঠিক পরে `git status` চালাও দেখতে কোন file গুলোতে তোমার attention লাগবে ঠিক; বাকি সবকিছু already হয়ে গেছে।

## ২. Conflict marker পড়া

Conflicted file টা open করো। Git সরাসরি file-এর ভেতরে marker বসিয়ে দিয়েছে, দুটো version পাশাপাশি দেখাচ্ছে:

![Conflict markers diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/03-git-github/images/conflict-markers.svg)

- `<<<<<<< HEAD` — এর নিচে, `=======` পর্যন্ত যা আছে সব **তোমার current branch-এর** version।
- `=======` — দুটো version-এর মাঝের dividing line।
- `>>>>>>> feature/longer-timeout` — এর উপরে, `=======` থেকে যা আছে সব **incoming branch-এর** version। `>>>>>>>`-এর পরের নামটা ঠিক কোন branch থেকে এসেছে তা বলে দেয়।

এই marker গুলো Git তোমার file-এর ভেতরে লিখে দেওয়া plain text — এটা file নিজে বোঝে এমন কোনো special syntax না। তুমি যদি এখন এই অবস্থায় code run করো, almost certainly একটা syntax error আসবে।

## ৩. আসলে কীভাবে resolve করবে

Resolution একটা তিন-step loop, প্রতিটা conflicted file-এর জন্য repeat করতে হয়:

1. **File open করে decide করো final code কী হওয়া উচিত।** কখনো এটা পুরোপুরি "আমার টাই রাখো", কখনো "ওদেরটাই রাখো", আর কখনো আসলে কোনোটাই না — তোমার আসলেই দুটো পরিবর্তন combine করা লাগবে (যেমন, একটা branch একটা নতুন function যোগ করেছে, অন্যটা সেই function যে variable ব্যবহার করে তার নাম পরিবর্তন করেছে)।
2. **তিনটা marker লাইনই delete করো** (`<<<<<<<`, `=======`, `>>>>>>>`), সাথে যে version বাদ দিচ্ছো সেটাও, শুধু decide করা code টুকু রেখে।
3. **Git-কে জানাও এটা resolve হয়ে গেছে:**
   ```bash
   git add config.js
   ```
   এখানে `git add` মানে "একটা নতুন পরিবর্তন stage করো" না — এটা specifically Git-কে বলে "আমি এই file-এর conflict resolve করে ফেলেছি।"

প্রতিটা conflicted file `git add` করা হয়ে গেলে, operation টা finish করো:

```bash
# এই conflict যদি একটা merge থেকে এসে থাকে:
git commit

# এই conflict যদি একটা rebase থেকে এসে থাকে:
git rebase --continue
```

একটা merge conflict finish করতে একটা `git commit` লাগে। একটা rebase conflict **replay হওয়া প্রতিটা commit-এ একবার** হতে পারে — একটা resolve করার পর, `git rebase --continue` এগিয়ে যায় আর হয়তো সাথে সাথেই *পরের* commit replay করার সময় আরেকটা conflict-এ পড়তে পারে। এটা normal; rebase শেষ না হওয়া পর্যন্ত loop টা repeat করো।

## ৪. মন পরিবর্তন হয়েছে? Abort করে আবার শুরু করো

Conflict resolution যদি খারাপভাবে চলতে থাকে, বা তুমি শুধু একটা clear working tree নিয়ে ভাবতে চাও, তুমি কখনো আটকে থাকবে না:

```bash
git merge --abort      # চলমান merge cancel করে, আগের মতোই সবকিছু restore করে
git rebase --abort     # চলমান rebase cancel করে, আগের মতোই সবকিছু restore করে
```

দুটোই সম্পূর্ণ safe — এগুলো অসম্পূর্ণ merge/rebase attempt টা ছাড়া আর কিছুই হারায় না। তোমার original commit গুলো untouched থাকে।

## ৫. এটা সহজ করে দেয় এমন কিছু tool

- **তোমার editor-এর merge view।** VS Code (আর বেশিরভাগ modern editor) automatically conflict marker detect করে আর প্রতিটা conflict-এর ঠিক উপরে "Accept Current Change / Accept Incoming Change / Accept Both Changes" button দেখায় — বেশিরভাগ ক্ষেত্রে marker হাতে edit করা লাগে না।
- **`git mergetool`** তোমার system-এ configure করা যেকোনো diff/merge tool launch করে আরও visual একটা three-way comparison-এর জন্য।
- **`git diff`** (conflict unresolved থাকা অবস্থায়) তোমাকে একটা combined diff format দেখায় যা মার্ক করে দেয় কোন side থেকে কোন পরিবর্তন এসেছে।
- **`git log --merge`** conflict-এ থাকা file গুলোর সাথে relevant commit গুলোই শুধু দেখায়, যদি তোমার আরও context লাগে *কেন* প্রতিটা side কী পরিবর্তন করেছে তা বুঝতে।

## ৬. একটা tip যা বেশিরভাগ conflict হওয়ার আগেই prevent করে

বেশিরভাগ কষ্টদায়ক conflict আসে এমন একটা feature branch থেকে যা দীর্ঘদিন `main` touch না করে বেঁচে আছে। এর fix সরাসরি [Chapter 1](/git-github/01-branching-strategies) আর [Chapter 2](/git-github/02-merge-vs-rebase)-এর সাথে connect করে: feature branch গুলো **short-lived** রাখো, আর কাজ করার সময় নিয়মিত `main` কে তোমার branch-এ নিয়ে আসো (merge বা rebase দিয়ে, Chapter 2-এর নিয়ম মেনে) — একটু একটু করে ছোট conflict তাড়াতাড়ি resolve করা সপ্তাহের পর সপ্তাহ divergence-এর পর একটা huge conflict resolve করার চেয়ে অনেক সহজ।

## ৭. মূল কথাগুলো (Key Takeaways)

- একটা conflict তখনই হয় যখন দুটো branch-ই একই file-এর একই লাইন পরিবর্তন করেছে — বাকি সবকিছু automatically merge হয়ে যায়।
- `git status` ঠিক কোন file গুলোতে manual resolution লাগবে বলে দেয়।
- Conflict marker (`<<<<<<<`, `=======`, `>>>>>>>`) হলো Git-এর বসিয়ে দেওয়া plain text — সবগুলো সরিয়ে দাও, শুধু final code যা তুমি চাও রেখে।
- `git add <file>` একটা file-এর conflict resolved মার্ক করে; `git commit` একটা merge finish করে, `git rebase --continue` একটা rebase finish করে (আর প্রতি commit-এ repeat হতে পারে)।
- `git merge --abort` / `git rebase --abort` সম্পূর্ণ operation safely cancel করে আর তোমার আগের state restore করে — ভয় না পেয়ে এগুলো ব্যবহার করো।
- Short-lived branch, নিয়মিত `main`-এর সাথে sync করা, সেটাই আসল fix — একটা huge conflict-এর চেয়ে ছোট ছোট frequent conflict অনেক ভালো।

## ৮. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. কিছু merge-এ Git কেন conflict দেয় আর কিছুতে দেয় না?</strong></summary>

Git automatically merge করতে পারে যখনই দুটো branch একটা file-এর (বা সম্পূর্ণ আলাদা file-এর) আলাদা আলাদা অংশ পরিবর্তন করেছে। এটা conflict দেয় শুধু তখন যখন দুটো branch-ই একই file-এর একই (বা খুব কাছাকাছি) লাইন পরিবর্তন করেছে, কারণ কোন version — বা দুটোর কী combination — ঠিক তা decide করার কোনো safe উপায় এর নেই।
</details>

<details>
<summary><strong>প্রশ্ন ২. একটা conflict marker block-এ, `>>>>>>>`-এর ঠিক পরের text তোমাকে কী বলে?</strong></summary>

এটা সেই branch-এর নাম বলে যেখান থেকে তার উপরের content এসেছে (`=======` আর `>>>>>>>`-এর মাঝে) — conflict-এর "incoming" side, `HEAD`-এর বিপরীতে, যা তোমার current branch-এর version।
</details>

<details>
<summary><strong>প্রশ্ন ৩. `git rebase` থেকে আসা একটা conflict resolve করার পর, `git rebase --continue` চালানোর পরপরই কেন আরেকটা conflict হতে পারে?</strong></summary>

একটা rebase commit গুলোকে একটা একটা করে নতুন base-এর উপর replay করে। একটা conflict resolve করা শুধু currently replay হওয়া commit-টাই fix করে — sequence-এর পরের commit-টা একই লাইন আবার touch করতে পারে আর নিজের conflict trigger করতে পারে, তাই `--continue` আবার pause করতে পারে আর resolve loop-টা প্রতি commit-এ repeat হতে পারে।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `git merge --abort` আসলে কী undo করে, আর কী untouched রাখে?</strong></summary>

এটা চলমান merge attempt cancel করে আর তোমার working directory আর branch ঠিক `git merge` চালানোর আগে যেমন ছিল তেমনই restore করে — এটা তোমার actual commit history touch করে না বা হারায় না, শুধু অসম্পূর্ণ merge attempt-টাই।
</details>

## ৯. ফ্রি রিসোর্স (English)

- Official docs: [Resolving a merge conflict using the command line (GitHub)](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/addressing-merge-conflicts/resolving-a-merge-conflict-using-the-command-line)
- Official docs: [Git Basics — git merge conflicts](https://git-scm.com/book/en/v2/Git-Branching-Basic-Branching-and-Merging)

---

**পরের চ্যাপ্টার:** Chapter 4 — Interactive Rebase: Cleaning Up History *(শীঘ্রই আসছে)*
