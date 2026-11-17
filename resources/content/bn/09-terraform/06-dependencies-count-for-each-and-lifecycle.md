# Chapter 6 — Dependency, `count`, `for_each` ও `lifecycle`

> **Course:** Terraform · **Chapter:** 6 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/terraform/06-dependencies-count-for-each-and-lifecycle)

প্রতিটা আসল project-এ এখন তিনটা প্রশ্ন ওঠে। Terraform **কোন ক্রমে** জিনিস বানায়, আর তুমি কি তা প্রভাবিত করতে পারো? **অনেকগুলো একই রকম জিনিস** কীভাবে বানাবে একটা block দশবার কপি না করে? আর **মূল্যবান কিছু কীভাবে রক্ষা করবে**, বা একটা setting-কে একা রাখতে Terraform-কে কীভাবে বলবে? উত্তর তিনটা tool: **dependency**, **`count` ও `for_each`** meta-argument, আর **`lifecycle`** block। তিনটাই যেকোনো resource-এ কাজ করে, তাই উদাহরণ Docker project ব্যবহার করে আর কোনো cloud account লাগে না।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Terraform ও OpenTofu **install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের code সরকারি Terraform documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: implicit ও explicit dependency কীভাবে কাজ করে ও `depends_on` নিয়ে পরামর্শ, `count` ও `for_each`-এর syntax, `count.index`, `each.key` ও `each.value`, দুটোর বিধিনিষেধ (plan-এর সময় মান জানা থাকতে হবে, `for_each`-এ sensitive মান নয়, list থেকে set-এ স্বয়ংক্রিয় বদল নেই), আর প্রতিটা `lifecycle` argument তার সতর্কতাসহ সরকারি docs থেকে পড়া, আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। **HCL Terraform দিয়ে parse করা হয়নি**, তাই ছোট ভুল থাকতে পারে। একটা বিষয় **`count` documentation page-এ বলা নেই**: একটা list-এর মাঝখান থেকে আইটেম সরালে instance address-এর কী হয়। ৪ নম্বর অংশ এটা index address কীভাবে কাজ করে তা থেকে ব্যাখ্যা করে, আর তোমার নিজের practice machine-এ plan চালিয়ে যাচাই করতে বলে। এগুলো আসল কিছুতে কখনো চালিও না, আর `yes` টাইপ করার আগে প্রতিটা plan পড়ো।

![count instance-কে সংখ্যা দিয়ে চেনে, for_each key দিয়ে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/09-terraform/images/count-vs-for-each.svg)

## ১. Dependency: কোনটা কীসের আগে বানানো হয়

Terraform তোমার resource-এর একটা **graph** বানায়। মূল docs যেমন বলে, সে প্রস্তাবিত কাজ করে "সঠিক ক্রমে, resource dependency মেনে", আর স্বাধীন resource সমান্তরালে চালায়। বেশিরভাগ সময় তুমি ক্রম বলো না। তুমি শুধু অন্য একটা resource **উল্লেখ** করো, আর Terraform dependency দেখে নেয়:

```hcl
resource "docker_image" "nginx" {
  name = "nginx:latest"
}

resource "docker_container" "web" {
  image = docker_image.nginx.image_id   # this reference creates the dependency
  name  = "web"
}
```

এটা একটা **implicit dependency**: container এমন একটা মান ব্যবহার করে যা image-এর পরেই শুধু থাকে। Docs-এর পরামর্শ: "`depends_on`-এর বদলে যখনই সম্ভব dependency বোঝাতে expression reference ব্যবহার করার সুপারিশ করি।"

### `depends_on`: একটা লুকানো dependency

কখনো B-এর জন্য A আগে থাকা লাগে, কিন্তু B A-র কোনো মান ব্যবহার করে না, তাই উল্লেখ করার কিছু নেই। Docs এগুলোকে বলে "লুকানো resource বা module dependency যা Terraform নিজে অনুমান করতে পারে না"। তখনই শুধু হাতে বলো (ধরো project-এর অন্য কোথাও একটা `docker_container.db` ঘোষণা করা আছে):

```hcl
resource "docker_container" "migrate" {
  image = docker_image.nginx.image_id
  name  = "migrate"

  # The migration needs the database to be running, but it uses none of its values.
  depends_on = [docker_container.db]
}
```

