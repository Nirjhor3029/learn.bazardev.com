# Chapter 5 — Ansible variable, fact, condition ও `register`

> **Course:** Infrastructure as Code & Ansible · **Chapter:** 5 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/iac/05-ansible-variables-facts-and-conditions)

এ পর্যন্ত তোমার playbook প্রতিটা machine-এ একই কাজ করেছে। আসল server আলাদা: একটা Ubuntu চালায়, আরেকটা Debian; একটায় একটা tool আগে থেকেই আছে, আরেকটায় নেই; একটা command এক জায়গায় সফল হয় আর অন্য জায়গায় ব্যর্থ। এই chapter একটা playbook-কে **কাজের আগে দেখে নিতে** শেখায়। এতে চারটা ধারণা মেলে: **variable** (তোমার বাছা মান), **fact** (Ansible machine সম্পর্কে যা আবিষ্কার করে), **condition** (`when`), আর **`register`** (এক task-এর ফল পরের task-এর জন্য ধরে রাখা)।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Ansible **install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের playbook গুলো সরকারি Ansible documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: fact কীভাবে সংগ্রহ ও পড়া হয় (`ansible_facts`, `setup` module, `gather_facts`), `when` clause ও তার রূপ, `register`, `debug` ও `set_fact` module, `ignore_errors`, `failed_when` ও `changed_when`, আর `group_vars` ও `host_vars` সরকারি docs থেকে পড়া, প্রতিটা YAML block যে বৈধ YAML (tool দিয়ে parse করা), আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। **আসল run-এর ঠিক output দেখানো হয়নি**, কারণ কিছু চালানো হয়নি; তুমি নিজের practice machine-এ তা দেখবে। আসল কিছুতে একটা playbook-এর ওপর ভরসার আগে সেখানেই, `--check` দিয়ে শুরু করো।

![Variable, fact ও registered ফল একটা when condition-কে খাওয়ায়, যা ঠিক করে task চলবে কি না](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/08-iac/images/vars-facts-when-register.svg)

## ১. তথ্য কোথা থেকে আসে

একটা task তিন ধরনের তথ্য ব্যবহার করতে পারে:

| ধরন | কে দেয় | উদাহরণ |
|---|---|---|
| **Variable** | **তুমি**: play, file, inventory বা command line-এ | `app_dir: /var/www/myapp` |
| **Fact** | **Ansible আবিষ্কার করে** machine দেখে | operating system, hostname |
| **Registered ফল** | একই run-এর **আগের একটা task** | একটা command-এর output |

একটা `when` condition এই তিনটার যেকোনোটা পরীক্ষা করে ঠিক করতে পারে task চলবে কি না।

## ২. Variable, সংক্ষিপ্ত স্মরণ আর দুটো নতুন জায়গা

Chapter 3 ও 4 থেকে variable তুমি জানো: play-র `vars:`, `vars_files:`, role-এর `defaults/`, আর command line-এর `-e` (সর্বোচ্চ অগ্রাধিকার)। একাধিক server থাকলে আরও দুটো জায়গা গুরুত্বপূর্ণ:

- **`group_vars`**: host-এর একটা **group**-এর মান। Docs: "তুমি সাধারণত group variable তোমার inventory file-এর পাশে রাখো।" এগুলোর নাম group-এর নামে, যেমন `web` group-এর জন্য `group_vars/web`, আর `group_vars/all` পুরো site-এর ডিফল্ট রাখে। (Docs-এর উদাহরণ file-গুলোর নাম extension ছাড়া। `.yml` extension-ও গৃহীত হয়; docs দেখো।)
- **`host_vars`**: **একটা** host-এর মান, host-এর নামে একটা file-এ, যেমন `host_vars/web1`। Docs-এর উদাহরণ একটা host-এর জন্য `ntp_server: override.example.com` সেট করে।

একটা কাঠামো:

```text
ansible/
├── inventory.ini
├── group_vars/
│   └── web.yml          # values for every host in the "web" group
├── host_vars/
│   └── web1.yml         # values for web1 only (these override group values)
└── site.yml
```

`group_vars/web.yml`:

```yaml
app_dir: /var/www/myapp
php_memory_limit: 256M
```

`host_vars/web1.yml`:

```yaml
php_memory_limit: 512M
```

