# Chapter 8 — আসল Project: Kubernetes-এ Laravel

> **Course:** Kubernetes · **Chapter:** 8 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/kubernetes/08-real-project-laravel-on-kubernetes)

এবার সব কাজে লাগানোর পালা। আমরা একটা local cluster-এ **MySQL database-সহ একটা Laravel app** চালাব: Deployment, Service, ConfigMap, Secret, storage-সহ StatefulSet, একটা migration Job, probe, limit আর Ingress।

> **সৎ কথা:** এই manifest-গুলো pattern শেখানোর জন্য লেখা। এগুলো সাধারণ ধাঁচের, কিন্তু তোমার cluster-এ আমি চালাইনি, তাই নাম, version আর image-এর খুঁটিনাটি বদলাতে হতে পারে। প্রথমবারে না চললে manifest debug করাও দক্ষতার অংশ।

![পুরো project](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/05-kubernetes/images/laravel-on-k8s.svg)

## ১. আমরা কী বানাচ্ছি

| অংশ | Kubernetes object | কোন chapter থেকে |
|---|---|---|
| Laravel app (৩ replica) | Deployment | ৩ |
| App-এর স্থির ঠিকানা | Service (ClusterIP) | ৪ |
| Host নাম দিয়ে public প্রবেশ | Ingress | ৭ |
| গোপন নয় এমন setting | ConfigMap | ৫ |
| DB password, `APP_KEY` | Secret | ৫ |
| নিজের disk-সহ MySQL | StatefulSet + headless Service + PVC | ৬ |
| একবার `php artisan migrate` চালানো | Job | নিচে |
| নিজে পরীক্ষা ও সীমা | probe + resources | ৭ |

আলাদা namespace ব্যবহার করো যাতে এক command-এ সব মুছে ফেলা যায়:

```bash
kubectl create namespace laravel
kubectl config set-context --current --namespace=laravel
```

## ২. ধাপ ১ — Image

Docker course-এর মতো তোমার Laravel image build করো (এমন Dockerfile যা app-কে port 80-তে চালায়), তারপর cluster-এর কাছে সেটা সহজলভ্য করো। minikube-এ:

```bash
docker build -t laravel-app:1.0.0 .
minikube image load laravel-app:1.0.0
```

আসল cluster-এ তুমি registry-তে (Docker Hub, GitHub Container Registry) push করে সেই image-এর নাম ব্যবহার করবে। সবসময় **version tag** ব্যবহার করো, `latest` নয়, যাতে rollback-এর মানে থাকে।

minikube-এ local image ব্যবহার করলে container-এ `imagePullPolicy: IfNotPresent` যোগ করো, নইলে Kubernetes internet থেকে নামাতে গিয়ে `ImagePullBackOff` দিতে পারে।

## ৩. ধাপ ২ — Setting আর secret

```yaml
# config.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: app-config
data:
  APP_ENV: production
  APP_DEBUG: "false"
  DB_CONNECTION: mysql
  DB_HOST: mysql          # ধাপ ৩-এর headless Service-এর নাম
  DB_PORT: "3306"
  DB_DATABASE: laravel
  DB_USERNAME: laravel
```

Secret command line থেকে বানাও, যাতে আসল মান Git-এর কোনো ফাইলে না যায়:

```bash
kubectl create secret generic app-secret \
  --from-literal=DB_PASSWORD='change-me-please' \
  --from-literal=MYSQL_ROOT_PASSWORD='change-me-too' \
  --from-literal=APP_KEY="$(php artisan key:generate --show)"
```

খেয়াল করো `DB_HOST: mysql`। Pod-রা database-কে Service-এর নাম দিয়ে খোঁজে (Chapter 4)।

## ৪. ধাপ ৩ — Storage-সহ MySQL

```yaml
# mysql.yaml
apiVersion: v1
kind: Service
metadata:
  name: mysql
spec:
  clusterIP: None          # headless
  selector:
    app: mysql
  ports:
    - port: 3306
---
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: mysql
spec:
  serviceName: mysql
  replicas: 1
  selector:
    matchLabels:
      app: mysql
  template:
    metadata:
      labels:
        app: mysql
    spec:
      containers:
        - name: mysql
          image: mysql:8.4
          env:
            - name: MYSQL_DATABASE
              value: laravel
            - name: MYSQL_USER
              value: laravel
            - name: MYSQL_PASSWORD
              valueFrom:
                secretKeyRef: { name: app-secret, key: DB_PASSWORD }
            - name: MYSQL_ROOT_PASSWORD
              valueFrom:
                secretKeyRef: { name: app-secret, key: MYSQL_ROOT_PASSWORD }
          ports:
            - containerPort: 3306
          volumeMounts:
            - name: data
              mountPath: /var/lib/mysql
          readinessProbe:
            exec:
              command: ["mysqladmin", "ping", "-h", "127.0.0.1"]
            initialDelaySeconds: 20
            periodSeconds: 10
          resources:
            requests: { cpu: 200m, memory: 512Mi }
            limits:   { memory: 1Gi }
  volumeClaimTemplates:
    - metadata:
        name: data
      spec:
        accessModes: ["ReadWriteOnce"]
        resources:
          requests:
            storage: 2Gi
```

