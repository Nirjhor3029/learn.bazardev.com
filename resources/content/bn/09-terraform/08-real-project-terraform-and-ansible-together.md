# Chapter 8 — Real project: Terraform ও Ansible একসাথে

> **Course:** Terraform · **Chapter:** 8 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/terraform/08-real-project-terraform-and-ansible-together)

এই chapter-এ দুটো tool শেষ পর্যন্ত মিলিত হয়। Infrastructure as Code course-এ তুমি ভাগটা শিখেছ: **Terraform server-টাকে অস্তিত্ব দেয়, Ansible তাকে কাজের করে।** এখন তুমি পুরো শৃঙ্খলটা কাগজে বানাও, আর তার নিরাপদ অংশ অনুশীলন করো: Terraform একটা server, একটা firewall ও একটা SSH key বানায়; server-এর address Ansible-কে দেওয়া হয়; Ansible web server install ও configure করে। এই chapter-এর দক্ষতা tool দুটোর মাঝের **হাতবদল (hand-off)**, আর দুই-tool-এর ব্যবস্থাকে নিরাপদ রাখা **ক্রম ও review**।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Terraform, OpenTofu ও Ansible **install করা নেই**, কোনো cloud account ব্যবহার করা হয়নি, আর **কিছু তৈরি বা bill করা হয়নি**। তাই নিচের code ও command গুলো সরকারি documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: `templatefile` function, `local_file` resource, `terraform output -raw` ও `-json`, provisioner শেষ উপায় এই HashiCorp-এর পরামর্শ, আর (আগের chapter ও IaC course থেকে) DigitalOcean resource, Ansible inventory, playbook, `apt`, `template` ও `service` feature সরকারি docs থেকে পড়া, আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। **HCL, YAML ও shell লাইন চালানো হয়নি**, তাই ছোট ভুল থাকতে পারে, আর **image, size ও region-এর নাম বদলায়**: খুঁজে নাও। এই chapter বলে কী চালাতে হবে আর কোন ক্রমে, কিন্তু তোমাকে কিছু `apply` করতে বলে **না**। পড়া ও plan করায় কোনো খরচ নেই। আসল `apply` একটা bill-হওয়া server বানায় (billing "destroy করলে শেষ হয়"), তাই শুধু ইচ্ছা করে, ছোট size নিয়ে করো, আর পরে destroy করো।

![Terraform server বানায়, তার address হস্তান্তর করে, Ansible তা configure করে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/09-terraform/images/terraform-then-ansible.svg)

## ১. কে কীসের মালিক

| | **Terraform** | **Ansible** |
|---|---|---|
| **মালিক** | cloud account-এ যা **আছে**: server, firewall, SSH key | server-এর **ভেতরে** যা আছে: package, file, service |
| **কথা বলে** | provider-এর API-র সাথে, একটা API token দিয়ে | server-এর সাথে, SSH দিয়ে, তোমার key দিয়ে |
| **মনে রাখে** | একটা state file | কিছুই না; প্রতি run-এ machine যাচাই করে |
| **কখন বদলায়...** | server-এর size বদলালে, port খুললে, দ্বিতীয় server যোগ করলে | config file বদলালে, package যোগ করলে, setting ঘোরালে |

প্রতিটা পরিবর্তনের জন্য একটা ভালো অভ্যাস: জিজ্ঞেস করো **"এটা কি জিনিসটা থাকা নিয়ে, নাকি তার ভেতরে কী আছে তা নিয়ে?"** প্রথমটা Terraform-এর পরিবর্তন, দ্বিতীয়টা Ansible-এর। শূন্য থেকে একটা server আবার বানানো মানে "Terraform, তারপর Ansible", সবসময় এই ক্রমে।

## ২. Project-এর কাঠামো

```text
web-server-project/
├── terraform/
│   ├── main.tf
│   ├── variables.tf
│   ├── outputs.tf
│   └── inventory.tftpl      # template for the Ansible inventory
├── ansible/
│   ├── ansible.cfg
│   ├── site.yml
│   └── templates/
│       └── index.html.j2
└── .gitignore
```

দুটো folder, দুটো tool, একটা repository। এখানের সবকিছু Git-এ যায় **শুধু** state, secret ও তৈরি করা inventory ছাড়া:

```text
.terraform/
*.tfstate
*.tfstate.*
*.tfvars
ansible/inventory.ini
.vault_pass
```

## ৩. ধাপ ১: Terraform server বানায়

