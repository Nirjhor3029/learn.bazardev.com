# Chapter 2 — Ansible-এর গোড়ার কথা: Inventory আর Ad-hoc Command

> **Course:** Infrastructure as Code (Terraform & Ansible) · **Chapter:** 2 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/iac/02-ansible-basics)

Chapter 1 তোমাকে ধারণাটা দিয়েছে। এবার প্রথম tool-এর সাথে পরিচয়। এই chapter-এ তুমি Ansible-কে বলবে **কোন machine-গুলো** manage করতে হবে (*inventory*), আর সব machine-এ একসাথে **এক-ঝটকার command** চালাবে (*ad-hoc command*)। শেষে "আমার পাঁচটা server-এর সবগুলোর disk কি ভরে গেছে?" এই প্রশ্নের উত্তর এক লাইনে পাবে।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Ansible **install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের প্রতিটা command ও file সরকারি Ansible documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: command-এর রূপ (`ansible-inventory -i inventory.ini --list`, `ansible myhosts -m ping -i inventory.ini`, ad-hoc flag গুলো, Ubuntu-র install command, `ansible.cfg` খোঁজার ক্রম) সরকারি docs থেকে পড়া, নিচের YAML inventory যে বৈধ YAML (tool দিয়ে parse করা), আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। Output-এর block গুলো **docs থেকে আকার নিয়ে** দেওয়া, কোনো আসল machine-এ চালিয়ে নেওয়া নয়, আর তোমার IP ও version আলাদা হবে। নিজের machine-এ প্রথম চালানোটাই আসল পরীক্ষা, আর শুরু করো একটা practice machine-এ, production-এ কখনো নয়।

![Ansible কীভাবে তোমার server-এ পৌঁছায়: একটা control node, SSH, অনেক managed node](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/08-iac/images/ansible-control-node.svg)

## ১. শব্দভাণ্ডার (পাঁচটা শব্দ)

| শব্দ | মানে |
|---|---|
| **Control node** | যে machine-এ তুমি Ansible চালাও, সাধারণত তোমার laptop বা workspace server। এতে Ansible install থাকা লাগে। |
| **Managed node** | যে server-কে Ansible manage করে। এতে **Ansible install লাগে না** (Chapter 1: *agentless*)। |
| **Inventory** | Managed node-এর তালিকার file, আর তাদের ভাগ করা (web server, database server)। |
| **Module** | একটা ছোট tool যা managed node-এ একটা কাজ করে: package install, file copy, service restart। |
| **Ad-hoc command** | এক লাইনের `ansible ...` command যা **একটা module** একবার চালায়। দ্রুত, কিন্তু সংরক্ষিত বা পুনর্ব্যবহারযোগ্য নয়। |

প্রবাহটা সোজা: তুমি control node-এ command চালাও; Ansible inventory পড়ে machine খুঁজে নেয়, প্রতিটার সাথে **SSH** দিয়ে সংযোগ করে, সেখানে module চালায়, আর ফল তোমাকে দেখায়।

## ২. আগে যা লাগবে

সরকারি docs এই চাহিদাগুলো দেয়:

- **Control node:** "Python install করা প্রায় যেকোনো UNIX-like machine"। **Windows শুধু WSL (Windows Subsystem for Linux) দিয়ে** সমর্থিত। সরাসরি (native) সমর্থিত নয়।
- **Managed node:** একটা user account যা interactive POSIX shell সহ **SSH** দিয়ে সংযোগ করতে পারে, আর node-এ **Python**, কারণ Ansible সেখানে Python code চালায়। (কয়েকটা module, যেমন network module, ব্যতিক্রম।) Managed node-এ Ansible নিজে install করা **লাগে না**।

তাই Ansible-এর আগে নিশ্চিত করো তুমি **SSH key** দিয়ে তোমার server-এ ঢুকতে পারো। এটা Linux course (SSH chapter) আর Cloud course (তোমার প্রথম VPS)-এ আছে। Ansible quick-start-ও চায় তোমার public key প্রতিটা host-এর `authorized_keys`-এ থাকুক। আগে সাধারণ SSH দিয়ে পরীক্ষা করো:

```bash
ssh deploy@192.0.2.50
```

