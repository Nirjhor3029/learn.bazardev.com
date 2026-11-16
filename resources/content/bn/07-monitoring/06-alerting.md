# Chapter 6 — যে Alert সাহায্য করে, বিরক্ত করে না

> **Course:** Monitoring & Logging · **Chapter:** 6 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/monitoring/06-alerting)

Dashboard (Chapter 5) তখনই কাজে লাগে যখন কেউ সেটা **দেখছে**। রাত ৩টায় কেউ দেখে না। **Alert** এই ব্যাপারটা উল্টে দেয়: সিস্টেম নিজেই তোমার হয়ে সংখ্যাগুলো পাহারা দেয় আর কিছু একজন মানুষের দরকার হলে **তোমার কাঁধে টোকা দেয়**। ঠিকভাবে করলে alert মানে "ওঠো, এটা জরুরি"। ভুলভাবে করলে দিনে পঞ্চাশটা বার্তা আসে, তুমি সবগুলো উপেক্ষা করতে শেখো, আর আসল alert-টা গোলমালে হারিয়ে যায়। এই chapter প্রথম ধরনের alert নিয়ে।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Prometheus, Alertmanager ও `promtool` **install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের rule, config ও command গুলো সরকারি documentation থেকে লেখা এবং **চালানো হয়নি**। যা *যাচাই করা হয়েছে*: নিচের প্রতিটা YAML টুকরো যে বৈধ YAML (tool দিয়ে parse করা), field-এর নাম, ডিফল্ট মান (`group_wait` ৩০ সেকেন্ড, `group_interval` ৫ মিনিট, `repeat_interval` ৪ ঘণ্টা, `evaluation_interval` ১ মিনিট, port 9093) আর `promtool`/`amtool` command-এর রূপ সরকারি docs ও source repository থেকে পড়া, আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। PromQL rule গুলো Chapter 2 ও 4-এর metric-নাম ব্যবহার করে। কোনো rule **তোমার server-এ** ঠিকমতো fire করে কি না, সেটা ৯ নম্বর অংশের test দিয়ে তুমি নিজে প্রমাণ করবে। Package-এর version আর menu বা flag-এর নাম তোমার সিস্টেমে আলাদা হতে পারে।

![একটা alert-এর যাত্রা: Prometheus rule দেখে, Alertmanager জোট করে ও পথ ঠিক করে, তুমি পাও একটা বার্তা](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/07-monitoring/images/alerting-flow.svg)

## ১. দুটো কাজ, দুটো program

Alerting দুটো program-এর মধ্যে ভাগ করা, আর এ দুটোকে গুলিয়ে ফেলা নতুনদের সবচেয়ে সাধারণ বিভ্রান্তি:

| Program | তার একমাত্র কাজ |
|---|---|
| **Prometheus** | **কখন কিছু ভুল তা ঠিক করে।** সে timer ধরে তোমার *alert rule* (একটা PromQL শর্ত) চালায়। শর্ত যথেষ্ট সময় সত্য থাকলে alert *firing* হয় আর Prometheus সেটা এগিয়ে দেয়। |
| **Alertmanager** | **কে শুনবে আর কীভাবে তা ঠিক করে।** সে firing alert পায়, একই ধরনেরগুলো **জোট (group)** করে, তোমার চুপ-করানোগুলো **silence** করে, সঠিক **receiver** (email, Telegram, webhook) বাছে আর **একটাই** গোছানো বার্তা পাঠায়। |

আলাদা কেন? কারণ "disk কি ভরে গেছে?" আর "কে, কোন পথে, কোন সময়ে জাগবে?" দুটো আলাদা প্রশ্ন, আর দ্বিতীয়টা প্রায়ই বদলায় (on-call মানুষ বদলায়, channel বদলায়) অথচ প্রথমটা একটুও বদলায় না।

## ২. কীসের alert দরকার? (সবচেয়ে গুরুত্বপূর্ণ অংশ)

পঞ্চাশটা alert যে কেউ লিখতে পারে। দক্ষতা হলো সেই কয়েকটা লেখা যা সত্যিই গুরুত্বপূর্ণ। Prometheus-এর সরকারি alerting নির্দেশনা বলে: alerting সরল রাখো, যেখানে "করার কিছু নেই" সেখানে page এড়াও, আর "যতটা সম্ভব কম alert রাখো"। বাঁচার জন্য চারটা নিয়ম:

