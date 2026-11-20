# Chapter 1 — কেন Backup লাগে: RPO, RTO, আর Strategy বেছে নেওয়া

> **Course:** Database Backup & Recovery · **Chapter:** 1 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/database-backup/01-why-backups-rpo-and-rto)

> **Honesty note:** এই chapter concept নিয়ে, কোনো command চালানো হয়নি এখনো। Chapter 2 থেকে শুরু করে, এই course সত্যিকারের `mysqldump` আর restore command চালাবে — এই course-এর নিজের writing server-এ genuinely install করা Percona MySQL 8.4-এর বিরুদ্ধে। ওই প্রতিটা command targeted করবে একটা **throwaway demo database, যেটা সেই chapter-এর ভেতরেই তৈরি হয়ে আবার drop হয়ে যাবে** — কখনো আসল `learn.bazardev.com` application database-এর বিরুদ্ধে না। Owner-এর আগে থেকেই একটা explicit decision record-এ আছে যে production database-এ real backup system বসানো হবে না এই course লেখার side effect হিসেবে — আর এই course accident-এ না, design করেই সেটা মেনে চলছে।

Cloud course (Chapter 7) আগেই RPO আর RTO পরিচয় করিয়ে দিয়েছে managed cloud infrastructure-এর লেভেলে — "RDS-এ automated backup আছে, একটা button চাপো।" এই course আরেক লেভেল গভীরে যায়: তুমি সবসময় managed cloud-এ থাকবে না, আর একটা plain Linux VPS-এ self-managed MySQL/Percona install তোমাকে এর কিছুই ফ্রি-তে দেয় না। কেউ তোমার জন্য `mysqldump` schedule করে দেয় না। কেউ তোমার restore test করে না। এই chapter শুরু হচ্ছে একটা backup আসলে কী থেকে রক্ষা করে সেটা precise ভাবে বুঝিয়ে, Chapter 2 কোনো command touch করার আগেই।

## ১. একটা backup কী থেকে রক্ষা করে — আর কী থেকে করে না

একটা surprising সংখ্যক "আমাদের backup আছে" setup আসলে ভুল জিনিস থেকে রক্ষা করছে। চারটা আলাদা failure mode, প্রতিটার জন্য আলাদা উত্তর লাগে:

- **Hardware failure** — একটা disk মারা যায়, একটা VPS host-এর outage হয়। Multi-AZ, RAID, আর তোমার cloud provider-এর underlying storage replication — এগুলো সবই এখানে help করে। **এদের কোনোটাই backup না।**
- **Human error** — কেউ ভুল `UPDATE` চালায় `WHERE` clause ছাড়া, ভুল table drop করে, বা একটা deploy script এমন table truncate করে যা করা উচিত ছিল না। Real backup use হওয়ার সবচেয়ে common কারণ এটাই।
- **Application বা deploy bug** — একটা migration নিঃশব্দে data corrupt করে, একটা bug ভালো row-কে খারাপ row দিয়ে overwrite করে। দেরিতে ধরা পড়লে, এটা তখন ইতিমধ্যে প্রতিটা replica-তে বেক হয়ে গেছে।
- **Compromise বা ransomware** — একটা attacker (বা compromised account) ইচ্ছাকৃতভাবে data delete বা encrypt করে। একটা backup এখানে তখনই help করে যদি সেটা **এমন জায়গায় store করা থাকে যেখানে attacker-এর access-ও পৌঁছাতে আর delete করতে পারে না** — এটা নিয়ে আরও বিস্তারিত Chapter 6-এ (offsite storage)।

Multi-AZ আর RAID কেন count হয় না তার কারণ: উপরের তিনটা non-hardware case-এই, *খারাপ write-টা নিজেই* ঠিকমতো copy হয়ে যায় প্রতিটা replica আর RAID array-এর প্রতিটা disk-এ। Redundancy একটা disk fail হওয়া থেকে বাঁচায়; একটা mistake-এর বিরুদ্ধে এটা কিছুই করে না, কারণ mistake হার্ডওয়ারের ভুল না — এটা data, ঠিকমতো লেখা হয়েছে, যেটা শুধু ভুল হয়ে গেছে।

## ২. RPO আর RTO, concrete ভাবে

এই দুটো সংখ্যাই পুরো backup strategy design conversation — বাকি সবকিছু (কোন tool, কতবার, কোথায় store হবে) শুধু implementation detail যা এই দুটো satisfy করে।

