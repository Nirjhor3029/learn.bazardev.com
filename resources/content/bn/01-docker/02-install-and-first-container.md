# Chapter 2 — Docker Install + তোমার প্রথম Container

> **Course:** Docker (Zero to Hero) · **Chapter:** 2 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/docker/02-install-and-first-container)

এবার হাতে-কলমে কাজ শুরু। এই চ্যাপ্টার শেষে তোমার মেশিনে Docker install হয়ে যাবে, দুইটা container চালাবে, আর প্রতিটা command আসলে কী করছে সেটাও বুঝে যাবে।

## ১. Docker Install করা

### Linux (Ubuntu/Debian — official convenience script)

```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
```

তারপর তোমার user-কে `sudo` না লিখে `docker` চালানোর permission দাও:

```bash
sudo usermod -aG docker $USER
```

এই group change effective হতে **logout করে আবার login করো** (বা মেশিন reboot দাও)।

### macOS / Windows

**Docker Desktop** install করো — এতে Docker Engine, CLI, আর একটা GUI, সব একটা lightweight VM-এর ভেতরে বান্ডেল করা থাকে:

- macOS: [docker.com/products/docker-desktop](https://www.docker.com/products/docker-desktop/)
- Windows: একই লিংক, WSL2 enabled থাকতে হবে।

### Install ঠিকমতো হয়েছে কিনা দেখো

```bash
docker --version
docker info
```

`docker info` যদি কোনো error না দিয়ে সার্ভারের বিস্তারিত তথ্যের একটা বড় ব্লক দেখায়, তাহলে Docker daemon ঠিকমতো চলছে।

## ২. তোমার প্রথম container: `hello-world`

```bash
docker run hello-world
```

এরকম output দেখবে:

```
Unable to find image 'hello-world:latest' locally
latest: Pulling from library/hello-world
...
Status: Downloaded newer image for hello-world:latest

Hello from Docker!
This message shows that your installation appears to be working correctly.
```

এখানে ঠিক কী ঘটলো, স্টেপ বাই স্টেপ — এটাই Chapter 1-এর architecture diagram, যা এখন বাস্তবে চলছে:

1. CLI, `docker run hello-world` কমান্ডটা Daemon-কে পাঠালো।
2. Daemon `hello-world` image **লোকালে** খুঁজলো — পেলো না।
3. এটা Docker Hub থেকে image **pull** করে নিয়ে আসলো।
4. সেই image থেকে একটা **container** চালু হলো।
5. Container তার মেসেজ প্রিন্ট করে বন্ধ হয়ে গেলো (তার একটাই কাজ ছিল, সেটা শেষ)।

আবার `docker run hello-world` চালাও — এবার দেখবে instant হয়ে গেছে, কারণ image টা এখন লোকালে cache হয়ে আছে। নতুন করে download করতে হচ্ছে না।

## ৩. একটা real container: nginx (একটা web server)

```bash
docker run -d -p 8080:80 --name my-nginx nginx
```

এবার browser-এ `http://localhost:8080` (বা `http://<তোমার-server-ip>:8080`) ওপেন করো — nginx-এর welcome page দেখবে, যা একটা container-এর ভেতর থেকে serve হচ্ছে।

Flag গুলো বুঝে নিই:

| Flag | মানে |
|---|---|
| `-d` | **Detached** — ব্যাকগ্রাউন্ডে চালায়, টার্মিনাল ফিরিয়ে দেয় |
| `-p 8080:80` | **Port mapping** — `host_port:container_port`। তোমার মেশিনের 8080 পোর্টে আসা request container-এর ভেতরের 80 পোর্টে (যেখানে nginx listen করছে) ফরওয়ার্ড হয় |
| `--name my-nginx` | Container-কে একটা পরিচিত নাম দেয়, Docker-এর random নাম বানানোর বদলে |
| `nginx` | যে image চালানো হবে (Docker Hub থেকে automatically pull হয়ে যাবে, যদি লোকালে না থাকে) |

## ৪. চলমান container গুলো দেখা

```bash
docker ps
```

সব **running** container দেখায় — এখানে `my-nginx` দেখবে `Up` status-এ।

```bash
docker ps -a
```

**সব** container দেখায়, বন্ধ হয়ে যাওয়া গুলোও (যেমন `hello-world` container, যেটা মেসেজ প্রিন্ট করার পরেই exit করে গেছে)।

```bash
docker logs my-nginx
```

সেই container-এর output/logs দেখায় — কিছু ঠিকমতো কাজ না করলে debug করার সময় কাজে লাগে।

## ৫. Container বন্ধ করা এবং সরানো

```bash
docker stop my-nginx
docker rm my-nginx
```

- `stop` container-টা gracefully বন্ধ করে (এটা তখনও থাকে, শুধু চলছে না)।
- `rm` এটা সম্পূর্ণভাবে remove করে দেয়।

একসাথে দুইটাও করা যায়:

```bash
docker rm -f my-nginx
```

## ৬. Images একটু দেখে নেওয়া

```bash
docker images
```

তুমি pull বা build করা প্রতিটা image-এর লিস্ট দেখায়, যা লোকালে আছে। এখানে `hello-world` আর `nginx` দেখবে। Images vs containers, এবং সেগুলোর আশেপাশের commands নিয়ে বিস্তারিত থাকবে **Chapter 3**-এ।

## ৭. মূল কথাগুলো (Key Takeaways)

- `docker run <image>` প্রয়োজনে image pull করে এবং সেখান থেকে একটা container চালু করে।
- `-d` ব্যাকগ্রাউন্ডে চালায়; `-p host:container` port map করে, যাতে বাইরে থেকে container-এ পৌঁছানো যায়।
- `docker ps` running container দেখায়; `docker ps -a` সব container দেখায়, running হোক বা stopped।
- `docker stop` container pause করে; `docker rm` সেটা delete করে; `docker rm -f` দুটোই একসাথে করে।
- `docker images` লোকালে কী আছে তার লিস্ট দেখায়।

## ৮. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. `docker ps` আর `docker ps -a`-এর পার্থক্য কী?</strong></summary>

`docker ps` শুধু বর্তমানে running container দেখায়। `docker ps -a` সব container দেখায়, stopped বা exited হয়ে যাওয়া গুলোও।
</details>

<details>
<summary><strong>প্রশ্ন ২. `docker run -d -p 8080:80 nginx`-এ `8080:80` মানে কী?</strong></summary>

এটা হোস্ট মেশিনের 8080 পোর্টকে container-এর ভেতরের 80 পোর্টের সাথে map করে। `localhost:8080`-এ আসা traffic forward হয় 80 পোর্টে, যেখানে container-এর ভেতরে nginx listen করছে।
</details>

<details>
<summary><strong>প্রশ্ন ৩. দ্বিতীয়বার `docker run hello-world` কেন instant হলো, প্রথমবার কয়েক সেকেন্ড লাগলো কেন?</strong></summary>

প্রথমবার Docker Hub থেকে নেটওয়ার্কের মাধ্যমে `hello-world` image pull করতে হয়েছিল। দ্বিতীয়বার image টা লোকালে cache হয়ে পাওয়া গেছে, তাই download লাগেনি — সরাসরি cached image থেকে নতুন container চালু হয়ে গেছে।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `docker stop` আর `docker rm`-এর পার্থক্য কী?</strong></summary>

`docker stop` container বন্ধ করে দেয় কিন্তু রেখে দেয় (পরে `docker start` দিয়ে আবার চালু করা যায়)। `docker rm` container সম্পূর্ণভাবে delete করে দেয়। `docker rm -f` এক স্টেপে বন্ধ এবং remove দুটোই করে।
</details>

## ৯. ফ্রি রিসোর্স (English)

- Official docs: [Install Docker Engine](https://docs.docker.com/engine/install/)
- Official docs: [Docker run reference](https://docs.docker.com/reference/cli/docker/container/run/)
- YouTube: **"Docker Tutorial for Beginners [FULL COURSE in 3 Hours]"** — channel: *TechWorld with Nana* (এই install + first-run flow-টাই cover করে)

---

**পরের চ্যাপ্টার:** Chapter 3 — Images vs Containers *(শীঘ্রই আসছে)*
