# Chapter 2 — Linux Server-এর স্বাস্থ্য: CPU, Memory, Disk আর Log

> **Course:** Monitoring & Logging · **Chapter:** 2 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/monitoring/02-linux-server-health)

কোনো ঝকঝকে tool-এর আগে তোমার শেখা উচিত **একটা Linux server দেখে বলতে পারা সে সুস্থ কিনা**। এই course-এর প্রতিটা dashboard আসলে এই একই সংখ্যাগুলো, সময়ের ওপর আঁকা। তাই আমরা শুরু করি হাতে এগুলো পড়া শিখে, এমন command দিয়ে যা আগে থেকেই install করা আছে।

Chapter 1-এ তুমি *saturation* signal দেখেছ: system কতটা "ভরা"। চারটা resource ভরে যেতে পারে: **CPU, memory, disk আর network**। এই chapter দেখায় প্রতিটা কীভাবে পরীক্ষা করবে, তারপর যা দেখলে তা ব্যাখ্যা করা log কীভাবে পড়বে।

> নিচের উদাহরণ output-গুলো সাধারণ দৃষ্টান্ত। তোমার সংখ্যা আলাদা হবে। প্রতিটা command নিজের server-এ চেষ্টা করো। এগুলো শুধু *পড়ে*, একটা নির্দোষ ব্যতিক্রম ছাড়া: অংশ ২-এর ছোট CPU-load অনুশীলন, যা তুমি নিজেই থামাও।

![কোথায় দেখবে: পাঁচটা পরীক্ষা ও তাদের command](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/07-monitoring/images/server-health-checks.svg)

## ১. প্রথম দেখা: `uptime` আর load average

```bash
uptime
```

```
 09:39:28 up 16 days, 16:21,  6 users,  load average: 0.06, 0.35, 0.56
```

- **up 16 days:** শেষ reboot-এর পর কতদিন। অপ্রত্যাশিত reboot নিজেই একটা সূত্র।
- **load average**-এ তিনটা সংখ্যা: শেষ **১, ৫ ও ১৫ মিনিটের** গড়।

**Load average** গোনে সেই process যা চলছে **বা চলার অপেক্ষায়** (আর Linux-এ disk-এর অপেক্ষায়ও)। পড়তে হলে **CPU core**-এর সংখ্যার সাথে তুলনা করো:

```bash
nproc          # CPU core-এর সংখ্যা, যেমন 6
```

| ৬-core machine-এ load (১ মিনিট) | মানে |
|---|---|
| প্রায় ০ থেকে ৩ | স্বচ্ছন্দ |
| প্রায় ৬ | সব core ব্যস্ত: "ভরা" |
| ৬-এর অনেক ওপরে (যেমন ১৫) | কাজ **সারিতে দাঁড়িয়ে**: সব ধীর লাগবে |

**প্রবণতাও** পড়ো: `0.06, 0.35, 0.56` কমছে (শান্ত হচ্ছে)। `5.2, 2.1, 0.8` বাড়ছে (কিছু একটা এইমাত্র শুরু হয়েছে)।

## ২. CPU: কে ব্যবহার করছে?

```bash
top            # সরাসরি দেখা; বের হতে q চাপো
htop           # আরও বন্ধুসুলভ, install থাকলে (sudo apt install htop)
```

`top`-এর সারাংশ লাইনই সবচেয়ে কাজের অংশ:

```
%Cpu(s):  3.1 us,  6.1 sy,  0.0 ni, 90.8 id,  0.0 wa,  0.0 hi,  0.0 si,  0.0 st
```

| Field | মানে | সমস্যা যখন... |
|---|---|---|
| **us** | তোমার program-এ কাটানো সময় (user space) | অনেক বেশি: কোনো app কঠিন কাজ বা loop-এ |
| **sy** | Kernel-এ কাটানো সময় (system call, network) | অনেক বেশি: ভারী I/O বা network সামলানো |
| **id** | Idle: অব্যবহৃত | 0-র কাছাকাছি মানে CPU saturated |
| **wa** | **Disk-এর অপেক্ষা (I/O wait)** | বেশি: bottleneck হলো disk, CPU নয় |
| **st** | Hypervisor **চুরি করা** সময় | VPS-এ বেশি: গোলমেলে প্রতিবেশী, বা তুমি plan-এর CPU ভাগ ছাড়িয়েছ |

