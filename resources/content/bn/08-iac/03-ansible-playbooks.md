# Chapter 3 — Ansible Playbook: Module, Handler ও Idempotency

> **Course:** Infrastructure as Code (Terraform & Ansible) · **Chapter:** 3 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/iac/03-ansible-playbooks)

Chapter 2-এ তুমি এক লাইনের **ad-hoc** command চালিয়েছ। সেগুলো দ্রুত, কিন্তু কিছুই সংরক্ষিত হয় না, তাই আবার চালানো, review করা বা Git-এ রাখা যায় না। **Playbook** এটা ঠিক করে। এটা একটা YAML file, যাতে তুমি যা চাও তা ক্রমে লেখা থাকে, আর Ansible machine-গুলোকে তার সাথে মেলায়। এই file-ই শেষ পর্যন্ত তোমাকে "ওই server-টা আবার বানাও" বলতে দেয় আর সত্যিই তা করতে দেয়।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Ansible **install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের playbook ও command গুলো সরকারি Ansible documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: playbook-এর গঠন, `ansible-playbook`-এর flag (`--check`, `--diff`, `--syntax-check`, `--list-tasks`, `--verbose`), module-এর parameter (`apt`, `file`, `copy`, `command`), handler-এর নিয়ম (`notify`, play-এর শেষে একবার চলা, শুধু বদল হলে, `flush_handlers`), variable-এর নিয়ম ও `become` সরকারি docs থেকে পড়া, নিচের প্রতিটা YAML block যে বৈধ YAML (tool দিয়ে parse করা), আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। **Output-এর block গুলো আকারে উদাহরণ**, docs-এর নমুনা থেকে নেওয়া, আসল চালানো থেকে নয়, আর তোমার সংখ্যা আলাদা হবে। কোনো playbook **তোমার** server-এ কাজ করে কি না তা আগে একটা practice machine-এ প্রমাণ করো, ৮ নম্বর অংশের নিরাপদ ধাপ ব্যবহার করে।

![Playbook-এর গঠন: একটা play, তার task, আর একটা handler যা শুধু কিছু বদলালে চলে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/08-iac/images/playbook-anatomy.svg)

## ১. Ad-hoc থেকে playbook

| | Ad-hoc command | Playbook |
|---|---|---|
| **কোথায় থাকে** | Terminal-এ টাইপ করা | Git-এ রাখা একটা YAML file |
| **ধাপ** | একটা module, একবার | অনেক task, ক্রমে |
| **পুনর্ব্যবহারযোগ্য?** | না (docs ad-hoc command-কে "পুনর্ব্যবহারযোগ্য নয়" বলে) | হ্যাঁ, যখন খুশি চালাও |
| **Review করা যায়?** | না | হ্যাঁ, pull request দিয়ে |

Docs-এর ভাষায় playbook হলো **play-এর একটা ক্রমিক তালিকা**। প্রতিটা play "playbook-এর সামগ্রিক লক্ষ্যের একটা অংশ সম্পন্ন করে, এক বা একাধিক task চালিয়ে"। Play ও task **উপর থেকে নিচে** চলে।

## ২. তোমার প্রথম playbook

সরকারি quick-start playbook ছোট। ঠিকমতো indentation দিয়ে:

```yaml
- name: My first play
  hosts: myhosts
  tasks:
    - name: Ping my hosts
      ansible.builtin.ping:

    - name: Print message
      ansible.builtin.debug:
        msg: Hello world
```

এভাবে চালাও:

```bash
ansible-playbook -i inventory.ini playbook.yaml
```

গঠনটা পড়ো, কারণ প্রতিটা playbook-এ একই চারটা স্তর থাকে:

| স্তর | File-এ | মানে |
|---|---|---|
| **Play** | `- name: My first play` আইটেম | একটা machine-সেটের জন্য একটা কাজের block |
| **hosts** | `hosts: myhosts` | কোন inventory host বা group এই play-এর লক্ষ্য (Chapter 2-এর মতো *pattern*) |
| **tasks** | `tasks:` | কাজের ক্রমিক তালিকা |
| **Task** | `tasks`-এর নিচে প্রতিটা `- name: ...` | একটা কাজ: একটা `name` (মানুষের জন্য লেবেল) আর **একটা module** ও তার option |

