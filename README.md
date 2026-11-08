# Andoy Cakes & Pastries Corner - Ordering System (PHP + MySQL)

A complete online ordering system with a real MySQL database: customers order and pay by GCash or cash, admins run the shop,
and a super admin sees everything. It works on your own computer and on a real website on the internet.

## A. Put it on the internet (shared hosting, e.g. cPanel)
You need a hosting plan with **PHP 8.0 or newer** and **one MySQL database**, and (recommended) a free SSL certificate so the address starts with https.

1. In your hosting control panel, create an empty MySQL database and a database user, and give that user full access to the database.
   Write down the database name, user and password.
2. Upload the zip with File Manager and extract it into `public_html` (or into a sub-folder, for example `public_html/shop`).
3. Open your site address in the browser. The first visit goes straight to the installer (`/install.php`).
4. Fill in the form: the database details, your shop name, and the admin and super admin accounts with your own passwords. Press **Install now**.
5. The installer builds all the tables, saves the settings, and deletes itself. Then log in.
6. In **Admin > Settings** set your real GCash number and name, pickup address, phone and delivery fee. Add your own products and photos.
7. Turn on SSL in your host (often called "SSL/TLS" or "AutoSSL"), then open the site with https. To force https, remove the `#` in front of the last three lines of `.htaccess`.

Nothing else needs editing. The site finds its own web address, remembers logins safely over https, and shows friendly messages instead of technical errors to visitors.

**Checklist before you announce the shop:** the installer is gone from the server, `database.sql` is not on the server, you tested a full order (register, order, pay, admin verifies, complete), and you set a strong password for the admin and super admin.

## B. Run it on your own computer (XAMPP / Laragon)
1. Copy the folder into `C:\xampp\htdocs\kakanin-ordering-system` and start Apache and MySQL.
2. Open `http://localhost/kakanin-ordering-system/`. With no database yet, it opens the installer. (Default XAMPP: host `localhost`, user `root`, empty password, any database name.)
   Or import `database.sql` in phpMyAdmin for a pre-populated local shop database.

## What is inside
Customer: register with a profile picture (then back to log in), show-password eye, remember me for 7 days, products with star ratings,
cart, checkout with pickup or delivery date and time, GCash (pay first, upload the screenshot) or cash, order tabs, tracking, buy again,
ratings, messages, notifications, red dots, profile, light and dark mode.
Admin: dashboard, order cards with next-step buttons, payment verification, products, categories, customers, messages,
monthly and yearly sales reports with CSV, settings including the editable login page text.
Super admin: create, reset and remove admins, view admin passwords, complete all-time sales, customer controls (view password, ban, delete).

## Safety built in
- Passwords are stored as secure hashes. Logins are slowed after 8 wrong tries from one connection (15 minute wait).
- Every form is protected against forged requests. Images are checked and renamed; PHP cannot run in `uploads/`.
- Private folders and `.sql` files are blocked from the web (Apache hosts). On an nginx host, ask your host to deny `/config/`, `/includes/`, `/install/` and `*.sql`.
- Super admin "show password": encrypted copies of customer and admin passwords are kept using a secret key the installer creates for you (`ENC_KEY` in `config/local.php`). Existing admin accounts need a password reset before their password can be shown.
  If you prefer maximum safety, remove that feature (ask for a Reset password button instead).

## Looking after the live shop
- Back up the database weekly: phpMyAdmin > Export (and download the `uploads` folder now and then).
- Payment screenshots are stored in `uploads/proofs` with random names.
- If `BASE_URL` is detected wrongly on an unusual host, add `define('BASE_URL', '/your-folder');` to `config/local.php`.
- If the super admin forgets the password, an admin cannot reset it: change it in phpMyAdmin (users table) or ask for a reset script.

## Requirements
PHP 8.0+ with pdo_mysql, mbstring, openssl and fileinfo (the installer checks these for you), and MySQL 5.7+ / MariaDB 10.3+.
