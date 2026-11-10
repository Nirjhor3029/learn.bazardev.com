# Chapter 1 — Docker কী, এবং কেন গুরুত্বপূর্ণ?

> **Course:** Docker (Zero to Hero) · **Chapter:** 1 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/docker/01-what-is-docker)

এই চ্যাপ্টারে কিছু install করতে হবে না — এটা সম্পূর্ণ concept। কিন্তু এই বিষয়টা ঠিকমতো বুঝে নিতে হবে, কারণ পরের প্রতিটা চ্যাপ্টার এর উপর ভিত্তি করেই তৈরি।

## ১. Docker আসার আগের সমস্যাটা কী ছিল

প্রায় প্রতিটা developer-এর জীবনে এই ঘটনা ঘটেছে:

- তুমি নিজের ল্যাপটপে একটা অ্যাপ বানালে। সব কিছু পারফেক্টভাবে কাজ করছে।
- সেটা সার্ভারে deploy করার পর — **error**। "আমার মেশিনে তো কাজ করছিল", কিন্তু সার্ভারে করছে না।
- কারণ: তোমার ল্যাপটপে PHP 8.3, সার্ভারে PHP 8.1। একটা library তোমার লোকালে ইনস্টল করা, সার্ভারে নেই। তোমার OS Ubuntu, টিমমেটের Windows।

এটাকেই বলে **"works on my machine" সমস্যা** — software development-এ সবচেয়ে common এবং সবচেয়ে বিরক্তিকর একটা সমস্যা।

আগের সমাধানগুলো:
- **Virtual Machine (VM)** — পুরো একটা virtual কম্পিউটার বানানো, সাথে full OS। কাজ করে, কিন্তু অনেক heavy — একটা VM-এ 2-4 GB RAM লাগে, বুট হতে কয়েক মিনিট লাগে, এবং একটা সার্ভারে ৫-৬টা VM চালালেই রিসোর্স শেষ হয়ে যায়।
- **একটা বিস্তারিত setup document** — "এই এই স্টেপ ফলো করে সার্ভার সেটআপ করো" — কিন্তু manual, ভুল হওয়ার সম্ভাবনা বেশি, এবং সময়মতো আপডেট না হলে পুরোনো/ভুল হয়ে যায়।

Docker এই সমস্যাটা সম্পূর্ণ আলাদাভাবে সমাধান করে — **lightweight, fast, এবং guaranteed consistent।**

## ২. Container কী (concept)

**Container** হলো একটা অ্যাপকে "package" করে ফেলা — code, dependencies (library, runtime version), configuration — সব কিছু একটা বক্সের ভেতরে আটকে রাখা, যাতে সেই বক্স যেখানেই (ল্যাপটপ, সার্ভার, ক্লাউড) চালানো হোক, **একইরকম behavior** দেখায়।

সহজ উপমা: **shipping container**। আগে যখন জাহাজে মাল পাঠানো হতো, প্রতিটা জিনিসের shape-size আলাদা হতো, loading-unloading করতে অনেক সময় লাগত। পরে একটা standard-size **shipping container** আসলো — ভেতরে যা-ই থাকুক (কাপড়, ইলেকট্রনিক্স, খাবার), সব একই সাইজের বক্সে ভরে দেওয়া হয়। জাহাজ, ট্রাক, ট্রেন — যেকোনো vehicle একই বক্স handle করতে পারে, কারণ বক্সের shape fixed। Docker container সফটওয়্যারের জন্য ঠিক একই কাজ করে — **যা-ই হোক, একই "বক্সে" package হয়, তাই যেখানেই চালাও, একইভাবে কাজ করে।**

### VM vs Container — আসল পার্থক্য কী?

| | Virtual Machine | Container |
|---|---|---|
| Full OS লাগে? | হ্যাঁ, প্রতিটা VM-এ সম্পূর্ণ guest OS | না, host মেশিনের OS kernel শেয়ার করে |
| Boot time | কয়েক মিনিট | কয়েক সেকেন্ড (কখনো কখনো ১ সেকেন্ডের কম) |
| আকার | GB (full OS + app) | MB (শুধু app + dependencies) |
| Isolation level | Strong (hardware-level virtualization) | Process-level (lightweight, কিন্তু VM-এর তুলনায় কম isolated) |
| একটা সার্ভারে কতগুলো চালানো যায় | কম (৫-১০টা, রিসোর্স heavy) | অনেক (স্বচ্ছন্দে ১০০+ container) |

