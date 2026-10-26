<?php
/**
 * The room link goes into the episode's own Google Calendar event - but only
 * room_link_in_event_hours_before hours before the start, never before that.
 *
 * Why this file exists: the event on bitcoinshipshow@gmail.com is the one new
 * signups are added to as guests (gate/gcal.php). Tomek wants the room to show
 * up there 48h out, not three weeks early. Google has no "edit this later"
 * primitive, so this script is the scheduler: run it from cron (hourly is
 * plenty) and it enforces the rule in BOTH directions.
 *
 *   now <  start - 48h   -> the event must NOT carry the room link
 *   now >= start - 48h   -> the event MUST carry the room link
 *
 * Silent by construction: every write goes out with sendUpdates=none, so no
 * guest gets an "event changed" email. Attendees, times, reminders and the rest
 * of the description are preserved - only `location` and the JOIN THE ROOM block
 * in the description are touched, and only when they are already wrong.
 *
 * Usage
 *   php roomlink.php                       enforce (cron)
 *   php roomlink.php --dry-run             say what it would change
 *   php roomlink.php --config=<file.php>   load that config instead of
 *                                          gate/config.php (workstation runs:
 *                                          the private config is not in the repo)
 *   php roomlink.php --token-file=<json>   run from the workstation with an
 *                                          OAuth token file instead of the
 *                                          config's calendar credential
 *   .../gate/roomlink.php?token=<admin_token>[&dry=1]
 *
 * The credential is the config's `google` block, which is calendar-only by
 * design. Without it the script reports "off" and changes nothing.
 *
 * PHP 7.4+ compatible.
 */

require __DIR__ . '/lib.php';

$SLUG = 'shipshow';
$MARKER = '<b>🎥 JOIN THE ROOM</b>';   // presence of this = link is in the description
$ANCHOR = '<b>🕐 WHEN</b>';            // the block is inserted just above this

// ---------------------------------------------------------------- invocation

$isCli = (PHP_SAPI === 'cli');
$opt   = array('dry' => false, 'token_file' => '', 'config' => '');
if ($isCli) {
    $argv = isset($argv) ? $argv : array();
    foreach (array_slice($argv, 1) as $a) {
        if ($a === '--dry-run' || $a === '--dry') { $opt['dry'] = true; }
        if (strpos($a, '--token-file=') === 0)    { $opt['token_file'] = substr($a, 13); }
        if (strpos($a, '--config=') === 0)        { $opt['config'] = substr($a, 9); }
    }
}

function roomlink_out($s) { echo $s . "\n"; }

// On the server the config is gate/config.php; a workstation run has to point at
// the private file, which is deliberately not in the repo.
if ($opt['config'] !== '') {
    if (!is_file($opt['config'])) { roomlink_out('roomlink: no such config ' . $opt['config']); exit(1); }
    $cfg = require $opt['config'];
} else {
    $cfg = gate_config();
}

$g = gate_get($cfg, $SLUG);
if (!$g) { roomlink_out('roomlink: no active gate ' . $SLUG); exit(1); }

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $tok = isset($_GET['token']) ? (string)$_GET['token'] : '';
    if ($tok === '' || !hash_equals((string)$cfg['admin_token'], $tok)) {
        http_response_code(403);
        echo "forbidden\n";
        exit;
    }
    $opt['dry'] = !empty($_GET['dry']);
}

// ---------------------------------------------------------------- google api

/** Calendar credential: the config's, or an OAuth token file for a local run. */
function roomlink_credentials($cfg, $tokenFile) {
    if ($tokenFile !== '') {
        if (!is_file($tokenFile)) { return null; }
        $j = json_decode((string)@file_get_contents($tokenFile), true);
        if (!is_array($j) || empty($j['refresh_token']) || empty($j['client_id']) || empty($j['client_secret'])) {
            return null;
        }
        return array('client_id' => $j['client_id'], 'client_secret' => $j['client_secret'], 'refresh_token' => $j['refresh_token']);
    }
    if (empty($cfg['google']['refresh_token'])) { return null; }
    return array(
        'client_id'     => $cfg['google']['client_id'],
        'client_secret' => $cfg['google']['client_secret'],
        'refresh_token' => $cfg['google']['refresh_token'],
    );
}

function roomlink_http($method, $url, $body, $headers) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return array('status' => $status, 'body' => $raw === false ? '' : $raw);
    }
    // Fallback for a workstation run: the bundled CLI PHP has no curl, and the
    // server path always takes the branch above.
    $ctx = stream_context_create(array('http' => array(
        'method'        => $method,
        'header'        => implode("\r\n", $headers),
        'content'       => $body === null ? '' : $body,
        'timeout'       => 45,
        'ignore_errors' => true,          // keep the body of a 4xx response
    )));
    $raw = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('~\s(\d{3})\s~', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }
    return array('status' => $status, 'body' => $raw === false ? '' : $raw);
}

function roomlink_access_token($cred) {
    $res = roomlink_http('POST', 'https://oauth2.googleapis.com/token',
        http_build_query(array(
            'client_id'     => $cred['client_id'],
            'client_secret' => $cred['client_secret'],
            'refresh_token' => $cred['refresh_token'],
            'grant_type'    => 'refresh_token',
        )),
        array('Content-Type: application/x-www-form-urlencoded'));
    if ($res['status'] !== 200) { return null; }
    $j = json_decode($res['body'], true);
    return empty($j['access_token']) ? null : $j['access_token'];
}

/** The room block, in the shape the event already uses. */
function roomlink_block($link) {
    $esc = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    return "<b>🎥 JOIN THE ROOM</b><br>\n<a href=\"" . $esc . "\">" . $esc . "</a><br>\n";
}

