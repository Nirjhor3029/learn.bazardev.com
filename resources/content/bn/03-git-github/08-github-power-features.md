# Chapter 8 — GitHub Power Features

> **Course:** Git & GitHub (Advanced) · **Chapter:** 8 of 8 (শেষ চ্যাপ্টার)
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/git-github/08-github-power-features)

এই শেষ chapter Git নিজের বাইরে, আর Chapter 6-এর pull request flow-এর বাইরে GitHub feature-গুলোর একটা tour — এমন কিছু জিনিস যা নিরবে একটা well-run repository-কে organized মনে করায়, কোনো process ছাড়া code-এর স্তুপের বদলে।

![GitHub power features diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/03-git-github/images/github-power-features.svg)

## ১. Issue, label, আর milestone

একটা **issue** হলো কাজের একটা tracked unit — একটা bug report, একটা feature request, একটা task — যার নিজের discussion thread আছে, কোনো specific commit বা branch থেকে independent। **Label** (`bug`, `good first issue`, `priority:high`) একটা লম্বা issue list কে এক নজরেই filterable করে তোলে। একটা **milestone** issue আর PR-কে একটা specific target-এর দিকে group করে (যেমন "v2.0 release"), GitHub তোমাকে কত হয়ে গেছে তার একটা simple progress bar দেখিয়ে।

একটা commit message বা PR description থেকে `#42` দিয়ে একটা issue reference করো, GitHub automatically এটাকে একটা clickable link বানিয়ে দেয় — আর Chapter 6-এ cover করা মতো, একটা PR-এ `Closes #42` merge-এ সেই issue auto-close করে দেয়।

## ২. `.github/` — যে file গুলো GitHub automatically পড়ে

Repo root-এর একটা `.github/` folder-এ কিছু specially-named file থাকলে, GitHub-এর behavior অন্য কোনো configuration ছাড়াই পরিবর্তন হয়ে যায়:

- **`PULL_REQUEST_TEMPLATE.md`** — কেউ একটা নতুন PR open করলেই description box pre-fill করে দেয়, তাই তুমি "কী পরিবর্তন হয়েছে / কেন / কীভাবে test করা হয়েছে" একটা habit হিসেবে enforce করতে পারো, মানুষ মনে রাখবে বলে আশা করার বদলে।
- **`ISSUE_TEMPLATE/`** — template-গুলোর একটা folder (বা একটা নতুন YAML-based structured form) যা bug report আর feature request-কে একটা consistent shape দেয়, একটা blank box-এর বদলে।
- **`CODEOWNERS`** — file path কে GitHub username/team-এর সাথে map করে, তাই `/backend/**` touch করা একটা PR automatically সেই path-এর owner-এর কাছ থেকে review request করে। Chapter 1-এর branch protection-এর সাথে combine করে, তুমি specifically সেই owner-এর approval require করতে পারো, শুধু কোনো approval না।

## ৩. GitHub CLI (`gh`)

github.com-এর web UI-এ যা কিছু আছে তার একটা command-line equivalent `gh` দিয়ে আছে, যা একবার muscle memory হয়ে গেলে আসলেই faster — একটা PR open করতে বা CI status check করতে কোনো browser tab-switching লাগে না:

```bash
gh pr create --fill              # তোমার branch-এর commit থেকে title/body দিয়ে একটা PR open করো
gh pr status                     # তোমার open PR গুলোর state দেখো
gh pr checkout 42                # PR #42-এর পিছনের branch fetch করে switch করো, locally review করতে
gh issue create --title "Bug: login fails on Safari"
gh repo clone Nirjhor3029/devops-for-developers
```

## ৪. Release আর tag

একটা **tag** হলো একটা specific commit-এর একটা permanent, named pointer — একটা branch-এর থেকে আলাদা, এটা নতুন commit যোগ হলে forward move করে না। Tag হলো project version number mark করার উপায়:

```bash
git tag v1.2.0
git push --tags
```

একটা GitHub **Release** একটা tag-এর উপর build করে: একটা dedicated page release notes, একটা changelog, আর optionally attached file (compiled binary, সেই exact point-এ source-এর একটা zip) সহ। GitHub এমনকি শেষ release-এর পর থেকে merge হওয়া PR থেকে notes auto-generate করতে পারে।

## ৫. জানা দরকার এমন আরও কিছু

