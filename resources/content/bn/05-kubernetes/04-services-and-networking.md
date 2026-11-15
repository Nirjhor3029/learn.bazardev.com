# Chapter 4 — Service আর Networking

> **Course:** Kubernetes · **Chapter:** 4 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/kubernetes/04-services-and-networking)

তোমার Deployment এখন ৩টা nginx Pod চালাচ্ছে। কিন্তু কেউ ওদের কাছে পৌঁছাবে কীভাবে? Pod মরে আর বদলে যায়, আর প্রতিটা নতুন Pod পায় **নতুন IP address**। ওই IP কোথাও hard-code করা যায় না। **Service** এই সমস্যার সমাধান।

## ১. সমস্যা: Pod-এর IP বদলাতেই থাকে

প্রতিটা Pod-এর নিজের IP আছে, কিন্তু:

- Pod বদলালে তার IP বদলে যায়;
- ৩টা replica মানে ৩টা আলাদা IP, অথচ তুমি চাও traffic ওদের মধ্যে ভাগ হোক।

**Service** একদল Pod-কে দেয় **একটা স্থির IP আর একটা স্থির DNS নাম**, আর পেছনের সুস্থ Pod-গুলোর মধ্যে request ছড়িয়ে দেয়।

![তিনটা Pod-এর সামনে একটা Service](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/05-kubernetes/images/service-routing.svg)

## ২. Service কীভাবে তার Pod খুঁজে পায়

**Label selector** দিয়ে — Chapter 3-এর একই ধারণা। Service বলে "`app=web` আছে এমন প্রতিটা Pod-এ traffic পাঠাও।"

```yaml
apiVersion: v1
kind: Service
metadata:
  name: web
spec:
  selector:
    app: web          # Pod-এর label-এর সাথে মিলতে হবে
  ports:
    - port: 80        # Service যে port-এ শোনে
      targetPort: 80  # container যে port-এ শোনে
```

```bash
kubectl apply -f service.yaml
kubectl get service web
kubectl get endpoints web      # Service-এর পেছনের আসল Pod IP
```

Service "কাজ করছে না" মনে হলে প্রথমে `kubectl get endpoints web` দেখো। তালিকা খালি মানে selector কোনো Pod-এর সাথে মেলেনি (সাধারণত label-এ বানান ভুল)।

`port` হলো client যা ব্যবহার করে। `targetPort` হলো container-এর ভেতরের port। ওরা আলাদা হতে পারে: যেমন Node app-এর জন্য `port: 80` আর `targetPort: 3000`।

## ৩. Service-এর ধরন

| Type | কোথা থেকে পৌঁছানো যায় | কখন ব্যবহার |
|---|---|---|
| **ClusterIP** (default) | শুধু cluster-এর ভেতর থেকে | ভেতরের সেবা: database, backend API |
| **NodePort** | বাইরে থেকে `<node-ip>:<port>` (port ৩০০০০–৩২৭৬৭) | দ্রুত পরীক্ষা, সরল setup |
| **LoadBalancer** | বাইরে থেকে, cloud load balancer-এর public IP দিয়ে | Cloud provider-এ public app |

প্রতিটা type আগেরটার ওপর তৈরি: LoadBalancer-এও NodePort আর ClusterIP থাকে।

```yaml
spec:
  type: NodePort
  selector:
    app: web
  ports:
    - port: 80
      targetPort: 80
      nodePort: 30080     # ঐচ্ছিক; না দিলে Kubernetes একটা বেছে নেয়
```

`LoadBalancer`-এর জন্য cloud লাগে (AWS, GCP, সমর্থনকারী VPS provider)। Laptop-এ এটা `<pending>` থেকে যায়। minikube-এর জন্য একটা shortcut আছে:

```bash
minikube service web --url      # browser-এ খোলার মতো একটা URL দেয়
```

## ৪. Cluster-এর ভেতরে DNS

Pod-দের কোনো IP জানতেই হয় না। Kubernetes একটা DNS service চালায়, আর প্রতিটা Service একটা নাম পায়:

```
<service-name>.<namespace>.svc.cluster.local
```

একই namespace-এর যেকোনো Pod থেকে শুধু ছোট নামটাই ব্যবহার করা যায়:

```bash
kubectl run tmp --rm -it --image=busybox:1.36 -- wget -qO- http://web
```

তোমার Laravel Pod-এর MySQL লাগলে IP-র বদলে `DB_HOST=mysql` (Service-এর নাম) দাও। এই course-এর শেষ project-এ app-গুলো এভাবেই একে অপরকে খুঁজে পাবে।

## ৫. Namespace (যতটুকু দরকার)

**Namespace** হলো একটা cluster-এর ভেতরে object গুছিয়ে রাখার folder (যেমন `dev`, `staging`, `prod`)। নাম শুধু একটা namespace-এর ভেতরে অনন্য হলেই চলে।

```bash
kubectl get namespaces
kubectl create namespace dev
kubectl get pods -n dev                 # -n = কোন namespace
kubectl get pods --all-namespaces
```

`-n` না দিলে `kubectl` `default` নামের namespace ব্যবহার করে। অন্য namespace-এর Service-এ পৌঁছাতে লম্বা নাম ব্যবহার করো: `web.dev`।

## ৬. Laptop থেকে দ্রুত একবার দেখা

শুধু কিছু পরীক্ষা করতে NodePort লাগে না:

```bash
kubectl port-forward service/web 8080:80
# এখন http://localhost:8080 খোলো
```

এটা debug-এর সরঞ্জাম, আসল user-দের সেবা দেওয়ার উপায় নয়।

## ৭. মূল কথা

- Pod-এর IP বদলায়, তাই সামনে **Service** বসাও: একটা স্থির IP ও DNS নাম, যা Pod-দের মধ্যে load ভাগ করে।
- Service **label selector** দিয়ে Pod খোঁজে। `kubectl get endpoints` খালি মানে selector কিছুর সাথে মেলেনি।
- `port` হলো Service-এর port; `targetPort` হলো container-এর port।
- **ClusterIP** (ভেতরে), **NodePort** (node port দিয়ে বাইরে), **LoadBalancer** (cloud-এর public IP)।
- Cluster-এর ভেতরে Service-এর নামই hostname (`http://web`, `DB_HOST=mysql`)।
- **Namespace** object গুছিয়ে রাখে; `-n <name>` ব্যবহার করো।

## ৮. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. Pod-এর IP address-এর ওপর কেন ভরসা করা যায় না?</strong></summary>

Pod বদলালে সে নতুন IP পায়, আর একটা Deployment-এ একাধিক Pod-এর আলাদা IP থাকে। Service একটা স্থির ঠিকানা দেয়।
</details>

<details>
<summary><strong>Q2. একটা Service আছে কিন্তু traffic কোনো Pod-এ পৌঁছাচ্ছে না। আগে কী দেখবে?</strong></summary>

`kubectl get endpoints <service>`। তালিকা খালি হলে Service-এর selector Pod-এর label-এর সাথে মেলেনি।
</details>

<details>
<summary><strong>Q3. `port` আর `targetPort`-এর পার্থক্য কী?</strong></summary>

`port` হলো Service যে port-এ শোনে। `targetPort` হলো container যে port-এ শোনে।
</details>

<details>
<summary><strong>Q4. যে database-এ শুধু অন্য Pod পৌঁছাবে, তার জন্য কোন Service type?</strong></summary>

ClusterIP (default), কারণ এটা শুধু cluster-এর ভেতর থেকে পৌঁছানো যায়।
</details>

<details>
<summary><strong>Q5. একই namespace-এর একটা Pod কীভাবে অন্য Service-কে ডাকে?</strong></summary>

Cluster DNS-এ Service-এর নাম দিয়ে, যেমন `http://web` বা `DB_HOST=mysql`।
</details>

## ৯. বিনামূল্যের resource

- Official: [Service](https://kubernetes.io/docs/concepts/services-networking/service/)
- Official: [DNS for Services and Pods](https://kubernetes.io/docs/concepts/services-networking/dns-pod-service/)
- Official: [Namespaces](https://kubernetes.io/docs/concepts/overview/working-with-objects/namespaces/)
- Official tutorial: [Exposing your app](https://kubernetes.io/docs/tutorials/kubernetes-basics/expose/expose-intro/)

---

**Next:** Chapter 5 — ConfigMap আর Secret *(coming soon)*
