# Chapter 6 — Laravel stack-এর জন্য Ansible role: PHP, Caddy, MySQL

> **Course:** Infrastructure as Code & Ansible · **Chapter:** 6 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/iac/06-ansible-laravel-stack-roles)

এবার যা শিখেছ সব কাজে লাগিয়ে আসল কিছু বানাও: একটা **Laravel** application-এর যে server-software লাগে, তিনটা পুনর্ব্যবহারযোগ্য role হিসেবে লেখা। **PHP** code চালায়, **Caddy** তা দুনিয়াকে পরিবেশন করে, আর **MySQL** ডেটা রাখে। শেষে তোমার কাছে এমন একটা playbook থাকবে যা একটা ফাঁকা Ubuntu server-কে Laravel-এর জন্য তৈরি বানায়, প্রতিবার একই ভাবে, database password Vault-এ রেখে।

> **সততার নোট: কী চালানো হয়েছে আর কী হয়নি।** এই course লেখার server-এ Ansible **install করা নেই**, কোনো remote server configure করা হয়নি, আর এর জন্য কিছু install করাও হয়নি। তাই নিচের role গুলো সরকারি documentation থেকে লেখা এবং **এখানে চালানো হয়নি**। যা *যাচাই করা হয়েছে*: Laravel 13-এর PHP version ও extension-এর চাহিদা আর তার directory-permission-এর পরামর্শ, Ubuntu 24.04 ("noble") `caddy`, `php8.3-fpm`, `mysql-server` ও `python3-pymysql` package করে কি না, `php8.3-xml`, `-mbstring`, `-curl` ও `-mysql` package কী দেয় (তাদের package-বর্ণনা থেকে), Caddy-র `php_fastcgi` directive, Caddy-র official apt repository-র command, `ansible.mysql.mysql_db` ও `mysql_user` module (তাদের parameter ও PyMySQL-এর চাহিদা), `ansible-galaxy` দিয়ে collection install, আর প্রতিটা YAML block যে বৈধ YAML (tool দিয়ে parse করা); প্রতিটা বাইরের link খুলে দেখা হয়েছে। **Package-এর নাম, version ও module-এর নাম বদলায়**: উদ্ধৃত version গুলো এই chapter লেখার সময় Ubuntu package search যা দেখিয়েছিল। সবকিছু আগে একটা **practice server**-এ চালাও যা ফেলে দেওয়া যায়, আগে `--check` দিয়ে, আর বিশ্বাস না করা পর্যন্ত আসল secret দিয়ে কখনো নয়।

![তিনটা role Laravel stack বানায়: Caddy পরিবেশন করে, PHP-FPM code চালায়, MySQL ডেটা রাখে](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/08-iac/images/laravel-stack-roles.svg)

## ১. আমরা কী বানাচ্ছি, আর কেন এই পছন্দ

একটা অনুরোধ এভাবে চলে: browser **Caddy**-তে পৌঁছায় port 80 ও 443-এ; Caddy PHP অনুরোধ **PHP-FPM**-কে একটা স্থানীয় socket দিয়ে পাঠায়; PHP code (Laravel) **MySQL** পড়ে ও লেখে। প্রতিটা অংশ একটা role (Chapter 4), তাই প্রতিটা পুনর্ব্যবহারযোগ্য ও আলাদাভাবে পরীক্ষাযোগ্য।

**লক্ষ্য system: Ubuntu 24.04 ("noble")।** একটা system বাছলে chapter সৎ থাকে। ওই release-এ Ubuntu-র নিজস্ব repository-তে যা লাগে তা আছে, Ubuntu package search যেমন দেখিয়েছে: `caddy` 2.6.2 (universe), `php8.3-fpm` 8.3.6, `mysql-server` 8.0.x আর `python3-pymysql`। Distribution package ব্যবহার মানে সাধারণ `apt` module (Chapter 3) যথেষ্ট, আর system-এর security update খাটে। জানার মতো আপস: **distribution-এর Caddy পুরনো**, Caddy-র নিজের apt repository-র চেয়ে। সবচেয়ে নতুন release চাইলে ওই repository-র official নির্দেশনা Caddy docs-এ আছে (resource দেখো); তারা একটা signing key ও একটা package source যোগ করে, তাই `sudo` ও যত্ন লাগে।

