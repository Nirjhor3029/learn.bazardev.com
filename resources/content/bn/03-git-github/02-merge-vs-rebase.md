# Chapter 2 — Merge vs Rebase

> **Course:** Git & GitHub (Advanced) · **Chapter:** 2 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/git-github/02-merge-vs-rebase)

"merge করবো না rebase করবো?" জিজ্ঞেস করে তিনজন developer-এর কাছ থেকে তিনটা আলাদা confident উত্তর পেয়ে থাকলে, এই chapter তোমার জন্য। দুটোই দুটো branch-এর কাজ একসাথে নিয়ে আসে — শুধু সম্পূর্ণ আলাদা history পেছনে রেখে যায়।

## ১. `git merge` আসলে কী করে

```bash
git switch main
git merge feature
```

Git একটা একদম নতুন commit বানায় — একটা **merge commit** — যার *দুটো* parent আছে: `main`-এর tip আর `feature`-এর tip। দুটো branch-এর existing history-র কিছুই পরিবর্তন হয় না। Result সৎভাবে দেখায় আসলে কী ঘটেছে: দুটো কাজের line parallel-এ ছিল, তারপর তারা join হলো।

## ২. `git rebase` আসলে কী করে

```bash
git switch feature
git rebase main
```

Git `feature`-এর unique প্রতিটা commit নেয়, এবং একটা একটা করে **replay** করে, যেন তুমি সেগুলো `main`-এর current tip থেকে শুরু করে লিখেছো — তুমি যখন আলাদা হয়েছিলে তখনকার `main` থেকে না। Result একটা সোজা, linear history, কোনো merge commit ছাড়াই — কিন্তু সেই replay করা প্রতিটা commit technically একটা *নতুন* commit, একটা নতুন hash সহ, actual code পরিবর্তন একই হলেও।

![Merge vs rebase diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/03-git-github/images/merge-vs-rebase.svg)

## ৩. এই chapter-এর সবচেয়ে গুরুত্বপূর্ণ নিয়ম

**এমন branch কখনো rebase করবে না যা অন্যরা আগে থেকেই pull করেছে বা তাতেও কাজ করছে।**

কারণ rebase নতুন hash দিয়ে একদম নতুন commit বানায়, যে কারো কাছে আগের commit আছে তার history তোমার থেকে permanently আলাদা হয়ে যায় — Git বুঝতে পারে না "এগুলো একই পরিবর্তন, শুধু আবার লেখা"। তাদের পরের `git pull` duplicate-এর মতো দেখতে commit বা conflict-এর একটা confusing mess হয়ে যায় যা হওয়ার কথাই না।

এই নিয়মের practical version:
- কোনো branch rebase করো যথেচ্ছভাবে **শুধু তুমি** যাতে কাজ করছো, বিশেষ করে push করার আগে।
- একবার একটা branch push হয়ে গেলে এবং অন্যরা হয়তো তাতে কাজ করেছে, rebase-এর বদলে merge-এ switch করো (বা, shared history rewrite করতেই হলে, জড়িত সবাইকে জানতে আর coordinate করতে হবে — এটা একটা rare, deliberate ঘটনা হিসেবে treat করো, routine না)।

## ৪. তাহলে আসলে কোনটা ব্যবহার করবে?

| পরিস্থিতি | ব্যবহার করো |
|---|---|
| তোমার শেষ হওয়া feature branch `main`-এ আনা | **Merge** (একটা pull request দিয়ে — Chapter 6) — সত্যিকারের history রাখে, shared branch-এ নিরাপদ |
| PR open করার আগে নিজের feature branch পরিষ্কার করা — latest `main` টেনে আনা, messy "fix typo" commit সরানো | **Rebase** — এটা তোমার branch, কেউ এখনো pull করেনি, একটা clean linear history review করতে সত্যিই ভালো |
| একটা branch যাতে অন্য টিমমেট আগে থেকেই pull করেছে বা commit push করেছে | **Merge, rebase না** — উপরের নিয়ম দেখো |

Chapter 1-এর trunk-based workflow-এর practical version ঠিক এটাই: তোমার feature branch-এ একা কাজ করার সময় সেটাকে latest `main`-এর উপর rebased রাখো, তারপর ready হলে (একটা reviewed pull request দিয়ে) এটা merge করো।

## ৫. Fast-forward merge নিয়ে একটা কথা

