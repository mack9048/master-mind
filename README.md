# Master Mind · Meet the Expert — Workshop booking

Current edition: **Favour Your Brand** — Branding Masterclass with Favour Francis · 26 Nov 2026, 10:30 AM · ₹299

```
/            React (Vite) frontend  → deploy on Vercel
/backend     PHP + MySQL API & admin → deploy on Hostinger (apiclass.macadz.com)
```

## Frontend (Vercel)

- Website domain: `class.macadz.com` (Hostinger DNS: CNAME `class` → the value Vercel shows).
- Vercel builds the repo root automatically (`vercel.json` → Vite, output `dist`).
- Set **Vercel → Settings → Environment Variables**: `VITE_API_URL` = URL of the backend, e.g. `https://apiclass.macadz.com` (no trailing slash), then redeploy.
- Content for the edition lives in `src/config.js` (dates, fee, speaker, texts); images in `public/img`.
- Local dev: `npm install && npm run dev`.

## Backend (Hostinger)

1. Upload the **contents** of `backend/` to `public_html` of apiclass.macadz.com.
2. hPanel → Databases: create the database, then import `backend/database.sql` in phpMyAdmin.
3. Copy `config.sample.php` → `config.php` and fill in:
   - `db.pass` (name/user are pre-filled for `u455934768_class` / `u455934768_class`)
   - `frontend_url` — your Vercel URL (and any custom domain in `allowed_origins`); the API only accepts requests from these sites
   - `app_secret` — any long random text
   - `admin.pass` — password for `/admin`
   - **UPI QR**: `payment.upi_id` (QR with ₹299 pre-filled is generated) **or** upload your QR image as `assets/img/upi-qr.png`
4. Check `https://apiclass.macadz.com/api/event.php` shows JSON, then book a test seat on the website and see it in `https://apiclass.macadz.com/admin/`.

### Razorpay (online payment)

Payments use **Razorpay Standard Checkout**. The keys live only on the server, in `backend/.env`
(same folder as `config.php`; blocked from the web by `.htaccess`, ignored by Git):

```
RAZORPAY_KEY_ID=rzp_test_xxxxxxxx
RAZORPAY_KEY_SECRET=xxxxxxxxxxxxxxxx
```

Flow: form → `POST /api/register.php` (booking saved, unpaid) → `POST /api/create-order.php`
(server creates the Razorpay order for the fee) → Razorpay modal → `POST /api/verify-payment.php`
(HMAC-SHA256 signature check, then the booking is marked paid). Without keys the site falls back to the UPI QR flow.
The Key ID reaches the browser through `create-order`; the Key Secret never leaves the server.
For live payments replace both values with your `rzp_live_…` keys from the Razorpay Dashboard.

### API

| Endpoint | |
|---|---|
| `GET  /api/event.php` | seats left, registration open |
| `POST /api/register.php` | name, phone, email, city, profession, organization, expectation → `reg_code`, `token`, UPI details |
| `POST /api/create-order.php` | `reg_code`, `token` → Razorpay `order_id`, `amount` (paise), `currency`, `key_id` |
| `POST /api/verify-payment.php` | `reg_code`, `token`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature` → marks paid |
| `POST /api/payment.php` | UPI fallback: `action=upi_ref`, `reg_code`, `token`, `payment_ref` (UTR) |
| `/admin/` | dashboard: stats, verify UPI payments (UTR), edit / add / cancel / delete bookings, WhatsApp confirmation, check-in, CSV export |

Registrations are stored per `event.slug`, so future Master Mind editions share the same table.
