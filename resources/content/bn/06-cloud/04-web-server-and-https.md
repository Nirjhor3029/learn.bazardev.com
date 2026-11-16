# Chapter 4 — HTTPS-সহ Web Server

> **Course:** Cloud (AWS / VPS Basics) · **Chapter:** 4 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cloud/04-web-server-and-https)

তোমার এখন একটা সুরক্ষিত server (Chapter 2) আর সেদিকে মুখ করা একটা domain (Chapter 3) আছে। একটা অংশ বাকি: এমন একটা program যা **80 ও 443 port-এ শোনে আর web request-এর উত্তর দেয়**। সেই program-টাই **web server**, আর এই chapter-এ তুমি তাকে **HTTPS** বলাও শেখাবে।

## ১. Web server কী করে

Web server:

1. একটা port-এ **শোনে** (HTTP-র জন্য 80, HTTPS-এর জন্য 443)।
2. **Request পড়ে**, কোন hostname চাওয়া হয়েছে (`myshop.com` না `api.myshop.com`) সহ।
3. **উত্তর দেয়**: একটা ফাইল পাঠিয়ে, তোমার app-এ (PHP-FPM, Node, Docker container) request পাঠিয়ে, বা redirect করে।

দুটো জনপ্রিয় পছন্দ:

| | **Nginx** | **Caddy** |
|---|---|---|
| জনপ্রিয়তা | অত্যন্ত প্রচলিত, বিশাল community | নতুন, দ্রুত বাড়ছে |
| HTTPS certificate | তুমি বসাও (certbot দিয়ে) | **স্বয়ংক্রিয়**, বিল্ট-ইন |
| Config-এর ধরন | `server { ... }` block | ছোট `site { ... }` block |

আমরা দুটোই দেখাই। একটা server-এ একটাই বাছো, দুটো নয় (ওরা 80 আর 443 port নিয়ে ঝগড়া করবে)।

## ২. বিকল্প A: Nginx

Install করো আর দেখো:

```bash
sudo apt update
sudo apt install nginx
systemctl status nginx
```

Browser-এ `http://YOUR_SERVER_IP` খোলো: "Welcome to nginx" page দেখা উচিত। (না দেখলে UFW আর provider-এর firewall-এ port 80 অনুমোদিত কিনা দেখো: Chapter 2।)

**তোমার নিজের site।** Ubuntu-তে প্রতিটা site-এর একটা ফাইল থাকে `/etc/nginx/sites-available/`-এ, আর `sites-enabled/`-এ তার একটা link সেটা চালু করে। Laravel app-এর উদাহরণ:

```nginx
# /etc/nginx/sites-available/myshop
server {
    listen 80;
    server_name myshop.com www.myshop.com;

    root /var/www/myshop/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # তোমার PHP version-এর সাথে মেলাও
    }

    location ~ /\.(?!well-known) {
        deny all;                                      # .env আর তার মতো ফাইল কখনো দেখাবে না
    }
}
```

চালু করো, **config পরীক্ষা করো**, তারপর reload:

```bash
sudo ln -s /etc/nginx/sites-available/myshop /etc/nginx/sites-enabled/
sudo nginx -t                     # সবসময় আগে এটা চালাও
sudo systemctl reload nginx
```

`nginx -t` প্রয়োগ করার *আগে* syntax পরীক্ষা করে। ভাঙা config দিয়ে reload করলে তোমার site-গুলো বসে যেতে পারে, তাই `-t` অভ্যাস করো।

**Reverse proxy।** তোমার app কোনো local port-এ চললে (যেমন `127.0.0.1:8080`-এ publish করা Docker container), Nginx ফাইল দেওয়ার বদলে request সেখানে পাঠায়:

```nginx
server {
    listen 80;
    server_name app.myshop.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

অনেক app এভাবেই এক server ভাগ করে: web server একমাত্র সদর দরজা, আর প্রতিটা app নিজের local port-এ গোপনে শোনে। (Chapter 2 থেকে মনে রেখো: ওই app port-গুলো firewall-এ বন্ধ রাখো; শুধু Nginx public।)

## ৩. বিকল্প B: Caddy

Caddy-র config খুব কম লাগে, আর HTTPS certificate **নিজে নিজে** আনে।

```bash
sudo apt install caddy      # Caddy-র apt repository লাগতে পারে; install docs দেখো
```

একই Laravel site-এর জন্য একটা `Caddyfile` (সাধারণত `/etc/caddy/Caddyfile`):

```
myshop.com, www.myshop.com {
    root * /var/www/myshop/public
    encode zstd gzip
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}
```

আর reverse proxy-র জন্য:

```
app.myshop.com {
    reverse_proxy 127.0.0.1:8080
}
```

পরিবর্তন প্রয়োগ করো:

```bash
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

তোমার DNS আগে থেকেই server-এর দিকে থাকলে আর 80/443 port খোলা থাকলে Caddy প্রথম request-এই certificate আনে। কোনো বাড়তি ধাপ ছাড়াই HTTPS পাও। (পরের দুটো অংশ বাদ দিতে পারো।)

## ৪. HTTPS কী

**HTTP** সবকিছু সাধারণ লেখা হিসেবে পাঠায়। পথের যে কেউ (café-র Wi-Fi, একটা ISP) তা পড়তে বা বদলাতে পারে। **HTTPS** হলো **TLS**-এর ভেতরে HTTP, যা দেয়:

- **Encryption:** মাঝখানে কেউ data পড়তে পারে না।
- **Integrity:** কেউ না জানিয়ে তা বদলাতে পারে না।
- **Identity:** padlock মানে server সত্যিই ওই domain-এর মালিক।

![HTTP থেকে HTTPS-এ redirect; TLS শেষ হয় web server-এ](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/06-cloud/images/https-flow.svg)

Identity আসে **certificate** থেকে, একটা ফাইল যা **Certificate Authority (CA)** সই করে, যাকে browser আগে থেকেই বিশ্বাস করে। **Let's Encrypt** একটা বিনামূল্যের, স্বয়ংক্রিয় CA, আর এ কারণেই HTTPS-এ এখন টাকা লাগে না।

আজকাল HTTPS প্রত্যাশিত: browser সাধারণ HTTP form-এ সতর্কতা দেখায়, আর অনেক feature (service worker, geolocation, payment) এটা চায়।

## ৫. Nginx আর certbot দিয়ে বিনামূল্যের HTTPS

শর্ত: domain-এর A record আগে থেকেই এই server-এর দিকে (Chapter 3), আর port 80 ও 443 খোলা। Let's Encrypt domain-এ একটা request করে প্রমাণ নেয় যে তুমি domain নিয়ন্ত্রণ করো, তাই আগে DNS ঠিক থাকতে হবে।

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d myshop.com -d www.myshop.com
```

Certbot একটা email চায় (মেয়াদের নোটিসের জন্য), certificate আনে, তোমার Nginx config **edit করে** 443-এ শোনাতে, আর **HTTP থেকে HTTPS-এ redirect** করার প্রস্তাব দেয়। হ্যাঁ বলো।

Certificate-এর মেয়াদ **৯০ দিন**। Certbot নিজে renew করতে একটা timer বসায়। শুধু ভরসা করো না, পরীক্ষা করো:

```bash
sudo certbot renew --dry-run
systemctl list-timers | grep certbot
```

Dry run সফল হলে renewal কাজ করবে।

## ৬. সমস্যা সমাধান

| লক্ষণ | সম্ভাব্য কারণ | কী দেখবে |
|---|---|---|
| Page কখনো লোড হয় না | Firewall বা DNS | `sudo ufw status`, provider firewall, `dig +short myshop.com` |
| **502 Bad Gateway** | Proxy-র পেছনের app চলছে না বা port ভুল | Container/PHP-FPM চলছে কি? server-এ `curl http://127.0.0.1:8080` |
| **404 Not Found** | ভুল `root`, বা ভুল site উত্তর দিয়েছে | `root` path; `server_name` hostname-এর সাথে মেলে কিনা |
| **403 Forbidden** | ফাইলের permission | Web user (`www-data`) folder পড়তে পারে কি? |
| Certbot ব্যর্থ | DNS এখনও এদিকে নেই, বা port 80 আটকানো | `dig +short`, `ufw status` |
| HTTPS-এর পরেও browser "not secure" দেখায় | Mixed content: page একটা `http://` ছবি বা script লোড করছে | `https://` বা relative URL ব্যবহার করো |

