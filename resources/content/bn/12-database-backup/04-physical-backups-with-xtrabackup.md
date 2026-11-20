# Chapter 4 — XtraBackup দিয়ে Physical Backup

> **Course:** Database Backup & Recovery · **Chapter:** 4 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/database-backup/04-physical-backups-with-xtrabackup)

> **Honesty note:** এই chapter-এর জন্য Percona XtraBackup 8.4.0 সত্যিকারভাবে এই course-এর নিজের writing server-এ install করা হয়েছে (`sudo apt install percona-xtrabackup`, already configured Percona APT repo থেকে)। নিচের প্রতিটা backup, incremental, আর prepare/merge step সত্যিকারভাবে `backup_demo`-র বিরুদ্ধে চালানো হয়েছে। দুটো জিনিস explicit বলা জরুরি: প্রথমত, Chapter 2-3-এর বিপরীতে, এই chapter-এর backup user (`xtrabackup_demo`) necessarily **instance-wide** privilege রাখে (`BACKUP_ADMIN`, `RELOAD`, `PROCESS`, `LOCK TABLES`, `REPLICATION CLIENT`) — XtraBackup সরাসরি raw file copy করে, তাই এই privilege গুলো একটা logical-backup user-এর মতো একটা schema-তে scope করা যায় না; এর কাছে **কোনো table-এর data পড়ার `SELECT` privilege নেই**, নিচে `SHOW GRANTS` দিয়ে verify করা। দ্বিতীয়ত, একটা physical backup আসলে restore করতে (`--copy-back`) পুরো live `mysqld` process বন্ধ করতে হয় — এখানে করা হয়নি, একই কারণে এই course তার নিজের shared writing server-এ কখনো live service touch করে না।

Chapter 2 `mysqldump`-কে row-কে SQL হিসেবে export করতে বলেছিল। এই chapter সম্পূর্ণ আলাদা একটা question করে: database-কে তার নিজের data describe করতে বলার বদলে, যদি সরাসরি disk-এ MySQL-এর যে actual file আছে সেগুলোই copy করো তাহলে কী হয়? এটাই একটা physical backup, আর XtraBackup হলো একটা live, চলমান MySQL server থেকে পুরো operation-এর জন্য write lock না করে এটা নেওয়ার standard open-source tool।

## ১. একটা backup user যা একটা row-ও পড়তে পারে না

```bash
sudo mysql -e "CREATE USER 'xtrabackup_demo'@'localhost' IDENTIFIED BY '...'; GRANT BACKUP_ADMIN, RELOAD, PROCESS, LOCK TABLES, REPLICATION CLIENT ON *.* TO 'xtrabackup_demo'@'localhost';"
```

```bash
sudo mysql -e "SHOW GRANTS FOR 'xtrabackup_demo'@'localhost';"
```

```
GRANT RELOAD, PROCESS, LOCK TABLES, REPLICATION CLIENT ON *.* TO `xtrabackup_demo`@`localhost`
GRANT BACKUP_ADMIN ON *.* TO `xtrabackup_demo`@`localhost`
```

কোনো `SELECT` নেই, কোনো `INSERT` নেই, SQL দিয়ে কোনো table-এর actual data access নেই — এই account শুধু backup mechanics coordinate করার জন্য exist করে (সংক্ষেপে consistency-র জন্য lock করা, log flush করা, redo-log metadata পড়া)। Actual data copying filesystem level-এ হয়, যে কারণে `sudo` আলাদাভাবে লাগে: MySQL-এর datadir (`/var/lib/mysql`) `mysql` system user-এর owned, কোনো MySQL-level grant নির্বিশেষে অন্য কারো read access নেই, design করেই।

## ২. প্রথম real backup, আর filter আসলে কোথায় apply হয়

```bash
sudo xtrabackup --defaults-extra-file=/home/dev/.my-cnf.d/xtrabackup_demo.cnf \
  --backup \
  --target-dir=/home/dev/backups/xtrabackup_demo/full \
  --databases="backup_demo"
```

