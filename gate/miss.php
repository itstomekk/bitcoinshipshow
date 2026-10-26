<?php
/**
 * 404 catch-all: log the miss, then send the visitor to the homepage.
 *
 * Wired with `ErrorDocument 404 /gate/miss.php` in the docroot .htaccess.
 *
 *   - asset requests (any file extension we actually serve) and anything under
 *     /gate keep a real 404. A broken stylesheet, image or endpoint must stay
 *     visibly broken instead of silently turning into the homepage.
 *   - everything else is logged to data/misses-<slug>.csv and 302'd to the
 *     homepage carrying utm_source=fallback, so the dashboard shows both which
 *     paths are dead and how much traffic lands on the fallback.
 *   - the miss log is a SEPARATE file with its own cap. Bot probes must never be
 *     able to fill the click log that the signup funnel depends on.
 *
 * PHP 7.4+ compatible. No external dependencies.
 */
require __DIR__ . '/lib.php';

$cfg  = gate_config();
$slug = 'shipshow';
$home = 'https://bitcoinship.show/';

$uri  = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '/';
$path = parse_url($uri, PHP_URL_PATH);
if (!is_string($path) || $path === '') { $path = '/'; }
$qs   = parse_url($uri, PHP_URL_QUERY);
$qs   = is_string($qs) ? substr($qs, 0, 200) : '';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate');

$ext     = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$assetExt = array(
    'css', 'js', 'mjs', 'map', 'json', 'xml', 'txt', 'csv',
    'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp', 'avif', 'bmp',
    'woff', 'woff2', 'ttf', 'otf', 'eot',
    'pdf', 'zip', 'gz', 'mp3', 'mp4', 'webm', 'wasm',
);
$isAsset = ($ext !== '' && in_array($ext, $assetExt, true));
$isAdmin = (strpos($path, '/gate') === 0);

if ($isAsset || $isAdmin) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "404 not found\n";
    exit;
}

// ---- log the miss --------------------------------------------------------
// Same user-agent classifier as go.php, so the two logs agree on what a bot is.
$ua  = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
$bot = preg_match('~bot|crawl|spider|slurp|preview|proxy|scanner|monitor|fetch|facebookexternalhit|slack|telegram|whatsapp|discord|curl|wget|python|java/|go-http|headless|barracuda|mimecast|proofpoint|safelinks|zscaler~i', $ua) ? 1 : 0;
if ($ua === '') { $bot = 1; }

$ref = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $h = parse_url((string)$_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    $ref = $h ? substr($h, 0, 80) : '';
}

$dir = gate_data_dir($cfg);
if ($dir) {
    $file = $dir . '/misses-' . $slug . '.csv';
    if (!is_file($file) || filesize($file) < 2 * 1024 * 1024) {   // own hard cap
        $new = !is_file($file);
        $fh  = @fopen($file, 'a');
        if ($fh) {
            if (flock($fh, LOCK_EX)) {
                if ($new) { fputcsv($fh, array('ts', 'path', 'query', 'bot', 'ref', 'ua')); }
                fputcsv($fh, array(gmdate('c'), substr($path, 0, 200), $qs, $bot, $ref, substr($ua, 0, 140)));
                flock($fh, LOCK_UN);
            }
            fclose($fh);
            @chmod($file, 0600);
        }
    }
}

// ---- send them home ------------------------------------------------------
$utmSource = trim($path, '/');
if ($utmSource === '') { $utmSource = 'home'; }
$fallbackUtm = http_build_query(array(
    // Use the requested missing path as the source so each broken URL is a
    // distinct, immediately recognizable entry in traffic attribution.
    'utm_source' => $utmSource,
    'utm_medium' => '404',
    'utm_campaign' => 'broken-link',
));
header('Location: ' . $home . '?' . $fallbackUtm, true, 302);
exit;
