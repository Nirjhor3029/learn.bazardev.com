# Chapter 2 — Local Cluster, `kubectl`, আর তোমার প্রথম Pod

> **Course:** Kubernetes · **Chapter:** 2 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/kubernetes/02-local-cluster-and-kubectl)

Chapter 1 ছিল theory। এবার তুমি নিজের computer-এই একটা আসল cluster পাবে আর তাতে কিছু চালাবে। এর জন্য server ভাড়া করা লাগে না।

## ১. তোমার laptop-এ একটা cluster

পুরো cluster-এ কয়েকটা machine লাগে, কিন্তু শেখার জন্য একটা computer-এই ছোট একটা চালানো যায়। জনপ্রিয় দুটো tool:

- **minikube** — তোমার machine-এ এক-node-এর cluster চালু করে। Kubernetes-এর official tutorial-গুলো এটাই ব্যবহার করে, তাই সাথে সাথে অনুসরণ করা সবচেয়ে সহজ।
- **kind** ("Kubernetes in Docker") — cluster-টা Docker container-এর ভেতরে চালায়। তুমি যদি আগে থেকেই Docker-এ অভ্যস্ত হও, এটা ভালো পছন্দ।

এই chapter **minikube** ব্যবহার করে, কিন্তু শুরু করার পরের প্রতিটা command যেকোনো cluster-এ একই।

```bash
minikube start        # যা লাগে নামায় আর একটা local cluster চালু করে
```

Install-এর link শেষের resource-এ আছে। তোমার আগে থেকে Docker (বা অন্য driver) install থাকতে হবে — Docker course থেকে তোমার আছে।

## ২. `kubectl`: তোমার remote control

**`kubectl`** ("kube-control" বা "kube-cuddle" বলা হয়) হলো সেই command-line tool যা cluster-এর API server-এর সাথে কথা বলে। Kubernetes-এর প্রতিটা command যা তুমি কখনো টাইপ করবে তা এটা দিয়েই শুরু হয়।

```bash
kubectl version --client     # kubectl install আছে কি?
kubectl cluster-info         # cluster কোথায়, আর পৌঁছানো যায় কি?
kubectl get nodes            # cluster-এর server-গুলোর তালিকা
```

`kubectl get nodes` একটা node দেখাবে যার status `Ready`। এটা দেখলে বুঝবে তোমার cluster কাজ করছে।

Pattern সবসময় `kubectl <verb> <thing>`: `get`, `describe`, `delete`, `logs`, `apply` — `pods`, `nodes`, `services` ইত্যাদির উপর।

## ৩. তোমার প্রথম Pod, দ্রুত উপায়ে

**Pod** হলো Kubernetes যা চালায় তার সবচেয়ে ছোট একক: এক (কখনো একাধিক) container-এর একটা মোড়ক। একটা চালু করো:

```bash
kubectl run hello --image=nginx
```

মানে: "`nginx` image থেকে `hello` নামে একটা Pod চালাও"। এবার দেখো:

```bash
kubectl get pods              # Pod আর তাদের status-এর তালিকা
kubectl describe pod hello    # বিস্তারিত, আর একেবারে নিচে events-এর log
kubectl logs hello            # container-এর output
```

Image নামা আর চালু হওয়ার সময় status যায় `ContainerCreating` → `Running`। তোমার browser থেকে এটাতে পৌঁছাতে, একটা local port Pod-এ forward করো:

```bash
kubectl port-forward pod/hello 8080:80
# এখন http://localhost:8080 খোলো, nginx-এর welcome page দেখবে
```

ভেতরেও ঢুকতে পারো, ঠিক `docker exec`-এর মতো:

```bash
kubectl exec -it hello -- sh
```

## ৪. তোমার প্রথম Pod, ঠিক উপায়ে: একটা YAML file

`kubectl run` পরীক্ষার জন্য সুবিধাজনক, কিন্তু আসল কাজে এমন file ব্যবহার হয় যা তুমি Git-এ রাখতে পারো। এটা `pod.yaml` নামে save করো:

```yaml
apiVersion: v1
kind: Pod
metadata:
  name: hello
  labels:
    app: hello
spec:
  containers:
    - name: web
      image: nginx:1.27
      ports:
        - containerPort: 80
```

প্রায় প্রতিটা Kubernetes YAML-এ একই চারটা top-level field থাকে:

- **`apiVersion`** — এই object type API-র কোন version-এর (Pod-এর জন্য `v1`)।
- **`kind`** — তুমি কী বানাচ্ছ (`Pod`)।
- **`metadata`** — এর নাম আর label (label হলো tag; Chapter 4 এগুলো ব্যবহার করে)।
- **`spec`** — কাঙ্ক্ষিত অবস্থা: এখানে কোন container চলবে।

File থেকে বানাও:

```bash
kubectl apply -f pod.yaml
```

