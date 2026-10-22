# Deployment guide (Practical 15)

## A. Local run with XAMPP (basic)

1. Install XAMPP (PHP 8.1+). Extract the project to `C:\xampp\htdocs\studenthub` so that `index.php` sits directly inside that folder.
2. XAMPP Control Panel → start **Apache** and **MySQL**.
3. Open `http://localhost/phpmyadmin` → **Import** → choose `database/studenthub.sql` → **Go**. (The file drops and recreates the `studenthub` database, so re-importing resets the demo data.)
4. Visit `http://localhost/studenthub/test_connection.php`. Every row should say **Pass**.
5. Open `http://localhost/studenthub/` and log in with the demo accounts from the README.

If MySQL uses a password or a different port, set `STUDENTHUB_DB_PASSWORD` / `STUDENTHUB_DB_PORT` (see `includes/config.php`) or edit the fallbacks there.

PHP extensions needed (all on by default in XAMPP): `mysqli`, `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (only for reading image size, `getimagesize`).

## B. Apache virtual host (intermediate extension)

1. Copy `deploy/studenthub.local.conf` into `C:\xampp\apache\conf\extra\httpd-vhosts.conf` and fix the paths.
2. In `C:\xampp\apache\conf\httpd.conf` make sure this line is **not** commented out: `Include conf/extra/httpd-vhosts.conf`.
3. Add `127.0.0.1 studenthub.local` to the hosts file (see `deploy/hosts-entry.txt`).
4. Restart Apache and open `http://studenthub.local/`.

The project detects its own web path (`/studenthub` under htdocs, empty under a virtual host), so no code change is needed between A and B.

## C. Static front end online + documented backend (advanced extension)

The PHP/MySQL application needs a server, so the *public* part is split in two:

**1. Static showcase (free hosting).** `static-demo/` is a self-contained HTML page with screenshots and a feature tour. Publish it with GitHub Pages:

```bash
git init && git add . && git commit -m "StudentHub final project"
git branch -M main
git remote add origin https://github.com/<you>/studenthub.git
git push -u origin main
# GitHub → Settings → Pages → Build from branch → main → folder: /static-demo  (or copy it to /docs)
```

Netlify or Cloudflare Pages work the same way: set the publish directory to `static-demo`.

**2. Backend on a PHP host.** Any shared host or small VPS with PHP 8.1+ and MySQL/MariaDB:

1. Create a database and a **dedicated MySQL user** that only has rights on it (never use `root` online).
2. Import `database/studenthub.sql` (remove the `DROP DATABASE` / `CREATE DATABASE` lines if your host pre-creates the database).
3. Upload the project; set environment variables or edit `includes/config.php`: `STUDENTHUB_DB_HOST`, `STUDENTHUB_DB_NAME`, `STUDENTHUB_DB_USER`, `STUDENTHUB_DB_PASSWORD`.
4. Make `storage/` and `uploads/posters/` writable by the web server user (`chmod 750` / `chown www-data`).
5. Serve over **HTTPS** (cookies automatically become `Secure`).
6. **Change the demo passwords** (Users page, or `UPDATE users SET password_hash = …`) and delete the demo accounts you do not need.
7. Set `STUDENTHUB_DEBUG` to `0` (the default) so errors stay in the log.

Alternative: run the same folder in Docker with the official `php:8.3-apache` image plus a `mariadb` container; mount `database/studenthub.sql` into `/docker-entrypoint-initdb.d/`.

## Running the automated tests

```bash
php -S 127.0.0.1:8099 -t .          # terminal 1 (or use your XAMPP URL)
python3 tests/e2e_test.py           # terminal 2: needs Python 3 and the mysql client on PATH
```

The script **resets the database**, runs every check, and writes `docs/test-results.md`. Pass another base URL as the first argument, e.g. `python3 tests/e2e_test.py http://localhost/studenthub`.
