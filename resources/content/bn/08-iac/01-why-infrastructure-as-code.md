# Chapter 1 — কেন Infrastructure as Code? Terraform আর Ansible এক ছবিতে

> **Course:** Infrastructure as Code (Terraform & Ansible) · **Chapter:** 1 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/iac/01-why-infrastructure-as-code)

Cloud course-এ তুমি web console-এ click করে একটা server বানিয়েছিলে, তারপর SSH দিয়ে ঢুকে command টাইপ করতে করতে app চালু করেছিলে। কাজ হয়েছিল। এবার ভাবো, আজ রাতে server-টা মরে গেল, অথবা তোমার boss বললেন "শুক্রবারের মধ্যে ওটার মতো হুবহু আরও তিনটা চাই"। তুমি কি ঠিক **একই রকম** আবার বানাতে পারবে? স্মৃতি থেকে? এই course-এর উত্তর: setup-টাকে **code** হিসেবে লিখে রাখো, যাতে একটা tool সেটা দেখে বারবার, প্রতিবার একইভাবে বানিয়ে দেয়।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Terraform, OpenTofu ও Ansible **install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই এই chapter-এর code **উদাহরণ মাত্র, চালানো হয়নি**: এগুলো শুধু প্রতিটা tool-এর *চেহারা* দেখায়, আর প্রতিটা tool আসল command-সহ ঠিকমতো শেখানো হবে পরের chapter-গুলোয়। যা *যাচাই করা হয়েছে*: নিচের সংজ্ঞাগুলো Terraform, Ansible ও OpenTofu-র সরকারি documentation থেকে পড়া, YAML টুকরোটা যে বৈধ YAML (tool দিয়ে parse করা), আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। এখানের কিছুই কোনো আসল cloud account ছোঁয় না বা টাকা খরচ করায় না।

![হাতে server বানানো বনাম code থেকে বানানো](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/08-iac/images/by-hand-vs-as-code.svg)

## ১. একটা গল্প যা তুমি চিনবে

এক বছর আগে তুমি production server বানিয়েছিলে। PHP install করেছিলে, একটা config file বদলেছিলে, একটা cron job বসিয়েছিলে, firewall-এর একটা port খুলেছিলে, আর মাঝরাতে একটা অদ্ভুত permission সমস্যা ঠিক করেছিলে। কিছুই লিখে রাখোনি, কারণ সব "এমনিই চলছিল"।

এখন staging-এর জন্য দ্বিতীয় server লাগবে। তুমি ধাপগুলো আবার করার চেষ্টা করো, কিন্তু মাঝরাতের permission-এর ঠিকটা ভুলে যাও। Staging আর production আলাদা আচরণ করে, একটায় bug আসে আরেকটায় আসে না। কেউ বলতে পারে না কেন, কারণ **production server-এ ঠিক কী আছে তা কেউ জানে না**। সেটা হয়ে গেছে একটা *snowflake*: অনন্য, ভঙ্গুর, আর নকল করা অসম্ভব।

## ২. হাতে করার সমস্যা

| সমস্যা | দেখতে কেমন |
|---|---|
| **পুনরাবৃত্তি করা যায় না** | প্রতিটা নতুন server একটু আলাদা, কারণ কোনো মানুষ একটা ধাপ ভুলেছে |
| **লেখা নেই** | আসল setup থাকে কারও মাথায় আর shell history-তে |
| **Review করা যায় না** | চালানোর আগে কেউ change যাচাই করতে পারে না; একটা ভুল টাইপ সোজা production-এ যায় |
| **সেরে ওঠা ধীর** | Server মরলে আবার বানাতে ঘণ্টার পর ঘণ্টা মনে করতে হয় |
| **Drift** | সময়ের সাথে মানুষ সরাসরি জিনিস বদলায়, আর server ধীরে ধীরে তার কোনো বর্ণনার সাথেই মেলে না |

## ৩. ধারণাটা: Infrastructure as Code (IaC)

**Infrastructure as Code** মানে তোমার server, network, software আর setting-গুলোকে **text file-এ** বর্ণনা করা, আর একটা tool সেই file পড়ে আসল দুনিয়াকে তার সাথে মিলিয়ে দেবে।