**কেন PHP 8.3?** Laravel 13 deployment documentation বলে: "Laravel 13-এর PHP 8.3 বা তার বেশি লাগে", আর যে PHP extension লাগে তা তালিকাভুক্ত করে: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer ও XML। Ubuntu 24.04-এর PHP 8.3 version-এর চাহিদা মেটায়।

Chapter 2 থেকে 5-এর ওপর গড়া project কাঠামো:

```text
ansible/
├── ansible.cfg
├── inventory.ini
├── requirements.yml          # the ansible.mysql collection
├── site.yml
├── secrets.yml               # ENCRYPTED with ansible-vault (Chapter 4)
├── group_vars/
│   └── web.yml
└── roles/
    ├── php/
    │   ├── defaults/main.yml
    │   └── tasks/main.yml
    ├── caddy/
    │   ├── defaults/main.yml
    │   ├── tasks/main.yml
    │   ├── handlers/main.yml
    │   └── templates/Caddyfile.j2
    └── mysql/
        ├── defaults/main.yml
        └── tasks/main.yml
```

## ২. Role `php`

`roles/php/defaults/main.yml` setting রাখে, version এক জায়গায় (Chapter 4: defaults ওভাররাইড করা সবচেয়ে সহজ):

```yaml
php_version: "8.3"

php_packages:
  - "php{{ php_version }}-fpm"
  - "php{{ php_version }}-cli"
  - "php{{ php_version }}-curl"
  - "php{{ php_version }}-mbstring"
  - "php{{ php_version }}-mysql"
  - "php{{ php_version }}-xml"

php_required_modules:
  - ctype
  - curl
  - dom
  - fileinfo
  - filter
  - hash
  - mbstring
  - openssl
  - pcre
  - pdo
  - session
  - tokenizer
  - xml
```

`php_required_modules` হলো **Laravel-এর নিজের তালিকা**। Package গুলোর মধ্যে: Ubuntu-র বর্ণনা অনুযায়ী `php8.3-xml` "DOM, SimpleXML, XML, and XSL" দেয়, `php8.3-mbstring` "MBSTRING module", `php8.3-curl` "CURL module", আর `php8.3-mysql` "MySQL module for PHP", যা Laravel MySQL-এর সাথে কথা বলতে ব্যবহার করে। বাকি দরকারি module (Ctype, Fileinfo, Tokenizer, Session ইত্যাদি) সাধারণত PHP-র মূল package-এর সাথে আসে, কিন্তু **package page গুলো সেগুলো তালিকাভুক্ত করেনি**, তাই role ধরে না নিয়ে **যাচাই করে** (নিচে)।

`roles/php/tasks/main.yml`:

```yaml
- name: PHP and the extensions Laravel needs are installed
  ansible.builtin.apt:
    name: "{{ php_packages }}"
    state: present
    update_cache: true
    cache_valid_time: 3600

- name: PHP-FPM is running
  ansible.builtin.service:
    name: "php{{ php_version }}-fpm"
    state: started

- name: List the PHP modules that are loaded
  ansible.builtin.command: php -m
  register: php_modules
  changed_when: false

- name: Fail if a PHP module Laravel needs is missing
  ansible.builtin.fail:
    msg: "PHP module missing: {{ item }}"
  loop: "{{ php_required_modules }}"
  when:
    - php_modules.stdout_lines is defined
    - item not in (php_modules.stdout_lines | map('lower') | list)
```

