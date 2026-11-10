# Chapter 6 — Docker Compose: Multi-Container Apps

> **Course:** Docker (Zero to Hero) · **Chapter:** 6 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/docker/06-docker-compose)

এই চ্যাপ্টারটা একটু সময় নিয়ে পড়ো — এটাই সেই chapter যেখানে এতদিন শেখা সব কিছু (image, container, volume, network) আলাদা আলাদা ধারণা না থেকে একটা practical skill-এ পরিণত হবে। ধীরে ধীরে পড়ো, নিজে example-টা টাইপ করে দেখো, তাহলেই পুরোপুরি বুঝে যাবে।

## ১. সমস্যাটা সহজভাবে বলি

Chapter 5-এ, একটা app আর database একসাথে চালাতে, তোমাকে চারটা আলাদা command টাইপ করতে হয়েছিল, একটা নির্দিষ্ট order-এ, আর সবগুলো মনে রাখতে হয়েছিল:

```bash
docker network create mynet
docker volume create db-data
docker run -d --name db --network mynet -v db-data:/var/lib/mysql -e MYSQL_ROOT_PASSWORD=secret mysql:8
docker run -d --name app --network mynet -p 3000:3000 my-first-app
```

শুধু **দুটো** container-এর জন্যই এতগুলো মনে রাখতে হচ্ছে। Real application-এ প্রায়ই তিনটা, চারটা, বা তার বেশি container থাকে (app, database, cache, admin tool...)। প্রতিবার এগুলো টাইপ করা — আর প্রতিবার সঠিক order মেনে চলা — এটা scale করে না, আর টিমমেটের সাথে share করাও কঠিন, chat-এ command এর লিস্ট copy-paste করা ছাড়া আর কোনো উপায় নেই।

**Docker Compose** এই সমস্যা সমাধান করে: তোমার পুরো multi-container setup — প্রতিটা service, তারা কীভাবে connect হয়, কী data persist করে — সব **একটা ফাইলে** লিখে রাখো। তারপর একটা command দিয়ে পুরো সিস্টেম চালু হয়ে যায়, ঠিক একইভাবে, প্রতিবার, যেকোনো মেশিনে।

## ২. তোমার কাছে আছে কিনা দেখে নেওয়া

আধুনিক Docker-এ Compose আগে থেকেই CLI-এর ভেতরে বিল্ট-ইন আছে (খেয়াল করো, **space** আছে, hyphen নেই):

```bash
docker compose version
```

(কিছু পুরোনো tutorial-এ `docker-compose` (hyphen দিয়ে) দেখতে পাবে — সেটা পুরোনো, আলাদা একটা tool ছিল। `docker compose` (space দিয়ে) হলো এখনকার, built-in version। দুটোই একই কাজ করে প্রায়, কিন্তু এখন থেকে space version-টা ব্যবহার করো।)

## ৩. পুরো ছবিটা, file পড়ার আগে

আমরা এখন কী বানাচ্ছি তা একনজরে দেখে নেওয়া যাক — তিনটা service, Compose automatically বানানো একটা shared network, একটা persistent volume:

![Docker Compose overview diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/01-docker/images/docker-compose-overview.svg)

নিচের file পড়ার সময় এই ছবিটা মনে রাখো — এখানে প্রতিটা লাইন সরাসরি এই ছবির কোনো একটা অংশের সাথে মিলে যায়।

## ৪. Compose file পড়া, ধীরে ধীরে, অংশে অংশে

এখানে একটা সম্পূর্ণ, real `docker-compose.yml` — Chapter 5-এর সেই app + database, সাথে বোনাস আরেকটা service। এটা তাড়াহুড়ো করে পড়ো না; প্রতিটা লাইন পড়ো আর আগে শেখা কোনো না কোনো command-এর সাথে মিলিয়ে নাও।

```yaml
services:
  app:
    build: .
    ports:
      - "3000:3000"
    environment:
      - DB_HOST=db
    depends_on:
      - db

  db:
    image: mysql:8
    environment:
      - MYSQL_ROOT_PASSWORD=secret
      - MYSQL_DATABASE=myapp
    volumes:
      - db-data:/var/lib/mysql

  adminer:
    image: adminer
    ports:
      - "8081:8080"
    depends_on:
      - db

volumes:
  db-data:
```

অংশে অংশে দেখি:

