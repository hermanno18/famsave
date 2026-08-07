# FamSave 💰

A family savings account tracker PWA. Built with PHP + SQLite3 + Alpine.js + Tailwind CSS.
No Node.js required — runs on any PHP 7.4+ shared hosting.

---

## Features

- 📱 **PWA** — installable on Android & iOS (Add to Home Screen)
- 🔐 **Auth** — username/password, CSRF-protected, bcrypt hashed
- 💸 **Deposits** — members log deposits with photo/PDF proof
- 🏧 **Withdrawals** — members request withdrawals; admin approves/rejects
- 👑 **Admin panel** — approve transactions, update bank balance, manage members
- 🌍 **Bilingual** — English / French toggle
- 📊 **Balances** — individual + main account, with animated count-up
- 🔔 **In-app notifications** — per-user, real-time badge

---

## Default Admin Login

```
Username: admin
Password: Admin1234!
```
**Change this immediately after first login** (Profile → Change Password).

---

## Running Locally (dev/test)

No Node, no build step — just PHP's built-in server:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000`. Log in with `admin` / `Admin1234!` and change the password
right away (Profile → Change Password). The SQLite DB is created automatically in `/data/`
on first request — nothing to configure.

---

## Deployment to Shared Hosting (cPanel / WHC / any Apache+mod_php host)

This is the easiest path — no root/SSH required, works on basically any PHP host that gives
you `public_html` + FTP or a File Manager.

### Step 1 — Generate Icons (run once locally if you have PHP)

```bash
php generate_icons.php
```

If you don't have PHP locally, the app still works — just without the proper PWA icon.
You can use [Favicon.io](https://favicon.io) to generate 192×192 and 512×512 PNGs and place them in `/icons/`.

### Step 2 — Confirm PHP version & extensions with your host

Ask (or check cPanel → "MultiPHP Manager") that the account runs **PHP 7.4+** with the
`pdo_sqlite` and `fileinfo` extensions enabled. Both ship enabled by default on virtually
every shared host — just double check `pdo_sqlite` since some hosts disable unused PDO drivers.

### Step 3 — Upload via FTP/SFTP or File Manager

Upload the **entire `family-savings/` folder** to your hosting public directory:

- If you want `https://yourdomain.com/famsave/` → upload to `public_html/famsave/`
- If you want `https://yourdomain.com/` → upload to `public_html/`

```
public_html/
└── famsave/           ← upload everything here
    ├── index.php
    ├── login.php
    ├── admin/
    ├── includes/
    ├── data/          ← must be writable (chmod 755, auto-created if missing)
    ├── uploads/       ← must be writable (chmod 755)
    └── ...
```

Don't upload `.git/`, `php-runtime/`, or `php-server.log` — they're dev-only cruft
(already covered by `.gitignore`, but double-check your FTP client isn't including them).

### Step 4 — Set folder permissions

In cPanel File Manager (right-click → Permissions) or via SSH if your host offers it:

```bash
chmod 755 data uploads
```

Both folders need to be writable by the PHP process (the SQLite DB file and uploaded
proof files live here).

### Step 5 — Confirm mod_rewrite / .htaccess support

The included `.htaccess` handles security headers and blocks direct access to `.db`,
`.sql`, `.log`, `.env`, `.sh`, `.bat` files. Almost all shared hosts (cPanel, WHC, etc.)
have `mod_rewrite` and `.htaccess` overrides on by default — no action needed. If your
host uses **LiteSpeed**, it reads `.htaccess` the same way, so you're covered too.

### Step 6 — Visit the app & lock it down

Go to `https://yourdomain.com/famsave/` (or your root domain) — the SQLite database is
created automatically on first visit. Log in as `admin` / `Admin1234!` and **change the
password immediately** via Profile → Change Password.

### Step 7 — Force HTTPS (recommended)

Most shared hosts give you a free Let's Encrypt cert in cPanel ("SSL/TLS Status" →
"Run AutoSSL"). Once installed, the app auto-detects HTTPS and marks session cookies
`Secure` automatically — nothing to change in code.

---

## Deployment to a VPS (Ubuntu/Debian, Nginx + PHP-FPM)

For a VPS you own the whole box, so we install PHP ourselves and put a real web server
(Nginx) in front of it. These exact steps assume a fresh **Ubuntu 22.04/24.04** droplet;
Apache-based hosts can reuse the `.htaccess` already in the repo instead of Step 5.

### Step 1 — SSH in and update the box

```bash
ssh root@your-server-ip
apt update && apt upgrade -y
```

### Step 2 — Install Nginx, PHP-FPM, and required extensions

```bash
apt install -y nginx php-fpm php-sqlite3 php-mbstring php-gd php-curl
```

Check which PHP-FPM socket version got installed (needed for the Nginx config below):

```bash
ls /run/php/
# e.g. php8.1-fpm.sock
```

### Step 3 — Deploy the code

```bash
mkdir -p /var/www/famsave
cd /var/www/famsave
git clone https://github.com/hermanno18/famsave.git .
# or: scp/rsync the family-savings/ folder up instead of git clone
```

### Step 4 — Set ownership & permissions

Nginx/PHP-FPM run as `www-data` by default on Debian/Ubuntu:

```bash
chown -R www-data:www-data /var/www/famsave
chmod -R 755 /var/www/famsave
chmod -R 775 /var/www/famsave/data /var/www/famsave/uploads
```

### Step 5 — Configure Nginx

Create `/etc/nginx/sites-available/famsave`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/famsave;
    index index.php;

    # Block direct access to sensitive files (mirrors the repo's .htaccess rules)
    location ~* \.(db|sqlite|sql|log|env|sh|bat)$ {
        deny all;
    }

    location ~ ^/(data|uploads)/ {
        deny all;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;  # match the version from Step 2
    }

    client_max_body_size 8M;  # matches MAX_UPLOAD_BYTES in includes/helpers.php
}
```

Enable the site and reload Nginx:

```bash
ln -s /etc/nginx/sites-available/famsave /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