`terraform/main.tf` হলো Chapter 5-এর cloud code (SSH key, Droplet, firewall), সাথে শেষে একটা নতুন অংশ, **হাতবদলের file**:

```hcl
terraform {
  required_providers {
    digitalocean = {
      source  = "digitalocean/digitalocean"
      version = "~> 2.0"
    }
    local = {
      source = "hashicorp/local"
    }
  }
}

provider "digitalocean" {}

resource "digitalocean_ssh_key" "deploy" {
  name       = "deploy-key"
  public_key = file(var.ssh_public_key_path)
}

resource "digitalocean_droplet" "web" {
  image    = var.image
  name     = "web-1"
  region   = var.region
  size     = var.size
  ssh_keys = [digitalocean_ssh_key.deploy.fingerprint]
}

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

`terraform/variables.tf`-এ Chapter 5-এর একই variable (`region`, `size`, `image`, `admin_cidr`) থাকে, সাথে তোমার **public** key-র path। API token-এর জন্য এখনো **কোনো variable নেই**: সেটা environment-এর `DIGITALOCEAN_TOKEN` থেকে আসে (Chapter 5)।

```hcl
variable "ssh_public_key_path" {
  type        = string
  description = "Full path to your SSH PUBLIC key (the file ending in .pub)"
}
```

`terraform/outputs.tf` Ansible-এর যে একটা তথ্য লাগে সেটা প্রকাশ করে, server-এর address:

```hcl
output "droplet_ip" {
  description = "Public IPv4 address of the web server"
  value       = digitalocean_droplet.web.ipv4_address
}
```

## ৪. ধাপ ২: হাতবদল, Terraform থেকে Ansible

Ansible-এর একটা **inventory** (IaC course, Chapter 2) লাগে যা server-কে তালিকাভুক্ত করে। Terraform address জানে, তাই file-টা সে-ই লিখুক। দুটো উপায়, দুটোই docs-এ আছে।

### উপায় A: Terraform inventory file লেখে

`templatefile` function "দেওয়া path-এর file পড়ে আর তার content একটা template হিসেবে render করে", আর তার ভেতরে `${ }` ও `%{ for }` কাজ করে। `local_file` resource **তোমার** machine-এ একটা file-এ content লেখে। একসাথে:

`terraform/inventory.tftpl`:

```text
[web]
web1 ansible_host=${web_ip} ansible_user=root
```

`main.tf`-এ যোগ করো:

```hcl
resource "local_file" "inventory" {
  filename = "${path.module}/../ansible/inventory.ini"
  content = templatefile("${path.module}/inventory.tftpl", {
    web_ip = digitalocean_droplet.web.ipv4_address
  })
}
```

Terraform Droplet বানালে সে IP জানে, template render করে, আর `ansible/inventory.ini` লেখে। File-এ `web` group থাকে যাকে playbook লক্ষ্য করবে। দুটো নোট। `ansible_user=root` একটা ধারণা: তোমার বাছা image তোমার SSH key দিয়ে কোন user হিসেবে ঢুকতে দেয় তা DigitalOcean-এর documentation-এ দেখো। আর `local_file` docs-এ একটা সতর্কতা আছে: file যেখানে নেই এমন "নতুন machine-এ configuration apply করলে Terraform প্রতিবার resource-টাকে মুছে ফেলা হয়েছে বলে দেখে"। নতুন CI machine-এ plan-এ এটা নিরীহ গোলমাল হিসেবে দেখা দেয়, যা automation-এ উপায় B পছন্দ করার একটা কারণ।

### উপায় B: output পড়ে নিজে file লেখো

`terraform output NAME` একটা output print করে। `-raw` দিলে এটা মান "কোনো বিশেষ formatting ছাড়া সরাসরি output-এ" print করে, যা shell script-এর জন্য ভালো (string, number ও boolean মানে কাজ করে; জটিলগুলোর জন্য `-json`):

```bash
cd terraform
printf '[web]\nweb1 ansible_host=%s ansible_user=root\n' "$(terraform output -raw droplet_ip)" > ../ansible/inventory.ini
cd ..
```

Docs যা বলে তা নিয়ে সাবধান: `-json` বা `-raw` দিলে Terraform **`sensitive` মান সাদা text-এ** দেখায়। IP গোপন নয়, কিন্তু password বা token-এর জন্য এই কৌশল ব্যবহার করো না, আর output কখনো CI log-এ print করো না।

### Terraform-এর ভেতর থেকে কেন Ansible চালাবে না?

Terraform-এর ঠিক এর জন্যই একটা feature আছে, **provisioner** (`local-exec`, `remote-exec`), আর একটা থেকে `ansible-playbook` ডাকার লোভ হয়। সরকারি docs এর বিরুদ্ধে পরামর্শ দেয়: "তোমার configuration-এ provisioner ব্যবহারের আগে সব বিকল্প শেষ করো।" তাদের কারণ: "Terraform provisioner-এর আচরণ অনুমানযোগ্যভাবে model করতে পারে না" (তাই **plan তোমাকে দেখাতে পারে না** সে কী করবে), বেশিরভাগ provisioner "তোমার server-এ সরাসরি network access ও credential চায়", আর একটা ব্যর্থ provisioner "resource-কে আধা-configure অবস্থায় ফেলে রাখতে পারে"। Docs post-apply কাজের জন্য "উদ্দেশ্য-নির্মিত সমাধান ব্যবহারের জোরালো সুপারিশ" করে। Ansible **ঠিক সেই** উদ্দেশ্য-নির্মিত tool, তাই একে **নিজস্ব ধাপ** হিসেবে চালাও: তুমি তাকে review করতে, আবার চালাতে, আর infrastructure থেকে আলাদা করে পরীক্ষা করতে পারবে।

## ৫. ধাপ ৩: Ansible server configure করে

`ansible/ansible.cfg` (IaC course, Chapter 2):

```ini
[defaults]
inventory = inventory.ini
```

`ansible/site.yml` শুধু IaC course-এর feature ব্যবহার করে: `apt`, `template` ও `service` module, `become`, আর একটা variable (Chapter 3)। একটা ছোট playbook, যাতে **হাতবদলই** মূল বিষয় থাকে:

```yaml
- name: Configure the web server
  hosts: web
  become: true
  vars:
    site_message: "Built with Terraform and Ansible"

  tasks:
    - name: Web server packages are installed
      ansible.builtin.apt:
        name:
          - nginx
          - git
        state: present
        update_cache: true
        cache_valid_time: 3600

    - name: Landing page is in place
      ansible.builtin.template:
        src: index.html.j2
        dest: /var/www/html/index.html
        owner: root
        group: root
        mode: '0644'

    - name: Nginx is running
      ansible.builtin.service:
        name: nginx
        state: started
