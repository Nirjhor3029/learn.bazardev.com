# Chapter 3 — Virtual Host আর SNI: একটা Server, অনেক Site

> **Course:** Networking & Web Servers · **Chapter:** 3 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/networking/03-virtual-hosts-and-sni)

Cloud Chapter 4-এ **একটা** domain, **একটা** web server-এ, **একটা** certificate দিয়ে setup করা হয়েছিল। বাস্তবে, একটা ছোট VPS সাধারণত অনেক site চালায় — এই course-এর নিজের server-ই চালায়, যা একটা ভালো real example দেয়। এই chapter দুটো mechanism নিয়ে যা এটা সম্ভব করে: plain HTTP-এর জন্য **name-based virtual host**, আর HTTPS-এর জন্য **SNI**।

## ১. Plain HTTP-এ virtual host: `Host` header

প্রতিটা HTTP request একটা `Host` header বহন করে — করতেই হয়, কারণ অনেক নাম একটা IP আর একটা port 80 ভাগ করতে পারে:

```
GET / HTTP/1.1
Host: pay.bazardev.com
```

একাধিক site configure করা একটা web server প্রতিটা site-এর জন্য একটা block রাখে আর সেই header দিয়ে মিল খুঁজে নেয়। Nginx-এ, প্রতিটা block তার উত্তর দেওয়া নাম list করে:

```nginx
server {
    listen 80;
    server_name learn.bazardev.com;
    root /var/www/learn/public;
}

server {
    listen 80;
    server_name pay.bazardev.com;
    root /var/www/pay/public;
}
```

Caddy একইরকম কাজ করে, শুধু domain-টাই block-এর নিজের heading (Cloud Chapter 4 আগেই একটা site-এর জন্য এটা দেখিয়েছে):

```
learn.bazardev.com {
    root * /var/www/learn/public
    file_server
}

pay.bazardev.com {
    root * /var/www/pay/public
    file_server
}
```

**যদি একটা request-এর `Host` কোনো configure করা site-এর সাথে না মেলে, কী হয়?** কেউ একদিন তোমার IP-তে সরাসরি, বা একটা random hostname দিয়ে, বা একটা পুরনো DNS record দিয়ে probe করবে যা এখনো এখানে point করে। Nginx চুপচাপ **প্রথম load হওয়া server block**-এ fallback করে যদি না তুমি অন্যথা বলো — যার মানে একটা অচেনা মানুষের ভুল request শুরুতে define করা যে কোনো site দিয়ে serve হতে পারে, এমনকি real content-ও যা তার দেখার কথা না। এর বদলে একটা block-কে explicit-ভাবে catch-all বলে দাও:

```nginx
server {
    listen 80 default_server;
    server_name _;
    return 444;   # Nginx-specific: close the connection, send nothing back
}
```

একটা address আর port-এর জন্য Nginx শুধু **একটাই** `default_server` অনুমতি দেয়। Caddy-এ এরকম চুপচাপ fallback নেই: যে request-এর `Host` কোনো site block-এর সাথে মেলে না, সেটা Caddy-এর routing থেকে এমনিতেই কোনো response পায় না, design করেই।

## ২. HTTPS-এর সমস্যা: কিছু পড়ার আগেই certificate বাছা

TLS যোগ করলে একটা নতুন সমস্যা আসে। HTTPS `Host` header-কেও সবকিছুর সাথে encrypt করে — কিন্তু encryption *শুরুই* হয় server আগে থেকেই একটা certificate ফিরিয়ে পাঠানোর পর, আর একাধিক site-এর একটা server-এ **প্রতি domain-এ একটা certificate**, পুরো IP-এর জন্য একটা না। তাহলে: server কীভাবে জানবে *কোনটা* certificate পাঠাবে, এমন কিছু পড়ার আগেই যা বলে দিত?

## ৩. SNI: encryption-এর আগে যাওয়া hostname