- **RPO (Recovery Point Objective) — কতটুকু data loss acceptable?** তোমার RPO যদি "1 ঘণ্টা" হয়, তোমার সবচেয়ে সাম্প্রতিক usable backup (বা backup + log-এর combination, Chapter 5) disaster strike করার মুহূর্তে কখনো 1 ঘণ্টার বেশি পুরানো হওয়া চলবে না। একটা শুধু-রাতের backup schedule তোমাকে up to 24 ঘণ্টার RPO দেয়, তুমি চাও বা না চাও।
- **RTO (Recovery Time Objective) — কতক্ষণ down থাকা acceptable restore করার সময়?** তোমার RTO যদি "30 মিনিট" হয়, actual restore process — ঠিক backup খুঁজে পাওয়া, সেটা চালানো, app ঠিকমতো কাজ করছে কিনা verify করা — সবকিছু 30 মিনিটের মধ্যে শেষ হতে হবে। একটা 40 GB `mysqldump` restore করতে 3 ঘণ্টা লাগলে, সেটাই তোমার RTO, কোথাও লেখা থাকুক আর না থাকুক।

এই দুটো সংখ্যার কোনোটাই আসলে technical choice না — এটা একটা business/product উত্তর infrastructure requirement-এর পোশাক পরে আছে। "আমরা এক দিনের order loss afford করতে পারি" আর "এক মিনিটের order loss-ও afford করতে পারি না" — একই MySQL engine-এর জন্য সম্পূর্ণ আলাদা backup strategy তৈরি করে।

![RPO আর RTO একটা timeline-এ, আর একটা backup strategy প্রতিটা axis-এ map করা](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/12-database-backup/images/rpo-rto-timeline.svg)

## ৩. Logical vs. physical backup — প্রথম real decision

প্রতিটা MySQL backup tool আসলে এই দুটো approach-এর কোনো একটার variation — আর এই পার্থক্য RPO আর RTO দুটোর জন্যই গুরুত্বপূর্ণ:

- **Logical backup** (Chapter 2-এর `mysqldump` এর classic example) data-কে SQL statement হিসেবে export করে (`CREATE TABLE`, `INSERT`) — human-readable, MySQL version-এর মধ্যে এমনকি অন্য engine-এও portable, আর একটা মাত্র table restore করা সহজ। খরচ: বড় database-এ SQL statement generate আর row-by-row replay করা slow, তাই data বাড়লে RTO খারাপ হতে থাকে।
- **Physical backup** (Chapter 4-এর XtraBackup এর classic example) MySQL যে actual data file disk-এ রাখে সেগুলো copy করে। বড় database-এ backup আর restore দুটোই অনেক faster, কারণ কোনো row-by-row SQL generate বা replay করার দরকার নেই — কিন্তু backup একই MySQL version আর storage engine-এর সাথে বাঁধা, আর তুমি সহজে "শুধু একটা table" আলাদা করে নিতে পারবে না।

কোনোটাই strictly ভালো না — এগুলো size-এর বিরুদ্ধে আলাদাভাবে trade off করে। একটা 50 MB database হয় logical dump থেকে সেকেন্ডে restore হয়ে যায় — এখানে পার্থক্য খুব কম matter করে। একটা 500 GB database-এ logical restore-এর RTO "overnight"-এ পরিবর্তন হতে পারে, যেটা exactly সেই রকম gap যা কাগজের একটা সংখ্যা hide করে রাখে, যতক্ষণ না তুমি আসলে সেটা test করো (Chapter 2-এর পুরো point এটাই)।

## ৪. Full, incremental, আর differential — storage বনাম RTO trade off

Logical-vs-physical ছাড়াও, backup আলাদা হয় *প্রতিবার কতটুকু* capture করে তার ভিত্তিতে:

- **Full backup** — সবকিছু, প্রতিবার। Restore করা সবচেয়ে simple (একটা file, একটা step) কিন্তু produce করতে সবচেয়ে বেশি storage আর সময় লাগে।
- **Incremental backup** — *যে কোনো ধরনের শেষ backup*-এর পর থেকে যা পরিবর্তন হয়েছে শুধু তাই। Produce করতে সবচেয়ে ছোট আর fastest, কিন্তু restore করতে হলে পুরো full backup প্লাস তার পর থেকে প্রতিটা incremental, সঠিক order-এ replay করতে হবে — বেশি moving part, chain-এর কোনো একটা link missing হলে RTO balloon হওয়ার বেশি উপায়।
- **Differential backup** — *শেষ full backup*-এর পর থেকে যা পরিবর্তন হয়েছে শুধু তাই। একটা মাঝামাঝি option: incremental-এর চেয়ে বড়, কিন্তু restore করতে সবসময় শুধু full backup প্লাস একটা differential লাগবে, পুরো chain না।

একটা common real pattern: সপ্তাহে একবার full backup, মাঝে daily incremental। এটা storage আর backup সময় কম রাখে, আর restore chain-টা যথেষ্ট ছোট রাখে যাতে সেটা আসলে trust করা যায়।

## ৫. 3-2-1 Rule

