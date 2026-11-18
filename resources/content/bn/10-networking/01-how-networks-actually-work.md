# Chapter 1 — Network আসলে কীভাবে কাজ করে: IP, Port, TCP/UDP, আর Three-Way Handshake

> **Course:** Networking & Web Servers · **Chapter:** 1 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/networking/01-how-networks-actually-work)

Cloud course-এ তুমি firewall-এ port 22, 80 আর 443 খুলেছো (Chapter 2), DNS দিয়ে একটা domain-কে IP-তে পাঠিয়েছো (Chapter 3), আর web server-কে HTTPS-এর পেছনে রেখেছো (Chapter 4)। সেটা কাজ করেছে, কিন্তু কিছু শব্দ ব্যাখ্যা না করেই ব্যবহার হয়েছিল: *port*, *socket*, *TCP*। এই chapter সেই শব্দগুলো খুলে দেখায়, যাতে এই course-এর বাকি অংশ — virtual host, TLS-এর ভেতরের কাজ, reverse proxy, load balancing — শক্ত ভিত্তির উপর দাঁড়ায়।

## ১. যে layer-গুলো তোমার আসলে লাগবে

Networking course সাধারণত 7-layer OSI model দিয়ে শুরু হয়। বাস্তবে কাজ করা engineer-রা সহজ একটা 4-layer mental model ব্যবহার করে (যার উপর internet নিজেই বানানো — **TCP/IP model**):

| Layer | কাজ | যা তুমি আগে থেকেই জানো |
|---|---|---|
| **Application** | তোমার program আসলে কী বলতে চায় | HTTP, DNS, SSH |
| **Transport** | এক *program* থেকে অন্য *program*-এ message পাঠানো | TCP, UDP |
| **Internet** | এক *machine* থেকে অন্য *machine*-এ, network পার হয়ে packet পাঠানো | IP (IPv4, IPv6) |
| **Link** | এক physical/local network-এর ভেতর frame পাঠানো | Ethernet, Wi-Fi |

প্রতিটা layer শুধু তার ঠিক নিচের layer-টুকু জানলেই চলে। তোমার Laravel app একটা HTTP client call করে; সেটা জানে না bytes Wi-Fi, fiber না VPN দিয়ে যাচ্ছে কিনা — TCP আর IP সেটা নিচে সামলায়। এই chapter বেশিরভাগ **Internet** আর **Transport** row-তে থাকবে, কারণ "port", "socket" আর "handshake" শব্দগুলো এখান থেকেই আসে।

## ২. IP address: machine-কে কীভাবে নাম দেওয়া হয়

একটা **IP address** একটা machine-কে (বা একটা network interface-কে) network-এ চিহ্নিত করে — এটা Internet layer-এর কাজ।

- **IPv4** দেখতে এমন `203.0.113.10`: চারটা সংখ্যা 0–255। এরকম মোটে প্রায় 4.3 বিলিয়ন আছে, আর বছর আগেই শেষ হয়ে গেছে, তাই **IPv6** আছে: `2001:db8::1f`, অনেক বড় address space নিয়ে। বেশিরভাগ VPS provider দুটোই দেয়।
- **Private range**-গুলো internal ব্যবহারের জন্য সংরক্ষিত, public internet-এ কখনো route হয় না: `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`। তোমার বাসার router আর office network এগুলো ব্যবহার করে। একটা VPS-এ সাধারণত সরাসরি public IP থাকে, কিন্তু cloud provider-রা instance-কে একটা private IP-ও দেয়, একই network-এর অন্য instance-এর সাথে public internet পার না হয়ে কথা বলার জন্য।
- **`127.0.0.1`** (`localhost`) মানে সবসময় "এই একই machine" — network নয়।
- বাসার router-এর পেছনে, অনেক device একটা public IP ভাগ করে **NAT (Network Address Translation)** দিয়ে: router address rewrite করে যাতে reply ঠিক device-এ ফিরে আসে। VPS-এ সাধারণত এটা লাগে না; এর আগে থেকেই নিজের public IP থাকে।

## ৩. Port আর socket: program-কে আলাদা করে চেনা

একটা machine, একটা IP address — কিন্তু অনেক program একসাথে network চাইতে পারে (তোমার web server, MySQL, Redis, একটা SSH session)। **Port** এটা সমাধান করে: একটা 16-bit সংখ্যা (0–65535) যা বলে একটা packet *কোন program*-এর জন্য।

