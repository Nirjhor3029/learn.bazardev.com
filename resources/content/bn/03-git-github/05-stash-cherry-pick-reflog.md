# Chapter 5 — Stash, Cherry-pick, আর Reflog

> **Course:** Git & GitHub (Advanced) · **Chapter:** 5 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/git-github/05-stash-cherry-pick-reflog)

এই chapter-এ তিনটা independent tool, প্রতিটা একটা আলাদা everyday problem solve করে: অসম্পূর্ণ কাজ pause করা, একটা specific commit অন্য কোথাও copy করা, আর তুমি ভেবেছিলে recover করার কোনো উপায় নেই এমন একটা mistake থেকে recover করা।

![Stash, cherry-pick, and reflog diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/03-git-github/images/stash-cherrypick-reflog.svg)

## ১. `git stash` — অসম্পূর্ণ কাজ shelve করে clean ভাবে branch switch করা

তুমি একটা পরিবর্তনের মাঝখানে আছো, কিছুই commit করার জন্য ready না, আর সাথে সাথে তোমার branch switch করা লাগবে — একজন টিমমেটের PR review করতে, একটা urgent bug fix করতে, যা হোক। অন্য branch-এর সাথে conflict করা uncommitted পরিবর্তন overwrite হয়ে যাবে এমন অবস্থায় Git তোমাকে clean ভাবে branch switch করতে দেবে না। `git stash` এটা solve করে তোমার uncommitted পরিবর্তন temporarily shelve করে, তোমাকে একটা clean working directory ফিরিয়ে দিয়ে:

```bash
git stash              # সব uncommitted পরিবর্তন shelve করো
git switch main         # এখন safely switch করা যায় — working dir clean
# ...urgent কাজটা করো...
git switch feature/login
git stash pop           # shelve করা পরিবর্তন ফিরিয়ে আনো, stash থেকে remove করো
```

আরও কিছু command জানা জরুরি:

```bash
git stash list                      # current সব stash দেখো
git stash push -m "wip: login form" # default-এর বদলে একটা descriptive নাম দিয়ে stash করো
git stash show -p stash@{0}         # apply না করেই একটা stash-এর ভেতরে কী আছে দেখো
git stash apply                     # pop-এর মতো, কিন্তু পরে stash-টা রেখে দেয় (একাধিক branch-এ apply করতে চাইলে useful)
git stash drop stash@{0}            # আর দরকার নেই এমন একটা stash delete করো
```

একটা stash **শুধু local** — এটা কখনো push হয় না, আর তুমি pop/apply বা drop করা পর্যন্ত, বা repository delete না করা পর্যন্ত তোমার repository-তে থাকে।

## ২. `git cherry-pick` — একটা specific commit অন্য কোথাও copy করা

কখনো তোমার ঠিক একটা commit-এর পরিবর্তন অন্য একটা branch-এ লাগে, পুরো branch merge বা rebase না করেই। Classic case: কেউ একটা critical fix সরাসরি একটা `hotfix` branch-এ commit করেছে, আর তোমার সেই exact fix `main`-এও লাগবে, `hotfix` থেকে আর কিছু না টেনে।

```bash
git switch main
git cherry-pick a1b2c3d
```

Git commit `a1b2c3d`-এর আনা পরিবর্তন নেয় আর তোমার current branch-এ একটা **একদম নতুন commit** হিসেবে apply করে — default-এ একই পরিবর্তন, একই message, কিন্তু সম্পূর্ণ নতুন commit hash (একটা rebase replay-র মতোই)। Target branch যদি একই লাইন পরিবর্তন করে থাকে, cherry-pick ঠিক একটা merge বা rebase-এর মতোই conflict করতে পারে — [Chapter 3](/git-github/03-resolving-merge-conflicts)-এ যেমন শিখেছো সেই একই উপায়ে resolve করো: marker ঠিক করো, `git add`, তারপর `git cherry-pick --continue` (বা cancel করতে `--abort`)।

সাধারণ real-world ব্যবহার:
- `main` থেকে একটা bugfix backport করা এমন একটা পুরোনো `release/1.2` branch-এ যা এখনো maintain করা হচ্ছে।
- একটা messy experimental branch যা তুমি phele দেওয়ার কথা ভাবছো, তার থেকে একটা আসলেই useful commit বের করে আনা।
- ভুলে ভুল branch-এ চলে যাওয়া একটা commit ঠিক branch-এ move করা।

## ৩. `git reflog` — "আমি মনে হয় আমার কাজ destroy করে ফেলেছি" মুহূর্তের safety net

তোমার local repository-তে যতবার `HEAD` move করে — একটা commit, একটা checkout, একটা rebase, একটা reset, একটা merge — Git নিরবে এটা **reflog**-এ রেকর্ড করে। এটা তোমার commit history থেকে আলাদা; এটা তোমার `HEAD` যেখানে যেখানে *পয়েন্ট* করেছিল তার একটা log, locally রাখা হয়, সেই commit গুলো অন্য কোনো উপায়ে এখনো reachable কিনা তার উপর নির্ভর না করেই।

```bash
git reflog
```

```
a1b2c3d (HEAD -> main) HEAD@{0}: commit: Add input validation
e4f5g6h HEAD@{1}: rebase (finish): returning to refs/heads/main
h7i8j9k HEAD@{2}: reset: moving to HEAD~3
l0m1n2o HEAD@{3}: commit: Add login form
```