সারাংশের নিচে process-এর তালিকা দেখায় **কে** CPU ব্যবহার করছে। `top`-এ **`P`** চাপলে CPU অনুযায়ী আর **`M`** চাপলে memory অনুযায়ী সাজে।

**চেষ্টা করো।** নির্দোষ কিছু load বানাও, তা ফুটে উঠতে দেখো, আর থামাও:

```bash
yes > /dev/null &        # এক CPU core পোড়ায়; background-এ চলে
top                      # তালিকার ওপরে "yes" খোঁজো; q চাপো
kill %1                  # থামাও
```

## ৩. Memory: `free -h` ঠিকভাবে পড়ো

```bash
free -h
```

```
               total        used        free      shared  buff/cache   available
Mem:            11Gi       8.6Gi       1.8Gi       461Mi       2.0Gi       3.1Gi
Swap:             0B          0B          0B
```

নতুনদের সবচেয়ে প্রচলিত ভুল: **"free মাত্র ১.৮ GB, memory শেষ!"** আবশ্যিক নয়। Linux বাড়তি RAM ফাইলের **cache** হিসেবে ব্যবহার করে (`buff/cache`) আর program-এর লাগলে সাথে সাথে ফিরিয়ে দেয়। যে সংখ্যাটা গুরুত্বপূর্ণ তা হলো **`available`**: program **swap না করে** আর কতটা memory পেতে পারে।

- **সুস্থ:** `available` `total`-এর একটা স্বচ্ছন্দ অংশ আর স্থির থাকে।
- **বিপদ:** `available` প্রায় শূন্যের দিকে যাচ্ছে, আর `swap` ভারীভাবে ব্যবহার হচ্ছে। ভারী swapping server-কে হামাগুড়ি দেওয়ায়।

কিছু program ন্যায্যভাবেই অনেক memory ব্যবহার করে। Database চিরাচরিত উদাহরণ: MySQL ইচ্ছা করেই RAM-এ বড় **buffer pool** রাখে, তাই memory-র অর্ধেক নেওয়া database স্বাভাবিক হতে পারে। যা স্বাভাবিক *নয়* তা হলো memory যা **সীমাহীনভাবে বাড়তেই থাকে** (**memory leak**)।

**OOM killer।** Memory সত্যিই ফুরালে kernel বাঁচতে একটা process মেরে ফেলে, প্রায়ই তোমার সবচেয়ে বড়টা (database বা app)। এটা খোঁজো:

```bash
sudo journalctl -k | grep -i "out of memory"
sudo dmesg -T | grep -i "killed process"
```

কোনো service "এলোমেলোভাবে উধাও" হলে প্রথমে এখানে দেখো।

সবচেয়ে বেশি memory ব্যবহারকারী খোঁজো:

```bash
ps aux --sort=-%mem | head -5
```

## ৪. Disk: নীরব ঘাতক

ভরা disk outage-এর সবচেয়ে প্রচলিত কারণগুলোর একটা, আবার ঠেকানো সবচেয়ে সহজ কারণগুলোরও একটা। ভরা disk database-কে লেখা থেকে আটকাতে, log ভাঙতে আর app crash করাতে পারে।

```bash
df -h                    # প্রতিটা filesystem-এ জায়গা কতটা ব্যবহৃত
```

```
Filesystem      Size  Used Avail Use% Mounted on
/dev/sda1       193G   27G  166G  14% /
```

প্রতিটা mount করা filesystem-এর **Use%** দেখো। **Inode**-ও দেখো, "ফাইলের সংখ্যার" সীমা। প্রচুর জায়গা বাকি থাকতেও inode ফুরাতে পারে (লক্ষ লক্ষ ছোট ফাইলে):

```bash
df -i
```

**কে disk খাচ্ছে খোঁজা:**

```bash
sudo du -h --max-depth=1 /var | sort -h | tail      # /var-এর নিচে সবচেয়ে বড় folder
sudo du -h --max-depth=1 /var/log | sort -h | tail
```

(`ncdu` আরও বন্ধুসুলভ interactive সংস্করণ: `sudo apt install ncdu`।)

সাধারণ সন্দেহভাজন:

| অপরাধী | কোথায় | কী দেখবে |
|---|---|---|
| কখনো rotate না হওয়া **log ফাইল** | `/var/log`, `storage/logs/` | `du -sh /var/log` |
| **Database dump ও backup** | যেখানেই রাখো | backup folder-এ `ls -lh` |
| **Docker image ও volume** | `/var/lib/docker` | `docker system df` |
| **পুরনো release, upload, cache** | তোমার app folder | `du` করো |
| **systemd journal** | `/var/log/journal` | `journalctl --disk-usage` |

জায়গা খালি করতে যা বোঝো না তা কখনো মুছো না। আগে জানো ওগুলো কী, আর যেকোনো জায়গায় `rm -rf` চালানোর আগে ভাবো।

**Disk-এর গতি (I/O)।** `top`-এ `wa` বেশি দেখালে কোন disk ব্যস্ত তা খোঁজো:

```bash
vmstat 1 5               # প্রতি সেকেন্ডে এক লাইন, পাঁচবার; "wa" আর "b" দেখো
iostat -x 1 3            # প্রতি disk-এর ব্যবহার (লাগে: sudo apt install sysstat)
```

`iostat -x`-এ যে disk-এর `%util` 100-র কাছে আর অপেক্ষার সময় বাড়ছে, সেটা saturated।

## ৫. Process আর network

**Process:**

```bash
ps aux --sort=-%cpu | head -5        # সবচেয়ে বেশি CPU ব্যবহারকারী
ps aux --sort=-%mem | head -5        # সবচেয়ে বেশি memory ব্যবহারকারী
systemctl --failed                   # যে service crash করেছে
```

**Network-এ কী শুনছে** (Cloud course-এর firewall নিয়মের সাথে মেলাও):

```bash
sudo ss -tlnp
```

```
State   Recv-Q  Send-Q  Local Address:Port   Process
LISTEN  0       511     0.0.0.0:80           nginx
LISTEN  0       128     0.0.0.0:22           sshd
LISTEN  0       70      127.0.0.1:3306       mysqld
```

**Local Address** কলাম পড়ো: `127.0.0.1:3306` মানে MySQL শুধু এই machine থেকে পৌঁছানো যায় (ভালো)। `0.0.0.0:3306` মানে সে প্রতিটা interface-এ শোনে (firewall এটা আটকায় কিনা দেখো)। **`0.0.0.0`-এ বাঁধা database port-এর ব্যাপারটা তদন্তযোগ্য।**

সংযোগের সংখ্যা আর response-time-এর ভাঙন:

```bash
ss -s                                # সারাংশ: কয়টা TCP connection
curl -o /dev/null -s -w "dns=%{time_namelookup} connect=%{time_connect} ttfb=%{time_starttransfer} total=%{time_total}\n" https://myshop.com/
```

`ttfb` মানে **time to first byte**: যে মুহূর্তে server সাড়া দিতে শুরু করে। `connect` কম অথচ ttfb বেশি হলে সাধারণত *application* ধীর, network নয়।

## ৬. Log পড়া

Metric বলে *যে* কিছু ভুল। **Log বলে কেন।** Linux-এ দুটো প্রধান জায়গা:

**ক. `journalctl`** **systemd journal** পড়ে, যেখানে systemd-চালু service (nginx, mysql, তোমার queue worker, ssh) তাদের output লেখে:

```bash
journalctl -u nginx --since "1 hour ago"     # একটা service, সাম্প্রতিক
journalctl -u myshop-worker -f               # সরাসরি অনুসরণ (থামাতে Ctrl+C)
journalctl -p err -b                         # error ও তার চেয়ে খারাপ, এই boot-এ
journalctl -k                                # kernel message (OOM kill, disk error)
journalctl --disk-usage                      # journal কত বড়?
```

**একটা প্রচলিত চমক:** সাধারণ user হিসেবে `journalctl` চালালে হয়তো শুধু *নিজের* message দেখবে আর একটা hint, "You are currently not seeing messages from other users and the system"। `sudo journalctl ...` ব্যবহার করো, বা তোমার user-কে `systemd-journal` (বা `adm`) group-এ যোগ করো।

**খ. `/var/log`-এর সাধারণ ফাইল:**

| ফাইল | কী আছে |
|---|---|
| `/var/log/syslog` | সাধারণ system message |
| `/var/log/auth.log` | **Login আর `sudo`**: ব্যর্থ SSH চেষ্টা এখানে থাকে |
| `/var/log/nginx/access.log`, `error.log` | প্রতিটা web request, আর web server error |
| `/var/log/mysql/error.log` | Database error |
| তোমার app-এর `storage/logs/laravel.log` | Laravel application error (Chapter 3) |

