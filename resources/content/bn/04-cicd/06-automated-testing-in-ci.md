# Chapter 6 — CI-তে Automated Testing

> **Course:** CI/CD (GitHub Actions) · **Chapter:** 6 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cicd/06-automated-testing-in-ci)

এই course-এ এখন পর্যন্ত সবকিছু প্রতিটা push-এ *কিছু* চালানো নিয়ে ছিল। এই chapter specifically তোমার actual **test suite** চালানো নিয়ে — একটা CI pipeline-এর সবচেয়ে common, সবচেয়ে valuable কাজ, আর [Git & GitHub Chapter 6](/git-github/06-pull-requests-and-code-review)-এ প্রথম mention করা "status check"-এর direct mechanism।

![Testing in CI diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/04-cicd/images/testing-in-ci.svg)

## ১. এই specific job কেন অন্য কিছুর থেকে বেশি গুরুত্বপূর্ণ

[Chapter 1](/cicd/01-what-is-cicd) সমস্যা early ধরার general case বানিয়েছিল। একটা test suite হলো সেই জায়গা যেখানে সেই principle আসলে payoff দেয়: একটা PR-এ fail হওয়া test, merge-এর আগে author আর reviewer-এর কাছে visible, কয়েক মিনিট খরচ করে। সেই একই bug `main`-এ — বা production-এ — পৌঁছালে, কাউকে পরে একটা debugging session খরচ করতে হয়, often কোন পরিবর্তন এটা cause করেছে তার context ছাড়াই। প্রতিটা একক push-এ test চালানো, "আমি ভুলে গেছিলাম" বা "আমি তাড়াহুড়োয় ছিলাম"-এর কোনো সম্ভাবনা ছাড়াই, এটাই পুরো point।

## ২. একটা real example: একটা database দিয়ে একটা Laravel app test করা

বেশিরভাগ non-trivial test suite-এর একটা real database লাগে — শুধু unit test না, feature/integration test যা আসলে database layer touch করে। CI-তে একটা চালাতে একটা **service container** লাগে: একটা দ্বিতীয় container যা তোমার job-এর runner-এর পাশাপাশি শুরু হয়, network-এ reachable, শুধু সেই job-এর duration-এর জন্য।

```yaml
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_ROOT_PASSWORD: secret
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install
      - run: cp .env.ci .env
      - run: php artisan migrate
      - run: php artisan test
```

`services:` key একটা container define করে যা GitHub তোমার job-এর ঠিক পাশাপাশি শুরু করে, একই network-এ, তোমার কোনো step চলার আগেই। তোমার test step এর সাথে exactly একটা real database-এর মতো connect করে `127.0.0.1:3306`-এ — এটা একটা genuine MySQL server, কোনো mock না, যা matter করে কারণ mocked test pass করা যখন real database আলাদাভাবে behave করে সেটাই exactly সেই gap যা testing-এর purpose defeat করে। `options:` health check GitHub-কে wait করায় যতক্ষণ না MySQL আসলেই connection accept করার জন্য ready হয়, তোমার step শুরু হওয়ার আগে, একটা race condition এড়িয়ে যেখানে test এমন একটা database-এর against চলে যা এখনো start up শেষ করেনি।

একটা lighter-weight alternative-এর জন্য, অনেক project purely CI-র জন্য একটা আলাদা, faster `sqlite`-based `.env.ci` configure করে, যখন full MySQL-specific behavior আসলে test হচ্ছে না — speed আর fidelity-র মাঝে একটা reasonable tradeoff, তোমার test আসলে কী verify করতে চায় তার উপর depend করে।

## ৩. Result বের করা: report আর coverage

একটা simple pass/fail-এর বাইরে, বেশিরভাগ test runner একটা structured report বানাতে পারে — code coverage (তোমার code-এর কত percentage test আসলে exercise করেছে), বা একটা JUnit-format XML report যা কিছু tool একটা nicer summary-তে parse করতে পারে। এগুলো exactly সেই ধরনের output যা [Chapter 5](/cicd/05-caching-and-artifacts) cover করেছিল: `actions/upload-artifact` দিয়ে এগুলো upload করো তাই এগুলো run-এর summary page থেকে downloadable, শুধু raw log output-এ সংক্ষেপে exist করার বদলে।

```yaml
- run: php artisan test --coverage-clover=coverage.xml
- uses: actions/upload-artifact@v4
  if: always()
  with:
    name: coverage-report
    path: coverage.xml
```

`if: always()` এখানে জানা worth: default-এ, একটা job-এর আগের একটা step fail করলে একটা step skip হয়ে যায়। একটা report upload করার জন্য, তুমি usually চাও এটা upload হোক **test fail হলেও** — কী fail হয়েছে দেখানো report exactly তোমার সেই case-এ লাগবে।

## ৪. Flaky test নিয়ে একটা কথা

