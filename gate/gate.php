<?php
/**
 * Email gate - the endpoint.
 *
 * POST { slug, email, consent?, company (honeypot) }  ->  link-free confirmation; room URL by email only.
 * GET  anything                                       ->  a page with NO link.
 *
 * The room URL is included only in the signup email. It is never in public
 * page source, JavaScript, JSON responses, or the HTML confirmation.
 */

require __DIR__ . '/lib.php';
require_once __DIR__ . '/track.php';
require __DIR__ . '/invite.php';
require __DIR__ . '/gcal.php';

$cfg = gate_config();

// ---------------------------------------------------------------- helpers

function gate_wants_json() {
    return isset($_POST['format']) && $_POST['format'] === 'json';
}

function gate_json($payload, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload);
    exit;
}

function gate_fail($title, $msg, $back = null, $backLabel = null, $status = 400) {
    if (gate_wants_json()) {
        gate_json(array('ok' => $status < 400, 'error' => $msg), $status);
    }
    $b  = '<p class="kicker">BITCOIN SHIPSHOW</p>';
    $b .= '<h1>' . gate_esc($title) . '</h1>';
    $b .= '<div class="card">';
    $b .= '<p class="lead">' . gate_esc($msg) . '</p>';
    if ($back) {
        $b .= '<a class="btn ghost" href="' . gate_esc($back) . '">Back to ' . gate_esc($backLabel ? $backLabel : 'the site') . '</a>';
    }
    $b .= '</div>';
    gate_page($title, $b, $status);
    exit;
}

// ---------------------------------------------------------------- routing

$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';

if ($method !== 'POST') {
    gate_fail(
        'Nothing to see here',
        'This page only responds to the form on the site. Enter your email there and check your inbox for the call link.',
        'https://bitcoinship.show/#join',
        'bitcoinship.show',
        405
    );
}

$slug = isset($_POST['slug']) ? preg_replace('/[^a-z0-9_-]/i', '', $_POST['slug']) : '';
$g    = gate_get($cfg, $slug);

if (!$g) {
    gate_fail('That link is closed', 'This gate does not exist or is no longer active.', 'https://bitcoinship.show/', 'bitcoinship.show', 404);
}

// ---------------------------------------------------------------- checks

// 1. Honeypot. Bots fill every field they find; humans never see this one.
if (!empty($_POST['company'])) {
    if (gate_wants_json()) { gate_json(array('ok' => true, 'status' => 'dup', 'headline' => 'Invite sent'), 200); }
    gate_fail('Thanks', 'Check back at the site for the next episode.', $g['return_url'], $g['return_label'], 200);
}

// 2. Email
$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    gate_fail('That email does not look right', 'Go back and try again - we need a working address to let you in.', $g['return_url'], $g['return_label'], 400);
}

// 3. Rate limit
$ipHash = gate_ip_hash($cfg, gate_client_ip());
if (!gate_rate_ok($cfg, $ipHash)) {
    gate_fail('Slow down', 'Too many requests from this connection. Try again in an hour.', $g['return_url'], $g['return_label'], 429);
}

// 4. Store
$consent  = !empty($_POST['consent']);
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$ua       = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

// Campaign attribution: the landing page stuffed the visitor's first-touch UTM
// values into the form (see partials/boot.php). Store them with the lead so a
// report can answer "which link drove this signup".
$utm = array();
foreach (array('utm_source', 'utm_medium', 'utm_campaign') as $utmKey) {
    if (!isset($_POST[$utmKey])) { continue; }
    $utmVal = substr(trim(preg_replace('/[^\x20-\x7E]/', '', (string)$_POST[$utmKey])), 0, 80);
    if ($utmVal !== '') { $utm[$utmKey] = $utmVal; }
}

$stored   = gate_store_lead($cfg, $slug, $email, $consent, $referrer, $ua, $ipHash, $utm);

// A storage failure must not lock out a real person - log it and let them in.
if ($stored === 'error') {
    @error_log('[gate] could not write lead for slug=' . $slug);
}

// Fresh lead: control copy to the organizer, then the calendar invite.
// The organizer notification is separate from the attendee invite. The invite
// is controlled by the gate's auto_invite setting; only its new-lead path runs here.
// Failures are logged inside invite_send and never block the unlock.
$inviteSent = false;
$gcalResult = 'off';
if ($stored === 'new') {
    gate_notify($cfg, $g, $email, $referrer);
    if (!empty($g['auto_invite'])) {
        $inviteSent = invite_send($cfg, $g, $slug, $email);
    }
    // A real Google invitation, sent from the Shipshow calendar account, so
    // the guest gets an RSVP-able entry instead of only an .ics. Deliberately
    // independent of the email: a Google outage must not stop a signup, and a
    // mail failure must not stop the invite. Fails soft, always.
    $gcalResult = gcal_add_guest($cfg, $g, $email);
    if ($gcalResult !== 'off') { gcal_log($cfg, $slug, $email, $gcalResult); }
}

