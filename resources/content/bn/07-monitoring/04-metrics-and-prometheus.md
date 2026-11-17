# Chapter 4 — Metric আর Prometheus

> **Course:** Monitoring & Logging · **Chapter:** 4 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/monitoring/04-metrics-and-prometheus)

Chapter 2-এ তুমি হাতে `uptime` আর `free -h` চালিয়েছ। এতে **এখনকার** অবস্থা জানা যায়, কিন্তু "রাত ৩টায় load কত ছিল?" বা "memory কি ধীরে ধীরে বাড়ছে?" জিজ্ঞেস করা যায় না। সেজন্য একটা program-কে **প্রতি কয়েক সেকেন্ডে, সবসময়** একই সংখ্যাগুলো জমা করে সংরক্ষণ করতে হবে। সেই program-ই **Prometheus**, সবচেয়ে বেশি ব্যবহৃত বিনামূল্যের monitoring system।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ **Prometheus আর `node_exporter` install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের Prometheus config আর query সরকারি documentation থেকে লেখা এবং **চালানো হয়নি**। YAML যে বৈধ তা যাচাই করা হয়েছে, আর প্রতিটা বাইরের link-ও। যা *সত্যিই চালানো* হয়েছে: ওই machine-এ থাকা web server **Caddy** শুধু local ঠিকানায় Prometheus-এর নিজস্ব ফরম্যাটে metric দেখায়, তাই এই chapter-এর "আসল output" আর `rate()`-এর প্রদর্শনী সেখান থেকে (শুধু পড়ার `curl`, কিছু বদলানো হয়নি)। Package-এর version আর হুবহু output তোমার server-এ আলাদা হবে।

![Prometheus কীভাবে metric জমা করে: সে প্রতিটা target-এর /metrics পেজ টেনে আনে (scrape)](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/07-monitoring/images/prometheus-pull-model.svg)

## ১. Metric কী?

**Metric** হলো এমন একটা সংখ্যা যা সময়ের সাথে বদলায়। Prometheus-এর রাখা প্রতিটা sample-এর চারটা অংশ:

```
http_requests_total{method="GET", code="200"}   1027      (১৪:০৫:১৫-এ)
└─────── নাম ────────┘└──── label ─────────────┘ └মান┘   └ সময় ┘
```

- **নাম:** কী মাপা হচ্ছে (`http_requests_total`)।
- **Label:** key/value tag, যা একটা metric-কে অনেক **series**-এ ভাগ করে। `method="GET"` আর `method="POST"` একই metric-এর দুটো আলাদা series।
- **মান:** একটা সংখ্যা।
- **সময়:** কখন জমা হয়েছে (Prometheus নিজে যোগ করে)।

Series হলো শুধু *(সময়, সংখ্যা) জোড়ার একটা তালিকা*। Graph হলো সেই series-কে chart-এ আঁকা। dashboard বলতে আসলে এটুকুই।

## ২. Pull model: Prometheus তোমার কাছে আসে

অনেক পুরনো system-এ প্রতিটা server নিজের সংখ্যা কেন্দ্রীয় জায়গায় **push** করে। Prometheus করে উল্টোটা। সে **pull** করে (**scrape**): প্রতি `scrape_interval` পরপর (প্রায়ই **১৫ সেকেন্ড**) সে প্রতিটা **target**-কে একটা সাধারণ HTTP request পাঠায় আর একটা text পেজ পড়ে, সাধারণত `/metrics`-এ।

এই নকশা কেন কাজের:

- **মরে যাওয়া target ধরা পড়ে।** target সাড়া না দিলে Prometheus তার জন্য `up = 0` লেখে। যে server push করে সে crash করলে শুধু চুপ হয়ে যায়, আর নীরবতা সহজে চোখ এড়ায়।
- **Target সহজ থাকে।** app-কে শুধু একটা web পেজে নিজের সংখ্যা *দেখাতে* হয়। Prometheus কোথায় আছে তা তাকে জানতে হয় না।
- **তুমি নিজেও দেখতে পারো:** পেজটা `curl` করে অন্য যেকোনো web পেজের মতো পড়ো।

প্রতিটা target-এর জন্য একটা বিশেষ metric **`up`** তৈরি হয়: `1` মানে শেষ scrape কাজ করেছে, `0` মানে ব্যর্থ। `up == 0` হলো তোমার লেখা সবচেয়ে সহজ, সবচেয়ে গুরুত্বপূর্ণ alert (Chapter 6)।

