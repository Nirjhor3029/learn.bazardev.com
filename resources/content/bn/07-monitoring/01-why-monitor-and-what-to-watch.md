# Chapter 1 — কেন Monitor করবে? Metric, Log, Alert আর চারটা Golden Signal

> **Course:** Monitoring & Logging · **Chapter:** 1 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/monitoring/01-why-monitor-and-what-to-watch)

তোমার app এখন live (Cloud course)। এবার এমন একটা প্রশ্ন যার মুখোমুখি প্রতিটা team শেষে হয়:

> **"User আমাদের জানানোর আগে আমরা কীভাবে বুঝব কিছু ভেঙেছে?"**

সৎ উত্তর যদি হয় "কোনো customer message দেয়", তাহলে তোমার এখনও monitoring নেই। এই course ধাপে ধাপে বিনামূল্যের tool দিয়ে সেটা ঠিক করে।

## ১. একটা গল্প যা তুমি চিনবে

রাত ২টায় database-এর disk ভরে গেল। Order ব্যর্থ হতে শুরু করল। কেউ জেগে নেই। সকাল ৯টায় একজন customer email করল: "তোমাদের site ভাঙা"। তুমি server খুলে দুই মিনিটে ভরা disk পেলে, আর দশ মিনিটে ঠিক করলে। কিন্তু site **সাত ঘণ্টা** বন্ধ ছিল, আর ঠিক করতে লেগেছে দশ মিনিট।

দামি অংশ মেরামত ছিল না। দামি ছিল **না জানা**। Monitoring "সাত ঘণ্টা"-কে "পাঁচ মিনিট" বানায়।

## ২. Monitoring, logging, alerting: তিনটা আলাদা কাজ

| কাজ | কোন প্রশ্নের উত্তর দেয় | উদাহরণ |
|---|---|---|
| **Monitoring** (metric) | "System এখন কেমন চলছে, আর সময়ের সাথে?" | CPU 85%, 120 request/second, 2% error |
| **Logging** | "ঠিক কী ঘটেছিল?" | `ERROR Payment failed for order 4812: gateway timeout` |
| **Alerting** | "কাকে, কখন জানাতে হবে?" | তোমার ফোনে message: "disk 95% full" |

ওরা একসাথে কাজ করে:

1. **Metric** দেখায় *যে* কিছু ভুল (14:03-এ error rate লাফিয়েছে)।
2. **Alert** নিশ্চিত করে একজন মানুষ *শোনে*।
3. **Log** ব্যাখ্যা করে *কেন* (gateway timeout হয়েছিল)।

![Monitoring, logging ও alerting একসাথে কাজ করছে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/07-monitoring/images/monitoring-overview.svg)

**Trace** (কয়েকটা service-এর মধ্য দিয়ে একটা request অনুসরণ)-এর সাথে এগুলোকে প্রায়ই বলে **observability-র তিন স্তম্ভ**: metric, log আর trace। একটা Laravel server-এর জন্য তুমি বেশিরভাগ প্রথম দুটো ব্যবহার করবে। Trace গুরুত্বপূর্ণ হয় অনেক service থাকলে।

**Monitoring বনাম observability:** monitoring সেই সমস্যার ওপর নজর রাখে যা তুমি *আগে ভেবেছ* ("disk ভরা")। Observability মানে তোমার system এত data দেয় যে তুমি *না ভাবা* সমস্যাও তদন্ত করতে পারো। ভালো metric আর log জমিয়ে তুমি সেদিকে এগোও।

## ৩. Metric কী?

**Metric** হলো **সময়ের সাথে মাপা একটা সংখ্যা**। যেমন প্রতি ১৫ সেকেন্ডে নেওয়া "CPU usage" একটা metric। বিন্দুর ধারা হিসেবে জমা থাকলে তা graph করা যায় আর নিয়মের সাথে মেলানো যায়।

```
time      cpu_percent
14:00:00      22
14:00:15      24
14:00:30      91   <- হঠাৎ লাফ
14:00:45      90
```

Metric ছোট, জমা রাখা সস্তা, graph করা দ্রুত, আর **প্রবণতা ও alert**-এর জন্য দারুণ। *কোন* request ব্যর্থ হলো এমন খুঁটিনাটি এতে নেই। সেটা log-এর কাজ।

## ৪. কী দেখবে? চারটা golden signal

Google-এর Site Reliability Engineering (SRE) বই চারটা signal প্রস্তাব করে যা প্রায় যেকোনো user-মুখী service-এর স্বাস্থ্য বর্ণনা করে। এই চারটা শিখলে জানবে প্রথমে কী মাপবে:

| Signal | মানে | Web app-এর উদাহরণ |
|---|---|---|
| **Latency** | Request কতক্ষণ নেয় | "৯৫% page ৪০০ ms-এর নিচে লোড হয়" |
| **Traffic** | কতটা চাহিদা | প্রতি সেকেন্ডে request |
| **Errors** | কতগুলো request ব্যর্থ হয় | যত শতাংশ response 5xx |
| **Saturation** | System কতটা "ভরা" | CPU, memory, disk, database connection |

![চারটা golden signal](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/07-monitoring/images/four-golden-signals.svg)

দুটো কথা:

- Latency **সফল আর ব্যর্থ** request আলাদাভাবে মাপো। দ্রুত error গড়ে মেশালে "ভালো" দেখায়।
- গড়ের চেয়ে **percentile** (যেমন p95, p99) বেছো। ৯৯টা request ১০০ ms আর একটা ১০ সেকেন্ড নিলে গড় ঠিকঠাক দেখায় (প্রায় ২০০ ms), কিন্তু একজন user ১০ সেকেন্ড অপেক্ষা করেছে। p99 কষ্টটা দেখায়; গড় সেটা লুকায়।

## ৫. Symptom আর cause

আগে **user যা অনুভব করে** তা দেখো, তারপর কারণে খুঁড়ো:

- **Symptom:** "checkout page error দিচ্ছে" (user টের পায়)।
- **Cause:** "database-এর connection ফুরিয়েছে" (user সরাসরি টের পায় না)।

বেশিরভাগ **symptom**-এ alert দাও (error, latency, site বন্ধ), কারণ ওরা জানায় user কষ্টে আছে। Cause-এর metric (CPU, disk, connection) ব্যবহার করো **diagnose** করতে আর সমস্যা symptom হওয়ার *আগেই* ধরতে। Chapter 6 এই ধারণার ওপর গড়ে উঠবে।

## ৬. Black-box আর white-box monitoring

- **Black-box:** **বাইরে** থেকে দেখা, user-এর মতো। "`https://myshop.com/` কি ২ সেকেন্ডের কম সময়ে 200 দেয়?" Uptime checker এটা করে।
- **White-box:** **ভেতর** থেকে দেখা। "App জানাচ্ছে ৩% request ব্যর্থ; queue-তে ৫,০০০ job অপেক্ষায়।" Metric আর log এটা করে।

দুটোই লাগে। Black-box check ধরে *"পুরো server-এ পৌঁছানো যাচ্ছে না"*, যা ভেতরের metric জানাতে পারে না কারণ ওরাও বন্ধ।

## ৭. SLI, SLO আর "কয়টা nine?"

Team-রা তিনটা শব্দ দিয়ে নির্ভরযোগ্যতাকে মাপযোগ্য করে:

- **SLI (Service Level Indicator):** একটা মাপ, যেমন "যত শতাংশ request সফল"।
- **SLO (Service Level Objective):** তার জন্য তোমার **লক্ষ্য**: "৩০ দিনে ৯৯.৯% request সফল"।
- **SLA (Service Level Agreement):** customer-দের সাথে একটা **চুক্তি**, প্রায়ই জরিমানা-সহ। (SLO রাখতে SLA লাগে না।)

Uptime-এর লক্ষ্য ছোট শোনায় কিন্তু অনেক downtime বোঝায়। ৩০ দিনের মাসের জন্য:

| Availability | মাসে অনুমোদিত downtime | বছরে |
|---|---|---|
| 99% ("দুই nine") | প্রায় ৭ ঘণ্টা ১২ মিনিট | প্রায় ৩.৬৫ দিন |
| 99.9% ("তিন nine") | প্রায় ৪৩ মিনিট | প্রায় ৮.৮ ঘণ্টা |
| 99.99% ("চার nine") | প্রায় ৪.৩ মিনিট | প্রায় ৫৩ মিনিট |
| 99.999% ("পাঁচ nine") | প্রায় ২৬ সেকেন্ড | প্রায় ৫ মিনিট |

প্রতিটা বাড়তি nine অনেক বেশি পরিশ্রম ও টাকা খরচ করায়। তোমার business-এর সাথে মানায় এমন লক্ষ্য বাছো: একটা ছোট দোকান ৯৯.৯%-এ খুশি হতে পারে; একটা payment system-এর আরও লাগতে পারে। অনুমোদিত downtime হলো তোমার **error budget**: লক্ষ্য রেখেও যতটা ব্যর্থতা "খরচ" করতে পারো।

## ৮. এই course-এ যে tool-গুলো পাবে

কিছু কিনতে হবে না। একটা প্রচলিত বিনামূল্যের stack:

