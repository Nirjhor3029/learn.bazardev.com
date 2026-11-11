# Chapter 6 — GitHub-এ Pull Request আর Code Review

> **Course:** Git & GitHub (Advanced) · **Chapter:** 6 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/git-github/06-pull-requests-and-code-review)

এখন পর্যন্ত সবকিছু pure Git ছিল — তুমি GitHub, GitLab, বা কোথাও host না করলেও একই ভাবে কাজ করে। একটা **pull request (PR)** কোনো Git concept না; এটা Git-এর উপর বানানো একটা GitHub feature, আর এটাই সেই উপায় যেভাবে বেশিরভাগ real team আসলে একটা feature branch থেকে `main`-এ code নিয়ে আসে।

![Pull request lifecycle diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/03-git-github/images/pr-lifecycle.svg)

## ১. একটা pull request আসলে কী

একটা PR হলো একটা **proposal**: "এই আমার branch, এটা `main` থেকে এভাবে আলাদা, দয়া করে review করো আর ready হলে merge করো।" একটা open করলে এটা নিজে কোনো code পরিবর্তন করে না — এটা শুধু GitHub-এ একটা page বানায় যা diff দেখায়, যেখানে discussion, automated check, আর eventual merge সবকিছু হয়।

```bash
git push origin feature/login
```

তোমার branch push করো, তারপর GitHub-এ: "Compare & pull request" → base branch select করো (usually `main`) আর তুমি just push করা branch টা → একটা description লিখো → "Create pull request।"

## ২. এমনভাবে PR লেখা যা review করা সহজ

একটা ভালো PR description দুটো প্রশ্নের উত্তর দেয় যা একজন reviewer-এর সবসময় থাকে: **কী পরিবর্তন হয়েছে, আর কেন।** Diff already line by line *কী* পরিবর্তন হয়েছে দেখায় — তোমার description-এ *কেন*-টা থাকা উচিত, সাথে non-obvious যা কিছু একজন reviewer-এর জানা লাগবে (একটা tricky edge case, একটা deliberate tradeoff, তুমি কী test করেছো)।

Description-এ একটা issue link করলে (`Closes #42`) PR merge হওয়ার সাথে সাথেই সেই issue automatically close হয়ে যায় — একটা ছোট habit যা extra manual কাজ ছাড়াই project-এর issue tracker honest রাখে।

এখনো review-এর জন্য ready না, কিন্তু visibility বা early CI feedback চাও? এটাকে একটা **draft PR** হিসেবে open করো — একই page, একই check, কিন্তু এটা signal দেয় "merge-এর জন্য ready না" আর তুমি ready mark না করা পর্যন্ত বেশিরভাগ team এটা review করবে না।

## ৩. Review নিজে

Reviewer তিন ধরনের feedback দিতে পারে:
- **Comment** — একটা প্রশ্ন বা note, mergeability নিয়ে কোনো judgment ছাড়াই।
- **Approve** — "এটা merge করার জন্য ভালো," optionally minor comment সহ।
- **Request changes** — merge block করে (branch protection এটা require করলে) যতক্ষণ না author feedback address করে আর reviewer re-approve করে।

Reviewer গণ diff-এর specific লাইনে সরাসরি comment করে, আর GitHub-এর "suggestion" feature দিয়ে একটা exact replacement propose ও করতে পারে, যা author এক click-এ accept করতে পারে। Author যখন response-এ আরও commit push করে, সেগুলো সরাসরি সেই একই PR-এ দেখা যায় — কিছু close করে আবার open করার প্রয়োজন নেই।

## ৪. Check: automated reviewer

বেশিরভাগ real project প্রতিটা PR-এ automated check চালায় — একটা test suite, একটা linter, একটা type-checker — CI দিয়ে (এই series-এর পরের একটা course-এর পুরো subject)। এগুলো সরাসরি PR page-এ pass/fail status check হিসেবে দেখা যায়। [Chapter 1](/git-github/01-branching-strategies) branch protection rule mention করেছিল; এখানেই এগুলো connect করে: একটা protected `main` branch এই check pass করা, আর একটা approving review require করতে পারে, merge button clickable হওয়ার আগেই।

## ৫. Merge করা: তিনটা আলাদা method, একই destination

GitHub-এর merge button আসলে একটা PR-এর commit গুলো base branch-এ আনার তিনটা আলাদা উপায় দেয় — এটা ঠিক [Chapter 2](/git-github/02-merge-vs-rebase)-এর merge-vs-rebase decision, শুধু একটা UI choice হিসেবে exposed:

| Method | এটা কী করে | কখন এটা ঠিক choice |
|---|---|---|
| **Merge commit** | Standard `git merge` — branch-এর প্রতিটা commit রাখে, একটা merge commit যোগ করে | তুমি feature কীভাবে build হয়েছে তার full, honest history চাও |
| **Squash and merge** | Branch-এর প্রতিটা commit base branch-এ একটা single নতুন commit-এ combine করে | Branch-এ messy "wip"/"fix typo" commit আছে ([Chapter 4](/git-github/04-interactive-rebase)-এর territory) আর তুমি `main`-এর history-তে প্রতি feature-এ একটা clean entry চাও |
| **Rebase and merge** | Branch-এর commit গুলো individually base branch-এর উপর replay করে, কোনো merge commit ছাড়া | তুমি একটা সম্পূর্ণ linear history চাও আর branch-এর individual commit গুলো already clean আর meaningful |

অনেক team specifically **squash and merge** feature branch-এর জন্য standardize করে কারণ এটা `main`-এর history কে trivially easy পড়তে করে তোলে — প্রতি PR-এ একটা commit — প্রতিটা contributor-কে perfect commit hygiene practice করতে require না করেই।

## ৬. মূল কথাগুলো (Key Takeaways)

- একটা pull request একটা GitHub feature, কোনো Git concept না — এটা একটা pushed branch-এর around বানানো একটা review-and-merge proposal।
- একটা ভালো description *কেন* ব্যাখ্যা করে, শুধু *কী* না; merge-এ auto-close করতে issue link করো `Closes #N` দিয়ে।
- Review হলো comment, approve, বা request changes — branch protection require করলে শেষটা merge block করতে পারে।
- Status check (CI) একটা PR-এ automatically চলে আর merge-এর আগে required review-এর সাথে required ও হতে পারে।
- তিনটা merge method (merge commit, squash, rebase) হলো Chapter 2-এর decision-এর UI version — squash সাধারণত `main`-এর history clean রাখার common default।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. একটা pull request কি একটা Git feature না GitHub feature? একটা open করা তোমার code-এ আসলে কী করে?</strong></summary>

এটা একটা GitHub feature (অন্য platform-এর নিজের equivalent আছে, যেমন GitLab-এর merge request) — Git নিজে "pull request"-এর কোনো concept-ই রাখে না। একটা open করলে কোনো code পরিবর্তন হয় না; এটা একটা review page বানায় যা তোমার pushed branch আর base branch-এর মাঝের diff দেখায়, যেখানে discussion, check, আর eventual merge হয়।
</details>

<details>
<summary><strong>প্রশ্ন ২. একটা PR description-এ "Closes #42" লেখার effect কী?</strong></summary>

GitHub সেই PR merge হওয়ার সাথে সাথেই issue #42 automatically close করে দেয়, কাউকে পরে manually issue close করতে হওয়া ছাড়াই issue tracker কে actual code পরিবর্তনের সাথে sync রাখে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা repository-র branch protection যদি একটা approving review আর passing status check require করে, একজন reviewer "Request changes" click করলে কী হয়?</strong></summary>

Merge button blocked হয়ে যায় — author feedback address না করা (typically আরও commit push করে) আর reviewer either approve করা বা তার "request changes" dismiss হওয়া পর্যন্ত PR merge করা যায় না, সব automated status check pass করলেও।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একটা feature branch-এ দশটা commit, এর মধ্যে কয়েকটা "wip" বা "fix typo"। কোন merge method `main`-এর history সবচেয়ে clean রাখে, আর এটা সেই দশটা commit-এর সাথে কী করে?</strong></summary>

Squash and merge — এটা দশটা commit-ই base branch-এ একটা single নতুন commit-এ combine করে, তাই branch-এর individual commit গুলো যতই messy হোক, `main`-এ পুরো feature represent করা একটা clean entry থাকে।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official guide: [About pull requests (GitHub Docs)](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/proposing-changes-to-your-work-with-pull-requests/about-pull-requests)
- Official guide: [About pull request merges](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/incorporating-changes-from-a-pull-request/about-pull-request-merges)

---

**পরের চ্যাপ্টার:** Chapter 7 — Undoing Mistakes: reset vs revert *(শীঘ্রই আসছে)*