তোমার account-এ password না চেয়ে এটা না ঢোকালে আগে ঠিক করো। Ansible SSH-এর চেয়ে ভালো কিছু করতে পারে না।

## ৩. Control node-এ Ansible install করা

Docs একাধিক উপায় বলে। দুটো সাধারণ উপায়:

**`pipx` দিয়ে** (OS যখন `pip` সীমিত করে, তখন docs একে সহজলভ্য বিকল্প বলে):

```bash
pipx install --include-deps ansible
```

**Ubuntu-তে, Ansible PPA দিয়ে** (docs বলে PPA-তে Ubuntu-র সাধারণ repository-র চেয়ে নতুন version থাকে):

```bash
sudo apt update
sudo apt install software-properties-common
sudo add-apt-repository --yes --update ppa:ansible/ansible
sudo apt install ansible
```

এগুলোতে `sudo` লাগে, কারণ এগুলো system-এর package source বদলায়। সবসময়ের মতো প্রতিটা লাইন চালানোর আগে পড়ো। তারপর যাচাই করো কাজ হয়েছে কি না:

```bash
ansible --version
```

(Package-এর নাম, version আর ঠিক output system ভেদে আলাদা।)

## ৪. Inventory file

Inventory হলো তোমার machine-এর তালিকা। সবচেয়ে সহজ রূপ **INI** file। Docs-এর quick-start `myhosts` নামের একটা group ব্যবহার করে:

```ini
[myhosts]
192.0.2.50
192.0.2.51
192.0.2.52
```

`192.0.2.x` ঠিকানাগুলো documentation-এর জন্য সংরক্ষিত, তাই নিরাপদ উদাহরণ। ওখানে নিজের server-এর IP বা domain নাম বসাও। `inventory.ini` নামে save করো।

**ব্যবহারের আগে inventory যাচাই করো।** এটা শুধু file পড়ে, তাই নিরাপদ:

```bash
ansible-inventory -i inventory.ini --list
```

### Group: machine-গুলোকে অর্থপূর্ণ বাক্সে রাখো

আসল project-এ নানা ধরনের server থাকে। Group দিয়ে তুমি আলাদাভাবে লক্ষ্য করতে পারো। Docs পরামর্শ দেয় **কী** (web, db), **কোথায়** (region) আর **কখন** (staging, production) অনুযায়ী ভাগ করতে। Group-এর নাম case sensitive, আর docs space, hyphen ও শুরুতে সংখ্যা এড়াতে বলে, তাই `web-servers` নয়, `web_servers` ব্যবহার করো।

```ini
[web]
web1.example.com
web2.example.com

[db]
db1.example.com

[production:children]
web
db
```

`[production:children]` `production`-কে **group-এর group** বানায়। Docs পরামর্শ দেয় প্রতিটা group শুধু একটা parent-এর নিচে রাখতে, যাতে variable inheritance অনুমানযোগ্য থাকে।

### YAML রূপ, আর host-কে সহজ নাম দেওয়া

তালিকা বড় হলে docs বলে YAML যুক্তিযুক্ত হয়ে ওঠে। একই host, প্রতিটার একটা সহজ নাম, আর `ansible_host`-এ আসল ঠিকানা:

```yaml
myhosts:
  hosts:
    my_host_01:
      ansible_host: 192.0.2.50
    my_host_02:
      ansible_host: 192.0.2.51
    my_host_03:
      ansible_host: 192.0.2.52
```

### Variable: কোন user, কোন ঠিকানা

Inventory **variable**-ও বহন করতে পারে। দুটো প্রতিনিয়ত ব্যবহার হয়:

| Variable | মানে |
|---|---|
| `ansible_host` | যে আসল ঠিকানায় সংযোগ হবে, তাই host-এর `webserver01`-এর মতো যৌক্তিক নাম থাকতে পারে |
| `ansible_user` | যে SSH user হিসেবে ঢুকবে |

Variable একটা host-এ বা `vars:` দিয়ে পুরো group-এ বসানো যায়। Docs-এর উদাহরণ `webservers`-এর প্রতিটা host-এর জন্য একটা user ঠিক করে:

```yaml
webservers:
  hosts:
    webserver01:
      ansible_host: 192.0.2.140
      http_port: 80
    webserver02:
      ansible_host: 192.0.2.150
      http_port: 443
  vars:
    ansible_user: my_server_user
```

