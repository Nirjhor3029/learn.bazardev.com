# Chapter 7 — নিরাপদে Ansible চালানো: tag, limit, lint ও CI

> **Course:** Infrastructure as Code & Ansible · **Chapter:** 7 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/iac/07-ansible-safety-testing-and-ci)

এখন তোমার এমন playbook ও role আছে যা একটা পুরো server বানাতে পারে। এটা ক্ষমতা, আর ক্ষমতা নিয়ে প্রথম শেখার জিনিস হলো তা দিয়ে নিজেকে আঘাত না করা। একটা Ansible command কয়েক সেকেন্ডে তোমার inventory-র **প্রতিটা** server বদলে দিতে পারে। এই chapter **নিরাপত্তার স্তর** নিয়ে: পরিবর্তনের preview দেখা, তা কয়েকটা host বা task-এ **সংকীর্ণ** করা, **ধীরে ধীরে** চালু করা, **linter** দিয়ে ভুল স্বয়ংক্রিয়ভাবে ধরা, আর সবকিছু **CI**-তে রাখা যাতে কেউ মনে রাখার ওপর নির্ভর না করে।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Ansible ও `ansible-lint` **install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের command ও file গুলো সরকারি Ansible ও Ansible Lint documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: host pattern ও `--limit`, `--tags` ও `--skip-tags` সাথে `--list-tags` ও `--list-tasks`, বিশেষ tag `always` ও `never`, tag inheritance, `serial`, `max_fail_percentage` ও `any_errors_fatal`, `rescue` ও `always` সহ block, `ansible-lint` কীভাবে install ও চালাতে হয় আর সে কীভাবে রিপোর্ট করে, তার configuration, `noqa` comment ও profile, আর তার GitHub Action সরকারি docs থেকে পড়া, প্রতিটা YAML block যে বৈধ YAML (tool দিয়ে parse করা), আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। **আসল run-এর ঠিক বার্তা দেখানো হয়নি**, কারণ কিছু চালানো হয়নি। একটা **ফেলনা server**-এ অনুশীলন করো, আর কোনো আসল পরিবর্তনের আগে নিচের `--check` ও `--limit` অভ্যাস ব্যবহার করো।

![নিরাপত্তার মই: lint, syntax-check, সংকীর্ণ করা, check, আগে একটা host, ধাপে ধাপে, দুইবার চালানো](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/08-iac/images/safety-ladder.svg)

## ১. মই

একটা পরিবর্তনকে মই বেয়ে ওঠা ভাবো, একবারে একটা ধাপ। প্রতিটা ধাপে কিছু ভুল দেখালে তুমি থামতে পারো, আর ভুলের দাম এখনো ছোট:

| ধাপ | Tool | কী ধরে |
|---|---|---|
| ১. Lint | `ansible-lint` | খারাপ অভ্যাস, যেমন একটা `command` task যা সবসময় changed জানায় |
| ২. Syntax | `--syntax-check` | YAML ও কাঠামোর ভুল |
| ৩. সংকীর্ণ করা | `--limit`, `--tags` | তুমি শুধু যে host ও task চেয়েছিলে তাই ছোঁও |
| ৪. Preview | `--check --diff` | কী বদলাত |
| ৫. আগে একটা host | `--limit one-host` | বাকিদের আগে একটা server-এ আসল run |
| ৬. ছড়ানো | `serial`, `max_fail_percentage` | একবারে ছোট batch, বেশি ব্যর্থ হলে থামা |
| ৭. দুইবার চালানো | দ্বিতীয় run | `changed=0` idempotency প্রমাণ করে |

প্রথম তিনটা আগের chapter থেকে জানো। এই chapter বাকিগুলো যোগ করে।

## ২. `--limit`: কোন host

একটা **host pattern** বলে একটা command কোন machine-কে লক্ষ্য করে। Docs থেকে:

| Pattern | মানে |
|---|---|
| `all` বা `*` | সব host |
| `host1` | একটা host |
| `host1:host2` বা `host1,host2` | কয়েকটা host |
| `webservers` | একটা group |
| `webservers:dbservers` | যেকোনো group-এর host |
| `webservers:!atlanta` | `webservers`-এর host, `atlanta`-র গুলো **বাদে** |
| `webservers:&staging` | `webservers`-এর যে host **একই সাথে** `staging`-এও আছে |

