# Chapter 8 — Real Project: একটা Laravel App Monitor করা

> **Course:** Monitoring & Logging · **Chapter:** 8 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/monitoring/08-real-project-monitoring-a-laravel-app)

এবার পুরো course একসাথে জোড়ার সময়। Cloud course-এ তুমি যে Laravel app deploy করেছিলে তাকে আমরা **চোখ আর কণ্ঠস্বর** দেব: সংখ্যা, একটা dashboard, log, exception, একটা বাইরের check আর এমন alert যা শুধু গুরুত্বপূর্ণ হলেই তোমাকে জাগায়।

![একটা Laravel server-এর পুরো monitoring stack](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/07-monitoring/images/laravel-monitoring-stack.svg)

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই chapter-এর **PHP code সত্যিই চালানো হয়েছে**, একদম নতুন একটা Laravel 13.40 project-এ (PHP 8.5, SQLite, PHP-র built-in web server): request-গোনা middleware, token-যাচাই-সহ `/metrics` endpoint, queue ও failed-job gauge, আর `/health` endpoint, ভাঙা database-সহ তার ব্যর্থতার ক্ষেত্রও। দেখানো output গুলো আসল। Caddyfile টুকরো `caddy adapt` দিয়ে যাচাই করা, আর প্রতিটা YAML টুকরো বৈধ। **Prometheus, Alertmanager, Grafana, `node_exporter`, Blackbox exporter, Loki, Alloy ও Sentry এই course লেখার server-এ install করা নেই**, তাই এদের configuration সরকারি documentation থেকে লেখা (Chapter 4 থেকে 7-এর মতো) এবং **চালানো হয়নি**। তোমার machine-এর জন্য নাম, path ও version মিলিয়ে নিও, আর পুরো শৃঙ্খল কাজ করছে প্রমাণ করতে ১১ নম্বর অংশের "fire drill" করো।

## ০. পরিকল্পনা: আমরা কী জানতে চাই?

Tool দিয়ে নয়, প্রশ্ন দিয়ে শুরু করো (Chapter 1)। একটা Laravel shop-এর জন্য আমরা জানতে চাই:

| প্রশ্ন | সংকেত | Tool (chapter) |
|---|---|---|
| দর্শক কি **এখনই** site খুলতে পারছে? | `probe_success` | বাইরে থেকে Blackbox (7) |
| App কি **সত্যিই সুস্থ** (database, cache)? | `/health` 200 দেয় | `/health`-এ Blackbox probe (7, 8) |
| ব্যবহারকারীরা কি **error** পাচ্ছে? | 5xx response-এর ভাগ | App metric (8) + Prometheus (4) |
| এটা কি **ধীর**? | গড় response-সময় | App metric (8) |
| **Background কাজ** কি আটকে আছে? | অপেক্ষমাণ job, ব্যর্থ job | App metric (8) |
| **Server** কি সুস্থ? | CPU, memory, disk | `node_exporter` (2, 4) |
| **ঠিক কী** ভেঙেছে? | exception + stack trace | Sentry (7) |
| ১৪:০২-এর কাছাকাছি **কী ঘটেছিল**? | structured log | Loki (3, 7) |
| **শুধু গুরুত্বপূর্ণ হলে** আমাকে জাগাও | alert rule | Alertmanager (6) |

প্রতিটা tool তুমি আগেই জানো। এই chapter-এর নতুন অংশ হলো "(8)"-এর প্রথম সারি: **তোমার নিজের app**-কে তার সংখ্যা জানাতে শেখানো।

## ১. ধাপ ১: কাজের log (Chapter 3, পাঁচ মিনিট)

এটা আগে করো, কারণ এতে কিছু খরচ হয় না। `.env`-এ:

```bash
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
```

Chapter 3-এর request-id middleware যোগ করো (`Log::shareContext(['request_id' => $id])`), যাতে একটা request-এর প্রতিটা লাইনে একই id থাকে, আর id-টা একটা `X-Request-Id` response header-এ দাও। কোনো গ্রাহক অভিযোগ করলে তুমি ওই id চাও আর সেটা `grep` করো (বা Loki-তে খোঁজো)।

## ২. ধাপ ২: একটা আসল health check

