# Chapter 3 — Trigger: Event, Branch, Path, Schedule

> **Course:** CI/CD (GitHub Actions) · **Chapter:** 3 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cicd/03-triggers-and-events)

Chapter 2 খুব বেশি ব্যাখ্যা ছাড়াই `on: push` ব্যবহার করেছিল। `on:` key আসলে তোমার pipeline-এর উপর অনেক practical control রাখে — একটা workflow exactly কখন চলবে decide করা এটা শুরু হওয়ার পর কী করবে তার মতোই গুরুত্বপূর্ণ, কারণ unnecessarily এটা চালানো সময়, আর paid plan-এ, real money waste করে।

![Workflow triggers diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/04-cicd/images/workflow-triggers.svg)

## ১. Common event type

```yaml
on:
  push:
  pull_request:
  workflow_dispatch:
  schedule:
    - cron: '0 2 * * *'
```

- **`push`** — repository-তে commit push হলেই fire হয়।
- **`pull_request`** — একটা PR open হলে, বা already open একটা PR-এর branch-এ নতুন commit push হলে fire হয়। এটাই exactly [Git & GitHub Chapter 6](/git-github/06-pull-requests-and-code-review)-এর status-check mechanism।
- **`workflow_dispatch`** — GitHub UI-এর Actions tab-এ একটা manual "Run workflow" button যোগ করে। এমন কিছুর জন্য useful যা তুমি automatically-র বদলে on-demand trigger করতে চাও, যেমন একটা production deploy যা তুমি একজন human explicitly চালানোর decision নিবে চাও।
- **`schedule`** — cron syntax ব্যবহার করে একটা timer-এ চলে, কোনো commit activity থেকে সম্পূর্ণ independent। `cron: '0 2 * * *'` মানে "প্রতিদিন রাত ২:০০ UTC-তে" — nightly test run বা scheduled cleanup job-এর জন্য handy। (GitHub note করে high load-এর সময় scheduled run সামান্য delayed হতে পারে — critical কিছুর জন্য exact timing-এর উপর নির্ভর করো না।)

তুমি একাধিক event list করতে পারো, আর এর মধ্যে **কোনো একটা** match করলেই workflow চলে।

## ২. Branch দিয়ে `push` আর `pull_request` সীমিত করা

বেশিরভাগ workflow-র প্রতিটা একক branch-এ চলা লাগে না। একটা common pattern:

```yaml
on:
  push:
    branches: [main]
  pull_request:
    branches: [main]
```

এটা `main`-এ direct push-এ workflow চালায়, আর `main`-কে **target** করা যেকোনো PR-এ — কিন্তু, ধরো, একজন developer তার নিজের feature branch-এ review-এর জন্য ready হওয়ার আগেই experimental commit push করলে না। তুমি pattern-ও ব্যবহার করতে পারো: `branches: ['release/**']` `release/` দিয়ে শুরু হওয়া যেকোনো branch match করে।

## ৩. কোন file পরিবর্তন হয়েছে তা দিয়ে সীমিত করা

একটা workflow যা শুধু PHP code test করে কেউ একটা README update করলেই আবার চলা লাগে না। `paths` আর `paths-ignore` একটা push বা PR আসলে কোন file touch করেছে তার উপর filter করে:

```yaml
on:
  push:
    paths-ignore:
      - 'docs/**'
      - '**.md'
```

এটা workflow সম্পূর্ণ skip করে যখন প্রতিটা পরিবর্তিত file ignore pattern-গুলোর একটার সাথে match করে। `paths-ignore:`-এর বদলে `paths:` দিয়ে এটা উল্টো করো skip-এর বদলে match require করতে — একটা monorepo-তে useful যেখানে তুমি শুধু একটা service-এর test চালাতে চাও যখন সেই service-এর নিজের folder পরিবর্তন হয়েছে।

## ৪. একটা practical combined example

```yaml
name: CI

on:
  push:
    branches: [main]
    paths-ignore: ['docs/**']
  pull_request:
    branches: [main]
  workflow_dispatch:

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: composer install
      - run: php artisan test
```

এটা `main`-কে target করা push আর PR-এ automatically চলে (docs-only পরিবর্তন skip করে), **আর** repo access থাকা কাউকে একটা manual button দেয় demand-এ সেই একই test trigger করতে — তিনটা আলাদা workflow file ছাড়াই।