1. **লক্ষণের (symptom) alert দাও, কারণের (cause) নয়।** লক্ষণ হলো ব্যবহারকারী যা টের পায়: site বন্ধ, page ধীর, checkout-এ error। কারণ হলো *কেন*: CPU ৯০%, একটা process restart হয়েছে। Site খুশি থাকলে ব্যস্ত CPU-তে মানুষের দরকার নেই। লক্ষণের alert দাও; কারণগুলো dashboard-এ রাখো, পরে তদন্তের জন্য।
2. **প্রতিটা alert-এ করার মতো কিছু থাকতে হবে।** জিজ্ঞেস করো: "রাত ৩টায় এটা fire করলে আমি ঠিক কী করব?" সৎ উত্তর যদি হয় "কিছুই না, আবার ঘুমাব", তাহলে ওটা alert নয়; ওটাকে dashboard panel বা দৈনিক রিপোর্ট বানাও।
3. **দুটো স্তরই যথেষ্ট।**
   - **critical** ("page"): এখনই কিছু ভেঙেছে আর ব্যবহারকারী ক্ষতিগ্রস্ত। মানুষকে ঘুম থেকে তোলে।
   - **warning** ("ticket"): কিছু শিগগিরই সমস্যা হবে (disk ভরে আসছে)। সকাল পর্যন্ত অপেক্ষা করতে পারে; chat channel বা email-এ পাঠাও, বেজে ওঠা ফোনে নয়।
4. **প্রসঙ্গ (context) দাও।** বার্তায় থাকবে *কী* ভুল, *কোথায়*, *কতটা খারাপ*, আর আদর্শভাবে একটা dashboard বা ছোট "কী করতে হবে" পাতার link। শুধু "alert!" লেখা বার্তা সবচেয়ে খারাপ সময়ে পাঁচ মিনিটের গোয়েন্দাগিরি চাপায়।

আসল বিপদ হলো **alert fatigue (alert-ক্লান্তি)**। মানুষ যদি এমন alert পায় যার কিছু করার নেই, তারা সব alert উপেক্ষা করতে শেখে, আর তারপর আসল alert-টা মিস করে। যে noisy alert তুমি রেখে দাও সেটা তোমার monitoring-কে *খারাপ* করে। বারবার "নেকড়ে এসেছে" বলা alert মুছে ফেলো বা ঠিক করো।

## ৩. একটা alert rule-এর গড়ন

Alert rule থাকে একটা YAML file-এ যা Prometheus লোড করে। ফরম্যাট সরকারি documentation থেকে:

```yaml
# /etc/prometheus/rules/server.yml
groups:
  - name: server-health
    rules:
      - alert: InstanceDown
        expr: up{job="node"} == 0
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "{{ $labels.instance }} is down"
          description: "Prometheus could not scrape {{ $labels.instance }} for 2 minutes."
```

লাইন ধরে ধরে পড়ো:

- **`alert`**: নাম। এমন নাম বাছো যা সমস্যার মতো পড়া যায়: `InstanceDown`, `Check1` নয়।
- **`expr`**: একটা PromQL শর্ত। Prometheus এটা প্রতি `evaluation_interval`-এ (ডিফল্ট **১ মিনিট**) চালায়। Expression যে series ফেরত দেয় তার প্রতিটাই একটা *সক্রিয় alert*। এখানে `up == 0` প্রতিটা সেই target-এর জন্য একটা series দেয় যাকে scrape করা যায়নি। (`up` Chapter 4 থেকে।)
- **`for`**: **alert fire হওয়ার আগে শর্তটা কতক্ষণ সত্য থাকতে হবে।** ভুয়া সতর্কতার বিরুদ্ধে এটাই তোমার প্রধান অস্ত্র। Reboot-এর সময় ২০ সেকেন্ডের ঝলকানি কাউকে জাগানো উচিত নয়; পুরো ২ মিনিট বন্ধ থাকলে জাগানো উচিত। `for` না থাকলে alert একদম প্রথম মূল্যায়নেই fire করে।
- **`labels`**: alert-এ জোড়া বাড়তি label। `severity` কোনো জাদু নয়: এটা শুধু একটা label যা *তুমি* বাছো, আর Alertmanager তা দিয়ে **routing** করতে পারে (৬ নম্বর অংশ)।
- **`annotations`**: মানুষের পড়ার লেখা। দুটো template variable পাওয়া যায়: **`$labels`** (ওই alert-এর label, যেমন `$labels.instance`) আর **`$value`** (expression-এর দেওয়া সংখ্যা)।

### তিনটা অবস্থা

Prometheus যখন একটা শর্তের ওপর নজর রাখে, alert তিনটা অবস্থার একটায় থাকে (ওপরের ছবি দেখো):

| অবস্থা | মানে |
|---|---|
| **inactive** | শর্ত মিথ্যা। সব ঠিক আছে। |
| **pending** | শর্ত সত্য, কিন্তু এখনো পুরো `for` সময় হয়নি। কিছু পাঠানো হয় না। |
| **firing** | শর্ত পুরো `for` সময় ধরে সত্য ছিল। Alertmanager-এ পাঠানো হয়। |

