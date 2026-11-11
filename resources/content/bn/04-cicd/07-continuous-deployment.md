# Chapter 7 — Continuous Deployment: Automatically Ship করা

> **Course:** CI/CD (GitHub Actions) · **Chapter:** 7 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cicd/07-continuous-deployment)

Chapter 2 থেকে 6 CI/CD-এর CI অর্ধেক বানিয়েছে — build, test, সমস্যা early ধরা। এই chapter CD অর্ধেক: একটা পরিবর্তন ভালো প্রমাণিত হলে, এটা আসলে production-এ চালু করা, কোনো human deploy command type না করেই।

![Continuous deployment diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/04-cicd/images/continuous-deployment.svg)

## ১. একটা deploy job-এর shape

[Chapter 1](/cicd/01-what-is-cicd)-এর পার্থক্যটা মনে করো: Continuous Delivery একজন human deploy click করা পর্যন্ত সবকিছু automate করে; Continuous Deployment সেই click-টাও সরিয়ে দেয়। যেকোনোটাই, একটা containerized app-এর (exactly [Docker Chapter 8](/docker/08-real-project-laravel-dockerize) যা বানিয়েছে) একটা typical deploy job দুটো কাজ করে: একটা Docker image **build আর push**, তারপর এটা কোথাও real জায়গায় **deploy** করা।

```yaml
jobs:
  test:
    # ...Chapter 6-এর test job...

  build-and-push:
    needs: test
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
            docker run -d --rm --name myapp myapp:${{ github.sha }}
```

তিনটা job `needs:` দিয়ে chained ([Chapter 2](/cicd/02-github-actions-basics)-এ প্রথম introduce হয়েছিল): `deploy` শুধু তখনই শুরু হয় যদি `build-and-push` succeed করে, যা শুধু তখনই শুরু হয় যদি `test` succeed করে। একটা ভাঙা পরিবর্তন — এই তিনটা gate-এর যেকোনো একটায় ধরা পড়া — কখনো শেষটায় পৌঁছায় না।

## ২. Commit দিয়ে image tag করা, শুধু `latest` না

খেয়াল করো image **দুটো** tag পায়: `${{ github.sha }}` (exact commit যা এটা build করেছে) আর `latest`। এটা দেখার থেকে বেশি গুরুত্বপূর্ণ: যদি প্রতিটা build শুধু `latest`-ই push করে, তোমার rebuild না করে একটা পুরোনো, known-good version deploy করার কোনো উপায় নেই। Commit SHA দিয়ে tag করা মানে তুমি ever ship করা প্রতিটা build এখনো তোমার registry-তে বসে আছে, individually addressable — এটা [Git & GitHub Chapter 7](/git-github/07-reset-vs-revert) যে safety describe করেছিল তার deploy-level version: কিছুই ফেলে দেওয়া হয় না, তাই একটা known-good state-এ ফিরে যাওয়া সবসময় সম্ভব।

## ৩. GitHub Environment — একটা deliberate manual gate

```yaml
deploy:
  environment: production
```

একটা GitHub **Environment** (repository Settings → Environments-এর under configure করা) তোমাকে একটা specific deployment target-এ rule attach করতে দেয়: job এগিয়ে যাওয়ার আগে এক বা একাধিক reviewer-কে manually approve করতে require করা, কোন branch এতে deploy করতে পারবে restrict করা, আর environment-specific secret store করা (staging বনাম production-এর জন্য একটা আলাদা `SERVER_HOST`, উদাহরণস্বরূপ)। এটাই exactly সেই জায়গা যেখানে Continuous Delivery করা (পুরো Deployment না) একটা team তাদের একটা human checkpoint রাখে — এই point পর্যন্ত সবকিছু fully automatic, আর এটা explicit, visible click যা বলে "হ্যাঁ, ship করো।"

## ৪. একটা real server-এ connect করা

উপরের example `appleboy/ssh-action` ব্যবহার করে, একটা popular community action যা একটা server-এ SSH করে আর একটা script চালায় — একটা managed platform-এর বদলে তোমার নিজের VPS-এ deploy করার setup-এর জন্য সরাসরি applicable। `script:` block exactly তেমনই চলে যেন তুমি নিজে SSH দিয়ে সেই command গুলো type করেছো: নতুন build হওয়া image pull করো, পুরোনো running container stop করো, নতুনটা start করো। Server-এর কখনো তোমার repository বা CI secrets-এ direct access লাগে না, শুধু SSH key ছাড়া — image already আগের job-এ build আর push হয়ে গেছে।

## ৫. একটা deploy যাই হোক ভুল হলে কী হয়