Docs বলে range ও IPv6 address-এর জন্য comma পছন্দের। `--limit` একটা playbook run-কে তার `hosts:` লাইনের চেয়েও **আরও** সংকীর্ণ করে। Docs-এর উদাহরণ:

```bash
ansible-playbook site.yml --limit datacenter2
ansible-playbook site.yml --limit @retry_hosts.txt
```

`@` সহ দ্বিতীয় রূপ একটা file থেকে host-তালিকা পড়ে, যে host ব্যর্থ হয়েছে শুধু সেগুলোতে আবার চালাতে সুবিধাজনক। বাদ দেওয়ার জন্য docs জোর দেয় **single quote**, "bash-এর ব্যাখ্যা আটকাতে":

```bash
ansible all -m ansible.builtin.ping --limit 'all:!host1'
```

ঝুঁকিপূর্ণ run-এর আগে দেখো কোন host গুলোতে লাগবে। `--list-hosts` playbook-এর জন্য docs-এ তালিকাভুক্ত যাচাই-option-এর একটা:

```bash
ansible-playbook site.yml --limit web1 --list-hosts
```

**এই chapter-এর সবচেয়ে কাজের অভ্যাস:** **আগে একটা host-এ** চালাও। `--limit web1` একটা server-এ আসল run দেয়। কাজ করলে limit বাড়াও।

## ৩. Tag: কোন task

একটা **tag** হলো task-এর একটা লেবেল। তুমি লেবেল দিয়ে task চালাতে বা বাদ দিতে পারো। Docs অনুযায়ী tag যায় একটা task, block, play বা role-এ:

```yaml
- name: Install packages
  ansible.builtin.apt:
    name: git
    state: present
  tags:
    - packages
    - web
```

একটা role-এ, `site.yml`-এ:

```yaml
roles:
  - role: caddy
    tags: [caddy, web]
  - role: php
    tags: [php, web]
```

তারপর `--tags` (**শুধু** এগুলো চালাও) বা `--skip-tags` (এগুলো **ছাড়া সব** চালাও) দিয়ে বাছো:

```bash
ansible-playbook site.yml --tags "caddy"
ansible-playbook site.yml --skip-tags "packages"
ansible-playbook site.yml --list-tags
ansible-playbook site.yml --tags "caddy" --list-tasks
```

`--list-tags` কোন tag আছে দেখায়, আর `--tags ... --list-tasks` **কিছু না চালিয়ে** কোন task চলত তার preview দেয়। দুটো flag-ই দিলে docs বলে `--skip-tags` অগ্রাধিকার পায়।

দুটো বিশেষ tag: **`always`** দেওয়া task প্রতিবার চলে (যদি না তুমি স্পষ্টভাবে skip করো), আর **`never`** দেওয়া task skip হয় যদি না তুমি `--tags` দিয়ে বিশেষভাবে চাও। `never` tag বিপজ্জনক task-এর জন্য ভালো, যেমন "test database মুছে ফেলো" task যা দুর্ঘটনায় চলুক তুমি চাও না।

**Docs-এর একটা সতর্কতা:** block, play, role বা import-এ থাকা tag ভেতরের **প্রতিটা task**-এ খাটে, আর block-এর tag তার `rescue` ও `always` অংশকেও প্রভাবিত করতে পারে (tag-নির্বাচন সেগুলো না ঢাকলে সেগুলো চলবে না)। Inheritance dynamic include (`include_role`, `include_tasks`)-এ খাটে **না**: সেখানে tag শুধু include-কেই প্রভাবিত করে। Tag একটা সুবিধা; **পুরো run-ই সত্য**, তাই পুরো playbook সামঞ্জস্যপূর্ণ প্রমাণ করতে সবসময় একটা tag-ছাড়া run দিয়ে শেষ করো।

## ৪. ধীরে ধীরে ছড়ানো: `serial`