## ৩. `/metrics` পেজ দেখতে কেমন

এই course-এর machine-এর web server Caddy থেকে **আসল output** এখানে। Caddy-র admin interface `127.0.0.1:2019`-এ (শুধু local) শোনে আর `/metrics`-এ metric দেয়:

```bash
curl -s http://localhost:2019/metrics | grep caddy_
```

(একটা excerpt: সংক্ষিপ্ত রাখতে কয়েকটা metric বাদ দেওয়া হয়েছে।)

```
# HELP caddy_admin_http_requests_total Counter of requests made to the Admin API's HTTP endpoints.
# TYPE caddy_admin_http_requests_total counter
caddy_admin_http_requests_total{code="200",handler="admin",method="GET",path="/metrics"} 1
caddy_admin_http_requests_total{code="200",handler="admin",method="POST",path="/load"} 24
# HELP caddy_config_last_reload_successful Whether the last configuration reload attempt was successful.
# TYPE caddy_config_last_reload_successful gauge
caddy_config_last_reload_successful 1
# HELP caddy_reverse_proxy_upstreams_healthy Health status of reverse proxy upstreams.
# TYPE caddy_reverse_proxy_upstreams_healthy gauge
caddy_reverse_proxy_upstreams_healthy{upstream="127.0.0.1:8080"} 1
caddy_reverse_proxy_upstreams_healthy{upstream="unix//run/php/php8.3-fpm.sock"} 1
```

এই সাদা-text গঠনকে বলে **exposition format**। প্রতিটা metric-এ থাকে:

- `# HELP`: এক line-এর বর্ণনা,
- `# TYPE`: তার ধরন (পরের অংশ),
- তারপর প্রতিটা series-এর জন্য এক line: `name{labels} value`।

যা কিছু web পেজে এই text ছাপতে পারে তাকে Prometheus monitor করতে পারে। যে program অন্য program-এর হয়ে এটা করে তাকে বলে **exporter**।

## ৪. চার ধরনের metric

| ধরন | কী | কোন দিকে যায় | উদাহরণ |
|---|---|---|---|
| **Counter** | এমন সংখ্যা যা শুধু **বাড়ে** (restart-এ ০ হয়ে যায়) | ↗ | পরিবেশিত request, error, পাঠানো byte |
| **Gauge** | এমন সংখ্যা যা **বাড়ে-কমে** | ↕ | ব্যবহৃত memory, queue-র দৈর্ঘ্য, তাপমাত্রা |
| **Histogram** | **bucket**-এ observation গোনা, percentile-এর জন্য | | request-এর সময় |
| **Summary** | Percentile app-এর **ভেতরেই** হিসাব করা | | GC pause-এর সময় |

একই Caddy পেজ থেকে আসল উদাহরণ:

```
# TYPE caddy_admin_http_requests_total counter
caddy_admin_http_requests_total{code="200",handler="admin",method="POST",path="/load"} 24

# TYPE go_goroutines gauge
go_goroutines 51

# TYPE process_resident_memory_bytes gauge
process_resident_memory_bytes 6.5118208e+07        # প্রায় ৬২ MiB (বৈজ্ঞানিক সংখ্যা-লিখন)
```

একটা **histogram** (এটা একটা **দৃষ্টান্ত**, ওই server থেকে নয়) গোনে কতগুলো request *সর্বোচ্চ* একটা নির্দিষ্ট সময় নিয়েছে। `le` label-এর মানে "less than or equal":

```
http_request_duration_seconds_bucket{le="0.1"}  8421     # ৮৪২১টা request নিয়েছে <= ০.১ সেকেন্ড
http_request_duration_seconds_bucket{le="0.5"}  9790     # ৯৭৯০টা নিয়েছে <= ০.৫ সেকেন্ড
http_request_duration_seconds_bucket{le="+Inf"} 9800     # সবগুলো ৯৮০০টা
http_request_duration_seconds_sum   1432.7                # মোট সেকেন্ড
http_request_duration_seconds_count 9800                  # মোট request
```

এই bucket থেকে Prometheus Chapter 1-এ দেখা **p99** আন্দাজ করতে পারে (`histogram_quantile`)। "৯৯% request X সেকেন্ডের মধ্যে শেষ হয়েছে" এভাবেই মাপা হয়।

## ৫. Counter আর `rate()`: সবচেয়ে গুরুত্বপূর্ণ ধারণা