এখন `web1` `512M` ব্যবহার করে আর বাকি প্রতিটা `web` host `256M`, **একই playbook-এ**। Docs অনেক অগ্রাধিকার-স্তর তালিকাভুক্ত করে, আর নীতি Chapter 3-এর সেটাই: মান যত বেশি নির্দিষ্ট আর "ঘটনাস্থলের কাছের", তত শক্তিশালী, `-e` সবার ওপরে। Docs অনুযায়ী এই দুটো directory Ansible-এর সাথে আসা ডিফল্ট vars plugin load করে, তাই তুমি playbook-এ এদের উল্লেখ করো না।

## ৩. Fact: Ansible প্রতিটা machine সম্পর্কে যা শেখে

প্রতিটা play-র শুরুতে Ansible একটা লুকানো প্রথম task চালায়, **Gathering Facts** (Chapter 3-এর output-এ দেখেছ)। Docs: "ডিফল্টভাবে Ansible প্রতিটা play-র শুরুতে fact সংগ্রহ করে।" Fact হলো "তোমার remote system সম্পর্কিত ডেটা, operating system ও IP address সহ", আর তুমি এগুলো **`ansible_facts`** dictionary থেকে পড়ো:

```yaml
- name: Show which system this is
  ansible.builtin.debug:
    msg: "{{ ansible_facts['nodename'] }} runs {{ ansible_facts['distribution'] }}"
```

যে fact গুলো তুমি প্রতিনিয়ত ব্যবহার করবে (সবই docs-এর উদাহরণে আছে): `ansible_facts['os_family']` (যেমন `Debian`), `ansible_facts['distribution']` (যেমন `Ubuntu`), `ansible_facts['distribution_major_version']` ও `ansible_facts['nodename']`।

একটা machine-এর **সব fact দেখতে** Chapter 2-এর `setup` module ব্যবহার করো:

```bash
ansible web -m ansible.builtin.setup
```

অথবা playbook-এ `var: ansible_facts` সহ একটা `debug` task যোগ করো। Output একবার পড়ো, যাতে জানো কী পাওয়া যায়।

Docs থেকে দুটো খুঁটিনাটি। প্রথমত, ডিফল্টে কিছু fact **একই সাথে** `ansible_` prefix সহ top-level variable হিসেবেও পাওয়া যায়, আর "setup module-এর output-এ দেখানো *ansible_* prefix এই variable গুলোকে ডাকার সময় ব্যবহার হয় না" (তাই `ansible_facts['os_family']` লেখো, যে রূপ এই course ব্যবহার করে আর যা ওই setting-এর ওপর নির্ভর করে না; `INJECT_FACTS_AS_VARS` দিয়ে injection বন্ধ করা যায়)। দ্বিতীয়ত, fact সংগ্রহে সময় লাগে। কোনো play-তে একটাও না লাগলে বন্ধ করো:

```yaml
- hosts: web
  gather_facts: false
```

Docs বলে অনেক machine manage করলে এটা scalability-তে সাহায্য করতে পারে।

## ৪. Condition: `when`

`when` clause সহ একটা task **শুধু condition সত্য হলে চলে**। Docs একে বর্ণনা করে "double curly brace ছাড়া একটা কাঁচা Jinja2 expression" হিসেবে: `when`-এর ভেতরে তুমি variable-এর নাম সরাসরি লেখো, `{{ }}` **ছাড়া**:

```yaml
- name: Update the package index on Debian-family systems
  ansible.builtin.apt:
    update_cache: true
  when: ansible_facts['os_family'] == "Debian"
```

`and` ও `or` দিয়ে condition জোড়ো, গোষ্ঠী করতে parenthesis দিয়ে:

```yaml
when: (ansible_facts['distribution'] == "Ubuntu" and ansible_facts['distribution_major_version'] == "24") or
      (ansible_facts['distribution'] == "Debian" and ansible_facts['distribution_major_version'] == "12")
```

যখন **সব** condition সত্য হতে হয়, একটা **list** পড়তে ভালো, আর এটা `and` বোঝায়:

```yaml
when:
  - ansible_facts['distribution'] == "Ubuntu"
  - ansible_facts['distribution_major_version'] == "24"
```

### Loop সহ `when`

"একটা loop-এর সাথে `when` মেশালে Ansible প্রতিটা আইটেমের জন্য আলাদাভাবে condition প্রক্রিয়া করে।" Docs-এর উদাহরণ শুধু ৫-এর বেশি আইটেমে command চালায়:

```yaml
- name: Run with items greater than 5
  ansible.builtin.command: echo {{ item }}
  loop: [ 0, 2, 4, 6, 8, 10 ]
  when: item > 5
```

