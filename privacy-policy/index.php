<?php
/** /privacy-policy/ - privacy policy. Shell and styling live in partials/legal.php. */
$PAGE_SLUG  = 'privacy-policy';
$PAGE_TITLE = 'Privacy Policy — Bitcoin Shipshow';
$PAGE_DESC  = 'What Bitcoin Shipshow collects when you sign up for the monthly online Bitcoin meetup, what it is used for, who else touches it, and how to have it deleted.';

$LEGAL = [
    'h1'      => 'Privacy policy',
    'updated' => '7 October 2026',
    'intro'   => 'Bitcoin Shipshow is a free monthly online Bitcoin meetup and live demo show at '
               . '<a href="https://bitcoinship.show/">bitcoinship.show</a>. This page says what we collect '
               . 'when you use the site or sign up for the invitation, what we do with it, and how to make us stop. '
               . 'We collect as little as the show needs to work.',
    'sections' => [
        ['Who we are', '<p>Bitcoin Shipshow ("we", "us") is run by the Bitcoin Shipshow team. '
            . 'Anything about your data: <a href="mailto:hello@bitcoinship.show">hello@bitcoinship.show</a>.</p>'],

        ['What we collect', '<p>When you leave your email on the sign-up form:</p><ul>'
            . '<li>your <b>email address</b> and the time you signed up;</li>'
            . '<li>a consent flag, recorded when you submit the form to receive the invitation;</li>'
            . '<li>the <b>page you came from</b> (referrer) and your <b>browser user-agent</b> string;</li>'
            . '<li>the <b>campaign tags</b> on the link you used (utm_source, utm_medium, utm_campaign and similar), so we know which post or channel brought you;</li>'
            . '<li>a <b>one-way hash of your IP address</b>. It is a keyed hash, so we cannot turn it back into your IP. '
            . 'It is used only to rate-limit abuse and to spot duplicate sign-ups.</li></ul>'
            . '<p>When you click a link in one of our emails we log the time, which link it was, the hashed IP, the referring host and your user-agent. '
            . 'When someone requests a page that does not exist we log the path, referring host and user-agent, so we can fix broken links.</p>'
            . '<p>We do not ask for your name, phone number, payment details or anything from your Google account.</p>'],

        ['Cookies and similar storage', '<ul>'
            . '<li><b>bss_utm</b> — a first-party cookie holding the campaign tags from the link you arrived on, kept for 90 days, so the sign-up can be attributed to the right post.</li>'
            . '<li>A <b>local-storage flag</b> in your browser that remembers you have already signed up, so the page does not ask you twice.</li></ul>'
            . '<p>No advertising cookies and no cross-site tracking. Page-view statistics come from '
            . '<a href="https://www.goatcounter.com/">GoatCounter</a>, a privacy-friendly counter that sets no cookies.</p>'],

        ['What we use it for', '<ul>'
            . '<li>to email you the invitation to the next show, including a calendar file;</li>'
            . '<li>to email you a reminder shortly before the show starts;</li>'
            . '<li>to count sign-ups and see which channels work;</li>'
            . '<li>to keep the site safe from abuse.</li></ul>'
            . '<p>We do not sell your data, rent it out, or use it for anything unrelated to the show. '
            . 'The legal basis is your consent (the sign-up form) and our legitimate interest in running and securing the site.</p>'],

        ['Google Calendar', '<p>When you sign up we may add your email address as a <b>guest on the show\'s event in the Bitcoin Shipshow Google Calendar</b>, '
            . 'so a normal calendar invitation lands with you. This is done with the Google Calendar API, using a Google account that belongs to Bitcoin Shipshow, '
            . 'with the narrow <code>calendar.events</code> permission, and only on that one calendar. '
            . 'We never sign in to your Google account and we never read your calendar, mail, files or contacts. '
            . 'Calendar changes we make later (for example adding the room link shortly before the show) are made silently, without extra emails.</p>'
            . '<p>Bitcoin Shipshow\'s use and transfer of information received from Google APIs adheres to the '
            . '<a href="https://developers.google.com/terms/api-services-user-data-policy">Google API Services User Data Policy</a>, '
            . 'including the Limited Use requirements.</p>'],

        ['Who else handles it', '<ul>'
            . '<li><b>Our web host</b> stores the site and the sign-up records and sends the invitation emails.</li>'
            . '<li><b>Google</b> runs the calendar described above, and YouTube carries the public stream.</li>'
            . '<li><b>GoatCounter</b> counts page views without cookies.</li>'
            . '<li><b>HiveTalk</b> hosts the video room; its own privacy terms apply once you are in the room.</li></ul>'
            . '<p>We share your email with nobody else. We may disclose data if the law requires it.</p>'],

        ['How long we keep it', '<p>We keep your sign-up until you ask us to delete it or the project ends. '
            . 'Click and 404 logs are kept only as long as they are useful for running the site.</p>'],

        ['Your choices', '<p>You can ask us to show you what we hold, correct it, delete it, or stop emailing you. '
            . 'Write to <a href="mailto:hello@bitcoinship.show">hello@bitcoinship.show</a> from the address you signed up with, and we will do it. '
            . 'If you are in the EU or UK you also have the right to complain to your data-protection authority.</p>'],

        ['Children', '<p>The show is not aimed at children, and we do not knowingly collect data from anyone under 16.</p>'],

        ['Changes', '<p>If this policy changes we update the date at the top of this page. For a material change we will say so on the front page.</p>'],
    ],
];

require __DIR__ . '/../partials/legal.php';
