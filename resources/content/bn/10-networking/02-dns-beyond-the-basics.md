# Chapter 2 — DNS-এর Basic ছাড়িয়ে: CAA, SRV, DNSSEC, আর `dig`-এর পরেও Debugging

> **Course:** Networking & Web Servers · **Chapter:** 2 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/networking/02-dns-beyond-the-basics)

Cloud Chapter 3-এ DNS-এর কাজ, lookup chain, A/AAAA/CNAME/MX/TXT/NS record, TTL, আর সাধারণ `dig` নিয়ে আগেই বলা হয়েছে। এই chapter সেটা জানা ধরে নিয়ে তিনটা জিনিসে যায় যা একজন developer পরে সামনে পড়ে, কিন্তু আগের chapter-এ ছিল না: একটা record type যা নিয়ন্ত্রণ করে *কাকে তোমার certificate issue করার অনুমতি*, একটা record type যা শুধু address না, *একটা service-ই advertise করে*, আর একটা system যা resolver-কে *প্রমাণ* করতে দেয় একটা answer জাল করা হয়নি — আর সাধারণ `dig`-এর চেয়ে জোরালো একটা debugging command।

## ১. CAA: কে তোমার domain-এর জন্য certificate issue করতে পারবে

Domain-এর নিয়ন্ত্রণ প্রমাণ করতে পারলে (Chapter 4-এ দেখাবো Let's Encrypt ঠিক কীভাবে এটা check করে) সাধারণত *যে কোনো* trusted CA থেকে certificate পাওয়া যায়। একটা **CAA (Certification Authority Authorization)** record এটা সীমিত করে: সরাসরি DNS থেকে বলে দেয় কোন CA-গুলো তোমার domain-এর জন্য issue করতে পারবে, [RFC 8659](https://www.rfc-editor.org/rfc/rfc8659)-এ সংজ্ঞায়িত।

```
myshop.com.  CAA  0 issue "letsencrypt.org"
myshop.com.  CAA  0 issuewild "letsencrypt.org"
```

- **`issue`** — এই CA এই নামের জন্য normal certificate issue করতে পারবে।
- **`issuewild`** — এই CA *wildcard* certificate (`*.myshop.com`) issue করতে পারবে। এটা না থাকলে, `issue`-এর CA-গুলোও wildcard issue করতে পারবে না।
- **`iodef`** — একটা optional contact (একটা `mailto:` বা `https:` URL) যেখানে CA একটা refuse করা request জানাতে পারে।

একটা real example — GitHub একাধিক publish করে, কারণ একাধিক CA তাদের জন্য issue করে:

```bash
dig +short github.com CAA
```

```
0 issuewild "digicert.com"
0 issue "globalsign.com"
0 issue "sectigo.com"
0 issue "digicert.com"
0 issue "letsencrypt.org"
0 issuewild "sectigo.com"
0 issuewild "letsencrypt.org"
```

একটা domain-এ **কোনো CAA record না থাকলে** কোনো publicly trusted CA-কেই অনুমতি দেয়, যা সাধারণ ঘটনা আর বেশিরভাগ site-এর জন্য ঠিক আছে। একটা CA ঠিক করে ফেললে (এই course-এ Let's Encrypt) আর তোমার registrar account বা DNS কখনো compromise হলে ক্ষতি কমাতে CAA যোগ করার মূল্য আছে: access থাকলেও, attacker তুমি অনুমতি না দেওয়া CA থেকে কাজ করা certificate পাবে না। প্রতিটা publicly trusted CA issue করার আগে CAA check করতে বাধ্য, তাই এটা CA-এর পক্ষ থেকেই জোর করা, শুধু পরামর্শ না।

## ২. SRV: একটা service advertise করা, শুধু address না

A, AAAA আর CNAME সবাই "এই নামটা কোথায়?"-এর উত্তর দেয়। **SRV** আলাদা প্রশ্নের উত্তর দেয়: "কোন host আর port-এ *এই নির্দিষ্ট service* চলছে?", যাতে client একটা fixed, well-known port না ধরেই service খুঁজে পায়। Format, [RFC 2782](https://www.rfc-editor.org/rfc/rfc2782) থেকে:

```
_service._proto.name.  TTL  IN  SRV  priority  weight  port  target.
```

- **`_service._proto`** — service আর transport, যেমন `_sip._tcp` বা `_caldavs._tcp`।
- **priority** — কম সংখ্যা আগে try হয় (MX-এর মতোই idea)।
- **weight** — একই priority-র target-দের মধ্যে traffic ভাগ করে, proportionally।
- **port** — service-টার এই instance-টা আসলে কোন port-এ চলছে — SRV থাকার পুরো কারণ, কারণ client ধরে নিতে পারে না এটা 443।
- **target** — একটা hostname, আলাদাভাবে নিজের A/AAAA lookup দরকার।

দুটো real, public example যা তুমি এখনই check করতে পারো:

```bash
dig +short _caldavs._tcp.icloud.com SRV
```

```
0 0 443 caldav.icloud.com.
```

```bash
dig +short _xmpp-server._tcp.jabber.org SRV
```

```
30 30 5269 scarlet.jabber.org.
```

এভাবেই একটা Apple Calendar app iCloud-এর CalDAV server খুঁজে পায়, আর একটা XMPP (chat) server federate করার জন্য আরেকটা খুঁজে পায় — client-এ কোনো hostname বা port বাঁধা থাকে না। প্লেইন website-এর বাইরে SRV আবার দেখা পাবে: VoIP (SIP), Minecraft server, আর Microsoft 365/Exchange Autodiscover সবাই এটা ব্যবহার করে। একটা typical Laravel site-এর নিজের SRV publish করার দরকার নেই, কিন্তু এখন তুমি জানো অন্য কারো zone-এ এটা দেখলে এটা কী।

## ৩. DNSSEC: প্রমাণ করা একটা answer জাল করা হয়নি

সাধারণ DNS-এ কোনো authentication নেই: একটা resolver-এর কোনো উপায় নেই আসল answer আর মাঝপথে ঢুকিয়ে দেওয়া জাল answer (DNS cache poisoning) আলাদা করার। **DNSSEC (DNS Security Extensions)** এটা ঠিক করে record sign করে, যাতে resolver cryptographic-ভাবে সেগুলো verify করতে পারে, শুধু যে উত্তর দিল তাকে বিশ্বাস না করে।

তিনটা record type এটা বহন করে:

- **DNSKEY** — একটা zone-এর public signing key।
- **RRSIG** — একদল record-এর উপর signature, ওই key দিয়ে বানানো।
- **DS (Delegation Signer)** — একটা child zone-এর DNSKEY-এর *hash*, **parent zone-এ** publish করা। এটাই সেই link যা আলাদা signed zone-গুলোকে একটা **chain of trust**-এ পরিণত করে।

![DNSSEC: একটা trust anchor থেকে নিচে নামা DS record-এর chain](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/10-networking/images/dnssec-chain.svg)

Root zone হলো একমাত্র link যা কোনো parent থেকে verify করা যায় না — প্রতিটা DNSSEC-validating resolver শুধু root-এর public key নিয়েই আসে (একটা **trust anchor**)। সেখান থেকে, chain-এর প্রতিটা DS record check করা যায়, তাই resolver কোনো একটা server-কে চোখ বুজে বিশ্বাস না করেই trust তোমার domain পর্যন্ত চলে আসে।

একটা real, signed domain-এ দেখো:

```bash
dig +dnssec cloudflare.com A
```

```
;; flags: qr rd ra; QUERY: 1, ANSWER: 3, AUTHORITY: 0, ADDITIONAL: 1
cloudflare.com.   299  IN  A      104.16.132.229
cloudflare.com.   299  IN  A      104.16.133.229
cloudflare.com.   299  IN  RRSIG  A 13 2 300 20261119012945 20261116232945 34505 cloudflare.com. X1shl+zekYn9Qbx+A/6RvNOnAVUUWhDdRL1HEJI2tHr1c3oTSGy8LX3kAVCWUFUv4RpzjwLY02Qjr9c/6kvBpQ==
```

```bash
dig +short cloudflare.com DS
```

```
2371 13 2 32996839A6D808AFE3EB4A795A0E6A7A39A76FC52FF228B22B76F6D63826F2B9
```

`RRSIG` line হলো `A` record-গুলোর উপর signature; `DS` line হলো `.com` যা publish করে যাতে resolver প্রথমেই `cloudflare.com`-এর key বিশ্বাস করতে পারে। (একটা validating resolver **`ad`** — "authenticated data" — flag সেট করে একটা response-এ যখন প্রতিটা check পাস করে; `dig` `+cd` ছাড়া তোমাকে যা পেয়েছে দেখায়, কিন্তু আসল verification সাধারণত তোমার resolver-এর ভেতরেই হয়, তোমার কাছাকাছি।)

**DNSSEC যা করে *না*:** এটা কিছুই encrypt করে না — একটা query আর তার answer এখনও তারে plain text (সেটার জন্য [DNS over HTTPS/TLS](https://www.cloudflare.com/learning/dns/dns-over-tls/) আছে, একটা আলাদা বিষয়)। এটা শুধু প্রমাণ করে একটা answer authentic আর অপরিবর্তিত। এটা chain-এর *প্রতিটা* link sign হওয়া আর তোমার resolver আসলে validate করার উপরও নির্ভর করে — কোনো level-এ একটা ভাঙা DS record zone-টাকে শুধু insecure না, validating resolver-দের জন্য **পৌঁছানোই যায় না** করে ফেলে, এই কারণেই এটা সাধারণত হাতে configure না করে DNS host-এ কয়েকটা click দিয়েই চালু করা হয় (যেমন Cloudflare-এর dashboard করে)।

## ৪. `dig`-এর পরেও Debugging: `+trace`

সাধারণ `dig domain.com` *তোমার configure করা resolver*-কে জিজ্ঞেস করে আর শেষ answer দেখায়। `dig +trace` এর বদলে নিজেই delegation chain হেঁটে যায়, hop by hop — কাজে লাগে যখন সন্দেহ হয় সমস্যা একটা পুরনো cache না, বরং ভাঙা delegation (ভুল NS record):

```bash
dig +trace github.com A
```

```
.                IN NS  a.root-servers.net.   (and 12 more — the root)
;; Received ... from 127.0.0.53#53(127.0.0.53)

com.             IN NS  a.gtld-servers.net.   (and 12 more — the .com TLD)
;; Received ... from k.root-servers.net

github.com.      IN NS  dns1.p08.nsone.net.
github.com.      IN NS  ns-520.awsdns-01.net.  (and more — github.com's own servers)
;; Received ... from e.gtld-servers.net

github.com.   60  IN A  140.82.121.4
;; Received ... from ns-1283.awsdns-32.org
```

(Real output, এই chapter লেখার সময় নিজে চালিয়ে পাওয়া — ঠিক ঠিক IP আর server-এর নাম আলাদা হবে, সময়ের সাথে কিছু বদলায়ও; গুরুত্বপূর্ণ হলো shape-টা: root → TLD → domain-এর নিজের name server → শেষ answer, প্রতিটা line বলে দেয় কোন server আসলে ওই hop-এ উত্তর দিয়েছে।) উপর থেকে নিচে পড়ো: TLD hop-এ আটকে গেলে, registrar-এর NS record ভুল; domain-এর নিজের server-এ পৌঁছায় কিন্তু কোনো answer না পেলে, DNS host-এর zone-টাই সমস্যা — আন্দাজ করার চেয়ে অনেক দ্রুত নির্ণয়।

আরও কিছু tool জানা ভালো, প্রতিটা `dig`-এর চেয়ে ছোট একটা প্রশ্নের উত্তর দেয়:

| Command | উত্তর দেয় |
|---|---|
| `host myshop.com` | একটা দ্রুত, ছোট lookup — fast sanity check-এর জন্য কাজের |
| `whois myshop.com` | Domain-টা কে register করেছে, আর (কখনো কখনো) তার name server — একটা registration প্রশ্ন, DNS প্রশ্ন না |
| `dig myshop.com NS +short` | ঠিক কোন name server এখন delegated আছে (তোমার DNS host-এর নিজের list-এর সাথে তুলনা করো) |

## ৫. মূল কথা

- **CAA** বলে দেয় কোন CA তোমার domain-এর জন্য certificate issue করতে পারবে, প্রতিটা public CA-এর জন্যই জোর করা — normal cert-এর জন্য `issue`, wildcard-এর জন্য `issuewild`। Record না থাকলে কোনো CA-কেই অনুমতি দেয়।
- **SRV** একটা service-এর আসল host *আর port* advertise করে (`priority weight port target`), এমন protocol-এর জন্য যারা fixed well-known port ধরে নিতে পারে না — typical website-এর জন্য দরকার নেই, chat, VoIP, আর calendar/contacts discovery-তে সাধারণ।
- **DNSSEC** DNS answer sign করে (`DNSKEY`, `RRSIG`) আর zone-গুলোকে তাদের parent-এর সাথে link করে (`DS`), root থেকে নিচে একটা **chain of trust** বানিয়ে। এটা answer authenticate করে; encrypt করে না।
- `dig +trace` নিজেই delegation chain হেঁটে যায় — root, তারপর TLD, তারপর domain-এর নিজের server — যা দেখিয়ে দেয় একটা ভাঙা DNS setup *ঠিক কোথায়* ভাঙে।

## ৬. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. CAA record কী নিয়ন্ত্রণ করে?</strong></summary>

কোন certificate authority একটা domain-এর জন্য TLS certificate issue করতে পারবে। কোনো CAA record না থাকলে কোনো publicly trusted CA-ই issue করতে পারে।
</details>

<details>
<summary><strong>Q2. একটা domain-এর CAA record-এ শুধু <code>issue "letsencrypt.org"</code> আছে, কোনো <code>issuewild</code> নেই। Let's Encrypt এর জন্য একটা wildcard certificate issue করতে পারবে কি?</strong></summary>

না। `issue` শুধু normal certificate cover করে; wildcard certificate-এর জন্য আলাদা একটা `issuewild` entry লাগে, এমনকি আগে থেকে অনুমতি পাওয়া CA-এরও।
</details>

<details>
<summary><strong>Q3. A/CNAME record যা সমাধান করে না, SRV কী সমাধান করে?</strong></summary>

A/CNAME শুধু বলে একটা নাম কোথায় যায়; SRV এটাও বলে একটা নির্দিষ্ট service কোন *port*-এ চলছে, আর priority/weight দিয়ে কয়েকটা instance-এর মধ্যে বাছতে দেয় — এমন service-এর জন্য কাজের যারা fixed well-known port ধরে নিতে পারে না।
</details>

<details>
<summary><strong>Q4. একটা SRV record-এ "target" কী?</strong></summary>

একটা hostname — এর নিজের আলাদা A বা AAAA lookup লাগে IP address-এ পরিণত হতে; SRV নিজে শুধু host আর port দেয়।
</details>

<details>
<summary><strong>Q5. একটা DS record আসলে কী ধারণ করে?</strong></summary>

Child zone-এর DNSKEY (public signing key)-এর একটা hash, parent zone-এ publish করা — এটাই সেই link যা resolver-কে child zone-এর key বিশ্বাস করতে দেয়।
</details>

<details>
<summary><strong>Q6. DNSSEC কি DNS traffic encrypt করে?</strong></summary>

না। এটা শুধু প্রমাণ করে একটা answer আসল আর অপরিবর্তিত; query আর answer এখনও plain text-এ পাঠানো হয়।
</details>

<details>
<summary><strong>Q7. তোমার সন্দেহ registrar-এ একটা domain-এর NS record ভুল। সাধারণ <code>dig</code> না <code>dig +trace</code> — কোনটা দ্রুত বলে দেয়, আর কেন?</strong></summary>

`dig +trace`, কারণ এটা প্রতিটা hop (root, TLD, domain-এর নিজের server) আলাদাভাবে দেখায়, তাই শেষের failure-টুকু দেখার বদলে ঠিক কোথায় delegation কাজ করা বন্ধ করেছে তা দেখা যায়।
</details>

<details>
<summary><strong>Q8. একটা site-এর আগে থেকেই কাজ করা certificate থাকলেও কেন ইচ্ছাকৃতভাবে CAA record যোগ করবে?</strong></summary>

DNS বা registrar account কখনো compromise হলে ক্ষতি কমাতে: সেই access থাকলেও, attacker domain অনুমতি দেয়নি এমন একটা CA থেকে কাজ করা certificate পাবে না।
</details>

## ৭. বিনামূল্যের resource

- RFC Editor: [RFC 8659 — DNS Certification Authority Authorization (CAA)](https://www.rfc-editor.org/rfc/rfc8659)
- RFC Editor: [RFC 2782 — A DNS RR for specifying the location of services (SRV)](https://www.rfc-editor.org/rfc/rfc2782)
- Cloudflare: [How DNSSEC works](https://www.cloudflare.com/dnssec/)
- IBM: [What is dig +trace?](https://www.ibm.com/think/topics/using-dig-trace)
- ICANN: [DNSSEC — what is it and why is it important?](https://www.icann.org/resources/pages/dnssec-what-is-it-why-important-2019-03-05-en)

---

**Next:** Chapter 3 — Virtual host আর SNI: একটা server, অনেক site *(coming soon)*