শেষ দুটো task Chapter 5-কে মনে রেখে পড়ো। প্রথম condition, `is defined`, check mode থেকে রক্ষা করে: সেখানে একটা `command` task **skip** হয়, তাই তুলনা করার মতো output নেই, আর ব্যর্থ হওয়ার বদলে যাচাইটাও skip হয়। `php -m` শুধু **পড়ে**, তাই এতে `changed_when: false` আছে আর তার ফল **register** করা। তারপর একটা `fail` task Laravel-এর তালিকার ওপর loop করে, আর কোনো module load না থাকলে একটা পরিষ্কার বার্তা দিয়ে থামে (`when` প্রতিটা আইটেমের জন্য একবার চলে, loop-এর জন্য docs যেমন বলে)। `map('lower')` তুলনায় বড় হাতের অক্ষর উপেক্ষা করায়, কারণ `php -m` কিছু নাম বড় হাতের অক্ষরে print করে (যেমন `PDO`)। Filter-টা সাধারণ Jinja2; তোমার practice server-এ নিশ্চিত করো। এটা "production-এ কিছু অদ্ভুতভাবে ব্যর্থ হচ্ছে"-কে "playbook আমাকে ঠিক বলেছে কী নেই" বানায়।

Server-এ `php -v` version দেখায় (Laravel docs-ও এটা ব্যবহার করে)। এটা 8.3 বা তার বেশি হতে হবে।

## ৩. Role `caddy`

`roles/caddy/defaults/main.yml`:

```yaml
site_domain: ":80"
app_dir: /var/www/myapp
php_version: "8.3"
```

(`:80` port 80-তে সাধারণ HTTP পরিবেশন করে, যা domain ছাড়া practice server-এর জন্য সুবিধাজনক। আসল domain-এর জন্য `site_domain` তাতে সেট করো, DNS server-এর দিকে নির্দেশ করা অবস্থায়, আর Caddy তার জন্য স্বয়ংক্রিয়ভাবে HTTPS certificate নেয়; site address নিয়ে Caddy-র docs দেখো।)

`roles/caddy/templates/Caddyfile.j2`: PHP-র জন্য Caddy-র directive `php_fastcgi`। Docs একে বলে "একটা সুবিধাজনক শর্টকাট, যা একটা লম্বা configuration প্রতিস্থাপন করে", আর বলে এটা "প্রায় সবসময় `root` directive-এর সাথে জোড়া হওয়া উচিত" এবং `file_server`। Docs-এর উদাহরণ socket রূপ `php_fastcgi unix//run/php/php8.2-fpm.sock`, আর Laravel-এর নিজস্ব Nginx উদাহরণ একটা `php8.3-fpm.sock` socket ব্যবহার করে, তাই 8.3-এর জন্য আমরা লিখি:

```text
{{ site_domain }} {
    root * {{ app_dir }}/public
    encode gzip
    php_fastcgi unix//run/php/php{{ php_version }}-fpm.sock
    file_server
}
```

`root * {{ app_dir }}/public` লক্ষ্য করো: web root হলো application-এর **`public`** directory। Laravel docs জোর দিয়ে বলে: তোমার server-কে অনুরোধ `public/index.php`-তে পাঠাতে হবে, আর "`index.php` কখনো তোমার project-এর root-এ সরানোর চেষ্টা করো না, কারণ project root থেকে পরিবেশন করলে অনেক সংবেদনশীল configuration file public internet-এ উন্মুক্ত হয়।"

`roles/caddy/tasks/main.yml`:

```yaml
- name: Caddy is installed
  ansible.builtin.apt:
    name: caddy
    state: present
    update_cache: true
    cache_valid_time: 3600

- name: Caddyfile is in place
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

`roles/caddy/handlers/main.yml`:

```yaml
- name: Restart caddy
  ansible.builtin.service:
    name: caddy
    state: restarted
