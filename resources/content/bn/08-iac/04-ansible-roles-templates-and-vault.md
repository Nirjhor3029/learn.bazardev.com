# Chapter 4 — Ansible Role, Template ও secret-এর জন্য Vault

> **Course:** Infrastructure as Code & Ansible · **Chapter:** 4 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/iac/04-ansible-roles-templates-and-vault)

তোমার Chapter 3-এর playbook কাজ করেছে, কিন্তু আসল project বড় হয়। একটা লম্বা file পড়া কঠিন হয়ে যায়। একই config server ভেদে একটু আলাদা হয়। আর দেরিতে হোক বা আগে, setup-এ একটা **password** লাগে, যা কখনো Git-এ সাদা text-এ থাকা চলবে না। এই chapter ওই তিন সমস্যার তিনটা tool দেয়:

| সমস্যা | Tool |
|---|---|
| একটা বিশাল playbook, copy-paste করা task | **Role**: সম্পর্কিত কাজ প্যাকেজ করার একটা প্রমিত folder কাঠামো |
| একই file, server ভেদে সামান্য পার্থক্য | **Template**: ফাঁকা জায়গাসহ একটা file, প্রতিটা machine-এর জন্য ভরা হয় |
| Setup-এ password ও key | **Vault**: secret encrypt করো যাতে Git-এ নিরাপদ থাকে |

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Ansible **install করা নেই**, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের file ও command গুলো সরকারি Ansible ও Jinja2 documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: role-এর folder কাঠামো ও চালানোর ক্রম, `template` module-এর parameter (`src`, `dest`, `mode`, `%s` সহ `validate`), Jinja2-এর delimiter, `loop`-এর syntax, `ansible-vault` command (`create`, `encrypt`, `encrypt_string`, `view`, `edit`, `decrypt`, `rekey`), vault password দেওয়ার উপায়, password file-এর নিয়ম আর `no_log`-এর পরামর্শ সরকারি docs থেকে পড়া, প্রতিটা YAML block যে বৈধ YAML (tool দিয়ে parse করা), আর প্রতিটা বাইরের link খুলে দেখা হয়েছে। **এই chapter-এর প্রতিটা secret একটা নকল placeholder।** কোনো আসল password, key বা `.env` ব্যবহার বা তৈরি করা হয়নি। প্রতিটা ধাপ আগে একটা practice machine-এ, ফেলনা secret দিয়ে প্রমাণ করো, তারপর আসল secret-এ ভরসা করো।

![Role-ভিত্তিক project: role কাজ ধরে, template মান ভরে, Vault secret encrypt করে রাখে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/08-iac/images/roles-templates-vault.svg)

## ১. Role: পুনর্ব্যবহারযোগ্য কাজের folder কাঠামো

**Role** হলো সম্পর্কিত task, file, template ও variable প্যাকেজ করার একটা প্রমিত উপায়, যাতে playbook এক লাইনে বলতে পারে "এই server একটা `web` server"। Docs কাঠামোটা দেয়। প্রতিটা role-এ এই directory-গুলোর **অন্তত একটা** লাগে, আর যেগুলো ব্যবহার করো না সেগুলো বাদ দাও:

```
roles/
    web/
        tasks/        # main.yml: the tasks the role runs
        handlers/     # main.yml: the role's handlers
        templates/    # Jinja2 templates (.j2)
        files/        # static files for copy
        defaults/     # main.yml: low-precedence variables (easy to override)
        vars/         # main.yml: high-precedence variables
        meta/         # main.yml: dependencies and Galaxy metadata
```

| Folder | এতে কী যায় |
|---|---|
| `tasks/main.yml` | Role যে task-তালিকা play-তে যোগ করে |
| `handlers/main.yml` | Handler (এই service restart করো)। যে play role ব্যবহার করে তাতে এগুলো আমদানি হয় |
| `defaults/main.yml` | Role-এর **setting**, "খুব কম অগ্রাধিকারে": অন্য যেকোনো কিছু এগুলোকে ছাপিয়ে যায় |
| `vars/main.yml` | উচ্চ অগ্রাধিকারের role variable, যে মান এলোমেলোভাবে বদলানো উচিত নয় তার জন্য |
| `templates/` ও `files/` | Role-এর নিজস্ব template ও static file, লম্বা path ছাড়াই পাওয়া যায় |
| `meta/main.yml` | Role-এর dependency। শুধু role ব্যবহার করতে এটা লাগে না |