ডিফল্টে Ansible পরের task-এ যাওয়ার আগে **সব host-এ প্রতিটা task** চালায় (docs একে `linear` strategy বলে)। একটা খারাপ পরিবর্তন একসাথে সব host-এ পৌঁছালে সবাই একসাথে ব্যর্থ হয়। **`serial`** keyword **batch-এর আকার** বদলায়: "একবারে কয়টা host Ansible manage করবে"। Docs থেকে:

```yaml
- name: Update the web servers
  hosts: web
  serial: 3
```

ছয়টা host থাকলে পুরো play আগে তিনটাতে চলে, তারপর পরের তিনটাতে। এটা শতাংশও হতে পারে (`serial: "30%"`) বা একটা **list**, যা ক্লাসিক **canary** ধাঁচ দেয়: আগে একটা host, তারপর কয়েকটা, তারপর বাকিরা:

```yaml
- name: Update the web servers, canary first
  hosts: web
  serial:
    - 1
    - 5
    - "20%"
```

"`serial` দিয়ে batch-এর আকার ঠিক করলে Ansible-এর ব্যর্থতার পরিধি পুরো host-তালিকা থেকে batch-এর আকারে নেমে আসে": এখন একটা ব্যর্থতা সবার মধ্যে ছড়ানোর বদলে rollout থামায়।

দুটো keyword **কখন থামবে** তা নিয়ন্ত্রণ করে:

- **`max_fail_percentage`** run থামায় যখন একটা নির্দিষ্ট শতাংশের বেশি host ব্যর্থ হয়। Docs: "ঠিক করা শতাংশ **ছাড়িয়ে** যেতে হবে, শুধু সমান হলে নয়।" `serial`-এর সাথে এটা **প্রতি batch-এ** খাটে। Docs-এর উদাহরণ `serial: 10`-এর সাথে `max_fail_percentage: 30`: প্রতি batch-এ দশটা server থাকলে, কোনো batch-এ তিনটার বেশি ব্যর্থ হলে play থামে।
- **`any_errors_fatal: true`** একটা ব্যর্থতার পরে **সব** host-এ play থামায়: "Ansible বর্তমান batch-এর সব host-এ মারাত্মক task শেষ করে, তারপর সব host-এ play চালানো বন্ধ করে।" আংশিক সাফল্য থামার চেয়ে খারাপ হলে এটা ব্যবহার করো, যেমন upgrade-এর আগে load balancer সরানো।

## ৫. Block: একটা ব্যর্থতার পথ

একটা **block** task জড়ো করে, আর ব্যর্থতার জন্য দুটো অংশ যোগ করে। Docs থেকে: `rescue`-এর task চলে "একটা task 'failed' অবস্থা ফেরত দেওয়ার পরে", আর `always`-এর task "আগের block-এর task-অবস্থা যা-ই হোক, চলে"। ফেরার পথসহ একটা ঝুঁকিপূর্ণ config পরিবর্তনের জন্য:

```yaml
- name: Change the Caddy configuration, with a way back
  block:
    - name: New Caddyfile is in place
      ansible.builtin.template:
        src: Caddyfile.j2
        dest: /etc/caddy/Caddyfile
        mode: '0644'
        backup: true

    - name: Caddy restarts with the new file
      ansible.builtin.service:
        name: caddy
        state: restarted
  rescue:
    - name: Stop with a clear instruction
      ansible.builtin.fail:
        msg: "Caddy did not restart. Restore the backup copy of the Caddyfile and check the new file."
  always:
    - name: Report that the attempt finished
      ansible.builtin.debug:
        msg: "Caddy change attempted on {{ inventory_hostname }}"
```

`backup: true` (Chapter 3) পুরনো file রাখে। **Rescue করা ব্যর্থতা** নিয়ে docs লক্ষ্য করো: একটা `rescue` অংশ শেষ হলে Ansible "run-এর জন্য আসল task-এর failed অবস্থা ফিরিয়ে নেয়" আর play চলতে থাকে, আর rescue করা ব্যর্থতা "`max_fail_percentage` বা `any_errors_fatal` চালু করে না", যদিও Ansible "playbook-এর পরিসংখ্যানে তবু ব্যর্থতা জানায়"। এখানে rescue ইচ্ছা করে `fail` দিয়ে শেষ, তাই host সত্যিই ব্যর্থ হয় আর rollout থামে।

