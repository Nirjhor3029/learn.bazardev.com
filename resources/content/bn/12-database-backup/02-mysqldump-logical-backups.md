# Chapter 2 — `mysqldump` সত্যিকারভাবে: Logical Backup আর Restore প্রমাণ করা

> **Course:** Database Backup & Recovery · **Chapter:** 2 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/database-backup/02-mysqldump-logical-backups)

> **Honesty note:** এই chapter-এর প্রতিটা command আর output সত্যিকারভাবে চালানো হয়েছে, এই course-এর নিজের writing server-এ, Percona MySQL 8.4.11-এর বিরুদ্ধে। সবকিছু target করে `backup_demo` — এই course-এর জন্য specifically তৈরি করা একটা throwaway database, তার নিজের scoped MySQL user সহ (`backup_demo`, privilege শুধু `backup_demo.*`-তে limited, credentials একটা private `--defaults-extra-file`-এ রাখা, কোনো git repo-র বাইরে)। এখানে কিছুই কখনো `learn_bazardev`-কে touch করে না, যেটা এই site-এর পেছনে real application database — Chapter 1-এর owner-এর standing decision-এর সাথে consistent, এই course শেখানোর side effect হিসেবে production পরিবর্তন করবে না।

Chapter 1 একটা specific লাইনে শেষ হয়েছিল: *"একটা backup যা তুমি কখনো restore করোনি, সেটা শুধু একটা আশা।"* এই chapter সেটাকে literal করে তোলে — real data তৈরি করো, `mysqldump` দিয়ে backup নাও, সত্যিকারভাবে তার একটা অংশ ধ্বংস করো, তারপর backup থেকে restore করো আর একটা row count দিয়ে প্রমাণ করো এটা আসলেই ফিরে এসেছে।

## ১. Backup করার মতো কিছু তৈরি করা

দুটো ছোট, related table — exactly একটা real app-এর shape, scale ছাড়া:

```sql
CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  placed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id)
);
```

`customers`-এ 3টা row, `orders`-এ 3টা row (একই customer-এর দুটো order, একটা real one-to-many relationship-এর মতো)। এটাই সেই data যা Section 3 এখনই "accidentally" ধ্বংস করতে যাচ্ছে।

## ২. প্রথম real dump, আর exit code একা trust না করার একটা শিক্ষা

```bash
mysqldump --defaults-extra-file=~/.my-cnf.d/backup_demo.cnf --databases backup_demo > backup_demo_full.sql
```

```
mysqldump: Error: 'Access denied; you need (at least one of) the PROCESS privilege(s) for this operation' when trying to dump tablespaces
```

```bash
echo "exit code: $?"
```

```
exit code: 0
```

এটা একটা genuinely useful, real gotcha, staged না: `mysqldump --databases` tablespace metadata capture করার চেষ্টা করে, যার জন্য `PROCESS` privilege লাগে — যা একটা properly scoped, least-privilege backup user-এর (Security course-এর Chapter 6) কাছে normally **থাকে না**, ইচ্ছাকৃতভাবে। Dump তাও সম্পূর্ণ হয়েছে আর `0` (success) exit হয়েছে, error শুধু stderr-এ। **একটা cron job যা শুধু exit code check করে, এটা সম্পূর্ণভাবে miss করবে।** একটা dump আসলে সম্পূর্ণ হয়েছে কিনা confirm করার reliable উপায় হলো প্রতিটা successful `mysqldump` file-এর শেষে যে trailer লাইন লেখে সেটা:

```bash
tail -1 backup_demo_full.sql
```

```
-- Dump completed on 2026-11-20 11:21:06
```

Chapter 3 এটার চারপাশে একটা real automation script তৈরি করে — শুধু `$?` না, exactly এই লাইন check করে।

## ৩. `--databases` বনাম plain dump: একটা choice যা ঠিক করে তুমি কীভাবে restore করতে পারবে

```bash
grep -n "CREATE DATABASE\|^USE" backup_demo_full.sql
```

