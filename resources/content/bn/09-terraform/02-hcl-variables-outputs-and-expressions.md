# Chapter 2 — HCL: Variable, Output ও Expression

> **Course:** Terraform · **Chapter:** 2 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/terraform/02-hcl-variables-outputs-and-expressions)

Chapter 1-এ প্রতিটা মান সরাসরি `main.tf`-এ লেখা ছিল: container-এর নাম, port, image। একটা project-এর জন্য এটা চলে, কিন্তু আসল infrastructure-এ চাই **একই code, আলাদা মান**: `dev` ও `prod`, এখানে port 8000 ওখানে 8080। এই chapter HCL-এর সেই অংশগুলো শেখায় যা code-কে পুনর্ব্যবহারযোগ্য ও পড়ার মতো করে: **variable** (input), **output**, **local** ও **expression**।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Terraform ও OpenTofu **install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের code ও command গুলো সরকারি Terraform documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: `variable` block (`validation` ও `sensitive` সহ), মান সেট করার উপায় ও তাদের অগ্রাধিকারের ক্রম, `locals`, `output`, `terraform output` ও `terraform console` command, type, string interpolation ও heredoc, conditional expression, `for` expression, আর `join`, `upper` ও `length` function সরকারি docs থেকে পড়া, আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। **HCL Terraform দিয়ে parse করা হয়নি**, তাই ছোট ভুল থাকতে পারে। **Output-এর block গুলো docs-এ বর্ণিত আকার দেখায়**, আসল চালানো নয়। Chapter-এর নিজস্ব উদাহরণ Chapter 1-এর Docker project-এর ওপর দাঁড়িয়ে; একটা practice machine-এ চেষ্টা করো, আর `yes` টাইপ করার আগে প্রতিটা plan পড়ো।

![Variable ঢোকে, resource কাজ করে, output বেরোয়](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/09-terraform/images/variables-in-outputs-out.svg)

## ১. Input variable

**Input variable** হলো একটা নামওয়ালা, type-ওয়ালা মান যা Terraform যে চালায় সে code-এর বাইরে থেকে সেট করতে পারে। তুমি `variable` block দিয়ে ঘোষণা করো, আর `var.<name>` হিসেবে ব্যবহার করো। Docs-এর উদাহরণ:

```hcl
variable "environment" {
  type        = string
  description = "Deployment environment name"
  default     = "dev"

  validation {
    condition     = contains(["dev", "staging", "prod"], var.environment)
    error_message = "Environment must be dev, staging, or prod."
  }
}
```

| Argument | মানে |
|---|---|
| `type` | কোন ধরনের মান চলবে (২ নম্বর অংশ) |
| `description` | মানুষের জন্য একটা বাক্য। সবসময় লেখো |
| `default` | কেউ সেট না করলে যে মান ব্যবহার হয়। **Default না থাকলে Terraform plan বানানোর আগে মান জিজ্ঞেস করে** |
| `validation` | একটা নিয়ম। `condition` মিথ্যা হলে Terraform থামে আর `error_message` দেখায়। Docs: এটা নিশ্চিত করতে সাহায্য করে "ব্যবহারকারী input হিসেবে যে মান দেয় তা module-এর চাহিদা মেটায়" |
| `sensitive` | CLI output-এ মান লুকাও (৩ নম্বর অংশ) |

যেখানে মান চাই সেখানেই variable ব্যবহার করো:

```hcl
resource "docker_container" "web" {
  name = "${var.environment}-web"
}
```

## ২. Type

HCL-এর মানের type আছে। Docs থেকে:

| Type | মানে | উদাহরণ |
|---|---|---|
| `string` | Text | `"hello"` |
| `number` | পূর্ণ সংখ্যা ও ভগ্নাংশ | `15`, `6.283185` |
| `bool` | `true` বা `false` | `true` |
| `list` | শূন্য থেকে index করা ক্রমিক সারি | `["us-west-1a", "us-west-1c"]` |
| `set` | ক্রমহীন, অনন্য মান। Index access সমর্থিত নয়; আগে `tolist()` দিয়ে বদলাও | `set(string)` |
| `map` / `object` | নামওয়ালা মান; key গুলো string | `{ name = "Mabel", age = 52 }` |
| `null` | "অনুপস্থিতি বা বাদ দেওয়া"। কোনো argument `null` করা মানে তা না লেখার সমান | `null` |

