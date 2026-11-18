# Chapter 4 — TLS Deep Dive: Handshake, Certificate Chain, আর HSTS

> **Course:** Networking & Web Servers · **Chapter:** 4 of 8
> ওয়েবসাইটে পড়ুন: [learn.bazardev.com/networking/04-tls-deep-dive](https://learn.bazardev.com/networking/04-tls-deep-dive)
>
> 🇬🇧 **English পছন্দ?** [Read this chapter in English →](https://learn.bazardev.com/networking/04-tls-deep-dive)

Cloud Chapter 4-এ certbot বা Caddy দিয়ে কাজ করা `https://` site পেয়েছিলে, আর জেনেছিলে TLS encryption, integrity, আর identity দেয়। এই course-এর Chapter 3 দেখিয়েছিল server কীভাবে সঠিক certificate বাছে (SNI)। এই chapter-এ দেখবে সেটার পরে আসলে কী হয়: plain TCP connection-কে encrypted connection-এ পরিণত করা handshake, certificate **chain** আসলে কী, আর HSTS নামের header টা, যা অনেক site-ই যুক্त করতে ভুলে যায়।

## ১. TLS 1.3 handshake, এক round trip-এ

Chapter 1-এ TCP-র three-way handshake দেখিয়েছিলাম — SYN, SYN-ACK, ACK — যা শুধু একটা reliable pipe খোলে, তখনো কিছু encrypted না। TLS সেই pipe-এর **ভেতরে**, ঠিক তার পরেই চলে। নিচে এই course-এর নিজের server-এর বিরুদ্ধে live capture করা একটা real handshake, `openssl`-এর protocol-debug flag চালু করে:

```bash
echo | openssl s_client -connect learn.bazardev.com:443 -servername learn.bazardev.com -tls1_3 -msg -state
```

```
>>> TLS 1.3, Handshake, ClientHello
<<< TLS 1.3, Handshake, ServerHello
<<< TLS 1.3, ChangeCipherSpec                 (compatibility only — see below)
<<< TLS 1.3, Handshake, EncryptedExtensions
<<< TLS 1.3, Handshake, Certificate
<<< TLS 1.3, Handshake, CertificateVerify
<<< TLS 1.3, Handshake, Finished
>>> TLS 1.3, ChangeCipherSpec
>>> TLS 1.3, Handshake, Finished
SSL_connect:SSL negotiation finished successfully
```

(Real output, readability-এর জন্য বার বার আসা low-level `RecordHeader`/`InnerContent` লাইনগুলো বাদ দিয়ে — শুধু আসল handshake message গুলো রাখা হয়েছে, server যেই order-এ পাঠিয়েছে ঠিক সেই order-এ।)

![TLS 1.3: a full handshake in one round trip — ClientHello with key_share, then ServerHello through Finished in a single encrypted flight](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/10-networking/images/tls-handshake.svg)

প্রতিটা message আসলে কী করে পড়ো, [RFC 8446](https://www.rfc-editor.org/rfc/rfc8446)-এ define করা:

1. **ClientHello** — browser পাঠায় কোন কোন TLS version আর cipher suite support করে, SNI দিয়ে hostname (Chapter 3), আর একটা **`key_share`**: একটা fresh, one-time Diffie-Hellman key exchange-এর নিজের অর্ধেক। এটাই সেই নতুন idea যা বাকি সবকিছু সম্ভব করে: client server-কে জিজ্ঞেস করার জন্য অপেক্ষা করে না — server যেই key-exchange method বাছবে তা guess করে নিজের share তখনই পাঠিয়ে দেয়।
2. **ServerHello** — server একটা version আর cipher suite বাছে, আর নিজের key exchange-এর অর্ধেক ফেরত পাঠায়। **দুই পক্ষই এখন independently একই shared secret বের করতে পারে** (Diffie-Hellman এভাবেই কাজ করে) — তাই এখন থেকে সবকিছু encrypted।
3. **EncryptedExtensions, Certificate, CertificateVerify** — এখন encrypted। server তার certificate chain পাঠায়, তারপর প্রমাণ করে যে সে আসলে সেই certificate-এর matching private key-ই রাখে — এখন পর্যন্ত exchange হওয়া সবকিছুর একটা hash sign করে (`CertificateVerify`) — শুধু certificate দেখিয়ে না, এই একই flight-এ তার ownership প্রমাণ করে।
4. **Finished** (server), তারপর **Finished** (client) — প্রতি পক্ষ সম্পূর্ণ handshake transcript-এর উপর একটা MAC পাঠায়, যাতে transit-এ কিছু বদলে গেলে বা হারিয়ে গেলে দুই পক্ষই ধরতে পারে।

এটুকুই — **এক round trip**, আর client-এর `Finished`-এর পরপরই application data (আসল HTTP request) শুরু হতে পারে। Capture-এ দেখা `ChangeCipherSpec` message গুলো TLS 1.3-এ আসল কোনো কাজ করে না — এগুলো শুধু এজন্য আছে যাতে network path-এ থাকা পুরনো middlebox, যা TLS 1.2-এর জন্য লেখা, একটা চেনা message shape দেখে connection drop না করে ([RFC 8446 §5](https://www.rfc-editor.org/rfc/rfc8446), Appendix D)।

## ২. কেন এটা একটা পরিবর্তন: TLS 1.2 নিয়েছিল দুই round trip

TLS 1.2 (এখনো কিছু পুরনো client বলে থাকে) প্রথমে key exchange method ঠিক করে, তারপর তা চালায় — `ClientHello` → `ServerHello + Certificate + ServerKeyExchange + ServerHelloDone` → `ClientKeyExchange + Finished` → `Finished`: application data move করার আগে **দুই round trip**। TLS 1.3 পুরো একটা round trip বাঁচায়, কারণ client *guess করে আগেই* তার key share পাঠিয়ে দেয়, জিজ্ঞেস করা অপেক্ষা করে না।

এই server এখনো TLS 1.2 connection accept করে, যেই client-রা ভালো কিছু করতে পারে না তাদের জন্য:

```bash
echo | openssl s_client -connect learn.bazardev.com:443 -servername learn.bazardev.com -tls1_2
```

```
New, TLSv1.2, Cipher is ECDHE-ECDSA-AES128-GCM-SHA256
Protocol: TLSv1.2
```

খেয়াল করো cipher-এর নাম **ECDHE** দিয়ে শুরু — Elliptic-Curve Diffie-Hellman, *Ephemeral*। এই "ephemeral"-ই তোমাকে **forward secrecy** দেয়: এই এক connection-এর জন্যই একটা নতুন key pair তৈরি হয় আর পরে ফেলে দেওয়া হয়, তাই পরের বছর server-এর long-term private key leak হয়ে গেলেও আজকের capture করা traffic decrypt করা যায় না — সেই one-time key সেই connection-এর memory ছাড়া আর কোথাও কখনো ছিলই না। TLS 1.2 এটা skip করতে *পারত* (plain RSA key exchange, forward secrecy ছাড়া) — TLS 1.3 এই option টাই পুরোপুরি বাদ দিয়ে প্রতি connection-এ ephemeral exchange বাধ্যতামূলক করে দিয়েছে।

## ৩. Certificate chain: server কেন একটার বেশি certificate পাঠায়

একটা certificate বলে "এখানে একটা public key আছে, আর একটা trusted third party সাক্ষ্য দিয়েছে এটা এই hostname-এর।" কিন্তু browser আর operating system প্রতিটা CA-কে সরাসরি trust করে না — তারা একটা ছোট, বাছাই করা **root CA**-র list সাথেই নিয়ে আসে। এই ফাঁক পার করতে, একটা CA একটা **intermediate** certificate দিয়ে sign করে, আর সেই intermediate-কে sign করে এমন একটা root যা client আগে থেকেই trust করে। server পুরো chain পাঠায় (root ছাড়া — client-এর কাছে সেটা আগে থেকেই আছে) যাতে client সেটা উপরের দিকে হেঁটে, চেনা একটা root পর্যন্ত পৌঁছাতে পারে।

নিচে এই server এখন যেই real chain দেখায় তা (`openssl s_client -connect learn.bazardev.com:443 -servername learn.bazardev.com`, যেখানে `s:` = subject, `i:` = প্রতি certificate-এর issuer):

```
0 s:CN=learn.bazardev.com
  i:C=US, O=Let's Encrypt, CN=YE1
1 s:C=US, O=Let's Encrypt, CN=YE1
  i:C=US, O=ISRG, CN=Root YE
2 s:C=US, O=ISRG, CN=Root YE
  i:C=US, O=Internet Security Research Group, CN=ISRG Root X2
3 s:C=US, O=Internet Security Research Group, CN=ISRG Root X2
  i:C=US, O=Internet Security Research Group, CN=ISRG Root X1
```

নিচ থেকে পড়ো: leaf certificate (**0**, ঠিক এই hostname-এর জন্য) sign করা intermediate **YE1** দিয়ে, যাকে নিজে sign করেছে **Root YE**, যাকে cross-sign করেছে **ISRG Root X2**, যাকে আবার cross-sign করেছে পুরনো, বেশি widely trusted **ISRG Root X1**। (একটা CA যেই exact intermediate আর root নাম ব্যবহার করে তা key rotate করার সাথে সাথে সময়ে বদলায় — এই নামগুলো মুখস্থ করো না; **shape** টা মুখস্থ করো: leaf → এক বা একাধিক intermediate → client-এর trust store-এ আগে থেকেই থাকা একটা root। এখানে শেষ link-এর মতো cross-signing হলো একটা CA নতুন root চালু করার সময় পুরনো trust store-এর সাথে compatible থাকার উপায়।) Linux-এ নিজের trust store-এর root গুলো `/etc/ssl/certs/`-এ দেখা যায়।

**Certificate expire হওয়ার আগেও revoke হতে পারে** — private key leak হলে, CA আগেভাগেই certificate-টা invalid বলে মার্ক করতে পারে। এটা check করতে **OCSP** (Online Certificate Status Protocol — "certificate X কি revoke হয়েছে?") বা পুরনো **CRL** (revoke হওয়া serial number-এর একটা downloadable list) ব্যবহার হয়। প্রতি connection-এ live OCSP check করা slow, আর এতে তোমার browsing CA-র কাছে leak হয়, তাই আধুনিক server প্রায়ই **OCSP stapling** ব্যবহার করে: server নিজেই নিয়মিত CA থেকে একটা signed "এখনো ঠিক আছে" উত্তর নিয়ে এসে handshake-এর সাথে জুড়ে দেয়, client-কে জিজ্ঞেস করার দরকার পড়ে না।

## ৪. HSTS: প্রথম request-এর ফাঁক বন্ধ করা

HTTPS পুরোপুরি কাজ করলেও, একটা ফাঁক থেকে যায়: একটা browser যখন **প্রথমবার** তোমার domain-এ যায়, তখন প্রায়ই সে এখনো জানে না HTTPS ব্যবহার করতে হবে — শুধু `myshop.com` টাইপ করলে, বা পুরনো bookmark খুললে, default হয়ে যায় plain `http://`। একটা server এটাকে HTTPS-এ redirect করতে পারে (Cloud chapter-এ Caddy ঠিক এটাই করতে দেখিয়েছিল), কিন্তু redirect-টা নিজেই একটা unencrypted request, আর সেই network path-এ বসে থাকা একজন attacker ("SSL-stripping" attack) এটা intercept করে victim-কে চুপচাপ `http://`-তেই রেখে দিতে পারে, redirect-টা যেতে না দিয়ে।

**HSTS (HTTP Strict Transport Security)** এটা **পরের বার** visit-এর জন্য বন্ধ করে দেয়: browser একবার আগে trust করা একটা connection-এ এই response header দেখলে, সে মনে রাখে এই host-এর জন্য সবসময় সরাসরি HTTPS-এ যেতে হবে, প্রথমে plain HTTP চেষ্টা না করেই — তাই intercept করার মতো আর কোনো redirect-ই থাকে না।

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

- **`max-age`** — কত সেকেন্ড ধরে browser এটা enforce করতে থাকবে (এখানে 31536000 = এক বছর)।
- **`includeSubDomains`** — এই নিয়ম শুধু exact host না, প্রতিটা subdomain-এ apply করো।
- **`preload`** — একটা optional তৃতীয় directive, আরও এগিয়ে যাওয়ার জন্য (নিচের section)।

এবার আসল finding — এখনই, live, এই course-এরই server-এর বিরুদ্ধে check করা:

```bash
curl -sI https://learn.bazardev.com/ | grep -i strict
```

```
(no output — the header is not present)
```

এই site HTTP-কে HTTPS-এ redirect করে (`curl -sI http://learn.bazardev.com/` `https://` URL-এ `308 Permanent Redirect` ফেরত দেয় — Caddy এটা automatic-ই করে), কিন্তু এখন `Strict-Transport-Security` পাঠায় **না**। এটা একটা common ফাঁক: Caddy আর Nginx দুটোই certificate আর redirect automatic-ই handle করে, কিন্তু **কেউই default-এ HSTS পাঠায় না** — এই header নিজে যুক্त করতে হয়। একটা Caddyfile-এ:

```
myshop.com {
    header Strict-Transport-Security "max-age=31536000; includeSubDomains"
}
```

বা Nginx-এ:

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

### Preload: **প্রথম** visit-এর ফাঁকও বন্ধ করা

HSTS শুধু সেই browser-কে protect করে যে আগে একবার HTTPS দিয়ে connect করেছে। **HSTS preload list**, Chrome maintain করে আর বেশিরভাগ বড় browser ব্যবহার করে, সেই ফাঁকটাও বন্ধ করে দেয়: browser-এর ভেতরেই shipped একটা hardcoded domain list, যারা browser-এর একদম প্রথম connection থেকেই HTTPS-only, আগে কোনো visit লাগে না। [hstspreload.org](https://hstspreload.org/)-এ submit করতে লাগে একটা valid certificate, একটা HTTP→HTTPS redirect, আর একটা live `Strict-Transport-Security` header যার `max-age` কমপক্ষে এক বছর, `includeSubDomains`, আর `preload` directive নিজেই present।

Preload **হুট করে চালু করার মতো কিছু না**: `includeSubDomains` যুক্ত করার আগে প্রতিটা subdomain HTTPS support করতে হবে (একটা HTTP-only subdomain-ই সব visitor-এর জন্য ভেঙে দেয়), আর বাদ দেওয়াও slow — নতুন entry (আর removal) browser-এ পৌঁছায় শুধু পরের browser release rebuild-এর পর, যেটা মাস লাগতে পারে। প্রথমে plain HSTS যুক্ত করো, কিছুদিন confirm করো সব subdomain আসলেই HTTPS-এ কাজ করছে, তারপরই preload ভাবো।

## ৫. মূল বিষয়গুলো (Key takeaways)

- TLS 1.3 একটা পূর্ণ handshake **এক round trip**-এ শেষ করে: client নিজের key share `ClientHello`-তেই পাঠায়, জিজ্ঞেস করার অপেক্ষা না করে, তাই server `ServerHello` + certificate + `Finished` সব একটাই flight-এ ফেরত দিতে পারে।
- **Ephemeral (EC)DHE key exchange forward secrecy দেয়** — server-এর long-term key পরে leak হলেও আজকের traffic unreadable থেকে যায়। TLS 1.3 এটা বাধ্যতামূলক করে দিয়েছে; TLS 1.2-তে এটা optional ছিল।
- একটা server একটা **certificate chain** পাঠায় — leaf → intermediate(s) — যাতে client উপরে হেঁটে আগে থেকে trust করা একটা **root CA**-এ পৌঁছাতে পারে; root নিজে পাঠানোর দরকার হয় না।
- Certificate আগেই **revoke** হতে পারে; **OCSP stapling**-এর মাধ্যমে server প্রতি connection-এ client-কে CA-এর কাছে পাঠানো ছাড়াই "এখনো valid" প্রমাণ করতে পারে।
- **HSTS** (`Strict-Transport-Security` header) browser-কে আগে দেখা একটা host-এ আর কখনো plain HTTP আবার চেষ্টা করতে দেয় না — পরের বার visit-এ SSL-stripping-এর ফাঁক বন্ধ করে দেয়। Caddy আর Nginx HTTP→HTTPS auto-redirect করে কিন্তু default-এ এই header পাঠায় **না**; নিজে যুক্ত করতে হয়।
- **HSTS preload** প্রথম visit-এর ফাঁকও বন্ধ করে, কিন্তু এটা একটা slow, উল্টানো কঠিন commitment — আগে সব জায়গায় plain HSTS পূর্ণভাবে কাজ করাও।

## ৬. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>প্রশ্ন ১. TLS 1.3 handshake এক round trip-এ শেষ হতে দেয়, client তার খুব প্রথম message-এ এমন কী পাঠায়?</strong></summary>

তার `key_share` — একটা Diffie-Hellman key exchange-এর অর্ধেক, `ClientHello`-তেই পাঠানো হয়, server জিজ্ঞেস করার অপেক্ষা না করে, যাতে server সবকিছু (encrypted certificate সহ) একটা মাত্র flight-এ ফেরত দিতে পারে।
</details>

<details>
<summary><strong>প্রশ্ন ২. ECDHE-র মতো একটা cipher suite-এ "ephemeral" মানে কী, আর কেন এটা গুরুত্বপূর্ণ?</strong></summary>

ওই একটা connection-এর জন্যই একটা নতুন key pair তৈরি হয় আর পরে ফেলে দেওয়া হয়। এটা forward secrecy দেয়: server-এর long-term private key পরে compromise হলেও, আগের connection-এর traffic তখনও decrypt করা যায় না, কারণ সেই one-time key আর কোথাও কখনো ছিলই না।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা server নিজের certificate-এর সাথে intermediate certificate-ও পাঠায়, কিন্তু root certificate পাঠায় না কেন?</strong></summary>

Browser আর operating system প্রতিটা CA-কে সরাসরি trust করে না, শুধু একটা ছোট root CA-র list যা তারা আগে থেকেই সাথে নিয়ে আসে। Intermediate গুলো server-এর certificate-কে trust করা একটা root পর্যন্ত জোড়ে; root নিজে পাঠানোর দরকার নেই কারণ client-এর কাছে সেটা আগে থেকেই আছে।
</details>

<details>
<summary><strong>প্রশ্ন ৪. একটা site-এর বছরের পর বছর ধরে কাজ করা HTTP→HTTPS redirect আছে। এটা একাই কি ভবিষ্যতের প্রতিটা visit-কে SSL-stripping attack থেকে বাঁচায়?</strong></summary>

না। যতবার browser এখনো সরাসরি HTTPS-এ যেতে শেখেনি, ততবার redirect-টা নিজেই একটা unencrypted request, আর সেই একটা request intercept করা যায়। HSTS পরের visit গুলোর জন্য ঠিক করে দেয়, browser header দেখার পর HTTP step পুরোপুরি skip করে; তাও এটা user-এর একদম প্রথম visit cover করে না, যেটা শুধু HSTS preloading address করে।
</details>

<details>
<summary><strong>প্রশ্ন ৫. HTTPS প্রথম চালু করার দিনই `includeSubDomains` যুক্ত করে HSTS preload-এ submit করা উচিত না কেন?</strong></summary>

`includeSubDomains` তখনই প্রতিটা subdomain-কে HTTPS-only বাধ্য করে — একটা subdomain ready না থাকলে সব visitor-এর জন্য ভেঙে যায়। Preload বাদ দেওয়াও slow, কারণ পরিবর্তন user-এর কাছে পৌঁছায় শুধু পরের browser release rebuild-এর পর, যেটা মাস লাগতে পারে। আগে confirm করো প্রতিটা subdomain HTTPS-এ কাজ করছে।
</details>

## ৭. ফ্রি রিসোর্স

- RFC Editor: [RFC 8446 — The Transport Layer Security (TLS) Protocol Version 1.3](https://www.rfc-editor.org/rfc/rfc8446)
- MDN: [Strict-Transport-Security header](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Strict-Transport-Security)
- HSTS Preload List: [hstspreload.org](https://hstspreload.org/)
- Caddy: [Automatic HTTPS](https://caddyserver.com/docs/automatic-https)
- DigiCert: [What is OCSP stapling?](https://www.digicert.com/ocsp-stapling)

---

**পরের chapter:** [Chapter 5 — Reverse proxy patterns: path routing, websockets, caching, and rate limiting](05-reverse-proxy-patterns.md)
