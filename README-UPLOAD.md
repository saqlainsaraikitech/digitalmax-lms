# DigitalMax LMS — Hostinger par install guide (lms.digitalmx.pk)

## Step 1 — Subdomain banayein
1. Hostinger **hPanel** → **Domains → Subdomains**
2. Subdomain: `lms` (domain: digitalmx.pk select karein) → Create
3. Document root note kar lein (aam tor par `public_html/lms`)

## Step 2 — MySQL database banayein
1. hPanel → **Databases → Management**
2. **New database** banayein → naam, user aur password note kar lein
   - Misal: DB `u123456_lms`, user `u123456_lmsuser`

## Step 3 — Files upload karein
1. hPanel → **File Manager** → subdomain wale folder me jayein (`public_html/lms`)
2. `digitalmax-lms-v24.zip` upload karke **Extract** karein
3. Tasdeeq karein: `install.php`, `login.php`, `banned.php`, `admin/`, `student/`, `inc/` sab mojood hon

> **Update (v24) — PEHLE BACKUP:** Admin → System & Backup → **Download Database Backup** lazmi lein. Phir zip extract karein — **`config.php` zip me nahi hai**, is liye wo overwrite nahi hoga. `install.php` dobara NA chalayein, `uploads/` aur `backups/` delete NA karein. Naye database columns (community_rules, whatsapp links, ban fields) pehle visit par **khud-ba-khud** add ho jayenge.

## Step 4 — Installer chalaye
1. Browser me kholein: **https://lms.digitalmx.pk/install.php**
2. Database wali details (Step 2 wali) bharein
3. **Admin account** banayein (apna naam, email, password — yahi se login hoga)
4. **Install** dabayein → "Install ho gaya" nazar aayega

## Step 5 — Security (LAZMI)
File Manager me ja kar **`install.php` ko DELETE kar dein**. Ye sirf pehli dafa ke liye tha.

## Step 6 — Login aur setup
1. **https://lms.digitalmx.pk/login.php** → admin email/password se login
2. Pehle **Course** banayein (Courses → New course)
3. **Curriculum** me sections aur lectures add karein
   - Lecture me **YouTube video ka link** dalein — video YouTube par **Unlisted** rakhein
4. **Students** → New student: naam, email, password de kar account banayein
5. Student ko **course me enroll** karein (student ke View page se)
6. **Team** → member add karke permissions tick karein (kaun kya dekh/sambhal sakta hai)
7. **Tasks** → team ko kaam assign karein

## Payment flow (manual)
1. Student apne panel → **Payments** → amount, method (Easypaisa/JazzCash), **screenshot upload**
2. Admin → **Payments** → screenshot dekh kar **Approve / Reject**
3. Approve ke baad student ko **course me enroll** kar dein (Students → View → Enroll)

## WhatsApp setup
- Admin panel → **Settings** → apna **WhatsApp support number** likhein (0300...) — student wala floating button isi par message bhejega
- Payment **Approved** hone par har payment ke sath **"Notify on WhatsApp"** button aayega — student ke number (uske account me saved phone) par pre-filled message khul jayega

## Referral / Affiliate system
- Har student ka unique link: `lms.digitalmx.pk/join?ref=DMXXXXX` (student ke "Earn with DigitalMax" page par, copy button ke sath)
- Link se aane wale ka click count hota hai + 30 din ka cookie lagta hai — uski payment submit par affiliate auto-attach
- Commission %: Admin → Settings (default 10%) — approved payment par lock hota hai
- Payouts: Admin → Affiliates & Payouts — affiliate ka Easypaisa/JazzCash number (uske profile phone) ke sath pending list, 1-click "Clear payout"

## Upload security
- `uploads/` me hardened `.htaccess`: PHP/engine off, `.php.jpg` jese double-extension tricks block, sirf images + documents serve hongi, directory listing off, MIME-sniffing off
- Payment screenshots **auto-compress**: upload par PHP GD se max 1600px + WebP/JPEG me **max 300KB** — original 5-8MB file save nahi hoti, Hostinger storage safe

