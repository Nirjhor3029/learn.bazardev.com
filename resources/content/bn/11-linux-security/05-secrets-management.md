# Chapter 5 — Secrets Management: কখনো Git-এ না, সবসময় Rotatable

> **Course:** Linux Server Security & Secrets · **Chapter:** 5 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-security/05-secrets-management)

> **Honesty note:** Section 1-এর git-history example-টা আসলেই চালানো হয়েছে, একটা disposable throwaway repository-তে যেটা শুধু এটা দেখানোর জন্যই বানানো হয়েছিল (একটা fake, স্পষ্টভাবে fake password দিয়ে, কখনো একটা real credential না) — এই project-এর নিজের কোনো repository-র বিরুদ্ধে কখনো না। `gitleaks` নিজেই writing server-এ install করা নেই, তাই নিচের এর command গুলো এর নিজের official repository থেকে documented, চালানো হয়নি। Section 6–7-এর Laravel-specific pitfall গুলো সরাসরি Laravel-এর নিজের official documentation থেকে নেওয়া, যেখানে এগুলো explicitly বলা আছে।

একটা "secret" হলো এমন কিছু, যা অন্য কারো কাছে চলে গেলে, তাদের এমন কিছু করার সুযোগ দেয় যা তুমি authorize করোনি: একটা database password, একটা API key, একটা SSH private key, একটা `.env` file-এর content, একটা webhook signing secret, একটা SSL certificate-এর private key। এই chapter কোনো একটা tool নিয়ে না — এটা সেই কয়েকটা habit নিয়ে যা secret leak হওয়া থেকে বাঁচায়, আর একটা leak হয়ে গেলে আসলে কী করতে হবে তা নিয়ে।

## ১. Secret-এর জন্য Git কেন বিশেষভাবে বিপজ্জনক

একটা file থেকে একটা secret delete করে সেই change commit করা repository থেকে secret-টা remove করে **না**। Git এটা যা ever track করেছে এমন প্রতিটা file-এর প্রতিটা version রাখে; যে commit secret-টা *যোগ করেছিল* সেটা এখনও আছে, আর repository-র history access আছে এমন কেউ সেটা retrieve করতে পারে:

```bash
git log --oneline -- config/database.php
```

```
0de6e5f Move password to env var
d0e84ae Add database config
```

```bash
git show d0e84ae:config/database.php
```

```
<?php
return ['password' => 'S3cr3t-Fake-Demo-Value-Not-Real'];
```

এটা একটা disposable test repository-র বিরুদ্ধে একটা real run: current file-এ শুধু `env('DB_PASSWORD')` আছে — পরিষ্কার — কিন্তু পুরনো commit তখনও original fake secret-টা ফিরিয়ে দেয়, byte for byte, একদম যেন আজও সেখানে আছে। এটা years later-ও সত্য, এমনকি current file পুরোপুরি clean দেখালেও। একবার commit হয়ে যাওয়া একটা secret-কে ধরে নিতে হবে এটা **সেই commit-এর মুহূর্ত থেকে permanently exposed**, পরের commit-গুলো file-এর সাথে যা-ই করুক।

## ২. Prevention: file-টা প্রথমেই stage হতেই পারা উচিত না

প্রথম, সবচেয়ে cheap defense হলো real secret রাখা যেকোনো কিছুর জন্য একটা `.gitignore` entry — `.env`, `*.pem`, `*.key` — যাতে file-টা `git add`-এর candidate-ই কখনো না হয়। এই project-এর নিজের repository-গুলো ঠিক এই rule-ই মানে (তুমি এখন পর্যন্ত দেখা প্রতিটা course project-এ `.env` gitignored)।

`.gitignore` কিন্তু শুধু একটা already-ignored file নিয়ে একটা *accident* থামায় — কেউ সরাসরি একটা line of application code-এ একটা live API key paste করে দিলে এটা কিছুই করে না। একটা **pre-commit secret scanner** সেই case-টাও ধরে, commit হতে যাওয়া diff-টা scan করে এমন pattern খুঁজে যা credential-এর মতো দেখায় (দীর্ঘ hex/base64 string `key`, `secret`, `password`-এর মতো শব্দের পাশে, আর known provider token format)। `gitleaks` একটা well-documented, widely used open-source example:

```bash
gitleaks detect --source . --no-git  # working tree একবার scan করো
gitleaks protect --staged            # pre-commit hook হিসেবে: যা commit হতে যাচ্ছে শুধু তা scan করো
```

