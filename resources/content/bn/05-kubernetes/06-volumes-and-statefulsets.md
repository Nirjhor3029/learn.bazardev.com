# Chapter 6 — Volume, PersistentVolume আর StatefulSet

> **Course:** Kubernetes · **Chapter:** 6 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/kubernetes/06-volumes-and-statefulsets)

এ পর্যন্ত যা চালিয়েছি সবই **stateless**: Pod মরলে কিছু হারায় না, কারণ একই রকম নতুন Pod বদলে বসে। Database আলাদা। Pod restart হয়ে data চলে গেলে বিপর্যয়। এই chapter data রাখা নিয়ে।

## ১. সমস্যা: container-এর storage অস্থায়ী

Container বদলালে তার নিজের filesystem হারিয়ে যায়। Docker course-এ তুমি এটা জেনেছ, সেখানে সমাধান ছিল volume। Kubernetes-এও একই ধারণা, তবে অংশ বেশি।

## ২. Volume

**Volume** হলো Pod-এর সাথে জোড়া storage। সবচেয়ে সরল ধরন `emptyDir` একটা scratch folder, যা **Pod** যতক্ষণ থাকে ততক্ষণ থাকে (তার container-গুলোর মধ্যে ভাগ করা, Pod delete হলে হারায়)। অস্থায়ী ফাইল আর cache-এর জন্য ভালো, database-এর জন্য নয়।

যে data বেঁচে থাকতে হবে তার জন্য **persistent** storage লাগে।

## ৩. PersistentVolume আর PersistentVolumeClaim

Kubernetes আলাদা করে *কে storage দেয়* আর *কে ব্যবহার করে*:

| Object | ভূমিকা | ভাবো এভাবে |
|---|---|---|
| **PersistentVolume (PV)** | আসল storage-এর টুকরো (cloud disk, NFS share) | একটা parking-এর জায়গা |
| **PersistentVolumeClaim (PVC)** | Pod-এর storage-এর *অনুরোধ*: "আমার ৫Gi লাগবে" | জায়গা চাওয়ার টিকিট |
| **StorageClass** | স্বয়ংক্রিয়ভাবে PV বানানোর রেসিপি | Parking lot-এর নিয়ম |

Developer হিসেবে সাধারণত তুমি শুধু **PVC** লেখো। বেশিরভাগ cluster-এ (minikube, cloud) StorageClass তোমার জন্য PV আপনা-আপনি বানায়।

![Pod, PVC, PV আর disk](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/05-kubernetes/images/pvc-storage.svg)

```yaml
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: data-claim
spec:
  accessModes:
    - ReadWriteOnce        # একটা node read-write mount করতে পারে
  resources:
    requests:
      storage: 1Gi
```

```bash
kubectl apply -f pvc.yaml
kubectl get pvc          # STATUS হওয়া উচিত Bound
kubectl get pv
```

Pod-এ ব্যবহার:

```yaml
spec:
  containers:
    - name: db
      image: mysql:8.4
      env:
        - name: MYSQL_ROOT_PASSWORD
          valueFrom:
            secretKeyRef: { name: db-secret, key: DB_PASSWORD }
      volumeMounts:
        - name: data
          mountPath: /var/lib/mysql
  volumes:
    - name: data
      persistentVolumeClaim:
        claimName: data-claim
```

Pod delete করো, বদলি Pod একই claim mount করে — **data তখনও আছে**।

**Access mode:** `ReadWriteOnce` (একটা node, read-write — সবচেয়ে প্রচলিত), `ReadOnlyMany` (অনেক node, শুধু পড়া), `ReadWriteMany` (অনেক node, read-write; সমর্থনকারী storage লাগে, যেমন NFS)।

## ৪. Reclaim policy: claim মুছলে কী হয়?

PV-র একটা policy থাকে: **Delete** (claim-এর সাথে disk-ও মুছে যায় — dynamic storage-এর সাধারণ default) অথবা **Retain** (disk রেখে দেওয়া হয়, হাতে পরিষ্কার করতে হয়)। দামি data-সহ claim মোছার আগে এটা দেখে নাও।

## ৫. Database-এর জন্য Deployment কেন খারাপ মিল

Deployment Pod-দের বিনিময়যোগ্য ধরে, নাম random (`web-7d9f-x2k`), কোনো নির্দিষ্ট ক্রম নেই। Database cluster-এর দরকার উল্টোটা:

- প্রতিটা সদস্যের **স্থির নাম** (যাতে replica primary-কে খুঁজে পায়),
- **নিজের disk** যা তার সাথে সাথে চলে,
- **নির্দিষ্ট শুরুর ক্রম**।

## ৬. StatefulSet