```bash
kubectl apply -f config.yaml
kubectl apply -f mysql.yaml
kubectl get pods -w           # mysql-0 Ready হওয়া পর্যন্ত অপেক্ষা (থামাতে Ctrl+C)
```

## ৫. ধাপ ৪ — Laravel Deployment আর Service

```yaml
# app.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: app
  template:
    metadata:
      labels:
        app: app
    spec:
      containers:
        - name: laravel
          image: laravel-app:1.0.0
          imagePullPolicy: IfNotPresent
          ports:
            - containerPort: 80
          envFrom:
            - configMapRef:
                name: app-config
          env:
            - name: DB_PASSWORD
              valueFrom:
                secretKeyRef: { name: app-secret, key: DB_PASSWORD }
            - name: APP_KEY
              valueFrom:
                secretKeyRef: { name: app-secret, key: APP_KEY }
          readinessProbe:
            httpGet: { path: /up, port: 80 }
            initialDelaySeconds: 5
            periodSeconds: 5
          livenessProbe:
            httpGet: { path: /up, port: 80 }
            initialDelaySeconds: 20
            periodSeconds: 10
          resources:
            requests: { cpu: 100m, memory: 128Mi }
            limits:   { cpu: 500m, memory: 256Mi }
---
apiVersion: v1
kind: Service
metadata:
  name: app
spec:
  selector:
    app: app
  ports:
    - port: 80
      targetPort: 80
```

`/up` নতুন Laravel version-এর বিল্ট-ইন health route। তোমার version-এ না থাকলে probe-কে `/`-এ দাও বা ছোট একটা route যোগ করো। Liveness probe শুধু দেখে app সাড়া দেয় কিনা; এটা ইচ্ছা করেই database query করে **না** (Chapter 7)।

## ৬. ধাপ ৫ — Job দিয়ে migration

**Job** একটা Pod-কে চিরকালের বদলে একবার শেষ পর্যন্ত চালায়। `php artisan migrate`-এর জন্য এটাই ঠিক সরঞ্জাম, যা তুমি চাও না প্রতিটা replica শুরুতে চালাক।

```yaml
# migrate.yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: migrate
spec:
  backoffLimit: 3
  template:
    spec:
      restartPolicy: Never
      containers:
        - name: migrate
          image: laravel-app:1.0.0
          imagePullPolicy: IfNotPresent
          command: ["php", "artisan", "migrate", "--force"]
          envFrom:
            - configMapRef:
                name: app-config
          env:
            - name: DB_PASSWORD
              valueFrom:
                secretKeyRef: { name: app-secret, key: DB_PASSWORD }
            - name: APP_KEY
              valueFrom:
                secretKeyRef: { name: app-secret, key: APP_KEY }
```

```bash
kubectl apply -f migrate.yaml
kubectl logs job/migrate
kubectl apply -f app.yaml
```

Job চালাও প্রথম deployment-এর **আগে** বা সাথে, আর নতুন migration-সহ প্রতিটা release-এ আবার। (`--force` লাগে কারণ `APP_ENV=production` হলে Laravel নইলে নিশ্চিতকরণ চায়।) Job-এর নাম অনন্য হতে হয়, তাই আবার চালানোর আগে পুরনো Job মুছো (`kubectl delete job migrate`)।

## ৭. ধাপ ৬ — Ingress

```yaml
# ingress.yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: app
spec:
  ingressClassName: nginx
  rules:
    - host: laravel.local
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: app
                port:
                  number: 80
```

```bash
minikube addons enable ingress
kubectl apply -f ingress.yaml
minikube ip                      # যেমন 192.168.49.2
```

নামটা যাতে চেনা যায় সেজন্য তোমার computer-এর hosts ফাইলে একটা লাইন যোগ করো: `192.168.49.2  laravel.local`, তারপর `http://laravel.local` খোলো। (কিছু setup-এ, যেমন macOS বা Windows-এ Docker driver, `minikube tunnel` লাগে; minikube-এর "Accessing apps" documentation-এ এটা ব্যাখ্যা করা আছে।)

