<?php
/**
 * Bitcoin Shipshow — analytics dashboard (funnel: signups -> invites -> clicks).
 *   https://bitcoinship.show/gate/analytics.php?token=YOUR_ADMIN_TOKEN&slug=shipshow
 *
 * Same admin_token as leads.php. Server-rendered (no JS, no external deps),
 * reads the same three CSVs the gate writes + metrics-shipshow.json. Charts SVG.
 * "Pirate terminal" theme: corsair sea-ink/parchment/gold + monospace phosphor.
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
$dir  = rtrim($cfg['data_dir'], '/');

/* ------------------------------------------------------------------ load */

function an_is_test_email($e) {
    $e = strtolower(trim((string)$e));
    if ($e === '')                          return true;
    if (strpos($e, 'test') !== false)       return true;
    if ($e === 'kolotom@gmail.com')         return true;
    if (strpos($e, 'kolotom+') === 0)       return true;
    if ($e === 'info@thebitcoindistrict.com') return true;
    if ($e === 'invites@bitcoinship.show')  return true;
    return false;
}

/* Short permalink sources (/x, /yt, /nostr, /lp, /meetup) are public for the whole
   internet, so their clicks include link checkers and crawlers that a user-agent
   filter cannot catch. They are traffic, not a step in the invite funnel, and are
   reported in their own CLICK SOURCES group instead of the funnel numbers. */
function an_is_permalink_src($src) {
    $s = strtolower(trim((string)$src));
    if ($s === '') return false;
    foreach (array('-bio', '-post', '-link', '-dir') as $suffix) {
        if (substr($s, -strlen($suffix)) === $suffix) return true;
    }
    return false;
}

function an_load($path) {
    $rows = array();
    if (!is_file($path)) return $rows;
    $fh = @fopen($path, 'r');
    if (!$fh) return $rows;
    while (($r = fgetcsv($fh)) !== false) {
        if ($r === array(null) || $r === false || count($r) < 2) continue;
        $rows[] = $r;
    }
    fclose($fh);
    return $rows;
}

$leads = array();
foreach (an_load($dir . '/leads-' . $slug . '.csv') as $r) {
    if (isset($r[0]) && $r[0] === 'created_at') continue;
    /* The lead CSV has three row shapes (see HANDOFF): 6 cols with the referrer at
       index 3, 7 cols (Aug-Sep, an extra `design` at index 3) with the referrer at 4,
       and 9 cols since 2026-10-05 with utm_source/medium/campaign appended at 6-8.
       Locate the fields by row width - reading index 4 unconditionally is what made
       the referrer report print user-agent strings. */
    $n = count($r);
    if ($n === 7) {
        $design = isset($r[3]) ? $r[3] : '';
        $ref    = isset($r[4]) ? $r[4] : '';
        $ua     = isset($r[5]) ? $r[5] : '';
    } else {
        $design = '';
        $ref    = isset($r[3]) ? $r[3] : '';
        $ua     = isset($r[4]) ? $r[4] : '';
    }
    $camp = '';
    if ($n >= 9) {
        $camp = implode('/', array_filter(
            array(trim((string)$r[6]), trim((string)$r[7]), trim((string)$r[8])),
            function ($v) { return $v !== ''; }));
    }
    $leads[] = array('ts' => $r[0], 'email' => strtolower(trim($r[1])),
                     'consent' => isset($r[2]) ? $r[2] : '', 'design' => $design,
                     'ref' => $ref, 'ua' => $ua, 'camp' => $camp);
}
$realLeads = array();
foreach ($leads as $l) { if (!an_is_test_email($l['email'])) $realLeads[$l['email']] = $l; }

$invites = array();
foreach (an_load($dir . '/invites-' . $slug . '.csv') as $r) {
    if (isset($r[0]) && $r[0] === 'sent_at') continue;
    $invites[] = array('ts' => $r[0], 'email' => strtolower(trim($r[1])), 'ok' => isset($r[2]) ? $r[2] : '0');
}
$invitedEmails = array();
foreach ($invites as $i) { if (!an_is_test_email($i['email']) && $i['ok'] === '1') $invitedEmails[$i['email']] = true; }

$clicks = array();
foreach (an_load($dir . '/clicks-' . $slug . '.csv') as $r) {
    if (isset($r[0]) && $r[0] === 'ts') continue;
    $clicks[] = array('ts' => $r[0], 'label' => isset($r[1]) ? $r[1] : '',
                      'src' => isset($r[2]) ? $r[2] : '', 'sig' => isset($r[3]) ? $r[3] : '',
                      'ip' => isset($r[4]) ? $r[4] : '', 'bot' => isset($r[5]) ? $r[5] : '0');
}
$humanClicks = array();
foreach ($clicks as $c) {
    if ($c['src'] !== '' && strpos($c['src'], 'test') === 0) continue;
    if ($c['bot'] === '1') continue;
    $humanClicks[] = $c;
}

/* --------------------------------------------------------------- metrics */
$signups = count($realLeads);
$delivered = count($invitedEmails);

$joinClicks = 0; $clickers = array(); $joinUniq = array(); $labelCounts = array(); $srcCounts = array(); $linkSrcs = array();
foreach ($humanClicks as $c) {
    if (an_is_permalink_src($c['src'])) { @$linkSrcs[$c['src']]++; continue; }
    if ($c['label'] === 'join') { $joinClicks++; if ($c['sig'] !== '') $joinUniq[$c['sig']] = true; }
    $key = ($c['sig'] !== '') ? 's:' . $c['sig'] : 'i:' . $c['ip'];
    $clickers[$key] = true;
    @$labelCounts[$c['label']]++;
    @$srcCounts[$c['src']]++;
}
$uniqClickers = count($clickers);
$joinClickers = count($joinUniq);
$conversion = $delivered > 0 ? round(100 * $joinClickers / $delivered, 1) : 0;
$clickRate   = $delivered > 0 ? round(100 * $uniqClickers / $delivered, 1) : 0;