`process_cpu_seconds_total`-এর মতো একটা counter (process শুরু থেকে ব্যবহৃত CPU সময়) শুধু বাড়ে। এর graph একটা চিরকাল উঠতে থাকা রেখা, যা প্রায় কিছুই জানায় না। তুমি চাও **কত দ্রুত বাড়ছে**: সেটাই **`rate()`**।

`rate()` যা করে চলো হাতে করে দেখি। আসল Caddy পেজে একই counter ১৫ সেকেন্ড ব্যবধানে দুবার scrape করো:

```bash
curl -s http://localhost:2019/metrics | grep '^process_cpu_seconds_total '
# process_cpu_seconds_total 3277.79        (scrape ১)
# ... ১৫ সেকেন্ড অপেক্ষা ...
# process_cpu_seconds_total 3277.8         (scrape ২)
```

```
increase = 3277.80 - 3277.79        = 0.010 CPU-সেকেন্ড
rate     = 0.010 / 15 সেকেন্ড        = 0.0007 CPU-সেকেন্ড প্রতি সেকেন্ড
```

`0.0007` CPU-সেকেন্ড প্রতি সেকেন্ডের মানে ওই সময়ে Caddy **একটা CPU core-এর ০.০৭%** ব্যবহার করেছে। সে প্রায় বসে ছিল, তাই সংখ্যা ছোট, কিন্তু আসল কথা পদ্ধতিটা: **rate হলো দুটো scrape-এর পার্থক্য, মাঝের সময় দিয়ে ভাগ।** PromQL-এ:

```
rate(process_cpu_seconds_total[5m])      # গত ৫ মিনিটের গড় প্রতি-সেকেন্ড হার
increase(http_requests_total[1h])        # গত এক ঘণ্টায় কতগুলো request
```

**নিয়ম:** কখনো কাঁচা counter-এর graph আঁকবে না। `rate()` বা `increase()`-এ মুড়ে দাও। (program restart-এ counter শূন্য হয়ে গেলে সেটা তোমার হয়ে সামলানো হয়: `rate()` reset বোঝে।)

## ৬. Label আর cardinality-র ফাঁদ

Label শক্তিশালী: `sum by (method) (rate(http_requests_total[5m]))` প্রতিটা method-এর জন্য প্রতি সেকেন্ডের request দেয়। কিন্তু **প্রতিটা আলাদা label-মান একটা নতুন series বানায়**, আর প্রতিটা series memory ও disk খায়।

- **ভালো label**-এর মান ছোট, স্থির একটা সেট: `method`, `status code`, `route name`, `server`।
- **খারাপ label** সীমাহীন: `user_id`, `email`, `order_id`, id-সহ পুরো URL। দশ লাখ user দশ লাখ series বানাবে আর Prometheus-কে crash করাতে পারে। এই সমস্যার নাম **high cardinality**।

User-নির্দিষ্ট বিবরণ **log**-এ (Chapter 3) যাবে, metric label-এ নয়।

## ৭. `node_exporter`: server-এর নিজের সংখ্যা

Chapter 2-এর CPU, memory, disk আর network সংখ্যার জন্য তোমাকে কিছু লিখতে হয় না। **`node_exporter`** একটা ছোট program, প্রতিটা server-এ চলে, `/proc` ও `/sys` পড়ে আর ফল **9100** port-এ (তার ডিফল্ট) প্রকাশ করে:

| Metric | মানে |
|---|---|
| `node_cpu_seconds_total{mode="idle"}` | প্রতিটা core-এ idle থাকা CPU সময় (একটা counter: `rate` ব্যবহার করো) |
| `node_load1`, `node_load5`, `node_load15` | Chapter 2-এর load average |
| `node_memory_MemAvailable_bytes` | **available** memory (ভরসার সংখ্যা) |
| `node_memory_MemTotal_bytes` | মোট memory |
| `node_filesystem_avail_bytes`, `node_filesystem_size_bytes` | ফাঁকা ও মোট disk জায়গা |
| `node_network_receive_bytes_total` | পাওয়া byte (একটা counter) |

Ubuntu-তে `node_exporter` আর Prometheus package হিসেবে পাওয়া যায় (`prometheus-node-exporter` ও `prometheus`; এই course-এর machine-এ `apt-cache policy` version ১.১০.২ আর ২.৫৩.৫ দেখিয়েছিল, তবে তোমার Ubuntu release আলাদা হতে পারে)। install করবে `sudo apt install prometheus prometheus-node-exporter` দিয়ে। **(এই chapter-এর জন্য এটা চালানো হয়নি।)**

