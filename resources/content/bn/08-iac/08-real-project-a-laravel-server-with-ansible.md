# Chapter 8 — Real project: Ansible দিয়ে বানানো একটা Laravel server

> **Course:** Infrastructure as Code & Ansible · **Chapter:** 8 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/iac/08-real-project-a-laravel-server-with-ansible)

এটা শেষ রেখা। Chapter 6-এ তুমি **stack** (PHP, Caddy, MySQL) বানিয়েছ। এই chapter-এ তুমি শেষ role, **`app`**, যোগ করো, যা তার ওপর একটা Laravel application deploy করে: code, তার dependency, তার `.env`, database migration, cache, আর scheduler। তারপর পুরোটা Chapter 7-এর নিরাপদ উপায়ে চালাও। শেষে একটা ফাঁকা Ubuntu server আর একটা command তোমাকে একটা চলমান Laravel site দেয়, প্রতিবার একই ভাবে।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Ansible, Composer ও PHP **install করা নেই**, কোনো server configure করা হয়নি, আর কোনো Laravel application deploy করা হয়নি। তাই নিচের playbook গুলো সরকারি documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: Ansible-এর `git`, `user`, `cron`, `file`, `template`, `command` ও `apt` module আর unprivileged-`become` নিয়ে পরামর্শ, Laravel-এর `.env` ও configuration-cache-এর নিয়ম, `php artisan migrate --force`, `php artisan optimize`, health route `/up`, scheduler-এর একটা মাত্র cron এন্ট্রি, Composer-এর `--no-dev` ও `--optimize-autoloader` option, Ubuntu 24.04 `composer` package করে কি না, আর প্রতিটা YAML block যে বৈধ YAML (tool দিয়ে parse করা); প্রতিটা বাইরের link খুলে দেখা হয়েছে। **আসল deployment-এর output দেখানো হয়নি।** আগে একটা **ফেলনা server**-এ deploy করো, একটা **practice repository** দিয়ে, আর **নকল secret** দিয়ে, আর প্রতিটা command যা print করে তা পড়ো। পুরো pipeline-কে বিশ্বাস না করা পর্যন্ত আসল credential ব্যবহার করো না।

![app role stack-এর ওপর code, dependency, environment file, migration ও cache deploy করে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/08-iac/images/laravel-deploy-role.svg)

## ১. "Deploy করা" মানে কী

একটা server-এ Laravel app বসানো একটা ছোট ধাপের তালিকা, আর প্রতিটা ধাপ তোমার জানা কিছুর সাথে মেলে:

| ধাপ | Ansible-এর tool (আগের chapter) |
|---|---|
| Code-এর মালিক একটা user | `user` module |
| একটা **version**-এ code আনা | `git` module (`version` একটা tag, branch বা commit) |
| `.env` লেখা secret সহ | `template` + Vault + `no_log` (Chapter 4) |
| Dependency install | একটা condition দিয়ে পাহারা দেওয়া Composer চালানো `command` (Chapter 3, 5) |
| `storage` ও `bootstrap/cache` writable করা | `file` module |
| Database schema update | `php artisan migrate --force` চালানো `command` |
| Config, route ও view cache করা | `php artisan optimize` চালানো `command` |
| Scheduled task চালানো | `cron` module |
| প্রমাণ করা যে এটা জীবিত | `/up` health route ডাকা `command` |

নতুন ধারণা কোনো একটা ধাপ নয়। সেটা হলো সেগুলোর **ক্রম আর পাহারা**, যাতে দ্বিতীয় run কিছু না বদলায়, আর একটা variable বদলে একটা **নতুন version** deploy করা যায়।

## ২. কাঠামো

```text
ansible/
├── ansible.cfg
├── inventory.ini            # from the Terraform course (or your own server)
├── requirements.yml
├── site.yml
├── secrets.yml              # ENCRYPTED: vault_db_password, vault_app_key
├── group_vars/
│   └── web.yml
└── roles/
    ├── php/        (Chapter 6)
    ├── mysql/      (Chapter 6)
    ├── caddy/      (Chapter 6)
    └── app/
        ├── defaults/main.yml
        ├── tasks/main.yml
        └── templates/env.j2
```

তুমি Terraform course অনুসরণ করলে তার শেষ chapter তোমার জন্য `inventory.ini` বানিয়েছে: Terraform server বানিয়েছে, আর এই playbook হলো "Ansible"-এর অংশ যা demo `nginx` page-কে আসল stack দিয়ে প্রতিস্থাপন করে।

