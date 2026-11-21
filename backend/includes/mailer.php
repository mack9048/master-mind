<?php
declare(strict_types=1);

require_once __DIR__ . '/ticket.php';

/*
 * Sends the booking confirmation e-mail with the PDF ticket attached.
 * Uses SMTP when config 'mail.smtp_host' is set (recommended: smtp.hostinger.com, port 465,
 * with a mailbox such as tickets@macadz.com), otherwise PHP mail().
 */

function mail_configured(): bool
{
    return (string) cfg('mail.from_email', '') !== '';
}

/** Adds ticket_sent_at column if an older table doesn't have it yet. */
function ensure_ticket_column(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $cols = $pdo->query('SHOW COLUMNS FROM registrations')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('ticket_sent_at', $cols, true)) {
        $pdo->exec('ALTER TABLE registrations ADD ticket_sent_at DATETIME NULL');
    }
    $done = true;
}

/**
 * Send confirmation + ticket to the booking's e-mail.
 * @return array{ok:bool, error?:string}
 */
function send_ticket_email(PDO $pdo, array $r): array
{
    if (!mail_configured()) {
        return ['ok' => false, 'error' => 'E-mail is not set up (mail.from_email in config.php).'];
    }
    if ($r['payment_status'] !== 'paid') {
        return ['ok' => false, 'error' => 'Tickets are only sent for paid bookings.'];
    }
    if (!filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Booking has no valid e-mail address.'];
    }

    $subject = 'Your ticket: ' . cfg('event.series') . ' – ' . cfg('event.title') . ' (' . $r['reg_code'] . ')';
    $html    = ticket_email_html($r);
    $text    = ticket_email_text($r);
    $pdf     = ticket_pdf($r);

    try {
        $raw = build_mime((string) cfg('mail.from_email'), (string) cfg('mail.from_name', 'MAC Media School'), $r['email'], $r['name'], $subject, $html, $text, ticket_filename($r), $pdf);
        if ((string) cfg('mail.smtp_host', '') !== '') {
            smtp_send($raw['headers'], $raw['body'], (string) cfg('mail.from_email'), $r['email'], $subject);
        } else {
            $ok = mail($r['email'], mime_header_encode($subject), $raw['body'], $raw['headers'], '-f' . cfg('mail.from_email'));
            if (!$ok) {
                throw new RuntimeException('PHP mail() refused the message');
            }
        }
    } catch (Throwable $e) {
        error_log('[mastermind] mail: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not send e-mail: ' . $e->getMessage()];
    }

    ensure_ticket_column($pdo);
    $pdo->prepare('UPDATE registrations SET ticket_sent_at = NOW() WHERE id = ?')->execute([$r['id']]);
    return ['ok' => true];
}

/** Send automatically once, right after a booking becomes paid. Never throws. */
function auto_send_ticket(PDO $pdo, int $id): void
{
    try {
        if (!mail_configured()) {
            return;
        }
        ensure_ticket_column($pdo);
        $st = $pdo->prepare('SELECT * FROM registrations WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        if ($r && $r['payment_status'] === 'paid' && empty($r['ticket_sent_at'])) {
            send_ticket_email($pdo, $r);
        }
    } catch (Throwable $e) {
        error_log('[mastermind] auto ticket: ' . $e->getMessage());
    }
}

function mime_header_encode(string $s): string
{
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

/** @return array{headers:string, body:string} */
function build_mime(string $fromEmail, string $fromName, string $to, string $toName, string $subject, string $html, string $text, string $fileName, string $file): array
{
    $mixed = 'mm_mixed_' . bin2hex(random_bytes(8));
    $alt   = 'mm_alt_' . bin2hex(random_bytes(8));
    $eol   = "\r\n";

    $headers = implode($eol, [
        'From: ' . mime_header_encode($fromName) . " <$fromEmail>",
        'Reply-To: ' . (cfg('event.email') ?: $fromEmail),
        'MIME-Version: 1.0',
        "Content-Type: multipart/mixed; boundary=\"$mixed\"",
    ]);

    $body = "--$mixed$eol"
        . "Content-Type: multipart/alternative; boundary=\"$alt\"$eol$eol"
        . "--$alt$eol"
        . "Content-Type: text/plain; charset=UTF-8$eol"
        . "Content-Transfer-Encoding: base64$eol$eol"
        . chunk_split(base64_encode($text), 76, $eol)
        . "--$alt$eol"
        . "Content-Type: text/html; charset=UTF-8$eol"
        . "Content-Transfer-Encoding: base64$eol$eol"
        . chunk_split(base64_encode($html), 76, $eol)
        . "--$alt--$eol"
        . "--$mixed$eol"
        . "Content-Type: application/pdf; name=\"$fileName\"$eol"
        . "Content-Disposition: attachment; filename=\"$fileName\"$eol"
        . "Content-Transfer-Encoding: base64$eol$eol"
        . chunk_split(base64_encode($file), 76, $eol)
        . "--$mixed--$eol";

    return ['headers' => $headers, 'body' => $body];
}

/** Minimal SMTP client (SSL on 465 or STARTTLS on 587) with AUTH LOGIN. */
function smtp_send(string $headers, string $body, string $from, string $to, string $subject): void
{
    $host = (string) cfg('mail.smtp_host');
    $port = (int) cfg('mail.smtp_port', 465);
    $user = (string) cfg('mail.smtp_user', $from);
    $pass = (string) cfg('mail.smtp_pass', '');

    $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 20);
    if (!$fp) {
        throw new RuntimeException("SMTP connect failed: $errstr");
    }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $c, array $expect) use ($fp, $read): string {
        if ($c !== '') {
            fwrite($fp, $c . "\r\n");
        }
        $resp = $read();
        if (!in_array((int) substr($resp, 0, 3), $expect, true)) {
            throw new RuntimeException('SMTP error: ' . trim($resp));
        }
        return $resp;
    };

    $cmd('', [220]);
    $helo = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
    $cmd("EHLO $helo", [250]);
    if ($port === 587) {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP STARTTLS failed');
        }
        $cmd("EHLO $helo", [250]);
    }
    $cmd('AUTH LOGIN', [334]);
    $cmd(base64_encode($user), [334]);
    $cmd(base64_encode($pass), [235]);
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);
    $msg = "To: <$to>\r\nSubject: " . mime_header_encode($subject) . "\r\nDate: " . date('r') . "\r\n" . $headers . "\r\n\r\n" . $body;
    $msg = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $msg));
    $cmd($msg . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
}

