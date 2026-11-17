# Chapter 5 — Grafana Dashboard

> **Course:** Monitoring & Logging · **Chapter:** 5 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/monitoring/05-grafana-dashboards)

Prometheus (Chapter 4) প্রশ্নের **উত্তর খুঁজে দেয় আর data জমায়** চমৎকারভাবে, কিন্তু তার নিজের web পেজ একটা query-বাক্স, এমন কিছু নয় যা তুমি দলের জন্য TV-তে ঝুলিয়ে দেবে। **Grafana** সেই tool যা ওই সংখ্যাগুলোকে **dashboard**-এ বদলায়: chart, বড় status সংখ্যা আর রঙ-করা সতর্কতা, সবকিছু এক পর্দায়। Prometheus *জমায়*; Grafana *আঁকে*।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ **Grafana install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই এই chapter-এর ধাপগুলো Grafana-র সরকারি documentation থেকে লেখা এবং **চালানো হয়নি**; Grafana-র version ভেদে menu-র নাম বদলায়, তাই তোমার version-এর জন্য দেওয়া docs দেখো। যা *যাচাই করা হয়েছে*: Grafana-র নিজের ডিফল্ট setting (তাদের source repository থেকে পড়া), provisioning-এর ফরম্যাট, আর নিচের প্রতিটা YAML, `.ini` ও Caddyfile টুকরো যে বৈধ (tool দিয়ে parse করা; Caddyfile `caddy adapt` দিয়ে)। প্রতিটা বাইরের link খুলে দেখা হয়েছে। PromQL query গুলো Chapter 4-এর standard query আর চালানো হয়নি।

![একটা ভালো server dashboard (বানানো সংখ্যাসহ দৃষ্টান্ত): আগে উত্তর, মাঝখানে প্রবণতা, নিচে খুঁটিনাটি](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/07-monitoring/images/grafana-dashboard-layout.svg)

## ১. Grafana কী করে (আর কী করে না)

- Grafana **তোমার metric জমা করে না, রাখেও না।** সে একটা **data source**-এর (Prometheus, পরে log-এর জন্য Loki, এমনকি MySQL database) সাথে যুক্ত হয় আর তুমি dashboard খুললে তাকে প্রশ্ন করে।
- **Dashboard** হলো **panel** দিয়ে গড়া একটা পেজ। প্রতিটা panel হলো একটা query আর ফল আঁকার একটা উপায়।
- সে শুধু *পড়ে*, তাই তুমি কোনো data না হারিয়ে dashboard মুছে আবার বানাতে পারো। সংখ্যাগুলো Prometheus-এ নিরাপদেই থাকে।

## ২. Grafana পাওয়া (একটা পরিকল্পনা, এখানে চালানো হয়নি)

