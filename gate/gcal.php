<?php
/**
 * Google Calendar bridge - add a new lead as a guest on the next episode.
 *
 * Why this file exists: the invite email the gate sends is ours, but the
 * CALENDAR entry has to come from the Shipshow Google account or it is not a
 * real invitation (no RSVP, no updates when Tomek edits the event). Google is
 * not an open relay like SMTP, so adding a guest needs a credential.
 *
 * The credential in config['google'] is CALENDAR-ONLY
 * (scope https://www.googleapis.com/auth/calendar.events). It cannot read
 * Gmail, cannot touch Drive, cannot see any other calendar. Rotate it by
 * minting a fresh consent and pasting the new refresh_token into the config.
 *
 * Everything here fails soft: a Google outage must never block a signup, and
 * must never stop the invite email, which is the thing people actually need.
 *
 * PHP 7.4+ compatible. Needs the curl extension (standard on cPanel).
 */

/** Access token from the refresh token, cached in data/ until it expires. */
function gcal_access_token($cfg) {
    if (empty($cfg['google']['refresh_token'])) { return null; }

    $file = gate_data_dir($cfg) . '/gcal-token.json';
    $now  = time();

    if (is_file($file)) {
        $cached = json_decode((string)@file_get_contents($file), true);
        if (is_array($cached) && !empty($cached['access_token'])
            && !empty($cached['expires_at']) && (int)$cached['expires_at'] > $now + 60) {
            return $cached['access_token'];
        }
    }

    $res = gcal_http('POST', 'https://oauth2.googleapis.com/token',
        http_build_query(array(
            'client_id'     => $cfg['google']['client_id'],
            'client_secret' => $cfg['google']['client_secret'],
            'refresh_token' => $cfg['google']['refresh_token'],
            'grant_type'    => 'refresh_token',
        )),
        array('Content-Type: application/x-www-form-urlencoded'));

    if (!$res || (int)$res['status'] !== 200) {
        @error_log('[gate] gcal token refresh failed: ' . ($res ? substr($res['body'], 0, 200) : 'no response'));
        return null;
    }
    $j = json_decode($res['body'], true);
    if (empty($j['access_token'])) { return null; }

    $ttl = isset($j['expires_in']) ? (int)$j['expires_in'] : 3600;
    @file_put_contents($file, json_encode(array(
        'access_token' => $j['access_token'],
        'expires_at'   => $now + $ttl,
    )), LOCK_EX);
    @chmod($file, 0600);
    return $j['access_token'];
}

/** Tiny curl wrapper. Returns array(status, body) or null. */
function gcal_http($method, $url, $body = null, $headers = array()) {
    if (!function_exists('curl_init')) {
        @error_log('[gate] gcal: curl extension missing');
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false,
    ));
    if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
    $out  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($out === false) {
        @error_log('[gate] gcal curl error: ' . $err);
        return null;
    }
    return array('status' => $code, 'body' => $out);
}

/** The gate's gcal block, or null when it is off / unconfigured. */
function gcal_settings($cfg, $g) {
    if (empty($g['gcal']['enabled']))                     { return null; }
    if (empty($g['gcal']['calendar_id']))                 { return null; }
    if (empty($g['gcal']['event_id']))                    { return null; }
    if (empty($cfg['google']['refresh_token']))           { return null; }
    return $g['gcal'];
}

/**
 * Add $email as a guest of the gate's episode event.
 * Returns 'added', 'exists', 'off' or 'error'.
 *
 * Read-modify-write is serialised with a lock file: two people signing up in
 * the same second would otherwise both read the same guest list and the second
 * write would silently drop the first.
 */
function gcal_add_guest($cfg, $g, $email) {
    $gc = gcal_settings($cfg, $g);
    if (!$gc) { return 'off'; }

    $email = strtolower(trim((string)$email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return 'error'; }

    $tok = gcal_access_token($cfg);
    if (!$tok) { return 'error'; }

    $cal  = rawurlencode($gc['calendar_id']);
    $evid = rawurlencode($gc['event_id']);
    $auth = array('Authorization: Bearer ' . $tok, 'Accept: application/json');
    $base = 'https://www.googleapis.com/calendar/v3/calendars/' . $cal . '/events/' . $evid;

    $lock = @fopen(gate_data_dir($cfg) . '/gcal.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        if ($lock) { fclose($lock); }
        return 'error';
    }

    $result = 'error';
    try {
        $res = gcal_http('GET', $base, null, $auth);
        if (!$res || (int)$res['status'] !== 200) {
            @error_log('[gate] gcal get event failed: ' . ($res ? (int)$res['status'] . ' ' . substr($res['body'], 0, 200) : 'no response'));
            $result = 'error';
        } else {
            $evt  = json_decode($res['body'], true);
            $att  = (isset($evt['attendees']) && is_array($evt['attendees'])) ? $evt['attendees'] : array();

            $already = false;
            foreach ($att as $a) {
                if (isset($a['email']) && strtolower($a['email']) === $email) { $already = true; break; }
            }

            if ($already) {
                $result = 'exists';
            } elseif (count($att) >= 190) {
                // Gmail caps guests per event; past the cap every add would fail
                // anyway, so stop calling and make the reason visible in the log.
                @error_log('[gate] gcal: guest cap reached on ' . $gc['event_id'] . ' - ' . $email . ' NOT added');
                $result = 'error';
            } else {
                $att[] = array('email' => $email);
                // sendUpdates=all on purpose (Tomek, 2026-10-07): the Google
                // invitation IS the calendar delivery. Google mails the guest a
                // real, RSVP-able invite from the Shipshow calendar, and the
                // entry tracks later edits to the event. The signup email no
                // longer carries its own Add-to-Calendar link.
                // Was sendUpdates=none, which added the guest silently and left
                // the .ics attachment as the only calendar artifact.
                $res = gcal_http('PATCH', $base . '?sendUpdates=all&supportsAttachments=true',
                    json_encode(array('attendees' => $att)),
                    array_merge($auth, array('Content-Type: application/json')));
                if ($res && ((int)$res['status'] === 200 || (int)$res['status'] === 201)) {
                    $result = 'added';
                } else {
                    @error_log('[gate] gcal patch failed: ' . ($res ? (int)$res['status'] . ' ' . substr($res['body'], 0, 300) : 'no response'));
                    $result = 'error';
                }
            }
        }
    } catch (Exception $e) {
        @error_log('[gate] gcal exception: ' . $e->getMessage());
        $result = 'error';
    }

    flock($lock, LOCK_UN);
    fclose($lock);
    return $result;
}

/** Append to data/gcal-<slug>.csv so we can see who got a real Google invite. */
function gcal_log($cfg, $slug, $email, $result) {
    $dir = gate_data_dir($cfg);
    if (!$dir) { return; }
    $file = $dir . '/gcal-' . preg_replace('/[^a-z0-9_-]/i', '', $slug) . '.csv';
    $new  = !is_file($file);
    $fh   = @fopen($file, 'a');
    if (!$fh) { return; }
    if (flock($fh, LOCK_EX)) {
        if ($new) { fputcsv($fh, array('ts', 'email', 'result')); }
        fputcsv($fh, array(gmdate('c'), $email, $result));
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    @chmod($file, 0600);
}
