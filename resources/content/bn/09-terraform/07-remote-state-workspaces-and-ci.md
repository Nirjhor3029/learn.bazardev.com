# Chapter 7 — Remote state, workspace ও CI

> **Course:** Terraform · **Chapter:** 7 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/terraform/07-remote-state-workspaces-and-ci)

নিজের laptop-এ একটা `terraform.tfstate` file নিয়ে সবকিছু সহজ। **দলে** এটা ভেঙে পড়ে (Chapter 3): দুজন একসাথে apply করে, কেউ পুরনো কপি ব্যবহার করে, আর state file, যাতে secret থাকতে পারে, একটা laptop-এ পড়ে থাকে। এই chapter Terraform-এর দলের দিক নিয়ে: state একটা **নিরাপদ ভাগ করা জায়গায়** রাখা (একটা *backend*), একটা configuration-এর জন্য **একাধিক state** রাখা (*workspace*, আর কেন এটা তুমি যা ভাবছ তা নয়), আর **CI** থেকে Terraform চালানো যাতে প্রতিটা পরিবর্তন একই ভাবে plan, review ও apply হয়।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Terraform ও OpenTofu **install করা নেই**, কোনো cloud storage তৈরি করা হয়নি, আর কোনো workflow চালানো হয়নি। তাই নিচের code ও command গুলো সরকারি Terraform ও `setup-terraform` documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: backend কী ও তার সীমা, S3 backend-এর argument ও locking, versioning ও credential নিয়ে তার পরামর্শ, `init`-এর `-migrate-state` ও `-reconfigure` option, workspace কী ও সে নিয়ে docs-এর সতর্কতা, `terraform.tfstate.d`-এর অবস্থান, automation-এ Terraform চালানোর HashiCorp-এর নির্দেশনা, আর একটা `setup-terraform` GitHub Actions workflow-এর আকার সরকারি docs থেকে পড়া, আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। **HCL ও workflow file চালানো হয়নি।** Workflow-এর version নম্বর action-এর নিজস্ব উদাহরণ থেকে, আর সেগুলো **পুরনো হয়ে যাবে**, তাই বর্তমানগুলো দেখে নাও। এখানে কিছুই তোমাকে আসল cloud resource বানাতে বলে না।

![দল একটা ভাগ করা remote state ব্যবহার করে, আর CI প্রতিটা pull request-এ plan করে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/09-terraform/images/remote-state-and-ci.svg)

## ১. Backend: state কোথায় থাকে

**Backend** ঠিক করে Terraform তার state কোথায় রাখে। Docs: "Backend ঠিক করে Terraform তার state ডেটা file কোথায় রাখবে।" কিছু configure না করলে Terraform **`local`** backend ব্যবহার করে, যা তুমি এ পর্যন্ত ব্যবহার করা `terraform.tfstate` file। State "একাধিক মানুষকে একই infrastructure-সংগ্রহে access ও কাজ করতে দেয়", কিন্তু শুধু যদি সেটা এমন কোথাও থাকে যেখানে **সবাই** পৌঁছাতে পারে।

তুমি backend configure করো `terraform` block-এর ভেতরে। Docs-এর উদাহরণ (`remote` backend দিয়ে):

```hcl
terraform {
  backend "remote" {
    organization = "example_corp"

    workspaces {
      name = "my-app-prod"
    }
  }
}
```

Docs থেকে নিয়ম:

- **একটা configuration-এ একটা backend।** "একটা configuration শুধু একটা backend block দিতে পারে।"
- **কোনো variable নয়।** "একটা backend block নামওয়ালা মান (input variable, local বা data source attribute-এর মতো) উল্লেখ করতে পারে না।" Backend-এর setting লিখে দিতে হয়, বা `init`-এর সময় অন্যভাবে দিতে হয়।
- **যেকোনো পরিবর্তনের পরে `init` আবার চালাও।** "Backend-এর configuration বদলানোর পরে তোমাকে আবার `terraform init` চালাতে হবে।"
- **Locking।** কিছু backend শুধু state জমা রাখে। অন্যরা "দ্বন্দ্ব ও অসামঞ্জস্য রোধ করতে Terraform-এর কাজের সময় state lock করা সমর্থন করে।" Locking আছে এমন backend পছন্দ করো: এটা দুটো run-কে একই state একসাথে বদলাতে আটকায়।

## ২. একটা উদাহরণ: S3 backend

