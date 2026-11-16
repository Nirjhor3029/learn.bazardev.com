# Chapter 3 — Domain আর DNS

> **Course:** Cloud (AWS / VPS Basics) · **Chapter:** 3 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/cloud/03-domains-and-dns)

তোমার server-এর একটা IP address আছে, যেমন `203.0.113.10`। এটা কেউ টাইপ করতে চায় না। মানুষ টাইপ করে `myshop.com`। দুটোকে জোড়ে যে ব্যবস্থা, তার নাম **DNS**, আর "আমার site কেন আসছে না?"-র পেছনে প্রায় অন্য সব কিছুর চেয়ে বেশিবার এটাই থাকে।

## ১. এক বাক্যে DNS

**DNS (Domain Name System) হলো internet-এর ফোনবই: এটা `myshop.com`-এর মতো নামকে `203.0.113.10`-এর মতো IP address-এ বদলে দেয়।**

Computer একে অপরের সাথে কথা বলে IP address দিয়ে। DNS আছে যাতে মানুষ নাম ব্যবহার করতে পারে।

## ২. Domain নামের অংশ

নাম পড়ো ডান থেকে বামে:

```
        blog.myshop.com
          │     │    └── TLD (top-level domain): .com, .org, .bd, .dev
          │     └─────── domain (যেটা তুমি register করো): myshop.com
          └───────────── subdomain (এগুলো তুমি নিজে ইচ্ছামতো বানাও): blog
```

- তুমি বছরের ফি দিয়ে একটা domain **register** করো (যেমন `myshop.com`)।
- তারপর যত ইচ্ছা **subdomain** বিনামূল্যে বানাতে পারো: `www.myshop.com`, `api.myshop.com`, `blog.myshop.com`, প্রতিটা যেখানে খুশি পাঠানো যায়।
- Subdomain ছাড়া শুধু `myshop.com`-কে বলে **apex** বা **root** domain।

`.bd`-এর মতো country domain-এর নিজস্ব register-এর নিয়ম আর registrar আছে, তাই ওটার ওপর পরিকল্পনা করার আগে শর্ত দেখে নাও।

## ৩. Registrar আর DNS host: দুটো আলাদা কাজ

মানুষ এ দুটো গুলিয়ে ফেলে:

- **Registrar:** যে company-তে তুমি domain নাম *কেনো* ও renew করো (Namecheap, GoDaddy, Cloudflare Registrar, Porkbun আর আরও অনেক)।
- **DNS host:** যে service তোমার *DNS record* রাখে, মানে "এই নাম কোথায়?"-র উত্তর। Default-এ সাধারণত registrar-ই হয়, কিন্তু হতেই হবে এমন নয়। তুমি এক জায়গায় কিনে অন্য জায়গার DNS (যেমন Cloudflare বা AWS Route 53) ব্যবহার করতে পারো।

দুটোর যোগসূত্র domain-এর **NS (name server) record**। এগুলো বলে "আমার domain নিয়ে *এই* server-দের জিজ্ঞেস করো"। Registrar-এ NS record বদলালে তোমার DNS অন্য host-এ চলে যায়।

## ৪. DNS record

**Record** হলো তোমার domain-এর DNS zone-এর একটা লাইন। যেগুলো তুমি ব্যবহার করবে:

| Type | কী জোড়ে | উদাহরণ | মানে |
|---|---|---|---|
| **A** | নাম → IPv4 address | `myshop.com → 203.0.113.10` | "এই নাম এই IP-তে থাকে" |
| **AAAA** | নাম → IPv6 address | `myshop.com → 2001:db8::10` | একই, IPv6-এর জন্য |
| **CNAME** | নাম → অন্য একটা *নাম* | `www → myshop.com` | "ওই অন্য নামের মতোই" |
| **MX** | domain → mail server | `10 mail.example.net` | Domain-এর email কোথায় যাবে |
| **TXT** | নাম → লেখা | `v=spf1 ...` | যাচাই ও email নিরাপত্তা (SPF, DKIM) |
| **NS** | domain → name server | `ns1.provider.com` | এই domain-এর হয়ে কে উত্তর দেয় |

একটা website-এর সাধারণ setup:

```
Type   Name   Value            TTL
A      @      203.0.113.10     300
CNAME  www    myshop.com.      300
A      api    203.0.113.10     300
```

`@` মানে "domain নিজে" (apex)। মনে রাখার নিয়ম:

- **CNAME apex-এ বসতে পারে না**, শুধু subdomain-এ। ওখানে A record ব্যবহার করো।
- CNAME একটা *নামে* যায়; A record একটা *IP-তে*।
- `api`-কে apex-এর একই IP-তে পাঠানো ঠিক আছে: একটা server অনেক নামের সেবা দিতে পারে। কোন নামে request এসেছে তা দেখে web server ঠিক করে কী দেখাবে (Chapter 4)।
- **Wildcard** record `*` তোমার না-ঠিক-করা যেকোনো subdomain-এর সাথে মেলে (যেমন `*.myshop.com`)।

## ৫. Lookup কীভাবে কাজ করে

কেউ `myshop.com` খুললে server-এর একটা শৃঙ্খল সাহায্য করে। মুখস্থ করতে হবে না, তবে ছবিটা গুরুত্বপূর্ণ:

![DNS lookup: browser, resolver, root, TLD, তোমার DNS](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/06-cloud/images/dns-lookup.svg)

1. Browser **resolver**-কে জিজ্ঞেস করে (সাধারণত তোমার ISP-র, বা 1.1.1.1 বা 8.8.8.8-এর মতো কোনোটা)।
2. Resolver না জানলে **root server**-কে জিজ্ঞেস করে: "`.com` কে দেখে?"
3. তারপর **`.com` TLD server**-কে: "`myshop.com` কে দেখে?" ওরা তোমার **NS record** দিয়ে উত্তর দেয়।
4. তারপর **তোমার DNS host**-কে, যে **A record** ফেরত দেয়: `203.0.113.10`।
5. Resolver IP-টা browser-কে দেয় আর কিছুক্ষণ **মনে রাখে**।

## ৬. TTL আর "propagation"

প্রতিটা record-এর একটা **TTL (time to live)** আছে সেকেন্ডে: resolver আবার জিজ্ঞেস করার আগে কতক্ষণ উত্তর মনে রাখতে পারবে। `300` = ৫ মিনিট, `3600` = ১ ঘণ্টা।

"DNS propagation" কোনো রহস্যময় ছড়িয়ে পড়া নয়। এটা শুধু **পুরনো উত্তর cache-এ থাকা, যতক্ষণ না TTL শেষ হয়**। যে A record-এর TTL 86400 (২৪ ঘণ্টা) সেটা বদলালে কিছু মানুষ এক দিন পর্যন্ত পুরনো IP দেখতে পারে।

**ব্যবহারিক পরামর্শ:** পরিকল্পিত server সরানোর এক দিন আগে TTL `300` করে দাও। সরানোর পর আবার বাড়াতে পারো।

নতুন NS record (DNS host বদলানো) স্থির হতে বেশি সময় নিতে পারে, কারণ registrar আর TLD-র cache জড়িত।

## ৭. নিজে DNS পরীক্ষা করো

দুটো tool, Linux ও macOS-এ বিল্ট-ইন (`nslookup` Windows-এও চলে):

```bash
dig +short myshop.com A            # IPv4 address
dig +short www.myshop.com CNAME    # CNAME কোথায় যায়
dig +short myshop.com NS           # কোন name server উত্তর দেয়
dig myshop.com A                   # TTL কলাম-সহ পুরো উত্তর
dig @1.1.1.1 myshop.com A          # নির্দিষ্ট resolver-কে জিজ্ঞেস
```

পুরো `dig` output-এ এরকম একটা উত্তরের লাইন থাকে:

```
myshop.com.   299   IN   A   203.0.113.10
```

`299` হলো resolver-এর cache-এ **বাকি TTL**। দুবার জিজ্ঞেস করলে এটা কমতে দেখবে। তোমার পরিবর্তন না দেখা গেলে অন্য resolver (`@8.8.8.8`) বা তোমার DNS host-এর নিজস্ব server-কে (`dig @ns1.provider.com ...`) জিজ্ঞেস করো, যাতে বোঝা যায় সমস্যা record-এ না কি শুধু cache-এ।

## ৮. VPS-এ domain বসানো

