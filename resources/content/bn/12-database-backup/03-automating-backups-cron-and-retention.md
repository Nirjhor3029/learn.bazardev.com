# Chapter 3 — Backup Automate করা: Cron, Retention, আর Rotation

> **Course:** Database Backup & Recovery · **Chapter:** 3 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/database-backup/03-automating-backups-cron-and-retention)

> **Honesty note:** এই chapter-এর backup script, এর real run, আর retention test — সবকিছু সত্যিকারভাবে `backup_demo`-র বিরুদ্ধে চালানো হয়েছে, Chapter 2-এর সেই same throwaway database। কয়েক দিনের accumulated backup simulate করার জন্য file গুলো `touch -d "N days ago"` দিয়ে তৈরি করা হয়েছে, যাতে retention logic মিনিটে test করা যায়, এক সপ্তাহ অপেক্ষা না করে — যে `find ... -delete` command এগুলোর বিরুদ্ধে চলেছে সেটা real, অপরিবর্তিত command ছিল, কোনো dry run না। যেটা **করা হয়নি**: এই shared writing server-এ কোনো crontab entry আসলে install করা হয়নি। Exact লাইন দেওয়া আর explain করা আছে, কিন্তু এই course যেই server-এ লেখা হচ্ছে সেখানে একটা permanent scheduled job add করা তার নিজের একটা ছোট risk বহন করে (Security course-এর Chapter 1-এর "দ্বিতীয় session খোলা রাখো" caution শুধু SSH-এর জন্য না, কোনো unattended config change-এর জন্যই apply হয়) — তাই, আগের প্রতিটা chapter-এর সাথে consistent থেকে, এটা "এই তো tested script আর exact লাইন"-এ থেমে যায়, পিছনে কিছু unattended চলতে না রেখে।

একটা backup যা হাতে করে মনে করে চালাতে হয়, সেটা একদিন চালানো বন্ধ হয়ে যাওয়া একটা backup। এই chapter Chapter 2-এর manual `mysqldump`-কে এমন কিছুতে পরিণত করে যা নিজেই প্রতি রাতে চলে, honestly বলে এটা কাজ করেছে কিনা, আর নিজের পরে পরিষ্কার করে যাতে `backup_demo`-র মতো size-এর একটা habit নিঃশব্দে একটা full disk-এ পরিণত না হয়।

## ১. Chapter 2-এর একটা loop বন্ধ করা: warning-টা আসলেই দূর করা

Chapter 2 দেখেছিল `mysqldump` stderr-এ একটা `PROCESS` privilege error print করে — *প্রতিটা* run-এ, শুধু `--databases`-এ না, কারণ এটা default-ভাবে tablespace metadata dump করার চেষ্টা করে, যার জন্য permission properly scoped `backup_demo` user-এর নেই:

```bash
mysqldump --defaults-extra-file=~/.my-cnf.d/backup_demo.cnf backup_demo customers > /dev/null
```

```
mysqldump: Error: 'Access denied; you need (at least one of) the PROCESS privilege(s) for this operation' when trying to dump tablespaces
```

Trailer লাইন check করা (Chapter 2-এর fix) বলে দেয় backup তাও successful হয়েছে — কিন্তু একটা cron job যা বছরের পর বছর প্রতি রাতে এটা চালাবে, সেটাও নিঃশব্দে ওই stderr লাইন একটা cron-mail inbox-এ জমা করবে যা কেউ পড়ে না, প্রতিটা রাতে, forever। Actual fix একটা মাত্র flag:

```bash
mysqldump --defaults-extra-file=~/.my-cnf.d/backup_demo.cnf --no-tablespaces --single-transaction backup_demo > /dev/null
echo "exit: $?"
```

```
exit: 0
```

কোনো stderr output নেই। `--no-tablespaces` `mysqldump`-কে বলে দেয় ওই চেষ্টাটা সম্পূর্ণ skip করতে, চেষ্টা করে fail হওয়ার বদলে — একটা backup user-এর জন্য ঠিক fix যাকে deliberately `PROCESS` ছাড়া scoped করা হয়েছে, একটা অপ্রয়োজনীয় privilege দেওয়া বা প্রতি রাতে noise সহ্য করার বদলে।

## ২. পুরো Script, শুরু থেকে শেষ