## Security (v19 Fortress)
- **CSRF protection**: tamam 21 forms + AJAX calls me token — koi doosri site se fake request nahi bhej sakta
- **GET deletes khatam**: delete/approve/reject/status sab POST + confirm dialog ke sath
- **Login brute-force block**: 1 IP se 5 ghalat passwords → 15 minute lock
- **Secure session cookies**: HttpOnly + SameSite=Lax (+ HTTPS par Secure)
- **Root `.htaccess`**: `config.php`, `*.sql`, `install.php` browser se blocked; `backups/` direct access blocked; security headers (nosniff, SAMEORIGIN)
- **Error hiding**: unexpected errors par technical details user ko nahi dikhti (log me jati hain)
- **Zip me `config.php` nahi hoti** — update ke waqt overwrite ka khatra khatam (fresh install par `install.php` khud bana leta hai)

## Safe update (nayi version upload)
1. Admin → System & Backup → **Download Database Backup** 💾
2. Nayi zip upload karke extract karo (purani files par overwrite — `uploads/` aur `backups/` delete NA karo)
3. `install.php` dobara mat chalao · upgrade SQLs ki zaroorat nahi (self-healing)

## Batches & Vault (v21)
- **Admin → 📅 Batches**: har course ke batches (name, start/end date, duration) — status auto (Upcoming/Active/Ended), student count sath
- **Student assign par batch lazmi**: course ke sath batch dropdown (jis course ke batches hon)
- **Lecture → Assign to Batches**: "Share with ALL batches" ya selective batch checkboxes — student ko sirf apne batch ke lectures dikhenge
- **Student side**: dashboard/player par 🟢/🔴 batch badge (Ends in N days / Archive Access Only); locked lectures par "Yeh content naye batch ke liye hai. Upgrade / Re-enroll to access."
- **🧰 Vault** (admin + student): Niches, Master Prompts, Ad Copy, Supplier Lists — markdown editor, student side tabs + 📋 1-click copy + 🖨️ Print/Save-as-PDF cheat-sheets
- Purani enrollments (bina batch) ko sab lectures visible rehte hain — kuch nahi tootega

## Announcements / Notice Board (v22)
- **Admin → 📢 Announcements**: rich text editor (bold/italic/list/link), target audience (All Students / Specific Course / Specific Batch), priority (Normal / 🔴 Urgent / 📌 Pinned), Hide/Show status, edit/delete
- **Student dashboard**: urgent announcement par dismissible red alert bar — `🔔 Important: [Title] - [Preview] [Read More]`
- **🔔 Notice Board page**: card timeline (date/time, course/batch tag, formatted text, clickable links) + sidebar me unread count badge — page kholte hi read mark
- Sirf relevant announcements: apne enrolled courses / batch wali ya universal

## VIP WhatsApp Community (v23)
- **DB**: `batches.whatsapp_group_link` + `courses.whatsapp_group_link` (batch link ko priority, warna course link)
- **Admin**: Course edit + Batch create/edit me "WhatsApp Community / Group Invite Link" field (https:// validation, khali = hide)
- **Student dashboard**: green VIP card — "Join Batch VIP Discussion & Support Group" + [👉 Join WhatsApp Group Now] (new tab)
- **Player screen**: chhota `💬 Batch Community` button
- **Strict**: sirf enrolled students ko — pending/unenrolled ko kuch nahi; expired batch par blurred 🔒 locked card; link set na ho to block auto-hide

## Database backup
- Admin → **System & Backup**: 1-click `.sql` download, emergency restore (file upload, bina phpMyAdmin), auto-backup cron
- Cron command Hostinger me: `php /home/USERNAME/public_html/lms/cron_backup.php SECRET_KEY` (key page par milti hai) — roz backup, akhri 7 server par save
- `backups/` folder browser se protected hai (`.htaccess`)

## Version upgrade (purani install par nayi zip)
Agar LMS pehle se installed hai aur aap nayi version upload kar rahe hain:
1. Nayi zip extract karein (purani files overwrite ho jayengi — `config.php` aur `uploads/` bachayein, unko overwrite NA karein)
2. Agar `upgrade-vX.sql` file mojood ho (misal: `upgrade-v6.sql`) to usay phpMyAdmin me **Import** kar lein
   - Note: v6 me ye step **optional** hai — pehli login par column khud ban jayega
3. `install.php` dobara NA chalayein

## Zaroori notes
- `config.php` me database credentials hain — kisi se share na karein
- `uploads/` folder me screenshots aur assignment files save hoti hain — wahan PHP run nahi ho sakti (`.htaccess` se protected)
- Passwords hashed (bcrypt) save hote hain, plain text me nahi
- Koi masla aaye to `install.php` dobara NA chalayein jab tak purani tables ka backup na le liya ho