Alert pending থাকা অবস্থায় শর্ত মিথ্যা হয়ে গেলে সেটা নিঃশব্দে আবার inactive হয়ে যায়: ২০ সেকেন্ডের ঝলকানি ঠিক এভাবেই উপেক্ষা হয়। (Prometheus এগুলোকে `ALERTS` নামের একটা series হিসেবেও রাখে, তাই Grafana-য় "কখন কী fire করছিল" graph করা যায়।) সংশ্লিষ্ট একটা setting `keep_firing_for` শর্ত মিটে যাওয়ার *পরেও* কিছুক্ষণ alert-কে firing রাখে, যাতে বারবার চালু-বন্ধ হওয়া alert বার্তার পর বার্তা না পাঠায়।

## ৪. একটা server-এর জন্য শুরুর rule-এর সেট

এগুলো Chapter 2 ও 4-এর `node_exporter` metric ব্যবহার করে। পাঁচটা rule, প্রতিটা উত্তর দেয় "মানুষের কি কিছু করতে হবে?":

```yaml
# /etc/prometheus/rules/server.yml  (পুরো file)
groups:
  - name: server-health
    rules:
      # Critical: the server (or its exporter) is unreachable.
      - alert: InstanceDown
        expr: up{job="node"} == 0
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "{{ $labels.instance }} is down"
          description: "Prometheus could not scrape {{ $labels.instance }} for 2 minutes."

      # Warning: the root disk is getting full. Fix it before it becomes critical.
      - alert: DiskAlmostFull
        expr: (1 - node_filesystem_avail_bytes{mountpoint="/"} / node_filesystem_size_bytes{mountpoint="/"}) * 100 > 85
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "Disk / is {{ $value | printf \"%.0f\" }}% full on {{ $labels.instance }}"

      # Warning: at the current pace, the disk will be full within a day.
      - alert: DiskFullWithin24h
        expr: predict_linear(node_filesystem_avail_bytes{mountpoint="/"}[6h], 24 * 3600) < 0
        for: 30m
        labels:
          severity: warning
        annotations:
          summary: "Disk / on {{ $labels.instance }} is on track to fill up within 24 hours"

      # Warning: very little memory left for programs (uses AVAILABLE, as in Chapter 2).
      - alert: MemoryLow
        expr: node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes * 100 < 10
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "Only {{ $value | printf \"%.0f\" }}% memory available on {{ $labels.instance }}"

      # Warning: the CPUs have been overloaded for a long time (load per core, as in Chapter 5).
      - alert: HighLoad
        expr: node_load5 / on (instance) count by (instance) (node_cpu_seconds_total{mode="idle"}) > 1.5
        for: 15m
        labels:
          severity: warning
        annotations:
          summary: "Load is {{ $value | printf \"%.1f\" }} per core on {{ $labels.instance }}"
```

এই বাছাইগুলো কেন:

- **শুধু একটা `critical`।** "Server-এ পৌঁছানো যাচ্ছে না" একটা সত্যিকারের জরুরি অবস্থা। বাকিগুলো *warning*: তারা বলে "আজ এটা দেখো", "এখনই জাগো" নয়।
- **Resource alert-এ লম্বা `for` (১০ থেকে ৩০ মিনিট)।** CPU-র হঠাৎ ঝাঁপ বা memory-র ক্ষণিক চূড়া স্বাভাবিক জীবনের অংশ; শুধু *টানা* সমস্যা বার্তার যোগ্য।
- **`predict_linear`** শেষ ৬ ঘণ্টার data দেখে, একটা সরলরেখা সামনে টানে আর জিজ্ঞেস করে "২৪ ঘণ্টা পরে এটা কোথায় থাকবে?"। এটা "disk ৭০% ভরা" (জরুরি নয়) কে বদলে করে "disk কাল সকালে ভরে যাবে" (জরুরি)। এটা তোমার পাওয়া সেরা alert-গুলোর একটা, কারণ এটা শান্তভাবে কাজ করার সময় দেয়। এর ফল একটা *অনুমান*, তাই এটা warning-এর জন্য ভালো, page-এর জন্য নয়।
- **Disk-এ ৮৫%, ৯৯% নয়।** এমন আগে alert দাও যাতে কাজের সময়েই ঠিক করা যায়।
- **`$value | printf "%.0f"`** একটা Go-template উপায় সংখ্যাকে গোল করার, যাতে বার্তায় "87.31928" এর বদলে "87%" লেখা থাকে।

