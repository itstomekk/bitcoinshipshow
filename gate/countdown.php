<?php
/**
 * Animated countdown GIF for the invite email.
 *
 *   <img src="https://bitcoinship.show/gate/countdown.php?slug=shipshow">
 *
 * Email clients do not run JavaScript, so the "animation" is a GIF rendered
 * fresh on every open: 60 one-second frames counting down from the moment the
 * mail is opened (the standard email-countdown technique). Played once, it
 * stops on the last frame. Before the show: DAYS / HOURS / MINUTES / SECONDS.
 * While live: "WE'RE LIVE". Target = gate_event_start(), the same single source
 * of truth the email, the .ics and the site use - no date is hardcoded here.
 *
 * Font: Pirata One (SIL OFL, redistributable) - same display face as the site.
 * Needs GD with FreeType + GIF (verified on the host 2026-10-02).
 */
require __DIR__ . '/lib.php';

$cfg  = gate_config();
$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9_-]/i', '', $_GET['slug']) : 'shipshow';
$g    = gate_get($cfg, $slug);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('X-Robots-Tag: noindex, nofollow');

const CD_W = 560;
const CD_H = 150;

$font = __DIR__ . '/assets/PirataOne-Regular.ttf';

function cd_hex($im, $hex) {
    $hex = ltrim($hex, '#');
    return imagecolorallocate($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

/** Draw $text centred on x=$cx with its baseline-ish centre at y=$cy. */
function cd_text($im, $font, $size, $color, $cx, $cy, $text) {
    $box = imagettfbbox($size, 0, $font, $text);
    $w = $box[2] - $box[0];
    $h = $box[1] - $box[7];
    imagettftext($im, $size, 0, (int)round($cx - $w / 2 - $box[0]), (int)round($cy + $h / 2 - $box[1]), $color, $font, $text);
}

function cd_canvas() {
    $im = imagecreatetruecolor(CD_W, CD_H);
    imagefill($im, 0, 0, cd_hex($im, '#061726'));   // matches the email's outer bg (reads as transparent)
    return $im;
}

/** Panel geometry: width, gap, top, height, left edge of the first panel. */
function cd_geom() {
    $pw = 116; $gap = 26; $top = 10; $ph = 96;
    return array($pw, $gap, $top, $ph, (int)((CD_W - (4 * $pw + 3 * $gap)) / 2));
}

/** The four two-digit strings shown for $left seconds. */
function cd_digits($left) {
    return array(sprintf('%02d', intdiv($left, 86400)), sprintf('%02d', intdiv($left % 86400, 3600)),
                 sprintf('%02d', intdiv($left % 3600, 60)), sprintf('%02d', $left % 60));
}

/** One countdown frame for $left seconds remaining. */
function cd_frame($font, $left) {
    $im    = cd_canvas();
    $white = cd_hex($im, '#FFFFFF');
    $soft  = cd_hex($im, '#CBDCE8');

    $cells = array(
        array(sprintf('%02d', intdiv($left, 86400)), 'DAYS'),
        array(sprintf('%02d', intdiv($left % 86400, 3600)), 'HOURS'),
        array(sprintf('%02d', intdiv($left % 3600, 60)), 'MINUTES'),
        array(sprintf('%02d', $left % 60), 'SECONDS'),
    );

    list($pw, $gap, $top, $ph, $x0) = cd_geom();

    foreach ($cells as $i => $cell) {
        $x = $x0 + $i * ($pw + $gap);
        cd_text($im, $font, 54, $white, $x + $pw / 2, $top + $ph / 2, $cell[0]);
        cd_text($im, $font, 17, $soft, $x + $pw / 2, $top + $ph + 24, $cell[1]);
        if ($i < 3) {
            cd_text($im, $font, 40, $white, $x + $pw + $gap / 2, $top + $ph / 2 - 3, ':');
        }
    }
    return $im;
}

/** Single banner frame (live / no date). */
function cd_banner($font, $big, $small) {
    $im    = cd_canvas();
    $gold  = cd_hex($im, '#D9AE3C');
    $parch = cd_hex($im, '#CBDCE8');
    imagerectangle($im, 6, 6, CD_W - 7, CD_H - 7, $gold);
    imagerectangle($im, 8, 8, CD_W - 9, CD_H - 9, $gold);
    cd_text($im, $font, 50, $gold, CD_W / 2, 64, $big);
    cd_text($im, $font, 19, $parch, CD_W / 2, 116, $small);
    return $im;
}

function cd_gif_bytes($im) {
    imagetruecolortopalette($im, false, 64);
    ob_start();
    imagegif($im);
    imagedestroy($im);
    return ob_get_clean();
}

/**
 * Stitch single-frame GIFs (same size) into one animated GIF89a.
 * First frame's colour table becomes the global table; every frame carries its
 * own palette as a local colour table so per-frame quantisation stays exact.
 * No NETSCAPE loop block => clients play it once and rest on the last frame.
 * Each item is array(gif_bytes, left, top): later frames are small patches
 * (only the panels that changed) placed over the previous frame, which keeps
 * the file ~10x smaller than 60 full frames.
 */
function cd_animate(array $items, $delayCs) {
    $out = '';
    foreach ($items as $n => $item) {
        list($gif, $left, $top) = $item;
        $packed  = ord($gif[10]);
        $hasGct  = ($packed & 0x80) !== 0;
        $gctLen  = $hasGct ? 3 * (1 << (($packed & 0x07) + 1)) : 0;
        $gct     = substr($gif, 13, $gctLen);
        $p       = 13 + $gctLen;
        $len     = strlen($gif);

        while ($p < $len && $gif[$p] === "\x21") {          // skip extension blocks
            $p += 2;
            while ($p < $len && ($b = ord($gif[$p])) > 0) { $p += $b + 1; }
            $p += 1;
        }
        if ($p >= $len || $gif[$p] !== "\x2C") { continue; }   // no image descriptor

        $desc = substr($gif, $p, 10);
        $desc = "\x2C" . pack('v', $left) . pack('v', $top) . substr($desc, 5);
        $end  = ($gif[$len - 1] === "\x3B") ? $len - 1 : $len;
        $data = substr($gif, $p + 10, $end - ($p + 10));

        if ($out === '') {
            $out = 'GIF89a' . substr($gif, 6, 7) . $gct;
            // NETSCAPE2.0 loop block (0 = forever). Without it Gmail's image
            // proxy serves the GIF as a still image. Each loop replays the
            // same minute; a fresh open always starts from the real time.
            $out .= "\x21\xFF\x0BNETSCAPE2.0\x03\x01" . pack('v', 0) . "\x00";
        }
        // Graphic Control Extension: disposal 1 (leave in place), delay, no transparency
        $out .= "\x21\xF9\x04\x04" . pack('v', $delayCs) . "\x00\x00";

        $dp = ord($desc[9]);
        if ($hasGct && ($dp & 0x80) === 0) {
            $desc[9] = chr(($dp & 0x40) | 0x80 | ($packed & 0x07));
            $out .= $desc . $gct . $data;
        } else {
            $out .= $desc . $data;
        }
    }
    return $out . "\x3B";
}

// ---------------------------------------------------------------- render
$start = $g ? gate_event_start($g) : null;
$now   = time();

if (!$g || !is_file($font) || !function_exists('imagettftext')) {
    http_response_code($g ? 500 : 404);
    header('Content-Type: text/plain; charset=utf-8');
    echo $g ? "countdown unavailable\n" : "unknown gate\n";
    exit;
}

$frames = array();
if (!$start) {
    $frames[] = array(cd_gif_bytes(cd_banner($font, 'NEXT VOYAGE SOON', 'bitcoinship.show')), 0, 0);
} else {
    $target = $start->getTimestamp();
    $ends   = $target + 60 * (int)$g['event_mins'];
    if ($now >= $target && $now < $ends) {
        $frames[] = array(cd_gif_bytes(cd_banner($font, "WE'RE LIVE", 'all hands on deck - join the Shipshow')), 0, 0);
    } else {
        list($pw, $gap, $top, $ph, $x0) = cd_geom();
        $prev = null;
        for ($i = 0; $i < 60; $i++) {
            $left = max(0, $target - $now - $i);
            $full = cd_frame($font, $left);
            $digs = cd_digits($left);
            if ($prev === null) {
                $frames[] = array(cd_gif_bytes($full), 0, 0);
            } else {
                // first panel (from the left) whose digits changed; everything to its right is redrawn
                $first = 3;
                for ($k = 0; $k < 4; $k++) { if ($digs[$k] !== $prev[$k]) { $first = $k; break; } }
                $px = $x0 + $first * ($pw + $gap);
                $patch = imagecrop($full, array('x' => $px, 'y' => $top, 'width' => $x0 + 4 * $pw + 3 * $gap - $px + 1, 'height' => $ph + 1));
                imagedestroy($full);
                $frames[] = array(cd_gif_bytes($patch), $px, $top);
            }
            $prev = $digs;
            if ($left === 0) { break; }
        }
    }
}

header('Content-Type: image/gif');
echo count($frames) === 1 ? $frames[0][0] : cd_animate($frames, 100);