একটা **flaky test** এমন একটা যা কখনো pass করে কখনো fail করে, মাঝে কোনো code পরিবর্তন ছাড়াই — usually timing assumption, test-গুলোর মাঝে shared state, বা কোনো external আর unpredictable কিছুর উপর depend করার কারণে হয়। Lure করা fix হলো job pass না করা পর্যন্ত শুধু আবার চালানো। এটা resist করো: একটা flaky test test-এ (বা এটা যা test করছে তার code-এ) একটা real bug যা retry দিয়ে মাস্ক করলে শুধু hide হয়, আর একটা team যা "শুধু আবার চালাও" অভ্যস্ত হয়ে যায় red CI result-কে সম্পূর্ণ trust করা বন্ধ করে দেয়, যা নিরবে একটা test suite রাখার পুরো কারণ destroy করে। Actual cause খুঁজে বের করে fix করো — usually একটা timing issue বা test-isolation problem — এটা paper over করার বদলে।

## ৫. মূল কথাগুলো (Key Takeaways)

- CI-তে automated testing সরাসরি pull request status check-এর পিছনের mechanism — প্রতিটা push-এ একটা ভাঙা পরিবর্তন ধরা, এটা `main`-এ পৌঁছানোর আগেই।
- একটা `services:` container একটা real dependency (যেমন একটা database) তোমার job-এর duration-এর জন্য পাশাপাশি চালায়, network-এ reachable — একটা mock না, আসলেই real behavior-এর against test করা।
- `options:`-এ একটা health check test গুলোকে এমন একটা service container-এর against race করা থেকে আটকায় যা এখনো start up শেষ করেনি।
- Test report আর coverage output artifact-এ থাকা উচিত (Chapter 5) তাই এগুলো run-এর পরে inspect করা যায়, শুধু log-এ সংক্ষেপে visible হওয়ার বদলে।
- `if: always()` একটা step-কে (যেমন একটা report upload করা) আগের step fail হওয়ার পরেও চলতে দেয় — কিছু ভুল হলে তুমি precisely যখন report চাও তখন প্রয়োজন।
- একটা flaky test এর root cause-এ fix করা উচিত, blindly re-run করে mask করা উচিত না — পরেরটা করলে সময়ের সাথে CI result-এর উপর trust কমে যায়।

## ৬. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. একটা pull request-এ fail হওয়া একটা test কেন সেই একই bug `main` বা production-এ পৌঁছানোর চেয়ে কম খরচ করে?</strong></summary>

একটা PR-এ fail হওয়া test সাথে সাথেই ধরা পড়ে, author আর reviewer-এর কাছে visible, exactly কোন পরিবর্তন এটা cause করেছে তার full context সহ — কয়েক মিনিটে fixable। সেই একই bug `main` বা production-এ পৌঁছালে usually পরে discover হয়, এটা cause করা specific পরিবর্তন থেকে disconnected, আরও pressure-এর নিচে একটা আলাদা debugging investigation লাগে।
</details>

<details>
<summary><strong>প্রশ্ন ২. একটা `services:` container কোন problem solve করে যা একটা mocked database-এর against test চালানো করতো না?</strong></summary>

এটা test গুলোকে একটা genuinely real database-এর against চালাতে দেয় (যেমন actual MySQL), এমন behavior পার্থক্য ধরে যা একটা mock paper over করতে পারতো — "mock দিয়ে pass করে" আর "আসলে কাজ করে"-র মাঝের gap-টাই exactly সেই risk যা একটা service container এড়ায়।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা database service container শুরু করার সময় একটা health check (যেমন `--health-cmd="mysqladmin ping"`) কেন include করা হয়?</strong></summary>

একটা container শুরু হওয়া মানে এর ভেতরের database সাথে সাথেই connection accept করার জন্য ready এমন না। Health check GitHub-কে wait করায় যতক্ষণ না database আসলেই ready হিসেবে respond করে তোমার test step চালানোর আগে, একটা race condition এড়িয়ে যেখানে test শুধু খুব আগে connect করার কারণে fail করে।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একটা test coverage report artifact হিসেবে upload করা একটা step-এ তুমি কেন `if: always()` চাইতে পারো?</strong></summary>

Default-এ, job-এর আগের একটা step fail হয়ে গেলে একটা step skip হয়ে যায়। তুমি usually precisely test fail হওয়া case-এ coverage/test report inspect করতে চাও, তাই `if: always()` নিশ্চিত করে upload step তবুও চলে আর report এখনো available থাকে, আগের test step pass বা fail যাই করুক।
</details>

## ৭. ফ্রি রিসোর্স (English)

- Official guide: [Using scripts to test your code](https://docs.github.com/en/actions/automating-builds-and-tests/building-and-testing-java-with-maven) *(Actions docs-এ তোমার language-এর equivalent guide দেখো)*
- Official guide: [About service containers](https://docs.github.com/en/actions/using-containerized-services/about-service-containers)
- Official guide: [Storing and sharing data with workflow artifacts](https://docs.github.com/en/actions/using-workflows/storing-workflow-data-as-artifacts)

---

**পরের চ্যাপ্টার:** Chapter 7 — Continuous deployment: shipping automatically *(শীঘ্রই আসছে)*