Git-এ commit করা inventory-তে কখনো password রেখো না। Secret-এর জন্য Chapter 4-এ Ansible Vault শেখানো হবে।

### নিজের machine-এই অনুশীলন

Practice করতে দ্বিতীয় server লাগে না। Ansible যে machine-এ চলছে তাকেই লক্ষ্য করতে পারে, SSH-এর বদলে `local` connection দিয়ে। `localhost ansible_connection=local`-এর মতো এক লাইনের inventory সেটা করে (বিস্তারিত জানতে Ansible docs-এর connection plugin অংশ দেখো)। Ad-hoc command চেষ্টার এটা নিরাপদ জায়গা, যতক্ষণ তুমি `ping` আর `setup`-এর মতো শুধু পড়ার command-এ থাকো।

## ৫. তোমার প্রথম command: ping

Inventory তৈরি হলে docs-এর প্রথম পরীক্ষা:

```bash
ansible myhosts -m ping -i inventory.ini
```

বাঁ থেকে ডানে পড়ো:

| অংশ | মানে |
|---|---|
| `ansible` | ad-hoc command |
| `myhosts` | **pattern**: কোন host বা group-এ চালাবে (`all` মানে প্রতিটা host) |
| `-m ping` | **module**: `ping` module ব্যবহার করো |
| `-i inventory.ini` | **inventory**: কোন file-এ machine-এর তালিকা |

প্রতিটা host-এর জন্য ভালো ফল দেখতে এমন (docs থেকে আকার):

```
192.0.2.50 | SUCCESS => {
    "ansible_facts": {
        "discovered_interpreter_python": "/usr/bin/python3"
    },
    "changed": false,
    "ping": "pong"
}
```

দুটো জিনিস লক্ষ্য করো। `"ping": "pong"` **network ping (ICMP) নয়**। এর মানে Ansible SSH দিয়ে ঢুকতে পেরেছে আর host-এ Python চালাতে পেরেছে, যা বাকি সবকিছু কাজ করার আগে ঠিক যা লাগে। আর `"changed": false` মানে command machine-এ কিছুই বদলায়নি। Control node আর managed node-এ username আলাদা হলে command-এ `-u username` দাও, বা inventory-তে `ansible_user` বসাও।

প্রথম সংযোগে host-এর fingerprint নিশ্চিত করতে বললে সেটা SSH-এর স্বাভাবিক কাজ। SSH chapter-এ যেভাবে শিখেছ সেভাবে fingerprint মিলিয়ে দেখো। বার্তাটা সরাতে host যাচাই বন্ধ করে দিয়ো না।

## ৬. Ad-hoc command: ছাঁচটা

Docs-এর সাধারণ রূপ:

```
ansible [pattern] -m [module] -a "[module options]"
```

| Flag | মানে |
|---|---|
| `-m` | ব্যবহারযোগ্য module। বাদ দিলে ডিফল্ট `ansible.builtin.command` |
| `-a` | module-এর option, `key=value` জোড়ায় |
| `-i` | কোন inventory file পড়বে |
| `-u` | এই user হিসেবে সংযোগ করো |
| `--become` | অধিকার বাড়াও, remote machine-এ `sudo`-র মতো। Password চাওয়াতে `--ask-become-pass` (বা `-K`) দাও |
| `-f` | একসাথে কয়টা machine-এ কাজ করবে (docs বলে ডিফল্ট পাঁচ) |
| `-C` বা `--check` | **Check mode**: কিছু না বদলে বলে *কী বদলাত* |

### কাজের উদাহরণ (docs থেকে মানিয়ে নেওয়া)

সব জায়গায় একটা command চালাও (ডিফল্ট module `command`):

```bash
ansible web -i inventory.ini -a "uptime"
```

Machine-এ একটা file copy করো:

```bash
ansible web -i inventory.ini -m ansible.builtin.copy -a "src=/etc/hosts dest=/tmp/hosts"
```

Ubuntu বা Debian-এ একটা package install করো (docs Red Hat পরিবারের জন্য `yum` দেখায়; Ubuntu-তে মিলে যাওয়া module `apt`):