> **নিরাপত্তা: port 9100 internet-এ খুলে রেখো না।** Metric পেজ তোমার server সম্পর্কে অনেক কিছু প্রকাশ করে (version, mount, load)। এটাকে `127.0.0.1`-এ (Prometheus একই machine-এ থাকলে) বা **private network ঠিকানায়** শুনতে দাও, আর firewall জনসাধারণের জন্য বন্ধ রাখো। flag-টা `--web.listen-address` (`node_exporter --help` দেখো; package install-এ `systemctl cat prometheus-node-exporter` দেখায় argument কোথায় ঠিক করা)। কী শুনছে তা Chapter 2-এর `ss -tlnp` দিয়ে দেখো।

## ৮. Prometheus কনফিগার করা

Prometheus একটাই YAML file দিয়ে কনফিগার হয়, `prometheus.yml`। একটা ছোট, বৈধ শুরু, যা Prometheus নিজেকে, একটা server আর একটা Laravel app scrape করে:

```yaml
global:
  scrape_interval: 15s        # কত ঘন ঘন scrape
  evaluation_interval: 15s    # কত ঘন ঘন alert rule যাচাই (Chapter 6)

scrape_configs:
  - job_name: prometheus
    static_configs:
      - targets: ["localhost:9090"]

  - job_name: node
    static_configs:
      - targets: ["localhost:9100"]
        labels:
          server: web-1

  - job_name: myshop
    metrics_path: /metrics
    static_configs:
      - targets: ["127.0.0.1:8080"]
```

প্রতিটা `job_name` target-দের দল বানায়; Prometheus প্রতিটা series-এ নিজে `job` ও `instance` label যোগ করে। Prometheus নিজের on-disk database-এ data রাখে আর, ডিফল্টভাবে, **১৫ দিনের পুরনো data মুছে ফেলে** (`--storage.tsdb.retention.time`)। দীর্ঘ ইতিহাস চাইলে বড় মান বা আকারের সীমা দাও। reload করার আগে সবসময় file যাচাই করো: `promtool check config prometheus.yml` (Prometheus-এর সাথে আসা যাচাই-tool)।

তুমি Prometheus-এর web পেজ (port **9090**) খুলে তার **Expression browser**-এ query লিখতে পারো। এই port-ও private রাখো।

## ৯. তোমার প্রথম PromQL

**PromQL** হলো Prometheus-এর query ভাষা। শুরুতে দুটো জিনিস জানো:

- **Instant vector** হলো "প্রতিটা series-এর সর্বশেষ মান": `node_load1`।
- **Range vector** হলো "একটা সময়-জানালার মানগুলো", `[5m]` দিয়ে লেখা: `node_cpu_seconds_total[5m]`। এটা সরাসরি আঁকা যায় না; `rate()`-এর মতো function-কে দিতে হয়।

কাজের, standard query (সরকারি `node_exporter` guide ও docs থেকে মানিয়ে নেওয়া; **এখানে চালানো হয়নি**):

```promql
up                                                   # 1 = target জীবিত, 0 = মৃত
up == 0                                              # শুধু মৃতগুলো

# ব্যবহৃত CPU, শতাংশ (১০০ থেকে idle অংশ বাদ), প্রতিটা server-এর core ধরে গড়
100 - (avg by (instance) (rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)

# ব্যবহৃত memory, শতাংশ (Chapter 2-এর মতো "available" ধরে)
(1 - node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes) * 100

# ব্যবহৃত disk, শতাংশ, RAM-ভিত্তিক filesystem বাদ দিয়ে
(1 - node_filesystem_avail_bytes{fstype!~"tmpfs|overlay"}
   / node_filesystem_size_bytes{fstype!~"tmpfs|overlay"}) * 100

# প্রতি সেকেন্ডে request, প্রতিটা HTTP method ধরে
sum by (method) (rate(http_requests_total[5m]))

# histogram থেকে ৯৯তম percentile latency
histogram_quantile(0.99, sum by (le) (rate(http_request_duration_seconds_bucket[5m])))
```

এগুলো পড়ার উপায়:

- `{mode="idle"}` হলো **label matcher**: শুধু সেই series যার `mode` হুবহু `idle`। `=~` ও `!~` প্যাটার্ন মেলায়।
- `avg by (instance)` একটা **aggregation**: অনেক series মিলিয়ে প্রতি `instance`-এ একটা।
- `[5m]`-এর ভেতরের সবকিছু একটা জানালা, তাই লম্বা জানালা মসৃণ কিন্তু দেরিতে সাড়া দেয়।

CPU query বলে: *CPU কত সময় idle ছিল তার ভগ্নাংশ ১০০ থেকে বাদ*। এটা `top`-এর (Chapter 2) "id" সংখ্যা, এখন অবিরাম মাপা।

## ১০. তোমার নিজের application ও অন্য service-এর metric

- **তোমার Laravel app:** `/metrics` route দেয় এমন একটা Prometheus client library যোগ করো, যেমন [`spatie/laravel-prometheus`](https://github.com/spatie/laravel-prometheus) বা নিচু স্তরের PHP client [`promphp/prometheus_client_php`](https://github.com/PromPHP/prometheus_client_php)। route-টা সুরক্ষিত রাখো (শুধু Prometheus-এর IP বা private network): এটা জনসাধারণের জন্য নয়।
- **অন্য service-এর** তৈরি exporter আছে, তাই তোমাকে লিখতে হয় না: [`mysqld_exporter`](https://github.com/prometheus/mysqld_exporter) (MySQL), [`redis_exporter`](https://github.com/oliver006/redis_exporter) (Redis), [`php-fpm_exporter`](https://github.com/hipages/php-fpm_exporter) (PHP-FPM), আর [`blackbox_exporter`](https://github.com/prometheus/blackbox_exporter), যে site-কে **বাইরে থেকে** পরীক্ষা করে (HTTP, ping, TLS মেয়াদ), যা Chapter 1-এর black-box check।
- **কিছু software-এ আগে থেকেই আছে**, যেমন ওপরের Caddy-র উদাহরণ।

তোমার app-এ মাপার প্রথম জিনিস: request-এর হার, error-এর হার (5xx), request-এর সময়, queue-র দৈর্ঘ্য, ব্যর্থ job। এগুলো Chapter 1-এর চারটা golden signal।

## ১১. Prometheus যা নয়

- সে **সময়ের সাথে সংখ্যা** জমায়, log-এর লেখা নয়। log-এর জন্য Loki বা সমতুল্য (Chapter 7)।
- একটা Prometheus মানে স্থানীয় disk-সহ একটা machine: ছোট দলের server-এর জন্য দারুণ, কিন্তু নিজে দীর্ঘমেয়াদী, high-availability archive নয়।
- সে সুন্দর dashboard আঁকে না। ওটা **Grafana** (Chapter 5)। সে নিজে notification পাঠায় না। ওটা **Alertmanager** (Chapter 6)।

## ১২. মূল কথা

- **Metric** হলো নাম, label, একটা সংখ্যা আর সময়। **Series** হলো সেই সংখ্যাগুলো সময়ের ওপর।
- Prometheus প্রতিটা target-এর `/metrics` প্রায় প্রতি ১৫ সেকেন্ডে **pull** (scrape) করে। বিল্ট-ইন **`up`** metric বলে target সাড়া দিয়েছে কিনা।
- চার ধরন: **counter** (শুধু বাড়ে), **gauge** (বাড়ে-কমে), **histogram** ও **summary** (percentile)।
- **কখনো কাঁচা counter আঁকবে না।** **`rate()`** বা **`increase()`** ব্যবহার করো: scrape-এর মধ্যে পরিবর্তন, সময় দিয়ে ভাগ।
- **Label-এর cardinality কম** রাখো। User id আর email যাবে log-এ, label-এ নয়।
- **`node_exporter`** (port 9100) CPU, memory, disk ও network দেয়। Exporter-এর port **private** রাখো।
- PromQL-এর মূল: instant বনাম range vector, label matcher, `rate`, `sum by`, `histogram_quantile`।
- Prometheus সংখ্যা জমায় (ডিফল্ট ১৫ দিন); Grafana আঁকে; Alertmanager জানায়।

## ১৩. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. Prometheus কি metric push করে নাকি pull করে? এতে কী আসে-যায়?</strong></summary>

সে প্রতিটা target-এর `/metrics` পেজ **pull** (scrape) করে। এজন্য মরে যাওয়া target ধরা পড়ে (`up = 0`), চুপ হয়ে যায় না, আর target সহজ থাকে।
</details>

<details>
<summary><strong>Q2. `up == 0` তোমাকে কী বলে?</strong></summary>

ওই target-এর শেষ scrape ব্যর্থ: সে বন্ধ, পৌঁছানো যাচ্ছে না, বা `/metrics` দিচ্ছে না।
</details>

<details>
<summary><strong>Q3. "ব্যবহৃত memory"-র জন্য কোন metric ধরন মানায়, আর "মোট পরিবেশিত request"-এর জন্য কোনটা?</strong></summary>

ব্যবহৃত memory একটা **gauge** (বাড়ে-কমে)। মোট পরিবেশিত request একটা **counter** (শুধু বাড়ে)।
</details>

<details>
<summary><strong>Q4. কাঁচা counter-এর graph কেন আঁকবে না?</strong></summary>

সে শুধু ওঠে, তাই রেখাটা প্রায় কিছুই কাজের দেখায় না। কত দ্রুত বদলাচ্ছে দেখতে `rate()` (প্রতি সেকেন্ডে) বা `increase()` (একটা সময়ে) ব্যবহার করো।
</details>

<details>
<summary><strong>Q5. একটা counter ৩২৭৭.৭৯ পড়ল, আর ১৫ সেকেন্ড পরে ৩২৭৭.৮০। তার rate কত?</strong></summary>

(৩২৭৭.৮০ − ৩২৭৭.৭৯) / ১৫ ≈ প্রতি সেকেন্ডে ০.০০০৭। Rate হলো দুটো scrape-এর পার্থক্য, মাঝের সময় দিয়ে ভাগ।
</details>

<details>
<summary><strong>Q6. `user_id` label কেন খারাপ বুদ্ধি?</strong></summary>

প্রতিটা আলাদা মান একটা নতুন series বানায়। অনেক user থাকলে series-এর সংখ্যা বিশাল (high cardinality), যা অনেক memory খায় আর Prometheus-কে crash করাতে পারে। User-ভিত্তিক বিবরণ log-এ রাখো।
</details>

<details>
<summary><strong>Q7. `node_exporter` কী করে, আর কোন port-এ?</strong></summary>

সে server-এর CPU, memory, disk ও network সংখ্যা পড়ে আর ডিফল্টভাবে port 9100-এ metric হিসেবে প্রকাশ করে, Prometheus-এর scrape করার জন্য।
</details>

<details>
<summary><strong>Q8. Port 9100 কেন পুরো internet-এর জন্য খোলা রাখা উচিত নয়?</strong></summary>

Metric পেজ তোমার server-এর খুঁটিনাটি প্রকাশ করে। এটাকে `127.0.0.1` বা private ঠিকানায় bind করো আর firewall জনসাধারণের জন্য বন্ধ রাখো।
</details>

<details>
<summary><strong>Q9. Instant vector আর range vector-এর পার্থক্য কী?</strong></summary>

Instant vector হলো প্রতিটা series-এর সর্বশেষ মান (`node_load1`)। Range vector হলো একটা সময়-জানালার মানগুলো (`node_cpu_seconds_total[5m]`), আর এটাকে `rate()`-এর মতো function-কে দিতে হয়।
</details>

## ১৪. বিনামূল্যের resource

- Prometheus docs: [Overview](https://prometheus.io/docs/introduction/overview/) ও [Data model](https://prometheus.io/docs/concepts/data_model/)
- [Metric types](https://prometheus.io/docs/concepts/metric_types/) (counter, gauge, histogram, summary)
- [Querying basics (PromQL)](https://prometheus.io/docs/prometheus/latest/querying/basics/)
- [Configuration reference](https://prometheus.io/docs/prometheus/latest/configuration/configuration/)
- হাতে-কলমে guide: [Node Exporter দিয়ে Linux host metric monitor](https://prometheus.io/docs/guides/node-exporter/)
- [Metric ও label-এর নামকরণ](https://prometheus.io/docs/practices/naming/) এবং [Instrumentation-এর রীতি](https://prometheus.io/docs/practices/instrumentation/)
- Caddy: [Metrics](https://caddyserver.com/docs/metrics) (এই chapter-এর আসল sample-এর উৎস)
- GitHub-এ [`node_exporter`](https://github.com/prometheus/node_exporter)

---

**পরের chapter:** [Chapter 5 — Grafana dashboard](05-grafana-dashboards.md)
