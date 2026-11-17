# Chapter 7 — Network, নিরাপত্তা, Backup আর খরচ

> **Course:** Cloud (AWS / VPS Basics) · **Chapter:** 7 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cloud/07-networking-security-and-costs)

তুমি server চালাতে, ফাইল রাখতে আর database হোস্ট করতে পারো। শেষ project-এর আগে চারটা জিনিস একটা demo-কে ভরসাযোগ্য কিছুতে বদলে দেয়: **কে কীসে পৌঁছাতে পারে** (network), **সবকিছু নিরাপদ রাখা** (নিরাপত্তা), **ভুল থেকে বেঁচে ফেরা** (backup) আর **আকস্মিক বিল এড়ানো** (খরচ)।

> আগের মতোই, AWS-এর দাম আর console-এর screen বদলায়। এই chapter ধারণা শেখায় আর কোনো দাম দেয় না।

## ১. VPC: cloud-এ তোমার নিজস্ব network

**VPC (Virtual Private Cloud)** হলো **AWS-এর ভেতরে তোমার নিজস্ব বিচ্ছিন্ন network**, একটা Region-এ। তুমি যা-ই চালু করো (EC2, RDS) সব একটা VPC-র ভেতরে থাকে। প্রতিটা নতুন account-এ ব্যবহারের জন্য একটা **default VPC** তৈরি থাকে, তাই কিছু নকশা না করেই server চালু করা যায়। আসল project-এ তুমি নিজেরটা বানাও।

VPC গড়া হয় এই অংশগুলো দিয়ে:

| অংশ | কী করে |
|---|---|
| **CIDR block** | VPC-র private IP address-এর পরিসর, যেমন `10.0.0.0/16` |
| **Subnet** | VPC-র একটা টুকরো, যা **একটা Availability Zone-এ** থাকে |
| **Route table** | Network traffic পরে কোথায় যাবে তার নিয়ম |
| **Internet gateway** | VPC আর public internet-এর মধ্যের দরজা |
| **NAT gateway** | Private server-কে বাইরে সংযোগ করতে দেয় (update-এর জন্য), বাইরে থেকে পৌঁছানো না গিয়ে |

**এক মিনিটে CIDR।** `10.0.0.0/16` মানে "প্রথম ১৬ bit স্থির, বাকিগুলো মুক্ত", যা প্রায় ৬৫,০০০ address দেয়। `10.0.1.0/24` দেয় ২৫৬টা। `/`-এর পরের সংখ্যা যত বড়, পরিসর তত *ছোট*। (AWS প্রতিটা subnet-এ ৫টা address সংরক্ষণ করে।) হাতে হিসাব করা লাগে না: CIDR calculator ব্যবহার করো।

### Public আর private subnet

মূল নকশার ধারণা:

- **Public subnet**-এর internet gateway-এ route আছে। এখানের জিনিস internet থেকে পৌঁছানো যায়: তোমার web server বা load balancer।
- **Private subnet**-এ internet থেকে ঢোকার **কোনো** route নেই। এখানের জিনিস লুকানো: তোমার database, ভেতরের service।

![Public ও private subnet-সহ একটা VPC](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/06-cloud/images/vpc-layout.svg)

Visitor web server-এ পৌঁছায়। Web server database-এ পৌঁছায়। **Internet-এর কেউ database-এ একেবারেই পৌঁছাতে পারে না**, ঠিকানা আন্দাজ করেও না। এটাই সবচেয়ে নিরাপদ ও প্রচলিত বিন্যাস। (NAT gateway private server-কে update নামাতে সাহায্য করে, কিন্তু তার নিজেরও ঘণ্টা হিসেবে খরচ আছে, তাই চালু রাখার আগে দাম দেখো।)

## ২. Security group: প্রতিটা resource-এর firewall

**Security group** হলো instance, database বা load balancer-এ জোড়া firewall। Chapter 2-এর UFW-র তুলনায় এটা server-এর **বাইরে** থাকে, আর তুমি AWS-এ এটা পরিচালনা করো।

কীভাবে কাজ করে:

