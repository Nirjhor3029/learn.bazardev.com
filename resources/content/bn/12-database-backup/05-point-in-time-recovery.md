# Chapter 5 — Point-in-Time Recovery: Binary Log প্লাস একটা Base Backup

> **Course:** Database Backup & Recovery · **Chapter:** 5 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/database-backup/05-point-in-time-recovery)

> **Honesty note:** এই recovery-র জন্য exact binary log event extract করা পর্যন্ত প্রতিটা step genuinely `backup_demo`-র বিরুদ্ধে এই course-এর নিজের writing server-এ চালানো হয়েছে: একটা real base backup, backup-এর আগে আর পরে real binlog position পড়া, একটা real disaster (`DELETE FROM orders WHERE 1=1`), আর backup আর disaster-এর মধ্যের event-ই শুধু extract হয়েছে কিনা তার একটা real, verified প্রমাণ। যে step **live সম্পূর্ণ করা হয়নি**: এই extracted event গুলো actually apply করতে একটা real MySQL privilege wall-এ লেগেছে (`SUPER`/`REPLICATION_APPLIER`, Section 5), যা এই chapter-এর scoped backup account-এর ইচ্ছাকৃতভাবে নেই — আর এই walkthrough deliberately সেই wall-এর চারপাশে escalate করেনি, কারণ এটা আসলে একটা genuinely ভালো security property, শুধু একটা অসুবিধা না (Section 5 explain করে কেন)। তার আগে পর্যন্ত সবকিছু real, verified output, কোনো hypothetical না।

Chapter 2 একটা backup নেওয়ার মুহূর্তে restore করেছিল। Chapter 4 scale-এ faster restore করেছিল। কোনোটাই একটা খুব specific, খুব common real question-এর উত্তর দেয় না: *"এখন 2:47 PM, কেউ 2:41 PM-এ একটা ভুল query চালিয়েছে, আর শেষ backup ছিল 2:00 AM-এ — mistake বাদে বাকি সব কি ফিরিয়ে আনা যায়?"* Point-in-time recovery-ই উত্তর: একটা base backup, প্লাস তার পর থেকে প্রতিটা binary log event, exactly damage হওয়ার ঠিক আগ পর্যন্ত replay করা।

## ১. Binary log: puzzle-এর বাকি অর্ধেক

MySQL-এর binary log (binlog) data পরিবর্তন করে এমন প্রতিটা change order অনুযায়ী record করে, backup-এর থেকে আলাদা একটা durable log-এ। এই server-এ এটা already on, read-only confirm করা:

```bash
sudo mysql -e "SHOW VARIABLES LIKE 'log_bin'; SHOW VARIABLES LIKE 'binlog_format';"
```

```
log_bin	ON
binlog_format	ROW
```

`ROW` format actual row change record করে (যে SQL text এটা causes করেছে তা না) — exactly replay করতে বেশি reliable, যদিও binlog event plain, human-readable SQL না (Section 4 সরাসরি এটা deal করে)। একটা base backup একটা instant-এ একটা snapshot capture করে; binlog সেই instant *থেকে* যা কিছু ঘটেছে সবকিছু — দুটো মিলে কোনো দুটো backup-এর মধ্যে যেকোনো point-এ database reconstruct করতে পারে, শুধু backup গুলোতেই না।

## ২. Base backup নেওয়া, আর exactly এটা কোথায় আছে record করা

```bash
mysql ... -e "SHOW BINARY LOG STATUS;"
```

```
File		Position
binlog.000020	24636
```

(MySQL 8.4-এ, `SHOW MASTER STATUS` — পুরানো command নাম — সরিয়ে `SHOW BINARY LOG STATUS` করা হয়েছে, একটা বড় source/replica terminology change-এর অংশ যা Chapter 7-এ আবার relevant হবে।)

```bash
mysqldump --single-transaction --no-tablespaces backup_demo > pitr_base_backup.sql
```

```
-- Dump completed on 2026-11-20 18:09:12
```

এই idle demo database-এ কোনো concurrent write না থাকায়, dump-এর ঠিক পরেই binlog position অপরিবর্তিত — `binlog.000020`, position `24636`। এই pair — backup file, আর এটা নেওয়ার মুহূর্তের exact binlog coordinate — এই chapter-এর বাকি সবকিছু যেখান থেকে measure করা হয় সেই anchor।

## ৩. Normal activity, তারপর একটা real disaster, দুটোই logged

