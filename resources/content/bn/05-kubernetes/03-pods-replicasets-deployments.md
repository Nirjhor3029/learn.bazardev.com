# Chapter 3 — Pod, ReplicaSet আর Deployment

> **Course:** Kubernetes · **Chapter:** 3 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/kubernetes/03-pods-replicasets-deployments)

Chapter 2-এর শেষে আমরা একটা ফাঁক দেখেছিলাম: একা Pod delete করলে সেটা চিরতরে চলে যায়। এই chapter-এ আমরা সেটা ঠিক করব বাস্তব জীবনে সবচেয়ে বেশি ব্যবহৃত object দিয়ে — **Deployment**।

## ১. তিনটা স্তর

Kubernetes তিনটা object একটার ওপর একটা সাজায়, প্রতিটা একটা করে ক্ষমতা যোগ করে:

| Object | কী যোগ করে |
|---|---|
| **Pod** | তোমার container(গুলো) চালায়। মরে যেতে পারে, বদলে বসানো হয় না। |
| **ReplicaSet** | *N টা একই রকম Pod* চালু রাখে। একটা delete করলে আরেকটা বানায়। |
| **Deployment** | ReplicaSet পরিচালনা করে, যাতে নিরাপদে **update** আর **rollback** করা যায়। |

ReplicaSet তুমি প্রায় কখনোই নিজে বানাও না। তুমি **Deployment** বানাও, Deployment বানায় ReplicaSet, আর ReplicaSet বানায় Pod।

![Deployment একটা ReplicaSet-এর মালিক, যে Pod-গুলোর মালিক](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/05-kubernetes/images/deployment-replicaset-pods.svg)

## ২. তোমার প্রথম Deployment

এটা `deployment.yaml` নামে save করো:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: web
spec:
  replicas: 3
  selector:
    matchLabels:
      app: web
  template:
    metadata:
      labels:
        app: web
    spec:
      containers:
        - name: nginx
          image: nginx:1.27
          ports:
            - containerPort: 80
```

Apply করো আর দেখো:

```bash
kubectl apply -f deployment.yaml
kubectl get deployments
kubectl get pods
```

তিনটা Pod দেখবে, নাম অনেকটা `web-<hash>-<random>`।

ফাইলটা একবার ধীরে পড়ো:

- `replicas: 3` — **কাঙ্ক্ষিত অবস্থা**: "আমি ৩টা Pod চাই।"
- `template` — প্রতিটা Pod-এর নকশা (Chapter 2-এর Pod YAML-এর মতোই দেখতে)।
- template-এ `labels: app: web` আর `selector.matchLabels: app: web` — এভাবেই Deployment *নিজের* Pod-গুলো খুঁজে পায়। **দুটো মিলতে হবে।**

## ৩. Label আর selector

**Label** হলো object-এর গায়ে ছোট key/value ট্যাগ, যেমন `app: web` বা `env: prod`। **Selector** হলো label-এর query: "`app=web` আছে এমন সবকিছু দাও।"

Kubernetes label দিয়েই object-গুলো জোড়ে। Deployment label দিয়ে তার Pod খোঁজে। Chapter 4-এ Service-ও label দিয়ে Pod খুঁজবে। Label-এ বানান ভুল হলে ওরা একে অপরকে পাবে না — আর কোনো error-ও দেখাবে না, তাই কিছু "জুড়ছে না" মনে হলে আগে label দেখো।

```bash
kubectl get pods --show-labels
kubectl get pods -l app=web         # label দিয়ে ছাঁকা
```

## ৪. এবার সত্যিকারের self-healing

Chapter 2-এ যে পরীক্ষাটা ব্যর্থ হয়েছিল, সেটা আবার করো:

```bash
kubectl get pods                      # একটা Pod-এর নাম copy করো
kubectl delete pod web-xxxxxxxxxx-yyyyy
kubectl get pods                      # নতুন Pod ইতিমধ্যে শুরু হচ্ছে
```

ReplicaSet দেখল "কাঙ্ক্ষিত ৩, আসল ২" আর একটা বদলি বানাল। এই loop — *কাঙ্ক্ষিত আর আসলের তুলনা করো, পার্থক্য ঠিক করো* — Kubernetes-এর প্রাণ।

## ৫. Scaling

এক command-এ Pod-এর সংখ্যা বদলাও:

```bash
kubectl scale deployment web --replicas=5
kubectl get pods
```

অথবা, আসল project-এর জন্য আরও ভালো: YAML-এ `replicas:` বদলাও আর আবার `kubectl apply -f deployment.yaml` চালাও। তাহলে ফাইলটাই সত্যের উৎস থাকে, আর Git course-এ শেখার মতো তুমি এটা Git-এ রাখতে পারো।

## ৬. Rolling update

তুমি nginx upgrade করতে চাও। YAML-এ image বদলাও:

```yaml
          image: nginx:1.28