দ্বিতীয় form-টা একটা pre-commit hook-এ wire করা মানে একটা secret একটা commit হয়ে যাওয়ার **আগে** flag হয় — এটা ধরার সবচেয়ে cheap possible point, কারণ তখন কিছুই undo করার দরকার নেই।

## ৩. তারপরও একটা secret leak হলে: আগে rotate করো, পরে clean up করো

Prevention থাকার পরও, কখনো কখনো একটা secret একটা pushed commit-এ চলে যায়। Operation-গুলোর honest, সঠিক order এটা আগে handle না করা মানুষকে surprise করে:

1. **সাথে সাথে credential rotate করো** — একটা নতুন database password generate করো, API key revoke আর reissue করো, একটা নতুন SSH key pair generate করো। এটাই একমাত্র step যা আসলে exposure-কে neutralize করে, আর এটা discovery-র কয়েক মিনিটের মধ্যে হওয়া উচিত, একটা cleanup শেষ হওয়ার পর না।
2. **তারপরই, repository history clean up করো** (`git filter-repo` বা BFG Repo-Cleaner standard tool, secret touch করা প্রতিটা commit rewrite করে) — আর এটাও একটা courtesy, Section 4-এর কারণে একটা fix না।

আগে rotate, পরে cleanup — কখনো উল্টোটা না — এই chapter-এর সেই একটা rule যা বাকি সব ভুলে গেলেও মনে রাখার মতো।

## ৪. কেন শুধু history cleanup কখনো যথেষ্ট না

![কেন একটা leak হওয়া secret rotate করা git history scrub করার চেয়েও গুরুত্বপূর্ণ](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/11-linux-security/images/secrets-git-history.svg)

একটা secret থাকা commit যে মুহূর্তে একটা shared remote-এ **push** হয় (GitHub, GitLab, একজন teammate-এর clone), এটা এমন জায়গায় আগে থেকেই থাকতে পারে যেখানে main repository-র history-rewriting পৌঁছাতে পারে না: cleanup-এর আগে বানানো একজন developer-এর নিজের local clone, একটা CI runner-এর checkout আর তার log, একটা cached fork, একটা public repository-র search-engine বা security-scanner crawl। Canonical repo-তে history rewrite করা cleanup হিসেবে তখনও worth — কিন্তু এটা guarantee করতে পারে না secret-টা এটা আগে যেখানে যেখানে ভ্রমণ করেছে সব জায়গা থেকে চলে গেছে। এই কারণেই Section 3 rotation-কে প্রথমে রাখে: rotation *পুরনো* secret-কে worthless করে দেয়, এটা যেখানেই যত copy থাকুক তা না দেখেই।

## ৫. Secret আসলে কোথায় থাকা উচিত

- **Environment variable**, যা application runtime-এ পড়ে, codebase-এর অংশ এমন কোনো file-এ লেখা না। Laravel-এর `.env`, `vlucas/phpdotenv` দিয়ে পড়া, এই pattern-ই; একটা systemd service repository-র সম্পূর্ণ বাইরে থাকা একটা file-এর দিকে point করা একটা `EnvironmentFile=` directive দিয়ে একই effect পেতে পারে।
- **Chapter 1-এর `authorized_keys` pattern-এর মতো file permission**: একটা secrets file application-এর নিজের user-এর মালিকানায়, mode `600`, machine-এর অন্য কেউ পড়তে না পারার মতো হওয়া উচিত:
  ```bash
  chmod 600 .env
  ```
- **একটা dedicated secrets manager**, একটা সার্ভারের বেশি কিছুর জন্য: এই course-এর নিজের IaC course (Chapter 4, "Ansible roles, templates, and Vault for secrets") ঠিক এটাই cover করে **Ansible Vault** দিয়ে — secret value encrypt করা যাতে সেগুলো একটা provisioning repository-তে কখনো git-এ plaintext না হয়ে নিরাপদে থাকতে পারে। Cloud-hosted equivalent (একটা cloud provider-এর নিজের secrets manager, বা self-hosted HashiCorp Vault) বড় scale-এ একই সমস্যা সমাধান করে: secret deploy বা boot time-এ fetch হয়, কোনো repository-তে কখনো থাকে না, plaintext হোক বা না হোক।

## ৬. একটা Laravel-specific pitfall যা এই course-কে সরাসরি বলতে হবে