```bash
#!/usr/bin/env bash
set -euo pipefail

CNF="$HOME/.my-cnf.d/backup_demo.cnf"
BACKUP_DIR="$HOME/backups/backup_demo"
DB="backup_demo"
RETENTION_DAYS=7
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
OUTFILE="$BACKUP_DIR/${DB}_${TIMESTAMP}.sql.gz"
LOGFILE="$BACKUP_DIR/backup.log"

mkdir -p "$BACKUP_DIR"

mysqldump --defaults-extra-file="$CNF" --single-transaction --no-tablespaces "$DB" | gzip > "$OUTFILE"

if zcat "$OUTFILE" | tail -1 | grep -q "Dump completed on"; then
    echo "$(date '+%F %T') OK: $OUTFILE ($(du -h "$OUTFILE" | cut -f1))" >> "$LOGFILE"
else
    echo "$(date '+%F %T') FAILED: $OUTFILE missing completion trailer" >> "$LOGFILE"
    rm -f "$OUTFILE"
    exit 1
fi

find "$BACKUP_DIR" -name "${DB}_*.sql.gz" -mtime "+${RETENTION_DAYS}" -print -delete >> "$LOGFILE"

echo "$(date '+%F %T') Backup complete: $OUTFILE"
```

চারটা জিনিস লক্ষ্য করার মতো, প্রতিটা একটা careless version-এ যা ভুল হতে পারে তার সরাসরি উত্তর:

- **`set -euo pipefail`** — script কোনো command fail হলে, একটা unset variable হলে, বা pipeline-এর একটা step fail হলে সাথে সাথে exit করে, একটা half-finished backup নিয়ে নিঃশব্দে চলতে থাকার বদলে।
- **Trailer লাইন check করা, শুধু mysqldump-এর exit code না** — Chapter 2-এর finding, apply করা: `zcat ... | grep -q "Dump completed on"` dump আসলে একটা real, সম্পূর্ণ file হওয়ার actual প্রমাণ।
- **একটা failed dump রেখে দেওয়া হয় না, delete করা হয়** (`rm -f "$OUTFILE"` fail হলে) — backup directory-তে একটা incomplete `.sql.gz` বসে থাকা, filename দিয়ে একটা ভালো file থেকে আলাদা করা যায় না, কোনো file না থাকার চেয়েও খারাপ: এটা দেখতে একটা valid backup-এর মতো, যতদিন না এটা আসলে লাগে।
- **Retention একই script-এর ভেতরে চলে, একটা confirmed-good backup-এর পরে** — তাই একটা successful নতুন backup সবসময় থাকে, কোনো পুরানো একটা remove হওয়ার আগে।

সত্যিকারভাবে চালানো:

```bash
~/backups/backup_demo_backup.sh
cat ~/backups/backup_demo/backup.log
```

```
2026-11-20 11:28:57 OK: /home/dev/backups/backup_demo/backup_demo_20261120_112857.sql.gz (4.0K)
```

![cron প্রতি রাতে এই script চালায়: dump, trailer লাইন verify, তারপর age অনুযায়ী rotate](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/12-database-backup/images/cron-retention-cycle.svg)

## ৩. এক সপ্তাহ অপেক্ষা না করে retention test করা

`find ... -mtime +7 -delete` লাইনটা এমন জিনিস যা লেখা সহজ কিন্তু কখনো actually verify করা হয় না — এটা subtly ভুল হলে, মাস পরে একটা disk ভরে যাওয়ার আগে তুমি জানবে না। `touch -d` সেকেন্ডে একটা file-এর age fake করতে পারে, যা এটা সত্যিকারভাবে এখনই test করার জন্য যথেষ্ট:

```bash
cd ~/backups/backup_demo
for days_ago in 10 8 5 2; do
    cp backup_demo_20261120_112857.sql.gz "backup_demo_simulated_${days_ago}d_ago.sql.gz"
    touch -d "${days_ago} days ago" "backup_demo_simulated_${days_ago}d_ago.sql.gz"
done
find . -name "backup_demo_*.sql.gz" -mtime +7
```

```
./backup_demo_simulated_8d_ago.sql.gz
./backup_demo_simulated_10d_ago.sql.gz
```

Exactly 7-দিনের retention window-এর চেয়ে পুরানো দুটো file — 5-দিন আর 2-দিনের file ঠিকমতো দেখা যাচ্ছে না। Real delete চালানো:

```bash
find . -name "backup_demo_*.sql.gz" -mtime +7 -print -delete
ls .
```

