# Chapter 1 — Cloud কী? VPS বনাম AWS বনাম Shared Hosting

> **Course:** Cloud (AWS / VPS Basics) · **Chapter:** 1 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cloud/01-what-is-the-cloud)

তুমি app বানাতে আর container করতে পারো। এখন সেটা তোমার laptop ছাড়া অন্য কোথাও চলতে হবে, যেখানে আসল মানুষ পৌঁছাতে পারে। এই course সেই "অন্য কোথাও" নিয়ে।

## ১. "Cloud" মানে কী?

**Cloud হলো অন্য কারো computer, যা তুমি internet দিয়ে ভাড়া নাও।**

আসলে এটুকুই। একটা company (AWS, Google, Microsoft, DigitalOcean, Hetzner আর আরও অনেক) বিশাল data center-এ server-এর পর server রাখে। তুমি তার একটা অংশ ভাড়া নাও, সাধারণত যতটুকু ব্যবহার করো ততটুকুর টাকা দিয়ে, আর web page বা একটা command দিয়ে মিনিটের মধ্যে বানাতে বা মুছতে পারো।

Cloud-এর আগে website চালাতে চাইলে company-কে **server কিনতে, ঘর খুঁজতে, বিদ্যুৎ ও ঠান্ডার ব্যবস্থা করতে আর ভাঙা অংশ বদলাতে** হতো। Cloud মানে: একটা button চাপো, server তৈরি।

কী পাও:

- **গতি:** সপ্তাহের বদলে মিনিটে server।
- **ব্যবহার অনুযায়ী খরচ:** ছোট থাকলে ছোট বিল, বড় হলে তবেই বড় বিল।
- **নমনীয়তা:** ব্যস্ত সময়ে বেশি শক্তি, কাজ শেষে মুছে ফেলা।
- **বিস্তার:** অনেক দেশে server।

কী ছাড়ো:

- অন্যের hardware আর নিয়ম, আর **এমন বিল যা অবাক করতে পারে** যদি কিছু বন্ধ করতে ভুলে যাও। খরচ নিয়ে আমরা Chapter 7-এ কথা বলব।

## ২. কে কী দেখে? (IaaS, PaaS, SaaS)

Cloud service কয়েক স্তরে আসে। প্রশ্ন সবসময় একটাই: **আমি কতটা সামলাই, আর provider কতটা?**

| ধরন | তুমি পাও | তুমি সামলাও | উদাহরণ |
|---|---|---|---|
| **IaaS** (Infrastructure) | একটা virtual server | OS, software, security update, তোমার app | AWS EC2, একটা VPS |
| **PaaS** (Platform) | তোমার code চালানোর জায়গা | শুধু তোমার app আর data | Heroku-ধাঁচের platform, AWS Elastic Beanstalk |
| **SaaS** (Software) | তৈরি product | শুধু তোমার ব্যবহার | Gmail, GitHub, Google Docs |

![IaaS, PaaS ও SaaS-এ কে কী সামলায়](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/06-cloud/images/iaas-paas-saas.svg)

বেশি নিয়ন্ত্রণ মানে বেশি কাজ। VPS তোমাকে স্বাধীনতা দেয়, কিন্তু **তোমাকেই** patch করতে, সুরক্ষিত রাখতে আর backup নিতে হয়। এই course বেশিরভাগ IaaS স্তরে থাকে, কারণ DevOps দক্ষতা সবচেয়ে বেশি কাজে লাগে এখানেই।

## ৩. Website চালানোর চারটা প্রচলিত উপায়

### Shared hosting
তুমি এমন server-এর ছোট একটা জায়গা ভাড়া নাও যা **অনেক customer ভাগ করে ব্যবহার করে**। সস্তা আর সহজ (cPanel, ক্লিকে install)। কিন্তু নিজের software install করা যায় না, প্রতিবেশীদের সাথে resource ভাগ করতে হয়, নিয়ন্ত্রণ কম। সাধারণ site-এর জন্য চলে, আসল app-এর জন্য সীমিত (যেমন Docker সাধারণত অনুমোদিত নয়)।

### VPS (Virtual Private Server)
একটা physical machine কয়েকটা virtual server-এ ভাগ হয়। তুমি পাও **root access-সহ তোমার নিজের server**: নিজের OS, নিজের software, নিজের IP। মাসিক নির্দিষ্ট দাম। এই course-এর Laravel site-ও এমন জায়গায় চলে। Provider-দের মধ্যে আছে DigitalOcean, Hetzner, Vultr, Linode আর অনেক স্থানীয় provider।

### Public cloud (AWS, Google Cloud, Azure)
শুধু server নয়, শত শত service: database, file storage, queue, load balancer, AI আর আরও, সব ব্যবহার অনুযায়ী বিল। খুব শক্তিশালী, খুব নমনীয়, আবার **শুরুতে খুব বড় আর গোলমেলে**, দাম আগে থেকে আন্দাজ করা কঠিন।

### PaaS / serverless
তুমি code push করো আর platform সেটা চালায়। সামলানো কম, কিন্তু নিয়ন্ত্রণও কম, আর বড় হলে খরচ বাড়তে পারে।

| | Shared hosting | VPS | AWS-ধাঁচের cloud | PaaS |
|---|---|---|---|---|
| নিয়ন্ত্রণ | কম | **বেশি** | **বেশি** | মাঝারি |
| সামলানোর পরিশ্রম | সবচেয়ে কম | মাঝারি | সবচেয়ে বেশি | কম |
| দামের পূর্বানুমান | বেশি | **বেশি** | কম | মাঝারি |
| অনেক service (DB, queue, storage) | না | নিজে install | **হ্যাঁ, managed** | কিছু |
| প্রথম পছন্দ হিসেবে ভালো | সরল site | শেখা, ছোট app | বাড়ন্ত team, বড় system | দ্রুত prototype |

