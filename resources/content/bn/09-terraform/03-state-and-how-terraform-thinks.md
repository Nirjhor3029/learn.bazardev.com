# Chapter 3 — State, আর Terraform কীভাবে ঠিক করে কী বদলাবে

> **Course:** Terraform · **Chapter:** 3 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/terraform/03-state-and-how-terraform-thinks)

Terraform-এ প্রতিটা চমক, ভালো বা খারাপ, একটা জিনিসে ফিরে আসে: **state**। কেন শুধু কিছুর নাম বদলালে `plan` "destroy আর create" বলে? Terraform কীভাবে টের পায় কেউ হাতে server মুছে ফেলেছে? একটা resource "ভুলে যাওয়ানো" মানে কী? Terraform **তিনটা জিনিস** কীভাবে তুলনা করে তা বুঝলে এই প্রশ্নগুলোর উত্তর নিজেই মেলে। এই chapter তা শেখায়, আর Chapter 2-এর Docker project দিয়ে তোমার নিজের machine-এ দেখায়।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Terraform ও OpenTofu **install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের command গুলো সরকারি Terraform documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: state-এর উদ্দেশ্য ও ডিফল্ট জায়গা, `terraform state` subcommand (`list`, `show`, `mv`, `rm`), `-dry-run`, `-refresh-only`, `-refresh=false` ও `-replace` option, state বদলানো command যে backup লেখে সেই নিয়ম, আর import-এর বর্ণনা সরকারি docs থেকে পড়া, আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। **Terraform-এর বার্তার ঠিক ভাষা উদ্ধৃত করা হয়নি**, কারণ চালানো হয়নি: chapter বলে docs কী ঘটে বলে, আর আসল লেখা তুমি তোমার machine-এ দেখবে। নিচের কিছু পরীক্ষায় **container মুছে ফেলা** বা **state বদলানো** হয়। এগুলো শুধু Chapter 1 ও 2-এর ফেলনা Docker project-এর জন্য, একটা practice machine-এ, আসল কিছুর জন্য কখনো নয়।

![Terraform তিনটা জিনিস তুলনা করে: তোমার configuration, তার state, আর আসল দুনিয়া](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/09-terraform/images/three-things-state.svg)

## ১. Terraform যে তিনটা জিনিস তুলনা করে

| জিনিস | কী | কোথায় থাকে |
|---|---|---|
| **Configuration** | তুমি যা **চাও** | তোমার `.tf` file |
| **State** | Terraform যা **manage করে বলে বিশ্বাস করে**, আর কোন আসল object কোন resource block-এর | একটা file, ডিফল্টে `terraform.tfstate` |
| **আসল দুনিয়া** | যা **সত্যিই আছে** | Docker, একটা cloud provider, DNS... |

Docs থেকে: "Terraform তোমার workspace-এর state ব্যবহার করে আসল দুনিয়ার resource-কে তোমার configuration-এর সাথে map করতে।" State ছাড়া Terraform বলতে পারত না *কোন* container `docker_container.nginx`-এর। State metadata-ও রাখে আর বড় project দ্রুত করে।

একটা `plan` হলো Terraform-এর উত্তর "আমি কীভাবে দুনিয়াকে configuration-এর সাথে মেলাব?" Docs অনুযায়ী যেকোনো কাজের আগে Terraform আসল infrastructure-এর বিরুদ্ধে তার state **refresh** করে, তারপর তোমার configuration-কে state-এর সাথে তুলনা করে, আর কাজ প্রস্তাব করে। পুরো algorithm এটাই:

1. **আসল দুনিয়া দেখো** আর যা জানে তা update করো।
2. Configuration ও state **তুলনা করো**।
3. পার্থক্য **প্রস্তাব করো**: create, change, replace, destroy।

এই ছবিটা মনে রাখো। নিচের সবকিছু এর একটা রূপভেদ।

## ২. State আসলে কী, বাস্তবে

ডিফল্টে "Terraform প্রতিটা workspace-এর state `terraform.tfstate` নামের একটা স্থানীয় file-এ রাখে"। এটা একটা **JSON text file**, আর আগের সংস্করণ পাশে একটা backup file-এ থাকে। Docs ও Chapter 1 থেকে তিনটা নিয়ম:

1. **হাতে edit করো না।** Docs: "এই file সরাসরি edit করো না।" সাধারণ পরিবর্তনের জন্য `terraform state` command ব্যবহার করো।
2. **এতে secret সাদা text-এ থাকতে পারে।** Variable `sensitive` করলে তা state থেকে সরে না (Chapter 2)। কখনো commit করো না, chat বা ticket-এ paste করো না।
3. **এটা মূল্যবান।** হারিয়ে গেলে Terraform আর জানে না কোন আসল object-এর মালিক সে। পরের plan সবকিছু আবার বানাতে চাইবে, আর যা আগে থেকে আছে তার সাথে সংঘাত হতে পারে।

তুমি এটা সাবধানে পড়তে পারো, না বদলিয়ে (pager ব্যবহার করো, আর output ভাগ করো না):

```bash
less terraform.tfstate
```

## ৩. নিরাপদে state-এর ভেতরে দেখা

JSON পড়ার বদলে এর জন্য বানানো command ব্যবহার করো। এগুলো শুধু **পড়ে**:

```bash
terraform state list                       # every resource Terraform knows about
terraform state show docker_container.nginx   # details of one resource
terraform show                             # the whole state in readable form
```

Docs অনুযায়ী `terraform state list` "একটা configuration-এ Terraform যে প্রতিটা resource জানে তার resource address" দেখায়, আর address-এর অংশ দিয়ে ছাঁটা যায়। `terraform state show` "একটা resource-এর বিস্তারিত state ডেটা দেখায়"। `docker_container.nginx`-এর মতো জিনিসকে বলে **resource address**: block-এর type, একটা dot, আর তোমার দেওয়া নাম।

## ৪. পরীক্ষা ১: কেউ আসল দুনিয়া বদলায়

এটা এই course-এর সবচেয়ে কাজের পরীক্ষা। Chapter 2-এর শেষ করা project থেকে শুরু করো, apply করা, container চলছে। (নিচের নামগুলো ডিফল্ট `dev-nginx` ব্যবহার করে। অন্য মান দিয়ে apply করলে তোমার container-এর নাম ব্যবহার করো।)

1. Terraform খুশি কি না দেখো:

```bash
terraform plan
```

এটা জানানো উচিত যে আসল infrastructure configuration-এর সাথে মেলে, বদলানোর কিছু নেই।

2. এবার **Terraform-এর বাইরে** কাজ করো, যেমন একজন অসাবধান সহকর্মী করতে পারে। Docker দিয়ে হাতে container সরাও:

```bash
docker rm -f dev-nginx
```

3. আবার plan চালাও:

```bash
terraform plan
```

Terraform আগে তার state refresh করে, তাই সে **টের পায় container নেই**। Configuration এখনো বলছে এটা থাকা উচিত, তাই plan আবার **create** করার প্রস্তাব দেয় (`+`)। কেউ Terraform-কে বলেনি; সে আসল দুনিয়া দেখেছে। এটাই **drift**: Terraform-এর অগোচরে দুনিয়া বদলেছে।

4. এখন তোমার একটা পছন্দ আছে, আর plan-এর option গুলো ঠিক এজন্যই:

| তুমি কী চাও | Command |
|---|---|
| code-এর সাথে মেলাতে **দুনিয়াকে ফেরাও** (container আবার বানাও) | `terraform apply` |
| **পরিবর্তন মেনে নাও** আর শুধু Terraform-এর রেকর্ড update করো, কিছু না ছুঁয়ে | `terraform apply -refresh-only` |

Docs `-refresh-only`-কে এমন plan বলে যার "লক্ষ্য শুধু Terraform-এর state ও root module-এর output মান update করা" "Terraform-এর বাইরে আসল object-এ করা পরিবর্তনের" সাথে মেলাতে, যেমন কোনো incident-এর পরে। রেকর্ড update-এর **preview** আগে দেখতে `terraform plan -refresh-only` ব্যবহার করো। লক্ষ্য করো, শুধু রেকর্ড update করলে কিন্তু তোমার configuration এখনো container ঘোষণা করলে *পরের* সাধারণ plan সেটা আবার বানানোর প্রস্তাব দেবে। রেকর্ড মেলানো আর code ঠিক করা দুটো আলাদা সিদ্ধান্ত।

Container ফেরাতে `terraform apply` চালাও। Drift-এর কারণেই IaC course বলেছিল: **code বদলাও, server নয়**।