```bash
ansible web -i inventory.ini --become -m ansible.builtin.apt -a "name=git state=present"
```

একটা service চালু বা restart করো (docs `httpd` ব্যবহার করে; নাম system ভেদে আলাদা):

```bash
ansible web -i inventory.ini --become -m ansible.builtin.service -a "name=caddy state=restarted"
```

প্রতিটা machine সম্পর্কে Ansible যা জানে সব দেখাও (এগুলোকে **fact** বলে: OS, memory, IP ঠিকানা):

```bash
ansible web -i inventory.ini -m ansible.builtin.setup
```

`state=` শব্দগুলো লক্ষ্য করো: `present`, `restarted`, `started`, `stopped`, `absent`। এগুলোই Chapter 1-এর declarative ধারণা। তুমি বলো কী সত্যি হওয়া উচিত, আর Ansible শুধু যা না বদলালেই নয় তা বদলায়। Docs বলে ad-hoc command-ও playbook-এর একই declarative, idempotent মডেল ব্যবহার করে।

### `command` বনাম `shell`

- `ansible.builtin.command` ডিফল্ট। এটা pipe (`|`) বা redirect (`>`) **বোঝে না**।
- `ansible.builtin.shell` একটা shell দিয়ে চলে, তাই pipe বা redirect লাগলে এটা ব্যবহার করো।
- **Quote-এর গুরুত্ব আছে।** `ansible web -m ansible.builtin.shell -a 'echo $TERM'`-এ single quote `$TERM`-কে **remote** machine-এ expand হতে দেয়। Double quote হলে **তোমার** machine আগে সেটা expand করে ফেলত।

## ৭. Ad-hoc command-এর নিরাপদ অভ্যাস

একটা ad-hoc command এক চাপে অনেক server বদলাতে পারে, তাই শুরু থেকেই অভ্যাস গড়ো:

1. **ছোট থেকে শুরু।** `all`-এর আগে একটা group বা একটা host-এ চালাও। আগে একটা test machine-এ অনুশীলন করো।
2. **আগে শুধু পড়ার।** `ping`, `setup` ও `uptime` কিছু বদলায় না। এগুলোতে শেখো।
3. **বদলের জন্য check mode।** কী বদলাত দেখতে আগে `-C` দাও। (Docs এভাবে ব্যবহার করে: `ansible all -m copy -a "content=foo dest=/root/bar.txt" -C`। মনে রেখো, সব module check mode-এ ফল আগে থেকে বলতে পারে না।)
4. **`--become`-এ সাবধান।** এটা administrator অধিকারে চলে। `all`-এ reboot বা package মোছা প্রতিটা server-কে একসাথে আঘাত করে।
5. **মনে রেখো ad-hoc সংরক্ষিত নয়।** Docs এই command-গুলোকে "পুনর্ব্যবহারযোগ্য নয়" বলে। আবার করতে হলে playbook লেখো। সেটা Chapter 3।

## ৮. `ansible.cfg`: প্রতিবার `-i` টাইপ করা বন্ধ করো

প্রতিবার `-i inventory.ini` টাইপ করা বিরক্তিকর। Ansible একটা configuration file থেকে setting পড়ে। Docs বলে সে কোথায় কোথায় খোঁজে, এই ক্রমে, আর **প্রথম** যেটা পায় সেটা ব্যবহার করে:

1. `ANSIBLE_CONFIG` environment variable, সেট থাকলে
2. **বর্তমান directory**-র `ansible.cfg`
3. `~/.ansible.cfg`
4. `/etc/ansible/ansible.cfg`

Inventory-র পাশে project-স্তরের `ansible.cfg` সাধারণ পছন্দ, কারণ সেটা Git-এ project-এর সাথে যায়:

```ini
[defaults]
inventory = inventory.ini
```

এখন `-i` ছাড়াই `ansible web -m ping` কাজ করে। Docs-এর একটা নিরাপত্তার কথা: বর্তমান directory যদি **world-writable** হয়, Ansible সেখান থেকে config file **load করে না**, যাতে অপরিচিত কেউ config ঢুকিয়ে দিতে না পারে। `ansible-config` command সব উপলব্ধ setting, সেগুলো কোথা থেকে আসে আর ডিফল্ট কী তা দেখাতে পারে।