File গুলো সাধারণ text, তাই software development থেকে যা জানো সবই পাও:

- **Git history**: কে, কখন, কী আর কেন বদলেছে
- **Code review**: চালানোর *আগে* সহকর্মী change পড়ে দেখে
- **Copy ও পুনর্ব্যবহার**: একই file staging আর production দুটোই বানায়
- **চাইলেই আবার বানানো**: মরা server-এর বদলে code আবার চালালেই নতুন server

Git course আর CI/CD course এখানে সরাসরি কাজে লাগে: infrastructure code-ও application code-এর মতোই branch আর pull request দিয়ে যায়।

## ৪. Declarative বনাম imperative

কম্পিউটারকে কী করতে হবে তা বলার দুটো উপায়:

| ধরন | তুমি বলো | উদাহরণ |
|---|---|---|
| **Imperative** | **কীভাবে**: ঠিক ধাপগুলো, ক্রমে | "`apt install git` চালাও, তারপর এই file বদলাও, তারপর ওই service restart করো" |
| **Declarative** | **কী**: তুমি যে শেষ ফল চাও | "Git install করা আছে; এই file-এ এই content আছে; service চলছে" |

সরকারি Terraform documentation তার configuration-কে *declarative* বলে: সেগুলো "infrastructure-এর শেষ অবস্থা বর্ণনা করে"। তুমি ফলটা বলো, tool ধাপগুলো বের করে। Ansible playbook-ও লেখা হয় system-কে **যে অবস্থায় চাও** সেটা বর্ণনা করে।

Shell script হলো imperative পদ্ধতির ক্লাসিক উদাহরণ। দুইবার চালালে দ্বিতীয়বার হয়তো ব্যর্থ হয় ("user আগে থেকেই আছে") বা কিছু দুইবার করে ফেলে। Declarative tool *তুমি যা চাও* আর *যা আছে* মিলিয়ে দেখে, আর শুধু পার্থক্যটুকু বদলায়।

## ৫. Idempotency: দুইবার চালালেও নিরাপদ

একটা খুব গুরুত্বপূর্ণ শব্দ: **idempotent**। কোনো কাজ idempotent হলো যদি সেটা **একবার করো বা দশবার, ফল একই**।

Ansible documentation বলে: যে system আগে থেকেই বর্ণিত অবস্থায় আছে তার ওপর playbook চালালে কিছুই বদলায় না, "playbook বহুবার চালালেও"।

এটাই IaC-কে নিরাপদ করে। "এটা কি আগে চালিয়েছিলাম?" মনে রাখতে হয় না। আবার চালাও, আর শুধু যা নেই বা আলাদা তা ঠিক হয়। একই ধারণায় তুমি **drift সারাতেও** পারো: code চালাও, আর server code-এর বলা অবস্থায় ফিরে আসে।

## ৬. দুটো tool, দুটো কাজ

এই course দুটো tool শেখায়। এরা **প্রতিদ্বন্দ্বী নয়**। এদের কাজ আলাদা, আর আসল project-এ প্রায়ই দুটোই ব্যবহার হয়।

| | **Terraform** (আর OpenTofu) | **Ansible** |
|---|---|---|
| **প্রধান কাজ** | **Provisioning**: infrastructure নিজেই বানানো (server, network, firewall, DNS record, database) | **Configuration**: machine-এর *ভেতরে* যা চলে তা সাজানো (package, config file, user, service) |
| **বাড়ির উপমা** | বাড়ি বানায়: দেয়াল, ঘর, দরজা | বাড়ি সাজায়: খাট, বাতি, রান্নাঘর |
| **কীভাবে কথা বলে** | Cloud provider-এর API দিয়ে, একটা *provider* plugin-এর মাধ্যমে | **SSH** দিয়ে, target machine-এ **কোনো agent install ছাড়া** |
| **ভাষা** | নিজস্ব configuration language (HCL) | YAML playbook |
| **কিছু মনে রাখে?** | হ্যাঁ: যা বানিয়েছে তার একটা **state file** রাখে | State file নেই: প্রতিবার machine যাচাই করে |