- **`services:`** — এর নিচে প্রতিটা ব্লক একটা container, Compose-এর ভাষায় যাকে বলে "service"। এখানে তিনটা আছে: `app`, `db`, `adminer`।
- **`app: build: .`** — `image:`-এর বদলে, এটা Compose-কে বলে **current ফোল্ডারের Dockerfile থেকে একটা image build করো** (Chapter 4-এ যেটা লিখেছিলে), readymade কোনো image pull না করে।
- **`ports: - "3000:3000"`** — Chapter 2-এর `-p 3000:3000`-এর সাথে ঠিক একই মানে।
- **`environment:`** — `-e` flag-এর মতোই। এখানে একটা গুরুত্বপূর্ণ বিষয়: `DB_HOST=db` তোমার app code-কে বলে একটা database host-এ connect করতে যার নাম `db` — `localhost` না, কোনো IP address না, শুধু **service-এর নাম**। এটা কাজ করে ঠিক Chapter 5-এ যা শিখেছো সেই কারণেই: Compose automatically একটা network বানায়, আর প্রতিটা service সেখানে একে অপরকে নামের মাধ্যমে খুঁজে পায়। এই file-এর কোথাও তুমি `docker network create` লেখোনি — Compose সেটা নিজে নিজেই করে, নিঃশব্দে, প্রতিবার।
- **`depends_on: - db`** — Compose-কে বলে `app`-এর আগে `db` চালু করতে। (নিচে একটা গুরুত্বপূর্ণ nuance আছে — এটা skip করো না।)
- **`db: image: mysql:8`** এবং **`volumes: - db-data:/var/lib/mysql`** — Chapter 5-এর named-volume pattern-ই, শুধু `-v` flag-এর বদলে declaratively লেখা।
- **`adminer`** — একটা ফ্রি, ছোট web-based database admin tool। এটার database খুঁজে পাওয়ার জন্য কোনো configuration লাগে না, কারণ (আবারও) এটা সহজেই `db` hostname-এ পৌঁছাতে পারে।
- **`volumes: db-data:`** (top level, `services:`-এর বাইরে) — এটা ঘোষণা করে যে `db-data` একটা real named volume, যা Compose manage করবে, ঠিক `docker volume create db-data`-এর মতোই।

## ৫. চালিয়ে দেখা

```bash
docker compose up -d
```

এটাই সম্পূর্ণ command। Compose যা করবে:
1. তোমার Dockerfile থেকে `app` image build করবে (শুধু তখনই, যদি আগে build করা না থাকে, বা Dockerfile পরিবর্তন হয়ে থাকে — Chapter 4-এর caching এখানেও কাজ করে)।
2. `mysql:8` আর `adminer` pull করবে, যদি না থাকে।
3. shared network বানাবে।
4. `db-data` volume বানাবে, যদি না থাকে।
5. তিনটা container-ই চালু করবে, সব connected, সঠিক order-এ।

এখন চেক করো কাজ করেছে কিনা:

```bash
docker compose ps
curl http://localhost:3000
```

এবং browser-এ `http://localhost:8081` (বা `http://<তোমার-server-ip>:8081`) খোলো — এটাই Adminer। Server `db`, username `root`, password `secret` (compose file থেকে) দিয়ে login করো, আর তুমি দেখবে `db` container-এর ভেতরে চলা actual MySQL database-টা, একটা web UI দিয়ে, কোনো setup ছাড়াই।

## ৬. দৈনন্দিন commands

```bash
docker compose logs -f app       # একটা service-এর logs live দেখা
docker compose ps                # সব service আর তাদের status দেখা
docker compose exec app bash     # "app" service-এ shell করা (docker exec-এর মতোই)
docker compose restart app       # শুধু একটা service restart করা
docker compose stop              # সব বন্ধ করা, container আর volume রেখে দেওয়া
docker compose down              # বন্ধ করে container remove করা (volume থেকে যায়)
docker compose down -v           # বন্ধ করে, container remove করে, volume-ও delete করে — এটা তোমার data delete করে দেয়, সাবধানে ব্যবহার করো
docker compose build             # container চালু না করে শুধু image rebuild করা
```

**যে command-টা সাবধানে ব্যবহার করতে হবে:** `docker compose down -v`। `-v` volume-ও remove করে দেয় — মানে তোমার database-এর data সাথে সাথে চলে যায়। `docker compose down` একা নিরাপদ; তোমার `db-data` volume ঠিকই থাকে, Chapter 5-এ যেমন শিখেছো।

## ৭. `depends_on`-এর একটা গুরুত্বপূর্ণ nuance

`depends_on` শুধু অপেক্ষা করে `db` **container চালু হওয়া পর্যন্ত** — তার ভেতরের MySQL আসলে পুরোপুরি চালু হয়ে connection গ্রহণ করার জন্য প্রস্তুত হওয়া পর্যন্ত না। এই দুটো সময় আলাদা; container চালু হওয়ার পরেও MySQL-এর পুরোপুরি ready হতে আরও কয়েক সেকেন্ড লাগতে পারে।

Practically এর মানে হলো, তোমার `app` container একটু আগেই database-এ connect করার চেষ্টা করতে পারে আর প্রথম চেষ্টায় fail করতে পারে। সাধারণ, practical সমাধান:
- App-কে startup-এ সাথে সাথে হার না মেনে কয়েকবার retry করতে দাও (অনেক framework, Laravel-ও, configure করা থাকলে এটা আগে থেকেই করে)।
- `db` service-এ একটা `healthcheck` আর `depends_on`-এ `condition: service_healthy` যোগ করো (এটা একটু advanced pattern — জানা ভালো যে এটা আছে, Compose নিয়ে আরও গভীরে গেলে আবার দেখা হবে)।

