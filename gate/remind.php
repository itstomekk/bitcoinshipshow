<?php
/**
 * One-hour reminder - the only email that carries the call link.
 *
 * The signup email deliberately does NOT contain the room link: it hands over
 * an "Add to Calendar" button and an .ics, and the link lives inside the
 * calendar entry. This script is what actually delivers the link, an hour
 * before the show, to everyone who was invited to THIS episode.
 *
 * Runs from cron (CLI) or from a URL carrying the admin token. Sending is
 * idempotent per address: the reminded_at stamp in
 * data/remind-<episode>.csv is written after a successful hand-off to the
 * mail system, so a re-run never double-sends.
 *
 * Usage
 *   php remind.php                 normal cron run (only inside the window)
 *   php remind.php --dry-run       say what would go out, send nothing
 *   php remind.php --force         ignore the time window (testing)
 *   php remind.php --only=a@b.com  restrict to one address (testing)
 *   .../gate/remind.php?token=<admin_token>[&force=1][&dry=1][&only=a@b.com]
 *
 * PHP 7.4+ compatible.
 */

require __DIR__ . '/lib.php';
require __DIR__ . '/track.php';
require __DIR__ . '/invite.php';   // invite_clock / invite_zone_label / invite_headline

$cfg = gate_config();

// ---------------------------------------------------------------- invocation

$isCli = (PHP_SAPI === 'cli');
$argv  = $isCli && isset($argv) ? $argv : array();

$opt = array('dry' => false, 'force' => false, 'only' => '');
if ($isCli) {
    foreach (array_slice($argv, 1) as $a) {
        if ($a === '--dry-run') { $opt['dry']   = true; }
        if ($a === '--force')   { $opt['force'] = true; }
        if (strpos($a, '--only=') === 0) { $opt['only'] = substr($a, 7); }
    }
} else {
    header('Content-Type: text/plain; charset=utf-8');
    $tok = isset($_GET['token']) ? (string)$_GET['token'] : '';
    if ($tok === '' || !hash_equals((string)$cfg['admin_token'], $tok)) {
        http_response_code(403);
        echo "forbidden\n";
        exit;
    }
    $opt['dry']   = !empty($_GET['dry']);
    $opt['force'] = !empty($_GET['force']);
    $opt['only']  = isset($_GET['only']) ? trim((string)$_GET['only']) : '';
}

function remind_out($s) { echo $s . "\n"; }

// ---------------------------------------------------------------- helpers
// remind_list_path() and remind_enrol() live in lib.php, because invite.php
// enrols people into the list and this script is what consumes it.

/** [ [email, reminded_at], ... ] */
function remind_load($cfg, $episode) {
    $file = remind_list_path($cfg, $episode);
    $rows = array();
    if (!is_file($file)) { return $rows; }
    $fh = @fopen($file, 'r');
    if (!$fh) { return $rows; }
    fgetcsv($fh);
    while (($row = fgetcsv($fh)) !== false) {
        if (isset($row[0]) && trim($row[0]) !== '') {
            // Column 2 is reminded_at. Column 1 is invited_at - reading the
            // wrong one makes every row look already-reminded and silently
            // sends nothing.
            $rows[] = array('email' => trim($row[0]), 'reminded_at' => isset($row[2]) ? trim((string)$row[2]) : '');
        }
    }
    fclose($fh);
    return $rows;
}

/** Rewrite the list with reminded_at stamped for the addresses just sent. */
function remind_mark($cfg, $episode, $sentSet) {
    $file = remind_list_path($cfg, $episode);
    if (!is_file($file) || empty($sentSet)) { return; }
    $fh = @fopen($file, 'c+');
    if (!$fh) { return; }
    if (!flock($fh, LOCK_EX)) { fclose($fh); return; }

    $all = array();
    rewind($fh);
    fgetcsv($fh);
    while (($row = fgetcsv($fh)) !== false) { $all[] = $row; }

    ftruncate($fh, 0);
    rewind($fh);
    fputcsv($fh, array('email', 'invited_at', 'reminded_at'));
    foreach ($all as $row) {
        $e = isset($row[0]) ? strtolower(trim($row[0])) : '';
        if ($e !== '' && isset($sentSet[$e])) { $row[2] = gmdate('c'); }
        fputcsv($fh, array(
            isset($row[0]) ? $row[0] : '',
            isset($row[1]) ? $row[1] : '',
            isset($row[2]) ? $row[2] : '',
        ));
    }
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    @chmod($file, 0600);
}