| কাজ | Tool |
|---|---|
| Metric জমানো ও সংরক্ষণ | **Prometheus** |
| Dashboard আঁকা | **Grafana** |
| Alert পাঠানো | **Alertmanager**, বা email/Slack |
| Log জমানো ও খোঁজা | **Loki** (বা ELK stack) |
| বাইরে থেকে পরীক্ষা | একটা **uptime monitor** (UptimeRobot, Better Stack-এর মতো) |
| Application error ধরা | একটা **error tracker** (Sentry-র মতো) |

পেইড, সব-একসাথে service-ও আছে (Datadog, New Relic আর আরও)। এগুলোর খরচ বেশি, কিন্তু setup-এর সময় বাঁচায়। আগে ধারণা শেখো, তাহলে দুটোই ব্যবহার করতে পারবে।

## ৯. মূল কথা

- Monitoring আছে যাতে **তুমি** user-দের আগে সমস্যা খুঁজে পাও। Outage-এর দামি অংশ প্রায়ই *না জানা*।
- **Metric** (সময়ের সাথে সংখ্যা) দেখায় *যে* কিছু ভুল; **log** ব্যাখ্যা করে *কেন*; **alert** নিশ্চিত করে কেউ *শোনে*। **Trace** অনেক service জুড়ে request অনুসরণ করে।
- **চারটা golden signal**: **latency**, **traffic**, **errors**, **saturation**।
- গড়ের চেয়ে **percentile** বেছো, আর error ও সফলতা আলাদাভাবে মাপো।
- User যা অনুভব করে সেই **symptom**-এ alert দাও; **cause** দিয়ে diagnose করো।
- **Black-box** (বাইরে) আর **white-box** (ভেতরে) monitoring একসাথে ব্যবহার করো।
- **SLO** হলো তোমার নির্ভরযোগ্যতার লক্ষ্য; uptime-এর প্রতিটা বাড়তি "nine" অনেক বেশি খরচ করায়।

## ১০. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. Metric আর log-এর পার্থক্য কী?</strong></summary>

Metric হলো সময়ের সাথে মাপা একটা সংখ্যা (যেমন CPU বা প্রতি সেকেন্ডে request), প্রবণতা ও alert-এর জন্য ভালো। Log হলো একটা ঘটনার বিস্তারিত রেকর্ড (যেমন error message), ঠিক কী ঘটেছে জানার জন্য ভালো।
</details>

<details>
<summary><strong>Q2. চারটা golden signal-এর নাম বলো।</strong></summary>

Latency, traffic, errors আর saturation।
</details>

<details>
<summary><strong>Q3. Latency-র জন্য গড়ের চেয়ে percentile (যেমন p99) প্রায়ই ভালো কেন?</strong></summary>

গড় ঠিকঠাক দেখাতে পারে অথচ কয়েকজন user অনেক বেশি অপেক্ষা করছে। উঁচু percentile সেই ধীর request-গুলো দেখায়।
</details>

<details>
<summary><strong>Q4. Black-box আর white-box monitoring-এর পার্থক্য কী? দুটোই লাগে কেন?</strong></summary>

Black-box বাইরে থেকে user-এর মতো পরীক্ষা করে (যেমন uptime check)। White-box ভেতরের জিনিস দেখে, যেমন metric ও log। পুরো server বন্ধ হলে ভেতরের metric-ও থেমে যায়, তাই শুধু বাইরের check-ই টের পায়।
</details>

<details>
<summary><strong>Q5. একটা SLO বলছে ৩০ দিনে ৯৯.৯% availability। মোটামুটি কতটা downtime অনুমোদিত?</strong></summary>

প্রায় ৪৩ মিনিট।
</details>

<details>
<summary><strong>Q6. বেশিরভাগ symptom-এ alert দেবে না cause-এ? কেন?</strong></summary>

বেশিরভাগ symptom-এ (error, latency, downtime), কারণ এগুলো মানে user ক্ষতিগ্রস্ত। Cause-এর metric diagnose করতে আর আগাম সতর্কতা দিতে সাহায্য করে।
</details>

## ১১. বিনামূল্যের resource

- Google SRE book: [Monitoring Distributed Systems](https://sre.google/sre-book/monitoring-distributed-systems/) (চারটা golden signal এখান থেকে)
- Google SRE book: [Service Level Objectives](https://sre.google/sre-book/service-level-objectives/)
- OpenTelemetry: [Observability primer](https://opentelemetry.io/docs/concepts/observability-primer/)
- Prometheus: [Overview](https://prometheus.io/docs/introduction/overview/)
- Grafana: [Documentation](https://grafana.com/docs/grafana/latest/)

---

**পরের chapter:** [Chapter 2 — Linux server-এর স্বাস্থ্য: CPU, memory, disk আর log](02-linux-server-health.md)
