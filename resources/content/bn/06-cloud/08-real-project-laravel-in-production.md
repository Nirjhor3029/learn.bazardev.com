# Chapter 8 — আসল Project: Production-এ একটা Laravel App

> **Course:** Cloud (AWS / VPS Basics) · **Chapter:** 8 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cloud/08-real-project-laravel-in-production)

এবার সবকিছু জোড়া লাগানোর পালা। আমরা একটা Laravel app-কে "আমার laptop-এ চলে" থেকে **internet-এ live** করব, domain, HTTPS, database, queue worker, scheduler, deploy script আর backup-সহ।

ধাপগুলো **Ubuntu চালানো একটা VPS** ধরে। AWS-এ একই ধাপ একটা **EC2** instance-এ কাজ করে, আর শেষে দেখাব প্রতিটা অংশ AWS-এর কোন service-এর সাথে মেলে।

> **সৎ কথা:** এই command-গুলো Ubuntu-তে এটা করার সাধারণ উপায়, কিন্তু আমি তোমার জন্য একদম নতুন server-এ এগুলো চালাইনি। Package-এর নাম ও version বদলায় (যেমন PHP version), তাই তোমার server যা দেয় সেই অনুযায়ী মানিয়ে নাও, আর চালানোর আগে প্রতিটা command পড়ো। একবার ধীরে ধীরে আসল deploy করাই এটা শেখার সেরা উপায়।

![সম্পূর্ণ production setup](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/06-cloud/images/laravel-production.svg)

## ধাপ ০ — পরিকল্পনা

| অংশ | কোথা থেকে |
|---|---|
| নিরাপদ server | Chapter 2 |
| Server-মুখী domain | Chapter 3 |
| Web server + HTTPS | Chapter 4 |
| PHP, Composer, MySQL | এই chapter |
| App code, `.env`, migration | এই chapter |
| Queue worker + scheduler | এই chapter |
| Deploy | এই chapter (আর CI/CD course) |
| Backup | Chapter 7, এখানে প্রয়োগ |

## ধাপ ১ — নিরাপদ server আর domain

Chapter 2 মানো: server বানাও, নিজের `deploy` user, শুধু SSH key, `PermitRootLogin no`, UFW-তে OpenSSH, 80 ও 443 অনুমোদন। তারপর Chapter 3: A record দিয়ে `myshop.com` ও `www`-কে server-এর IP-তে পাঠাও। `dig +short myshop.com` দিয়ে দেখো।

## ধাপ ২ — Stack install

তোমার `deploy` user হিসেবে (`sudo`-সহ):

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx git unzip mysql-server \
  php-fpm php-cli php-mysql php-mbstring php-xml php-curl php-zip php-bcmath php-intl
php -v                      # version লিখে রাখো, যেমন 8.3
```

**Composer** install করো getcomposer.org-র official নির্দেশ ধরে (installer checksum যাচাই করে), তারপর `composer --version` দিয়ে নিশ্চিত হও।

দেখো তোমার PHP version তোমার Laravel version-এর শর্ত মেটায় কিনা, আর `systemctl status php8.3-fpm` (তোমার version দাও) চলছে দেখায় কিনা।

## ধাপ ৩ — Database

MySQL default-এ শুধু `localhost`-এ শোনে, যা আমরা চাই: **firewall-এ port 3306 কখনো খুলো না।** একটা database আর একটা নির্দিষ্ট user বানাও:

```bash
sudo mysql
```

```sql
CREATE DATABASE myshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'myshop'@'localhost' IDENTIFIED BY 'use-a-long-random-password';
GRANT ALL PRIVILEGES ON myshop.* TO 'myshop'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

App-এর user **শুধু নিজের database** ছুঁতে পারে, পুরো server নয়। (AWS-এ তুমি private subnet-এর একটা RDS endpoint ব্যবহার করবে। Chapter 6 ও 7।)

## ধাপ ৪ — Server-এ code আনো

```bash
sudo mkdir -p /var/www/myshop
sudo chown deploy:www-data /var/www/myshop
cd /var/www/myshop
git clone https://github.com/YOUR_NAME/YOUR_REPO.git .
composer install --no-dev --optimize-autoloader
```

**Private repository**-র জন্য নিজের ব্যক্তিগত key-র বদলে GitHub-এ একটা **deploy key** (ওই একটা repo-র জন্য read-only SSH key) যোগ করো।