### Variable সেট আছে কি? `is defined`

Docs থেকে: "একটা দরকারি variable সেট না থাকলে তুমি Jinja2-এর *defined* test দিয়ে বাদ দিতে বা ব্যর্থ করতে পারো।"

```yaml
- name: Run only if "foo" is defined
  ansible.builtin.debug:
    msg: "foo is {{ foo }}"
  when: foo is defined

- name: Stop with a clear message if "bar" is missing
  ansible.builtin.fail:
    msg: "Bailing out. This play requires 'bar'"
  when: bar is undefined
```

`fail` module ওই host-এর জন্য play-কে তোমার বার্তা দিয়ে থামায়। এটা পরের অস্পষ্ট "variable undefined" error-কে এখনকার একটা পরিষ্কার নির্দেশনা বানায় (Terraform-এর `validation`-এর একই ধারণা)।

### একটা ফাঁদ

Docs সতর্ক করে একটা **string**-কে একটা **integer**-এর সাথে তুলনা করা অপ্রত্যাশিত ফলের সাধারণ কারণ: `"6" == 6` তুমি যা চাও তা নয়। `distribution_major_version`-এর মতো fact **string**, তাই ওপরের উদাহরণের মতো quote দিয়ে তুলনা করো।

## ৫. `register`: একটা task-এর ফল ধরে রাখা

`register` একটা task যা ফেরত দিয়েছে তা একটা variable-এ save করে যা পরে ব্যবহার করা যায়। Docs-এর রূপ:

```yaml
- name: Read the Git version
  ansible.builtin.command: git --version
  register: git_version
  changed_when: false
```

`git_version` এখন ফেরত-মানের একটা dictionary ধরে। `command`-এর জন্য এতে **return code** `rc` আর output `stdout` থাকে। প্রতিটা module-এর documentation-এ একটা **RETURN** অংশ আছে যা তার মান তালিকাভুক্ত করে। কী পেলে দেখতে `debug` দিয়ে print করো:

```yaml
- name: Show what was registered
  ansible.builtin.debug:
    var: git_version
```

`debug` docs থেকে দুটো নিয়ম: **`var:`** দাও শুধু নাম দিয়ে, **brace ছাড়া** (এটা "ইতিমধ্যেই Jinja2 context-এ চলে আর অন্তর্নিহিত `{{ }}` মোড়ক আছে"), আর text-এর জন্য **`msg:`** ব্যবহার করো, যেখানে brace লাগে। `verbosity: 2` একটা debug task-কে শুধু `-vv` বা তার বেশি দিয়ে চালালে দেখায়। আরও, playbook **`-v`** দিয়ে চালালে task গুলো চলার সময় তাদের মান দেখায়।

তারপর ফলটা একটা `when`-এ ব্যবহার করো:

```yaml
- name: Only continue if Git is installed
  ansible.builtin.debug:
    msg: "Found {{ git_version.stdout }}"
  when: git_version.rc == 0
```

তুমি registered task-এর **অবস্থাও** পরীক্ষা করতে পারো। Docs থেকে: `when: result is failed` ও `when: result is succeeded`। যে host-এ একটা task **skip** হয়েছে সেখানে "undefined" পরীক্ষার বদলে `is skipped` পরীক্ষা করো।

## ৬. `command`-কে সৎ করা: `changed_when` ও `failed_when`

Chapter 3 সতর্ক করেছিল `command` ও `shell` সবসময় `changed` জানায়। যে task শুধু কিছু **পড়ে** তার সেটা বলা উচিত। Docs থেকে, `changed_when` ঠিক করে "Ansible-এর পরিসংখ্যানে একটা পরিবর্তন জানানো হবে কি না আর handler চালু হবে কি না":

```yaml
- name: This will never report 'changed' status
  ansible.builtin.shell: wall 'beep'
  changed_when: False
```

এজন্যই ওপরের `git --version` task-এ `changed_when: false` আছে: version পড়া কিছু বদলায় না, আর দ্বিতীয় run `changed=0`-তে থাকা উচিত।

কখনো একটা command-এর নিজের exit code সফলতার সঠিক পরীক্ষা নয়। `failed_when` তোমাকে নিজে ব্যর্থতা সংজ্ঞায়িত করতে দেয়:

```yaml
- name: Fail task when either condition is met
  ansible.builtin.command: /usr/bin/example-command
  register: command_result
  failed_when: command_result.rc != 0 or 'ERROR' in command_result.stdout
```