কিছু port **IANA** well-known service-এর জন্য standard করে রেখেছে, যাতে client জানে কী আশা করতে হবে:

| Port | Service |
|---|---|
| 22 | SSH |
| 53 | DNS |
| 80 | HTTP |
| 443 | HTTPS |
| 25 / 587 | SMTP (mail) |
| 3306 | MySQL (default) |
| 6379 | Redis (default) |

1024-এর নিচের port "well-known" আর (Linux-এ) bind করতে root লাগে; তার উপরে যে কেউ listen করতে পারে। যখন তোমার browser *বাইরে* কোনো server-এ connect করে, নিজের জন্য কোনো well-known port ব্যবহার করে না — OS একটা random ফাঁকা port দেয় **ephemeral range** থেকে (Linux-এ সাধারণত 32768–60999), শুধু ওই একটা connection-এর জন্য, তারপর ছেড়ে দেয়।

একটা IP আর একটা port একসাথে করলে পাও একটা **socket**: যে combination আসলে data পাঠায় বা নেয়। Port 443-এ listen করা একটা server একসাথে হাজার হাজার browser-এর সাথে কথা বলতে পারে, কারণ প্রতিটা কথাবার্তা পুরো 4-tuple দিয়ে চেনা যায়:

```
(client IP, client port, server IP, server port)
```

তোমার server-এর socket (`198.51.100.10:443`) প্রতিটা visitor-এর জন্য একই থাকে; প্রতিটা visitor-এর connection-কে আলাদা করে *তার* IP আর *তার* ephemeral port। এটাই concrete-ভাবে বলে দেয় firewall rule আসলে কী করে: UFW-এর `allow 443` (Cloud, Chapter 2) মানে "এই machine-এর port 443-এ নতুন connection accept করো, যে কোনো source IP আর port থেকে"।

## ৪. TCP বনাম UDP: কিছু পাঠানোর দুটো পথ

দুটোই Transport layer-এ থাকে, দুটোই port ব্যবহার করে, আর মিল এখানেই শেষ।

| | **TCP** | **UDP** |
|---|---|---|
| Connection | আগে তৈরি হয় (handshake), পরে বন্ধ হয় | কিছুই না — শুধু পাঠায় |
| Order | নিশ্চিত, ক্রম ঠিক থাকে | নিশ্চিত না |
| Delivery | নিশ্চিত (হারানো packet আবার পাঠায়) | Best-effort, হারাতে পারে |
| গতি/overhead | শুরু হতে দেরি, বেশি bookkeeping | খুব কম overhead |
| ব্যবহার করে | HTTP, HTTPS, SSH, MySQL | DNS query, video/voice call, game state |

TCP হলো "reliable কিন্তু setup খরচ আছে": যখন প্রতিটা byte গুরুত্বপূর্ণ আর ক্রমও ঠিক থাকতে হবে, তখন এটাই ভালো — তাই এই course-এর প্রায় সবকিছু TCP-তে চলে। UDP হলো "দ্রুত কিন্তু সমস্যা নিজে সামলাও": voice call-এ একটা শব্দ হারালে খুব একটা বোঝা যায় না, কিন্তু একটা perfect retransmit-এর জন্য অপেক্ষা করলে call ব্যবহারযোগ্যই থাকবে না — তাই voice/video protocol UDP ব্যবহার করে, মাঝেমধ্যে হারানো মেনে নিয়েই। DNS query (Cloud, Chapter 3)-ও বেশিরভাগ UDP ব্যবহার করে: একটা ছোট request/response, উত্তর না পেলে আবার চেষ্টা করা সস্তা। (একটা নতুন protocol, **QUIC** — **HTTP/3**-এর পেছনের transport — UDP-এর উপর চলে কিন্তু TCP-এর reliability নিজে বানিয়ে নেয়, বিশেষত নিচের handshake সস্তা করার জন্য। HTTP/3-এর পুরো support এখনো সব server-এ বাড়ছে, তাই এই course TCP-based HTTP/1.1 আর HTTP/2 শেখাতে থাকবে, যা আজও web-এর বিশাল সংখ্যাগরিষ্ঠ অংশ।)

