# Chapter 3 — Application Log: Level, গঠন আর Rotation

> **Course:** Monitoring & Logging · **Chapter:** 3 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/monitoring/03-application-logs)

Chapter 2-এ তুমি **server-এর** log পড়েছ। কিন্তু সবচেয়ে কাজের log হলো যেটা **তোমার নিজের application লেখে**: *"Order 1042 ব্যর্থ: payment gateway timeout হয়েছে।"* কোনো tool এই বাক্য বানাতে পারে না। application তখন কী করছিল তা শুধু তোমার code জানে, তাই শুধু তোমার code-ই সেটা লিখে রাখতে পারে।

এই chapter শেখায় রাত ২টায় কাজে লাগে এমন log কীভাবে লিখবে, কীভাবে সেগুলো disk ভরে ফেলা থেকে আটকাবে, আর কীভাবে দ্রুত পড়বে। উদাহরণে **Laravel** (PHP) ব্যবহার করা হয়েছে, কিন্তু প্রতিটা ধারণা যেকোনো ভাষায় খাটে।

> **উদাহরণগুলো কীভাবে যাচাই করা হয়েছে:** Laravel-এর code আর তার output একটা আসল Laravel 13 project-এ চালানো হয়েছে (Monolog-এর standard formatter)। `logrotate` config যাচাই করা হয়েছে তার dry-run mode (`logrotate -d`) দিয়ে, যা কিছু বদলায় না। পুরনো Laravel version (১০ থেকে ১২) এখানকার সবকিছুতেই একই চলে, শুধু `Log::shareContext()` বাদে, যেটার জন্য Laravel ১১ বা নতুন লাগে। তোমার সময় আর path আলাদা হবে।

![Log level: শান্ত থেকে ভয়ের দিকে, আর একটা log line-এর জীবন](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/07-monitoring/images/log-levels-and-flow.svg)

## ১. Application log কেন?

Metric (Chapter 1) বলে **যে** কিছু ভুল: *১৪:০৫-এ error বেড়েছে।* Log বলে **কেন**: *কোন* request ব্যর্থ হলো, *কোন* user-এর, *কী* message নিয়ে। একটা ভালো log line তিনটা প্রশ্নের উত্তর দেয়:

- **কী ঘটেছে?** (স্পষ্ট একটা message)
- **কার / কোন জিনিসের?** (order id, user id)
- **কোথায় আর কখন?** (timestamp, channel, request id)

## ২. Log level

প্রতিটা log line-এর একটা **level** থাকে, যা বলে ঘটনা কতটা গুরুতর। এগুলো একটা পুরনো standard (RFC 5424) থেকে এসেছে আর প্রায় সব logging library ব্যবহার করে। শান্ত থেকে জোরালো ক্রমে:

| Level | Laravel method | কখন ব্যবহার করবে | উদাহরণ |
|---|---|---|---|
| `debug` | `Log::debug()` | developer-এর জন্য খুঁটিনাটি; production-এ বন্ধ | "Cart contents: ..." |
| `info` | `Log::info()` | স্বাভাবিক, উল্লেখযোগ্য ঘটনা | "Order 1042 placed" |
| `notice` | `Log::notice()` | স্বাভাবিক কিন্তু একটু অস্বাভাবিক | "User email বদলেছে" |
| `warning` | `Log::warning()` | কিছু একটা অদ্ভুত, তবে app সামলে নিয়েছে | "Slow query: 1830 ms" |
| `error` | `Log::error()` | একটা request বা job **ব্যর্থ** হয়েছে | "Payment failed" |
| `critical` | `Log::critical()` | বড় একটা অংশ ভেঙে গেছে | "Database পাওয়া যাচ্ছে না" |
| `alert` | `Log::alert()` | **এখনই** ব্যবস্থা নাও | "Disk full, লিখতে পারছে না" |
| `emergency` | `Log::emergency()` | system ব্যবহারের অযোগ্য | "App boot করতে পারছে না" |