একটা সহজ, মনে রাখার মতো rule যা backup strategy নিঃশব্দে fail হওয়ার বেশিরভাগ উপায় ধরে ফেলে: তোমার data-এর **3**টা copy রাখো, **2** টা আলাদা storage media-এ, আর তার **1**টা copy offsite রাখো (এমন জায়গায় যেখানে একটা local disaster — আগুন, চুরি, একটা single compromised VPS — পৌঁছাতে পারে না)।

একটা typical self-managed MySQL setup-এ apply করলে: live database copy 1; একই server-এর disk-এ লেখা একটা `mysqldump` copy 2, কিন্তু **একাই "offsite" test fail করে** — VPS disk fail হলে বা পুরো VPS compromise হলে, সেই backup-ও একসাথে মারা যায়। Chapter 6 covers করে সত্যিকারের একটা copy server থেকে বের করে পাঠানো, যেটা "আমার একটা backup আছে"-কে "আমার এমন একটা backup আছে যা আসলে যে disaster নিয়ে আমি worried সেটা survive করে"-তে পরিবর্তন করে।

## ৬. এই course chapter ধরে কী কভার করে

- **Chapter 2** — `mysqldump` সত্যিকারভাবে: syntax, যে option গুলো matter করে, আর একটা scratch database-এ restore করে আসলে প্রমাণ করা backup কাজ করে।
- **Chapter 3** — এটা automate করা: cron, retention policy, পুরানো backup rotate করা যাতে disk নিঃশব্দে ফুরিয়ে না যায়।
- **Chapter 4** — XtraBackup দিয়ে physical backup, আর `mysqldump`-এর উপর extra setup worth হয় কখন।
- **Chapter 5** — Point-in-time recovery: একটা base backup আর binary log combine করে একটা খারাপ query চালানোর ঠিক আগের *exact সেকেন্ডে* restore করা, শুধু গত রাতে না।
- **Chapter 6** — Offsite storage: একটা dump encrypt করা আর আসলে সেটা server থেকে বের করা, 3-2-1-এর "1" satisfy করা।
- **Chapter 7** — Replication basics, আর precise কারণ কেন একটা replica backup-এর substitute না (Section 1-এর "খারাপ write-ও faithfully copy হয়" replica-র জন্যও apply হয়)।
- **Chapter 8** — একটা real project: একটা Laravel app-এর database-এর throwaway copy-র বিরুদ্ধে শুরু থেকে শেষ পর্যন্ত backup-and-restore drill, restore-টা আসলে time করা সহ।

## ৭. মূল শিক্ষা

- একটা backup human error, application bug, আর compromise থেকে রক্ষা করে — শুধু hardware failure থেকে না। Multi-AZ, RAID, আর replication backup হিসেবে count হয় না, কারণ এগুলো খারাপ write-ও faithfully copy করে।
- **RPO** কতটুকু data loss tolerable (backup frequency ঠিক করে); **RTO** কতক্ষণ downtime tolerable (কোন tool আর process use করবে সেটা shape করে)। দুটোই একটা deliberate উত্তর হওয়া উচিত, যে schedule accidentally তৈরি হয়ে গেছে তার ফলাফল না।
- **Logical** backup (`mysqldump`) portable আর table-restorable কিন্তু scale-এ slower; **physical** backup (XtraBackup) scale-এ fast কিন্তু একই MySQL version-এর সাথে বাঁধা।
- **Full / incremental / differential** backup storage আর সময় trade off করে restore-এ কতগুলো piece সঠিকভাবে chain করতে হবে তার বিরুদ্ধে।
- **3-2-1**: তিনটা copy, দুই storage type, একটা offsite — একটা backup যেটা যে server-কে protect করছে সেটা থেকে কখনো বের হয় না, সেটা সবচেয়ে বেশি matter করা rule-টাই fail করে।

## ৮. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. RAID বা Multi-AZ কেন backup হিসেবে count হয় না?</strong></summary>

এগুলো redundant copy sync রেখে hardware failure থেকে রক্ষা করে, কিন্তু একটা খারাপ write — একটা ভুল `UPDATE`, একটা drop হওয়া table, একটা malicious deletion — প্রতিটা redundant copy-তেও faithfully copy হয়ে যায়। Redundancy একটা disk মারা যাওয়া থেকে survive করে; একটা mistake-এর বিরুদ্ধে এটা কিছুই করে না, কারণ mistake-টা সঠিকভাবে-লেখা data, যা শুধু ভুল হয়ে গেছে।
</details>

<details>
<summary><strong>Q2. নিজের ভাষায় RPO আর RTO define করো, প্রতিটা কোন backup decision drive করে তা সহ।</strong></summary>