Laravel-এর নিজের official documentation এটা explicitly বলে, কারণ এটা একটা genuinely common real-world incident, কল্পনা না: **production-এ `APP_DEBUG=true` নিজেই একটা secrets leak**, git-এ কিছু থাকুক বা না থাকুক তার থেকে independent। Debug mode on থাকলে, একটা unhandled exception একটা detailed error page render করে যা দেখায় পুরো stack trace, request-এর data, আর — critically — request-এর কাছে available **environment variable-এর resolved value**, database credential আর API key সহ, যে কেউ error-টা trigger করেছে তাকে। একটা attacker-কে তোমার code-এ কোনো secret খুঁজতেই হবে না, যদি সে শুধু একটা debug-mode production site-এ কোনো unhandled error trigger করতে পারে। Laravel-এর নিজের setup ঠিক এই কারণে production-এর জন্য `APP_DEBUG=false` explicitly required বলে।

## ৭. একটা quieter Laravel gotcha: cached config একটা `.env` change লুকিয়ে রাখে

```bash
php artisan config:cache
```

এই command (একটা production deploy-এর অংশ হিসেবে commonly চালানো, একটা real performance gain-এর জন্য) প্রতিটা config file **আর current `.env` value** পড়ে, আর result-টা একটা single cached PHP file-এ bake করে। এর পর, **Laravel আর `.env` একদমই পড়ে না** — এটা শুধু cache পড়ে। তুমি তারপর সরাসরি production server-এ `.env` edit করে (Section 3-এর urgent প্রথম step) একটা secret rotate করলে, application tখনও *পুরনো*, cached value ব্যবহার করতে থাকে যতক্ষণ না তুমি explicitly cache clear আর rebuild করো:

```bash
php artisan config:clear
php artisan config:cache
```

এটা skip করা মানে তুমি মিনিট আগে rotate হয়েছে ভাবা একটা secret, practice-এ, তখনও সেটাই যা running application ব্যবহার করছে — একটা real incident-এর সময় discover করার মতো একটা বিপজ্জনক gap, তার আগে না।

## ৮. CI/CD-তে secret

CI/CD course (Chapter 4, "environment variables, secrets, and matrix builds") automation side থেকে এটা আগে থেকেই cover করে; secret-specific point এখানে আবার বলা worth: GitHub Actions-এর নিজের **encrypted secret** (`Settings → Secrets and variables → Actions`) encrypted at rest store হয় আর workflow log-এ automatically **mask** হয় (কোনো step echo করলে `***` হিসেবে print হয়)। একটা workflow YAML file-এ সরাসরি plaintext লেখা একটা secret এর কিছুই পায় না — এটা Section 1-এর মতোই permanence নিয়ে git-এ commit হয়, repository পড়তে পারা যে কারো কাছে visible, আর log-এ কখনো mask হয় না কারণ GitHub প্রথমেই জানতো না এটা একটা secret ছিল।

## ৯. মূল কথা

- একটা git-এ commit হওয়া secret **সেই commit থেকেই permanently exposed**, পরের commit-গুলো file-এর সাথে যা-ই করুক — পরে delete করা এটা history থেকে remove করে না।
- আগে prevent করো: known secret file-এর জন্য `.gitignore`, সাথে code-এ সরাসরি paste হওয়া একটা secret ধরার জন্য একটা pre-commit scanner (`gitleaks` বা similar)।
- তারপরও একটা leak হলে: **সাথে সাথে credential rotate করো**, তারপর git history cleanup করো একটা courtesy হিসেবে — rotation-ই আসলে exposure neutralize করে।
- History cleanup guarantee করতে পারে না যে প্রতিটা clone, fork, CI log, বা crawl যা আগে থেকে secret copy করেছে তা থেকে remove হয়েছে — rotation আগে আসার আরেকটা কারণ।
- Secret environment variable-এ থাকা উচিত restrictive file permission দিয়ে (`chmod 600`), বা একটা সার্ভারের বেশি কিছুর জন্য একটা dedicated secrets manager (Ansible Vault, একটা cloud secrets manager)।
- **Production-এ `APP_DEBUG=true` নিজেই একটা secrets leak** — Laravel-এর debug error page environment variable value expose করতে পারে যে কাউকে একটা unhandled exception trigger করে।
- `php artisan config:cache` `.env` value একটা cache file-এ freeze করে; শুধু `.env` edit করে একটা secret rotate করা একটা stale config cache থাকা server-এ এটা clear আর rebuild না হওয়া পর্যন্ত কিছুই করে না।
- GitHub Actions-এর encrypted, auto-masked secret ঠিক এই জন্য আছে যাতে CI secret কখনো একটা workflow file-এ plaintext হতে না হয়।

## ১০. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. একটা file থেকে একটা secret delete করে সেই change commit করা repository থেকে এটা কেন remove করে না?</strong></summary>

