# Chapter 4 — Module: পুনর্ব্যবহারযোগ্য infrastructure

> **Course:** Terraform · **Chapter:** 4 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/terraform/04-modules)

এখন তোমার একটা Docker web server code-এ আছে। যদি **দুটো** লাগে: একটা `dev`-এর জন্য port 8000-এ আর একটা `staging`-এর জন্য port 8080-এ? Block কপি করলে একবার কাজ হয়, তারপর প্রতিটা ঠিক দুইবার করতে হয়, আর কপিগুলো ধীরে ধীরে আলাদা হয়ে যায়। Ansible role যে সমস্যা সমাধান করেছিল এটা সেটাই। Terraform-এ উত্তর হলো **module**: Terraform code-এর একটা folder যা তুমি একবার লেখো আর যতবার খুশি **ডাকো**, আলাদা input দিয়ে।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Terraform ও OpenTofu **install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের code ও command গুলো সরকারি Terraform documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: module কী (root ও child module), `module` block (`source`, `version`, input, `module.<label>.<output>`), meta-argument, source-এর ধরন, প্রমিত module কাঠামো, কখন module লিখবে সেই পরামর্শ, module-এ provider-এর নিয়ম, আর `moved` block সরকারি docs থেকে পড়া, আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। **HCL Terraform দিয়ে parse করা হয়নি**, তাই ছোট ভুল থাকতে পারে। উদাহরণ Chapter 1 থেকে 3-এর Docker project-এর ওপর দাঁড়ানো আর এতে কোনো খরচ নেই। একটা practice machine-এ চেষ্টা করো, আর `yes` টাইপ করার আগে প্রতিটা plan পড়ো।

![একটা module, আলাদা input দিয়ে দুইবার ডাকা](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/09-terraform/images/module-called-twice.svg)

## ১. Module কী

সরকারি docs থেকে: module হলো "resource-এর একটা সংগ্রহ যা Terraform একসাথে manage করে।" দুটো শব্দ গুরুত্বপূর্ণ:

| শব্দ | মানে |
|---|---|
| **Root module** | যে folder-এ তুমি Terraform চালাও সেখানকার configuration file। **প্রতিটা** Terraform project-এর একটা আছে: Chapter 1 থেকে 3-এ তুমি যা লিখেছ সবই একটা root module |
| **Child module** | এমন module যাকে অন্য module একটা `module` block দিয়ে **ডাকে**। একটা root module child module একাধিকবার ডাকতে পারে, আর একটা child নিজের child-ও ডাকতে পারে |

তাই তুমি সারাক্ষণ module-ই লিখে এসেছ। এই chapter একটা থেকে আরেকটাকে **ডাকা** নিয়ে। Docs-এর কারণ: "যখন তুমি বারবার একই ধরনের resource-এর গোষ্ঠী provision করো", পুনর্ব্যবহারযোগ্য module দলগুলোকে "provision প্রমিত করতে আর দরকারি resource আরও দ্রুত ও অনুমানযোগ্যভাবে deploy করতে" সাহায্য করে।

## ২. Module-এর একটা interface থাকে

ভালো module একটা function-এর মতো দেখায়। এর **input** (variable), **output** আর resource-এর একটা body আছে। Docs: module "root module-এর একই configuration ধারণা ব্যবহার করে, একটা পরিষ্কার interface সহ"। প্রস্তাবিত ন্যূনতম কাঠামো ("প্রমিত module কাঠামো") চারটা file:

```text
modules/web_server/
├── README.md       # what this module is for
├── main.tf         # the resources
├── variables.tf    # the inputs
└── outputs.tf      # the outputs
```

Docs সুপারিশ করে **প্রতিটা variable ও output-কে একটা description** দিতে, আর nested module `modules/` directory-র নিচে রাখতে, relative path দিয়ে ডাকতে (যেমন `./modules/web_server`)। `main.tf`, `variables.tf` ও `outputs.tf` নাম একটা রীতি, যাতে মানুষ জানে কোথায় দেখতে হবে।

## ৩. একটা module লেখা: `web_server`

এই project-এর কাঠামো বানাও:

```text
terraform-modules-demo/
├── main.tf              # the root module
├── outputs.tf
└── modules/
    └── web_server/
        ├── main.tf
        ├── variables.tf
        └── outputs.tf
```

`modules/web_server/variables.tf`: input। একটা module-এর variable-ই হলো ডাকার লোক তাকে যেভাবে configure করে।

```hcl
variable "name" {
  type        = string
  description = "Name of the container"
}

variable "external_port" {
  type        = number
  description = "Port on this machine that reaches the web server"

  validation {
    condition     = var.external_port >= 1024 && var.external_port <= 65535
    error_message = "Use a port between 1024 and 65535."
  }
}
```

`modules/web_server/main.tf`: resource।

