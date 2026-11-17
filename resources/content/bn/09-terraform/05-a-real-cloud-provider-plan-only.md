# Chapter 5 — একটা আসল cloud provider (শুধু plan)

> **Course:** Terraform · **Chapter:** 5 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/terraform/05-a-real-cloud-provider-plan-only)

এ পর্যন্ত Terraform তোমার নিজের machine-এর container manage করেছে। Terraform-এর আসল ব্যবহার একটা **cloud provider**-এ: server, firewall ও SSH key যা অন্য কারও computer-এ থাকে আর আসল টাকা খরচ করায়। এই chapter তোমাকে সেই code লিখতে শেখায়, উদাহরণ হিসেবে **DigitalOcean** ব্যবহার করে, আর **কিছু তৈরি না করে `terraform plan` দিয়ে তা review** করতে শেখায়। নতুন ধারণা resource নয়। সেটা **credential**: Docker provider-এর কোনোটাই লাগেনি, কিন্তু cloud provider-এর লাগে এমন একটা key যা তোমার টাকা খরচ করতে পারে, আর সেই key রক্ষা করাই এই chapter-এর সবচেয়ে গুরুত্বপূর্ণ দক্ষতা।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Terraform ও OpenTofu **install করা নেই**, কোনো DigitalOcean account ব্যবহার করা হয়নি, আর কিছু তৈরি বা bill করা হয়নি। তাই নিচের code ও command গুলো সরকারি Terraform ও DigitalOcean provider documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: provider ঘোষণা ও `DIGITALOCEAN_TOKEN` variable, `digitalocean_droplet`, `digitalocean_ssh_key` ও `digitalocean_firewall` resource-এর argument, DigitalOcean API token ও তাদের scope কীভাবে কাজ করে, `terraform validate` কী করে, আর Droplet কীভাবে bill হয় সরকারি documentation থেকে পড়া, আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। **HCL Terraform দিয়ে parse করা হয়নি**, তাই ছোট ভুল থাকতে পারে, আর **provider-এর version, image-এর নাম, region ও size সময়ের সাথে বদলায়**, তাই প্রতিটা নাম DigitalOcean-এর বর্তমান documentation-এ মিলিয়ে নাও। এই chapter `plan`-এ থামে। এটা তোমাকে `apply` চালাতে বলে **না**।

![শুধু plan: token read-only, তাই plan দেখতে পারে কিন্তু তৈরি করতে পারে না](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/09-terraform/images/plan-only-cloud.svg)

## ১. কেন আগে "শুধু plan"

Cloud-এর বিরুদ্ধে `terraform apply` এমন জিনিস তৈরি করে যা **প্রতি সেকেন্ডে bill হয়**। DigitalOcean-এর pricing documentation থেকে: Droplet ঘণ্টার হারে **প্রতি সেকেন্ডে** bill হয় (ন্যূনতম ৬০ সেকেন্ড বা $0.01), "Droplet তৈরি করলে billing শুরু হয় আর destroy করলে শেষ হয়", আর **বন্ধ করা Droplet-এও খরচ চলে**। একটা ভুলে যাওয়া server মানে ক্রমে বাড়তে থাকা bill।

`terraform plan` কিছু তৈরি করে না (Chapter 1)। এটা শুধু আসল দুনিয়া পড়ে আর দেখায় কী **ঘটত**। তাই এটা শেখার নিরাপদ উপায়, আর কেউ টাকা দেওয়ার আগে infrastructure code **review** করার ভালো উপায়। এই chapter-এ তুমি cloud plan পড়তে শেখো, আর সেখানেই থামো। কখনো apply করার সিদ্ধান্ত নিলে ইচ্ছা করে, ছোট size নিয়ে করো, আর কাজ শেষে resource destroy করো।

## ২. Cloud provider-এর credential লাগে

