<?php
/**
 * Bitcoin Shipshow — unified ops dashboard (traffic + reach for all properties).
 *   https://bitcoinship.show/gate/ops.php?token=YOUR_ADMIN_TOKEN
 *   https://bitcoinship.show/gate/ops.php?token=...&focus=roatan
 *
 * Same admin_token as leads.php / analytics.php. Server-rendered, no JS, no
 * external deps. Reads gate/data/metrics-<slug>.json (published daily by
 * Hermes\cpanel-tools\metrics_collector.py) for each property and renders one
 * section per property. The full Shipshow funnel stays in analytics.php — this
 * page links to it, so nothing regresses.
 *
 * "Pirate terminal" theme, same components as analytics.php.
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

$dir   = rtrim($cfg['data_dir'], '/');
$focus = isset($_GET['focus']) ? preg_replace('/[^a-z0-9_-]/i', '', $_GET['focus']) : '';

/* ------------------------------------------------------------- properties */

$PROPS = array(
    array('slug' => 'shipshow', 'title' => 'Bitcoin Shipshow',
          'sub' => 'bitcoinship.show',
          'links' => array('analytics' => 'analytics.php', 'leads' => 'leads.php')),
    array('slug' => 'roatan', 'title' => 'Roatan network',
          'sub' => 'roatan.biz &middot; roatan.live &middot; roatan.directory &middot; roatan.work &middot; roatan.academy'),
    array('slug' => 'bitcoindistrict', 'title' => 'Bitcoin District',
          'sub' => 'thebitcoindistrict.com'),
);

function ops_load($dir, $slug) {
    $f = $dir . '/metrics-' . $slug . '.json';
    if (!is_file($f)) return null;
    $m = json_decode(@file_get_contents($f), true);
    return is_array($m) ? $m : null;
}

function ops_num($v) {
    return ($v === null || $v === '') ? '-' : number_format((int)$v);
}

function ops_dash($v) {
    return ($v === null || $v === '') ? '-' : $v;
}

/* ------------------------------------------------------------ svg helpers */

function ops_spark($days, $color, $h = 40) {
    $n = count($days); if (!$n) return '';
    $max = 1; foreach ($days as $v) $max = max($max, $v);
    $W = 120; $H = $h; $cw = $W / $n; $bw = max(0.6, $cw * 0.62);
    $s = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" preserveAspectRatio="none" style="width:100%;height:' . $H . 'px;display:block">';
    $s .= '<line x1="0" y1="' . ($H - 1) . '" x2="' . $W . '" y2="' . ($H - 1) . '" stroke="rgba(203,220,232,.22)" stroke-width="1"/>';
    $i = 0;
    foreach ($days as $v) {
        $bh = ($H - 3) * ($v / $max);
        $x = $i * $cw; $y = $H - 1 - $bh;
        $s .= '<rect x="' . round($x, 2) . '" y="' . round($y, 2) . '" width="' . round($bw, 2) . '" height="' . round($bh, 2) . '" fill="' . $color . '"/>';
        $i++;
    }
    return $s . '</svg>';
}

function ops_bars($rows, $color, $key = 'name', $limit = 8) {
    if (!$rows) return '';
    $rows = array_slice($rows, 0, $limit);
    $max = 1; foreach ($rows as $r) $max = max($max, (int)$r['count']);
    $out = '';
    foreach ($rows as $r) {
        $lab = (string)$r[$key];
        if ($lab === '') $lab = '(none)';
        $w = round(100 * (int)$r['count'] / $max);
        $out .= '<div class="hb"><div class="hb-l" title="' . gate_esc($lab) . '">' . gate_esc($lab) . '</div>'
              . '<div class="hb-t"><div class="hb-b" style="width:' . $w . '%;background:' . $color . '"></div></div>'
              . '<div class="hb-v">' . (int)$r['count'] . '</div></div>';
    }
    return $out;
}

function ops_empty($what) {
    return '<div class="muted">' . gate_esc($what) . '</div>';
}

