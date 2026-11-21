<?php
/**
 * Master Mind – Meet the Expert
 * ---------------------------------------------------------------
 * Copy this file to `config.php` and fill in your own values.
 * `config.php` is git-ignored so your passwords never reach GitHub.
 */

return [

    /* ---------- React frontend (Vercel) ---------- */
    'frontend_url'    => 'https://class.macadz.com',
    // Other sites allowed to call this API (Vercel URL, local dev …)
    'allowed_origins' => ['https://master-mind-sable-nine.vercel.app', 'http://localhost:5173'],
    'app_secret'      => '',   // any long random text – signs tokens & admin cookie

    /* ---------- MySQL database ---------- */
    'db' => [
        'host'    => 'localhost',      // on Hostinger / cPanel this is usually "localhost"
        'port'    => 3306,
        'name'    => 'u455934768_class',      // database name from hPanel
        'user'    => 'u455934768_class',
        'pass'    => 'PUT_YOUR_DB_PASSWORD_HERE',
        'charset' => 'utf8mb4',
    ],

    /* ---------- Current edition of the series ---------- */
    'event' => [
        'slug'        => 'favour-your-brand-2026',   // stored with every registration (Master Mind is a series)
        'series'      => 'Master Mind',
        'tagline'     => 'Meet the Expert',
        'title'       => 'Favour Your Brand',
        'subtitle'    => 'Branding Masterclass · One Day Workshop',
        'speaker'     => 'Favour Francis',
        'speaker_role'=> 'Creative Director & Brand Consultant',
        // ISO-8601 with India offset – used by the countdown
        'starts_at'   => '2026-11-26T10:30:00+05:30',
        'date_label'  => 'Thursday, 26 November 2026',
        'time_label'  => '10:30 AM',
        'venue'       => 'MAC Media School',
        'venue_map'   => 'https://share.google/HKLeRtwW1w3lzlQrD',
        'fee'         => 299,            // in rupees
        'seats'       => 60,             // total seats (0 = unlimited, hides the seat counter)
        'phone'       => '+91 90724 83502',
        'whatsapp'    => '919072483502', // digits only, used for wa.me links
        'email'       => 'info@macadz.com',
        'website'     => 'https://school.macadz.com',
    ],

    /* ---------- Payment: UPI QR ----------
     * EITHER upload your UPI QR image as assets/img/upi-qr.png (or .jpg) in this backend folder,
     * OR fill in upi_id and a QR with the amount pre-filled is generated on the website.
     * Students enter the UTR after paying; you mark them paid in /admin.
     */
    'payment' => [
        'upi_id'   => '',                 // e.g. macmedia@okaxis
        'upi_name' => 'MAC Media School',
    ],

    /* ---------- Razorpay online payment ----------
     * Fill both to send students straight to Razorpay Checkout.
     * (Alternatively put RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET in a .env file.)
     * Leave empty to use the UPI QR flow instead.
     */
    'razorpay' => [
        'key_id'     => '',
        'key_secret' => '',
    ],

    /* ---------- Confirmation e-mail with PDF ticket ----------
     * Sent automatically when a booking becomes paid, and from the dashboard.
     * Create a mailbox in hPanel → Emails (e.g. tickets@macadz.com) and fill it in.
     * Leave smtp_host empty to use PHP mail() instead (less reliable).
     */
    'mail' => [
        'from_email' => '',                  // e.g. tickets@macadz.com
        'from_name'  => 'MAC Media School',
        'smtp_host'  => 'smtp.hostinger.com',
        'smtp_port'  => 465,
        'smtp_user'  => '',                  // usually the same as from_email
        'smtp_pass'  => '',                  // mailbox password
    ],

    /* ---------- Admin panel (/admin) ---------- */
    'admin' => [
        'user' => 'admin',
        'pass'      => '',   // admin password (plain), OR leave empty and use pass_hash below
        // Optional, more secure:  php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
        'pass_hash' => '',
    ],

    // Set to false on the live server
    'debug' => false,
];
