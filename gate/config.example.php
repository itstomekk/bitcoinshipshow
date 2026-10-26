<?php
// Template only. Copy to gate/config.php on the server and fill in real values.
// The real gate/config.php is never committed (see .gitignore).
return array(
    'admin_token'   => 'CHANGE_ME_long_random_string',   // guards leads/export/ops/remind/roomlink endpoints
    'secret'        => 'CHANGE_ME_long_random_string',   // HMAC key for IP hashes and signed .ics tokens
    'notify_email'  => 'you@example.com',
    'data_dir'      => __DIR__ . '/data',                // leads + click CSVs live here, never in git
    'rate_max'      => 10,
    'rate_window'   => 600,
    'google'        => array(                            // optional: calendar-only OAuth, adds signups as guests
        'client_id'     => '',
        'client_secret' => '',
        'refresh_token' => '',
    ),
    'gates' => array(
        'shipshow' => array(
            'active'        => true,
            'brand'         => 'Bitcoin Shipshow',
            'episode'       => 'E001',
            'event_title'   => 'Bitcoin Shipshow',
            'event_label'   => 'First Tuesday, monthly',
            'event_rule'    => 'first-tuesday',          // recurrence rule, see gate_event_start() in lib.php
            'event_start'   => '2026-01-06T10:00:00-08:00',
            'event_mins'    => 60,
            'event_note'    => '',
            'target_url'    => 'https://example.com/room', // room link, changes every episode
            'room_link_in_event_hours_before' => 48,
            'auto_invite'   => false,                    // keep false unless you really want instant invite mail
            'featured'      => array(),                  // guests shown in the invite email
            'stream_url'    => '',
            'nostr_url'     => '',
            'mail'          => array('from' => 'invites@example.com'),
        ),
    ),
);
