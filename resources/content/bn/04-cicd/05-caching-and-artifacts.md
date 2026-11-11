# Chapter 5 — Caching আর Artifact

> **Course:** CI/CD (GitHub Actions) · **Chapter:** 5 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cicd/05-caching-and-artifacts)

[Chapter 2](/cicd/02-github-actions-basics) mention করেছিল প্রতিটা job একটা fresh, disposable runner-এ চলে। এটা consistency-র জন্য great, কিন্তু এর একটা real cost আছে: `composer install` বা `npm install` প্রতিটা একক run-এ শুরু থেকে চলে, প্রতিটা dependency download করে — এমনকি গতকাল থেকে তোমার dependency-তে কিছু পরিবর্তন না হলেও। Caching আর artifact দুটো আলাদা problem solve করে যা দুটোই এই "প্রতিটা run খালি শুরু হয়" reality থেকে আসে।

![Caching and artifacts diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/04-cicd/images/caching-and-artifacts.svg)

## ১. Caching — কিছু পরিবর্তন না হলে গতকালের download reuse করো

`actions/cache` একটা workflow-কে একটা run-এর পর একটা folder (যেমন `vendor/` বা `node_modules/`) save করতে দেয়, আর পরের একটা run-এ এটা restore করে — তুমি দেওয়া **key** match করলে:

```yaml
steps:
  - uses: actions/checkout@v4
  - uses: actions/cache@v4
    with:
      path: vendor
      key: composer-${{ hashFiles('composer.lock') }}
      restore-keys: composer-
  - run: composer install
```

`key` usually তোমার lock file-এর একটা hash — মানে cache key শুধু তখনই পরিবর্তন হয় যখন তোমার actual dependency পরিবর্তন হয়। `composer.lock` যদি last time-এর identical হয়, key match করে, GitHub save করা `vendor/` folder restore করে, আর `composer install` almost instantly শেষ হয় (এটা শুধু confirm করে সবকিছু already আছে)। `composer.lock` পরিবর্তন হলে, key cache হওয়া কিছুর সাথে match করে না — একটা **cache miss** — আর `composer install` একটা normal full install করে, তারপর পরের বার জন্য নতুন key-এর under একটা নতুন cache entry save করে।

`restore-keys` exact key খুঁজে না পেলে একটা close-but-not-exact cache match করার জন্য একটা fallback prefix দেয় — কোনোটাই না থেকে একটু outdated cache (বেশিরভাগ dependency unchanged) restore করা ভালো।

একটা cache miss কখনো একটা failure না — এটা শুধু মানে caching না থাকলে যত slow হতো সেই একটা run তত slow। একটা "too strict" cache key ব্যবহারে কোনো risk নেই; downside purely speed, কখনো correctness না।

Cache বেশিরভাগ branch দিয়ে scoped, একটা combined size limit আছে (প্রতি repository প্রায় 10GB), আর 7 দিন unused entry automatically evict হয়ে যায় — তোমার নিজে cleanup manage করা লাগে না।

## ২. Artifact — real file job-এর মধ্যে বহন করো, বা pipeline থেকে সম্পূর্ণ বের করো

একটা cache **সময়ের সাথে আলাদা run-এ একটা repeated step speed up করার** জন্য। একটা artifact সম্পূর্ণ আলাদা একটা problem solve করে: **একই run-এর ভেতরে** একটা job থেকে অন্য একটায় একটা actual output file পৌঁছানো, বা workflow শেষ হওয়ার পর একজন human download করতে পারে এমন করা।

```yaml
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: npm run build
      - uses: actions/upload-artifact@v4
        with:
          name: dist-build
          path: dist/

  deploy:
    needs: build
    runs-on: ubuntu-latest
    steps:
      - uses: actions/download-artifact@v4
        with:
          name: dist-build
          path: dist/
      - run: ./deploy.sh
```

[Chapter 2](/cicd/02-github-actions-basics)-এ যেমন বলেছিল: প্রতিটা job নিজের আলাদা runner পায়, এগুলোর মাঝে automatically কিছু shared না। `build` job-এর `dist/` folder শুধু `build`-এর runner-এ exist করে — সেই job শেষ হওয়ার সাথে সাথেই এটা চলে যায়, কিছু explicitly forward না বহন করলে। `upload-artifact` এটা এই workflow run-এর সাথে attached একটা named artifact হিসেবে save করে; `deploy` job-এ `download-artifact` (যা `needs: build` ব্যবহার করে এর পরে চলে, parallel-এ না) সেই exact folder নিজের, সম্পূর্ণ আলাদা runner-এ ফিরিয়ে আনে।