এই file-গুলোর কোনোটাই বাধ্যতামূলক নয়। Docs বলে শুধু `files/something.txt` আছে এমন role-ও বৈধ। (`ansible-galaxy` হলো role ভাগাভাগির Ansible client, আর docs বলে সেটা নিজের role বানানোর একটা ডিফল্ট কাঠামো দেয়। ঠিক command-এর জন্য তার help দেখো।)

**Variable-এর সাধারণ নিয়ম:** ব্যবহারকারী যে setting বদলাতে চাইতে পারে তার সবগুলো `defaults/main.yml`-এ রাখো। ওভাররাইড করার সবচেয়ে সহজ জায়গা এটাই, আর এতে role-এর সব knob এক file-এ লেখা থাকে।

### একটা ছোট `web` role

`roles/web/defaults/main.yml` হলো role-এর setting:

```yaml
site_domain: example.com
app_dir: /var/www/myapp
php_version: "8.3"
```

`roles/web/tasks/main.yml` হলো কাজ (`loop` লক্ষ্য করো, ৩ নম্বর অংশে ব্যাখ্যা আছে):

```yaml
- name: App directory exists
  ansible.builtin.file:
    path: "{{ app_dir }}"
    state: directory
    mode: '0755'

- name: Shared directories exist
  ansible.builtin.file:
    path: "{{ item }}"
    state: directory
    mode: '0755'
  loop:
    - "{{ app_dir }}/storage"
    - "{{ app_dir }}/releases"

- name: Web server config is in place
  ansible.builtin.template:
    src: Caddyfile.j2
    dest: /etc/caddy/Caddyfile
    owner: root
    group: root
    mode: '0644'
    backup: true
  notify: Restart caddy

- name: Caddy is running
  ansible.builtin.service:
    name: caddy
    state: started
```

`roles/web/handlers/main.yml`:

```yaml
- name: Restart caddy
  ansible.builtin.service:
    name: caddy
    state: restarted
```

### Role ব্যবহার করা

Playbook খুব ছোট হয়ে যায়। `roles:` keyword হলো ক্লাসিক উপায়, আর role-গুলো তুমি যে ক্রমে লেখো সেই ক্রমে চলে:

```yaml
- name: Web servers
  hosts: web
  become: true
  roles:
    - web
```

Docs একটা play-এর চালানোর ক্রম দেয়: `pre_tasks`, তারপর `roles:`-এর প্রতিটা role (লেখা ক্রমে, role dependency আগে), তারপর play-এর নিজের `tasks`, তারপর handler, তারপর `post_tasks`। Ansible role খোঁজে সবার আগে **playbook-এর পাশের** `roles/` directory-তে, আরও কিছু জায়গার মধ্যে, তাই `site.yml`-এর পাশে `roles/` রাখলেই কাজ করে।

Role ব্যবহারের অন্য উপায়: task হিসেবে `import_role` (static) ও `include_role` (dynamic, শর্তসাপেক্ষ হতে পারে), অথবা `meta/main.yml`-এ `dependencies` এন্ট্রি হিসেবে।

### Role-এর setting ওভাররাইড করা

যেহেতু `defaults/`-এর অগ্রাধিকার **সবচেয়ে কম**, তুমি play, inventory বা command line থেকে ওভাররাইড করতে পারো:

```yaml
- name: Web servers
  hosts: web
  become: true
  vars:
    site_domain: shop.example.com
  roles:
    - web
```

Docs অনুযায়ী play `vars` role default-এর ওপরে, আর extra vars (`-e site_domain=...`) সবকিছুর ওপরে।

## ২. Template: একটা file, অনেক server

Chapter 3 `copy` ব্যবহার করেছে, যা সব জায়গায় **একই** file পাঠায়। কিন্তু web server config domain ও path ভেদে আলাদা। `ansible.builtin.template` module এমন একটা file পাঠায় যা আগে variable দিয়ে **ভরে নেওয়া** হয়। Docs: "Template গুলো Jinja2 templating language দিয়ে প্রক্রিয়া করা হয়", আর "সব templating Ansible control node-এ হয়, task target machine-এ পাঠানো ও চালানোর আগে।"

যে parameter গুলো তুমি ব্যবহার করবে (docs থেকে): `src` (control node-এ template, UTF-8), `dest` (machine-এ কোথায় লেখা হবে), `owner`, `group`, `mode` (octal মান quote করো), `backup` ও `validate`।