```
31:CREATE DATABASE /*!32312 IF NOT EXISTS*/ `backup_demo` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
33:USE `backup_demo`;
```

`mysqldump --databases backup_demo` file-এ একটা `CREATE DATABASE` আর `USE` statement bake করে দেয় — এটা restore করলে (`mysql < backup_demo_full.sql`) সবসময় exactly `backup_demo` নামের একটা database recreate আর target করবে, যেখানেই চালাও। এটা disaster recovery-র জন্য convenient (একটা identically-named fresh server-এ restore) কিন্তু এর মানে তুমি **সহজে** একই file কোনো আলাদা নামের database-এ point করে safely restore test করতে পারবে না — যা exactly Section 4-এর লাগবে।

`--databases` সরিয়ে দিলে এই দুটো লাইন সম্পূর্ণ সরে যায় — file টা হয়ে যায় `CREATE TABLE` / `INSERT` statement-এর একটা portable set, কোনো database নাম bake করা নেই, command line-এ তুমি যেকোনো target নাম দিয়ে restore করতে পারবে:

```bash
mysqldump --defaults-extra-file=~/.my-cnf.d/backup_demo.cnf backup_demo > backup_demo_tables.sql
grep -c "CREATE DATABASE\|^USE" backup_demo_tables.sql
```

```
0
```

## ৪. Disaster simulate করা, সত্যিকারভাবে

```bash
mysql --defaults-extra-file=~/.my-cnf.d/backup_demo.cnf backup_demo -e "DROP TABLE orders;"
mysql --defaults-extra-file=~/.my-cnf.d/backup_demo.cnf backup_demo -e "SHOW TABLES;"
```

```
Tables_in_backup_demo
customers
```

`orders` সত্যিকারভাবে এখন `backup_demo` থেকে চলে গেছে। এটাই Chapter 1 Section 1-এর "human error" category বাস্তবে: কোনো `WHERE` clause typo লাগেনি, শুধু একটা direct `DROP TABLE` — একটা real backup আসলে use হওয়ার সবচেয়ে common কারণ।

![mysqldump restore-test cycle: data তৈরি, dump, একটা table ধ্বংস, একটা scratch database-এ restore, row count verify](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/12-database-backup/images/mysqldump-restore-cycle.svg)

## ৫. একটা আলাদা scratch database-এ restore করা — আর প্রমাণ করা এটা আসলেই কাজ করেছে

একটা *restore test*-এর পুরো point হলো এমন জায়গায় recover করা যা live database না, যাতে original-কে আরও risk না করে correctness verify করতে পারো। ঠিক একইভাবে scoped একটা দ্বিতীয় database exactly এই কাজের জন্য:

```bash
sudo mysql -e "CREATE DATABASE backup_demo_restore_test; GRANT ALL PRIVILEGES ON backup_demo_restore_test.* TO 'backup_demo'@'localhost'; FLUSH PRIVILEGES;"
```

এখন **pre-disaster** dump restore করো — Section 2-এ নেওয়া, `orders` drop হওয়ার আগে — ওই scratch database-এ, target explicitly নাম দিয়ে (এটা সম্ভব শুধু কারণ Section 3 `--databases` ছাড়া একটা plain dump ব্যবহার করেছিল):

```bash
mysql --defaults-extra-file=~/.my-cnf.d/backup_demo.cnf backup_demo_restore_test < backup_demo_full_notarget.sql
```

```bash
mysql --defaults-extra-file=~/.my-cnf.d/backup_demo.cnf backup_demo_restore_test -e "SHOW TABLES; SELECT COUNT(*) AS customers FROM customers; SELECT COUNT(*) AS orders FROM orders;"
```

```
Tables_in_backup_demo_restore_test
customers
orders
customers
3
orders
3
```

`orders` — মুহূর্ত আগে original `backup_demo` থেকে drop হওয়া — scratch database-এ ফিরে এসেছে, সব 3টা row সহ। এটা কোনো claim না; এটা একটা row count যা কয়েক command আগে exist করত না। Backup কাজ করেছে, আর এখন একটা আশার বদলে একটা প্রমাণ আছে।