function ticket_email_text(array $r): string
{
    return "Hi {$r['name']},\n\nYour seat is confirmed for " . cfg('event.series') . ' – ' . cfg('event.title') . " with " . cfg('event.speaker') . ".\n\n"
        . "Registration ID: {$r['reg_code']}\nDate: " . cfg('event.date_label') . "\nTime: " . cfg('event.time_label') . "\nVenue: " . cfg('event.venue')
        . "\nMap: " . cfg('event.venue_map') . "\n\nYour entry ticket is attached (PDF). You can also download it here:\n" . ticket_url($r['reg_code'])
        . "\n\nPlease show the ticket or your Registration ID at the entry desk.\nQuestions? Call " . cfg('event.phone') . "\n\nSee you there!\nMAC Media School";
}

function ticket_email_html(array $r): string
{
    $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $row = fn ($k, $v) => "<tr><td style=\"padding:6px 0;color:#8a8a92;font-size:13px;width:90px\">$k</td><td style=\"padding:6px 0;color:#111;font-size:14px;font-weight:600\">$v</td></tr>";
    return '<!doctype html><html><body style="margin:0;background:#f2f2f4;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f2f4;padding:24px 12px"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden">
<tr><td style="background:#111113;padding:28px 28px 24px">
  <div style="display:inline-block;background:#f9c816;color:#111;font-size:11px;font-weight:700;letter-spacing:1.5px;padding:6px 12px;border-radius:20px">' . $e(strtoupper(cfg('event.series') . ' · ' . cfg('event.tagline'))) . '</div>
  <h1 style="margin:16px 0 4px;color:#fff;font-size:28px;line-height:1.1">You’re in! 🎉</h1>
  <p style="margin:0;color:#c9c9d0;font-size:15px">' . $e(cfg('event.title')) . ' — Branding Masterclass with ' . $e(cfg('event.speaker')) . '</p>
</td></tr>
<tr><td style="padding:26px 28px 8px">
  <p style="margin:0 0 16px;color:#111;font-size:15px">Hi ' . $e($r['name']) . ', your seat is <b>confirmed</b>. Your entry ticket is attached to this e-mail as a PDF.</p>
  <div style="background:#111113;border-radius:12px;padding:16px;text-align:center;margin:0 0 18px">
    <div style="color:#8a8a92;font-size:11px;letter-spacing:2px">REGISTRATION ID</div>
    <div style="color:#f9c816;font-size:26px;font-weight:700;letter-spacing:2px;margin-top:4px">' . $e($r['reg_code']) . '</div>
  </div>
  <table role="presentation" cellpadding="0" cellspacing="0" width="100%">'
    . $row('Date', $e(cfg('event.date_label')))
    . $row('Time', $e(cfg('event.time_label')))
    . $row('Venue', $e(cfg('event.venue')) . ' · <a href="' . $e(cfg('event.venue_map')) . '" style="color:#3d6bff">Map</a>')
    . $row('Paid', '₹' . (int) $r['amount']) . '
  </table>
  <p style="margin:22px 0 8px;text-align:center"><a href="' . $e(ticket_url($r['reg_code'])) . '" style="display:inline-block;background:#f9c816;color:#111;text-decoration:none;font-weight:700;letter-spacing:1px;padding:14px 26px;border-radius:30px">DOWNLOAD TICKET (PDF)</a></p>
  <p style="margin:18px 0 0;color:#555;font-size:13px">Please show this ticket or your Registration ID at the entry desk. Questions? Call ' . $e(cfg('event.phone')) . '.</p>
</td></tr>
<tr><td style="padding:18px 28px 26px;color:#8a8a92;font-size:12px">MAC Media School · <a href="' . $e(cfg('event.website')) . '" style="color:#8a8a92">school.macadz.com</a></td></tr>
</table></td></tr></table></body></html>';
}