Artifact human-এর জন্যও সরাসরি useful: test report, coverage output, build log, বা একটা fail হওয়া UI test-এর screenshot সবকিছু workflow run-এর summary page-এ সরাসরি downloadable file হিসেবে দেখা যায় — locally reproduce করতে না পারা একটা failure debug করার সময় আসলেই helpful। Default-এ, GitHub artifact 90 দিন রাখে (per-upload `retention-days` দিয়ে configurable)।

## ৩. যে পার্থক্যটা গুরুত্বপূর্ণ

| | Cache | Artifact |
|---|---|---|
| Purpose | Speed — পরিবর্তন হয়নি এমন কাজ আবার না করা | Transport — real output job-এর মাঝে, বা একজন human-এর কাছে move করা |
| Scope | সময়ের সাথে আলাদা run-এ | একই run-এর ভেতরে (job থেকে job), বা সেই run শেষ হওয়ার পর |
| Missing হলে | নিরব, শুধু slower — কখনো কিছু ভাঙে না | যে job এটা expect করে সেটা পুরোপুরি fail করবে missing হলে |
| Typical content | Dependency folder (`vendor/`, `node_modules/`) | Build output, test report, log, screenshot |

## ৪. মূল কথাগুলো (Key Takeaways)

- `actions/cache` এর key (usually একটা lock-file hash) match করলে আগে save করা একটা folder restore করে — একটা miss শুধু মানে একটা normal, slower install, কখনো failure না।
- `restore-keys` exact cache key খুঁজে না পেলে একটা fallback prefix match দেয়।
- `actions/upload-artifact` আর `actions/download-artifact` একই run-এর job-এর মাঝে actual file move করে (job-গুলোর সেই order-এ চলার জন্য `needs:` লাগে), আর পরে run-এর summary page থেকে file downloadable করে তোলে।
- Cache purely একটা speed optimization; artifact একটা real data dependency — একটা artifact লাগে এমন job এটা missing হলে fail করে, একটা cache miss-এর মতো না।
- Cache 7 দিন ব্যবহার না হলে auto-evict হয় (প্রতি repo ~10GB limit); artifact default-এ 90 দিন রাখা হয়।

## ৫. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. `actions/cache` একটা matching key খুঁজে না পেলে (একটা "cache miss") একটা workflow run-এর কী হয়?</strong></summary>

কিছু ভাঙে না — এর পরের step (যেমন `composer install`) শুধু একটা normal, full installation চালায়, যেন caching configure-ই করা হয়নি। একটা cache miss শুধু সময় খরচ করে, কখনো failure cause করে না।
</details>

<details>
<summary><strong>প্রশ্ন ২. একটা cache key typically কেন `my-cache`-এর মতো fixed কিছুর বদলে `hashFiles('composer.lock')` ব্যবহার করে?</strong></summary>

Lock file hash করলে key automatically শুধু তখনই পরিবর্তন হয় যখন তোমার actual dependency পরিবর্তন হয়। একটা fixed key either কখনো update হবে না (forever একটা stale cache serve করবে) বা manually bump করা লাগবে — lock file hash করা manual effort ছাড়াই cache সঠিক রাখে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. `build` job একটা `dist/` folder বানায়। `deploy` job কেন artifact ব্যবহার না করে সরাসরি সেই folder পড়তে পারে না?</strong></summary>

প্রতিটা job নিজের আলাদা, disposable runner-এ চলে যাদের মাঝে automatically কিছু shared না। `build` job-এর `dist/` folder শুধু `build`-এর runner-এ exist করে আর সেই job শেষ হলে চলে যায় — `upload-artifact` আর `download-artifact` explicitly folder-এর content একটা job-এর runner থেকে অন্যটায় বহন করে।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একটা job যদি এমন একটা artifact download করার আশা করে যা কখনো upload-ই হয়নি (যেমন upload step নিরবে fail করেছে বা remove করা হয়েছে), cache miss-এর সাথে compare করলে কী হয়?</strong></summary>

Download step পুরোপুরি fail করে, আর typically পুরো job-ও এর সাথে fail করে — একটা cache miss-এর থেকে আলাদা, যা শুধু একটা slower normal run-এ degrade হয়, একটা missing artifact একটা real missing dependency যা ছাড়া job এগিয়ে যেতে পারে না।
</details>

## ৬. ফ্রি রিসোর্স (English)

- Official guide: [Caching dependencies to speed up workflows](https://docs.github.com/en/actions/using-workflows/caching-dependencies-to-speed-up-workflows)
- Official guide: [Storing and sharing data with workflow artifacts](https://docs.github.com/en/actions/using-workflows/storing-workflow-data-as-artifacts)

---

**পরের চ্যাপ্টার:** Chapter 6 — Automated testing in CI *(শীঘ্রই আসছে)*