## ৮. Day-2 operation

```bash
kubectl get all                                  # namespace-এর সবকিছু
kubectl logs deployment/app                      # app-এর log
kubectl scale deployment app --replicas=5        # বেশি ক্ষমতা
kubectl set image deployment/app laravel=laravel-app:1.0.1   # নতুন version
kubectl rollout status deployment/app
kubectl rollout undo deployment/app              # কিছু ভাঙলে ফিরে যাও
```

**পরিষ্কার করা:** সবকিছু একটা namespace-এ আছে, তাই `kubectl delete namespace laravel` সব মুছে দেয়। এতে database-এর PVC আর তোমার data-ও মুছে যায়। আসল environment-এ সাবধান।

## ৯. এরপর কী উন্নত করবে

- **Queue worker আর scheduler:** `php artisan queue:work` দ্বিতীয় Deployment হিসেবে চালাও, আর `schedule:run` **CronJob** হিসেবে।
- **ফাইল upload:** `storage` folder প্রতি Pod-এ আলাদা। User-এর upload-এর জন্য object storage (S3-সামঞ্জস্যপূর্ণ) বা shared volume ব্যবহার করো।
- **Session ও cache:** একাধিক replica থাকলে local file driver নয়, Redis বা database ব্যবহার করো।
- **আসল database hosting:** StatefulSet-এর বদলে managed MySQL ভাবো (Chapter 6)।
- **CI/CD:** তোমার pipeline image build করুক, version tag দিয়ে push করুক, আর `kubectl set image` চালাক (CI/CD course দেখো)।
- **Package manager:** manifest-এর সেট template করতে ও ভাগ করতে **Helm** শেখো।

## ১০. মূল কথা

- আসল app মানে কয়েকটা object একসাথে: app-এর জন্য Deployment + Service + Ingress, setting-এর জন্য ConfigMap/Secret, database-এর জন্য StatefulSet + PVC।
- Pod-রা একে অপরকে **Service-এর নাম** দিয়ে খোঁজে (`DB_HOST=mysql`)।
- Migration-এর মতো একবারের কাজে **Job**, সময়সূচির কাজে **CronJob**।
- **Version-সহ image tag**, probe ও resource limit ব্যবহার করো, আর Secret-এর মান Git-এর বাইরে রাখো।
- `kubectl rollout undo` তোমার নিরাপত্তা-জাল; একটা namespace পরিষ্কার করা সহজ করে।

## ১১. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. `php artisan migrate` প্রতিটা app Pod-এর শুরুতে না চালিয়ে Job হিসেবে চালাই কেন?</strong></summary>

Job একবার শেষ পর্যন্ত চলে। ৩টা replica থাকলে প্রতিটা Pod-এ migration চালালে সেটা একসাথে কয়েকবার চলত আর সংঘাত হতে পারত।
</details>

<details>
<summary><strong>Q2. Laravel Pod কীভাবে MySQL database খুঁজে পায়?</strong></summary>

Cluster DNS-এ Service-এর নাম দিয়ে: `DB_HOST=mysql`।
</details>

<details>
<summary><strong>Q3. এই project-এ `APP_ENV` আর `DB_PASSWORD` কোথায় থাকে?</strong></summary>

`APP_ENV` ConfigMap-এ; `DB_PASSWORD` (আর `APP_KEY`) Secret-এ।
</details>

<details>
<summary><strong>Q4. তুমি `laravel-app:1.0.1` deploy করলে আর user error দেখছে। সবচেয়ে দ্রুত নিরাপদ সমাধান কী?</strong></summary>

`kubectl rollout undo deployment/app`, যা আগের ReplicaSet-এ ফিরিয়ে দেয়।
</details>

## ১২. বিনামূল্যের resource

- Official: [Run a Stateless Application Using a Deployment](https://kubernetes.io/docs/tasks/run-application/run-stateless-application-deployment/)
- Official: [Run a Single-Instance Stateful Application (MySQL)](https://kubernetes.io/docs/tasks/run-application/run-single-instance-stateful-application/)
- Official: [Jobs](https://kubernetes.io/docs/concepts/workloads/controllers/job/) আর [CronJob](https://kubernetes.io/docs/concepts/workloads/controllers/cron-jobs/)
- Official: [Helm — Quickstart](https://helm.sh/docs/intro/quickstart/)
- Laravel: [Health route and deployment](https://laravel.com/docs/deployment)

---

**Course শেষ।** Certificate পেতে website-এ course exam দাও।
