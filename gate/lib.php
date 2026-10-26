<?php
/**
 * Email gate - shared helpers + page shell.
 * PHP 7.4+ compatible. No external dependencies.
 */

function gate_config() {
    static $cfg = null;
    if ($cfg === null) { $cfg = require __DIR__ . '/config.php'; }
    return $cfg;
}

function gate_get($cfg, $slug) {
    if (!isset($cfg['gates'][$slug])) { return null; }
    $g = $cfg['gates'][$slug];
    if (empty($g['active'])) { return null; }
    return $g;
}

function gate_esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function gate_client_ip() {
    $keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $v = $_SERVER[$k];
            if (strpos($v, ',') !== false) {
                $parts = explode(',', $v);
                $v = trim($parts[0]);
            }
            return $v;
        }
    }
    return '0.0.0.0';
}

function gate_ip_hash($cfg, $ip) {
    return substr(hash_hmac('sha256', $ip, $cfg['secret']), 0, 16);
}

function gate_data_dir($cfg) {
    $dir = $cfg['data_dir'];
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    return $dir;
}

/**
 * Per-IP rate limit. Returns true if the request is allowed.
 */
function gate_rate_ok($cfg, $ipHash) {
    $dir  = gate_data_dir($cfg);
    $file = $dir . '/rl_' . preg_replace('/[^a-f0-9]/', '', $ipHash) . '.txt';
    $now  = time();
    $start = $now;
    $count = 0;

    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $parts = explode('|', (string)$raw);
        if (count($parts) === 2) {
            $start = (int)$parts[0];
            $count = (int)$parts[1];
        }
        if ($now - $start > (int)$cfg['rate_window']) {
            $start = $now;
            $count = 0;
        }
    }

    $count++;
    @file_put_contents($file, $start . '|' . $count, LOCK_EX);
    return $count <= (int)$cfg['rate_max'];
}

/**
 * Append a lead. Returns 'new', 'dup' or 'error'.
 * Storage is a plain CSV per gate - openable in Excel, no database needed.
 */
function gate_store_lead($cfg, $slug, $email, $consent, $referrer, $ua, $ipHash, $utm = array()) {
    $dir  = gate_data_dir($cfg);
    $safe = preg_replace('/[^a-z0-9_-]/i', '', $slug);
    $file = $dir . '/leads-' . $safe . '.csv';
    $norm = strtolower(trim($email));

    // Campaign columns are appended AFTER ip_hash, so every existing reader that
    // indexes columns 0-5 keeps working unchanged on rows written before today.
    $utmCols = array('utm_source', 'utm_medium', 'utm_campaign');

    $fh = @fopen($file, 'c+');
    if (!$fh) { return 'error'; }
    if (!flock($fh, LOCK_EX)) { fclose($fh); return 'error'; }

    $isNew  = true;
    $st     = fstat($fh);
    $size   = isset($st['size']) ? (int)$st['size'] : 0;
    if ($size === 0) {
        fputcsv($fh, array_merge(
            array('created_at', 'email', 'consent', 'referrer', 'user_agent', 'ip_hash'),
            $utmCols
        ), ',', '"', '\\');
    } else {
        rewind($fh);
        while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if (isset($row[1]) && strtolower(trim($row[1])) === $norm) { $isNew = false; break; }
        }
    }

    if ($isNew) {
        $extra = array();
        foreach ($utmCols as $c) {
            $extra[] = isset($utm[$c]) ? substr((string)$utm[$c], 0, 80) : '';
        }
        fseek($fh, 0, SEEK_END);
        fputcsv($fh, array_merge(array(
            gmdate('c'),
            $norm,
            $consent ? '1' : '0',
            substr((string)$referrer, 0, 300),
            substr((string)$ua, 0, 300),
            $ipHash,
        ), $extra), ',', '"', '\\');
    }

    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    @chmod($file, 0600);

    return $isNew ? 'new' : 'dup';
}

/**
 * Work out when the next episode is.
 * Uses an explicit event_start if set, otherwise the recurrence rule
 * ("last tuesday of the month, 18:00 Europe/Berlin"), rolling forward
 * automatically once an episode has finished.
 * Returns a DateTime or null.
 */