### Step 6 — Open the firewall

```bash
ufw allow 'Nginx Full'
ufw allow OpenSSH
ufw enable
```

### Step 7 — Point DNS & get a free TLS cert

Point your domain's `A` record at the VPS's IP, then:

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d yourdomain.com
```

Certbot edits the Nginx config to redirect HTTP → HTTPS and auto-renews via a systemd
timer — nothing else to configure. The app already detects HTTPS and marks cookies
`Secure` on its own.

### Step 8 — Visit the app & lock it down

Go to `https://yourdomain.com/` — the SQLite DB is created automatically on first visit.
Log in as `admin` / `Admin1234!` and change the password immediately.

### Keeping it updated (VPS only)

```bash
cd /var/www/famsave
git pull
chown -R www-data:www-data .
systemctl reload php8.1-fpm nginx
```

---

## Security Notes

- `/data/` is protected by `.htaccess` — the SQLite DB is not publicly accessible
- `/uploads/` is protected — files are served through `serve_file.php` (requires login)
- Sessions are `httponly` + `SameSite=Lax`
- All queries use PDO prepared statements (no SQL injection)
- File uploads are validated by MIME type + size (max 5 MB)

---

## Structure

```
family-savings/
├── includes/
│   ├── bootstrap.php   # Entry point, constants, session
│   ├── db.php          # SQLite schema + query helpers
│   ├── auth.php        # Login, CSRF, guards
│   ├── helpers.php     # Flash, redirect, format, upload
│   └── layout.php      # page_start() / page_end() shell
├── lang/
│   ├── en.php          # English strings
│   └── fr.php          # French strings
├── admin/
│   ├── index.php       # Admin dashboard
│   ├── deposits.php    # Approve/reject deposits
│   ├── withdrawals.php # Approve/reject withdrawals
│   ├── balance.php     # Update main account balance
│   └── users.php       # Create/manage members
├── api/
│   └── action.php      # AJAX endpoint (approve/reject/toggle/reset/lang)
├── data/               # SQLite DB lives here (auto-created)
├── uploads/            # Proof files (served via serve_file.php)
├── icons/              # PWA icons (192, 512 px)
├── dashboard.php       # Member home
├── deposit.php         # Log a deposit
├── withdraw.php        # Request a withdrawal
├── profile.php         # Profile + full history
├── login.php
├── logout.php
├── serve_file.php      # Authenticated file serving
├── manifest.json       # PWA manifest
├── sw.js               # Service worker
└── .htaccess           # Security + PHP config
```

---

## PHP Requirements

- PHP **7.4+** (8.x recommended)
- Extensions: `pdo_sqlite`, `fileinfo`, `gd` (for icon generation only)
- `upload_max_filesize` ≥ 6M (set in `.htaccess`)

---

## Customizing

**App URL / subfolder**: The app auto-detects its URL prefix. No config needed.

**Currency**: Search & replace `XAF` and `FCFA` if you switch currencies.

**Max upload size**: Change `MAX_UPLOAD_BYTES` in `includes/helpers.php`.

**Admin username**: Update in `includes/db.php` in `_create_schema()`.


---

## TL;DR Deploy Cheatsheet

**Shared hosting:** generate icons → FTP the folder to `public_html/` → `chmod 755 data uploads`
→ visit the URL (DB creates itself) → log in `admin` / `Admin1234!` → change the password.

**VPS:** `apt install nginx php-fpm php-sqlite3` → clone/upload code to `/var/www/famsave` →
`chown www-data:www-data` + `chmod 775 data uploads` → add the Nginx server block → `certbot --nginx`
→ visit the URL → log in `admin` / `Admin1234!` → change the password.

See the full step-by-step sections above for either path.