```

`ansible/templates/index.html.j2`:

```text
<!doctype html>
<title>{{ site_message }}</title>
<h1>{{ site_message }}</h1>
<p>Server: {{ inventory_hostname }}</p>
```

(এখানে `nginx` ব্যবহার করা হয়েছে কারণ এটা Ubuntu-র নিজস্ব package repository-তে আছে, তাই হাতবদল অনুসরণ করা সহজ। PHP, Caddy ও MySQL সহ একটা পূর্ণ Laravel stack role হিসেবে IaC course-এ বানানো হয়। যে Ansible অভ্যাস গুরুত্বপূর্ণ তা থেকে যায়: role, template, secret-এর জন্য Vault।)

## ৬. Runbook, ক্রমে

একটা checklist হিসেবে পড়ো যা তুমি সহকর্মীকে দিতে পারতে। **(plan-only) চিহ্নিত ধাপে কোনো খরচ নেই।**

**A. Infrastructure review করো (plan-only)**

```bash
cd terraform
terraform init
terraform fmt
terraform validate
read -rs DIGITALOCEAN_TOKEN && export DIGITALOCEAN_TOKEN     # a READ-ONLY token
terraform plan
```

Chapter 5-এর মতো plan পড়ো: প্রথম run-এ শুধু `+` লাইন, উদ্দিষ্ট region, size ও image, SSH তোমার address-এ সীমিত। তুমি **চারটা** নতুন resource দেখবে: key, Droplet, firewall আর inventory file। **শুধু অনুশীলন করলে এখানে থামো।** `unset DIGITALOCEAN_TOKEN` আর token মুছে ফেলো।

**B. ইচ্ছা করে বানালে তবেই**

ছোট size আর তৈরির অধিকারসহ, শীঘ্র মেয়াদ-শেষ হওয়া একটা token ব্যবহার করো। তারপর:

```bash
terraform apply          # read the plan, then type yes
```

Droplet তৈরি থেকে destroy পর্যন্ত bill হয়। Terraform `ansible/inventory.ini` লেখে (উপায় A)।

**C. Ansible দিয়ে server configure করো**

নতুন server-এ SSH সাড়া দিতে এক মিনিট লাগতে পারে। তারপর `ansible` folder থেকে, IaC course-এর নিরাপদ ক্রম ব্যবহার করো:

```bash
cd ../ansible
ansible web -m ping                                  # can Ansible log in? expect "pong"
ansible-playbook site.yml --syntax-check
ansible-playbook site.yml --check --diff             # preview, change nothing
ansible-playbook site.yml                            # apply
ansible-playbook site.yml                            # second run: expect changed=0
```

যদি server আগে ব্যবহার করা একটা address-এ **আবার বানানো** হয়, SSH সতর্ক করতে পারে host key বদলেছে। এটা SSH-এর নিজের কাজ: কারণ দেখো, পুরনো এন্ট্রি ইচ্ছা করে সরাও, আর host-key যাচাই কখনো বন্ধ করো না। তারপর `http://<the-droplet-ip>` খোলো আর তোমার page খোঁজো।

