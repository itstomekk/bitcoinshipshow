<?php
/**
 * Resolves which design to serve. Include this first, before any output.
 *
 * Order of precedence:
 *   1. ?design=<slug>   explicit, and it sticks (cookie, 30 days)
 *   2. cookie           so /about/ and /sponsors/ keep the same skin
 *   3. DEFAULT_DESIGN
 *
 * ?design=  with an unknown slug silently falls back to the default rather than
 * erroring - these URLs get pasted into chat apps that mangle query strings.
 */

/* consts and functions live at file scope, so a second require would fatal */
if (defined('BSS_BOOT')) { return; }
define('BSS_BOOT', 1);

/* ---------------------------------------------------------------------------
   First-touch campaign capture (Phase 4, link attribution).

   A visit that lands with ?utm_source=…&utm_medium=…&utm_campaign=… keeps that
   attribution in a cookie for 90 days, so the signup row can record WHICH link
   drove it (X post, newsletter, a partner page, a demo card…). A later tagged
   visit overwrites the earlier one - last click wins inside the window.
   Only the five standard UTM keys are stored, stripped to printable ASCII.

   Convention (keep to it when tagging links):
     utm_source   x | nostr | youtube | newsletter | email | telegram | <property-slug>
                  (a property slug = bitcoinshipshow / roatan / district, used when
                   one of our own sites links out to another of our properties)
     utm_medium   social | email | site | guest-post | qr
     utm_campaign <shipshow | roatan | district>-<episode-or-partner>
   Example: https://bitcoinship.show/?utm_source=x&utm_medium=social&utm_campaign=shipshow-042
--------------------------------------------------------------------------- */
const UTM_COOKIE = 'bss_utm';
const UTM_KEYS   = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content');
const UTM_MAXLEN = 80;
const UTM_DAYS   = 90;

$UTM = array();
if (isset($_COOKIE[UTM_COOKIE])) {
    $decoded = json_decode((string) $_COOKIE[UTM_COOKIE], true);
    if (is_array($decoded)) { $UTM = $decoded; }
}

$utmSeen = array();
foreach (UTM_KEYS as $utmKey) {
    if (!isset($_GET[$utmKey])) { continue; }
    $utmVal = substr(trim(preg_replace('/[^\x20-\x7E]/', '', (string) $_GET[$utmKey])), 0, UTM_MAXLEN);
    if ($utmVal !== '') { $utmSeen[$utmKey] = $utmVal; }
}
if ($utmSeen) {
    $UTM = array_merge($UTM, $utmSeen);
    setcookie(UTM_COOKIE, json_encode($UTM), array(
        'expires'  => time() + 60 * 60 * 24 * UTM_DAYS,
        'path'     => '/',
        'samesite' => 'Lax',
    ));
}

/** Hidden inputs carrying the visitor's campaign into the signup POST. */
function utm_fields(): string {
    global $UTM;
    $html = '';
    foreach (UTM_KEYS as $utmKey) {
        if (empty($UTM[$utmKey])) { continue; }
        $html .= '<input type="hidden" name="' . $utmKey . '" value="' . e($UTM[$utmKey]) . '">';
    }
    return $html;
}

/** "x/social/shipshow-042" - one line for the leads table. */
function utm_line(): string {
    global $UTM;
    $parts = array();
    foreach (array('utm_source', 'utm_medium', 'utm_campaign') as $utmKey) {
        if (!empty($UTM[$utmKey])) { $parts[] = $UTM[$utmKey]; }
    }
    return implode('/', $parts);
}

const DEFAULT_DESIGN = 'corsair';
const DESIGN_COOKIE  = 'bss_design';

/* Design settled 2026-09-21: corsair / "Blue Ensign". While this is true the
   site serves ONE design to everybody - the cookie and ?design= are both
   ignored, and the picker is gone.

   Ignoring the cookie matters: anyone who clicked a skin during the August
   testing still carries a 30-day bss_design cookie, and honouring it would
   quietly keep serving them the old design long after it was "locked".
   Set to false to reopen the picker for testing. */
const DESIGN_UNLOCKED = false;

$DESIGNS = require __DIR__ . '/designs.php';

if (!DESIGN_UNLOCKED) {
    $DESIGN = DEFAULT_DESIGN;
    /* actively clear any stale cookie rather than just ignoring it */
    if (isset($_COOKIE[DESIGN_COOKIE])) {
        setcookie(DESIGN_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax']);
    }
} else {
    $requested = isset($_GET['design']) ? preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['design'])) : null;

    if ($requested !== null && isset($DESIGNS[$requested])) {
        $DESIGN = $requested;
        setcookie(DESIGN_COOKIE, $DESIGN, [
            'expires'  => time() + 60 * 60 * 24 * 30,
            'path'     => '/',
            'samesite' => 'Lax',
        ]);
    } elseif (isset($_COOKIE[DESIGN_COOKIE]) && isset($DESIGNS[$_COOKIE[DESIGN_COOKIE]])) {
        $DESIGN = $_COOKIE[DESIGN_COOKIE];
    } else {
        $DESIGN = DEFAULT_DESIGN;
    }
}

$D = $DESIGNS[$DESIGN];

/* Picker only ever shows while the design is unlocked. */
$SHOW_PICKER = DESIGN_UNLOCKED && (($_GET['picker'] ?? '1') !== '0');

/* Cache-bust on file mtime so a CSS edit shows up without a hard refresh. */
function asset(string $path): string {
    $abs = __DIR__ . '/../' . ltrim($path, '/');
    $v   = @filemtime($abs);
    return '/' . ltrim($path, '/') . ($v ? '?v=' . $v : '');
}

function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