`--no-dev` development package বাদ দেয়। তোমার app-এ frontend build (Vite) থাকলে এখানে `npm ci && npm run build` চালাও, বা CI-তে build করে output কপি করো। Node শুধু build-এর সময় লাগে।

## ধাপ ৫ — `.env` সাজাও

```bash
cp .env.example .env
nano .env
```

Production-এ যে setting-গুলো গুরুত্বপূর্ণ:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://myshop.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myshop
DB_USERNAME=myshop
DB_PASSWORD=the-password-from-step-3
```

**`APP_DEBUG=false` অত্যন্ত জরুরি।** Debug চালু থাকলে error page যেকোনো visitor-কে তোমার environment variable দেখিয়ে দিতে পারে, password সহ।

তারপর:

```bash
php artisan key:generate
php artisan migrate --force
php artisan storage:link
```

Production-এ `--force` লাগে, নইলে Laravel "নিশ্চিত?" জিজ্ঞেস করে।

**Permission।** Web server-এর user (`www-data`) `storage` আর `bootstrap/cache`-এ লিখতে পারতে হবে:

```bash
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

**`.env` কখনো commit করো না**, আর কখনো web থেকে পড়ার মতো রেখো না (web root হলো `public/`, project folder নয়, তাই পড়া যায় না)।

## ধাপ ৬ — Nginx আর HTTPS

Chapter 4-এর মতো `/etc/nginx/sites-available/myshop` বানাও, `root /var/www/myshop/public;` আর তোমার PHP-FPM socket দিয়ে। তারপর:

```bash
sudo ln -s /etc/nginx/sites-available/myshop /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d myshop.com -d www.myshop.com
sudo certbot renew --dry-run
```

`https://myshop.com` খোলো। বদলে Caddy ব্যবহার করলে Chapter 4-এর Caddyfile স্বয়ংক্রিয় HTTPS-সহ একই কাজ করে।

## ধাপ ৭ — Production-এর জন্য Laravel দ্রুত করো

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

(`php artisan optimize` মূলগুলো একসাথে চালায়।) একটা চিরাচরিত ফাঁদ: **`config:cache`-এর পর `env()` শুধু config ফাইলের ভেতরে কাজ করে।** অন্য কোথাও `env('SOMETHING')` ডাকলে `null` পায়। তোমার code-এ `config('...')` ব্যবহার করো। আর server-এ `.env` edit করার পর `php artisan config:clear` (বা আবার cache) চালাও, নইলে তোমার পরিবর্তন উপেক্ষিত হয়।

## ধাপ ৮ — Queue worker আর scheduler

অনেক app background-এ email পাঠায় বা ছবি প্রসেস করে (**queue**), আর সময়সূচির কাজ চালায় (**scheduler**)।

**Scheduler:** একটা cron entry প্রতি মিনিটে Laravel-এর scheduler চালায়। Web user-এর crontab edit করো:

```bash
sudo crontab -u www-data -e
```

```
* * * * * cd /var/www/myshop && php artisan schedule:run >> /dev/null 2>&1
```

**Queue worker:** এটা সবসময় চলতে হবে আর crash করলে আবার চালু হতে হবে। **systemd** service ব্যবহার করো:

```ini
# /etc/systemd/system/myshop-worker.service
[Unit]
Description=MyShop queue worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/myshop/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now myshop-worker
systemctl status myshop-worker
```

(তোমার `.env`-এ `QUEUE_CONNECTION=database` বা `redis` দিতে হবে, `sync` নয়।) Worker পুরনো code memory-তে ধরে রাখে, তাই **প্রতিটা deploy-এর পর restart করো**: `php artisan queue:restart`।

## ধাপ ৯ — Deploy script

Server-এ ফাইল edit করে কখনো deploy করো না। ধাপগুলো একবার লিখে রাখো:

```bash
#!/usr/bin/env bash
# /var/www/myshop/deploy.sh
set -euo pipefail
cd /var/www/myshop

php artisan down --retry=60          # maintenance page দেখাও
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
sudo systemctl reload php8.3-fpm     # তোমার PHP version দাও
php artisan up
echo "Deployed."
```

`set -euo pipefail` script-কে **প্রথম error-এই থামিয়ে দেয়**, অর্ধেক deploy নিয়ে এগিয়ে না গিয়ে। তুমি এটা চালাও `bash deploy.sh` দিয়ে।