## ৩. Role `app`: setting

`roles/app/defaults/main.yml`:

```yaml
app_user: deploy
app_dir: /var/www/myapp
app_repo: https://example.com/your-org/your-laravel-app.git
app_version: v1.0.0
app_env: production
app_url: http://localhost
db_name: laravel
db_user: laravel
```

`app_repo` একটা **placeholder**: নিজের একটা practice repository-র দিকে নির্দেশ করো। `app_version` একটা Git **tag**। Docs বলে `git` module-এর `version` "একটা branch-এর নাম, একটা tag-এর নাম" নেয় (আর ডিফল্ট `HEAD`)। **Branch নয়, tag deploy করো:** একটা tag code-এর একটা ঠিক অবস্থা নাম দেয়, তাই "কী চলছে" প্রশ্নের একটা পরিষ্কার উত্তর আছে, আর rollback মানে "পুরনো tag deploy করো"।

## ৪. Role `app`: task

`roles/app/tasks/main.yml`, একটা রেসিপির মতো পড়ো:

```yaml
- name: Tools the deployment needs are installed
  ansible.builtin.apt:
    name:
      - git
      - unzip
      - composer
      - acl
      - curl
    state: present
    update_cache: true
    cache_valid_time: 3600

- name: The deploy user exists
  ansible.builtin.user:
    name: "{{ app_user }}"
    shell: /bin/bash
    state: present

- name: The application directory exists
  ansible.builtin.file:
    path: "{{ app_dir }}"
    state: directory
    owner: "{{ app_user }}"
    group: www-data
    mode: '0755'

- name: The code is checked out at the wanted version
  ansible.builtin.git:
    repo: "{{ app_repo }}"
    dest: "{{ app_dir }}"
    version: "{{ app_version }}"
  become_user: "{{ app_user }}"
  register: app_code

- name: The environment file is in place
  ansible.builtin.template:
    src: env.j2
    dest: "{{ app_dir }}/.env"
    owner: "{{ app_user }}"
    group: www-data
    mode: '0640'
  no_log: true

- name: Composer dependencies are installed
  ansible.builtin.command: composer install --no-dev --optimize-autoloader --no-interaction
  args:
    chdir: "{{ app_dir }}"
  become_user: "{{ app_user }}"
  when: app_code.changed
  changed_when: true

- name: Laravel can write where it must
  ansible.builtin.file:
    path: "{{ app_dir }}/{{ item }}"
    state: directory
    owner: www-data
    group: www-data
    mode: '0775'
    recurse: true
  loop:
    - storage
    - bootstrap/cache

- name: The database schema is up to date
  ansible.builtin.command: php artisan migrate --force
  args:
    chdir: "{{ app_dir }}"
  become_user: "{{ app_user }}"
  register: app_migrate
  changed_when: "'Nothing to migrate' not in app_migrate.stdout"

- name: Configuration, routes and views are cached
  ansible.builtin.command: php artisan optimize
  args:
    chdir: "{{ app_dir }}"
  become_user: "{{ app_user }}"
  when: app_code.changed
  changed_when: true

- name: The Laravel scheduler runs every minute
  ansible.builtin.cron:
    name: "Laravel scheduler"
    user: www-data
    job: "cd {{ app_dir }} && php artisan schedule:run >> /dev/null 2>&1"

- name: The application answers its health route
  ansible.builtin.command: curl --fail --silent --show-error http://127.0.0.1/up
  register: app_health
  changed_when: false
```

### প্রতিটা task কেন এমন দেখতে

**Package।** `composer` Ubuntu 24.04-এর নিজস্ব repository-তে আছে (লেখার সময় version 2.7)। `acl` আছে কারণ Ansible docs অনুযায়ী যখন Ansible-কে একটা **unprivileged** `become_user` হিসেবে module চালাতে হয়, তাকে তার অস্থায়ী file ভাগ করতে হয়, আর docs বলে এটা "`setfacl` command দেয় এমন package install করে সমাধান করা যায়", যা "প্রায়ই `acl` package"। Docs এটাও সতর্ক করে যে unprivileged user হিসেবে চালানো root হিসেবে চালানোর চেয়ে কম নিরাপদ হতে পারে, যা একটা কারণ এই role unprivileged task গুলো কম ও সুনির্দিষ্ট রাখে।

