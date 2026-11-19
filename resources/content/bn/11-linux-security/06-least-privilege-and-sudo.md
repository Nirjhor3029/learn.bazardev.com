# Chapter 6 — Least Privilege: sudo Policy, Service Account, আর File Permission

> **Course:** Linux Server Security & Secrets · **Chapter:** 6 of 8
> 🇬🇧 **English পড়তে চান?** [English এ পড়ুন →](https://learn.bazardev.com/linux-security/06-least-privilege-and-sudo)

> **Honesty note:** Section 2 আর 6-এ `ls -la` আর `umask` আসলেই চালানো হয়েছে, read-only, এই course-এর নিজের writing server-এর বিরুদ্ধে — সেই output real। Deploy-focused worked example (একটা `deploy` user, একটা `laravel.log` permission error, `sudoers.d` snippet) official documentation থেকে লেখা illustrative scenario, কোনো real incident-এর transcript না। কোনো `chmod`/`chown`/`sudoers` *write* command writing server-এ apply করা হয়নি, এখন পর্যন্ত প্রতিটা `sudo`-related change-এর মতো একই কারণে: একটা ভাঙা sudo rule একটা ভাঙা SSH config-এর মতোই disruptive হতে পারে। `chmod(1)`, `chown(1)`, `umask(1p)`, `sudoers(5)`, আর `visudo(8)` manual page, সাথে Ubuntu আর Laravel-এর নিজের documentation থেকে নেওয়া, সব শেষে link দেওয়া।

এখন পর্যন্ত প্রতিটা chapter *access* remove বা restrict করেছে — কে login করতে পারবে, কোথা থেকে, কোন factor দিয়ে। এই chapter একটা আলাদা কিছু restrict করে: **কেউ বা কিছু একবার server-এ এসে গেলে, এটা আসলে ঠিক কী touch করতে পারবে।** এটা এই পুরো course-এর সবচেয়ে বেশি-ব্যবহৃত skill — permission error-এর সাথে তুমি বারবার মোকাবিলা করবে, সাধারণত সবচেয়ে খারাপ সময়ে (deploy-এর মাঝখানে, demo-র মাঝখানে), তাই এই chapter বেশিরভাগের চেয়ে গভীরে যায়।

## ১. Principle: least privilege, আর এটা কেন গুরুত্বপূর্ণ এমনকি কিছু "ভুল" না হলেও

**Least privilege** মানে একজন person, একটা process, বা একটা account-কে ঠিক তার কাজের জন্য যা লাগে তা দেওয়া — বেশি না। এর payoff মানুষকে কম trust করার সাথে সম্পর্কিত না; এটা **blast radius** নিয়ে: একটা web app-এ bug থাকলে, একটা deploy script-এ typo থাকলে, একটা account-এর credential কখনো চুরি হলে (Chapter 5), damage সীমিত থাকে ওই *specific* account যা আগে থেকেই touch করতে পারতো তার মধ্যে। একটা web server process যা শুধু application code পড়তে পারে আর ঠিক দুটো folder-এ লিখতে পারে, সেটা application-এ চলা যা-ই ভুল হোক, accidentally (বা maliciously) `/etc/passwd` overwrite করতে পারবে না।

## ২. File permission, সঠিকভাবে: তিনটা group, তিনটা bit

Linux system-এর প্রতিটা file আর directory-র একটা owner, একটা group, আর তিন সেট permission bit থাকে — **owner**-এর জন্য, **group**-এর জন্য, আর **বাকি সবার** জন্য:

```bash
ls -la /var/www/dev/projects/
```

```
drwxr-xr-x  5 dev dev  4096 Nov 18 10:02 learn.bazardev.com
-rw-r--r--  1 dev dev   220 Sep 30 19:56 .editorconfig
```

দশটা character বাম থেকে ডানে পড়ো: প্রথমটা file type (`d` = directory, `-` = regular file), তারপর তিনটা করে character-এর তিনটা group: **owner**, **group**, **other** — প্রতিটা group `r` (read), `w` (write), `x` (execute), বা `-` (দেওয়া হয়নি)।

| | owner | group | other |
|---|---|---|---|
| `drwxr-xr-x` | `rwx` | `r-x` | `r-x` |

একই জিনিস, একটা number হিসেবে (**octal notation**), যা তুমি বেশিরভাগ সময় আসলে type করবে: `r=4, w=2, x=1`, প্রতি group একসাথে add করে।

| Digit | মানে | Common use |
|---|---|---|
| `7` (4+2+1) | read, write, execute | একা খুব কম লাগে |
| `6` (4+2) | read, write | একটা file যা owner সরাসরি edit করে |
| `5` (4+1) | read, execute | একটা script বা browse করার মতো একটা directory |
| `4` | read only | একটা config file যা অন্যদের শুধু পড়া উচিত |
| `0` | কিছুই না | কোনো access-ই না |

```bash
chmod 755 deploy.sh    # owner: rwx, group: r-x, other: r-x
chmod 644 config.php   # owner: rw-, group: r--, other: r--
```

## ৩. Directory file না: প্রায় সবাইকে ধরা একটা confusion

এটা Linux permission নিয়ে সবচেয়ে common misunderstanding, আর এটা Section 7-এর deploy scenario-র জন্য সরাসরি গুরুত্বপূর্ণ। একটা **directory**-তে, তিনটা bit file-এ যা মানে তার থেকে আলাদা কিছু মানে:

| Bit | একটা file-এ | একটা directory-তে |
|---|---|---|
| `r` | file-এর content পড়া | এর ভেতরের entry-র নাম **list** করা |
| `w` | file-এর content modify করা | এর ভেতরের entry **create, delete, বা rename** করা |
| `x` | এটা একটা program হিসেবে execute করা | এতে **ঢোকা** (`cd` করা) বা এর পুরো path দিয়ে কিছু access করা |

Critical, non-obvious consequence: **একটা file delete করা যাবে কিনা এটা সেই file-এর *directory*-র একটা property, file নিজের না।** `000` permission-এর (কারো জন্যই কিছু না) একটা file তখনও সেই directory-তে write access থাকা কেউ delete করতে পারে — একটা file delete করা *directory*-র listing-এ একটা write, file-এর নিজের content-এর edit না। এই কারণেই `/tmp`-এর sticky bit (Section 5) লাগে, সবার `/tmp`-এ নিজেই write access থাকলেও user-দের একে অপরের file delete করা থেকে থামানোর জন্য।

## ৪. Ownership: তিনটা group আসলে কাদের বোঝায়

```bash
sudo chown deploy:www-data storage/
sudo chgrp www-data storage/
```

`chown user:group` দুটোই একসাথে সেট করে; `chgrp` শুধু group বদলায়। **Owner** সাধারণত সেই account যা file তৈরি করেছে বা primarily manage করা উচিত; **group** হলো একটা file world-writable না করে কয়েকটা account-এর মধ্যে access share করার mechanism — তোমার deploy user আর তোমার web server-এর user দুটোকেই একই group-এ add করো, আর group permission-ই নিয়ন্ত্রণ করে কে কী করতে পারে, "other"-কে কখনো touch না করেই।

## ৫. Special bit: setgid আর sticky bit

Basic নয়টা bit-এর বাইরে দুটো bit দুটো খুব common real সমস্যা সমাধান করে:

- **একটা directory-তে setgid** (`chmod g+s`, group execute position-এ `s` হিসেবে দেখায়, যেমন `drwxrwsr-x`): ভেতরে তৈরি হওয়া প্রতিটা নতুন file automatically *directory*-র group-এর হয়ে যায়, creating user-এর নিজের default group না। এটা ছাড়া, একটা shared deploy directory ধীরে ধীরে এমন file দিয়ে ভরে যায় যার মালিক যে user-এর deploy script তৈরি করেছে, বাকি সবার জন্য group-based access ভেঙে দেয়।
  ```bash
  chmod g+s /var/www/shared-uploads
  ```
- **Sticky bit** (`chmod +t`, other-execute position-এ `t` হিসেবে দেখায়, যেমন `drwxrwxrwt` — ঠিক `/tmp` যেমন দেখায়): এই bit থাকা একটা directory-তে, একজন user শুধু *নিজের* file delete বা rename করতে পারে, এমনকি directory নিজে সবার জন্য writable হলেও। এটাই Section 3-এর "যার write access আছে সে যা-ই delete করতে পারে" সমস্যার ঠিক fix, specifically shared, world-writable directory-র জন্য scoped।

## ৬. umask: কেন নতুন file default-এ 644 আর নতুন directory 755 হয়

```bash
umask
```

```
0002
```

`umask` একটা *subtraction mask* যা maximum-এর উপর (file-এর জন্য `666`, directory-র জন্য `777` — file-গুলো কখনো default execute bit পায় না, কারণ বেশিরভাগ নতুন তৈরি হওয়া file program না) apply হয় যখনই কিছু তৈরি হয়। এই `0002` একটা real value, সরাসরি এই course-এর নিজের writing server থেকে পড়া: `666 - 002 = 664`, `777 - 002 = 775` — নতুন file group-writable হয়ে যায়, শুধু owner-writable না। এটা Ubuntu-এর একজন normal user-এর জন্য default *কারণ* Ubuntu প্রতিটা user-কে একই নামের একটা নিজের private group দেয় (Section 4) — group-writable নিরাপদ যখন সেই group-এর একমাত্র member file-এর নিজের owner। আলাদাভাবে setup করা একটা server `0022` দেখাতে পারে (`666 - 022 = 644`, `777 - 022 = 755` — শুধু owner-writable) — যা অন্য জায়গায় একইভাবে common; যেভাবেই হোক, এই number একটা server-wide default, সাধারণত একবার একটা shell profile বা systemd service definition-এ সেট করা আর পরে খুব কম touch করা হয়।

## ৭. Real Laravel gotcha: একটা `storage/` permission error, আর এটা ঠিক করার ভুল উপায়

![একটা directory-কে deploy user আর web server দুটোকেই group দিয়ে ঠিকভাবে scope করা - chmod 777 দিয়ে না](https://raw.githubusercontent.com/Nirjhor3029/devops-for-developers/main/11-linux-security/images/least-privilege-permissions.svg)

একটা Laravel deploy-এর সময় খুব common একটা real error:

```
Illuminate\Contracts\Container\BindingResolutionException: ...
file_put_contents(.../storage/logs/laravel.log): Failed to open stream: Permission denied
```

Code একটা account দিয়ে deploy হয় (একটা `deploy` user, বা তোমার নিজের login) কিন্তু **serve** হয় একটা আলাদা account দিয়ে (`www-data`, বা PHP-FPM যে user হয়ে চলে) — আর Laravel-এর নিজের documentation explicitly বলে `storage/` আর `bootstrap/cache/` যে user web server চালায় তার জন্য writable হতে হবে, কারণ framework runtime-এ log, compiled view, আর cached config সেখানে লেখে।

যে fix আসলে least privilege follow করে সেটা ownership আর group-sharing (Section 4–5), সবার জন্য permission loose করা না:

```bash
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo chmod g+s storage bootstrap/cache
```

`775` owner (`deploy`) আর group (`www-data`)-কে পুরো read/write/execute দেয়, আর *other*-কে শুধু read/execute — web server লিখতে পারে কারণ এটা group-এর অংশ, কিন্তু box-এ একজন stranger তখনও পারে না। `chmod -R 777` (কিছু outdated tutorial-এ দেখা যায়) technically এই error-ও fix করে, কিন্তু এই দুটো directory-কে **machine-এর প্রতিটা account আর প্রতিটা process-এর জন্য** writable করে দেয়, Section 4–5 আগে থেকেই ঠিকভাবে সমাধান করা একটা সমস্যার জন্য এই পুরো chapter-এর উদ্দেশ্যটাই নষ্ট করে।

## ৮. Owner/group/other-এর বাইরে: ACL

Owner/group/other model-এ ঠিক একটা group slot থাকে — কখনো কখনো এটা যথেষ্ট না: দুটো সত্যিকারের অসম্পর্কিত account-এর (ধরো, একটা backup script আর একটা monitoring agent) প্রতিটার নিজের, একই directory-তে আলাদা access লাগে, আর তাদের একটা shared Unix group-এ জোর করা clumsy। **Access Control List (ACL)** basic তিনটার বাইরে per-user বা per-group permission extend করে:

```bash
setfacl -m u:backup-agent:rx /var/www/dev/projects/shop.bazardev.com
getfacl /var/www/dev/projects/shop.bazardev.com
```

এটা `backup-agent`-কে specifically read+execute দেয়, directory-র existing owner, group, বা অন্য কোনো account-কে affect না করে — এটা exist করে জানা worth, যদিও simpler owner/group/other model (Section 2–5) এই course-এর বেশিরভাগ case-এর জন্য যথেষ্ট।

## ৯. Service account: প্রতি service-এর জন্য একটা dedicated, unprivileged identity

MySQL `root` হয়ে চলে না, আর তোমার নিজের login হয়েও চলে না — এটা নিজের dedicated `mysql` system user হয়ে চলে, specifically MySQL-এর file আর process মালিক হওয়ার জন্য তৈরি, কোনো login shell আর কোনো password ছাড়া। তোমার নিজের long-running service-এর জন্য একই pattern তৈরি করো:

```bash
sudo useradd --system --no-create-home --shell /usr/sbin/nologin svc_myapp
```

- **`--system`** — একটা system account (low, non-human UID range), normal user listing আর login prompt থেকে বাদ।
- **`--no-create-home`** — একটা service-এর shell dotfiles-সহ একটা home directory লাগে না।
- **`--shell /usr/sbin/nologin`** — কেউ `ssh svc_myapp@server` বা `su svc_myapp` করার চেষ্টা করলেও, ঢোকার মতো কোনো interactive shell নেই।

এটা সরাসরি Chapter 1-এর `AllowUsers` principle extend করে: সেই chapter কোন account *একদমই* SSH-এ authenticate করতে পারবে তা সীমিত করেছিল; এটা নিশ্চিত করে একটা service-এর নিজের account সেভাবে ব্যবহার করা না-ই যায় চেষ্টা করলেও, আর ঠিক যা একটা service-এর লাগে তার বাইরে কিছুর মালিক না হয়।

## ১০. sudo, সীমিতভাবে

```bash
sudo visudo
```

**sudo policy সবসময় `visudo` দিয়ে edit করো, `/etc/sudoers` সরাসরি একটা plain text editor-এ খুলে কখনো না।** `visudo` save করার আগে file-এর syntax check করে আর একটা broken file save করতে refuse করে — Chapter 1-এর `sshd -t`-এর sudo equivalent, আর একই কারণে: `/etc/sudoers`-এ একটা syntax error machine-এর সবার জন্য `sudo` lock করে দিতে পারে, তোমার নিজের জন্যও।

`/etc/sudoers.d/`-এর drop-in file Chapter 3-এর `jail.local`-এর মতোই convention মানে — package upgrade কখনো touch না করা একটা বড় edit করা `/etc/sudoers`-এর বদলে, প্রতি concern-এর জন্য একটা ছোট file:

```bash
sudo visudo -f /etc/sudoers.d/deploy
```

```
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart php8.5-fpm, /usr/bin/systemctl restart laravel-queue
```

এটা `deploy` account-কে root হয়ে ঠিক দুটো নামে-দেওয়া service restart করার ability দেয়, আর কিছু না — কোনো shell না, কোনো arbitrary command না, এমনকি অন্য service-এর বিরুদ্ধে অন্য কোনো `systemctl` subcommand-ও না। এটাকে অনেক দুর্বল, common shortcut `deploy ALL=(ALL) NOPASSWD: ALL`-এর সাথে তুলনা করো, যা unrestricted, password-ছাড়া root দেয় — একটা deploy script-এর জন্য convenient, কিন্তু একটা compromised `deploy` account (Chapter 5-এর পুরো point credential নিয়ে) একটা compromised root account-এর সমান, কোনো second check-ই ছাড়া। `NOPASSWD` নিজে একটা real trade-off যা honestly বলা worth: non-interactive automation-এর জন্য এটা দরকার (password টাইপ করার জন্য কিছুই নেই, Chapter 4 TOTP-র জন্য `Match User` দিয়ে যে সমস্যা সমাধান করেছিল একই), কিন্তু এর মানে সেই account-এর SSH key আগে থেকেই আছে এমন কেউ সাথে সাথে root হিসেবে act করতে পারে — ঠিক এই কারণেই এর পাশের command list এই example-এর মতো narrow হওয়া উচিত, `ALL` না।

প্রতিটা `sudo` invocation real account দিয়ে log হয় যা এটা চালিয়েছে, "root" না — Chapter 1-এর একই accountability argument:

```bash
sudo journalctl _COMM=sudo --since "1 hour ago"
```

## ১১. সবকিছু একসাথে: একটা realistic deploy pipeline

- প্রতিটা human admin-এর **নিজের** নামে account আছে (Chapter 1) একটা **narrow, নামে-দেওয়া sudo rule**-সহ (এই chapter) ঠিক তার role-এর জন্য যা root-level action লাগে — কখনো shared বা generic account না।
- Application code একটা dedicated **`deploy`** account-এর মালিকানায় (Section 9-এর pattern, একজন human-এর personal login না), একটা **narrow `sudoers.d` rule**-সহ (Section 10) শুধু একটা deploy-এর জন্য restart করতে হবে এমন specific service restart করার জন্য।
- চলমান web server process তার নিজের **service account** ব্যবহার করে (`www-data` বা similar), ঠিক `storage/` আর `bootstrap/cache/`-এর (Section 4, 5, 7) group access share করে — আর কিছু না।
- একটা CI/CD pipeline-এর deploy key (Chapter 4-এর `Match User` exemption) specifically `deploy` account হয়ে authenticate করে — কখনো একজন human-এর নিজের account হয়ে না, কখনো root হয়ে না।

এই picture-এ কোনো একটা compromised account সবকিছুর জন্য full root access-এর সমান না — এটাই এই পুরো chapter-এর point।

## ১২. মূল কথা

- **Least privilege** blast radius সীমিত করে: একটা account বা process-এ যা-ই ভুল হোক, damage সীমিত থাকে ওই specific account আগে থেকে যা touch করতে পারতো তার মধ্যে।
- একটা **directory**-তে, `r`/`w`/`x` মানে list/create-delete-rename/enter — file-এর মতো একই না। একটা file delete করা যাবে কিনা তা *directory*-র write permission-এর উপর নির্ভর করে, file নিজের না।
- একটা shared directory-তে **setgid** নতুন file-কে automatically directory-র group inherit করায়; **sticky bit** (যেমন `/tmp`-এ) user-দের একটা shared, world-writable directory-তে একে অপরের file delete করা থামায়।
- **`umask`** (Ubuntu-তে সাধারণত `0002`, অন্য জায়গায় `0022`) এই কারণেই নতুন file default-এ `644`/`664` আর directory `755`/`775` হয়, কোনো command explicitly সেট না করেই।
- Laravel-এর `storage/` আর `bootstrap/cache/` permission error সঠিকভাবে fix হয় **group ownership + setgid** দিয়ে (Section 7), `chmod 777` দিয়ে না।
- **ACL** (`setfacl`/`getfacl`) basic owner/group/other model-এর বাইরে per-user বা per-group permission extend করে, rarer case-এর জন্য যা সত্যিই যথেষ্ট না।
- একটা **service account** (`--system --no-create-home --shell /usr/sbin/nologin`) প্রতিটা long-running service-কে নিজের identity দেয়, যা তার লাগে না তার মালিক না হয়ে, আর কেউ চেষ্টা করলেও login করতে না পেরে।
- **sudo policy সবসময় `visudo` দিয়ে edit করো**, আর rule-কে `/etc/sudoers.d/`-এ specific command-এ scope করো, কখনো একটা blanket `NOPASSWD: ALL` না — rule যত narrow, একটা compromised account থেকে damage তত ছোট।

## ১৩. নিজেকে পরীক্ষা করো (exam prep)

<details>
<summary><strong>Q1. একটা directory-তে "w" permission bit-এর মানে কী, আর একটা file-এ এর মানে থেকে এটা কীভাবে আলাদা?</strong></summary>

একটা directory-তে, "w" মানে এর ভেতরে entry create, delete, বা rename করতে পারা। একটা file-এ, "w" মানে file-এর নিজের content modify করতে পারা - এগুলো সত্যিই আলাদা operation, এই কারণেই একটা file-এর নিজের permission তা delete করা যাবে কিনা নির্ধারণ করে না।
</details>

<details>
<summary><strong>Q2. একটা file-এর permission 000 (কারো জন্যই কোনো access না)। এটা তখনও delete করা যায়, আর কে করতে পারে?</strong></summary>

হ্যাঁ - যার এটা থাকা directory-তে write permission আছে সে করতে পারে। একটা file delete করা directory-র listing-এ একটা modification, file-এর নিজের content-এর edit না, তাই file-এর নিজের permission bit এটা remove করা যাবে কিনা নিয়ন্ত্রণ করে না।
</details>

<details>
<summary><strong>Q3. একটা shared directory-তে setgid আসলে কী বদলায়?</strong></summary>

ভেতরে তৈরি হওয়া নতুন file automatically directory-র group-এর হয়ে যায়, creating user-এর নিজের default group না, যা সেই directory share করা সবার জন্য group-based access consistently কাজ করা রাখে।
</details>

<details>
<summary><strong>Q4. umask 0022 দিয়ে, একটা নতুন তৈরি হওয়া file কী permission পায়, আর কেন?</strong></summary>

644 (rw-r--r--)। একটা file-এর default maximum 666; umask 022 এর থেকে subtract হয় (666 - 022 = 644), এই কারণেই নতুন file default-এ শুধু owner-এর জন্য writable।
</details>

<details>
<summary><strong>Q5. storage/ আর bootstrap/cache/-এ chmod -R 777 একটা Laravel permission error-এর জন্য ভুল fix কেন, technically কাজ করলেও?</strong></summary>

এটা machine-এর প্রতিটা account আর process-এর জন্য ওই directory writable করে দেয়, শুধু যে web server user-এর আসলে write access লাগে তার জন্য না - এটা symptom fix করে least privilege সম্পূর্ণ ছেড়ে দিয়ে, যখন group ownership plus setgid একই error সঠিকভাবে fix করে।
</details>

<details>
<summary><strong>Q6. একটা long-running service কেন একজন developer-এর নিজের login-এর বদলে একটা dedicated system account ব্যবহার করা উচিত?</strong></summary>

একটা service account শুধু সেই service-এর লাগে এমন কিছুর মালিক হয়, কোনো login shell নেই (তাই compromised হলেও interactively ব্যবহার করা যায় না), আর service-এর access কে একজন human-এর নিজের account আর permission থেকে পরিষ্কারভাবে আলাদা রাখে।
</details>

<details>
<summary><strong>Q7. sudo policy কেন সবসময় /etc/sudoers সরাসরি একটা plain text editor-এ না, visudo দিয়েই edit করতে হবে?</strong></summary>

visudo save করার আগে file-এর syntax check করে আর একটা broken file save করতে refuse করে, একটা typo machine-এর সবার জন্য sudo access lock করা থামায় - Chapter 1-এ SSH config-এর জন্য sshd -t যে একই safety role খেলে।
</details>

<details>
<summary><strong>Q8. "deploy ALL=(ALL) NOPASSWD: ALL"-এর মতো একটা rule-এর practical risk কী, specific command নাম দেওয়ার তুলনায়?</strong></summary>

এটা সেই account-কে unrestricted, password-ছাড়া root দেয় - deploy account-এর credential কখনো compromise হলে, এটা সাথে সাথেই একটা compromised root account-এর সমান, কোনো second check-ই ছাড়া। Specific command নাম দেওয়া (যেমন শুধু দুটো systemctl restart) damage-কে ঠিক ওই action-গুলোর মধ্যে বেঁধে রাখে।
</details>

## ১৪. Free resources

- `man chmod`, `man chown`, `man umask`, `man sudoers`, `man visudo`
- Ubuntu Server Documentation: [File permissions](https://ubuntu.com/server/docs) আর [sudo](https://ubuntu.com/server/docs)
- Laravel Documentation: [Installation — Directory Permissions](https://laravel.com/docs/installation#directory-permissions)
- Red Hat: [An introduction to Linux ACLs](https://www.redhat.com/sysadmin/linux-access-control-lists)
- DigitalOcean: [An Introduction to Linux Permissions](https://www.digitalocean.com/community/tutorials/an-introduction-to-linux-permissions)

---

**Next:** [Chapter 7 — Security auditing: Lynis, unattended-upgrades, আর একটা hardening checklist](07-security-auditing-and-hardening-checklists.md)