// ---------------------------------------------------------------- the card

$tz    = null;
$start = null;
$end   = null;
$human = '';
$iso   = '';
$gcal  = '';
$ics   = '';
$url   = track_url($cfg, 'join', 'site-card', $email);   // click-counted via go.php
try {
    $start = gate_event_start($g);
    if (!$start) { throw new Exception('no date'); }
    $tz    = $start->getTimezone();
    $end   = clone $start;
    $end->modify('+' . (int)$g['event_mins'] . ' minutes');
} catch (Exception $e) {
    $start = null;
}

$whenHtml = '';
if ($start) {
    $human = $start->format('D j M Y, H:i') . ' ' . $start->format('T');
    $iso   = $start->format('c');
    $whenHtml =
        '<div class="when">' .
          '<span class="label">' . gate_esc($g['event_label']) . '</span>' .
          '<span class="date">' . gate_esc($human) . '</span>' .
          '<div class="local" data-iso="' . gate_esc($iso) . '"></div>' .
        '</div>';
}

// Calendar links
$calHtml = '';
if ($start && $end) {
    $utcStart = clone $start; $utcStart->setTimezone(new DateTimeZone('UTC'));
    $utcEnd   = clone $end;   $utcEnd->setTimezone(new DateTimeZone('UTC'));
    $gcal = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
          . '&text=' . rawurlencode($g['event_title'])
          . '&dates=' . $utcStart->format('Ymd\THis\Z') . '/' . $utcEnd->format('Ymd\THis\Z')
          . '&details=' . rawurlencode($g['event_note'] . "\n\nJoin: " . $g['target_url'])
          . '&location=' . rawurlencode($g['target_url']);
    $ics = 'gate/ics.php?slug=' . rawurlencode($slug) . '&t=' . gate_token($cfg, $slug, $g);
    $calHtml =
        '<div class="cals">' .
          '<a href="' . gate_esc($gcal) . '" target="_blank" rel="noopener">Add to Google Calendar</a>' .
          '<a href="' . gate_esc(str_replace('gate/ics.php', 'ics.php', $ics)) . '">Download .ics</a>' .
        '</div>';
}

if (gate_wants_json()) {
    gate_json(array(
        'ok'          => true,
        'status'      => $stored,                       // new | dup
        'headline'    => ($stored === 'dup') ? 'You are already on the list' : 'Invite sent',
        'event_label' => $g['event_label'],
        'event_human' => $human,
        'event_iso'   => $iso,
        'invite_sent' => $inviteSent,
        'gcal'        => $gcalResult,                   // added | exists | off | error
    ), 200);
}

$rulesHtml = '';
if (!empty($g['rules'])) {
    $rulesHtml = '<h2>House rules</h2><ul>';
    foreach ($g['rules'] as $r) { $rulesHtml .= '<li>' . gate_esc($r) . '</li>'; }
    $rulesHtml .= '</ul>';
}

$dupLine = ($stored === 'dup')
    ? '<p>This address is already on the list. Check your inbox for the call invitation.</p>'
    : ($inviteSent
        ? '<p>Your call invitation is on its way by email. Check your inbox, including spam.</p>'
        : '<p>Your signup was saved, but the invitation could not be sent. Please contact <a href="mailto:hello@bitcoinship.show">the Shipshow team</a>.</p>');

$body =
  '<p class="kicker">' . gate_esc($g['brand']) . '</p>' .
  '<div class="card">' .
    '<h1>' . $g['headline_html'] . '</h1>' .
    $dupLine .
    $whenHtml .
    $rulesHtml .
  '</div>' .
  '<p class="foot"><a href="' . gate_esc($g['return_url']) . '">&#8592; back to ' . gate_esc($g['return_label']) . '</a></p>' .
  '<script>' .
    'var l=document.querySelector(".local[data-iso]");' .
    'if(l){try{var d=new Date(l.getAttribute("data-iso"));' .
      'l.textContent="Your local time: "+d.toLocaleString(undefined,{weekday:"short",day:"numeric",month:"short",hour:"2-digit",minute:"2-digit"});' .
    '}catch(e){}}' .
  '</script>';

gate_page('You are in - ' . $g['event_title'], $body, 200);