## ৫. Forked pull request নিয়ে একটা security note

একটা **forked repository** থেকে আসা `pull_request` event (তোমার project-এর বাইরের কেউ একটা PR open করলে) একটা restricted token দিয়ে চলে যা তোমার repository-র secrets access করতে বা পরিবর্তন push back করতে পারে না — একটা safety measure, কারণ একজন stranger-এর PR-এ কী code আছে তা তুমি control করো না। একটা আলাদা event, `pull_request_target`, বরং full access দিয়ে চলে, কিন্তু fork-এর বদলে তোমার base branch-এর workflow file-এর against চলে — এটা carelessly ব্যবহার করলে একটা malicious fork-এর code তোমার repository-র real permission দিয়ে চলতে পারে। বেশিরভাগ project-এর জন্য, default `pull_request` behavior exactly যা তুমি চাও আর কোনো special handling লাগে না।

## ৬. মূল কথাগুলো (Key Takeaways)

- `on:` একটা workflow trigger করতে এক বা একাধিক event list করে; এর মধ্যে কোনো একটা ঘটলেই এটা চলে।
- `push` আর `pull_request` real repository activity থেকে fire হয়; `schedule` কোনো commit থেকে independent একটা cron timer-এ fire হয়; `workflow_dispatch` একটা manual trigger button যোগ করে।
- `branches:` একটা trigger কে specific branch name বা pattern-এ সীমিত করে (যেমন `release/**`)।
- `paths:` / `paths-ignore:` একটা trigger কে আসলে কোন file পরিবর্তন হয়েছে তার উপর সীমিত করে, irrelevant পরিবর্তনে wasted run এড়িয়ে।
- একটা fork থেকে `pull_request` default-এ restricted permission দিয়ে চলে — untrusted code-এর against একটা deliberate safety measure।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. `push` আর `schedule` trigger কীভাবে fire হয় তাতে পার্থক্য কী?</strong></summary>

`push` real repository activity-র সরাসরি response-এ fire হয় — কেউ commit push করলে। `schedule` purely একটা cron timer-এর ভিত্তিতে fire হয়, কোনো commit আসলে হয়েছে কিনা তা থেকে সম্পূর্ণ independent, repository activity নির্বিশেষে configured সময়ে চলে।
</details>

<details>
<summary><strong>প্রশ্ন ২. একটা workflow-এর `push` trigger-এর নিচে `paths-ignore: ['docs/**']` আছে। একটা commit শুধু `docs/setup.md` পরিবর্তন করে। Workflow চলে কি?</strong></summary>

না — একমাত্র পরিবর্তিত file ignore pattern-এর সাথে match করে বলে, সেই push-এর জন্য পুরো workflow run skip হয়ে যায়, workflow যে code নিয়ে care করে তা affect না করা একটা পরিবর্তনে wasted CI time এড়িয়ে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা team already `push`-এ automatically চলা একটা workflow-এ `workflow_dispatch` কেন যোগ করবে?</strong></summary>

এটা GitHub UI-এ একটা manual "Run workflow" button যোগ করে, কাউকে demand-এ সেই একই workflow trigger করতে দেয় — একটা নতুন commit না বানিয়ে একটা deploy re-run করার মতো case-এর জন্য useful, বা কোনো specific push-এর সাথে unrelated একটা কারণে এটা trigger করতে।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একটা forked repository-র PR থেকে আসা `pull_request` event GitHub কেন restricted permission দিয়ে চালায়?</strong></summary>

কারণ একটা fork থেকে আসা PR-এ project-এর বাইরের কারো code থাকতে পারে, full access (secrets সহ) দিয়ে এটা চালালে untrusted code potentially secrets exfiltrate করতে বা পরিবর্তন push করতে পারে। Fork-based `pull_request` event-এর জন্য restricted token সেই risk-এর against একটা deliberate safeguard।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official guide: [Events that trigger workflows](https://docs.github.com/en/actions/writing-workflows/choosing-when-your-workflow-runs/events-that-trigger-workflows)
- Official guide: [Triggering a workflow (filters, paths, branches)](https://docs.github.com/en/actions/writing-workflows/choosing-when-your-workflow-runs/triggering-a-workflow)

---

**পরের চ্যাপ্টার:** Chapter 4 — Environment variables, secrets, and matrix builds *(শীঘ্রই আসছে)*