### সম্পর্কিত একটা option, `-refresh=false`

`terraform plan -refresh=false` "আসল দুনিয়া দেখো" ধাপ বাদ দেয়। Docs সতর্ক করে এটা "বাইরের পরিবর্তন উপেক্ষা করতে বাধ্য করে, যার ফলে plan অসম্পূর্ণ বা ভুল হতে পারে"। বিশাল project-এ এটা দ্রুত, কিন্তু drift লুকাতে পারে। এটাকে অভ্যাস করো না।

## ৫. পরীক্ষা ২: `-replace` দিয়ে rebuild বাধ্য করা

কখনো একটা আসল object আছে কিন্তু খারাপ অবস্থায় (একটা container ঠিকমতো চলছে না)। Code না বদলেও তুমি চাও Terraform সেটা **মুছে আবার বানাক**। Docs-এর উত্তর `-replace` option:

```bash
terraform plan  -replace=docker_container.nginx    # preview
terraform apply -replace=docker_container.nginx    # do it
```

Docs অনুযায়ী এটা "Terraform-কে দেওয়া address-এর resource instance প্রতিস্থাপন করার plan করতে নির্দেশ দেয়", "এক বা একাধিক আসল object খারাপ হয়ে গেলে সাহায্য করে", একাধিক object প্রতিস্থাপনে বারবার দেওয়া যায়, আর `-destroy`-এর সাথে মেশানো যায় না। Plan-এ `-/+` খোঁজো (Chapter 1)।

## ৬. পরীক্ষা ৩: resource-এর নাম বদলানো, আর `terraform state mv`

`main.tf` খুলে `docker_container "nginx"` block-এর নাম `docker_container "web"` করো। (`docker_container.nginx.name` উল্লেখ করা output-ও update করো।) `terraform plan` চালাও।

Terraform **জানে না তুমি নাম বদলেছ**। সে দেখে: "`docker_container.nginx` state-এ আছে কিন্তু configuration-এ নেই, তাই এটা destroy করো; `docker_container.web` configuration-এ আছে কিন্তু state-এ নেই, তাই এটা create করো।" নাম বদল হয়ে যায় একটা destroy আর একটা create। একটা container-এর জন্য এটা নিরীহ। একটা **database**-এর জন্য এটা বিপর্যয়।

Docs-এর সমাধান Terraform-কে বলা যে বিদ্যমান object-এর একটা **নতুন address**:

```bash
terraform state mv docker_container.nginx docker_container.web
```

Docs অনুযায়ী `terraform state mv` "Terraform state-এ binding বদলায়, যাতে বিদ্যমান আসল object নতুন resource instance-এর সাথে বাঁধা হয়।" দুটো address একই ধরনের resource-কে নির্দেশ করতে হবে। আবার `terraform plan` চালাও: এখন বদলানোর কিছু না দেখানো উচিত। Docs একটা সতর্কতা যোগ করে: তোমার configuration edit আর `state mv`-এর মাঝে কোনো সহকর্মী যেন পরিবর্তন না করে, নইলে একটা plan object-টাকে destroy করে আবার বানাতে পারে।

## ৭. `terraform state rm`: Terraform-কে ভুলিয়ে দাও

```bash
terraform state rm -dry-run docker_container.web   # preview what would be forgotten
terraform state rm docker_container.web            # forget it
```

Docs অনুযায়ী এটা "বিদ্যমান আসল object-এর binding সরায় **আগে destroy না করেই**"। Container চলতে থাকে, কিন্তু Terraform আর তাকে manage করে না। পরিণতি বুঝে নাও: পরের `terraform plan` "প্রতিটা 'ভুলে যাওয়া' instance-এর জন্য একটা নতুন object create করার action রাখবে", আর নাম আগের object-এর সাথে মিলে গেলে create ব্যর্থ হতে পারে। Docs এটা তোমার সাধারণ plan-ও-review workflow-এর ভেতরে করতে `removed` block সুপারিশ করে। `state rm`-কে একটা **অস্ত্রোপচারের যন্ত্র** ধরো: আগে সবসময় `-dry-run`। `-lock=false` এড়াও, যা docs-এর মতে বিপজ্জনক যদি অন্যরা একই workspace-এ command চালাতে পারে।