> **ব্যবহারকারীর কষ্টেরও alert দাও।** ওপরের পাঁচটা rule *machine* দেখে। একটা web app-এর জন্য সবচেয়ে মূল্যবান alert হলো লক্ষণের ওপর: কত শতাংশ request ব্যর্থ হচ্ছে। তোমার app যদি একটা request counter দেয় (নাম আলাদা হয়; নিচেরটা একটা দৃষ্টান্ত), rule এমন হতে পারে:
>
> ```yaml
>       - alert: HighErrorRate
>         expr: sum(rate(http_requests_total{status=~"5.."}[5m])) / sum(rate(http_requests_total[5m])) * 100 > 5
>         for: 5m
>         labels:
>           severity: critical
>         annotations:
>           summary: "More than 5% of requests are failing with a server error"
> ```
>
> Chapter 8-এ তুমি একটা Laravel app-এ আসল metric যোগ করবে।

## ৫. Prometheus-কে rule আর Alertmanager সম্পর্কে বলো

`prometheus.yml`-এ তুমি দুটো জিনিস যোগ করো: rule file কোথায়, আর Alertmanager কোথায়। (Alertmanager-এর নিজের port **9093**।) গড়নটা সরকারি উদাহরণ config থেকে:

```yaml
# /etc/prometheus/prometheus.yml (the new parts)
global:
  evaluation_interval: 1m     # how often rules are checked (1m is the default)

rule_files:
  - "rules/*.yml"

alerting:
  alertmanagers:
    - static_configs:
        - targets: ["localhost:9093"]
```

তারপর Prometheus-কে reload (বা restart) করতে বলো আর তার web পেজে **Alerts** খোলো: প্রতিটা rule আর তার অবস্থা (inactive, pending বা firing) দেখতে পাবে।

Reload করার **আগে** file গুলো যাচাই করো। `promtool` Prometheus-এর সাথেই আসে:

```bash
promtool check rules /etc/prometheus/rules/server.yml
promtool check config /etc/prometheus/prometheus.yml
```

Rule file-এ একটা ভুল বানান হলো *একটাও alert না থাকার* আর তা না জানার ক্লাসিক উপায়। প্রতিবার এই check চালাও (আর তোমার CI pipeline-এও, CI/CD course-এর মতো)।

## ৬. Alertmanager: group, route, receiver

Alertmanager-এর তিনটা ধারণা। প্রতিটা বার্তার বন্যা ঠেকাতেই আছে।

**Grouping।** সরকারি সংজ্ঞা: "Grouping categorizes alerts of similar nature into a single notification." পুরো একটা rack যদি network হারায়, তুমি ৪০টা নয়, তাদের তালিকাসহ **একটা** বার্তা পাও।

**Inhibition।** "Suppressing notifications for certain alerts if certain other alerts are already firing." একটা server যদি *বন্ধ* থাকে, তাহলে ওই server-এর "disk check failed", "memory alert"... আলাদা করে লাগে না: সবই পরিণতি। একটা alert (কারণটা) যথেষ্ট।

**Silence।** "A straightforward way to simply mute alerts for a given time." নির্ধারিত রক্ষণাবেক্ষণ? ওই server-এর alert দুই ঘণ্টার জন্য silence করো, যাতে পরিকল্পিত reboot কাউকে page না করে। Silence নিজে থেকেই মেয়াদ শেষ হয়, আর সেটাই একে নিরাপদ করে।

আর **routing**: একটা গাছ যা প্রতিটা alert-কে তার label (যেমন `severity`) দেখে সঠিক receiver-এ পাঠায়। নিচে একটা ছোট সম্পূর্ণ config। Field-এর নাম আর তিনটা সময়ের ডিফল্ট সরকারি docs থেকে; নিচের মানগুলো ডিফল্ট-ই, লিখে দেখানো হয়েছে:

```yaml
# /etc/alertmanager/alertmanager.yml
global:
  smtp_smarthost: 'smtp.example.com:587'
  smtp_from: 'alerts@example.com'
  smtp_auth_username: 'alerts@example.com'
  smtp_auth_password_file: /etc/alertmanager/smtp_password   # the password lives in a file, not here

route:
  receiver: team-chat                  # the default: everything goes here
  group_by: [alertname, instance]
  group_wait: 30s                      # wait this long to collect alerts into the first message
  group_interval: 5m                   # wait this long before telling about NEW alerts in a group
  repeat_interval: 4h                  # remind this often while an alert is still firing
  routes:
    - matchers:
        - severity="critical"
      receiver: on-call                # critical alerts: chat AND email

inhibit_rules:
  - source_matchers: [alertname="InstanceDown"]   # if the server is down...
    target_matchers: [severity="warning"]         # ...mute its warnings
    equal: [instance]                             # ...but only for the SAME instance

receivers:
  - name: team-chat
    telegram_configs:
      - bot_token_file: /etc/alertmanager/telegram_token
        chat_id: -1001234567890
  - name: on-call
    telegram_configs:
      - bot_token_file: /etc/alertmanager/telegram_token
        chat_id: -1001234567890
    email_configs:
      - to: 'oncall@example.com'
        send_resolved: true
```