**একটা common ভুল ধারণা:** Container মানে "mini VM" না। Container আসলে host মেশিনের **একই OS kernel** ব্যবহার করে — শুধু process গুলোকে একটা isolated "বাবল"-এর মধ্যে রাখে (নিজের filesystem, network, process list দেখায়, কিন্তু kernel শেয়ার করে)। এই কারণেই container এত lightweight এবং fast।

## ৩. Docker আসলে কী?

"Container" হলো একটা **general concept** (Linux kernel-এর ফিচার — namespaces + cgroups দিয়ে বানানো) — এটা Docker আবিষ্কার করেনি। কিন্তু container-কে সহজে ব্যবহার করার জন্য যে টুল বানানো হয়েছে, সেটা Docker — এবং এতটাই ভালোভাবে করেছে যে এখন "container" বললে সবাই মনে মনে "Docker"-ই বোঝে।

Docker আসলে কয়েকটা অংশ নিয়ে গঠিত:

- **Docker Engine** — ব্যাকগ্রাউন্ডে চলা একটা service (`dockerd`), যেটা আসলে container বানায়/চালায়/বন্ধ করে।
- **Docker CLI** — টার্মিনালে `docker run` বা `docker build` লিখলে, এটা client হিসেবে Engine-কে বলে "এটা করো"।
- **Docker Image** — একটা read-only "ব্লুপ্রিন্ট" / স্ন্যাপশট — তোমার app + dependencies + প্রয়োজনীয় OS files-এর একটা প্যাকেজ। (বিস্তারিত থাকবে Chapter 3-এ।)
- **Docker Container** — একটা image-কে "run" করলে যে live, running instance পাওয়া যায়, সেটাই container। (একই image থেকে একাধিক container চালানো যায়।)
- **Docker Hub** — একটা অনলাইন registry (GitHub-এর মতো, কিন্তু code-এর বদলে images-এর জন্য) — যেখান থেকে readymade image ডাউনলোড (`pull`) করা যায়, আর নিজের বানানো image আপলোড (`push`) করা যায়।

### সবচেয়ে সহজভাবে বলতে গেলে:

> **Image = একটা recipe (instruction + ingredients)**
> **Container = সেই recipe অনুযায়ী বানানো, প্লেটে সাজানো খাবার**

একই recipe (image) দিয়ে যতবারই খাবার (container) বানাও, স্বাদ প্রতিবারই একইরকম হবে — predictable এবং consistent।

## ৪. Docker-এর architecture — কীভাবে কাজ করে

```
 ┌──────────────┐        commands       ┌───────────────────┐
 │  Docker CLI   │ ────────────────────▶ │   Docker Daemon     │
 │ (docker run..)│                       │     (dockerd)        │
 └──────────────┘                       └─────────┬───────────┘
                                                     │
                            ┌────────────────────────┼─────────────────────────┐
                            ▼                        ▼                         ▼
                     ┌─────────────┐          ┌─────────────┐           ┌─────────────┐
                     │  Image A     │          │  Container 1 │           │  Container 2 │
                     │ (blueprint)  │ ───run──▶│ (running)    │           │ (running)     │
                     └─────────────┘          └─────────────┘           └─────────────┘
                            ▲
                            │ pull / push
                     ┌─────────────┐
                     │ Docker Hub   │  (registry — images অনলাইনে জমা থাকে)
                     └─────────────┘
```

Flow টা সবসময় একইরকম:
1. CLI-তে একটা command দাও (যেমন `docker run nginx`)।
2. CLI সেটা Daemon-কে পাঠিয়ে দেয়।
3. Daemon চেক করে image-টা লোকালে আছে কিনা — না থাকলে Docker Hub থেকে **pull** করে নিয়ে আসে।
4. Image থেকে একটা **container** শুরু হয় — একটা running, isolated process।

## ৫. কেন companies/developer-রা Docker ব্যবহার করে