- **Gist** — একটা quick, shareable link দিয়ে share করা একটা single file (বা কয়েকটা), নিজের একটা lightweight version history সহ — এমন একটা code snippet বা config example-এর জন্য useful যার একটা পুরো repository লাগে না।
- **Code search** (`github.com/search`, বা কোনো page-এ `/` press করো) — পুরো GitHub-এ (বা একটা repo/org-এ scoped) code search করে, symbol আর regex match-ও সহ, একটা browser-এর find-on-page-এর থেকে অনেক বেশি।
- **Saved reply** — কোনো issue/PR comment box-এ insert করতে পারা canned response, একটা repository-র common "দয়া করে template fill করো" বা "দয়া করে main-এর উপর rebase করো" response-এর জন্য কাজে লাগে।
- **GitHub Actions** — একটা push বা PR-এর মতো event-এ automatically test, build, বা deployment চালানো — যথেষ্ট powerful যে এই series-এর পরে নিজের একটা পুরো course (CI/CD) পাওয়ার যোগ্য, তাই এই chapter শুধু এটা exist করে আর Chapter 6-এর `workflows/ci.yml` status check-এর সাথে সরাসরি connect করে তা flag করে।

## ৬. মূল কথাগুলো (Key Takeaways)

- Issue, label, আর milestone কোনো specific branch বা commit থেকে independent কাজ আর discussion organize করে; একটা PR-এ `Closes #N` দুটোকে connect করে।
- `.github/PULL_REQUEST_TEMPLATE.md`, `ISSUE_TEMPLATE/`, আর `CODEOWNERS` হলো সেই file যা GitHub automatically পড়ে PR, issue, আর review assignment standardize করতে।
- `gh` CLI github.com-এর web UI-এ almost সবকিছু mirror করে, terminal না ছেড়েই PR আর issue create/review করতে দেয়।
- একটা tag একটা commit-এর একটা permanent pointer (version number-এর জন্য ব্যবহার হয়); একটা GitHub Release একটা tag-এর উপর একটা notes-and-files page বানায়।
- GitHub Actions (CI/CD automation) সরাসরি Chapter 6-এর status check-এর সাথে connect করে, আর এই series-এর পরে নিজের একটা dedicated course পায়।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. একটা PR description-এ "Closes #42" লেখা কী করে, আর কোন আগের chapter এটা cover করেছিল?</strong></summary>

এটা PR-কে issue #42-এর সাথে link করে আর PR merge হওয়ার সাথে সাথেই সেই issue automatically close করে দেয় — এটা প্রথমে Chapter 6-এ (Pull Requests and Code Review) cover করা হয়েছিল।
</details>

<details>
<summary><strong>প্রশ্ন ২. একটা repository-র যদি `/backend/**` কে একটা specific team-এর সাথে map করা একটা `CODEOWNERS` file থাকে, আর branch protection owner approval require করে, কেউ শুধু `/frontend/**` file touch করা একটা PR open করলে কী হয়?</strong></summary>

সেই specific CODEOWNERS rule apply হবে না, কারণ PR কোনো `/backend/**` path touch করে না — এটা শুধু actual পরিবর্তন হওয়া `/frontend/**` path-এর সাথে map করা owner (থাকলে) থেকে একটা required review trigger করবে, বা CODEOWNERS-এ সেই path-এর সাথে কিছু match না করলে কোনো automatic owner review-ই হবে না।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা Git tag আর একটা Git branch-এর মূল পার্থক্য কী?</strong></summary>

একটা branch হলো একটা pointer যা এতে নতুন commit যোগ হলে automatically forward move করে। একটা tag হলো একটা specific commit-এর একটা permanent pointer — এটা move করে না, আর ঠিক এই কারণেই tag `v1.2.0`-এর মতো fixed version number mark করতে ব্যবহার হয়।
</details>

<details>
<summary><strong>প্রশ্ন ৪. এই chapter কেন GitHub Actions-কে গভীরভাবে cover করার বদলে শুধু সংক্ষেপে mention করে?</strong></summary>

GitHub Actions (automated CI/CD — push বা PR-এর মতো event-এ test, build, আর deployment চালানো) যথেষ্ট substantial যে এই series-এর পরে নিজের একটা পুরো course পাওয়ার যোগ্য, তাই এই chapter শুধু এটাকে Chapter 6-এর status check concept-এর সাথে connect করে, এখানে গভীরভাবে না শিখিয়ে।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official guide: [About issues](https://docs.github.com/en/issues/tracking-your-work-with-issues/about-issues)
- Official guide: [About code owners](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/about-code-owners)
- Official guide: [GitHub CLI manual](https://cli.github.com/manual/)
- Official guide: [About releases](https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases)

---

## কোর্স সম্পন্ন

এটাই **Git & GitHub (Advanced)**-এর সব ৮টা chapter — branching strategy বেছে নেওয়া থেকে শুরু করে, merge vs rebase, conflict resolve করা, history পরিষ্কার করা, local recovery toolkit, pull request, safely ভুল undo করা, আর পুরো টিমের workflow একসাথে বেঁধে রাখা GitHub feature পর্যন্ত।

Roadmap-এর আরেকটা course continue করতে [course হোমপেজ](https://learn.bazardev.com)-এ ফিরে যাও।
