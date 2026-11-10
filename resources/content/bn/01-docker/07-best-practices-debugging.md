# Chapter 7 — Best Practices + Debugging

> **Course:** Docker (Zero to Hero) · **Chapter:** 7 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/docker/07-best-practices-debugging)

এতদিন যা শিখেছো সব focus ছিল কিছু *কাজ করানোর* উপর। এই chapter হলো সেটা **ভালোভাবে** করানোর উপর — ছোট image, নিরাপদ container — আর কিছু ভেঙে গেলে কী করতে হবে, কারণ কোনো না কোনো সময় কিছু না কিছু ভাঙবেই।

## ১. Multi-stage build — সবচেয়ে বড় size-এর সুবিধা

Chapter 4-এর Dockerfile build tool, dev dependencies, আর তোমার source code সবকিছু একটা final image-এ install করে। এর বেশিরভাগই লাগে শুধু *build করার সময়*, *চালানোর সময়* না। একটা **multi-stage build** সেই heavy জিনিসগুলো একটা ফেলে দেওয়া stage-এ রাখে, আর শুধু শেষ result-টা একটা ছোট final image-এ copy করে।

![Multi-stage build diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/01-docker/images/multi-stage-build.svg)

```dockerfile
# Stage 1: build (এই image বড়, কিন্তু সমস্যা নেই — এটা কখনো ship হয় না)
FROM node:20 AS build
WORKDIR /app
COPY package.json .
RUN npm install
COPY . .
RUN npm run build

# Stage 2: final (ছোট, এটাই আসলে ship হয়)
FROM node:20-alpine
WORKDIR /app
COPY --from=build /app/dist ./dist
COPY --from=build /app/node_modules ./node_modules
CMD ["node", "dist/main.js"]
```

`AS build` প্রথম stage-এর নাম দেয়। দ্বিতীয় stage-এর `COPY --from=build` সেই প্রথম stage-এর ভেতরে *ফিরে গিয়ে* শুধু যে ফাইল গুলো চাও সেগুলোই copy করে। Stage 1-এর বাকি সব — compiler, dev dependencies, prune করার আগের পুরো `node_modules` — সবকিছু ফেলে দেওয়া হয়। তোমার final image-এ শুধু তাই থাকে যা Stage 2 explicitly copy করেছে।

Real world-এ result: image প্রায়ই এক গিগাবাইটের বেশি থেকে ২০০MB-এর নিচে নেমে আসে। ছোট image দ্রুত pull হয়, দ্রুত চালু হয়, আর তার attack surface ছোট।

## ২. Container-এর ভেতরে root হিসেবে চালাবে না

Default-এ, একটা container *container-এর ভেতরে* `root` user হিসেবে চলে। কোনো attacker যদি কোনো vulnerability দিয়ে তোমার app থেকে বেরিয়ে আসতে পারে, root হিসেবে চালানো থাকলে তার কাজ অনেক সহজ হয়ে যায়। Dockerfile-এ fix করো:

```dockerfile
FROM node:20-alpine
WORKDIR /app
COPY . .
RUN addgroup -S appgroup && adduser -S appuser -G appgroup
USER appuser
CMD ["node", "app.js"]
```

`USER appuser` এর পরের প্রতিটা instruction — এবং চলমান container নিজেই — সেই non-root user-এ পাল্টে দেয়। অনেক official image-এ (যেমন recent `node` আর `postgres` image) আগে থেকেই একটা readymade non-root user থাকে; নিজে বানানোর আগে Docker Hub-এ সেই image-এর documentation দেখে নাও।

## ৩. কখনো image-এর ভেতরে secret রেখো না

```dockerfile
# এরকম করো না:
ENV DB_PASSWORD=supersecret123
COPY .env .
```

`ENV` বা `COPY` দিয়ে একটা image-এর ভেতরে যা কিছু রাখা হয়, যে কেউ `docker history` বা `docker inspect` চালাতে পারে সে সেটা দেখতে পারবে — পরের একটা layer-এ "remove" করলেও, সেটা প্রায়ই আগের layer-এ এখনও বসে থাকে (Chapter 3 থেকে মনে করো: layer গুলো একটা স্তুপ, একটা merge করা file না)। তার বদলে:

- Secret **runtime**-এ দাও, build time-এ না: `docker run -e DB_PASSWORD=secret ...`, বা Compose-এ একটা `environment:` block যা একটা `.env` file থেকে আসে যা কখনো git-এ commit হয় না।
- Production-এ আরও sensitive কিছুর জন্য, plain environment variable-এর বদলে [Docker secrets](https://docs.docker.com/engine/swarm/secrets/) বা তোমার hosting platform-এর secret manager ব্যবহার করো।

## ৪. আরও কিছু ছোট ভালো অভ্যাস

- **নির্দিষ্ট version pin করো** (`node:20.11-alpine`, `node:latest` না) — Chapter 3-এ কারণ বলা আছে।
- **Instruction গুলো least-to-most-frequently-changing order-এ সাজাও** — Chapter 4-এ caching-এর কারণ বলা আছে।
- **`.dockerignore` ব্যবহার করো** `node_modules`, `.git`, `vendor`-কে build context-এর বাইরে রাখতে।
- **`ADD`-এর বদলে `COPY` ব্যবহার করো**, যদি না `ADD`-এর extra feature (tar file auto-extract করা, remote URL থেকে fetch করা) specifically লাগে — `COPY` সহজ এবং predictable, আর সাধারণত এটাই যথেষ্ট।
- **একটা `HEALTHCHECK` যোগ করো**, যাতে Docker (এবং Compose) আসলেই বলতে পারে তোমার app কাজ করছে কিনা, শুধু "চলছে" তা না:
  ```dockerfile
  HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost:3000/ || exit 1
  ```
  Chapter 6-এর `depends_on` nuance ঠিক এই দিকেই ইঙ্গিত করছিল — একটা healthcheck Compose-কে একটা real signal দেয় অপেক্ষা করার জন্য, শুধু "container চালু হয়েছে" তা না।

## ৫. Debugging: কিছু ভেঙে গেলে তোমার toolkit

```bash
docker logs my-container          # container যা প্রিন্ট করেছে সব দেখা
docker logs -f my-container       # live logs follow করা, `tail -f`-এর মতো
docker logs --tail 50 my-container  # শুধু শেষ ৫০ লাইন
```

```bash
docker exec -it my-container sh   # ভেতরে shell নিয়ে ঘুরে দেখা
docker inspect my-container       # পুরো JSON dump: env variable, mount, network, exit code, সব কিছু
```

```bash
docker ps -a                      # সব container দেখা, stopped/crashed গুলোও
docker inspect --format='{{.State.ExitCode}}' my-container   # শুধু exit code
```

### Exit code পড়া

| Exit code | সাধারণত মানে |
|---|---|
| `0` | পরিষ্কারভাবে exit হয়েছে — process ইচ্ছাকৃতভাবে তার কাজ শেষ করেছে |
| `1` | একটা general application error — `docker logs` দেখো |
| `137` | Killed হয়েছে — প্রায়ই **OOMKilled** (memory শেষ হয়ে গেছে) |
| `139` | Process-এর ভেতরে segmentation fault |
| `143` | একটা graceful stop signal পেয়েছে (যেমন `docker stop`) |

`137` দেখলে, প্রথমে `docker inspect my-container` চালিয়ে `"OOMKilled": true` খুঁজো — সমাধান সাধারণত container-কে বেশি memory দেওয়া, বা app-এর ভেতরে memory leak খুঁজে বের করা।

### Common error message আর তার আসল মানে

| Error | আসলে কী হচ্ছে |
|---|---|
| `port is already allocated` | তোমার মেশিনে অন্য কিছু আগে থেকেই সেই host port ব্যবহার করছে — `-p` mapping পরিবর্তন করো বা অন্য process বন্ধ করো |
| `Cannot connect to the Docker daemon` | Docker service চলছে না, বা তোমার user `docker` group-এ নেই (Chapter 2) |
| `no such file or directory` (build করার সময়) | একটা `COPY` instruction এমন ফাইল reference করছে যা তোমার build context-এ নেই — `.dockerignore` এবং তোমার folder structure চেক করো |
| `exec format error` | Image টা যে CPU architecture-এ build করা হয়েছে, তুমি সেটা অন্য architecture-এ চালাচ্ছো (Apple Silicon Mac-এ `amd64` image pull করলে এটা common, বা উল্টোটা) |

### মেশিন পরিষ্কার রাখা

Image, container, আর volume যা তোমার আর লাগে না, সেগুলো নিঃশব্দে disk space খায়। মাঝে মাঝে চেক করে পরিষ্কার করো:

```bash
docker system df       # image/container/volume কতটা space নিচ্ছে দেখা
docker system prune    # বন্ধ হয়ে যাওয়া container, অব্যবহৃত network, dangling image remove করা
docker stats           # প্রতিটা running container-এর CPU/memory usage live দেখা
```

## ৬. মূল কথাগুলো (Key Takeaways)

- Multi-stage build heavy build tool-কে তোমার final image-এর বাইরে রাখে — image size প্রায়ই ৮০% বা তার বেশি কমে যায়।
- Container non-root user হিসেবে চালাও; কখনো `ENV` বা `COPY` দিয়ে image-এর ভেতরে secret রাখবে না।
- Version pin করো, caching-এর জন্য instruction সাজাও, `.dockerignore` ব্যবহার করো, `ADD`-এর বদলে `COPY` ব্যবহার করো, একটা `HEALTHCHECK` যোগ করো।
- `docker logs`, `docker exec`, আর `docker inspect` — তোমার তিনটা প্রধান debugging টুল।
- Exit code `137` প্রায় সবসময় মানে container অতিরিক্ত memory ব্যবহার করার কারণে killed হয়েছে (OOMKilled)।
- `docker system prune` আর `docker system df` তোমার disk-কে অব্যবহৃত image/container দিয়ে নিঃশব্দে ভরে যাওয়া থেকে বাঁচায়।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. Multi-stage build কেন single-stage-এর চেয়ে ছোট final image বানায়?</strong></summary>

কারণ শুধু final stage-ই আসলে ship হয়। আগের stage গুলোতে (যেখানে compiler, build tool, পুরো dev dependencies থাকতে পারে) শুধু build output বানানোর জন্য ব্যবহার করা হয়, আর তুমি explicitly `COPY --from=` দিয়ে যা নেও তা ছাড়া বাকি সব ফেলে দেওয়া হয়।
</details>

<details>
<summary><strong>প্রশ্ন ২. Dockerfile-এ `COPY .env .` কেন একটা security ভুল বলে ধরা হয়?</strong></summary>

কারণ সেই `.env` ফাইলের content একটা image layer-এ বেক হয়ে যায়, যে কেউ `docker history` বা image inspect করতে পারে সে সেটা দেখতে পারবে — পরে কোনো instruction দিয়ে ফাইল delete করলেও, সেই ফাইল সহ layer-টা প্রায়ই নিচে থেকে যায়। Secret build time-এর বদলে runtime-এ দেওয়া উচিত (যেমন `docker run -e`)।
</details>

<details>
<summary><strong>প্রশ্ন ৩. একটা container exit code 137 নিয়ে বন্ধ হয়ে গেছে — প্রথমে কী চেক করবে?</strong></summary>

এটার উপর `docker inspect` চালিয়ে `"OOMKilled": true` খুঁজো। Exit code 137 প্রায় সবসময় মানে container তার অনুমোদিত memory-র চেয়ে বেশি ব্যবহার করার কারণে killed হয়েছে — সমাধান সাধারণত memory limit বাড়ানো বা app-এর memory leak ঠিক করা।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `docker logs` আর `docker exec -it ... sh`-এর practical পার্থক্য কী?</strong></summary>

`docker logs` দেখায় container আগে কী প্রিন্ট করেছে — কী হয়েছে তা দেখার জন্য useful। `docker exec -it ... sh` এখনই running container-এর ভেতরে একটা active shell দেয় — ঘুরে দেখা, ফাইল চেক করা, বা live কিছু test করার জন্য useful।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official docs: [Dockerfile best practices](https://docs.docker.com/build/building/best-practices/)
- Official docs: [Multi-stage builds](https://docs.docker.com/build/building/multi-stage/)
- Official docs: [`docker system prune` reference](https://docs.docker.com/reference/cli/docker/system/prune/)

---

**পরের চ্যাপ্টার:** Chapter 8 — Real Project: একটা Laravel App Dockerize করা *(শীঘ্রই আসছে)*