Docs-এর S3 উদাহরণ (bucket-এর নাম ও key placeholder):

```hcl
terraform {
  backend "s3" {
    bucket = "mybucket"
    key    = "path/to/my/key"
    region = "us-east-1"
  }
}
```

S3 backend documentation-এর পরামর্শ:

| বিষয় | পরামর্শ |
|---|---|
| **Versioning** | জোরালোভাবে সুপারিশ: "দুর্ঘটনায় মোছা বা মানুষের ভুলে state পুনরুদ্ধার করতে" **bucket versioning** চালু করো |
| **Locking** | `use_lockfile` একটা lock file দিয়ে locking চালু করে (ডিফল্ট `false`)। DynamoDB দিয়ে locking **deprecated** |
| **Encryption** | `encrypt` (ঐচ্ছিক) state ও lock file-এর server-side encryption চালু করে |
| **Credential** | "Credential ও অন্য সংবেদনশীল ডেটা দিতে environment variable ব্যবহার করার সুপারিশ করি" (যেমন `AWS_ACCESS_KEY_ID` ও `AWS_SECRET_ACCESS_KEY`), config-এ কখনো নয় |

একই page-এর দুটো সতর্কতা, **যেকোনো** backend-এর জন্য মনে রাখার মতো: `-backend-config` দিয়ে দেওয়া বা configuration-এ লেখা মান "`.terraform` subdirectory ও plan file দুটোতেই" থাকে, আর Terraform file-এ বসানো একটা encryption key "`terraform.tfstate`-এ disk-এ রেখে দেওয়ার কারণ হয়"। তাই secret backend block-এর **বাইরে** রাখো, আর environment দিয়ে দাও। আর জানার মতো একটা তথ্য: storage নিজে (bucket) Terraform-এর তাতে state রাখার **আগে থাকা** চাই, তাই project তার দিকে নির্দেশ করার আগে এটা একবার, আলাদাভাবে তৈরি করা হয়।

যে backend-ই বাছো, Chapter 1 ও 3-এর নিয়ম এখনো খাটে: state-এ secret থাকতে পারে, তাই storage-এ লাগে **কড়া access control**, **encryption**, আর **versioning বা backup**।

### বিদ্যমান state নতুন backend-এ সরানো

`backend` block যোগ বা বদলালে আবার `terraform init` চালাও। Docs: Terraform তোমার **বিদ্যমান state নতুন backend-এ সরানোর** প্রস্তাব দেয়, আর block সরালে `local`-এ ফিরিয়ে আনার প্রস্তাব দেয়। `init`-এর option গুলো গুরুত্বপূর্ণ:

| Option | কী করে (docs থেকে) |
|---|---|
| `-migrate-state` | "বিদ্যমান state নতুন backend-এ কপি করার চেষ্টা করে" |
| `-reconfigure` | "বিদ্যমান configuration উপেক্ষা করে, বিদ্যমান state-এর **migration আটকায়**" |

"Backend configuration update করতে `-reconfigure` বা `-migrate-state`-এর একটা দিতেই হবে।" ভুলটা বাছা সহজ আর দামি: `-reconfigure` নতুন backend শুরু করে তোমার পুরনো state **ছাড়াই**, আর Terraform তখন ভাবে কিছুই নেই। **যেকোনো migration-এর আগে তোমার বর্তমান state file নিরাপদ কোথাও কপি করো।** আর মনে রেখো `terraform state` command (Chapter 3)-ও backup লেখে।

## ৩. Workspace: একটা configuration-এর জন্য কয়েকটা state

কিছু backend একটা configuration-এর জন্য **একাধিক নামওয়ালা state** সমর্থন করে। Docs: "একটা configuration-এর সাথে একাধিক state যুক্ত"। প্রতিটাকে বলে **workspace**। Terraform সবসময় `default` নামের একটা নিয়ে শুরু করে, "যা তুমি মুছতে পারো না"।

```bash
terraform workspace list
terraform workspace new staging
terraform workspace select staging
terraform workspace delete staging
```

এই চারটা command (`list`, `new`, `select`, `delete`) docs-এ নাম করা। **Local** backend-এ non-default workspace-এর state **`terraform.tfstate.d`** নামের directory-তে থাকে, আর docs একে একটা শুধু-স্থানীয় `terraform.tfstate`-এর মতো ধরতে বলে: কখনো commit করো না। Remote backend-এ workspace-এর নাম backend-এর state path-এ যোগ হয়।