1. Registrar-এ domain কেনো।
2. DNS panel-এ (registrar-এর বা Cloudflare/Route 53-এর) একটা **A record** যোগ করো: নাম `@` → তোমার VPS-এর IP, আর `www` → `@`-এ `CNAME` (বা আরেকটা A record)।
3. কয়েক মিনিট অপেক্ষা করো। `dig +short myshop.com` দিয়ে দেখো।
4. `http://myshop.com` খোলো। Chapter 4-এ web server বসানোর আগে কিছু দেখবে না, কিন্তু DNS এখন কাজ করছে।

**Cloudflare নোট:** Cloudflare-এর DNS ব্যবহার করলে record-এর পাশে কমলা মেঘ ("proxied") দেখতে পারো। এটা traffic Cloudflare-এর মধ্য দিয়ে পাঠায় (তোমার server-এর IP লুকায়, caching ও সুরক্ষা যোগ করে)। শেখার জন্য "DNS only" (ধূসর মেঘ) দিয়ে শুরু করতে পারো, যাতে ঠিক কী ঘটছে দেখা যায়।

## ৯. মূল কথা

- **DNS** নামকে IP address-এ বদলায়।
- **Registrar** নাম বিক্রি করে; **DNS host** record রাখে। **NS record** দুটোকে জোড়ে।
- **A** = নাম → IPv4, **AAAA** = IPv6, **CNAME** = নাম → নাম (apex-এ নয়), **MX** = mail, **TXT** = যাচাই/email নিরাপত্তা।
- **Subdomain** বিনামূল্যে ও সীমাহীন; একটা server অনেক নামের সেবা দিতে পারে।
- **TTL** হলো cache কতক্ষণ উত্তর রাখে; "propagation" মানে cache-এর মেয়াদ ফুরানো। সরানোর আগে TTL কমাও।
- `dig +short name TYPE` দিয়ে debug করো, আর cache-এর সম্ভাবনা বাদ দিতে অন্য resolver ব্যবহার করো।

## ১০. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. DNS কী করে?</strong></summary>

`myshop.com`-এর মতো মানুষের সুবিধার নামকে IP address-এ অনুবাদ করে।
</details>

<details>
<summary><strong>Q2. Registrar আর DNS host-এর পার্থক্য কী?</strong></summary>

Registrar-এ তুমি domain কেনো ও renew করো। DNS host সেই record রাখে যা বলে নামটা কোথায় যায়। ওরা আলাদা company হতে পারে, NS record দিয়ে জোড়া।
</details>

<details>
<summary><strong>Q3. কখন A record আর কখন CNAME ব্যবহার করবে?</strong></summary>

A record একটা নামকে IPv4 address-এ জোড়ে (আর apex-এ এটাই ব্যবহার করতে হয়)। CNAME একটা নামকে অন্য নামে জোড়ে, যেমন `www` → `myshop.com`।
</details>

<details>
<summary><strong>Q4. তুমি A record বদলেছ কিন্তু পুরনো IP-ই দেখছ। কেন, আর কী করতে পারো?</strong></summary>

Resolver পুরনো উত্তর TTL শেষ না হওয়া পর্যন্ত cache-এ রাখে। অপেক্ষা করো, বা `dig @...` দিয়ে অন্য resolver বা authoritative server দেখো। পরের বার বদলানোর আগে TTL কমিয়ে নাও।
</details>

<details>
<summary><strong>Q5. `api.myshop.com` আর `myshop.com` কি একই server-এ যেতে পারে?</strong></summary>

হ্যাঁ। দুটো নাম একই IP ভাগ করতে পারে; কোন hostname-এ request এসেছে তা দেখে web server ঠিক করে কী দেবে।
</details>

## ১১. বিনামূল্যের resource

- Cloudflare: [What is DNS?](https://www.cloudflare.com/learning/dns/what-is-dns/)
- Cloudflare: [Common DNS record types](https://www.cloudflare.com/learning/dns/dns-records/)
- Julia Evans: [How DNS works (comic zine)](https://wizardzines.com/zines/dns/)
- Tool: [DNS Checker](https://dnschecker.org/), বিভিন্ন জায়গা থেকে উত্তর দেখতে

---

**Next:** Chapter 4 — HTTPS-সহ web server *(coming soon)*