### এক মিনিটে Jinja2

Template হলো সাধারণ text file, যাতে তিন ধরনের চিহ্ন থাকে (Jinja2 docs থেকে):

| চিহ্ন | উদ্দেশ্য |
|---|---|
| `{{ ... }}` | **Expression**: একটা মান print করো |
| `{% ... %}` | **Statement**: `for` loop, `if` শর্ত |
| `{# ... #}` | **Comment**: output-এ থাকে না |

`roles/web/templates/Caddyfile.j2`:

```text
{# Managed by Ansible: edit the template, not this file #}
{{ site_domain }} {
    root * {{ app_dir }}/public
{% if enable_gzip | default(true) %}
    encode gzip
{% endif %}
    php_fastcgi unix//run/php/php{{ php_version }}-fpm.sock
    file_server
}
```

তিনটা জিনিস লক্ষ্য করো। `{{ site_domain }}` variable-এর মান দিয়ে বদলে যায়। `if` block শুধু `enable_gzip` সত্যি হলে `encode gzip` যোগ করে। আর `default` filter, যা Jinja2 docs অনুযায়ী মান **undefined হলে** দেওয়া ডিফল্ট ফেরত দেয়, `enable_gzip`-কে ঐচ্ছিক করে: কেউ সেট না করলেও role ব্যর্থ হয় না। `.j2` file extension শুধু নাম রাখার অভ্যাস, নিয়ম নয়।

`for` loop একই রকম, তালিকার প্রতিটা আইটেমের জন্য একটা লাইন পুনরাবৃত্তি করতে:

```text
{% for ip in allowed_ips %}
allow {{ ip }}
{% endfor %}
```

Caddyfile block একক `{` `}` brace ব্যবহার করে, যা Jinja2 উপেক্ষা করে। সাবধান হও শুধু যদি তোমার file-এ `{{`, `{%` বা `{#` থাকে, যেগুলো Jinja2 নিজের চিহ্ন হিসেবে পড়ে। `{% ... %}` লাইনের জায়গায় বাড়তি ফাঁকা লাইন আসতে পারে; Caddy তাতে আপত্তি করে না।

### `validate`: ভাঙা config install করো না

`template` module-এ `validate` option আছে: "আপডেট করা file চূড়ান্ত গন্তব্যে copy করার আগে যে যাচাই command চালাতে হবে", যেখানে অস্থায়ী file `%s` দিয়ে পাঠানো হয়। যাচাই ব্যর্থ হলে খারাপ file ভালো file-এর ওপরে **copy হয় না**। Docs-এর নিজের উদাহরণ SSH রক্ষা করে, যেখানে ভাঙা config তোমাকে বাইরে আটকে দিতে পারে:

```yaml
- name: Update sshd configuration safely, avoid locking yourself out
  ansible.builtin.template:
    src: etc/ssh/sshd_config.j2
    dest: /etc/ssh/sshd_config
    owner: root
    group: root
    mode: '0600'
    validate: /usr/sbin/sshd -t -f %s
    backup: yes
```

অন্য program-এর জন্য *তাদের* docs-এ "config যাচাই করো" command খোঁজো, আর `validate` লাইন হিসেবে ব্যবহারের আগে practice machine-এ পরীক্ষা করো।

## ৩. Loop: একটা তালিকার জন্য একই task

`web` role-এ তুমি আগেই `loop` দেখেছ। Docs-এর রূপ:

```yaml
- name: Add several users
  ansible.builtin.user:
    name: "{{ item }}"
    state: present
  loop:
    - testuser1
    - testuser2
```

প্রতিটা এন্ট্রি পালা করে `{{ item }}` হয়। Dictionary-র তালিকার জন্য `{{ item.name }}` ইত্যাদি ব্যবহার করো। Chapter 3-এ শেখানো মতো `"{{ item }}"` quote করো। Docs বলে পুরনো `with_<lookup>` রূপ deprecated নয় আর বৈধ থাকবে, কিন্তু বেশিরভাগ ক্ষেত্রে তারা `loop` সুপারিশ করে। মনে রেখো `apt` `name`-এ আগেই **তালিকা** নেয়, তাই package-এর জন্য loop প্রায়ই লাগে না।

## ৪. Vault: যে secret Git-এ নিরাপদ