/** Drop the block (its label line and the link line under it) from a description. */
function roomlink_strip($desc) {
    $out = preg_replace('~<b>[^<]*JOIN THE ROOM</b><br>\s*(?:<a [^>]*>[^<]*</a><br>\s*)?~u', '', (string)$desc);
    return $out === null ? (string)$desc : $out;
}

/** Put the block back just above the WHEN heading (or at the top if it is gone). */
function roomlink_insert($desc, $block, $anchor) {
    $pos = strpos((string)$desc, $anchor);
    if ($pos === false) { return $block . (string)$desc; }
    return substr($desc, 0, $pos) . $block . substr($desc, $pos);
}

function roomlink_log($cfg, $line) {
    $file = gate_data_dir($cfg) . '/roomlink.log';
    @file_put_contents($file, gmdate('Y-m-d\TH:i:s\Z') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

// ---------------------------------------------------------------- main

try {
    $start = gate_event_start($g);
    if (!$start) { throw new Exception('no event date'); }
} catch (Exception $e) {
    roomlink_out('roomlink: ' . $e->getMessage());
    exit(1);
}
$start->setTimezone(new DateTimeZone('UTC'));

$hours = isset($g['room_link_in_event_hours_before']) ? (int)$g['room_link_in_event_hours_before'] : 48;
$release = clone $start;
$release->modify('-' . $hours . ' hours');

$now = new DateTime('now', new DateTimeZone('UTC'));
$wantLink = ($now >= $release);
$link = (string)$g['target_url'];

roomlink_out('event start  : ' . $start->format('Y-m-d H:i:s') . ' UTC');
roomlink_out('link goes in : ' . $release->format('Y-m-d H:i:s') . ' UTC (' . $hours . 'h before)');
roomlink_out('now          : ' . $now->format('Y-m-d H:i:s') . ' UTC');
roomlink_out('rule says    : ' . ($wantLink ? 'event MUST carry the room link' : 'event must NOT carry the room link'));

$cred = roomlink_credentials($cfg, $opt['token_file']);
if (!$cred) {
    roomlink_out('credential   : off (no calendar credential' . ($opt['token_file'] !== '' ? ' in ' . $opt['token_file'] : ' in config[google]') . ')');
    roomlink_log($cfg, 'off: no calendar credential');
    exit(0);
}
$token = roomlink_access_token($cred);
if (!$token) {
    roomlink_out('credential   : error (token refresh failed)');
    roomlink_log($cfg, 'error: token refresh failed');
    exit(1);
}

$gc  = isset($g['gcal']) ? $g['gcal'] : array();
$cal = rawurlencode((string)$gc['calendar_id']);
$evid = rawurlencode((string)$gc['event_id']);
$auth = array('Authorization: Bearer ' . $token, 'Accept: application/json');
$base = 'https://www.googleapis.com/calendar/v3/calendars/' . $cal . '/events/' . $evid;

$res = roomlink_http('GET', $base, null, $auth);
if ($res['status'] !== 200) {
    roomlink_out('event        : error (GET ' . $res['status'] . ')');
    roomlink_log($cfg, 'error: GET ' . $res['status']);
    exit(1);
}
$ev   = json_decode($res['body'], true);
$desc = isset($ev['description']) ? (string)$ev['description'] : '';
$loc  = isset($ev['location']) ? (string)$ev['location'] : '';

$inDesc = (strpos($desc, $MARKER) !== false);
$inLoc  = (strpos($loc, $link) !== false);
roomlink_out('event now    : location ' . ($inLoc ? 'has' : 'has no') . ' link, description ' . ($inDesc ? 'has' : 'has no') . ' room block');

if ($wantLink) {
    $wantLoc  = $link;
    $wantDesc = $inDesc ? $desc : roomlink_insert($desc, roomlink_block($link), $ANCHOR);
    $done = $inDesc && $inLoc;
} else {
    $wantLoc  = '';
    $wantDesc = roomlink_strip($desc);
    $done = !$inDesc && !$inLoc;
}

if ($done) {
    roomlink_out('result       : already correct, nothing to do');
    exit(0);
}

if ($opt['dry']) {
    roomlink_out('result       : DRY RUN, would set location to ' . ($wantLoc === '' ? '(empty)' : $wantLoc)
        . ' and the description to ' . strlen($wantDesc) . ' bytes (was ' . strlen($desc) . ')');
    exit(0);
}

$patch = json_encode(array('location' => $wantLoc, 'description' => $wantDesc));
$res = roomlink_http('PATCH', $base . '?sendUpdates=none', $patch,
    array_merge($auth, array('Content-Type: application/json')));
if ($res['status'] !== 200) {
    roomlink_out('result       : error (PATCH ' . $res['status'] . ' ' . substr($res['body'], 0, 160) . ')');
    roomlink_log($cfg, 'error: PATCH ' . $res['status']);
    exit(1);
}

// read back: a 200 is not proof the event now says what we wanted
$res = roomlink_http('GET', $base, null, $auth);
$ev   = json_decode($res['body'], true);
$desc = isset($ev['description']) ? (string)$ev['description'] : '';
$loc  = isset($ev['location']) ? (string)$ev['location'] : '';
$okNow = $wantLink
    ? (strpos($desc, $MARKER) !== false && strpos($loc, $link) !== false)
    : (strpos($desc, $MARKER) === false && strpos($loc, $link) === false);

roomlink_out('result       : ' . ($okNow ? 'patched and read back OK' : 'PATCHED BUT READBACK DISAGREES'));
roomlink_log($cfg, ($wantLink ? 'added' : 'removed') . ' room link (readback ' . ($okNow ? 'ok' : 'MISMATCH') . ')');
exit($okNow ? 0 : 1);