**D. বানালে, ভেঙে ফেলো**

```bash
cd ../terraform
terraform destroy        # read what it lists, then type yes
```

তারপর কিছু চলছে না নিশ্চিত করতে **DigitalOcean control panel দেখো**, কারণ billing শুধু destroy-এ থামে। API token মুছে ফেলো।

## ৭. দ্বিতীয় দিন: নিরাপদে বদলানো

| তুমি চাও... | যা বদলাবে | তারপর চালাও |
|---|---|---|
| আরেকটা port খুলতে, বা server-এর size বদলাতে | `terraform/` code | `terraform plan`, review, `apply`, address বদলালে তারপর আবার Ansible |
| page বদলাতে, package যোগ করতে | `ansible/` code | `--check --diff`, তারপর playbook দুইবার |
| শূন্য থেকে server আবার বানাতে | কিছু না, বা `-replace` | `terraform apply -replace=digitalocean_droplet.web`, তারপর পুরো Ansible run |
| একটা secret (database password) যোগ করতে | Ansible Vault file | `--ask-vault-pass` বা Git-এর বাইরে রাখা password file দিয়ে চালাও (IaC course, Chapter 4) |

আবার-বানানোর সারিটাই পুরো course-এর ফল: দুটো স্তরই code বলে, **একটা server ফেলে দিয়ে মিনিটের মধ্যে হুবহু আবার বানানো যায়**, একই দুটো ধাপ চালিয়ে। প্রতিস্থাপিত server **নতুন IP** পেলে Terraform inventory আবার লেখে (উপায় A) বা তুমি আবার বানাও (উপায় B), আর playbook অপরিবর্তিত থাকে।

## ৮. জোড়াটা automate করা

CI-তে (Chapter 7) দুটো tool-এর **দুটো job** থাকে:

1. **প্রতিটা pull request-এ:** **read-only** token দিয়ে `terraform fmt -check`, `init`, `validate` ও `plan`, আর `ansible-playbook --syntax-check` (একটা test server থাকলে তার বিরুদ্ধে `--check`-ও)। Reviewer plan-কে diff-এর মতো পড়ে।
2. **Merge-এর পরে, অনুমোদন সহ:** Terraform-এর একটা **saved plan** apply করো, তারপর playbook চালাও। এগুলো **আলাদা, আলাদাভাবে review-যোগ্য ধাপ** রাখো, provisioner docs-এর দেওয়া কারণে।

Credential থাকে CI system-এর **secret**-এ, repository-তে কখনো নয়।

## ৯. তুমি কী বানিয়েছ, আর অভ্যাস

এখন তুমি বলতে পারো: *server, তার firewall ও তার key আছে কারণ Terraform বলে; তাতে যা চলে তা আছে কারণ Ansible বলে; দুটোই Git-এ থাকে; প্রতিটা পরিবর্তন আগে plan বা check করা হয়; আর repository-তে কোনো secret নেই।* অভ্যাসগুলো এক তালিকায়:

1. **প্রতিটা জিনিসের একজন মালিক:** যা আছে তার জন্য Terraform, যা ভেতরে আছে তার জন্য Ansible।
2. **আবার বানানোর সময় সবসময় এই ক্রমে:** Terraform, তারপর Ansible।
3. **Output দিয়ে হাতবদল করো,** provisioner দিয়ে নয়।
4. **দুটো ধাপ, আলাদাভাবে review:** `plan` ও `--check --diff`।
5. **কখনো commit করো না** state, plan file, secret সহ `.tfvars`, বা vault password file।
6. **সর্বনিম্ন ক্ষমতার credential, ছোট মেয়াদ,** শুধু environment variable।
7. **আগে plan; ইচ্ছা করে apply;** destroy করো আর **control panel-এ যাচাই করো**।