তোমার setup-এ secret লাগে: database password, API key। Chapter 1-এর সোনালি নিয়ম এখনো বহাল: **কখনো সাদা text-এ commit করো না।** Ansible-এর উত্তর **Vault**: "password-এর মতো সংবেদনশীল ডেটা encrypt ও manage করার উপায়।" তুমি একটা **vault password** দিয়ে একটা file বা একটা মান encrypt করো, *encrypted* ফলটা commit করো, আর Ansible চালানোর সময়ই শুধু password দাও।

### দুটো জিনিস তুমি encrypt করতে পারো

| কী | কীভাবে | আপস (docs থেকে) |
|---|---|---|
| **পুরো file** | `ansible-vault encrypt` / `create` | এটা মান **আর variable-এর নাম** দুটোই লুকায়। Task সহ যেকোনো content এভাবে encrypt করা যায়। |
| **একটা মান** | `ansible-vault encrypt_string` | আশেপাশের file সহজে পড়া যায়, কিন্তু শুধু মানটাই encrypt হয়, অন্য content নয়। |

আরও মনে রেখো: "Ansible জানতে পারে না তার encrypted file-এর content লাগবে কি না, যতক্ষণ না file decrypt করে", তাই encrypted file ছোঁয় এমন প্রতিটা run-এ password লাগে।

### Command-গুলো

নিচের প্রতিটা command docs থেকে। **অনুশীলনে একটা ফেলনা file ও নকল password ব্যবহার করো।**

```bash
ansible-vault create secrets.yml           # make a new encrypted file (asks for a new password, opens your editor)
ansible-vault encrypt secrets.yml          # encrypt an existing plain file
ansible-vault view secrets.yml             # read it without keeping a plain copy
ansible-vault edit secrets.yml             # change it in place
ansible-vault decrypt secrets.yml          # turn it back into plain text (be careful)
ansible-vault rekey secrets.yml            # change the vault password
```

`create`-এর পরে `secrets.yml`-এর ভেতরে তুমি সাধারণ YAML টাইপ করো। একটা **নকল** মান:

```yaml
vault_db_password: not-a-real-password
```

Disk-এ file এখন পড়া-অযোগ্য ciphertext। একটা মানের জন্য docs দেখায়:

```bash
ansible-vault encrypt_string --vault-password-file a_password_file 'foobar' --name 'the_secret'
```

**Docs-এর তিনটা সতর্কতা, দুইবার পড়ো:**

1. "Command line-এ সরাসরি (prompt ছাড়া) secret লিখলে secret string তোমার **shell history**-তে থেকে যায়।" Prompt-এর রূপ ব্যবহার করো, অথবা standard input থেকে মান পড়া `--stdin-name` রূপ। (Prompt ব্যবহারের সময় docs বলে string দেওয়ার পর Enter চাপতে নেই।)
2. **তোমার editor secret ফাঁস করতে পারে** plaintext backup বা swap file দিয়ে, কারণ Vault একটা সাধারণ editor খোলে। Docs vim ও Emacs-এর জন্য এগুলো বন্ধ করার setting দেয়। তোমারটা দেখে নাও।
3. **Prompt-এ ভুল টাইপ করা password accidental অমিলের একটা সাধারণ কারণ** (docs সারাতে `rekey` সুপারিশ করে)। সাবধানে টাইপ করো, আর আসল password একটা password manager-এ রাখো, কারণ সেটা ছাড়া encrypted ডেটা পড়া যায় না।

### Run-এর সময় Ansible-কে password দেওয়া

Docs থেকে তিনটা উপায়:

```bash
ansible-playbook --ask-vault-pass site.yml                              # type it when asked
ansible-playbook --vault-password-file /path/to/my/vault-password-file site.yml   # read it from a file
ansible-playbook --vault-id dev@prompt site.yml                         # a labelled vault ID: label@source
```

Password file হলো **এক লাইনের text** যাতে password আছে। এটা এমন একটা **executable script**-ও হতে পারে যা password print করে, দলগুলো এভাবেই secrets manager জোড়ে। ডিফল্ট file ঠিক করা যায় `DEFAULT_VAULT_PASSWORD_FILE` config option বা `ANSIBLE_VAULT_PASSWORD_FILE` environment variable দিয়ে।

Docs থেকে password file-এর নিয়ম:

- "File-এর permission যথাযথ আছে নিশ্চিত করো।" শুধু তুমি যেন পড়তে পারো (যেমন `chmod 600`)।
- **"Password file source control-এ যোগ করো না।"** File বানানোর আগেই তার নাম `.gitignore`-এ রাখো, যাতে অসাবধান `git add .` তাকে নিয়ে না নেয়।

Vault ID (`label@source`) তোমাকে বিভিন্ন environment-এর জন্য **আলাদা password** ব্যবহার করতে দেয়, যেমন `dev` ও `prod`। Docs সতর্ক করে ডিফল্টে label শুধু একটা ইঙ্গিত: কোনো label-এর জন্য প্রতিবার একই password ব্যবহার করতে Ansible বাধ্য করে না।

### Playbook-এ secret ব্যবহার

Encrypted file `vars_files` দিয়ে load করো, তারপর variable অন্য variable-এর মতোই ব্যবহার করো। Laravel-ধাঁচের একটা উদাহরণ যা template ও vaulted password থেকে `.env` লেখে (সব মান নকল):

```yaml
- name: App configuration
  hosts: web
  become: true
  vars_files:
    - secrets.yml
  tasks:
    - name: App env file is in place
      ansible.builtin.template:
        src: env.j2
        dest: /var/www/myapp/.env
        owner: root
        group: www-data
        mode: '0640'
      no_log: true
```

আর `env.j2`:

```text
APP_ENV=production
DB_PASSWORD={{ vault_db_password }}
```

`ansible-playbook --ask-vault-pass app.yml` দিয়ে চালাও। Server-এর `.env` **encrypted নয়** (application-কে পড়তে হয়), তাই `www-data` group সহ `0640` permission গুরুত্বপূর্ণ: শুধু root আর web server-এর group পড়তে পারে।

অনেক দলের ব্যবহৃত একটা রীতি, যা একটা অভ্যাস এবং Ansible-এর নিয়ম **নয়**: vaulted নামে prefix দাও (`vault_db_password`) আর একটা সাধারণ variable তাদের দিকে নির্দেশ করুক (`db_password: "{{ vault_db_password }}"`), যাতে প্রতিটা secret কোথায় ব্যবহার হচ্ছে তা তুমি code-এ খুঁজতে পারো।

### `no_log`: output-এ secret print করো না

Ansible task-এর ফল print করে। কোনো task secret নিয়ে কাজ করলে তাতে `no_log: true` দাও (ওপরের মতো)। Docs: "সংবেদনশীল মান তোমার log থেকে দূরে রাখতে, সেগুলো প্রকাশ করা task-এ `no_log: True` attribute দাও।" এর সীমাটা জানো: docs বলে **`no_log` debugging output-কে প্রভাবিত করে না**, তাই production-এ playbook debug না করতে সাবধান হও, আর কখনো `debug` task-কে secret print করতে রেখে দিয়ো না।

## ৫. Commit করার মতো একটা project

সব জুড়ে দাও। নিচের সবকিছু Git-এ যেতে পারে, **শুধু password file ছাড়া**:

```text
iac-demo/
  ansible.cfg           # [defaults] inventory = inventory.ini
  inventory.ini
  site.yml              # hosts: web, roles: [web]
  app.yml               # the vault-using play above
  secrets.yml           # ENCRYPTED with ansible-vault
  roles/
    web/
      defaults/main.yml
      tasks/main.yml
      handlers/main.yml
      templates/Caddyfile.j2
  .gitignore            # contains: .vault_pass
```

`git add` চালানোর আগে যাচাই করো যে `secrets.yml` খুললে `$ANSIBLE_VAULT;...` দেখায়, আর `.vault_pass` `.gitignore`-এ তালিকাভুক্ত। দ্রুত নিজেকে পরীক্ষা: file-টা সাধারণ text হিসেবে খোলো আর password পড়ার চেষ্টা করো।

## ৬. Checklist

1. প্রতিটা পুনরাবৃত্ত কাজের block কি একটা role, আর setting গুলো `defaults/main.yml`-এ?
2. যে মান server ভেদে আলাদা তা কি variable ও template-এ, file-এর কপিতে নয়?
3. প্রতিটা secret কি Vault-encrypted file (বা `encrypt_string` মান)-এ, কখনো সাদা text-এ নয়?
4. Vault password file কি `.gitignore`-এ, আর শুধু আমার পড়ার মতো?
5. Secret ছোঁয় এমন প্রতিটা task-এ কি `no_log: true` আছে?
6. আমি কি `--syntax-check`, তারপর `--check --diff`, তারপর apply, তারপর `changed=0` দেখানো দ্বিতীয় run চালিয়েছি (Chapter 3)?

