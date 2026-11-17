# Chapter 1 — Terraform কী? Install আর তোমার প্রথম run (cloud লাগে না)

> **Course:** Terraform · **Chapter:** 1 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/terraform/01-what-is-terraform-and-your-first-run)

Infrastructure as Code course-এ তুমি ধারণাটা পেয়েছ: infrastructure code হিসেবে বর্ণনা করো, আর একটা tool আসল দুনিয়াকে তার সাথে মেলায়। এই course সেই tool-গুলোর একটা, **Terraform**, নিয়ে, যা infrastructure **বানায়**: server, network, DNS record, database। এই chapter-এর শেষে তুমি জানবে Terraform কীভাবে ভাবে, আর নিজের machine-এ তার পুরো loop (`init`, `plan`, `apply`, `destroy`) চালাবে, **কোনো cloud account ছাড়া, কোনো খরচ ছাড়া**, Docker ব্যবহার করে।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Terraform ও OpenTofu **install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের command ও code সরকারি Terraform ও OpenTofu documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: install command, `init` / `fmt` / `validate` / `apply` / `show` / `state list` / `destroy` command, উদাহরণ configuration (HashiCorp-এর নিজস্ব Docker tutorial থেকে নেওয়া), plan-এর চিহ্নের মানে, version constraint-এর নিয়ম, lock file ও state file-এর পরামর্শ সরকারি docs থেকে পড়া, আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। **HCL code Terraform দিয়ে parse করা হয়নি** (এটা install করা নেই), তাই এটা ততটাই ঠিক যতটা যে tutorial থেকে এসেছে সেটা। **Output-এর block গুলো docs-এ বর্ণিত আকার দেখায়**, আসল চালানো নয়, আর provider-এর version সময়ের সাথে বদলায়। **তোমার** machine-এ কাজ করে কি না তা প্রমাণ করার দায় তোমার, একটা practice machine-এ, নিচের নিরাপদ ধাপ দিয়ে।

![Terraform loop: write, init, plan, apply, destroy, আর কোন file Git-এ যাবে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/09-terraform/images/terraform-loop.svg)

## ১. Terraform কী

সরকারি docs থেকে: Terraform হলো "একটা infrastructure as code tool যা দিয়ে cloud ও on-prem resource বানানো, বদলানো ও version করা যায়"। চারটা ধারণা এর প্রায় সবকিছু বুঝিয়ে দেয়:

| ধারণা | মানে |
|---|---|
| **Configuration** | `.tf`-এ শেষ হওয়া text file, যাতে তুমি যা চাও তা **ঘোষণা** করো। Docs এগুলোকে declarative বলে: এগুলো "infrastructure-এর শেষ অবস্থা বর্ণনা করে"। |
| **Provider** | একটা plugin যা Terraform-কে একটা platform-এর সাথে কথা বলতে দেয়। "Provider-এর কারণে Terraform API আছে এমন প্রায় যেকোনো platform বা service-এর সাথে কাজ করতে পারে।" Cloud company, DNS service, এমনকি Docker-এরও provider আছে। |
| **Resource** | তুমি যে একটা infrastructure-এর টুকরো ঘোষণা করো: একটা server, একটা network, একটা container। |
| **State** | Terraform "তোমার আসল infrastructure-এর হিসাব রাখে একটা state file-এ, যা তোমার environment-এর সত্যের উৎস", আর কী বদলাতে হবে তা বের করতে এটা ব্যবহার করে। |

Terraform একটা **execution plan** বানায় ("কী তৈরি, update বা destroy করবে") আর **তোমার অনুমোদনে** তা চালায় "সঠিক ক্রমে, resource-এর dependency মেনে"। Plan ধাপটাই Terraform-কে ব্যবহারে নিরাপদ করে: পরিবর্তন **হওয়ার আগে** তুমি তা দেখো।

## ২. HCL ভাষা, এক মিনিটে

Terraform file HCL-এ লেখা। এতে মাত্র দুটো ধারণা, দুটোই docs থেকে:

- একটা **argument** একটা নামে একটা মান বসায়: `image_id = "abc123"`।
- একটা **block** content জড়ো করে। এর একটা **type**, শূন্য বা তার বেশি **label**, আর braces-এ একটা **body** থাকে।

```hcl
resource "aws_instance" "example" {
  ami = "abc123"

  network_interface {
    # ...
  }
}
```