```bash
mysql ... backup_demo -e "
INSERT INTO orders (customer_id, amount) VALUES (2, 310.00);
INSERT INTO orders (customer_id, amount) VALUES (1, 55.25);
UPDATE customers SET email='karim.hasan@example.com' WHERE id=2;
"
```

Disaster-এর ঠিক আগে, binlog position আবার record করা হয় — এটাই সেই exact point যা এই chapter recover করবে:

```bash
mysql ... -e "SHOW BINARY LOG STATUS;"
```

```
File		Position
binlog.000020	25678
```

তারপর, একটা real mistake:

```bash
mysql ... backup_demo -e "DELETE FROM orders WHERE 1=1;"
mysql ... backup_demo -e "SELECT COUNT(*) FROM orders;"
```

```
COUNT(*)
0
```

প্রতিটা order, genuinely চলে গেছে। এটা Chapter 1-এর "human error" category আরেকবার real-এ ঘটা — আর exactly point-in-time recovery কী কারণে exist করে তার উদাহরণ: "2:00 AM-এ restore করো" না, "2:46:59 PM-এ restore করো," `DELETE`-এর এক সেকেন্ড আগে।

![Base backup position 24636-তে, PITR target 25678-তে, disaster 26084-তে - replay DELETE-এর ঠিক আগে থামে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/12-database-backup/images/point-in-time-recovery.svg)

## ৪. Exactly যা চাও সেটাই extract করা, আর প্রমাণ করা

`mysqlbinlog` সরাসরি server-এর binlog file পড়ে (`/var/lib/mysql`-এ filesystem access লাগে, তাই এখানে `sudo`) আর position দিয়ে একটা range extract করতে পারে:

```bash
sudo mysqlbinlog -v --start-position=24636 --stop-position=25678 /var/lib/mysql/binlog.000020 > pitr_replay_verbose.sql
grep -E "^### (INSERT|UPDATE|DELETE)" pitr_replay_verbose.sql
```

```
### INSERT INTO `backup_demo`.`orders`
### INSERT INTO `backup_demo`.`orders`
### UPDATE `backup_demo`.`customers`
```

এটা real, checkable প্রমাণ যে extraction window exactly সঠিক: দুটো insert আর একটা update — Section 3-এর normal activity — আর, গুরুত্বপূর্ণভাবে, **zero `DELETE` statement**। `-v` flag `ROW`-format binlog event decode করে এই readable `###`-prefixed comment-এ, purely human inspection-এর জন্য; file-এর actual, replayable content নিচে তাও row-image `BINLOG '...'` statement যা MySQL নিজেই বোঝে (Section 5-এ exactly কেন এই পার্থক্য matter করে বিস্তারিত)।

Base backup restore করলে replay-এর আগের starting point confirm হয়:

```bash
mysql ... backup_demo_pitr_test < pitr_base_backup.sql
mysql ... backup_demo_pitr_test -e "SELECT COUNT(*) FROM orders; SELECT email FROM customers WHERE id=2;"
```

```
COUNT(*)
4
email
karim@example.com
```

চারটা order (backup-এর pre-disaster count), পুরানো email — exactly base backup-এর state, extracted event layer করার জন্য ready।

## ৫. এই chapter যে wall-এ লেগেছে, honestly, আর কেন এটা কোনো bug না

Extracted file apply করলে একটা real MySQL privilege requirement-এ লাগে:

```bash
mysql ... backup_demo_pitr_test < pitr_replay.sql
```

```
ERROR 1227 (42000): Access denied; you need (at least one of) the SUPER,
SYSTEM_VARIABLES_ADMIN, SESSION_VARIABLES_ADMIN or REPLICATION_APPLIER
privilege(s) for this operation
```

একটা `ROW`-format binlog event একটা actual `BINLOG '...'` SQL statement দিয়ে apply হয় — আর MySQL deliberately এটা চালাতে একটা elevated, administrative-grade privilege require করে, কারণ একটা session যা arbitrary row-image event replay করতে পারে, effectively এমন একটা session যা normal statement-level logic bypass করে arbitrary data change inject করতে পারে। এটা exactly Chapter 4-এর `xtrabackup_demo` account-এর instance-wide (কিন্তু data-blind) privilege লাগার একই reasoning: backup আর recovery-র mechanics normal application data access-এর চেয়ে আলাদা একটা trust level-এ থাকে, আর MySQL-এর privilege model সেটা honestly reflect করে, pretend না করে এটা অন্য কিছু।