- **শুধু allow নিয়ম।** কোনো "deny" নিয়ম নেই। যা অনুমোদিত নয় তা আটকানো।
- **Inbound** default-এ নিষিদ্ধ; **outbound** default-এ অনুমোদিত।
- এটা **stateful**: আসা request অনুমোদিত হলে উত্তর নিজে নিজেই ফিরে যেতে পারে।
- নিয়ম বলে: *protocol + port + source*। Source একটা IP পরিসর **বা অন্য একটা security group** হতে পারে।

একটা উদাহরণ:

| Security group | Inbound নিয়ম | মানে |
|---|---|---|
| `web-sg` | `0.0.0.0/0` থেকে TCP 80, 443 | যে কেউ website লোড করতে পারে |
| `web-sg` | `203.0.113.7/32` থেকে TCP 22 | **শুধু তোমার নিজের IP** SSH করতে পারে |
| `db-sg` | `web-sg` থেকে TCP 3306 | **শুধু `web-sg`-এর server** MySQL-এর সাথে কথা বলতে পারে |

শেষ সারিটাই শক্তিশালী কৌশল: database **web server-দের security group থেকে** সংযোগ নেয়, তাই তুমি কখনো IP address লেখো না, আর নতুন web server স্বয়ংক্রিয়ভাবে access পায়।

**SSH (22) বা database port কখনো `0.0.0.0/0`-তে (পুরো internet) খুলো না।** Bot কয়েক মিনিটে খুঁজে পাবে। শুধু নিজের IP অনুমোদন করো, বা SSH-এর বদলে AWS-এর session tool ব্যবহার করো।

Subnet স্তরে আরেকটা, মোটা firewall-ও আছে, **network ACL (NACL)**। এটা *stateless* আর allow *ও* deny নিয়ম সমর্থন করে। বেশিরভাগ project NACL default-এ রেখে security group-এর ওপর নির্ভর করে।

## ৩. নিরাপত্তার checklist

এগুলোর বেশিরভাগ তুমি আগেই দেখেছ। প্রতিটা project-এ মিলিয়ে দেখার তালিকা হিসেবে ধরো:

- [ ] Root user ও প্রতিটা মানুষ IAM user-এ **MFA**; root-এর access key নয় (Chapter 5)।
- [ ] **Least privilege** IAM policy; server-এ access key-র বদলে **role**।
- [ ] **Git বা image-এ secret নয়** (Docker course)।
- [ ] **SSH** শুধু তোমার IP থেকে, key দিয়ে, password login বন্ধ (Chapter 2)।
- [ ] **Database private**, শুধু app-এর security group থেকে পৌঁছানো যায়।
- [ ] **S3 bucket private**, যদি না public site উদ্দেশ্য।
- [ ] **সবখানে HTTPS** (Chapter 4)।
- [ ] **Update:** OS ও package নিয়মিত patch করো।
- [ ] **Encryption at rest:** EBS disk, RDS ও S3-তে চালু করা যায়, সাধারণত একটা setting-এ।
- [ ] **Audit log:** **CloudTrail** তোমার account-এ API দিয়ে কে কী করেছে তা রেকর্ড করে, এভাবেই "ওটা কে মুছল?" বা "এই key কি অপব্যবহার হয়েছে?"-র উত্তর মেলে।

## ৪. Backup: যা তোমার লাগে মাত্র একবার

একদিন একটা table মুছে যাবে, একটা disk নষ্ট হবে, বা কেউ ভুল bucket মুছবে। Backup দিয়েই তুমি সেরে ওঠো।

দুটো সংখ্যা তোমার প্রয়োজন বর্ণনা করে:

- **RPO (Recovery Point Objective):** কতটা data হারানো সহ্য করতে পারো? "সর্বোচ্চ ১ ঘণ্টার order" মানে অন্তত ঘণ্টায় ঘণ্টায় backup।
- **RTO (Recovery Time Objective):** কতক্ষণ বন্ধ থাকা সহ্য করতে পারো? "২ ঘণ্টার মধ্যে ফিরে আসা" তোমার restore-এর ধরন ঠিক করে।

**3-2-1 নিয়ম** ভালো পথনির্দেশ: data-র **৩**টা কপি, **২** ধরনের আলাদা storage-এ, **১**টা কপি অন্য কোথাও (অন্য Region বা account-এ)।

AWS যা দেয়:

| Data | Backup-এর উপায় |
|---|---|
| **RDS database** | **Automated backup** (দৈনিক, point-in-time recovery-সহ, তুমি যত দিন বাছো তত দিন রাখা) ও হাতে নেওয়া **snapshot** |
| **EC2 disk (EBS)** | **EBS snapshot** (disk-এর কপি, ক্রমবর্ধমানভাবে জমা) |
| **S3 ফাইল** | **Versioning** পুরনো version রাখে; **cross-Region replication** অন্য Region-এ কপি করে |
| **তোমার VPS বা নিজে-চালানো DB** | নির্ধারিত dump (যেমন `mysqldump`) **S3-তে কপি করা** |

খুব প্রচলিত ধরন: একটা cron job database dump করে, compress করে S3-তে upload করে, আর একটা lifecycle rule N দিনের পুরনো কপি মুছে দেয়।

দুটো সতর্কতা:

1. **Multi-AZ আর RAID backup নয়।** এরা hardware বিকলের বিরুদ্ধে রক্ষা করে, *তোমার* data মোছার বিরুদ্ধে নয়, কারণ মোছাটাও কপি হয়ে যায়।
2. **যে backup কখনো restore করোনি সেটা শুধু আশা।** একটা test environment-এ restore অনুশীলন করো আর সময় মাপো। এভাবেই তোমার আসল RTO জানা যায়।

## ৫. খরচ: আকস্মিক বিল এড়ানোর উপায়

Cloud billing ব্যবহার অনুযায়ী, যা চমৎকার যতক্ষণ না কিছু ভুলে যাও। বিল তৈরি হয় কয়েকটা উৎস থেকে:

| উৎস | মনে রাখো |
|---|---|
| **Compute** | Instance যতক্ষণ চলে ততক্ষণের বিল। বড় type = বেশি খরচ |
| **Storage** | Disk (EBS), S3 আর snapshot জমানো GB-র বিল দেয়, **server stopped থাকলেও** |
| **Data transfer** | Internet-এ **বেরিয়ে** যাওয়া traffic-এ টাকা লাগে; ঢোকা সাধারণত বিনামূল্যে |
| **Managed বাড়তি** | Load balancer, NAT gateway, RDS Multi-AZ ও public IPv4 address ঘণ্টা হিসেবে বিল দেয় |
| **অলস অবশিষ্ট** | জোড়া-নেই এমন disk, পুরনো snapshot, অব্যবহৃত Elastic IP, ভুলে যাওয়া test server |

**যে অভ্যাস চমক এড়ায়:**

1. **আগে email alert-সহ budget বসাও** (Chapter 5)। এটা সতর্ক করে; খরচ থামায় না।
2. **নিয়মিত বিল দেখো।** কোন service-এ কত খরচ তা দেখতে **Cost Explorer** ব্যবহার করো।
3. Resource-এ **tag দাও** (যেমন `project=shop`, `env=test`) যাতে project ধরে খুঁজতে, গোছাতে ও মুছতে পারো।
4. **অনুশীলনের পর পরিষ্কার করো:** test instance terminate করো, জোড়া-নেই এমন volume, পুরনো snapshot ও অব্যবহৃত Elastic IP মুছো, আর test bucket খালি করো।
5. **Right-size:** শেখা আর ছোট app-এর জন্য সাধারণত ছোট instance যথেষ্ট। "যদি লাগে" ভেবে বড় বেছো না।
6. যে non-production server-কে রাতভর চলতে হয় না তা **stop করো বা সময়সূচি দাও**।
7. **Free-tier-এর শর্ত মন দিয়ে পড়ো:** এগুলো পরিমাণ ও সময় দুদিকেই সীমিত, আর তার বাইরে সবকিছুর বিল হয়।
8. **তোমার Region জানো:** যে Region কদাচিৎ খোলো সেখানে ভুলে যাওয়া resource-ও টাকা খরচ করে। Region selector দেখো (Chapter 5)।

শেষে একটা **অনুশীলন-পরের checklist:** EC2 (instance, volume, Elastic IP), RDS (database, snapshot), S3 (bucket) আর **Billing** page খোলো। যা লাগে না তা এখনও থাকলে মুছে দাও।

## ৬. মূল কথা

