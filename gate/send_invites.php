<?php
/**
 * Admin: send invite emails by hand. POST only, admin_token required.
 *
 *   one address (test):   token=...&slug=shipshow&to=someone@example.com
 *   backfill (dry run):   token=...&slug=shipshow&mode=backfill&dry=1
 *   backfill (for real):  token=...&slug=shipshow&mode=backfill
 *
 * Backfill = every lead in data/leads-<slug>.csv that has NO successful row in
 * data/invites-<slug>.csv and is not in the gate's 'invite_skip' list. So it is
 * safe to re-run: nobody ever gets the same invite twice.
 * Output is plain text, one line per address.
 */
require __DIR__ . '/lib.php';
require_once __DIR__ . '/invite.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo "POST only\n"; exit;
}

$cfg  = gate_config();
$tok  = isset($_POST['token']) ? (string)$_POST['token'] : '';
$slug = isset($_POST['slug']) ? preg_replace('/[^a-z0-9_-]/i', '', $_POST['slug']) : '';
$g    = gate_get($cfg, $slug);

if (empty($cfg['admin_token']) || !hash_equals((string)$cfg['admin_token'], $tok) || !$g) {
    http_response_code(403); echo "Forbidden.\n"; exit;
}

@set_time_limit(300);

// one address
if (!empty($_POST['to'])) {
    $to = trim((string)$_POST['to']);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo "bad address\n"; exit; }
    $ok = invite_send($cfg, $g, $slug, $to, 'manual');
    echo ($ok ? 'sent   ' : 'FAILED ') . $to . "\n";
    exit;
}

if ((isset($_POST['mode']) ? $_POST['mode'] : '') !== 'backfill') {
    http_response_code(400); echo "nothing to do (to=... or mode=backfill)\n"; exit;
}

$dry  = !empty($_POST['dry']);
$skip = array();
foreach ((isset($g['invite_skip']) ? (array)$g['invite_skip'] : array()) as $s) { $skip[strtolower(trim($s))] = true; }
$sent = invite_already_sent($cfg, $slug);

$file = gate_data_dir($cfg) . '/leads-' . $slug . '.csv';
if (!is_file($file) || ($fh = fopen($file, 'r')) === false) { http_response_code(500); echo "no leads file\n"; exit; }
fgetcsv($fh);
$seen = array();
$n = array('sent' => 0, 'failed' => 0, 'already' => 0, 'skipped' => 0, 'would' => 0);
while (($row = fgetcsv($fh)) !== false) {
    $email = isset($row[1]) ? trim($row[1]) : '';
    $key   = strtolower($email);
    if ($email === '' || isset($seen[$key])) { continue; }
    $seen[$key] = true;
    if (isset($sent[$key])) { $n['already']++; echo "already  $email\n"; continue; }
    if (isset($skip[$key]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $n['skipped']++; echo "skip     $email\n"; continue; }
    if ($dry) { $n['would']++; echo "WOULD    $email\n"; continue; }
    $ok = invite_send($cfg, $g, $slug, $email, 'backfill');
    $n[$ok ? 'sent' : 'failed']++;
    echo ($ok ? 'sent     ' : 'FAILED   ') . $email . "\n";
    usleep(1500000);   // ~1.5 s between messages - gentle on the shared MTA
}
fclose($fh);
echo "\n" . json_encode($n) . ($dry ? "  (dry run - nothing sent)" : '') . "\n";