```bash
mysql --defaults-extra-file=~/.my-cnf.d/backup_demo.cnf backup_demo_restore_test -e "SELECT * FROM orders;"
```

```
id	customer_id	amount	placed_at
1	1	499.00	2026-11-20 11:20:32
2	2	1250.50	2026-11-20 11:20:32
3	1	75.00	2026-11-20 11:20:32
```

Exact amount, exact timestamp, exact foreign key `customer_id`-এ — একটা real restore, কোনো reconstruction না।

## ৬. Practice-এ যে option গুলো matter করে

আরও কিছু `mysqldump` flag যা deliberately জানা উচিত, উপরের default-এর বাইরে:

- **`--single-transaction`** — InnoDB table-এর উপর পুরো dump-টাকে একটা transaction-এ wrap করে, তাই এখনও write পাচ্ছে এমন একটা database-এর dump একটা নির্দিষ্ট সময়ের হিসেবে consistent থাকে, একটা concurrent write-এর আগে আর পরের row মিশে না গিয়ে। প্রায় সব production database-এর জন্যই এটা essential, যা idle বসে নেই।
- **`--routines --triggers --events`** — plain `mysqldump` default-ভাবে stored procedure, trigger, বা scheduled event capture করে **না**; তোমার schema যদি এগুলোর কোনোটা ব্যবহার করে, explicitly এগুলো add করো।
- **`--no-data`** — শুধু table structure dump করে (`CREATE TABLE` statement), কোনো row ছাড়া — একটা fresh environment setup করার জন্য শুধু schema capture করতে useful।
- **Output compress করা** — `mysqldump ... | gzip > backup.sql.gz` dump-টাকে সরাসরি `gzip`-এ pipe করে, text-heavy SQL file-কে অনেকটা ছোট করে ফেলে, uncompressed version-এর জন্য আলাদা disk round-trip ছাড়াই।

## ৭. মূল শিক্ষা

- একটা `mysqldump` export `0` exit করতে পারে যদিও stderr একটা real problem report করছে (এখানে, tablespace metadata-এর উপর একটা `PROCESS` privilege error) — একটা dump আসলে শেষ হয়েছে confirm করতে `-- Dump completed on ...` trailer লাইন check করো, শুধু exit code না।
- `mysqldump --databases` `CREATE DATABASE`/`USE` bake করে দেয়, সবসময় exact ওই database নামে restore করে; একটা plain `mysqldump dbname` dump-এ কোনো database নাম bake করা নেই আর তুমি যেকোনো target-এ restore করতে পারো যা তুমি specify করো।
- একটা restore **test** মানে একটা আলাদা database-এ restore করা, original-এ না — এটাই আসলে প্রমাণ করে backup কাজ করে, live data আবার risk না করে।
- `--single-transaction` এখনও write পাচ্ছে এমন কোনো database-এ almost mandatory, যাতে একটা dump concurrent change-এর আগে আর পরের data না মিশিয়ে ফেলে।
- Plain `mysqldump` default-ভাবে routine, trigger, আর event skip করে — তোমার schema এগুলো ব্যবহার করলে explicitly `--routines --triggers --events` add করো।

## ৮. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. এই chapter-এর প্রথম mysqldump run stderr-এ একটা error print করলেও 0 exit করেছে। সেই error কী ছিল, আর এটা কেন matter করে?</strong></summary>

Tablespace metadata dump করার সময় একটা "PROCESS privilege" error, কারণ scoped backup_demo user-এর কাছে ইচ্ছাকৃতভাবে ওই elevated privilege নেই। এটা matter করে কারণ একটা script যা শুধু exit code check করে সেটাকে একটা সম্পূর্ণ successful backup ধরে নিবে আর warning-টা সম্পূর্ণ miss করবে।
</details>

<details>
<summary><strong>Q2. একটা mysqldump আসলে সম্পূর্ণ হয়েছে confirm করার reliable উপায় কী, exit code-এর বাইরে?</strong></summary>