এখানে `resource` হলো block-এর type, `"aws_instance"` ও `"example"` তার দুটো label (জিনিসের ধরন, আর তার জন্য **তোমার দেওয়া নাম**), আর `ami = "abc123"` একটা argument। একটা block-এর ভেতরে **nested block** থাকতে পারে, যেমন `network_interface`। নামে অক্ষর, সংখ্যা, underscore ও hyphen চলে, আর সংখ্যা দিয়ে শুরু হতে পারে না। Comment শুরু হয় `#` দিয়ে (docs `#` সুপারিশ করে; `//` ও `/* */`-ও চলে)।

## ৩. Terraform install করা

এটা করো একটা **practice machine-এ**, production-এ নয়। নিচের command গুলো Ubuntu ও Debian-এর জন্য HashiCorp-এর নিজস্ব নির্দেশনা থেকে। এগুলোতে `sudo` লাগে, কারণ এগুলো তোমার system-এ একটা **নতুন package source** ও একটা signing key যোগ করে, তাই চালানোর আগে প্রতিটা লাইন পড়ো। সবসময়ের মতো: কোনো `sudo` লাইন না বুঝলে চালিও না।

```bash
sudo apt-get update && sudo apt-get install -y gnupg software-properties-common

wget -O- https://apt.releases.hashicorp.com/gpg | gpg --dearmor | sudo tee /usr/share/keyrings/hashicorp-archive-keyring.gpg > /dev/null

gpg --no-default-keyring --keyring /usr/share/keyrings/hashicorp-archive-keyring.gpg --fingerprint

echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] https://apt.releases.hashicorp.com $(grep -oP '(?<=UBUNTU_CODENAME=).*' /etc/os-release || lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/hashicorp.list

sudo apt update
sudo apt-get install terraform
```

প্রতিটা অংশ কী করে: প্রথম লাইন দুটো ছোট tool install করে; দ্বিতীয়টা HashiCorp-এর **signing key** নামায়; তৃতীয়টা key-এর **fingerprint** print করে যাতে তুমি HashiCorp-এর website-এর সাথে মেলাতে পারো (SSH fingerprint মেলানোর একই অভ্যাস); চতুর্থটা HashiCorp-এর package repository যোগ করে; শেষ দুটো Terraform install করে।

কাজ হয়েছে কি না দেখো। Docs `terraform -help` দেখায় (যা command-এর তালিকা দেয়), আর `terraform version` command version, platform ও install করা provider print করে:

```bash
terraform -help
terraform version
```

### OpenTofu-র কী হবে?

**OpenTofu** হলো IaC course-এর Chapter 1-এর সেই open-source fork। তার command `tofu`, আর তার core workflow তুমি যা শিখতে যাচ্ছ তা-ই: `tofu init`, `tofu plan`, `tofu apply`। Ubuntu-তে তার docs একটা installer script দেয় (docs বলে চালানোর আগে নামানো script পরীক্ষা করতে) অথবা ধাপে ধাপে apt repository setup, আর package-এর নাম `tofu`। Resource-এ OpenTofu-র install page দেখো। এই course `terraform` লেখে; যেখানেই দেখবে, OpenTofu ব্যবহারকারী `tofu` টাইপ করে। একটার ওপর ভরসার আগে কোনো পার্থক্য আছে কি না প্রতিটা project-এর নিজস্ব docs-এ দেখো।

## ৪. তোমার প্রথম project: Docker container-এ একটা web server

Cloud account ছাড়া অনুশীলনের জন্য আমরা **Docker provider** ব্যবহার করি। HashiCorp-এর নিজস্ব getting-started tutorial ঠিক এটাই করে: Terraform `nginx` image pull করবে আর একটা container হিসেবে চালাবে। কিছুই তোমার machine-এর বাইরে যায় না, আর কিছুতে টাকা লাগে না। তোমার **Docker install করা ও কাজ করা** চাই (Docker course)।

একটা ফাঁকা folder বানাও আর `main.tf` নামে একটা file:

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

resource "docker_image" "nginx" {
  name         = "nginx:latest"
  keep_locally = false
}