এই chapter-এর scoped `backup_demo` account-কে deliberately কখনো `SUPER` বা `REPLICATION_APPLIER` দেওয়া হয়নি — আগের প্রতিটা chapter-এর account তার লাগবে এমন minimum-এ scoped থাকার সাথে consistent থেকে — আর এই walkthrough এটা বেশি দিয়ে সেই wall-এর চারপাশে escalate করেনি। এটা convenience-এর জন্য এড়ানো কোনো shortcut না: **একটা real backup/restore automation script-এও `SUPER`-equivalent access থাকা উচিত না।** একটা real server-এ, exactly এই step — একটা point-in-time recovery-র extracted binlog event apply করা — এমন কিছু যা একজন DBA হাতে করে, specifically এই purpose-এর জন্য খোলা একটা administrative session-এ, ঠিক যেমন Chapter 4-এর `--copy-back`-এর জন্য live server বন্ধ করতে হয় আর এটা কোনো cron job-কে unattended trust করা উচিত না। এই chapter যে restriction-এ লেগেছে সেটা system intended হিসেবে কাজ করছে, এতে কোনো gap না।

## ৬. Recipe, যখন তোমার কাছে সেই administrative session থাকবে

এখানে final apply step সম্পূর্ণ না করলেও, recipe exactly Section 2-4 যা তৈরি করেছে, শুরু থেকে শেষ:

1. Base backup-এর exact binlog file আর position note করো (`SHOW BINARY LOG STATUS`, কোনো concurrent write ছাড়া নেওয়া একটা backup-এর ঠিক আগে বা পরে, বা `mysqldump --source-data=2` দিয়ে যা dump file-এর ভেতরে automatically এটা record করে)।
2. Exactly কোন মুহূর্তে recover করতে হবে identify করো — usually একটা application error log-এর timestamp থেকে, `mysqlbinlog -v`-এর human-readable output-এর বিরুদ্ধে cross-reference করে matching position খুঁজে।
3. `mysqlbinlog --start-position=<base> --stop-position=<খারাপ event-এর ঠিক আগে> binlog.NNNNNN > replay.sql` — শুধু মাঝের range extract করো।
4. Target database-এ base backup restore করো।
5. `replay.sql` apply করো, একটা administrative session থেকে যার এটা করার privilege আছে।
6. Chapter 2 যেভাবে করেছিল সেভাবেই result verify করো: real row count, real data, শুধু "command error দেয়নি" না।

## ৭. মূল শিক্ষা

- একটা base backup একা শুধু এটা নেওয়ার মুহূর্তে restore করতে পারে; binary log সেটাকে *যেকোনো* মুহূর্ত পর্যন্ত extend করে, যেটাই point-in-time recovery-র পুরো point।
- একটা base backup-এর মুহূর্তে exact binlog file আর position record করাই সেটার পরের সবকিছুকে পরে position দিয়ে addressable করে।
- `mysqlbinlog --start-position=X --stop-position=Y` event-এর একটা exact range extract করে — এখানে extracted file-এর content সরাসরি check করে verify করা হয়েছে (দুটো insert, একটা update, zero delete), position সঠিক ধরে নিয়ে না।
- `ROW`-format binlog event apply করতে `SUPER`/`REPLICATION_APPLIER` লাগে — একটা genuine MySQL security boundary, কোনো arbitrary অসুবিধা না, কারণ raw row image replay করা normal statement-level check bypass করে।
- একটা backup/restore automation account-এও সেই privilege থাকা উচিত না — real point-in-time recovery deliberately একটা manual, administrative-session action, Chapter 4-এর `--copy-back`-এর জন্য একটা বন্ধ live server লাগার একই category।

## ৮. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. একটা base backup একা কেন "2:47 PM-এর ঠিক আগে" recovery target satisfy করতে পারে না, যদি backup 2:00 AM-এ নেওয়া হয়েছিল?</strong></summary>

একটা base backup শুধু এটা নেওয়ার exact মুহূর্তের database state reflect করে - তারপর থেকে 2:47 PM পর্যন্ত যা লেখা হয়েছে তা শুধু binary log-এ exist করে, backup file-এ না, তাই backup একা শুধু 2:00 AM-এ restore করতে পারে।
</details>

<details>
<summary><strong>Q2. এই chapter কেন exact binlog file আর position base backup-এর আগে আর disaster-এর ঠিক আগে দুটোই record করে?</strong></summary>