## ৬. `ansible-lint`: ক্লান্ত না হওয়া এক reviewer

**Ansible Lint** তার নিজের ভাষায় "playbook, role ও collection lint করার একটা command-line tool", যার লক্ষ্য "প্রমাণিত অভ্যাস, ধাঁচ ও আচরণ উৎসাহিত করা আর সাধারণ ফাঁদ এড়ানো"। এটা **মতামতপ্রবণ (opinionated)**, আর তার rule আলাদা করে বন্ধ করা যায়। এটা Windows সমর্থন করে না।

Install করো (docs থেকে, একটা **practice machine**-এ, চাইলে virtual environment-এ):

```bash
pip3 install ansible-lint
```

(Docs `pip3 install ansible-dev-tools`-ও সুপারিশ করে, যা `ansible-core`-ও আনে, আর তোমার অন্য Python package থেকে আলাদা রাখতে pipx-এর কথা বলে।) Docs যেমন জোর দেয়, এটা **তোমার project-এর root থেকে** চালাও:

```bash
ansible-lint
ansible-lint site.yml roles/php
```

এটা প্রতিটা সন্ধান file, line, একটা **rule-এর নাম** ও একটা বার্তা দিয়ে জানায়। Docs-এর নিজের উদাহরণ সন্ধান এটা:

```text
examples/playbooks/example.yml:9: no-changed-when: Commands should not change things if nothing needs doing.
```

এটাই **ঠিক** Chapter 3 ও 5-এর শিক্ষা: একটা `command` task-এ `changed_when` লাগে। Linter এটা তোমার জন্য খুঁজে বের করে।

Docs থেকে কাজের option:

| Option | মানে |
|---|---|
| `ansible-lint -T` | tag ও তারা কোন rule ঢাকে তার তালিকা |
| `ansible-lint -t idempotency playbook.yml` | শুধু ওই tag-ওয়ালা rule চালাও |
| `ansible-lint -x formatting,metadata playbook.yml` | tag বা ID দিয়ে rule **বাদ দাও** |
| `ansible-lint --profile=basic` | একটা কড়াকড়ির **profile** বাছো (`min`, `basic`, `moderate`, `safety`, `shared`, `production`; `--list-profiles` দেখো) |
| `ansible-lint --fix` | যা পারে ঠিক করতে দাও, YAML formatting সহ। সে যা বদলায় তা **review করো** |
| `-s` / `--strict` | error-এর পাশাপাশি warning-এও non-zero exit |

পুরনো project-এ নিচু profile দিয়ে শুরু করো আর যত পরিষ্কার হয় তত ওপরে ওঠো: docs `min`-কে বর্ণনা করে "Ansible তোমার content load করতে পারে নিশ্চিত করা" হিসেবে, `basic`-কে "প্রমিত style ও formatting চালু করা" হিসেবে, আর `safety`-কে "automation-এর সামঞ্জস্য, নির্ভরযোগ্যতা ও নিরাপত্তা নিশ্চিত করা" হিসেবে।

একটা **false positive** চুপ করাতে লাইনের শেষে `# noqa: rule_id` যোগ করো (docs বলে line-ভিত্তিক rule-এর জন্য এটা লাইনের শেষে থাকতে হবে), অথবা একটা configuration file-এর `skip_list`-এ rule তালিকাভুক্ত করো (`.ansible-lint` ডিফল্টে খোঁজা হয়)। **চুপ করানো একটা সকারণ সিদ্ধান্ত হওয়া উচিত,** output নীরব করার উপায় নয়। কারণটা পাশে comment-এ লেখো।

## ৭. CI: প্রতিটা pull request যন্ত্র যাচাই করুক

