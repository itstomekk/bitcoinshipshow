<?php
/**
 * /live/ is retired (Tomek, 2026-10-07: "this works strange... should redirect
 * to main page anyway").
 *
 * It was the next-expedition screen: the countdown, the E02 lineup, and where
 * the stream and the replays land. All of that lives on the homepage now, so
 * this permanent redirect keeps every existing link working - the Satlantis /
 * NIP-52 event descriptions, the reminder emails, and anything posted before
 * today.
 *
 * The last full version of the page is archived in the project folder at
 * _archive/2026-10-07-live-page-retired/index.php
 */
header('Location: https://bitcoinship.show/', true, 301);
exit;