resource "docker_container" "nginx" {
  image = docker_image.nginx.image_id
  name  = "tutorial"

  ports {
    internal = 80
    external = 8000
  }
}
```

(এটা tutorial-এর Mac ও Linux রূপ। Version নম্বরটা tutorial-এরটা; তুমি পড়ার সময় নতুন একটা থাকতে পারে।) Block ধরে ধরে পড়ো:

| Block | কী বলছে |
|---|---|
| `terraform { required_providers { ... } }` | "আমার `docker` provider লাগবে, Terraform Registry-র `kreuzwerker/docker` থেকে, `~> 4.2.0` version-সীমায়।" |
| `provider "docker" {}` | ওই provider configure করো (এখানে ডিফল্ট দিয়ে) |
| `resource "docker_image" "nginx"` | নিশ্চিত করো `nginx:latest` image pull করা আছে। `keep_locally = false` মানে resource destroy হলে image machine থেকে সরাও |
| `resource "docker_container" "nginx"` | ওই image থেকে `tutorial` নামে একটা container চালাও, host-এর port **8000** container-এর port **80**-তে map করে |

`image = docker_image.nginx.image_id` লক্ষ্য করো। একটা resource **আরেকটা resource-এর মান উল্লেখ** করতে পারে। এভাবেই Terraform বোঝে container image-এর ওপর **নির্ভরশীল**, তাই আগে image pull করে। ক্রম তোমাকে কখনো হাতে লিখতে হয় না।

### Command-গুলো, ক্রমে

**`terraform init`**: folder তৈরি করে। Tutorial অনুযায়ী এটা `docker` provider একটা লুকানো `.terraform` directory-তে নামায় আর `.terraform.lock.hcl` নামে একটা file লেখে, যা ব্যবহৃত **ঠিক provider version** গুলো রেকর্ড করে।

```bash
terraform init
```

**`terraform fmt`**: file গুলোকে প্রমিত style-এ লেখে আর যে file বদলেছে তার তালিকা দেয়। **`terraform validate`**: configuration বৈধ ও সামঞ্জস্যপূর্ণ কি না যাচাই করে, `Success! The configuration is valid.` print করে। কোনোটাই আসল infrastructure ছোঁয় না।

```bash
terraform fmt
terraform validate
```

**`terraform apply`**: plan বানায়, দেখায়, আর **তোমার জন্য অপেক্ষা করে**। Tutorial-এ plan যা বানাতে হবে তা `+` দিয়ে চিহ্নিত করে, এখনো অজানা মান `(known after apply)` দেখায়, আর শেষ হয় `Plan: 2 to add, 0 to change, 0 to destroy.` দিয়ে। তুমি `yes` টাইপ করলেই কেবল এটা কাজ করে, আর শেষ হয় `Apply complete! Resources: 2 added, 0 changed, 0 destroyed.` দিয়ে।

```bash
terraform apply
```

তোমার machine-এর port 8000 এখন container-এর port 80-তে map করা, তাই `http://localhost:8000` খুললে nginx-এর welcome page দেখা উচিত। তারপর দেখো Terraform কী জানে:

```bash
terraform show          # the recorded state, including the container's IP address and ports
terraform state list    # the resources Terraform tracks
```

`terraform state list` দেখায় `docker_container.nginx` ও `docker_image.nginx`, তুমি যে দুটো resource ঘোষণা করেছ। ছোট কিছু বদলাও, যেমন external port, তারপর `terraform plan` চালাও (পরের অংশ) আর দেখো কিছু করার **আগেই** Terraform পরিবর্তনটা বর্ণনা করে।

**`terraform destroy`**: এই project যা বানিয়েছে তা সরায়। Docs: "`terraform destroy` command তোমার Terraform project-এর manage করা resource বন্ধ করে।" এটা `-` চিহ্নসহ একটা plan দেখায়, শেষ হয় `Plan: 0 to add, 0 to change, 2 to destroy.` দিয়ে, আর জিজ্ঞেস করে `Do you really want to destroy all resources?`। Tutorial সতর্ক করে: **"কোনো undo নেই। শুধু 'yes' গৃহীত হবে।"** এটা অন্য কোথাও চলা এমন resource **destroy করে না** যা এই project manage করে না।

```bash
terraform destroy
```

## ৫. Plan পড়া

`terraform plan` সবচেয়ে ভালো করে চেনার command। Docs অনুযায়ী এটা: (১) বিদ্যমান object-এর বর্তমান অবস্থা পড়ে, (২) তোমার configuration-কে আগের state-এর সাথে মেলায়, আর (৩) আসল object-গুলো configuration-এর সাথে মেলাতে কী পরিবর্তন করতে হবে তা প্রস্তাব করে। এটা সেই পরিবর্তন **চালায় না**। চিহ্নগুলো:

| চিহ্ন | মানে |
|---|---|
| `+` | Create: resource এখনো নেই |
| `-` | Destroy: resource মুছে যাবে |
| `~` | **জায়গায়** update: destroy ও আবার তৈরি না করে বদলানো |
| `-/+` | **Replace**: মুছে ফেলে আবার তৈরি করা |