- **VPC** হলো Region-এ তোমার private network। **Subnet** একটা AZ-এ থাকে; **public** subnet internet gateway-এ route করে, **private** করে না।
- **Web tier public subnet-এ** আর **database private subnet-এ** রাখো।
- **Security group** stateful, শুধু-allow firewall। Source হিসেবে **অন্য security group** দাও (`db-sg` অনুমোদন করে `web-sg`-কে)। SSH বা database কখনো `0.0.0.0/0`-তে খুলো না।
- নিরাপত্তার checklist মানো: MFA, least privilege, Git-এ secret নয়, private DB ও bucket, HTTPS, update, encryption, **CloudTrail**।
- **Backup:** তোমার **RPO** ও **RTO** জানো, **3-2-1** মানো, RDS automated backup, EBS snapshot ও S3 versioning ব্যবহার করো, আর **restore পরীক্ষা করো**। Multi-AZ backup নয়।
- **খরচ:** compute, storage (stopped হলেও), বেরিয়ে যাওয়া data transfer আর অলস অবশিষ্ট। **Budget** বসাও, resource-এ **tag** দাও, অনুশীলনের পর **পরিষ্কার** করো।

## ৭. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. Public আর private subnet-এর পার্থক্য কী?</strong></summary>

Public subnet-এর internet gateway-এ route আছে, তাই তার resource internet থেকে পৌঁছানো যায়। Private subnet-এ এমন route নেই, তাই তার resource (যেমন database) internet থেকে লুকানো।
</details>

<details>
<summary><strong>Q2. শুধু তোমার web server-কে database-এ সংযোগ করতে দেবে কীভাবে?</strong></summary>

Database private subnet-এ রাখো আর database-এর security group-এ web server-দের security group-কে source করে database port অনুমোদন করো।
</details>

<details>
<summary><strong>Q3. Security group "stateful" হওয়ার মানে কী?</strong></summary>

আসা request অনুমোদিত হলে উত্তর আলাদা নিয়ম ছাড়াই নিজে নিজে ফিরে যেতে পারে।
</details>

<details>
<summary><strong>Q4. SSH (port 22) 0.0.0.0/0-তে খোলা খারাপ কেন?</strong></summary>

Internet-এর যে কেউ সংযোগ চেষ্টা করতে পারে, আর bot কয়েক মিনিটে খোলা SSH খুঁজে পায়। শুধু নিজের IP অনুমোদন করো।
</details>

<details>
<summary><strong>Q5. RPO আর RTO মানে কী?</strong></summary>

RPO হলো কতটা data হারানো সহ্য করতে পারো (কত ঘনঘন backup নেবে তা ঠিক করে)। RTO হলো কতক্ষণ বন্ধ থাকা সহ্য করতে পারো (কত দ্রুত restore করতে হবে তা ঠিক করে)।
</details>

<details>
<summary><strong>Q6. Multi-AZ backup-এর বিকল্প নয় কেন?</strong></summary>

এটা hardware বা data center বিকলের বিরুদ্ধে রক্ষা করে, কিন্তু ভুল মোছা বা নষ্ট data standby-তেও কপি হয়ে যায়।
</details>

<details>
<summary><strong>Q7. অনুশীলন "বন্ধ" করার পরও টাকা খরচ করতে থাকে এমন তিনটা জিনিসের নাম বলো।</strong></summary>

উদাহরণ: EBS volume ও snapshot, S3 data, অব্যবহৃত Elastic IP বা public IPv4 address, load balancer, NAT gateway, RDS instance।
</details>

## ৮. বিনামূল্যের resource

- AWS: [How Amazon VPC works](https://docs.aws.amazon.com/vpc/latest/userguide/how-it-works.html)
- AWS: [Security groups](https://docs.aws.amazon.com/vpc/latest/userguide/vpc-security-groups.html)
- AWS: [AWS CloudTrail](https://docs.aws.amazon.com/awscloudtrail/latest/userguide/cloudtrail-user-guide.html)
- AWS: [Cost Explorer](https://aws.amazon.com/aws-cost-management/aws-cost-explorer/) ও [Billing and Cost Management docs](https://docs.aws.amazon.com/awsaccountbilling/latest/aboutv2/billing-what-is.html)
- Tool: [CIDR calculator](https://cidr.xyz/) (address পরিসর চোখে দেখায়)

---

**Next:** Chapter 8 — আসল project: Production-এ একটা Laravel app *(coming soon)*
