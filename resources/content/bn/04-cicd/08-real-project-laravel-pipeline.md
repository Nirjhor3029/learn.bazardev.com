# Chapter 8 — Real Project: একটা Dockerized Laravel App-এর জন্য পুরো Pipeline

> **Course:** CI/CD (GitHub Actions) · **Chapter:** 8 of 8 (শেষ চ্যাপ্টার)
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cicd/08-real-project-laravel-pipeline)

এই শেষ chapter নতুন কিছু শেখায় না — এটা Chapter 1 থেকে 7-এর প্রতিটা অংশ একটা real, complete workflow file-এ assemble করে [Docker Chapter 8](/docker/08-real-project-laravel-dockerize) যে exact app dockerize করেছিল তার জন্য। তুমি যদি দুটো course-ই order-এ follow করে থাকো, নিচের প্রতিটা লাইন recognizable হওয়া উচিত।

![Full pipeline overview diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/04-cicd/images/full-pipeline-overview.svg)

## ১. পুরো workflow file

```yaml
name: CI/CD

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
      - uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}
          restore-keys: composer-
      - run: composer install
      - run: cp .env.ci .env
      - run: php artisan migrate
      - run: php artisan test --coverage-clover=coverage.xml
      - uses: actions/upload-artifact@v4
        if: always()
        with:
          name: coverage-report
          path: coverage.xml

  build-and-push:
    needs: test
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: docker/login-action@v3
        with:
          username: ${{ secrets.DOCKERHUB_USERNAME }}
          password: ${{ secrets.DOCKERHUB_TOKEN }}
      - uses: docker/build-push-action@v5
        with:
          push: true
          tags: |
            myapp:${{ github.sha }}
            myapp:latest

  deploy:
    needs: build-and-push
    runs-on: ubuntu-latest
    environment: production
    steps:
      - uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SERVER_SSH_KEY }}
          script: |
            docker pull myapp:${{ github.sha }}
            docker stop myapp || true
            docker run -d --rm --name myapp \
              --env-file /srv/myapp/.env \
              myapp:${{ github.sha }}
```

## ২. এটা chapter by chapter পড়া

- **`on:`** — [Chapter 3](/cicd/03-triggers-and-events): `main`-কে target করা push আর PR-এ চলে (docs-only পরিবর্তন skip করে), সাথে `workflow_dispatch` দিয়ে একটা manual button।
- **`services: mysql`** — [Chapter 6](/cicd/06-automated-testing-in-ci): test job-এর পাশাপাশি একটা real database, একটা health check সহ তাই test কখনো এখনো ready না হওয়া একটা database-এর against race করে না।
- **`actions/cache`** — [Chapter 5](/cicd/05-caching-and-artifacts): `composer.lock` unchanged থাকলে `vendor/` restore করে, তাই বেশিরভাগ run একটা full dependency download skip করে।
- **`if: always()` সহ `upload-artifact`** — [Chapter 5](/cicd/05-caching-and-artifacts) আর [Chapter 6](/cicd/06-automated-testing-in-ci): coverage report test fail হলেও save হয়, কারণ exactly সেই সময়েই তোমার এটা সবচেয়ে বেশি লাগে।
- **`needs: test` / `needs: build-and-push`** — [Chapter 2](/cicd/02-github-actions-basics): প্রতিটা job শুধু তখনই চলে যদি এর আগেরটা succeed করে, তাই কোথাও একটা failure হলে pipeline ঠিক সেখানেই থামে।
- **`if: github.ref == 'refs/heads/main'`** — নিশ্চিত করে image শুধু `main` থেকেই build আর push হয়, প্রতিটা PR branch থেকে না, কারণ PR-এর শুধু test pass করা লাগবে, কিছু ship করা লাগবে না।
- **`${{ github.sha }}` আর `latest` দিয়ে tag করা `docker/build-push-action`** — [Chapter 7](/cicd/07-continuous-deployment) আর [Docker Chapter 4](/docker/04-dockerfile): প্রতিটা build registry-তে individually addressable থাকে, কখনো overwrite হয় না।
- **`environment: production`** — [Chapter 7](/cicd/07-continuous-deployment): একমাত্র জায়গা যেখানে একজন human approve click করতে require করা যায়, team যদি পুরো Deployment-এর বদলে Continuous Delivery চায়।
- **`appleboy/ssh-action`** — [Chapter 7](/cicd/07-continuous-deployment): real server-এ connect করে exactly সেই command গুলো চালায় যা তুমি হাতে type করতে, নতুন image pull করে আর container restart করে — এটাই সেই একই deploy step যা এই course host করা VPS-এর মতো একটায় চলতো।
- **Secret (`DOCKERHUB_TOKEN`, `SERVER_SSH_KEY`, ইত্যাদি)** — [Chapter 4](/cicd/04-env-vars-secrets-matrix): এগুলো কখনো workflow file-এ নিজে দেখা যায় না, শুধু `${{ secrets.NAME }}` reference হিসেবে, প্রতিটা log-এ automatically masked।

## ৩. এই pipeline end to end কী guarantee করে

