<?php
/** /terms-of-service/ - terms of service. Shell and styling live in partials/legal.php. */
$PAGE_SLUG  = 'terms-of-service';
$PAGE_TITLE = 'Terms of Service — Bitcoin Shipshow';
$PAGE_DESC  = 'The rules of Bitcoin Shipshow, the free monthly online Bitcoin meetup and live demo show: joining the room, recordings, tips, and what we do not promise.';

$LEGAL = [
    'h1'      => 'Terms of service',
    'updated' => '7 October 2026',
    'intro'   => 'These terms cover your use of <a href="https://bitcoinship.show/">bitcoinship.show</a>, the email invitation, '
               . 'and the live show and its video room. By signing up or joining, you agree to them. '
               . 'How we handle your data is in the <a href="/privacy-policy/">privacy policy</a>.',
    'sections' => [
        ['What Bitcoin Shipshow is', '<p>A free monthly online Bitcoin meetup: builders demo what they are shipping in Bitcoin, Lightning and Nostr, '
            . 'streamed live and published afterwards. It is a community project, provided as it is.</p>'],

        ['Signing up and the invitation', '<p>Sign-up is free and needs only an email address. The invitation, calendar file and room link are meant for you personally. '
            . 'Please do not post the room link publicly. We may close the room to a link that has been shared widely, and we may refuse or remove a sign-up.</p>'],

        ['Joining the room', '<p>Be decent. No harassment, hate, spam, scams or illegal content, and no disrupting the show or the people presenting. '
            . 'Hosts can mute, remove or block anyone in the room at their discretion.</p>'],

        ['Recording and publishing', '<p>The show is livestreamed and recordings are published, for example on YouTube and social channels. '
            . 'If you speak, share your screen or turn on your camera in the room, you may appear in the recording. '
            . 'Keep your camera and microphone off if you do not want that.</p>'],

        ['Demos, views and no advice', '<p>Presenters and guests speak for themselves, and their views and products are not endorsed by Bitcoin Shipshow. '
            . 'Nothing on the show or the site is financial, investment, legal or tax advice, and a demo is not a recommendation to buy, use or trust anything. '
            . 'Do your own research and never send funds on the strength of a live demo.</p>'],

        ['Tips and zaps', '<p>The show runs on value-for-value. Tips and zaps are voluntary gifts, are not a purchase, and are not refundable.</p>'],

        ['Brand and content', '<p>The Bitcoin Shipshow name, logo and artwork belong to the project. The brand kit at <a href="/assets/">bitcoinship.show/assets</a> '
            . 'says how they may be used to promote the show. Other logos and names belong to their owners. '
            . 'What you present or say remains yours, and by presenting you let us publish it as part of the recording.</p>'],

        ['No warranty, limited liability', '<p>The site, the invitations and the show are provided "as is", without promises that they will always be available, on time or error-free. '
            . 'Dates and lineups can change. To the extent the law allows, Bitcoin Shipshow and the people running it are not liable for any loss arising from your use of the site or the show. '
            . 'Nothing here limits rights you have by law that cannot be limited.</p>'],

        ['Links to other sites', '<p>The site links to YouTube, Nostr clients, the video room and partner sites. We do not control them, and their own terms apply.</p>'],

        ['Changes and contact', '<p>We may update these terms and will change the date at the top when we do. '
            . 'Questions: <a href="mailto:hello@bitcoinship.show">hello@bitcoinship.show</a>.</p>'],
    ],
];

require __DIR__ . '/../partials/legal.php';
