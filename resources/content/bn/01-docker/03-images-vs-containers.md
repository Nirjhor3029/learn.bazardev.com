# Chapter 3 — Images vs Containers (হাতে-কলমে)

> **Course:** Docker (Zero to Hero) · **Chapter:** 3 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/docker/03-images-vs-containers)

Chapter 1-এ শিখেছো concept-টা: image = recipe, container = সেই recipe দিয়ে বানানো খাবার। এখন commands নিয়ে হাতে-কলমে কাজ করি, আর একটু ভালোভাবে দেখি একটা image ভেতরে আসলে *কীভাবে* বানানো হয়, যাতে কিছুই "ম্যাজিক" মনে না হয়।

## ১. একটা ছবি হাজার কথার সমান

নিচে দেখো image আর container ভেতরে আসলে কেমন দেখায়:

![Docker image layers diagram](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/01-docker/images/docker-image-layers.svg)

একটা image-কে ভাবো **একটার উপর একটা রাখা স্বচ্ছ (transparent) কাগজের শীটের মতো**:

- সবার নিচের শীট হলো base operating system (যেমন Ubuntu)।
- তার উপরের শীটে শুধু সেই *পরিবর্তনগুলো* আছে, যা dependencies install করার জন্য লেগেছে।
- তার উপরের শীটে শুধু সেই *পরিবর্তনগুলো* আছে, যা তোমার app-এর code যোগ করার জন্য লেগেছে।

প্রতিটা শীটকে বলে একটা **layer**। একটা layer পুরো filesystem আবার সংরক্ষণ করে না — শুধু নিচের layer থেকে *কী পরিবর্তন হলো* সেটাই রাখে। সবগুলো একসাথে স্তুপ করলে পুরো image পাওয়া যায়।

Image যখন **run** করো, Docker আরেকটা শীট উপরে যোগ করে — এটাই একমাত্র শীট যেখানে লেখা যায়। এটাই তোমার **container**। এর নিচে যা আছে (image) সব frozen/read-only থাকে; শুধু এই সবার উপরের শীটটাই container চলার সময় পরিবর্তন হয়। Container delete করলে, সেই উপরের শীটটা হারিয়ে যায় — নিচের image অক্ষত থাকে।

**এটা practically কেন গুরুত্বপূর্ণ:** দুটো image যদি একই `ubuntu:22.04` থেকে শুরু হয়, Docker সেই base layer-টা *একবারই* download করবে এবং দুটোর জন্যই reuse করবে। এই কারণেই, তোমার কাছে আগে থেকে থাকা image-এর সাথে base শেয়ার করা দ্বিতীয় একটা image pull করা প্রায়ই অনেক দ্রুত হয়।

এই অংশটা (images, layers, এবং নিচের commands) step by step দেখতে চাইলে, এই ভিডিওতে বিস্তারিত দেখানো আছে:

<iframe width="560" height="315" src="https://www.youtube.com/embed/3c-iBn73dDE" title="Docker Tutorial for Beginners - TechWorld with Nana" frameborder="0" allowfullscreen></iframe>

*(এই video ইংরেজিতে, কিন্তু visual demonstration বোঝার জন্য ভাষা কোনো সমস্যা না — commands আর diagram দেখেই বুঝে যাবে।)*

## ২. Image নিয়ে কাজ করার commands

```bash
docker images
```
লোকালে থাকা সব image-এর লিস্ট দেখায়।

```bash
docker pull nginx:1.27
```
একটা image-এর নির্দিষ্ট version (**tag**) download করে। Tag না দিলে Docker `latest` ব্যবহার করে — কিন্তু `latest` আসলে কেউ একজন বেছে দেওয়া একটা label মাত্র, সবসময় সবচেয়ে নতুন version না-ও হতে পারে, তাই real project-এ নির্দিষ্ট tag ব্যবহার করা safer।

```bash
docker history nginx:1.27
```
সেই image যেসব layer নিয়ে বানানো, সেগুলো যে ক্রমে build হয়েছে সেই ক্রমে দেখায় — এটাই উপরের diagram-এর command version।

```bash
docker inspect nginx:1.27
```
Image নিয়ে বিস্তারিত metadata JSON আকারে দেখায় — layers, environment variables, exposed ports, আরও অনেক কিছু।

```bash
docker rmi nginx:1.27
```
একটা image remove করে (শুধু তখনই কাজ করবে যদি কোনো container সেটা ব্যবহার না করে — আগে সেই container গুলো stop/remove করো)।

```bash
docker image prune
```
**Dangling images** পরিষ্কার করে — পুরোনো build থেকে পড়ে থাকা layer, যেগুলো এখন কেউ ব্যবহার করে না। মাঝে মাঝে চালানো ভালো অভ্যাস, যাতে disk space ভরে না যায়।

## ৩. Container নিয়ে কাজ করার commands

Chapter 2 থেকে `docker run`, `docker ps`, `docker stop`, আর `docker rm` তো জানোই। আরও কয়েকটা গুরুত্বপূর্ণ command:

```bash
docker exec -it my-nginx bash
```
একটা running container-এর **ভেতরে** একটা interactive shell খোলে — যেন তুমি SSH করে ঢুকেছো। Debug করার সময় বা কিছু দেখার জন্য extremely useful। (`-it` মানে interactive + একটা terminal attach করা; এটা না দিলে command চলে সাথে সাথে ফিরে আসতো, shell দিতো না।)

```bash
docker cp my-nginx:/etc/nginx/nginx.conf ./nginx.conf
```
একটা running container থেকে ফাইল তোমার মেশিনে কপি করে (বা উল্টোটাও করা যায়, মেশিন থেকে container-এ)।

```bash
docker restart my-nginx
```
Container বন্ধ করে আবার চালু করে — mounted config file পরিবর্তন করার পর কাজে লাগে।

```bash
docker pause my-nginx
docker unpause my-nginx
```
Container বন্ধ না করেই তার ভেতরের সব process freeze করে দেয় — দৈনন্দিন কাজে কম লাগে, কিন্তু এটা আছে জানা ভালো।

## ৪. নিজে করে দেখো

```bash
docker run -d -p 8080:80 --name my-nginx nginx
docker exec -it my-nginx bash
# এখন তুমি container-এর ভেতরে
cat /etc/os-release
exit
# এখন তুমি আবার নিজের মেশিনে
docker history nginx
docker rm -f my-nginx
```

Container-এর ভেতরে `cat /etc/os-release` চালালে দেখবে nginx-এর image যেই base OS-এর উপর বানানো, সেটা দেখাচ্ছে — তুমি মাত্র উপরের diagram-এর Layer 1 সরাসরি দেখলে।

## ৫. মূল কথাগুলো (Key Takeaways)

- Image হলো read-only **layer**-এর একটা স্তুপ; প্রতিটা layer শুধু নিচের layer থেকে যা পরিবর্তন হয়েছে সেটাই রাখে।
- Container একটা image-এর উপরে একটা writable layer যোগ করে — container delete করলে শুধু সেই উপরের layer-টাই হারিয়ে যায়।
- দুটো image-এর মধ্যে শেয়ার করা base layer একবারই download হয় এবং reuse হয় — এই কারণেই Docker pull প্রায়ই আশার চেয়ে দ্রুত হয়।
- `docker exec -it <container> bash` দিয়ে running container-এর ভেতরে shell পাওয়া যায় — debug করার প্রধান টুল।
- `docker image prune` অব্যবহৃত, dangling image layer পরিষ্কার করে দেয়।

## ৬. নিজেকে যাচাই করো (Exam Prep)

<details>
<summary><strong>প্রশ্ন ১. দুটো image একই base layer ব্যবহার করলেও Docker কেন সেটা একবারই download করে?</strong></summary>

কারণ layer গুলো content-addressed এবং disk-এ শেয়ার করা থাকে। একটা image-এর যদি এমন layer লাগে যা Docker-এর কাছে আগে থেকেই আছে (যেমন একই `ubuntu:22.04` base), তাহলে নতুন করে download না করে আগের কপিটাই reuse করে।
</details>

<details>
<summary><strong>প্রশ্ন ২. Container remove করলে তার writable layer-এর কী হয়?</strong></summary>

Container-এর সাথে সাথে সেটাও delete হয়ে যায়। নিচের image (তার read-only layer গুলো) অক্ষত থাকে এবং যখন চাও তখন সেটা দিয়ে একটা সম্পূর্ণ নতুন container চালু করা যায়।
</details>

<details>
<summary><strong>প্রশ্ন ৩. `docker exec -it my-nginx bash` আসলে কী করে?</strong></summary>

এটা already running `my-nginx` container-এর ভেতরে `bash` চালিয়ে একটা interactive (`-i`) session শুরু করে, সাথে একটা terminal attach করে (`-t`) — ফলে সেই container-এর ভেতরে একটা shell পাওয়া যায়, SSH করার মতোই।
</details>

<details>
<summary><strong>প্রশ্ন ৪. `nginx:latest`-এর বদলে `nginx:1.27`-এর মতো নির্দিষ্ট tag ব্যবহার করা কেন safer?</strong></summary>

`latest` আসলে একটা label মাত্র — যে image বানিয়েছে সে ঠিক করে এটা কোন version-কে point করবে, এবং সময়ের সাথে এটা পরিবর্তন হতে পারে। নির্দিষ্ট version tag দিলে সবসময় একই image পাওয়া যায়, যা `latest` আপডেট হওয়ার কারণে অপ্রত্যাশিত সমস্যা এড়ায়।
</details>

## ৭. ফ্রি রিসোর্স (English)

- Official docs: [Understanding image layers](https://docs.docker.com/guides/docker-concepts/building-images/understanding-image-layers/)
- Official docs: [`docker exec` reference](https://docs.docker.com/reference/cli/docker/container/exec/)
- Video: **"Docker Tutorial for Beginners [FULL COURSE in 3 Hours]"** — *TechWorld with Nana* (উপরে embed করা আছে)

---

**পরের চ্যাপ্টার:** Chapter 4 — Dockerfile: নিজের Image লেখা *(শীঘ্রই আসছে)*
