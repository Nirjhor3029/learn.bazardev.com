# Chapter 4 — Dockerfile: নিজের Image লেখা

> **Course:** Docker (Zero to Hero) · **Chapter:** 4 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/docker/04-dockerfile)

এতদিন শুধু অন্যের বানানো image ব্যবহার করেছো (`nginx`, `hello-world`)। আজ নিজেই একটা বানাবে। একটা **Dockerfile** আসলে শুধু একটা text file, যেখানে image বানানোর জন্য step-by-step instructions লেখা থাকে — Chapter 1-এর সেই "recipe"-টাই, লিখিত আকারে।

## ১. যে instructions গুলো আসলে ব্যবহার করবে

| Instruction | কী করে |
|---|---|
| `FROM` | যেই base image থেকে শুরু হবে (তোমার সবার নিচের layer) |
| `WORKDIR` | পরের instructions-এর জন্য image-এর ভেতরে "current folder" সেট করে |
| `COPY` | তোমার মেশিন থেকে ফাইল image-এর ভেতরে কপি করে |
| `RUN` | Image **build করার সময়** একটা command চালায় (যেমন package install করা) |
| `ENV` | Image-এর ভেতরে একটা environment variable সেট করে |
| `EXPOSE` | Container কোন port-এ listen করে সেটা জানায় (আসলে publish করে না — সেটা `docker run` করার সময় `-p` দিয়েই করতে হবে) |
| `CMD` | **Container চালু হওয়ার সময়** default command যা চলবে |

## ২. একটা real, minimal Dockerfile

একটা ফোল্ডারে দুটো ফাইল বানাও।

`app.js`:
```js
const http = require('http');
http.createServer((req, res) => res.end('Hello from inside a container!')).listen(3000);
console.log('Listening on port 3000');
```

`Dockerfile` (কোনো file extension নেই, ঠিক এই নামেই):
```dockerfile
FROM node:20-alpine
WORKDIR /app
COPY app.js .
EXPOSE 3000
CMD ["node", "app.js"]
```

Build করো:
```bash
docker build -t my-first-app .
```

- `-t my-first-app` — image-কে একটা **নাম (tag)** দেয়, যাতে পরে সেটা দিয়ে reference করা যায়।
- `.` — **build context**: যেই ফোল্ডারে Docker তোমার `COPY` instructions-এর ফাইল খুঁজবে। (এই কারণেই এমন ফোল্ডার থেকে `docker build` চালানো উচিত না, যেখানে অনেক অপ্রয়োজনীয় বড় ফাইল আছে — Docker আগে পুরো ফোল্ডারটা পড়ে।)

Run করো:
```bash
docker run -d -p 3000:3000 my-first-app
curl http://localhost:3000
```

দেখবে `Hello from inside a container!` — তোমার লেখা code, তোমার বানানো image-এর ভেতরে চলছে, তোমার লেখা Dockerfile থেকে। এটাই পুরো loop।

## ৩. Instruction-এর order গুরুত্বপূর্ণ — build caching

Docker একটা Dockerfile-এর প্রতিটা instruction-কে আলাদা একটা layer হিসেবে cache করে (Chapter 3-এ দেখা সেই layering-ই)। Rebuild করার সময়, যদি কোনো instruction আর তার উপরের সব কিছু অপরিবর্তিত থাকে, Docker সেটা আবার না চালিয়ে cached result reuse করে — instant, slow-এর বদলে।

![Dockerfile build cache diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/01-docker/images/dockerfile-cache.svg)

এই কারণেই real-world Dockerfile-এ **dependency install করা** আর **app code copy করা** আলাদা করে রাখা হয়:

```dockerfile
FROM node:20-alpine
WORKDIR /app
COPY package.json .
RUN npm install
COPY . .
CMD ["node", "app.js"]
```

`package.json` খুব কমই পরিবর্তন হয়, তাই `npm install` প্রায় কখনোই আবার চালাতে হয় না। শুধু `COPY . .` (এবং তার পরে যা আছে) আবার চলে যখন তুমি তোমার actual source code পরিবর্তন করো — যার ফলে rebuild অনেক বেশি fast হয়। যদি `COPY . .` কে `npm install`-এর **আগে** লিখতে, তাহলে code-এর একটা লাইন পরিবর্তন করলেও প্রতিবার সম্পূর্ণ dependency reinstall হতো।

## ৪. `CMD` vs `ENTRYPOINT` (common confusion)

দুটোই ঠিক করে container চালু হওয়ার সময় কী চলবে। Practical পার্থক্য:

- `CMD` হলো একটা **default** — সহজে override করা যায়। `docker run my-first-app node other-script.js` চালালে Dockerfile-এর `CMD` সম্পূর্ণভাবে ignore হয়ে যায়, বদলে `other-script.js` চলে।
- `ENTRYPOINT` হলো **fixed** — `docker run`-এ image-এর নামের পরে যা-ই লেখো, সেটা এটাকে replace না করে এর সাথে *যুক্ত* হয়ে যায়।

সাধারণ ছোট image-এর জন্য `CMD` একাই যথেষ্ট। `ENTRYPOINT` তখন লাগে যখন তুমি চাও container সবসময় একটা নির্দিষ্ট command-line tool-এর মতোই behave করুক, কেউ যা-ই argument দিক।

## ৫. `.dockerignore`

ঠিক `.gitignore`-এর মতো, একটা `.dockerignore` ফাইল Docker-কে বলে দেয় build করার সময় কোন ফাইল গুলো বাদ দিতে — `node_modules`, `.git`, `vendor` কে build context-এর বাইরে রাখলে build fast থাকে আর image-এর size ছোট থাকে:

```
node_modules
.git
vendor
*.log
```

## ৬. মূল কথাগুলো (Key Takeaways)

- Dockerfile হলো build instructions-এর একটা text file — `FROM`, `WORKDIR`, `COPY`, `RUN`, `EXPOSE`, `CMD`।
- `docker build -t <name> .` current ফোল্ডারের Dockerfile থেকে একটা image build করে।
- Instructions গুলো **সবচেয়ে কম** থেকে **সবচেয়ে বেশি** পরিবর্তনশীল ক্রমে সাজাও, যাতে caching আসলেই সময় বাঁচায়।
- `CMD` একটা default যা override করা যায়; `ENTRYPOINT` fixed, এতে শুধু argument যুক্ত হয়।
- `.dockerignore` অপ্রয়োজনীয় ফাইলকে build context-এর বাইরে রাখে।

## ৭. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. `docker build -t my-first-app .`-এ শেষের `.` কেন লাগে?</strong></summary>

ওটাই build context — যেই ফোল্ডার Docker daemon-কে পাঠায়, এবং যেই ফোল্ডারের সাথে Dockerfile-এর `COPY` instructions relative। এটা না থাকলে `docker build` জানতেই পারবে না কোন ফাইল গুলো image-এ কপি করা অনুমোদিত।
</details>

<details>
<summary><strong>প্রশ্ন ২. বাকি app code copy করার আগে `package.json` copy করে `npm install` চালানো হয় কেন?</strong></summary>

কারণ Docker প্রতিটা instruction-কে একটা layer হিসেবে cache করে। `package.json` পরিবর্তন না হলে, `npm install` আবার না চালিয়ে তার cached result reuse করা যায় — তাই শুধু source code পরিবর্তন করলে প্রতিবার rebuild-এ slow reinstall করতে হয় না।
</details>

<details>
<summary><strong>প্রশ্ন ৩. Dockerfile-এ `CMD ["node", "app.js"]` থাকলে, `docker run my-first-app node other.js` চালালে কী হবে?</strong></summary>

`docker run`-এ দেওয়া command `CMD`-কে সম্পূর্ণভাবে **replace** করে দেয়, তাই `app.js`-এর বদলে `other.js` চলবে। `ENTRYPOINT` ব্যবহার করলে এটা ভিন্নভাবে behave করতো — `other.js` তখন একটা argument হিসেবে যুক্ত হতো।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `EXPOSE 3000` আসলে কী করে?</strong></summary>

এটা শুধু documentation — Dockerfile পড়া কাউকে (এবং কিছু tooling-কে) জানায় container-এর ভেতরে app কোন port-এ listen করছে। এটা port কে তোমার host মেশিনে publish **করে না** — সেটার জন্য `docker run` করার সময় তখনও `-p 3000:3000` লাগবে।
</details>

## ৮. ফ্রি রিসোর্স (English)

- Official docs: [Dockerfile reference](https://docs.docker.com/reference/dockerfile/)
- Official docs: [Best practices for writing Dockerfiles](https://docs.docker.com/build/building/best-practices/)
- Video: **"Docker Tutorial for Beginners [FULL COURSE in 3 Hours]"** — *TechWorld with Nana* (এতে সম্পূর্ণ Dockerfile walkthrough আছে)

---

**পরের চ্যাপ্টার:** Chapter 5 — Volumes & Networking *(শীঘ্রই আসছে)*