$cut24 = time() - 86400;
$signups24 = 0; foreach ($realLeads as $l) { if (strtotime($l['ts']) >= $cut24) $signups24++; }
$clicks24  = 0; foreach ($humanClicks as $c) { if (strtotime($c['ts']) >= $cut24) $clicks24++; }

/* referrers */
$refCounts = array();
foreach ($realLeads as $l) {
    $ref = trim($l['ref']);
    if ($ref === '') $ref = '(direct)';
    elseif (preg_match('#^https?://([^/]+)#i', $ref, $m)) $ref = $m[1];
    @$refCounts[$ref]++;
}
arsort($refCounts);

/* first-touch campaign recorded on each lead row (utm_source/medium/campaign) */
$campCounts = array();
foreach ($realLeads as $l) { @$campCounts[$l['camp'] !== '' ? $l['camp'] : '(untagged)']++; }
arsort($campCounts);

/* 30-day series */
function an_days($DAYS) {
    $out = array();
    for ($i = $DAYS - 1; $i >= 0; $i--) $out[gmdate('Y-m-d', time() - $i * 86400)] = 0;
    return $out;
}
$leadDays  = an_days(30);
$clickDays = an_days(30);
foreach ($realLeads   as $l) { $d = gmdate('Y-m-d', strtotime($l['ts'])); if (isset($leadDays[$d]))  $leadDays[$d]++; }
$funnelClicks = array();
foreach ($humanClicks as $c) { if (!an_is_permalink_src($c['src'])) $funnelClicks[] = $c; }
foreach ($funnelClicks as $c) { $d = gmdate('Y-m-d', strtotime($c['ts'])); if (isset($clickDays[$d])) $clickDays[$d]++; }
$kd = array_keys($leadDays);
$rangeTxt = substr($kd[0], 5) . ' .. ' . substr($kd[count($kd) - 1], 5);
$daysActive = 0; foreach ($leadDays as $v) if ($v > 0) $daysActive++;
$avgSignups = $daysActive > 0 ? round($signups / $daysActive, 1) : 0;
$peakDay = 0; foreach ($leadDays as $v) if ($v > $peakDay) $peakDay = $v;

/* reach (metrics-shipshow.json) */
$reach = array();
$mfile = $dir . '/metrics-shipshow.json';
if (is_file($mfile)) {
    $m = json_decode(@file_get_contents($mfile), true);
    if (is_array($m)) $reach = $m;
}
$yt  = isset($reach['youtube']) && is_array($reach['youtube']) ? $reach['youtube'] : array();
$no  = isset($reach['nostr'])   && is_array($reach['nostr'])   ? $reach['nostr']   : array();
$ht  = isset($reach['hashtags']) && is_array($reach['hashtags']) ? $reach['hashtags'] : array();
$tw  = isset($reach['twitter'])    && is_array($reach['twitter'])    ? $reach['twitter']    : array();
$gc  = isset($reach['goatcounter']) && is_array($reach['goatcounter']) ? $reach['goatcounter'] : array();
$gcDaily = isset($gc['daily30']) && is_array($gc['daily30']) ? $gc['daily30'] : array();
$sc  = isset($reach['search_console']) && is_array($reach['search_console']) ? $reach['search_console'] : array();
$asofTxt = '';
if (isset($reach['as_of']) && $reach['as_of']) { try { $asofTxt = (new DateTime($reach['as_of']))->format('Y-m-d H:i'); } catch (Exception $e) {} }

/* ------------------------------------------------------------- svg / bars */
function an_spark($days, $color, $h = 40) {
    $n = count($days); if (!$n) return '';
    $max = 1; foreach ($days as $v) $max = max($max, $v);
    $W = 120; $H = $h; $cw = $W / $n; $bw = max(0.6, $cw * 0.62);
    $s = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" preserveAspectRatio="none" style="width:100%;height:' . $H . 'px;display:block">';
    $s .= '<line x1="0" y1="' . ($H - 1) . '" x2="' . $W . '" y2="' . ($H - 1) . '" stroke="rgba(203,220,232,.22)" stroke-width="1"/>';
    $i = 0;
    foreach ($days as $d => $v) {
        $bh = ($H - 3) * ($v / $max);
        $x = $i * $cw; $y = $H - 1 - $bh;
        $s .= '<rect x="' . round($x, 2) . '" y="' . round($y, 2) . '" width="' . round($bw, 2) . '" height="' . round($bh, 2) . '" fill="' . $color . '"/>';
        $i++;
    }
    $s .= '</svg>';
    return $s;
}

function an_hb($label, $val, $max, $color) {
    $w = $max > 0 ? round(100 * $val / $max) : 0;
    return '<div class="hb"><div class="hb-l" title="' . gate_esc($label) . '">' . gate_esc($label) . '</div>'
         . '<div class="hb-t"><div class="hb-b" style="width:' . $w . '%;background:' . $color . '"></div></div>'
         . '<div class="hb-v">' . $val . '</div></div>';
}

$stat = function ($v, $l, $d = '', $gold = false, $hint = null) {
    $tip = '';
    if (is_array($hint)) {
        $tt = (isset($hint['title']) && $hint['title'] !== '') ? $hint['title'] : $l;
        $tip = '<span class="ti">?</span><span class="tip">'
             . '<span class="tip-t">' . gate_esc($tt) . '</span>'
             . '<span class="tip-m">' . gate_esc(isset($hint['meaning']) ? $hint['meaning'] : '') . '</span>'
             . (isset($hint['source']) && $hint['source'] !== '' ? '<span class="tip-s">source: ' . gate_esc($hint['source']) . '</span>' : '')
             . (isset($hint['window']) && $hint['window'] !== '' ? '<span class="tip-s">window: ' . gate_esc($hint['window']) . '</span>' : '')
             . '</span>';
    }
    return '<div class="stat">' . $tip
         . '<div class="v' . ($gold ? ' gold' : '') . '">' . $v . '</div>'
         . '<div class="l">' . $l . '</div>'
         . ($d !== '' ? '<div class="d">' . $d . '</div>' : '') . '</div>';
};