/** The reminder itself: short, on-brand, and it carries the link. */
function remind_send($cfg, $g, $email, $start, $episode, $minutesLeft) {
    $esc  = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $utm  = array('utm_source' => 'email', 'utm_medium' => 'email',
                  'utm_campaign' => $episode !== '' ? 'shipshow-' . strtolower($episode) . '-reminder' : 'shipshow-reminder');
    $link = track_url($cfg, 'join', 'email', $email, $utm);
    $title = (string)$g['event_title'];

    $clocks = array();
    foreach (array('San Francisco' => 'America/Los_Angeles', 'Berlin' => 'Europe/Berlin') as $city => $tz) {
        $c = invite_clock($start, $tz);
        if ($c) { $clocks[$city] = $c; }
    }
    $clockText = '';
    $clockHtml = '';
    foreach ($clocks as $city => $c) {
        $clockText .= $city . ': ' . $c . "\n";
        $clockHtml .= $esc($city) . ' &middot; <b>' . $esc($c) . '</b><br>';
    }

    $subject = 'We go live in ' . (int)$minutesLeft . ' minutes - ' . $episode . ' ' . "\u{26F5}";
    $head    = 'The ship leaves in ' . (int)$minutesLeft . ' minutes';

    $text  = $head . "\n\n";
    $text .= "Here is the room link - this is the one that matters:\n" . $link . "\n\n";
    $text .= $title . "\n" . $clockText . "\n";
    $text .= "Working demos only, no slideware. Screensharing for everyone presenting, mute when you are not speaking.\n\n";
    $text .= "- The Bitcoin Shipshow crew\n";

    $html = '<div style="background:#061726;padding:28px 14px;font-family:Georgia,\'Times New Roman\',serif;">'
          . '<div style="max-width:560px;margin:0 auto;background:#061726;">'
          . '<div style="background:#0B1F33;border:4px solid #0B1F33;padding:20px 20px 14px;text-align:center;">'
          . '<img src="https://bitcoinship.show/img/shipshow-boat.png" alt="Bitcoin Shipshow" width="170" style="display:block;margin:0 auto 8px;max-width:100%;height:auto;">'
          . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:4px;text-transform:uppercase;font-weight:bold;color:#D9AE3C;">Bitcoin Shipshow</div>'
          . '</div>'
          . '<div style="background:#CBDCE8;border:4px solid #0B1F33;margin:18px 6px 0;padding:18px;color:#0B1F33;">'
          . '<div style="font-size:26px;font-weight:bold;line-height:1.15;">' . $esc($head) . '</div>'
          . '<div style="font-size:14px;line-height:1.6;margin-top:8px;">' . $clockHtml . '</div>'
          . '</div>'
          . '<div style="text-align:center;padding:16px 6px 0;">'
          . '<a href="' . $esc($link) . '" style="display:block;background:#1E6FA6;border:4px solid #0B1F33;text-decoration:none;padding:16px 18px;font-family:Georgia,\'Times New Roman\',serif;font-size:21px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#FFFFFF;">Join the Shipshow</a>'
          . '<div style="color:#A9C3D6;font-size:13px;line-height:1.6;margin-top:12px;">'
          . 'This is the room link. Please do not repost it publicly.</div>'
          . '</div>'
          . '<div style="color:#A9C3D6;font-size:12px;line-height:1.7;text-align:center;padding:20px 10px 26px;">'
          . 'Working demos only, no slideware. Screensharing for everyone presenting, mute when you are not speaking.<br>'
          . '<a href="https://bitcoinship.show" style="color:#D9AE3C;text-decoration:none;font-weight:bold;">bitcoinship.show</a>'
          . '</div>'
          . '</div></div>';

    $mail     = !empty($g['mail']) ? $g['mail'] : (!empty($cfg['mail']) ? $cfg['mail'] : array());
    $from     = !empty($mail['from'])      ? $mail['from']      : 'invites@bitcoinship.show';
    $fromName = !empty($mail['from_name']) ? $mail['from_name'] : 'Bitcoin Shipshow';
    $to       = str_replace(array("\r", "\n"), '', (string)$email);

    $boundary = '----=_shipshow_rem_' . bin2hex(random_bytes(8));
    $altBound = '----=_shipshow_rem_alt_' . bin2hex(random_bytes(6));

    $message  = "--" . $boundary . "\r\n";
    $message .= "Content-Type: multipart/alternative; boundary=\"" . $altBound . "\"\r\n\r\n";
    $message .= "--" . $altBound . "\r\n";
    $message .= "Content-Type: text/plain; charset=utf-8\r\n";
    $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $message .= quoted_printable_encode($text) . "\r\n";
    $message .= "--" . $altBound . "\r\n";
    $message .= "Content-Type: text/html; charset=utf-8\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message .= chunk_split(base64_encode($html)) . "\r\n";
    $message .= "--" . $altBound . "--\r\n";
    $message .= "--" . $boundary . "--\r\n";

    $headers  = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . ">\r\n";
    $headers .= 'Reply-To: hello@bitcoinship.show' . "\r\n";
    $headers .= 'Return-Path: ' . $from . "\r\n";
    $headers .= 'Message-ID: <' . bin2hex(random_bytes(8)) . '@bitcoinship.show>' . "\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
    $headers .= 'X-Mailer: Bitcoin Shipshow gate' . "\r\n";

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $message, $headers, '-f ' . $from);
}

