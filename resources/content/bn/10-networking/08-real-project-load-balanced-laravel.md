# Chapter 8 — Real Project: Load-Balanced, Multi-Domain Laravel Setup, TLS সহ

> **Course:** Networking & Web Servers · **Chapter:** 8 of 8
> ওয়েবসাইটে পড়ুন: [learn.bazardev.com/networking/08-real-project-load-balanced-laravel](https://learn.bazardev.com/networking/08-real-project-load-balanced-laravel)
>
> 🇬🇧 **English পছন্দ?** [Read this chapter in English →](https://learn.bazardev.com/networking/08-real-project-load-balanced-laravel)

Cloud Chapter 8 আগে থেকেই একটা Laravel app-কে কিছু না থেকে একটা কাজ করা, single-server production deploy-এ নিয়ে গেছে: server, domain, HTTPS, database, queue worker, scheduler, একটা deploy script, backup। **এই chapter ধরে নেয় সেই per-server setup আগে থেকেই করা আছে**, আর যা একটা মাত্র server যথেষ্ট না হলে বদলে যায় সেই একটা জিনিস বানায়: একটা load balancer-এর পেছনে একাধিক app server, একটার বেশি domain serve করা, TLS-এর উপর দিয়ে — Chapter 3-এর SNI, Chapter 5-এর reverse proxy pattern, আর Chapter 6-এর load balancing আর health check, সব একসাথে ব্যবহার করে। আর এই exact combination যেই bug তৈরি করে, যদি প্রস্তুত না থাকো, সেটা live, ইচ্ছাকৃতভাবে ধরে দেখায়।

## ১. Setup

![Two domains, one Caddy instance: app-a load-balanced across two backends with health checks; app-b a separate, unrelated app entirely](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/10-networking/images/capstone-architecture.svg)

নিচের সবকিছু এই chapter-এর জন্য আসলেই বানানো আর চালানো হয়েছে (শুধু বর্ণনা করা না): একটা Caddy instance, দুটো *আলাদা* domain-এর সামনে। `app-a.test` দুটো backend process-এ load-balanced, active health check সহ (Chapter 6); `app-b.test` একটা সম্পূর্ণ আলাদা, একক backend, প্রমাণ করে একাধিক domain আর একটা load-balanced pool একই proxy-তে একসাথে থাকতে পারে, একে অপরকে বাধা না দিয়ে:

```
{
    admin off
    http_port 19080
    https_port 19443
}

app-a.test {
    tls internal
    reverse_proxy 127.0.0.1:19081 127.0.0.1:19082 {
        lb_policy round_robin
        health_uri /session.php
        health_interval 2s
    }
}

app-b.test {
    tls internal
    reverse_proxy 127.0.0.1:19090
}
```

(`caddy validate` আগেই confirm করেছে এটা syntactically ঠিক, চালানোর আগেই।) `http_port`/`https_port` override শুধু এই কারণে আছে যে এই course লেখা হচ্ছে যেই machine-এ তার production Caddy আগে থেকেই port 80/443-এর মালিক — একটা real, dedicated server-এ, এই global block টা শুধু বাদ দেওয়া হয় আর Caddy সাধারণ 80/443 ব্যবহার করে। আগের প্রতিটা chapter-এর demo থেকে একটা real পার্থক্য: `tls internal` Caddy-কে বলে তার নিজের **local, locally-trusted CA** থেকে certificate ইস্যু করতে, Let's Encrypt-এর বদলে — `.test` domain-এর জন্য ঠিক choice যা public internet-এ আসলে নেই। একটা real production domain-এ, তুমি শুধু এই লাইনটা **লিখবেই না**, আর Caddy-র automatic HTTPS (Cloud chapter, আর এই course-এর Chapter 3-4) ঠিক `learn.bazardev.com`-এর মতোই একটা real Let's Encrypt certificate ইস্যু করে দেয়।

Proof যে দুটো domain সত্যিই আলাদা থাকে — প্রতিটা তার **নিজের** certificate পায়, SNI দিয়ে মিলানো (Chapter 3), live confirm করা:

```bash
openssl s_client -connect 127.0.0.1:19443 -servername app-a.test 2>/dev/null | openssl x509 -noout -ext subjectAltName
openssl s_client -connect 127.0.0.1:19443 -servername app-b.test 2>/dev/null | openssl x509 -noout -ext subjectAltName
```

```
X509v3 Subject Alternative Name: critical
    DNS:app-a.test
X509v3 Subject Alternative Name: critical
    DNS:app-b.test
```

একই IP, একই port, দুটো আলাদা certificate — ঠিক Chapter 3-এর lesson, এখন এমন একটা setup-এর ভেতরে চলছে যা load-balance-ও করছে, health-check-ও করছে।

## ২. এই combination যেই bug তৈরি করে — দেখানো হলো, শুধু বর্ণনা না

`app-a.test`-এর দুটো backend হলো দুটো ছোট PHP script, প্রতিটা একটা session শুরু করে আর একটা value মনে রাখে। একটা fresh request, fresh cookie jar সহ, load balancer-এর বিরুদ্ধে:

```bash
curl -sk --resolve app-a.test:19443:127.0.0.1 -c cookies.txt https://app-a.test:19443/session.php
```

```
handled by: backend-1
session value: set by backend-1
session id: 87e8672f0290a7a502cace1d5a417d3d
```

**ঠিক একই cookie jar**, আরো তিনবার পাঠানো — round-robin (Chapter 6) মানে load balancer প্রতি request-এ backend পাল্টায়:

```bash
curl -sk --resolve app-a.test:19443:127.0.0.1 -b cookies.txt https://app-a.test:19443/session.php
curl -sk --resolve app-a.test:19443:127.0.0.1 -b cookies.txt https://app-a.test:19443/session.php
curl -sk --resolve app-a.test:19443:127.0.0.1 -b cookies.txt https://app-a.test:19443/session.php
```

```
handled by: backend-2
session value: set by backend-2
session id: 87e8672f0290a7a502cace1d5a417d3d
handled by: backend-1
session value: set by backend-1
session id: 87e8672f0290a7a502cace1d5a417d3d
handled by: backend-2
session value: set by backend-2
session id: 87e8672f0290a7a502cace1d5a417d3d
```

ভালোভাবে পড়ো: **cookie-র session ID কখনো বদলায় না** — client সবকিছু ঠিকভাবেই করছে। কিন্তু **session value পাল্টে যায়**, কোন backend জবাব দিচ্ছে তার উপর নির্ভর করে। প্রতিটা backend-এর default, file-based session storage থাকে শুধু তার **নিজের** local disk-এ; `backend-2` কখনো `backend-1`-এর session file দেখেনি, তাই এটা error না দিয়ে চুপচাপ একই ID-র নিচে একটা নতুন session শুরু করে দেয়। একটা real Laravel app-এ, এটাই ঠিক সেই bug "আমি form পূরণ করলাম আর এটা বলে আমি logged out" বা একটা CSRF token mismatch যা *কখনো কখনো* হয় — request যেই backend-এ গিয়েছিল তার কাছে সেই session ছিলই না।

**Fix:** session storage-কে এমন কোথাও থাকতে হবে যেখানে প্রতিটা backend পৌঁছাতে পারে, কোনো একটা backend-এর নিজের disk-এ না। দুটো backend-কে একই shared session store-এ point করে, ঠিক একই request গুলো আবার করা:

```
handled by: backend-1
session value: set by backend-1
session id: c4bd9b8532c15bef15949b6db40b945d
handled by: backend-2
session value: set by backend-1
session id: c4bd9b8532c15bef15949b6db40b945d
handled by: backend-1
session value: set by backend-1
session id: c4bd9b8532c15bef15949b6db40b945d
handled by: backend-2
session value: set by backend-1
session id: c4bd9b8532c15bef15949b6db40b945d
```

`handled by` তবু পাল্টাতে থাকে — load balancer round-robin করা বন্ধ করেনি — কিন্তু `session value` এখন `set by backend-1`-এই থেকে যায়, কোন backend জবাব দিচ্ছে তা না দেখেই, কারণ দুটোই এখন একই জায়গা পড়ে আর লেখে। **এই exact demo নিয়ে পুরো honesty:** দুটো backend একই local directory ভাগ করায় এটা কাজ করেছে শুধু কারণ এগুলো একই machine-এর দুটো process। দুটো সত্যিই আলাদা server-এ, point করার মতো কোনো shared local directory থাকেই না — আসল fix হলো প্রতিটা server থেকে network-এর উপর দিয়ে পৌঁছানো যায় এমন একটা session store: Laravel-এর `.env`-এ `SESSION_DRIVER=redis` বা `SESSION_DRIVER=database`, `file` কখনোই না, যেই মুহূর্তে একটার বেশি app server থাকে। এটা ঠিক সেই sticky-session workaround যা Chapter 6 flag করেছিল (`ip_hash`/`cookie` stickiness একই সমস্যা fix না করে তার উপর একটা পর্দা ফেলে দেয়) — এখানে, এটা workaround না, আসল, underlying fix।

## ৩. এই demo-কে একটা real production setup-এ map করা

| এই chapter-এর demo-তে | একটা real production setup-এ |
|---|---|
| `tls internal`, একটা local CA | লাইনটা বাদ দাও; Caddy-র automatic HTTPS real Let's Encrypt certificate ইস্যু করে (Chapter 2-4) |
| একই machine-এ দুটো PHP process | দুই (বা তার বেশি) আলাদা server, প্রতিটা Cloud Chapter 8 অনুযায়ী আগে থেকেই পুরোপুরি setup করা |
| Session-এর জন্য একটা shared local directory | প্রতিটা server-এর `.env`-এ `SESSION_DRIVER=redis` বা `database`, একটা shared Redis/database instance-এ point করা |
| Proxying আর load balancing দুটোই করা একটা Caddy instance | একই — Caddy (বা Nginx) দুটোই করতে পারে; একটা dedicated load-balancer tier লাগে শুধু এই course যেই scale-এ পৌঁছায়নি তাতে |
| ম্যানুয়ালি `curl` চালিয়ে behavior check করা | কিছু ভুল হলে live diagnose করার জন্য Chapter 7-এর tool (`ss -ti`, `tcpdump`, `mtr`, `openssl s_client`) |
| একটা plain `/session.php`-তে health check | একটা real, সস্তা health endpoint — Monitoring Chapter 8 আগে থেকেই একটা Laravel app-এর জন্য ঠিক এই রকম একটা check বানিয়েছিল |

Demo আর real জিনিসটার মধ্যে কোনো concept-ই বদলায় না — এখানের প্রতিটা অংশ একটা আগের chapter-এর সরাসরি, নামে উল্লেখ করা callback, শুধু বর্ণনা না করে আসলেই চালিয়ে।

## ৪. মূল বিষয়গুলো (Key takeaways)

- একাধিক domain-এর সামনে, আর প্রতি domain-এ একাধিক backend সহ, একটা load balancer হলো ঠিক Chapter 3-এর SNI routing আর Chapter 6-এর load balancing একই সাথে চলা — এগুলো একে অপরকে বাধা দেয় না।
- `tls internal` local testing-এর জন্য একটা legitimate Caddy feature, fake domain-এর বিরুদ্ধে; একটা real production domain শুধু এটা বাদ দেয় আর automatic-ই একটা real Let's Encrypt certificate পায়।
- **File-based session storage ভেঙে যায় যেই মুহূর্তে একটার বেশি app server থাকে**, load balancer পুরোপুরি ঠিকভাবে কাজ করলেও — এখানে দেখানো হয়েছে, একই session ID, কোন backend জবাব দিচ্ছে তার উপর নির্ভর করে আলাদা value দিয়ে।
- Fix হলো একটা session store যা প্রতিটা backend network-এর উপর দিয়ে পৌঁছাতে পারে (Redis, একটা shared database) — IP-ভিত্তিক stickiness না, যা একবারে শুধু একজন user-এর জন্য সমস্যা ঢেকে রাখে, আর সেই backend down হয়ে গেলে তবু ভেঙে যায়।
- একটা real, multi-server, multi-domain Laravel setup-এর প্রতিটা অংশ সরাসরি এই course-এর আগেই cover করা একটা chapter-এ map হয় — এই chapter হলো যেখানে এগুলো একসাথে চালানো হয়, নতুন concept শুরু হওয়ার জায়গা না।

## ৫. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>প্রশ্ন ১. এই chapter-এর demo-তে, session ID request-এ request-এ একই থাকে কিন্তু session value কেন বদলায়?</strong></summary>

Client প্রতিবার একই cookie (session ID) পাঠায়, ঠিকভাবেই। কিন্তু প্রতিটা backend session শুধু তার নিজের local disk-এ রাখে — যেই backend সেই ID-র file কখনো দেখেনি সে error না দিয়ে একই ID-র নিচে একটা নতুন session তৈরি করে, তাই *value* backend অনুযায়ী আলাদা হয়, যদিও *ID* কখনো আলাদা হয় না।
</details>

<details>
<summary><strong>প্রশ্ন ২. এই chapter-এ দেখানো session bug-এর আসল fix কী, আর স্পষ্টভাবে কী fix না?</strong></summary>

Fix হলো network-এর উপর দিয়ে প্রতিটা backend পৌঁছাতে পারে এমন session storage (Redis বা একটা shared database, যেমন `SESSION_DRIVER=redis`)। IP-ভিত্তিক sticky session (Chapter 6) স্পষ্টভাবে একটা fix না — এগুলো শুধু একজন user-কে একই backend-এ ফিরে route করে, আর সেই specific backend down হয়ে গেলে ওই user-এর session তবু হারায়।
</details>

<details>
<summary><strong>প্রশ্ন ৩. কেন <code>tls internal</code> এই chapter-এর demo-র জন্য ঠিক কিন্তু একটা real production domain-এর জন্য না?</strong></summary>

`tls internal` Caddy-র নিজের local CA থেকে certificate ইস্যু করে, যা কোনো real browser default-এ trust করে না — fake `.test` domain-এ testing-এর জন্য ব্যবহার করলে ঠিক আছে। একটা real production domain-এর একটা publicly trusted certificate automatic-ই পাওয়া উচিত Caddy-র সাধারণ automatic HTTPS (Let's Encrypt) দিয়ে, যার জন্য শুধু `tls internal` যুক্তই না করলে হয়।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একই Caddy instance-এ দুটো domain, প্রতিটা আলাদা backend pool-এর দিকে point করা। এগুলো একে অপরকে বাধা দেয় না কেন?</strong></summary>

Caddy প্রতি request-এ কোন site block apply হবে তা বাছে SNI দিয়ে (TLS ClientHello-এর hostname, Chapter 3) — প্রতিটা domain-এর traffic তার নিজের site block আর নিজের backend pool-এ আলাদাভাবে মেলানো হয়, যতই অন্য কতগুলো domain একই IP আর port ভাগ করুক না কেন।
</details>

<details>
<summary><strong>প্রশ্ন ৫. এই রকম একটা load-balanced setup-এর জন্য reverse proxy থেকে আলাদা, একটা dedicated load-balancer machine লাগে কি?</strong></summary>

অবশ্যই লাগে না। একই Caddy (বা Nginx) instance যা TLS terminate করে আর domain দিয়ে route করে, সেটাই backend-এর মধ্যে load-balance-ও করতে পারে — একটা process দুটোই কাজ করছে, যেমন এই chapter-এর demo দেখিয়েছে। একটা dedicated load-balancer tier কাজে লাগে তখনই যখন এই course এখনো যেই scale-এ পৌঁছায়নি তার চেয়ে অনেক বড় হয়ে যায়।
</details>

## ৬. ফ্রি রিসোর্স

- Caddy: [the `tls` directive, including `internal`](https://caddyserver.com/docs/caddyfile/directives/tls)
- Laravel: [Session configuration](https://laravel.com/docs/session)
- Laravel: [Redis](https://laravel.com/docs/redis)

---

**Course complete.** ওয়েবসাইটে course exam দিয়ে তোমার certificate অর্জন করো।
