# Chapter 5 — AWS-এর মূল ধারণা: Account, Region আর IAM

> **Course:** Cloud (AWS / VPS Basics) · **Chapter:** 5 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cloud/05-aws-core-concepts)

VPS হলো একটা server। **AWS (Amazon Web Services)** হলো শত শত service, আর প্রথম screen-টা বিহ্বল করে দিতে পারে। ভালো খবর: ছোট একটা ধারণার সেট প্রায় সবকিছু বুঝিয়ে দেয়। এই chapter সেই সেট শেখায়, সাথে **প্রথমে যে তিনটা কাজ করবে** যাতে একটা ভুলে তোমার টাকা বা account না যায়।

> **সঠিকতা নিয়ে নোট:** AWS তার console, দাম আর free-tier-এর নিয়ম প্রায়ই বদলায়। এই chapter টেকসই ধারণাগুলো শেখায়। যেকোনো সংখ্যা বা দামের জন্য AWS-এর নিজস্ব page দেখো; link শেষে আছে।

## ১. AWS account কী

AWS account হলো **তুমি যা বানাও আর যার জন্য বিল পাও, সবকিছুর একটা পাত্র**। সাইন আপের সময় তুমি একটা email address, একটা payment card দাও আর ফোন যাচাই করো। যে email দিয়ে সাইন আপ করো সেটাই account-এর **root user** হয়।

Root user *সবকিছু* করতে পারে, account বন্ধ করা আর billing বদলানো সহ। তাই এটা তোমার সবচেয়ে বিপজ্জনক login। নিয়ম:

> **Root user-কে সুরক্ষিত করো, তারপর আর ব্যবহার করো না।**

## ২. প্রথম কাজ: root user-কে রক্ষা করো

1. **শক্ত, অনন্য password** ব্যবহার করো, password manager-এ রাখো।
2. Root user-এ **MFA (multi-factor authentication)** চালু করো: authenticator app বা security key, যাতে শুধু চুরি হওয়া password অকেজো।
3. **Root-এর জন্য access key বানিও না।** কখনোই না।
4. নিজের admin user বানানোর পর (অংশ ৬), শুধু বিরল কাজে যা সত্যিই লাগে তার জন্যই root-এ ঢোকো।

## ৩. AWS ব্যবহারের তিন উপায়

| উপায় | কী | কখন |
|---|---|---|
| **Console** | Web site | শেখা, ঘুরে দেখা |
| **CLI** | Terminal-এর `aws` command | Script, গতি, পুনরাবৃত্তির কাজ |
| **SDK / API** | PHP, Python, JavaScript-এর library | তোমার app AWS-এর সাথে কথা বলে (S3-তে upload, email পাঠানো) |

তিনটাই আসলে একই কাজ করে: AWS-এর API ডাকে। এই course-এ পরে দেখবে console-এ ক্লিক শেখার জন্য ঠিক, কিন্তু আসল project-এ ধাপগুলো code হিসেবে লেখা হয় (Infrastructure as Code course)।

## ৪. Region আর Availability Zone

AWS পৃথিবীর অনেক জায়গায় চলে।

- **Region** একটা ভৌগোলিক এলাকা, কোড যেমন `ap-southeast-1` (সিঙ্গাপুর) বা `eu-west-1` (আয়ারল্যান্ড)।
- প্রতিটা Region-এ কয়েকটা **Availability Zone (AZ)** থাকে: আলাদা data center, নিজস্ব বিদ্যুৎ ও network, অল্প দূরত্বে, লেখা হয় `ap-southeast-1a`, `ap-southeast-1b`-এর মতো।

![Region-এর ভেতরে Availability Zone](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/06-cloud/images/aws-regions-azs.svg)

কেন গুরুত্বপূর্ণ:

- **Latency:** user-দের সবচেয়ে কাছের Region বাছো (Chapter 1)।
- **স্থিতিস্থাপকতা:** দুটো AZ-এ ছড়ালে একটা data center বিকল হলে তুমি বসে যাও না।
- **Region আলাদা আলাদা জগৎ।** সিঙ্গাপুরে বানানো server console আয়ারল্যান্ডে সেট থাকলে **দেখা যায় না**। এটা সবাইকে একবার ধাঁধায় ফেলে: "আমার server কোথায় গেল?" আগে console-এর ওপরের ডানদিকের **Region selector** দেখো।
- কিছু service **global** (যেমন IAM আর Route 53); বেশিরভাগ **regional**।
- Region ভেদে দাম আর উপলব্ধ service আলাদা হতে পারে।

## ৫. IAM: কে কী করতে পারে

**IAM (Identity and Access Management)** তোমার AWS account-এ প্রবেশ নিয়ন্ত্রণ করে। এর সবকিছু দুটো প্রশ্নের উত্তর দেয়: **কে** চাইছে, আর সে **কী** করার অনুমতি পেয়েছে?