ওপরের সবকিছু কেউ একটা pull request খুললে **স্বয়ংক্রিয়ভাবে** চলতে পারে (Terraform course-এর Chapter 7 Terraform-এর জন্য একই ধারণা দেখায়)। `ansible-lint` project একটা **GitHub Action** দেয়; docs-এর workflow file `.github/workflows/ansible-lint.yml`, একটা job যা `ubuntu-24.04`-এ চলে আর `ansible/ansible-lint@main` ব্যবহার করে:

```yaml
name: ansible-lint

on:
  pull_request:

jobs:
  lint:
    runs-on: ubuntu-24.04
    steps:
      - uses: actions/checkout@v4
      - uses: ansible/ansible-lint@main
```

Docs কিছু ঐচ্ছিক input দেখায় (`args`, `setup_python`, `python_version`, `working_directory`, `requirements_file`) আর বলে বেশিরভাগ ব্যবহারকারীর এগুলো লাগে না। **`@main` লক্ষ্য করো:** এটা project-এর চলমান development branch অনুসরণ করে, তাই নতুন release রাতারাতি তোমার ফল বদলাতে পারে। স্থিতিশীল pipeline-এর জন্য action-কে তোমার যাচাই করা একটা release tag বা commit-এ **pin** করো (project-এর release দেখো), আর ইচ্ছা করে update করো, Terraform-এর provider pin করার একই শৃঙ্খলা।

Ansible-এর জন্য CI-র কী করা উচিত, খরচের ক্রমে:

1. প্রতিটা pull request-এ **Lint** (ওপরে)। বিনামূল্যে, দ্রুত, কোনো server লাগে না।
2. **Syntax check** (`ansible-playbook site.yml --syntax-check`), Ansible ও তোমার role-এর ব্যবহৃত collection install করার পরে (`ansible-galaxy collection install -r requirements.yml`, Chapter 6)। তোমার playbook একটা encrypted Vault file load করলে নিশ্চিত করো CI সেটা পড়তে পারে, vault password আসছে একটা **CI secret** থেকে, repository-র file থেকে কখনো নয়।
3. একটা test server থাকলে তার বিরুদ্ধে **`--check --diff`**। এতে CI-তে একটা SSH key লাগে, তাই **শুধু test server-এ access আছে এমন একটা আলাদা key** ব্যবহার করো, CI secret হিসেবে রাখা।
4. **আসল server-এ apply,** শুধু **merge-এর পরে**, একটা **human অনুমোদনের** পিছনে, pull request থেকে কখনো নয় (Terraform-এর মতো একই নিয়ম)।

## ৮. একটা নিরাপদ run, শুরু থেকে শেষ

একটা দলে role-এর পরিবর্তনের জন্য:

```bash
ansible-lint                                                   # rung 1
ansible-playbook site.yml --syntax-check                       # rung 2
ansible-playbook site.yml --limit web1 --list-hosts            # rung 3: am I touching only web1?
ansible-playbook site.yml --limit web1 --check --diff          # rung 4: preview on one host
ansible-playbook site.yml --limit web1                         # rung 5: real run on one host
ansible-playbook site.yml                                      # rung 6: the rest, with serial in the play
ansible-playbook site.yml                                      # rung 7: second run, expect changed=0
```

তোমার play-তে Vault লাগলে `--ask-vault-pass` (বা Git-এর বাইরে রাখা একটা vault password file) যোগ করো। Chapter 3 থেকে মনে রেখো **`--check` একটা preview, নিশ্চয়তা নয়**: কিছু module তাদের ফল আগে বলতে পারে না।

## ৯. অভ্যাস

1. **আগে lint, তারপর syntax-check,** প্রতিবার, আদর্শভাবে CI-তে।
2. **আগে একটা host:** বাকিদের আগে একটা server-এ `--limit`।
3. **`serial` দিয়ে batch-এ ছড়াও,** আর `max_fail_percentage` দাও যাতে একটা খারাপ পরিবর্তন নিজেই থামে।
4. **বিপজ্জনক task-এ `never` ব্যবহার করো** আর বাদ পড়া চলবে না এমন task-এ `always`।
5. **Tag দিয়ে চালানো run শেষ করো একটা tag-ছাড়া run দিয়ে।**
6. **ঝুঁকিপূর্ণ পরিবর্তনে ব্যর্থতার পথ দাও** `block` / `rescue` ও `backup: true` দিয়ে।
7. **Lint rule ইচ্ছা করে চুপ করাও,** কারণ লেখা একটা comment সহ।
8. **CI যা চালায় তা pin করো,** আর deploy credential pull request থেকে দূরে রাখো।