```hcl
terraform {
  required_providers {
    docker = {
      source  = "kreuzwerker/docker"
      version = ">= 4.2.0"
    }
  }
}

resource "docker_image" "nginx" {
  name         = "nginx:latest"
  keep_locally = true
}

resource "docker_container" "web" {
  image = docker_image.nginx.image_id
  name  = var.name

  ports {
    internal = 80
    external = var.external_port
  }
}
```

`modules/web_server/outputs.tf`: module যা ফেরত দেয়।

```hcl
output "container_name" {
  description = "Name of the running container"
  value       = docker_container.web.name
}

output "url" {
  description = "Where to open the web server"
  value       = "http://localhost:${var.external_port}"
}
```

তিনটা খুঁটিনাটি বুঝে নেওয়ার মতো:

1. **Module-এর ভেতরে `provider "docker"` block নেই।** Docs কড়া: "অন্য module দ্বারা ডাকা হওয়ার জন্য বানানো module-এ কোনো `provider` block থাকা চলবে না।" Child module তার parent থেকে provider configuration **উত্তরাধিকারে** পায়। তবে শুধু *configuration* উত্তরাধিকারে আসে, তাই module এখনো তার **নিজের চাহিদা** ঘোষণা করে: "প্রতিটা module-কে নিজের provider requirement ঘোষণা করতে হবে"। ওপরের `required_providers` block সেটাই।
2. **Module-এ `version = ">= 4.2.0"`, root-এ `~> 4.2.0`।** Docs অনুযায়ী পুনর্ব্যবহারযোগ্য module শুধু **সর্বনিম্ন** অনুমোদিত version সীমিত করবে, আর root module (project) দুই সীমা ঠিক করতে `~>` constraint ব্যবহার করবে। Module বলে "আমার অন্তত এটা লাগবে"; project ঠিক করে সে ঠিক কী চালাবে।
3. **`keep_locally = true`।** এই module-এর দুটো কপি দুজনেই `nginx:latest`-এর একটা image resource বানায়। একজন destroy হওয়ার সময় image মুছে ফেলতে পারলে অন্যজন যেটা এখনো ব্যবহার করছে সেটা সরে যেতে পারত। Image স্থানীয়ভাবে রাখলে এই সংঘাত এড়ানো যায়। (আসল module প্রায়ই image input হিসেবে নেয়।)

## ৪. Module ডাকা

Root-এর `main.tf`। এখানে docs-এর `module` block-এর রূপ: একটা `source`, ঐচ্ছিক `version`, তারপর module যে **input** সংজ্ঞায়িত করেছে সেগুলো argument হিসেবে।

```hcl
terraform {
  required_providers {
    docker = {
      source  = "kreuzwerker/docker"
      version = "~> 4.2.0"
    }
  }
}

provider "docker" {}

module "web_dev" {
  source        = "./modules/web_server"
  name          = "dev-web"
  external_port = 8000
}

module "web_staging" {
  source        = "./modules/web_server"
  name          = "staging-web"
  external_port = 8080
}
```

একটা module, দুটো **instance**: প্রতিটা নিজের input পায় আর নিজের resource manage করে। Root-এর `outputs.tf` module-এর output পড়ে `module.<label>.<output>` দিয়ে:

```hcl
output "dev_url" {
  value = module.web_dev.url
}

output "staging_url" {
  value = module.web_staging.url
}
```

যে loop জানো সেটা চালাও। একটা module-এর `source` (বা registry module হলে `version`) যোগ বা বদলানোর পরে `terraform init` চালাও, যাতে Terraform module-এর code load করতে পারে। Docs এটা `source` ও `version` বদলানোর জন্য বলে; বাস্তবে, যখনই একটা module-call যোগ করো `init` চালাও।

```bash
terraform init
terraform validate
terraform plan
terraform apply
terraform output
terraform destroy
```

Plan-এ resource-গুলো module-সহ address-এ তালিকাভুক্ত হয়। Module-এর ভেতরের একটা resource-এর address `module.<label>.<resource address>`:

```text
module.web_dev.docker_container.web
module.web_dev.docker_image.nginx
module.web_staging.docker_container.web
module.web_staging.docker_image.nginx
```

`terraform state list` দিয়ে দেখো (Chapter 3)। Module label-ই state-এ দুটো instance-কে আলাদা রাখে।

## ৫. Module কোথা থেকে আসতে পারে

`source` argument বলে module কোথায় আছে। Docs থেকে, module আসতে পারে "public বা private Terraform registry, Git repository, object storage service ও স্থানীয় file system" থেকে।

```hcl
# a folder on disk (what we used)
module "servers" {
  source = "./app-cluster"
}

# the public Terraform Registry, with a version constraint
module "vpc" {
  source  = "terraform-aws-modules/vpc/aws"
  version = "6.0.1"
}

# a Git repository, pinned to a tag, branch, or commit with ?ref=
module "vpc" {
  source = "git::https://example.com/vpc.git?ref=v1.2.0"
}
```

