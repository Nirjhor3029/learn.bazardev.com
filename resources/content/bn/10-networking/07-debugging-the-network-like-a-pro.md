# Chapter 7 — Network Debug করা একজন Pro-এর মতো: ss, tcpdump, traceroute, আর openssl

> **Course:** Networking & Web Servers · **Chapter:** 7 of 8
> ওয়েবসাইটে পড়ুন: [learn.bazardev.com/networking/07-debugging-the-network-like-a-pro](https://learn.bazardev.com/networking/07-debugging-the-network-like-a-pro)
>
> 🇬🇧 **English পছন্দ?** [Read this chapter in English →](https://learn.bazardev.com/networking/07-debugging-the-network-like-a-pro)

ছয়টা chapter পেরিয়ে, তুমি `ss -tln` (Chapter 1) ব্যবহার করেছো কী listening আছে দেখতে, আর `openssl s_client` (Chapter 3-4) ব্যবহার করেছো একটা handshake পরীক্ষা করতে। এই chapter আলাদা আলাদা command-কে একটা **method**-এ পরিণত করে: "site down" বা "slow" হলে, কোন tool কোন প্রশ্নের জবাব দেয়, কোন order-এ — `ss`-কে আরো গভীরে নিয়ে যাওয়া, আর দুটো tool যুক্ত করা যা এই course এখনো ছোঁয়নি: `tcpdump` (wire-এ আসলে কী হচ্ছে) আর path tracing (`traceroute`/`mtr`)। নিচের প্রতিটা capture real, এই chapter লেখার সময় এই course-এরই server-এর বিরুদ্ধে live চালানো।

## ১. Debugging ladder: চারটা প্রশ্ন, একটা order-এ

একটা connection সমস্যা চারটা level-এর একটায় থাকে, আর order-এ check করলে সবচেয়ে কম সময় নষ্ট হয়:

1. **আমি কি server-টায় পৌঁছাতেই পারছি?** — path tracing (`traceroute`/`mtr`)।
2. **সেখানে কিছু listen করছে কি?** — `ss` (Chapter 1-এ basic দেখিয়েছিল; এই chapter আরো গভীরে যায়)।
3. **Wire-এ আসলে কী হচ্ছে?** — `tcpdump`।
4. **Application protocol (TLS, HTTP) ঠিকমতো কাজ করছে কি?** — `openssl`, `curl`, browser নিজেই।

![The debugging ladder: four questions, from the network path down to the application protocol](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/10-networking/images/debug-layers.svg)

আসল সমস্যা যদি step ১ (firewall পুরোপুরি path block করছে) হয়, তাহলে সরাসরি step ৪-এ চলে যাওয়া TLS error-এর পেছনে ছুটে সময় নষ্ট করে, যা কখনোই আসল কারণ ছিল না।

## ২. Step ১: পৌঁছাতে পারছি কি? Path tracing

**Traceroute**-র trick, [RFC 792](https://www.rfc-editor.org/rfc/rfc792) (ICMP) দিয়ে standardized: TTL=1 দিয়ে একটা packet পাঠাও। প্রথম router এটা decrement করে 0 করে, বাদ দিয়ে দেয়, আর একটা ICMP **Time Exceeded** message দিয়ে জবাব দেয় — যা সেই router-এর address প্রকাশ করে। আরেকটা TTL=2 দিয়ে পাঠালে, এবার **দ্বিতীয়** router জবাব দেয়। বাড়তে থাকা TTL দিয়ে এটা পুনরাবৃত্তি করলে পুরো path এক হপ করে প্রকাশ হয়, আর প্রতিটা জবাবের travel time সেই হপের latency।

(এই course লেখা হচ্ছে যেই machine-এ, তাতে `traceroute` binary নিজেই install করা নেই — `mtr` আছে, আর এটা একই কাজ করে প্লাস প্রতি hop-এর জন্য continuous, live-updating ping statistics, তাই এখানে এটাই দেখানো হলো, আসলেই চালিয়ে।) এই server থেকে Cloudflare-এর public DNS resolver-এ একটা real trace:

```bash
mtr -r -c 3 -n 1.1.1.1
```

```
HOST: nirjhor-workspace           Loss%   Snt   Last   Avg  Best  Wrst StDev
  1.|-- 10.243.2.101               0.0%     3    7.8   5.3   3.1   7.8   2.3
  2.|-- 10.0.66.2                  0.0%     3    4.3   3.3   2.5   4.3   0.9
  3.|-- 62.115.45.148              0.0%     3    5.4  12.5   3.3  28.7  14.1
  4.|-- 62.115.136.146             0.0%     3   25.4  27.0  20.3  35.3   7.6
  5.|-- 62.115.124.117             0.0%     3   21.3  11.8   5.0  21.3   8.5
  6.|-- 62.115.144.199             0.0%     3   11.9   7.3   4.7  11.9   3.9
  7.|-- 162.158.84.237             0.0%     3   38.9  21.2   7.6  38.9  16.1
  8.|-- 1.1.1.1                    0.0%     3    6.5   5.2   4.5   6.5   1.1
```

এটা একটা গল্পের মতো পড়ো: hop ১-২ হলো এই server-এর নিজের hosting provider network (private `10.x` address), hop ৩-৬ হলো provider-এর upstream transit, hop ৭ হলো Cloudflare-এর নিজের network edge (`162.158.x.x` একটা পরিচিত Cloudflare range), আর hop ৮ হলো destination নিজেই। একটা real সমস্যা hop ৫ পর্যন্ত 0% loss আর hop ৬ থেকে 100% loss দেখালে, এটা তোমাকে ঠিক বলে দেয় কোন network boundary-কে সমস্যাটা report করতে হবে — তোমার নিজের box আর hop ১-৫ ঠিক আছে; hop ৫ আর ৬-এর মাঝে কিছু একটা traffic বাদ দিচ্ছে। `Loss%` প্রতি hop-এ দেখার সংখ্যা; একটা slow-কিন্তু-complete hop (এখানে hop ৭-এর বেশি spread-এর মতো) সাধারণত real সমস্যা না — slow আর 0% loss মানে শুধু ওই router তার **নিজের** ICMP traffic-এ জবাব দেওয়াকে কম priority দেয়, **তোমার** traffic বাদ দিচ্ছে না।

## ৩. Step ২: কিছু listen করছে কি? `ss`, `-tln`-এর বাইরে

Chapter 1-এ listening socket দেখতে `ss -tln` ব্যবহার করা হয়েছিল। একটা আগে থেকেই খোলা connection যা *slow মনে হচ্ছে*, তার জন্য `ss -i` (বা একসাথে, `-ti`) kernel-এর নিজের live measurement দেখায় সেই TCP connection-এর জন্য — কোনো packet capture লাগে না:

```bash
ss -ti dst 167.86.127.108
```

```
State  Recv-Q Send-Q  Local Address:Port    Peer Address:Port
ESTAB  0      0        167.86.127.108:57210  167.86.127.108:https
       cubic wscale:10,10 rto:213 rtt:12.102/11.706 ato:40 mss:40448 cwnd:10
       bytes_sent:1849 bytes_acked:1850 bytes_received:5122 segs_out:9 segs_in:7
       pacing_rate 535Mbps delivery_rate 247Mbps minrtt:1.31
```

জানার মতো field গুলো:

- **`rtt:12.102/11.706`** — smoothed round-trip time আর তার variance, millisecond-এ, kernel নিজে এই exact connection-এর real ACK থেকে measure করেছে — একটা live, per-connection ping, কোনো অতিরিক্ত packet না পাঠিয়েই।
- **`cwnd`** — **congestion window**: এই peer-এর দিকে কতটা unacknowledged packet TCP এখন flight-এ allow করে। একটা otherwise healthy path-এ `cwnd` কম থেকে আটকে থাকা মানে কিছু একটা (packet loss, একটা slow receiver) throughput আটকে রাখছে।
- **`retrans`** (উপরে নেই — এই loopback-গতির connection-এ কিছুই হারায়নি) থাকলে দেখাতো কত segment আসলেই হারিয়ে আবার পাঠানো হয়েছে।

এটা application layer-কে সন্দেহ করার আগেই জবাব দেয় "এই connection-এর জন্য TCP নিজেই কি healthy?"

## ৪. Step ৩: Wire-এ আসলে কী হচ্ছে? `tcpdump`

`ss` একটা connection-এর kernel-এর *summary* দেখায়। `tcpdump` আসল packet গুলো দেখায়। এই server-এরই নিজের HTTPS port-এ একটা request যাওয়ার সময় নেওয়া একটা real capture:

```bash
sudo tcpdump -i lo -n -c 10 'tcp port 443'
```

```
10:51:00.817554 IP 127.0.0.1.51492 > 127.0.0.1.443: Flags [S], seq 1918073176, win 65495, ...
10:51:00.817593 IP 127.0.0.1.443 > 127.0.0.1.51492: Flags [S.], seq 744179515, ack 1918073177, ...
10:51:00.817611 IP 127.0.0.1.51492 > 127.0.0.1.443: Flags [.], ack 1, win 64, ...
10:51:00.824377 IP 127.0.0.1.51492 > 127.0.0.1.443: Flags [P.], seq 1:1579, ack 1, ..., length 1578
10:51:00.824417 IP 127.0.0.1.443 > 127.0.0.1.51492: Flags [.], ack 1579, win 78, length 0
10:51:00.826143 IP 127.0.0.1.443 > 127.0.0.1.51492: Flags [P.], seq 1:5012, ack 1579, ..., length 5011
10:51:00.826156 IP 127.0.0.1.51492 > 127.0.0.1.443: Flags [.], ack 5012, win 103, length 0
10:51:00.850851 IP 127.0.0.1.51492 > 127.0.0.1.443: Flags [P.], seq 1579:1643, ack 5012, ..., length 64
10:51:00.851511 IP 127.0.0.1.443 > 127.0.0.1.51492: Flags [P.], seq 5012:5079, ack 1643, ..., length 67
10:51:00.851998 IP 127.0.0.1.51492 > 127.0.0.1.443: Flags [P.], seq 1643:1771, ack 5079, ..., length 128
```

এটা আগের chapter-এর প্রতিটা idea, আসল byte হিসেবে দেখা যাচ্ছে:

- Packet ১-৩ (`[S]`, `[S.]`, `[.]`) হলো Chapter 1-এর **TCP three-way handshake** — SYN, SYN-ACK, ACK — এখন বর্ণনা না করে সরাসরি দেখা যাচ্ছে।
- Packet ৪, client থেকে ১৫৭৮ byte (`[P.]`, `seq 1:1579`): এটা Chapter 4-এর TLS **ClientHello** — এর size বড় কারণ এতে SNI hostname আর TLS 1.3-এর এক-round-trip handshake-এর জন্য `key_share` থাকে।
- Packet ৬, server থেকে ৫০১১ byte: `ServerHello` + certificate chain + `Finished` flight — এটাও Chapter 4 থেকে — client-এর `key_share`-এর পরপরই একটা মাত্র TCP segment হিসেবে, encrypted, আসছে, যখনই দুই পক্ষ একটা shared secret derive করে ফেলেছে।
- এর পরের ছোট packet গুলো TLS `Finished` exchange আর আসল HTTP request/response-এর শুরু।

Filter syntax (`'tcp port 443'`) একটা **BPF (Berkeley Packet Filter)** expression — `tcpdump`-এর নিজের manual `host`, `port`, `tcp`, `udp`, আর `and`/`or`/`not`-কে core building block হিসেবে document করে (যেমন একটা specific peer-এর জন্য `'host 1.1.1.1 and tcp port 443'`)। `-n` DNS lookup skip করে (দ্রুত, আর তুমি যেই exact hostname debug করার চেষ্টা করছো সেটার পেছনে IP ঢেকে দেয় না), `-c` সবসময় চলার বদলে একটা fixed count-এর পর থামে। Capture করতে `CAP_NET_RAW` লাগে — ব্যবহারিকভাবে, `sudo`।

## ৫. Step ৪: Application protocol কি আসলেই কাজ করছে? `openssl`

Chapter 3-4 আগে থেকেই SNI আর certificate chain পরীক্ষা করতে `openssl s_client` ব্যবহার করেছে। একটা standing **debugging** অভ্যাস হিসেবে (একবারের inspection না), একই tool জবাব দেয় "এই certificate expire হতে আর কত দিন বাকি?", browser না খুলেই:

```bash
echo | openssl s_client -connect learn.bazardev.com:443 -servername learn.bazardev.com 2>/dev/null | openssl x509 -noout -subject -dates
```

```
subject=CN=learn.bazardev.com
notBefore=Nov 10 15:13:51 2026 GMT
notAfter=Feb  8 15:13:50 2027 GMT
```

এটা ঠিক সেই রকম এক-লাইনের command যা একটা cron job বা একটা monitoring check-এ (Monitoring Chapter 4-এ Prometheus cover করা হয়েছিল; certificate-expiry probe alert করার মতো একটা স্বাভাবিক জিনিস) রাখার মতো, একজন রাগী user-এর কাছ থেকে expire হওয়া certificate জানার বদলে।

## ৬. সব একসাথে: একটা troubleshooting table

| Symptom | প্রথমে কী check করবে | কী ঠিক আর কী ভুল সেটা বলে |
|---|---|---|
| "Site একদমই load হচ্ছে না" | server-এর IP-তে `mtr`/`traceroute` | Network path আসলেই খোলা, নাকি কোনো hop-এ মরে যাচ্ছে? |
| "Site আমার জন্য load হচ্ছে, তাদের জন্য না" | server-এ `ss -tln` (Chapter 1) | Service আসলেই তারা যেই address-এ পৌঁছাতে চায় সেখানে listen করছে কি? |
| "Slow, down না" | live connection-এ `ss -ti` | TCP নিজেই healthy (`rtt`, `cwnd`, `retrans`), নাকি সমস্যা app-এর ভেতরে, আরো উপরে? |
| "কিছু ভুল হচ্ছে কিন্তু আসলে কী পাঠানো হচ্ছে জানি না" | narrow filter দিয়ে `tcpdump` | আন্দাজ না করে, আসল byte দেখো — handshake, TLS, HTTP header |
| "HTTPS-specific error, cert warning, handshake fail" | `openssl s_client -servername ...` | কোন certificate ফিরে এলো, কোন TLS version, এখানে SNI গুরুত্বপূর্ণ কিনা (Chapter 3) |
| "DNS জড়িত মনে হচ্ছে" | `dig +trace` (Chapter 2) | DNS delegation chain-এর ঠিক কোন hop জবাব দেওয়া বন্ধ করেছে |

## ৭. মূল বিষয়গুলো (Key takeaways)

- Order মেনে debug করো: **path reachability → কিছু listen করছে কি → wire-এ কী আছে → application protocol ঠিক কিনা।** এগিয়ে যাওয়া একটা আগের layer-এর সমস্যার symptom-এর পেছনে সময় নষ্ট করায়।
- **Path tracing** (`traceroute`/`mtr`) বাড়তে থাকা TTL-এর packet-এ ICMP Time Exceeded জবাব ব্যবহার করে hop-by-hop path প্রকাশ করে ([RFC 792](https://www.rfc-editor.org/rfc/rfc792)); `mtr` প্রতি hop-এ continuous ping statistics যুক্ত করে।
- `ss -ti` একটা আগে থেকে থাকা TCP connection-এর জন্য kernel-এর নিজের live measurement (`rtt`, `cwnd`, `retrans`) প্রকাশ করে, কোনো packet capture ছাড়াই।
- `tcpdump` wire-এর আসল byte দেখায় — আগের chapter-গুলোর TCP handshake আর TLS message **বর্ণনা না করে সরাসরি দেখার** আসল জায়গা। BPF filter (`host`, `port`, `tcp`/`udp`) একটা capture-কে ঠিক যা গুরুত্বপূর্ণ তাতেই সীমিত করে।
- `openssl s_client`-কে `openssl x509 -noout -dates`-এ pipe করা একটা সত্যিই কাজে লাগার মতো standing habit: browser ছাড়াই একটা live certificate-এর expiry check করা।

## ৮. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>প্রশ্ন ১. Traceroute/mtr কীভাবে একটা path-এর প্রতিটা router খুঁজে পায়?</strong></summary>

এটা বাড়তে থাকা TTL-এর packet পাঠায় (১ থেকে শুরু)। প্রতিটা router যা একটা packet-এর TTL 0-এ decrement করে তা বাদ দিয়ে দেয় আর একটা ICMP Time Exceeded message দিয়ে জবাব দেয়, যা সেই router-এর address প্রকাশ করে — প্রতি TTL value-এ এক hop।
</details>

<details>
<summary><strong>প্রশ্ন ২. <code>ss -ti</code> output-এ, <code>cwnd</code> field কী বলে?</strong></summary>

Congestion window — ওই peer-এর দিকে কতটা unacknowledged packet TCP এখন flight-এ allow করে। একটা otherwise healthy path-এ কম আটকে থাকা cwnd বোঝায় কিছু একটা (যেমন packet loss) throughput আটকে রাখছে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা TLS connection-এর tcpdump capture-এ, ClientHello packet প্রায়ই তার আগের তিনটা handshake packet-এর চেয়ে লক্ষণীয়ভাবে বড় কেন?</strong></summary>

Three-way handshake packet গুলো (SYN, SYN-ACK, ACK) কোনো application data বহন করে না। ClientHello SNI hostname আর, TLS 1.3-এ, এক-round-trip handshake-এর জন্য দরকারি key_share বহন করে — real payload, শুধু handshake flag না।
</details>

<details>
<summary><strong>প্রশ্ন ৪. Hostname resolve হতে দেওয়ার বদলে কেন <code>tcpdump -n</code> ব্যবহার করবে?</strong></summary>

এটা দ্রুত (প্রতি packet-এ DNS lookup লাগে না), আর এটা জড়িত literal IP address ঢেকে দেয় না — যা গুরুত্বপূর্ণ যখন hostname resolution-টাই সেই জিনিসের অংশ হতে পারে যা তুমি debug করছো।
</details>

<details>
<summary><strong>প্রশ্ন ৫. একজন user-এর জন্য একটা site পুরোপুরি unreachable। HTTPS/TLS debugging-এ ডুব দেওয়ার আগে কেন <code>mtr</code>/<code>traceroute</code> check করবে?</strong></summary>

Network path নিজেই server-এ পৌঁছানোর আগে কোনো hop-এ ভাঙা থাকলে, কোনো পরিমাণ TLS বা application-layer debugging-ই এটা ব্যাখ্যা করবে না — connection ওই পর্যন্তই পৌঁছায় না। আগে reachability check করা একটা আগের, সহজ সমস্যার symptom-এর পেছনে ছোটা এড়ায়।
</details>

## ৯. ফ্রি রিসোর্স

- RFC Editor: [RFC 792 — Internet Control Message Protocol](https://www.rfc-editor.org/rfc/rfc792)
- `man ss` / `ss --help` (এই chapter-এ `-i`/`-t` ব্যবহার হয়েছে; Chapter 1-এ `-l`/`-n`)
- `man tcpdump` — filter expression (BPF) syntax
- `man mtr`
- OpenSSL: [`s_client` command](https://docs.openssl.org/master/man1/openssl-s_client/)

---

**পরের chapter:** [Chapter 8 — Real project: a load-balanced, multi-domain Laravel setup with TLS](08-real-project-load-balanced-laravel.md)
