# Chapter 4 — Interactive Rebase: History পরিষ্কার করা

> **Course:** Git & GitHub (Advanced) · **Chapter:** 4 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/git-github/04-interactive-rebase)

Chapter 2-এ plain `git rebase` cover করেছিলাম — তোমার branch-কে অন্য একটার উপরে replay করা। Interactive rebase (`git rebase -i`) একই replay mechanism, শুধু Git replay করার আগে *তুমি* commit-এর list edit করতে পারো: reorder করতে পারো, একাধিককে একটায় combine করতে পারো, message reword করতে পারো, বা commit পুরোপুরি delete করতে পারো। এটাই সেই tool যা একটা messy "work in progress" commit trail-কে একটা clean, readable story-তে বদলে দেয়, pull request open করার আগে।

## ১. একটা শুরু করা

```bash
git rebase -i HEAD~4
```

এটা তোমার editor-এ তোমার current branch-এর শেষ 4টা commit খুলে দেয়, **সবচেয়ে পুরোনোটা প্রথমে**, প্রতিটা নিজের লাইনে:

```
pick a1b2c3d Add login form
pick e4f5g6h fix typo
pick h7i8j9k fix typo again
pick l0m1n2o add validaton
```

এটা শুধু দেখার জন্য একটা log না — এটা একটা literal **to-do list** যা Git execute করতে যাচ্ছে। লাইনের শুরুর word পরিবর্তন করো, লাইন reorder করো, বা একটা লাইন delete করো, file save করো, আর Git exactly যা তুমি লিখেছো তাই করবে।

## ২. প্রতিটা লাইনে যে command গুলো ব্যবহার করতে পারো

| Command | এটা কী করে |
|---|---|
| `pick` | এই commit টা as-is রাখো (প্রতিটা লাইনের default) |
| `reword` | Commit-এর পরিবর্তন রাখো, কিন্তু message edit করতে pause করো |
| `edit` | এই commit apply হওয়ার ঠিক পরেই pause করো, যাতে তুমি হাতে এর content amend করতে পারো |
| `squash` | এই commit-টা উপরের commit-এর সাথে combine করো, আর combined message edit করতে দেয় |
| `fixup` | এই commit-টা উপরের commit-এর সাথে combine করো, এই commit-এর message **নিরবে discard করে** |
| `drop` | এই commit সম্পূর্ণ remove করো (বা শুধু লাইন delete করো — একই effect) |
| Reorder | শুধু একটা লাইন উপরে/নিচে move করো — commit গুলো যে order-এ লাইনগুলো থাকবে সেই order-এ replay হয় |

![Interactive rebase diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/03-git-github/images/interactive-rebase.svg)

## ৩. একটা real example: "fix typo" commit পরিষ্কার করা

উপরের to-do list-কে এভাবে পরিবর্তন করে:

```
pick a1b2c3d Add login form
squash e4f5g6h fix typo
squash h7i8j9k fix typo again
reword l0m1n2o add validaton
```

...আর save করলে, Git: দুটো typo-fix commit-কে "Add login form"-এর সাথে ভাঁজ করে ফেলবে (একটা combined message লিখতে বলবে, বা শুধু প্রথমটাই রাখবে), তারপর শেষ commit-এ pause করবে যাতে তুমি এর message ঠিক করে "Add input validation" করতে পারো। চার-commit-এর messy trail দুটো clean commit হয়ে যায় যা আসলেই কী পরিবর্তন হয়েছে বলে — দুটো typo fix history থেকে সম্পূর্ণ বিলীন হয়ে যায়, কারণ তাদের পরিবর্তন এখন যে commit-টা তারা fix করছিল তার অংশ হয়ে গেছে।

`squash` বনাম `fixup` purely message নিয়ে: `squash` ব্যবহার করো যখন তুমি combined message review/edit করতে চাও, `fixup` ব্যবহার করো যখন যে commit ভাঁজ হচ্ছে তার একটা throwaway message আছে যা তোমার আবার দেখার প্রয়োজন নেই (যেমন "wip" বা "fix typo")।

## ৪. Chapter 2-এর সেই একই golden rule এখানে আরও জোরে applicable

Interactive rebase commit rewrite করে — এটা touch করা প্রতিটা commit নতুন hash পায়, plain rebase-এর মতোই। **অন্যরা আগে থেকেই pull করেছে বা commit push করেছে এমন branch-এ কখনো `git rebase -i` চালাবে না।** এটা Chapter 2-র চেয়ে এখানে আরও গুরুত্বপূর্ণ, কারণ interactive rebase প্রায়ই range-এর *প্রতিটা* commit rewrite করে, শুধু unchanged replay না।

