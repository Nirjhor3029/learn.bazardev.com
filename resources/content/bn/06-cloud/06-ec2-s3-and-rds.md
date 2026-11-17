# Chapter 6 — AWS EC2, S3 আর RDS

> **Course:** Cloud (AWS / VPS Basics) · **Chapter:** 6 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cloud/06-ec2-s3-and-rds)

AWS-এ শত শত service, কিন্তু সাধারণ একটা web app শুরু হয় মাত্র **তিনটা** দিয়ে: code চালানোর একটা server (**EC2**), ফাইল রাখার জায়গা (**S3**), আর একটা database (**RDS**)। এই তিনটা শিখলে তুমি বেশিরভাগ কিছু বানাতে পারবে।

> আগের chapter-এর মতোই: AWS তার console, দাম আর free-tier-এর নিয়ম প্রায়ই বদলায়। এই chapter টেকসই ধারণা শেখায় আর কোনো দাম দেয় না। সংখ্যার জন্য AWS-এর নিজস্ব page দেখো।

## ১. EC2: একটা virtual server

**EC2 (Elastic Compute Cloud)** হলো AWS-এর VPS। তুমি একটা virtual server ভাড়া নাও, যার নাম **instance**, আর তার operating system তুমি সামলাও। Chapter 2 বুঝে থাকলে EC2 চেনা লাগবে: SSH key, সাধারণ user, firewall, web server।

Instance চালুর সময় যা বাছো:

| অংশ | কী |
|---|---|
| **AMI** (Amazon Machine Image) | শুরুর disk image: কোন OS (Ubuntu, Amazon Linux...) |
| **Instance type** | আকার: CPU আর memory। নাম দেখতে `t3.micro`-র মতো (family `t3`, size `micro`)। বড় size = বেশি শক্তি = বেশি খরচ |
| **Key pair** | Login-এর SSH key (private key তুমি **একবারই** download করো) |
| **Security group** | Instance-এর firewall: কোন port কার জন্য খোলা (পরের chapter) |
| **EBS volume** | Instance-এর disk |
| **Region / AZ** | কোথায় চলে (Chapter 5) |

**Connect করা**, instance চলার পর আর security group SSH (port 22) অনুমোদন করলে:

```bash
chmod 400 my-key.pem                    # অন্যরা পড়তে পারে এমন key SSH নেয় না
ssh -i my-key.pem ubuntu@PUBLIC_IP      # Ubuntu AMI-তে "ubuntu"; Amazon Linux-এ "ec2-user"
```

Default login user AMI-র ওপর নির্ভর করে। এরপর Chapter 2 ও 4-এর সবকিছু প্রযোজ্য: নিজের user বানাও, web server বসাও, HTTPS চালু করো।

### Stop, start, terminate

খরচ নিয়ে এটাই সবচেয়ে গুরুত্বপূর্ণ কথা:

| কাজ | কী ঘটে | তবু কীসের টাকা লাগে? |
|---|---|---|
| **Stop** | Instance বন্ধ হয়; disk থেকে যায় | **Disk (EBS)**-এর, কিন্তু compute-এর আর নয় |
| **Start** | একই disk থেকে আবার boot | আবার compute |
| **Terminate** | Instance **মুছে যায়** (আর default-এ তার disk-ও) | কিছুর না, কিন্তু data **চলে গেছে** |

Stopped মানে বিনামূল্যে নয়: জোড়া disk আর কিছু address-এর খরচ চলতে থাকে। অনুশীলন শেষ হলে যা লাগবে না তা **terminate** করো।

### IP address

Default-এ instance একটা **public IP পায় যা stop আর start করলে বদলে যায়**। তোমার DNS record তাকে দেখালে সেটা ভেঙে যাবে (Chapter 3)। **Elastic IP** হলো একটা স্থির public address যা তুমি instance-এ জোড়ো, যাতে restart-এও টেকে। Public IPv4 address-এ খরচ হতে পারে, তাই বর্তমান দাম দেখো আর যা লাগে না তা ছেড়ে দাও।

## ২. S3: bucket-এ ফাইল

**S3 (Simple Storage Service)** **ফাইল (যাদের বলে object) রাখে পাত্রে (যাদের বলে bucket)**। এটা server-এ জোড়া disk নয়। তুমি নাম ধরে internet (HTTPS) দিয়ে object রাখো আর আনো।

