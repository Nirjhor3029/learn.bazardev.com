# Chapter 7 — Central Logging, Uptime Check ও Error Tracking

> **Course:** Monitoring & Logging · **Chapter:** 7 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/monitoring/07-central-logging-uptime-and-errors)

এ পর্যন্ত তুমি **মাপতে** (Prometheus), **আঁকতে** (Grafana) আর **খবর পেতে** (Alertmanager) পারো। তিনটা ফাঁক বাকি, আর প্রতিটার একটা করে ছোট tool আছে:

![তিনটা আলাদা প্রশ্ন, তিনটা tool: central logging, uptime check, error tracking](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/07-monitoring/images/three-questions.svg)

1. তোমার একাধিক server (বা container), আর প্রতিটায় `ssh` + `grep` ধীর। → **Central logging।**
2. তোমার নিজের monitoring চলে নেটওয়ার্কের *ভেতরে*। পুরো server বন্ধ হলে সেও বন্ধ। → **বাইরে থেকে uptime check।**
3. Log-এ "error" লেখা আছে, কিন্তু তুমি চাও exception, stack trace আর *কতজন ব্যবহারকারী আক্রান্ত*। → **Error tracking।**

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** Loki, Grafana Alloy, Blackbox exporter, Sentry ও GlitchTip এই course লেখার server-এ **install করা নেই**, আর এদের জন্য কিছু install করাও হয়নি। তাই নিচের সবকিছু সরকারি documentation থেকে লেখা এবং **চালানো হয়নি**; version-এর সংখ্যা, menu-র নাম আর কিছু setting-এর নাম বদলায়, তাই তোমার version-এর জন্য দেওয়া docs দেখো। যা *যাচাই করা হয়েছে*: Alloy config-এর component-এর নাম ও argument, Loki-র ডিফল্ট port ও উদাহরণ config, LogQL-এর উদাহরণ, Loki-র label-নির্দেশনা, Blackbox exporter-এর `/probe` ব্যবহার ও metric-এর নাম (এর source code থেকে পড়া), Sentry Laravel-এর command, আর প্রতিটা YAML টুকরো যে বৈধ। প্রতিটা বাইরের link খুলে দেখা হয়েছে। Alloy-র টুকরোটা Alloy-র নিজস্ব syntax ব্যবহার করে, যা এখানে কোনো tool দিয়ে parse করা যায়নি, তাই নিজের machine-এ পরীক্ষা করে নিও।

## অংশ ক. Central logging

### ১. `ssh` আর `grep`-এর সমস্যা

Chapter 3-এ তুমি `tail` ও `grep` দিয়ে log পড়েছ। একটা server-এ এটা কাজ করে। এটা ভাঙে যখন:

- তোমার **কয়েকটা server বা container**: ওই ব্যর্থ request কোনটা সামলেছিল?
- একটা container **বদলে যায় বা server নতুন করে বানানো হয়**: তার log-ও তার সাথে **হারিয়ে যায়**।
- তুমি graph-এ দেখতে চাও "গত এক ঘণ্টায় **সবকিছু জুড়ে** সব error"।
- তদন্তকারীকে শুধু log পড়ার জন্য production-এ **shell access** দিতে চাও না।

**Central logging** মানে প্রতিটা server তার log line **এক জায়গায়** পাঠায়, যেখানে সব একসাথে খোঁজা যায়। (এটা নিরাপত্তারও লাভ: আক্রমণকারী বা দুর্ঘটনায় কোনো server-এর file মুছে গেলেও তার log-এর কপি অন্য জায়গায় থাকে।)

### ২. Loki: "Prometheus, কিন্তু log-এর জন্য"

**Loki** Grafana-র log সিস্টেম। সরকারি বর্ণনা: Prometheus-এর অনুপ্রেরণায় একটা log aggregation সিস্টেম। এর বড় ধারণা সস্তা storage: Loki "does not index the contents of the logs, but only indexes metadata about your logs", আর ওই metadata হলো **label**। তুলনা করো full-text search engine-এর সাথে, যা প্রতিটা শব্দ index করে আর তাই অনেক বেশি disk ও memory খায়। Loki-তে তুমি আগে label দিয়ে ছেঁকে নাও, তারপর Loki মিলে-যাওয়া লেখা স্ক্যান করে।