Backup-এর সময়ের position বলে দেয় replay কোথা থেকে শুরু হবে; disaster-এর ঠিক আগের position সেই exact point যেখানে recovery থামা উচিত - একসাথে এগুলো extract আর replay করার জন্য event-এর precise range define করে, এই chapter-এ verify করা হয়েছে এতে শুধু wanted change আছে।
</details>

<details>
<summary><strong>Q3. এই chapter কীভাবে actually verify করলো extracted binlog range (24636 থেকে 25678) exactly intended event ধারণ করে?</strong></summary>

mysqlbinlog-কে -v দিয়ে চালিয়ে ROW-format event readable comment-এ decode করে, তারপর INSERT/UPDATE/DELETE grep করে - output exactly দুটো INSERT আর একটা UPDATE দেখিয়েছে, zero DELETE সহ, confirm করে disaster নিজেই range থেকে সঠিকভাবে exclude হয়েছে।
</details>

<details>
<summary><strong>Q4. ROW-format binlog replay কেন plain SQL-এর বদলে একটা actual BINLOG statement apply করা লাগে?</strong></summary>

ROW format যে SQL text এটা produce করেছে তার বদলে actual পরিবর্তিত row image record করে, তাই এটা replay করা মানে সেই row image সরাসরি MySQL-এর BINLOG statement দিয়ে আবার apply করা - এর বদলে চালানোর মতো কোনো equivalent plain INSERT/UPDATE/DELETE text নেই।
</details>

<details>
<summary><strong>Q5. একটা BINLOG statement apply করতে specifically কেন SUPER বা REPLICATION_APPLIER লাগে?</strong></summary>

একটা session যা arbitrary row-image event replay করতে পারে সেটা normal statement-level logic আর check bypass করে data change inject করতে পারে - MySQL এই bypass capability-র কারণেই specifically একটা administrative-grade privilege require করে, কোনো arbitrary restriction না।
</details>

<details>
<summary><strong>Q6. এই chapter কেন scoped backup_demo account-কে replay সম্পূর্ণ করার জন্য লাগা privilege দিতে বেছে নেয়নি?</strong></summary>

একটা real backup/restore automation account-এও SUPER-equivalent access থাকা উচিত না - শুধু একটা demo শেষ করার জন্য এটা দিলে এই পুরো course জুড়ে follow করা least-privilege principle-এর বিরোধিতা হতো, আর production-এ এই step আসলে কীভাবে করা উচিত তা misrepresent করতো।
</details>

<details>
<summary><strong>Q7. Real production use-এ, একটা point-in-time recovery-র final binlog-replay step আসলে কে বা কী চালানো উচিত?</strong></summary>

একজন DBA, deliberately খোলা একটা administrative session-এ - Chapter 4-এর `--copy-back`-এর জন্য আগে live server বন্ধ করা লাগার একই category-র action; এর কোনোটাই কোনো cron job বা unattended automation script-কে trust করে চালানো উচিত না।
</details>

<details>
<summary><strong>Q8. এই MySQL 8.4 server-এ SHOW MASTER STATUS কী করলো, আর এর বদলে কী ব্যবহার করা উচিত?</strong></summary>

এটা একটা syntax error দিয়েছে - MySQL 8.4 একটা বড় source/replica terminology change-এর অংশ হিসেবে SHOW MASTER STATUS সরিয়ে দিয়েছে, আর SHOW BINARY LOG STATUS এর replacement command, এই chapter জুড়ে ব্যবহার করা হয়েছে।
</details>

## ৯. Free Resources

- MySQL 8.4 Reference Manual: [Point-in-Time (Incremental) Recovery](https://dev.mysql.com/doc/refman/8.4/en/point-in-time-recovery.html)
- MySQL 8.4 Reference Manual: [`mysqlbinlog` — Binary Log File Reader](https://dev.mysql.com/doc/refman/8.4/en/mysqlbinlog.html)
- MySQL 8.4 Reference Manual: [`SHOW BINARY LOG STATUS` Statement](https://dev.mysql.com/doc/refman/8.4/en/show-binary-log-status.html)
- MySQL 8.4 Reference Manual: [`REPLICATION_APPLIER` Privilege](https://dev.mysql.com/doc/refman/8.4/en/privileges-provided.html#priv_replication-applier)

---

**এর পর:** [Chapter 6 — Offsite Storage: Dump Encrypt করা আর Server থেকে বের করা](06-offsite-storage-and-encryption.md)
