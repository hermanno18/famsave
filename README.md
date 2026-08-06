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

## Deployment to WHC Shared Hosting

### Step 1 — Generate Icons (run once locally if you have PHP)

```bash
php generate_icons.php
```

If you don't have PHP locally, the app still works — just without the proper PWA icon.
You can use [Favicon.io](https://favicon.io) to generate 192×192 and 512×512 PNGs and place them in `/icons/`.

### Step 2 — Upload via FTP

Upload the **entire `family-savings/` folder** to your WHC hosting public directory:

- If you want `https://yourdomain.com/famsave/` → upload to `public_html/famsave/`
- If you want `https://yourdomain.com/` → upload to `public_html/`

```
public_html/
└── famsave/           ← upload everything here
    ├── index.php
    ├── login.php
    ├── admin/
    ├── includes/
    ├── data/          ← must be writable (chmod 755)
    ├── uploads/       ← must be writable (chmod 755)
    └── ...
```

### Step 3 — Set Folder Permissions

In your WHC cPanel File Manager, set permissions to **755** for:
- `/data/` folder
- `/uploads/` folder

Or via SSH:
```bash
chmod 755 data uploads
```

### Step 4 — Enable mod_rewrite (if not already)

The `.htaccess` file handles security headers and file protection.
WHC shared hosting has mod_rewrite enabled by default — no action needed.

### Step 5 — Visit the app

Go to `https://yourdomain.com/famsave/` — the SQLite database is created automatically on first visit.

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




🚀 To deploy to WHC:
 1. Run php generate_icons.php once (if PHP installed locally) — or grab 192px & 512px PNGs from favicon.io (https://favicon.io) and drop them in /icons/
 2. FTP the whole `family-savings/` folder to public_html/ (or public_html/famsave/)
 3. chmod 755 the /data/ and /uploads/ folders in cPanel
 4. Visit the URL — DB creates itself! Log in as admin / Admin1234!