Laravel-এর (১১ ও নতুন) একটা route আগে থেকেই আছে, **`/up`**, যা `bootstrap/app.php`-এ ঠিক করা (`health: '/up'`)। Application কোনো exception ছাড়া **boot** হলে এটা 200 দেয়। এটা কাজের, কিন্তু এটা একটা *অগভীর* check, আর কতটা অগভীর তা আমরা ঠিক পরীক্ষা করেছি: ইচ্ছে করে database path ভেঙে দিলেও `/up` **তবু 200 দিয়েছে**। যে site-এর database বন্ধ সে সুস্থ নয়।

তাই একটা **গভীরতর** check যোগ করো যা app যা ছাড়া বাঁচে না তা ছুঁয়ে দেখে:

```php
<?php
// app/Http/Controllers/HealthController.php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke()
    {
        $checks = [];

        try {
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            report($e);
            $checks['database'] = 'fail';
        }

        try {
            Cache::put('health-check', 'ok', 10);
            $checks['cache'] = Cache::get('health-check') === 'ok' ? 'ok' : 'fail';
        } catch (\Throwable $e) {
            report($e);
            $checks['cache'] = 'fail';
        }

        $healthy = ! in_array('fail', $checks, true);

        // The word "fail" is in the body only when something is wrong,
        // so a probe can also check the body text (Chapter 7).
        return response()->json(['status' => $healthy ? 'ok' : 'fail'] + $checks, $healthy ? 200 : 503);
    }
}
```

যা চালানো হয়েছে: database ঠিক থাকলে `/health` `200` আর `{"status":"ok","database":"ok","cache":"ok"}` দিয়েছে। Database path ভাঙা অবস্থায় সে দিয়েছে **`503`** আর `{"status":"fail","database":"fail","cache":"fail"}` (এই project-এর cache-ও database-এ থাকে, তাই দুটোই ব্যর্থ), আর একই ভাঙা server-এ `/up` তবু `200` বলেছে।

দুটো নকশা-নিয়ম:

- **এটাকে সস্তা ও দ্রুত রাখো।** এটা প্রতি মিনিটে চলে। একটা `select 1`, একটা cache লেখা: হ্যাঁ। পুরো table স্ক্যান বা ধীর বাইরের API-তে call: না (একটা payment gateway ধীর হলে তোমার *নিজের* health check ব্যর্থ হওয়া উচিত নয়)।
- **সঠিক status code দাও।** Probe আগে status code (200 বনাম 503) দেখে বিচার করে; JSON মানুষের জন্য।

## ৩. ধাপ ৩: app-কে তার সংখ্যা জানাতে শেখাও

Prometheus (Chapter 4) metric-সহ একটা **text পাতা** পড়ে। তোমার Laravel app ওই পাতা পরিবেশন করতে পারে। এমন package আছে যা তোমার হয়ে এটা করে (Packagist-এ "Prometheus" খোঁজো আর সাম্প্রতিক release, download ও সমর্থিত Laravel version দেখে বিচার করো)। কিন্তু ফরম্যাট এত সরল যে নিজে ছোট একটা সংস্করণ লেখাই বোঝার সেরা উপায়, আর এতে কোনো dependency লাগে না। আমরা পাঁচটা সংখ্যা দেখাই:

| Metric | ধরন | কেন |
|---|---|---|
| `app_http_requests_total{status="..."}` | counter | Status code ধরে কত request: **error-এর হার** দেয় |
| `app_http_request_duration_seconds_sum` ও `_count` | counter | মোট সময় ও request-এর সংখ্যা: **গড় response-সময়** দেয় |
| `app_queue_jobs_waiting` | gauge | Queue-তে অপেক্ষমাণ job: **worker কি আটকে আছে?** |
| `app_queue_jobs_failed` | gauge | `failed_jobs`-এর সারি: **background কাজ কি ব্যর্থ হচ্ছে?** |

(Counter শুধু বাড়ে আর তুমি তার `rate()` graph করো; gauge ওঠানামা করে। Chapter 4 দেখো।)

### ৩ক. একটা middleware যা গোনে

```php
<?php
// app/Http/Middleware/CountRequests.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CountRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('metrics_start', hrtime(true));

        return $next($request);
    }

    // Runs AFTER the response was sent, so it never slows the visitor down.
    public function terminate(Request $request, Response $response): void
    {
        if ($request->is('metrics', 'up', 'health')) {
            return; // do not count the monitoring requests themselves
        }

        $ms = (int) ((hrtime(true) - $request->attributes->get('metrics_start', hrtime(true))) / 1_000_000);
        $status = $response->getStatusCode();

        // Cache::add sets the key to 0 only if it does not exist yet; increment then adds.
        Cache::add("metrics:http:$status", 0);
        Cache::increment("metrics:http:$status");
        Cache::add('metrics:http:ms', 0);
        Cache::increment('metrics:http:ms', $ms);
    }
}
```