## ৯. পুনর্ব্যবহারযোগ্য প্রথম checklist

1. আমার key দিয়ে কি machine-এ `ssh` করতে পারি? (না পারলে এখানেই থামো।)
2. সঠিক group-এ কি inventory-তে আছে? (`ansible-inventory -i inventory.ini --list`)
3. `ansible <group> -m ping` কি `pong` বলে?
4. বদলের জন্য: আগে **ছোট** group-এ `-C` দিয়ে চালিয়েছি কি?
5. এটা আবার লাগবে কি? তাহলে এর জায়গা playbook-এ (Chapter 3)।

## ১০. নিজে যাচাই করো

<details>
<summary><strong>Q1. Control node আর managed node-এর পার্থক্য কী?</strong></summary>

Control node-এ তুমি Ansible চালাও, আর এতে Ansible install থাকে। Managed node হলো যে server-গুলো সে manage করে, এতে Ansible install লাগে না, শুধু SSH access আর Python লাগে।
</details>

<details>
<summary><strong>Q2. Inventory কী?</strong></summary>

একটা file যাতে Ansible যে machine-গুলো manage করে তাদের তালিকা আর ভাগ থাকে, যেমন web ও db, ঐচ্ছিকভাবে `ansible_host` ও `ansible_user`-এর মতো variable সহ।
</details>

<details>
<summary><strong>Q3. Output-এ `"ping": "pong"` মানে কী?</strong></summary>

Network ping নয়। এর মানে Ansible SSH দিয়ে সংযোগ করেছে আর ওই host-এ Python চালাতে পেরেছে, অর্থাৎ machine manage হওয়ার জন্য তৈরি।
</details>

<details>
<summary><strong>Q4. এই command পড়ো: `ansible web -i inventory.ini --become -m ansible.builtin.apt -a "name=git state=present"`</strong></summary>

`web` group-এর host-গুলোতে, `inventory.ini` file থেকে, administrator অধিকারে, `apt` module দিয়ে নিশ্চিত করো `git` package present আছে। Git আগে থেকে install থাকলে কিছুই বদলায় না।
</details>

<details>
<summary><strong>Q5. `-C` (`--check`) কী করে?</strong></summary>

Check mode। কিছু না বদলে বলে কী বদলাত, যাতে বদলের আগে preview দেখতে পারো।
</details>

<details>
<summary><strong>Q6. `command`-এর বদলে কখন `shell` ব্যবহার করবে?</strong></summary>

যখন pipe বা redirect-এর মতো shell-এর সুবিধা লাগে, যা ডিফল্ট `command` module সমর্থন করে না।
</details>

<details>
<summary><strong>Q7. বারবার করা কাজের জন্য ad-hoc command কেন যথেষ্ট নয়?</strong></summary>

এগুলো একটা module একবার চালায়, সংরক্ষিত বা পুনর্ব্যবহারযোগ্য নয়। বারবার বা একাধিক ধাপের কাজ playbook-এ থাকা উচিত।
</details>

<details>
<summary><strong>Q8. Ansible কোন ক্রমে `ansible.cfg` খোঁজে?</strong></summary>

`ANSIBLE_CONFIG` environment variable, তারপর বর্তমান directory-র `ansible.cfg`, তারপর `~/.ansible.cfg`, তারপর `/etc/ansible/ansible.cfg`। প্রথমটা পেলে সেটাই ব্যবহার করে।
</details>

## ১১. বিনামূল্যের resource

- Ansible docs: [Building an inventory](https://docs.ansible.com/ansible/latest/getting_started/get_started_inventory.html)
- Ansible docs: [Introduction to ad-hoc commands](https://docs.ansible.com/ansible/latest/command_guide/intro_adhoc.html)
- Ansible docs: [Installing Ansible](https://docs.ansible.com/ansible/latest/installation_guide/intro_installation.html) ও [নির্দিষ্ট operating system-এ](https://docs.ansible.com/projects/ansible/latest/installation_guide/installation_distros.html)
- Ansible docs: [Configuring Ansible](https://docs.ansible.com/ansible/latest/reference_appendices/config.html)

---

**পরের chapter:** Chapter 3 — Ansible playbook: module, handler ও idempotency *(শীঘ্রই আসছে)*