প্রতিটা task-এর একটা `name` সবসময় দাও। রাত ২টায় কিছু ব্যর্থ হলে output-এ এটাই তুমি পড়বে।

### Output পড়া

Docs-এর নমুনা output-এর আকার:

```
PLAY [My first play] ****

TASK [Gathering Facts] ****
ok: [192.0.2.50]

TASK [Ping my hosts] ****
ok: [192.0.2.50]

TASK [Print message] ****
ok: [192.0.2.50] => {
    "msg": "Hello world"
}

PLAY RECAP ****
192.0.2.50: ok=3    changed=0    unreachable=0    failed=0    skipped=0    rescued=0    ignored=0
```

`Gathering Facts` একটা স্বয়ংক্রিয় প্রথম task, যা প্রতিটা machine-এর তথ্য সংগ্রহ করে (Chapter 2-এর `setup` module-এর একই fact)। শেষের **PLAY RECAP** প্রতিটা host-এর এক লাইনের সারাংশ। আগে এটা পড়তে শেখো:

| শব্দ | মানে |
|---|---|
| `ok` | Task চলেছে আর machine আগে থেকেই ঠিক ছিল (বা একটা ফল দিয়েছে) |
| `changed` | Task machine-এ কিছু **বদলেছে** |
| `failed` / `unreachable` | কিছু ভুল। ওপরের লাল লেখা পড়ো |
| `skipped` | একটা শর্ত বলেছে "এখানে দরকার নেই" |

## ৩. একটা আসল playbook: web server তৈরি করা

এখানে এমন একটা playbook যা আসল, কাজের setup করে। এটাকে রেসিপির মতো পড়ো। ধরে নেওয়া হয়েছে Caddy আগে থেকে install করা (Cloud course-এর মতো) আর playbook-এর পাশে একটা `files/Caddyfile` আছে।

```yaml
- name: Prepare web servers
  hosts: web
  become: true
  vars:
    app_dir: /var/www/myapp
    packages:
      - git
      - unzip

  tasks:
    - name: Base packages are installed
      ansible.builtin.apt:
        name: "{{ packages }}"
        state: present
        update_cache: true
        cache_valid_time: 3600

    - name: App directory exists
      ansible.builtin.file:
        path: "{{ app_dir }}"
        state: directory
        mode: '0755'

    - name: Web server config is in place
      ansible.builtin.copy:
        src: files/Caddyfile
        dest: /etc/caddy/Caddyfile
        mode: '0644'
        backup: true
      notify: Restart caddy

    - name: Caddy is running
      ansible.builtin.service:
        name: caddy
        state: started

  handlers:
    - name: Restart caddy
      ansible.builtin.service:
        name: caddy
        state: restarted
```

Task-এর নামগুলো জোরে পড়ো: "Base package-গুলো install **করা আছে**। App directory **আছে**। Caddy **চলছে**।" প্রতিটা নাম একটা **ফল** বলে, কাজ নয়। এটাই Chapter 1-এর declarative ধরন।

### যে module-গুলো ব্যবহার করলে

| Module | কী করে | ওপরে ব্যবহৃত parameter (docs থেকে) |
|---|---|---|
| `ansible.builtin.apt` | Debian ও Ubuntu-তে package manage করে | `name` package-এর **তালিকা** নেয়। `state: present` ডিফল্ট। `update_cache` `apt-get update`-এর সমতুল্য চালায়। `cache_valid_time` (সেকেন্ডে) cache তার চেয়ে নতুন হলে update বাদ দেয়। |
| `ansible.builtin.file` | File, directory ও link manage করে | `path`, `state` (`directory`, `absent`, `touch`, ...), `mode`, `owner`, `group`। |
| `ansible.builtin.copy` | **Control node** থেকে machine-এ file copy করে | `src`, `dest`, `mode`, `owner`, `group`, `backup`। Content আলাদা হলেই কেবল remote file বদলায়। |
| `ansible.builtin.service` | Service চালু, বন্ধ বা restart করে | `name`, `state` (`started`, `stopped`, `restarted`)। |

**`mode` quote করো।** Docs পরামর্শ দেয় octal সংখ্যা, যেমন `'0644'`, quote করতে, যাতে ফল ধারাবাহিক হয়। Quote ছাড়া সংখ্যাটা ভুলভাবে পড়া হতে পারে।