function an_funnel($steps) {
    $out = '<div class="fn">';
    $prev = null;
    foreach ($steps as $s) {
        $drop = '';
        if ($prev !== null) {
            $d = $prev > 0 ? round(100 * ($prev - $s['v']) / $prev) : 0;
            if ($d > 0) $drop = '<span class="drop">- ' . $d . '% drop</span>';
        }
        $w = $steps[0]['v'] > 0 ? round(100 * $s['v'] / $steps[0]['v']) : 0;
        $out .= '<div class="row"><div class="fn-l">' . $s['l'] . '</div>'
              . '<div class="bar"><div class="fill" style="width:' . $w . '%;background:' . $s['c'] . '"></div></div>'
              . '<div class="fn-v">' . $s['v'] . '</div></div>' . $drop;
        $prev = $s['v'];
    }
    return $out . '</div>';
}

/* ------------------------------------------------------------------ body */
$mono = 'Consolas,"Cascadia Mono",Menlo,monospace';
$G = '#3ad07a'; $GD = '#D9AE3C'; $BL = '#1E6FA6';

$body = '<style>'
  . '.wrap{max-width:1060px}'
  . '.wrap h1{font-size:clamp(30px,5.5vw,46px);margin:10px 0 4px}'
  . '.prompt{font-family:' . $mono . ';font-size:12.5px;color:' . $G . ';margin:6px 0 0}'
  . '.prompt .cur{color:#7fa0bd}'
  . '.term{background:rgba(6,23,38,.94);border:1px solid #14486F;box-shadow:inset 0 0 34px rgba(11,31,51,.55);margin:14px 0}'
  . '.term-hd{display:flex;justify-content:flex-start;align-items:center;cursor:pointer;user-select:none;font-family:' . $mono . ';font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:' . $GD . ';border-bottom:1px solid #14486F;padding:6px 12px;background:linear-gradient(90deg,rgba(20,72,111,.55),rgba(6,23,38,0))}'
  . '.term-hd .ok{color:' . $G . '}'
  . '.term-bd{padding:12px 14px}'
  . '.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));gap:1px;background:#14486F;border:1px solid #14486F}'
  . '.stat{background:#061726;padding:11px 12px}'
  . '.stat .v{font-family:' . $mono . ';font-size:23px;line-height:1;color:' . $G . '}'
  . '.stat .v.gold{color:' . $GD . '}'
  . '.stat .l{font-family:' . $mono . ';font-size:10px;letter-spacing:.09em;text-transform:uppercase;color:#7fa0bd;margin-top:6px}'
  . '.stat .d{font-family:' . $mono . ';font-size:10px;color:' . $GD . ';margin-top:3px}'
  . '.stat{position:relative}'
  . '.stat .ti{position:absolute;top:5px;right:7px;color:#7fa0bd;font-size:10px;cursor:help;font-family:' . $mono . '}'
  . '.stat .tip{display:none;position:absolute;top:calc(100% + 5px);left:0;z-index:70;width:252px;background:#0b1f2e;border:1px solid #14486F;padding:9px 11px;box-shadow:0 6px 20px rgba(0,0,0,.6);font-family:' . $mono . ';font-size:11px;line-height:1.5;color:#CBDCE8;text-align:left}'
  . '.stat:hover .tip{display:block}'
  . '.stat .tip-t{display:block;color:' . $GD . ';font-weight:700;text-transform:uppercase;font-size:10px;letter-spacing:.06em;margin-bottom:4px}'
  . '.stat .tip-s{display:block;color:#7fa0bd;font-size:10px;margin-top:5px}'
  . '.stat .tip-m{display:block}'
  . '.h2{font-family:' . $mono . ';font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:' . $GD . ';margin:24px 0 8px}'
  . '.h2 .dim{color:#7fa0bd;text-transform:none;letter-spacing:0}'
  . '.hb{display:grid;grid-template-columns:120px 1fr 42px;gap:10px;align-items:center;margin:5px 0;font-family:' . $mono . ';font-size:12px}'
  . '.hb-l{color:#CBDCE8;text-align:right;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
  . '.hb-t{background:rgba(20,72,111,.35);height:14px}'
  . '.hb-b{height:100%}'
  . '.hb-v{color:' . $G . ';text-align:left}'
  . '.fn{font-family:' . $mono . ';font-size:12px}'
  . '.fn .row{display:grid;grid-template-columns:110px 1fr 50px;gap:10px;align-items:center;margin:7px 0}'
  . '.fn-l{color:#CBDCE8;text-align:right;text-transform:uppercase;font-size:10.5px;letter-spacing:.06em}'
  . '.fn .bar{height:20px;background:rgba(20,72,111,.35)}'
  . '.fn .fill{height:100%}'
  . '.fn-v{color:' . $G . ';font-weight:700}'
  . '.fn .drop{font-family:' . $mono . ';font-size:10px;color:#7fa0bd;grid-column:2;text-align:right;margin:-2px 0 6px}'
  . '.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}'
  . '@media(max-width:760px){.grid2{grid-template-columns:1fr}}'
  . '.tc{width:100%;border-collapse:collapse;font-family:' . $mono . ';font-size:11.5px;color:#CBDCE8}'
  . '.tc th{text-align:left;font-size:9.5px;letter-spacing:.09em;text-transform:uppercase;color:#7fa0bd;border-bottom:1px solid #14486F;padding:4px 6px;white-space:nowrap}'
  . '.tc td{padding:4px 6px;border-bottom:1px solid rgba(20,72,111,.35);vertical-align:top}'
  . '.tc td.dim{color:#7fa0bd}'
  . '.tc td.em{color:' . $GD . '}'
  . '.tc td.g{color:' . $G . '}'
  . '.status{margin-top:22px;font-family:' . $mono . ';font-size:11px;color:#7fa0bd;border-top:1px solid #14486F;padding:10px 2px;display:flex;flex-wrap:wrap;gap:6px 18px;justify-content:space-between}'
  . '.status a{color:' . $GD . ';text-decoration:none}'
  . '.ml{font-family:' . $mono . ';font-size:11.5px;margin:4px 0 10px;padding-left:16px;color:#CBDCE8}'
  . '.ml li{margin:3px 0}'
  . '.ml a{color:' . $G . ';text-decoration:none}'
  . '.ml a:hover{text-decoration:underline}'
  . '.ml .dim{color:#7fa0bd;font-size:10px}'
  . '.term-hd .ok{margin-left:auto}'
  . '.term-hd::before{content:"\25BE ";font-size:11px;color:#7fa0bd;margin-right:7px}'
  . '.term.collapsed .term-hd::before{content:"\25B8 "}'
  . '.term.collapsed .term-bd{display:none}'
  . '.term.t-g{border-left:3px solid #3ad07a}'
  . '.term.t-b{border-left:3px solid #1E6FA6}'
  . '.term.t-y{border-left:3px solid #D9AE3C}'
  . '.term.t-p{border-left:3px solid #9b6fd9}'
  . '.term.t-r{border-left:3px solid #d06a6a}'
  . '.term a{color:#3ad07a;text-decoration:none}'
  . '.term a:hover{text-decoration:underline}'
  . '.socials{display:flex;flex-wrap:wrap;gap:6px 16px;margin:14px 0 4px;font-family:' . $mono . ';font-size:12px}'
  . '.socials a{color:#D9AE3C;text-decoration:none}'
  . '.socials a:hover{text-decoration:underline}'
  . '</style>';