function gate_event_start($g) {
    if (!empty($g['event_start'])) {
        try { return new DateTime($g['event_start']); } catch (Exception $e) { return null; }
    }
    if (empty($g['event_rule'])) { return null; }

    $r     = $g['event_rule'];
    $which = isset($r['which']) ? strtolower($r['which']) : 'last';
    $day   = isset($r['day'])   ? strtolower($r['day'])   : 'tuesday';
    $time  = isset($r['time'])  ? $r['time']              : '18:00';
    $tzid  = isset($r['tz'])    ? $r['tz']                : 'Europe/Berlin';
    $mins  = isset($g['event_mins']) ? (int)$g['event_mins'] : 120;

    try { $tz = new DateTimeZone($tzid); } catch (Exception $e) { return null; }

    $now = new DateTime('now', $tz);
    $tp  = explode(':', $time);
    $h   = isset($tp[0]) ? (int)$tp[0] : 18;
    $m   = isset($tp[1]) ? (int)$tp[1] : 0;

    // this month, then the next two - covers the roll-over
    for ($i = 0; $i < 3; $i++) {
        $month = new DateTime('now', $tz);
        $month->modify('first day of this month');
        if ($i > 0) { $month->modify('+' . $i . ' month'); }

        $expr = $which . ' ' . $day . ' of ' . $month->format('F Y');
        try { $cand = new DateTime($expr, $tz); } catch (Exception $e) { continue; }
        $cand->setTime($h, $m, 0);

        $ends = clone $cand;
        $ends->modify('+' . $mins . ' minutes');
        if ($ends > $now) { return $cand; }
    }
    return null;
}

function ics_esc($s) {
    $s = str_replace(array("\\", ";", ","), array("\\\\", "\\;", "\\,"), (string)$s);
    return str_replace(array("\r\n", "\n"), '\\n', $s);
}

/**
 * Build the VCALENDAR lines for a gate event. $start/$end must be UTC DateTimes.
 * Single source of truth: ics.php serves these bytes, invite.php attaches them.
 *
 * The room link IS in here: the invitee's own calendar entry is one of the two
 * places they get it (the Add-to-Calendar button is the other). The episode's
 * Google Calendar EVENT is the surface that holds it back until
 * room_link_in_event_hours_before - see gate/roomlink.php.
 */
function gate_ics_lines($slug, $g, $start, $end) {
    $uid = $slug . '-' . $start->format('Ymd\THis\Z') . '@bitcoinship.show';
    return array(
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//bitcoinship.show//email gate//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $start->format('Ymd\THis\Z'),
        'DTEND:' . $end->format('Ymd\THis\Z'),
        'SUMMARY:' . ics_esc($g['event_title']),
        'DESCRIPTION:' . ics_esc($g['event_note'] . "\n\nJoin: " . $g['target_url']),
        'LOCATION:' . ics_esc($g['target_url']),
        'URL:' . ics_esc($g['target_url']),
        'BEGIN:VALARM',
        'TRIGGER:-PT15M',
        'ACTION:DISPLAY',
        'DESCRIPTION:' . ics_esc($g['event_title'] . ' starts in 15 minutes'),
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR',
    );
}

/**
 * Signed token so ics.php cannot be scraped without having seen the card.
 */
function gate_token($cfg, $slug, $g) {
    return substr(hash_hmac('sha256', $slug . '|' . $g['target_url'], $cfg['secret']), 0, 32);
}