```
./backup_demo_simulated_8d_ago.sql.gz
./backup_demo_simulated_10d_ago.sql.gz
backup.log
backup_demo_20261120_112857.sql.gz
backup_demo_simulated_2d_ago.sql.gz
backup_demo_simulated_5d_ago.sql.gz
```

যে দুটো file `-print` হয়েছিল সেই দুটোই exactly এখন directory listing থেকে চলে গেছে — 5-দিন, 2-দিন, আর আজকের backup সবাই বেঁচে গেছে। এটা একটা real retention rule-এর একটা real test, manual page পড়া না।

## ৪. Time-based বনাম count-based retention

দুটো আলাদা question, দুটো আলাদা `find`/`ls` one-liner — যেটা তোমার reasoning-এর সাথে actually মেলে সেটা বেছে নাও, যেটা লিখতে ছোট সেটা না:

- **"N দিনের চেয়ে পুরানো যা কিছু delete করো"** (time-based): `find "$DIR" -name '*.sql.gz' -mtime +7 -delete`। Simple, কিন্তু কতটা backup রাখা হবে সেটা নির্ভর করে job আসলে কতবার চলেছে তার উপর — কয়েক রাত miss হলে intended-এর চেয়ে কম backup থাকে, কোনো warning ছাড়াই।
- **"Exactly সবচেয়ে recent N backup রাখো"** (count-based): `ls -t "$DIR"/*.sql.gz | tail -n +6 | xargs -r rm`, সত্যিকারভাবে test করা:

```bash
ls -t backup_demo_*.sql.gz
```

```
backup_demo_fake_4.sql.gz
backup_demo_fake_3.sql.gz
backup_demo_fake_2.sql.gz
backup_demo_fake_1.sql.gz
backup_demo_20261120_112857.sql.gz
backup_demo_simulated_2d_ago.sql.gz
backup_demo_simulated_5d_ago.sql.gz
```

```bash
ls -t backup_demo_*.sql.gz | tail -n +4 | xargs -r rm -v
```

```
removed 'backup_demo_fake_1.sql.gz'
removed 'backup_demo_20261120_112857.sql.gz'
removed 'backup_demo_simulated_2d_ago.sql.gz'
removed 'backup_demo_simulated_5d_ago.sql.gz'
```

`ls -t` newest আগে list করে; `tail -n +4` লাইন 4 থেকে বাকি সবকিছু রাখে (মানে, সবচেয়ে newest 3 drop করে, বাকিগুলো delete-এর জন্য রাখে) — সবচেয়ে নতুন 3টা file বেঁচে গেছে, বাকি সব remove হয়ে গেছে। `xargs -r` specifically avoid করে একটা empty list হলে `rm` কোনো argument ছাড়া চালানো (কিছু system-এ `-r` ছাড়া একটা plain `xargs` তাও `rm` চালানোর চেষ্টা করবে, যা একা harmless কিন্তু একটা habit হিসেবে avoid করার মতো একটা silent footgun)।

**একটা বেছে নাও।** দুটোকে informally mix করা — "roughly এক সপ্তাহ, কিন্তু roughly 10টা backup-ও" — মানে কোনো সংখ্যাই actually guaranteed না, যা retention policy থাকার পুরো point-টাই নষ্ট করে দেয়।

## ৫. Crontab লাইন, আর এটা কেন এখানে install করা হয়নি

Script, দিনে একবার রাত 2 টায় চলবে, `set -euo pipefail` যা দিয়ে যায় তার stdout log করবে:

```
0 2 * * * /home/dev/backups/backup_demo_backup.sh >> /home/dev/backups/backup_demo/cron.log 2>&1
```

`crontab -e` current user-এর crontab-এ এই লাইন add করে; `0 2 * * *` মানে minute 0, hour 2, প্রতিদিন, প্রতি মাস, প্রতি weekday — cron-এর পাঁচটা field `minute hour day-of-month month day-of-week` order-এ। Script-এর full absolute path matter করে: cron job গুলো একটা minimal environment দিয়ে চালায়, তোমার interactive shell-এর `$PATH` বা alias না, তাই একটা bare filename দিয়ে invoke করা script যা হাতে চালালে ঠিক কাজ করে, exactly এই পার্থক্যের কারণে cron-এর অধীনে কোনো such file error ছাড়াই silently fail করতে পারে।