`bootstrap/app.php`-এ **প্রতিটা** request-এর জন্য এটা register করো:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->append(\App\Http\Middleware\CountRequests::class);
})
```

`terminate()` কেন? এটা চলে response দর্শকের কাছে চলে যাওয়ার পরে, তাই গোনার জন্য তাকে কোনো খরচ দিতে হয় না। আমরা `/metrics`, `/up` ও `/health` বাদ দিই, যাতে monitoring নিজে সংখ্যা ফুলিয়ে না দেয়।

### ৩খ. token-সুরক্ষিত `/metrics` পাতা

```php
<?php
// app/Http/Controllers/MetricsController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class MetricsController extends Controller
{
    // Status codes we count. A short, fixed list keeps the label values bounded.
    private const STATUSES = [200, 201, 204, 301, 302, 304, 401, 403, 404, 419, 422, 429, 500, 502, 503];

    public function __invoke(Request $request)
    {
        $token = (string) config('services.metrics_token');

        // No token configured, or the wrong one: pretend the page does not exist.
        abort_unless($token !== '' && hash_equals($token, (string) $request->bearerToken()), 404);

        $lines = [];
        $lines[] = '# HELP app_http_requests_total Requests handled, by status code.';
        $lines[] = '# TYPE app_http_requests_total counter';
        $total = 0;
        foreach (self::STATUSES as $status) {
            $n = (int) Cache::get("metrics:http:$status", 0);
            $total += $n;
            $lines[] = "app_http_requests_total{status=\"$status\"} $n";
        }

        $lines[] = '# HELP app_http_request_duration_seconds_sum Total time spent handling requests.';
        $lines[] = '# TYPE app_http_request_duration_seconds_sum counter';
        $lines[] = 'app_http_request_duration_seconds_sum '.((int) Cache::get('metrics:http:ms', 0) / 1000);
        $lines[] = '# HELP app_http_request_duration_seconds_count Number of requests timed.';
        $lines[] = '# TYPE app_http_request_duration_seconds_count counter';
        $lines[] = "app_http_request_duration_seconds_count $total";

        $lines[] = '# HELP app_queue_jobs_waiting Jobs waiting in the default queue.';
        $lines[] = '# TYPE app_queue_jobs_waiting gauge';
        $lines[] = 'app_queue_jobs_waiting '.Queue::size();

        $lines[] = '# HELP app_queue_jobs_failed Jobs in the failed_jobs table.';
        $lines[] = '# TYPE app_queue_jobs_failed gauge';
        $lines[] = 'app_queue_jobs_failed '.DB::table('failed_jobs')->count();

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
```

Route গুলো। Monitoring request-এ session বা cookie লাগে না, তাই আমরা এগুলোকে `web` group থেকে বের করে নিই (প্রথমবার চালাতে `/metrics` অকারণে session cookie পাঠাচ্ছিল):

```php
// routes/web.php
Route::withoutMiddleware('web')->group(function () {
    Route::get('/health', \App\Http\Controllers\HealthController::class);
    Route::get('/metrics', \App\Http\Controllers\MetricsController::class);
});
```

আর token, configuration থেকে পড়া (কখনো code-এ লিখে দিও না):

```php
// config/services.php  (add one line inside the returned array)
'metrics_token' => env('METRICS_TOKEN'),
```

```bash
# .env  (never commit this file). Make a long random value, for example: openssl rand -hex 32
METRICS_TOKEN=put-a-long-random-value-here
```

**যা চালানো হয়েছে, আসল ফলসহ।** কয়েকবার visit-এর পরে (`/`-এ দুটো request আর একটা না-থাকা পাতায়), তারপর দুটো queued job আর `failed_jobs`-এ একটা সারি যোগ করে:

```
$ curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:18101/metrics                      # no token
404
$ curl -s -o /dev/null -w "%{http_code}\n" -H "Authorization: Bearer wrong" .../metrics         # wrong token
404
$ curl -s -H "Authorization: Bearer <the token>" .../metrics                                    # right token
# HELP app_http_requests_total Requests handled, by status code.
# TYPE app_http_requests_total counter
app_http_requests_total{status="200"} 2
app_http_requests_total{status="404"} 1
...
app_http_request_duration_seconds_sum 0.148
app_http_request_duration_seconds_count 3
...
app_queue_jobs_waiting 2
app_queue_jobs_failed 1
```

(শুধু অশূন্য লাইন দেখানো হয়েছে। মাঝখানে `/health`, `/up` ও `/metrics` call করা হয়েছিল আর নকশা অনুযায়ী সেগুলো গোনা **হয়নি**।)

### ৩গ. এই ছোট সংস্করণের সৎ সীমা

- **Counter থাকে cache-এ।** তুমি `php artisan cache:clear` চালালে (যেমন deploy script-এ, Cloud course) সেগুলো **শূন্যে ফিরে যায়**। সমস্যা নেই: Chapter 4-এ দেখেছ, Prometheus-এর `rate()` যে counter আবার শুরু হয় তা সামলাতে পারে।
- **দ্রুত cache ব্যবহার করো।** `database` cache store দিয়ে (আমাদের test project-এর মতো) প্রতিটা request কয়েকটা ছোট লেখা যোগ করে। Production-এ Redis থাকলে সেটা ব্যবহার করো; `Cache::increment` এর জন্যই বানানো।
- **একটা server।** Load balancer-এর পেছনে কয়েকটা web server চালালে প্রতিটার নিজের counter (local cache) থাকবে আর Prometheus প্রতিটাকে scrape করবে। একটা shared cache সব server-এর request একসাথে গুনবে আর কয়েকবার scrape হবে। ওই আকারে পৌঁছালে তৈরি package বা OpenTelemetry-র মতো agent বাড়তি জটিলতার যোগ্য।
- **Queue gauge** `Queue::size()` তোমার ডিফল্ট connection-এর ডিফল্ট queue-র জন্য। অন্য queue একইভাবে যোগ করো।

### ৩ঘ. `/metrics` private রাখো (দুটো তালা)

Metric ট্রাফিক, error আর queue-র আকার প্রকাশ করে। আমরা **দুটো তালা** ব্যবহার করি: token (ওপরে), আর Caddy-তে একটা rule যাতে শুধু monitoring server-ই পথটা ধরতে পারে। এই Caddyfile `caddy adapt` দিয়ে যাচাই করা (203.0.113.10 একটা সংরক্ষিত documentation ঠিকানা; তোমার monitoring server-এর ঠিকানা দাও):

```
shop.example.com {
	# /metrics is reachable only from the monitoring server
	@metrics_outside {
		path /metrics
		not remote_ip 203.0.113.10
	}
	respond @metrics_outside 404

	root * /var/www/shop/public
	php_fastcgi unix//run/php/php8.3-fpm.sock
	encode gzip
	file_server
}
```

Prometheus যদি site-এর **একই** server-এ চলে, তার তোমার public নামে করা request ওই server-এর নিজের ঠিকানা থেকে আসে, তাই `remote_ip`-এ সেই ঠিকানা দাও (access log উৎস হিসেবে কী দেখায় তা দেখে নিও)।

## ৪. ধাপ ৪: server (Chapter 2 ও 4)

Server-এ `node_exporter` install করো (Chapter 4 দেখো) যাতে CPU, memory, disk ও network মাপা হয়। আগের মতো তার port 9100 **private** রাখো (firewall)।

## ৫. ধাপ ৫: Prometheus সবকিছু scrape করে

সব job একসাথে, `prometheus.yml`-এ। নতুনটা `laravel`। Prometheus `authorization` block দিয়ে প্রতিটা scrape-এ token পাঠাতে পারে, আর token **file** থেকে পড়লে (`credentials_file`) সেটা config-এর বাইরে থাকে:

```yaml
# /etc/prometheus/prometheus.yml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

rule_files:
  - "rules/*.yml"

alerting:
  alertmanagers:
    - static_configs:
        - targets: ["localhost:9093"]

scrape_configs:
  - job_name: node
    static_configs:
      - targets: ["localhost:9100"]

  - job_name: laravel
    metrics_path: /metrics
    scheme: https
    authorization:
      type: Bearer
      credentials_file: /etc/prometheus/laravel_metrics_token
    static_configs:
      - targets: ["shop.example.com"]

  - job_name: blackbox
    metrics_path: /probe
    params:
      module: [http_2xx]
    static_configs:
      - targets:
          - https://shop.example.com
          - https://shop.example.com/health
    relabel_configs:
      - source_labels: [__address__]
        target_label: __param_target
      - source_labels: [__param_target]
        target_label: instance
      - target_label: __address__
        replacement: 127.0.0.1:9115
```

Token-টা ওই file-এ রাখো (`chmod 600`, Prometheus-এর user-এর মালিকানায়) আর Git-এ **নয়**। তারপর reload-এর আগে file যাচাই করো, Chapter 6-এর মতো: `promtool check config /etc/prometheus/prometheus.yml`। Prometheus-এর **Status → Targets** পাতায় সব job **UP** দেখাবে।

`/health`-ও probe হয় বলে, app 503 দিলেই ওই URL-এর `probe_success` 0 হয়: "database বন্ধ" বাইরে থেকে বিনা খরচে ধরা পড়ে। (তুমি `fail_if_body_matches_regexp: ["fail"]` দিয়ে আরও কড়া একটা Blackbox module লিখতে পারো, Chapter 7-এর মতো।)

## ৬. ধাপ ৬: app-এর জন্য alert rule

Chapter 6-এর দর্শন কাজে লাগাও: **আগে লক্ষণ, কম alert, ঝলকানি এড়াতে `for`**, আর প্রতিটায় একটা runbook নোট।

```yaml
# /etc/prometheus/rules/laravel.yml
groups:
  - name: laravel-app
    rules:
      # Symptom, critical: visitors cannot open the site (or the health check fails).
      - alert: SiteDown
        expr: probe_success{job="blackbox"} == 0
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "{{ $labels.instance }} is not reachable or unhealthy"
          runbook: "Check the web server and PHP-FPM, then the database. See /health."

      # Symptom, critical: more than 5% of requests end in a server error.
      - alert: HighErrorRate
        expr: sum(rate(app_http_requests_total{status=~"5.."}[5m])) / sum(rate(app_http_requests_total[5m])) * 100 > 5
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "{{ $value | printf \"%.1f\" }}% of requests are failing with a server error"
          runbook: "Open Sentry for the new issue, then search Loki for ERROR around the start time."

      # Symptom, warning: pages are slow (average above one second for 10 minutes).
      - alert: SlowResponses
        expr: rate(app_http_request_duration_seconds_sum[5m]) / rate(app_http_request_duration_seconds_count[5m]) > 1
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "Average response time is {{ $value | printf \"%.2f\" }} s"

      # Warning: background work is piling up (is the queue worker running?).
      - alert: QueueBacklog
        expr: app_queue_jobs_waiting > 100
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "{{ $value }} jobs are waiting in the queue"
          runbook: "Check that the queue worker is running (systemctl status, see the Cloud course)."

      # Warning: jobs have failed and nobody has looked.
      - alert: FailedJobs
        expr: app_queue_jobs_failed > 0
        for: 15m
        labels:
          severity: warning
        annotations:
          summary: "{{ $value }} failed jobs: run php artisan queue:failed"

      # Warning: the HTTPS certificate is close to expiry (Chapter 7).
      - alert: CertificateExpiresSoon
        expr: probe_ssl_earliest_cert_expiry{job="blackbox"} - time() < 14 * 24 * 3600
        for: 1h
        labels:
          severity: warning
        annotations:
          summary: "The certificate of {{ $labels.instance }} expires in less than 14 days"
```

Chapter 6-এর পাঁচটা server rule-এর (`InstanceDown`, `DiskAlmostFull`, `DiskFullWithin24h`, `MemoryLow`, `HighLoad`) সাথে মিলিয়ে এটা একটা সম্পূর্ণ, ছোট, *লক্ষণ-আগে* সেট। নোট:

- **ব্যবহারকারী-সংক্রান্ত দুটো critical** (`SiteDown`, `HighErrorRate`)। কারণ-সংক্রান্ত সবকিছু (disk, memory, queue) warning, Chapter 6-এ যেমন শেখানো হয়েছে।
- **কোনো traffic না থাকলে** error-হারের ভাগ কোনো data ফেরত দেয় না (কিছু-কে-কিছু দিয়ে ভাগ), তাই নিঝুম রাত তোমাকে page করে না। তোমার `SiteDown` তবু বাইরে থেকে নজর রাখে।
- প্রতিটা rule `promtool test rules` দিয়ে পরীক্ষা করো, Chapter 6-এর মতো। File গুলো Git-এ রাখো (secret ছাড়া)।

Chapter 6-এর Alertmanager routing মনে রাখো: `severity="critical"` জোরালো channel-এ যায়, warning শান্ত channel-এ।

## ৭. ধাপ ৭: Loki-তে log (Chapter 7)

Server-এ Chapter 7-এর config দিয়ে Alloy চালাও, যা `/var/www/shop/storage/logs/*.log`-এর দিকে তাক করা, আর শুধু `job="laravel"` ও `env="production"` label-সহ। তারপর Grafana-র **Explore**-এ:

```
{job="laravel"} |= "ERROR"
sum(rate({job="laravel"} |= "ERROR" [1m]))
```

আর কোনো গ্রাহকের `request_id` খোঁজো line filter দিয়ে (`|= "req-7f3a"`), label হিসেবে **নয়**।

## ৮. ধাপ ৮: Sentry-তে exception (Chapter 7)

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish
php artisan sentry:test
```

`bootstrap/app.php`-এ `Integration::handles($exceptions);` আর `.env`-এ DSN (`SENTRY_LARAVEL_DSN`)-সহ। Environment-এর নাম `production` দাও, trace sample rate কম রাখো, আর শুধু **নতুন** ও **ফিরে-আসা** issue-তে জানাও।

## ৯. ধাপ ৯: একটা dashboard, "আগে উত্তর" (Chapter 5)

"Shop: is it OK?" নামে **একটা** dashboard বানাও। ওপরের সারি = পাঁচ সেকেন্ডের উত্তর; খুঁটিনাটি নিচে। নয়টা panel যথেষ্ট:

| সারি | Panel | ধরন | Query (PromQL, অন্যথা বলা না থাকলে) |
|---|---|---|---|
| **উত্তর** | Site reachable | Stat (1 = UP) | `probe_success{instance="https://shop.example.com"}` |
| | Health check | Stat (1 = OK) | `probe_success{instance="https://shop.example.com/health"}` |
| | Error rate % | Stat, ৫-এর ওপরে লাল | `sum(rate(app_http_requests_total{status=~"5.."}[$__rate_interval])) / sum(rate(app_http_requests_total[$__rate_interval])) * 100` |
| | Cert days left | Stat, ১৪-র নিচে লাল | `(probe_ssl_earliest_cert_expiry{job="blackbox"} - time()) / 86400` |
| **প্রবণতা** | Status ধরে প্রতি সেকেন্ডে request | Time series | `sum by (status) (rate(app_http_requests_total[$__rate_interval]))` |
| | গড় response-সময় (s) | Time series | `rate(app_http_request_duration_seconds_sum[$__rate_interval]) / rate(app_http_request_duration_seconds_count[$__rate_interval])` |
| | Queue: waiting ও failed | Time series | `app_queue_jobs_waiting` ও `app_queue_jobs_failed` |
| **Server** | CPU, memory, disk % | Gauge | Chapter 5-এর তিনটা query |
| **Log** | সাম্প্রতিক error | Logs (Loki) | `{job="laravel"} \|= "ERROR"` |

এটা JSON হিসেবে export করো আর provisioning দিয়ে Git-এ রাখো (Chapter 5)। Deploy-এর জন্য **annotation** যোগ করো, যাতে "deploy-এর ঠিক পরে ভাঙল" চোখে পড়ে।

## ১০. Monitoring নিজে monitor দরকার হলে?

Chapter 6-এর শেষ সতর্কতা প্রয়োগ করো। এই সরল setup-এ Prometheus আর Alertmanager একই server-এ চলে, তাই **বাইরের** check অপরিহার্য: হয় Blackbox (আর আদর্শভাবে Prometheus-ও) **দ্বিতীয় একটা machine**-এ চালাও, নয়তো `https://shop.example.com/health`-এ নজর রাখা একটা বিনামূল্যের বাইরের uptime service (বা অন্য machine-এ Uptime Kuma) যোগ করো। **ইচ্ছে করে কিছু ভাঙলে** (পরের অংশ) তোমার ফোনে যে test বার্তা আসে তা যেকোনো পরিমাণ configuration-এর চেয়ে বেশি মূল্যবান।

## ১১. Fire drill: প্রমাণ করো এটা কাজ করে

যে monitoring-কে কখনো প্রতিক্রিয়া করতে দেখোনি সেটা অনুমান। একটা **test বা staging কপিতে** (ব্যবসার সময় live shop-এ **নয়**) এগুলো করো আর যা দেখলে লিখে রাখো:

| এটা ভাঙো | যা দেখার আশা | কত সময়ে |
|---|---|---|
| PHP-FPM বন্ধ করো (`systemctl stop php8.3-fpm`) | `SiteDown` pending, তারপর firing, chat-এ বার্তা; dashboard-এর stat লাল | প্রায় ৩ মিনিট |
| Database-কে অগম্য করো | `/health` 503 দেয়; `SiteDown` fire করে (`/health`-এ probe) | প্রায় ৩ মিনিট |
| একটা route-এ exception ছোঁড়ো | Sentry-তে একটা **নতুন issue**; Loki-তে error লাইন | কয়েক সেকেন্ড |
| অনেক request-এ error ফেরত দাও (সবসময় ব্যর্থ হওয়া route, loop-এ call করা) | প্রায় ৫ মিনিট পরে `HighErrorRate` fire করে | প্রায় ৬ মিনিট |
| Queue worker বন্ধ করো, ১৫০টা job dispatch করো | ১০ মিনিট পরে `QueueBacklog` | প্রায় ১১ মিনিট |
| Monitoring নিজেকেই `systemctl stop` করো | **বাইরের** check তোমাকে জানায় | তোমার setup অনুযায়ী |

তারপর **সবকিছু আবার চালু করো** আর নিশ্চিত হও প্রতিটা alert **resolve** হয় আর "resolved" বার্তা পাও। শেষে একটা **runbook** লেখো: প্রতিটা alert-এর জন্য তিন লাইন (এর মানে কী, আগে কী দেখবে, কাকে ডাকবে)। ওপরের `runbook` annotation-এ তুমি এর link দাও।

## ১২. মূল শিক্ষা

- **প্রশ্ন** দিয়ে শুরু করো; এই course-এর প্রতিটা tool একটা প্রশ্নের উত্তর দেয়: Prometheus (সংখ্যা), Grafana (ছবি), Loki (log-এর লেখা), Sentry (exception), Blackbox (বাইরের দৃষ্টি), Alertmanager (কাকে জানানো হবে)।
- **Laravel-এর `/up` অগভীর**: database ভাঙা অবস্থায়ও সে 200 বলেছে। একটা **গভীরতর `/health`** যোগ করো যা database ও cache ছোঁয়, ব্যর্থ হলে **503** দেয় আর সস্তা থাকে।
- তোমার app **নিজের metric** Prometheus-এর text ফরম্যাটে দিতে পারে: একটা middleware `terminate()`-এ (response-এর পরে) request গোনে, একটা token-সুরক্ষিত `/metrics` counter ও queue gauge ছাপে। Label **কম ও সীমিত** রাখো।
- **`/metrics` দুবার সুরক্ষিত করো** (একটা গোপন token, আর Caddy-র একটা rule যা পথটা monitoring server-এ সীমিত করে)। Token থাকবে `.env` বা file-এ, Git-এ নয়।
- **ব্যবহারকারী-দৃশ্যমান লক্ষণের** (site বন্ধ, error-হার) alert দাও, resource সমস্যাকে **warning** রাখো, প্রতিটা alert-এ **runbook** নোট দাও।
- একটা **আগে-উত্তর dashboard**, Git-এ code হিসেবে।
- Cache-এর counter `cache:clear`-এ শূন্য হয়: `rate()` সামলে নেয়। Redis ব্যবহার করো; নকশা এক-server-এর; বড় setup-এর জন্য package আছে।
- **Fire drill চালাও।** Staging-এ ইচ্ছে করে জিনিস ভাঙো আর প্রতিটা alert **fire ও resolve** দুটোই দেখো।

## ১৩. নিজেকে পরীক্ষা করো (exam-এর প্রস্তুতি)

<details>
<summary><strong>Q1. Laravel-এর `/up` route কেন health check হিসেবে যথেষ্ট নয়?</strong></summary>

এটা শুধু দেখায় application boot হয়। আমাদের test-এ database ভাঙা অবস্থায়ও সে 200 দিয়েছে। গভীরতর health route-এর উচিত database ও cache ছোঁয়া আর কিছু ব্যর্থ হলে 503 দেওয়া।
</details>

<details>
<summary><strong>Q2. `handle()`-এর বদলে `terminate()`-এ request গোনা হয় কেন?</strong></summary>

`terminate()` চলে response দর্শকের কাছে পাঠানো হয়ে যাওয়ার পরে, তাই গোনার জন্য তাকে অপেক্ষা করতে হয় না।
</details>

<details>
<summary><strong>Q3. গোনার সময় code কেন `/metrics`, `/up` ও `/health` বাদ দেয়?</strong></summary>

যাতে monitoring-এর নিজের request (প্রতি কয়েক সেকেন্ডে) request-সংখ্যা ফুলিয়ে error-হার আর traffic-এর graph বিকৃত না করে।
</details>

<details>
<summary><strong>Q4. `/metrics` কীভাবে সুরক্ষিত, আর ভুল token দিলে কী পাওয়া যায়?</strong></summary>

`.env`-এর একটা গোপন token দিয়ে, যা bearer token-এর সাথে `hash_equals` দিয়ে মেলানো হয়, আর একটা web-server rule দিয়ে যা শুধু monitoring server-কে পথটা ধরতে দেয়। ভুল বা অনুপস্থিত token 404 পায়, যেন পাতাটাই নেই।
</details>

<details>
<summary><strong>Q5. Metrics controller-এ status-code-এর তালিকা নির্দিষ্ট কেন, যা-ই ঘটুক তা ব্যবহার না করে?</strong></summary>

Label-এর মানের সংখ্যা ছোট ও সীমিত রাখতে (Chapter 4 ও 7-এর cardinality নিয়ম), যাতে metric সীমাহীনভাবে বাড়তে না পারে।
</details>

<details>
<summary><strong>Q6. `php artisan cache:clear` চালালে counter-এর কী হয়, আর সেটা গ্রহণযোগ্য কেন?</strong></summary>

সেগুলো শূন্য থেকে আবার শুরু হয়। Prometheus-এর `rate()` যে counter reset হয় তা সামলাতে পারে, তাই graph আর alert ঠিক থাকে।
</details>

<details>
<summary><strong>Q7. এই chapter-এর কোন alert গুলো `critical`, আর শুধু সেগুলোই কেন?</strong></summary>

`SiteDown` ও `HighErrorRate`: এরা বর্ণনা করে ব্যবহারকারী এখনই যা টের পাচ্ছে। ধীর response, queue জমে যাওয়া, ব্যর্থ job আর certificate-এর মেয়াদের মতো কারণগুলো warning, যা কাজের সময় পর্যন্ত অপেক্ষা করতে পারে।
</details>

<details>
<summary><strong>Q8. Config-এ না লিখে Prometheus-কে `/metrics`-এর token কীভাবে দেবে?</strong></summary>

Scrape job-এর `authorization` block-এ `credentials_file` ব্যবহার করো, যা token-সহ একটা file-এর দিকে নির্দেশ করে (permission 600)।
</details>

<details>
<summary><strong>Q9. "Fire drill" কেন চালাবে, আর alert fire হওয়া ছাড়া আর কী যাচাই করবে?</strong></summary>

যে monitoring-কে কখনো প্রতিক্রিয়া করতে দেখোনি তাকে ভরসা করা যায় না। Staging-এ ইচ্ছে করে জিনিস ভাঙো, নিশ্চিত হও প্রতিটা alert fire করে ও বার্তা পৌঁছায়, তারপর নিশ্চিত হও সেটা resolve হয়, আর প্রতিটা alert-এর জন্য runbook লেখো।
</details>

<details>
<summary><strong>Q10. এই setup-এ কিছু check কেন server-এর বাইরে থেকে আসতে হবে?</strong></summary>

Prometheus ও Alertmanager একই server-এ চলে, তাই পুরো machine বা তার network মরলে তারাও মরে আর কিছু পাঠায় না। বাইরের check (দ্বিতীয় machine বা বাইরের uptime service) ওই নীরবতা টের পায়।
</details>

## ১৪. বিনামূল্যের resource

- Laravel docs: [Deployment](https://laravel.com/docs/13.x/deployment), [Queues](https://laravel.com/docs/13.x/queues) ও [Logging](https://laravel.com/docs/13.x/logging)
- Prometheus: [Exposition format (text পাতা)](https://prometheus.io/docs/instrumenting/exposition_formats/), [scrape configuration](https://prometheus.io/docs/prometheus/latest/configuration/configuration/) (`authorization`, `credentials_file`)
- [Blackbox exporter](https://github.com/prometheus/blackbox_exporter), [Alerting best practices](https://prometheus.io/docs/practices/alerting/)
- Caddy: [`remote_ip` matcher](https://caddyserver.com/docs/caddyfile/matchers#remote-ip) ও [`php_fastcgi`](https://caddyserver.com/docs/caddyfile/directives/php_fastcgi)
- [Laravel-এর জন্য Sentry](https://docs.sentry.io/platforms/php/guides/laravel/)
- Grafana, Loki ও Alertmanager-এর জন্য এই course-এর আগের chapter গুলো

---

**পরের ধাপ:** Monitoring & Logging-এর final exam, website-এ, আটটা chapter quiz পাস করার পরে।