RPO (Recovery Point Objective) কতটুকু data loss acceptable — এটা backup *কতবার* করতে হবে তা drive করে। RTO (Recovery Time Objective) restore করার সময় কতটুকু downtime acceptable — এটা *কোন tool আর process* use করবে তা drive করে, কারণ একটা correct-কিন্তু-slow restore তার target miss করতে পারে।
</details>

<details>
<summary><strong>Q3. Logical আর physical backup-এর মধ্যে practical পার্থক্য কী, আর সেই পার্থক্য কখন matter করা শুরু করে?</strong></summary>

Logical backup (যেমন `mysqldump`) data-কে replayable SQL হিসেবে export করে — portable আর table-restorable, কিন্তু scale-এ generate আর restore করতে slow। Physical backup (যেমন XtraBackup) MySQL-এর actual data file copy করে — যে কোনো scale-এ fast, কিন্তু একই MySQL version আর storage engine-এর সাথে বাঁধা। একটা ছোট database-এ পার্থক্য খুব কম matter করে, একটা বড় database-এ এটা RTO-কে dominate করতে পারে।
</details>

<details>
<summary><strong>Q4. একটা incremental backup chain কেন একটা differential chain-এর চেয়ে restore করতে বেশি risky হতে পারে, এটা produce করতে cheaper হলেও?</strong></summary>

Incremental থেকে restore করতে হলে পুরো full backup প্লাস তার পর থেকে প্রতিটা incremental, সঠিক order-এ replay করতে হয় — একটা মাত্র missing বা corrupt link পুরো chain ভেঙে দেয়। একটা differential backup-এ সবসময় শুধু full backup প্লাস একটা differential লাগে, তাই restore fail হওয়ার জায়গা কম।
</details>

<details>
<summary><strong>Q5. একটা team রাতে `mysqldump` দিয়ে backup নেয় আর dump সেই একই server-এর disk-এ store করে। এটা 3-2-1 rule-এর কোন অংশ fail করে, আর কেন?</strong></summary>

এটা "1 offsite" requirement fail করে (আর setup-এর উপর depend করে "2 storage type" requirement-ও হয়তো) — backup ঠিক সেই একই disk-এ থাকে যেখানে live database আছে, তাই একটা disk failure, VPS loss, বা সেই একটা server compromise হলে data আর সেটা recover করার জন্য যে backup, দুটোই destroy হয়।
</details>

<details>
<summary><strong>Q6. শুধু-রাতের backup schedule কী RPO দেয়, কেউ সেই সংখ্যা কোথাও লিখে না রাখলেও?</strong></summary>

24 ঘণ্টা পর্যন্ত — পরের scheduled backup-এর ঠিক আগে disaster strike করলে, শেষ backup-এর পর থেকে যা লেখা হয়েছে সবকিছু হারিয়ে যায়। RPO actual schedule-এর একটা property, কারো intention-এর না।
</details>

<details>
<summary><strong>Q7. "আমাদের backup আছে" কেন একটা complete claim না, যতক্ষণ না এটাও answer করা হয় এটা কী থেকে রক্ষা করে?</strong></summary>

যে backup শুধু hardware failure-এর বিরুদ্ধে guard করে (যেমন শুধু RAID বা Multi-AZ-এর উপর নির্ভর করা) human error, bad deploy, আর compromise সম্পূর্ণভাবে uncovered রেখে দেয় — Section 1-এর চারটা failure mode প্রতিটা আলাদাভাবে consider করতে হবে, আর একটা strategy যা শুধু একটা cover করে সেটা অন্য একটার কাছে সবকিছু হারাতে পারে।
</details>

<details>
<summary><strong>Q8. এই course কেন deliberately তার কোনো real backup command learn.bazardev.com-এর actual application database-এর বিরুদ্ধে চালায় না?</strong></summary>

Owner-এর আগে থেকেই একটা decision record-এ আছে যে production database-এ real backup system বসানো হবে না। এই course-এর প্রতিটা hands-on command একটা throwaway demo database-এর বিরুদ্ধে চালানো হয়, যা সেই chapter-এর ভেতরেই তৈরি হয়ে drop হয়ে যায়, তাই course real mechanics শেখায় production-কে silently পরিবর্তন না করেই।
</details>

## ৯. Free Resources

- MySQL 8.4 Reference Manual: [Backup and Recovery](https://dev.mysql.com/doc/refman/8.4/en/backup-and-recovery.html)
- Percona: [A Brief Guide to MySQL Backups](https://www.percona.com/blog/a-brief-guide-to-mysql-backups/)
- AWS: [Recovery Point Objective and Recovery Time Objective](https://aws.amazon.com/what-is/recovery-time-objective/)

---

**এর পর:** [Chapter 2 — `mysqldump` সত্যিকারভাবে: Logical backup আর restore প্রমাণ করা](02-mysqldump-logical-backups.md)
