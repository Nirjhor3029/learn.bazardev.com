# Chapter 2 — GitHub Actions Basics: Workflow, Job, Step

> **Course:** CI/CD (GitHub Actions) · **Chapter:** 2 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cicd/02-github-actions-basics)

Chapter 1 CI/CD conceptually describe করেছিল। **GitHub Actions** হলো GitHub-এর নিজের built-in tool আসলে একটা চালানোর জন্য — কোনো আলাদা service-এ sign up করা লাগে না, কোনো extra account লাগে না। এই chapter তিনটা building block cover করে যা দিয়ে প্রতিটা workflow বানানো।

![Workflow, job, and step hierarchy diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/04-cicd/images/workflow-job-step-hierarchy.svg)

## ১. একটা workflow শুধু একটা YAML file

তোমার repository-তে `.github/workflows/*.yml`-এ রাখা যেকোনো file একটা workflow। GitHub এটা পড়ে, আর তুমি specify করা condition মিললে এটা চালায়:

```yaml
name: CI

on: push

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install
      - run: php artisan test
```

- **`name`** — এই workflow-এর জন্য GitHub-এর "Actions" tab-এ কী দেখায়।
- **`on`** — এটা কী trigger করে (এখানে, প্রতিটা push; Chapter 3 এটা গভীরভাবে cover করে)।
- **`jobs`** — এক বা একাধিক named job যা এই workflow চালায়।

## ২. একটা job নিজের fresh virtual machine-এ চলে

একটা job-এর `runs-on` key-এর নিচে যা কিছু আছে তা **runner** বেছে নেয় — একটা virtual machine যা GitHub এই job-এর জন্যই spin up করে, তোমার step গুলো এর উপর চালায়, তারপর শেষ হয়ে গেলে সম্পূর্ণ ফেলে দেয়। `ubuntu-latest` সবচেয়ে common choice; `windows-latest` আর `macos-latest` platform-specific build-এর জন্য exist করে (আর তুমি নিজের self-hosted runner-ও চালাতে পারো, specialized hardware বা network access-এর জন্য)।

প্রতিটা runner সম্পূর্ণ খালি শুরু হয় বলে, **প্রায় প্রতিটা job-এর প্রথম step** হলো `actions/checkout@v4` — এটা ছাড়া, তোমার workflow একটা খালি folder-এ চলবে, তোমার repository-র code-এ কোনো access ছাড়াই।

**একই workflow-এর একাধিক job default-এ parallel-এ চলে**, প্রতিটা নিজের আলাদা runner-এ, একে অপরের থেকে সম্পূর্ণ independent — exactly diagram-এ যেমন `test` আর `lint` একই সময়ে শুরু হচ্ছে দেখায়। একটা job-এর আসলেই অন্যটা আগে শেষ হওয়া লাগলে (যেমন, deploy-র আগে build), তুমি `needs:` দিয়ে এটা explicitly declare করো — পরের একটা chapter-এ আরও cover করা।

## ৩. একটা step হয় একটা command বা একটা reusable action

একটা job-এর ভেতরে, step গুলো **sequentially, উপর থেকে নিচে, সেই একই runner-এ** চলে — প্রতিটা এর আগের step রেখে যাওয়া file আর state দেখতে পারে। একটা step দুটো জিনিসের একটা:

- **`run:`** — একটা literal shell command, exactly যেমনটা তুমি একটা terminal-এ type করতে। `run: php artisan test` শুধু runner-এ সেই command চালায়।
- **`uses:`** — একটা reusable, pre-built **action** যা অন্য কেউ (often GitHub নিজেই, বা broader community) already লিখে ফেলেছে, `owner/repo@version` দিয়ে reference করা। `actions/checkout@v4` হলো GitHub-এর নিজের action তোমার repository-র code fetch করার জন্য; `shivammathur/setup-php@v2` হলো একটা popular community action যা runner-এ একটা specific PHP version install করে।