আগে `-` ও `-/+` খুঁজতে শেখো। এগুলোই কিছু মোছে বা আবার বানায়, আর database বা server-এর জন্য ওই লাইনটাই গুরুত্বপূর্ণ। `-out=FILE` দিয়ে একটা plan save করা যায় আর পরে সেই file `terraform apply`-কে দেওয়া যায়। Docs বলে এটা মূলত automation-এর জন্য, আর plan file-এ **সংবেদনশীল ডেটা সাদা text-এ** থাকতে পারে, তাই এগুলোকে সংবেদনশীল ধরো।

## ৬. কোন file Git-এ যাবে

প্রথম run-এর পর folder-এ থাকে:

| File বা folder | Git? | কেন |
|---|---|---|
| `main.tf` (তোমার `.tf` file) | **হ্যাঁ** | এটাই তোমার infrastructure code |
| `.terraform.lock.hcl` | **হ্যাঁ** | Docs: "এই file তোমার version control repository-তে রাখা উচিত", যাতে provider version-এর পরিবর্তন review হয়। `terraform init` একে তৈরি বা update করে, আর `terraform init -upgrade` নতুন version-এ যায় |
| `.terraform/` | না | নামানো provider plugin; `terraform init` একে আবার বানায় |
| `terraform.tfstate` | **কখনো না** | State file |

State file নিয়ে docs স্পষ্ট: "Terraform তোমার state একটা plaintext file-এ রাখে, যাতে তোমার configuration-এ দেওয়া যেকোনো secret মান থাকে", আর একটা মান `sensitive` চিহ্নিত করলে তা state থেকে **বাদ যায় না**। "তোমার state file-কে সংবেদনশীল ডেটা ধরো, Git workflow থেকে বাদ রেখে।" প্রথম commit-এর আগে একটা `.gitignore` দিয়ে শুরু করো:

```text
.terraform/
*.tfstate
*.tfstate.*
```

Chapter 3 ব্যাখ্যা করে state কী আর Terraform তা কীভাবে ব্যবহার করে, আর Chapter 7 তা রাখার নিরাপদ জায়গা দেখায়, যেমন remote storage।

## ৭. Version constraint, এক মিনিটে

`version = "~> 4.2.0"` লাইনটা একটা **constraint**। Docs থেকে: `=` একটা ঠিক version অনুমতি দেয়, `!=` একটা বাদ দেয়, `>`, `>=`, `<`, `<=` তুলনা করে, আর `~>` "শুধু ডানদিকের সবচেয়ে শেষ version-অংশকে বাড়তে দেয়"। তাদের উদাহরণ: `~> 1.0.4` `1.0.5` ও `1.0.10` অনুমতি দেয় কিন্তু `1.1.0` নয়, আর `~> 1.1` `1.2` ও `1.10` অনুমতি দেয় কিন্তু `2.0` নয়। তাই `~> 4.2.0` মানে "যেকোনো 4.2.x, কিন্তু 4.3 নয়"। Docs সুপারিশ করে root module (তোমার project) প্রতিটা provider-এর জন্য নিচের ও ওপরের দুই সীমা ঠিক করতে `~>` constraint ব্যবহার করুক, যাতে স্বয়ংক্রিয় upgrade তোমাকে অবাক করতে না পারে।

## ৮. মূল workflow

তুমি যা করলে সবই একটা loop, যেটাকে সরকারি docs **Write, Plan, Apply** বলে:

1. **Write**: `.tf` file লেখো, Git-এ রাখো, folder তৈরি করতে একবার `terraform init` চালাও।
2. **Plan**: পরিবর্তনের preview দেখো। Pull request-এর মতো review করো।
3. **Apply**: অনুমোদন দাও, আর Terraform তা করে দেয়। পরের পরিবর্তন আবার Write থেকে শুরু।

দলগুলো ওপরে branch ও pull request যোগ করে: merge-এর আগে plan review করা হয়, ঠিক যেভাবে Git ও CI/CD course application code-এর জন্য শিখিয়েছে।

## ৯. প্রথম দিন থেকে নিরাপদ অভ্যাস

1. **প্রতিটা plan পড়ো**, বিশেষ করে `-` ও `-/+`, `yes` টাইপ করার আগে।
2. **স্থানীয়, ফেলনা জিনিসে অনুশীলন করো** (এখানকার মতো Docker) যতক্ষণ না loop স্বয়ংক্রিয় হয়।
3. **State বা plan file কখনো commit করো না।** এগুলোতে secret সাদা text-এ থাকতে পারে।
4. **`.terraform.lock.hcl` commit করো**, আর provider `~>` দিয়ে pin করো।
5. **`destroy`-এর undo নেই।** তুমি কোন folder-এ আছ জানো, আর সে যা তালিকাভুক্ত করে তা পড়ো।
6. **Terraform যা বানায় তার মালিক Terraform-কে থাকতে দাও।** হাতে resource বদলালে পরের plan তোমার পরিবর্তন ফেরাতে চাইবে (IaC course-এর drift সমস্যা)।