## ১০. নিজে যাচাই করো

<details>
<summary><strong>Q1. একটা পরিবর্তন "firewall-এ port 8080 খোলা"। Terraform না Ansible?</strong></summary>

Terraform। Firewall provider account-এ থাকা cloud infrastructure, server-এর ভেতরের কিছু নয়।
</details>

<details>
<summary><strong>Q2. একটা পরিবর্তন "server-এ আরেকটা package install করা"। Terraform না Ansible?</strong></summary>

Ansible। এটা server-এর ভেতরে কী আছে তা নিয়ে, আর Ansible-এর `apt` module idempotent।
</details>

<details>
<summary><strong>Q3. Terraform কীভাবে Ansible-কে server-এর address জানায়?</strong></summary>

একটা output (`droplet_ip`) দিয়ে। হয় Terraform `templatefile` ও `local_file` দিয়ে একটা inventory file render করে, নয়তো তুমি `terraform output -raw droplet_ip` দিয়ে output পড়ে নিজে inventory লেখো।
</details>

<details>
<summary><strong>Q4. Terraform provisioner থেকে কেন `ansible-playbook` ডাকবে না?</strong></summary>

Docs provisioner-কে শেষ উপায় বলে: Terraform এগুলো plan-এ model করতে পারে না, এগুলো সরাসরি access ও credential চায়, আর ব্যর্থতা resource-কে আধা-configure রেখে যেতে পারে। Ansible নিজস্ব ধাপে চালালে দুটোই review-যোগ্য ও আবার চালানোর যোগ্য থাকে।
</details>

<details>
<summary><strong>Q5. `terraform output -raw NAME` কী করে, আর এর বিপদ কী?</strong></summary>

এটা একটা string, number বা boolean output formatting ছাড়া print করে, যা shell script-এর জন্য ভালো। `-raw` বা `-json` দিলে Terraform sensitive মান সাদা text-এ দেখায়, তাই secret-এ কখনো ব্যবহার করো না বা log-এ print করো না।
</details>

<details>
<summary><strong>Q6. এই project-এর কোন file commit করা যাবে না?</strong></summary>

State file, `.terraform/`, secret সহ `.tfvars` file, তৈরি করা `inventory.ini` ও vault password file (সাথে যেকোনো plan file)।
</details>

<details>
<summary><strong>Q7. Server আবার বানাতে tool গুলো কোন ক্রমে চালাবে, আর Ansible run সুস্থ কি না কীভাবে দেখবে?</strong></summary>

আগে Terraform (server বানাও বা প্রতিস্থাপন করো), তারপর Ansible। সুস্থ Ansible run প্রথমবার `--check --diff` দিয়ে preview-র পর দ্বিতীয়বার `changed=0` দেখায়।
</details>

<details>
<summary><strong>Q8. অনুশীলনের `apply`-এর পরে কী করতে হবে, আর কেন?</strong></summary>

`terraform destroy` চালাও আর cloud control panel-এ দেখো কিছু চলছে না, কারণ resource destroy না হওয়া পর্যন্ত billing চলে, server বন্ধ থাকলেও। তারপর API token মুছে ফেলো।
</details>

## ১১. বিনামূল্যের resource

- Terraform docs: [`templatefile`](https://developer.hashicorp.com/terraform/language/functions/templatefile), [`terraform output`](https://developer.hashicorp.com/terraform/cli/commands/output), [Provisioners](https://developer.hashicorp.com/terraform/language/provisioners)
- Provider docs: [`local_file` resource](https://github.com/hashicorp/terraform-provider-local/blob/main/docs/resources/file.md)
- এই course-এর আগে: Chapter 5 (DigitalOcean resource ও token) ও Chapter 7 (CI)। IaC course-এ: inventory ও ad-hoc command, playbook, role ও Vault।

## এরপর কোথায়

তুমি Terraform course শেষ করেছ। আটটা chapter quiz পাস করার পরে website-এ **final exam** দাও। আরও এগোতে: **Infrastructure as Code & Ansible** course-এ Ansible role দিয়ে পূর্ণ Laravel stack বানাও, তারপর এই chapter-এর pipeline-এর সাথে জোড়ো।

---

**পরের ধাপ:** Terraform-এর final exam, website-এ, আটটা chapter quiz পাস করার পরে।