Docker provider তোমার নিজের machine-এর একটা program-এর সাথে কথা বলত। Cloud provider একটা company-র **API**-র সাথে কথা বলে, আর API-কে জানতে হয় তুমি কে আর কী করতে পারো। সেটাই **API token**: একটা লম্বা গোপন string, যা কার্যত তোমার account-এর password। যার কাছে এটা আছে সে এটা যা অনুমতি দেয় তা করতে পারে। তাই Vault chapter ও Chapter 1-এর নিয়ম আরও জোরালোভাবে খাটে:

1. **Token কখনো code-এ রেখো না,** variable-এর default-এও না, আর Git-এ যায় এমন কোনো file-এও না।
2. **যতটা কম ক্ষমতায় কাজ চলে ততটাই দাও** (সম্ভব হলে read-only)।
3. **এর মেয়াদ রাখো,** আর কাজ শেষে মুছে দাও।

### Token বানানো

DigitalOcean-এর documentation অনুযায়ী: Control Panel-এ **Account > API**-তে যাও, **Tokens** tab খোলো, আর **Generate New Token** চাপো। তুমি একটা নাম দাও, একটা **expiration** বাছো, আর একটা **scope** বাছো:

| Scope | কী অনুমতি দেয় |
|---|---|
| **Read Only** | `api:read` alias: তোমার team role-এ দেখা যায় এমন সব resource-এ read access |
| **Full Access** | `api:write` alias: তোমার team role-এ ব্যবহারযোগ্য সব resource-এ পূর্ণ access |
| **Custom Scopes** | আলাদা আলাদা অনুমতি বাছো: প্রতিটা resource type-এ create, read, update, delete |

Docs-এর তিনটা তথ্য গুরুত্বপূর্ণ। Secret "শুধু একবার দেখানো হয়", তাই সরাসরি একটা নিরাপদ জায়গায় কপি করো। **Token তৈরির পরে তার scope edit করা যায় না।** আর **দরকারি scope ছাড়া কোনো endpoint-এ call করলে `403 Forbidden` ফেরত আসে**।

**এই chapter-এর জন্য ছোট মেয়াদের একটা Read Only token বানাও।** *Plan* করতে read-only token-ই যথেষ্ট, কারণ plan শুধু পড়ে। এটা একটা নিরাপত্তা-জালও দেয়: কখনো ভুল করে `apply`-এ `yes` টাইপ করলে API create অনুরোধ 403 দিয়ে ফিরিয়ে দেওয়ার কথা, bill না বানিয়ে। (এটা docs-এর scope নিয়মের ইঙ্গিত; ভরসার আগে তোমার account-এ যাচাই করো।) Chapter শেষে token মুছে ফেলো।

### Token পিছনে না রেখে Terraform-কে দেওয়া

Provider docs দেওয়ার উপায়গুলো তালিকাভুক্ত করে। `token` argument প্রয়োজনীয়, কিন্তু environment variable থেকেও আসতে পারে: **আগে `DIGITALOCEAN_TOKEN` দেখা হয়, তারপর `DIGITALOCEAN_ACCESS_TOKEN`**, আর variable সেট থাকলে তুমি code থেকে `token` argument বাদ দিতে পারো। Docs-এর নিজের উদাহরণ একটা Terraform variable থেকে token পড়ে, যা `*.tfvars` file-এ বা `-var` দিয়ে সেট করা। অভিজ্ঞতা ও Chapter 2-এর আত্মা থেকে নিরাপদ অভ্যাস হলো **environment variable**, কারণ তখন token এমন কোনো file-এ নেই যা commit হতে পারে। আরও ভালো, এমনভাবে টাইপ করো যাতে shell history-তে সংরক্ষিত না হয়:

```bash
read -rs DIGITALOCEAN_TOKEN     # paste the token, nothing is shown on screen
export DIGITALOCEAN_TOKEN
```