Terraform যেখানে পারে নিজে বদলে নেয়: `15` ও `"15"`, `true` ও `"true"` দুই দিকেই বদলায়। বদল অসম্ভব হলে type-mismatch error আসে। প্রতিটা variable-এ `type` দাও: এটা ভুল আগেই ধরে আর কী আশা করা হচ্ছে তা নথিবদ্ধ করে।

## ৩. মান সেট করা, আর কোনটা জেতে

Docs একটা variable-কে মান দেওয়ার এই উপায়গুলো তালিকাভুক্ত করে:

| উপায় | উদাহরণ |
|---|---|
| Command line | `terraform apply -var="instance_type=t3.medium" -var="environment=prod"` |
| একটা variable-definition file | `terraform apply -var-file="production.auto.tfvars"` |
| `terraform.tfvars` বা `terraform.tfvars.json` | **স্বয়ংক্রিয়ভাবে** load হয় |
| `*.auto.tfvars` বা `*.auto.tfvars.json` | **স্বয়ংক্রিয়ভাবে** load হয় |
| Environment variable | `export TF_VAR_environment=staging` |
| Variable-এর `default` | আর কেউ সেট না করলে ব্যবহার হয় |

`terraform.tfvars`-এর মতো একটা file শুধু মানগুলো তালিকাভুক্ত করে:

```hcl
environment   = "staging"
external_port = 8080
```

একই variable একাধিক জায়গা থেকে মান পেলে docs এই ক্রম দেয়, **সবচেয়ে ওপরেরটা আগে**:

1. Command line-এর `-var` ও `-var-file`
2. `*.auto.tfvars` file, lexical (বর্ণানুক্রমিক) ক্রমে
3. `terraform.tfvars.json`
4. `terraform.tfvars`
5. Environment variable (`TF_VAR_...`)
6. `variable` block-এর `default`

সংক্ষেপে: command line file-কে হারায়, file environment variable-কে হারায়, আর default শেষ ভরসা।

### Sensitive variable, আর একটা সৎ সীমা

```hcl
variable "database_password" {
  type        = string
  description = "Password for the RDS database instance"
  sensitive   = true
}
```

`sensitive = true` CLI output-এ মান **দেখানো** থেকে Terraform-কে আটকায়। কিন্তু docs স্পষ্ট: **"Terraform এখনো sensitive variable-এর মান তোমার state-এ রাখে।"** এটা পর্দায় মান লুকায়; state file থেকে সরায় না (Chapter 1: state plaintext আর কখনো Git-এ যায় না)। `ephemeral` argument state ও plan file থেকে মান বাদ দিতে পারে, কিন্তু মানটা কোথায় ব্যবহার করা যায় তা সীমিত করে; ভরসার আগে docs পড়ো।

Docs ও Chapter 1-এর মেজাজে ব্যবহারিক নিয়ম: secret দাও environment variable বা এমন file দিয়ে যা Git-এ **নেই**, commit করা code-এ কখনো `default` হিসেবে নয়, আর secret ধরা `*.tfvars` file `.gitignore`-এ রাখো। ভালো অভ্যাস: নকল মানসহ একটা `terraform.tfvars.example` commit করো, যাতে সহকর্মীরা জানে কী সেট করতে হবে।

## ৪. Local: একটা expression-কে নাম দাও

`locals` block একটা expression-কে নাম দেয়, যাতে তুমি তা একবার লেখো। **একবচন** `local.<name>` দিয়ে পড়ো:

```hcl
locals {
  container_name = "${var.environment}-nginx"
}
```

Docs অনুযায়ী local variable, resource-এর attribute, function-এর ফল ও অন্য local ব্যবহার করতে পারে, আর শুধু যে module-এ ঘোষণা করা সেখানেই দৃশ্যমান। একটা মান পুনর্ব্যবহারে, জটিল expression-কে নাম দিতে, বা একটা জায়গায় কিছু বদলাতে এগুলো ব্যবহার করো। Docs সতর্কও করে এগুলো "configuration পড়া কঠিন করতে পারে কারণ মান কোথা থেকে আসে তা ঢেকে দেয়", তাই যে মান শুধু একবার ব্যবহার হয় তার জন্য local বানিও না।