$stat = function ($v, $l, $d = '', $gold = false) {
    return '<div class="stat"><div class="v' . ($gold ? ' gold' : '') . '">' . $v . '</div>'
         . '<div class="l">' . $l . '</div>'
         . ($d !== '' ? '<div class="d">' . $d . '</div>' : '') . '</div>';
};

function ops_leads($dir, $slug) {
    $f = $dir . '/leads-' . $slug . '.csv';
    if (!is_file($f)) return null;
    $fh = @fopen($f, 'r');
    if (!$fh) return null;
    $total = 0; $tally = array();
    while (($r = fgetcsv($fh)) !== false) {
        if (!isset($r[1]) || (isset($r[0]) && $r[0] === 'created_at')) continue;
        $total++;
        // utm columns 6-8 exist only on 9-column rows (see leads.php); older rows
        // keep ip_hash in column 6, so never treat those as campaigns.
        $camp = '';
        if (count($r) >= 9) {
            $camp = implode('/', array_filter(array(
                trim((string)$r[6]), trim((string)$r[7]), trim((string)$r[8]),
            ), function ($v) { return $v !== ''; }));
        }
        if ($camp === '') $camp = '(untagged)';
        $tally[$camp] = (isset($tally[$camp]) ? $tally[$camp] : 0) + 1;
    }
    fclose($fh);
    $rows = array();
    foreach ($tally as $k => $v) $rows[] = array('name' => $k, 'count' => $v);
    usort($rows, function ($a, $b) { return $b['count'] - $a['count']; });
    return array('total' => $total, 'rows' => $rows);
}

/* ------------------------------------------------------------------ theme */

$mono = 'Consolas,"Cascadia Mono",Menlo,monospace';
$G = '#3ad07a'; $GD = '#D9AE3C'; $BL = '#1E6FA6';

$body = '<style>'
  . '.wrap{max-width:1060px}'
  . '.wrap h1{font-size:clamp(30px,5.5vw,46px);margin:10px 0 4px}'
  . '.prompt{font-family:' . $mono . ';font-size:12.5px;color:' . $G . ';margin:6px 0 0}'
  . '.prompt .cur{color:#7fa0bd}'
  . '.term{background:rgba(6,23,38,.94);border:1px solid #14486F;box-shadow:inset 0 0 34px rgba(11,31,51,.55);margin:14px 0}'
  . '.term.hit{border-color:' . $GD . ';box-shadow:inset 0 0 34px rgba(217,174,60,.18),0 0 0 1px rgba(217,174,60,.35)}'
  . '.term-hd{display:flex;justify-content:space-between;align-items:center;gap:10px;font-family:' . $mono . ';font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:' . $GD . ';border-bottom:1px solid #14486F;padding:6px 12px;background:linear-gradient(90deg,rgba(20,72,111,.55),rgba(6,23,38,0))}'
  . '.term-hd .ok{color:' . $G . ';text-transform:none;letter-spacing:0}'
  . '.term-hd .sub{color:#7fa0bd;text-transform:none;letter-spacing:0;font-size:10.5px}'
  . '.term-bd{padding:12px 14px}'
  . '.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(112px,1fr));gap:1px;background:#14486F;border:1px solid #14486F}'
  . '.stat{background:#061726;padding:11px 12px}'
  . '.stat .v{font-family:' . $mono . ';font-size:23px;line-height:1;color:' . $G . '}'
  . '.stat .v.gold{color:' . $GD . '}'
  . '.stat .l{font-family:' . $mono . ';font-size:10px;letter-spacing:.09em;text-transform:uppercase;color:#7fa0bd;margin-top:6px}'
  . '.stat .d{font-family:' . $mono . ';font-size:10px;color:' . $GD . ';margin-top:3px}'
  . '.h2{font-family:' . $mono . ';font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:' . $GD . ';margin:20px 0 8px}'
  . '.h2 .dim{color:#7fa0bd;text-transform:none;letter-spacing:0}'
  . '.hb{display:grid;grid-template-columns:150px 1fr 42px;gap:10px;align-items:center;margin:5px 0;font-family:' . $mono . ';font-size:12px}'
  . '.hb-l{color:#CBDCE8;text-align:right;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
  . '.hb-t{background:rgba(20,72,111,.35);height:14px}'
  . '.hb-b{height:100%}'
  . '.hb-v{color:' . $G . ';text-align:left}'
  . '.muted{font-family:' . $mono . ';font-size:12px;color:#7fa0bd;padding:3px 2px}'
  . '.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}'
  . '@media(max-width:760px){.grid2{grid-template-columns:1fr}}'
  . '.tc{width:100%;border-collapse:collapse;font-family:' . $mono . ';font-size:11.5px;color:#CBDCE8}'
  . '.tc th{text-align:left;font-size:9.5px;letter-spacing:.09em;text-transform:uppercase;color:#7fa0bd;border-bottom:1px solid #14486F;padding:4px 6px;white-space:nowrap}'
  . '.tc td{padding:4px 6px;border-bottom:1px solid rgba(20,72,111,.35);vertical-align:top}'
  . '.tc td.n{color:' . $G . ';text-align:right;white-space:nowrap}'
  . '.tc td.dim{color:#7fa0bd}'
  . '.tc td.em{color:' . $GD . '}'
  . '.foot-note{font-family:' . $mono . ';font-size:11px;color:#7fa0bd;margin:10px 2px 0}'
  . '.status{margin-top:22px;font-family:' . $mono . ';font-size:11px;color:#7fa0bd;border-top:1px solid #14486F;padding:10px 2px;display:flex;flex-wrap:wrap;gap:6px 18px;justify-content:space-between}'
  . '.status a{color:' . $GD . ';text-decoration:none}'
  . '.status code{color:' . $G . '}'
  . '</style>';

