<?php
/**
 * Master Mind – Meet the Expert
 * ---------------------------------------------------------------
 * Copy this file to `config.php` and fill in your own values.
 * `config.php` is git-ignored so your passwords never reach GitHub.
 */

return [

    /* ---------- MySQL database ---------- */
    'db' => [
        'host'    => 'localhost',      // on Hostinger / cPanel this is usually "localhost"
        'port'    => 3306,
        'name'    => 'mastermind',     // database name you created
        'user'    => 'mastermind_user',
        'pass'    => 'CHANGE_ME',
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
        'venue_map'   => 'https://maps.google.com/?q=MAC+Media+School',
        'fee'         => 299,            // in rupees
        'seats'       => 60,             // total seats (0 = unlimited, hides the seat counter)
        'phone'       => '+91 90724 83502',
        'whatsapp'    => '919072483502', // digits only, used for wa.me links
        'email'       => 'info@macadz.com',
        'website'     => 'https://school.macadz.com',
    ],

    /* ---------- Payment ----------
     * mode = 'razorpay' : online payment through Razorpay Checkout (recommended)
     * mode = 'upi'      : registration is saved as "pending", the student is
     *                     shown your UPI ID and asked to enter the UTR / transaction ID.
     *                     You confirm payments from the admin panel.
     */
    'payment' => [
        'mode'           => 'upi',
        'razorpay_key'   => '',          // rzp_live_xxxxxxxx
        'razorpay_secret'=> '',
        'upi_id'         => 'yourname@upi',
        'upi_name'       => 'MAC Media School',
    ],

    /* ---------- Admin panel (/admin) ---------- */
    'admin' => [
        'user' => 'admin',
        // Generate a hash with:  php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
        'pass_hash' => '',
    ],

    // Set to false on the live server
    'debug' => false,
];