| ধারণা | কী |
|---|---|
| **User** | নিজস্ব login বা key-সহ একজন মানুষ (বা program) |
| **Group** | একই permission ভাগ করা user-দের দল (যেমন "developers") |
| **Role** | এমন একটা পরিচয় যা **কেউ বা কিছু সাময়িকভাবে গ্রহণ করে**, নিজের কোনো password নেই (যেমন S3 থেকে পড়তে হবে এমন একটা EC2 server) |
| **Policy** | permission-এর তালিকা দেওয়া একটা JSON ডকুমেন্ট, user, group বা role-এ জোড়া |

![IAM: পরিচয় আর policy মিলে প্রবেশ ঠিক করে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/06-cloud/images/iam-model.svg)

**সবকিছু default-এ নিষিদ্ধ।** নতুন user কোনো policy অনুমতি না দেওয়া পর্যন্ত কিছুই করতে পারে না। এটা নিরাপদ শুরুর বিন্দু।

### একটা policy, লাইন ধরে ধরে

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:GetObject", "s3:ListBucket"],
      "Resource": [
        "arn:aws:s3:::my-bucket",
        "arn:aws:s3:::my-bucket/*"
      ]
    }
  ]
}
```

- **Effect:** `Allow` বা `Deny`। (স্পষ্ট `Deny` সবসময় জেতে।)
- **Action:** *কী* করা যাবে: `service:Operation`, যেমন `s3:GetObject` (ফাইল পড়া)।
- **Resource:** *কোন জিনিসগুলোতে*, **ARN** (Amazon Resource Name) আকারে লেখা।
- `Version` শুধু policy ভাষার version; যেমন আছে তেমন রাখো।

ARN দেখতে `arn:aws:service:region:account-id:resource`-এর মতো। ওপরেরটার মানে "`my-bucket` bucket আর তার ভেতরের সব"। এই policy কাউকে ওই একটা bucket **পড়তে** দেয়, আর কিছু না।

### Least privilege

প্রতিটা user বা program-কে **শুধু যে permission লাগে** তাই দাও, বেশি নয়। একটা script শুধু একটা bucket থেকে পড়লে সে ওই bucket-এর read access পায়, "admin" নয়। তার key ফাঁস হলেও ক্ষতি সীমিত থাকে।

### Access key-র চেয়ে role ভালো

**Access key** (একটা ID আর একটা secret) হলো program-এর দীর্ঘস্থায়ী password। এগুলো ফাঁস হয়: code-এ paste হয়, GitHub-এ commit হয়, chat-এ থেকে যায়। স্বয়ংক্রিয় bot-রা public repository-তে AWS key খোঁজে, আর কয়েক মিনিটেই তোমার বিলে দামি server চালাতে পারে।

তাই:

- **Access key কখনো code বা Git-এ রেখো না।**
- *AWS-এ চলা* কিছুর (একটা server) জন্য **role** জোড়ো। সে স্বয়ংক্রিয়ভাবে স্বল্পস্থায়ী credential পায়, ফাঁস হওয়ার মতো key-ই থাকে না।
- মানুষের জন্য MFA-সহ user ব্যবহার করো (team-এর জন্য AWS *IAM Identity Center*-ও দেয়)।
- কোনো key ফাঁস হলে IAM-এ **সাথে সাথে সেটা নিষ্ক্রিয় করো** আর নতুন বানাও।

## ৬. দ্বিতীয় কাজ: নিজের admin user

Root-এর বদলে প্রতিদিনের জন্য একটা user বানাও:

1. Console-এ **IAM** খোলো, একটা **user** বানাও আর তাকে administrator permission দাও (ব্যক্তিগত শেখার account-এ AWS-managed `AdministratorAccess` policy সহজ পছন্দ। Team-এ তুমি আরও কড়া হবে)।
2. ওই user-এও **MFA** চালু করো।
3. এখন থেকে এই user দিয়ে login করো। Root-কে তালাবদ্ধ রাখো।

## ৭. তৃতীয় কাজ: budget alert

নতুনদের চিরাচরিত বিপর্যয়: তুমি কিছু চালু করে ভুলে যাও, আর একটা বিল এসে হাজির। কিছু বানানোর **আগেই** একটা নিরাপত্তা-জাল পাতো:

1. **Billing** → **Budgets** খোলো।
2. একটা ছোট অঙ্কের **monthly cost budget** বানাও যাতে তুমি স্বস্তিতে থাকো (আর দিলে দ্বিতীয় একটা কম সীমা)।
3. তোমার email দাও, যাতে খরচ সীমার কাছে গেলে AWS **তোমাকে সতর্ক করে**।

Budget **সতর্ক করে**; নিজে খরচ থামায় না। তোমাকেই দেখতে হবে আর যা লাগে না তা বন্ধ করতে হবে। খরচ নিয়ন্ত্রণ আমরা Chapter 7-এ ঠিকভাবে দেখব।

AWS-এর page-এ **বর্তমান free tier-এর নিয়ম**ও পড়ো। সেগুলো বদলায়, আর কিছু বিনামূল্যের সুবিধা সাইন আপের পর সীমিত সময়ের জন্যই থাকে।

## ৮. AWS CLI

Terminal থেকে AWS ব্যবহার করতে **AWS CLI** install করো (official install page দেখো) আর নিজের user-এর সাথে জোড়ো:

```bash
aws configure                    # access key, secret, default region, output format জিজ্ঞেস করে
aws sts get-caller-identity      # "আমি কে?": CLI কোন account ও user ব্যবহার করছে দেখায়
aws s3 ls                        # উদাহরণ: তোমার S3 bucket-এর তালিকা
```

`aws configure` key জমা রাখে **তোমার নিজের computer-এর** `~/.aws/credentials`-এ। ওই ফাইল গোপন: কখনো commit করো না, কাউকে দিয়ো না। কিছু গোলমেলে লাগলে `aws sts get-caller-identity` ভালো প্রথম command: সে বলে তুমি আসলে কোন পরিচয় ও account ব্যবহার করছ।

## ৯. মূল কথা

- AWS account-এ তুমি যা বানাও আর যার টাকা দাও সব থাকে। **Root user** সর্বশক্তিমান: শক্ত password আর **MFA** দিয়ে রক্ষা করো, তারপর আর ব্যবহার করো না।
- শিখতে **Console**, পুনরাবৃত্তির কাজে **CLI**, app-এর ভেতরে **SDK**। সবই একই API ডাকে।
- **Region** ভৌগোলিক এলাকা; **Availability Zone** তার ভেতরের আলাদা data center। Region আলাদা জগৎ: কিছু উধাও মনে হলে Region selector দেখো।
- **IAM** কে আর কী-র উত্তর দেয়: **user**, **group**, **role** আর JSON **policy** (`Effect`, `Action`, `Resource`)। সবকিছু **default-এ নিষিদ্ধ**।
- **Least privilege** মানো। দীর্ঘস্থায়ী **access key**-র চেয়ে **role** বেছো; key কখনো code বা Git-এ রেখো না।
- প্রথম দিন: MFA দিয়ে root সুরক্ষিত করো, নিজের admin user বানাও, **budget alert** দাও।

## ১০. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. প্রতিদিনের কাজে root user ব্যবহার করা উচিত নয় কেন?</strong></summary>

Root সবকিছু করতে পারে, account বন্ধ করা ও billing বদলানো সহ, তাই চুরি হওয়া বা ভুল login বিপর্যয়কর। MFA দিয়ে তাকে রক্ষা করো আর আলাদা IAM user ব্যবহার করো।
</details>

<details>
<summary><strong>Q2. Region আর Availability Zone-এর পার্থক্য কী?</strong></summary>

Region একটা ভৌগোলিক এলাকা। Availability Zone ওই Region-এর ভেতরের কয়েকটা আলাদা data center-এর একটা।
</details>

<details>
<summary><strong>Q3. তুমি একটা server বানিয়েছ কিন্তু console-এ দেখা যাচ্ছে না। আগে কী দেখবে?</strong></summary>

Region selector। Resource একটা Region-এ থাকে আর console অন্য Region-এ সেট থাকলে দেখা যায় না।
</details>

<details>
<summary><strong>Q4. IAM policy statement-এর তিনটা প্রধান অংশ কী?</strong></summary>

`Effect` (Allow বা Deny), `Action` (কী করা যাবে) আর `Resource` (কোন জিনিসে প্রযোজ্য)।
</details>

<details>
<summary><strong>Q5. AWS-এ চলা server-এর জন্য access key-র চেয়ে role সাধারণত নিরাপদ কেন?</strong></summary>

Role স্বয়ংক্রিয়ভাবে স্বল্পস্থায়ী credential দেয়, তাই code বা Git-এ ফাঁস হওয়ার মতো দীর্ঘস্থায়ী secret থাকে না।
</details>

<details>
<summary><strong>Q6. AWS budget কি তোমাকে খরচ করা থেকে থামায়?</strong></summary>

না। খরচ সীমার কাছে গেলে সে সতর্ক করে; তোমাকেই ব্যবস্থা নিতে হয়।
</details>

## ১১. বিনামূল্যের resource

- AWS: [Getting started with AWS](https://aws.amazon.com/getting-started/)
- AWS: [Security best practices in IAM](https://docs.aws.amazon.com/IAM/latest/UserGuide/best-practices.html)
- AWS: [Global infrastructure: Regions and Availability Zones](https://aws.amazon.com/about-aws/global-infrastructure/regions_az/)
- AWS: [Installing the AWS CLI](https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html)
- AWS: [Free Tier](https://aws.amazon.com/free/) ও [Budgets](https://aws.amazon.com/aws-cost-management/aws-budgets/) (বর্তমান শর্ত পড়ো)

---

**Next:** Chapter 6 — AWS EC2, S3 আর RDS *(coming soon)*