$body .= '<p class="kicker">BITCOIN SHIPSHOW</p>'
  . '<h1>Analytics</h1>'
  . '<div class="prompt">$ shipshow --analytics --slug=' . gate_esc($slug) . ' <span class="cur"># live</span></div>';

$body .= '<div class="stats">'
  . $stat($signups, 'sign-ups', $signups24 ? '+' . $signups24 . ' / 24h' : '0 / 24h', false, array('meaning' => 'Real (non-test) emails captured when a visitor submits the signup form.', 'source' => 'leads-shipshow.csv, written by the gate on submit'))
  . $stat($delivered, 'invited', $signups > 0 ? round(100 * $delivered / $signups) . '% of signups' : '', false, array('meaning' => 'Signups that received a delivered calendar invite.', 'source' => 'invites-shipshow.csv, rows with ok=1'))
  . $stat($uniqClickers, 'clickers', $clickRate . '% of invited', false, array('meaning' => 'Unique people (by IP or session id) who clicked any invite link.', 'source' => 'clicks-shipshow.csv, deduped by ip/session'))
  . $stat($joinClicks, 'join clicks', $joinClickers . ' uniq', false, array('meaning' => 'Total clicks on the join action; uniq = distinct join clickers.', 'source' => 'clicks CSV, label=join'))
  . $stat($conversion . '%', 'conversion', 'join / invited', false, array('meaning' => 'Distinct join clickers divided by invited emails.', 'source' => 'computed from leads + clicks'))
  . $stat($avgSignups, 'avg signups/day', $daysActive . ' active days', false, array('meaning' => 'Sign-ups divided by the number of days that had at least one signup.', 'source' => 'leads CSV', 'window' => '30 days'))
  . $stat($peakDay, 'peak day', 'max in window', false, array('meaning' => 'Most sign-ups recorded on a single day.', 'source' => 'leads CSV', 'window' => '30 days'))
  . $stat($signups - $uniqClickers, 'no click', 'invited, silent', true, array('meaning' => 'Invited signups with zero recorded clicks.', 'source' => 'invites minus clickers'))
  . '</div>';

$body .= '<div class="term t-g"><div class="term-hd"><span>&#9608; FUNNEL</span><span class="ok">signups &#8594; invited &#8594; clicked &#8594; joined</span></div><div class="term-bd">'
  . an_funnel(array(
      array('l' => 'SIGN-UPS', 'v' => $signups,    'c' => $G),
      array('l' => 'INVITED',  'v' => $delivered,  'c' => $BL),
      array('l' => 'CLICKED',  'v' => $uniqClickers, 'c' => $GD),
      array('l' => 'JOINED',   'v' => $joinClickers, 'c' => $G),
    ))
  . '</div></div>';

$body .= '<div class="term t-b"><div class="term-hd"><span>&#9608; ACTIVITY</span><span class="ok">' . $rangeTxt . ' (UTC)</span></div><div class="term-bd">'
  . '<div class="h2" style="margin-top:0">sign-ups / day <span class="dim">max ' . $peakDay . '</span></div>'
  . an_spark($leadDays, $G, 44)
  . '<div class="h2">clicks / day</div>'
  . an_spark($clickDays, $GD, 44)
  . '</div></div>';

if ($gcDaily) {
    $visitDays = array();
    foreach ($gcDaily as $row) {
        if (isset($row['day'])) $visitDays[(string)$row['day']] = (int)(isset($row['count']) ? $row['count'] : 0);
    }
    if ($visitDays) {
        $vk = array_keys($visitDays);
        $body .= '<div class="term t-b"><div class="term-hd"><span>&#9608; SITE VISITS</span><span class="ok">' . number_format(array_sum($visitDays)) . ' visits &middot; last 30 days</span></div><div class="term-bd">'
          . '<div class="h2" style="margin-top:0">visits / day <span class="dim">' . gate_esc(substr($vk[0], 5) . ' .. ' . substr($vk[count($vk) - 1], 5)) . ' (GoatCounter daily totals)</span></div>'
          . an_spark($visitDays, $BL, 64) . '</div></div>';
    }
}