- **Bucket:** একটা পাত্র, **বিশ্বজুড়ে অনন্য নাম**, একটা Region-এ থাকে।
- **Object:** একটা ফাইল আর তার **key**, মানে নাম/path, যেমন `uploads/2026/photo.jpg`। (S3-তে আসল folder নেই; `/` শুধু key-র অংশ।)
- প্রায় সীমাহীন জায়গা; যা রাখো আর স্থানান্তর করো তার টাকা দাও।
- AWS S3-কে **অত্যন্ত বেশি durability-র জন্য নকশা করে**, তাই object হারানোর সম্ভাবনা খুব কম। (Durability মানে backup নয়: *তুমি* কোনো ফাইল মুছলে বা ওপরে লিখলে সেটা চলে যায়। পুরনো version রাখতে **versioning** চালু করো।)

ভালো ব্যবহার: user-এর upload, ছবি, **backup**, log, static website, অন্য service-এর ফাইল।

**Server-এর নিজের disk-এ upload কেন নয়?** তোমার app কয়েকটা server-এ চললে (বা server বদলালে) প্রতিটার নিজের disk, আর একটায় upload হওয়া ফাইল অন্যগুলোয় নেই। S3 ভাগ করা storage, আর তোমার server-গুলো ফেলনা হয়ে যায়। (Kubernetes-এর শেষ project-এ তুমি একই file-upload সমস্যায় আবার পড়বে।)

### CLI থেকে S3

```bash
aws s3 mb s3://my-unique-bucket-name           # bucket বানাও
aws s3 cp photo.jpg s3://my-unique-bucket-name/uploads/photo.jpg
aws s3 ls s3://my-unique-bucket-name/uploads/
aws s3 sync ./backups s3://my-unique-bucket-name/backups   # পুরো folder কপি
```

### Bucket private রাখো

নতুন bucket default-এ **public access আটকায়**। পুরোদস্তুর public website না চালালে সেটা **চালু রাখো**। অনেক data ফাঁস আসে এমন public bucket থেকে যা কেউ প্রকাশ করতে চায়নি। কাউকে অল্প সময়ের জন্য private ফাইল download করতে দিতে **pre-signed URL** বানাও: এমন link যা কয়েক মিনিট বা ঘণ্টা চলে, তারপর মেয়াদ শেষ।

### Storage class

যে data কদাচিৎ পড়া হয় তার জন্য S3 সস্তা class দেয় (যেমন "Infrequent Access" আর Glacier-এর মতো archive class), আর **lifecycle rule** পুরনো object নিজে সরাতে বা মুছতে পারে। পুরনো backup আর log-এর জন্য কাজের।

### Laravel থেকে

Laravel S3-কে filesystem disk হিসেবে ব্যবহার করতে পারে (`league/flysystem-aws-s3-v3` package আর `.env`-এ bucket ও region-সহ `FILESYSTEM_DISK=s3` দিয়ে)। EC2-তে `.env`-এ access key রাখার বদলে instance-কে শুধু ওই bucket-এর access-সহ একটা **IAM role** দাও (Chapter 5)।

## ৩. RDS: একটা managed database

**RDS (Relational Database Service)** তোমার জন্য database চালায়: MySQL, PostgreSQL, MariaDB আর আরও। তুমি নিজে EC2 server-এ MySQL install করতে *পারো*, কিন্তু তখন backup, update, failover আর disk জায়গা **তুমি** সামলাও। RDS-এ AWS সামলায়:

- Database software install ও **patch** করা,
- **স্বয়ংক্রিয় backup** আর point-in-time recovery (কত দিন রাখবে তুমি বাছো),
- **Multi-AZ** বিকল্প: অন্য Availability Zone-এ একটা standby কপি, মূলটা বিকল হলে সে দায়িত্ব নেয়,
- monitoring আর সহজে আকার বদলানো।

**Schema, query, index আর application security** তবু তোমার। RDS engine সামলায়, তোমার নকশা নয়।

![EC2 app-এর S3 ও RDS-এর সাথে কথা বলা](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/06-cloud/images/ec2-s3-rds.svg)

Database-এর **endpoint** দিয়ে app জোড়ো, AWS-এর দেওয়া একটা hostname:

```
DB_HOST=mydb.abcdefghij12.ap-southeast-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
```

(Password রাখো secret store-এ বা এমন `.env`-এ যা Git-এ **নেই**।)

**নিরাপত্তার নিয়ম:** database পুরো internet থেকে পৌঁছানোর মতো থাকা **উচিত নয়**। এটা private রাখো আর শুধু তোমার app-এর security group-কে সংযোগ করতে দাও (Chapter 7)। বিশ্বের জন্য খোলা database কয়েক ঘণ্টায় আক্রান্ত হয়।

### Multi-AZ বনাম read replica

এ দুটো আলাদা জিনিস:

- **Multi-AZ** **availability**-র জন্য: স্বয়ংক্রিয় failover-এর standby কপি। এটা read দ্রুত করে না।
- **Read replica** **performance**-এর জন্য: বাড়তি কপি যা read query সামলে মূল database-এর চাপ কমায়।

### নিজের EC2-তে MySQL বনাম RDS

| | নিজের EC2-তে MySQL | RDS |
|---|---|---|
| Backup, patch, failover | তুমি বানাও ও রক্ষণাবেক্ষণ করো | Managed |
| খরচ | কাগজে সস্তা | মাসে বেশি; তোমার সময় ও ঝুঁকি বাঁচায় |
| নিয়ন্ত্রণ | পূর্ণ | কিছু setting সীমিত |
| ভালো | শেখা, ছোট project | যেখানে data হারালে ক্ষতি |

## ৪. কোন কাজে কোন service?

| তোমার লাগবে... | ব্যবহার |
|---|---|
| App-এর code চালাতে | **EC2** |
| Upload, ছবি, backup, log রাখতে | **S3** |
| Backup-সামলানো relational database | **RDS** |
| Server-এর দিকে নাম দেখাতে | **Route 53** (AWS-এর DNS) |
| এগুলোর মধ্যে permission | **IAM role** |

## ৫. মূল কথা

- **EC2** = virtual server (instance): AMI, instance type, key pair আর security group বাছো; `ssh -i key.pem` দিয়ে জোড়ো।
- **Stop** disk (আর তার খরচ) রাখে; **terminate** instance মুছে দেয়। স্থির address লাগলে **Elastic IP**।
- **S3** = **bucket**-এ (অনন্য নাম) object storage, folder-এর বদলে key; upload ও backup-এর জন্য আদর্শ। **Bucket private রাখো**, pre-signed URL ব্যবহার করো, জরুরি data-তে versioning চালু করো।
- **RDS** = **managed** database: AWS patch, backup, failover সামলায়; schema ও query তোমার। Private রাখো।
- **Multi-AZ** = availability; **read replica** = read performance।
- S3-তে পৌঁছাতে server-কে **IAM role** দাও, access key নয়।

## ৬. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. EC2 instance চালুর সময় তুমি কী কী বাছো?</strong></summary>

একটা AMI (OS image), একটা instance type (CPU/memory আকার), SSH-এর জন্য key pair, একটা security group (firewall), storage (EBS) আর Region/AZ।
</details>

<details>
<summary><strong>Q2. Instance stop আর terminate করার পার্থক্য কী?</strong></summary>

Stop করলে বন্ধ হয় কিন্তু disk থাকে (যার খরচ চলে) আর আবার চালু করা যায়। Terminate করলে instance আর default-এ তার disk মুছে যায়, তাই data চলে যায়।
</details>

<details>
<summary><strong>Q3. User-এর upload রাখতে web server-এর নিজের disk-এর চেয়ে S3 ভালো কেন?</strong></summary>

S3 ভাগ করা আর কোনো একটা server-এর ওপর নির্ভরশীল নয়, তাই প্রতিটা server একই ফাইল দেখে আর server বদলালেও ফাইল হারায় না।
</details>

<details>
<summary><strong>Q4. EC2-তে নিজে install করা MySQL যা করে না, RDS তোমার জন্য এমন দুটো কাজ বলো।</strong></summary>

উদাহরণ: স্বয়ংক্রিয় backup, patch, Multi-AZ failover, monitoring।
</details>

<details>
<summary><strong>Q5. Multi-AZ আর read replica-র পার্থক্য কী?</strong></summary>

Multi-AZ হলো failover-এর standby (availability)। Read replica বাড়তি read query সামলায় (performance)।
</details>

<details>
<summary><strong>Q6. EC2 app নিরাপদে একটা S3 bucket পড়ার অনুমতি পাবে কীভাবে?</strong></summary>

Access key রাখার বদলে instance-এ শুধু ওই bucket-এর অনুমতি দেওয়া একটা IAM role জোড়ো।
</details>

## ৭. বিনামূল্যের resource

- AWS: [What is Amazon EC2?](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/concepts.html)
- AWS: [Amazon S3 User Guide](https://docs.aws.amazon.com/AmazonS3/latest/userguide/Welcome.html)
- AWS: [What is Amazon RDS?](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/Welcome.html)
- AWS: [Launch a virtual machine (tutorial)](https://aws.amazon.com/getting-started/launch-a-virtual-machine-B-0/)
- Laravel: [File Storage](https://laravel.com/docs/filesystem) (S3 driver)

---

**Next:** Chapter 7 — Network, নিরাপত্তা, backup আর খরচ *(coming soon)*