**`copy`-তে `backup: true`** পুরনো file বদলানোর আগে সময়ের ছাপসহ একটা কপি রাখে। চালু config বদলানোর সময় সস্তা নিরাপত্তা-জাল।

## ৪. Idempotency, ঠিকমতো দেখা

ওপরের playbook একটা নতুন server-এ চালাও। বেশিরভাগ task `changed` দেখাবে: package install হয়েছে, directory তৈরি হয়েছে, config copy হয়েছে। এখনই আবার চালাও। কিছু করার নেই, তাই recap-এ `changed=0` আসে। এটাই Chapter 1-এর **idempotency**, আর এটাই দিয়ে বোঝা যায় playbook সুস্থ কি না:

```
# first run on a fresh server (illustrative shape)
192.0.2.50: ok=5    changed=4    ...

# second run, nothing left to do
192.0.2.50: ok=5    changed=0    ...
```

Docs কারণটা বলে: বেশিরভাগ module "চেক করে কাঙ্ক্ষিত অবস্থা আগে থেকেই আছে কি না, থাকলে কিছুই বদলায় না", তাই "task আবার করলে শেষ অবস্থা বদলায় না"। তারা সতর্কও করে যে **"সব playbook ও সব module এভাবে আচরণ করে না"**, তাই production-এ বারবার চালানোর ওপর ভরসার আগে পরীক্ষা করো।

### ফাঁদ: `command` ও `shell`

`ansible.builtin.command` ও `shell` module একটা কাঁচা command চালায়। Command-টা চালানো *দরকার* ছিল কি না Ansible বলতে পারে না, তাই ডিফল্টভাবে প্রতিবারই চালায় আর `changed` জানায়। এতে `changed=0` পরীক্ষা ভেঙে যায়। দুটো সমাধান:

1. **আসল module ব্যবহার করো** যেখানেই একটা আছে (`command: apt install`-এর বদলে `apt`)। প্রায় সবসময় এটাই সেরা উত্তর।
2. সত্যিই `command` লাগলে `creates` বা `removes` দাও। Docs অনুযায়ী `creates` থাকলে "মিলে যাওয়া file আগে থেকে থাকলে এই ধাপ চলবে না":

```yaml
- name: Create the database once
  ansible.builtin.command: /usr/bin/make_database.sh db_user db_name creates=/path/to/database
```

Docs আরও বলে `command` shell দিয়ে **চলে না**, তাই `*`, `<`, `>`, `|`, `;` ও `&` সেখানে কাজ করবে না। এগুলোর জন্য `ansible.builtin.shell` ব্যবহার করো।

## ৫. Handler: কিছু বদলালেই শুধু করো

Config task-টার দিকে ফিরে তাকাও। তাতে `notify: Restart caddy` আছে। `Restart caddy` নামের **handler** `handlers:` অংশে বসে। Docs অনুযায়ী handler-এর আচরণ:

- Handler এমন একটা task যা **শুধু notify হলে চলে**।
- এটা **শুধু তখনই চলে যখন যে task একে notify করেছে সেটা `changed` জানায়**। Caddyfile আগে থেকে মিললে কিছুই হয় না, তাই Caddy অকারণে restart **হয় না**।
- ডিফল্টভাবে handler চলে **play-এর সব task শেষ হওয়ার পরে**।
- একাধিকবার notify হলেও handler **একবার** চলে।
- Handler চলে `handlers:`-এ **সংজ্ঞায়িত** ক্রমে, `notify`-এর ক্রমে নয়।

"Config বদলালে service restart করো" এর জন্য ঠিক এটাই লাগে। `meta: flush_handlers` task দিয়ে notify করা handler আগেও চালাতে বাধ্য করা যায়, আর `listen` দিয়ে handler একটা topic-এর নামে subscribe করতে পারে।

## ৬. Variable

কিছু তুমি আগেই ব্যবহার করেছ। Variable বারবার আসা মান সরায়, তাই path বা package-এর তালিকা **একবার** লেখা হয়।

```yaml
vars:
  app_dir: /var/www/myapp
```

Variable ব্যবহার করো ডাবল curly brace দিয়ে: `{{ app_dir }}`। Docs থেকে চারটা নিয়ম:

1. **`{{` দিয়ে শুরু হওয়া মান quote করো।** `name: "{{ packages }}"` আর `app_path: "{{ base_path }}/22"`-এ quote লাগে। না দিলে YAML `Syntax Error while loading YAML` দিয়ে ব্যর্থ হয়।
2. **নাম**-এ শুধু অক্ষর, সংখ্যা ও underscore থাকতে পারে, আর সংখ্যা দিয়ে শুরু হতে পারে না। `async` বা `environment`-এর মতো Python বা playbook keyword হতে পারে না।
3. **Play `vars`** শুধু ওই play-এর task-এ দৃশ্যমান। `vars_files:` একটা বাইরের YAML file থেকে variable load করে, যা মান playbook-এর বাইরে রাখে।
4. **Extra vars** যা command line-এ `-e` দিয়ে দেওয়া হয়, variable যে সব জায়গা থেকে আসতে পারে তার মধ্যে **সর্বোচ্চ** অগ্রাধিকার পায়। Docs ২২টা স্তর দেখায়, তাই "মান আমার সেট করা নয়" হলে অগ্রাধিকারই প্রথম দেখার জায়গা।

Git-এ commit করা সাধারণ variable-এ কখনো password রেখো না। Chapter 4-এ Ansible Vault আছে।

## ৭. `become`: administrator হিসেবে চালানো

Package install করতে root লাগে। Playbook-এ তুমি `become: true` দিয়ে এটা চাও, হয় **play**-তে (ওপরের মতো, যাতে প্রতিটা task বাড়তি অধিকারে চলে) নয়তো একটা **task**-এ:

```yaml
- name: Ensure the web service is running
  ansible.builtin.service:
    name: caddy
    state: started
  become: true
```

`become_user` ঠিক করে কোন account-এ উঠবে; ডিফল্ট `root`, আর docs বলে এটা `become: true` বোঝায় **না**। Command line-এ `--become` (বা `-b`) আর `--ask-become-pass` (বা `-K`) আছে, আর `-K` শুধু password চায়, নিজে থেকে অধিকার বাড়ানো চালু করে না। `become` যতটা পারো সংকীর্ণ রাখো: শুধু দুটো task-এ root লাগলে ওই দুটোতেই দাও।

## ৮. নিরাপদে চালানো: apply করার আগে check করো

এগুলো অভ্যাস বানাও, এই ক্রমে। নিচের সব flag docs-এর "Verifying playbooks" অংশে তালিকাভুক্ত:

```bash
ansible-playbook -i inventory.ini web.yml --syntax-check   # is the YAML/structure valid?
ansible-playbook -i inventory.ini web.yml --list-tasks     # what would run, in what order
ansible-playbook -i inventory.ini web.yml --check          # report what WOULD change, change nothing
ansible-playbook -i inventory.ini web.yml --check --diff   # ...and show the text differences
ansible-playbook -i inventory.ini web.yml                  # now really apply
ansible-playbook -i inventory.ini web.yml                  # second run: expect changed=0
```

Docs বলে check mode-এ "কোনো পরিবর্তন প্রয়োগ করার বদলে Ansible জানায় সে কী কী বদলাত।" দুটো সতর্কতা: সব module check mode-এ নিজের ফল আগে বলতে পারে না (`creates` ছাড়া `command` ব্যবহার করা task বাদ পড়ে), আর dry run কোনো নিশ্চয়তা নয়। Task-এর বিস্তারিত output দেখতে `--verbose`, আর একসাথে ১০টা machine-এ কাজ করতে `-f 10` ব্যবহার করো। আর Chapter 2-এর মতোই: test machine-এ অনুশীলন করো, ছোট group দিয়ে শুরু করো।

## ৯. YAML টিকে থাকার টিপস

YAML space নিয়ে নির্দয়, আর playbook YAML।

- **Space দিয়ে indent করো, কখনো tab নয়।** প্রতি স্তরে ২ space, আর ধারাবাহিক রাখো।
- তালিকার আইটেম শুরু হয় `- ` দিয়ে। Task একটা তালিকা।
- `key: value`-তে colon-এর পর space লাগে।
- ভুল পড়ার সম্ভাবনা থাকা মান quote করো: `'0644'`, আর `{{` দিয়ে শুরু হওয়া যেকোনো মান।
- `--syntax-check` অভিযোগ করলে সমস্যা প্রায় সবসময় সে যে লাইনের নাম বলে তার কাছের একটা indentation-এর লাইন।

