# Chapter 7 — Ingress, Health Probe আর Resource Limit

> **Course:** Kubernetes · **Chapter:** 7 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/kubernetes/07-ingress-probes-and-limits)

Demo আর আসল deployment-এর মধ্যে তিনটা জিনিস পার্থক্য গড়ে: internet-কে ঢোকানোর ঠিকঠাক উপায় (**Ingress**), তোমার app সুস্থ কিনা Kubernetes-কে জানানোর উপায় (**probe**), আর এক app যাতে অন্যদের সম্পদ কেড়ে না নেয় তার সীমা (**resource request ও limit**)।

## ১. Ingress: অনেক site-এর একটা সদর দরজা

Chapter 4-এ তুমি `NodePort` আর `LoadBalancer` দেখেছ। প্রতি app-এ একটা LoadBalancer খরচের ব্যাপার: দশটা app মানে দশটা public IP। **Ingress** HTTP(S) traffic **host নাম আর path** অনুযায়ী পাঠায়, তাই একটা প্রবেশপথ অনেক Service-এর সেবা দিতে পারে।

![Host আর path অনুযায়ী Ingress routing](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/05-kubernetes/images/ingress-routing.svg)

দুটো অংশ লাগে:

- একটা **Ingress controller**: যে program আসলে traffic গ্রহণ করে (যেমন **ingress-nginx**, বা Traefik)। Kubernetes-এ এটা default হিসেবে থাকে *না*।
- একটা **Ingress** object: তোমার routing-এর নিয়ম।

minikube-এ controller চালু করতে:

```bash
minikube addons enable ingress
```

নিয়মের ফাইল:

```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: site
spec:
  ingressClassName: nginx
  rules:
    - host: shop.example.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: shop
                port:
                  number: 80
    - host: blog.example.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: blog
                port:
                  number: 80
```

Controller install না থাকলে Ingress কিছুই করে না। নিয়ম উপেক্ষিত হচ্ছে মনে হলে আগে `kubectl get pods -A` দিয়ে controller আছে কিনা দেখো।

**HTTPS:** Ingress একটা TLS certificate রাখতে পারে (Secret-এ জমা), আর **cert-manager** add-on বিনামূল্যের Let's Encrypt certificate নিজে নিজে আনতে পারে।

## ২. Health probe

Kubernetes জানে container-এর *process* চলছে কিনা। সে জানে না তোমার app আসলে *কাজ করছে* কিনা: web server চলতে চলতে আটকেও থাকতে পারে। **Probe** দিয়ে তাকে বলে দেওয়া যায় কীভাবে পরীক্ষা করতে হবে।

| Probe | প্রশ্ন | ব্যর্থ হলে |
|---|---|---|
| **liveness** | "App কি জীবিত?" | Container **restart** হয় |
| **readiness** | "এখন কি traffic নিতে পারবে?" | Pod **Service থেকে সরে যায়** (restart নয়) |
| **startup** | "শুরু করা কি শেষ?" | ধীরে-চালু-হওয়া app-এর জন্য অন্য probe দেরি করায় |

```yaml
containers:
  - name: web
    image: nginx:1.27
    readinessProbe:
      httpGet:
        path: /
        port: 80
      initialDelaySeconds: 3
      periodSeconds: 5
    livenessProbe:
      httpGet:
        path: /
        port: 80
      initialDelaySeconds: 10
      periodSeconds: 10
      failureThreshold: 3
```

Rolling update-এর সময় (Chapter 3) readiness কেন সবচেয়ে জরুরি: Deployment নতুন Pod-এ traffic পাঠায় আর পুরনোটা সরায় শুধু **নতুন Pod ready হলে**। Readiness probe না থাকলে user এমন Pod-এ পড়তে পারে যা এখনো boot হচ্ছে।

একটা সাধারণ ভুল: liveness probe-এ database পরীক্ষা করা। Database ধীর হলে Kubernetes তোমার সব সুস্থ app Pod restart করে পরিস্থিতি আরও খারাপ করে। Liveness check সরল রাখো।

## ৩. Resource request আর limit

প্রতিটা container ঘোষণা করতে পারে তার কত CPU আর memory লাগে।

```yaml
resources:
  requests:
    cpu: 100m          # ১০০ millicore = একটা CPU core-এর ০.১
    memory: 128Mi
  limits:
    cpu: 500m
    memory: 256Mi
```