- **Consistency** — "works on my machine" সমস্যা শেষ। Dev, staging, production — সব জায়গায় একই container চলে, তাই behavior একইরকম।
- **Fast setup** — নতুন কেউ টিমে জয়েন করলেও, `docker compose up` দিলেই সব service (app, database, cache) একসাথে চালু হয়ে যায় — manual setup document লাগে না।
- **Microservices friendly** — একটা বড় app-কে ছোট ছোট service-এ ভাগ করে (payment service, auth service, notification service), প্রতিটা নিজের container-এ চালানো সহজ।
- **CI/CD-র জন্য perfect** — automated testing/deployment pipeline-এ, container ব্যবহার করে guarantee দেওয়া যায় যে test এবং production environment একইরকম।
- **Resource efficient** — VM-এর তুলনায় অনেক কম resource-এ বেশি service চালানো যায়, তাই hosting cost কমে।
- **Easy rollback** — নতুন version-এ সমস্যা হলে, আগের image-এর container-এ ফিরে যাওয়া অনেক সহজ।

## ৬. এই course-এ আমরা যা শিখব (roadmap preview)

| Chapter | Topic |
|---|---|
| ১ | Docker কী, এবং কেন গুরুত্বপূর্ণ ✅ (এটাই) |
| ২ | Docker install + প্রথম container চালানো |
| ৩ | Images vs Containers — hands-on commands |
| ৪ | Dockerfile — নিজের image লেখা |
| ৫ | Volumes & Networking — data persist করা, container-to-container communication |
| ৬ | Docker Compose — multi-container app (Laravel + MySQL + Redis) একসাথে চালানো |
| ৭ | Best practices + debugging |
| ৮ | Real project — নিজের Laravel app-কে Dockerize করা, শুরু থেকে শেষ |

## ৭. মূল কথাগুলো (Key Takeaways)

- Container = app + dependencies + config, একটা portable "বক্সে" package করা।
- VM পুরো একটা OS virtualize করে (heavy), container host-এর OS kernel শেয়ার করে (light, fast)।
- Docker হলো container বানানো/চালানো/manage করার জন্য সবচেয়ে জনপ্রিয় টুল (Engine + CLI + Image + Container + Hub)।
- Image = static blueprint, Container = running instance।
- মূল সুবিধা: **consistency** — যেখানেই চালাও, একইরকম behavior পাবে।

## ৮. নিজেকে যাচাই করো (Exam Prep)

নিজেকে এই প্রশ্নগুলো জিজ্ঞেস করো — উত্তর দিতে না পারলে, উপরের সেই অংশটা আবার পড়ো।

<details>
<summary><strong>প্রশ্ন ১. VM আর Container-এর মূল পার্থক্য কী?</strong></summary>

VM পুরো একটা আলাদা guest OS virtualize করে (heavy, boot হতে সময় লাগে)। Container host OS-এর kernel শেয়ার করে, শুধু process-level-এ isolate করে (lightweight, fast boot)।
</details>

<details>
<summary><strong>প্রশ্ন ২. Image আর Container-এর পার্থক্য কী?</strong></summary>

Image হলো static blueprint/snapshot (recipe)। Container হলো সেই image থেকে শুরু হওয়া running instance (রান্না করা খাবার)। একই image থেকে একাধিক container চালানো যায়।
</details>

<details>
<summary><strong>প্রশ্ন ৩. Docker Hub আসলে কী?</strong></summary>

এটা একটা অনলাইন registry (GitHub-এর মতো, কিন্তু image-এর জন্য) — যেখান থেকে readymade image pull করা যায়, এবং নিজের বানানো image push করে রাখা যায়।
</details>

<details>
<summary><strong>প্রশ্ন ৪. "Works on my machine" সমস্যাটা Docker কীভাবে সমাধান করে?</strong></summary>

কারণ Docker container-এ app-এর সাথে dependencies/config সব কিছু একই package-এ থাকে — তাই dev, staging, production যেখানেই চালানো হোক, একই environment পাওয়া যায়, OS/library version mismatch হওয়ার সুযোগ থাকে না।
</details>

## ৯. ফ্রি রিসোর্স (English)

- Official docs: [Docker Get Started guide](https://docs.docker.com/get-started/)
- YouTube: **"Docker Tutorial for Beginners [FULL COURSE in 3 Hours]"** — channel: *TechWorld with Nana*
- YouTube: **"Docker Tutorial for Beginners - A Full DevOps Course"** — channel: *freeCodeCamp.org*

---

**পরের চ্যাপ্টার:** Chapter 2 — Install + First Container *(শীঘ্রই আসছে)*
