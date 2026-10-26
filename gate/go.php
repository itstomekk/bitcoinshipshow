<?php
/**
 * Click counter + redirect.  /gate/go.php?l=<label>&s=<source>[&c=<sig>]
 *
 * Logs one row to data/clicks-<slug>.csv and 302s to the destination. The
 * destination is resolved from a fixed whitelist / the gate config, never from
 * the URL, so this cannot be used as an open redirect. Unknown labels go to the
 * homepage (logged as 'unknown') so a stale link never dead-ends.
 */
require __DIR__ . '/lib.php';
require_once __DIR__ . '/track.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate');

$slug  = 'shipshow';
$cfg   = gate_config();
$g     = gate_get($cfg, $slug);
$home  = 'https://bitcoinship.show/';

$label = strtolower(preg_replace('/[^a-z0-9_-]/i', '', isset($_GET['l']) ? (string)$_GET['l'] : ''));
$src   = substr(strtolower(preg_replace('/[^a-z0-9_-]/i', '', isset($_GET['s']) ? (string)$_GET['s'] : '')), 0, 24);
$sig   = substr(preg_replace('/[^a-f0-9]/', '', isset($_GET['c']) ? (string)$_GET['c'] : ''), 0, 8);

$dest = '';
if ($g) {
    $fixed = array(
        'home'  => $home,
        'ft'    => 'https://www.freedomtech.co',
        'bd'    => 'https://thebitcoindistrict.com',
        'ht'    => 'https://hivetalk.org',
        'yt'    => 'https://www.youtube.com/@BitcoinShipshow',
        'yt-live' => !empty($g['stream_url']) ? $g['stream_url'] : 'https://www.youtube.com/@BitcoinShipshow',
        'x'     => 'https://x.com/bitcoinshipshow',
        'nostr' => !empty($g['nostr_url']) ? $g['nostr_url']
                   : 'https://njump.me/npub1mtjn0yp705hvgszssf2ddjpzk3zzjzgvu58dwwpkxqud0hde6fxss7cwyw',
    );
    if ($label === 'join') {
        $dest = $g['target_url'];
    } elseif ($label === 'ics') {
        $dest = 'https://bitcoinship.show/gate/ics.php?slug=' . rawurlencode($slug) . '&t=' . gate_token($cfg, $slug, $g);
    } elseif (isset($fixed[$label])) {
        $dest = $fixed[$label];
    } elseif (strpos($label, 'feat-') === 0 && !empty($g['featured']) && is_array($g['featured'])) {
        foreach ($g['featured'] as $f) {
            if ('feat-' . track_slug($f['name']) === $label) { $dest = $f['url']; break; }
        }
    }
}
$known = ($dest !== '' && preg_match('~^https://~i', $dest));
if (!$known) { $dest = $home; $label = 'unknown'; }

// ---- log (GET only: HEAD/prefetch probes are not clicks) -----------------
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET' && $g) {
    $ua  = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
    $bot = preg_match('~bot|crawl|spider|slurp|preview|proxy|scanner|monitor|fetch|facebookexternalhit|slack|telegram|whatsapp|discord|curl|wget|python|java/|go-http|headless|barracuda|mimecast|proofpoint|safelinks|zscaler~i', $ua) ? 1 : 0;
    if ($ua === '') { $bot = 1; }
    $ref = '';
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $h = parse_url((string)$_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        $ref = $h ? $h : '';
    }
    $dir = gate_data_dir($cfg);
    if ($dir) {
        $file = $dir . '/clicks-' . $slug . '.csv';
        if (!is_file($file) || filesize($file) < 5 * 1024 * 1024) {   // hard cap: no log spam
            $new = !is_file($file);
            $fh  = @fopen($file, 'a');
            if ($fh) {
                if (flock($fh, LOCK_EX)) {
                    if ($new) { fputcsv($fh, array('ts', 'label', 'src', 'sig', 'ip_hash', 'bot', 'ref', 'ua')); }
                    fputcsv($fh, array(gmdate('c'), $label, $src, $sig,
                                       gate_ip_hash($cfg, gate_client_ip()), $bot, $ref, substr($ua, 0, 140)));
                    flock($fh, LOCK_UN);
                }
                fclose($fh);
                @chmod($file, 0600);
            }
        }
    }
}

// Forward UTM campaign params to the destination so the landing page (and
// GoatCounter) can attribute the visit to source / medium / campaign. Only the
// known UTM keys are forwarded; everything else is dropped.
$utm = array();
foreach (array('utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content') as $uk) {
    if (isset($_GET[$uk]) && (string)$_GET[$uk] !== '') {
        $utm[] = rawurlencode($uk) . '=' . rawurlencode((string)$_GET[$uk]);
    }
}
if ($utm) {
    $dest .= (strpos($dest, '?') === false ? '?' : '&') . implode('&', $utm);
}

header('Location: ' . $dest, true, 302);
exit;
