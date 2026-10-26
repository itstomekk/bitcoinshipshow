<?php
/**
 * Leads viewer - a clean table of who signed up, browser-side.
 *   https://bitcoinship.show/gate/leads.php?token=YOUR_ADMIN_TOKEN&slug=shipshow
 * Same token as export.php (admin_token in gate/config.php). No token = 403.
 * Rendered through gate_page(), which carries the current corsair design.
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

$rows = array();
if (is_file($file)) {
    $fh = @fopen($file, 'r');
    if ($fh) {
        $first = true;
        while (($r = fgetcsv($fh)) !== false) {
            if ($r === array(null) || $r === false) { continue; }
            if ($first && isset($r[0]) && $r[0] === 'created_at') { $first = false; continue; }
            $first = false;
            $rows[] = $r;
        }
        fclose($fh);
    }
}
// newest first
$rows = array_reverse($rows);
$count = count($rows);

$trs = '';
foreach ($rows as $i => $r) {
    $ts   = isset($r[0]) ? $r[0] : '';
    $em   = isset($r[1]) ? $r[1] : '';
    $con  = isset($r[2]) && $r[2] === '1';
    $des  = isset($r[3]) ? $r[3] : '';
    $ref  = isset($r[4]) ? $r[4] : '';
    $ua   = isset($r[5]) ? $r[5] : '';
    // Campaign columns (utm_source/medium/campaign) are appended by gate_store_lead
    // as columns 6-8, so they only exist on rows written 9 columns wide. Older rows
    // are 6 wide (Oct 2026) or 7 wide (Aug-Sep 2026, with a leading `design` column)
    // and their column 6 is ip_hash - never read a campaign out of those.
    $camp = '';
    if (count($r) >= 9) {
        $camp = implode('/', array_filter(array(
            trim((string)$r[6]), trim((string)$r[7]), trim((string)$r[8]),
        ), function ($v) { return $v !== ''; }));
    }

    // human-ish date (server TZ), keep raw in title
    $when = $ts;
    try { $when = (new DateTime($ts))->format('Y-m-d H:i'); } catch (Exception $e) {}

    $reftxt = $ref;
    if (preg_match('#^https?://([^/]+)#i', (string)$ref, $m)) { $reftxt = $m[1]; }

    $trs .=
        '<tr data-row>' .
        '<td class="n">' . ($count - $i) . '</td>' .
        '<td class="ts" title="' . gate_esc($ts) . '">' . gate_esc($when) . '</td>' .
        '<td class="em">' . gate_esc($em) . '</td>' .
        '<td>' . ($con ? '<span class="yes">' . "\u{2713}" . '</span>' : '<span class="no">&#8212;</span>') . '</td>' .
        '<td>' . gate_esc($des) . '</td>' .
        '<td class="dim">' . gate_esc($reftxt) . '</td>' .
        '<td class="dim">' . gate_esc($camp) . '</td>' .
        '</tr>';
}

$body =
  '<p class="kicker">BITCOIN SHIPSHOW</p>' .
  '<h1>Leads</h1>' .
  '<div class="bar">' .
    '<strong>' . $count . '</strong> signups &middot; gate "' . gate_esc($slug) . '"' .
    ' &middot; <a href="export.php?token=' . rawurlencode($token) . '&amp;slug=' . rawurlencode($slug) . '">raw CSV &#8595;</a>' .
  '</div>' .
  '<input class="find" id="find" type="search" placeholder="Filter email or referrer..." autocomplete="off">' .
  '<div class="tbl-wrap">' .
    '<table class="tbl" id="tbl">' .
      '<thead><tr>' .
        '<th>#</th><th>when (server)</th><th>email</th><th>ok</th><th>design</th><th>referrer</th><th>campaign</th>' .
      '</tr></thead>' .
      '<tbody>' . ($trs !== '' ? $trs : '<tr><td colspan="7" class="dim">No leads yet.</td></tr>') . '</tbody>' .
   '</table>' .
  '</div>' .
  '<script>' .
    'var f=document.getElementById("find"),t=document.getElementById("tbl");' .
    'if(f&&t){f.addEventListener("input",function(){' .
      'var q=f.value.trim().toLowerCase();' .
      'Array.prototype.forEach.call(t.querySelectorAll("tr[data-row]"),function(tr){' .
        'tr.style.display=tr.textContent.toLowerCase().indexOf(q)>-1?"":"none";' .
      '});' .
    '});}' .
  '</script>';

gate_page('Leads - ' . $slug, $body, 200);