# Chapter 5 — Processes আর Services (systemd)

> **Course:** Linux & Shell Basics · **Chapter:** 5 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-shell/05-processes-and-services)

তুমি যা-ই চালাও — একটা script, একটা web server, একটা database — চালু হওয়ার সাথে সাথে এটা একটা **process**-এ পরিণত হয়। এই chapter হলো মেশিনে আসলে কী চলছে দেখা, সেটা নিরাপদে (বা অনিরাপদভাবে) বন্ধ করা, আর `systemd` বোঝা — যেই জিনিসটা নিঃশব্দে real server (এই course হোস্ট করা সার্ভার সহ) চালু রাখে।

## ১. একটা process আসলে কী

একটা process হলো একটা program-এর একটা running instance, এবং প্রতিটা চালু হওয়ার সাথে সাথে একটা unique number পায়: তার **PID** (Process ID)। একটা program একই সাথে একাধিক আলাদা process হিসেবে চলতে পারে (দুটো terminal tab খুলে একই script চালালে, তোমার কাছে দুটো আলাদা PID থাকবে)।

```bash
ps aux
```
মেশিনে এখন চলা প্রতিটা process list করে। জানার মতো column:

| Column | মানে |
|---|---|
| `PID` | Process-এর unique ID |
| `%CPU` / `%MEM` | এটা এখন কতটা CPU/memory ব্যবহার করছে |
| `STAT` | তার state (`S` sleeping, `R` running, `Z` একটা "zombie" — শেষ হয়ে গেছে কিন্তু এখনো পরিষ্কার হয়নি) |
| `COMMAND` | যেই actual command এটা চালু করেছে |

```bash
top
```
বা, install করা থাকলে:
```bash
htop
```
`ps aux`-এর একটা live, auto-refreshing version — এখন কী CPU/memory সবচেয়ে বেশি খাচ্ছে বোঝার সবচেয়ে দ্রুত উপায়।

## ২. একটা process বন্ধ করা — এবং আসল গুরুত্বপূর্ণ পার্থক্য

```bash
kill 12345
```
একটা **SIGTERM** পাঠায় — বন্ধ হওয়ার একটা *ভদ্র request*। একটা ভালোভাবে লেখা program এই signal ধরতে পারে, যা করছিল শেষ করে (data save করা, file বন্ধ করা, পরিষ্কারভাবে disconnect করা), আর নিজে নিজে exit করে।

```bash
kill -9 12345
```
একটা **SIGKILL** পাঠায় — request না, একটা instant termination। Process কিছু পরিষ্কার করার কোনো সুযোগই পায় না।

![SIGTERM vs SIGKILL diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/02-linux-shell/images/sigterm-vs-sigkill.svg)

এটা পরিচিত লাগলে, ঠিক লাগারই কথা: এটা ঠিক Docker course-এর `docker stop` (SIGTERM, graceful) vs `docker kill`/forced removal-এর মতোই, শুধু container level-এর বদলে operating-system level-এ। **সবসময় প্রথমে plain `kill` try করো।** `kill -9`-এ যাও শুধু তখন যখন একটা process আসলেই stuck এবং ভদ্র request ignore করছে — এটা তোমার শেষ উপায় হওয়া উচিত, প্রথম move না, কারণ process যদি মাঝপথে কিছু লিখছিল তাহলে data হারিয়ে যেতে পারে।

```bash
killall nginx
```
একই নামের সব process একসাথে kill করে — useful, কিন্তু এটা চালানোর আগে নিশ্চিত হও এটাই তুমি আসলে চাও।

## ৩. Foreground, background, আর jobs

```bash
long-running-script.sh        # foreground-এ চলে — script শেষ না হওয়া পর্যন্ত তোমার terminal আটকে থাকে
long-running-script.sh &      # & সাথে সাথে এটাকে background-এ পাঠায় — তুমি prompt ফিরে পাও
```

Foreground-এ কিছু চলছে আর সেটা বন্ধ না করেই terminal ফিরে পেতে চাও:
- `Ctrl+Z` চাপো — এটা pause করে control তোমাকে ফিরিয়ে দেয়।
- `bg` — এটাকে resume করে, কিন্তু background-এ।
- `fg` — একটা backgrounded process-কে ফিরে foreground-এ আনে।
- `jobs` — তোমার session-এ এখন চলা/pause হওয়া কী আছে list করে।

```bash
nohup long-running-script.sh &
```
`nohup` ("no hang up") তোমার terminal সম্পূর্ণ বন্ধ করলেও process চালু রাখে — এটা ছাড়া, terminal session বন্ধ করলে তাতে চালু করা যা কিছু kill হয়ে যেতে পারে।

## ৪. `systemd` — real service আসলে কীভাবে চালু থাকে

হাতে চালু করা script তোমার terminal বন্ধ হলে মরে যায় (`nohup` না করলে)। Real service — web server, database, এই page-টা যা serve করছে তা — boot-এ automatically চালু হতে হয়, crash করলে নিজে নিজে restart হতে হয়, আর একই set-এর command দিয়ে manage করা যেতে হয়। এটাই ঠিক **`systemd`** আর তার command, **`systemctl`**-এর কাজ।