একজন developer একটা PR open করে: `test` job automatically চলে, reviewer diff পড়া শুরু করার আগেই তাকে একটা status check দিয়ে ([Git & GitHub Chapter 6](/git-github/06-pull-requests-and-code-review)-এর সাথে সরাসরি connect করে)। PR team-এর preferred merge method দিয়ে `main`-এ merge হয়। সেই merge পুরো pipeline আবার trigger করে — test, তারপর build-and-push, তারপর deploy — আর production এখন exactly সেই code চালাচ্ছে যা review আর merge হয়েছে, মাঝে যা কিছু হয়েছে তার Actions tab-এ একটা full, visible record সহ।

এই chain-এ কিছু ভাঙলে — একটা fail হওয়া test, একটা fail হওয়া image build, একটা rejected deploy approval — production শুধু আগে যা চালাচ্ছিল তাই চালাতে থাকে। কিছু partial, কিছু ভাঙা, কখনো এটায় পৌঁছায় না।

## ৪. মূল কথাগুলো (Key Takeaways)

- একটা real pipeline হলো এই course-এর প্রতিটা আগের chapter-এর অংশ, `needs:` দিয়ে chained তাই কোথাও একটা failure হলে downstream সবকিছু থামে।
- Trigger (Ch 3), caching (Ch 5), একটা real database service (Ch 6), আর masked secret (Ch 4) test stage-কে fast আর trustworthy দুটোই করে তোলে।
- Commit SHA দিয়ে image tag করা (Ch 7) মানে registry প্রতিটা build-এর একটা complete, individually deployable history রাখে, শুধু সবচেয়ে recent-টা না।
- একটা GitHub Environment (Ch 7) হলো সেই জায়গা যেখানে একটা team তার একটা deliberate human checkpoint insert করে, চাইলে, বাকি সব জায়গায় automation না হারিয়ে।
- আগের সাতটা chapter-এর পুরো point একটা property-তে converge করে: কোনো stage-এ একটা failure এর পরের প্রতিটা stage-কে protect করে, production পর্যন্ত।

## ৫. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. উপরের পুরো workflow-এ, একটা fail হওয়া test কখনো একটা নতুন Docker image push না হওয়া কী guarantee করে?</strong></summary>

`build-and-push` job `needs: test` declare করে, তাই এটা শুধু তখনই শুরু হয় যদি `test` job successfully complete হয়। একটা fail হওয়া test সেখানে pipeline থামিয়ে দেয় — `build-and-push` (আর এর পরের সবকিছু) শুধু কখনো চলে না।
</details>

<details>
<summary><strong>প্রশ্ন ২. `test`-এ একটা equivalent restriction না থাকলেও `build-and-push` কেন `if: github.ref == 'refs/heads/main'` include করে?</strong></summary>

Test প্রতিটা PR-এ চলা উচিত reviewer-দের একটা status check দিতে, এটা কোন branch থেকে তা নির্বিশেষে। কিন্তু একটা deployable image build আর push করা শুধু আসলে `main`-এ merge হওয়া code-এর জন্য meaningful — প্রতিটা experimental PR branch-এর জন্য একটা image build করা এমন কিছুর উপর resource waste করবে যা কখনো deploy হবে না।
</details>

<details>
<summary><strong>প্রশ্ন ৩. গত run-এর পর থেকে `composer.lock` পরিবর্তন না হলে, এই pipeline-এর কোন অংশ speed up হয়, আর কোনটা হয় না?</strong></summary>

`actions/cache` step cached `vendor/` folder restore করে, `composer install`-কে almost instant করে দেয় — এটা শুধু একটা speed optimization। এর test execution নিজে, Docker build, বা deploy step-এর উপর কোনো effect নেই, কারণ caching শুধু যে dependency-install step-এর সাথে এটা attached তাতেই apply হয়।
</details>

<details>
<summary><strong>প্রশ্ন ৪. এই পুরো course-এ, trigger, `needs:`, service container, environment — এই প্রতিটা mechanism ultimately কোন একটা property serve করে?</strong></summary>

যে কোনো একটা single stage-এ একটা failure সেখানেই pipeline থামায়, কোনো ভাঙা, untested, বা unapproved কিছু পরের stage-এ, production-এ তো দূরেই থাক, পৌঁছানো আটকায়। Cover করা প্রতিটা feature — branch filter থেকে required reviewer পর্যন্ত — either সমস্যা early ধরতে বা একটা failure আসলেই progress block করে তা enforce করতে exist করে।
</details>

## ৬. ফ্রি রিসোর্স (English)

- Official guide: [Building and testing with GitHub Actions](https://docs.github.com/en/actions/automating-builds-and-tests)
- Official guide: [Deploying with GitHub Actions](https://docs.github.com/en/actions/deployment/about-deployments/deploying-with-github-actions)

---

## কোর্স সম্পন্ন

এটাই **CI/CD (GitHub Actions)**-এর সব ৮টা chapter — CI/CD আসলে কী মানে তা থেকে শুরু করে, workflow, trigger, secret আর matrix build, caching আর artifact, একটা real database দিয়ে automated testing, continuous deployment, আর শেষে Docker আর Git & GitHub course-এর সাথে প্রতিটা অংশ বেঁধে একটা complete pipeline পর্যন্ত।

Roadmap-এর আরেকটা course continue করতে [course হোমপেজ](https://learn.bazardev.com)-এ ফিরে যাও।