```
...
2026-11-20T17:53:22.870679+06:00 0 [Note] [MY-011825] [Xtrabackup] Backup created in directory '/home/dev/backups/xtrabackup_demo/full/'
...
2026-11-20T17:53:24.213366+06:00 0 [Note] [MY-011825] [Xtrabackup] completed OK!
```

```bash
sudo du -sh /home/dev/backups/xtrabackup_demo/full/
sudo du -sh /home/dev/backups/xtrabackup_demo/full/backup_demo/
```

```
81M	/home/dev/backups/xtrabackup_demo/full/
244K	/home/dev/backups/xtrabackup_demo/full/backup_demo/
```

এটাই এই chapter-এর সবচেয়ে গুরুত্বপূর্ণ real সংখ্যা: `backup_demo`-র নিজের দুটো table **244 KB**। Full backup directory **81 MB** — roughly 330 গুণ বড়। বাকিটা:

```bash
sudo du -sh .../ibdata1 .../mysql.ibd .../undo_001 .../undo_002
```

```
12M	ibdata1
36M	mysql.ibd
16M	undo_001
16M	undo_002
```

`ibdata1` (shared system tablespace), `mysql.ibd` (MySQL-এর নিজের internal schema), আর undo log — সবই **একটা MySQL instance-এ একবার** exist করে, একটা database-এ একবার না — `--databases="backup_demo"` শুধু filter করে কোন schema-র *নিজের* `.ibd` table file copy হবে। এটা instance-wide file গুলো filter করতে পারে না যা একটা physical backup-এর consistency-র জন্য fundamentally লাগে, কারণ এই file গুলো শুরু থেকেই কোনো একটা database-এর "অংশ" না। একটা real server-এ যেখানে একটা 500 GB database হোস্ট হয়, এই overhead negligible; এই course-এর tiny demo-তে, এটা সংখ্যাটাকে সম্পূর্ণভাবে dominate করে।

![81M full backup বনাম 260K incremental, আর --databases filtering কেন shared instance file ছোট করে না](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/12-database-backup/images/xtrabackup-full-incremental.svg)

## ৩. Incremental backup: যা `mysqldump` genuinely করতে পারে না

`mysqldump`-এর "শুধু যা পরিবর্তন হয়েছে" এমন কোনো concept নেই — প্রতিবার run সব row read আর export করে, প্রতিবার। XtraBackup একটা log sequence number (LSN) track করে আর শুধু আগের backup-এর checkpoint-এর পর থেকে পরিবর্তিত InnoDB page backup করতে পারে। একটা real নতুন row insert করার পর:

```bash
mysql ... backup_demo -e "INSERT INTO orders (customer_id, amount) VALUES (3, 899.99);"
```

```bash
sudo xtrabackup --defaults-extra-file=... --backup \
  --target-dir=/home/dev/backups/xtrabackup_demo/inc1 \
  --incremental-basedir=/home/dev/backups/xtrabackup_demo/full \
  --databases="backup_demo"
```

```
... completed OK!
```

```bash
sudo du -sh /home/dev/backups/xtrabackup_demo/inc1/
sudo ls -la /home/dev/backups/xtrabackup_demo/inc1/backup_demo/
```

```
260K	/home/dev/backups/xtrabackup_demo/inc1/
-rw-r----- 1 root root 16384 customers.ibd.delta
-rw-r----- 1 root root    67 customers.ibd.meta
-rw-r----- 1 root root 49152 orders.ibd.delta
-rw-r----- 1 root root    67 orders.ibd.meta
```

**Full backup-এর জন্য 81 MB, incremental-এর জন্য 260 KB** — একটা inserted row-এর জন্য, এই ratio প্রায় সম্পূর্ণ fixed overhead দুই দিকেই, কিন্তু principle real আর scale করে: একটা `.delta` file-এ শুধু পরিবর্তিত InnoDB page থাকে, `orders.ibd`-র একটা পুরো copy না। এটা exactly Chapter 1-এর incremental-vs-full tradeoff (storage আর সময় বাঁচে, একটা longer, more fragile restore chain-এর বিনিময়ে) actual file size দিয়ে concrete করা।

## ৪. একটা full আর incremental merge করা — তিনটা real `--prepare` step

