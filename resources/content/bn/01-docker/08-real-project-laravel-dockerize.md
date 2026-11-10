# Chapter 8 — Real Project: একটা Laravel App Dockerize করা

> **Course:** Docker (Zero to Hero) · **Chapter:** 8 of 8 (শেষ চ্যাপ্টার!)
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/docker/08-real-project-laravel-dockerize)

এখানেই প্রতিটা chapter একসাথে আসছে। Multi-stage Dockerfile (Ch7), Compose (Ch6), volume আর networking (Ch5), caching আর layer (Ch3-4), non-root user আর healthcheck (Ch7) — সব কিছু, একটা real, কাজ-করা Laravel application-এ apply করা হচ্ছে।

## ১. একটা real Laravel deployment-এর আকৃতি

Production-এ একটা Laravel app কখনো শুধু "একটা container" হয় না। সাধারণত এটা **তিনটা আলাদা অংশ একসাথে কাজ করে**:

- **`app`** — PHP-FPM, যেটা আসলে তোমার Laravel code চালায়।
- **`webserver`** — nginx, যেটা আসা HTTP request গ্রহণ করে, static file (CSS, JS, images) সরাসরি serve করে, আর `.php` দিয়ে শেষ হওয়া যা কিছু `app`-এর কাছে forward করে দেয়।
- **`db`** — MySQL, তোমার application-এর data জমা রাখে।

(শুধু একটা container-এ `php artisan serve` চালালে কেন হয় না? কারণ এই built-in server explicitly documentation-এ development convenience হিসেবে বলা, real production traffic handle করার জন্য বানানো হয়নি। nginx + PHP-FPM হলো standard, বহু-পরীক্ষিত combination।)

![Laravel Dockerize overview diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/01-docker/images/laravel-dockerize-overview.svg)

## ২. Dockerfile — multi-stage, non-root, production-minded

```dockerfile
# Stage 1: Composer দিয়ে PHP dependencies install করা (এই stage কখনো ship হয় না)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

# Stage 2: আসল PHP-FPM runtime, যেটা ship হয়
FROM php:8.3-fpm-alpine
RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Laravel-কে runtime-এ এই দুটো ফোল্ডারে WRITE করতে হয় (logs, compiled views, cache)
RUN addgroup -S laravel && adduser -S laravel -G laravel \
    && chown -R laravel:laravel storage bootstrap/cache

# Production performance cache বেক করে দেওয়া (Chapter 7-এর "best practices" বাস্তবে প্রয়োগ)
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

USER laravel
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=3s CMD php artisan --version || exit 1
CMD ["php-fpm"]
```

এখানে প্রতিটা লাইনই তুমি আগে শিখেছো:
- **Stage 1 / Stage 2** — Chapter 7-এর ঠিক সেই multi-stage pattern: Composer আর তার cache কখনো final image-এ যায় না।
- **`chown -R laravel:laravel storage bootstrap/cache`** — এটাই real-world-এ Laravel-in-Docker-এর সবচেয়ে common bug: Laravel-কে চলার সময় `storage/` আর `bootstrap/cache/`-এ *write* করতে হয়, আর `USER laravel`-এ switch করার পরেও যদি এই ফোল্ডার গুলো `root`-এর থাকে, তাহলে প্রতিটা request permission error দিয়ে fail করে। User switch করার **আগে** ownership ঠিক করে নেওয়া — এই লাইনটা ঠিক এই কারণেই আছে।
- **`config:cache`, `route:cache`, `view:cache`** — এগুলো build time-এ বেক করে দিলে তোমার app production-এ প্রতিটা request-এ config/route file নতুন করে parse করে না — একটা real performance সুবিধা, "best practices" এর সঠিক উপায়ে করা: build time-এ, কেউ ভুলে যাওয়া একটা manual step হিসেবে না।
- **`HEALTHCHECK`** — সরাসরি Chapter 7 থেকে, যাতে Compose (এবং যেকোনো orchestrator) বুঝতে পারে app আসলে বেঁচে আছে কিনা, শুধু "চালু হয়েছে" তা না।

## ৩. nginx configuration (`docker/nginx.conf`)

