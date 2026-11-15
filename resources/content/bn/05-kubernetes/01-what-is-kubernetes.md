# Chapter 1 — Kubernetes কী, আর এটা কেন গুরুত্বপূর্ণ?

> **Course:** Kubernetes · **Chapter:** 1 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/kubernetes/01-what-is-kubernetes)

Docker course-এ তুমি একটা app container-এ package করেছ আর Docker Compose দিয়ে চালিয়েছ। এটা **একটা server**-এ ভালো কাজ করে। এই course শুরু হয় ঠিক সেখান থেকে যেখানে ওটা থেমে যায়: যখন তোমার *অনেক* container, *অনেক* server-এ, আর app down হওয়া তুমি সহ্য করতে পারো না — তখন কী হয়?

## ১. Kubernetes যে সমস্যাটা সমাধান করে

ধরো তোমার Laravel shop এখন জনপ্রিয়। তোমার দরকার:

- web container-এর **৩টা copy**, যাতে একটা crash করলে site বন্ধ না হয়।
- ওই copy-গুলো **কয়েকটা server**-এ ছড়ানো, যাতে একটা server মরলেও site না মরে।
- **Downtime ছাড়া** নতুন version release, আর ভাঙা হলে সঙ্গে সঙ্গে আগের অবস্থায় ফেরার উপায়।
- ব্যস্ত দিনে নিজে থেকে বাড়তি copy যোগ হওয়া, আর রাতে কমে যাওয়া।

SSH দিয়ে হাতে `docker run` করে এটা করলে তুমি নিজেই scheduler হয়ে যাও: কোন server-এ কী চলবে তুমি ঠিক করো, crash তুমি খেয়াল করো, restart তুমি করো, আর server একটা একটা করে update করো। কয়েকটা container-এর বেশি হলে এটা আর চলে না।

**Kubernetes** (প্রায়ই **K8s** লেখা হয় — "K", তারপর ৮টা অক্ষর, তারপর "s") একটা open-source system যা এই কাজ তোমার হয়ে করে। এটা Google-এ শুরু হয়েছিল, এখন Cloud Native Computing Foundation (CNCF) এটা maintain করে। তুমি বলো *কী চাও*; ও বের করে *কীভাবে*, আর সেটা ঠিক রাখে।

## ২. এটা তোমার জন্য কী কী করে

- **Scheduling** — free CPU আর memory দেখে ঠিক করে কোন server-এ (node) কোন container চলবে।
- **Self-healing** — crash করা container restart করে; একটা পুরো server মরলে সেই container-গুলো আরেকটা সুস্থ server-এ আবার চালু করে।
- **Scaling** — "আমার ৫টা copy চাই" মানে একটা লাইন, বদলাতেও একটা লাইন।
- **Rolling update ও rollback** — পুরোনো copy ধীরে ধীরে নতুনটা দিয়ে বদলায়, আর চাইলে undo করা যায়।
- **Service discovery ও load balancing** — একদল container-কে একটা স্থির নাম দেয় আর traffic ভাগ করে দেয়।
- **Config ও secret** — setting আর password তোমার image-এর বাইরে রাখে।

## ৩. মূল ধারণা: তুমি declare করো, Kubernetes reconcile করে

Kubernetes-কে তুমি command-এর তালিকা দাও না। তুমি দাও **কাঙ্ক্ষিত অবস্থার বর্ণনা (desired state)**, সাধারণত একটা YAML file-এ:

```yaml
# "আমার এই app-এর ৩টা copy চলুক।"
replicas: 3
```

তারপর Kubernetes একটা loop চালায় সারাক্ষণ: *যা আছে আর যা চেয়েছ তা মিলিয়ে দেখো; না মিললে ঠিক করো।* ৩টার একটা copy মেরে ফেললে কয়েক সেকেন্ডে নতুন একটা আসে, কারণ আসল অবস্থা (২) আর কাঙ্ক্ষিত অবস্থা (৩) আর মিলছে না। একে বলে **declarative** configuration, আর এই পুরো course-এর সবচেয়ে গুরুত্বপূর্ণ ধারণা এটাই।

## ৪. Architecture, এক ছবিতে

![Kubernetes cluster architecture: control plane and worker nodes](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/05-kubernetes/images/kubernetes-architecture.svg)

একসাথে পরিচালিত server-এর একটা দলকে বলে **cluster**। এর দুই ধরনের অংশ আছে:

**Control plane (মস্তিষ্ক)** ঠিক করে কী হওয়া উচিত:

- **kube-apiserver** — সদর দরজা। `kubectl` আর অন্য সব অংশ cluster-এর সাথে এর মাধ্যমেই কথা বলে।
- **etcd** — cluster-এর database: কাঙ্ক্ষিত আর বর্তমান দুই অবস্থাই এখানে থাকে।
- **kube-scheduler** — নতুন একটা Pod কোন node-এ চলবে তা বেছে নেয়।
- **kube-controller-manager** — সেই loop-গুলো চালায় যা "আসল ≠ কাঙ্ক্ষিত" ধরে আর ঠিক করে।