প্রথমে সবসময় log দেখো:

```bash
sudo tail -n 50 /var/log/nginx/error.log
sudo journalctl -u caddy --since "10 minutes ago"
```

## ৭. মূল কথা

- **Web server** 80/443-এ শোনে, hostname পড়ে, আর ফাইল দেয় বা request তোমার app-এ পাঠায়।
- **Nginx** প্রচলিত পছন্দ (config block, HTTPS-এর জন্য certbot)। **Caddy** সহজ আর HTTPS **স্বয়ংক্রিয়ভাবে** করে। এক server-এ একটাই ব্যবহার করো, দুটো নয়।
- Reload-এর আগে সবসময় **config পরীক্ষা করো** (`nginx -t`, `caddy validate`)।
- **Reverse proxy** অনেক app-কে এক server ভাগ করতে দেয়, প্রতিটা নিজের গোপন local port-এ।
- **HTTPS** = HTTP + TLS: **Let's Encrypt**-এর মতো CA-র certificate দিয়ে encryption, integrity আর identity (বিনামূল্যের, ৯০ দিন, স্বয়ংক্রিয় renew)।
- Certificate নেওয়ার আগে DNS আগে থেকেই server-এর দিকে থাকতে হবে, আর 80 ও 443 খোলা।
- কিছু ভাঙলে আগে **error log** দেখো।

## ৮. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. Web server-এর তিনটা কাজ কী?</strong></summary>

একটা port-এ শোনা, request পড়া (কোন hostname চাওয়া হয়েছে সহ), আর উত্তর দেওয়া: ফাইল দেওয়া, app-এ request পাঠানো, বা redirect করা।
</details>

<details>
<summary><strong>Q2. `systemctl reload nginx`-এর আগে `nginx -t` চালাই কেন?</strong></summary>

এটা configuration-এ ভুল আছে কিনা দেখে। ভাঙা config reload করলে তোমার site বসে যেতে পারে।
</details>

<details>
<summary><strong>Q3. Reverse proxy কী করে?</strong></summary>

Public port-এ request নেয় আর local port-এ গোপনে শোনা app-এ পাঠায়, তাই অনেক app এক server ও এক সদর দরজা ভাগ করতে পারে।
</details>

<details>
<summary><strong>Q4. HTTPS (TLS) তোমাকে কোন তিনটা জিনিস দেয়?</strong></summary>

Encryption, integrity (না জানিয়ে বদল হয় না) আর identity (server সত্যিই domain-এর মালিক)।
</details>

<details>
<summary><strong>Q5. Certbot certificate আনতে ব্যর্থ। সবচেয়ে সম্ভাব্য দুটো কারণ কী?</strong></summary>

Domain-এর DNS এখনও এই server-এর দিকে নেই, অথবা port 80/443 firewall-এ আটকানো।
</details>

<details>
<summary><strong>Q6. তুমি 502 Bad Gateway দেখছ। সাধারণত এর মানে কী?</strong></summary>

Web server ঠিক আছে, কিন্তু যে app-এ সে পাঠায় সেটা চলছে না বা প্রত্যাশিত port-এ নেই।
</details>

## ৯. বিনামূল্যের resource

- Nginx: [Beginner's guide](https://nginx.org/en/docs/beginners_guide.html)
- Caddy: [Documentation](https://caddyserver.com/docs/) ও [Automatic HTTPS](https://caddyserver.com/docs/automatic-https)
- Let's Encrypt: [How it works](https://letsencrypt.org/how-it-works/)
- Certbot: [Instructions](https://certbot.eff.org/instructions) (তোমার software আর OS বাছো)
- Mozilla: [SSL Configuration Generator](https://ssl-config.mozilla.org/)

---

**Next:** Chapter 5 — AWS-এর মূল ধারণা: account, region আর IAM *(coming soon)*