```nginx
server {
    listen 80;
    index index.php index.html;
    root /var/www/html/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

একটা লাইন খেয়াল করে দেখার মতো: **`fastcgi_pass app:9000`**। কোনো IP address না, `localhost` না — শুধু `app`, service-এর নাম। এটাই Chapter 5 আর Chapter 6-এর lesson, এখন real ব্যবহারে: nginx আর PHP-FPM দুটো আলাদা container, এবং তারা একে অপরকে খুঁজে পায় শুধুমাত্র Compose তাদের যেই নাম দিয়েছে তার shared network-এর মাধ্যমে।

## ৪. `docker-compose.yml`

```yaml
services:
  app:
    build: .
    volumes:
      - app-code:/var/www/html
    env_file: .env
    environment:
      - DB_HOST=db
    depends_on:
      - db

  webserver:
    image: nginx:alpine
    ports:
      - "8000:80"
    volumes:
      - app-code:/var/www/html
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app

  db:
    image: mysql:8
    environment:
      - MYSQL_DATABASE=laravel
      - MYSQL_ROOT_PASSWORD=secret
    volumes:
      - db-data:/var/lib/mysql

volumes:
  app-code:
  db-data:
```

একটা বিষয় একটু সময় নিয়ে বোঝার মতো: **`app` আর `webserver` কেন একই `app-code` volume mount করে?** nginx-কে static file (CSS, JS, images) নিজেই efficiently serve করার জন্য `public/` ফোল্ডারে সরাসরি access লাগে, PHP-কে কষ্ট না দিয়ে — শুধু `.php` দিয়ে শেষ হওয়া request `app`-এর কাছে forward হয়। এই file গুলো serve করার জন্য nginx-এর কাছে `app`-এর মতো একই codebase disk-এ থাকা লাগবে।

এখানে Docker-এর একটা genuinely useful behavior কাজ করছে: যখন একটা **named volume** খালি থাকে আর এমন একটা path-এ mount হয় যেখানে image থেকে ইতিমধ্যে file আছে (এখানে, `/var/www/html`, Dockerfile-এর `COPY . .` দিয়ে populate করা), Docker automatically প্রথমবার সেই initial content volume-এ copy করে দেয়। এই কারণেই `webserver` — যেটা কখনো নিজের কোনো `COPY` চালায়নি — তোমার app-এর code serve করার জন্য পেয়ে যায়। এটাই Chapter 5-এর সেই `volumes:` mechanism, শুধু একটা নতুন, খুবই practical উপায়ে ব্যবহার করা হচ্ছে।

## ৫. চালিয়ে দেখা

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

`http://localhost:8000` ভিজিট করো — nginx তোমার Laravel app-এর static asset সরাসরি serve করছে, PHP request `app` container handle করছে, data `db`-র ভেতরে MySQL-এ জমা হচ্ছে, সাথে একটা persistent volume আছে, তাই `docker compose down` (`-v` ছাড়া!) কখনো তোমার data হারায় না।

এখন থেকে প্রায়ই লাগবে এমন কিছু command:

```bash
docker compose logs -f app         # Laravel-এর logs live দেখা
docker compose exec app bash       # app container-এ shell করা
docker compose exec app php artisan tinker   # Laravel-এর REPL, container-এর ভেতরে
docker compose down                # সব বন্ধ করা, data রেখে দেওয়া
```

## ৬. এখান থেকে কোথায় যাবে

এই setup-কে ঠিক যা আগে শিখেছো সেটাই দিয়ে extend করা যায়:
- **Redis যোগ করো** cache/queue-এর জন্য: আরেকটা service block, `image: redis:alpine`, আর `.env`-এ `CACHE_DRIVER=redis` — এটা automatically একই network-এ যুক্ত হয়ে যায়, ঠিক `db`-র মতোই।
- **একটা queue worker যোগ করো**: *একই* `app` image ব্যবহার করে একটা দ্বিতীয় service, কিন্তু default `CMD`-কে override করে `command: php artisan queue:work` দিয়ে।
- **একটা registry-তে image push করো** আর এই same `docker-compose.yml` (বা তার production equivalent) একটা real server-এ deploy করো — এই পুরো course-এর মূল কথাই হলো, এটা যেখানেই চালাও, একইভাবে কাজ করে।