## ৫. Output: যে মান বেরিয়ে আসে

**Output** `apply`-এর পর একটা মান print করে, আর তা অন্য tool-এর (এবং পরে অন্য module-এর) জন্য উপলব্ধ করে। Docs-এর উদাহরণ:

```hcl
output "instance_id" {
  description = "ID of the EC2 instance"
  value       = aws_instance.web.id
}
```

`value` হতে পারে "যেকোনো বৈধ expression"। `terraform apply`-এর পর Terraform root module-এর output দেখায়। যেকোনো সময় নাম ধরে আবার পড়তে পারো:

```bash
terraform output
terraform output instance_id
```

মানের বদলে `<sensitive>` দেখাতে output-কে `sensitive = true` করো। কিন্তু, আবার docs থেকে: sensitive output-ও **state-এ রাখা হয়**, আর `terraform output`-এর `-json` ও `-raw` flag sensitive মান **সাদা text-এ** দেখায়। এগুলো CI log-এ print করো না।

## ৬. Expression

**Expression** হলো যা একটা মান তৈরি করে। সবচেয়ে সহজগুলো তুমি আগেই ব্যবহার করেছ: একটা literal (`"nginx:latest"`), একটা reference (`var.environment`, `docker_image.nginx.image_id`)।

### String ও interpolation

Quote করা string-এর ভেতরে `${ ... }` একটা expression মূল্যায়ন করে ফল text-এ বসায়:

```hcl
"Hello, ${var.name}!"
```

আক্ষরিক `${` লিখতে `$${` ব্যবহার করো। একাধিক লাইনের text-এর জন্য **heredoc** শুরু হয় `<<` ও লাইনের শেষে একটা identifier দিয়ে; `<<-` সাধারণ শুরুর indentation সরিয়ে দেয়:

```hcl
user_data = <<-EOT
  hello
    world
EOT
```

Docs একটা **template directive**-ও বর্ণনা করে যা দুটো text-এর মধ্যে বেছে নেয়, `%{ if ... }...%{ else }...%{ endif }`, আর বলে `else` অংশ ঐচ্ছিক।

### Conditional expression

```hcl
condition ? true_val : false_val
```

দুটো ফলের **একই type** হতে হবে। Docs সতর্ক করে `var.example ? 12 : "hello"` চুপচাপ সংখ্যাকে string বানিয়ে ফেলে, আর স্পষ্ট হতে সুপারিশ করে, যেমন `var.example ? tostring(12) : "hello"`।

সাধারণ ব্যবহার: environment ভেদে আলাদা মান।

```hcl
locals {
  external_port = var.environment == "prod" ? 80 : 8000
}
```

### `for` expression: একটা collection বদলানো

`for` expression একটা বিদ্যমান list বা map থেকে নতুন list বা map বানায়। Docs থেকে:

```hcl
[for s in var.list : upper(s)]
{for s in var.list : s => upper(s)}
[for s in var.list : upper(s) if s != ""]
```

Square bracket **list** দেয়, `=>` সহ brace **map** দেয়, আর ঐচ্ছিক `if` শুধু মিলে যাওয়া আইটেম রাখে।

### Function

Terraform-এর built-in function আছে, `name(arguments)` রূপে ডাকা। Docs থেকে কয়েকটা:

| Function | কী করে | উদাহরণ |
|---|---|---|
| `upper` | অক্ষর বড় হাতের করে | `upper("hello")` হলো `HELLO` |
| `join` | string-এর list একটা separator দিয়ে জোড়ে | `join("-", ["foo", "bar", "baz"])` হলো `"foo-bar-baz"` |
| `length` | list বা map-এর আইটেম, বা string-এর অক্ষর গোনে | `length(["a", "b"])` হলো `2` |
| `contains` | একটা মান list-এ আছে কি? (ওপরের validation উদাহরণে ব্যবহৃত) | `contains(["dev", "prod"], "dev")` হলো `true` |

আরও অনেক আছে। হাতে লম্বা কিছু লেখার আগে function reference দেখো।