এটা একটা খুবই common panic moment-এর জন্য গুরুত্বপূর্ণ: তুমি `git reset --hard` এক step বেশি চালিয়ে ফেলেছো, বা একটা rebase ভুল হয়ে গেছে, আর যে commit গুলো তোমার আছে বলে শপথ করতে পারতে সেগুলো `git log`-এ শুধু... নেই। এগুলো almost কখনো আসলে চলে যায় না — Git commit object গুলো সাথে সাথে delete করে না, এটা শুধু সেগুলোর দিকে point করা বন্ধ করে। Reflog এখনো exactly মনে রাখে mistake-এর ঠিক আগে `HEAD` কোথায় ছিল:

```bash
git reflog                       # mistake-এর ঠিক আগের entry খুঁজো, যেমন HEAD@{2}
git reset --hard HEAD@{2}        # HEAD (আর তোমার branch) কে exactly সেই point-এ ফিরিয়ে নাও
# বা, সবকিছু reset না করে শুধু একটা specific commit নিতে চাইলে:
git cherry-pick h7i8j9k
```

Reflog **শুধু local** (কখনো push হয় না, কখনো টিমমেটদের সাথে share হয় না) আর entry গুলো শেষে expire হয়ে যায় — default-এ Git unreachable commit গুলো reflog দিয়ে reachable রাখে garbage collection-এর জন্য eligible হওয়ার আগে প্রায় 90 দিন পর্যন্ত। তবে recent কিছুর জন্য, এটা আসলেই Git-এর সবচেয়ে আশ্বস্তকারী tool গুলোর একটা: এখানে খুব কম mistake-ই আসলে permanent।

## ৪. মূল কথাগুলো (Key Takeaways)

- `git stash` uncommitted পরিবর্তন shelve করে যাতে তুমি clean working directory নিয়ে branch switch করতে পারো; `git stash pop` সেগুলো ফিরিয়ে আনে। Stash শুধু local।
- `git cherry-pick <hash>` একটা specific commit-এর পরিবর্তন তোমার current branch-এ একটা একদম নতুন commit হিসেবে copy করে — backport আর একটা commit ঠিক branch-এ move করার জন্য useful। এটা একটা merge-এর মতোই conflict করতে পারে, একই উপায়ে resolve করা।
- `git reflog` হলো `HEAD` যেখানে যেখানে point করেছিল তার একটা local record — commit, reset, rebase, checkout — তোমার visible commit history থেকে independent।
- বেশিরভাগ "আমি ভুলে আমার commit destroy করে ফেলেছি" panic-এর solution আছে: `git reflog`-এ commit টা খুঁজো, তারপর `git reset --hard` দিয়ে সেখানে যাও বা specific commit-টা `git cherry-pick` করে ফিরিয়ে আনো।
- এই তিনটার কোনোটাই (stash entry, reflog entry) কখনো remote-এ push হয় না — এগুলো শুধু তোমার local repository-তে exist করে।

## ৫. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. Branch switch করার আগে অসম্পূর্ণ কাজ commit করার বদলে `git stash` কেন ব্যবহার করবে?</strong></summary>

Stashing তোমাকে history-তে একটা half-finished commit না বানিয়েই সাথে সাথে একটা clean working directory দেয়। এটা এমন কাজ shorterm shelve করার জন্য যা তুমি এখনো commit করতে ready না, যেখানে অসম্পূর্ণ কাজ commit করলে একটা messy "wip" commit থেকে যেত যা পরে পরিষ্কার করতে হতো (যেমন Chapter 4-এর interactive rebase দিয়ে)।
</details>

<details>
<summary><strong>প্রশ্ন ২. একটা commit-এর পরিবর্তন অন্য branch-এ আনার সময় `git cherry-pick` আর `git merge`-এর মূল পার্থক্য কী?</strong></summary>

`git cherry-pick` শুধু একটা specific commit-এর পরিবর্তন apply করে, current branch-এ একটা single নতুন commit বানায় — source branch থেকে আর কিছু আসে না। `git merge` অন্য branch-এর পুরো history নিয়ে আসে (এর সাথে unique প্রতিটা commit), শুধু একটা না।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা accidental `git reset --hard` যা recent commit delete করে ফেলেছে বলে মনে হচ্ছে, তার পর তুমি প্রথমে কোন command চালাবে, আর কেন?</strong></summary>

`git reflog` — এটা `HEAD` recently যেখানে যেখানে point করেছিল সব দেখায়, reset-এর ঠিক আগে exist করা commit-টাও সহ। Git একটা branch সেই commit-এর দিকে point করা বন্ধ করলে সাথে সাথেই commit object delete করে না, তাই "হারিয়ে যাওয়া" commit-এর hash almost সবসময় এখনো সেখানে দেখা যায় আর recover করা যায়।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একজন টিমমেট যদি তোমার একটা specific stash বা reflog entry share করতে বলে, তুমি তাকে কী বলবে?</strong></summary>

তুমি পারবে না — stash আর reflog দুটোই তোমার নিজের repository-র জন্য purely local, কখনো push বা কারো সাথে share হয় না। তার আসল পরিবর্তনটা লাগলে, তোমার এটাকে একটা real commit-এ বদলাতে হবে (যেমন stash apply করে commit করা, বা commit cherry-pick করা) আর সেটা push করতে হবে।
</details>

## ৬. ফ্রি রিসোর্স (English)

- Official docs: [Git Tools — Stashing and Cleaning](https://git-scm.com/book/en/v2/Git-Tools-Stashing-and-Cleaning)
- Official docs: [`git cherry-pick` reference](https://git-scm.com/docs/git-cherry-pick)
- Official docs: [Git Internals — Maintenance and Data Recovery (reflog)](https://git-scm.com/book/en/v2/Git-Internals-Maintenance-and-Data-Recovery)

---

**পরের চ্যাপ্টার:** Chapter 6 — Pull Requests and Code Review on GitHub *(শীঘ্রই আসছে)*