Docs বলে: "`depends_on` শুধু শেষ উপায় হিসেবে ব্যবহার করা উচিত", কারণ এটা plan-কে বেশি রক্ষণশীল করতে পারে। কেন লাগছে তা বলে সবসময় একটা **comment** লেখো, যাতে পরের পাঠক এটা মুছে না ফেলে।

## ২. `count`: N কপি, সংখ্যা দেওয়া

`count` কয়েকটা প্রায় একই রকম instance বানায়। Docs-এর উদাহরণে `count = 4` চারটা বানায়, আর `count.index` প্রতিটাকে তার সংখ্যা দেয়, `0` থেকে শুরু:

```hcl
resource "docker_container" "web" {
  count = 3
  image = docker_image.nginx.image_id
  name  = "web-${count.index}"

  ports {
    internal = 80
    external = 8000 + count.index
  }
}
```

তিনটা container: port 8000-এ `web-0`, 8001-এ `web-1`, 8002-এ `web-2`। Instance-কে index দিয়ে ডাকো: `docker_container.web[0]`, `docker_container.web[1]`। শুধু block-এর নাম `docker_container.web` পুরো সেট বোঝায়। প্রতিটা instance থেকে মান পড়তে `for` expression ব্যবহার করো (Chapter 2):

```hcl
output "container_names" {
  value = [for c in docker_container.web : c.name]
}
```

**ঐচ্ছিক resource:** `count` কিছু শর্তসাপেক্ষ করারও সাধারণ উপায়, কারণ `0` মানে "কোনোটাই নয়"। Chapter 2-এর conditional expression দিয়ে:

```hcl
resource "docker_container" "debug" {
  count = var.enable_debug ? 1 : 0
  image = docker_image.nginx.image_id
  name  = "debug"
}
```

**`count` ও `for_each` দুটোরই নিয়ম:** মান **plan-এর সময় জানা** থাকতে হবে। Docs: `count` "এমন resource attribute উল্লেখ করতে পারে না যা configuration apply হওয়ার পরেই শুধু জানা যায়"। `apply`-এর পরেই শুধু থাকে এমন মান পড়ে তুমি container গুনতে পারো না।

## ৩. `for_each`: প্রতি key-তে একটা instance

`for_each` একটা **map** বা **string-এর set** নেয়, আর প্রতিটা এন্ট্রির জন্য একটা instance বানায়। ভেতরে `each.key` হলো "এই instance-এর সাথে মেলা map-এর key বা set-এর সদস্য", আর `each.value` "map-এর মান" (set-এর ক্ষেত্রে key-র সমান)।

```hcl
variable "sites" {
  type        = map(number)
  description = "Site name and the port it listens on"
  default = {
    dev     = 8000
    staging = 8080
  }
}

resource "docker_container" "web" {
  for_each = var.sites
  image    = docker_image.nginx.image_id
  name     = "${each.key}-web"

  ports {
    internal = 80
    external = each.value
  }
}
```

Instance গুলো **key দিয়ে** ডাকা হয়: `docker_container.web["dev"]`, `docker_container.web["staging"]`। String-এর set-ও চলে; একটা list `toset()` দিয়ে বদলাও:

```hcl
for_each = toset(["Todd", "James", "Alice", "Dottie"])
```

Docs অনুযায়ী বিধিনিষেধ:

- Map-এর key গুলো, বা set-এর সব মান, plan-এর সময় **জানা মান** হতে হবে।
- `for_each` list বা tuple-কে set-এ **স্বয়ংক্রিয়ভাবে বদলায় না**: `toset()` ব্যবহার করো।
- **Sensitive মান** `for_each`-এর argument হতে পারে না।
- একটা block **`count` ও `for_each` দুটোই** ব্যবহার করতে পারে না।

প্রতিটা instance থেকে মান পড়া: key রেখে একটা map বানানো `for` expression:

```hcl
output "container_names" {
  value = { for key, c in docker_container.web : key => c.name }
}
```

Module-ও `for_each` ব্যবহার করতে পারে (Chapter 4): একটা `module` block-এ `for_each = var.sites` প্রতি site-এর জন্য একটা **module instance** বানায়, প্রতিটা `module.web["dev"]` রূপে ডাকা।

## ৪. `count` না `for_each`? গুরুত্বপূর্ণ পার্থক্য