Code-এর ভেতরে `${terraform.workspace}` দিয়ে বর্তমান workspace-এর নাম ব্যবহার করতে পারো, "যেখানেই interpolation অনুমোদিত"। যেমন, যাতে একই code প্রতিটা workspace-কে তার নিজের container-নাম দেয়:

```hcl
resource "docker_container" "web" {
  image = docker_image.nginx.image_id
  name  = "${terraform.workspace}-web"

  ports {
    internal = 80
    external = 8000
  }
}
```

(এখানে দুটো workspace-ই port 8000 চাইবে। প্রতিটাকে আলাদা port দাও, যেমন `terraform.workspace` দিয়ে key করা একটা map দিয়ে, নইলে দ্বিতীয় apply সংঘাত করবে।)

### যে সতর্কতা এড়িয়ে যেও না

Workspace দেখতে "প্রতি environment-এ একটা workspace"-এর মতো। Docs পরিষ্কার বলে যে যখন গুরুত্বপূর্ণ তখন এটা এর জন্য **নয়**:

- "Workspace system decomposition বা **আলাদা credential ও access control** লাগে এমন deployment-এর জন্য উপযুক্ত নয়।"
- "এগুলো private নয়, যদি না তুমি শুধু স্থানীয় state ব্যবহার করো আর তোমার state version control-এ commit না করো।"
- Docs বদলে সুপারিশ করে **আলাদা configuration**, প্রতিটার নিজের backend সহ, **পুনর্ব্যবহারযোগ্য module** (Chapter 4)-এর ওপর বানানো, কড়া বিচ্ছেদের জন্য।

সোজা কথায়: `dev` আর `prod` একই bucket-এ, একই credential নিয়ে একই configuration-এর দুটো workspace হওয়া উচিত নয়। `dev`-এ একটা ভুল, বা `dev`-এর একটা credential ফাঁস, `prod`-এ পৌঁছাতে পারা চলবে না। (আরও লক্ষ্য করো, HCP Terraform-এ "workspace"-এর অন্য মানে আছে; docs এগুলোকে আলাদা ধারণা বলে।) Workspace ঠিক আছে একই জিনিসের **স্বল্পস্থায়ী কপির** জন্য, যেমন একটা feature branch-এর পরীক্ষামূলক কপি।

### Environment আলাদা রাখার ধাঁচ

প্রতি environment-এ একটা **folder** রাখো, প্রতিটার নিজের backend setting ও নিজের credential সহ, সবাই একই module ডাকে:

```text
infrastructure/
├── modules/
│   └── web_server/        # written once (Chapter 4)
└── environments/
    ├── dev/
    │   ├── main.tf        # calls ../../modules/web_server, backend key "dev"
    │   └── ...
    └── prod/
        ├── main.tf        # calls ../../modules/web_server, backend key "prod"
        └── ...
```

প্রতিটা environment-এর **নিজের state**, নিজের plan, আর নিজের, আরও কড়াভাবে রাখা, token ব্যবহার করতে পারে। ভাগ করা code module-এ থাকে, তাই তুমি তবু একবারই লেখো।

## ৪. CI-তে Terraform

Laptop থেকে `terraform apply` চালানো scale করে না: এটা একজনের machine, একজনের credential আর একজনের স্মৃতির ওপর নির্ভর করে। Automation-এ Terraform চালানোর HashiCorp-এর guide ধাঁচটা বর্ণনা করে। এর মূল কথা:

**Non-interactive run।** `-input=false` ব্যবহার করো যাতে Terraform কখনো prompt-এর জন্য অপেক্ষা না করে: "`-input=false` option বলে Terraform input চাওয়ার চেষ্টা করবে না।" Variable দাও `-var` বা `-var-file` দিয়ে।

```bash
terraform init -input=false
terraform plan -out=tfplan -input=false
terraform apply -input=false tfplan
```

**Plan save করো আর সেই file apply করো।** `-out` plan save করে, আর একই file apply করার মানে **একজন মানুষ যে plan review করেছে সেটাই চলে**। Plan file-এ তোমার configuration, state ও variable থাকে, তাই এটা রক্ষা করো (Chapter 1)। "Plan বানানো ও apply করার মাঝে Terraform বা কোনো plugin upgrade হলে" error আসে, তাই version pin করো (docs-এর উদাহরণ `terraform` block-এ `required_version = ">= 1.2.0"` দেয়)।