নোট, কারণ প্রতিটা লাইনের গুরুত্ব আছে:

- **`group_wait` / `group_interval` / `repeat_interval`** বন্যা নিয়ন্ত্রণ করে। ছোট `group_wait` মানে দ্রুত কিন্তু হয়তো কয়েকটা বার্তা; লম্বা `repeat_interval` মানে কম মনে-করিয়ে-দেওয়া। ডিফল্ট (৩০ সেকেন্ড, ৫ মিনিট, ৪ ঘণ্টা) শুরুর জন্য ঠিক আছে।
- **`equal: [instance]` গুরুত্বপূর্ণ।** এটা ছাড়া "server A-তে InstanceDown" *প্রতিটা* server-এর warning চুপ করিয়ে দেবে। (সরকারি উদাহরণ config-এও একটা সতর্কতা আছে: `equal`-এ তালিকাভুক্ত সব label যদি দুই alert-এই না থাকে, তবুও rule প্রযোজ্য হয়।)
- **Secret file-এর বাইরে।** SMTP password আর Telegram bot token আলাদা file থেকে পড়া হয় (`smtp_auth_password_file`, `bot_token_file`), যেগুলো তুমি কড়া permission দিয়ে বানাও (`chmod 600`, Alertmanager-এর user-এর মালিকানায়, Linux course-এর মতো) আর **কখনো Git-এ commit করো না**। উদাহরণের chat id আর ঠিকানা নিজেরটা দিয়ে বদলাও; Telegram bot বানানো হয় **@BotFather**-এর সাথে chat করে (নিচে Telegram docs দেখো)।
- **`send_resolved: true`** "সমস্যা মিটেছে" বার্তাও পাঠায়। Webhook ও Telegram ডিফল্টভাবে এটা করে; email করে না, যতক্ষণ না চাও।
- **Webhook receiver** (`webhook_configs`-এ একটা `url`) alert-কে JSON হিসেবে যেকোনো tool-এ পাঠায়: তোমার নিজের Laravel route, chat bot, ticket system।
- **Port 9093 private রাখো**, ঠিক যেমন Chapter 5 Prometheus আর Grafana-র জন্য বলেছিল। সরাসরি (out of the box) Alertmanager কোনো login চায় না: যে-ই তাকে ধরতে পারে সে **তোমার alert silence করে দিতে পারে**। শুধু local machine-এ শোনাও (command-line flag দিয়ে listen address ঠিক করো; তোমার version-এ নামটা জানতে `alertmanager --help` দেখো) আর firewall ব্যবহার করো।

## ৭. Pipeline পরীক্ষা করো: alert কি সত্যিই তোমার কাছে পৌঁছায়?

যে alert কখনো fire হতে দেখোনি তাকে ভরসা করা যায় না। **তিনটা** জিনিস পরীক্ষা করো, এই ক্রমে:

1. **Notification-এর পথ।** Alertmanager-কে একটা নকল alert পাঠাও, যাতে Telegram বা inbox-এ বার্তা আসতে দেখো। সবচেয়ে সহজ উপায় যাতে বাড়তি tool লাগে না তা নিচের তৃতীয় পরীক্ষা (test machine-এ `node_exporter` বন্ধ করা); Alertmanager-এর documentation হাতে test alert পাঠানোর জন্য তার HTTP API-ও বর্ণনা করে। `amtool` (যা Alertmanager-এর সাথে আসে) জিনিস দেখতে ও যাচাই করতেও পারে: `amtool --help` দেখো।
2. **Rule নিজে,** একটা **unit test** দিয়ে (পরের অংশ), কিছু না ভেঙে।
3. **পুরো শৃঙ্খল, একবার, সত্যিকারে।** একটা test machine-এ `node_exporter` বন্ধ করো আর দেখো: প্রায় এক মিনিট পরে pending, `for` সময়ের পরে firing, তারপর বার্তা। আবার চালু করো আর resolve হতে দেখো।

তারপর **silence-এর অভ্যাস করো**, যাতে রাত ৩টায় `amtool` কীভাবে Alertmanager-এর সাথে কথা বলে জানা থাকে (docs অনুযায়ী তার ডিফল্ট ঠিকানা `http://localhost:9093`, `~/.config/amtool/config.yml`-এ বদলানো যায়):