## ১০. নিজে যাচাই করো

<details>
<summary><strong>Q1. Terraform প্রধানত কী করে, আর Ansible থেকে কীভাবে আলাদা?</strong></summary>

Terraform শেষ অবস্থা ঘোষণা করে infrastructure **provision** করে (server, network, DNS, container), আর একটা state file রাখে। Ansible প্রধানত machine-এর ভেতরে যা চলে তা configure করে এবং কোনো state file রাখে না।
</details>

<details>
<summary><strong>Q2. চারটা ধারণা বলো: configuration, provider, resource, state।</strong></summary>

Configuration হলো declarative `.tf` file। Provider একটা plugin যা একটা platform-এর সাথে কথা বলে। Resource তোমার ঘোষণা করা infrastructure-এর একটা টুকরো। State সেই file যেখানে Terraform রেকর্ড করে সে কী manage করছে।
</details>

<details>
<summary><strong>Q3. `resource "docker_container" "nginx" { ... }`-এ `resource`, `"docker_container"` ও `"nginx"` কী?</strong></summary>

`resource` হলো block-এর type। `"docker_container"` (provider থেকে আসা) resource-এর ধরন, আর `"nginx"` তার জন্য তোমার নিজের দেওয়া নাম।
</details>

<details>
<summary><strong>Q4. `terraform init` কী করে?</strong></summary>

এটা folder তৈরি করে: দরকারি provider লুকানো `.terraform` directory-তে নামায় আর `.terraform.lock.hcl` তৈরি বা update করে, যা ঠিক provider version রেকর্ড করে।
</details>

<details>
<summary><strong>Q5. `image = docker_image.nginx.image_id` কেন গুরুত্বপূর্ণ?</strong></summary>

এটা আরেকটা resource-এর মান উল্লেখ করে, তাই Terraform বোঝে container image-এর ওপর নির্ভরশীল আর তুমি ক্রম না লিখেই সঠিক ক্রমে দুটো বানায়।
</details>

<details>
<summary><strong>Q6. Plan-এ `+`, `-`, `~` ও `-/+` মানে কী?</strong></summary>

Create, destroy, জায়গায় update, আর replace (destroy করে আবার create)।
</details>

<details>
<summary><strong>Q7. কোন file commit করবে, আর কোনটা কখনো না?</strong></summary>

`.tf` file ও `.terraform.lock.hcl` commit করো। `.terraform/` commit করো না, আর state file কখনো নয়, কারণ তাতে secret সাদা text-এ থাকতে পারে।
</details>

<details>
<summary><strong>Q8. `~> 4.2.0` কী অনুমতি দেয়?</strong></summary>

শুধু শেষ অংশ বাড়তে পারে: যেকোনো `4.2.x`, কিন্তু `4.3` নয়।
</details>

## ১১. বিনামূল্যের resource

- Terraform docs: [What is Terraform?](https://developer.hashicorp.com/terraform/intro) ও [The core Terraform workflow](https://developer.hashicorp.com/terraform/intro/core-workflow)
- Tutorial: [Install Terraform](https://developer.hashicorp.com/terraform/tutorials/docker-get-started/install-cli) ও [Build infrastructure (Docker)](https://developer.hashicorp.com/terraform/tutorials/docker-get-started/docker-build), তারপর [Destroy infrastructure](https://developer.hashicorp.com/terraform/tutorials/docker-get-started/docker-destroy)
- Terraform docs: [`terraform plan`](https://developer.hashicorp.com/terraform/cli/commands/plan), [`terraform version`](https://developer.hashicorp.com/terraform/cli/commands/version), [Configuration syntax](https://developer.hashicorp.com/terraform/language/syntax/configuration), [Version constraints](https://developer.hashicorp.com/terraform/language/expressions/version-constraints), [Dependency lock file](https://developer.hashicorp.com/terraform/language/files/dependency-lock), [Sensitive data in state](https://developer.hashicorp.com/terraform/language/state/sensitive-data)
- OpenTofu: [Debian/Ubuntu-তে install](https://opentofu.org/docs/intro/install/deb/) ও [Core workflow](https://opentofu.org/docs/intro/core-workflow/)

---

**পরের chapter:** Chapter 2 — HCL: variable, output ও expression *(শীঘ্রই আসছে)*