$body .= '<div class="grid2">';

$body .= '<div class="term t-y"><div class="term-hd"><span>&#9608; CLICK SOURCES</span><span class="ok">funnel links</span></div><div class="term-bd">';
$maxSrc = max($srcCounts ?: array(0));
foreach ($srcCounts as $lab => $n) $body .= an_hb($lab, $n, $maxSrc, $GD);
if (!$srcCounts) $body .= '<div style="padding:4px;color:#7fa0bd;font-family:' . $mono . ';font-size:12px">no clicks yet</div>';
$body .= '<div class="h2">actions</div>';
$maxLab = max($labelCounts ?: array(0));
foreach ($labelCounts as $lab => $n) $body .= an_hb($lab, $n, $maxLab, $BL);
if (!$labelCounts) $body .= '<div style="padding:4px;color:#7fa0bd;font-family:' . $mono . ';font-size:12px">none</div>';
$body .= '<div class="h2">short links <span class="dim">bio &amp; channel links &middot; traffic, not funnel</span></div>';
$maxLink = max($linkSrcs ?: array(0));
foreach ($linkSrcs as $lab => $n) $body .= an_hb($lab, $n, $maxLink, $BL);
if (!$linkSrcs) $body .= '<div style="padding:4px;color:#7fa0bd;font-family:' . $mono . ';font-size:12px">no permalink clicks yet</div>';
$body .= '</div></div>';

$body .= '<div class="term t-y"><div class="term-hd"><span>&#9608; REFERRERS</span></div><div class="term-bd">';
$topRef = array_slice($refCounts, 0, 8, true);
$maxRef = $topRef ? max($topRef) : 1;
foreach ($topRef as $ref => $n) $body .= an_hb($ref, $n, $maxRef, $G);
if (!$topRef) $body .= '<div style="padding:4px;color:#7fa0bd;font-family:' . $mono . ';font-size:12px">none yet</div>';
$body .= '</div></div>';

$body .= '</div>';

$body .= '<div class="grid2">';
$body .= '<div class="term t-p"><div class="term-hd"><span>&#9608; SIGNUPS BY CAMPAIGN</span><span class="ok">first touch &middot; utm_source/medium/campaign</span></div><div class="term-bd">';
$maxC = ($campCounts ? max($campCounts) : 1);
foreach ($campCounts as $lab => $n) $body .= an_hb($lab, $n, $maxC, $GD);
if (!$campCounts) $body .= '<div style="padding:4px;color:#7fa0bd;font-family:' . $mono . ';font-size:12px">no signups yet</div>';
$body .= '</div></div>';
$gcCamp = (isset($gc['campaigns']) && is_array($gc['campaigns'])) ? $gc['campaigns'] : array();
$body .= '<div class="term t-p"><div class="term-hd"><span>&#9608; CAMPAIGN TRAFFIC</span><span class="ok">goatcounter &middot; all time</span></div><div class="term-bd">';
$maxGC = 1; foreach ($gcCamp as $c) { $maxGC = max($maxGC, (int)$c['count']); }
foreach ($gcCamp as $c) $body .= an_hb($c['name'] !== '' ? $c['name'] : '(none)', (int)$c['count'], $maxGC, $G);
if (!$gcCamp) $body .= '<div style="padding:4px;color:#7fa0bd;font-family:' . $mono . ';font-size:12px">no utm-tagged visits yet</div>';
$body .= '</div></div>';
$body .= '</div>';

/* misses: dead paths that gate/miss.php redirected to the homepage. Bots and
   obvious scanner probes are counted but kept out of the list, so what remains
   is what a real visitor actually hit and would have seen as a 404. */
$misses = an_load($dir . '/misses-' . $slug . '.csv');
$missTotal = 0; $missBots = 0; $missProbes = 0; $missCounts = array();
foreach ($misses as $r) {
    if (isset($r[0]) && $r[0] === 'ts') continue;
    $mp = isset($r[1]) ? (string)$r[1] : '';
    if ($mp === '') continue;
    $missTotal++;
    if (isset($r[3]) && $r[3] === '1') { $missBots++; continue; }
    if (preg_match('~wp-|/wp/|\.env|\.git|xmlrpc|phpmyadmin|/\.\.|cgi-bin|/shell|/admin|eval\(|\.sql|backup~i', $mp)) { $missProbes++; continue; }
    @$missCounts[$mp]++;
}
arsort($missCounts);

$body .= '<div class="term t-y"><div class="term-hd"><span>&#9608; MISSES</span><span class="ok">404 &#8594; homepage &middot; UTM source = missing URL</span></div><div class="term-bd">';
$body .= '<div class="h2" style="margin-top:0">' . number_format($missTotal) . ' dead requests <span class="dim">&middot; ' . number_format($missBots) . ' bots &middot; ' . number_format($missProbes) . ' scanner probes</span></div>';
$maxMiss = ($missCounts ? max($missCounts) : 1);
foreach (array_slice($missCounts, 0, 8, true) as $mp => $n) {
    $label = (strlen($mp) > 42) ? substr($mp, 0, 41) . '…' : $mp;
    $body .= an_hb($label, $n, $maxMiss, $GD);
}
if (!$missCounts) $body .= '<div style="padding:4px;color:#7fa0bd;font-family:' . $mono . ';font-size:12px">no dead links from real visitors</div>';
$body .= '<div class="h2" style="margin-bottom:0"><span class="dim">logged + redirected by gate/miss.php &middot; source misses-' . gate_esc($slug) . '.csv</span></div>';
$body .= '</div></div>';