Docs-এর নিয়ম: "প্রায় একই রকম instance বানাতে চাইলে `count` ব্যবহার করো", আর "যখন কিছু instance-এর argument-এর **আলাদা মান** লাগে তখন `for_each` ব্যবহার করো" যা একটা integer থেকে বের করা যায় না।

একটা ফাঁদ আছে, আর এটাই বলে কেন আসল infrastructure-এ `for_each` প্রায়ই ভালো পছন্দ। **`count` instance-কে অবস্থান দিয়ে চেনে**: `web[0]`, `web[1]`, `web[2]`। ধরো তুমি নামের একটা **list** থেকে instance বানাও:

```hcl
variable "names" {
  default = ["alpha", "beta", "gamma"]
}

resource "docker_container" "app" {
  count = length(var.names)
  image = docker_image.nginx.image_id
  name  = var.names[count.index]
}
```

এখন list-এর **মাঝখান** থেকে `"beta"` সরাও। `gamma` অবস্থান 2 থেকে 1-এ আসে। Terraform state-এ `app[1]`-কে "beta নামের container" হিসেবে মনে রাখে, আর এখন configuration বলছে `app[1]`-এর নাম `gamma` হওয়া উচিত, আর `app[2]` আর নেই। শুধু `beta` সরানোর বদলে plan চাইবে `app[1]` **বদলাতে বা প্রতিস্থাপন** করতে আর `app[2]` **destroy** করতে। এটা সংখ্যা দিয়ে address করার ফল। (এটা `count` documentation page-এ লেখা নেই, তাই **নিজে প্রমাণ করো**: তোমার practice project-এ plan চালাও আর পড়ো।)

`for_each` এটা এড়ায়, কারণ প্রতিটা instance-এর একটা **স্থির key** আছে। ওপরের map থেকে `staging` key সরাও, আর শুধু `docker_container.web["staging"]` destroy-এর plan হয়। `dev`-কে ছোঁয়া হয় না। সাধারণ নিয়ম: **"N টা একই রকম জিনিস" ও on/off (`0` বা `1`)-এর জন্য `count`; instance-এর নাম থাকলেই `for_each`।**

পরে কোনো resource একক block থেকে `count` বা `for_each`-এ নিলে পুরনো address আর থাকে না। `moved` block (Chapter 4) ব্যবহার করো, যেমন `from = docker_container.web` ও `to = docker_container.web["dev"]`, যাতে বিদ্যমান object থাকে, আর plan-এ destroy নেই তা যাচাই করো।

## ৫. `lifecycle` block

`lifecycle` block **একটা resource-কে Terraform কীভাবে দেখে** তা বদলায়। এটা resource-এর ভেতরে বসে। Docs এই argument গুলো দেয়:

### `create_before_destroy`

পরিবর্তন প্রতিস্থাপন বাধ্য করলে ডিফল্ট হলো পুরনো object destroy করে তারপর নতুনটা create করা। এটা Terraform-কে **আগে নতুনটা বানাতে** বলে:

```hcl
lifecycle {
  create_before_destroy = true
}
```

Downtime গুরুত্বপূর্ণ হলে কাজের। Docs সতর্ক করে এটা opt-in আর "প্রতিটা resource type-এর সীমাবদ্ধতা তোমাকে বুঝতে হবে" (দুটো object একসাথে থাকতে পারে, তাই নাম বা port সংঘাত করতে পারে)। Dependency এটা উত্তরাধিকারে পায়।

### `prevent_destroy`

```hcl
lifecycle {
  prevent_destroy = true
}
```

Terraform "যে plan resource-এর সাথে যুক্ত infrastructure object destroy করবে সেটা প্রত্যাখ্যান করে আর error ফেরত দেয়।" এটা এমন জিনিসের পাহারাদার যা দুর্ঘটনায় কখনো হারানো চলবে না, যেমন database। Docs-এর সরাসরি দুটো সৎ সীমা: এটা ওই object-এর জন্য **`terraform destroy`-ও আটকায়** (আগে setting সরাতে হবে), আর resource-এর configuration মুছে ফেললে এটা **destroy আটকায় না**, কারণ "Terraform `create_before_destroy` ছাড়া কোনো resource-এর `lifecycle` rule স্পষ্টভাবে state-এ রেকর্ড করে না"। এটা plan-এর বিরুদ্ধে পাহারা, কেউ block মুছে ফেললে নয়। Code review ও backup তবু গুরুত্বপূর্ণ।