**User ও permission।** `deploy` user code-এর মালিক, আর group `www-data`, Ubuntu-তে PHP-FPM সাধারণত যে user হিসেবে চলে (তোমার pool configuration-এ নিশ্চিত করো)। Laravel-এর documentation বলে web server-এর user-এর জন্য শুধু দুটো জায়গা **writable** হতে হবে: `storage` ও `bootstrap/cache`। তাই code `deploy`-এর মালিকানায় থাকে (app-এর একটা bug নিজের code নিজে বদলাতে পারে না), আর শুধু ওই দুটো directory `www-data`-কে দেওয়া হয়। `recurse` সহ `mode: '0775'` ওখানের সাধারণ file-এও execute bit বসায়; ওই folder-এ নিরীহ, কিন্তু পরে কড়া করতে পারো।

**Code।** `git` module-এ `repo`, `dest` ও `version` আছে, আর `before` ও `after` revision ফেরত দেয়। আমরা ফলটা `app_code` নামে `register` করি, কারণ `app_code.changed` **শুধু নতুন code এলে** সত্য। Docs একটা parameter নিয়ে সতর্ক করে: `accept_hostkey` "MITM আক্রমণের বিরুদ্ধে একটা সুরক্ষা বন্ধ করে", তাই error সরাতে এটা সেট করো না। **Private** repository-র জন্য **শুধু-পড়ার deploy key** ব্যবহার করো (module-এ `key_file` option আছে) আর host key ইচ্ছা করে যোগ করো।

**`.env`** (Composer চলার **আগে** লেখা: একটা নিরাপদ অভ্যাস, কারণ Composer-এর script framework-এর কিছু অংশ boot করতে পারে; তোমার app-এর `composer.json` script দেখো)। Laravel-এর docs কড়া: `.env` file "তোমার application-এর source control-এ commit করা উচিত নয়", কারণ এতে credential থাকে। তাই **template** server-এ Vault-এর মান থেকে এটা বানায় (`no_log: true`, Chapter 4), `0640` ও group `www-data` দিয়ে যাতে web server পড়তে পারে আর বাইরের কেউ পারে না। ঠিক variable-এর নামের জন্য **তোমার নিজের application-এর `.env.example`** guide হিসেবে ব্যবহার করো: Laravel docs বলে একটা নতুন installation-এ এমন একটা থাকে যা "অনেক সাধারণ environment variable সংজ্ঞায়িত করে"।

**Writable folder।** সরাসরি Laravel-এর deployment documentation থেকে: "Laravel-কে `bootstrap/cache` ও `storage` directory-তে লিখতে হবে।"

**Composer।** Docs: `install` `composer.lock` পড়ে আর "সেখান থেকে ঠিক version গুলো" ব্যবহার করে, `--no-dev` শুধু dev-এর package বাদ দেয়, আর `--optimize-autoloader` দ্রুততর autoloader বানায়। আমরা `--no-interaction` ব্যবহার করি যাতে Composer কখনো input-এর জন্য অপেক্ষা না করে। Task-টা `when: app_code.changed` দিয়ে পাহারা দেওয়া, তাই নতুন code না থাকলে দ্বিতীয় run-এ এটা **skip** হয়। `changed_when: true` সৎ: যখন চলে তখন এটা সত্যিই কিছু বদলায়, আর Chapter 7-এর `no-changed-when` lint সন্ধানও থামায়।

**Migration।** Docs: কিছু migration অপারেশন ধ্বংসাত্মক, তাই production-এ Laravel নিশ্চিতকরণ চায়, আর `php artisan migrate --force` সেগুলো **prompt ছাড়া** চালায়। Automation-এ তোমার `--force` লাগে, তাই নিশ্চিত করো একজন মানুষ migration review করেছে (Chapter 7-এর pull request ও `--check` অভ্যাস)। `changed_when` output পড়ে যাতে সত্যিই কিছু চললেই শুধু `changed` জানায়। **ওই output-এর ভাষা ("Nothing to migrate") একটা ধারণা**: তোমার practice server-এ একবার চালাও আর তোমার Laravel version-এর ঠিক শব্দ নিশ্চিত করো।

**Optimize।** Laravel-এর `php artisan optimize` configuration, event, route ও view cache করে আর "সাধারণত তোমার application-এর deployment প্রক্রিয়ার অংশ হিসেবে চালানো উচিত"। Docs-এর একটা সতর্কতা: configuration cache হলে "`.env` file load হবে না", তাই `env()` শুধু configuration file-এর ভেতরে ডাকতে হবে। এটাও `when: app_code.changed` দিয়ে পাহারা দেওয়া।