একটা fresh XtraBackup directory as-is restorable না: এতে raw, এখনো-consistent-না data file আর একটা redo log থাকে যা এখনও replay করা লাগবে। `--prepare` সেই replay করে — আর একটা incremental merge করতে একটা specific তিন-step order-এ করা লাগে, সবকিছু এখানে genuinely চালানো:

```bash
# ১. Full backup prepare করো, কিন্তু "mergeable" রাখো (apply-log-only)
sudo xtrabackup --prepare --apply-log-only --target-dir=/home/dev/backups/xtrabackup_demo/full

# ২. Incremental-এর পরিবর্তিত page full backup-এ merge করো, এখনো apply-log-only
sudo xtrabackup --prepare --apply-log-only \
  --target-dir=/home/dev/backups/xtrabackup_demo/full \
  --incremental-dir=/home/dev/backups/xtrabackup_demo/inc1

# ৩. Final prepare - এই step-টাই এটাকে actually restorable করে
sudo xtrabackup --prepare --target-dir=/home/dev/backups/xtrabackup_demo/full
```

তিনটাই `completed OK!` print করেছে। প্রথম দুটো step-এ `--apply-log-only` সেই detail যা ভুল করা সহজ: এটা skip করলে (খুব আগেই একটা normal, final `--prepare` চালালে) full backup incremental-এর পরিবর্তন merge হওয়ার *আগেই* consistent হয়ে যায় — এর উপর একটা পরের prepare data extend করার বদলে corrupt করতে পারে। তিন-step order specifically এই কারণে exist করে যাতে full backup শুধু তখনই "শেষ" হয় যখন প্রতিটা incremental আগে থেকেই fold হয়ে গেছে।

এই তিনটা command-এর প্রতিটা শুধু **backup copy-র বিরুদ্ধে** চলেছে — XtraBackup-এর `--prepare` কাজ করে `--target-dir`-এর file-এর বিরুদ্ধে সংক্ষেপে নিজের একটা embedded InnoDB engine start করে, log replay করে, তারপর ওই embedded instance বন্ধ করে দিয়ে। Real `mysqld` যা `backup_demo` (আর এই server-এর বাকি সব database) serve করে, এই তিন step-এর কোনোটায় কখনো বন্ধ হয়নি, lock হয়নি, বা contact-ও হয়নি:

```bash
mysql --defaults-extra-file=~/.my-cnf.d/backup_demo.cnf backup_demo -e "SELECT COUNT(*) FROM orders;"
```

```
COUNT(*)
4
```

Live table-এ এখনো সব 4টা row আছে, সম্পূর্ণভাবে undisturbed — প্রমাণ যে পুরো merge একটা copy-তে, পাশে ঘটেছে।

## ৫. Actual restore step এখানে কেন করা হয়নি

একটা physical backup আসলে restore করা মানে `xtrabackup --copy-back --target-dir=... --datadir=/var/lib/mysql` — কিন্তু এটা শুধু একটা **empty** datadir-এর বিরুদ্ধে কাজ করে, যার মানে real `mysqld` process আগে সম্পূর্ণভাবে বন্ধ করতে হবে। একটা server-এ যেখানে এই course (আর পুরো site) নির্ভর করে এমন প্রতিটা database-এর জন্য একটা MySQL instance চলছে, এটা এমন একটা line না যা এই course cross করবে, একই কারণে আগের chapter গুলো এখানে live `sshd_config` touch করা বা crontab install করা থেকে থেমেছিল: কিছু ভুল হলে production database server বন্ধ করা undo করার কোনো উপায় নেই। তোমার নিজের server-এ — বা ideally, একটা dedicated replica বা staging box-এ, কখনো primary live server-এ না — এটাই সেই step যা একটা prepared backup directory-কে আবার একটা running database-এ পরিণত করে।

## ৬. Logical বনাম physical, এখন real সংখ্যা দিয়ে weigh করা

Chapter 1 এই tradeoff describe করেছিল; এই chapter finally honest সংখ্যা attach করতে পারে, exactly একই `backup_demo` database থেকে:

| | `mysqldump` (Chapter 2) | XtraBackup (এই chapter) |
|---|---|---|
| Output size, full backup | কয়েক KB SQL text | 81 MB (এই tiny demo-তে almost সম্পূর্ণ fixed per-instance overhead) |
| Incremental support | কোনো না — প্রতিটা run একটা full export | হ্যাঁ — একটা পরিবর্তিত row-এর জন্য 260 KB |
| Restore mechanism | SQL statement replay (`mysql < dump.sql`) | একটা **empty, বন্ধ** datadir-এ file copy back |
| লাগে এমন privilege | যে schema backup হচ্ছে তাতে scoped | Instance-wide (`BACKUP_ADMIN`, `PROCESS`, `RELOAD`, ...), কিন্তু zero data-read access |
| আলাদা DB নামে restore | Trivial (plain dump, `--databases` ছাড়া) | এভাবে supported না — physical file instance-এর নিজের structure-এর সাথে বাঁধা |

কোনো column-ই "ভালো" না — একটা ছোট-থেকে-মাঝারি database যার infrequent backup আর table-level flexibility লাগে, সেটা `mysqldump`-এর column-এর সাথে fit করে; একটা বড়, fast-changing database যেখানে scale-এ backup/restore *সময়* matter করে, সেটা XtraBackup-এর column-এর সাথে fit করে।

## ৭. মূল শিক্ষা

- একটা physical backup MySQL-এর actual on-disk file copy করে, SQL export করার বদলে — scale-এ fundamentally faster, কারণ কোনো row-by-row export বা replay নেই, কিন্তু datadir-এ সরাসরি filesystem access আর একটা instance-wide-privileged (তবে data-read না করা) MySQL account লাগে।
- XtraBackup-এ `--databases` filtering শুধু control করে কোন schema-র নিজের table file copy হবে — shared system tablespace, `mysql` schema, আর undo log instance-wide আর সবসময় include হয়, যে কারণে একটা 244 KB database-এর backup এখানে 81 MB হয়েছে।
- XtraBackup-এর incremental backup শুধু আগের backup-এর LSN checkpoint-এর পর থেকে পরিবর্তিত InnoDB page capture করে — `mysqldump`-এর এর কোনো equivalent-ই নেই।
- একটা full আর incremental backup merge করতে একটা specific তিন-step `--prepare` sequence লাগে (full-এ `--apply-log-only`, তারপর full-এ `--incremental-dir` দিয়ে, তারপর একটা final plain `--prepare`) — order বা flag ভুল হলে merge extend হওয়ার বদলে corrupt হতে পারে।
- `--prepare` সম্পূর্ণভাবে backup copy-র উপর একটা temporary embedded InnoDB instance দিয়ে কাজ করে; real, live `mysqld` এর দ্বারা touch হয় না। Actually restore করা (`--copy-back`) সেই একটা step যার জন্য live server বন্ধ করতে হয়, যে কারণে এটা এই shared writing server-এ করা হয়নি।

## ৮. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. xtrabackup_demo MySQL user-এর কেন Chapter 2-এর backup_demo user-এর মতো একটা schema-তে scoped privilege-এর বদলে instance-wide privilege লাগে?</strong></summary>

XtraBackup SQL-এর মাধ্যমে data পড়ার বদলে সরাসরি raw data file copy করে - এর coordination privilege (BACKUP_ADMIN, RELOAD, PROCESS, LOCK TABLES) MySQL-এ inherently instance-level আর একটা মাত্র schema-র grant-এ limit করা যায় না, যদিও account-এর কাছে কোনো table-এর data-এ SELECT/read privilege নেই।
</details>

<details>
<summary><strong>Q2. backup_demo database-এর নিজের table 244KB ছিল, কিন্তু full XtraBackup output 81MB হলো। বাকি ~80MB কোথা থেকে এলো?</strong></summary>

Shared system tablespace (ibdata1), mysql schema-র নিজের data (mysql.ibd), আর undo log - এগুলো সবই একটা MySQL instance-এ একবার exist করে, একটা database-এ একবার না, তাই --databases filter নির্বিশেষে এগুলো সবসময় একটা physical backup-এ include হয়।
</details>

<details>
<summary><strong>Q3. XtraBackup backup নেওয়ার সময় --databases flag আসলে কী filter করে?</strong></summary>