```

Caddy docs অনুযায়ী package install করলে "স্বয়ংক্রিয়ভাবে Caddy-কে caddy নামের একটা systemd service হিসেবে চালু করে"। Docs page Caddyfile-এর path-এর নাম বলে না, তাই `/etc/caddy/Caddyfile`-এর ওপর ভরসার আগে তোমার server-এ service যে file পড়ে তা **নিশ্চিত করো** (যেমন `systemctl cat caddy` দিয়ে)। চালু site-এ restart করার আগে Caddy-র নিজের validation command দিয়ে file-এর syntax যাচাই করো (Caddy-র documentation দেখো)। Chapter 4-এর মতো, এমন যাচাই জুড়তে `template` module-এর `validate` option-ই জায়গা।

## ৪. Role `mysql`

MySQL-এ আরও দুটো জিনিস লাগে। যে Ansible module database ও user বানায় সেগুলো **`ansible.mysql`** collection-এ (built-in নয়), আর সেগুলোর **managed** server-এ **PyMySQL** Python package লাগে: docs বলে "remote host-এ PyMySQL package install থাকা লাগে।"

Docs থেকে নামের একটা নোট: এই module গুলো আগে `community.mysql`-এ ছিল, আর ওই collection-এর পুরনো module-নাম এখন `ansible.mysql`-এর দিকে নির্দেশ করা **deprecated redirect**। নতুন নাম ব্যবহার করো। Collection-এর নাম আবার বদলাতে পারে, তাই Ansible documentation দেখে নাও।

তোমার **control node**-এ একটা requirements file দিয়ে (docs-এর রূপ) collection install করো, তারপর একটা command চালাও:

`requirements.yml`:

```yaml
collections:
  - ansible.mysql
```

```bash
ansible-galaxy collection install -r requirements.yml
```

Docs অনুযায়ী ডিফল্টে এটা `~/.ansible/collections`-এর নিচে install হয়।

`roles/mysql/defaults/main.yml`:

```yaml
db_name: laravel
db_user: laravel
```

`roles/mysql/tasks/main.yml`:

```yaml
- name: MySQL server and the Python driver are installed
  ansible.builtin.apt:
    name:
      - mysql-server
      - python3-pymysql
    state: present
    update_cache: true
    cache_valid_time: 3600

- name: MySQL is running
  ansible.builtin.service:
    name: mysql
    state: started

- name: The application database exists
  ansible.mysql.mysql_db:
    name: "{{ db_name }}"
    state: present
    encoding: utf8mb4
    login_unix_socket: /run/mysqld/mysqld.sock

- name: The application database user exists
  ansible.mysql.mysql_user:
    name: "{{ db_user }}"
    password: "{{ vault_db_password }}"
    priv: "{{ db_name }}.*:ALL"
    state: present
    login_unix_socket: /run/mysqld/mysqld.sock
  no_log: true
```

Docs থেকে কী ঘটছে। `mysql_db` database-এর `name`, একটা `state` (`present`), একটা `encoding`, আর `login_unix_socket` নেয়: "স্থানীয় সংযোগের জন্য একটা Unix domain socket-এর path", যা `Please explicitly state intended protocol` error-ও এড়ায়। `mysql_user` একটা `name`, একটা `password`, একটা `host` (ডিফল্ট `localhost`), আর `db.table:priv1,priv2` রূপে `priv` নেয়, তাই `laravel.*:ALL` মানে `laravel` database-এর প্রতিটা table-এ সব অধিকার আর আর কিছু না। User শুধু **একটা database**-এ access পায়, পুরো server-এ নয়: আবার সর্বনিম্ন ক্ষমতা। `update_password`-এর ডিফল্ট `always`, যা প্রতি run-এ password-কে তোমার Vault-এর মানের সাথে মিলিয়ে রাখে।

Secret নিয়ে দুটো কথা (Chapter 4)। Password আসে `vault_db_password` থেকে, একটা **encrypted** variable, আর task-এ `no_log: true` আছে যাতে password print না হয়। মনে রেখো `no_log` `debug` output লুকায় না, আর `mysql_user` documentation-এর নিজস্ব উদাহরণ একটা সাধারণ-text password ব্যবহার করে, যা তুমি **কখনো** কপি করবে না। Module গুলো MySQL-এর administrator হিসেবে কীভাবে ঢোকে তা তোমার installation-এর ওপর নির্ভর করে (অনেক Ubuntu installation-এ MySQL-এর root account স্থানীয় socket দিয়ে operating-system root-কে গ্রহণ করে, কিন্তু **তোমারটা দেখো**)। এখানে কিছুই MySQL-কে network-এ খোলে না; এটা এভাবেই রাখো, আর তোমার firewall-এ port 3306 বন্ধ রাখো (Terraform course, Chapter 5)।

## ৫. Role গুলো জোড়া

`group_vars/web.yml` (Chapter 5):

```yaml
app_dir: /var/www/myapp
site_domain: ":80"
php_version: "8.3"
db_name: laravel
db_user: laravel
```

`secrets.yml` `ansible-vault` দিয়ে encrypted (Chapter 4) আর এক লাইন ধরে, এখানে একটা **নকল** মান:

```yaml
vault_db_password: not-a-real-password
```

`site.yml`:

```yaml
- name: Build the Laravel stack
  hosts: web
  become: true
  vars_files:
    - secrets.yml
  roles:
    - php
    - mysql
    - caddy