এই লাইনটা সঠিক আর উপরের সবকিছুর বিরুদ্ধে tested — কিন্তু এটা এই server-এর crontab-এ **আসলে** add করা হয়নি, ঠিক একই কারণে যে কারণে আগের chapter গুলো এই shared writing server-এ `sshd_config` বা `ufw` touch করা থেকে থেমেছিল: একটা unattended job যা এই course শেষ হওয়ার পরও চলতে থাকে, এমন একটা machine-এ একটা ছোট, অপ্রয়োজনীয় risk যেখানে এখনো অন্য chapter লেখা হচ্ছে। তোমার নিজের server-এ, এটাই সেই একটা step যা উপরের সবকিছুকে "একটা script যা আমি চালাতে পারি" থেকে "একটা backup যা আসলে আমার কিছু না করেই হয়"-তে পরিণত করে।

## ৬. Disk নিজেই দেখতে ভুলো না

```bash
df -h ~/backups/backup_demo
```

```
Filesystem      Size  Used Avail Use% Mounted on
/dev/sda1       193G   34G  159G  18% /
```

Retention specifically এই কারণে exist করে যাতে backup নিঃশব্দে পুরো disk consume না করে — কিন্তু retention একটা failed dump-এর *পরে* চলা, বা retention-এ নিজেই একটা bug থাকা, দুটোই তাও একটা disk নিঃশব্দে ভরে যেতে দিতে পারে। Monitoring & Logging course-এর disk-space alerting সরাসরি এখানে apply হয়: একটা real backup setup এই chapter-এর retention job-কে actual disk usage-এর উপর একটা real alert-এর সাথে pair করে, তাই "retention script-এ একটা bug আছে" একজন human ঘণ্টার মধ্যে ধরে, একদিন একটা backup — বা live database নিজেই — disk ভরে গিয়ে লিখতে না পারার দিনে discover হওয়ার বদলে।

## ৭. মূল শিক্ষা

- `mysqldump` default-ভাবে প্রতিটা run-এ tablespace metadata dump করার চেষ্টা করে, যা একটা properly scoped backup user প্রতিবারই stderr-এ একটা `PROCESS` privilege error দিয়ে fail করবে; `--no-tablespaces` এই চেষ্টা সম্পূর্ণ সরিয়ে দেয়, একটা unattended job-এর জন্য সঠিক fix যার forever stderr warning email করা উচিত না।
- একটা real backup script actual success check করা উচিত (dump-এর নিজের completion trailer, Chapter 2), একটা failed output রেখে দেওয়ার বদলে delete করা উচিত, আর শুধু একটা confirmed-good নতুন backup exist করলেই retention চালানো উচিত।
- `touch -d "N days ago"` সেকেন্ডে একটা `find ... -mtime +N -delete` retention rule genuinely test করতে পারে, সপ্তাহের পর সপ্তাহ unverified trust করার বদলে।
- Time-based retention ("N দিনের চেয়ে পুরানো") আর count-based retention ("N newest রাখো") আলাদা question-এর উত্তর দেয় আর silently diverge করতে পারে — দুটো একসাথে reason না করে একটা deliberately বেছে নাও।
- Cron job গুলো একটা minimal environment দিয়ে চলে: সবসময় script-এর আর এটা যে binary call করে তার full absolute path ব্যবহার করো (যেগুলো guaranteed `$PATH`-এ নেই), কারণ যা interactively কাজ করে সেটা cron-এর অধীনে silently fail করতে পারে।

## ৮. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. Chapter 2 mysqldump-এর stderr-এ একটা PROCESS privilege error পেয়েছিল। একটা unattended cron job-এর জন্য actual fix কী, শুধু check করার বদলে?</strong></summary>

--no-tablespaces add করো, যা mysqldump-কে বলে দেয় tablespace-dump চেষ্টাটা সম্পূর্ণ skip করতে, চেষ্টা করে fail হওয়ার বদলে। পরে check করাও কাজ করে, কিন্তু একটা cron job forever চলে, তাই unattended কিছুর জন্য source-এ noisy stderr সরিয়ে দেওয়াই ভালো fix।
</details>

<details>
<summary><strong>Q2. Example backup script কেন completion-trailer check fail হলে নিজের output file delete করে?</strong></summary>

একটা half-written .sql.gz file filename দিয়ে একটা ভালো backup থেকে আলাদা করা যায় না, কিন্তু actually restore করার জন্য useless বা dangerous - এটা রেখে দিলে পরের একটা "সবচেয়ে newest backup বেছে নাও" restore নিঃশব্দে একটা real file-এর বদলে একটা broken file ধরে নিতে পারে।
</details>

