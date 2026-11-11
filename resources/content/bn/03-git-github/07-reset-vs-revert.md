# Chapter 7 — ভুল Undo করা: reset বনাম revert

> **Course:** Git & GitHub (Advanced) · **Chapter:** 7 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/git-github/07-reset-vs-revert)

তুমি already `git reflog` ([Chapter 5](/git-github/05-stash-cherry-pick-reflog)) এর সাথে পরিচিত হয়েছো, accidentally কিছু ভুল হলে এর safety net হিসেবে। এই chapter দুটো tool নিয়ে যা তুমি **deliberately** ব্যবহার করো যখন ইচ্ছাকৃতভাবে করা একটা commit ভুল বলে দেখা যায়: `git reset` আর `git revert`। এগুলো একই problem বিপরীত উপায়ে solve করে, আর একটা shared branch-এ ভুল একটা বেছে নিলে exactly [Chapter 2](/git-github/02-merge-vs-rebase)-এ warn করা সেই ধরনের mess হয়।

![Reset vs revert diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/03-git-github/images/reset-vs-revert.svg)

## ১. `git reset` — branch pointer move করো, history rewrite করো

`git reset` তোমার current branch-কে একটা আলাদা (usually আগের) commit-এ point করতে move করে। তুমি যে commit(s) reset করে পার হয়ে গেছো সেগুলো নতুন history-তে কোথাও "merge away" বা record হয় না — এগুলো শুধু history-র অংশ হওয়া বন্ধ হয়ে যায় (যদিও, Chapter 5-এ cover করা মতো, এগুলো reflog দিয়ে কিছু সময় ধরে এখনো recoverable)।

```bash
git reset --soft HEAD~1    # শেষ commit undo করো, এর পরিবর্তন staged রাখো
git reset --mixed HEAD~1   # শেষ commit undo করো, এর পরিবর্তন unstaged রাখো (এটাই default mode)
git reset --hard HEAD~1    # শেষ commit undo করো AR এর পরিবর্তন সম্পূর্ণ discard করো
```

তিনটা mode শুধু তোমার **working directory আর staging area**-তে কী হয় তাতে আলাদা — তিনটাই branch pointer একই ভাবে move করে:

| Mode | Branch pointer | Staged পরিবর্তন | Working directory file |
|---|---|---|---|
| `--soft` | পিছনে move করে | Undo হওয়া commit-এর পরিবর্তন staged রাখে | অপরিবর্তিত |
| `--mixed` (default) | পিছনে move করে | Undo হওয়া commit-এর পরিবর্তন unstage করে | অপরিবর্তিত — পরিবর্তন এখনো আছে, শুধু unstaged |
| `--hard` | পিছনে move করে | Clear হয়ে যায় | **Discard হয়ে যায়** — exactly target commit-এর মতো হয়ে যায় |

`--hard` uncommitted যা কিছু আছে তার জন্য destructive — সবসময় প্রথমে `git status` চালাও, আর মনে রাখো তুমি বেশি reset করে ফেললে reflog তোমার ফিরে আসার উপায়।

## ২. `git revert` — একটা পুরোনো commit undo করতে একটা নতুন commit যোগ করো

`git revert` কখনো কিছু rewrite বা remove করে না। এটা একটা target commit কী পরিবর্তন করেছে দেখে, আর একটা **একদম নতুন commit** বানায় যা exact বিপরীত পরিবর্তন apply করে — net effect: খারাপ পরিবর্তন cancel হয়ে যায়, কিন্তু original (খারাপ) commit, আর নতুন undo commit, দুটোই history-তে permanently visible থাকে।

```bash
git revert a1b2c3d
```

এটা ঠিক একটা merge-এর মতোই conflict করতে পারে, যদি পরের commit গুলো revert যা undo করতে চায় সেই একই লাইন touch করে থাকে — [Chapter 3](/git-github/03-resolving-merge-conflicts)-এর একই উপায়ে resolve করো: marker ঠিক করো, `git add`, তারপর `git revert --continue` (বা `--abort`)।

## ৩. তাহলে কোনটা, কখন?

এটা Chapter 2-এর golden rule-এর একটা সরাসরি application — এটা নির্ভর করে তুমি যে commit undo করছো তা অন্য কারো কাছে already আছে কিনা তার উপর:

| পরিস্থিতি | ব্যবহার করো | কেন |
|---|---|---|
| এমন একটা commit যা শুধু তোমার machine-এ আছে, কখনো push হয়নি | **`reset`** | অন্য কারো কাছে এটা নেই; তোমার নিজের unpushed history rewrite করা সবসময় safe |
| তুমি একটা commit push করেছো, কিন্তু certain অন্য কেউ এখনো pull করেনি (শুধু তুমি, just now) | **`reset`** (তারপর একটা normal push, বা দরকার হলে force-push) | এখনো effectively private |
| এমন একটা commit যা already একটা shared branch-এ আছে (`main`, বা অন্যরা pull করেছে এমন কোনো branch) | **`revert`** | Shared history rewrite করলে সবার copy diverge হয়ে যায়, exactly Chapter 2-এ warn করা মতো; revert history touch না করেই problem fix করে যার উপর অন্য কেউ নির্ভর করছে |
| একটা shared branch-এও mistake-টা history থেকে সম্পূর্ণ গায়েব করা লাগবে | কোনোটাই না, carefully — এর team coordination লাগবে (history rewrite + সবার re-sync), Chapter 2-এ বলা একই "rare and deliberate" territory | Routine `reset --hard` + force-push একটা shared branch-এ অন্য সবার clone ভেঙে দেয় |

## ৪. মূল কথাগুলো (Key Takeaways)

- `git reset` তোমার branch pointer একটা আলাদা commit-এ move করে আর history rewrite করে — `--soft` পরিবর্তন staged রাখে, `--mixed` (default) unstage করে, `--hard` সম্পূর্ণ discard করে।
- `git revert` কখনো কিছু rewrite করে না — এটা একটা নতুন commit যোগ করে যা একটা পুরোনো commit cancel করে, history-তে দুটোই visible রেখে।
- Reset শুধু তোমার কাছে থাকা commit-এর জন্য; revert এমন commit-এর জন্য যা already একটা shared branch-এ থাকতে পারে — Chapter 2-এর merge-vs-rebase rule-এর একই reasoning।
- `git revert` ঠিক একটা merge-এর মতোই conflict করতে পারে, Chapter 3-এর একই উপায়ে resolve করা।
- `reset --hard` তোমার working directory থেকে uncommitted পরিবর্তন permanently discard করে — প্রথমে `git status` check করো, আর মনে রাখো `git reflog` এখনো তুমি reset করে পার হয়ে যাওয়া একটা commit recover করতে পারে।

## ৫. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. তিনটা `git reset` mode-এর (`--soft`, `--mixed`, `--hard`) মধ্যে একটা common জিনিস কী, আর এগুলোর মধ্যে পার্থক্য কী?</strong></summary>

তিনটাই branch pointer-কে target commit-এ exactly একই ভাবে পিছনে move করে। এগুলো শুধু undo হওয়া commit-এর পরিবর্তনে কী হয় তাতে আলাদা: `--soft` সেগুলো staged রাখে, `--mixed` (default) unstage করে কিন্তু working directory-তে রাখে, আর `--hard` সম্পূর্ণ discard করে।
</details>

<details>
<summary><strong>প্রশ্ন ২. `git revert a1b2c3d`-এর পর, commit `a1b2c3d` কি এখনো repository-র history-তে exist করে?</strong></summary>

হ্যাঁ — `git revert` কখনো কিছু remove বা rewrite করে না। এটা একটা একদম নতুন commit যোগ করে যা `a1b2c3d`-এর পরিবর্তনের বিপরীত apply করে; original commit আর নতুন revert commit দুটোই history-তে permanently visible থাকে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. গতকাল একটা খারাপ commit `main`-এ merge হয়ে গেছে আর কয়েকজন টিমমেট already এটা pull করে ফেলেছে। এটা undo করতে কোন tool ব্যবহার করবে, আর কেন?</strong></summary>

`git revert` — টিমমেট already commit-টা pull করে ফেলেছে, তাই `git reset` (plus force-push) দিয়ে history rewrite করলে তাদের local copy remote থেকে diverge হয়ে যাবে, ঠিক Chapter 2-এ shared branch rebase করা নিয়ে warn করা একই problem। Revert একটা নতুন commit যোগ করে issue fix করে, অন্য কেউ already যার উপর কাজ করেছে সেই history touch না করেই।
</details>

<details>
<summary><strong>প্রশ্ন ৪. তুমি `git reset --hard HEAD~1` চালিয়ে ফেলেছো আর সাথে সাথেই বুঝেছো তোমার সেই commit লাগবে। এটা recoverable, কীভাবে?</strong></summary>

Usually হ্যাঁ। Git সাথে সাথেই commit object delete করে না — এটা এখনো unreferenced হয়ে বসে আছে। `git reflog` চালালে reset-এর ঠিক আগের commit-এর hash দেখাবে, আর `git reset --hard <সেই hash>` (বা `git cherry-pick <সেই hash>`) এটা ফিরিয়ে আনে, ঠিক Chapter 5-এ cover করা মতো।
</details>

## ৬. ফ্রি রিসোর্স (English)

- Official docs: [`git reset` reference](https://git-scm.com/docs/git-reset)
- Official docs: [`git revert` reference](https://git-scm.com/docs/git-revert)
- Official guide: [Git Tools — Reset Demystified](https://git-scm.com/book/en/v2/Git-Tools-Reset-Demystified)

---

**পরের চ্যাপ্টার:** Chapter 8 — GitHub Power Features *(শীঘ্রই আসছে)*