## ১০. নিজে যাচাই করো

<details>
<summary><strong>Q1. বারবার করা কাজের জন্য ad-hoc command-এর বদলে playbook কেন?</strong></summary>

Playbook একটা সংরক্ষিত YAML file: পুনরাবৃত্তি করা যায়, Git-এ review করা যায়, আর অনেক task ক্রমে চালাতে পারে। Ad-hoc command একটা module একবার চালায় এবং পুনর্ব্যবহারযোগ্য নয়।
</details>

<details>
<summary><strong>Q2. Playbook-এর চারটা স্তর কী?</strong></summary>

একটা play (কাজের block), তার `hosts` (কোন machine), তার `tasks` (ক্রমিক তালিকা), আর প্রতিটা task (একটা নাম ও option সহ একটা module)।
</details>

<details>
<summary><strong>Q3. Task-এর নাম "Start Caddy"-র বদলে "Caddy is running" হওয়া উচিত কেন?</strong></summary>

এটা কাঙ্ক্ষিত ফল বর্ণনা করে, যা declarative, idempotent ধরনের সাথে মেলে: machine আগে থেকে ওরকম না থাকলেই Ansible কাজ করে।
</details>

<details>
<summary><strong>Q4. Playbook দুইবার চালালে দ্বিতীয়বারের PLAY RECAP কী দেখায়, আর তা কী প্রমাণ করে?</strong></summary>

`changed=0`। এটা প্রমাণ করে playbook idempotent: প্রথমবারে machine কাঙ্ক্ষিত অবস্থায় এসেছে, তাই দ্বিতীয়বারে করার কিছু ছিল না।
</details>

<details>
<summary><strong>Q5. `command` ব্যবহার করা task idempotency ভাঙতে পারে কেন, আর দুটো সমাধান কী?</strong></summary>

কাঁচা command চালানো দরকার ছিল কি না Ansible বলতে পারে না, তাই প্রতিবার চালায় আর `changed` জানায়। যেখানে আসল module আছে সেটা ব্যবহার করো, অথবা `creates` বা `removes` দাও, যাতে ফল আগে থেকে থাকলে command বাদ যায়।
</details>

<details>
<summary><strong>Q6. Handler কখন চলে, আর কতবার?</strong></summary>

শুধু যখন যে task একে notify করে সেটা `changed` জানায়, ডিফল্টভাবে play-এর সব task শেষ হওয়ার পরে, আর একাধিকবার notify হলেও মাত্র একবার।
</details>

<details>
<summary><strong>Q7. `name: "{{ packages }}"` কেন quote দিয়ে লিখতে হয়?</strong></summary>

`{{` দিয়ে শুরু হওয়া YAML মান quote করতে হয়, নইলে YAML variable আর dictionary-র শুরু আলাদা করতে পারে না এবং syntax error দেয়।
</details>

<details>
<summary><strong>Q8. Playbook apply করার আগে command-এর নিরাপদ ক্রম বলো।</strong></summary>

`--syntax-check`, তারপর `--list-tasks`, তারপর `--check` (ও `--diff`), তারপর আসল চালানো, তারপর দ্বিতীয়বার চালানো যা `changed=0` দেখাবে, সবসময় একটা practice machine বা ছোট group দিয়ে শুরু করে।
</details>

## ১১. বিনামূল্যের resource

- Ansible docs: [Creating a playbook](https://docs.ansible.com/ansible/latest/getting_started/get_started_playbook.html) ও [Ansible playbooks](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_intro.html)
- Ansible docs: [Handlers](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_handlers.html)
- Ansible docs: [Using variables](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_variables.html) ও [Understanding privilege escalation: become](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_privilege_escalation.html)
- Module page: [apt](https://docs.ansible.com/ansible/latest/collections/ansible/builtin/apt_module.html), [file](https://docs.ansible.com/ansible/latest/collections/ansible/builtin/file_module.html), [copy](https://docs.ansible.com/ansible/latest/collections/ansible/builtin/copy_module.html), [command](https://docs.ansible.com/ansible/latest/collections/ansible/builtin/command_module.html)

---

**পরের chapter:** [Chapter 4 — Ansible role, template ও secret-এর জন্য Vault](04-ansible-roles-templates-and-vault.md)
