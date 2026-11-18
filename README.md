# Master Mind · Meet the Expert — Workshop booking site

Landing page + registration for the **Master Mind** series by MAC Media School.
Current edition: **Favour Your Brand** — Branding Masterclass with Favour Francis, 26 Nov 2026, 10:30 AM, fee ₹299.

- Pixel-particle animated title, highlight + cursor intro, live countdown
- Registration form → MySQL (PHP 8 / PDO, CSRF, honeypot, duplicate-phone check, seat limit)
- Payment: **UPI mode** (student pays to your UPI ID and enters the UTR, you confirm in admin) or **Razorpay mode** (online checkout, auto-confirmed)
- Admin panel at `/admin`: stats, search, filter, mark paid / cancelled / attended, CSV export

## Setup (Hostinger / cPanel)

1. Upload all files to `public_html` of the domain (or deploy from this Git repo).
2. In hPanel → **Databases → MySQL**, create a database + user.
3. Open **phpMyAdmin** and import `database.sql`.
4. Copy `config.sample.php` → `config.php` on the server and fill in:
   - `db` – host `localhost`, database name, user, password
   - `payment.upi_id` (UPI mode) **or** set `payment.mode = 'razorpay'` with your Razorpay key/secret
   - `admin.pass_hash` – generate with `php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"`
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
