# Chapter 1 — Team-এর জন্য Branching Strategy

> **Course:** Git & GitHub (Advanced) · **Chapter:** 1 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/git-github/01-branching-strategies)

এই course ধরে নেয় তুমি আগে থেকেই basics জানো — `git init`, `add`, `commit`, `push`, `pull`। এটা cover করবে যা একের বেশি মানুষ একই codebase-এ কাজ করলে আসলে গুরুত্বপূর্ণ হয়ে যায়: branching model, merge conflict, rebasing, pull request, এবং panic না করে ভুল undo করা।

## ১. কেন branch লাগে

একটা branch হলো কাজের একটা আলাদা line, যা explicitly `main`-এ না আনা পর্যন্ত `main`-কে affect করে না। Branch ছাড়া, প্রতিটা পরিবর্তন সরাসরি একই shared history-তে যায় — মানে একটা অসম্পূর্ণ feature, একটা ঝুঁকিপূর্ণ experiment, আর একটা critical bugfix সব একসাথে জড়িয়ে যাবে, বাকিদের ছাড়া একটা ship করার কোনো উপায় থাকবে না।

Quick command refresher (তুমি এগুলো আগে থেকেই জানো, এটা শুধু এই course যে vocabulary ব্যবহার করবে তার জন্য):

```bash
git branch                    # branch list করা
git switch -c feature/login   # একটা নতুন branch বানানো আর switch করা
git switch main                # আবার ফিরে যাওয়া
git branch -d feature/login    # merge হয়ে গেলে একটা branch delete করা
```

## ২. যে দুটো model আসলে সামনে পড়বে

![Branching models diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/03-git-github/images/branching-models.svg)

### Trunk-based development (modern default — ছোট team সহ বেশিরভাগ team আসলে এটাই ব্যবহার করে)

- একটা long-lived branch: `main`।
- প্রতিটা পরিবর্তন একটা **short-lived** feature branch — আদর্শভাবে এক-দুই দিনের মধ্যেই merge হয়ে যায়।
- `main` সবসময় deployable অবস্থায় থাকে।
- সহজ mental model, sync রাখার মতো কম long-lived branch, কম কষ্টের merge।

### Git Flow (heavier, scheduled release-এর জন্য বানানো)

- দুটো long-lived branch: `main` (production) আর `develop` (পরের release, তৈরি হচ্ছে)।
- `feature/*` branch গুলো `develop`-এ merge হয়।
- Release-এর জন্য stabilize করার সময় `develop` থেকে একটা `release/*` branch কাটা হয়।
- Urgent production fix-এর জন্য `hotfix/*` branch সরাসরি `main` থেকে আসে, তারপর `main` আর `develop` দুটোতেই merge হয়ে যায়।

| | Trunk-based | Git Flow |
|---|---|---|
| সবচেয়ে ভালো কখন | Continuous deployment, ছোট-মাঝারি team | Scheduled release (যেমন নির্দিষ্ট সময়ে ship হওয়া versioned software) |
| Branch-এর বয়স | কয়েক দিন | কয়েক সপ্তাহ, কখনো আরও বেশি |
| Complexity | কম | বেশি — বেশি branch type track করতে হয় |
| Deploy হয় | `main` থেকে, continuously | একটা `release/*` branch থেকে, নির্দিষ্ট সময়ে |

কোনটা ব্যবহার করবে নিশ্চিত না থাকলে: **trunk-based default হিসেবে নাও।** এটা সহজ, modern team আর tool (GitHub-এর নিজের workflow feature সহ) এটার around-ই design করা, এবং পরে actual scheduled-release-এর প্রয়োজন হলে আরও structure add করতে পারবে।

## ৩. Branch-এর নাম এমনভাবে দেওয়া যাতে নিজেই ব্যাখ্যা করে

একটা consistent prefix কাউকে branch list-এ চোখ বুলালেই বলে দেয় এটা কী ধরনের পরিবর্তন, খোলার আগেই:

```
feature/user-authentication
fix/checkout-crash-on-ios
chore/upgrade-laravel-to-11
docs/update-readme
```

## ৪. `main` protect করা

GitHub-এ, তুমি require করতে পারো যে `main`-এ কিছু সরাসরি push হতে পারবে না — প্রতিটা পরিবর্তন একটা pull request দিয়েই যেতে হবে (Chapter 6-এ properly cover করা), optionally আগে একটা passing CI check বা একটা approving review require করে। এটা একটা setting, Git command না, এবং একটা shared repository-তে তুমি করতে পারো এমন সবচেয়ে বেশি value-র পাঁচ-মিনিটের পরিবর্তনগুলোর একটা: এটা "কেউ ভুলে ভাঙা কিছু সরাসরি main-এ push করেছে" structurally অসম্ভব করে দেয়।

## ৫. মূল কথাগুলো (Key Takeaways)

- একটা branch কাজকে আলাদা করে রাখে যতক্ষণ না তুমি `main`-এ আনার জন্য ready — merge হওয়ার আগে একটা branch-এ কিছু অন্য কাউকে affect করে না।
- Trunk-based development (একটা `main`, short-lived feature branch) বেশিরভাগ team-এর জন্য সহজ, modern default।
- Git Flow (`main` + `develop` + `feature`/`release`/`hotfix` branch) scheduled, versioned release ship করা team-এর জন্য — বেশি structure, বেশি overhead।
- Branch নামে prefix দেওয়া (`feature/`, `fix/`, `chore/`) একটা branch list-কে একনজরে নিজেই ব্যাখ্যা করে তোলে।
- GitHub-এর branch protection rule `main`-এ সরাসরি push block করতে পারে, সব পরিবর্তনকে review-এর মধ্য দিয়ে যেতে বাধ্য করে — একটা সস্তা, high-value safeguard।

## ৬. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. Trunk-based development আর Git Flow-এর মূল পার্থক্য কী?</strong></summary>

Trunk-based development-এ একটা long-lived branch (`main`) আছে short-lived feature branch দিয়ে যা দ্রুত merge হয়ে যায়। Git Flow একটা দ্বিতীয় long-lived branch (`develop`) যোগ করে, সাথে dedicated `release/*` আর `hotfix/*` branch type, continuous deployment-এর বদলে scheduled, versioned release-এর around তৈরি।
</details>

<details>
<summary><strong>প্রশ্ন ২. Scheduled, versioned release থাকা একটা team কেন trunk-based development-এর বদলে Git Flow preference দিতে পারে?</strong></summary>

Git Flow তাদের একটা `release/*` branch দেয় একটা নির্দিষ্ট feature set stabilize করতে একটা release-এর জন্য, `develop`-এ চলমান কাজ block না করে, আর একটা `hotfix/*` path দেয় পরের পুরো release cycle-এর জন্য অপেক্ষা না করেই urgent production patch করতে। Trunk-based development-এ এই separation built-in না, কারণ এটা ধরে নেয় `main` সবসময় as-is deploy করার জন্য ready।
</details>

<details>
<summary><strong>প্রশ্ন ৩. `main`-এ branch protection আসলে কী prevent করে?</strong></summary>

এটা `main`-এ সরাসরি push block করে, বদলে প্রতিটা পরিবর্তনকে একটা pull request দিয়ে যেতে require করে — optionally merge হওয়ার আগে একটা passing CI check বা একটা approving review-ও require করে। এটা কাউকে ভুলে test না করা বা ভাঙা code সরাসরি সবাই deploy করা branch-এ push করার সম্ভাবনা সরিয়ে দেয়।
</details>

## ৭. ফ্রি রিসোর্স (English)

- Official guide: [GitHub Flow](https://docs.github.com/en/get-started/using-github/github-flow)
- Reference: [A successful Git branching model (original Git Flow post, by Vincent Driessen)](https://nvie.com/posts/a-successful-git-branching-model/)

---

**পরের চ্যাপ্টার:** Chapter 2 — Merge vs Rebase *(শীঘ্রই আসছে)*