<details>
<summary><strong>Q3. Script-এ retention step কেন শুধু একটা confirmed-good নতুন backup-এর পরে চলে, আগে বা unconditionally না?</strong></summary>

Retention আগে (বা success নির্বিশেষে) চালালে পুরানো, এখনো-valid backup delete হওয়ার risk থাকে যখন সবচেয়ে newest চেষ্টাটাই মাত্র fail হয়েছে - একটা successful নতুন backup সবসময় exist করা উচিত কোনো পুরানো একটা remove হওয়ার আগে, তাই কখনো zero valid backup-এর একটা gap থাকে না।
</details>

<details>
<summary><strong>Q4. find -mtime +7 -delete retention rule এই chapter-এ এক সপ্তাহ অপেক্ষা না করে কীভাবে actually test করা হলো?</strong></summary>

touch -d "N days ago" ব্যবহার করে কয়েকটা backup file-এর modification time fake করা হয়েছিল (10, 8, 5, আর 2 দিন পুরানো), তারপর real find -mtime +7 -delete command সেগুলোর বিরুদ্ধে চালানো হয়েছিল - 7 দিনের চেয়ে পুরানো দুটো file genuinely delete হয়েছে, যখন নতুন দুটো বেঁচে গেছে।
</details>

<details>
<summary><strong>Q5. Time-based retention আর count-based retention-এর মধ্যে practical পার্থক্য কী?</strong></summary>

Time-based ("N দিনের চেয়ে পুরানো") একটা variable সংখ্যক backup রাখে job আসলে কতবার চলেছে তার উপর নির্ভর করে - miss হওয়া রাত মানে intended-এর চেয়ে কম backup, silently। Count-based ("N newest রাখো") সবসময় exactly N টা backup রাখে schedule gap নির্বিশেষে, কিন্তু কোনো নির্দিষ্ট time span-এর history guarantee করে না।
</details>

<details>
<summary><strong>Q6. হাতে ঠিকমতো চালানো একটা script কেন কখনো cron দিয়ে চালালে silently fail করে?</strong></summary>

Cron job গুলো একটা minimal environment দিয়ে চলে, interactive shell-এর $PATH বা alias না - একটা script বা command যা interactive shell-এ নাম দিয়ে ঠিকমতো resolve হয়, সেটা cron-এর অধীনে "command not found" দিয়ে fail করতে পারে যতক্ষণ না পুরো জায়গায় full absolute path ব্যবহার করা হয়।
</details>

<details>
<summary><strong>Q7. এই chapter-এর crontab লাইনটা কেন writing server-এ আসলে install করা হয়নি?</strong></summary>

আগের প্রতিটা chapter-এর shared writing server-এ unattended change নিয়ে caution-এর সাথে consistent থেকে (একই reasoning যা SSH/firewall change সেখানে untested রেখেছিল), এই course শেষ হওয়ার পরও চলতে থাকা একটা permanent scheduled job একটা ছোট, অপ্রয়োজনীয় risk - tested script আর exact crontab লাইন দেওয়া হয়েছে এর বদলে।
</details>

<details>
<summary><strong>Q8. Retention একা কেন একটা disk নিঃশব্দে ভরে যাওয়া থেকে সম্পূর্ণ রক্ষা করে না?</strong></summary>

Retention শুধু সেই backup গুলো সরায় যা এটা সফলভাবে চালাতে পারে - retention logic-এ নিজেই একটা bug, বা একটা failed dump যা তাও space consume করে, দুটোই একটা disk ভরতে দিতে পারে নির্বিশেষে। Retention-কে actual disk-usage alerting (Monitoring & Logging course)-এর সাথে pair করলে এই gap ঘণ্টার মধ্যে ধরা পড়ে, কিছু লিখতে fail হওয়ার পরে না।
</details>

## ৯. Free Resources

- `man crontab`, `man 5 crontab` (field order আর example)
- `man find` — `-mtime` আর `-delete` option specifically
- MySQL 8.4 Reference Manual: [`mysqldump` — the `--no-tablespaces` option](https://dev.mysql.com/doc/refman/8.4/en/mysqldump-option-summary.html#option_mysqldump_no-tablespaces)

---

**এর পর:** [Chapter 4 — XtraBackup দিয়ে Physical Backup](04-physical-backups-with-xtrabackup.md)