Interactive rebase দিয়ে history পরিষ্কার করার ঠিক সময় হলো একটা branch প্রথমবার push করার **আগে**, বা যতক্ষণ তুমিই একমাত্র কাজ করছো — share, review, বা merge হওয়ার পরে না।

## ৫. যদি আগেই push করে ফেলেছো, আর rebase করা লাগে

যদি তুমি এমন একটা branch rebase করো যা আগেই push করেছিলে (আর অন্য কেউ এখনো পুল করেনি), তোমার local branch আর remote branch-এর history এখন diverge হয়ে গেছে — একটা plain `git push` reject হবে। তোমার force-push লাগবে:

```bash
git push --force-with-lease
```

Plain `--force` না, `--force-with-lease` ব্যবহার করো। `--force-with-lease` remote branch overwrite করতে refuse করে যদি তুমি last fetch করার পর থেকে অন্য কেউ এতে নতুন commit push করে থাকে — তোমাকে protect করে যদি তুমি না জেনে একজন টিমমেটের কাজ accidentally destroy করে ফেলার ঝুঁকিতে থাকো। Plain `--force`-এর এমন কোনো check নেই।

## ৬. মূল কথাগুলো (Key Takeaways)

- `git rebase -i HEAD~N` তোমার শেষ N টা commit-এর (সবচেয়ে পুরোনোটা প্রথমে) একটা editable to-do list খোলে যা Git literally execute করবে।
- `pick` একটা commit রাখে, `reword` message edit করতে দেয়, `edit` amend করতে pause করে, `squash`/`fixup` উপরের commit-এর সাথে merge করে, `drop` remove করে, আর reorder করলে replay order পরিবর্তন হয়।
- `squash` দুটো message-ই combine করার জন্য রাখে; `fixup` ভাঁজ হওয়া commit-এর message নিরবে discard করে।
- Interactive rebase শুধু তখনই চালাও যখন অন্য কেউ এই commit গুলো pull করেনি বা এর উপর কাজ করেনি — এটা plain rebase-এর মতোই history rewrite করে, usually আরও বেশি।
- একটা rebase করা branch force-push করতে হলে `git push --force-with-lease` ব্যবহার করো, কখনো plain `--force` না।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. Interactive rebase-এর to-do list-এ, কোন commit প্রথমে আসে — পুরোনো টা না নতুন টা?</strong></summary>

Range-এর সবচেয়ে পুরোনো commit প্রথমে (সবার উপরে) আসে, আর সবচেয়ে নতুনটা সবার শেষে। List টা উপর থেকে নিচে replay হয়, তাই Git এই order-এই এগুলো apply করবে।
</details>

<details>
<summary><strong>প্রশ্ন ২. `squash` আর `fixup`-এর practical পার্থক্য কী?</strong></summary>

দুটোই একটা commit-কে উপরের commit-এর সাথে combine করে। `squash` pause করে আর combined commit message edit করতে দেয় (দুটো original message-ই কাজ করার জন্য রাখে), যখন `fixup` commit-টা নিরবে ভাঁজ করে ফেলে, এর message সম্পূর্ণ discard করে আর শুধু উপরের commit-এর message রাখে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. Chapter 2-এর "shared branch কখনো rebase করবে না" নিয়মটা interactive rebase-এর জন্য specifically কেন আরও বেশি গুরুত্বপূর্ণ?</strong></summary>

Interactive rebase শুধু unchanged commit replay করে না — এটা প্রায়ই অনেক commit rewrite, reorder, combine, বা delete করে। Touch হওয়া প্রতিটা commit নতুন hash পায়, তাই অন্য কেউ আগে থেকেই পুরোনো commit গুলো pull করে থাকলে, একটা plain rebase-এর চেয়ে significantly বেশি history diverge হয়, যা reconcile করা অনেক বেশি messy করে তোলে।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একটা interactive rebase-এর পর কেন `git push --force` না বরং `git push --force-with-lease` ব্যবহার করা উচিত?</strong></summary>

`--force-with-lease` overwrite করার আগে check করে remote branch তুমি last fetch করার পর থেকে পরিবর্তন হয়েছে কিনা — একজন টিমমেট তুমি না জানা নতুন commit push করে থাকলে, এটা refuse করে safely fail করে। Plain `--force` unconditionally remote overwrite করে, যা অন্য কেউ just push করা commit নিরবে destroy করে দিতে পারে।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official docs: [Git Tools — Rewriting History (interactive rebase)](https://git-scm.com/book/en/v2/Git-Tools-Rewriting-History)
- Official docs: [`git rebase` reference](https://git-scm.com/docs/git-rebase)

---

**পরের চ্যাপ্টার:** Chapter 5 — Stash, Cherry-pick, and Reflog *(শীঘ্রই আসছে)*