$body .= '<div class="term t-g"><div class="term-hd"><span>&#9608; REACH</span><span class="ok">' . ($asofTxt ? 'as of <span class="lt" data-utc="' . gate_esc($reach['as_of']) . '">' . $asofTxt . '</span>' : 'no metrics yet') . '</span></div><div class="term-bd"><div class="stats">'
  . $stat(isset($yt['subscribers']) ? number_format($yt['subscribers']) : '-', 'youtube subs', '', false, array('meaning' => 'Live subscriber count for the channel.', 'source' => 'YouTube Data API v3, channel @BitcoinShipshow'))
  . $stat(isset($yt['views']) ? number_format($yt['views']) : '-', 'youtube views', '', false, array('meaning' => 'Total views across all channel videos.', 'source' => 'YouTube Data API v3'))
  . $stat(isset($yt['videos']) ? number_format($yt['videos']) : '-', 'youtube videos', '', false, array('meaning' => 'Number of public videos on the channel.', 'source' => 'YouTube Data API v3'))
  . $stat((isset($no['followers']) && $no['followers'] !== null) ? number_format($no['followers']) : '-', 'nostr followers', '', false, array('meaning' => 'Distinct npubs whose kind-3 contact list tags your pubkey.', 'source' => '3+ public relays, NIP-02 contact lists'))
  . $stat((isset($tw['followers']) && $tw['followers'] !== null && (int)$tw['followers'] > 0) ? number_format($tw['followers']) : '-', 'X followers', '', false, array('meaning' => 'Follower count on X; unavailable/zero-like provider responses are shown as unavailable.', 'source' => 'twit.sh via TaskFuel'))
  . $stat((isset($tw['following']) && $tw['following'] !== null) ? number_format($tw['following']) : '-', 'following', '', false, array('meaning' => 'Accounts the @bitcoinshipshow handle follows.', 'source' => 'twit.sh via TaskFuel'))
  . $stat((isset($tw['tweets']) && $tw['tweets'] !== null) ? number_format($tw['tweets']) : '-', 'posts', '', false, array('meaning' => 'Total posts on the X profile.', 'source' => 'twit.sh via TaskFuel'))
  . $stat((isset($gc['visits']) && $gc['visits'] !== null) ? number_format($gc['visits']) : '-', 'goatcounter visits', '', true, array('meaning' => 'Total visitors to the site.', 'source' => 'GoatCounter stats/total API', 'window' => 'last 30 days'))
  . $stat((isset($gc['events']) && $gc['events'] !== null) ? number_format($gc['events']) : '-', 'events', '', true, array('meaning' => 'Tracked custom events (e.g. clicks on specific elements).', 'source' => 'GoatCounter', 'window' => 'last 30 days'))
  . $stat((isset($sc['clicks']) && $sc['clicks'] !== null) ? number_format($sc['clicks']) : '-', 'search clicks', '', false, array('meaning' => 'How often people clicked the site from Google search results.', 'source' => 'Google Search Console API', 'window' => 'last 28 days'))
  . $stat((isset($sc['impressions']) && $sc['impressions'] !== null) ? number_format($sc['impressions']) : '-', 'search impressions', '', false, array('meaning' => 'How often the site appeared in Google search results (any position).', 'source' => 'Google Search Console API', 'window' => 'last 28 days'))
  . '</div>';
$htParts = array();
$ntLinks = array();
foreach ($ht as $tag => $v) {
    $cnt = null;
    if (is_array($v)) { $cnt = isset($v['count']) ? $v['count'] : null; }
    else { $cnt = $v; }
    $relayNames = (is_array($v) && !empty($v['relay_names']) && is_array($v['relay_names'])) ? implode(', ', $v['relay_names']) : 'none';
    $relayNote = is_array($v) && isset($v['relay_sample']) ? ' <span class="dim">(' . (int)$v['relay_sample'] . ' sampled matches; checked ' . gate_esc($relayNames) . ')</span>' : '';
    $htParts[] = '<strong style="color:' . $G . '">' . ($cnt === null ? '-' : (int)$cnt) . '</strong> ' . gate_esc($tag) . $relayNote;
    if (is_array($v) && !empty($v['items'])) {
        foreach ($v['items'] as $it) {
            if (!empty($it['link'])) {
                $snip = (isset($it['snippet']) && $it['snippet'] !== '') ? $it['snippet'] : $it['id'];
                $ntLinks[] = '<li><a href="' . gate_esc($it['link']) . '" target="_blank" rel="noopener">' . gate_esc($snip) . '</a> <span class="dim">' . gate_esc($tag) . '</span></li>';
            }
        }
    }
}
if ($htParts) $body .= '<div class="h2">nostr mentions &nbsp;<span class="dim">' . implode(' &nbsp;&middot;&nbsp; ', $htParts) . ' &nbsp;(nostr.wine search)</span></div>';
if ($ntLinks) $body .= '<ul class="ml">' . implode('', $ntLinks) . '</ul>';
$news = isset($reach['news_mentions']) ? $reach['news_mentions'] : null;
$newsCnt = is_array($news) ? (isset($news['count']) ? $news['count'] : null) : $news;
$newsItems = (is_array($news) && isset($news['items'])) ? $news['items'] : array();
$body .= '<div class="h2">google news mentions &nbsp;<span class="dim"><strong style="color:' . $G . '">' . ($newsCnt === null ? '-' : (int)$newsCnt) . '</strong> "Bitcoin Shipshow" &nbsp;(news.google.com RSS, free)</span></div>';
if ($newsItems) {
    $nl = array();
    foreach ($newsItems as $n) {
        $t = isset($n['title']) ? $n['title'] : '';
        $l = isset($n['link']) ? $n['link'] : '';
        $d = isset($n['date']) ? $n['date'] : '';
        if ($l !== '') {
            $nl[] = '<li><a href="' . gate_esc($l) . '" target="_blank" rel="noopener">' . gate_esc($t) . '</a> <span class="dim">' . gate_esc($d) . '</span></li>';
        }
    }
    if ($nl) $body .= '<ul class="ml">' . implode('', $nl) . '</ul>';
}
$body .= '</div></div>';