```bash
systemctl status caddy
```
একটা service চলছে কিনা, কখন থেকে, আর তার সবচেয়ে recent log লাইনগুলো দেখায় — কিছু "কাজ করছে না" মনে হলে এটাই প্রথম move।

```bash
sudo systemctl start caddy      # এখনই চালু করো
sudo systemctl stop caddy       # এখনই বন্ধ করো
sudo systemctl restart caddy    # বন্ধ করে, আবার চালু করো — classic "বন্ধ করে আবার চালু করলে?"
sudo systemctl reload caddy     # current connection না ফেলে config আবার পড়ে (supported হলে)
```

```bash
sudo systemctl enable caddy     # প্রতিটা boot-এ automatically চালু হবে এমন করা
sudo systemctl disable caddy    # boot-এ auto-start বন্ধ করা
```

**`start` vs `enable` আসলেই একটা common confusion:** `start` এখনই, এই একবার চালায়। `enable` প্রতিবার মেশিন boot হলে automatically চালু হতে করে। সাধারণত দুটোই চাও — একবার `enable` করো, আর `start` করো (বা শুধু reboot করো) এখনই এটা চালু রাখতে।

```bash
journalctl -u caddy -f
```
সেই service-এর logs live follow করে — আগের chapter-এর `docker logs -f` আর `tail -f`-এর `systemd` equivalent। একই ধারণা, তিনটা আলাদা tool, একটা pattern যা তুমি এখন সব জায়গায় চিনতে পারবে।

## ৫. নিজে করে দেখো (read-only, যেকোনো মেশিনে নিরাপদ)

```bash
ps aux | head -10
systemctl status ssh          # বা install আছে জানা অন্য কোনো service
journalctl -u ssh -n 20       # সেই service-এর শেষ ২০ লাইন log
```

## ৬. মূল কথাগুলো (Key Takeaways)

- চলমান প্রতিটা program একটা unique PID-সহ process; `ps aux` সেগুলো list করে, `top`/`htop` live দেখায়।
- `kill` SIGTERM পাঠায় (বন্ধ হওয়ার ভদ্র request); `kill -9` SIGKILL পাঠায় (instant, কোনো cleanup ছাড়া) — সবসময় প্রথমে plain `kill` try করো।
- `&`, `bg`, `fg`, আর `jobs` তোমার current session-এ foreground/background process manage করে; `nohup` terminal বন্ধ করার পরেও একটাকে চালু রাখে।
- `systemctl status/start/stop/restart` একটা service এখনই manage করে; `enable`/`disable` নিয়ন্ত্রণ করে এটা boot-এ automatically চালু হবে কিনা — এগুলো দুটো আলাদা প্রশ্ন।
- `journalctl -u <service> -f` একটা service-এর logs live follow করে, `docker logs -f`-এর একই ধারণা।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. `kill` আর `kill -9`-এর আসল পার্থক্য কী?</strong></summary>

Plain `kill` SIGTERM পাঠায়, একটা request যা program ধরতে এবং respond করতে পারে — নিজের শর্তে পরিষ্কার করে, data save করে, exit করে। `kill -9` SIGKILL পাঠায়, যা process-কে সাথে সাথে terminate করে, কিছু পরিষ্কার করার কোনো সুযোগ না দিয়ে, যদি এটা মাঝপথে কিছু করছিল তাহলে data হারানো বা corrupt হওয়ার ঝুঁকি।
</details>

<details>
<summary><strong>প্রশ্ন ২. `systemctl start nginx` চালিয়েছো কিন্তু `enable` চালাওনি, সার্ভার reboot হওয়ার পর কী হবে?</strong></summary>

nginx automatically চালু হবে না — `start` শুধু এখন, current boot session-এ service-কে affect করে। শুধু `enable` এটাকে future boot-এ automatically চালু হতে configure করে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. তুমি `long-script.sh &` চালানো terminal বন্ধ করলে, এবং process তবুও মরে গেলো। কেন, আর কী এটা আটকাতো?</strong></summary>

`&` দিয়ে একটা process background-এ পাঠানো terminal session বন্ধ হওয়া থেকে এটাকে রক্ষা করে না — সাধারণত session শেষ হলে এটাও terminate হয়, যদি না তুমি `nohup`-ও ব্যবহার করতে (বা একটা proper service manager), যা এটাকে terminal থেকে সম্পূর্ণভাবে আলাদা করে দেয়।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `journalctl -u caddy -f` কী করে, আর আগের course গুলোর কোন দুটো command-এর সাথে এটা মিলে যায়?</strong></summary>

এটা `caddy` service-এর logs live follow করে, নতুন লাইন আসার সাথে সাথে প্রিন্ট করে। এটা একটা log file-এ `tail -f`-এর (Linux course, Chapter 2) আর একটা container-এ `docker logs -f`-এর (Docker course) একই ধারণা — তিনটা tool, একটা খুবই common pattern: real time-এ output হওয়া দেখা।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official reference: [systemd — `systemctl` manual](https://www.freedesktop.org/software/systemd/man/systemctl.html)
- Official reference: [`journalctl` manual](https://www.freedesktop.org/software/systemd/man/journalctl.html)

---

**পরের চ্যাপ্টার:** Chapter 6 — Package Management (apt) *(শীঘ্রই আসছে)*