**একজন মানুষ অনুমোদন দেয়।** মূল workflow-এ একটা ধাপ আছে "একজন human operator সেই plan review করে নিশ্চিত করুক এটা গ্রহণযোগ্য"। একসাথে শুধু **একটা বকেয়া plan** রাখো, কারণ একটা plan apply হলে একই state-এর বিরুদ্ধে বানানো অন্যগুলো অকার্যকর হয়ে যায়। Auto-approval শুধু ফেলনা কাজের জন্য: "স্বয়ংক্রিয় অনুমোদন **শুধু** non-critical infrastructure-এ ব্যবহার করো।"

**Environment থেকে credential।** "Provider authentication credential-এর জন্য যেখানে সম্ভব তার বদলে environment variable ব্যবহারের সুপারিশ করা হয়", আর Terraform এগুলো plan-এ রাখে না বা disk-এ লেখে না। CI-তে এর মানে CI system-এর **secret**, repository-র file নয়।

**Pull request-এ plan, merge-এর পরে apply।** Docs থেকে ধাঁচ: review-এর অধীন প্রতিটা commit-এ `terraform plan -input=false` চালাও (`-out` ছাড়া, "কারণ review plan কখনো apply হয় না"), আর পরিবর্তন merge হলে "primary version control branch থেকে একটা নতুন plan বানিয়ে apply করা যায়"। এটা Git ও CI/CD course-এর infrastructure-এ প্রয়োগ।

খোলা project-এর জন্য docs-এর একটা সতর্কতা: CI-তে Terraform-কে secret দিলে "যে-ই PR জমা দিতে পারে সে তা আবিষ্কার করতে পারে" যদি না যত্ন নাও। তাই pull request-এ যে job চলে সেটা **সবচেয়ে কম ক্ষমতার token** (Chapter 5: plan করতে Read Only যথেষ্ট) ব্যবহার করবে, আর যে job apply করে সেটা শুধু তোমার main branch-এ, একটা অনুমোদনের পিছনে চলবে।

### প্রতিটা pull request-এ plan করা একটা GitHub Actions workflow

`.github/workflows/terraform-plan.yml`, `setup-terraform` action-এর নিজস্ব উদাহরণ থেকে বানানো:

```yaml
name: terraform-plan

on:
  pull_request:

jobs:
  plan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: hashicorp/setup-terraform@v4
        with:
          terraform_version: "1.14.6"

      - name: Format check
        run: terraform fmt -check

      - name: Init
        run: terraform init -input=false

      - name: Validate
        run: terraform validate -no-color

      - name: Plan
        run: terraform plan -no-color -input=false
        env:
          DIGITALOCEAN_TOKEN: ${{ secrets.DIGITALOCEAN_READONLY_TOKEN }}
```

একটা রেসিপি হিসেবে পড়ো: code checkout করো; একটা **pin করা** Terraform version install করো; formatting ঠিক না থাকলে job ব্যর্থ করো (`terraform fmt -check`); prompt ছাড়া initialise করো; validate করো; তারপর plan করো, token একটা **repository secret** থেকে একটা **environment variable**-এ আসছে (file-এ কখনো লেখা নয়)। Action-এর README উদাহরণ `terraform_version` pin করে আর জানায় action একটা wrapper install করে যা পরের `terraform` ধাপের output প্রকাশ করে (`terraform_wrapper: false` দিয়ে বন্ধ করা যায়)। দেখানো version (`v4`, `1.14.6`) লেখার সময়ে README-র উদাহরণ: **বর্তমানগুলো খুঁজে নাও**।

Reviewer job-এর log-এ plan পড়ে, যেভাবে code diff পড়ে। দ্বিতীয়, আলাদা job (এখানে দেখানো নেই) শুধু merge-এর পরে main branch-এ চলবে, আর একটা saved plan ও একটা human approval ধাপ দিয়ে apply করবে। Protected environment ও approval-এর জন্য তোমার CI system-এর documentation দেখো।

## ৫. দলের জন্য একটা rollout checklist

1. **Locking, encryption ও versioning সহ backend,** আলাদাভাবে তৈরি আর কড়া access দিয়ে রক্ষিত।
2. **প্রতি environment-এ একটা state,** আলাদা folder ও credential, ভাগ করা module।
3. **Credential শুধু environment বা CI secret-এ,** plan job-এ সর্বনিম্ন ক্ষমতা।
4. **Pin করা Terraform ও provider version** (`required_version`, `~>`, আর commit করা `.terraform.lock.hcl`)।
5. **প্রতিটা pull request-এ plan,** code-এর মতো review করা; **শুধু merge-এর পরে apply,** একটা saved plan থেকে, অনুমোদন সহ।
6. **কখনো** state, plan file বা secret সহ `.tfvars` commit করো না।
7. **State migrate করার আগে** কপি করো, আর রাখতে চাইলে `-reconfigure` নয়, `-migrate-state` বাছো।