Registry module-এ `version` constraint দাও। Git-এ "একটা branch, SHA-1 hash বা tag" সহ `?ref=` দাও। `version` argument **শুধু** registry module-এ কাজ করে।

**একটা নিরাপত্তার অভ্যাস, docs থেকে নয় অভিজ্ঞতা থেকে:** তুমি লেখোনি এমন module হলো **তোমার credential নিয়ে চলা code**, যা ঠিক করে তোমার account-এ কী তৈরি হবে। ব্যবহারের আগে পড়ো, আর একটা ঠিক version বা Git tag বা commit-এ pin করো, branch-এর মতো চলমান লক্ষ্যে কখনো নয়।

## ৬. কখন module লিখবে, কখন না

Docs স্পষ্ট পরামর্শ দেয়, আর এটা ভুল করা সহজ:

- **করো:** "একটা ভালো module architecture-এ একটা নতুন ধারণা বর্ণনা করে abstraction-এর স্তর উঁচু করা উচিত।" Docs-এর উদাহরণ কয়েকটা resource দিয়ে বানানো একটা পুরো Consul cluster। আমাদেরটা, "একটা web server" (একটা image, একটা container, একটা port), একই ধারণার ছোট রূপ।
- **করো না:** "আমরা এমন module লেখার সুপারিশ করি না যা শুধু অন্য একটা resource type-এর পাতলা মোড়ক।" Module-এর নাম যদি শুধু তার একটা resource type পুনরাবৃত্তি করে, resource সরাসরি ব্যবহার করো।
- **সংযত থাকো:** "module-এর অতিরিক্ত ব্যবহার তোমার পুরো Terraform configuration বোঝা ও রক্ষণাবেক্ষণ কঠিন করতে পারে।"
- **গাছ সমতল রাখো:** গভীর nesting-এর বদলে **composition** পছন্দ করো (root পাশাপাশি কয়েকটা ছোট module ডাকে), কারণ এতে "আলাদা module গুলো আলাদা সমন্বয়ে পুনর্ব্যবহার করা সহজ হয়।"

একটা কাজের পরীক্ষা: তুমি কি module-টাকে resource type-এর নাম **না নিয়ে** এক বাক্যে বর্ণনা করতে পারো? "একটা web server যা আমরা যেকোনো port-এ চালাতে পারি" পাস করে। "`docker_container`-এর মোড়ক" করে না।

## ৭. বিদ্যমান resource module-এ সরানো: `moved` block

ধরো তুমি Chapter 2-এর project apply করেছ, root-এ `docker_container.nginx` সহ। এখন তুমি এটা একটা module-এর ভেতরে চাও। Terraform জানার উপায় নেই যে পুরনো আর নতুন address একই object, তাই Chapter 3-এর মতো সে পুরনোটা **destroy** আর নতুনটা **create** করার plan করবে। `moved` block এটা ঘোষণামূলকভাবে ঠিক করে। Docs থেকে, এটা "একটা resource address update করে তাকে destroy না করে":

```hcl
moved {
  from = docker_container.nginx
  to   = module.web_dev.docker_container.web
}
```

Docs অনুযায়ী `moved` block resource-এর নাম বদলাতে, একটা resource child module-এ সরাতে (ওপরের মতো), module-call-এর নাম বদলাতে (`from = module.a`, `to = module.b`), আর `count` বা `for_each` যোগ করলে বিদ্যমান resource-কে একটা instance key দিতে কাজ করে। Module address-এর জন্য Terraform v1.1 বা তার পরের version লাগে; পুরনো version-এ `terraform state mv` ব্যবহার করো। একটা module শুধু তার **নিজের** ও তার child-এর object নিয়ে `moved` বিবৃতি দিতে পারে। Chapter 3-এর `terraform state mv`-এর তুলনায় `moved` block **code-এ লেখা**, তাই pull request-এ review হয় আর যে-ই code চালায় তার জন্যই কাজ করে, শুধু তোমার জন্য নয়।

সাধারণ নিয়ম: **আগে সরাও, পরে বদলাও।** শুধু সরানোর জন্য plan যেন *কোনো destroy না* দেখায়। সাথে কোনো argument বদলালে (ধরো container-এর নাম), Terraform তবু তা প্রতিস্থাপনের plan করবে। `-/+` খোঁজো।

## ৮. Meta-argument, আর পরে যা আসছে

একটা `module` block `count`, `for_each` (একটা অন্যটার সাথে একসাথে চলে না), `depends_on` ও `providers`-ও নেয়। `for_each` একটা `module` block-কে একটা set বা map থেকে অনেক instance বানাতে দেয়। Chapter 6 `count`, `for_each` ও dependency ঠিকমতো শেখায়। এখন, ওপরের মতো প্রতি block-এ একবার module ডাকাই যথেষ্ট ও স্পষ্ট।