## ৭. নিজে যাচাই করো

<details>
<summary><strong>Q1. Role কী, আর এটা কিসের জন্য?</strong></summary>

একটা প্রমিত folder কাঠামো (tasks, handlers, templates, files, defaults, vars, meta) যা সম্পর্কিত কাজ প্যাকেজ করে, যাতে playbook এক লাইনে তা পুনর্ব্যবহার করতে পারে।
</details>

<details>
<summary><strong>Q2. ব্যবহারকারী বদলাতে চাইতে পারে এমন setting কোথায় যাবে, আর কেন?</strong></summary>

`defaults/main.yml`-এ, কারণ defaults-এর অগ্রাধিকার সবচেয়ে কম আর ওভাররাইড করা সবচেয়ে সহজ, আর এটা role-এর setting গুলো এক জায়গায় নথিবদ্ধ করে।
</details>

<details>
<summary><strong>Q3. `copy` আর `template`-এর পার্থক্য কী?</strong></summary>

`copy` সব জায়গায় একই file পাঠায়। `template` আগে control node-এ একটা Jinja2 file variable দিয়ে ভরে, তাই প্রতিটা machine একটু আলাদা ফল পেতে পারে।
</details>

<details>
<summary><strong>Q4. Jinja2 template-এ `{{ }}`, `{% %}` ও `{# #}` মানে কী?</strong></summary>

একটা expression যা মান print করে, `for` বা `if`-এর মতো একটা statement, আর একটা comment যা output-এ থাকে না।
</details>

<details>
<summary><strong>Q5. `template` module-এ `validate` কী করে?</strong></summary>

Rendered অস্থায়ী file-এর (`%s` হিসেবে দেওয়া) ওপর একটা check command চালায়, file জায়গামতো copy করার আগে, যাতে ভাঙা config কাজের config-কে প্রতিস্থাপন না করে।
</details>

<details>
<summary><strong>Q6. Vault যা encrypt করতে পারে এমন দুটো জিনিস, আর তাদের মধ্যে আপস বলো।</strong></summary>

পুরো file বা একটা মান। পুরো file encrypt করলে variable-এর নামও লুকায় আর task-ও ঢাকা যায়; একটা মান encrypt করলে file পড়া যায় কিন্তু শুধু ওই মানটাই ঢাকা পড়ে।
</details>

<details>
<summary><strong>Q7. Vault password file-এর তিনটা নিয়ম বলো।</strong></summary>

এতে password এক লাইনে থাকে (বা এটা এমন executable script যা তা print করে), এর permission কড়া হতে হবে, আর এটা কখনো source control-এ যোগ করা যাবে না (`.gitignore`-এ রাখো)।
</details>

<details>
<summary><strong>Q8. `no_log: true` কী করে, আর এর সীমা কী?</strong></summary>

এটা একটা task-এর সংবেদনশীল মান Ansible-এর output ও log থেকে দূরে রাখে। এটা debugging output-কে প্রভাবিত করে না, তাই production-এ debug করো না বা `debug` দিয়ে secret print করো না।
</details>

## ৮. বিনামূল্যের resource

- Ansible docs: [Roles](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_reuse_roles.html)
- Ansible docs: [template module](https://docs.ansible.com/ansible/latest/collections/ansible/builtin/template_module.html) ও [Templating (Jinja2)](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_templating.html)
- Ansible docs: [Loops](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_loops.html)
- Jinja2: [Template designer documentation](https://jinja.palletsprojects.com/en/stable/templates/)
- Ansible Vault: [Vault guide](https://docs.ansible.com/ansible/latest/vault_guide/index.html), [Encrypting content](https://docs.ansible.com/ansible/latest/vault_guide/vault_encrypting_content.html), [Using encrypted content](https://docs.ansible.com/ansible/latest/vault_guide/vault_using_encrypted_content.html), [Managing vault passwords](https://docs.ansible.com/ansible/latest/vault_guide/vault_managing_passwords.html)
- Ansible docs: [Logging ও `no_log`](https://docs.ansible.com/ansible/latest/reference_appendices/logging.html)

---

**পরের chapter:** [Chapter 5 — Ansible variable, fact, condition ও register](05-ansible-variables-facts-and-conditions.md)