## ৬. নিজে যাচাই করো

<details>
<summary><strong>Q1. Backend কী, আর ডিফল্টটা কী?</strong></summary>

যে জায়গায় Terraform তার state রাখে। ডিফল্ট `local`, disk-এ একটা `terraform.tfstate` file।
</details>

<details>
<summary><strong>Q2. `backend` block-এর ভেতরে কেন variable ব্যবহার করা যায় না?</strong></summary>

Docs বলে একটা backend block input variable, local বা data source attribute-এর মতো নামওয়ালা মান উল্লেখ করতে পারে না। Backend-এর setting লিখে দিতে হয় বা init-এর সময় অন্যভাবে দিতে হয়।
</details>

<details>
<summary><strong>Q3. State locking কীসের বিরুদ্ধে রক্ষা করে?</strong></summary>

দুটো run একই state একসাথে বদলানোর বিরুদ্ধে, যা দ্বন্দ্ব ও অসামঞ্জস্যপূর্ণ state তৈরি করত।
</details>

<details>
<summary><strong>Q4. তুমি backend বদলালে। `-migrate-state` আর `-reconfigure`-এর পার্থক্য কী?</strong></summary>

`-migrate-state` তোমার বিদ্যমান state নতুন backend-এ কপি করে। `-reconfigure` বিদ্যমান configuration উপেক্ষা করে আর state migrate করে না, তাই নতুন backend ফাঁকা থাকে আর Terraform ভাবে কিছুই নেই।
</details>

<details>
<summary><strong>Q5. Backend-এর credential কোথায় থাকা উচিত?</strong></summary>

Environment variable (বা CI system-এর secret)-এ, backend block বা `-backend-config`-এ নয়, কারণ ওই মান `.terraform` directory ও plan file-এ গিয়ে থাকে।
</details>

<details>
<summary><strong>Q6. `dev` ও `prod` আলাদা রাখতে workspace খারাপ উপায় কেন?</strong></summary>

Workspace একটা configuration ও backend ভাগ করে আর private নয়, তাই আলাদা credential বা access control দেয় না। Docs সুপারিশ করে আলাদা configuration, প্রতিটার নিজের backend সহ, পুনর্ব্যবহারযোগ্য module-এর ওপর বানানো।
</details>

<details>
<summary><strong>Q7. CI-তে কেন `-out` দিয়ে plan save করে সেই file apply করবে?</strong></summary>

যাতে একজন মানুষ যে plan review করেছে ঠিক সেটাই apply হয়। নতুন বানানো plan অনুমোদিতটার থেকে আলাদা হতে পারত।
</details>

<details>
<summary><strong>Q8. "Pull request-এ plan, merge-এ apply" ধাঁচ কী?</strong></summary>

Review-এর অধীন প্রতিটা commit-এ `terraform plan` চালাও যাতে reviewer প্রস্তাবিত পরিবর্তন পড়তে পারে, আর পরিবর্তন merge হলে main branch থেকে একটা plan বানিয়ে apply করো, গুরুত্বপূর্ণ কিছুর জন্য human অনুমোদন সহ।
</details>

## ৭. বিনামূল্যের resource

- Terraform docs: [Backends](https://developer.hashicorp.com/terraform/language/backend), [S3 backend](https://developer.hashicorp.com/terraform/language/backend/s3), [`terraform init`](https://developer.hashicorp.com/terraform/cli/commands/init)
- Terraform docs: [Workspaces (state)](https://developer.hashicorp.com/terraform/language/state/workspaces) ও [CLI workspaces](https://developer.hashicorp.com/terraform/cli/workspaces)
- HashiCorp tutorial: [Automate Terraform](https://developer.hashicorp.com/terraform/tutorials/automation/automate-terraform)
- GitHub: [`hashicorp/setup-terraform`](https://github.com/hashicorp/setup-terraform)

---

**পরের chapter:** Chapter 8 — Real project: Terraform ও Ansible একসাথে *(শীঘ্রই আসছে)*