Grafana তো জানোই, তাই পর্দার জন্য নতুন কিছু শেখার নেই: Loki আরেকটা **data source** হিসেবে যোগ হয়, আর তুমি **Explore**-এ খোঁজো বা **Logs** panel-এ দেখাও (Chapter 5)।

অংশগুলো:

| অংশ | কাজ |
|---|---|
| **তোমার app** | Chapter 3-এর মতো একটা log file লেখে (`storage/logs/laravel.log`)। |
| **Agent: Grafana Alloy** | প্রতিটা server-এ চলে, log file পড়ে, label জোড়ে আর HTTP দিয়ে line গুলো Loki-তে **ঠেলে (push)** দেয়। |
| **Loki** | Line গুলো জমায় আর query-র উত্তর দেয় (ডিফল্ট HTTP port **3100**)। |
| **Grafana** | যেখানে খোঁজো আর আঁকো। |

> **কোন agent? Alloy ব্যবহার করো, Promtail নয়।** পুরনো tutorial **Promtail** ব্যবহার করতে বলে। Grafana-র documentation এখন বলছে "Promtail is end of life (EOL) as of March 2, 2026": আর কোনো support বা update নেই, আর সব নতুন উন্নয়ন **Grafana Alloy**-তে। পুরনো Promtail config-কে Alloy config-এ বদলানোর একটা tool Alloy-তে আছে। Promtail ব্যবহার করা কোনো tutorial কপি করলে তুমি এমন কিছু কপি করছ যা আর রক্ষণাবেক্ষণ হয় না।

### ৩. Loki ও Alloy চালু করা (একটা পরিকল্পনা, এখানে চালানো হয়নি)