(এই পরীক্ষার পরে container এখনো চলছে, কিন্তু Terraform আর তার মালিক নয়। শুধু `terraform apply` চালিও **না**: এটা একই নামে দ্বিতীয় একটা container বানানোর চেষ্টা করবে আর সংঘাত হবে। তোমার practice project পরিষ্কার করতে বাকি container হাতে সরাও (`docker rm -f dev-nginx`), তারপর `terraform apply` চালিয়ে একটা নতুন, manage করা container বানাও, অথবা import (৯ নম্বর অংশ) দিয়ে বিদ্যমানটা ফিরিয়ে আনো।)

## ৮. Backup, আর কেন এটা গুরুত্বপূর্ণ

State **বদলায়** এমন যেকোনো command আগে একটা backup file লেখে। Docs: "state বদলানোর backup বন্ধ করা যায় না", "Terraform প্রতিটা state-বদলানো command-কে একটা backup file লিখতে বাধ্য করে", আর রাখতে না চাইলে হাতে মুছতে হয়। `list`-এর মতো শুধু-পড়া command কিছু লেখে না। তাই `state mv` ও `state rm` হাতে edit করার মতো ভয়ংকর নয়, কারণ ফেরার পথ রেখে যায়। Backup file-এ state-এর একই secret থাকে, তাই একই যত্ন ও একই `.gitignore` এন্ট্রি লাগে (Chapter 1-এর `*.tfstate.*` এটা ঢাকে)।

## ৯. বিদ্যমান infrastructure: import

যদি একটা server আগে থেকেই থাকে, হাতে বানানো, আর তুমি চাও Terraform তা manage করুক? শুধু একটা `resource` block লিখলে Terraform **দ্বিতীয় একটা বানানোর** চেষ্টা করবে। **Import** একটা বিদ্যমান object-কে state-এ একটা resource address-এর সাথে জুড়ে দেয়। Docs অনুযায়ী তুমি "সেগুলো তোমার Terraform workspace-এ import করতে পারো যাতে code হিসেবে manage শুরু করতে পারো", আর address-এর সাথে মেলে এমন একটা গন্তব্য `resource` block লিখতে হয়। Docs একটা `import` block-ও বর্ণনা করে (এটা import করার resource ID-র নাম দেয়) আর একটা plan option যা তোমার জন্য মিলে যাওয়া `resource` block **তৈরি** করতে পারে। ঠিক syntax তোমার Terraform version ও provider-এর ওপর নির্ভর করে, তাই Terraform import page পড়ো, আর আগে নিরীহ কিছুতে চেষ্টা করো। Import-এর মাধ্যমেই হাতে বানানো server-কে "code বদলাও, server নয়" নিয়মের আওতায় আনা যায়।

## ১০. State ও দলের কাজ: একটা আভাস

একা তোমার জন্য স্থানীয় state file ঠিক আছে। **দলের** জন্য এটা বিপজ্জনক: দুজন একসাথে apply করতে পারে, বা একজন পুরনো কপি ব্যবহার করতে পারে। Docs তার বদলে একটা **remote backend** (বা HCP Terraform) সুপারিশ করে, আর এমন version-control system নিয়ে সতর্ক করে যাতে **state locking** নেই, যা দুটো run-কে একই state একসাথে বদলাতে আটকায়। Chapter 7 remote state, locking ও workspace দেখায়। এখন নিয়মটা মনে রাখো: **একটা state, একটা নিরাপদ ঘর, কখনো Git-এ নয়।**

## ১১. যে অভ্যাস তোমাকে নিরাপদ রাখে

1. **তিনটায় ভাবো:** configuration, state, আসল দুনিয়া। Plan অবাক করলে জিজ্ঞেস করো কোন দুটো মিলছে না।
2. প্রতিবার **plan পড়ো** `-` ও `-/+`-এর জন্য।
3. **State কখনো হাতে edit করো না।** `terraform state ...` command ব্যবহার করো, আর আগে `-dry-run`।
4. **`state mv` দিয়ে নাম বদলাও,** নইলে নাম বদল হয়ে যায় destroy আর create।
5. **Drift-কে সংকেত ধরো।** সিদ্ধান্ত নাও: দুনিয়া ফেরাবে (`apply`), নাকি মেনে নেবে (`apply -refresh-only` আর code ঠিক করো)।
6. **`-refresh=false` বা `-lock=false`-কে অভ্যাস বানিও না।**
7. **State আর তার backup রক্ষা করো** password file-এর মতো।