Test pass হওয়া মানে একটা deploy perfect হবে তার guarantee না — environment-গুলোর মাঝে একটা config পার্থক্য, একটা external service down থাকা, যা কিছু। যেহেতু প্রতিটা image commit SHA দিয়ে tagged, rollback করা simple আর পুরো pipeline আবার চালানো লাগে না: আগের SHA-এর image সরাসরি redeploy করো।

```bash
docker pull myapp:e4f5g6h   # আগের known-good commit
docker stop myapp && docker run -d --rm --name myapp myapp:e4f5g6h
```

কিছু team এটা নিজের একটা `workflow_dispatch`-triggered workflow ([Chapter 3](/cicd/03-triggers-and-events) মনে করো) হিসেবে automate করে যা একটা SHA input হিসেবে নেয় আর শুধু deploy step চালায় — একটা manual "rollback button" যার নতুন commit বা নতুন build লাগে না।

## ৬. মূল কথাগুলো (Key Takeaways)

- একটা typical deploy job একটা Docker image build আর push করে, তারপর একটা real server-এ (often SSH দিয়ে) connect করে নতুন version চালাতে — test job-এর পরে `needs:` দিয়ে chained, তাই একটা ভাঙা পরিবর্তন কখনো এটায় পৌঁছায় না।
- শুধু `latest`-এর বদলে commit SHA দিয়ে image tag করা আগে ship করা প্রতিটা build individually addressable রাখে — Chapter 7-এর reset/revert safety net-এর deploy-level equivalent।
- একটা GitHub Environment job এগিয়ে যাওয়ার আগে manual approval require করতে পারে, কোন branch deploy করতে পারবে restrict করতে পারে, আর environment-specific secret রাখতে পারে — otherwise automatic pipeline-এ deliberate human checkpoint।
- SSH-based deploy action তোমার real server-এ exactly manually type করার মতোই একটা script চালায়, শুধু একটা SSH key লাগে, direct repository access না।
- একটা খারাপ deploy-র পর rollback করা শুধু আগের commit-এর already-built image tag redeploy করা — কোনো rebuild না, কোনো নতুন commit require না।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. `needs:` দিয়ে `test` → `build-and-push` → `deploy` chain করা কেন safety-র জন্য গুরুত্বপূর্ণ?</strong></summary>

প্রতিটা job শুধু তখনই শুরু হয় যদি এটা depend করা জিনিসটা succeed করে। একটা test failure একটা image build হওয়ার আগেই pipeline থামিয়ে দেয়, আর একটা fail হওয়া build কিছু deploy হওয়ার আগে এটা থামায় — একটা ভাঙা পরিবর্তন production-এ পৌঁছানোর বদলে সবচেয়ে early possible gate-এ block হয়ে যায়।
</details>

<details>
<summary><strong>প্রশ্ন ২. `latest`-এর পাশাপাশি একটা Docker image-কে `${{ github.sha }}` দিয়ে কেন tag করবে?</strong></summary>

শুধু `latest` tag করা মানে পুরোনো build overwrite হয়ে যায় আর একটা পুরোনো commit থেকে rebuild না করে redeploy করা যায় না। Commit SHA দিয়ে tag করা registry-তে আগে ship করা প্রতিটা version individually addressable রাখে, কোনো specific পুরোনো build সরাসরি deploy (বা তাতে roll back) করা সম্ভব করে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা deploy job-এ required reviewer সহ একটা GitHub Environment attach করা আসলে কী পরিবর্তন করে?</strong></summary>

Job চলার আগে pause করে আর designated reviewer-দের একজন GitHub-এর UI-এ manually এটা approve করার জন্য wait করে — otherwise fully automatic pipeline-কে পুরো Continuous Deployment-এর বদলে Continuous Delivery-তে বদলে, production পরিবর্তনের আগে একটা deliberate human checkpoint সহ।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একটা খারাপ deploy-র পর, rollback করতে পুরো CI/CD pipeline শুরু থেকে আবার চালানো কেন লাগে না?</strong></summary>

কারণ প্রতিটা build এর commit SHA দিয়ে tagged আর registry-তে রাখা হয়, আগের known-good image already exist করে আর rebuild করা লাগে না — rollback করা শুধু সেই existing image tag pull করা আর এটা দিয়ে container restart করা, build আর test step সম্পূর্ণ skip করে।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official guide: [Using environments for deployment](https://docs.github.com/en/actions/deployment/targeting-different-environments/using-environments-for-deployment)
- Reference: [docker/build-push-action](https://github.com/docker/build-push-action)
- Reference: [appleboy/ssh-action](https://github.com/appleboy/ssh-action)

---

**পরের চ্যাপ্টার:** Chapter 8 — Real project: a full pipeline for a Dockerized Laravel app *(শীঘ্রই আসছে)*
