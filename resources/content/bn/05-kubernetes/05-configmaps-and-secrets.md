# Chapter 5 — ConfigMap আর Secret

> **Course:** Kubernetes · **Chapter:** 5 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/kubernetes/05-configmaps-and-secrets)

তোমার app-এর কিছু setting লাগে: database host, app-এর নাম, debug flag, একটা password। Docker আর CI/CD course-এ তুমি শিখেছ এগুলো image-এর ভেতরে ঢোকানো যাবে না। Kubernetes-এ এর জন্য দুটো object আছে: সাধারণ setting-এর জন্য **ConfigMap**, আর স্পর্শকাতরগুলোর জন্য **Secret**।

## ১. Config কেন image-এ রাখা যায় না?

একই image dev, staging আর production-এ চলা উচিত। শুধু configuration বদলায়। Setting image-এর ভেতরে থাকলে প্রতিটা environment-এর জন্য আবার build করতে হয়, আর password image-এর history-তে ঢুকে যায়। তাই: **image = code, ConfigMap/Secret = setting।**

![Pod-এ ConfigMap আর Secret ঢোকানো](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/05-kubernetes/images/config-secret-injection.svg)

## ২. ConfigMap: স্পর্শকাতর নয় এমন setting

Command line থেকে বানাও:

```bash
kubectl create configmap app-config \
  --from-literal=APP_ENV=production \
  --from-literal=DB_HOST=mysql
kubectl get configmap app-config -o yaml
```

অথবা YAML-এ:

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: app-config
data:
  APP_ENV: production
  DB_HOST: mysql
```

## ৩. Secret: স্পর্শকাতর setting

```bash
kubectl create secret generic db-secret \
  --from-literal=DB_PASSWORD='s3cr3t-value'
kubectl get secret db-secret -o yaml
```

Output দেখো: মানটা দেখাবে `czNjcjN0LXZhbHVl`-এর মতো। এটা **base64**, একটা *encoding*, encryption নয়।

```bash
echo 'czNjcjN0LXZhbHVl' | base64 -d      # যে কেউ decode করতে পারে
```

তাহলে Secret আসলে কী দেয়?

- এটা আলাদা object, তাই **কে পড়তে পারবে** তা RBAC (permission) দিয়ে নিয়ন্ত্রণ করা যায়।
- সাধারণ `describe` output-এ এটা ছাপা হয় না, আর cluster admin চালু করলে disk-এ encrypted রাখা যায়।
- Secret YAML Git-এ commit করা **নিরাপদ নয়**। আসল মান দিয়ে কখনো করো না; আসল project-এ Sealed Secrets বা external secret manager-এর মতো tool ব্যবহার করো।

## ৪. Pod-এ ব্যবহার: environment variable

```yaml
spec:
  containers:
    - name: app
      image: nginx:1.27
      envFrom:
        - configMapRef:
            name: app-config        # প্রতিটা key একটা env var হয়ে যায়
      env:
        - name: DB_PASSWORD
          valueFrom:
            secretKeyRef:
              name: db-secret
              key: DB_PASSWORD      # Secret থেকে একটা key
```

Laravel app-এর জন্য `APP_ENV`, `DB_HOST` আর `DB_PASSWORD` সরাসরি তোমার চেনা `.env`-এর মানের সাথে মেলে।

## ৫. ফাইল হিসেবে ব্যবহার

পুরো একটা config ফাইলের জন্য এগুলো file হিসেবেও mount করা যায়:

```yaml
spec:
  containers:
    - name: app
      image: nginx:1.27
      volumeMounts:
        - name: config
          mountPath: /etc/app
  volumes:
    - name: config
      configMap:
        name: app-config
```

প্রতিটা key `/etc/app`-এর একটা ফাইল হয়। ConfigMap বদলালে mount করা ফাইল নিজে নিজে update হয় (একটু দেরিতে), কিন্তু **environment variable হয় না**: নতুন মান দেখতে Pod restart করতে হয়।

```bash
kubectl rollout restart deployment/web     # নতুন env মান পেতে Pod restart
```

## ৬. সহজ নিয়ম

- `.env`-এ যা রাখতে, তা হয় ConfigMap (স্পর্শকাতর নয়) নয়তো Secret (password, API key, token)।
- Secret log-এ ছাপো না, commit করো না, আর `kubectl get secret -o yaml`-এর output chat বা ticket-এ paste করো না।
- ConfigMap YAML Git-এ রাখো। Secret-এর *মান* তাতে রেখো না।

## ৭. মূল কথা

- Configuration image-এর বাইরে থাকে: সাধারণ setting-এ **ConfigMap**, স্পর্শকাতরে **Secret**।
- Secret-এর মান **base64-encoded, encrypted নয়**। RBAC দিয়ে access রক্ষা করো আর আসল মান কখনো Git-এ commit করো না।
- **Environment variable** (`envFrom`, `valueFrom`) বা **mount করা ফাইল** হিসেবে ঢোকাও।
- Env var শুরুতে পড়া হয়: ConfigMap বদলালে `kubectl rollout restart` লাগে। Mount করা ফাইল নিজে update হয়।

## ৮. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. Configuration কেন container image-এর ভেতরে ঢোকানো উচিত নয়?</strong></summary>

একই image প্রতিটা environment-এ চলা উচিত। ভেতরে ঢোকানো setting প্রতিটা environment-এর জন্য আবার build করায় আর image দিয়ে password ফাঁস হতে পারে।
</details>

<details>
<summary><strong>Q2. Secret-এর base64 কি encryption-এর সমান?</strong></summary>

না। Base64 শুধু encoding, যে কেউ decode করতে পারে। Secret রক্ষা পায় access control (RBAC) আর ঐচ্ছিক encryption at rest দিয়ে, base64 দিয়ে নয়।
</details>

<details>
<summary><strong>Q3. `APP_ENV=production`-এর জন্য কোন object আর `DB_PASSWORD`-এর জন্য কোনটা?</strong></summary>

`APP_ENV` ConfigMap-এ; `DB_PASSWORD` Secret-এ।
</details>

<details>
<summary><strong>Q4. যে ConfigMap একটা Pod environment variable দিয়ে পড়ে তা বদলালে app পরিবর্তন দেখে না কেন?</strong></summary>

Env var container শুরুর সময় সেট হয়। Pod restart করো, যেমন `kubectl rollout restart deployment/<name>` দিয়ে।
</details>

## ৯. বিনামূল্যের resource

- Official: [ConfigMaps](https://kubernetes.io/docs/concepts/configuration/configmap/)
- Official: [Secrets](https://kubernetes.io/docs/concepts/configuration/secret/)
- Official: [Good practices for Secrets](https://kubernetes.io/docs/concepts/security/secrets-good-practices/)
- Official task: [Configure a Pod to use a ConfigMap](https://kubernetes.io/docs/tasks/configure-pod-container/configure-pod-configmap/)

---

**Next:** Chapter 6 — Volume আর StatefulSet *(coming soon)*