Docs থেকে নোট: একটা list-এ একাধিক `failed_when` condition একটা অন্তর্নিহিত `and` দিয়ে জোড়া হয়, তাই `or` লেখো **একটা** string-এর ভেতরে, ওপরের মতো। আর `changed_when` একই ধরনের expression নেয়, যেমন `changed_when: "bass_result.rc != 2"`।

### `ignore_errors`: সাবধানে ব্যবহার করো

```yaml
- name: Do not count this as a failure
  ansible.builtin.command: /bin/false
  ignore_errors: true
```

Docs বলে এটা "শুধু তখনই কাজ করে যখন task চলতে পারে আর 'failed' মান ফেরত দেয়"। এটা undefined variable, connection ব্যর্থতা বা syntax error **ঢাকে না**। এটা আসল সমস্যা লুকায়, তাই একটা সুনির্দিষ্ট `failed_when` পছন্দ করো আর `ignore_errors` শুধু তখন ব্যবহার করো যখন ব্যর্থতা সত্যিই গ্রহণযোগ্য।

## ৭. `set_fact`: চলার সময় একটা নতুন variable বানাও

`set_fact` run-এর সময় **বর্তমান host-এ** একটা variable বানায়, যেমন একটা হিসাব থেকে:

```yaml
- name: Decide the web root
  ansible.builtin.set_fact:
    web_root: "{{ app_dir }}/public"
```

Docs: variable গুলো "যে host-এ সেট হয়েছে তার মাধ্যমে ansible-playbook run-এর পরের play-গুলোতে পাওয়া যাবে"। দুটো সতর্কতা: এভাবে সেট করা fact **প্রমিত অগ্রাধিকার নিয়ম** মানে, তাই উচ্চ অগ্রাধিকারের অন্য ধরনের variable এগুলোকে ছাপিয়ে যেতে পারে, আর `cacheable: true` এগুলো run-এর মধ্যে শুধু তখনই save করে যখন **fact caching আগে থেকেই চালু**। পারলে `vars:`-এ সাধারণ variable পছন্দ করো; যখন মানটা চলার সময় পাওয়া কিছুর ওপর নির্ভর করে তখন `set_fact` ব্যবহার করো।

## ৮. যে playbook কাজের আগে দেখে নেয়

সব টুকরো একসাথে। এটা অসমর্থিত system-এ থামে, শুধু Debian পরিবারে package install করে, নিরাপদে একটা version পড়ে, আর একটা পরিষ্কার রিপোর্ট print করে:

```yaml
- name: Adapt to the machine
  hosts: web
  become: true

  tasks:
    - name: Stop on a system this playbook does not support
      ansible.builtin.fail:
        msg: "Debian-family systems only, but this is {{ ansible_facts['distribution'] }}"
      when: ansible_facts['os_family'] != "Debian"

    - name: Base packages are installed
      ansible.builtin.apt:
        name:
          - git
          - unzip
        state: present
        update_cache: true
        cache_valid_time: 3600
      when: ansible_facts['os_family'] == "Debian"

    - name: Read the Git version
      ansible.builtin.command: git --version
      register: git_version
      changed_when: false

    - name: Decide the web root
      ansible.builtin.set_fact:
        web_root: "{{ app_dir }}/public"

    - name: Report
      ansible.builtin.debug:
        msg:
          - "{{ ansible_facts['nodename'] }} runs {{ ansible_facts['distribution'] }}"
          - "{{ git_version.stdout }}"
          - "web root will be {{ web_root }}"
```

`app_dir` আসে `group_vars/web.yml` থেকে (২ নম্বর অংশ)। Chapter 3-এর নিরাপদ উপায়ে চালাও: `--syntax-check`, তারপর `--check --diff`, তারপর apply, তারপর `changed=0`-র জন্য আবার। `command`-এর জন্য check mode নিয়ে docs-এর একটা নোট: `creates` বা `removes` ছাড়া একটা `command` task check mode-এ **skip** হয়, তাই যে task তার ফল register করে সেখানে আসল ফল থাকবে না। এটাই আরেকটা কারণ `--check`-কে preview ধরার, নিশ্চয়তা নয়।

## ৯. অভ্যাস