Git যা ever track করেছে এমন প্রতিটা file-এর প্রতিটা historical version রাখে। যে commit originally secret-টা যোগ করেছিল সেটা এখনও আছে আর এখনও retrievable (যেমন git show দিয়ে), file-এর current version-এ পরের commit যা-ই করুক।
</details>

<details>
<summary><strong>Q2. একটা shared repository-তে commit আর push হওয়া একটা secret পাওয়া গেলে operation-গুলোর সঠিক order কী?</strong></summary>

প্রথমে সাথে সাথে credential rotate করো - এটাই আসলে exposure neutralize করে। Git history cleanup আসে দ্বিতীয়, একটা courtesy হিসেবে, fix হিসেবে না।
</details>

<details>
<summary><strong>Q3. Git history rewrite করা কেন একটা leak হওয়া secret পুরোপুরি removed হওয়া guarantee করতে পারে না?</strong></summary>

একটা commit push হয়ে গেলে, এটা এমন জায়গায় আগে থেকেই থাকতে পারে যেখানে main repository-র rewrite পৌঁছাতে পারে না - অন্য একজন developer-এর local clone, একটা CI runner-এর checkout আর log, একটা cached fork, বা একটা public repository-র crawl।
</details>

<details>
<summary><strong>Q4. gitleaks-এর মতো একটা pre-commit secret scanner একা .gitignore যা ধরে না তার কী ধরে?</strong></summary>

.gitignore শুধু একটা already-ignored file stage হওয়া থামায়। একটা pre-commit scanner কোনো ignored file-এ না থাকা একটা live credential সরাসরি application code-এ paste হলেও ধরে, commit হওয়ার আগে diff-টা pattern-match করে।
</details>

<details>
<summary><strong>Q5. Production-এ APP_DEBUG=true কেন একা নিজেই একটা secrets leak হিসেবে ধরা হয়, git-এর কোনো সম্পর্ক ছাড়াই?</strong></summary>

Debug mode on থাকলে, একটা unhandled exception একটা detailed error page render করে যা environment variable-এর resolved value - database credential আর API key সহ - expose করতে পারে, যে কেউ error-টা trigger করে তার কাছে, codebase-এ কিছু খুঁজার দরকার ছাড়াই।
</details>

<details>
<summary><strong>Q6. php artisan config:cache চালানোর পর, শুধু .env edit করে একটা secret rotate করা কেন আসলে effect না-ও করতে পারে?</strong></summary>

config:cache current .env value একটা single cached file-এ bake করে দেয়, আর Laravel তখন শুধু সেই cache পড়ে, .env না, যতক্ষণ না config:clear আর config:cache আবার চালানো হয় - তাই application পুরনো cached value ব্যবহার করতে থাকে।
</details>

<details>
<summary><strong>Q7. GitHub Actions-এর encrypted Secrets setting-এ রাখা একটা secret, আর সরাসরি workflow YAML-এ লেখা একটার জন্য GitHub কী আলাদা করে?</strong></summary>

একটা encrypted secret encrypted at rest store হয় আর একটা workflow step এটা log করলে automatically mask হয় (*** হিসেবে print হয়)। YAML file-এ একটা plaintext value permanently git-এ commit হয় আর কখনো mask হয় না, কারণ GitHub-এর জানার কোনো উপায় নেই এটা secret হওয়ার কথা।
</details>

<details>
<summary><strong>Q8. এই course-এ (একটা আলাদা chapter থেকে) একটা tool-এর নাম বলো যা specifically একটা provisioning/IaC workflow-এর জন্য secrets management সমাধান করে।</strong></summary>

Ansible Vault (IaC course, Chapter 4-এ cover করা) - এটা secret value encrypt করে যাতে সেগুলো একটা provisioning repository-তে কখনো plaintext না হয়ে থাকতে পারে।
</details>

## ১১. Free resources

- Git Documentation: [git-filter-repo](https://github.com/newren/git-filter-repo) আর [BFG Repo-Cleaner](https://rtyley.github.io/bfg-repo-cleaner/)
- `gitleaks`: [official GitHub repository](https://github.com/gitleaks/gitleaks)
- Laravel Documentation: [Configuration — Debug Mode](https://laravel.com/docs/configuration#debug-mode) আর [Configuration Caching](https://laravel.com/docs/configuration#configuration-caching)
- GitHub Documentation: [Using secrets in GitHub Actions](https://docs.github.com/en/actions/security-guides/using-secrets-in-github-actions)

---

**Next:** [Chapter 6 — Least privilege: sudo policy, service account, আর file permission](06-least-privilege-and-sudo.md)