```bash
amtool alert                                   # list what is firing right now
amtool silence add alertname=DiskAlmostFull    # mute one alert (see --help for duration and comment options)
amtool silence query                           # list silences
amtool silence expire <id>                     # remove a silence early
```

## ৮. নীরব monitoring-সিস্টেম বিপজ্জনক

Prometheus নিজে মরে গেলে, বা Alertmanager আর mail পাঠাতে না পারলে? তখন **কোনো alert আসে না, আর তুমি ভাবো সব ঠিক আছে।** এটাই সবচেয়ে খারাপ ধরনের ব্যর্থতা। সরকারি নির্দেশনা বলে monitoring নিজের alert রাখো আর *বাইরে* থেকে এমন check যোগ করো যা "otherwise invisible" সমস্যা ধরে। ব্যবহারিক ধাপ:

- শুধু তোমার server নয়, **Prometheus নিজের** আর Alertmanager-এর scrape job-এর জন্যও `up`-এর alert রাখো।
- একটা চালু ধরন হলো "**heartbeat**" alert: এমন একটা rule যা *সবসময়* firing (`expr: vector(1)`), নিয়মিত একটা **বাইরের** service-এ পাঠানো, যে heartbeat **থেমে গেলে** অ্যালার্ম বাজায়। নীরবতাই তখন সংকেত। বাইরে থেকে তোমার site দেখা একটা বিনামূল্যের uptime checker (Chapter 7) কম ঝামেলায় একই সুরক্ষা দেয়।
- একটামাত্র channel-এর ওপর নির্ভর করো না। Email আর Telegram দুটোই একসাথে বন্ধ হলে critical alert-এর জন্য তৃতীয় একটা পথ চাই।

## ৯. `promtool` দিয়ে তোমার rule-এর unit test

`promtool` একটা alert rule **বানানো data দিয়ে** পরীক্ষা করতে পারে, কোনো আসল server ছাড়াই: তুমি কয়েক মিনিটের নকল metric বর্ণনা করো আর বলো কোন alert আশা করছ। ফরম্যাট সরকারি "Unit testing for rules" পাতা থেকে:

```yaml
# /etc/prometheus/rules/server_test.yml
rule_files:
  - server.yml
evaluation_interval: 1m
tests:
  - interval: 1m
    input_series:
      - series: 'up{job="node", instance="web1:9100"}'
        values: '1 1 0 0 0 0'          # up for 2 minutes, then down
    alert_rule_test:
      - eval_time: 3m                  # down for only 1 minute so far: still pending
        alertname: InstanceDown
        exp_alerts: []
      - eval_time: 5m                  # down for 3 minutes (> for: 2m): firing
        alertname: InstanceDown
        exp_alerts:
          - exp_labels:
              severity: critical
              job: node
              instance: "web1:9100"
            exp_annotations:
              summary: "web1:9100 is down"
              description: "Prometheus could not scrape web1:9100 for 2 minutes."
```

চালাও:

```bash
promtool test rules server_test.yml
```

এটা একসাথে দুটো জিনিস প্রমাণ করে: rule যখন fire করার কথা (৫ম মিনিটে) তখন করে, আর খুব তাড়াতাড়ি **করে না** (৩য় মিনিটে, যখন `for` এখনো গুনছে)। এই test গুলো তোমার rule-এর পাশে Git-এ রাখো আর CI-তে চালাও: তখন কেউ ব্যর্থ test-এর সতর্কতা ছাড়া alert ভাঙতে পারবে না।

## ১০. সময়ের সাথে alert-কে সুস্থ রাখা

Alerting "বানিয়ে ভুলে যাওয়ার" জিনিস নয়। ছোট একটা রুটিন একে কাজের রাখে:

1. **প্রতিটা alert-এ একটা "কী করতে হবে" নোট।** Annotation-এ একটা link রাখো (runbook: ছোট একটা পাতা বা wiki entry, যাতে প্রথম তিনটা দেখার জিনিস লেখা)। এটা আতঙ্কিত রাত ৩টাকে একটা checklist বানায়।
2. **প্রতি মাসে review।** যে alert fire করেছে তার জন্য জিজ্ঞেস করো: আসল ছিল? কেউ কিছু করেছে? কোনো alert দশবার fire করে কেউ কিছু না করলে **সেটা মুছে ফেলো বা threshold বা `for` বাড়াও**। লক্ষ্য কম, ভালো alert।
3. **প্রতিটা ঘটনা একটা alert শেখায়।** Outage-এর পরে জিজ্ঞেস করো: "আমরা কি সময়মতো জানতাম?" না হলে হারানো alert (*লক্ষণের* ওপর) যোগ করো আর তার একটা test লেখো।
4. **জরুরিতার সাথে channel মেলাও।** Critical যায় জোরালো channel-এ; warning যায় শান্ত chat রুমে বা দৈনিক email-এ। Warning যদি critical-এর মতোই জোরালো হয়, মানুষ দুটোই উপেক্ষা করে।
5. **রক্ষণাবেক্ষণের silence ব্যবহার করো,** "আজ রাতটা না হয় উপেক্ষাই করি" কখনো নয়।

