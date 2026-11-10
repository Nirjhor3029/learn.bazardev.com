# Chapter 5 — Volumes & Networking

> **Course:** Docker (Zero to Hero) · **Chapter:** 5 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/docker/05-volumes-and-networking)

Docker শেখার সময় প্রায় সবাই দুইটা জায়গায় আটকে যায়: **আমার data আসলে কোথায় থাকে**, এবং **আমার container গুলো একে অপরের সাথে কথা বলে কীভাবে**। দুটোই অনেক সহজ হয়ে যায়, যখন ছবি দিয়ে দেখা যায়।

![Volumes and networking diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/01-docker/images/volumes-and-networking.svg)

Real command দিয়ে step by step দেখতে চাইলে, এই video দুটো topic-ই ভালোভাবে cover করে:

<iframe width="560" height="315" src="https://www.youtube.com/embed/5grbXvV_DSk" title="Docker Networking Tutorial - Christian Lempa" frameborder="0" allowfullscreen></iframe>

*(এই video ইংরেজিতে, কিন্তু commands আর diagram দেখেই ধারণা স্পষ্ট হয়ে যাবে।)*

## ১. সমস্যাটা: Container disposable (ফেলে দেওয়ার মতো)

Chapter 3 থেকে মনে করো: একটা container-এর writable layer, container remove করার সাথে সাথেই হারিয়ে যায়। App-এর জন্য এটা ভালো (clean, disposable, নতুন করে শুরু করা যায়) — কিন্তু একটা **database**-এর জন্য ভয়ংকর। MySQL container `docker rm` করলে, তুমি তো চাও না এতদিনের save করা সব data হারিয়ে যাক।

**Volume** এই সমস্যা সমাধান করে: এগুলো কোনো একটা container-এর বাইরে বাস করে, তাই container যতবারই remove-recreate করো, volume-এর ভেতরের data ঠিক থাকে।

```bash
docker volume create db-data
docker run -d --name my-db -v db-data:/var/lib/mysql -e MYSQL_ROOT_PASSWORD=secret mysql:8
```

- `docker volume create db-data` — একটা named volume বানায়, যা সম্পূর্ণভাবে Docker manage করে।
- `-v db-data:/var/lib/mysql` — সেই volume-কে container-এর **ভেতরের** `/var/lib/mysql`-এ mount করে, যেটা ঠিক যেখানে MySQL তার data file রাখে।

এবার এটা try করো:

```bash
docker rm -f my-db
docker run -d --name my-db -v db-data:/var/lib/mysql -e MYSQL_ROOT_PASSWORD=secret mysql:8
```

Container delete করে নতুন একটা চালু করলেও, আগের সব data এখনো আছে — কারণ data আসলে কখনোই container-এর ভেতরে থাকেনি। এটা `db-data`-তে ছিল।

```bash
docker volume ls
docker volume inspect db-data
docker volume rm db-data
```

### Named volume vs. bind mount

আরেকটা related flag জানা ভালো — একটা **bind mount**, যা তোমার মেশিনের একটা আসল ফোল্ডার সরাসরি container-এ map করে:

```bash
docker run -d -p 3000:3000 -v $(pwd)/src:/app/src my-first-app
```

| | Named volume (`-v db-data:/path`) | Bind mount (`-v $(pwd)/src:/path`) |
|---|---|---|
| কে manage করে | Docker নিজেই | তুমি — এটা তোমার মেশিনের একটা আসল ফোল্ডার |
| সবচেয়ে ভালো কোথায় | Database, যা কিছু Docker-কে manage/backup করতে দিতে চাও | Local development-এ live-editing code |
| কোথায় থাকে | Docker-এর internal storage area | তুমি যেখানে point করো, তোমার মেশিনেই |

## ২. সমস্যাটা: Container গুলো default-এ একে অপরকে দেখতে পায় না

দুটো container আলাদাভাবে চালু করলে, default-এ তারা একে অপর থেকে isolated থাকে — `app`-এর কোনো ধারণা নেই যে `db` বলে কিছু আছে। একটা real application-এর জন্য (app server যেটা database-এর সাথে কথা বলে), তাদের communicate করতে হবে।

```bash
docker network create mynet
docker run -d --name db --network mynet -e MYSQL_ROOT_PASSWORD=secret mysql:8
docker run -d --name app --network mynet my-first-app
```