## ৫. TCP three-way handshake

তোমার HTTP request-এর একটা byte-ও চলার আগে, TCP কথাবার্তায় রাজি হয়ে নেয়:

![TCP three-way handshake, কোনো data যাওয়ার আগে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/10-networking/images/tcp-handshake.svg)

1. **SYN** — client একটা ফাঁকা local port নেয়, আর server-কে বলে "আমি শুরু করতে চাই, এই আমার শুরুর sequence number।"
2. **SYN-ACK** — server রাজি হয়, client-এর number acknowledge করে, আর নিজের শুরুর number ফিরে পাঠায়।
3. **ACK** — client server-এর number acknowledge করে। Socket এখন **established**; HTTP request-এর byte এখন যেতে পারে।

Sequence number দিয়ে দুই পক্ষই কোনো packet হারিয়ে গেলে বা ক্রম ভুল হলে বুঝতে পারে আর আবার চাইতে পারে — TCP-এর reliability আসলে এখান থেকেই আসে। Connection শেষ করা একইরকম, একটু ভদ্র বিনিময় (`FIN`, তারপর `ACK`, দুই দিক থেকেই), শুধু চুপ হয়ে যাওয়া না।

এই round trip-এ আসল সময় খরচ হয় — কোনো data যাওয়ার আগে অন্তত একটা পুরো trip server-এ গিয়ে ফিরে আসা, আর TLS (এই course-এর Chapter 4) তার উপর নিজের handshake যোগ করে। ঠিক এই কারণেই connection খোলা রেখে আবার ব্যবহার করা (`Connection: keep-alive`, যা HTTP/1.1 default-ভাবেই করে) একটা site-কে দ্রুত মনে করায়: handshake-এর খরচ একবার দাও, প্রতিটা request-এ না।

## ৬. নিজে দেখে নাও

দুটো ছোট, read-only command, আলাদা কিছু install লাগে না (Ubuntu-তে আগে থেকেই থাকা `iproute2`-এর অংশ):

```bash
ss -tln
```

```
State    Recv-Q  Send-Q  Local Address:Port   Peer Address:Port
LISTEN   0       511     0.0.0.0:80            0.0.0.0:*
LISTEN   0       511     0.0.0.0:443           0.0.0.0:*
LISTEN   0       128     127.0.0.1:3306        0.0.0.0:*
LISTEN   0       128     127.0.0.1:6379        0.0.0.0:*
```

`-t` = TCP socket, `-l` = শুধু listening socket, `-n` = সংখ্যা দেখায়, নাম resolve করে না। এভাবে পড়ো: "এই machine 80 আর 443-এ যে কারো জন্য (`0.0.0.0`) উত্তর দিচ্ছে, আর 3306/6379-এ শুধু নিজের জন্য (`127.0.0.1`)" — MySQL আর Redis ইচ্ছাকৃতভাবে network-এ খোলা নেই। সেই `127.0.0.1` line একটা firewall rule যা তুমি ফ্রি-তে পাও, UFW চালু হওয়ার আগেই।

Active কথাবার্তা দেখতে, শুধু listening socket না:

```bash
ss -tn state established
```

```
State       Recv-Q  Send-Q   Local Address:Port      Peer Address:Port
ESTAB       0       0        198.51.100.10:443       203.0.113.77:51442
```

সেই একটা line হলো Section 3-এর 4-tuple, Section 5-এর handshake হয়ে যাওয়ার পর — তোমার server-এ একজন আসল visitor, request-এর মাঝখানে।

## ৭. মূল কথা

- Internet চলে চারটা practical layer-এ: **Link → Internet (IP) → Transport (TCP/UDP) → Application (HTTP, DNS, SSH, …)**। প্রতিটা শুধু তার নিচেরটুকু জানলেই চলে।
- একটা **IP address** একটা machine-কে নাম দেয়; একটা **port** (0–65535) তার উপর একটা program-কে নাম দেয়; দুটো মিলে হয় একটা **socket**।
- **TCP** আগে connection তৈরি করে, order আর delivery নিশ্চিত করে (HTTP, SSH, MySQL); **UDP** শুধু পাঠায়, সস্তা কিন্তু unreliable (DNS query, voice/video)।
- **Three-way handshake** (SYN, SYN-ACK, ACK) কোনো TCP data যাওয়ার আগে ঘটে — এক কারণ কেন connection আবার ব্যবহার করা নতুন খোলার চেয়ে দ্রুত।
- `ss -tln` দেখায় তোমার server এখন কিসে listen করছে, কোন address-এ — ঠিক সেই port-গুলোতেই তোমার firewall rule লেখা।