**Worker node (পেশি)** হলো সেই server-গুলো যা তোমার container চালায়। প্রতিটাতে আছে:

- **kubelet** — একটা agent যে control plane-এর আদেশ নেয় আর এই node-এ ঠিক container-গুলো চলছে কিনা নিশ্চিত করে।
- **একটা container runtime** (যেমন containerd) — যা সত্যিকারের container চালায়। Docker থেকে ধারণাটা তুমি জানোই।

তোমার container চলে **Pod**-এর ভেতরে, যা Kubernetes-এর পরিচালনা করা সবচেয়ে ছোট একক (Chapter 3-এ ঠিকমতো আসবে)।

## ৫. তোমার কি আসলেই এটা লাগে?

সৎ উত্তর — প্রায়ই এখনো না। Kubernetes আসল জটিলতা নিয়ে আসে। তোমার app যদি Docker Compose দিয়ে একটা VPS-এ ভালোভাবে চলে (বেশিরভাগ ছোট project-এর মতো), সেটা পুরোপুরি ভালো একটা পছন্দ। Kubernetes-এর খরচ উসুল হতে শুরু করে যখন তোমার কয়েকটা server, zero-downtime release, নিজে থেকে recovery লাগে, অথবা তুমি এমন team বা company-তে কাজ করো যারা আগে থেকেই এটা চালায়। যেকোনোভাবেই এটা শেখা মূল্যবান, কারণ বড় scale-এ container চালানোর industry standard এটাই।

দুইভাবে ব্যবহার করা যায়: নিজের cluster চালানো, অথবা ভাড়া করা **managed** cluster (Amazon EKS, Google GKE, Azure AKS ইত্যাদি), যেখানে control plane provider-ই চালায়।

## ৬. মূল কথাগুলো

- Kubernetes (K8s) অনেক server জুড়ে container চালায় ও পরিচালনা করে, যাতে তোমাকে scheduler হতে না হয়।
- এটা দেয় scheduling, self-healing, scaling, rolling update, service discovery আর config/secret সামলানো।
- তুমি **desired state declare** করো; Kubernetes সারাক্ষণ বাস্তবকে তার সাথে মিলিয়ে চলে।
- একটা **cluster** = একটা control plane (apiserver, etcd, scheduler, controller-manager) + worker node (kubelet, container runtime, Pod)।
- সবসময় এটা লাগে না: অনেক app-এর জন্য এক server আর Docker Compose-ই যথেষ্ট।

## ৭. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. Kubernetes-এ "declarative" মানে কী?</strong></summary>

তুমি চালানোর command-এর তালিকা না দিয়ে চাওয়া অবস্থাটা বর্ণনা করো (যেমন app-এর ৩টা copy)। Kubernetes সারাক্ষণ আসল অবস্থা ওই বর্ণনার সাথে মিলিয়ে দেখে আর যেকোনো পার্থক্য ঠিক করে।
</details>

<details>
<summary><strong>Q2. তিনটা চলমান copy-র একটা delete করলে কী হয়, আর কেন?</strong></summary>

কয়েক সেকেন্ডের মধ্যে একটা নতুন copy চালু হয়। কাঙ্ক্ষিত অবস্থা ৩, আর আসল অবস্থা হয়ে গেল ২, তাই একটা controller পার্থক্যটা দেখে একটা বদলি বানায়।
</details>

<details>
<summary><strong>Q3. Control plane-এর কোন অংশ cluster-এর state রাখে, আর কোনটা নতুন Pod-এর জন্য node বাছে?</strong></summary>

`etcd` cluster-এর state (কাঙ্ক্ষিত ও বর্তমান) রাখে। `kube-scheduler` ঠিক করে নতুন Pod কোন node-এ চলবে।
</details>

<details>
<summary><strong>Q4. তোমার ছোট blog একটা VPS-এ Docker Compose দিয়ে ভালো চলছে। Kubernetes কি লাগবে?</strong></summary>

সম্ভবত না। Kubernetes আসল জটিলতা আনে; এটা কাজে আসে যখন কয়েকটা server, zero-downtime release বা নিজে থেকে recovery লাগে। ছোট app-এর জন্য এক server আর Docker Compose ভালো পছন্দ।
</details>

## ৮. বিনামূল্যের resource

- Official: [Kubernetes overview](https://kubernetes.io/docs/concepts/overview/)
- Official: [Kubernetes components](https://kubernetes.io/docs/concepts/overview/components/)
- Official, browser-এই hands-on: [Learn Kubernetes Basics](https://kubernetes.io/docs/tutorials/kubernetes-basics/)

---

**Next:** Chapter 2 — Local cluster, `kubectl`, আর তোমার প্রথম Pod *(coming soon)*