- **Request** = সংরক্ষিত পরিমাণ। Scheduler এটা দিয়ে যথেষ্ট জায়গাওয়ালা node বাছে।
- **Limit** = সীমা। Container এর ওপরে যেতে পারে না।

সীমায় পৌঁছালে দুটোর আচরণ আলাদা:

- **CPU** সীমা ছাড়ালে → container **throttle** হয় (ধীর হয়), মারা যায় না।
- **Memory** সীমা ছাড়ালে → container `OOMKilled` হয়ে **মারা যায়** আর restart হয়।

```bash
kubectl describe pod <name>     # Last State-এর নিচে "Reason: OOMKilled" খোঁজো
kubectl top pods                # সরাসরি ব্যবহার (metrics-server add-on লাগে)
```

Request ছাড়া scheduler ভালো পরিকল্পনা করতে পারে না, আর একটা গোলমেলে Pod প্রতিবেশীদের সম্পদ কেড়ে নিতে পারে। Production-এর প্রতিটা container-এ memory request ও limit দেওয়া ভালো অভ্যাস।

## ৪. সব একসাথে

Production-প্রস্তুত Deployment-এ থাকে: pin করা image tag, `replicas` ≥ ২, readiness ও liveness probe, resource request ও limit, ConfigMap/Secret থেকে config, আর সামনে Service ও Ingress। এর প্রতিটা অংশ তুমি এখন জানো। শেষ chapter এগুলো জোড়া লাগাবে।

## ৫. মূল কথা

- **Ingress** একটা প্রবেশপথ দিয়ে host ও path অনুযায়ী অনেক Service-এ HTTP(S) পাঠায়। এর জন্য **Ingress controller** (যেমন ingress-nginx) লাগে।
- **Readiness** probe: ব্যর্থ হলে traffic পাঠানো বন্ধ। **Liveness** probe: ব্যর্থ হলে restart। **Startup** probe: ধীর app-এর জন্য অপেক্ষা।
- Rolling update readiness-এর ওপর নির্ভর করে, যাতে user প্রস্তুত নয় এমন Pod-এ না পড়ে।
- **Request** scheduling-এর জন্য সম্পদ সংরক্ষণ করে; **limit** সীমা বাঁধে।
- CPU limit ছাড়ালে → throttle। Memory limit ছাড়ালে → `OOMKilled`।

## ৬. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. তুমি একটা Ingress বানালে কিন্তু কিছুই হচ্ছে না। কী বাদ পড়তে পারে?</strong></summary>

একটা Ingress controller (যেমন ingress-nginx)। Ingress object শুধু নিয়মের সমষ্টি; সেগুলো কার্যকর করতে controller install থাকতে হয়।
</details>

<details>
<summary><strong>Q2. Liveness আর readiness probe-এর পার্থক্য কী?</strong></summary>

Liveness ব্যর্থ হলে container restart হয়। Readiness ব্যর্থ হলে Pod Service থেকে সরে যায় তাই traffic পায় না, কিন্তু restart হয় না।
</details>

<details>
<summary><strong>Q3. একটা container তার memory limit ছাড়ালে কী হয়?</strong></summary>

সে `OOMKilled` হয়ে মারা যায় আর restart হয়। (CPU limit ছাড়ালে শুধু throttle হয়।)
</details>

<details>
<summary><strong>Q4. Resource request আর limit-এর পার্থক্য কী?</strong></summary>

Request হলো সংরক্ষিত পরিমাণ, যা scheduler Pod বসাতে ব্যবহার করে। Limit হলো container সর্বোচ্চ যা ব্যবহার করতে পারে।
</details>

## ৭. বিনামূল্যের resource

- Official: [Ingress](https://kubernetes.io/docs/concepts/services-networking/ingress/)
- Official: [Configure Liveness, Readiness and Startup Probes](https://kubernetes.io/docs/tasks/configure-pod-container/configure-liveness-readiness-startup-probes/)
- Official: [Resource Management for Pods and Containers](https://kubernetes.io/docs/concepts/configuration/manage-resources-containers/)
- minikube: [Accessing apps (including `minikube tunnel`)](https://minikube.sigs.k8s.io/docs/handbook/accessing/)

---

**Next:** Chapter 8 — আসল project: Kubernetes-এ Laravel *(coming soon)*