$gcPaths = (isset($gc['paths']) && is_array($gc['paths'])) ? $gc['paths'] : array();
$gcRefs  = (isset($gc['referrers']) && is_array($gc['referrers'])) ? $gc['referrers'] : array();
if ($gcPaths || $gcRefs) {
    $body .= '<div class="term t-b"><div class="term-hd"><span>&#9608; TRAFFIC</span><span class="ok">goatcounter &middot; last 30 days</span></div><div class="term-bd"><div class="grid2">';
    if ($gcPaths) {
        $body .= '<div><div class="h2" style="margin-top:0">top pages <span class="dim">by visits</span></div>';
        $body .= '<table class="tc"><thead><tr><th>page</th><th>visits</th></tr></thead><tbody>';
        foreach ($gcPaths as $p) {
            $pt = isset($p['path']) ? $p['path'] : '';
            $tt = isset($p['title']) ? $p['title'] : '';
            $label = $tt !== '' ? $tt : $pt;
            $href = 'https://bitcoinship.show' . ($pt !== '' && $pt[0] !== '/' ? '/' : '') . $pt;
            $body .= '<tr><td><a href="' . gate_esc($href) . '" target="_blank" rel="noopener">' . gate_esc($label) . '</a></td><td class="g">' . (int)$p['count'] . '</td></tr>';
        }
        $body .= '</tbody></table></div>';
    }
    if ($gcRefs) {
        $body .= '<div><div class="h2" style="margin-top:0">traffic sources <span class="dim">where visitors came from</span></div>';
        $body .= '<table class="tc"><thead><tr><th>source</th><th>visits</th></tr></thead><tbody>';
        foreach ($gcRefs as $r) {
            $rn = isset($r['name']) ? $r['name'] : '';
            $body .= '<tr><td>' . gate_esc($rn !== '' ? $rn : '(direct / none)') . '</td><td class="g">' . (int)$r['count'] . '</td></tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '</div></div></div>';
}

$scQ = isset($sc['top_queries']) && is_array($sc['top_queries']) ? $sc['top_queries'] : array();
$scP = isset($sc['top_pages'])   && is_array($sc['top_pages'])   ? $sc['top_pages']   : array();
$scC = isset($sc['countries'])   && is_array($sc['countries'])   ? $sc['countries']   : array();
$scD = isset($sc['devices'])     && is_array($sc['devices'])     ? $sc['devices']     : array();
if ($scQ || $scP || $scC || $scD) {
    $body .= '<div class="term t-y"><div class="term-hd"><span>&#9608; SEARCH</span><span class="ok">google search console &middot; last 28 days</span></div><div class="term-bd">';
    if ($scQ) {
        $body .= '<div class="h2" style="margin-top:0">top queries <span class="dim">what people google to find you</span></div>';
        $body .= '<table class="tc"><thead><tr><th>query</th><th>clicks</th><th>impr</th><th>avg pos</th></tr></thead><tbody>';
        foreach ($scQ as $q) $body .= '<tr><td>' . gate_esc($q['k']) . '</td><td class="g">' . (int)$q['clicks'] . '</td><td>' . (int)$q['impressions'] . '</td><td class="dim">' . $q['position'] . '</td></tr>';
        $body .= '</tbody></table>';
    }
    if ($scP) {
        $body .= '<div class="h2">top pages <span class="dim">which pages earn search clicks</span></div>';
        $body .= '<table class="tc"><thead><tr><th>page</th><th>clicks</th><th>impr</th></tr></thead><tbody>';
        foreach ($scP as $p) $body .= '<tr><td>' . gate_esc($p['k']) . '</td><td class="g">' . (int)$p['clicks'] . '</td><td>' . (int)$p['impressions'] . '</td></tr>';
        $body .= '</tbody></table>';
    }
    if ($scC || $scD) {
        $parts = array();
        foreach ($scC as $c) $parts[] = gate_esc($c['k']) . ' ' . (int)$c['clicks'] . '/' . (int)$c['impressions'];
        foreach ($scD as $dv) $parts[] = gate_esc($dv['k']) . ' ' . (int)$dv['clicks'] . '/' . (int)$dv['impressions'];
        $body .= '<div class="h2">countries &amp; devices <span class="dim">clicks/impressions &middot; ' . implode(' &middot; ', $parts) . '</span></div>';
    }
    $body .= '</div></div>';
}

$hist = isset($reach['history']) && is_array($reach['history']) ? $reach['history'] : array();
if ($hist) {
    /* one sparkline per tracked counter, oldest -> newest */
    $series = array('yt' => array(), 'nostr' => array(), 'twitter' => array(), 'gc' => array());
    foreach ($hist as $h) {
        $series['yt'][]      = isset($h['youtube']['subscribers']) ? (int)$h['youtube']['subscribers'] : 0;
        $series['nostr'][]   = (isset($h['nostr']['followers']) && $h['nostr']['followers'] !== null) ? (int)$h['nostr']['followers'] : 0;
        $series['twitter'][] = (isset($h['twitter']['followers']) && $h['twitter']['followers'] !== null) ? (int)$h['twitter']['followers'] : 0;
        $series['gc'][]      = (isset($h['goatcounter']['visits']) && $h['goatcounter']['visits'] !== null) ? (int)$h['goatcounter']['visits'] : 0;
    }
    $body .= '<div class="term t-b"><div class="term-hd"><span>&#9608; HISTORY</span><span class="ok">' . count($hist) . ' daily snapshots &middot; oldest &#8594; newest</span></div><div class="term-bd">';
    $body .= '<div class="grid2" style="gap:10px">';
    foreach (array('yt' => 'youtube subs', 'nostr' => 'nostr followers', 'twitter' => 'twitter followers', 'gc' => 'goatcounter visits') as $k => $lab) {
        $vals = $series[$k];
        $now  = $vals ? end($vals) : 0;
        $body .= '<div><div class="h2" style="margin:2px 0 4px">' . $lab . ' <span class="dim">now ' . (int)$now . '</span></div>'
               . an_spark($vals, ($k === 'gc' ? $G : $GD), 32) . '</div>';
    }
    $body .= '</div>';
    $body .= '<div style="padding:6px 0 0"><table class="tc"><thead><tr><th>snapshot (local)</th><th>yt subs</th><th>nostr</th><th>twitter</th><th>gc visits</th></tr></thead><tbody>';
    foreach (array_slice(array_reverse($hist), 0, 16) as $h) {
        $hd  = isset($h['as_of']) ? substr($h['as_of'], 0, 16) : '';
        $hdt = isset($h['as_of']) ? $h['as_of'] : '';
        $hys = isset($h['youtube']['subscribers']) ? number_format($h['youtube']['subscribers']) : '-';
        $hns = (isset($h['nostr']['followers']) && $h['nostr']['followers'] !== null) ? number_format($h['nostr']['followers']) : '-';
        $hts = (isset($h['twitter']['followers']) && $h['twitter']['followers'] !== null) ? number_format($h['twitter']['followers']) : '-';
        $hgs = (isset($h['goatcounter']['visits']) && $h['goatcounter']['visits'] !== null) ? number_format($h['goatcounter']['visits']) : '-';
        $body .= '<tr><td class="dim"><span class="lt" data-utc="' . gate_esc($hdt) . '">' . gate_esc($hd) . '</span></td><td class="g">' . $hys . '</td><td>' . $hns . '</td><td>' . $hts . '</td><td class="em">' . $hgs . '</td></tr>';
    }
    $body .= '</tbody></table></div></div></div>';
}

$body .= '<div class="grid2">';
$body .= '<div class="term t-r"><div class="term-hd"><span>&#9608; RECENT SIGN-UPS</span></div><div class="term-bd" style="padding:6px 10px"><table class="tc"><thead><tr><th>when</th><th>email</th><th>ref</th><th>campaign</th></tr></thead><tbody>';
$recent = array_reverse(array_values($realLeads));
foreach (array_slice($recent, 0, 9) as $l) {
    $when = $l['ts']; try { $when = (new DateTime($l['ts']))->format('m-d H:i'); } catch (Exception $e) {}
    $ref = preg_match('#^https?://([^/]+)#i', $l['ref'], $mm) ? $mm[1] : ($l['ref'] !== '' ? $l['ref'] : '(direct)');
    $body .= '<tr><td class="dim">' . gate_esc($when) . '</td><td class="em">' . gate_esc($l['email']) . '</td><td class="dim">' . gate_esc($ref) . '</td>'
           . '<td class="dim">' . gate_esc($l['camp'] !== '' ? $l['camp'] : '-') . '</td></tr>';
}
$body .= '</tbody></table></div></div>';

$body .= '<div class="term t-r"><div class="term-hd"><span>&#9608; RECENT CLICKS</span></div><div class="term-bd" style="padding:6px 10px"><table class="tc"><thead><tr><th>when</th><th>action</th><th>src</th><th>sig</th></tr></thead><tbody>';
$recentC = array_reverse($humanClicks);
foreach (array_slice($recentC, 0, 9) as $c) {
    $when = $c['ts']; try { $when = (new DateTime($c['ts']))->format('m-d H:i'); } catch (Exception $e) {}
    $body .= '<tr><td class="dim">' . gate_esc($when) . '</td><td class="g">' . gate_esc($c['label']) . '</td>'
           . '<td>' . gate_esc($c['src']) . '</td><td class="dim">' . gate_esc(substr($c['sig'], 0, 8)) . '</td></tr>';
}
$body .= '</tbody></table></div></div>';
$body .= '</div>';

$body .= '<div class="status"><span>as of <span class="lt" data-utc="' . gmdate('c') . '">' . gmdate('Y-m-d H:i') . '</span> &middot; all timestamps Honduras time (America/Tegucigalpa, UTC-6) &middot; gate ' . gate_esc($slug) . ' &middot; src leads/invites/clicks + metrics</span>'
  . '<span><a href="leads.php?token=' . rawurlencode($token) . '&amp;slug=' . rawurlencode($slug) . '">leads</a>'
  . ' &middot; <a href="export.php?token=' . rawurlencode($token) . '&amp;slug=' . rawurlencode($slug) . '">csv</a></span></div>';

$body .= '<div class="socials">'
  . '<a href="https://bitcoinship.show" target="_blank" rel="noopener">bitcoinship.show</a>'
  . '<a href="https://x.com/bitcoinshipshow" target="_blank" rel="noopener">X @bitcoinshipshow</a>'
  . '<a href="https://www.youtube.com/@BitcoinShipshow" target="_blank" rel="noopener">YouTube</a>'
  . '<a href="https://njump.me/npub1mtjn0yp705hvgszssf2ddjpzk3zzjzgvu58dwwpkxqud0hde6fxss7cwyw" target="_blank" rel="noopener">Nostr</a>'
  . '<a href="https://hivetalk.org" target="_blank" rel="noopener">HiveTalk</a>'
  . '<a href="https://bitcoinship.show/assets/" target="_blank" rel="noopener">Assets</a>'
  . '</div>';

$body .= '<script>'
  . 'document.querySelectorAll(".term-hd").forEach(function(h){h.addEventListener("click",function(){h.parentElement.classList.toggle("collapsed")})});'
  . 'function lt(d){d=new Date(d);if(isNaN(d))return "";return new Intl.DateTimeFormat("en-CA",{timeZone:"America/Tegucigalpa",year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit",hourCycle:"h23"}).format(d)}'
  . 'document.querySelectorAll("[data-utc]").forEach(function(e){var l=lt(e.getAttribute("data-utc"));if(l)e.textContent=l})'
  . '</script>';

gate_page('Analytics - ' . $slug, $body, 200);