একটা successful dump file-এর শেষে mysqldump যে "-- Dump completed on ..." trailer লাইন লেখে সেটা check করো — এর থাকা (বা না থাকা, একটা truncated/failed dump-এ) শুধু exit code-এর চেয়ে ভালো signal।
</details>

<details>
<summary><strong>Q3. mysqldump --databases output-এ কী extra add করে যা একটা plain mysqldump dbname dump করে না?</strong></summary>

একটা CREATE DATABASE statement আর একটা USE statement যা dump কোন database থেকে এসেছে exact নাম বলে দেয়, তাই এটা restore করলে সবসময় সেই একই নামের একটা database recreate আর target হয়।
</details>

<details>
<summary><strong>Q4. এই chapter restore test demonstrate করতে কেন একটা plain dump (--databases ছাড়া) ব্যবহার করেছে?</strong></summary>

একটা plain dump-এ কোনো database নাম bake করা নেই, তাই এটা command line-এ নাম দেওয়া যেকোনো target database-এ restore করা যায় — এখানে, একটা আলাদা backup_demo_restore_test scratch database-এ, original backup_demo নামে ফিরে যাওয়ার বদলে।
</details>

<details>
<summary><strong>Q5. একটা আলাদা scratch database-এ restore করা শুধু restore command successfully চলার চেয়ে বেশি কী প্রমাণ করে?</strong></summary>

একটা আলাদা database-এ restore করলে তুমি actual data (row count, specific value) verify করতে পারো original touch বা আবার risk না করেই — "command error দেয়নি" আর "data আসলে correct আর complete" আলাদা claim, আর শুধু দ্বিতীয়টাই backup আসলে কাজ করে তার real প্রমাণ।
</details>

<details>
<summary><strong>Q6. --single-transaction কোন problem solve করে, আর কোন table-এর জন্য এটা apply হয়?</strong></summary>

InnoDB table-এ, এটা পুরো dump-টাকে একটা single transaction-এ wrap করে যাতে backup একটা নির্দিষ্ট সময়ের হিসেবে consistent থাকে, database এখনও write পেলেও — এটা ছাড়া, একটা live database-এর বিরুদ্ধে একটা long-running dump একটা concurrent write-এর আগে আর পরের row মিশিয়ে ফেলতে পারে।
</details>

<details>
<summary><strong>Q7. Plain mysqldump কি default-ভাবে stored procedure আর trigger capture করে?</strong></summary>

না — routine, trigger, আর event সবই skip হয় যতক্ষণ না তুমি explicitly --routines, --triggers, আর --events add করো। যে schema এগুলোর কোনোটা ব্যবহার করে, তার এই flag গুলো লাগবে, না হলে একটা restore নিঃশব্দে এগুলো miss করবে।
</details>

<details>
<summary><strong>Q8. এই chapter-এর প্রতিটা command কেন backup_demo নামের একটা database target করে, learn_bazardev না — এই site-এর পেছনের real application database?</strong></summary>

Owner-এর একটা standing decision আছে যে এই course শেখানোর side effect হিসেবে production পরিবর্তন করবে না। backup_demo এই course-এর জন্য specifically তৈরি করা একটা throwaway database, তার নিজের scoped MySQL user সহ শুধু backup_demo.*-তে limited, তাই এখানে প্রতিটা demo real হলেও live site-এর জন্য কোনো risk নেই।
</details>

## ৯. Free Resources

- MySQL 8.4 Reference Manual: [`mysqldump` — A Database Backup Program](https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html)
- MySQL 8.4 Reference Manual: [`mysqldump` Command-Line Options](https://dev.mysql.com/doc/refman/8.4/en/mysqldump-option-summary.html)
- Percona: [A Brief Guide to MySQL Backups](https://www.percona.com/blog/a-brief-guide-to-mysql-backups/)

---

**এর পর:** [Chapter 3 — Backup automate করা: cron, retention, আর rotation](03-automating-backups-cron-and-retention.md)