```

Role গুলো তালিকার ক্রমে চলে (Chapter 4)। `php` `caddy`-র আগে কারণ Caddy PHP-FPM-এর socket-এর সাথে কথা বলে। লক্ষ্য করো playbook কত ছোট: সব বিস্তারিত role-এ থাকে, আর **server ভেদে পার্থক্য** থাকে `group_vars` ও `host_vars`-এ।

`ansible` folder থেকে, একটা practice server-এ নিরাপদ উপায়ে চালাও:

```bash
ansible-galaxy collection install -r requirements.yml
ansible web -m ping
ansible-playbook site.yml --syntax-check
ansible-playbook site.yml --ask-vault-pass --check --diff
ansible-playbook site.yml --ask-vault-pass
ansible-playbook site.yml --ask-vault-pass        # second run: expect changed=0
```

`--check` নিয়ে দুটো সতর্কতা: `php -m` চালানো `command` task check mode-এ skip হয় (তাই `is defined` পাহারার কারণে module যাচাইও skip হয়), আর অনেক task এমন package-এর ওপর নির্ভর করে যা dry run install করেনি। এটা স্বাভাবিক। **দ্বিতীয় আসল run-এ `changed=0`** হলো idempotency-র প্রমাণ।

## ৬. Laravel app-এর জন্য এরপর কী

Stack তৈরি, কিন্তু **application** এখনো deploy হয়নি। Chapter 8 সেটা করে। Laravel docs থেকে সামনে নেওয়ার তথ্য: Laravel-কে `bootstrap/cache` ও `storage`-এ **লিখতে** হয়, তাই web server-এর process-মালিকের সেখানে অনুমতি লাগে (Ubuntu-তে PHP-FPM-এর pool user সাধারণত `www-data`; তোমার pool configuration-এ নিশ্চিত করো)। Production-এ `APP_DEBUG` `false` হতে হবে। আর deployment-এ `php artisan optimize`-এর মতো optimisation command চালানো উচিত (যা configuration, event, route ও view cache করে) এবং নতুন code deploy করার পরে দীর্ঘস্থায়ী worker restart করা।

## ৭. অভ্যাস

1. **Stack-এর প্রতিটা অংশের জন্য একটা role,** তার setting `defaults/`-এ।
2. **ধরে নিও না, যাচাই করো:** `php -m` loop প্রমাণ করে Laravel-এর দরকারি extension সত্যিই আছে।
3. **Database-এ সর্বনিম্ন ক্ষমতা:** একটা user, একটা database, `db.*:ALL`।
4. **Secret Vault-এ, যে task সেগুলো ধরে তাতে `no_log`,** আর উদাহরণ থেকে সাধারণ-text password কখনো কপি করো না।
5. **MySQL network-এ খুলো না;** port 3306 firewall-এ বন্ধ রাখো।
6. **Web root `public`,** project root কখনো নয়।
7. **আগে practice server-এ চালাও,** আগে `--check`, আর দ্বিতীয় run-এ `changed=0` আশা করো।

## ৮. নিজে যাচাই করো

<details>
<summary><strong>Q1. Web root কেন `.../public`, project root নয়?</strong></summary>

Laravel docs বলে সব অনুরোধ `public/index.php`-তে যেতে হবে, আর project root থেকে পরিবেশন করলে সংবেদনশীল configuration file public internet-এ উন্মুক্ত হবে।
</details>

<details>
<summary><strong>Q2. Caddy-তে `php_fastcgi unix//run/php/php8.3-fpm.sock` কী করে?</strong></summary>