পরে `main`-এ merge হলেই **GitHub Actions**-কে SSH-এর মাধ্যমে এই script চালাতে দাও। এটাই CI/CD course-এর pipeline, GitHub Secrets-এ রাখা একটা নির্দিষ্ট deploy key-সহ। Database ছোঁয় এমন deploy-এর আগে backup থাকা উচিত (পরের ধাপ), আর বড় app-এ তুমি release folder-সহ zero-downtime deploy-এর দিকে যাবে।

## ধাপ ১০ — Backup

প্রতি রাতে database dump, compress করা, server-এর বাইরে কপি। প্রথমে database password একটা private ফাইলে রাখো যাতে সেটা কখনো command line বা `ps`-এ না আসে:

```bash
nano ~/.my.cnf
```

```
[client]
user=myshop
password=the-password-from-step-3
```

```bash
chmod 600 ~/.my.cnf
```

Backup script:

```bash
#!/usr/bin/env bash
# /home/deploy/backup-db.sh
set -euo pipefail
STAMP=$(date +%F-%H%M)
DIR=/var/backups/myshop
mkdir -p "$DIR"

mysqldump --single-transaction --no-tablespaces myshop | gzip > "$DIR/myshop-$STAMP.sql.gz"

# server-এর বাইরে কপি (AWS CLI আর এই bucket-এ সীমিত IAM user বা role লাগে)
aws s3 cp "$DIR/myshop-$STAMP.sql.gz" s3://my-backup-bucket/myshop/

# স্থানীয়ভাবে শুধু শেষ ৭ দিন রাখো
find "$DIR" -name 'myshop-*.sql.gz' -mtime +7 -delete
```

`crontab -e` দিয়ে প্রতি রাতে চালাও:

```
15 2 * * * /home/deploy/backup-db.sh >> /home/deploy/backup.log 2>&1
```

পুরনো কপি মেয়াদোত্তীর্ণ করতে S3 **lifecycle rule** যোগ করো, আর bucket private রাখো (Chapter 6)। এখনও AWS না থাকলে `scp` বা `rsync` দিয়ে ফাইলটা অন্য machine-এ কপি করো। মূল কথা এই server-এর **বাইরে** একটা কপি (3-2-1, Chapter 7)।

**এবার restore অনুশীলন করো** একটা scratch database-এ, আর সময় মাপো:

```bash
gunzip < myshop-2026-01-01-0215.sql.gz | mysql myshop_restore_test
```

যে backup restore করোনি সেটা শুধু আশা।

## ধাপ ১১ — নজর রাখো

- **চলছে কি?** একটা বিনামূল্যের uptime monitor (UptimeRobot, Better Stack-এর মতো) ব্যবহার করো যা `https://myshop.com/up` ping করে আর ব্যর্থ হলে email করে। Laravel 11+-এ `/up` health route আছে।
- **Error:** `storage/logs/laravel.log` আর `sudo tail -n 50 /var/log/nginx/error.log` পড়ো।
- **Disk ও memory:** `df -h` (ভরা disk database ও log ভাঙে), `free -h`।
- **Worker:** `systemctl status myshop-worker`।

**Monitoring & Logging** course-এ তুমি আরও গভীরে যাবে।

## ধাপ ১২ — Go-live checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `.env` Git-এ নেই
- [ ] HTTPS কাজ করে; HTTP থেকে HTTPS-এ redirect হয়; `certbot renew --dry-run` পাস করে
- [ ] Firewall শুধু 22 (সম্ভব হলে তোমার IP থেকে), 80, 443 অনুমোদন করে; port 3306 বন্ধ
- [ ] SSH: শুধু key, root login নেই
- [ ] Database user একটা database-এ সীমিত
- [ ] `storage` ও `bootstrap/cache` web user-এর জন্য লেখার যোগ্য
- [ ] Queue worker চলছে; scheduler cron বসানো
- [ ] Deploy script শুরু থেকে শেষ পর্যন্ত কাজ করে
- [ ] রাতের backup চলে, একটা কপি server-এর বাইরে, **আর একটা restore পরীক্ষা করা হয়েছে**
- [ ] Uptime monitor আর budget/খরচের alert বসানো
- [ ] OS update (`unattended-upgrades`) চালু

## ধাপ ১৩ — একই project AWS-এ