এই অংশটা প্রথমবার দেখলে ম্যাজিকের মতো লাগে: দুটো container একই custom network (`mynet`)-এ থাকার কারণে, `app` container শুধু `db`-র **নাম** ব্যবহার করে — যেন এটা একটা hostname — `db` container-এ পৌঁছাতে পারে:

```bash
docker exec -it app ping db
```

Docker প্রতিটা custom network-এ একটা built-in DNS server চালায়, তাই container-এর নাম automatically সঠিক internal IP address-এ resolve হয়ে যায়। তোমাকে কখনো কোনো IP address hardcode করতে হবে না, যা পরিবর্তন হয়ে যেতে পারে।

```bash
docker network ls
docker network inspect mynet
```

**গুরুত্বপূর্ণ একটা বিষয়:** এই automatic name-based lookup শুধু তখনই কাজ করে যখন তুমি একটা **custom** network বানিয়েছো (`docker network create ...`)। Docker-এর default network-এ (যদি `--network` কিছু না দাও) চালু করা container গুলো নামের মাধ্যমে reliably একে অপরকে খুঁজে পায় না — এই কারণেই real project গুলো সবসময় একটা আলাদা network বানায়, এবং Chapter 6 (Docker Compose) এটা automatically করে দেয়।

## ৩. মূল কথাগুলো (Key Takeaways)

- Container remove করলে তার নিজের storage হারিয়ে যায় — **volume** container-এর বাইরে থাকে, তাই data টিকে থাকে।
- `-v <volume-name>:/path` একটা Docker-managed named volume mount করে; `-v /host/path:/path` তোমার মেশিনের একটা আসল ফোল্ডার bind-mount করে।
- একটা **custom network**-এ থাকা container গুলো একে অপরকে নামের মাধ্যমে খুঁজে পায় — Docker automatically DNS resolution দেয়।
- Docker-এর default network-এ থাকা container গুলো নামের মাধ্যমে reliably একে অপরকে খুঁজে পায় না — multi-container setup-এর জন্য সবসময় একটা custom network বানাও।

## ৪. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. Volume ছাড়া একটা database container remove করলে তার data-র কী হবে?</strong></summary>

হারিয়ে যাবে। Data শুধু container-এর writable layer-এ ছিল, যা container-এর সাথেই delete হয়ে যায়। এই কারণেই একটা database container-এর data directory-র পেছনে সবসময় একটা named volume থাকা উচিত।
</details>

<details>
<summary><strong>প্রশ্ন ২. Named volume আর bind mount-এর পার্থক্য কী?</strong></summary>

Named volume সম্পূর্ণভাবে Docker-এর নিজের storage area-তে Docker তৈরি করে এবং manage করে — database-এর জন্য ideal। Bind mount তোমার মেশিনের একটা আসল ফোল্ডারে সরাসরি point করে, যা development-এর সময় live-editing source code-এর জন্য ideal, কারণ তোমার মেশিনে করা পরিবর্তন সাথে সাথে container-এর ভেতরেও দেখা যায়।
</details>

<details>
<summary><strong>প্রশ্ন ৩. `app` container কেন শুধু "db" নাম ব্যবহার করেই `db`-তে পৌঁছাতে পারে?</strong></summary>

কারণ দুটো container একই custom Docker network-এ চালু করা হয়েছে, আর Docker সেই network-এ একটা built-in DNS server চালায়, যা automatically container-এর নামকে তাদের internal IP address-এ resolve করে দেয়।
</details>

<details>
<summary><strong>প্রশ্ন ৪. আগে একটা custom network না বানালে এই name-based lookup কাজ করে না কেন?</strong></summary>

Container-এর মধ্যে Docker-এর automatic DNS resolution হলো custom (user-defined) network-এর একটা feature। Docker-এর default network-এ থেকে যাওয়া container গুলো একইরকম reliable name resolution পায় না — এই কারণেই real-world multi-container setup সবসময় একটা আলাদা network বানায়।
</details>

## ৫. ফ্রি রিসোর্স (English)

- Official docs: [Manage data in Docker](https://docs.docker.com/storage/)
- Official docs: [Docker networking overview](https://docs.docker.com/engine/network/)
- Video: **"Docker Networking Tutorial // ALL Network Types explained!"** — *Christian Lempa* (উপরে embed করা আছে)

---

**পরের চ্যাপ্টার:** Chapter 6 — Docker Compose: Multi-Container Apps *(শীঘ্রই আসছে)*