## ৭. নিরাপদে expression পরীক্ষা: `terraform console`

`terraform console` একটা interactive prompt খোলে যেখানে তুমি কোনো infrastructure না বদলিয়ে expression ও function পরীক্ষা করতে পারো। Docs-এর উদাহরণ session:

```
$ terraform console
> cidrnetmask("172.16.0.0/12")
"255.240.0.0"
> exit
```

`exit`, Control-C বা Control-D দিয়ে বন্ধ করো। Console আগে বর্তমান workspace-এর state পড়ে, তাই আগে apply করা resource-এর মানও দেখাতে পারে। কোনো মান এখনো না থাকলে `(known after apply)` দেখায়। কোনো expression আশানুরূপ চলছে না মনে হলে code-এ বসানোর আগে এখানে পরীক্ষা করো।

## ৮. সব একসাথে: Docker project, parameter সহ

Chapter 1-এর project তিনটা file-এ ভাগ করো। File-এর নাম একটা **রীতি** যা মানুষ আশা করে; Terraform folder-এর `.tf` file গুলো একসাথে পড়ে।

`variables.tf`:

```hcl
variable "environment" {
  type        = string
  description = "Deployment environment name"
  default     = "dev"

  validation {
    condition     = contains(["dev", "staging", "prod"], var.environment)
    error_message = "Environment must be dev, staging, or prod."
  }
}

variable "external_port" {
  type        = number
  description = "Port on this machine that reaches the web server"
  default     = 8000

  validation {
    condition     = var.external_port >= 1024 && var.external_port <= 65535
    error_message = "Use a port between 1024 and 65535."
  }
}
```

`main.tf`:

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

locals {
  container_name = "${var.environment}-nginx"
}

resource "docker_image" "nginx" {
  name         = "nginx:latest"
  keep_locally = false
}

resource "docker_container" "nginx" {
  image = docker_image.nginx.image_id
  name  = local.container_name

  ports {
    internal = 80
    external = var.external_port
  }
}
```

`outputs.tf`:

```hcl
output "container_name" {
  description = "Name of the running container"
  value       = docker_container.nginx.name
}

