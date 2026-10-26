<?php
/**
 * Auto-invite by email.
 *
 * Called from gate.php the moment a NEW lead signs up: everyone gets the
 * calendar invite to the next expedition immediately, with the Hivetalk join
 * link inside the event. No external dependencies, PHP 7.4+ compatible.
 *
 * v7.4 (2026-10-06): the signup email no longer carries the room link. The
 * big CTA is now "Add to Calendar" (Google template link, which has the link
 * inside the event), the .ics is attached for Apple/Outlook, and the link is
 * delivered by gate/remind.php an hour before the show. A real Google
 * invitation is sent separately by gate/gcal.php. Everyone invited for this
 * episode is enrolled into data/remind-<episode>.csv.
 * v7.3 (2026-10-05): join / ics / featured / sponsor / homeport / Nostr / X / YouTube links
 * go through gate/go.php (click counter + redirect). Google Calendar button stays direct.
 * v7.2 (2026-10-02, late): every v7 send BOUNCED - the local exim transport
 * rejects any message line over 2048 bytes ("message has lines too long for
 * transport"), and the featured-builders table row is ~3027 bytes on one line.
 * Both body parts are now transfer-encoded (text = quoted-printable, html =
 * base64, ics was already base64), so no line can exceed the limit.
 * v7 (2026-10-02, Tomek's review of v6): "You're in, pirate!"; new tagline;
 * episode -> expedition; small calendar buttons right under the countdown;
 * Roatan dropped; open mic -> open deck; "visit homeport" link under the CTA;
 * sponsors (transparent logos, img/email/) moved above the featured block;
 * featured in 2 columns with small logos; laptop hint removed. Countdown GIF
 * now loops (Gmail showed it static without a NETSCAPE loop block).
 *
 * v6 (2026-10-02): ET headline, city clocks without date, countdown GIF,
 * featured builders, Nostr link, send log (data/invites-<slug>.csv).
 * v5 (2026-10-01): timezone lines fixed. v4: sponsors strip. v3: corsair design.
 */

require_once __DIR__ . '/track.php';   // click-counted links (v7.3)

/** Zone labels as Tomek wants them shown (generic, not the DST abbreviation). */
function invite_zone_label($tzid) {
    $map = array(
        'America/Los_Angeles' => 'PT',
        'America/New_York'    => 'ET',
        'Europe/Berlin'       => 'CET',
        'America/Tegucigalpa' => 'CST',
    );
    return isset($map[$tzid]) ? $map[$tzid] : $tzid;
}

/** "10:00 PT" - local clock time only, no date (the date is in the headline). */
function invite_clock(DateTime $dt, $tzid) {
    try {
        $d = clone $dt;
        $d->setTimezone(new DateTimeZone($tzid));
        return $d->format('H:i') . ' ' . invite_zone_label($tzid);
    } catch (Exception $e) {
        return null;
    }
}

/** Headline: "Tue 6 Oct 2026, 1pm ET" (minutes only when not on the hour). */
function invite_headline(DateTime $dt) {
    $d = clone $dt;
    $d->setTimezone(new DateTimeZone('America/New_York'));
    $clock = $d->format('i') === '00' ? $d->format('ga') : $d->format('g:ia');
    return $d->format('D j M Y') . ', ' . $clock . ' ET';
}

function invite_episode($g) {
    if (!empty($g['episode'])) { return $g['episode']; }
    return preg_match('/\bE\d{2,4}\b/', (string)$g['event_title'], $m) ? $m[0] : '';
}

