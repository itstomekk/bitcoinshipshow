<?php
/**
 * Click tracking helpers (shared by gate.php and invite.php).
 *
 * Every tracked link is   https://bitcoinship.show/gate/go.php?l=<label>&s=<source>[&c=<sig>]
 *   l = what was clicked (join, ics, feat-angor, ft, bd, ht, yt, x, nostr, home)
 *   s = where the link lives (email, site, site-card)
 *   c = 8-char HMAC of the recipient's email: lets the report count unique
 *       invited people who clicked, without putting any address in a URL or log.
 * go.php logs the click and 302s to the real destination. PHP 7.4+ compatible.
 */

function track_sig($cfg, $email) {
    return substr(hash_hmac('sha256', 'c|' . strtolower(trim((string)$email)), (string)$cfg['secret']), 0, 8);
}

function track_slug($name) {
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$name)), '-');
}

function track_url($cfg, $label, $src, $email = '', $utm = array()) {
    $u = 'https://bitcoinship.show/gate/go.php?l=' . rawurlencode($label) . '&s=' . rawurlencode($src);
    if ((string)$email !== '') { $u .= '&c=' . track_sig($cfg, $email); }
    foreach ((array)$utm as $k => $v) {
        if ((string)$v === '') { continue; }
        $u .= '&' . rawurlencode($k) . '=' . rawurlencode((string)$v);
    }
    return $u;
}