$body .= '<p class="kicker">UNIFIED OPS</p>'
  . '<h1>Dashboard</h1>'
  . '<div class="prompt">$ ops --all-properties <span class="cur"># traffic + reach</span></div>';

/* --------------------------------------------------------- fleet summary */

$loaded = array();
$fleetPv = 0; $fleetVis = 0;
foreach ($PROPS as $p) {
    $m = ops_load($dir, $p['slug']);
    $loaded[$p['slug']] = $m;
    if ($m && isset($m['goatcounter']) && is_array($m['goatcounter'])) {
        $fleetPv  += (int)(isset($m['goatcounter']['pageviews']) ? $m['goatcounter']['pageviews'] : 0);
        $fleetVis += (int)(isset($m['goatcounter']['visitors']) ? $m['goatcounter']['visitors'] : 0);
    }
}

$body .= '<div class="stats">'
  . $stat(count($PROPS), 'properties', 'one page')
  . $stat(number_format($fleetPv), 'fleet pageviews', 'all time', true)
  . $stat(number_format($fleetVis), 'fleet visitors', 'all time')
  . '</div>';

/* ----------------------------------------------------------- per property */

foreach ($PROPS as $p) {
    $slug  = $p['slug'];
    $m     = $loaded[$slug];
    $isHit = ($focus === $slug);
    $links = '';

    if ($m === null) {
        $body .= '<div class="term' . ($isHit ? ' hit' : '') . '">'
          . '<div class="term-hd"><span>&#9608; ' . gate_esc($p['title']) . '</span><span class="sub">' . $p['sub'] . '</span></div>'
          . '<div class="term-bd">' . ops_empty('no metrics yet — run metrics_collector.py for ' . $slug) . '</div></div>';
        continue;
    }

    $gc  = (isset($m['goatcounter']) && is_array($m['goatcounter'])) ? $m['goatcounter'] : array();
    $yt  = (isset($m['youtube']) && is_array($m['youtube'])) ? $m['youtube'] : array();
    $no  = (isset($m['nostr']) && is_array($m['nostr'])) ? $m['nostr'] : array();
    $tw  = (isset($m['twitter']) && is_array($m['twitter'])) ? $m['twitter'] : array();
    $ht  = (isset($m['hashtags']) && is_array($m['hashtags'])) ? $m['hashtags'] : array();
    $win = (isset($gc['windows']) && is_array($gc['windows'])) ? $gc['windows'] : array();

    $asof = '';
    if (!empty($m['as_of'])) { try { $asof = (new DateTime($m['as_of']))->format('Y-m-d H:i'); } catch (Exception $e) {} }

    $hdrRight = ($asof !== '' ? 'as of ' . $asof . ' UTC' : 'no timestamp');
    if (!empty($gc['error'])) { $hdrRight .= ' &middot; goatcounter: ' . gate_esc(substr($gc['error'], 0, 60)); }

    $body .= '<div class="term' . ($isHit ? ' hit' : '') . '" id="' . gate_esc($slug) . '">'
      . '<div class="term-hd"><span>&#9608; ' . gate_esc($p['title']) . '</span>'
      . '<span class="ok">' . $hdrRight . '</span></div>'
      . '<div class="term-bd">'
      . '<div class="sub-muted" style="font-family:' . $mono . ';font-size:10.5px;color:#7fa0bd;margin:-4px 0 10px">' . $p['sub'] . '</div>';

    /* stat grid */
    $body .= '<div class="stats">'
      . $stat(ops_num(isset($win['all']) ? $win['all'] : null), 'pageviews', 'all time', true)
      . $stat(ops_num(isset($gc['visitors']) ? $gc['visitors'] : null), 'visitors', 'all time', true)
      . $stat(ops_num(isset($win['30d']) ? $win['30d'] : null), '30d')
      . $stat(ops_num(isset($win['7d']) ? $win['7d'] : null), '7d')
      . $stat(ops_num(isset($win['24h']) ? $win['24h'] : null), '24h');
    $body .= $stat(isset($yt['subscribers']) ? ops_num($yt['subscribers']) : '-', 'youtube subs')
      . $stat(isset($yt['views']) ? ops_num($yt['views']) : '-', 'youtube views')
      . $stat((isset($no['followers']) && $no['followers'] !== null) ? ops_num($no['followers']) : '-', 'nostr followers')
      . $stat((isset($tw['followers']) && $tw['followers'] !== null) ? ops_num($tw['followers']) : '-', 'twitter followers')
      . '</div>';

    /* 30d sparkline */
    $days = array();
    if (!empty($gc['daily30']) && is_array($gc['daily30'])) {
        foreach ($gc['daily30'] as $d) { $days[(string)$d['day']] = (int)$d['count']; }
    }
    if ($days) {
        $kd = array_keys($days);
        $body .= '<div class="h2">pageviews / day <span class="dim">' . substr($kd[0], 5) . ' .. ' . substr($kd[count($kd) - 1], 5) . ' (UTC)</span></div>'
               . ops_spark($days, $G, 44);
    }

    /* reach extras */
    $htParts = array();
    foreach ($ht as $tag => $n) {
        $htParts[] = '<strong style="color:' . $G . '">' . ($n === null ? '-' : (int)$n) . '</strong> ' . gate_esc($tag);
    }
    if ($htParts) {
        $body .= '<div class="h2">nostr mentions <span class="dim">' . implode(' &nbsp;&middot;&nbsp; ', $htParts) . '</span></div>';
    }
    if (isset($yt['error'])) $body .= '<div class="muted">youtube: ' . gate_esc($yt['error']) . '</div>';
    if (isset($tw['error'])) $body .= '<div class="muted">twitter: ' . gate_esc($tw['error']) . '</div>';
    if (isset($no['error'])) $body .= '<div class="muted">nostr: ' . gate_esc($no['error']) . '</div>';

    /* referrers + campaigns */
    $refs  = (isset($gc['referrers']) && is_array($gc['referrers'])) ? $gc['referrers'] : array();
    $camps = (isset($gc['campaigns']) && is_array($gc['campaigns'])) ? $gc['campaigns'] : array();
    $body .= '<div class="grid2" style="margin-top:6px">';
    $body .= '<div><div class="h2" style="margin-top:14px">referrers</div>'
           . (ops_bars($refs, $G) ?: ops_empty('none yet')) . '</div>';
    $body .= '<div><div class="h2" style="margin-top:14px">campaigns <span class="dim">utm_*</span></div>'
           . (ops_bars($camps, $GD) ?: ops_empty('no utm-tagged visits yet')) . '</div>';
    $body .= '</div>';

    /* top paths */
    $paths = (isset($gc['paths']) && is_array($gc['paths'])) ? $gc['paths'] : array();
    if ($paths) {
        $body .= '<div class="h2">top paths <span class="dim">all time</span></div>'
               . '<table class="tc"><thead><tr><th>path</th><th>title</th><th>views</th></tr></thead><tbody>';
        foreach (array_slice($paths, 0, 10) as $row) {
            $body .= '<tr><td class="em">' . gate_esc($row['path']) . '</td>'
                   . '<td class="dim">' . gate_esc($row['title']) . '</td>'
                   . '<td class="n">' . (int)$row['count'] . '</td></tr>';
        }
        $body .= '</tbody></table>';
    }

    /* signups by campaign (only for properties that run a gate) */
    $leads = ops_leads($dir, $slug);
    if ($leads && $leads['total'] > 0) {
        $tagged = 0;
        foreach ($leads['rows'] as $lr) { if ($lr['name'] !== '(untagged)') $tagged += $lr['count']; }
        $body .= '<div class="h2">signups by campaign <span class="dim">' . $tagged . ' of '
               . $leads['total'] . ' tagged &middot; utm_source/medium/campaign</span></div>'
               . ops_bars($leads['rows'], $BL, 'name', 8);
    }

    /* per-site breakdown when a property aggregates several GoatCounter codes */
    $sites = (isset($gc['sites']) && is_array($gc['sites'])) ? $gc['sites'] : array();
    if (count($sites) > 1) {
        $body .= '<div class="h2">by site</div><table class="tc"><thead><tr><th>code</th><th>pageviews</th><th>30d</th><th>7d</th></tr></thead><tbody>';
        foreach ($sites as $s) {
            $w = isset($s['pageviews']) && is_array($s['pageviews']) ? $s['pageviews'] : array();
            $body .= '<tr><td class="dim">' . gate_esc($s['code']) . '</td>'
                   . '<td class="n">' . (int)(isset($w['all']) ? $w['all'] : 0) . '</td>'
                   . '<td class="n">' . (int)(isset($w['30d']) ? $w['30d'] : 0) . '</td>'
                   . '<td class="n">' . (int)(isset($w['7d']) ? $w['7d'] : 0) . '</td></tr>';
        }
        $body .= '</tbody></table>';
    }

    /* deep links */
    if (!empty($p['links'])) {
        $bits = array();
        foreach ($p['links'] as $label => $file) {
            $bits[] = '<a href="' . gate_esc($file) . '?token=' . rawurlencode($token) . '&amp;slug=' . rawurlencode($slug) . '">' . gate_esc($label) . '</a>';
        }
        $body .= '<div class="foot-note">full detail: ' . implode(' &middot; ', $bits) . '</div>';
    }

    $body .= '</div></div>';
}

$body .= '<div class="status"><span>as of ' . gmdate('Y-m-d H:i') . ' UTC &middot; src gate/data/metrics-&lt;slug&gt;.json &middot; collector metrics_collector.py</span>'
  . '<span><a href="analytics.php?token=' . rawurlencode($token) . '&amp;slug=shipshow">shipshow analytics</a>'
  . ' &middot; <a href="leads.php?token=' . rawurlencode($token) . '&amp;slug=shipshow">leads</a>'
  . ' &middot; <a href="ops.php?token=' . rawurlencode($token) . '">refresh</a></span></div>';

gate_page('Ops - all properties', $body, 200);