Level একটা **filter**-ও। minimum level `warning` দিলে `debug` আর `info` line লেখার আগেই বাদ পড়ে যায়। তাই একটা setting-ই ঠিক করে দেয় log কতটা "কোলাহলপূর্ণ" হবে:

```bash
# .env
LOG_LEVEL=warning      # production: শান্ত রাখো
# LOG_LEVEL=debug      # local development: সবকিছু দেখো
```

সাধারণ শুরু: **production-এ `info` বা `warning`, আর `debug` শুধু কোনো সমস্যা খোঁজার সময়** (খোঁজা শেষে আবার ফিরিয়ে দিও, কারণ debug log বিশাল হয় আর সংবেদনশীল তথ্য থাকতে পারে)।

## ৩. কাজে লাগে এমন log line লেখা

দুটো line তুলনা করো:

```php
// ব্যবহার করা কঠিন: context নেই, খোঁজা যায় না
Log::error('Something went wrong');

// ব্যবহার করা সহজ: message স্থির, বিবরণ data আকারে
Log::error('Payment failed', ['order_id' => 1042, 'gateway' => 'bkash']);
```

নিয়মের মোটামুটি হিসাব:

1. **Message স্থির রাখো** আর বদলানো অংশ **context array**-তে দাও। তাহলে `Payment failed` message হুবহু খোঁজা যায়, আবার প্রতিটা order id-ও দেখা যায়।
2. **গুরুত্বপূর্ণ কিছুর সীমানায় log করো**: payment, login, job শুরু ও শেষ, অন্য service-কে call।
3. **ব্যর্থতার সময় exception-সহ log করো**, শুধু লেখা নয়। Laravel exception-এর class, message আর file ছাপে:

```php
try {
    $gateway->charge($order);
} catch (Throwable $e) {
    Log::error('Payment failed', ['order_id' => $order->id, 'exception' => $e]);
    throw $e;   // গিলে ফেলো না: app যেন ব্যর্থতা ঠিকভাবে সামলাতে পারে
}
```

4. **কখনো খালি `catch {}` রেখো না।** যে error কেউ log করে না, সেটা কেউ ঠিকও করতে পারে না।
5. ঘন ঘন চলা loop-এর ভেতরে log করো না (দশ লাখ লাইন `Processing row`)। সারসংক্ষেপ log করো: "৫০,০০০ row import হয়েছে ১২ সেকেন্ডে"।

### যা কখনো log করবে না

Log কপি হয়, অন্য tool-এ পাঠানো হয়, কয়েক সপ্তাহ থাকে, আর অনেকে পড়ে। **একে প্রায়-প্রকাশ্য ধরো।** কখনো লিখো না:

- **Password**, password-reset token, API key, `.env`-এর মান
- **পুরো card নম্বর**, CVV, bank-এর তথ্য
- **একবার-ব্যবহারের code (OTP)** আর session token
- বেশি পরিমাণ **ব্যক্তিগত তথ্য** (পুরো ঠিকানা, জাতীয় পরিচয়পত্র নম্বর)। মানুষের বিবরণের বদলে id (`user_id: 7`) log করো।

সংবেদনশীল দেখতে কিছু রাখতেই হলে **ঢেকে রাখো** (`01*******89`)। resource-এ দেওয়া OWASP logging cheat sheet-এ আরও আছে।

## ৪. Plain text বনাম structured (JSON) log

ডিফল্টভাবে Laravel প্রতিটা ঘটনার জন্য মানুষের পড়ার মতো একটা line লেখে:

```
[2026-11-16 08:33:26] production.INFO: Order placed {"order_id":1042,"user_id":7,"total":2500}
[2026-11-16 08:33:26] production.ERROR: Payment failed {"order_id":1042,"gateway":"bkash"}
```

`tail` দিয়ে নিজে পড়ার জন্য এটা ঠিক। কিন্তু যন্ত্রের পক্ষে এর ভেতরে *খোঁজা* কঠিন। **Structured log** প্রতিটা ঘটনা লেখে এক line-এ একটা **JSON object** হিসেবে:

```json
{"message":"Order placed","context":{"order_id":1042,"user_id":7,"total":2500},"level":200,"level_name":"INFO","channel":"production","datetime":"2026-11-16T08:33:26.886295+00:00","extra":{}}
{"message":"Slow query","context":{"ms":1830,"table":"orders"},"level":300,"level_name":"WARNING","channel":"production","datetime":"2026-11-16T08:33:26.886751+00:00","extra":{}}
```

প্রতিটা field-এর নাম আছে, তাই tool (আর পরে Chapter 7-এর Loki বা Elasticsearch-এর মতো কেন্দ্রীয় log system) `level_name`, `context.order_id` ইত্যাদি ধরে filter করতে পারে। আজই তুমি **`jq`** দিয়ে এটা করতে পারো, যা JSON-এর জন্য একটা বিনামূল্যের command-line tool:

```bash
# শুধু warning, আর শুধু দুটো field
jq -c 'select(.level_name=="WARNING") | {message, context}' storage/logs/app-json.log
# {"message":"Slow query","context":{"ms":1830,"table":"orders"}}

# message ধরে কয়টা করে error?
jq -r 'select(.level_name=="ERROR") | .message' storage/logs/app-json.log | sort | uniq -c | sort -rn
```

**ছোট project-এ, যেটা হাতে পড়ো, plain text ব্যবহার করো**; log কেন্দ্রীয় system-এ পাঠানো শুরু হলে **JSON-এ যাও**।

## ৫. Laravel-এর logging setup

সবকিছু `config/logging.php`-এ। **Channel** হলো log যাওয়ার একটা নামকরা জায়গা। ডিফল্ট channel ঠিক হয় `LOG_CHANNEL` দিয়ে:

| Channel | কোথায় লেখে | মন্তব্য |
|---|---|---|
| `single` | একটা file, `storage/logs/laravel.log` | সহজ, কিন্তু file **বাড়তেই থাকে** |
| `daily` | প্রতিদিন নতুন file, `laravel-2026-11-16.log` | শেষ `days` টা file রাখে (ডিফল্ট ১৪), পুরনোগুলো মোছে |
| `stderr` | standard error | container-এর জন্য: platform নিজে তুলে নেয় (Docker, Kubernetes) |
| `syslog`, `errorlog` | system log / PHP error log | `journalctl`-এর সাথে মেলে |
| `stack` | **একসাথে একাধিক channel** | সাধারণ ডিফল্ট; যেমন `LOG_STACK=single,slack` |

production-এর একটা ভালো শুরু:

```bash
# .env
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
```

নিজের file-এ **JSON** লিখতে `config/logging.php`-এ একটা channel যোগ করো:

```php
'channels' => [
    // ...
    'json' => [
        'driver'    => 'single',
        'path'      => storage_path('logs/app-json.log'),
        'level'     => env('LOG_LEVEL', 'debug'),
        'formatter' => Monolog\Formatter\JsonFormatter::class,
    ],
],
```

```php
Log::channel('json')->info('Order placed', ['order_id' => 1042]);
```

### প্রতিটা line-এ একটা request id

দশটা request একসাথে চললে তাদের log line মিশে যায়। প্রতিটা request-কে একটা id দাও আর সেটা **প্রতিটা** line-এ জুড়ে দাও, যাতে একটা request শুরু থেকে শেষ পর্যন্ত অনুসরণ করা যায়। একটা middleware-এ (`Log::shareContext`-এর জন্য Laravel ১১+ লাগে; পুরনো version-এ `Log::withContext` ব্যবহার করো):

```php
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

public function handle($request, Closure $next)
{
    $id = $request->header('X-Request-Id') ?: (string) Str::uuid();
    Log::shareContext(['request_id' => $id]);

    $response = $next($request);
    $response->headers->set('X-Request-Id', $id);   // user এটা support-কে বলতে পারবে
    return $response;
}
```

এখন প্রতিটা line-এ id আছে, error-এও:

```
[...] production.INFO: Checkout started {"request_id":"req-7f3a","cart":3}
[...] production.ERROR: Payment failed {"request_id":"req-7f3a","order_id":1042,"exception":"[object] (RuntimeException(code: 0): Gateway timeout at ...
```

`grep req-7f3a storage/logs/laravel.log` এখন ওই এক request-এর পুরো গল্প দেখায়।

## ৬. ধরা-না-পড়া error আপনা-আপনি log হয়

তোমার code এমন exception ছুড়লে যা কেউ ধরেনি, Laravel পুরো stack trace-সহ সেটা `error` level-এ log করে, আর user-কে একটা সাধারণ error page দেখায়। তাই একটাও `Log::` call না থাকলেও, কোনো user ৫০০ error জানালে `storage/logs/laravel.log`-ই **প্রথম দেখার জায়গা**। মনে রেখো production-এ `APP_DEBUG=false`, যাতে *user* কখনো trace না দেখে; *log* দেখে।

## ৭. দ্রুত log পড়া

```bash
tail -n 50 storage/logs/laravel.log              # শেষ ৫০ line
tail -f storage/logs/laravel.log                 # live অনুসরণ (Ctrl+C-তে থামে)
grep "production.ERROR" storage/logs/laravel.log | tail      # সাম্প্রতিক error
grep -c "production.ERROR" storage/logs/laravel-2026-11-16.log   # আজ কয়টা?
grep "order_id\":1042" storage/logs/*.log        # order 1042 নিয়ে সবকিছু
```

Stack trace `laravel.log`-কে লম্বা করে। প্রতিটা error-এর এক line-এর সারাংশ দেখতে শুধু সেই line গুলো দেখাও যেগুলো timestamp দিয়ে শুরু:

```bash
grep -E "^\[20[0-9-]+ [0-9:]+\] " storage/logs/laravel.log | cut -c1-200 | tail
```

### একটা খুব চেনা সমস্যা: `laravel.log`-এ "Permission denied"

একই log-এ প্রায়ই দুজন user লেখে: **web server-এর PHP** (`www-data`) আর **তুমি**, terminal থেকে `php artisan ...` চালিয়ে। যে আগে `laravel.log` বানায় সে-ই মালিক হয়ে যায়, আর অন্যজন তখন এমন error পায়: *"The stream or file laravel.log could not be opened in append mode: Permission denied"*। আসল error তখন এই error-এর আড়ালে চাপা পড়ে।

সমাধান হলো একটা **group** ভাগ করে দুজনকেই লিখতে দেওয়া, কখনো `chmod 777` দিয়ে নয়:

```bash
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R g+rwX storage bootstrap/cache
```

(তোমার deploy user আর `www-data` আলাদা হলে deploy user-কে `www-data` group-এ রাখাও ভালো।)

## ৮. Log rotation: log যেন disk না ভরে

যে log file কখনো পরিষ্কার হয় না সেটা একদিন disk ভরে ফেলবে, আর disk ভরা মানে outage (Chapter 2-এ `df -h` দেখেছ)। **Rotation** মানে: log পুরনো বা বড় হলে নাম বদলে দাও, compress করো, আর খুব পুরনোগুলো মুছে দাও।

- Laravel-এর **`daily`** channel নিজের লেখা file-এর জন্য এটা আগেই করে (`LOG_DAILY_DAYS` দিন রাখে)।
- **`single`** file আর **অন্য program-এর log**-এর জন্য Ubuntu-র **`logrotate`** করে। এটা `/etc/logrotate.d/` থেকে ছোট config file পড়ে আর দিনে একবার চলে।

`single` channel ব্যবহার করা Laravel app-এর একটা config (`/etc/logrotate.d/myshop` নামে রাখো):

```
/var/www/myshop/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
}
```

প্রতিটা line-এর মানে:

| Line | মানে |
|---|---|
| `daily` | দিনে একবার rotate |
| `rotate 14` | ১৪টা পুরনো file রাখো, তার বেশি মুছে দাও |
| `compress` | পুরনো file gzip দিয়ে compress |
| `delaycompress` | এক চক্র পরে compress, যাতে সবচেয়ে নতুন পুরনো file এখনো plain text থাকে |
| `missingok` | file না থাকলে অভিযোগ করো না |
| `notifempty` | খালি file বাদ |
| `copytruncate` | log কপি করে, তারপর আসলটা খালি করে। app **একই খোলা file**-এ লিখতে থাকে, তাই PHP restart করতে হয় না |

> **`copytruncate` বনাম `create`:** ডিফল্ট উপায় হলো file-এর *নাম বদলে* একটা নতুন খালি file `create` করা, কিন্তু চলমান program তাকে নতুন করে খুলতে না বলা পর্যন্ত পুরনো, নাম-বদলানো file-এই লিখতে থাকে। যে program নিজের log আবার খুলতে পারে না তাদের জন্য `copytruncate` এটা এড়ায়। ছোট অসুবিধা: কপি চলার সময় লেখা কয়েকটা line হারিয়ে যেতে পারে। সাধারণ web app-এর জন্য এটা মেনে নেওয়ার মতো।

**আগে নিরাপদে পরীক্ষা করো।** `-d` (debug) flag কী *হতো* তা ছাপে আর **কিছুই বদলায় না**:

```bash
sudo logrotate -d /etc/logrotate.d/myshop        # config পড়ো, পরিকল্পনা দেখো
sudo logrotate -d -f /etc/logrotate.d/myshop     # ধরে নাও এখনই সময় ("-f" = জোর করে)
```

জোর করা dry-run `log needs rotating` আর `renaming ...log.13.gz to ...log.14.gz`-এর মতো line ছাপে। ঠিক দেখালে তবেই দৈনিক timer-কে আসল কাজ করতে দাও (বা নিজে একবার `sudo logrotate -f` চালাও)।

**systemd journal**-এর নিজস্ব আকার-নিয়ন্ত্রণ আছে। দেখো, আর ছাঁটতে চাইলে **শুধু নিজে সিদ্ধান্ত নিলে** ছাঁটো (এটা পুরনো log entry মুছে দেয়):

```bash
journalctl --disk-usage
sudo journalctl --vacuum-time=14d      # ১৪ দিনের পুরনো journal entry মুছে দাও
```

## ৯. মূল কথা

- তোমার app-এর নিজের log বলে **কেন** কিছু ব্যর্থ হলো; server পারে না।
- **Level** ব্যবহার করো: স্বাভাবিক ঘটনায় `info`, অদ্ভুতে `warning`, ব্যর্থতায় `error`। minimum level একটা কোলাহল-filter (`LOG_LEVEL`)।
- **Message স্থির** রাখো, বিবরণ **context**-এ। **Exception** log করো। কখনো খালি `catch` নয়।
- Password, token, card নম্বর বা OTP **কখনো log করবে না**। Log প্রায়-প্রকাশ্য।
- **JSON log** যন্ত্র (আর `jq`) দিয়ে খোঁজা যায়। একটা request অনুসরণ করতে **request id** জোড়ো।
- Laravel: `LOG_CHANNEL=daily`, `LOG_LEVEL=warning`; ধরা-না-পড়া exception নিজে নিজে log হয়।
- `laravel.log`-এ "Permission denied" ঠিক করো ভাগ করা **group** দিয়ে, `chmod 777` নয়।
- Log **rotate** করো (`daily` channel বা `logrotate`) যাতে disk কখনো না ভরে, আর আগে `logrotate -d` দিয়ে পরীক্ষা করো।

## ১০. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. ব্যর্থ payment-এর জন্য সাধারণত কোন log level ব্যবহার করবে?</strong></summary>

`error`। একটা request বা job ব্যর্থ হয়েছে। `warning` হলো অদ্ভুত কিছুর জন্য যা app সামলে নিয়েছে, `critical` আর তার ওপরের level বড় ভাঙনের জন্য।
</details>