| এই chapter-এর VPS | AWS-এ |
|---|---|
| VPS | **EC2** instance (public subnet-এ, security group-এ 80/443 খোলা; 22 শুধু তোমার IP থেকে) |
| একই server-এর MySQL | Private subnet-এ **RDS**, শুধু app-এর security group থেকে পৌঁছানো যায় |
| `/var/www/myshop/storage`-এর upload | **S3** bucket, instance-এর **IAM role** দিয়ে ব্যবহার |
| S3-তে backup | RDS automated backup ও snapshot, সাথে S3 versioning |
| Registrar-এ domain | **Route 53** (বা নিজের DNS host রাখো), Elastic IP-র দিকে |
| `ufw` | **Security group** (চাইলে UFW-ও রাখো) |
| খরচ নিয়ন্ত্রণ | একটা **budget**, tag, আর অনুশীলনের পর পরিষ্কার |

খেয়াল করো ধারণাগত কিছুই বদলায়নি: একই web server, একই app, একই deploy script। তুমি শুধু কিছু অংশ managed service দিয়ে বদলেছ।

## মূল কথা

- Production Laravel deploy-এর জন্য লাগে: **নিরাপদ server**, একটা **domain**, **HTTPS-সহ Nginx বা Caddy**, **PHP-FPM**, একটা **database**, আর সঠিক `.env`-সহ app।
- `APP_ENV=production` ও **`APP_DEBUG=false`** দাও। `migrate --force` ব্যবহার করো। `storage` ও `bootstrap/cache` web user-এর জন্য লেখার যোগ্য করো। Web root হলো `public/`।
- গতির জন্য config/route/view cache করো; মনে রেখো cache-এর পর `env()` শুধু config ফাইলে কাজ করে, আর `.env` edit করার পর config cache সাফ করো।
- **Scheduler** cron থেকে আর **queue worker** **systemd**-এর অধীনে চালাও, আর প্রতিটা deploy-এ worker restart করো।
- Deploy-এর ধাপ একটা **script**-এ রাখো (`set -euo pipefail`) আর CI/CD দিয়ে স্বয়ংক্রিয় করো।
- Database-এর backup server-এর বাইরে রাখো, আর **restore পরীক্ষা করো**।
- Uptime monitor, log আর disk পরীক্ষা দিয়ে app-এর ওপর নজর রাখো।
- একই নকশা AWS-এ মেলে **EC2 + RDS + S3 + Route 53 + security group**-এর সাথে।

## নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. Production-এ `APP_DEBUG` কেন `false` হতে হবে?</strong></summary>

Debug চালু থাকলে error page যেকোনো visitor-কে password ও file path-এর মতো environment-এর তথ্য দেখিয়ে দিতে পারে।
</details>

<details>
<summary><strong>Q2. Web server-এর root project folder-এর বদলে `public/` folder কেন?</strong></summary>

যাতে `.env`, source code, `vendor` আর অন্য ব্যক্তিগত ফাইল web থেকে পৌঁছানো না যায়।
</details>

<details>
<summary><strong>Q3. Deploy-এর পর `php artisan queue:restart` চালাই কেন?</strong></summary>

Queue worker পুরনো code memory-তে ধরে রাখে। Restart করলে সে নতুন code লোড করে।
</details>

<details>
<summary><strong>Q4. Deploy script-এ `set -euo pipefail` কী করে?</strong></summary>

এটা script-কে প্রথম error-এ (আর unset variable বা ব্যর্থ pipe-এও) থামিয়ে দেয়, অর্ধেক-শেষ deploy নিয়ে এগিয়ে না গিয়ে।
</details>

<details>
<summary><strong>Q5. `php artisan config:cache`-এর পর তোমার code-এ `env('MY_KEY')` null ফেরত দেয়। কেন, আর সমাধান কী?</strong></summary>

Config cache হলে runtime-এ `.env` পড়া হয় না, তাই config ফাইলের বাইরে `env()` null দেয়। মানটা `config()` দিয়ে পড়ো (এমন config ফাইলে সেট করো যেখানে `env()` ব্যবহার হয়)।
</details>

<details>
<summary><strong>Q6. কী একটা backup-কে ভরসাযোগ্য করে?</strong></summary>

Server-এর বাইরে রাখা একটা কপি, আর এমন restore যা তুমি সত্যিই পরীক্ষা করেছ।
</details>

## বিনামূল্যের resource

- Laravel: [Deployment](https://laravel.com/docs/deployment)
- Laravel: [Queues](https://laravel.com/docs/queues) ও [Task scheduling](https://laravel.com/docs/scheduling)
- Laravel: [Configuration caching](https://laravel.com/docs/configuration#configuration-caching)
- Composer: [Download / installation](https://getcomposer.org/download/)

---

**Course শেষ।** Certificate পেতে website-এ course exam দাও।