সরকারি docs থেকে: Terraform হলো "একটা infrastructure as code tool যা দিয়ে cloud ও on-prem resource বানানো, বদলানো ও version করা যায়"। Terraform একটা **execution plan** দেখায় (কী তৈরি, update বা destroy করবে) আর **অনুমোদন পেলে** তা চালায়। Ansible-কে বলা হয় **agentless**: সে বিদ্যমান credential দিয়ে SSH-তে সংযোগ করে, তাই managed machine-এ বাড়তি কিছু install করতে হয় না।

মনে রাখার সহজ উপায়: **Terraform server-টাকে অস্তিত্ব দেয়। Ansible server-টাকে কাজের করে।** সীমানাটা কড়া নয় (Ansible-ও cloud server বানাতে পারে, আর Terraform-ও কিছু setup command চালাতে পারে), কিন্তু এই ভাগটাই সাধারণ ও পরিষ্কার, আর এই course এটাই অনুসরণ করে।

### OpenTofu নিয়ে একটা কথা

২০২৩ সালে HashiCorp Terraform-কে open-source license থেকে সরিয়ে *Business Source License (BUSL)*-এ নেয়। তার জবাবে কিছু company মিলে শুরু করে **OpenTofu**, Terraform-এর একটা open-source **fork**, যা এখন Linux Foundation-এর অধীনে। তার FAQ অনুযায়ী সে Terraform-এর সাথে **feature parity** রাখতে চায় আর বিদ্যমান Terraform provider-এর সাথে কাজ করে। শিক্ষার্থীর জন্য ধারণা, ভাষা আর workflow একই। এই course "Terraform" বলে, আর যেখানে OpenTofu আলাদা সেখানে জানিয়ে দেয়। কাজে কোনটা নেবে ঠিক করার আগে নিজের ব্যবহারের সাথে প্রতিটা tool-এর license মিলিয়ে দেখো।

## ৭. Code-এর প্রথম স্বাদ (শুধু চেহারা)

প্রতিটা লাইন এখনই বুঝতে হবে **না**। শুধু *চেহারাটা* দেখো: দুটোই ছোট, পড়া যায়, আর একটা ফল বর্ণনা করে।

**Terraform** (ঘোষণা করে *একটা server থাকা উচিত*): শুধু উদাহরণ, image ID-টা placeholder।

```hcl
resource "aws_instance" "web" {
  ami           = "ami-0123456789abcdef0"   # placeholder, not a real image
  instance_type = "t3.micro"
}
```

**Ansible** (ঘোষণা করে *একটা package install থাকা উচিত*):

```yaml
- name: Set up the web server
  hosts: web
  become: true
  tasks:
    - name: Git is installed
      ansible.builtin.apt:
        name: git
        state: present
```

Ansible অংশটা জোরে পড়ো: "`web` নামের host-গুলোতে, administrator হিসেবে, নিশ্চিত করো `git` package **present** আছে।" `present` শব্দটাই declarative ধারণা এক শব্দে: তুমি "install করো" বলছ না; বলছ কী সত্যি হতে হবে। Git আগে থেকে থাকলে কিছুই হয় না।

Chapter 2-4-এ Ansible ঠিকমতো শেখানো হবে, আর Chapter 5-6-এ Terraform।

## ৮. IaC যা ঠিক করে *না*

- **এটা জাদু নয়।** Code ভুল হতে পারে। একটা খারাপ rule দশটা server-এ লাগালে দশটা server আরও দ্রুত ভাঙে। তাই *review* আর *plan/dry-run ধাপ* এত জরুরি, আর তুমি দুটোই ব্যবহার করবে।
- **শৃঙ্খলা লাগে।** মানুষ যদি তবু হাতে server বদলায়, code আর বাস্তব আলাদা হয়ে যায়। নিয়ম: **code বদলাও, server নয়।**
- **Secret-এ সাবধান।** Commit করা code-এ কখনো password বা key রেখো না। Chapter 4 Ansible Vault দেখায়, আর Chapter 6 দেখায় Terraform code ও state থেকে secret দূরে রাখা।
- **এটা আরেকটা শেখার জিনিস।** একটা ছোট server যা কখনো বদলায় না তার জন্য লিখে রাখা checklist-ই যথেষ্ট হতে পারে। IaC লাভজনক হয় যখনই **দ্বিতীয়** কপি লাগে, বা **পুনরাবৃত্তিযোগ্য** rebuild লাগে।