/** Append one row to data/invites-<slug>.csv (sent_at, email, ok, note). */
function invite_log($cfg, $slug, $email, $ok, $note = '') {
    $dir = gate_data_dir($cfg);
    if (!$dir) { return; }
    $file = $dir . '/invites-' . $slug . '.csv';
    $new  = !is_file($file);
    $fh   = @fopen($file, 'a');
    if (!$fh) { return; }
    if (flock($fh, LOCK_EX)) {
        if ($new) { fputcsv($fh, array('sent_at', 'email', 'ok', 'note')); }
        fputcsv($fh, array(gmdate('c'), $email, $ok ? '1' : '0', $note));
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    @chmod($file, 0600);
}

/** Emails that already have a successful invite in the log (lower-cased set). */
function invite_already_sent($cfg, $slug) {
    $dir  = gate_data_dir($cfg);
    $file = $dir ? $dir . '/invites-' . $slug . '.csv' : '';
    $sent = array();
    if (!$file || !is_file($file)) { return $sent; }
    if (($fh = @fopen($file, 'r')) === false) { return $sent; }
    fgetcsv($fh);
    while (($row = fgetcsv($fh)) !== false) {
        if (isset($row[1], $row[2]) && $row[2] === '1') { $sent[strtolower(trim($row[1]))] = true; }
    }
    fclose($fh);
    return $sent;
}

/** One featured builder cell: small logo left, name + blurb right. */
function invite_featured_cell($f, $esc) {
    $logo = !empty($f['logo'])
        ? '<td width="40" valign="top" style="padding:2px 10px 0 0;width:40px;"><a href="' . $esc($f['url']) . '"><img src="' . $esc($f['logo']) . '" alt="" width="36" height="36" style="display:block;border:0;width:36px;height:36px;"></a></td>' . "\n"
        : '';
    return '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>' . "\n" . $logo
        . '<td valign="top"><a href="' . $esc($f['url']) . '" style="color:#D9AE3C;text-decoration:none;font-size:17px;font-weight:bold;">' . $esc($f['name']) . '</a>' . "\n"
        . '<div style="font-size:12px;line-height:1.4;color:#CBDCE8;margin-top:2px;">' . $esc($f['blurb']) . '</div></td>' . "\n"
        . '</tr></table>';
}

function invite_html($g, $headline, $clocks, $gcal, $icsUrl, $url, $countdownUrl, $episode, $heroHtml, $trk = null) {
    $esc   = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    // $trk('label') -> click-counted URL (go.php); without it every link stays direct
    $tl    = function ($label, $direct) use ($trk, $esc) { return $esc($trk ? $trk($label) : $direct); };
    $note  = $esc(!empty($g['card_note']) ? $g['card_note'] : 'Always on the first Tuesday of the month.');
    $t     = $esc($g['event_title']);
    $sub   = $esc(trim(str_replace('Bitcoin Shipshow', '', $g['event_title'])));
    $h     = $esc($headline);
    $gc    = $esc($gcal);
    $cd    = $esc($countdownUrl);
    $ep    = $esc($episode);
    $nostr = $tl('nostr', !empty($g['nostr_url']) ? $g['nostr_url'] : 'https://njump.me/npub1mtjn0yp705hvgszssf2ddjpzk3zzjzgvu58dwwpkxqud0hde6fxss7cwyw');
    $lnkHome = $tl('home',  'https://bitcoinship.show');
    $lnkFt   = $tl('ft',    'https://www.freedomtech.co');
    $lnkBd   = $tl('bd',    'https://thebitcoindistrict.com');
    $lnkHt   = $tl('ht',    'https://hivetalk.org');
    $lnkX    = $tl('x',     'https://x.com/bitcoinshipshow');
    $lnkYt   = $tl('yt',    'https://www.youtube.com/@BitcoinShipshow');
    $hr    = $heroHtml;

    // "We will stream here": a thumbnail linked to the YouTube broadcast (an
    // email cannot carry an iframe player). Both come from the gate config
    // (stream_url / stream_thumb, bumped per episode); without them the block
    // is simply left out.
    $streamHtml = '';
    if (!empty($g['stream_url']) && !empty($g['stream_thumb'])) {
        $lnkLive = $tl('yt-live', $g['stream_url']);
        $streamHtml = '    <!-- stream -->' . "\n"
            . '    <div style="text-align:center;padding:22px 8px 0;">' . "\n"
            . '      <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#D9AE3C;font-weight:bold;margin-bottom:10px;">We will stream here</div>' . "\n"
            . '      <a href="' . $lnkLive . '" style="text-decoration:none;"><img src="' . $esc($g['stream_thumb']) . '" alt="Watch Bitcoin Shipshow live on YouTube" width="560" style="display:block;margin:0 auto;width:100%;max-width:560px;height:auto;border:4px solid #0B1F33;"></a>' . "\n"
            . '      <a href="' . $lnkLive . '" style="display:inline-block;color:#D9AE3C;text-decoration:none;font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:1px;margin-top:10px;">YouTube &middot; @BitcoinShipshow &rarr;</a>' . "\n"
            . '    </div>' . "\n";
    }
    $testBanner = '';
    if (!empty($g['test_note'])) {
        $testBanner = '<div style="background:#D9AE3C;color:#0B1F33;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:bold;text-align:center;padding:10px 16px;border:4px solid #0B1F33;border-top:0;border-bottom:0;">' . $esc(!empty($g['test_note_text']) ? $g['test_note_text'] : 'This is a test invite - mind replying with any feedback? (layout, times, links)') . '</div>';
    }

    // one line: "San Francisco · 10:00 PT  |  Berlin · 19:00 CET" (each city is nowrap,
    // so a narrow phone breaks between the two cities, never inside one)
    $clockParts = array();
    foreach ($clocks as $city => $clock) {
        if ($clock) { $clockParts[] = '<span style="white-space:nowrap;">' . $esc($city) . ' &middot; <b>' . $esc($clock) . '</b></span>'; }
    }
    $clockHtml = implode(' &nbsp;|&nbsp; ', $clockParts);

    // featured builders, two per row
    $featHtml = '';
    $feat = (!empty($g['featured']) && is_array($g['featured'])) ? array_values($g['featured']) : array();
    if ($trk) { foreach ($feat as $k => $f) { $feat[$k]['url'] = $trk('feat-' . track_slug($f['name'])); } }
    for ($i = 0; $i < count($feat); $i += 2) {
        if (!isset($feat[$i + 1])) {
            // odd count: the last builder takes the full row instead of leaving an empty cell
            $featHtml .= '<tr>' . "\n"
                . '<td colspan="2" width="100%" valign="top" style="padding:10px 0;border-top:1px solid #14486F;">' . invite_featured_cell($feat[$i], $esc) . '</td>' . "\n"
                . '</tr>' . "\n";
            continue;
        }
        $featHtml .= '<tr>' . "\n"
            . '<td width="50%" valign="top" style="padding:10px 8px 10px 0;border-top:1px solid #14486F;">' . invite_featured_cell($feat[$i], $esc) . '</td>' . "\n"
            . '<td width="50%" valign="top" style="padding:10px 0 10px 8px;border-top:1px solid #14486F;">' . invite_featured_cell($feat[$i + 1], $esc) . '</td>' . "\n"
            . '</tr>' . "\n";
    }
    $featHead = $ep !== '' ? 'Featured in expedition ' . $ep : 'Featured this expedition';

    // marketing description (event_note): blank line = paragraph, newline = <br>
    $desc = '';
    foreach (array_map('trim', explode("\n\n", (string)$g['event_note'])) as $p) {
        if ($p === '' || stripos($p, 'Featured in') === 0) { continue; }   // featured has its own box
        // linkify, with short labels so a 60-char npub URL never stretches a phone screen
        $html = preg_replace_callback('~https?://[^\s<]+~', function ($m) {
            $href  = rtrim($m[0], '.,)');
            $label = stripos($href, 'njump.me/npub') !== false ? 'our Nostr profile'
                   : preg_replace('~^https?://(www\.)?~', '', rtrim($href, '/'));
            return '<a href="' . $href . '" style="color:#D9AE3C;text-decoration:none;">' . $label . '</a>'
                 . substr($m[0], strlen($href));
        }, $esc($p));
        $desc .= '<p style="margin:0 0 12px 0;font-size:14px;line-height:1.55;">' . nl2br($html) . '</p>' . "\n";
    }

    return <<<HTML
<div style="background:#061726;padding:30px 14px;font-family:Georgia,'Times New Roman',serif;">
  <div style="max-width:600px;margin:0 auto;background:#061726;">

    <!-- header: ink band, gold brand line -->
    <div style="background:#0B1F33;border:4px solid #0B1F33;border-bottom:0;padding:26px 22px 18px;text-align:center;">
      <img src="https://bitcoinship.show/img/shipshow-boat.png" alt="Bitcoin Shipshow" width="200" style="display:block;margin:0 auto 10px;max-width:100%;height:auto;">
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;letter-spacing:4px;text-transform:uppercase;font-weight:bold;color:#D9AE3C;">Bitcoin Shipshow</div>
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#CBDCE8;margin-top:6px;">{$sub}</div>
    </div>
{$testBanner}

    <!-- hero -->
    <div style="background:#0B1F33;border:4px solid #0B1F33;border-top:0;padding:20px 14px 24px;text-align:center;">
      <div style="font-size:40px;line-height:1.1;color:#D9AE3C;font-weight:bold;">{$hr}</div>
      <div style="font-size:15px;color:#CBDCE8;line-height:1.45;margin:12px auto 0;max-width:480px;">Hey, it will be good to see you there! It&#039;s kind of a group call, kind of a TV show: demos, chats &amp; releases from the freedom tech seas. Come present your project or meet other pirate builders!</div>
    </div>

    <!-- event card: parchment -->
    <div style="background:#CBDCE8;border:4px solid #0B1F33;margin:22px 8px 0;padding:20px 20px 16px;color:#0B1F33;">
      <div style="font-size:30px;font-weight:bold;line-height:1.1;margin-bottom:10px;">{$h}</div>
      <div style="font-size:15px;line-height:1.7;">{$clockHtml}</div>
      <div style="font-style:italic;font-size:13px;color:#0C2E4A;margin-top:12px;">{$note}</div>
    </div>

    <!-- countdown: white text, blends with the dark background -->
    <div style="text-align:center;padding:22px 8px 0;">
      <img src="{$cd}" alt="Countdown to the show" width="560" style="display:block;margin:0 auto;width:100%;max-width:560px;height:auto;border:0;">
    </div>

{$streamHtml}
    <!-- calendar invitation panel + homeport. The real Google invitation is
         mailed by gate/gcal.php the moment they sign up - they are added as a
         guest of this episode's event - so there is no self-serve
         Add-to-Calendar link here any more. -->
    <div style="text-align:center;padding:14px 8px 0;">
      <div style="background:#1E6FA6;border:4px solid #0B1F33;padding:16px 18px;font-family:Georgia,'Times New Roman',serif;font-size:19px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#FFFFFF;">Calendar invitation sent</div>
      <div style="color:#A9C3D6;font-size:13px;line-height:1.6;margin:12px auto 0;max-width:470px;">A separate invitation is on its way from the Shipshow calendar - accept it and the show lands in your calendar with a reminder. Nothing in a few minutes? Check your spam folder.</div>
      <a href="{$lnkHome}" style="display:block;background:#0B1F33;color:#D9AE3C;text-decoration:none;font-weight:bold;font-size:13px;letter-spacing:2px;text-transform:uppercase;font-family:Arial,Helvetica,sans-serif;border:3px solid #D9AE3C;padding:11px 16px;margin-top:14px;">Visit homeport &rarr;</a>
    </div>

    <!-- featured builders (2 columns) -->
    <div style="background:#0C2E4A;border:4px solid #0B1F33;border-bottom:1px solid #14486F;margin:24px 8px 0;padding:16px 16px 6px;color:#CBDCE8;">
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#D9AE3C;font-weight:bold;margin-bottom:6px;">{$featHead}</div>
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">{$featHtml}</table>
    </div>

    <!-- open dock: integrated with the featured box -->
    <div style="background:#0C2E4A;border:4px solid #0B1F33;border-top:0;margin:0 8px;padding:14px 18px;color:#CBDCE8;">
      <div style="font-size:19px;font-weight:bold;color:#FFFFFF;">+ Open dock</div>
      <div style="font-size:14px;line-height:1.5;margin-top:3px;">Come and present what you are building, choose scrutiny over mutiny!</div>
    </div>

    <!-- what to know -->
    <div style="background:#0C2E4A;border:4px solid #0B1F33;margin:24px 8px 0;padding:16px 18px;color:#CBDCE8;">
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#D9AE3C;font-weight:bold;margin-bottom:10px;">What to know</div>
      {$desc}
    </div>

    <!-- footer: brought to you by + links -->
    <div style="text-align:center;padding:28px 10px 8px;">
      <div style="color:#D9AE3C;font-size:11px;letter-spacing:3px;text-transform:uppercase;font-weight:bold;font-family:Arial,Helvetica,sans-serif;margin-bottom:12px;">Brought to you by</div>
      <a href="{$lnkFt}" style="text-decoration:none;"><img src="https://bitcoinship.show/img/email/freedomtech.png" alt="Freedom Tech" width="180" height="auto" style="display:block;margin:0 auto;border:0;width:180px;height:auto;"></a>
      <div style="padding-top:14px;">
        <a href="{$lnkBd}" style="text-decoration:none;"><img src="https://bitcoinship.show/img/email/bitcoin-district.png" alt="Bitcoin District" width="104" height="31" style="display:inline-block;border:0;vertical-align:middle;"></a>&nbsp;&nbsp;&nbsp;&nbsp;
        <a href="{$lnkHt}" style="text-decoration:none;"><img src="https://bitcoinship.show/img/email/hivetalk.png" alt="HiveTalk" width="104" height="32" style="display:inline-block;border:0;vertical-align:middle;"></a>
      </div>
    </div>

    <div style="text-align:center;padding:20px 10px 30px;color:#A9C3D6;font-size:12px;line-height:1.8;">
      <a href="{$lnkHome}" style="color:#D9AE3C;text-decoration:none;font-weight:bold;">bitcoinship.show</a> &middot;
      <a href="{$nostr}" style="color:#D9AE3C;text-decoration:none;">Nostr</a> &middot;
      <a href="{$lnkX}" style="color:#D9AE3C;text-decoration:none;">@bitcoinshipshow</a> &middot; <a href="{$lnkYt}" style="color:#D9AE3C;text-decoration:none;">YouTube</a> &middot; #bitcoinshipshow<br>
    </div>

  </div>
</div>
HTML;}

/**
 * Build and send one invite email. Returns true when PHP handed the message
 * to the mail system. Never throws. Every attempt is logged (invite_log).
 */
function invite_send($cfg, $g, $slug, $email, $logNote = 'auto') {
    // ---- event window -------------------------------------------------
    try {
        $start = gate_event_start($g);
        if (!$start) { throw new Exception('no date'); }
        $end = clone $start;
        $end->modify('+' . (int)$g['event_mins'] . ' minutes');
    } catch (Exception $e) {
        @error_log('[gate] invite skipped (no event date) slug=' . $slug);
        return false;
    }

    $episode  = invite_episode($g);
    $utm      = array(
        'utm_source'   => 'email',
        'utm_medium'   => 'email',
        'utm_campaign' => $episode !== '' ? 'shipshow-' . strtolower($episode) : 'shipshow',
    );
    $trk = function ($label) use ($cfg, $email, $utm) { return track_url($cfg, $label, 'email', $email, $utm); };
    $headline = invite_headline($start);
    $clocks   = array(
        'San Francisco' => invite_clock($start, 'America/Los_Angeles'),
        'Berlin'        => invite_clock($start, 'Europe/Berlin'),
    );

    // Google Calendar link, same shape as the unlock card
    $utcStart = clone $start; $utcStart->setTimezone(new DateTimeZone('UTC'));
    $utcEnd   = clone $end;   $utcEnd->setTimezone(new DateTimeZone('UTC'));
    $gcal = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
          . '&text=' . rawurlencode($g['event_title'])
          . '&dates=' . $utcStart->format('Ymd\THis\Z') . '/' . $utcEnd->format('Ymd\THis\Z')
          . '&details=' . rawurlencode($g['event_note'] . "\n\nJoin: " . $g['target_url'])
          . '&location=' . rawurlencode($g['target_url']);

    // signed .ics download link (same token as the unlock card)
    $tok    = gate_token($cfg, $slug, $g);
    $icsUrl = 'https://bitcoinship.show/gate/ics.php?slug=' . rawurlencode($slug) . '&t=' . $tok;
    $cdUrl  = 'https://bitcoinship.show/gate/countdown.php?slug=' . rawurlencode($slug);

    // ---- .ics attachment ----------------------------------------------
    $icsText = implode("\r\n", gate_ics_lines($slug, $g, $utcStart, $utcEnd)) . "\r\n";

    // ---- plain-text body (fallback / readable version) ----------------
    $textBody  = $start->format('F j') . ": Bitcoin Shipshow " . $episode . "! \u{26F5}\n";
    $textBody .= "Hey, it will be good to see you there! It's kind of a group call, kind of a TV show: demos, chats & releases from the freedom tech seas. Come present your project or meet other pirate builders!\n\n";
    $textBody .= $g['event_title'] . "\n";
    $textBody .= $headline . "\n";
    $textClocks = array();
    foreach ($clocks as $city => $clock) {
        if ($clock) { $textClocks[] = $city . ' · ' . $clock; }
    }
    if ($textClocks) { $textBody .= implode('  |  ', $textClocks) . "\n"; }
    // The room link is deliberately absent from this email, and the copy says
    // nothing about how it arrives: it rides in the Google invitation and in the
    // attached .ics, and remind.php mails it again an hour before.
    if (!empty($g['stream_url'])) { $textBody .= "\nWE WILL STREAM HERE (YouTube): " . $g['stream_url'] . "\n"; }
    $textBody .= "\nCALENDAR INVITATION: sent separately from the Shipshow calendar.\n";
    $textBody .= "Accept it and the show lands in your calendar with a reminder.\n";
    $textBody .= "Nothing in a few minutes? Check your spam folder.\n";
    $textBody .= "Visit homeport: https://bitcoinship.show\n";
    if (!empty($g['featured']) && is_array($g['featured'])) {
        $textBody .= "\n" . ($episode !== '' ? 'FEATURED IN EXPEDITION ' . $episode : 'FEATURED') . ":\n";
        foreach ($g['featured'] as $f) {
            $textBody .= '- ' . $f['name'] . ' (' . $f['url'] . ') - ' . $f['blurb'] . "\n";
        }
        $textBody .= "+ Open dock: come and present what you are building, choose scrutiny over mutiny!\n";
    }
    $textBody .= "\n" . $g['event_note'] . "\n";
    $textBody .= "\nBrought to you by: Freedom Tech (freedomtech.co), Bitcoin District (thebitcoindistrict.com), HiveTalk (hivetalk.org)\n";
    if (!empty($g['test_note'])) { $textBody .= "\n(This is a test invite - mind replying with any feedback?)\n"; }
    $textBody .= "\n- The Bitcoin Shipshow crew\n";

    $d        = $start->format('F j');
    $hero     = $d . ": Bitcoin Shipshow " . $episode . "! \u{26F5}";
    $heroHtml = $d . ": <span style='color:#FFFFFF;text-shadow:3px 3px 0 #000000;'>Bitcoin Shipshow</span> " . $episode . "! \u{26F5}";
    $htmlBody = invite_html($g, $headline, $clocks, $gcal, $icsUrl, $g['target_url'], $cdUrl, $episode, $heroHtml, $trk);

    // ---- MIME ---------------------------------------------------------
    $boundary = '----=_shipshow_' . bin2hex(random_bytes(8));
    $altBound = '----=_shipshow_alt_' . bin2hex(random_bytes(6));
    $ics64 = chunk_split(base64_encode($icsText));

    $message  = "This is a multi-part message in MIME format.\r\n";
    $message .= "--" . $boundary . "\r\n";
    $message .= "Content-Type: multipart/alternative; boundary=\"" . $altBound . "\"\r\n\r\n";

    // Body parts are TRANSFER-ENCODED, never raw 8bit. The exim transport on
    // the host rejects any line over 2048 bytes ("message has lines too long
    // for transport"), and the HTML is one long line per table row (the v7
    // featured grid row is ~3027 bytes) - every 8bit send bounced. QP/base64
    // both wrap at <=76 chars and decode back to the exact original.
    $message .= "--" . $altBound . "\r\n";
    $message .= "Content-Type: text/plain; charset=utf-8\r\n";
    $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $message .= quoted_printable_encode($textBody) . "\r\n";

    $message .= "--" . $altBound . "\r\n";
    $message .= "Content-Type: text/html; charset=utf-8\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";

    $message .= "--" . $altBound . "--\r\n";
    $message .= "--" . $boundary . "\r\n";
    $message .= "Content-Type: text/calendar; charset=utf-8; method=PUBLISH;\r\n";
    $message .= " name=bitcoin-shipshow.ics\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=bitcoin-shipshow.ics\r\n\r\n";
    $message .= $ics64 . "\r\n";
    $message .= "--" . $boundary . "--\r\n";

    // Sender: the gate's 'mail' block in config (display name carries the
    // ship emoji), falling back to a top-level 'mail' block, then defaults.
    $mail     = !empty($g['mail']) ? $g['mail'] : (!empty($cfg['mail']) ? $cfg['mail'] : array());
    $from     = !empty($mail['from'])      ? $mail['from']      : 'invites@bitcoinship.show';
    $fromName = !empty($mail['from_name']) ? $mail['from_name'] : 'Bitcoin Shipshow';
    $to       = $email;
    $subject  = $start->format('F j') . ": Bitcoin Shipshow " . $episode . "! \u{26F5}";
    // Replies land in the project inbox (hello@bitcoinship.show forwards to
    // kolotom@gmail.com via cPanel).
    $replyTo  = 'hello@bitcoinship.show';

    // RFC 2047: encode the display name (emoji) and the subject.
    $fromEnc = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $subjEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $headers  = 'From: ' . $fromEnc . ' <' . $from . ">\r\n";
    $headers .= 'Reply-To: ' . $replyTo . "\r\n";
    $headers .= 'Return-Path: ' . $from . "\r\n";
    $headers .= 'Message-ID: <' . bin2hex(random_bytes(8)) . '@bitcoinship.show>' . "\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";
    $headers .= 'X-Mailer: Bitcoin Shipshow gate' . "\r\n";

    // Strip anything that could smuggle headers in via the address.
    $to = str_replace(array("\r", "\n"), '', $to);

    // Test hook: SHIPSHOW_DUMP_INVITE=<path> writes the finished MIME message
    // there instead of sending it, so the invite can be previewed on a machine
    // with no MTA (Windows dev box). The env var is never set on the host, so
    // this branch does not run in production. Returns before the log and the
    // reminder enrolment so a preview leaves no trace.
    if (getenv('SHIPSHOW_DUMP_INVITE')) {
        file_put_contents(getenv('SHIPSHOW_DUMP_INVITE'), $headers . "\r\n" . $message);
        return true;
    }

    $ok = @mail($to, $subjEnc, $message, $headers, '-f ' . $from);
    if (!$ok) {
        @error_log('[gate] mail() failed for slug=' . $slug . ' to=' . $to);
    }
    invite_log($cfg, $slug, $to, $ok, $logNote);
    if ($ok) {
        // Only people we actually invited for THIS episode go on the reminder
        // list - remind.php is what hands them the room link an hour before.
        remind_enrol($cfg, $episode, $to);
    }
    return $ok;
}