তোমার সিস্টেমের জন্য Grafana-র সরকারি install পাতা ([Loki](https://grafana.com/docs/loki/latest/setup/install/), [Alloy](https://grafana.com/docs/alloy/latest/)) অনুসরণ করো; দুটোই package হিসেবে বা Docker-এ চালানো যায়। শেখার জন্য Loki-র নিজের repository-তে একটা machine-এর জন্য local disk storage-সহ উদাহরণ config আছে, যার গুরুত্বপূর্ণ লাইন:

```yaml
# from Loki's example config (loki-local-config.yaml), shortened
auth_enabled: false

server:
  http_listen_port: 3100

common:
  path_prefix: /tmp/loki
  storage:
    filesystem:
      chunks_directory: /tmp/loki/chunks
      rules_directory: /tmp/loki/rules
  replication_factor: 1
```

ওই file নিয়ে দুটো সতর্কতা: এর storage path `/tmp`-এর নিচে, তাই আসল ব্যবহারে একটা ঠিকঠাক directory-তে বদলাও (যেমন `/var/lib/loki`), আর `auth_enabled: false` মানে **Loki কারো কাছে login চায় না**। একে Prometheus-এর মতো ধরো (Chapter 5): port 3100 **private** রাখো (local machine-এ শোনাও আর firewall-এ আটকাও; listen address-এর জন্য Loki-র configuration reference দেখো)। অন্য জায়গা থেকে log পাঠানো server গুলো তা পাঠাবে private network বা VPN দিয়ে, খোলা port দিয়ে নয়।

একটা Laravel log Loki-তে পাঠানোর Alloy configuration। Alloy নিজের syntax ব্যবহার করে (YAML নয়)। এটা সরকারি `loki.source.file` উদাহরণের গড়ন অনুসরণ করে, যেখানে প্রতিটা file target-এ একটা `__path__` label থাকতে হয়:

```
// /etc/alloy/config.alloy
local.file_match "laravel" {
  path_targets = [{
    "__path__" = "/var/www/shop/storage/logs/*.log",
    "job"      = "laravel",
    "env"      = "production"
  }]
}

loki.source.file "laravel" {
  targets    = local.file_match.laravel.targets
  forward_to = [loki.write.local.receiver]
}

loki.write "local" {
  endpoint {
    url = "http://localhost:3100/loki/api/v1/push"
  }
}
```

একটা pipeline হিসেবে পড়ো: **file খোঁজো** (`local.file_match`) → **পড়ো** (`loki.source.file`) → **পাঠাও** (`loki.write`)। নোট:

- Alloy প্রতিটা file-এ কতদূর পড়েছে মনে রাখে ("positions" file), তাই restart-এর পরে সে এগিয়ে যায়, সবকিছু আবার পাঠায় না। সংরক্ষিত অবস্থান নেই এমন নতুন file-এর জন্য সে শুরু থেকে পড়ে, যদি না `tail_from_end = true` দাও।
- Alloy-র user-এর log file **পড়ার অনুমতি** লাগবে। Linux course-এর মতো পরিচ্ছন্ন উপায় হলো ওই user-কে log-এর মালিক group-এ যোগ করা, log কখনো সবার-পড়ার-যোগ্য না করা।
- Chapter 3-এর log **rotation** এখনো প্রযোজ্য। Rotate-করা file Alloy কীভাবে সামলায় তা ভরসা করার আগে Alloy-র documentation দেখো, আর একবার জোর করে rotate করে পরীক্ষা করো।

### ৪. Label: যে নিয়মটা সবচেয়ে জরুরি

Loki-র গতি নির্ভর করে label-এর ভিন্ন ভিন্ন সমন্বয়ের (প্রতিটা সমন্বয় একটা **stream**) সংখ্যা ছোট রাখার ওপর। Grafana-র নির্দেশনা: label ব্যবহার করো এমন মানের জন্য যা "fixed for a given system/app and have bounded values": application, environment, server, region। আর সতর্কতা: "Too many label value combinations leads to too many streams", যা index বড় আর query ধীর করে।

তাই:

| ভালো label | খারাপ label |
|---|---|
| `job="laravel"`, `env="production"`, `host="web1"` | `user_id`, `order_id`, `request_id`, trace ID |
| প্রতিটার কয়েকটা মাত্র মান | যে মান প্রায় প্রতিটা লাইনে আলাদা |

Grafana-র গাইড স্পষ্ট বলে trace ID বা order ID-র মতো ক্ষণস্থায়ী মান label-এ বের করে নিও না। অথচ ঠিক ওগুলোই তো তুমি *খুঁজতে* চাও: তার জন্য label নয়, **line filter** (পরের অংশ) ব্যবহার করো, যা লেখা স্ক্যান করে।

### ৫. LogQL: log খোঁজা

**LogQL** Loki-র query ভাষা, আর ইচ্ছে করেই এটা PromQL-এর মতো দেখতে। একটা query শুরু হয় curly bracket-এর ভেতরে একটা **stream selector** দিয়ে (label), তারপর ঐচ্ছিক **filter**।

```
{job="laravel", env="production"}                  all production Laravel logs
{job="laravel"} |= "ERROR"                          lines that CONTAIN ERROR
{job="laravel"} |= "ERROR" != "timeout"             ...but not the word timeout
{job="laravel"} |~ "ERROR|CRITICAL"                 lines matching a regular expression
{job="laravel"} | json | level="error"              parse JSON logs (Chapter 3), then filter on a field
```

সরকারি docs-এর operator: selector-এ **label**-এর জন্য `=`, `!=`, `=~`, `!~`; **লাইনের ভেতরের লেখা**র জন্য `|=`, `!=`, `|~`, `!~`। `| json` parser query-র সময় JSON log line থেকে field বের করে আনে, আর সেজন্যই এখানে **structured JSON log (Chapter 3)** লাভ দেয়: `user_id`-কে কখনো label না বানিয়েই তার ওপর filter করা যায়।

Log-কে **সংখ্যায়ও** বদলানো যায়: "metric query"। সরকারি উদাহরণ থেকে:

```
count_over_time({job="mysql"}[5m])
sum by (host) (rate({job="mysql"} |= "error" != "timeout" [1m]))
```

একটা Laravel app-এর জন্য "প্রতি মিনিটে কয়টা error?" হয়ে যায়:

```
sum(rate({job="laravel"} |= "ERROR" [1m]))
```

এটা Grafana-র time series panel-এ বসাও, আর হঠাৎ লাফ সাথে সাথে চোখে পড়বে। (Loki এমন query থেকে alertও পাঠাতে পারে, কিন্তু বেশিরভাগ পাঠকের জন্য সহজ পথ হলো Chapter 6-এর মতো *metric*-এর ওপর alert দেওয়া, আর তদন্তে log ব্যবহার করা।)

### ৬. Log-কে নিয়ন্ত্রণে রাখো

- **Log disk খায়।** কত দিন রাখবে ঠিক করো (যেমন ১৪ বা ৩০ দিন) আর Loki-র retention তার সাথে মিলিয়ে ঠিক করো (তার documentation দেখো)। সবকিছু চিরকাল রাখা কোনো পরিকল্পনা নয়।
- **Secret বা ব্যক্তিগত তথ্য log কোরো না** (password, token, card নম্বর, পুরো ব্যক্তিগত বিবরণ)। Central logging ভুলগুলোকে আরও জায়গায় কপি করে আর আরও দীর্ঘ সময় রাখে। Chapter 3-এর নিয়ম এখানে দ্বিগুণ প্রযোজ্য।
- **কে পড়তে পারবে নিয়ন্ত্রণ করো।** Log প্রায়ই তুমি যা ভাবো তার চেয়ে বেশি প্রকাশ করে (email, IP, ভেতরের path)। Grafana-র access শুধু যাদের দরকার তাদের দাও।

## অংশ খ. Uptime check

### ৭. বাইরে থেকে কেন

এ পর্যন্ত সবকিছু *ভেতর* থেকে দেখা। Prometheus তোমার server-এই চলে, তাই **পুরো server-এর power বা network চলে গেলে** Prometheus-ও বন্ধ, আর কিছুই বলে না। আবার server পুরোপুরি সুস্থ থাকলেও দর্শক **site-এ পৌঁছাতে নাও পারে**: DNS ভুল, মেয়াদ-শেষ certificate, firewall rule, বসে-যাওয়া web server। দর্শক যা দেখে তা দেখার একমাত্র উপায় **দর্শকের মতো আচরণ করা**: অন্য কোথাও থেকে নিয়মিত public URL-এ request করা।

### ৮. Blackbox exporter: Prometheus তোমার site-এ যায়

**Blackbox exporter** Prometheus প্রকল্পের তৈরি। সে একটা URL "probe" করে (HTTP, HTTPS, TCP, ping ও আরও) আর ফল metric হিসেবে জানায়। Prometheus তাকে যেকোনো exporter-এর মতোই scrape করে (Chapter 4)। সে port **9115**-এ শোনে, আর তুমি `/probe?target=...&module=...` দিয়ে তাকে target probe করতে বলো, যেমন `http://localhost:9115/probe?target=prometheus.io&module=http_2xx`। `http_2xx` module মানে "একটা HTTP request করো আর সফল (2xx) status আশা করো"।

নিচের scrape configuration-এর গড়ন সরকারি README থেকে। কৌশলটা `relabel_configs`-এ: এগুলো website-এর ঠিকানাকে `target` parameter হিসেবে exporter-কে পাঠায়, কিন্তু Prometheus-কে দিয়ে request পাঠায় **exporter**-এর কাছে (`127.0.0.1:9115`):

```yaml
# prometheus.yml (added to scrape_configs)
scrape_configs:
  - job_name: blackbox
    metrics_path: /probe
    params:
      module: [http_2xx]
    static_configs:
      - targets:
          - https://shop.example.com
          - https://shop.example.com/login
    relabel_configs:
      - source_labels: [__address__]
        target_label: __param_target
      - source_labels: [__param_target]
        target_label: instance
      - target_label: __address__
        replacement: 127.0.0.1:9115
```

এখন প্রতিটা URL-এর জন্য এই metric পাবে:

| Metric | মানে |
|---|---|
| **`probe_success`** | check পাস করলে `1`, ব্যর্থ হলে `0`। যেটা আসল গুরুত্বের। |
| `probe_duration_seconds` | পুরো probe-এ কত সময় লাগল ("ধীর কি?"-র ভালো সংকেত) |
| `probe_ssl_earliest_cert_expiry` | HTTPS certificate-এর মেয়াদ কখন শেষ (Unix সময় হিসেবে)। "শিগগির নবায়ন করো" alert-এর জন্য দারুণ। |

Exporter-এর নিজের config file-এ তুমি নিজের module-ও বানাতে পারো। সরকারি উদাহরণে `valid_status_codes`, `fail_if_not_ssl` আর **`fail_if_body_matches_regexp`**-এর মতো option আছে, যা পাতায় একটা নির্দিষ্ট লেখা *থাকলে* probe-কে ব্যর্থ ধরে। এটা আশ্চর্যরকম কাজের: একটা site status 200 দিয়েও পাতায় "Could not connect to database" দেখাতে পারে।

### ৯. এর জন্য alert (Chapter 6 ব্যবহার করে)

```yaml
groups:
  - name: website
    rules:
      - alert: SiteDown
        expr: probe_success{job="blackbox"} == 0
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "{{ $labels.instance }} is not reachable"

      - alert: CertificateExpiresSoon
        expr: probe_ssl_earliest_cert_expiry{job="blackbox"} - time() < 14 * 24 * 3600
        for: 1h
        labels:
          severity: warning
        annotations:
          summary: "The HTTPS certificate of {{ $labels.instance }} expires in less than 14 days"
```

`SiteDown` এই পুরো course-এর সবচেয়ে মূল্যবান alert, কারণ এটা একটা **লক্ষণ** (Chapter 6): ব্যবহারকারী তোমার কাছে পৌঁছাতে পারছে না। Certificate alert তোমাকে মেয়াদ-শেষ certificate-এর ক্লাসিক লজ্জা থেকে বাঁচায় (Caddy নিজে নবায়ন করে, কিন্তু নবায়ন কখনো নীরবে ব্যর্থ হলে alert তোমাকে জানায়)।

### ১০. সৎ সীমাবদ্ধতা: "বাইরে" সত্যিই বাইরে হতে হবে

তোমার website-এর *একই server*-এ চলা Blackbox exporter তার সাথেই মরে। সত্যিকারের বাইরের দৃষ্টির জন্য এর একটা করো:

- Blackbox exporter ও Prometheus **অন্য একটা machine**-এ চালাও (ছোট একটা দ্বিতীয় VPS, আদর্শভাবে অন্য provider-এ)।
- এমন একটা **uptime service** ব্যবহার করো যা বাইরে থেকে তোমার URL দেখে আর জানায়: একটা hosted (অনেকেরই free plan আছে; বর্তমান শর্ত দেখে নিও), অথবা open-source **Uptime Kuma**, যা তুমি অন্য একটা machine-এ নিজে চালাও।

Chapter 6-এর "পাহারাদারকে পাহারা দাও" ধারণা মনে রাখো: বাইরের check হলো তোমার পুরো monitoring নীরব হয়ে যাওয়ার বিরুদ্ধে সবচেয়ে সহজ সুরক্ষা।

## অংশ গ. Error tracking

### ১১. Log বলে "কিছু ভেঙেছে"; error tracking বলে কী আর কার

তোমার code exception ছুঁড়লে log-এ একটা লাইন থাকে, আর হয়তো হাজার হাজার লাইনের মধ্যে চাপা পড়া একটা stack trace। **Error tracker** ঠিক এই কাজের জন্যই বানানো:

- সে প্রতিটা exception পায় **stack trace-সহ** আর আশপাশের **প্রসঙ্গসহ** (URL, user ID, server, version)।
- সে একই error-কে একটা *issue*-তে **জোট** করে একটা **গণনাসহ** ("এই error ৪৮ জনের ৩১২ বার হয়েছে"), ৩১২টা আলাদা লাইনের বদলে।
- সে জানায় কখন একটা issue **নতুন**, বা ঠিক করার পরে **ফিরে এসেছে**।

সবচেয়ে পরিচিত হলো **Sentry**। **GlitchTip** একটা open-source বিকল্প যা তুমি নিজে চালাতে পারো; এর নিজের site বলে এটা Sentry-র open-source client SDK-এর সাথে কাজ করে, তাই তোমার code বদলাতে হয় না। দুটোই তৃতীয় পক্ষের service: hosted ব্যবহার করলে তোমার error data (যাতে ব্যক্তিগত তথ্য থাকতে পারে) তোমার server-এর বাইরে যায়। Privacy setting পড়ো আর ঠিক করো কী পাঠাতে রাজি।

### ১২. Laravel-এ Sentry যোগ করা

Sentry-র Laravel documentation থেকে (তোমার Laravel version-এর ধাপ ওই পাতায় আছে; নিচের exception hook Laravel 11 ও তার পরের জন্য, `bootstrap/app.php`-এ; পুরনো version অন্য file ব্যবহার করে, docs-এ দেখানো আছে):

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish          # creates config/sentry.php (the docs show how to pass your DSN)
php artisan sentry:test             # sends a test exception to your Sentry project
```

```php
// bootstrap/app.php
use Sentry\Laravel\Integration;

->withExceptions(function (Exceptions $exceptions): void {
    Integration::handles($exceptions);
})
```

```
# .env  (never commit this file)
SENTRY_LARAVEL_DSN=https://<key>@<your-sentry-host>/<project-id>
```

- **DSN** হলো যে ঠিকানায় তোমার app error পাঠায়। একে secret-এর মতো ধরো: এটা `.env` বা তোমার secret store-এ থাকবে, Git-এ নয় (CI/CD course এটা দেখিয়েছে)।
- Setup-এর পরে **`sentry:test`** চালাও। যে error tracker-কে কখনো error পেতে দেখোনি সে এমন alert-এর মতো যাকে কখনো fire করতে দেখোনি (Chapter 6)।
- Sentry **performance trace**-ও রাখতে পারে। ওই setting (`SENTRY_TRACES_SAMPLE_RATE`) হলো কত ভগ্নাংশ request trace হবে; প্রথমে একে **কম** রাখো, কারণ সবকিছু trace করলে টাকা ও গতি দুটোই যায়।
- প্রতিটা report-এ **environment** (`production`, `staging`) tag করো, যাতে তোমার laptop-এর test error কাউকে page না করে, আর যে গোলমাল চাও না তা ছেঁকে ফেলো (যেমন bot-এর "404 not found")।

### ১৩. নিজেকে fire-hose-এর নিচে ফেলো না

Error tracker প্রতিটা error-এর জন্য notification পাঠাতে পারে। Chapter 6-এর নিয়ম ব্যবহার করো: **নতুন issue** আর **regression**-এর (পুরনো issue ফিরে এলে) জন্য জানাও, জানা কোনোটার প্রতিটা ঘটনার জন্য নয়। গোলমেলে গুলো ঠিক করো বা উপেক্ষা করো।

## ১৪. কোন প্রশ্নের জন্য কোন tool?

| তোমার প্রশ্ন | Tool | কেন |
|---|---|---|
| "দর্শক কি *এখন* site-এ পৌঁছাতে পারছে?" | Uptime check (Blackbox / Uptime Kuma) | বাইরে থেকে দেখে |
| "Server সুস্থ? কিছু কি খারাপের দিকে যাচ্ছে?" | Prometheus + Grafana | সময়ের সাথে সংখ্যা |
| "১৪:০২-এর কাছাকাছি app কী বলেছিল?" | Loki log | সব server-এর পুরো লেখা |
| "কোন exception, কোন লাইনে, কতজন ব্যবহারকারী?" | Sentry / GlitchTip | stack trace-সহ জোট করা |
| "যখন গুরুত্বপূর্ণ তখন আমাকে জাগাও" | Alertmanager | Chapter 6 |

কোনোটাই অন্যগুলোর বিকল্প নয়। একটা সাধারণ রাত ৩টা: **Alertmanager** তোমাকে জাগায় (SiteDown) → **Grafana** দেখায় কখন শুরু → **Sentry** exception-এর নাম বলে → **Loki** তার আশপাশে কী ঘটেছিল দেখায়।

## ১৫. মূল শিক্ষা

- `ssh` + `grep` বড় হলে চলে না। **Central logging** প্রতিটা server-এর log এক জায়গায় পাঠায়। **Loki** শুধু **label** index করে (সস্তা) আর তুমি **LogQL** দিয়ে লেখা খোঁজো; **Grafana** পর্দা।
- Agent হিসেবে **Grafana Alloy** ব্যবহার করো: **Promtail-এর জীবনকাল শেষ** (২ মার্চ, ২০২৬)।
- **কম, স্থির label** (`job`, `env`, `host`); user ID, order ID বা trace ID কখনো label-এ নয়। বদলে `|= "text"` বা `| json` ব্যবহার করো। Loki-র port private রাখো; secret log কোরো না; retention ঠিক করো।
- **Uptime check বাইরে থেকে আসতে হবে।** Blackbox exporter `probe_success` দেয়; তার ওপর (`SiteDown`) আর certificate-এর মেয়াদের ওপর alert দাও। একই server-এ থাকা checker তার সাথেই মরে।
- **Error tracking** (Sentry, GlitchTip) stack trace-সহ exception দেয়, গণনাসহ জোট করা। DSN `.env`-এ রাখো, `sentry:test` চালাও, trace sampling কম রাখো, শুধু নতুন ও ফিরে-আসা issue-তে জানাও।
- চারটা tool, চারটা প্রশ্ন: **বাইরে** (uptime), **সংখ্যা** (Prometheus/Grafana), **লেখা** (Loki), **exception** (Sentry)।

## ১৬. নিজেকে পরীক্ষা করো (exam-এর প্রস্তুতি)

<details>
<summary><strong>Q1. `ssh` + `grep` আর যথেষ্ট না হওয়ার দুটো কারণ বলো।</strong></summary>

কয়েকটা server বা container থাকলে কোনটা request সামলেছে জানা যায় না, আর container বদলালে বা server নতুন করে বানালে log হারিয়ে যায়। এছাড়া সবকিছু জুড়ে সব error সহজে graph বা খোঁজা যায় না, আর তদন্তকারীর শুধু log পড়ার জন্য production-এ shell access থাকা উচিত নয়।
</details>

<details>
<summary><strong>Q2. Loki কী index করে, আর সেটা একে সস্তা করে কেন?</strong></summary>

শুধু প্রতিটা log stream-এর label (metadata), লাইনের ভেতরের লেখা নয়। ছোট index-এর disk ও memory প্রতিটা শব্দ index করার চেয়ে অনেক কম লাগে; query আগে label দিয়ে ছেঁকে নেয়, তারপর মিলে-যাওয়া লেখা স্ক্যান করে।
</details>

<details>
<summary><strong>Q3. আজ Loki-তে log পাঠাতে কোন agent ব্যবহার করবে, আর পুরনোটা কেন নয়?</strong></summary>

Grafana Alloy। Promtail ২ মার্চ, ২০২৬-এ end of life হয়েছে: আর কোনো support বা update নেই, আর সব নতুন উন্নয়ন Alloy-তে।
</details>

<details>
<summary><strong>Q4. `order_id` কেন খারাপ Loki label, আর তবুও একটা order-এর log কীভাবে খুঁজবে?</strong></summary>

এর মান প্রায় প্রতিটা লাইনে আলাদা, তাই অসংখ্য stream তৈরি হয়ে index ফুলে যায় আর query ধীর হয়। খোঁজো `{job="laravel"} |= "1042"`-এর মতো line filter দিয়ে, বা structured log-এ `| json` দিয়ে।
</details>

<details>
<summary><strong>Q5. `{job="laravel"} |= "ERROR"` আর `{job="laravel", level="ERROR"}`-এর পার্থক্য কী?</strong></summary>

প্রথমটা `job` label দিয়ে stream বাছে, তারপর শুধু সেই লাইন রাখে যাদের লেখায় "ERROR" আছে (line filter)। দ্বিতীয়টার জন্য stream-এ `level` নামের একটা label থাকতে হয়; label হলো কয়েকটা স্থির মানের জন্য, তাই লেখা ছেঁকে (বা JSON parse করে) খোঁজাই সাধারণ উপায়।
</details>

<details>
<summary><strong>Q6. একই server-এর Prometheus কেন বলতে পারে না যে পুরো server বন্ধ?</strong></summary>

সে server-এর সাথেই বন্ধ হয়, তাই ব্যর্থতার খবর কেউ দেয় না। সত্যিকারের uptime check অন্য machine বা আলাদা বাইরের service থেকে চলতে হবে।
</details>

<details>
<summary><strong>Q7. কোন Blackbox metric জানায় check পাস করেছে, আর HTTPS certificate-এর জন্য কীসের ওপর alert দেবে?</strong></summary>

`probe_success` সফল হলে 1, ব্যর্থ হলে 0। Certificate-এর জন্য `probe_ssl_earliest_cert_expiry - time()` কোনো সেকেন্ড-সংখ্যার (যেমন ১৪ দিন) কম হলে alert দাও।
</details>

<details>
<summary><strong>Q8. Status 200 দিয়েও একটা site কেন "বন্ধ" হতে পারে, আর probe কীভাবে ধরবে?</strong></summary>

পাতা লোড হলেও তার ভেতরে database connection error-এর মতো একটা বার্তা থাকতে পারে। `fail_if_body_matches_regexp` দেওয়া Blackbox module পাতার লেখায় ওই লেখা পেলে probe-কে ব্যর্থ ধরে।
</details>

<details>
<summary><strong>Q9. Log file যা দেয় না, error tracker তা কী দেয়?</strong></summary>

প্রতিটা exception তার stack trace ও প্রসঙ্গসহ, একটা issue-তে জোট করা, ঘটনার সংখ্যা ও আক্রান্ত ব্যবহারকারীসহ, আর কোনো issue নতুন হলে বা ঠিক করার পরে ফিরে এলে খবর।
</details>

<details>
<summary><strong>Q10. Sentry DSN কোথায় রাখবে, আর setup-এর পরে কী চালাবে?</strong></summary>

`.env` বা secret store-এ, কখনো Git-এ নয়। `php artisan sentry:test` চালাও, যাতে নিশ্চিত হও একটা test exception সত্যিই তোমার project-এ পৌঁছায়।
</details>

## ১৭. বিনামূল্যের resource

- Loki: [Overview](https://grafana.com/docs/loki/latest/get-started/overview/), [Install](https://grafana.com/docs/loki/latest/setup/install/), [Label best practices](https://grafana.com/docs/loki/latest/get-started/labels/bp-labels/), [LogQL query](https://grafana.com/docs/loki/latest/query/) ও [metric query](https://grafana.com/docs/loki/latest/query/metric_queries/)
- Promtail-এর end-of-life বিজ্ঞপ্তি: [Promtail docs](https://grafana.com/docs/loki/latest/send-data/promtail/)
- Alloy: [documentation](https://grafana.com/docs/alloy/latest/) ও [`loki.source.file`](https://grafana.com/docs/alloy/latest/reference/components/loki/loki.source.file/), [GitHub](https://github.com/grafana/alloy)
- [Blackbox exporter](https://github.com/prometheus/blackbox_exporter) (README, configuration, উদাহরণ config)
- [Uptime Kuma](https://github.com/louislam/uptime-kuma) (নিজে চালানো uptime monitor)
- [Laravel-এর জন্য Sentry](https://docs.sentry.io/platforms/php/guides/laravel/) ও [GlitchTip](https://glitchtip.com/documentation)

---

**পরের chapter:** Chapter 8 — Real project: একটা Laravel app monitor করা *(শীঘ্রই আসছে)*