পড়ার সুবিধাজনক উপায়:

```bash
sudo tail -n 50 /var/log/nginx/error.log          # শেষ ৫০ লাইন
sudo tail -f /var/log/nginx/error.log             # সরাসরি অনুসরণ
sudo grep -i "error" /var/log/syslog | tail       # খোঁজা
sudo grep "Failed password" /var/log/auth.log | wc -l     # ব্যর্থ login গোনা
```

উদাহরণ: কয়টা request 5xx error দিয়েছে? (Nginx-এর default log format-এ status code ৯ম field):

```bash
sudo awk '$9 ~ /^5/' /var/log/nginx/access.log | wc -l
```

Log **rotate** না হলে সীমাহীন বাড়ে (পুরনোগুলো compress ও মোছা হয়)। Ubuntu এটা করে **logrotate** দিয়ে। পরের chapter-এ তুমি নিজের app-এর জন্য এটা বসাবে।

## ৭. ৬০ সেকেন্ডের checklist

কেউ "server ধীর" বললে এগুলো ক্রমে চালাও। প্রায় এক মিনিট লাগে আর বেশিরভাগ সমস্যা ধরা পড়ে:

```bash
uptime                          # ১. load: কিছু কি সারিতে আছে?
free -h                         # ২. memory: "available" কম? swap হচ্ছে?
df -h                           # ৩. disk: কোনোটা 100%-এর কাছে?
top                             # ৪. কে CPU / memory নিচ্ছে? "wa" বেশি?
sudo ss -tlnp                   # ৫. service আদৌ শুনছে কি?
sudo journalctl -p err -n 50    # ৬. সাম্প্রতিক error
sudo tail -n 50 /var/log/nginx/error.log   # ৭. web server error
```

Performance engineer Brendan Gregg Netflix-এর জন্য একটা বিখ্যাত ছোট তালিকায় এই ধারণা জনপ্রিয় করেছেন (resource দেখো)।

## ৮. মোটামুটি সতর্কতার মাত্রা

এগুলো **অভিজ্ঞতার নিয়ম**, আইন নয়। তোমার server ও কাজের চাপ অনুযায়ী বদলাও।

| কী | কখন খতিয়ে দেখবে... |
|---|---|
| প্রতি core-এ load average | অনেকক্ষণ প্রায় ১-এর ওপরে |
| CPU idle (`id`) | কয়েক মিনিট প্রায় ১০ থেকে ২০%-এর নিচে |
| I/O wait (`wa`) | কিছুক্ষণ প্রায় ২০%-এর ওপরে |
| Memory `available` | total-এর প্রায় ১০ থেকে ১৫%-এর নিচে, বা swap ভারী ব্যবহারে |
| Disk `Use%` | প্রায় ৮০%-এর ওপরে (alert), ৯০% (জরুরি) |
| Inode `IUse%` | প্রায় ৮০%-এর ওপরে |
| ব্যর্থ service | যেকোনো (`systemctl --failed`) |

## ৯. এটুকু কেন যথেষ্ট নয়

এখানের সবকিছু কাজ করে, কিন্তু তুমি **হাতে, শুধু মনে পড়লে** পরীক্ষা করছ। এটা বলে server *এখন* কেমন, রাত ৩টায় কী ঘটেছিল বা গত এক সপ্তাহে কেমন বদলেছে তা নয়। পরের chapter-গুলো এটা ঠিক করে: সাজানো ও rotate করা log (Chapter 3), তারপর এই একই সংখ্যা প্রতি কয়েক সেকেন্ডে রেকর্ড করতে **Prometheus** (Chapter 4), আঁকতে **Grafana** (Chapter 5) আর তোমাকে জাগাতে **alert** (Chapter 6)।

## ১০. মূল কথা

