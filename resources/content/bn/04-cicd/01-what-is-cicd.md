# Chapter 1 — CI/CD কী, আর কেন এটা গুরুত্বপূর্ণ?

> **Course:** CI/CD (GitHub Actions) · **Chapter:** 1 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cicd/01-what-is-cicd)

তুমি যদি [Git & GitHub course](/git-github) শেষ করে থাকো, তাহলে already জানো কীভাবে একটা pull request দিয়ে code review আর merge করা হয়। CI/CD হলো সেই layer যা এর উপরে বসে: একজন human manually প্রতিবার test চালানো, app build করা, আর deploy করার বদলে, একটা machine তিনটাই করে — automatically, consistently, যখনই কেউ code push করে।

![CI/CD pipeline overview diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/04-cicd/images/cicd-pipeline-overview.svg)

## ১. এটা কোন problem solve করে

CI/CD-এর আগে, একটা typical workflow এমন দেখাতো: একজন developer একটা feature শেষ করে, নিজের machine-এ manually test suite চালায় (মনে থাকলে), manually server-এ SSH করে, latest code pull করে, আর app restart করে। এই manual step-গুলোর প্রতিটা এমন জায়গা যেখানে কিছু ভুল হতে পারে — একটা ভুলে যাওয়া test run, একটা manual deploy-র সময় একটা typo, একটা "আমার machine-এ কাজ করে" bug যা কেউ ধরতে পারে না কারণ reviewer আসলে কখনো code run-ই করেনি।

একটা **pipeline** এই সবকিছু replace করে একটা fixed, automated sequence of step দিয়ে যা প্রতিবার exactly একইভাবে চলে, তোমার repository-তে কিছু ঘটলে (একটা push, একটা PR, একটা merge) trigger হয়ে।

## ২. CI — Continuous Integration

**Continuous Integration** মানে প্রতিটা পরিবর্তন automatically build আর test হয় push হওয়ার সাথে সাথেই — সপ্তাহে একবার না, "কেউ মনে রাখলে" না, বরং প্রতিটা একক push বা pull request-এ। এটা সরাসরি [Git & GitHub Chapter 6](/git-github/06-pull-requests-and-code-review)-এর সাথে connect করে: একটা PR-এ "status check" যা branch protection require করতে পারে সেটাই exactly automatically চলা CI।

Core idea হলো সমস্যা **যত early আর যত cheap possible ধরা**। Push হওয়ার সাথে সাথে ধরা পড়া একটা bug fix করতে কয়েক মিনিট লাগে। সেই একই bug `main`-এ merge হওয়ার পরে, বা তার চেয়েও খারাপ, production-এ পৌঁছার পরে ধরা পড়লে, খরচ অনেক বেশি — এখন কাউকে বের করতে হবে কী ভেঙেছে, কখন, আর কেন, often pressure-এর মধ্যে।

## ৩. CD — Continuous Delivery বনাম Continuous Deployment

CD প্রায়ই একটা জিনিস হিসেবে বলা হয়, কিন্তু এটা আসলে দুটো related কিন্তু distinct practice refer করে:

| | Continuous **Delivery** | Continuous **Deployment** |
|---|---|---|
| Automatically কী হয় | CI pass করা প্রতিটা পরিবর্তন automatically package হয় আর release-এর জন্য ready করা হয় | CI pass করা প্রতিটা পরিবর্তন automatically production-এ release হয় |
| Human involvement | কেউ এখনো একটা button click করে আসলে deploy করতে | কেউ কিছু click করে না — এটা নিজেই ship হয়ে যায় |
| একটা team কেন এটা বেছে নিতে পারে | Real user-এর কাছে কিছু পৌঁছার আগে একটা manual gate চায় (যেমন একটা scheduled release window) | Test suite আর pipeline-এর উপর যথেষ্ট confident যে তারা pass হওয়া প্রতিটা পরিবর্তন সাথে সাথেই ship করে দিতে পারে |

দুটোই "CI শেষ হওয়ার জায়গা থেকে automated" — পার্থক্য purely এটাই যে একজন human final release step approve করে কিনা।

## ৪. একটা real pipeline আসলে step by step কী করে

উপরের diagram-কে shape হিসেবে ব্যবহার করে: একটা push বা PR pipeline trigger করে, যা **build** চালায় (dependency compile/install করে, code আসলে run হয় কিনা নিশ্চিত করে), তারপর **test** (automated test suite), তারপর — দুটোই pass করলে — CD side-এ move করে: **package** (যেমন, একটা Docker image build করা, exactly [Docker Chapter 4](/docker/04-dockerfile)-এ cover করা মতো) আর **deploy** (সেই package করা app কোথাও real জায়গায় run করানো)।