```

```bash
kubectl apply -f deployment.yaml
kubectl rollout status deployment/web
```

Deployment সবকিছু একবারে মেরে ফেলে না। সে একটা **নতুন ReplicaSet** বানায়, নতুন Pod একটা একটা করে চালু করে, আর তারপরই পুরনোগুলো সরায়। প্রতিটা মুহূর্তে কিছু Pod traffic সামলাতে থাকে, তাই user **কোনো downtime** দেখে না। একে বলে **rolling update**, আর এটাই default strategy।

তুমি এটা ঠিক করে নিতে পারো:

```yaml
spec:
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1          # কাঙ্ক্ষিত সংখ্যার ওপরে সর্বোচ্চ ১টা বাড়তি Pod
      maxUnavailable: 0    # কাঙ্ক্ষিত সংখ্যার নিচে কখনো নামবে না
```

## ৭. Rollback

নতুন version ভাঙা? আগের অবস্থায় ফিরে যাও:

```bash
kubectl rollout history deployment/web     # revision-এর তালিকা
kubectl rollout undo deployment/web        # আগেরটায় ফেরা
```

এটা কাজ করে কারণ Deployment **পুরনো ReplicaSet**-টা (০টা Pod সহ) রেখে দেয়। Rollback শুধু পুরনোটা বাড়ায় আর নতুনটা কমায়।

## ৮. কাজের command

```bash
kubectl get deploy,rs,pods                 # তিন স্তর একসাথে
kubectl describe deployment web            # বিস্তারিত আর event
kubectl logs deployment/web                # এর একটা Pod-এর log
kubectl delete -f deployment.yaml          # এই ফাইলের বানানো সব মুছে দাও
```

## ৯. মূল কথা

- **Pod** মরে গেলে বদলানো হয় না। **ReplicaSet** N টা কপি জীবিত রাখে। **Deployment** ReplicaSet পরিচালনা করে আর update ও rollback যোগ করে।
- তুমি Deployment বানাও; ReplicaSet আর Pod আপনা-আপনি আসে।
- `replicas` হলো কাঙ্ক্ষিত অবস্থা। `kubectl scale` দিয়ে বা YAML বদলে আবার apply করে এটা বদলাও।
- **Label** আর **selector** object জোড়ে। Deployment-এর selector তার template-এর label-এর সাথে মিলতে হবে।
- **Rolling update** ধীরে ধীরে Pod বদলায়, downtime ছাড়া। `kubectl rollout undo` আগের অবস্থায় ফেরায়।

## ১০. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. ReplicaSet আর Deployment-এর পার্থক্য কী?</strong></summary>

ReplicaSet নির্দিষ্ট সংখ্যক একই রকম Pod চালু রাখে। Deployment ReplicaSet পরিচালনা করে আর rolling update ও rollback যোগ করে। সাধারণত তুমি Deployment বানাও আর সে ReplicaSet বানায়।
</details>

<details>
<summary><strong>Q2. তোমার Deployment-এ `replicas: 3` আর তুমি একটা Pod delete করলে। কী হয়, আর কেন?</strong></summary>

একটা নতুন Pod তৈরি হয়। ReplicaSet দেখে আসল সংখ্যা (২) কাঙ্ক্ষিত সংখ্যা (৩) থেকে আলাদা, আর সেটা ঠিক করে দেয়।
</details>

<details>
<summary><strong>Q3. Deployment কীভাবে জানে কোন Pod তার?</strong></summary>

তার `selector.matchLabels` দিয়ে, যা Pod template-এর label-এর সাথে মিলতে হবে।
</details>

<details>
<summary><strong>Q4. তুমি YAML-এ image বদলে `kubectl apply` চালালে। Kubernetes কীভাবে downtime এড়ায়?</strong></summary>

সে rolling update করে: নতুন ReplicaSet বানায়, নতুন Pod ধীরে ধীরে চালু করে, আর নতুনগুলো ready হলেই কেবল পুরনোগুলো সরায়।
</details>

<details>
<summary><strong>Q5. নতুন version-এ bug আছে। কোন command আগের version-এ ফেরায়?</strong></summary>

`kubectl rollout undo deployment/<name>`
</details>

## ১১. বিনামূল্যের resource

- Official: [Deployments](https://kubernetes.io/docs/concepts/workloads/controllers/deployment/)
- Official: [ReplicaSet](https://kubernetes.io/docs/concepts/workloads/controllers/replicaset/)
- Official: [Labels and Selectors](https://kubernetes.io/docs/concepts/overview/working-with-objects/labels/)
- Official tutorial: [Performing a Rolling Update](https://kubernetes.io/docs/tutorials/kubernetes-basics/update/update-intro/)

---

**Next:** Chapter 4 — Service আর Networking *(coming soon)*