- চারটা resource saturate করতে পারে: **CPU, memory, disk, network**। আগে হাতে প্রতিটা পরীক্ষা করো।
- `uptime` **load average** দেখায়; `nproc`-এর সাথে তুলনা করো, আর প্রবণতা (১, ৫, ১৫ মিনিট) পড়ো।
- `top`-এ: **`id`** idle, **`wa`** disk-এর অপেক্ষা, **`st`** hypervisor-এর চুরি। `P` CPU অনুযায়ী, `M` memory অনুযায়ী সাজায়।
- `free -h`-এ **`available`** বিশ্বাস করো, `free` নয়; Linux বাড়তি RAM cache-এ লাগায়। **OOM killer**-এর মারা `journalctl -k`-তে দেখা যায়।
- ভরা disk আসল outage ঘটায়: `df -h` ও `df -i` দেখো; `du` দিয়ে ভারী জিনিস খোঁজো; log, backup, Docker ও journal-ই সাধারণ সন্দেহভাজন।
- `ss -tlnp` দেখায় কী কোথায় শুনছে। `0.0.0.0`-এ থাকা database-এ নজর দেওয়া দরকার।
- `journalctl -u <service>` আর `/var/log`-এর ফাইল ব্যবহার করো। সাধারণ user হিসেবে সব দেখতে `sudo` লাগতে পারে।
- **৬০ সেকেন্ডের checklist**: `uptime`, `free -h`, `df -h`, `top`, `ss -tlnp`, তারপর error log।

## ১১. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. একটা ৬-core server-এর ১ মিনিটের load average ১৫। এটা কী ইঙ্গিত দেয়?</strong></summary>

৬টা core যত কাজ চালাতে পারে তার চেয়ে অনেক বেশি কাজ অপেক্ষায়, তাই task সারিতে দাঁড়িয়ে আছে আর server ধীর লাগবে।
</details>

<details>
<summary><strong>Q2. `top`-এ `wa` বেশি মানে কী?</strong></summary>

CPU disk I/O-র অপেক্ষায়, তাই bottleneck disk, CPU নয়।
</details>

<details>
<summary><strong>Q3. `free -h` ১১ GB-র মধ্যে মাত্র ১.৮ GB "free" দেখাচ্ছে। Server কি memory-শূন্য? কী দেখবে?</strong></summary>

আবশ্যিক নয়। Linux বাড়তি RAM cache হিসেবে ব্যবহার করে। `available` কলাম আর swap ভারীভাবে ব্যবহার হচ্ছে কিনা দেখো।
</details>

<details>
<summary><strong>Q4. তোমার service নিজের log-এ কোনো error ছাড়াই বারবার উধাও হচ্ছে। কারণ কোথায় পেতে পারো?</strong></summary>

Kernel-এর OOM killer হয়তো মেরেছে। `journalctl -k` বা `dmesg`-এ "out of memory" বা "killed process" খোঁজো।
</details>

<details>
<summary><strong>Q5. `df -h` ৪০% ব্যবহৃত দেখায়, তবু নতুন ফাইল বানানো যাচ্ছে না। আর কী দেখবে?</strong></summary>

`df -i` দিয়ে inode ব্যবহার। জায়গা থাকতেও filesystem-এর inode (ফাইলের এন্ট্রি) ফুরাতে পারে।
</details>

<details>
<summary><strong>Q6. সাধারণ user হিসেবে `journalctl` প্রায় কিছুই দেখায় না কেন?</strong></summary>

সাধারণ user `systemd-journal` বা `adm` group-এ না থাকলে শুধু নিজের message দেখে। `sudo` ব্যবহার করো বা group-এ যোগ দাও।
</details>

<details>
<summary><strong>Q7. `ss -tlnp`-এ MySQL `0.0.0.0:3306` দেখাচ্ছে। এটা তদন্তযোগ্য কেন?</strong></summary>

সে প্রতিটা network interface-এ শোনে। Firewall port 3306 না আটকালে database internet থেকে পৌঁছানো যেতে পারে। সাধারণত তার শুধু `127.0.0.1` বা private address-এ শোনা উচিত।
</details>

## ১২. বিনামূল্যের resource

- Brendan Gregg: [Linux Performance](https://www.brendangregg.com/linuxperf.html) (বিশ্বের এক বিশেষজ্ঞের tool ও diagram)
- Netflix-এর checklist: [Linux Performance Analysis in 60,000 Milliseconds (PDF)](https://www.brendangregg.com/Articles/Netflix_Linux_Perf_Analysis_60s.pdf)
- Manual: [`free`](https://man7.org/linux/man-pages/man1/free.1.html) ও [`top`](https://man7.org/linux/man-pages/man1/top.1.html)
- systemd: [`journalctl` manual](https://www.freedesktop.org/software/systemd/man/latest/journalctl.html)

---

**Next:** Chapter 3 — Application log: level, গঠন আর rotation *(coming soon)*