এই course-এর machine-এ `apt-cache policy grafana` কিছুই পায় না: Grafana Ubuntu-র নিজের package তালিকায় নেই। Grafana Labs নিজেদের **apt repository** (`apt.grafana.com`) দেয়, আর সরকারি [Debian/Ubuntu install পেজ](https://grafana.com/docs/grafana/latest/setup-grafana/installation/debian/) নির্ভুল, বর্তমান command দেয় (signing key ও repository যোগ করা)। কোনো প্রবন্ধ থেকে command কপি না করে ওই পেজ অনুসরণ করো, কারণ খুঁটিনাটি বদলায়। এটা Docker-এও চলে।

Grafana-র নিজস্ব ডিফল্ট configuration থেকে (তাদের repository-র `conf/defaults.ini`):

| Setting | ডিফল্ট | কেন গুরুত্বপূর্ণ |
|---|---|---|
| `http_port` | `3000` | web পেজ port 3000-এ |
| `admin_user` / `admin_password` | `admin` / `admin` | **প্রথম login-এ এটা বদলাও**, আর কিছুর আগে |
| `http_addr` | *খালি* | খালি মানে **সব network interface**। firewall না আটকালে port 3000 internet-এ খোলা থাকবে |
| `[auth.anonymous] enabled` | `false` | ভালো: login ছাড়া কেউ dashboard দেখতে পারে না |

## ৩. Grafana নিরাপদ রাখো

Dashboard দেখায় তোমার system কীভাবে চলে, তাই একে সংবেদনশীল ধরো।

1. **`admin`/`admin` password এখনই বদলাও**, প্রথম login-এ (Grafana সাধারণত এর জন্য একটা screen দেয়; এড়িয়ে গেলে profile থেকে বদলাও)।
2. **Port 3000 খোলা রেখো না।** Grafana-কে শুধু local machine-এ শুনতে দাও আর সামনে **Caddy** (বা Nginx) বসাও, যা **HTTPS**-ও দেয়:

```ini
# /etc/grafana/grafana.ini  (package install-এ প্রধান setting file)
[server]
http_addr = 127.0.0.1
http_port = 3000
domain = grafana.example.com
root_url = https://grafana.example.com/

[auth.anonymous]
enabled = false
```

```
# Caddyfile
grafana.example.com {
	reverse_proxy 127.0.0.1:3000
}
```

Caddy ওই নামের HTTPS certificate নিজে নেয় ও নবায়ন করে (DNS record তোমার server-এ দেখাতে হবে, Cloud course-এর মতো)। Prometheus (port 9090) আর exporter (9100)-ও **private** রাখো; শুধু Grafana, HTTPS ও login-এর আড়ালে, নাগালে থাকবে।

3. **Commit করা file-এ কখনো secret রেখো না।** Grafana-র admin password আর API token যাবে environment variable বা secret store-এ, Git-এ নয়।

## ৪. Grafana-কে Prometheus-এর সাথে যুক্ত করো

Web interface-এ তুমি একটা **data source** যোগ করো: সাম্প্রতিক version-এ এটা **Connections → Data sources → Add data source → Prometheus**-এ (version ভেদে শব্দ আলাদা)। **URL**-এ Prometheus যেখানে শোনে সেটা দাও, যেমন দুটোই একই machine-এ চললে `http://localhost:9090`, আর **Save & test** চাপো। সবুজ বার্তা মানে Grafana তাকে পাচ্ছে।

একই জিনিস একটা **file** হিসেবে (Grafana-র "provisioning" ফিচার startup-এ এটা পড়ে, তাই setup পুনরাবৃত্তিযোগ্য আর Git-এ রাখা যায়)। এই ফরম্যাট Prometheus data source-এর সরকারি documentation থেকে:

```yaml
# /etc/grafana/provisioning/datasources/prometheus.yml
apiVersion: 1
datasources:
  - name: Prometheus
    type: prometheus
    access: proxy
    url: http://localhost:9090
    isDefault: true
```

`access: proxy` মানে Grafana-র server Prometheus-কে request পাঠায় (তোমার browser কখনো সরাসরি Prometheus-এর সাথে কথা বলে না), এটাই Prometheus-কে private রাখে।

## ৫. আগে Explore, তারপর বানাও

Panel বানানোর আগে query-টা **Explore**-এ (কম্পাস আইকন) চেষ্টা করো। এটা একটা খসড়ার জায়গা: PromQL লেখো, graph দেখো, বদলাও। কোনো query তোমার চাওয়া জিনিস দেখালে তবেই সেটা panel হিসেবে সংরক্ষণ করো। PromQL শেখার এটাই সবচেয়ে দ্রুত উপায়, আর এতে তুমি এমন query দিয়ে dashboard ভরবে না যা কখনো নিজে দেখোনি।

## ৬. Panel-এর ধরন: যেটা প্রশ্নের উত্তর দেয় সেটা বেছে নাও

| Panel | কীসের জন্য সেরা | উদাহরণ |
|---|---|---|
| **Time series** | সময়ের সাথে কিছু **কীভাবে বদলেছে** | গত ৬ ঘণ্টায় CPU % |
| **Stat** | একটা বড় সংখ্যা, চাইলে রঙিন | site কি চালু? (1 বা 0), এখনকার memory % |
| **Gauge / Bar gauge** | একটা মান তার সীমার বিপরীতে | প্রতিটা mount point-এ disk ব্যবহার |
| **Table** | তুলনার জন্য নির্ভুল মান | শীর্ষ process, target ও তাদের `up`-এর তালিকা |
| **Heatmap** | সময়ের ওপর বণ্টন (histogram) | request-এর সময়ের bucket |
| **State timeline** | কখন কোন অবস্থায় ছিল | সারাদিন service up/down |
| **Logs** | log line (logs data source লাগে, Chapter 7) | সাম্প্রতিক error |

মোটামুটি নিয়ম: **"খারাপ হচ্ছে কি?"-র জন্য time series, "এখন ঠিক আছে কি?"-র জন্য stat বা gauge।**

## ৭. তোমার প্রথম dashboard: একটা server

শুরু করো **ছয়টা panel** দিয়ে। এগুলো Chapter 4-এর standard `node_exporter` query ব্যবহার করে। (প্রতিটা panel-এর **unit** ঠিক করো: `Percent (0-100)`, `bytes` ইত্যাদি, যাতে অক্ষ ঠিকভাবে পড়া যায়।)

| # | শিরোনাম | Panel | PromQL | Unit |
|---|---|---|---|---|
| 1 | Is it up? | Stat | `up{job="node"}` | নেই (map: 1 = UP, 0 = DOWN) |
| 2 | CPU used | Time series | `100 - (avg by (instance) (rate(node_cpu_seconds_total{mode="idle"}[$__rate_interval])) * 100)` | Percent (0-100) |
| 3 | Memory used | Gauge | `(1 - node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes) * 100` | Percent (0-100) |
| 4 | Disk used (/) | Gauge | `(1 - node_filesystem_avail_bytes{mountpoint="/"} / node_filesystem_size_bytes{mountpoint="/"}) * 100` | Percent (0-100) |
| 5 | Load per core | Time series | `node_load1 / on (instance) count by (instance) (node_cpu_seconds_total{mode="idle"})` | নেই |
| 6 | Network received | Time series | `rate(node_network_receive_bytes_total{device!="lo"}[$__rate_interval])` | bytes/sec |

Query নিয়ে কথা:

- **`$__rate_interval`** একটা Grafana variable। `[5m]` হাতে লেখার বদলে Grafana এমন জানালা বেছে নেয় যা scrape interval আর zoom স্তরের সাথে মানায়, তাই zoom in/out করলেও graph ঠিক থাকে। `rate()`-এর ভেতরে এটা ব্যবহার করো।
- **Load per core** (panel ৫) হলো Chapter 2-এর load average-কে core সংখ্যা দিয়ে ভাগ, তাই যেকোনো server-এর আকারে **1.0 মানে "ভরা"**। কাঁচা load ৬ মানে কিছুই না, যতক্ষণ না core সংখ্যা জানো। `on (instance)` অংশটা Prometheus-কে বলে দুই দিককে শুধু `instance` label ধরে মেলাতে: এটা ছাড়া বাঁ দিকে বাড়তি label (যেমন `job`) থাকে আর ভাগফল কিছুই ফেরত দিত না।
- **Disk** (panel ৪) root filesystem `/` দেখে। সব disk দেখতে `mountpoint="/"` filter সরিয়ে দাও (তারপর RAM-ভিত্তিকগুলো `fstype` দিয়ে বাদ দাও, Chapter 4-এর মতো)।
- Memory-তে **available** ধরা হয়েছে, ভরসার সংখ্যা (Chapter 2)।

### Threshold: রঙকেই পড়তে দাও

প্রতিটা panel-এ **threshold** ঠিক করা যায়: তুমি যে মান বেছে নাও সেখানে রঙ বদলায়। Chapter 2-এর সতর্কতার মাত্রা ব্যবহার করো, যেমন memory ও disk-এর জন্য: ৭০-এর নিচে **সবুজ**, ৭০ থেকে **হলুদ**, ৯০ থেকে **লাল**। রাত ২টায় ক্লান্ত মানুষ যেন একটা সংখ্যা পড়ার আগেই লাল বাক্স দেখতে পায়। ([thresholds-এর documentation](https://grafana.com/docs/grafana/latest/panels-visualizations/configure-thresholds/) দেখো।)

## ৮. Variable: অনেক server-এর জন্য একটাই dashboard

তিনটা server থাকলে dashboard তিনবার কপি করো না। একটা **variable** যোগ করো (**Dashboard settings → Variables**): পেজের ওপরে একটা drop-down। **Label values** query ধরনের একটা **Query** variable (label `instance`, metric `up`, আর label filter `job="node"`) প্রতিটা server দিয়ে ভরে যায়। পুরনো tutorial একই জিনিস পুরনো রূপে লেখে: `label_values(up{job="node"}, instance)`।

তারপর তোমার panel-এ variable ব্যবহার করো:

```
node_load1{instance="$instance"}
```

এখন একটা dashboard তুমি যে server বাছো সেটাই দেখায়। Variable নিয়ে Grafana-র [variables guide](https://grafana.com/docs/grafana/latest/dashboards/variables/) আছে।

## ৯. ভালো dashboard কীসে হয়

যে dashboard সবকিছু দেখায় সে কিছুই দেখায় না। কাজে লাগে এমন নিয়ম:

1. **আগে উত্তর।** ওপরের সারি পাঁচ সেকেন্ডে বলবে "ঠিক আছে কি?": up/down, error-এর হার, কয়েকটা বড় gauge। খুঁটিনাটি নিচে।
2. **কম panel।** মোটামুটি ৬ থেকে ১২টা। বেশি লাগলে অন্য প্রশ্নের জন্য (যেমন "database", "queue") আরেকটা dashboard বানাও আর link দাও।
3. **User যা অনুভব করে তা মাপো।** Service-এর জন্য **RED** পদ্ধতি (**R**ate, **E**rrors, **D**uration, Chapter 1-এর golden signal)। Machine-এর জন্য প্রতিটা resource-এ **USE** পদ্ধতি (**U**tilization, **S**aturation, **E**rrors)। resource-এ দেওয়া দুটো link দেখো।
4. **একটা unit আর রঙের একটা মানে।** সবসময় percent, সবসময় bytes। লাল সবসময় খারাপ; "বড় সংখ্যা"-র জন্য কখনো লাল নয়।
5. **একই সময়-পরিসর, একই অক্ষ।** পাশাপাশি থাকা panel এক নজরে তুলনীয় হতে হবে।
6. **Panel-এর নাম প্রশ্নের মতো।** "Disk used (%)" গল্প বলে; "Panel 4" বলে না। সংখ্যাটার প্রসঙ্গ দরকার হলে panel-এর নিচে ছোট বর্ণনা দাও।
7. **ঘটনা চিহ্নিত করো।** Annotation (প্রতিটা chart-এ একটা খাড়া রেখা) deploy আর restart-এর জন্য "deploy-র ঠিক পরেই ভেঙেছে" স্পষ্ট করে।

আর সীমাটা মনে রাখো: **কেউ তাকালে তবেই dashboard কাজে লাগে।** রাত ৩টায় কেউ তাকায় না। সেটা **alert**-এর কাজ (Chapter 6)।

## ১০. শূন্য থেকে শুরু করো না: তৈরি dashboard import করো

Community [grafana.com/grafana/dashboards](https://grafana.com/grafana/dashboards/)-এ dashboard ভাগ করে। `node_exporter`-এর জন্য খুব জনপ্রিয় একটা হলো **"Node Exporter Full"** (ID **1860**): CPU, memory, disk ও network-এর শত শত তৈরি panel। Grafana-য়: **Dashboards → New → Import**, ID লেখো, তোমার Prometheus data source বেছে নাও। ([Import guide](https://grafana.com/docs/grafana/latest/dashboards/build-dashboards/import-dashboards/)।)

দুটো সতর্কতা:

- এটা **তোমার দরকারের চেয়ে বেশি**। এটাকে খুঁজে দেখতে ব্যবহার করো, তারপর যে panel গুলো তোমার জন্য জরুরি সেগুলো দিয়ে নিজের ছোট "আগে উত্তর" dashboard বানাও।
- Import করা dashboard অন্য কারো লেখা JSON। বিশ্বাসযোগ্য উৎস থেকে নাও, আর ভরসা করার আগে দেখে নাও।

## ১১. Code হিসেবে dashboard

ক্লিক করে বানানো dashboard শুধু Grafana-র ভেতরে থাকে। server মরলে সেটা হারিয়ে যায়। ভালো উপায়: **JSON হিসেবে export করো** (share/export বোতাম), file-টা **Git**-এ রাখো (Git course-এ যেমন শিখেছ), আর **provisioning** দিয়ে Grafana-কে disk থেকে লোড করতে দাও। নিচের ফরম্যাট Grafana-র provisioning documentation থেকে:

```yaml
# /etc/grafana/provisioning/dashboards/myshop.yml
apiVersion: 1
providers:
  - name: "myshop"
    orgId: 1
    folder: "MyShop"
    type: file
    disableDeletion: false
    updateIntervalSeconds: 30
    allowUiUpdates: false
    options:
      path: /var/lib/grafana/dashboards
```

Grafana প্রতি `updateIntervalSeconds` পরপর ওই folder স্ক্যান করে আর যে dashboard JSON পায় লোড করে। `allowUiUpdates: false` থাকলে মানুষ browser-এ provisioned dashboard বদলে সংরক্ষণ করতে পারে না: বদলাতে file edit করে commit করতে হয়। এখন তোমার dashboard-ও যেকোনো code-এর মতো review করা, version করা আর পুনরাবৃত্তিযোগ্য।

## ১২. মূল কথা

- **Prometheus জমায়, Grafana আঁকে।** Grafana একটা **data source**-এর সাথে যুক্ত হয় আর পেজ খুললে তোমার query চালায়।
- বদলানোর ডিফল্ট: **port 3000 সব interface-এ খোলা** আর login **`admin`/`admin`**। Password বদলাও, `127.0.0.1`-এ bind করো, আর সামনে HTTPS (Caddy) বসাও।
- আগে **Explore**-এ query চেষ্টা করো। প্রশ্নের উত্তর দেয় এমন **panel ধরন** বাছো: প্রবণতায় time series, "এখন ঠিক কি?"-তে stat/gauge।
- `rate()`-এর ভেতরে **`$__rate_interval`** ব্যবহার করো, **unit** ঠিক করো, আর **threshold** দাও যাতে রঙ পড়ে দেয়।
- **Variable** একটা dashboard-কে অনেক server-এর কাজে লাগায়।
- ভালো dashboard: **আগে উত্তর, ৬ থেকে ১২টা panel, একটা unit, লাল মানে খারাপ**, panel-এর নাম প্রশ্নের মতো।
- শিখতে একটা community dashboard (যেমন ID 1860) import করো, তারপর নিজের ছোটটা বানাও।
- Dashboard **Git-এ code হিসেবে** রাখো (provisioning)। আর মনে রাখো: dashboard দেখার জন্য; **alert** তোমাকে জাগানোর জন্য।

## ১৩. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. Grafana কি তোমার metric জমা রাখে?</strong></summary>

না। Grafana Prometheus-এর মতো একটা data source-এর সাথে যুক্ত হয় আর dashboard খুললে query চালায়। Prometheus সংখ্যা জমায়; Grafana আঁকে।
</details>

<details>
<summary><strong>Q2. Grafana-র এমন দুটো ডিফল্টের নাম বলো যা বদলানো বা সীমিত করা উচিত, আর কীভাবে।</strong></summary>

ডিফল্ট login `admin`/`admin` (প্রথম login-এ password বদলাও), আর port 3000 সব interface-এ শোনা (`http_addr = 127.0.0.1` দাও, সামনে HTTPS-সহ Caddy বসাও, আর firewall-এ 3000 বন্ধ রাখো)।
</details>

<details>
<summary><strong>Q3. Data source file-এ `access: proxy`-এর সুবিধা কী?</strong></summary>

Grafana-র server Prometheus-কে request পাঠায়, তাই user-এর browser কখনো সরাসরি Prometheus-এর সাথে কথা বলে না আর Prometheus private থাকতে পারে।
</details>

<details>
<summary><strong>Q4. "গত ৬ ঘণ্টায় CPU কীভাবে বদলেছে"-র জন্য কোন panel ধরন মানায়, আর "site কি এখন চালু"-র জন্য কোনটা?</strong></summary>

CPU-র প্রবণতার জন্য time series panel, আর up/down অবস্থার জন্য stat panel।
</details>

<details>
<summary><strong>Q5. `[5m]`-এর বদলে `rate(...[$__rate_interval])` কেন লিখবে?</strong></summary>

Grafana এমন জানালা বেছে নেয় যা scrape interval আর zoom স্তরের সাথে মানায়, তাই সবকিছুর জন্য একটা স্থির জানালার বদলে zoom in/out করলেও graph ঠিক থাকে।
</details>

<details>
<summary><strong>Q6. Panel-এ load average-কে core সংখ্যা দিয়ে কেন ভাগ করবে?</strong></summary>

যাতে যেকোনো server-এর আকারে 1.0 মানে "ভরা"। কাঁচা load ৬ একটা ১২-core machine-এ ঠিক আছে আর ২-core-এ অতিরিক্ত বোঝা।
</details>

<details>
<summary><strong>Q7. Dashboard variable কীসের কাজে লাগে?</strong></summary>

এটা একটা drop-down যোগ করে (যেমন server-এর নাম), যাতে প্রতিটার জন্য dashboard কপি না করে একটা dashboard যেকোনো server দেখাতে পারে।
</details>

<details>
<summary><strong>Q8. ভালো dashboard-এর তিনটা নিয়ম বলো।</strong></summary>

যেমন: ওপরে আগে উত্তর; মোটামুটি ৬ থেকে ১২টা panel; একটা unit আর লালের একটাই মানে; panel-এর নাম প্রশ্নের মতো; service-এর জন্য RED আর machine-এর জন্য USE।
</details>

<details>
<summary><strong>Q9. Provisioning দিয়ে dashboard JSON file হিসেবে Git-এ কেন রাখবে?</strong></summary>

শুধু ক্লিকে বানানো dashboard Grafana-র ভেতরে থাকে আর server মরলে হারায়। Git-এর file version করা, review করা আর পুনরাবৃত্তিযোগ্য।
</details>

## ১৪. বিনামূল্যের resource

- Grafana docs: [Prometheus data source](https://grafana.com/docs/grafana/latest/datasources/prometheus/) ও [Debian/Ubuntu-তে install](https://grafana.com/docs/grafana/latest/setup-grafana/installation/debian/)
- [Configure Grafana](https://grafana.com/docs/grafana/latest/setup-grafana/configure-grafana/) (`http_addr`-সহ সব setting)
- [Visualizations](https://grafana.com/docs/grafana/latest/panels-visualizations/visualizations/) ও [Explore](https://grafana.com/docs/grafana/latest/explore/)
- [Dashboard best practices](https://grafana.com/docs/grafana/latest/dashboards/build-dashboards/best-practices/)
- [Variables](https://grafana.com/docs/grafana/latest/dashboards/variables/) ও [Provisioning](https://grafana.com/docs/grafana/latest/administration/provisioning/)
- Community dashboard: [Node Exporter Full (ID 1860)](https://grafana.com/grafana/dashboards/1860-node-exporter-full/)
- দুটো পদ্ধতি: [The RED Method](https://grafana.com/blog/2018/08/02/the-red-method-how-to-instrument-your-services/) আর Brendan Gregg-এর [USE Method](https://www.brendangregg.com/usemethod.html)
- Caddy: [`reverse_proxy`](https://caddyserver.com/docs/caddyfile/directives/reverse_proxy)

---

**পরের chapter:** [Chapter 6 — Alerting](06-alerting.md)