## ৮. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. একটা port number আসলে কী চিহ্নিত করে?</strong></summary>

একটা machine-এর কোন program-এর জন্য network traffic — machine-টা নিজে না (সেটা IP address-এর কাজ)।
</details>

<details>
<summary><strong>Q2. Socket কী?</strong></summary>

IP address, port আর protocol-এর combination — concrete-ভাবে, 4-tuple (client IP, client port, server IP, server port) যা একটা নির্দিষ্ট connection চিহ্নিত করে।
</details>

<details>
<summary><strong>Q3. HTTP কেন UDP না করে TCP ব্যবহার করে — একটা কারণ বলো।</strong></summary>

একটা HTTP request/response-এর প্রতিটা byte ক্রমে, কোনো loss ছাড়া পৌঁছাতে হবে — TCP এটা নিশ্চিত করে; UDP করে না।
</details>

<details>
<summary><strong>Q4. TCP handshake-এর তিনটা step ক্রমে বলো।</strong></summary>

SYN (client প্রস্তাব করে) → SYN-ACK (server রাজি হয় আর নিজের প্রস্তাব দেয়) → ACK (client নিশ্চিত করে)। তারপরই data যায়।
</details>

<details>
<summary><strong>Q5. DNS কেন বেশিরভাগ TCP-এর বদলে UDP ব্যবহার করে?</strong></summary>

একটা DNS query ছোট আর উত্তর না এলে আবার চেষ্টা করা সস্তা, তাই TCP-এর বাড়তি reliability আর setup খরচ সাধারণত দরকার হয় না।
</details>

<details>
<summary><strong>Q6. <code>ss -tln</code>-এ একটা service <code>0.0.0.0:3306</code>-এর বদলে <code>127.0.0.1:3306</code>-এ listen করছে — এর মানে কী?</strong></summary>

Service (MySQL) শুধু একই machine থেকে connection নেয়, network থেকে না — কোনো firewall rule চালু হওয়ার আগেই এটা দূর থেকে পাওয়া যায় না।
</details>

<details>
<summary><strong>Q7. একটা TCP connection বারবার ব্যবহার করলে কেন একটা site দ্রুত মনে হয়?</strong></summary>

প্রতিটা নতুন TCP connection-এর নিজের three-way handshake লাগে, যার খরচ অন্তত একটা round trip, কোনো data যাওয়ার আগে। Connection আবার ব্যবহার করলে এই খরচ একবার দিতে হয়, প্রতিটা request-এ না।
</details>

<details>
<summary><strong>Q8. এই course-এ আগেই ব্যবহার করা 1024-এর উপরের একটা port আর তাতে কী চলে, বলো।</strong></summary>

যেমন, 3306 (MySQL) বা 6379 (Redis) — এই chapter-এর table থেকে কোনো port আর তার service সঠিকভাবে বললে গ্রহণযোগ্য।
</details>

## ৯. বিনামূল্যের resource

- Cloudflare: [What is the OSI Model?](https://www.cloudflare.com/learning/ddos/glossary/open-systems-interconnection-model-osi/)
- Cloudflare: [What is a packet?](https://www.cloudflare.com/learning/network-layer/what-is-a-packet/) আর [What is UDP?](https://www.cloudflare.com/learning/protocols/udp/)
- IANA: [Service Name and Transport Protocol Port Number Registry](https://www.iana.org/assignments/service-names-port-numbers/service-names-port-numbers.xhtml)
- DigitalOcean: [Understanding Sockets](https://www.digitalocean.com/community/tutorials/an-introduction-to-networking-terminology-interfaces-and-protocols) (networking terminology primer)
- `man ss` (`ss --help` দিয়ে option-এর সংক্ষিপ্ত তালিকা)

---

**পরের chapter:** [Chapter 2 — DNS-এর basic ছাড়িয়ে: CAA, SRV, DNSSEC, আর `dig`-এর পরেও debugging](02-dns-beyond-the-basics.md)