/**
 /* Shared page shell - inherits the CURRENT bitcoinship.show design system
    (corsair: IM Fell English + Pirata One, sea-ink frame, parchment cards,
    gold + blood-blue accents; replaced the retired Archivo/Space palette). */
 function gate_page($title, $bodyHtml, $status = 200) {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $t = gate_esc($title);
    echo <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{$t}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Pirata+One&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#0B1F33; --sea:#061726; --sea-mid:#0C2E4A; --sea-lite:#14486F;
    --parch:#CBDCE8; --parch-dk:#A9C3D6; --gold:#D9AE3C; --blood:#1E6FA6;
    --rule:4px solid var(--ink);
    --sh-sm:4px 4px 0 0 var(--ink); --sh:8px 8px 0 0 var(--ink); --sh-lg:10px 10px 0 0 var(--gold);
  }
  *{margin:0;padding:0;box-sizing:border-box}
  body{
    background:
      radial-gradient(circle at 18% 20%, rgba(20,72,111,.55), transparent 44%),
      radial-gradient(circle at 85% 75%, rgba(217,174,60,.14), transparent 46%),
      var(--sea);
    color:var(--parch); font-family:"IM Fell English",Georgia,"Times New Roman",serif; line-height:1.55;
    min-height:100vh; padding:46px 20px 70px; position:relative; overflow-x:hidden;
  }
  .wrap{max-width:640px;margin:0 auto;position:relative}
  .kicker{display:inline-block;background:var(--gold);color:var(--ink);border:var(--rule);
    box-shadow:var(--sh-sm); padding:6px 14px; font-family:"Pirata One",Georgia,serif;
    font-size:17px;letter-spacing:.08em;text-transform:uppercase;transform:rotate(-2deg)}
  h1{font-family:"Pirata One",Georgia,serif;font-weight:400;text-transform:uppercase;
    font-size:clamp(42px,9vw,74px);line-height:.95;margin:20px 0 8px;color:var(--gold)}
  h1 em{font-style:normal;color:var(--parch);text-shadow:5px 5px 0 var(--ink)}
  .card{background:var(--parch);color:var(--ink);border:var(--rule);box-shadow:var(--sh-lg);
    padding:28px 26px;margin-top:26px;transform:rotate(-.6deg);
    background-image:linear-gradient(160deg,#CBDCE8 0%,#B9CFE0 55%,#A9C3D6 100%)}
  .card > *{transform:rotate(.6deg)}
  .lead{font-size:18px;margin-bottom:6px;font-style:italic}
  .when{background:var(--ink);color:var(--gold);border:var(--rule);box-shadow:var(--sh-sm);
    padding:14px 16px;margin:20px 0 22px}
  .when .label{font-family:Georgia,serif;font-size:12px;letter-spacing:.14em;text-transform:uppercase;display:block;color:var(--parch-dk)}
  .when .date{font-family:"Pirata One",Georgia,serif;font-size:clamp(22px,4.6vw,30px);line-height:1.05;display:block;margin-top:4px}
  .when .local{font-size:13px;margin-top:6px;color:var(--parch-dk)}
  .btn{display:block;text-align:center;text-decoration:none;font-family:"Pirata One",Georgia,serif;text-transform:uppercase;
    font-size:24px;letter-spacing:.04em;padding:15px 20px;background:var(--blood);color:var(--parch);border:var(--rule);box-shadow:var(--sh);
    transition:transform .1s, box-shadow .1s}
  .btn:hover{transform:translate(-2px,-2px) rotate(.6deg);box-shadow:10px 10px 0 0 var(--ink)}
  .btn:active{transform:translate(4px,4px) rotate(.6deg);box-shadow:none}
  .btn.ghost{background:var(--parch);color:var(--ink);font-size:15px;padding:12px 16px;margin-top:12px}
  .urlbox{display:flex;gap:10px;align-items:center;margin-top:14px;border:var(--rule);background:#fff;padding:9px 11px}
  .urlbox code{font-family:Consolas,Menlo,monospace;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
  .urlbox button{font-family:Consolas,Menlo,monospace;font-weight:700;font-size:12px;background:var(--gold);color:var(--ink);
    border:2px solid var(--ink);padding:7px 11px;cursor:pointer;white-space:nowrap}
  .cals{display:flex;gap:12px;margin-top:14px;flex-wrap:wrap}
  .cals a{flex:1;min-width:150px;text-align:center;text-decoration:none;font-weight:700;font-size:14px;
    background:var(--gold);color:var(--ink);border:var(--rule);box-shadow:var(--sh-sm);padding:11px 12px;font-family:Georgia,serif}
  .cals a:nth-child(2){background:var(--sea-lite);color:var(--parch)}
  h2{font-family:"Pirata One",Georgia,serif;font-weight:400;text-transform:uppercase;font-size:20px;letter-spacing:.05em;margin:26px 0 10px}
  ul{margin:0;padding-left:20px;font-size:16px}
  li{margin:0 0 6px}
  .note{margin-top:22px;background:var(--gold);color:var(--ink);border:var(--rule);box-shadow:var(--sh-sm);
    padding:13px 15px;font-size:15px;font-style:italic}
  .foot{margin-top:26px;text-align:center;font-size:14px;font-style:italic}
  .foot a{color:var(--gold);text-decoration:none;border-bottom:2px solid var(--sea-mid)}
  .foot a:hover{border-bottom-color:var(--gold)}
  p{margin-bottom:10px}
  /* leads page (gate/leads.php) */
  .bar{margin:14px 0 4px;font-size:16px}.bar strong{color:var(--gold)}
  .bar a{color:var(--gold);font-weight:700;text-decoration:none;border-bottom:2px solid var(--sea-mid)}
  .find{width:100%;margin:8px 0 10px;padding:10px 12px;font-size:14px;border:var(--rule);
    font-family:Georgia,serif;background:#fff;color:var(--ink)}
  .tbl-wrap{overflow-x:auto;border:var(--rule);box-shadow:var(--sh)}
  .tbl{width:100%;border-collapse:collapse;background:var(--parch);color:var(--ink);font-size:14px}
  .tbl th{font-family:Georgia,serif;font-size:11px;letter-spacing:.1em;text-transform:uppercase;
    background:var(--ink);color:var(--gold);padding:9px 10px;text-align:left;white-space:nowrap}
  .tbl td{padding:8px 10px;border-top:1.5px solid rgba(11,31,51,.25);vertical-align:top}
  .tbl td.em{font-weight:700;white-space:nowrap}
  .tbl td.n,.tbl td.ts{white-space:nowrap;color:#33506B;font-family:Consolas,Menlo,monospace;font-size:12px}
  .tbl td.dim{color:#4A5E73;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .yes{color:#0a7d2f;font-weight:700}.no{color:#8a95a0}
</style>
</head>
<body>
<div class="wrap">
{$bodyHtml}
</div>
</body>
</html>
HTML;
}

/**
 * Control copy to the organizer on every fresh signup.
 * Plain text only, failures never block the signup flow.
 */
function gate_notify($cfg, $g, $email, $referrer) {
    $to = isset($cfg['notify_email']) ? trim($cfg['notify_email']) : '';
    if ($to === '') { return; }
    $from    = 'Bitcoin Shipshow <invites@bitcoinship.show>';
    $subject = '[shipshow] new lead: ' . $email;
    $ua      = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $body    = "new signup: " . $email . "\n"
             . "when:      " . date('Y-m-d H:i:s T') . "\n"
             . "consent:   " . (!empty($_POST['consent']) ? 'yes' : 'no') . "\n"
             . "referrer:  " . ($referrer !== '' ? $referrer : '-') . "\n"
             . "user agent:" . substr($ua, 0, 120) . "\n"
             . "all leads: https://bitcoinship.show/gate/leads.php?token=" . urlencode($cfg['admin_token']) . "&slug=shipshow\n";
    $headers = 'From: ' . $from . "\r\n"
             . 'Reply-To: hello@bitcoinship.show' . "\r\n"
             . 'X-Mailer: shipshow-gate' . "\r\n";
    @mail($to, $subject, $body, $headers);
}

/**
 * Reminder list for one episode: data/remind-<episode>.csv
 *
 * The signup email no longer carries the room link (it hands over an
 * "Add to Calendar" button and the .ics, and the link lives inside the
 * calendar entry). The link is delivered by gate/remind.php an hour before
 * the show, and this list is who it goes to: exactly the people invited for
 * THIS episode, so next month's run cannot re-mail last month's guests.
 *
 * Lives in lib.php rather than remind.php because invite.php enrols people
 * and remind.php consumes them - lib.php is the one file both already load.
 */
function remind_list_path($cfg, $episode) {
    $safe = preg_replace('/[^a-z0-9_-]/i', '', (string)$episode);
    if ($safe === '') { $safe = 'unknown'; }
    return gate_data_dir($cfg) . '/remind-' . $safe . '.csv';
}

/** Add an address to this episode's reminder list. Idempotent. */
function remind_enrol($cfg, $episode, $email) {
    $norm = strtolower(trim((string)$email));
    if ($norm === '') { return false; }

    $file = remind_list_path($cfg, $episode);
    $new  = !is_file($file);
    $fh   = @fopen($file, 'a');
    if (!$fh) { return false; }

    $ok = false;
    if (flock($fh, LOCK_EX)) {
        if ($new) { fputcsv($fh, array('email', 'invited_at', 'reminded_at')); }
        $seen = false;
        if (!$new) {
            rewind($fh);
            fgetcsv($fh);
            while (($row = fgetcsv($fh)) !== false) {
                if (isset($row[0]) && strtolower(trim($row[0])) === $norm) { $seen = true; break; }
            }
        }
        if (!$seen) {
            fseek($fh, 0, SEEK_END);
            fputcsv($fh, array($norm, gmdate('c'), ''));
            $ok = true;
        }
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    @chmod($file, 0600);
    return $ok;
}