## ৯. তোমার roadmap

| Chapter | তুমি শিখবে |
|---|---|
| 2 | Ansible-এর গোড়ার কথা: inventory আর ad-hoc command |
| 3 | Ansible playbook, module, handler, idempotency |
| 4 | Ansible role, template, আর secret-এর জন্য Vault |
| 5 | Terraform-এর গোড়ার কথা: provider, resource, `plan` ও `apply` |
| 6 | Terraform state, variable, output, module |
| 7 | Terraform আর Ansible একসাথে ব্যবহার |
| 8 | Real project: code থেকে বানানো একটা Laravel server |

## ১০. নিজে যাচাই করো

<details>
<summary><strong>Q1. "Snowflake server" কী, আর এটা সমস্যা কেন?</strong></summary>

এমন server যার ঠিক setup শুধু server-টাতেই (আর কারও স্মৃতিতে) আছে, কারণ সেটা হাতে বানানো আর সময়ের সাথে বদলানো। এটা নির্ভরযোগ্যভাবে নকল বা আবার বানানো যায় না, আর কেউ ঠিক জানে না এতে কী আছে।
</details>

<details>
<summary><strong>Q2. "Infrastructure as Code" মানে কী?</strong></summary>

Server, network ও software-কে text file-এ বর্ণনা করা, যা একটা tool পড়ে আসল infrastructure-কে তার সাথে মেলায়, ফলে setup Git-এ version করা, review করা, পুনর্ব্যবহার করা ও আবার বানানো যায়।
</details>

<details>
<summary><strong>Q3. Declarative বনাম imperative: কোনটা "কী" বলে আর কোনটা "কীভাবে"?</strong></summary>

Declarative বলে **কী** শেষ অবস্থা চাও। Imperative বলে **কীভাবে**, ঠিক ধাপের তালিকা হিসেবে।
</details>

<details>
<summary><strong>Q4. "Idempotent" মানে কী, আর IaC-র জন্য এটা কেন জরুরি?</strong></summary>

কাজটা একবার বা বহুবার চালালে ফল একই। এতে IaC আবার চালানো নিরাপদ হয়, কারণ শুধু যা নেই বা আলাদা তা বদলায়, আর drift সারানো যায়।
</details>

<details>
<summary><strong>Q5. Terraform আর Ansible প্রধানত কী করে, এক বাক্যে করে?</strong></summary>

Terraform infrastructure **provision** করে (server, network, DNS ইত্যাদি বানায়)। Ansible machine-এর ভেতরে যা চলে তা **configure** করে (package, file, user, service)।
</details>

<details>
<summary><strong>Q6. Ansible-এর ক্ষেত্রে "agentless" মানে কী?</strong></summary>

যেসব machine manage হচ্ছে তাতে বাড়তি কিছু install করতে হয় না। Ansible বিদ্যমান credential দিয়ে SSH-তে সংযোগ করে।
</details>

<details>
<summary><strong>Q7. OpenTofu কী?</strong></summary>

Terraform-এর একটা open-source fork, যা Terraform-এর license BUSL-এ বদলানোর পরে শুরু হয় এবং এখন Linux Foundation-এর অধীনে। সে Terraform-এর সাথে feature parity রাখতে চায় আর বিদ্যমান Terraform provider-এর সাথে কাজ করে।
</details>

<details>
<summary><strong>Q8. IaC ব্যবহার করলে সোনালি নিয়ম কী?</strong></summary>

Code বদলাও, server নয়। Server-এ হাতে বদলালে drift হয়, আর code বাস্তবকে বর্ণনা করা বন্ধ করে।
</details>

## ১১. বিনামূল্যের resource

- Terraform docs: [What is Terraform?](https://developer.hashicorp.com/terraform/intro)
- Ansible docs: [Introduction to Ansible](https://docs.ansible.com/ansible/latest/getting_started/introduction.html)
- OpenTofu: [FAQ](https://opentofu.org/faq/)

---

**পরের chapter:** [Chapter 2 — Ansible-এর গোড়ার কথা: Inventory আর ad-hoc command](02-ansible-basics.md)
