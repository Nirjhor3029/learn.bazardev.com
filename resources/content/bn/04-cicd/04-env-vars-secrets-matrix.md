# Chapter 4 — Environment Variable, Secret, আর Matrix Build

> **Course:** CI/CD (GitHub Actions) · **Chapter:** 4 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cicd/04-env-vars-secrets-matrix)

তিনটা practical tool যা real-world pipeline possible করে: তোমার step-এ configuration পাস করা, sensitive value নিরাপদে handle করা, আর একই job বারবার না লিখে একাধিক version-এর against test করা।

![Secrets flow and matrix build diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/04-cicd/images/secrets-and-matrix.svg)

## ১. `env:` দিয়ে Environment variable

`env:` key environment variable set করে যা তোমার step পড়তে পারে, আর workflow, job, বা individual step level-এ set করা যায় — একটা বেশি specific level একটা broader level-কে override করে:

```yaml
env:
  APP_ENV: testing          # এই workflow-এর প্রতিটা job-এ available

jobs:
  test:
    runs-on: ubuntu-latest
    env:
      DB_CONNECTION: sqlite  # এই job-এর প্রতিটা step-এ available
    steps:
      - run: echo $APP_ENV
        env:
          DEBUG: 'true'       # শুধু এই একটা step-এ available
```

GitHub Actions কিছু built-in variable-ও দেয় যা তুমি নিজে define করোনি — `github.sha` (build হওয়া commit), `github.ref` (branch/tag), `github.actor` (কে run trigger করেছে), আর অন্যান্য — workflow file-এর কোথাও `${{ github.sha }}` expression syntax দিয়ে accessible।

## ২. Secret — যে value তোমার code বা log-এ কখনো আসা উচিত না

Database password, API key, deployment credential — এগুলো কখনো তোমার repository-তে থাকা উচিত না (এটা exactly তুমি যেকোনো codebase-এ apply করা `.env`/credentials rule, শুধু pipeline level-এও enforce করা)। GitHub তোমাকে এর জন্য একটা dedicated, encrypted জায়গা দেয়: তোমার repository-তে **Settings → Secrets and variables → Actions**, যেখানে তুমি একটা নাম আর একটা value যোগ করো।

যোগ করা হয়ে গেলে, তোমার workflow-এ `${{ secrets.NAME }}` দিয়ে একটা secret reference করো:

```yaml
steps:
  - run: ./deploy.sh
    env:
      DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
      DEPLOY_KEY: ${{ secrets.DEPLOY_KEY }}
```

GitHub একটা log-এ কোনো secret-এর value দেখা গেলে automatically mask করে দেয় — একটা script accidentally এটা echo করলেও, log actual value-এর বদলে `***` দেখায়। এটা careless হওয়ার কোনো কারণ না (একটা secret এখনো একটা poorly লেখা script দিয়ে leak হতে পারে যা প্রথমে এটা transform করে), কিন্তু এটা একটা meaningful safety net।

Sensitive **না** এমন configuration-এর জন্য কিন্তু যা তুমি তবুও centrally manage করতে চাও — একটা default region name, একটা feature flag — GitHub **variable**-ও দেয় (`Settings → Secrets and variables → Actions → Variables` tab, `${{ vars.NAME }}` দিয়ে reference করা)। পার্থক্যটা purely sensitivity নিয়ে: exposed হলে real problem হবে এমন কিছুর জন্য secret ব্যবহার করো, বাকি সবকিছুর জন্য variable।

## ৩. Matrix build — একটা job definition, অনেক parallel run

ধরো তোমার test suite PHP 8.2 আর PHP 8.3 দুটোতেই pass করা লাগবে, বা Ubuntu আর Windows দুটোতেই। প্রতিটা combination-এর জন্য একটা আলাদা near-identical job লেখা exactly সেই ধরনের repetition যা `strategy.matrix` eliminate করার জন্য exist করে:

```yaml
jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.2', '8.3']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
      - run: composer install
      - run: php artisan test
```

এই single job definition automatically **দুটো** actual job-এ expand হয় — একটা `matrix.php` `'8.2'`-এর সমান, একটা `'8.3'`-এর সমান — parallel-এ চলে, প্রতিটা সম্পূর্ণ independent runner। একটা দ্বিতীয় matrix dimension যোগ করো (`os: [ubuntu-latest, windows-latest]`) আর তুমি **চারটা** job পাবে total: দুটো list-এর প্রতিটা combination।

