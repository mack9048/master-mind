# Master Mind · Meet the Expert — Workshop booking site

Landing page + registration for the **Master Mind** series by MAC Media School.
Current edition: **Favour Your Brand** — Branding Masterclass with Favour Francis, 26 Nov 2026, 10:30 AM, fee ₹299.

- Pixel-particle animated title, highlight + cursor intro, live countdown
- Registration form → MySQL (PHP 8 / PDO, CSRF, honeypot, duplicate-phone check, seat limit)
- Payment: **UPI QR** — after registering, the student scans your QR, pays ₹299 and enters the UTR; you confirm in admin. (Razorpay support is built in but off.)
- Admin panel at `/admin`: stats, search, filter, mark paid / cancelled / attended, CSV export

## Setup (Hostinger / cPanel)

1. Upload all files to `public_html` of the domain (or deploy from this Git repo).
2. In hPanel → **Databases → MySQL**, create a database + user.
3. Open **phpMyAdmin** and import `database.sql`.
4. Copy `config.sample.php` → `config.php` on the server and fill in:
   - `db` – host `localhost`, database name, user, password
   - **UPI QR**: upload your QR image as `assets/img/upi-qr.png` (or `.jpg`), **or** set `payment.upi_id` and a QR with ₹299 pre-filled is generated
   - `admin.pass` – password for `/admin` (or `admin.pass_hash` for a hashed one)
5. Visit the site, register a test entry, then check `/admin`.
6. Once SSL is active, uncomment the HTTPS redirect in `.htaccess`.

## Next edition of the series

Edit only the `event` block in `config.php` (new `slug`, title, speaker, date, fee) and replace
`assets/img/favour-francis.jpg`. Registrations are stored per `event_slug`, so past editions stay in the same table.

## Files

```
index.php            landing page
api/register.php     saves registration, starts payment
api/payment.php      Razorpay verify / UPI UTR submit
admin/               registrations dashboard
includes/            bootstrap (DB, helpers), Razorpay helper
database.sql         MySQL schema
config.sample.php    copy to config.php (git-ignored)
```
