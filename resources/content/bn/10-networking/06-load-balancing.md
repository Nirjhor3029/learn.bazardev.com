# Chapter 6 — Load Balancing: L4 vs L7, Algorithm, আর Health Checks

> **Course:** Networking & Web Servers · **Chapter:** 6 of 8
> ওয়েবসাইটে পড়ুন: [learn.bazardev.com/networking/06-load-balancing](https://learn.bazardev.com/networking/06-load-balancing)
>
> 🇬🇧 **English পছন্দ?** [Read this chapter in English →](https://learn.bazardev.com/networking/06-load-balancing)

এই course-এ এখন পর্যন্ত প্রতিটা reverse proxy example-এ প্রতি path-এর পেছনে ঠিক একটা backend ছিল। একটা **load balancer** হলো এমন একটা reverse proxy যার একই route-এর পেছনে **একাধিক** backend থাকে, আর সে ঠিক করে প্রতিটা request কে পাবে — আর, সবচেয়ে গুরুত্বপূর্ণভাবে, বুঝতে পারে কখন তাদের একটা জবাব দেওয়া বন্ধ করে দিয়েছে। এই chapter-এ আছে একটা distinction যা ঠিক করে load balancer আসলে কতটা দেখতে পায় (L4 vs L7), backend বাছার algorithm, আর কীভাবে এটা একটা healthy backend আর একটা dead backend-এর মধ্যে পার্থক্য বোঝে — এই chapter-এর জন্য বানানো একটা real, live demo সহ।

## ১. L4 vs L7: load balancer আসলে কতটা বোঝে

এই course এখন পর্যন্ত যা করেছে — `Host` header দিয়ে route করা (Chapter 3), URL path দিয়ে route করা (Chapter 5) — সবকিছুতে request-টা নিজেই পড়তে হয়েছে: HTTP header, বা অন্তত hostname। এটা **Layer 7 (application layer)** load balancing। এটা free না: proxy-কে এই decision নেওয়ার জন্য HTTP (বা অন্তত TLS-এর `ClientHello`) বুঝতে হয়।

**Layer 4 (transport layer)** load balancing connection-এর ভেতরে একদমই তাকায় না — এটা শুধু IP address আর port দেখে, আর raw TCP বা UDP stream byte-for-byte forward করে দেয়। Nginx-এর `stream {}` block (এই course-এ সবখানে ব্যবহৃত `http {}` block থেকে আলাদা একটা module) ঠিক এটাই করে, সরাসরি [nginx.org](https://nginx.org/en/docs/stream/ngx_stream_core_module.html) থেকে:

```nginx
stream {
    upstream backend {
        server 127.0.0.1:12346;
    }

    server {
        listen 12345;
        proxy_pass backend;
    }
}
```

Nginx এই connection-এর ভেতরে কী আছে কখনো parse করে না — এটা শুধু client আর `backend`-এর মধ্যে byte চালাচালি করে। এটা দ্রুত আর বেশি general (এটা শুধু HTTP না, কোনো TCP/UDP protocol-এর জন্যই কাজ করে) কিন্তু Chapter 3 বা 5-এ যা করা হয়েছে তার কিছুই করতে পারে না: hostname দিয়ে route করা যায় না, path দিয়ে route করা যায় না, কারণ এগুলোর জন্য যেই content লাগে, এই layer-এ Nginx সেটার দিকেই তাকায় না।

জানার মতো আরেকটা trick আছে, যা সরাসরি Chapter 3-এর SNI chapter-এর সাথে জুড়ে যায়। `ssl_preread` module TLS-এর `ClientHello` থেকে **SNI hostname** পড়ে — Chapter 3-এ একটা web server যেই same unencrypted field certificate বাছার জন্য পড়তে দেখিয়েছিল ঠিক সেটাই — কিন্তু connection-টা একদমই decrypt বা terminate না করে:

```nginx
stream {
    map $ssl_preread_server_name $backend {
        app1.example.com  192.168.0.1:443;
        app2.example.com  192.168.0.2:443;
        default           192.168.0.3:443;
    }

    server {
        listen      443;
        proxy_pass  $backend;
        ssl_preread on;
    }
}
```

এটা Chapter 3-এর demo থেকে সত্যিই আলাদা একটা জিনিস: সেখানে, `learn.bazardev.com`-এর web server নিজেই TLS **terminate** করেছিল, একটা matching certificate বেছে traffic decrypt করে। এখানে, raw, এখনো-encrypted TLS byte সেই hostname-এর মালিক backend-এই forward করে দেওয়া হয় — load balancer কখনো certificate বা decrypted traffic দেখেই না। এটাকে বলে **TLS passthrough**, আর encrypted traffic সঠিক backend-এ route করার এই উপায়টা তখন কাজে লাগে যখন backend-টাকে (load balancer-কে না) private key রাখার কাজটা করতে হবে।

## ২. Load-balancing algorithm

একাধিক healthy backend থাকলে, কাকে পরের request দেওয়া হবে তা কিছু একটা ঠিক করতে হয়। Nginx-এর `upstream` block ([nginx.org](https://nginx.org/en/docs/http/load_balancing.html)) support করে:

| Method | Directive | কীভাবে বাছে |
|---|---|---|
| **Weighted round-robin** (default) | *(কিছু লাগে না)* | পর্যায়ক্রমে server-গুলো ঘুরে ঘুরে চালায়; একটা server-এ `weight=N` থাকলে তার share বেশি হয়, proportionally। |
| **Least connections** | `least_conn;` | এখন সবচেয়ে কম active connection থাকা server-কে পরের request দেয় — request-এর সময় অনেক ভিন্ন হলে কাজে লাগে। |
| **IP hash** | `ip_hash;` | Client-এর IP-র একটা hash সবসময় একই server বাছে — সহজ session persistence, যতক্ষণ সেই server healthy থাকে। |
| **Least time** | `least_time header\|last_byte;` | response time আর active connection-এর সবচেয়ে ভালো মিশ্রণ থাকা server বাছে। (nginx 1.31-এ open-source হয়েছে, মে ২০২৬-এ released — বছরের পর বছর এটা শুধু Nginx Plus-এর feature ছিল।) |

```nginx
upstream backend {
    server backend1.example.com weight=5;
    server backend2.example.com;
    server backend3.example.com;
}
```

কোনো method না বলায়, এটা round-robin: প্রতি ৭টা request-এর মধ্যে, ৫টা `backend1`-এ যায়, বাকি দুটো অন্যদের একটায় একটা, তার `weight=5`-এর জন্য।

**Caddy**-র `reverse_proxy`-তেও একই idea `lb_policy`-এর নিচে আছে, একটা real surprise সহ: Nginx default হয় round-robin, কিন্তু **Caddy default হয় `random`** — প্রতি request-এর জন্য একটা upstream এলোমেলোভাবে বাছা, যদি না তুমি অন্য কিছু বলো:

```
reverse_proxy 127.0.0.1:8081 127.0.0.1:8082 {
    lb_policy round_robin
}
```

Caddy-র option list Nginx-এর open-source version-এর চেয়ে বড় — `round_robin`, `least_conn`, আর `ip_hash` (আরেকটা proxy-র পেছনে থাকলে `client_ip_hash`, `trusted_proxies`-এর সাথে ব্যবহৃত) ছাড়াও, এটা `cookie`, `header`, বা `query` value দিয়েও stickiness দেয়, শুধু IP না।

## ৩. Health check: একটা live demo

একটা dead backend load balancer-এর list-এ থাকলে সেটা সত্যিই ক্ষতিকর — এটা traffic-এর একটা অংশ একটা black hole-এ পাঠাতেই থাকে। **Health check** হলো যা এটা বন্ধ করে দেয়, আর এখানে Nginx-এর open-source edition আর Caddy সত্যিই আলাদা।

**Nginx (open source) শুধু *passive* health check করে** — এটা একটা backend down আছে এটা real traffic fail হওয়া থেকে শেখে, `server` লাইনে `max_fails` আর `fail_timeout` দিয়ে:

```nginx
upstream backend {
    server backend1.example.com;
    server backend2.example.com max_fails=3 fail_timeout=30s;
}
```

৩০-সেকেন্ডের একটা window-এর মধ্যে ৩টা fail হওয়া attempt-এর পর, `backend2`-কে পরের ৩০ সেকেন্ডের জন্য unavailable মার্ক করা হয়। *Active* health check — real traffic থেকে স্বাধীন, নিয়মিত সময়ে একটা health endpoint-কে নিজে থেকে জিজ্ঞেস করা — Nginx-এ আছে, কিন্তু [শুধু commercial Nginx Plus subscription-এর অংশ হিসেবে](https://nginx.org/en/docs/http/ngx_http_upstream_module.html) (`health_check` directive আর `ngx_http_upstream_hc_module`), এই course-এ ব্যবহৃত free, open-source build-এ না।

**Caddy-র open-source `reverse_proxy`-তে দুটোই আছে, ফ্রিতে।** এই chapter-এর জন্য বানানো একটা real demo এখানে — দুটো ফেলে দেওয়ার মতো backend, আর active health check চালু করা একটা load balancer:

```
reverse_proxy 127.0.0.1:18081 127.0.0.1:18082 {
    lb_policy round_robin
    health_uri /
    health_interval 2s
    health_timeout 1s
}
```

(আগে আসলেই `caddy validate` দিয়ে check করা, তারপর আসলেই চালানো।) দুটো backend চালু থাকতে এটার বিরুদ্ধে ছয়টা request:

```bash
for i in 1 2 3 4 5 6; do curl -s http://127.0.0.1:18080/; done
```

```
backend-2
backend-1
backend-2
backend-1
backend-2
backend-1
```

পরিষ্কার round-robin alternation। তারপর load balancer চলতে থাকা অবস্থায়ই `backend-1`-কে সরাসরি (`pkill` দিয়ে) মেরে দেওয়া হয়েছিল। Caddy-র নিজের log, ২ সেকেন্ডের মধ্যে (configure করা `health_interval`), দেখায় তার active health checker নিজে থেকেই বুঝে ফেলেছে — কোনো client-এর fail হওয়া request থেকে না, তার নিজের background probe থেকে:

```
{"logger":"http.handlers.reverse_proxy.health_checker.active","msg":"HTTP request failed","host":"127.0.0.1:18081","error":"...connect: connection refused"}
```

আর আরো ছয়টা request, কোনো client-ই কখনো dead backend-এ না গিয়ে:

```
backend-2
backend-2
backend-2
backend-2
backend-2
backend-2
```

![Active health checks: the load balancer polls on its own schedule and removes a dead backend before a real client ever reaches it](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/10-networking/images/health-checks.svg)

কারো request-ই কখনো fail হয়নি — dead backend পরের client আসার আগেই rotation থেকে বাদ হয়ে গিয়েছিল, কারণ Caddy এটা *তার নিজের schedule-এ* বুঝে ফেলেছিল, একজন real user-এর request বলি দিয়ে বুঝতে হয়নি। এটাই active আর passive health checking-এর মধ্যে practical পার্থক্য, শুধু বর্ণনা না, দেখিয়ে দেওয়া হলো।

## ৪. Session persistence: কখন গুরুত্বপূর্ণ, আর এর সীমা

একটা app যদি session data শুধু যেই server login handle করেছিল তার memory-তেই রাখে, তাহলে সেই user-এর পরের প্রতিটা request **নিশ্চিতভাবে** সেই একই server-এ পৌঁছাতে হবে, না হলে তাকে logged out দেখাবে। `ip_hash` (Nginx) বা `client_ip_hash`/`cookie` (Caddy) এটা সমাধান করে, সবসময় একই client-কে একই backend-এ route করে।

কিন্তু এটা একটা workaround, fix না: এর মানে load আর সমানভাবে ছড়ায় না (একটা জনপ্রিয় office-এর ভাগাভাগি করা IP, বা একজন heavy user, একটা মাত্র backend-কে overload করতে পারে), আর যেই moment সেই specific backend down হয়ে যায়, তার সাথে pinned থাকা প্রতিটা client-ই যেকোনোভাবেই তার session হারায়। আসল fix — session storage প্রতিটা server-এর নিজের memory থেকে সরিয়ে এমন কিছুতে নেওয়া যা প্রতিটা backend reach করতে পারে (Redis, একটা shared database) — এই course-এর Advanced path আজ পর্যন্ত যা cover করে তার চেয়ে বড় একটা architectural বিষয়; এই chapter-এ শুধু বোঝা লাগবে *কেন* sticky session-এর দরকার পড়ে, আর এগুলো একটা patch, সমাধান না।

## ৫. মূল বিষয়গুলো (Key takeaways)

- **L4** load balancing content না পড়েই raw TCP/UDP forward করে — দ্রুত আর protocol-agnostic, কিন্তু hostname বা path দিয়ে route করতে পারে না। **L7** (এই course-এর বাকি সবকিছু) এই decision নেওয়ার জন্য request পড়ে।
- `ssl_preread` encrypted TLS traffic-কে তার SNI hostname দিয়ে route করতে পারে **decrypt না করেই** (TLS passthrough) — Chapter 3-এর demo থেকে সত্যিই একটা আলাদা জিনিস, যেখানে proxy নিজেই TLS terminate করেছিল।
- Nginx default হয় **weighted round-robin**-এ; `least_conn`, `ip_hash`, আর (nginx 1.31 থেকে, এখন open-source) `least_time`-ও দেয়। Caddy-র `lb_policy` এর বদলে default হয় **`random`**-এ, আর cookie/header/query-ভিত্তিক stickiness-ও যুক্ত করে।
- Open-source Nginx-এ শুধু **passive** health check আছে (`max_fails`/`fail_timeout`, real fail হওয়া request থেকে শেখা); active, নিয়মিত সময়ের health check Nginx Plus-এর জন্যই। Caddy-র open-source `reverse_proxy`-তে দুটোই আছে — এই chapter-এ live দেখানো হলো।
- Sticky session (`ip_hash`, cookie-ভিত্তিক stickiness) হলো সেই server-গুলোর জন্য একটা workaround যারা session state স্থানীয়ভাবে রাখে, shared session storage-এর বিকল্প না।

## ৬. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>প্রশ্ন ১. L4 আর L7 load balancing-এর মধ্যে মূল পার্থক্য কী?</strong></summary>

L4 content না পড়েই শুধু IP আর port দিয়ে raw TCP/UDP traffic forward করে। L7 এটা কোথায় যাবে তা ঠিক করতে request নিজেই পড়ে (HTTP header, hostname, path) — এজন্যই শুধু L7 `Host` header বা URL path দিয়ে route করতে পারে।
</details>

<details>
<summary><strong>প্রশ্ন ২. <code>ssl_preread</code>-ভিত্তিক SNI routing Chapter 3-এর SNI demo থেকে কীভাবে আলাদা?</strong></summary>

Chapter 3-এর demo-তে proxy নিজেই TLS terminate করেছিল, একটা matching certificate বেছে traffic decrypt করে। `ssl_preread` ClientHello থেকে একই SNI hostname পড়ে কিন্তু connection-টা এখনো encrypted অবস্থায়ই forward করে দেয় — load balancer কখনো certificate বা decrypted traffic রাখেই না বা দেখেই না।
</details>

<details>
<summary><strong>প্রশ্ন ৩. কোনো directive না দিলে, Nginx-এর default load-balancing method কোনটা?</strong></summary>

Weighted round-robin — request গুলো পর্যায়ক্রমে server-গুলো ঘুরে চলে, কোনো `weight=N` থাকলে সেই server-এর share proportionally বেশি হয়।
</details>

<details>
<summary><strong>প্রশ্ন ৪. Open-source Nginx-এর health checking কী করতে পারে **না**, যা Caddy-র open-source reverse_proxy পারে?</strong></summary>

Active health check — real traffic থেকে স্বাধীন, একটা fixed schedule-এ একটা health endpoint-কে নিয়মিত জিজ্ঞেস করা। Open-source Nginx-এ শুধু passive check আছে, real request fail হওয়া থেকে শেখা; active check Nginx Plus-এর (commercial) feature। Caddy-র open-source `reverse_proxy` সরাসরি active check support করে (`health_uri`, `health_interval`)।
</details>

<details>
<summary><strong>প্রশ্ন ৫. <code>ip_hash</code>-ভিত্তিক session persistence-কে কেন একটা fix-এর বদলে একটা workaround বলা হয়?</strong></summary>

এটা শুধু client-কে IP দিয়ে একটা backend-এ pin করে — load আর সমানভাবে ছড়ায় না, আর সেই specific backend down হয়ে গেলে, তার সাথে pinned সব client-ই তবু তাদের session হারায়। আসল fix হলো session storage প্রতিটা backend-এর reach করার মতো জায়গায় নেওয়া (Redis-এর মতো কিছু), client-দের এক server-এ pin করা না।
</details>

## ৭. ফ্রি রিসোর্স

- Nginx: [Load Balancing with NGINX](https://nginx.org/en/docs/http/load_balancing.html)
- Nginx: [Module ngx_http_upstream_module](https://nginx.org/en/docs/http/ngx_http_upstream_module.html)
- Nginx: [Module ngx_stream_core_module](https://nginx.org/en/docs/stream/ngx_stream_core_module.html)
- Nginx: [Module ngx_stream_ssl_preread_module](https://nginx.org/en/docs/stream/ngx_stream_ssl_preread_module.html)
- Caddy: [`reverse_proxy` directive — load balancing and health checks](https://caddyserver.com/docs/caddyfile/directives/reverse_proxy)

---

**পরের chapter:** [Chapter 7 — Debugging the network like a pro: ss, tcpdump, traceroute, and openssl](07-debugging-the-network-like-a-pro.md)