Default-এ, একটা matrix combination fail করলে, GitHub সময় বাঁচাতে বাকিগুলো cancel করে। `fail-fast: false` set করো যদি তুমি চাও প্রতিটা combination যাই হোক finish হোক, তাই তুমি দেখতে পারো exactly কোন version গুলো আসলে ভেঙেছে:

```yaml
strategy:
  fail-fast: false
  matrix:
    php: ['8.2', '8.3']
```

## ৪. মূল কথাগুলো (Key Takeaways)

- `env:` workflow, job, বা step level-এ environment variable set করে; একটা বেশি specific level একটা broader level-কে override করে।
- `${{ github.sha }}` আর `${{ github.actor }}`-এর মতো built-in context variable automatically available, তুমি নিজে define না করেই।
- Secret repository settings-এ থাকে, `${{ secrets.NAME }}` দিয়ে reference হয়, আর GitHub automatically log থেকে এর value mask করে — কখনো একটা sensitive value সরাসরি একটা workflow file-এ রাখো না।
- Non-sensitive shared configuration **variable**-এ থাকে (`${{ vars.NAME }}`), secret-এ না — পার্থক্যটা purely sensitivity নিয়ে।
- `strategy.matrix` একটা job definition কে list করা value-এর প্রতিটা combination-এর জন্য একটা parallel job-এ expand করে — `fail-fast: false` একটা fail হওয়ার পরেও সব combination চলমান রাখে।

## ৫. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. একটা workflow workflow level-এ `APP_ENV: testing` set করে, আর একটা specific step-ও `APP_ENV: staging` set করে, সেই step আসলে কী value দেখে?</strong></summary>

`staging` — একই variable name-এর জন্য একটা বেশি specific level (step) একটা broader level (workflow)-কে override করে। Workflow-level value এখনো বাকি প্রতিটা step-এ apply হয় যা এটা override করে না।
</details>

<details>
<summary><strong>প্রশ্ন ২. একটা workflow step-এর একটা script accidentally `echo` দিয়ে একটা secret-এর value print করলে কী হয়?</strong></summary>

GitHub automatically log output-এ known secret value detect করে mask করে, actual value-কে `***` দিয়ে replace করে এমনকি একটা step এটা সরাসরি print করার চেষ্টা করলেও — যদিও এটা একটা value print করার আগে transform করার প্রতিটা সম্ভাব্য উপায়ের against foolproof না।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা default AWS region name-এর মতো কিছুর জন্য তুমি "secret"-এর বদলে একটা GitHub Actions "variable" কেন ব্যবহার করবে?</strong></summary>

Variable আর secret purely sensitivity handling-এ আলাদা — secret encrypted আর log থেকে masked, যা আসলেই sensitive কিছুর জন্য matter করে। একটা region name sensitive information না, তাই এটা variable-এ থাকে, যা simpler আর তবুও centrally manage করা, secret-এর লাগা extra masking machinery ছাড়াই।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একটা matrix `php: ['8.2', '8.3']` আর `os: [ubuntu-latest, windows-latest]` define করে। এটা কতগুলো actual job বানায়, আর কেন?</strong></summary>

চারটা — একটা matrix সব list করা dimension-এর প্রতিটা combination-এর জন্য একটা job-এ expand হয়, তাই দুটো PHP version গুণ দুটো operating system 2 × 2 = 4 টা আলাদা, parallel job বানায়, প্রতিটার `matrix.php` আর `matrix.os`-এর একটা আলাদা combination।
</details>

## ৬. ফ্রি রিসোর্স (English)

- Official guide: [Using secrets in GitHub Actions](https://docs.github.com/en/actions/security-guides/using-secrets-in-github-actions)
- Official guide: [Store information in variables](https://docs.github.com/en/actions/learn-github-actions/variables)
- Official guide: [Using a matrix for your jobs](https://docs.github.com/en/actions/using-jobs/using-a-matrix-for-your-jobs)

---

**পরের চ্যাপ্টার:** Chapter 5 — Caching and artifacts *(শীঘ্রই আসছে)*