### `ignore_changes`

```hcl
resource "docker_container" "web" {
  # ...
  lifecycle {
    ignore_changes = [
      labels
    ]
  }
}
```

(`labels` শুধু একটা দৃষ্টান্ত: তোমার resource type-এ সত্যিই আছে এমন একটা attribute-এর নাম দাও।) Resource **তৈরির** সময় Terraform attribute ব্যবহার করে, কিন্তু update-এর plan করার সময় **পরের পার্থক্য উপেক্ষা করে**। যখন অন্য কোনো tool বৈধভাবে একটা মান বদলায় (একটা tool label বা tag যোগ করে) তখন ব্যবহার করো। Docs অনুযায়ী `ignore_changes = all` প্রতিটা attribute উপেক্ষা করে, ফলে Terraform object তৈরি ও destroy করতে পারে কিন্তু কখনো update প্রস্তাব করে না। সতর্কতা: এটা শুধু resource type-এর সংজ্ঞায়িত attribute-এ কাজ করে, আর নিজের ওপর বা অন্য meta-argument-এ এটা ব্যবহার করা যায় না। কম ব্যবহার করো, কারণ এটা drift লুকায় (Chapter 3)।

### `replace_triggered_by`

```hcl
lifecycle {
  replace_triggered_by = [
    docker_image.nginx.id
  ]
}
```

উল্লেখ করা resource বা attribute বদলালেই এই resource প্রতিস্থাপন করো। তুমি শুধু **managed resource** উল্লেখ করতে পারো। Local বা variable-এর মতো সাধারণ মান সরাসরি গৃহীত হয় না; docs সেগুলো একটা `terraform_data` resource-এ মুড়তে বলে।

### `precondition` ও `postcondition`

এগুলো **বার্তাসহ পরীক্ষা**, variable-এর `validation`-এর মতো (Chapter 2), কিন্তু resource-এর জন্য। `precondition` resource-এর argument মূল্যায়নের **আগে** চলে; `postcondition` object apply হওয়ার **পরে** চলে, আর ব্যর্থ হলে তার ওপর নির্ভরশীল অন্য resource আটকে যায়:

```hcl
lifecycle {
  postcondition {
    condition     = self.public_dns != ""
    error_message = "EC2 instance must be in a VPC that has public DNS hostnames enabled."
  }
}
```

উদাহরণটা docs-এর নিজের। `self` যে resource পরীক্ষা হচ্ছে তাকে বোঝায়। একটা লুকানো ধারণাকে পরিষ্কার error বানাতে এগুলো ব্যবহার করো।

## ৬. এই feature গুলো নিয়ে plan পড়া

Chapter 1-এর চিহ্নে ফিরে তাকাও। একটা `count` বা `for_each` পরিবর্তন **প্রতি instance-এ একটা লাইন** দেখায়, bracket-এ address সহ, `docker_container.web["staging"]`। একটা `lifecycle` rule আচরণ হিসেবে দেখা দেয়: `create_before_destroy` থাকলে `-/+` প্রতিস্থাপনের ক্রম উল্টে যায়, আর `prevent_destroy` থাকলে যে plan object destroy করত সেটা plan-এর বদলে **error** দিয়ে ব্যর্থ হয়। ওই error-কে পাহারাদারের কাজ ধরো: থামো, আর বের করো কেন destroy প্রস্তাব হলো।

## ৭. অভ্যাস

1. **`depends_on`-এর চেয়ে reference পছন্দ করো।** শুধু লুকানো dependency-তে `depends_on` দাও, comment সহ।
2. **একই কপি ও on/off-এর জন্য `count`; নামওয়ালা instance-এর জন্য `for_each`।** Key স্থির, অবস্থান নয়।
3. **Apply-এর পরেই শুধু জানা মান থেকে `for_each` key বানিও না,** আর list-এর জন্য `toset()` ব্যবহার করো।
4. **যা আবার বানাতে পারবে না তা `prevent_destroy` দিয়ে রক্ষা করো, আর** review ও backup রাখো, কারণ block-এর সাথে পাহারাও চলে যায়।
5. **`ignore_changes` drift লুকায়।** একটা নির্দিষ্ট attribute-এর জন্য ব্যবহার করো, সত্যিই না চাইলে `all` নয়।
6. **`count`, `for_each` বা `lifecycle` বদলানোর প্রতিবার plan যাচাই করো:** যে `-` ও `-/+` আশা করোনি তা খোঁজো।