কাজ শেষে: `unset DIGITALOCEAN_TOKEN`, আর control panel-এ token বাতিল করো। সরাসরি `export DIGITALOCEAN_TOKEN=abc...` টাইপ করলে secret shell history-তে থেকে যেত, `ansible-vault`-এর জন্য Vault docs যে সতর্কতা দিয়েছিল ঠিক সেটাই।

## ৩. Provider ঘোষণা

Provider docs থেকে:

```hcl
terraform {
  required_providers {
    digitalocean = {
      source  = "digitalocean/digitalocean"
      version = "~> 2.0"
    }
  }
}

provider "digitalocean" {}
```

`DIGITALOCEAN_TOKEN` সেট থাকলে ফাঁকা `provider "digitalocean" {}` block-ই যথেষ্ট। `~> 2.0` constraint docs-এর (Chapter 1: শুধু শেষ অংশ বাড়তে পারে, তাই যেকোনো 2.x)। সবসময়ের মতো `terraform init` provider নামায়, আর `.terraform.lock.hcl` ঠিক version রেকর্ড করে।

## ৪. Resource গুলো

তিনটা resource একটা ছোট web server বর্ণনা করে। নিচের সব নাম provider docs থেকে।

**একটা SSH key** যাতে তুমি ঢুকতে পারো। এটা তোমার **public** key আপলোড করে (সেটা গোপন নয়) আর `id` ও `fingerprint` export করে:

```hcl
resource "digitalocean_ssh_key" "deploy" {
  name       = "deploy-key"
  public_key = file("/home/you/.ssh/id_ed25519.pub")
}
```

`file()` একটা file-এর content পড়ে। **তোমার** public key-র (`.pub`-এ শেষ হওয়া) পুরো path দাও, private key-র কখনো নয়।

**একটা Droplet** (DigitalOcean-এর virtual server-এর নাম)। Docs-এ `image`, `name` ও `size` প্রয়োজনীয়; `region`-ও স্পষ্টভাবে দাও। `ssh_keys`, `tags`, `user_data`, `monitoring` ও `ipv6` ঐচ্ছিক। এটা `id` ও `ipv4_address` export করে:

```hcl
resource "digitalocean_droplet" "web" {
  image    = var.image
  name     = "web-1"
  region   = var.region
  size     = var.size
  ssh_keys = [digitalocean_ssh_key.deploy.fingerprint]
}
```

`digitalocean_ssh_key.deploy.fingerprint` reference লক্ষ্য করো: Terraform বোঝে Droplet key-র ওপর নির্ভরশীল (Chapter 1) আর আগে key বানায়। কোন পরিবর্তন **প্রতিস্থাপন বাধ্য করে** তা নিয়ে docs-এর নোট: `user_data` বদলালে "একটা নতুন resource তৈরি করতে হয়", আর `ssh_keys` বদলালে "Droplet destroy করে আবার বানানোর প্রম্পট আসে"। এছাড়া, Droplet-এর disk size বাড়ানো **স্থায়ী** পরিবর্তন বলে docs-এ লেখা। এগুলোই সেই `-/+` লাইন যা তোমাকে plan-এ পড়তে হবে (Chapter 1)।

**একটা firewall**, যাতে শুধু দরকারি port খোলা থাকে। Docs `protocol`, `port_range` ও address সহ `inbound_rule` ও `outbound_rule` block দেখায়, আর Droplet-এ জুড়তে `droplet_ids` (`tcp` ও `udp`-এর জন্য `port_range` প্রয়োজনীয়):

```hcl
resource "digitalocean_firewall" "web" {
  name        = "web-firewall"
  droplet_ids = [digitalocean_droplet.web.id]

  inbound_rule {
    protocol         = "tcp"
    port_range       = "22"
    source_addresses = [var.admin_cidr]
  }

  inbound_rule {
    protocol         = "tcp"
    port_range       = "80"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  inbound_rule {
    protocol         = "tcp"
    port_range       = "443"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  outbound_rule {
    protocol              = "tcp"
    port_range            = "1-65535"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }

  outbound_rule {
    protocol              = "udp"
    port_range            = "1-65535"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }
}
```