// ---------------------------------------------------------------- the run

$nowUtc = new DateTime('now', new DateTimeZone('UTC'));

foreach ($cfg['gates'] as $slug => $gate) {
    $g = gate_get($cfg, $slug);
    if (!$g) { continue; }
    if (empty($g['auto_invite'])) { continue; }

    $start = gate_event_start($g);
    if (!$start) { continue; }
    $startUtc = clone $start;
    $startUtc->setTimezone(new DateTimeZone('UTC'));

    $leadMins = isset($g['remind_mins']) ? (int)$g['remind_mins'] : 60;
    if ($leadMins <= 0) { $leadMins = 60; }

    $sendFrom = clone $startUtc;
    $sendFrom->modify('-' . $leadMins . ' minutes');

    $episode = !empty($g['episode']) ? $g['episode'] : '';
    if ($episode === '' && preg_match('/\bE\d{2,4}\b/', (string)$g['event_title'], $m)) { $episode = $m[0]; }

    $inWindow = ($nowUtc >= $sendFrom && $nowUtc < $startUtc);
    if (!$inWindow && !$opt['force']) {
        remind_out('[' . $slug . '] not in window (starts ' . $startUtc->format('Y-m-d H:i') . ' UTC, '
                 . 'window opens ' . $sendFrom->format('Y-m-d H:i') . ' UTC) - nothing to do');
        continue;
    }

    $rows = remind_load($cfg, $episode);
    if (!$rows) {
        remind_out('[' . $slug . '] no reminder list yet for ' . $episode);
        continue;
    }

    $minutesLeft = max(1, (int)round(($startUtc->getTimestamp() - $nowUtc->getTimestamp()) / 60));

    $sent  = array();
    $count = 0;
    foreach ($rows as $row) {
        $email = $row['email'];
        if ($row['reminded_at'] !== '') { continue; }
        if ($opt['only'] !== '' && strtolower($email) !== strtolower($opt['only'])) { continue; }
        if ($opt['dry']) {
            remind_out('[' . $slug . '] WOULD SEND to ' . $email);
            $count++;
            continue;
        }
        $ok = remind_send($cfg, $g, $email, $start, $episode, $minutesLeft);
        remind_out('[' . $slug . '] ' . ($ok ? 'sent' : 'FAILED') . ' -> ' . $email);
        if ($ok) { $sent[strtolower($email)] = true; $count++; }
    }

    if (!$opt['dry'] && $sent) { remind_mark($cfg, $episode, $sent); }
    remind_out('[' . $slug . '] ' . $episode . ' done, ' . $count . ' message(s)' . ($opt['dry'] ? ' (dry run)' : ''));
}