## ৮. নিজে যাচাই করো

<details>
<summary><strong>Q1. Terraform কীভাবে জানে container-এর আগে image বানাতে হবে?</strong></summary>

Container `docker_image.nginx.image_id` উল্লেখ করে। ওই reference একটা implicit dependency বানায়, আর Terraform graph সঠিক ক্রমে সাজায়।
</details>

<details>
<summary><strong>Q2. কখন `depends_on` ব্যবহার করবে, আর সাথে কী যোগ করবে?</strong></summary>

শুধু লুকানো dependency-র জন্য যা Terraform অনুমান করতে পারে না, কারণ কিছু উল্লেখ করা হয়নি। Docs একে শেষ উপায় বলে, তাই কেন লাগছে তা ব্যাখ্যা করে একটা comment যোগ করো।
</details>

<details>
<summary><strong>Q3. `count.index` ও `each.key` কী দেয়?</strong></summary>

`count.index` হলো 0 থেকে শুরু করা instance-এর সংখ্যা। `each.key` হলো ওই instance-এর map-key (বা set-এর সদস্য)।
</details>

<details>
<summary><strong>Q4. `count` দিয়ে কীভাবে একটা resource ঐচ্ছিক করবে?</strong></summary>

`count = var.enabled ? 1 : 0`। `0` হলে কোনো instance তৈরি হয় না।
</details>

<details>
<summary><strong>Q5. নামওয়ালা জিনিসের জন্য `for_each` সাধারণত `count`-এর চেয়ে নিরাপদ কেন?</strong></summary>

`count` instance-কে অবস্থান দিয়ে address করে, তাই list-এর মাঝখান থেকে আইটেম সরালে বাকিরা সরে যায় আর plan আশার চেয়ে বেশি বদলাতে বা প্রতিস্থাপন করতে পারে। `for_each` স্থির key ব্যবহার করে, তাই একটা key সরালে শুধু ওই instance প্রভাবিত হয়।
</details>

<details>
<summary><strong>Q6. `for_each`-এর দুটো বিধিনিষেধ কী?</strong></summary>

এর key বা set-এর মান plan-এর সময় জানা থাকতে হবে, আর list বা tuple স্বয়ংক্রিয়ভাবে বদলায় না (`toset()` ব্যবহার করো)। Sensitive মানও ব্যবহার করা যায় না, আর একটা block `count` ও `for_each` দুটোই ব্যবহার করতে পারে না।
</details>

<details>
<summary><strong>Q7. `prevent_destroy` কীসের বিরুদ্ধে পাহারা দেয়, আর কীসের বিরুদ্ধে নয়?</strong></summary>

এটা Terraform-কে এমন plan প্রত্যাখ্যান করায় যা object destroy করবে, `terraform destroy`-ও। Resource-এর configuration মুছে ফেললে এটা destroy আটকায় না, কারণ rule state-এ রেকর্ড হয় না।
</details>

<details>
<summary><strong>Q8. কখন `ignore_changes` উপযুক্ত, আর এর খারাপ দিক কী?</strong></summary>

যখন অন্য কোনো tool তৈরির পরে একটা attribute বৈধভাবে বদলায়। খারাপ দিক এটা drift লুকায়, তাই ওই attribute-এর পরিবর্তন Terraform টের পায় না বা ঠিক করে না।
</details>

## ৯. বিনামূল্যের resource

- Terraform docs: [`depends_on`](https://developer.hashicorp.com/terraform/language/meta-arguments/depends_on)
- Terraform docs: [`count`](https://developer.hashicorp.com/terraform/language/meta-arguments/count) ও [`for_each`](https://developer.hashicorp.com/terraform/language/meta-arguments/for_each)
- Terraform docs: [`lifecycle`](https://developer.hashicorp.com/terraform/language/meta-arguments/lifecycle) ও [`moved` block দিয়ে refactoring](https://developer.hashicorp.com/terraform/language/modules/develop/refactoring)

---

**পরের chapter:** [Chapter 7 — Remote state, workspace ও CI](07-remote-state-workspaces-and-ci.md)