শুধু কোন schema-র নিজের .ibd table file backup directory-তে copy হবে - এটা system tablespace বা undo log-এর মতো instance-wide file exclude করতে পারে না, কারণ শুরু থেকেই সেগুলো কোনো একটা database-এর অংশ না।
</details>

<details>
<summary><strong>Q4. XtraBackup-এর incremental backup-এ কোন capability আছে যার mysqldump-এ কোনো equivalent নেই?</strong></summary>

আগের backup-এর LSN checkpoint-এর পর থেকে শুধু পরিবর্তিত InnoDB page backup করা - mysqldump-এর "শুধু যা পরিবর্তন হয়েছে" এমন কোনো concept-ই নেই; প্রতিটা run শুরু থেকে সব row export করে।
</details>

<details>
<summary><strong>Q5. একটা incremental merge করার আগে কেন full backup-কে একটা plain --prepare-এর বদলে --apply-log-only দিয়ে prepare করতে হয়?</strong></summary>

একটা plain --prepare backup-কে সম্পূর্ণ consistent আর "শেষ" করে দেয় - incremental merge হওয়ার আগে এটা হলে, এর উপর পরের একটা prepare data সঠিকভাবে extend করার বদলে corrupt করতে পারে। --apply-log-only full backup-কে mergeable অবস্থায় রাখে যতক্ষণ না প্রতিটা incremental fold হয়ে যায়।
</details>

<details>
<summary><strong>Q6. এই chapter-এ backup prepare আর merge করা কেন live backup_demo database-কে একদমই affect করেনি?</strong></summary>

xtrabackup --prepare backup-এর নিজের target directory-র file-এর বিরুদ্ধে একটা temporary, embedded InnoDB instance start করে কাজ করে, সেখানে redo log replay করে, তারপর সেই instance বন্ধ করে দিয়ে - real, live mysqld process যা backup_demo serve করে, কখনো বন্ধ হয়নি, lock হয়নি, বা contact-ও হয়নি।
</details>

<details>
<summary><strong>Q7. xtrabackup --copy-back এই chapter-এ কেন actually চালানো হয়নি?</strong></summary>

একটা physical backup আসলে restore করতে empty datadir-এ file copy করা লাগে, যার মানে live mysqld process আগে সম্পূর্ণভাবে বন্ধ করতে হবে - এই পুরো site-এর পেছনের actual database চালায় এমন একটা shared server-এ, কিছু ভুল হলে এটা reversible না, তাই এখানে করা হয়নি, এই course তার নিজের writing server-এ কখনো live service touch না করার সাথে consistent থেকে।
</details>

<details>
<summary><strong>Q8. এই chapter-এর real সংখ্যার ভিত্তিতে একটা scenario দাও যা mysqldump-এর জন্য ভালো, আর একটা XtraBackup-এর জন্য।</strong></summary>

mysqldump একটা ছোট-থেকে-মাঝারি database-এর জন্য ভালো যার table-level restore flexibility বা version-এর মধ্যে portability লাগে (Chapter 2)। XtraBackup একটা বড়, ঘন ঘন পরিবর্তনশীল database-এর জন্য ভালো যেখানে scale-এ backup/restore সময় আর incremental backup genuinely matter করে, এর বড় fixed per-instance overhead আর বেশি demanding privilege/restore requirement থাকলেও।
</details>

## ৯. Free Resources

- Percona XtraBackup 8.4 Documentation: [How XtraBackup Works](https://docs.percona.com/percona-xtrabackup/8.4/intro/how_xtrabackup_works.html)
- Percona XtraBackup 8.4 Documentation: [Incremental Backups](https://docs.percona.com/percona-xtrabackup/8.4/backup_scenarios/incremental_backup.html)
- Percona XtraBackup 8.4 Documentation: [The Backup Process — Permissions and Privileges](https://docs.percona.com/percona-xtrabackup/8.4/using_xtrabackup/privileges.html)

---

**এর পর:** [Chapter 5 — Point-in-time recovery: Binary log প্লাস একটা Base Backup](05-point-in-time-recovery.md)