## ৯. ভালো অভ্যাস

1. **আগে interface:** type ও description সহ variable, description সহ output।
2. **পুনর্ব্যবহারযোগ্য module-এ `provider` block নয়,** কিন্তু `required_providers` ঘোষণা করো।
3. **Module: সর্বনিম্ন version; root: `~>`।**
4. **Abstraction উঁচু করো।** Module একটা resource-এর পাতলা মোড়ক হলে সেটা মুছে ফেলো।
5. **গাছ সমতল রাখো,** আর root-এ ছোট module জোড়া লাগাও।
6. **যা তুমি লেখোনি তা pin করো,** আর আগে পড়ো।
7. **বদলানোর আগে সরাও** (`moved` block বা `state mv`), আর plan যে destroy দেখায় না তা যাচাই করো।

## ১০. নিজে যাচাই করো

<details>
<summary><strong>Q1. Root module কী?</strong></summary>

যে folder-এ তুমি Terraform চালাও সেখানকার Terraform configuration file। প্রতিটা project-এর একটা আছে, আর এটা child module ডাকতে পারে।
</details>

<details>
<summary><strong>Q2. ডাকার লোক কীভাবে module configure করে, আর কীভাবে ফল পড়ে?</strong></summary>

`module` block-এ module-এর input variable গুলো argument হিসেবে সেট করে, আর `module.<label>.<output>` দিয়ে output পড়ে।
</details>

<details>
<summary><strong>Q3. পুনর্ব্যবহারযোগ্য module-এ কেন `provider` block থাকবে না, আর তবু কী ঘোষণা করতে হবে?</strong></summary>

Child module parent থেকে provider configuration উত্তরাধিকারে পায়, আর docs বলে ডাকার জন্য বানানো module-এ provider block থাকা চলবে না। তবু তাকে `required_providers` দিয়ে নিজের provider requirement ঘোষণা করতে হবে।
</details>

<details>
<summary><strong>Q4. পুনর্ব্যবহারযোগ্য module-এ কোন version-constraint ধরন যাবে, আর root-এ কোনটা?</strong></summary>

Module শুধু সর্বনিম্ন সীমা দেবে (যেমন `>= 4.2.0`)। Root module নিচের ও ওপরের দুই সীমা ঠিক করতে `~>` constraint ব্যবহার করবে।
</details>

<details>
<summary><strong>Q5. `web_dev` নামে ডাকা module-এর ভেতরে `web` container resource-এর address কী?</strong></summary>

`module.web_dev.docker_container.web`
</details>

<details>
<summary><strong>Q6. কোন source-এর ধরন চলে, আর কোনটায় `version` কাজ করে?</strong></summary>

একটা স্থানীয় path, একটা Terraform registry (public বা private), Git repository ও object storage। `version` argument শুধু registry module-এ কাজ করে; Git-এ একটা branch, tag বা commit সহ `?ref=` ব্যবহার করো।
</details>

<details>
<summary><strong>Q7. কোনো module লেখার যোগ্য নয় তার দুটো লক্ষণ বলো।</strong></summary>

এটা শুধু একটা resource type-এর পাতলা মোড়ক, অথবা তার নাম শুধু ওই resource type-ই পুনরাবৃত্তি করে। Module-এর অতিরিক্ত ব্যবহার configuration বোঝাও কঠিন করে।
</details>

<details>
<summary><strong>Q8. `moved` block কী করে, আর হাতে state edit করার চেয়ে কেন ভালো?</strong></summary>

এটা plan-এ resource-কে destroy না করে তার address update করে, যেমন নাম বদলানো বা module-এ সরানোর সময়। এটা code-এ লেখা, তাই review হয় আর সবার জন্য কাজ করে, আর কেউ state file edit করে না।
</details>

## ১১. বিনামূল্যের resource

- Terraform docs: [Modules overview](https://developer.hashicorp.com/terraform/language/modules) ও [The `module` block](https://developer.hashicorp.com/terraform/language/block/module)
- Terraform docs: [Module sources](https://developer.hashicorp.com/terraform/language/modules/sources), [Standard module structure](https://developer.hashicorp.com/terraform/language/modules/develop/structure), [Develop modules](https://developer.hashicorp.com/terraform/language/modules/develop)
- Terraform docs: [Providers within modules](https://developer.hashicorp.com/terraform/language/modules/develop/providers) ও [Refactoring (`moved` blocks)](https://developer.hashicorp.com/terraform/language/modules/develop/refactoring)

---

**পরের chapter:** Chapter 5 — একটা আসল cloud provider (শুধু plan) *(শীঘ্রই আসছে)*
