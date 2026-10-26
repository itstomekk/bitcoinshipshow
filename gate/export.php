<?php
/**
 * Download the collected emails as CSV.
 *   https://bitcoinship.show/gate/export.php?token=YOUR_ADMIN_TOKEN&slug=shipshow
 * Add &view=1 to see them in the browser instead of downloading.
 */
require __DIR__ . '/lib.php';

$cfg   = gate_config();
$token = isset($_GET['token']) ? (string)$_GET['token'] : '';

if (!hash_equals((string)$cfg['admin_token'], $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden.\n";
    exit;
}

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9_-]/i', '', $_GET['slug']) : 'shipshow';
$file = rtrim($cfg['data_dir'], '/') . '/leads-' . $slug . '.csv';

if (!is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "No leads yet for gate '" . $slug . "'.\n";
    exit;
}

header('X-Robots-Tag: noindex, nofollow');
if (!empty($_GET['view'])) {
    header('Content-Type: text/plain; charset=utf-8');
} else {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $slug . '-emails-' . date('Y-m-d') . '.csv"');
}
readfile($file);