**Scheduler।** Laravel-এর **একটা** cron এন্ট্রি লাগে যা প্রতি মিনিটে `schedule:run` চালায়। `cron` module-এ একটা অনন্য **`name`** লাগে, যা "idempotency key": module একটা `#Ansible: <name>` comment লেখে আর তা দিয়ে এন্ট্রি আবার খুঁজে পায়, তাই task পুনরাবৃত্তি নিরাপদ। এর `minute`, `hour` ও অন্য field-এর ডিফল্ট `*`, যা প্রতি মিনিটের সময়সূচি দেয়।

**Health।** Laravel-এর অন্তর্নির্মিত health route `/up` "application exception ছাড়া boot হলে 200 HTTP response ফেরত দেবে"। `curl --fail` একটা HTTP ব্যর্থতায় error নিয়ে বের হয়, তাই app সাড়া না দিলে play ব্যর্থ হয়। এটা একটা **অগভীর** পরীক্ষা (এটা database পরীক্ষা করে না); Monitoring course একটা গভীরটা দেখায়। Queue worker ব্যবহার করলে Laravel-এর documentation সেগুলো একটা process monitor-এর নিচে চালানো আর deploy-এর পরে restart করার কথা বলে; সেটা একটা আলাদা role যা তুমি যোগ করতে পারো।

## ৫. Environment template ও secret

`roles/app/templates/env.j2`: এখানে দেওয়া নামগুলো সাধারণ গুলো; **তোমার নিজের `.env.example`-এর সাথে মিলিয়ে নাও**:

```text
APP_NAME=Laravel
APP_ENV={{ app_env }}
APP_KEY={{ vault_app_key }}
APP_DEBUG=false
APP_URL={{ app_url }}

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE={{ db_name }}
DB_USERNAME={{ db_user }}
DB_PASSWORD={{ vault_db_password }}
```

`APP_DEBUG=false` ঐচ্ছিক নয়। Laravel docs: "তোমার production environment-এ এই মান সবসময় `false` হওয়া উচিত", নইলে ব্যবহারকারীর কাছে সংবেদনশীল configuration মান উন্মুক্ত হওয়ার ঝুঁকি থাকে।

`secrets.yml` `ansible-vault` দিয়ে encrypted (Chapter 4)। তার content, **নকল** মান সহ:

```yaml
vault_db_password: not-a-real-password
vault_app_key: "base64:NOT-A-REAL-APP-KEY"
```

Laravel-এর নিজের key command দিয়ে একটা আসল application key বানাও (encryption নিয়ে Laravel documentation দেখো), Vault-এ রাখো, আর **কখনো** commit করো না। একই `vault_db_password` MySQL user (Chapter 6) ও এই `.env` দুটোকেই খাওয়ায়, তাই তারা একমত না হওয়ার উপায় নেই।

## ৬. সব জোড়া

`group_vars/web.yml`:

```yaml
app_dir: /var/www/myapp
site_domain: ":80"
php_version: "8.3"
db_name: laravel
db_user: laravel
app_repo: https://example.com/your-org/your-laravel-app.git
app_version: v1.0.0
app_url: http://localhost
```

`site.yml`, tag সহ (Chapter 7) যাতে stack না ছুঁয়ে **শুধু app** deploy করতে পারো:

```yaml
- name: A Laravel server, built with Ansible
  hosts: web
  become: true
  vars_files:
    - secrets.yml
  roles:
    - role: php
      tags: [php, stack]
    - role: mysql
      tags: [mysql, stack]
    - role: app
      tags: [app]
    - role: caddy
      tags: [caddy, stack]
```

ক্রম গুরুত্বপূর্ণ (Chapter 4): আগে PHP ও MySQL, তারপর app (যার database ও PHP লাগে), তারপর Caddy তা পরিবেশন করতে। প্রথমবার সবকিছু চালাও। পরে **একটা নতুন version deploy করা মানে একটা variable আর একটা tag**:

```bash
ansible-playbook site.yml --ask-vault-pass --tags app -e app_version=v1.1.0
```

`-e`-এর অগ্রাধিকার সর্বোচ্চ (Chapter 3), তাই ওই run-এর জন্য এটা `group_vars` ওভাররাইড করে, আর `--tags app` শুধু `app` role চালায়।