Action `with:` দিয়ে input নিতে পারে, উপরে `php-version: '8.3'`-এর মতো দেখানো হয়েছে। তোমার খুব কমই নিজের action শূন্য থেকে লেখা লাগবে — GitHub-এর [Marketplace](https://github.com/marketplace?type=actions)-এ almost প্রতিটা common কাজের জন্য একটা pre-built action আছে (একটা language runtime setup করা, একটা cloud provider-এ deploy করা, একটা Slack notification পাঠানো)।

## ৪. এটা চলা দেখা

একটা workflow trigger করা প্রতিটা push তোমার repository-র **Actions** tab-এর নিচে দেখা যায়, প্রতিটা run-কে একটা green check বা red X দিয়ে list করে। একটা run open করলে প্রতিটা job দেখায়, আর একটা job open করলে প্রতিটা step-এর live/complete log output দেখায় — exactly তুমি নিজে একটা terminal-এ সেই command গুলো চালালে যা দেখতে, যা একটা fail হওয়া step debug করা সহজ করে দেয়: red step খুঁজো, এর log পড়ো।

## ৫. মূল কথাগুলো (Key Takeaways)

- একটা workflow `.github/workflows/*.yml`-এ একটা YAML file, এটা কী trigger করে (`on`) আর এটা কী চালায় (`jobs`) define করে।
- প্রতিটা job নিজের fresh, disposable virtual machine-এ চলে ("runner"); `actions/checkout@v4` almost সবসময় প্রথম step, কারণ runner কোনো code ছাড়াই শুরু হয়।
- একই workflow-এর একাধিক job default-এ parallel-এ চলে; একটা job-এর ভেতরের step গুলো একই runner-এ sequentially চলে।
- একটা step হয় `run:` (একটা raw shell command) বা `uses:` (একটা reusable, versioned action, often GitHub-এর Marketplace থেকে)।
- Actions tab প্রতিটা run, job, আর step-এর log দেখায় — কিছু fail হলে প্রথম যেখানে দেখা উচিত।

## ৬. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. Almost প্রতিটা job কেন একটা `actions/checkout@v4` step দিয়ে শুরু হয়?</strong></summary>

একটা job-এর runner একটা সম্পূর্ণ fresh virtual machine যার উপর কিছুই নেই — তোমার repository-র নিজের code-ও না। Checkout action তোমার repository-র file runner-এ fetch করে আনে যাতে পরের step গুলোর (dependency install করা, test চালানো) আসলে কাজ করার মতো কিছু থাকে।
</details>

<details>
<summary><strong>প্রশ্ন ২. একটা workflow যদি দুটো job define করে, `test` আর `lint`, এদের মাঝে কোনো `needs:` relationship ছাড়া, এগুলো কোন order-এ চলে?</strong></summary>

এগুলো parallel-এ চলে, essentially একই সময়ে শুরু হয়ে, প্রতিটা নিজের আলাদা runner-এ — একটা workflow-এর job গুলো default-এ concurrently চলে যদি না একটা `needs:` dependency explicitly বলে একটাকে অন্যটার জন্য wait করতে হবে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা `run:` step আর একটা `uses:` step-এর পার্থক্য কী?</strong></summary>

`run:` runner-এ সরাসরি একটা literal shell command execute করে, exactly যেমন type করা হয়েছে। `uses:` বরং `owner/repo@version` দিয়ে reference করা একটা pre-built, versioned action চালায় — অন্য কেউ লেখা reusable packaged functionality, যা `with:` দিয়ে configuration accept করতে পারে।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একটা workflow run "test" job-এ একটা red X দেখায়। কী ভুল হয়েছে জানতে তুমি প্রথমে কোথায় দেখবে?</strong></summary>

Repository-র Actions tab-এ সেই run open করো, fail হওয়া job open করো, আর এর step-by-step log দেখো — specific যে step-এ red mark আছে সেটা actual command output আর error দেখাবে, নিজে terminal-এ চালালে যা দেখতে সেই একই output।
</details>

## ৭. ফ্রি রিসোর্স (English)

- Official guide: [Understanding GitHub Actions](https://docs.github.com/en/actions/learn-github-actions/understanding-github-actions)
- Official guide: [Workflow syntax for GitHub Actions](https://docs.github.com/en/actions/writing-workflows/workflow-syntax-for-github-actions)
- Browse: [GitHub Actions Marketplace](https://github.com/marketplace?type=actions)

---

**পরের চ্যাপ্টার:** Chapter 3 — Triggers: events, branches, paths, schedules *(শীঘ্রই আসছে)*
