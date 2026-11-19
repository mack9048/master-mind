# Master Mind · Meet the Expert — Workshop booking

Current edition: **Favour Your Brand** — Branding Masterclass with Favour Francis · 26 Nov 2026, 10:30 AM · ₹299

```
/            React (Vite) frontend  → deploy on Vercel
/backend     PHP + MySQL API & admin → deploy on Hostinger (class.macadz.com)
```

## Frontend (Vercel)

- Vercel builds the repo root automatically (`vercel.json` → Vite, output `dist`).
- Set **Vercel → Settings → Environment Variables**: `VITE_API_URL` = URL of the backend, e.g. `https://class.macadz.com` (no trailing slash), then redeploy.
- Content for the edition lives in `src/config.js` (dates, fee, speaker, texts); images in `public/img`.
- Local dev: `npm install && npm run dev`.

## Backend (Hostinger)

1. Upload the **contents** of `backend/` to `public_html` of class.macadz.com.
2. hPanel → Databases: create the database, then import `backend/database.sql` in phpMyAdmin.
3. Copy `config.sample.php` → `config.php` and fill in:
   - `db.pass` (name/user are pre-filled for `u455934768_master` / `u455934768_mastermind`)
   - `frontend_url` — your Vercel URL (and any custom domain in `allowed_origins`); the API only accepts requests from these sites
   - `app_secret` — any long random text
   - `admin.pass` — password for `/admin`
   - **UPI QR**: `payment.upi_id` (QR with ₹299 pre-filled is generated) **or** upload your QR image as `assets/img/upi-qr.png`
4. Check `https://class.macadz.com/api/event.php` shows JSON, then book a test seat on the website and see it in `https://class.macadz.com/admin/`.

### API

| Endpoint | |
|---|---|
| `GET  /api/event.php` | seats left, registration open |
| `POST /api/register.php` | name, phone, email, city, profession, organization, expectation → `reg_code`, `token`, UPI details |
| `POST /api/payment.php` | `action=upi_ref`, `reg_code`, `token`, `payment_ref` (UTR) |
| `/admin/` | dashboard: stats, verify UPI payments (UTR), edit / add / cancel / delete bookings, WhatsApp confirmation, check-in, CSV export |

Registrations are stored per `event.slug`, so future Master Mind editions share the same table.
