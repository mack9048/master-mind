<?php
// The website itself is the React app on Vercel. This backend only serves /api and /admin.
$cfg = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$to  = getenv('FRONTEND_URL') ?: ($cfg['frontend_url'] ?? '/admin/');
header('Location: ' . $to, true, 302);
