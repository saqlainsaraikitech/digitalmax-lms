# DigitalMax LMS — Learning Management System

![PHP](https://img.shields.io/badge/PHP-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?logo=mysql&logoColor=white)
![Live](https://img.shields.io/badge/Live-lms.digitalmax.pk-22c55e)

**DigitalMax LMS** — a complete course platform for selling YouTube / TikTok / Facebook Automation courses.
Runs on plain PHP + MySQL, built for shared hosting (Hostinger).

**Live:** https://lms.digitalmax.pk

## Features

- **3 roles** — Super Admin, Team, Students (login only, no public signup)
- **Course & curriculum manager** — courses, modules, lectures with YouTube (Unlisted) embeds
- **Watch rules** — 3-minute minimum watch tracking per lecture, NEW-lecture badges
- **Manual payment flow** — public `pay.php` → student sends screenshot on WhatsApp → admin verifies → account created
- **Fee tracking & Student Stats reports**, assignment submissions with inline grading (Pending Review / Graded tabs)
- **Batches & vault**, announcements, community rules, leads, affiliates, exports
- **Anti-leak hardening** — protected video delivery, session controls
- **Bilingual legal + payments module**, mobile-first student UX (80%+ students on mobile)
- **Theme** — DigitalMax navy `#294461` + coral `#c74b25`

## Requirements

- PHP 7.4+ with PDO MySQL
- MySQL / MariaDB
- Apache shared hosting (Hostinger-ready)

## Installation

1. Upload everything **except `config.php`** to your hosting (e.g. `public_html/lms/`).
2. Visit `https://yourdomain.com/install.php` — the web installer creates the database tables,
   writes `config.php`, and creates your admin account.
3. **Delete `install.php`** after the first successful install.
4. Never re-run the installer on a live site, and never overwrite `config.php` or delete `uploads/` on update.

> `config.php` holds your secrets and is **not** in this repo (see `.gitignore`).
> Copy `config.example.php` → `config.php` and fill it in if you prefer manual setup.

## Project structure

```
admin/        # admin panel (courses, lectures, students, payments, reports, ...)
student/      # student dashboard, lectures, assignments, payments
inc/          # shared helpers, auth, db, course outlines
assets/       # css, js, images
uploads/      # student uploads (not in repo)
install.php   # first-run web installer (delete after use)
install.sql   # schema
upgrade-v*.sql# version migrations
pay.php       # public payment page
```

## Conventions

- Release zips **exclude** `config.php` — the installer generates it.
- Updates: upload new code, run the matching `upgrade-v*.sql`, never touch `config.php` / `uploads/` / `backups/`.

## License

All rights reserved — DigitalMax / Saqlain Abid.