SSH (port 22) শুধু `var.admin_cidr` থেকে অনুমতি পায়, মানে **তোমার** address-সীমা, পুরো internet নয়। Web port 80 ও 443 সবার জন্য খোলা। (`port_range`-এর ঠিক রূপ আর ping লাগলে ICMP rule-এর জন্য provider docs দেখো।) Firewall ছাড়া server প্রতিটা port-এ পৌঁছানো যায়; code-এ লেখা firewall অন্য যেকোনো পরিবর্তনের মতো review হয়।

## ৫. Input ও output

`variables.tf`: Chapter 2-এর অভ্যাস মেনে, প্রতিটা variable-এ একটা type ও description। Token-এর জন্য **কোনো variable নেই**, কারণ সেটা environment থেকে আসে।

```hcl
variable "region" {
  type        = string
  description = "DigitalOcean region slug for the server"
  default     = "sgp1"
}

variable "size" {
  type        = string
  description = "Droplet size slug"
  default     = "s-1vcpu-1gb"
}

variable "image" {
  type        = string
  description = "Droplet image slug"
  default     = "ubuntu-24-04-x64"
}

variable "admin_cidr" {
  type        = string
  description = "Address range allowed to SSH in, for example 203.0.113.7/32"
}
```

`size = "s-1vcpu-1gb"` provider-এর নিজস্ব উদাহরণের slug। Provider docs-এর উদাহরণ image `ubuntu-20-04-x64` একটা **পুরনো** Ubuntu release; একটা বর্তমান release ব্যবহার করো আর এই chapter-এর ওপর ভরসা না করে **slug DigitalOcean-এর documentation-এ খুঁজে নাও**। Region-এর বেলাতেও তাই। `203.0.113.7/32` শুধু documentation-এর উদাহরণ address, আসল নয়: তোমার নিজের public address ব্যবহার করো। `admin_cidr`-এর ইচ্ছা করেই কোনো default নেই, তাই Terraform জিজ্ঞেস করে আর তুমি সেট করতে ভুলতে পারো না।

`outputs.tf`:

```hcl
output "droplet_ip" {
  description = "Public IPv4 address of the web server"
  value       = digitalocean_droplet.web.ipv4_address
}
```

আর Chapter 1 ও 2-এর `.gitignore`, সাথে secret ধরতে পারে এমন সবকিছু:

```text
.terraform/
*.tfstate
*.tfstate.*
*.tfvars
```

## ৬. শুধু-plan workflow

এগুলো ক্রমে করো। শুধু শেষটায় token লাগে।

```bash
terraform init          # downloads the digitalocean provider
terraform fmt
terraform validate      # checks the code; needs no token
read -rs DIGITALOCEAN_TOKEN && export DIGITALOCEAN_TOKEN    # a READ-ONLY token
terraform plan
```

`terraform validate` যাচাই করে configuration বৈধ ও সামঞ্জস্যপূর্ণ "দেওয়া কোনো variable বা বিদ্যমান state নির্বিশেষে", আর docs অনুযায়ী এটা "remote state বা provider API-র মতো remote service যাচাই করে না"। এর জন্য একটা initialized folder লাগে, কিন্তু এটা DigitalOcean-কে ডাকে না, তাই token ছাড়াই চলে। `terraform plan` সেই ধাপ যা API-র সাথে কথা বলে আর token চায়।

ভালো plan দেখতে কেমন (আকার, Chapter 1-এর নিয়ম থেকে): `+` চিহ্নিত তিনটা resource (SSH key, Droplet, firewall), এখনো অজানা মান `(known after apply)` (Droplet-এর IP, ID গুলো), আর `Plan: 3 to add, 0 to change, 0 to destroy.`-এর মতো একটা সারাংশ লাইন। এই ক্রমে দেখো:

1. প্রথম plan-এ **শুধু `+` লাইন**। যে জিনিসের আশা করোনি তাতে `-` বা `-/+` মানে থামো আর কারণ বের করো।
2. **Region, size ও image** তুমি যা চেয়েছ তাই। বড় size প্রতি সেকেন্ডে বেশি খরচ করায়।
3. **Firewall-এর SSH rule** তোমার address দেখায়, `0.0.0.0/0` নয়।
4. Output-এ **কোনো secret** নেই।

তারপর **থামো**। তুমি টাকা না দিয়ে infrastructure review করেছ। `unset DIGITALOCEAN_TOKEN` চালাও আর control panel-এ token মুছে ফেলো।

পরে ইচ্ছা করে `apply` করার সিদ্ধান্ত নিলে: ছোট মেয়াদের একটা **Full Access** বা উপযুক্ত custom-scope token, সবচেয়ে ছোট size ব্যবহার করো, plan দেখো, আর শেষে `terraform destroy` চালাও। তারপর কিছু চলছে না নিশ্চিত করতে **DigitalOcean control panel দেখো**, কারণ billing "destroy করলে শেষ হয়"।

## ৭. State file এখন আসল infrastructure ধরে

আসল `apply`-এর পর state-এ তোমার Droplet, firewall ও key-র বিবরণ থাকত। Chapter 1 ও Chapter 3 পুরোপুরি খাটে: এতে সংবেদনশীল ডেটা সাদা text-এ থাকতে পারে, কখনো Git-এ যায় না, আর দলের জন্য এটা রাখার একটা নিরাপদ ভাগ করা জায়গা লাগে (Chapter 7)। এই নকশায় তোমার API token **তোমার code-এ বা `.tfvars`-এ নেই**, যা environment variable পছন্দ করার আরেকটা কারণ।

## ৮. অন্য cloud

AWS, Azure, Google Cloud ও বাকিরা **একই আকার** মানে: `required_providers`-এ একটা provider, code-এর **বাইরে** থেকে দেওয়া credential, তারপর resource। তাদের resource-এর নাম ও authenticate করার উপায় আলাদা, তাই code লেখার আগে প্রতিটা provider-এর documentation page পড়ো। এই chapter-এর অভ্যাসগুলো সব জায়গায় খাটে: কম-ক্ষমতার credential, ছোট মেয়াদ, apply-এর আগে plan, আর code-এ firewall।

## ৯. অভ্যাস

1. **সবসময় আগে plan,** আর `-` ও `-/+` দেখে পড়ো।
2. **Credential আসে environment থেকে,** code, Git-এ থাকা `.tfvars` বা shell history থেকে নয়।
3. **সর্বনিম্ন ক্ষমতা:** plan-এর জন্য Read Only token। Token-এর scope পরে edit করা যায় না, তাই নতুন একটা বানাও।
4. **ছোট মেয়াদ,** আর আর যা ব্যবহার করো না সেই token মুছে ফেলো।
5. **Code-এ firewall,** SSH তোমার address-এ সীমিত।
6. **বর্তমান slug খুঁজে নাও** (image, region, size) provider-এর documentation-এ।
7. **Bill জানো:** এটা create-এ শুরু হয় আর শুধু destroy-এ থামে। বন্ধ করা মানে বিনামূল্যে নয়।

## ১০. নিজে যাচাই করো

<details>
<summary><strong>Q1. Docker provider-এর যা লাগেনি, cloud provider-এর তা কেন লাগে?</strong></summary>

সে একটা company-র API-র সাথে কথা বলে, যাকে জানতে হয় তুমি কে আর কী করতে পারো। তার জন্য API token লাগে, যা কার্যত তোমার account-এর password।
</details>

<details>
<summary><strong>Q2. DigitalOcean token কোথা থেকে আসবে, আর কোথায় কখনো থাকবে না?</strong></summary>