**StatefulSet** হলো stateful app-এর জন্য Deployment-এর মতো:

- Pod-এর নাম স্থির, সংখ্যা সহ: `db-0`, `db-1`, `db-2`।
- ক্রমে শুরু হয় (০, তারপর ১, তারপর ২) আর উল্টো ক্রমে বন্ধ হয়।
- প্রতিটা Pod `volumeClaimTemplates` থেকে **নিজের PVC** পায়। `db-1` আবার বানালে সে *একই* `db-1`-এর disk ফেরত পায়।
- একটা **headless Service** (`clusterIP: None`) প্রতিটা Pod-কে নিজস্ব DNS নাম দেয়, যেমন `db-0.db`।

```yaml
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: db
spec:
  serviceName: db            # headless Service
  replicas: 2
  selector:
    matchLabels:
      app: db
  template:
    metadata:
      labels:
        app: db
    spec:
      containers:
        - name: mysql
          image: mysql:8.4
          volumeMounts:
            - name: data
              mountPath: /var/lib/mysql
  volumeClaimTemplates:
    - metadata:
        name: data
      spec:
        accessModes: ["ReadWriteOnce"]
        resources:
          requests:
            storage: 1Gi
```

## ৭. একটা বাস্তব সতর্কতা

Kubernetes-এ production database চালানো **কঠিন**: backup, failover, upgrade। অনেক team app চালায় Kubernetes-এ, আর database রাখে managed service-এ (যেমন AWS RDS) বা আলাদা server-এ। StatefulSet শেখো যাতে বোঝো এটা কীভাবে কাজ করে, তারপর তোমার project-এর জন্য কোনটা ঠিক তা ঠিক করো। আরও মনে রেখো: **volume মানে backup নয়।** Data মুছলে বা নষ্ট হলে disk শুধু সেই ক্ষতিটাই ধরে রাখে।

## ৮. মূল কথা

- Container-এর নিজের filesystem অস্থায়ী; data-র জন্য **volume** ব্যবহার করো।
- `emptyDir` Pod যতক্ষণ থাকে ততক্ষণ। যে data বাঁচতে হবে তার জন্য **PersistentVolumeClaim**, যা একটা **PersistentVolume**-এর সাথে bound হয় (প্রায়ই StorageClass বানায়)।
- **StatefulSet** Pod-কে স্থির নাম (`db-0`), ক্রমিক শুরু আর `volumeClaimTemplates` দিয়ে প্রত্যেককে আলাদা disk দেয়।
- Stateless app-এ Deployment, পরিচয় ও storage দরকার এমন app-এ StatefulSet।
- Persistent storage backup নয়।

## ৯. নিজেকে যাচাই করো (exam prep)

<details>
<summary><strong>Q1. তোমার database Pod delete হয়ে আবার তৈরি হলো আর data নেই। সম্ভাব্য ভুল কী?</strong></summary>

Data container-এর নিজের filesystem বা `emptyDir`-এ ছিল। PersistentVolumeClaim ব্যবহার করা উচিত ছিল।
</details>

<details>
<summary><strong>Q2. PV আর PVC-এর পার্থক্য কী?</strong></summary>

PV হলো cluster-এর আসল storage-এর টুকরো। PVC হলো Pod-এর storage চাওয়ার অনুরোধ; সেটা উপযুক্ত PV-র সাথে bound হয়।
</details>

<details>
<summary><strong>Q3. StatefulSet Deployment-এর চেয়ে বাড়তি কী দুটো জিনিস দেয়?</strong></summary>

স্থির, সংখ্যাযুক্ত Pod-নাম (`db-0`, `db-1`), ক্রমিক শুরু ও বন্ধ, আর `volumeClaimTemplates` দিয়ে প্রতিটা Pod-এর আলাদা PVC।
</details>

<details>
<summary><strong>Q4. PersistentVolume ব্যবহার করলেই কি data-র backup হয়?</strong></summary>

না। এটা শুধু Pod restart-এর পরেও data রাখে। Data মুছলে বা নষ্ট হলে volume ক্ষতিটা ধরে রাখে। আসল backup তবু লাগে।
</details>

## ১০. বিনামূল্যের resource

- Official: [Volumes](https://kubernetes.io/docs/concepts/storage/volumes/)
- Official: [Persistent Volumes](https://kubernetes.io/docs/concepts/storage/persistent-volumes/)
- Official: [StatefulSets](https://kubernetes.io/docs/concepts/workloads/controllers/statefulset/)
- Official tutorial: [StatefulSet Basics](https://kubernetes.io/docs/tutorials/stateful-application/basic-stateful-set/)

---

**Next:** Chapter 7 — Ingress, health probe আর resource limit *(coming soon)*
