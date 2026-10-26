<?php
/**
 * Calendar file for a gate. Requires the signed token printed on the unlock card,
 * so this endpoint cannot be scraped for the room link.
 */
require __DIR__ . '/lib.php';

$cfg  = gate_config();
$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9_-]/i', '', $_GET['slug']) : '';
$tok  = isset($_GET['t']) ? (string)$_GET['t'] : '';
$g    = gate_get($cfg, $slug);

if (!$g || !hash_equals(gate_token($cfg, $slug, $g), $tok)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden.\n";
    exit;
}

try {
    $start = gate_event_start($g);
    if (!$start) { throw new Exception('no date'); }
} catch (Exception $e) {
    http_response_code(500); echo 'Bad event date'; exit;
}
$end = clone $start;
$end->modify('+' . (int)$g['event_mins'] . ' minutes');
$start->setTimezone(new DateTimeZone('UTC'));
$end->setTimezone(new DateTimeZone('UTC'));

$lines = gate_ics_lines($slug, $g, $start, $end);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $slug . '.ics"');
header('X-Robots-Tag: noindex, nofollow');
echo implode("\r\n", $lines) . "\r\n";