উত্তর হলো **SNI (Server Name Indication)**, একটা TLS extension, [RFC 6066](https://www.rfc-editor.org/rfc/rfc6066)-এ সংজ্ঞায়িত: browser-এর সবচেয়ে প্রথম message-টায় (**Client Hello** — Chapter 1-এর handshake শুরু করা একই message, এখন TLS parameter-ও বহন করে) hostname-টা থাকে যেটা চাই, **plain text-এ পাঠানো, কোনো encryption শুরু হওয়ার আগেই**। Server শুধু সেই একটা field পড়ে, মিলে যাওয়া certificate বাছে, আর বাকি handshake সাধারণভাবে চলে।

![SNI: একটা IP, একটা port 443, একটা certificate প্রতিটা request-এর জন্য বাছা](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/10-networking/images/sni-routing.svg)

এটাই internet-এর প্রতিটা multi-tenant server যেভাবে কাজ করে, এই course-এর নিজের server-ও সহ: `learn.bazardev.com`, `pay.bazardev.com` আর প্রতিটা অন্য `*.bazardev.com` site একই **IP address**-এ থাকে, প্রতিটার নিজের certificate, শুধু SNI দিয়ে আলাদা চেনা।

**নিজে দেখে নাও।** দুটো real request, একই IP, একই port, আলাদা `-servername` — প্রতিটা তার নিজের certificate পায়:

```bash
echo | openssl s_client -connect 167.86.127.108:443 -servername learn.bazardev.com 2>/dev/null \
  | openssl x509 -noout -subject
```

```
subject=CN=learn.bazardev.com
```

```bash
echo | openssl s_client -connect 167.86.127.108:443 -servername pay.bazardev.com 2>/dev/null \
  | openssl x509 -noout -subject
```

```
subject=CN=pay.bazardev.com
```

**এখন কোনো SNI ছাড়াই try করো** (এই chapter লেখার সময় এই course-এর নিজের server-এর বিরুদ্ধে সরাসরি test করা হয়েছে):

```bash
echo | openssl s_client -connect 167.86.127.108:443 2>&1 | head -3
```

```
Connecting to 167.86.127.108
...error:...SSL alert number 80
CONNECTED(00000003)
```

Handshake সরাসরি fail করে — `alert number 80` হলো TLS-এর সাধারণ `internal_error`। মেলানোর জন্য কোনো hostname না থাকলে, Caddy-এর কোনো certificate দেওয়ার নেই, তাই এটা handshake-ই শেষ করতে পারে না। (Nginx, একটা explicit default দিয়ে configure করা থাকলে, একটা fallback certificate দেখানো বেছে নিতে পারতো — কিন্তু যেভাবেই হোক, কিছু একটা SNI থেকেই ঠিক করতে হবে, বাকি কিছু পড়ার আগেই।)

## ৪. SNI-এর আগে: HTTPS site-প্রতি একটা IP

SNI প্রায় সর্বজনীন client support পেয়েছে 2010-এর দশকের শুরু-মাঝামাঝি। তার আগে (আর এখনো, দুর্লভ কোনো পুরনো client-এর জন্য), একটা server-এর কোনো উপায় ছিল না encrypted-কিন্তু-এখনো-decrypt-করা-যায়-না connection থেকে hostname পড়ার — তাই **HTTPS virtual hosting-এর জন্য প্রতি certificate-এ একটা IP address লাগতো**। IPv4 address-এর ঘাটতিই সরাসরি একটা কারণ SNI যথেষ্ট গুরুত্বপূর্ণ হয়ে সবখানে বানানো আর adopt হওয়ার: এটা "প্রতি HTTPS site-এ একটা IP"-কে "একটা IP, অসীম HTTPS site"-এ পরিণত করেছে, ঠিক যেভাবে port "প্রতি IP-তে একটা program"-কে অনেক program-এ পরিণত করে।

## ৫. জানার মতো একটা privacy note

SNI **unencrypted** পাঠানো হয়, design করেই — এটাই পুরো mechanism। মানে যে কেউ তোমার network traffic দেখতে পারে (একটা ISP, একটা network operator, একই Wi-Fi-তে যে কেউ) দেখতে পারে *কোন hostname* তুমি HTTPS-এও request করছো, যদিও তারা page-টা পড়তে পারে না। Browser আর কিছু provider **Encrypted Client Hello (ECH)** চালু করছে, যা আসল SNI-কে একটা বাইরের, শুরু থেকেই-encrypted hello-র ভেতরে মুড়ে দেয় — support এখনো বাড়ছে, তাই ধরে নিও না এটা এখনই একটা নির্দিষ্ট connection সুরক্ষিত করছে। এই course-এর জন্য, practical কথা সহজ: SNI হলো metadata যা একটা firewall বা proxy filter করতে পারে এমনকি request-টা না দেখেই, ঠিক এই কারণেই কিছু corporate আর national firewall IP-এর বদলে SNI hostname দিয়ে site block করে।

## ৬. মূল কথা

- Plain HTTP virtual host **`Host` header** দিয়ে মেলানো হয়; যেটা আগে load হয়েছিল তার উপর নির্ভর করার বদলে একটা explicit catch-all configure করো (Nginx-এ `default_server`)।
- HTTPS certificate বাছার আগে `Host` header পড়তে পারে না, কারণ encryption এখনো শুরুই হয়নি — **SNI** এটা সমাধান করে hostname plain text-এ পাঠিয়ে, Client Hello-এর ভেতরে।
- একটা IP, একটা port 443, আর **যতগুলো site configure করা আছে ততগুলো certificate** — এই course-এর নিজের multi-site server-এর বিরুদ্ধে `openssl s_client -servername` দিয়ে সরাসরি প্রমাণিত।
- কোনো মেলা SNI না থাকলে কোনো certificate দেওয়ার নেই — এই server-এ, এটা handshake-ই পুরো fail করে দেয়, কোনো কিছুতে fallback না করে।
- SNI unencrypted metadata; Encrypted Client Hello (ECH) হলো আসতে থাকা সমাধান, এখনো সবখানে নির্ভর করার মতো না।

## ৭. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. Port 80-তে একাধিক site থাকা একটা web server কীভাবে জানে একটা request কোন site-এর জন্য?</strong></summary>

Request-এর `Host` header থেকে, প্রতিটা site-এর configure করা নাম-এর সাথে মিলিয়ে।
</details>

<details>
<summary><strong>Q2. একাধিক site-এর একটা HTTPS server কেন শুধু `Host` header পড়ে certificate বাছতে পারে না, যেভাবে plain HTTP-এ করে?</strong></summary>

কারণ `Host` header encryption শুরু হওয়ার পরই পড়া যায়, আর encryption শুরু হতে পারে না যতক্ষণ server আগে থেকেই একটা certificate না পাঠিয়েছে — একটা chicken-and-egg সমস্যা।
</details>

<details>
<summary><strong>Q3. SNI আসলে কী পাঠায়, আর কখন?</strong></summary>

Request করা hostname, Client Hello-এর ভেতরে, plain text-এ, কোনো encryption শুরু হওয়ার আগেই — যথেষ্ট আগে যাতে server মেলা certificate বেছে নিতে পারে।
</details>

<details>
<summary><strong>Q4. দুটো আলাদা domain একই IP address-এ resolve করে আর দুটোই নিজের certificate দিয়ে valid HTTPS serve করে। এটা কীভাবে সম্ভব?</strong></summary>

SNI: server প্রতিটা connection-এর Client Hello থেকে hostname পড়ে আর সেই site-এর নিজের certificate দেখায়, যদিও দুই site-ই একটা IP আর একটা port 443 ভাগ করে।
</details>

<details>
<summary><strong>Q5. Nginx-এ, একটা request-এর `Host` header কোনো configure করা `server_name`-এর সাথে না মিললে, `default_server` set না করা থাকলে কী হয়?</strong></summary>

Nginx সেই address আর port-এর জন্য প্রথম load হওয়া server block-এ fallback করে — যা অনিচ্ছাকৃতভাবে একটা real site-এর content একটা না-মেলা request-কে দেখিয়ে দিতে পারে।
</details>

<details>
<summary><strong>Q6. SNI থাকার আগে HTTPS virtual hosting-এর জন্য প্রতি site-এ একটা IP কেন লাগতো?</strong></summary>

Handshake-এর আগে hostname পড়ার কোনো উপায় ছাড়া, server-এর কোনো উপায় ছিল না কোন certificate দেখাবে বাছার — তাই প্রতিটা certificate-এর নিজের IP address লাগতো আলাদা করে চিনতে।
</details>

<details>
<summary><strong>Q7. SNI দিয়ে পাঠানো hostname কি encrypted?</strong></summary>

না — এটা plain text-এ পাঠানো হয় Client Hello-এ, design করেই, যাতে server কোনো encryption শুরু হওয়ার আগেই এটা পড়তে পারে। এই কারণেই network path-এ একজন observer দেখতে পারে তুমি HTTPS-এও কোন hostname request করছো।
</details>

<details>
<summary><strong>Q8. এই course-এর নিজের server-এ, `openssl s_client` কোনো `-servername` ছাড়া connect করলে কী হলো?</strong></summary>

TLS handshake একটা `internal_error` alert দিয়ে fail করলো — কোনো configure করা site-এর সাথে মেলা hostname না থাকায়, Caddy-এর কোনো certificate দেওয়ার ছিল না আর handshake শেষ করতে পারলো না।
</details>

## ৮. বিনামূল্যের resource

- RFC Editor: [RFC 6066 — TLS Extensions: Server Name Indication](https://www.rfc-editor.org/rfc/rfc6066)
- Nginx docs: [Server names](https://nginx.org/en/docs/http/server_names.html)
- Caddy docs: [Automatic HTTPS](https://caddyserver.com/docs/automatic-https)
- Cloudflare: [What is SNI? How TLS server name indication works](https://www.cloudflare.com/learning/ssl/what-is-sni)
- IETF: [RFC 8744 — Issues and Requirements for SNI Encryption in TLS](https://www.rfc-editor.org/rfc/rfc8744) (উপরের privacy note-এর পেছনের context)

---

**পরের chapter:** [Chapter 4 — TLS deep dive: the handshake, certificate chains, and HSTS](04-tls-deep-dive.md)