এটা PHP অনুরোধ PHP-FPM-কে তার Unix socket দিয়ে পাঠায়। এটা একটা লম্বা configuration-এর শর্টকাট, আর সাধারণত `root` ও `file_server`-এর সাথে জোড়া হয়।
</details>

<details>
<summary><strong>Q3. `php` role কেন `php -m` চালায় আর module না থাকলে ব্যর্থ হয়?</strong></summary>

Package-এর বর্ণনা Laravel-এর দরকারি প্রতিটা module তালিকাভুক্ত করেনি, তাই role Laravel-এর তালিকা load করা module-এর সাথে মেলায় আর পরে অদ্ভুতভাবে ব্যর্থ হওয়ার বদলে একটা পরিষ্কার বার্তা দিয়ে থামে।
</details>

<details>
<summary><strong>Q4. `php -m` task-এ কেন `changed_when: false`?</strong></summary>

এটা শুধু পড়ে। এটা ছাড়া `command` সবসময় changed জানায় আর দ্বিতীয় run `changed=0`-তে পৌঁছাত না।
</details>

<details>
<summary><strong>Q5. MySQL module গুলোর managed server-এ কী লাগে, আর module কোথা থেকে আসে?</strong></summary>

Managed host-এ PyMySQL Python package, আর module আসে `ansible.mysql` collection থেকে (`ansible-galaxy collection install` দিয়ে install করা), যা deprecated `community.mysql` নাম প্রতিস্থাপন করেছে।
</details>

<details>
<summary><strong>Q6. `priv: "laravel.*:ALL"` কী দেয়?</strong></summary>

শুধু `laravel` database-এর প্রতিটা table-এ সব অধিকার, যা application-এর user-এর জন্য সর্বনিম্ন ক্ষমতা।
</details>

<details>
<summary><strong>Q7. Database password কোথা থেকে আসে, আর output থেকে কীভাবে দূরে রাখা হয়?</strong></summary>

Encrypted Vault variable `vault_db_password` থেকে, আর task-এ `no_log: true` আছে। `no_log` `debug` output লুকায় না, তাই কখনো print করো না।
</details>

<details>
<summary><strong>Q8. `site.yml`-এ কেন `caddy` role `php`-এর পরে চলে?</strong></summary>

Role গুলো তালিকার ক্রমে চলে, আর Caddy PHP অনুরোধ PHP-FPM-এর socket-এ পাঠায়, তাই PHP আগে install ও চালু থাকা উচিত।
</details>

## ৯. বিনামূল্যের resource

- Laravel docs: [Deployment](https://laravel.com/docs/deployment)
- Caddy docs: [Install](https://caddyserver.com/docs/install) ও [`php_fastcgi`](https://caddyserver.com/docs/caddyfile/directives/php_fastcgi)
- Ubuntu package: [`caddy`](https://packages.ubuntu.com/search?keywords=caddy&searchon=names&suite=noble&section=all), [`php8.3-fpm`](https://packages.ubuntu.com/search?keywords=php8.3-fpm&searchon=names&suite=noble&section=all), [`mysql-server`](https://packages.ubuntu.com/search?keywords=mysql-server&searchon=names&suite=noble&section=all)
- Ansible docs: [`ansible.mysql.mysql_db`](https://docs.ansible.com/ansible/latest/collections/ansible/mysql/mysql_db_module.html), [`ansible.mysql.mysql_user`](https://docs.ansible.com/ansible/latest/collections/ansible/mysql/mysql_user_module.html), [Installing collections](https://docs.ansible.com/ansible/latest/collections_guide/collections_installing.html)

---

**পরের chapter:** [Chapter 7 — নিরাপদে Ansible চালানো: tag, limit, lint ও CI](07-ansible-safety-testing-and-ci.md)