## ১২. নিজে যাচাই করো

<details>
<summary><strong>Q1. Plan করার সময় Terraform কোন তিনটা জিনিস তুলনা করে?</strong></summary>

তোমার configuration (তুমি যা চাও), state (Terraform যা manage করে বলে বিশ্বাস করে), আর আসল দুনিয়া (যা সত্যিই আছে)।
</details>

<details>
<summary><strong>Q2. একজন সহকর্মী হাতে একটা container মুছে ফেলল। পরের `terraform plan` কী দেখায়, আর কেন?</strong></summary>

এটা আবার create করার একটা plan (`+`)। Terraform আগে আসল infrastructure-এর বিরুদ্ধে তার state refresh করে, দেখে object নেই, আর configuration এখনো বলছে থাকা উচিত।
</details>

<details>
<summary><strong>Q3. `terraform apply -refresh-only` কী করে?</strong></summary>

আসল infrastructure না বদলিয়ে Terraform-এর state (ও output) Terraform-এর বাইরে করা পরিবর্তনের সাথে মেলায়।
</details>

<details>
<summary><strong>Q4. Code-এ `docker_container "nginx"`-এর নাম `"web"` করলে। Plan কী দেখায়, আর কী ঠিক করে?</strong></summary>

পুরনো address-এর destroy আর নতুনটার create, কারণ Terraform জানে না এটা একই object। `terraform state mv docker_container.nginx docker_container.web` binding সরিয়ে এটা ঠিক করে।
</details>

<details>
<summary><strong>Q5. `terraform state rm` কী করে, আর পরের plan-এ কী হয়?</strong></summary>

এটা আসল object destroy না করে তার binding সরায়, ফলে Terraform তাকে ভুলে যায়। পরের plan প্রতিটা ভুলে যাওয়া object-এর জন্য একটা নতুন object বানানোর প্রস্তাব দেবে, যা বিদ্যমান object-এর সাথে সংঘাত করতে পারে।
</details>

<details>
<summary><strong>Q6. Code না বদলিয়ে একটা resource আবার বানাতে Terraform-কে কীভাবে বাধ্য করবে?</strong></summary>

`terraform apply -replace=ADDRESS`, যেমন `-replace=docker_container.nginx`। আগে `terraform plan -replace=...` দিয়ে preview দেখো।
</details>

<details>
<summary><strong>Q7. `terraform.tfstate` কেন কখনো হাতে edit করবে না, আর তার বদলে কী ব্যবহার করবে?</strong></summary>

এটা JSON-এ Terraform-এর সত্যের উৎস, আর একটা ভুলে Terraform আসল object-এর হিসাব হারাতে পারে। `terraform state` command ব্যবহার করো, যেগুলো backup-ও লেখে।
</details>

<details>
<summary><strong>Q8. দলের জন্য স্থানীয় state file সমস্যা কেন?</strong></summary>

দুজন একসাথে apply করতে পারে বা পুরনো কপি ব্যবহার করতে পারে। State locking সহ একটা remote backend সুপারিশ করা হয়, আর state কখনো Git-এ রাখা চলবে না।
</details>

## ১৩. বিনামূল্যের resource

- Terraform docs: [State](https://developer.hashicorp.com/terraform/language/state) ও [Sensitive data in state](https://developer.hashicorp.com/terraform/language/state/sensitive-data)
- Terraform docs: [`terraform state`](https://developer.hashicorp.com/terraform/cli/commands/state), [Inspect state](https://developer.hashicorp.com/terraform/cli/state/inspect), [`state mv`](https://developer.hashicorp.com/terraform/cli/commands/state/mv), [`state rm`](https://developer.hashicorp.com/terraform/cli/commands/state/rm)
- Terraform docs: [`terraform plan`](https://developer.hashicorp.com/terraform/cli/commands/plan) (`-refresh-only`, `-replace`, `-refresh=false`-এর জন্য) ও [Import](https://developer.hashicorp.com/terraform/language/import)

---

**পরের chapter:** Chapter 4 — Module: পুনর্ব্যবহারযোগ্য infrastructure *(শীঘ্রই আসছে)*