## ৭. নিরাপদ run, Chapter 7 থেকে

একটা **practice server**-এ, এই ক্রমে:

```bash
ansible-galaxy collection install -r requirements.yml
ansible-lint
ansible-playbook site.yml --syntax-check
ansible web -m ping
ansible-playbook site.yml --ask-vault-pass --check --diff           # preview; the deploy tasks may be skipped
ansible-playbook site.yml --ask-vault-pass --limit web1             # a real run on one host
ansible-playbook site.yml --ask-vault-pass                          # the rest
ansible-playbook site.yml --ask-vault-pass                          # second run: expect changed=0
```

**দ্বিতীয় run** কী দেখানো উচিত, আর কেন: Composer ও `optimize` **skip** হয় (নতুন code নেই), git task **ok** জানায়, migration **ok** জানায় (migrate করার কিছু নেই), cron task **ok**, আর health check **ok**। কিছু তবু `changed` বললে কারণ খোঁজো: ওটা এমন একটা task যা idempotent নয়।

মনে রেখো `--check` একটা **preview, নিশ্চয়তা নয়**: check mode-এ `command` task skip হয়, তাই dry run deploy command চলতে দেখাবে না।

## ৮. Rollback

যেহেতু তুমি **tag** deploy করো, ফিরে যাওয়া মানে পুরনো tag দিয়ে একই command:

```bash
ansible-playbook site.yml --ask-vault-pass --tags app -e app_version=v1.0.0
```

এটা **code** ফিরিয়ে আনে। এটা **database migration** স্বয়ংক্রিয়ভাবে ফিরিয়ে নেয় **না**। Laravel migration rollback করতে পারে (তার migrations documentation-এ একটা "Rolling Back Migrations" অংশ আছে), কিন্তু যে migration ডেটা মুছেছে তা ডেটা ফিরিয়ে আনতে পারে না। এজন্যই ধ্বংসাত্মক migration deploy-এর **আগে review** দাবি করে, আর কেন তুমি **database backup** রাখো। Database উপেক্ষা করা rollback পরিকল্পনা অর্ধেক পরিকল্পনা।

## ৯. অন্য course-এর সাথে এটা কোথায় খাপ খায়

- **Terraform course, Chapter 8:** Terraform server, firewall ও key বানায় আর `inventory.ini` লেখে; এই playbook দ্বিতীয় ধাপ। **আগে Terraform, তারপর Ansible** চালাও।
- **Monitoring course, Chapter 8:** ওখানকার পদ্ধতিতে একই server-এ monitoring যোগ করো (health, metric, log, alert)।
- **CI/CD course:** একটা pipeline প্রতিটা pull request-এ Chapter 7-এর lint ও check চালাতে পারে, আর অনুমোদনের পরে deploy tag চালাতে পারে।

## ১০. তুমি কী বানিয়েছ, আর অভ্যাস

এখন তোমার আছে: code দিয়ে Laravel server হয়ে ওঠা একটা ফাঁকা server; Vault-এ secret; **একটা variable আর একটা tag**-এর deploy; একই command-এর rollback; আর নিরাপত্তার স্তর (lint, check, আগে-একটা-host, দ্বিতীয়-run-এ `changed=0`)। অভ্যাসগুলো:

1. **Branch নয়, tag deploy করো,** যাতে প্রতিটা deploy হুবহু পুনরুৎপাদনযোগ্য হয়।
2. **ধীর বা idempotent নয় এমন ধাপ** code বদলেছে কি না তার condition দিয়ে পাহারা দাও।
3. **Code deploy user-এর মালিকানায় রাখো,** আর শুধু `storage` ও `bootstrap/cache` web server-কে দাও।
4. **`.env` server-এ Vault থেকে বানাও,** কখনো commit করো না, production-এ সবসময় `APP_DEBUG=false`।
5. **Migration deploy-এর আগে review করো,** আর database backup রাখো।
6. **Health route দিয়ে প্রমাণ করো এটা জীবিত,** আর জেনে রাখো এটা অগভীর।
7. **আগে ফেলনা server-এ অনুশীলন করো,** practice repository ও নকল secret দিয়ে।
8. **আগে একটা host, তারপর বাকিরা, আর দ্বিতীয় run `changed=0` বলতে হবে।**

## ১১. নিজে যাচাই করো