## ৭. মূল কথাগুলো (Key Takeaways)

- একটা real Laravel deployment সাধারণত তিনটা container: nginx (`webserver`), PHP-FPM (`app`), আর একটা database (`db`) — একটা container-এ `php artisan serve` না।
- `USER` দিয়ে non-root user-এ switch করার **আগে** `storage/` আর `bootstrap/cache/` ফোল্ডারকে সেই user-এর নামে `chown` করো — এটাই real-world-এ সবচেয়ে common Docker+Laravel bug।
- `config:cache`, `route:cache`, আর `view:cache` build time-এ bake করা একটা genuine production performance practice, শুধু busywork না।
- nginx config-এর `fastcgi_pass app:9000` ঠিক Chapter 5 আর 6-এ শেখা সেই service-name networking, এখন real ব্যবহারে।
- দুটো service-এর মধ্যে শেয়ার করা, image-এর নিজের file দিয়ে pre-fill হওয়া একটা named volume — এটাই কিভাবে `webserver` কোনো duplicate ছাড়াই `app`-এর মতো একই codebase পায়।

## ৮. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. একটা real Laravel deployment সাধারণত একটার বদলে তিনটা container কেন ব্যবহার করে?</strong></summary>

কারণ `php artisan serve` একটা development convenience, production traffic-এর জন্য বানানো না। Standard production setup web server (nginx, static file serve করা আর request route করা)-কে PHP runtime (PHP-FPM, তোমার app code চালানো) থেকে আর database থেকে আলাদা রাখে — প্রতিটার নিজের একটা distinct, well-tested কাজ।
</details>

<details>
<summary><strong>প্রশ্ন ২. `chown -R laravel:laravel storage bootstrap/cache` কোন real bug আটকায়?</strong></summary>

Laravel-কে চলার সময় `storage/` (logs, cached views, uploaded files) আর `bootstrap/cache/` (compiled config/route cache)-এ write access লাগে। Container `USER` দিয়ে non-root user-এ switch করার সময় যদি এই ফোল্ডার গুলো এখনও `root`-এর থাকে, তাহলে সেখানে write করার চেষ্টা করা প্রতিটা request permission error দিয়ে fail করে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. nginx config-এ `fastcgi_pass app:9000` আসলে কীসের উপর নির্ভর করে?</strong></summary>

Docker Compose-এর automatic service-name networking-এর উপর (Chapter 5 আর 6): nginx আর PHP-FPM container দুটো আলাদা container, একই Compose-তৈরি network-এ, তাই nginx শুধু তার service name, `app`, একটা hostname হিসেবে ব্যবহার করে PHP-FPM-এ পৌঁছাতে পারে।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `app` আর `webserver` দুটোই কেন একই `app-code` volume mount করে?</strong></summary>

যাতে nginx (`webserver`) PHP-কে কোনোভাবে জড়িত না করেই CSS, JS-এর মতো static file সরাসরি codebase থেকে serve করতে পারে, আর তারপরও `.php` request `app`-এর কাছে forward করতে পারে। যেহেতু একটা fresh named volume প্রথমবার mount হওয়ার সময় image-এর নিজের file দিয়ে pre-fill হয়ে যায়, `webserver` নিজে কোনো `COPY` না চালিয়েও `app`-এর মতো একই codebase পেয়ে যায়।
</details>

## ৯. ফ্রি রিসোর্স (English)

- Official docs: [Dockerizing a PHP application](https://docs.docker.com/guides/php/)
- Official image docs: [`php` on Docker Hub](https://hub.docker.com/_/php) (এখানে ব্যবহৃত `-fpm-alpine` variant)
- Official image docs: [`nginx` on Docker Hub](https://hub.docker.com/_/nginx)

---

**এটাই সম্পূর্ণ course।** Image → container → volume → network → Dockerfile → Compose → best practices → একটা real deployment — তুমি zero থেকে একটা কাজ-করা, production-shaped setup পর্যন্ত পৌঁছেছো। 🎉

[learn.bazardev.com](https://learn.bazardev.com)-এ পরের topic: **Linux & Shell Basics** — শীঘ্রই আসছে।