একটা pipeline-এর critical property হলো **কোনো step-এ failure হলে এর পরের সবকিছু থেমে যায়**। Test fail করলে, কিছুই package বা deploy হয় না — একটা ভাঙা পরিবর্তন কখনো production-এ পৌঁছায় না, কারণ pipeline নিজেই এগিয়ে যেতে refuse করে।

## ৫. এটা একটা ছোট project-এর জন্যও কেন গুরুত্বপূর্ণ

CI/CD শুধু বড় team-এর জন্য ভাবতে lure হয়। Practice-এ, এমনকি একজন solo developer-ও benefit পায়: এটা প্রতিবার deploy করার আগে test চালাতে মনে রাখার mental overhead সরিয়ে দেয়, এটা deploy process প্রতিবার identical করে (কোন command ছিল আবার?-এর কোনো প্রশ্ন নেই), আর এটা ঠিক কী test হয়েছে আর কখন একটা specific পরিবর্তন live হয়েছে তার একটা honest, visible record বানায়।

## ৬. মূল কথাগুলো (Key Takeaways)

- একটা pipeline build → test → package → deploy-র sequence automate করে যা আগে manual step require করতো, প্রতিটা push-এ identically চালিয়ে।
- CI (Continuous Integration) মানে প্রতিটা push automatically build আর test হয় — এটাই Git & GitHub Chapter 6-এর status check-কে power দেয়।
- Continuous Delivery একজন human "deploy" click করা পর্যন্ত সবকিছু automate করে; Continuous Deployment নিজের deploy-টাও automate করে, কোনো human click ছাড়াই।
- একটা fail হওয়া step pipeline থামিয়ে দেয় — একটা ভাঙা পরিবর্তন পরের step-এ, production-এ তো দূরেই থাক, কখনো পৌঁছায় না।
- CI/CD যেকোনো size-এর project-এর জন্য benefit দেয়, শুধু বড় team না — consistency আর early bug detection solo-তেও গুরুত্বপূর্ণ।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. CI কীভাবে Git & GitHub course-এর "status check" concept-এর সাথে connect করে?</strong></summary>

একটা pull request-এ দেখা যাওয়া status check (আর branch protection merge-এর আগে যা require করতে পারে) হলো সেই PR-এ automatically চলা CI-র direct result — push দিয়ে trigger হওয়া build আর test step, GitHub-এ pass/fail report করে।
</details>

<details>
<summary><strong>প্রশ্ন ২. Continuous Delivery আর Continuous Deployment-এর মূল পার্থক্য কী?</strong></summary>

দুটোই একটা পরিবর্তন release-এর জন্য ready build, test, আর package করা পর্যন্ত সবকিছু automate করে। Continuous Delivery এখানে থেমে যায় আর একজন human actual deployment manually trigger করার জন্য wait করে; Continuous Deployment আরেক step এগিয়ে automatically deploy-ও করে, কোনো human action ছাড়াই।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা pipeline-এর "test" step fail করলে, এর পরে normally আসা "package" আর "deploy" step-এর কী হয়?</strong></summary>

এগুলো কখনো চলে না। একটা pipeline প্রথম fail হওয়া step-এ থেমে যায় — test fail করা একটা পরিবর্তন কখনো package বা deploy হয় না, যা exactly সেই mechanism যা ভাঙা code-কে production-এ পৌঁছানো থেকে আটকায়।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একটা ছোট project-এ একা কাজ করা একজন solo developer-এর জন্যও CI/CD setup করা কেন worth হতে পারে?</strong></summary>

এটা প্রতিবার manually test চালানো বা exact right deploy step follow করা মনে রাখার প্রয়োজন সরিয়ে দেয়, mood বা memory নির্বিশেষে process কে identical আর repeatable করে, আর কী test হয়েছে আর কখন প্রতিটা পরিবর্তন আসলে live হয়েছে তার একটা clear record বানায়।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official guide: [GitHub Actions documentation](https://docs.github.com/en/actions)
- Overview: [What is CI/CD? (Red Hat)](https://www.redhat.com/en/topics/devops/what-is-ci-cd)

---

**পরের চ্যাপ্টার:** Chapter 2 — GitHub Actions basics: workflows, jobs, steps *(শীঘ্রই আসছে)*