<details>
<summary><strong>Q2. Message স্থির রেখে order id কেন context array-তে রাখবে?</strong></summary>

তাহলে হুবহু message খোঁজা যায় আর তবু প্রতিটা order id দেখা যায়, আর log system `order_id` field ধরে filter করতে পারে। প্রতিবার বদলে যাওয়া message-কে group বা গোনা কঠিন।
</details>

<details>
<summary><strong>Q3. এর মধ্যে কোনটা কখনো log-এ লিখবে না?</strong></summary>

Password, API key/token, পুরো card নম্বর আর একবার-ব্যবহারের code। ব্যক্তিগত বিবরণের বদলে একটা id (যেমন `user_id`) log করো।
</details>

<details>
<summary><strong>Q4. Plain text-এর তুলনায় JSON (structured) log-এর সুবিধা কী?</strong></summary>

প্রতিটা field-এর নাম থাকে, তাই `jq`, Loki বা Elasticsearch-এর মতো tool লেখা আন্দাজ না করে field ধরে (যেমন `level_name` বা `context.order_id`) filter ও গুনতে পারে।
</details>

<details>
<summary><strong>Q5. `LOG_LEVEL=warning` কী করে?</strong></summary>

এটা লেখার minimum level ঠিক করে। `debug`, `info` আর `notice` line বাদ পড়ে; `warning` ও তার ওপরেরগুলো থাকে।
</details>

<details>
<summary><strong>Q6. প্রতিটা log line-এ request id কেন জুড়বে?</strong></summary>

অনেক request একসাথে চলে আর তাদের line মিশে যায়। ভাগ করা request id থাকলে তুমি সেটা `grep` করে একটা request-এর শুরু থেকে শেষের পুরো গল্প দেখতে পারো।
</details>

<details>
<summary><strong>Q7. তুমি নিজে `php artisan` চালানোর পর `laravel.log`-এ "Permission denied" আসে। সঠিক সমাধান কী?</strong></summary>

দুজন user (তুমি আর `www-data`) একই file-এ লেখে। একটা group ভাগ করে folder-কে group-লেখাযোগ্য করো (`chgrp -R www-data storage`, `chmod -R g+rwX storage`)। `chmod 777` ব্যবহার করো না।
</details>

<details>
<summary><strong>Q8. logrotate config-এ `copytruncate` কী করে, আর তার সুবিধা-অসুবিধা কী?</strong></summary>

এটা log কপি করে তারপর আসলটা খালি করে, ফলে চলমান app একই file-এ লিখতে থাকে আর restart লাগে না। অসুবিধা: কপির সময় লেখা কয়েকটা line হারিয়ে যেতে পারে।
</details>

<details>
<summary><strong>Q9. কোনো file না বদলিয়ে নতুন logrotate config কীভাবে যাচাই করবে?</strong></summary>

`logrotate -d <config>` চালাও (debug/dry-run)। এখনই rotation-এর সময় হলে কী হতো দেখতে `-f` জোড়ো।
</details>

## ১১. বিনামূল্যের resource

- Laravel docs: [Logging](https://laravel.com/docs/logging) (channel, level, context, exception)
- Monolog (Laravel-এর log-এর পেছনের library): [documentation](https://seldaek.github.io/monolog/)
- Level-এর standard: [RFC 5424, section 6.2.1 (Severity)](https://www.rfc-editor.org/rfc/rfc5424#section-6.2.1)
- [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html) (কী log করবে আর কী কখনো নয়)
- Manual: [`logrotate`](https://man7.org/linux/man-pages/man8/logrotate.8.html)
- [`jq` manual](https://jqlang.github.io/jq/manual/) (command line থেকে JSON query)
- The Twelve-Factor App: [Logs](https://12factor.net/logs) (log-কে ঘটনার প্রবাহ হিসেবে দেখা)

---

**পরের chapter:** Chapter 4 — Metric আর Prometheus *(শীঘ্রই আসছে)*