1. **কাজের আগে দেখো:** প্রতিটা machine একই ধরে না নিয়ে fact ও `when` ব্যবহার করো।
2. **`when`-এ brace নেই;** `debug` brace ছাড়া `var:` আর brace সহ `msg:` ব্যবহার করে।
3. **String-কে string-এর সাথে তুলনা করো।** `distribution_major_version`-এর মতো fact string।
4. **পড়াকে সৎ করো:** শুধু পড়া task-এ `changed_when: false`।
5. **`ignore_errors`-এর চেয়ে `failed_when` পছন্দ করো।** ব্যর্থতা লুকালে সমস্যা লুকায়।
6. **প্রতি-group ও প্রতি-host-এর মান `group_vars` ও `host_vars`-এ রাখো,** playbook-এ `if`-এর শৃঙ্খলে নয়।
7. **আগেভাগে পরিষ্কার বার্তা দিয়ে ব্যর্থ হও** (`fail`-এর সাথে `when: ... is undefined`)।
8. **শেখার সময় `debug` দিয়ে print করো, তারপর গোলমেলে task সরাও।**

## ১০. নিজে যাচাই করো

<details>
<summary><strong>Q1. একটা task যে তিন ধরনের তথ্য ব্যবহার করতে পারে তা কী কী?</strong></summary>

তোমার সেট করা variable, machine সম্পর্কে Ansible-এর আবিষ্কার করা fact, আর আগের task-এর registered ফল।
</details>

<details>
<summary><strong>Q2. `php_memory_limit` `group_vars/web.yml`-এ 256M আর `host_vars/web1.yml`-এ 512M। `web1` কী পায়?</strong></summary>

512M। Host-নির্দিষ্ট মান group-এর মানের চেয়ে বেশি নির্দিষ্ট, তাই সেটাই জেতে।
</details>

<details>
<summary><strong>Q3. একটা machine-এর operating-system পরিবার কীভাবে পড়বে, আর সব fact কীভাবে দেখবে?</strong></summary>

`ansible_facts['os_family']`। সব fact দেখতে `ansible <host> -m ansible.builtin.setup` বা `var: ansible_facts` সহ একটা `debug` task ব্যবহার করো।
</details>

<details>
<summary><strong>Q4. `when: ansible_facts['os_family'] == "Debian"`-এ কেন `{{ }}` নেই?</strong></summary>

`when` clause একটা কাঁচা Jinja2 expression, তাই variable-এর নাম double curly brace ছাড়া সরাসরি লেখা হয়।
</details>

<details>
<summary><strong>Q5. `register: git_version` কী করে, আর সে কী ধরেছে তা কীভাবে দেখবে?</strong></summary>

এটা task-এর ফেরত-মান (`command`-এর জন্য `rc` ও `stdout` সহ) `git_version` variable-এ save করে। `var: git_version` সহ `debug` দিয়ে print করো, বা playbook `-v` দিয়ে চালাও।
</details>

<details>
<summary><strong>Q6. `git --version` চালানো task-এ কেন `changed_when: false` দেবে?</strong></summary>

নইলে `command` সবসময় changed জানায়। Version পড়া কিছু বদলায় না, তাই not changed চিহ্নিত করলে দ্বিতীয় run `changed=0`-তে থাকে।
</details>

<details>
<summary><strong>Q7. সবখানে `ignore_errors: true` ব্যবহারের সমস্যা কী?</strong></summary>

এটা আসল ব্যর্থতা লুকায়, আর undefined variable, connection ব্যর্থতা বা syntax error-ও ঢাকে না। একটা সুনির্দিষ্ট `failed_when` ভালো।
</details>

<details>
<summary><strong>Q8. একটা দরকারি variable না থাকলে পরিষ্কার বার্তা দিয়ে play কীভাবে আগে থামাবে?</strong></summary>

`msg` সহ `fail` module আর `when: variable_name is undefined` ব্যবহার করো।
</details>

## ১১. বিনামূল্যের resource

- Ansible docs: [Discovering variables: facts and magic variables](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_vars_facts.html)
- Ansible docs: [Conditionals](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_conditionals.html) ও [Using variables](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_variables.html)
- Ansible docs: [Error handling in playbooks](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_error_handling.html)
- Module page: [debug](https://docs.ansible.com/ansible/latest/collections/ansible/builtin/debug_module.html) ও [set_fact](https://docs.ansible.com/ansible/latest/collections/ansible/builtin/set_fact_module.html)

---

**পরের chapter:** [Chapter 6 — Laravel stack-এর জন্য Ansible role: PHP, Caddy, MySQL](06-ansible-laravel-stack-roles.md)
