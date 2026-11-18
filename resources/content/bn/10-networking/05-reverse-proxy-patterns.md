# Chapter 5 — Reverse Proxy Patterns: Path Routing, WebSockets, Caching, আর Rate Limiting

> **Course:** Networking & Web Servers · **Chapter:** 5 of 8
> ওয়েবসাইটে পড়ুন: [learn.bazardev.com/networking/05-reverse-proxy-patterns](https://learn.bazardev.com/networking/05-reverse-proxy-patterns)
>
> 🇬🇧 **English পছন্দ?** [Read this chapter in English →](https://learn.bazardev.com/networking/05-reverse-proxy-patterns)

Cloud Chapter 4 সবচেয়ে সহজ reverse proxy দেখিয়েছিল: এক domain, এক `proxy_pass`, সবকিছু এক backend-এ ফরওয়ার্ড করা। Real setup-এ আরো কিছু লাগে: এক domain-এ URL path দিয়ে ভাগ করা একাধিক backend, request/response-এর বদলে দীর্ঘ-স্থায়ী WebSocket connection, প্রতিবার backend-কে না জিজ্ঞেস করে তার response cache করা, আর একটা backend-কে অতিরিক্ত request-এ চাপা পড়ে যাওয়া থেকে রক্ষা করা। এই chapter-এ এই চারটাই আছে — Nginx-এর directive গুলো nginx.org-এর বিরুদ্ধে check করা, আর Caddy-র example গুলো আসলেই `caddy validate` দিয়ে validate করা (এই course লেখা হচ্ছে যেই machine-এ, তাতে Nginx install করা নেই, তাই তার example গুলো doc থেকে নেওয়া, চালিয়ে দেখা না; Caddy install করা আছে, তাই সেগুলো চালিয়ে দেখা হয়েছে)।

## ১. Path-based routing: এক domain, একাধিক backend

একটা single domain আলাদা আলাদা URL path-কে সম্পূর্ণ আলাদা backend-এ ফরওয়ার্ড করতে পারে — `/`-তে একটা PHP app, `/api/`-তে আলাদা একটা microservice, `/app/`-তে একটা Node app।

**Nginx** এটা করে একাধিক `location` block দিয়ে, প্রতিটার নিজের `proxy_pass` সহ:

```nginx
server {
    server_name myshop.com;

    location /api/ {
        proxy_pass http://127.0.0.1:8080/;   # trailing slash strips /api/ before forwarding
    }

    location /app/ {
        proxy_pass http://127.0.0.1:9090;    # no trailing slash keeps /app/ in the forwarded path
    }

    location / {
        root /var/www/myshop/public;
    }
}
```

এই এক-character-এর পার্থক্য (`proxy_pass`-এর URL-এ একটা trailing slash) ঠিক করে দেয় forward করার আগে matched prefix strip হবে কিনা — একটা খুবই common Nginx গোঁজামিল।

একাধিক `location` block একই request-এ match করতে পারলে, Nginx শুধু longest prefix ব্যবহার করে না — এটা একটা fixed order মানে, সরাসরি [official docs](https://nginx.org/en/docs/http/ngx_http_core_module.html#location) থেকে: একটা **exact match (`=`)** তাৎক্ষণিকভাবে জিতে যায়; না হলে **longest prefix** মনে রাখা হয়, আর সেই prefix-এ **`^~`** থাকলে, regex location গুলো পুরোপুরি skip হয়ে যায়; না হলে **regex location (`~`, `~*`)** গুলো file-এ যেই order-এ লেখা আছে সেই order-এ try করা হয়, আর প্রথম যেটা match করে সেটাই জেতে; উপরের কিছুই match না করলে, Nginx শুরুতে মনে রাখা longest prefix-এ ফিরে যায়।

![One domain, three paths: each location/handle block owns a different backend](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/10-networking/images/path-routing.svg)

**Caddy** ব্যবহার করে `handle_path` (matched prefix strip করে) বা `handle` (পুরো path রাখে), আর প্রতি request-এ শুধু একটাই চলে — Caddy একটা একটা করে try করে আর প্রথম match-এ থেমে যায়:

```
myshop.com {
    handle_path /api/* {
        reverse_proxy 127.0.0.1:8080
    }

    handle /app/* {
        reverse_proxy 127.0.0.1:9090
    }

    handle {
        root * /var/www/myshop/public
        file_server
    }
}
```

(আসলেই check করা: `caddy validate --config Caddyfile --adapter caddyfile` এই exact snippet-এ `Valid configuration` ফেরত দেয়।) `handle_path /api/*` হলো Nginx-এর trailing-slash trick-এর Caddy সংস্করণ — `127.0.0.1:8080`-এ request পৌঁছানোর আগেই `/api` prefix বাদ দেওয়া হয়।

## ২. WebSocket proxy করা: connection এক response-এর পরেই শেষ হয় না

একটা normal HTTP reverse proxy একটা request forward করে, একটা response পায়, আর কাজ শেষ। একটা **WebSocket** connection (live chat, notification, collaborative editing-এ ব্যবহৃত) শুরু হয় একটা normal HTTP request হিসেবে, কিন্তু তারপর **upgrade** হয়ে যায়: client একটা `Upgrade: websocket` header পাঠায়, server `101 Switching Protocols` জবাব দেয়, আর সেই মুহূর্ত থেকে একই TCP connection একটা দীর্ঘ-স্থায়ী, দুই-দিকের stream বহন করে — আর কোনো request/response pair না।

মাঝখানে বসে থাকা একটা reverse proxy-কে বলে দিতে হয় এই upgrade-টা একটা normal, কম-সময়ের request-এর মতো না ধরে, বরং tunnel করে দিতে। [nginx.org-এর নিজের WebSocket proxying guide](https://nginx.org/en/docs/http/websocket.html) থেকে: `Upgrade` আর `Connection` হলো *hop-by-hop* header, তাই Nginx default-এ এগুলো backend-এ forward করে **না** — এগুলো স্পষ্টভাবে forward করতে হয়:

```nginx
location /chat/ {
    proxy_pass http://backend;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
}
```

এই unconditional `"upgrade"` একই location-এ সাধারণ HTTP request ভেঙে দেয় (তারা একদমই কোনো `Upgrade` header পাঠায় না), তাই production config-এ সাধারণত একটা `map` যুক্ত করা হয়, client আসলেই চাইলে তখনই `"upgrade"` পাঠাতে:

```nginx
http {
    map $http_upgrade $connection_upgrade {
        default upgrade;
        ''      close;
    }
}
```

```nginx
proxy_set_header Connection $connection_upgrade;
```

একই docs থেকে আরেকটা real detail: Nginx default-এ backend থেকে **৬০ সেকেন্ড** নিস্তব্ধতার পর একটা proxied connection বন্ধ করে দেয় — সাধারণ request-এর জন্য ঠিক আছে, কিন্তু একটা WebSocket এর চেয়ে অনেক বেশি সময় idle থাকতে পারে। এটা ঠিক করো একটা বড় `proxy_read_timeout` দিয়ে, বা backend-কে নিয়মিত WebSocket ping frame পাঠাতে দিয়ে, যা সেই clock বারবার reset করে।

**Caddy-র এসব কিছুই লাগে না।** তার `reverse_proxy` directive WebSocket upgrade নিজেই চিনে নেয় আর tunnel করে দেয় — একই `reverse_proxy 127.0.0.1:8080` লাইন যা plain HTTP-এর জন্য ব্যবহার করতে, সেটাই এটাও handle করে ফেলে।

## ৩. একটা backend-এর response cache করা

একটা reverse proxy একটা backend-এর response ধরে রাখতে পারে আর পরের matching request সরাসরি memory বা disk থেকে দিতে পারে, backend-কে পুরোপুরি skip করে। **Nginx** এটা করে `proxy_cache_path` দিয়ে (cache কোথায় আর কত বড় হবে ঠিক করে — শুধু `http` context-এ) প্লাস `proxy_cache` আর `proxy_cache_valid` (per-location):

```nginx
http {
    proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=myshop:10m max_size=1g inactive=60m;

    server {
        location /api/ {
            proxy_pass http://127.0.0.1:8080/;
            proxy_cache myshop;
            proxy_cache_valid 200 302 10m;
            proxy_cache_valid 404 1m;
            add_header X-Cache-Status $upstream_cache_status;   # see what actually happened
        }
    }
}
```

এই `X-Cache-Status` header Nginx-এর নিজের `$upstream_cache_status` variable-কে প্রকাশ করে, যেখানে ঠিক এই value গুলো documented: `MISS` (cache-এ ছিল না, backend-কে জিজ্ঞেস করা হয়েছে), `HIT` (সরাসরি cache থেকে দেওয়া হয়েছে), `EXPIRED` (cache entry অনেক পুরনো ছিল, backend-কে আবার জিজ্ঞেস করা হয়েছে), `BYPASS` (একটা `proxy_cache_bypass` rule ইচ্ছাকৃতভাবে cache skip করেছে), `STALE`, `UPDATING`, আর `REVALIDATED` (expire হতে থাকা একটা entry refresh করার আশেপাশের বিশেষ ক্ষেত্র)। Test করার সময় এই header দেখা caching আসলেই যা ভাবছ তাই করছে কিনা confirm করার সবচেয়ে তাড়াতাড়ি উপায় — ধরে নিও না, check করো।

একটা response-এর নিজের `Cache-Control` বা `Expires` header (তোমার app সেট করা) `proxy_cache_valid`-কে **override** করে — তাই caching আসলে তোমার backend যা বলে আর proxy যা allow করতে configure করা তার মধ্যে একটা negotiation। `proxy_cache_bypass` ইচ্ছাকৃতভাবে cache skip করতে দেয় — common case একটা logged-in session, যেখানে `proxy_cache_bypass $cookie_session;` মানে ওই cookie বহন করা কোনো request সবসময় fresh backend-এ যায়।

**Caddy-র `reverse_proxy`-তে কোনো built-in response cache নেই** — তার নিজের official directive page-এ এটার কোনো উল্লেখই নেই। Caddy-তে একটা proxied response cache করতে হলে একটা module যুক্ত করতে হয় (official [`caddyserver/cache-handler`](https://github.com/caddyserver/cache-handler), Souin cache-এর উপর বানানো), `xcaddy` দিয়ে build করা, কারণ এটা Caddy core-এর অংশ না।

## ৪. Rate limiting: backend-কে বেশি request থেকে রক্ষা করা

একটা reverse proxy এটাও নিয়ন্ত্রণ করতে পারে একজন client কত তাড়াতাড়ি একটা backend-এ hit করতে পারবে — একটা misbehaving script, একটা scraper, বা একটা brute-force login attempt-এর বিরুদ্ধে কাজে লাগে। **Nginx**-এর `ngx_http_limit_req_module` ([nginx.org](https://nginx.org/en/docs/http/ngx_http_limit_req_module.html)) দুই ভাগে কাজ করে — কিছু একটা দিয়ে key করা একটা shared-memory zone (সাধারণত client IP), আর যেখানে চাও সেখানে একটা limit apply করা:

```nginx
http {
    limit_req_zone $binary_remote_addr zone=login:10m rate=5r/s;

    server {
        location /login {
            limit_req zone=login burst=10 nodelay;
            limit_req_status 429;
            proxy_pass http://127.0.0.1:8080;
        }
    }
}
```

- **`rate=5r/s`** — প্রতি key-এ (এখানে, প্রতি client IP-তে) allowed sustained rate।
- **`burst=10`** — পুরোপুরি reject হওয়ার আগে ওই rate-এর বেশি কত request queue হতে পারে।
- **`nodelay`** — এটা ছাড়া, burst-এর ভেতরের request গুলো *queue হয়ে, rate-এর সাথে মিলিয়ে ছড়িয়ে* দেওয়া হয়; এটা থাকলে, সেগুলো তখনই দিয়ে দেওয়া হয় (তবু ১০-এ capped), তাই একটা ছোট, legitimate spike অকারণে slow হয়ে যায় না।
- **`limit_req_status 429`** — সঠিক `429 Too Many Requests` পাঠাও, কারণ এই directive-এর নিজের default একটা plain `503`।

জানার মতো একটা real catch: `$binary_remote_addr` হলো Nginx client হিসেবে যেই IP দেখে। Nginx নিজে যদি আরেকটা proxy বা load balancer-এর পেছনে বসে থাকে, তাহলে সেটা load balancer-এরই IP, আসল visitor-এর না — তখন প্রতিটা আসল visitor একই rate-limit bucket ভাগ করে নেয়। Chapter 6 (load balancing) আর `ngx_http_realip_module` হলো সেই অবস্থায় আসল client IP ফিরে পাওয়ার উপায়।

**Caddy core-এও কোনো rate limiting নেই।** maintained option হলো [`mholt/caddy-ratelimit`](https://github.com/mholt/caddy-ratelimit) — Caddy-র নিজের creator-এর লেখা, `xcaddy build --with github.com/mholt/caddy-ratelimit` দিয়ে যুক্ত করা, যা named zone সহ একটা `rate_limit` directive দেয়। Caching-এর মতোই, এটা একটা module যুক্ত করতে হয়, `reverse_proxy` নিজে থেকে করে না।

## ৫. মূল বিষয়গুলো (Key takeaways)

- **Path-based routing** এক domain-কে আলাদা URL prefix আলাদা backend-এ forward করতে দেয়: Nginx একাধিক `location` block দিয়ে (`proxy_pass`-এ trailing-slash prefix-stripping গোঁজামিলটা খেয়াল রাখো), Caddy `handle_path` (prefix strip করে) বা `handle` (prefix রাখে) দিয়ে।
- Nginx overlap করা `location` block একটা fixed order-এ resolve করে: exact match → longest `^~` prefix → file order-এ regex → শুরুতে মনে রাখা longest plain prefix।
- **WebSocket** একটা HTTP connection-কে একটা দীর্ঘ-স্থায়ী tunnel-এ upgrade করে। Nginx-কে `Upgrade`/`Connection` header forward করতে বলতে হয় আর সাধারণত একটা বড় timeout লাগে; Caddy-র `reverse_proxy` WebSocket নিজেই handle করে।
- **Response caching** (Nginx-এ `proxy_cache_path`/`proxy_cache`/`proxy_cache_valid`) পুনরাবৃত্ত request-এর জন্য backend skip করে; `$upstream_cache_status` বলে দেয় একটা request `HIT`, `MISS`, নাকি অন্য কিছু ছিল — check করো, ধরে নিও না। Caddy core-এ এর কোনো built-in সমতুল্য নেই; একটা third-party module লাগে।
- **Rate limiting** (Nginx-এ `limit_req_zone`/`limit_req`) একটা key-এ (সাধারণত IP) একটা backend-এ hit করার গতি cap করে, `burst` আর `nodelay` দিয়ে spiky traffic কীভাবে handle হবে নিয়ন্ত্রণ করে। Caddy core-এও কিছু built in নেই; `caddy-ratelimit` হলো maintained option।
- Caching আর rate limiting দুটোই client IP বা এরকম কিছুর উপর key করে — তোমার proxy নিজেই আরেকটা layer-এর পেছনে থাকলে, তোমার **আসল** client IP লাগবে (Nginx-এর `realip` module), না হলে bucket ভুল address-এর উপর key হয়।

## ৬. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>প্রশ্ন ১. Nginx-এ, <code>proxy_pass http://backend/;</code>-এর URL-এ trailing slash থাকা আর <code>proxy_pass http://backend;</code>-এ (trailing slash ছাড়া) না থাকার effect কী?</strong></summary>

Trailing slash থাকলে, backend-এ forward করার আগে matched `location` prefix URL থেকে strip করা হয়। না থাকলে, পুরো original path (matched prefix সহ) যেমন আছে তেমনই forward করা হয়।
</details>

<details>
<summary><strong>প্রশ্ন ২. একটা request Nginx-এর <code>location ^~ /images/</code> আর <code>location ~* \.jpg$</code> দুটোতেই match করতে পারে। কোনটা জেতে?</strong></summary>

`^~` prefix location জেতে — এই modifier Nginx-কে বলে দেয়, একবার এটাই longest matching prefix হলে, একটা regex আসলে match করত কিনা তা না দেখেই regex location গুলো পুরোপুরি check করা বাদ দিয়ে দিতে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. অতিরিক্ত configuration যুক্ত না করলে একটা plain Nginx reverse proxy কেন WebSocket connection ভেঙে দেয়?</strong></summary>

`Upgrade` আর `Connection` হলো hop-by-hop header, তাই Nginx default-এ এগুলো backend-এ forward করে না। `proxy_set_header Upgrade $http_upgrade;` আর একটা মিলে যাওয়া `Connection` header ছাড়া, backend কখনো upgrade request দেখেই না আর connection-কে WebSocket tunnel-এ বদলাতে পারে না।
</details>

<details>
<summary><strong>প্রশ্ন ৪. তোমার Nginx response-এ <code>X-Cache-Status: EXPIRED</code> আছে। এটা তোমাকে কী বলে?</strong></summary>

এই request-এর জন্য একটা cached entry ছিল, কিন্তু সেটা তার allowed cache সময়ের (`proxy_cache_valid`) বাইরে চলে গিয়েছিল, তাই Nginx পুরনো copy না দিয়ে backend-কে আবার জিজ্ঞেস করেছে। এটা `MISS`-এর থেকে আলাদা (কিছুই cache করা ছিল না) আর `HIT`-এর থেকেও (একটা এখনো valid cached copy দেওয়া হয়েছে)।
</details>

<details>
<summary><strong>প্রশ্ন ৫. একটা Nginx <code>limit_req_zone</code> <code>$binary_remote_addr</code>-এর উপর key করে, কিন্তু Nginx নিজে একটা load balancer-এর পেছনে বসে আছে। কী ভুল হয়?</strong></summary>

Nginx প্রতিটা request-এর "client" হিসেবে load balancer-এর IP-কেই দেখে, তাই সব আসল visitor একটা মাত্র rate-limit bucket ভাগ করে নেয় — একজন busy বা malicious visitor বাকি সবাইকেও rate-limit করিয়ে দিতে পারে। আগে আসল client IP ফিরে পাওয়া লাগবে (যেমন `realip` module দিয়ে)।
</details>

## ৭. ফ্রি রিসোর্স

- Nginx: [Module ngx_http_core_module — the `location` directive](https://nginx.org/en/docs/http/ngx_http_core_module.html#location)
- Nginx: [WebSocket proxying](https://nginx.org/en/docs/http/websocket.html)
- Nginx: [Module ngx_http_proxy_module — caching directives](https://nginx.org/en/docs/http/ngx_http_proxy_module.html)
- Nginx: [Module ngx_http_limit_req_module](https://nginx.org/en/docs/http/ngx_http_limit_req_module.html)
- Caddy: [`handle_path` directive](https://caddyserver.com/docs/caddyfile/directives/handle_path)
- GitHub: [caddyserver/cache-handler](https://github.com/caddyserver/cache-handler)
- GitHub: [mholt/caddy-ratelimit](https://github.com/mholt/caddy-ratelimit)

---

**পরের chapter:** Chapter 6 — Load balancing: L4 vs L7, algorithms, and health checks *(coming soon)*