### Grafana-র নিজের alerting নিয়ে একটা কথা

Grafana-রও (Chapter 5) নিজের alerting ফিচার আছে, যা সরাসরি dashboard-এর query থেকে alert দিতে পারে, আর একই ধরনের receiver-এ পাঠায়। দুটোই চলে। একটা সরল নিয়ম: Prometheus যদি তোমার প্রধান data source হয়, rule গুলো **Prometheus + Alertmanager-এ** Git-এ code হিসেবে রাখো, যেমন এই chapter দেখিয়েছে; Prometheus-এ নেই এমন data source-এর ওপর alert চাইলে Grafana alerting ব্যবহার করো। প্রতিটা alert-এর জন্য একটাই জায়গা বাছো, যাতে কোন সিস্টেম বার্তা পাঠাল তা নিয়ে ভাবতে না হয়।

## ১১. মূল শিক্ষা

- Dashboard-এর জন্য চোখ লাগে; **alert** নিজে গিয়ে তোমাকে খুঁজে নেয়। Prometheus **কখন** ঠিক করে (rule), Alertmanager **কে** ঠিক করে (group, route, silence, receiver)।
- **লক্ষণের** alert দাও, প্রতিটাকে **করার-মতো** রাখো, **দুটো স্তর** ব্যবহার করো (critical জাগায়, warning অপেক্ষা করে), **প্রসঙ্গ** দাও। কম alert ভালো: **alert-ক্লান্তি** monitoring-কে খারাপ করে।
- একটা rule = `expr` + **`for`** (কতক্ষণ সত্য থাকতে হবে) + label + annotation। অবস্থা: **inactive, pending, firing**।
- `predict_linear` তোমাকে আগেই জানাতে পারে disk **ভরবে**, যাতে শান্তভাবে কাজ করা যায়।
- Grouping, inhibition আর silence বন্যা ঠেকায়। Inhibit rule-এ **`equal`** ব্যবহার করো। ডিফল্ট: `group_wait` ৩০ সেকেন্ড, `group_interval` ৫ মিনিট, `repeat_interval` ৪ ঘণ্টা।
- **Secret থাকে file-এ, Git-এ নয়।** Alertmanager (port 9093) private রাখো: যে-ই তাকে ধরতে পারে সে তোমার alert silence করতে পারে।
- `promtool check rules` দিয়ে **file যাচাই** করো, `promtool test rules` দিয়ে **rule-এর unit test** করো, আর **পাহারাদারকেও পাহারা দাও**: monitoring নিজে মরলে alert দাও।

## ১২. নিজেকে পরীক্ষা করো (exam-এর প্রস্তুতি)

<details>
<summary><strong>Q1. Alerting-এ Prometheus-এর কাজ কী আর Alertmanager-এর কাজ কী?</strong></summary>

Prometheus alert rule মূল্যায়ন করে আর ঠিক করে কখন কিছু ভুল (alert fire করে)। Alertmanager firing alert পায় আর ঠিক করে কাকে কীভাবে জানানো হবে: সে group, silence, inhibit ও route করে, তারপর notification (email, Telegram, webhook) পাঠায়।
</details>

<details>
<summary><strong>Q2. "কারণের নয়, লক্ষণের alert দাও" কেন ভালো পরামর্শ? একটা উদাহরণ দাও।</strong></summary>

লক্ষণ হলো ব্যবহারকারী যা টের পায় (site বন্ধ, ধীর page, ব্যর্থ checkout); কারণ শুধু সম্ভাব্য একটা কারণ (বেশি CPU)। Site সুস্থ থাকলে ব্যস্ত CPU-তে মানুষের দরকার নেই, তাই তাতে alert দিলে শুধু গোলমাল হয়। ব্যর্থ request-এর alert দাও; CPU তদন্তের জন্য dashboard-এ রাখো।
</details>

<details>
<summary><strong>Q3. Alert rule-এ `for: 10m` কী করে?</strong></summary>

Alert fire হওয়ার আগে শর্তটা ১০ মিনিট টানা সত্য থাকতে হয়। ততক্ষণ সেটা "pending" আর কিছু পাঠানো হয় না। এতে ক্ষণিক ঝলকানির জন্য কাউকে জাগাতে হয় না।
</details>