এখন শুধু এটা মনে রাখো: **`depends_on` শুধু start order নিয়ন্ত্রণ করে, readiness না।** এই একটা তথ্য পরে অনেক ঘন্টার confused debugging বাঁচিয়ে দেবে।

## ৮. মূল কথাগুলো (Key Takeaways)

- Docker Compose একটা পুরো multi-container application manual command-এর লিস্টের বদলে একটা YAML file-এ describe করে।
- `build: .` তোমার নিজের Dockerfile থেকে build করে; `image:` readymade একটা pull করে — একই file-এ দুটোই mix করা যায়।
- Compose file-এর সব service-এর জন্য automatically একটা shared network বানায় — প্রতিটা service একে অপরকে তার **service name** দিয়ে খুঁজে পায়, কোনো manual `docker network create` ছাড়াই।
- Top-level `volumes:` named volume ঘোষণা করে, ঠিক `docker volume create`-এর মতোই, শুধু declaratively লেখা।
- `docker compose up -d` সব চালু করে; `docker compose down` বন্ধ করে container remove করে কিন্তু volume রেখে দেয়; `docker compose down -v` volume-ও delete করে দেয় — এটা নিয়ে সাবধান থাকতে হবে।
- `depends_on` শুধু **start order** guarantee করে, dependency আসলে connection নেওয়ার জন্য ready কিনা তা না।

## ৯. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. উপরের compose file-এ app-এর environment variable-এ `DB_HOST=db` কেন, `DB_HOST=localhost` কেন না?</strong></summary>

কারণ database app-এর নিজের "localhost"-এ চলছে না — এটা একটা আলাদা container। Compose file-এর সব service-কে একই automatically-তৈরি network-এ রাখে, যেখানে প্রতিটা service তার service name দিয়ে খুঁজে পাওয়া যায়। `db` হলো সেই hostname, যা app container-কে database container-এ পৌঁছাতে ব্যবহার করতে হবে।
</details>

<details>
<summary><strong>প্রশ্ন ২. `docker compose down` আর `docker compose down -v`-এর practical পার্থক্য কী?</strong></summary>

দুটোই container বন্ধ করে remove করে। `-v` অতিরিক্তভাবে সেই service গুলোর named volume-ও delete করে দেয় — মানে তাতে জমা থাকা data (যেমন database-এর data) permanently delete হয়ে যায়। `-v` ছাড়া, volume টিকে থাকে এবং পরের বার `docker compose up` চালালে তোমার data ঠিকই থাকবে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. `depends_on: - db` কি guarantee করে যে `app` service চালু হওয়ার আগে MySQL connection নেওয়ার জন্য ready?</strong></summary>

না — এটা শুধু guarantee করে যে `db` container চালু হয়েছে। MySQL নিজে সেই container-এর ভেতরে আরও কয়েক সেকেন্ড ধরে initialize হতে পারে। যে app ধরে নেয় database সাথে সাথেই ready, তার প্রথম connection চেষ্টা fail করতে পারে; এটা retry করা উচিত, বা compose file-এ একটা proper healthcheck ব্যবহার করা উচিত।
</details>

<details>
<summary><strong>প্রশ্ন ৪. এই example-এ `app` কেন `build: .` ব্যবহার করে, কিন্তু `db` আর `adminer` কেন `image: ...` ব্যবহার করে?</strong></summary>

`app` তোমার নিজের লেখা code, তাই Compose-কে current ফোল্ডারের Dockerfile থেকে একটা image build করতে হবে। `db` আর `adminer` standard, readymade tool যা তুমি লেখোনি, তাই এগুলো build করার প্রয়োজন নেই — Compose সরাসরি Docker Hub থেকে তাদের published image pull করে।
</details>

<details>
<summary><strong>প্রশ্ন ৫. এই file-এ চতুর্থ একটা service `redis` যোগ করলে, এটা `app`-এর সাথে কথা বলার জন্য `docker network create` চালাতে হবে কি?</strong></summary>

না। একই `docker-compose.yml`-এ ঘোষণা করা প্রতিটা service automatically একই Compose-managed network-এ রাখা হয় এবং একে অপরকে নামের মাধ্যমে খুঁজে পায় — Compose-এর networking নিজে হ্যান্ডেল করার এটাই পুরো উদ্দেশ্য।
</details>

## ১০. ফ্রি রিসোর্স (English)

- Official docs: [Docker Compose overview](https://docs.docker.com/compose/)
- Official docs: [Compose file reference](https://docs.docker.com/reference/compose-file/)
- Video: **"Docker Tutorial for Beginners [FULL COURSE in 3 Hours]"** — *TechWorld with Nana* (এর পরের অংশগুলোয় Compose বিস্তারিত cover করা আছে)

---

**পরের চ্যাপ্টার:** Chapter 7 — Best Practices + Debugging *(শীঘ্রই আসছে)*