তুমি branch off করার পর থেকে `main` যদি একটুও না এগিয়ে থাকে, `git merge feature`-এর একটা merge commit-ও লাগে না — এটা শুধু `main`-এর pointer `feature`-এর সাথে ঠিক মিলিয়ে এগিয়ে দিতে পারে। এটাকে বলে একটা **fast-forward merge**। কিছু team এটা disable করা preference দেয় (`git merge --no-ff feature`) specifically সবসময় একটা visible merge commit রাখতে, যেখানে একটা feature integrate হয়েছিল তা মার্ক করে, fast-forward technically সম্ভব হলেও।

## ৬. মূল কথাগুলো (Key Takeaways)

- `merge` দুটো history একসাথে জোড়া দিয়ে একটা নতুন commit বানায়; existing কিছু rewrite হয় না।
- `rebase` তোমার branch-এর commit গুলো অন্য একটা branch-এর উপর replay করে, নতুন hash-সহ নতুন commit বানায় এবং একটা linear history তৈরি করে — কোনো merge commit ছাড়া।
- এমন branch কখনো rebase করবে না যাতে অন্যরা আগে pull করেছে বা কাজ push করেছে — এটা তাদের history তোমার থেকে এমনভাবে আলাদা করে দেয় যা Git cleanly reconcile করতে পারে না।
- তোমার নিজের, এখনো share না করা feature branch rebase করো clean আর `main`-এর সাথে up to date রাখতে; আসলেই শেষ হওয়া কাজ `main`-এ আনতে (pull request দিয়ে) merge করো।
- একটা fast-forward merge শুধু branch pointer এগিয়ে দেয়, কোনো নতুন commit ছাড়া; `--no-ff` otherwise সম্ভব হলেও একটা merge commit force করে।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. `merge` আর `rebase` history-র সাথে মূলত কী আলাদাভাবে করে?</strong></summary>

`merge` দুটো parent-সহ একটা নতুন commit যোগ করে, যেকোনো branch-এর existing commit পরিবর্তন না করেই দুটো history জোড়া দেয়। `rebase` একটা branch-এর commit অন্যটার উপরে replay করে, সম্পূর্ণ নতুন commit (নতুন hash সহ) বানায় এবং কোনো merge commit ছাড়া একটা linear history তৈরি করে।
</details>

<details>
<summary><strong>প্রশ্ন ২. একজন টিমমেট আগে থেকেই pull করা একটা branch rebase করা কেন বিপজ্জনক?</strong></summary>

Rebase commit গুলোকে আলাদা hash-সহ নতুন commit-এ rewrite করে। তোমার টিমমেটের copy-তে এখনও পুরোনো commit আছে, তাই তাদের আর তোমার history permanently আলাদা হয়ে গেছে — Git automatically বুঝতে পারে না rewrite করা commit "একই" পরিবর্তন represent করে, যার ফলে তাদের পরের pull-এ confusing duplicate-এর মতো দেখতে commit বা conflict হয়।
</details>

<details>
<summary><strong>প্রশ্ন ৩. Chapter 1-এর trunk-based workflow-এ, কখন তোমার feature branch main-এর উপর rebase করা নিরাপদ, আর কখন merge-এ switch করা উচিত?</strong></summary>

Branch যতক্ষণ শুধু তোমার আছে rebase করা নিরাপদ — push করার আগে, বা অন্য কেউ এটা pull করা বা এর উপর কিছু build করার আগে। একবার এটা shared হয়ে গেলে এবং অন্যরা হয়তো এটা pull করেছে বা তাতে নিজের commit push করেছে, তোমার সেটা আরও rebase করার বদলে merge করা উচিত।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `--no-ff` Git-কে কী করতে force করে, যা otherwise skip করতে পারতো?</strong></summary>

এটা Git-কে একটা explicit merge commit বানাতে force করে, এমনকি যেখানে otherwise একটা fast-forward (কোনো নতুন commit ছাড়া শুধু branch pointer এগিয়ে দেওয়া) সম্ভব হতো — history-তে ঠিক কোথায় একটা feature integrate হয়েছিল তার একটা visible marker রেখে।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official docs: [Git Branching — Rebasing](https://git-scm.com/book/en/v2/Git-Branching-Rebasing)
- Official docs: [`git merge` reference](https://git-scm.com/docs/git-merge)

---

**পরের চ্যাপ্টার:** Chapter 3 — Resolving Merge Conflicts *(শীঘ্রই আসছে)*