![What happens when you run kubectl apply](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/05-kubernetes/images/kubectl-apply-flow.svg)

এটা Chapter 1-এর সেই declarative ধরন: তুমি কাঙ্ক্ষিত অবস্থা তুলে দাও, আর cluster সেটা বাস্তব করে। `kubectl apply -f pod.yaml` আবার চালালে কিছু ভাঙে না — সে শুধু মিলিয়ে দেখে আর বদলানোর কিছু পায় না।

## ৫. একটা ফাঁক: একা Pod নিজে সেরে ওঠে না

এটা চেষ্টা করো:

```bash
kubectl delete pod hello
kubectl get pods     # এটা নেই, আর কিছুই ফিরিয়ে আনে না
```

Chapter 1-এ self-healing-এর প্রতিশ্রুতি ছিল। কিন্তু একা বানানো একটা Pod-কে **কোনো controller দেখছে না**, তাই একবার delete হলে (বা তার node মরলে) সেটা চলেই যায়। সমাধান হলো তোমার Pod-গুলো একটা **Deployment**-কে পরিচালনা করতে দেওয়া — সেটা Chapter 3। বাস্তবে তুমি প্রায় কখনোই একা Pod বানাও না।

## ৬. Status পড়া

| Status | সাধারণত মানে |
|---|---|
| `Pending` | গৃহীত, কিন্তু এখনো চলছে না (node বা image-এর অপেক্ষায়) |
| `ContainerCreating` | Image নামছে আর container তৈরি হচ্ছে |
| `Running` | অন্তত একটা container চলছে |
| `ImagePullBackOff` | Image download করা যাচ্ছে না — নাম বা tag ভুল, বা access নেই |
| `CrashLoopBackOff` | Container চালু হয়, crash করে, আর বারবার restart হতে থাকে |

কিছু ভুল হলে ক্রম হলো: `kubectl get pods` → `kubectl describe pod <name>` (একেবারে নিচের **Events** পড়ো) → `kubectl logs <name>`।

## ৭. মূল কথাগুলো

- **minikube** বা **kind** তোমার নিজের computer-এ বিনামূল্যে একটা cluster দেয়; `kubectl` তার সাথে কথা বলে।
- `kubectl <verb> <thing>`: `get`, `describe`, `logs`, `exec`, `apply`, `delete`।
- **Pod** এক বা একাধিক container মোড়ে; `kubectl run` দ্রুত, কিন্তু `apiVersion`, `kind`, `metadata`, `spec` সহ একটা YAML file আর `kubectl apply -f`-ই ঠিক উপায়।
- `kubectl apply` কাঙ্ক্ষিত অবস্থা API server-এ পাঠায় যা সেটা সংরক্ষণ করে; scheduler একটা node বাছে; kubelet container চালু করে।
- একা Pod delete হলে আর তৈরি হয় না — Deployment (পরের chapter) এটা ঠিক করে।
- Debug-এর ক্রম: `get pods` → `describe pod` (Events) → `logs`।

## ৮. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. `kubectl get nodes` কী দেখায়, আর কোন status মানে cluster সুস্থ?</strong></summary>

এটা cluster-এর server (node)-গুলোর তালিকা দেখায়। সুস্থ node-এর status হয় `Ready`।
</details>

<details>
<summary><strong>Q2. প্রায় প্রতিটা Kubernetes YAML file-এর চারটা top-level field-এর নাম বলো।</strong></summary>

`apiVersion`, `kind`, `metadata` আর `spec`।
</details>

<details>
<summary><strong>Q3. `kubectl run` দিয়ে একটা Pod বানিয়ে delete করলে সেটা আবার তৈরি হয় না কেন?</strong></summary>

একা Pod-কে কোনো controller পরিচালনা করে না। Self-healing আসে Deployment-এর মতো controller থেকে, যা কাঙ্ক্ষিত অবস্থা দেখতে থাকে আর হারানো Pod বদলে দেয়।
</details>

<details>
<summary><strong>Q4. একটা Pod `ImagePullBackOff` দেখাচ্ছে। সবচেয়ে সম্ভাব্য কারণ কী, আর নিশ্চিত হতে কোন command?</strong></summary>

Cluster image নামাতে পারছে না — সাধারণত image-এর নাম বা tag ভুল, অথবা access নেই। `kubectl describe pod <name>` এর Events অংশে সঠিক error দেখায়।
</details>

## ৯. বিনামূল্যের resource

- Official: [Install tools (kubectl, kind, minikube)](https://kubernetes.io/docs/tasks/tools/)
- minikube: [Get started](https://minikube.sigs.k8s.io/docs/start/)
- Official: [Pods](https://kubernetes.io/docs/concepts/workloads/pods/)
- Official: [kubectl quick reference](https://kubernetes.io/docs/reference/kubectl/quick-reference/)

---

**Next:** Chapter 3 — Pod, ReplicaSet আর Deployment *(coming soon)*