<details>
<summary><strong>Q4. Prometheus-এ alert-এর তিনটা অবস্থার নাম বলো।</strong></summary>

Inactive (শর্ত মিথ্যা), pending (সত্য, কিন্তু এখনো পুরো `for` সময় হয়নি) আর firing (পুরো `for` সময় সত্য, Alertmanager-এ পাঠানো)।
</details>

<details>
<summary><strong>Q5. `alert fatigue` কী, আর কীভাবে এর সাথে লড়বে?</strong></summary>

মানুষ যখন এত alert পায় (বিশেষত যেগুলোর কিছু করার নেই) যে সব উপেক্ষা করতে শেখে, আসলটাসহ। লড়ো কম, করার-মতো alert দিয়ে: noisy গুলো মুছো বা ঠিক করো, `for` ব্যবহার করো, সম্পর্কিত alert জোট করো, আর warning শান্ত channel-এ পাঠাও।
</details>

<details>
<summary><strong>Q6. Disk alert-এর জন্য `predict_linear` কেন কাজের?</strong></summary>

এটা সাম্প্রতিক প্রবণতাকে সামনে টানে, তাই তোমাকে আগেই সতর্ক করা যায় যে disk, ধরো, ২৪ ঘণ্টায় ভরবে, যখন শান্তভাবে ঠিক করার সময় আছে, ৯৯% ভরার পর জানার বদলে।
</details>

<details>
<summary><strong>Q7. Grouping, inhibition আর silence প্রত্যেকে কী করে?</strong></summary>

Grouping একই ধরনের alert মিলিয়ে একটা notification বানায়; inhibition অন্য একটা (কারণ) alert আগে থেকেই firing থাকলে কিছু alert দমন করে; silence মিলে যাওয়া alert একটা নির্দিষ্ট সময়ের জন্য চুপ করায়, যেমন নির্ধারিত রক্ষণাবেক্ষণের সময়।
</details>

<details>
<summary><strong>Q8. Inhibit rule-এ সাধারণত `equal` কেন লাগে?</strong></summary>

যাতে চুপ করানো শুধু একই জিনিসের (যেমন একই `instance`) alert-এ প্রযোজ্য হয়। এটা ছাড়া একটা server বন্ধ থাকলে অন্য সব server-এর warning-ও চুপ হয়ে যেতে পারে।
</details>

<details>
<summary><strong>Q9. Alertmanager-এর port private কেন রাখবে, আর email-এর password কোথায় যাবে?</strong></summary>

সরাসরি Alertmanager কোনো login চায় না, তাই যে-ই তাকে ধরতে পারে সে তোমার alert silence করতে পারে; তাকে local machine-এ bind করো আর firewall-এ আটকাও। Password যাবে আলাদা, permission-সীমিত file-এ (যেমন `smtp_auth_password_file` দিয়ে), Git-এ commit করা config-এ নয়।
</details>

<details>
<summary><strong>Q10. কিছু না ভেঙে alert rule কীভাবে পরীক্ষা করবে, আর monitoring নিজে মরলে কী হবে?</strong></summary>

নকল input series আর নির্দিষ্ট সময়ে আশা-করা alert দিয়ে একটা `promtool test rules` unit test লেখো। Monitoring-সিস্টেম মরার জন্য Prometheus/Alertmanager-এর সুস্থতার alert দাও আর একটা heartbeat বা বাইরের uptime check রাখো, যাতে নীরবতাই অ্যালার্ম বাজায়।
</details>

## ১৩. বিনামূল্যের resource

- Prometheus docs: [Alerting rules](https://prometheus.io/docs/prometheus/latest/configuration/alerting_rules/) ও [Unit testing for rules](https://prometheus.io/docs/prometheus/latest/configuration/unit_testing_rules/)
- Alertmanager docs: [Overview (grouping, inhibition, silences)](https://prometheus.io/docs/alerting/latest/alertmanager/) ও [Configuration](https://prometheus.io/docs/alerting/latest/configuration/)
- [Alerting best practices](https://prometheus.io/docs/practices/alerting/) (ওপরে উদ্ধৃত "কম alert, লক্ষণের ওপর" নির্দেশনা)
- [GitHub-এ Alertmanager](https://github.com/prometheus/alertmanager) (`amtool`-এর উদাহরণসহ README) ও তার [উদাহরণ config](https://github.com/prometheus/alertmanager/blob/main/doc/examples/simple.yml)
- Telegram: [Bot API](https://core.telegram.org/bots/api)
- Grafana: [Alerting](https://grafana.com/docs/grafana/latest/alerting/)

---

**পরের chapter:** Chapter 7 — Central logging, uptime check ও error tracking *(শীঘ্রই আসছে)*