`DIGITALOCEAN_TOKEN` environment variable থেকে (এমনভাবে টাইপ করা যাতে shell history-তে সংরক্ষিত না হয়)। এটা কখনো code-এ, Git-এ commit করা file-এ বা variable-এর default-এ থাকবে না।
</details>

<details>
<summary><strong>Q3. শুধু-plan অনুশীলনের জন্য কোন token scope সঠিক, আর কেন?</strong></summary>

Read Only (`api:read`)। Plan শুধু পড়ে, আর দরকারি scope ছাড়া call 403 ফেরত দেয়, তাই ভুল করে apply করলে bill তৈরির বদলে প্রত্যাখ্যাত হওয়ার কথা।
</details>

<details>
<summary><strong>Q4. তুমি ভুল scope-এ একটা token বানিয়েছ। কী করবে?</strong></summary>

সঠিক scope-এ নতুন token বানাবে, কারণ তৈরির পরে token-এর scope edit করা যায় না, আর পুরনোটা মুছে ফেলবে।
</details>

<details>
<summary><strong>Q5. `terraform validate`-এর কি API token লাগে? কেন?</strong></summary>

না। এটা remote service বা provider API-কে না ডেকে configuration বৈধ ও সামঞ্জস্যপূর্ণ কি না যাচাই করে। শুধু একটা initialized folder লাগে।
</details>

<details>
<summary><strong>Q6. Provider docs অনুযায়ী Droplet আবার বানাতে বাধ্য করে এমন দুটো পরিবর্তন বলো।</strong></summary>

`user_data` বদলানো, আর `ssh_keys` বদলানো। Plan-এ এগুলো `-/+` হিসেবে দেখা যায়।
</details>

<details>
<summary><strong>Q7. Droplet-এর DigitalOcean billing কখন শুরু ও শেষ হয়, আর বন্ধ করলে কি থামে?</strong></summary>

Droplet তৈরি হলে শুরু, শুধু destroy হলে শেষ। বন্ধ করা Droplet-এও খরচ চলে।
</details>

<details>
<summary><strong>Q8. থামার আগে প্রথম plan-এ কোন চারটা জিনিস দেখবে?</strong></summary>

শুধু `+` লাইন (অপ্রত্যাশিত `-` বা `-/+` নয়), উদ্দিষ্ট region, size ও image, `0.0.0.0/0`-র বদলে তোমার address-এ সীমিত SSH rule, আর output-এ কোনো secret নেই।
</details>

## ১১. বিনামূল্যের resource

- DigitalOcean provider: [Terraform Registry-তে documentation](https://registry.terraform.io/providers/digitalocean/digitalocean/latest/docs) ও [GitHub-এ source docs](https://github.com/digitalocean/terraform-provider-digitalocean/tree/main/docs): [provider](https://github.com/digitalocean/terraform-provider-digitalocean/blob/main/docs/index.md), [droplet](https://github.com/digitalocean/terraform-provider-digitalocean/blob/main/docs/resources/droplet.md), [ssh_key](https://github.com/digitalocean/terraform-provider-digitalocean/blob/main/docs/resources/ssh_key.md), [firewall](https://github.com/digitalocean/terraform-provider-digitalocean/blob/main/docs/resources/firewall.md)
- DigitalOcean docs: [Create a personal access token](https://docs.digitalocean.com/reference/api/create-personal-access-token/) ও [Droplet pricing and billing](https://docs.digitalocean.com/products/droplets/details/pricing/)
- Terraform docs: [`terraform validate`](https://developer.hashicorp.com/terraform/cli/commands/validate) ও [`terraform plan`](https://developer.hashicorp.com/terraform/cli/commands/plan)

---

**পরের chapter:** [Chapter 6 — Dependency, `count`, `for_each` ও `lifecycle`](06-dependencies-count-for-each-and-lifecycle.md)