## ১০. নিজে যাচাই করো

<details>
<summary><strong>Q1. `ansible-playbook site.yml --limit 'web:!web3'` কী করে?</strong></summary>

এটা `web` group-এর host-এ playbook চালায়, `web3` বাদে। বাদ দেওয়ার জন্য single quote লাগে যাতে shell `!` ব্যাখ্যা না করে।
</details>

<details>
<summary><strong>Q2. `--tags caddy`-র জন্য কোন task চলত তা না চালিয়ে কীভাবে দেখবে?</strong></summary>

`--list-tasks` যোগ করো: `ansible-playbook site.yml --tags "caddy" --list-tasks`।
</details>

<details>
<summary><strong>Q3. বিশেষ tag `always` ও `never` কী করে?</strong></summary>

`always` task প্রতিবার চলে যদি না স্পষ্টভাবে skip করা হয়। `never` task skip হয় যদি না তুমি `--tags` দিয়ে বিশেষভাবে চাও।
</details>

<details>
<summary><strong>Q4. `serial: [1, 5, "20%"]` তোমাকে কী দেয়?</strong></summary>

একটা canary rollout: play আগে একটা host-এ চলে, তারপর পাঁচটায়, তারপর ২০ শতাংশ batch-এ, তাই ব্যর্থতা যে batch-এ ঘটে সেখানেই থামে।
</details>

<details>
<summary><strong>Q5. `serial: 10` ও `max_fail_percentage: 30` থাকলে play কখন থামে?</strong></summary>

যখন একটা batch-এ তিনটার বেশি host ব্যর্থ হয়। শতাংশ ছাড়িয়ে যেতে হবে, শুধু সমান হলে নয়।
</details>

<details>
<summary><strong>Q6. `rescue` অংশ যে ব্যর্থতা সামলায় তার কী হয়?</strong></summary>

আসল task-এর failed অবস্থা ফিরিয়ে নেওয়া হয় আর play চলতে থাকে, আর এটা `max_fail_percentage` বা `any_errors_fatal` চালু করে না, কিন্তু Ansible এখনো playbook-এর পরিসংখ্যানে ব্যর্থতা জানায়।
</details>

<details>
<summary><strong>Q7. `no-changed-when` lint সন্ধানের মানে কী, আর কীভাবে ঠিক করবে?</strong></summary>

একটা `command` বা `shell` task সবসময় changed জানাবে। `changed_when` যোগ করো (শুধু-পড়া task-এর জন্য যেমন `changed_when: false`), Chapter 3 ও 5-এ শেখানো মতো।
</details>

<details>
<summary><strong>Q8. `@main`-এর বদলে `ansible-lint` GitHub Action কেন pin করবে?</strong></summary>

`@main` একটা চলমান branch অনুসরণ করে, তাই তোমার code না বদলেও নতুন release তোমার CI ফল বদলাতে পারে। যাচাই করা tag বা commit-এ pin করলে তুমি update বেছে না নেওয়া পর্যন্ত pipeline স্থিতিশীল থাকে।
</details>

## ১১. বিনামূল্যের resource

- Ansible docs: [Patterns: targeting hosts and groups](https://docs.ansible.com/ansible/latest/inventory_guide/intro_patterns.html), [Tags](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_tags.html)
- Ansible docs: [Controlling playbook execution: strategies and more](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_strategies.html), [Blocks](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_blocks.html), [Error handling](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_error_handling.html)
- Ansible Lint: [About](https://docs.ansible.com/projects/lint/), [Installing](https://docs.ansible.com/projects/lint/installing/), [Usage](https://docs.ansible.com/projects/lint/usage/)

---

**পরের chapter:** Chapter 8 — Real project: Ansible দিয়ে বানানো একটা Laravel server *(শীঘ্রই আসছে)*