output "url" {
  description = "Where to open the web server"
  value       = "http://localhost:${var.external_port}"
}
```

এখন একই code আলাদা input থেকে আলাদা ফল দেয়:

```bash
terraform init
terraform validate
terraform plan                                          # dev, port 8000 (the defaults)
terraform plan -var="environment=staging" -var="external_port=8080"
terraform plan -var="environment=qa"                    # rejected by validation, shows the error_message
terraform apply -var="environment=staging" -var="external_port=8080"
terraform output url
terraform destroy -var="environment=staging" -var="external_port=8080"
```

`qa` লাইনটাই `validation`-এর উদ্দেশ্য: ভুল মান কোনো plan বানানোর **আগেই** থামানো হয়, তোমার লেখা বার্তা দিয়ে। লক্ষ্য করো `destroy`-এ `apply`-এর **একই মান** দেওয়া সবচেয়ে সহজ, অথবা মানগুলো একবার `terraform.tfvars`-এ রাখো, যাতে প্রতিটা command একই input দেখে। এজন্যই লোকে সাধারণত একটা `terraform.tfvars` file রাখে। Chapter 1-এর মতোই, `yes` টাইপ করার আগে প্রতিটা plan পড়ো আর `-` ও `-/+` খোঁজো, কারণ container-এর নাম বা port বদলালে সেটা প্রতিস্থাপন করতে হতে পারে।

## ৯. ভালো অভ্যাস

1. **প্রতিটা variable-এ `type` ও `description` দাও।** এটাই তোমার documentation।
2. **আগেই validate করো।** `validation` block পরের এক হেঁয়ালিপূর্ণ ব্যর্থতাকে এখনকার একটা পরিষ্কার বার্তা বানায়।
3. **Default হিসেবে secret নয়,** আর Git-এ থাকা file-এও নয়। মনে রেখো `sensitive` লুকায়, state থেকে সরায় না।
4. **Sensitive output log-এ print করো না** (`-json` ও `-raw` সাদা text-এ দেখায়)।
5. **Local কম ব্যবহার করো,** পুনর্ব্যবহৃত বা পড়তে কঠিন মানের জন্য, সবখানে নয়।
6. **`terraform console`-এ expression পরীক্ষা করো** ভরসার আগে।
7. **একই input, একই ফল।** কোনো `plan` অবাক করলে প্রথম প্রশ্ন: "কোন উৎস এই variable সেট করেছে?" (৩ নম্বর অংশ)।

## ১০. নিজে যাচাই করো

<details>
<summary><strong>Q1. কোনো variable-এর `default` না থাকলে আর তুমি সেট না করলে কী হয়?</strong></summary>

Plan বানানোর আগে Terraform তোমাকে মান দিতে বলে।
</details>

<details>
<summary><strong>Q2. একটা variable `terraform.tfvars`-এ `8080`, `TF_VAR_external_port=9090` দিয়ে, আর `-var="external_port=7070"` দিয়ে সেট হলো। কোন মান ব্যবহার হবে?</strong></summary>

`7070`। Command line (`-var`)-এর অগ্রাধিকার সর্বোচ্চ, তারপর `*.auto.tfvars`, তারপর `terraform.tfvars`, তারপর environment variable, তারপর default।
</details>

<details>
<summary><strong>Q3. `validation` কী করে, আর কখন চলে?</strong></summary>

মানের ওপর একটা নিয়ম যাচাই করে। `condition` মিথ্যা হলে Terraform plan বানানোর আগে থামে আর তোমার `error_message` দেখায়।
</details>

<details>
<summary><strong>Q4. `sensitive = true` কি secret-কে state file থেকে দূরে রাখে?</strong></summary>

না। এটা শুধু CLI output-এ মান লুকায়। Terraform এখনো sensitive মান state-এ রাখে, তাই state কখনো Git-এ যাবে না।
</details>

<details>
<summary><strong>Q5. Variable আর local-এর পার্থক্য কী?</strong></summary>

Variable একটা input যা Terraform যে চালায় সে বাইরে থেকে সেট করতে পারে। Local একটা expression-এর নাম, module-এর ভেতরে হিসাব করা, আর বাইরে থেকে সেট করা যায় না।
</details>

<details>
<summary><strong>Q6. `prod`-এ 80 আর অন্যথায় 8000 ফেরত দেয় এমন expression লেখো।</strong></summary>

`var.environment == "prod" ? 80 : 8000`
</details>

<details>
<summary><strong>Q7. `[for s in var.list : upper(s)]` আর `{for s in var.list : s => upper(s)}` কী ফেরত দেয়?</strong></summary>

প্রথমটা বড় হাতের string-এর একটা list ফেরত দেয়। দ্বিতীয়টা প্রতিটা মূল string থেকে তার বড় হাতের রূপে একটা map ফেরত দেয়।
</details>

<details>
<summary><strong>Q8. `terraform console` কিসের জন্য?</strong></summary>

Infrastructure না বদলিয়ে expression ও function interactive ভাবে পরীক্ষা করতে। এটা বর্তমান workspace-এর state-ও পড়ে, তাই আগে apply করা resource-এর মানও দেখাতে পারে।
</details>

## ১১. বিনামূল্যের resource

- Terraform docs: [Input variables](https://developer.hashicorp.com/terraform/language/values/variables), [Output values](https://developer.hashicorp.com/terraform/language/values/outputs), [Local values](https://developer.hashicorp.com/terraform/language/values/locals)
- Terraform docs: [Types and values](https://developer.hashicorp.com/terraform/language/expressions/types), [Strings and templates](https://developer.hashicorp.com/terraform/language/expressions/strings), [Conditional expressions](https://developer.hashicorp.com/terraform/language/expressions/conditionals), [`for` expressions](https://developer.hashicorp.com/terraform/language/expressions/for)
- Terraform docs: [`join`](https://developer.hashicorp.com/terraform/language/functions/join), [`upper`](https://developer.hashicorp.com/terraform/language/functions/upper), [`length`](https://developer.hashicorp.com/terraform/language/functions/length) ও [`terraform console`](https://developer.hashicorp.com/terraform/cli/commands/console)

---

**পরের chapter:** Chapter 3 — State, আর Terraform কীভাবে ঠিক করে কী বদলাবে *(শীঘ্রই আসছে)*