<details>
<summary><strong>Q1. কেন branch-এর বদলে একটা Git tag deploy করবে?</strong></summary>

একটা tag code-এর একটা ঠিক অবস্থা নাম দেয়, তাই কী চলছে তা অস্পষ্ট নয় আর rollback মানে শুধু পুরনো tag deploy করা। Branch সরে যায়।
</details>

<details>
<summary><strong>Q2. Composer task কেন `when: app_code.changed` দিয়ে পাহারা দেওয়া?</strong></summary>

এটা শুধু নতুন code এলে চালাতে হয়। কিছু না বদলালে দ্বিতীয় run-এ এটা skip হয়, যা run-কে `changed=0`-তে রাখে আর সময় বাঁচায়।
</details>

<details>
<summary><strong>Q3. Web server-এর user-এর কোন দুটো Laravel directory-তে লিখতে পারা চাই?</strong></summary>

`storage` ও `bootstrap/cache`। বাকি code deploy user-এর মালিকানায় থাকতে পারে।
</details>

<details>
<summary><strong>Q4. `.env` কেন commit না করে server-এ template ও Vault থেকে বানানো হয়?</strong></summary>

Laravel-এর docs বলে `.env` commit করা উচিত নয় কারণ এতে credential থাকে। Template deploy-এর সময় encrypted Vault-এর মান থেকে এটা ভরে, আর `no_log` মানগুলো output থেকে দূরে রাখে।
</details>

<details>
<summary><strong>Q5. `php artisan migrate --force` কী করে, আর playbook-এর কেন এটা লাগে?</strong></summary>

এটা production-এ Laravel যে নিশ্চিতকরণ prompt দেখায় তা ছাড়া migration চালায়। Automation prompt-এর উত্তর দিতে পারে না, কিন্তু এর মানে একটা ধ্বংসাত্মক migration-ও চলবে, তাই migration আগে review করতে হবে।
</details>

<details>
<summary><strong>Q6. `cron` task-এ কেন একটা `name` লাগে?</strong></summary>

Name হলো idempotency key। Module এটা দিয়ে একটা comment লেখে আর এন্ট্রি আবার খুঁজে পায়, তাই task পুনরাবৃত্তি করলে নকল তৈরি হয় না।
</details>

<details>
<summary><strong>Q7. PHP, MySQL বা Caddy না ছুঁয়ে একটা নতুন version কীভাবে deploy করবে?</strong></summary>

`ansible-playbook site.yml --ask-vault-pass --tags app -e app_version=<new-tag>`। Tag run-কে `app` role-এ সীমিত করে আর `-e` version ওভাররাইড করে।
</details>

<details>
<summary><strong>Q8. Code rollback কী ফিরিয়ে নেয় না, আর কী তোমাকে রক্ষা করে?</strong></summary>

এটা database migration, বিশেষ করে ডেটা মুছে ফেলা migration, ফিরিয়ে নেয় না। Deploy-এর আগে migration review করো আর database backup রাখো।
</details>

## ১২. বিনামূল্যের resource

- Laravel docs: [Deployment](https://laravel.com/docs/deployment), [Configuration](https://laravel.com/docs/configuration), [Migrations](https://laravel.com/docs/migrations), [Task scheduling](https://laravel.com/docs/scheduling), [Queues](https://laravel.com/docs/queues)
- Composer docs: [Command-line interface (`install`)](https://getcomposer.org/doc/03-cli.md)
- Ansible docs: [`git`](https://docs.ansible.com/ansible/latest/collections/ansible/builtin/git_module.html), [`user`](https://docs.ansible.com/ansible/latest/collections/ansible/builtin/user_module.html), [`cron`](https://docs.ansible.com/ansible/latest/collections/ansible/builtin/cron_module.html), [Privilege escalation (`become`)](https://docs.ansible.com/ansible/latest/playbook_guide/playbooks_privilege_escalation.html)

## এরপর কোথায়

তুমি **Infrastructure as Code & Ansible** course শেষ করেছ। আটটা chapter quiz পাস করার পরে website-এ **final exam** দাও। আরও এগোতে: **Terraform** course নাও আর তার শেষ chapter দিয়ে দুটোকে জোড়ো, আর তুমি যা বানিয়েছ তা দেখতে **Monitoring** course।

---

**পরের ধাপ:** Infrastructure as Code & Ansible-এর final exam, website-এ, আটটা chapter quiz পাস করার পরে।