## ৪. কীভাবে বাছবে

ভাবার একটা বাস্তব উপায়:

- **DevOps শেখা / ছোট project / কম বাজেট → একটা VPS।** সরল, সস্তা, দাম আগে থেকে জানা, আর তুমি আসল ভিত্তি শেখো: Linux, SSH, firewall, web server।
- **একটা team, অনেক service, বা বৃদ্ধি → AWS (বা অন্য বড় cloud)।** Managed service সবকিছু নিজে চালানোর ঝামেলা বাঁচায়।
- **আজই কোনো ops ছাড়া site অনলাইনে চাই → shared hosting বা PaaS।**

দক্ষতা এক জায়গা থেকে অন্যত্র যায়। SSH, Linux, DNS, firewall আর HTTPS একটা সস্তা VPS-এ আর AWS-এর server-এ একইভাবে কাজ করে। তাই আমরা পরের chapter-এ VPS দিয়ে শুরু করব, তারপর AWS শিখব।

**বাংলাদেশের developer-দের জন্য:** বেশিরভাগ আন্তর্জাতিক provider USD-তে টাকা নেয় আর আন্তর্জাতিক লেনদেনে চলে এমন card লাগে। বাছার আগে এটা দেখে নাও। **Latency**-র কথাও ভাবো: সিঙ্গাপুরের server সাধারণত যুক্তরাষ্ট্র বা ইউরোপের চেয়ে বাংলাদেশি user-দের কাছে কাছাকাছি, তাই দ্রুত। তোমার user-দের সবচেয়ে কাছের region বেছে নাও।

## ৫. যে শব্দগুলো সবখানে পাবে

- **Region / Availability Zone:** region হলো ভৌগোলিক এলাকা (যেমন `ap-southeast-1`, সিঙ্গাপুর); Availability Zone হলো তার ভেতরের আলাদা data center।
- **Instance:** একটা virtual server।
- **Shared responsibility:** provider *ভবন ও hardware* সুরক্ষিত রাখে; **তুমি** *তোমার server, data আর password* সুরক্ষিত রাখো।
- **Free tier:** নতুন account-এর জন্য সীমিত বিনামূল্যের ব্যবহার। সবসময় provider-এর site-এ বর্তমান শর্ত পড়ো, কারণ তা বদলায়।

## ৬. মূল কথা

- Cloud হলো **ভাড়া করা computer, যা তুমি internet দিয়ে নিয়ন্ত্রণ করো**, ব্যবহার অনুযায়ী বিল।
- **IaaS** (OS আর app তুমি সামলাও), **PaaS** (শুধু app), **SaaS** (শুধু ব্যবহার): বেশি নিয়ন্ত্রণ মানে বেশি কাজ।
- Shared hosting সস্তা কিন্তু সীমিত; **VPS** দেয় নিজের server; **AWS-ধাঁচের cloud** দেয় অনেক managed service; **PaaS** server-এর কাজ সরিয়ে দেয়।
- শেখার জন্য VPS সেরা জায়গা; দক্ষতা AWS-এও কাজে লাগে।
- যা চালাও তা সুরক্ষিত রাখার দায় তোমার (**shared responsibility**), আর বিলের দিকেও নজর রাখতে হবে।

## ৭. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. এক বাক্যে, cloud কী?</strong></summary>

Provider-এর মালিকানার computer, যা তুমি internet দিয়ে ভাড়া নাও, সাধারণত যতটুকু ব্যবহার করো ততটুকুর টাকা দিয়ে।
</details>

<details>
<summary><strong>Q2. IaaS, PaaS, SaaS-এর কোনটায় তুমি সবচেয়ে বেশি সামলাও? IaaS-এর একটা উদাহরণ দাও।</strong></summary>

IaaS: তুমি operating system, software আর security update সামলাও। উদাহরণ: একটা VPS বা AWS EC2।
</details>

<details>
<summary><strong>Q3. VPS-এর তুলনায় shared hosting-এর দুটো সীমাবদ্ধতা বলো।</strong></summary>

সাধারণত নিজের software (যেমন Docker) install বা root access পাওয়া যায় না, আর অন্য customer-দের সাথে resource ভাগ করতে হয়।
</details>

<details>
<summary><strong>Q4. "Shared responsibility" মানে কী?</strong></summary>

Provider data center আর hardware সুরক্ষিত রাখে। তোমার server, software, data আর password সুরক্ষিত রাখার দায় তোমার।
</details>

<details>
<summary><strong>Q5. User-দের কাছের region কেন গুরুত্বপূর্ণ?</strong></summary>

দূরত্ব কম মানে latency কম, তাই site দ্রুত মনে হয়।
</details>

## ৮. বিনামূল্যের resource

- AWS: [What is cloud computing?](https://aws.amazon.com/what-is-cloud-computing/)
- Cloudflare: [What is the cloud?](https://www.cloudflare.com/learning/cloud/what-is-the-cloud/)
- AWS: [Free Tier](https://aws.amazon.com/free/) (বর্তমান শর্ত পড়ো)
- DigitalOcean: [Community tutorials](https://www.digitalocean.com/community/tutorials) (বিনামূল্যের, নতুনদের জন্য লেখা)

---

**Next:** Chapter 2 — তোমার প্রথম VPS: SSH key, নিরাপদ শুরু আর firewall *(coming soon)*
