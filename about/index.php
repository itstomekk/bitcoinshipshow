<?php
/**
 * /about/ — the public "what is this?" page.
 *
 * Joins the shared head (partials/head.php) and the shared design (css/corsair.css)
 * so it renders exactly like the home page while staying fully public: no gated
 * sections, no scroll lock, every word crawlable.
 *
 * It is the SEO landing page. The home page keeps everything past the hero behind
 * the email gate, so anything that must be indexed lives here: the schedule, the
 * format, the FAQ (also emitted as FAQPage JSON-LD) and the links out.
 *
 * It is ALSO the only page with a Q&A block. Do not copy the FAQ anywhere else —
 * questions live here, and the $FAQ array below is their single source.
 *
 * Section order: hero, the show, brought-to-you-by, what gets on stage, fresh
 * releases, FAQ, gallery, how to get in + meetups. The partners block sits third,
 * directly under The show, matching the home page.
 */
require __DIR__ . '/../partials/boot.php';

$PAGE_SLUG  = 'about';
$NPUB       = 'npub1mtjn0yp705hvgszssf2ddjpzk3zzjzgvu58dwwpkxqud0hde6fxss7cwyw'; // the official Shipshow Nostr profile
$PAGE_TITLE = "Bitcoin Shipshow — Bitcoin Meetup, Community Call & Live Demos";
$PAGE_DESC  = "A free monthly online Bitcoin meetup: a pirates' community call where builders demo fresh Bitcoin, Lightning and Nostr releases. Live demos, not pitches.";

/* a purpose-built 1200x630 card for this page (same house art, different type) */
$PAGE_OG_IMAGE     = 'https://bitcoinship.show/img/og-shipshow-about.jpg';
$PAGE_OG_IMAGE_ALT = "Bitcoin Shipshow — a monthly online Bitcoin meetup and pirates' community call where builders demo fresh Bitcoin, Lightning and Nostr releases. First Tuesday of every month, 13:00 ET / 19:00 CET.";

/* ---------------------------------------------------------------- the FAQ
   Single source: rendered visibly AND as FAQPage structured data. Questions are
   written the way people search them. This block lives ONLY on this page.
   -------------------------------------------------------------------------- */
$FAQ = [
    [
        'q' => 'What is Bitcoin Shipshow?',
        'a' => "Bitcoin Shipshow is a monthly online Bitcoin meetup with a live demo show in the middle of it — part TV show, part community call. Every first Tuesday, builders from the Bitcoin, Lightning and Nostr ecosystems demo what they have actually shipped. Live demos, not pitches, and no price talk.",
    ],
    [
        'q' => 'Is Bitcoin Shipshow a Bitcoin meetup I can join online?',
        'a' => "Yes. It is an online-only Bitcoin meetup: no city, no venue, no door fee, no minimum. You sign up with an email, the invitation and the room link arrive by email, and you join from wherever you are — one person on a laptop counts.",
    ],
    [
        'q' => 'How is it different from a local Bitcoin meetup?',
        'a' => "Same instinct, no geography. A local meetup is whoever can get to the room; this is whoever can open a browser, so the room mixes builders from several continents in one call. Local meetups and hubs are welcome to bring their members along, and a replay makes an easy programme for a night you cannot fill.",
    ],
    [
        'q' => 'When is the next Bitcoin Shipshow?',
        'a' => "The first Tuesday of every month, 13:00 ET / 19:00 CET. The same slot every month, for about ninety minutes on the stream and half an hour more with the room open off the record. The countdown on the live page converts the start time to your own clock.",
    ],
    [
        'q' => 'How is it different from a Bitcoin conference or a Bitcoin podcast?',
        'a' => "There is no stage, no ticket, no booth and no slideware — a working demo on camera, then questions from the room. It is live and interactive rather than pre-recorded, and it is free. Every expedition is published afterwards as full episodes and clips, so the show also works as a rolling archive of fresh Bitcoin releases.",
    ],
    [
        'q' => 'Do you run a Bitcoin demo day or a pitch night?',
        'a' => "No. A demo day usually means decks, judging and investors in the room; this is the opposite. Three to five working demos, no judging panel, no pitch deck, no funding round attached. If someone wants feedback, they get it from the people actually building alongside them.",
    ],
    [
        'q' => 'How do I demo my project on the show?',
        'a' => "Leave your email on the front page and say what you are building when you reply, or just turn up to a show and introduce yourself in the room. We book three to five demos per expedition. Working demos only: if it runs, even roughly, it is ready.",
    ],
    [
        'q' => 'Do I need a finished or well-known product to demo?',
        'a' => "No. Early and unknown is exactly what we want. Each episode deliberately mixes first-time builders with one well-known project showing a fresh release, and there is a short slot for someone whose thing is still too early to demo properly.",
    ],
    [
        'q' => 'Where do I find fresh Bitcoin releases every month?',
        'a' => "Here, and it is the whole point of the show. Every expedition puts at least one fresh release on stage alongside work nobody has seen yet, and each episode is published afterwards on YouTube and announced on Nostr and X. Follow #bitcoinshipshow, or leave your email and the invite finds you on the day.",
    ],
    [
        'q' => 'What kind of projects get on stage?',
        'a' => "Anything interesting being built on Bitcoin, Lightning or Nostr: wallets, hubs, signers, Nostr clients and relays, music and media apps, Bitcoin films and art, education projects, meetups and circular economies, dev tools and open source. Beta and alpha are welcome.",
    ],
    [
        'q' => 'Is Bitcoin Shipshow free to watch?',
        'a' => "Yes. Watching is free, demoing is free and no sponsorship is required to get a slot. The show runs on value-for-value: tips and zaps on the stream go to the people building, and hosts do not take a cut of them.",
    ],
    [
        'q' => 'Who runs Bitcoin Shipshow, and can I sponsor it?',
        'a' => "It is independent and community-run: the hosts curate every slot and there is no media company behind it. A handful of aligned partners support each season, and one demo slot every month is reserved for a project with no partner behind it — so a sponsor can never buy the whole room. The partner one-pager is linked on this page.",
    ],
    [
        'q' => 'Where can I watch past episodes?',
        'a' => "Full episodes and clips are published on our YouTube channel and announced on Nostr and X, so the show doubles as an archive of fresh releases you can catch up on. Episode one is already up: Pakstr, IndeeHub, Sidecar and Angor.",
    ],
    [
        'q' => "What is the Pirates' Community Call?",
        'a' => "It is our tagline for what the show is: a community call that grew out of the Bitcoin community calls the hosts ran for three years, with the pirate flavour of a boat folded out of a newspaper. The public stream ends after about ninety minutes and the room stays open, off the record, for the real talk. Say pirate around here and this is what we mean — no skulls, no menace, just the crew.",
    ],
];

$faqLd = [
    '@context'   => 'https://' . 'schema' . '.org',
    '@type'      => 'FAQPage',
    'mainEntity' => [],
];
foreach ($FAQ as $item) {
    $faqLd['mainEntity'][] = [
        '@type'          => 'Question',
        'name'           => $item['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
    ];
}

$orgLd = [
    '@context' => 'https://' . 'schema' . '.org',
    '@type'    => 'Organization',
    'name'     => 'Bitcoin Shipshow',
    'url'      => 'https://bitcoinship.show/',
    'logo'     => 'https://bitcoinship.show/icon-512.png',
    'description' => "A monthly online Bitcoin meetup and live demo show: builders demo what they are shipping in Bitcoin, Lightning and Nostr.",
    'foundingDate' => '2023',
    'sameAs'   => [
        'https://x.com/bitcoinshipshow',
        'https://www.youtube.com/@BitcoinShipshow',
        'https://njump.me/' . $NPUB,
    ],
];

$crumbLd = [
    '@context'        => 'https://' . 'schema' . '.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Bitcoin Shipshow', 'item' => 'https://bitcoinship.show/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'What is Bitcoin Shipshow?', 'item' => 'https://bitcoinship.show/about/'],
    ],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
<meta name="keywords" content="bitcoin meetup, bitcoin meetup online, online bitcoin meetup, bitcoin meetups online, bitcoin community call, community call bitcoin, bitcoin pirates, pirates community call, bitcoin demo show, bitcoin demo day, bitcoin builders show, bitcoin builder demos, bitcoin project demos, fresh bitcoin releases, new bitcoin releases, new bitcoin projects, bitcoin project launch, bitcoin product demo, live bitcoin demos, bitcoin live show, bitcoin online community, bitcoin lightning demos, nostr demos, bitcoin dev meetup, monthly bitcoin meetup, bitcoin event online, value for value, zaps">
<meta name="author" content="Bitcoin Shipshow">
<style>
  /* this page's own additions — everything else comes from base.css + corsair.css */
  .design-corsair .s-releases {
    background: var(--co-parch); background-image: var(--co-foxing); color: var(--co-ink);
  }
  .design-corsair .s-releases .h-mark { background: var(--co-blood); color: var(--co-parch); box-shadow: 10px 10px 0 0 var(--co-gold); }
  .design-corsair .s-releases .tag { border-color: var(--co-ink); outline-color: var(--co-gold); color: var(--co-ink); }
  .design-corsair .s-releases .tag:nth-child(3n+2) { background: var(--co-ink); color: var(--co-parch); }

  .design-corsair .s-faq {
    background: var(--co-parch-dk); background-image: var(--co-foxing); color: var(--co-ink);
  }
  .design-corsair .s-faq .h-mark { background: var(--co-ink); color: var(--co-gold); box-shadow: 10px 10px 0 0 var(--co-blood); }

  .design-corsair .faq { display: grid; gap: 0; margin-top: 26px; max-width: 940px; }
  .design-corsair .faq-item {
    background: var(--co-parch); background-image: var(--co-foxing); color: var(--co-ink);
    border: var(--co-rule); border-bottom-width: 0; padding: 18px 20px 20px;
  }
  .design-corsair .faq-item:last-child { border-bottom-width: 4px; }
  .design-corsair .faq-q {
    font-family: "Pirata One", serif; font-weight: 400; font-size: clamp(21px, 2.6vw, 27px);
    line-height: 1.06; margin-bottom: 8px;
  }
  .design-corsair .faq-a { font-size: 16px; max-width: 74ch; }

  /* the brought-to-you-by panel, same construction as the home page */
  .design-corsair .patrons-panel {
    max-width: 720px; margin: 0 auto; position: relative; z-index: 4;
    background: var(--co-ink); border: 4px solid var(--co-gold);
    box-shadow: 14px 14px 0 0 var(--co-blood);
    padding: 34px 32px 30px; text-align: center;
  }
  .design-corsair .patrons-cap {
    display: inline-block; font-family: "Pirata One", Georgia, serif;
    letter-spacing: .1em; text-transform: uppercase; font-size: 22px; color: var(--co-gold);
    margin-bottom: 28px; padding-bottom: 10px; border-bottom: 3px double var(--co-gold);
  }
  .design-corsair .patrons-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 30px 54px; }
  .design-corsair .patron { text-decoration: none; text-align: center; line-height: 1.05; display: flex; flex-direction: column; align-items: center; gap: 10px; }
  .design-corsair .patron-tag {
    display: inline-block; font-family: "IM Fell English", Georgia, serif; font-style: italic;
    font-size: 12.5px; color: var(--co-ink); background: var(--co-gold);
    letter-spacing: .06em; padding: 3px 10px;
  }
  .design-corsair .patron-img { display: block; margin: 0 auto; opacity: 1; transition: opacity .14s, transform .14s; filter: drop-shadow(0 3px 0 rgba(0,0,0,.35)); }
  .design-corsair .patron-img-main { height: 72px; }
  .design-corsair .patron-sup .patron-img { height: 32px; opacity: .95; }
  .design-corsair .patron:hover .patron-img { opacity: 1; transform: translateY(-3px); }
  .design-corsair .patrons-sub { margin-top: 24px; padding-top: 22px; border-top: 1px dashed rgba(217,174,60,.4); }

  .design-corsair .explore { margin-top: 26px; font-size: 15.5px; line-height: 1.9; max-width: 74ch; }
  .design-corsair .explore a { color: var(--co-gold); text-decoration: none; border-bottom: 2px dashed var(--co-gold); }
  .design-corsair .explore a:hover { color: var(--co-parch); border-bottom-style: solid; }

  .design-corsair .hero-links { margin-top: 24px; font-size: 15px; }
  .design-corsair .hero-links a { color: var(--co-gold); text-decoration: none; border-bottom: 2px dashed var(--co-gold); }
  .design-corsair .hero-links a:hover { color: var(--co-parch); border-bottom-style: solid; }

  /* a hairline where a paper section meets another paper section */
  .design-corsair .s-stage, .design-corsair .s-releases, .design-corsair .s-faq { border-top: 4px solid var(--co-ink); }
</style>
<script type="application/ld+json"><?= json_encode($faqLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/ld+json"><?= json_encode($orgLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/ld+json"><?= json_encode($crumbLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
</head>

<body class="page-about design-<?= e($DESIGN) ?>">

<div class="fx" aria-hidden="true"></div>

<!-- ═══════════ 1 · hero — what this is, and the way in ═══════════ -->
<section class="s s-hero" id="top">
  <div class="wrap">

    <div class="brand">
      <img class="brand-mark" src="<?= e(asset('img/shipshow-boat.png')) ?>"
           srcset="<?= e(asset('img/shipshow-boat.png')) ?> 1x, <?= e(asset('img/shipshow-boat@2x.png')) ?> 2x"
           width="433" height="258" fetchpriority="high"
           alt="Bitcoin Shipshow — a paper boat folded from the front page of The Times, 3 January 2009">
    </div>

    <p class="eyebrow">Monthly · Live · Pirates' Community Call</p>

    <h1 class="title"><span class="title-a">Bitcoin</span><span class="title-b">Shipshow</span><span class="sr-only"> — the monthly online Bitcoin meetup, community call and live demo show</span></h1>

    <p class="lede">Part TV show, part team call. Every month, builders demo what they are actually
      shipping in Bitcoin, Lightning &amp; Nostr — <b>live demos, not pitches</b>. Then the stream
      ends and the real talk starts.</p>

    <p class="stream-cta">Free online Bitcoin meetup · first Tuesday of every month ·
      13:00 ET · 19:00 CET<br><a href="/live/">See the next expedition →</a></p>

    <form class="signup" id="signup" data-signup method="POST" action="/gate/gate.php">
      <input type="hidden" name="slug" value="shipshow">
      <input type="hidden" name="consent" value="1">
      <input type="hidden" name="design" value="<?= e($DESIGN) ?>">
<?= utm_fields() ?>
      <input class="hp" type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true">
      <p class="signup-head">Give your email, <span class="mark">get the invite</span></p>
      <div class="signup-row">
        <input class="signup-input" type="email" name="email" required
               placeholder="you@wherever.com" autocomplete="email" aria-label="Your email address">
        <button class="signup-btn" type="submit">Send it ⚡</button>
      </div>
      <p class="signup-note">Free, monthly, one email per show. The room link is never posted on the
        public site — it arrives by email. Unsubscribe any time.</p>
      <p class="formerror" data-error hidden></p>
    </form>

    <div class="sent" data-sent hidden>
      <h3 class="sent-head" data-sent="head">Check your <em>inbox</em></h3>
      <p class="sent-sub" data-sent="sub">Your call invitation is on its way by email.</p>
      <p class="sent-when" data-sent="when"></p>
    </div>

    <p class="hero-links">New here? <a href="#faq">Straight answers</a> ·
      <a href="#releases">Fresh releases</a> · <a href="#how">How to get in</a> ·
      <a href="#stage">What gets on stage</a> · <a href="/sponsors/">Partner one-pager</a></p>

  </div>

  <div class="ticker" aria-hidden="true">
    <img class="ticker-logo" src="<?= e(asset('img/shipshow-boat.png')) ?>" alt="">
    <div class="ticker-track">
      <a class="ticker-item" href="https://www.youtube.com/@BitcoinShipshow" target="_blank" rel="noopener">Watch on YouTube</a><span class="ticker-item">Monthly online Bitcoin meetup</span><span class="ticker-item">Community call</span><span class="ticker-item">Fresh releases</span><span class="ticker-item">Live demos, not pitches</span><a class="ticker-item" href="https://www.youtube.com/@BitcoinShipshow" target="_blank" rel="noopener">Watch on YouTube</a><span class="ticker-item">Monthly online Bitcoin meetup</span><span class="ticker-item">Community call</span><span class="ticker-item">Fresh releases</span><span class="ticker-item">Live demos, not pitches</span>
    </div>
  </div>
</section>

<!-- ═══════════ 2 · the show — the ship's manifest, on parchment ═══════════ -->
<section class="s s-show" id="show">
  <div class="wrap">
    <h2 class="h"><span class="h-mark">The show</span></h2>

    <ul class="stats">
      <li class="stat"><span class="stat-num">3–5</span><span class="stat-lbl">live project demos per expedition</span></li>
      <li class="stat"><span class="stat-num">~90</span><span class="stat-lbl">min public show, streamed &amp; published</span></li>
      <li class="stat"><span class="stat-num">~30</span><span class="stat-lbl">min private off-record room</span></li>
      <li class="stat"><span class="stat-num">3</span><span class="stat-lbl">years of community-call legacy</span></li>
    </ul>

    <p class="prose">Bitcoin Shipshow is an <strong>online Bitcoin meetup</strong> that behaves like
      a demo show. It is about building and creating — not price, not punditry. Each expedition
      mixes early, unknown builders with one well-known project showing a <strong>fresh release</strong>,
      plus a short slot for someone whose thing is still too early to demo properly.</p>

    <p class="prose">Every expedition becomes quality video content: full episodes and clips,
      published across platforms, so the show doubles as a rolling archive of what just shipped in
      Bitcoin, Lightning and Nostr. The last half hour is the <strong>community call</strong> proper —
      off the record, no recording, no clips, just the room.</p>

    <ul class="platforms">
      <li class="chip"><a href="https://x.com/bitcoinshipshow" data-goatcounter-click="social-x">@bitcoinshipshow</a></li>
      <li class="chip"><a href="https://njump.me/<?= e($NPUB) ?>" data-goatcounter-click="social-nostr" title="<?= e($NPUB) ?>">nostr</a></li>
      <li class="chip"><a href="https://www.youtube.com/@BitcoinShipshow" data-goatcounter-click="social-youtube">YouTube</a></li>
    </ul>
  </div>
</section>

<!-- ═══════════ 3 · brought to you by — directly under the show, as on the home page ═══════════ -->
<section class="s s-patrons" id="partners" aria-label="Brought to you by">
  <div class="wrap">
    <h2 class="h"><span class="h-mark">Brought to you by</span></h2>
    <p class="prose">The show is independent and community-run — supported by a handful of aligned
      partners per season: companies, brands, YouTubers and creators who want to stand with the
      people building. Hosts curate every slot, and one demo slot every month is reserved for a
      project with no partner behind it.</p>

    <div class="patrons-panel">
      <p class="patrons-cap">⚓ Brought to you by</p>
      <div class="patrons-row">
        <a class="patron patron-main" href="https://www.freedomtech.co?utm_source=bitcoinshipshow&amp;utm_medium=site&amp;utm_campaign=partner-freedomtech" data-goatcounter-click="partner-freedomtech" target="_blank" rel="noopener"><img class="patron-img patron-img-main" src="<?= e(asset('img/freedomtech-logo.png')) ?>" alt="Freedom Tech - main sponsor"><span class="patron-tag">main sponsor</span></a>
      </div>
      <div class="patrons-row patrons-sub">
        <a class="patron patron-sup" href="https://thebitcoindistrict.com?utm_source=bitcoinshipshow&amp;utm_medium=site&amp;utm_campaign=partner-bitcoin-district" data-goatcounter-click="partner-bitcoin-district" target="_blank" rel="noopener"><img class="patron-img" src="<?= e(asset('img/bitcoin-district-logo.png')) ?>" alt="Bitcoin District"></a>
        <a class="patron patron-sup" href="https://hivetalk.org?utm_source=bitcoinshipshow&amp;utm_medium=site&amp;utm_campaign=partner-hivetalk" data-goatcounter-click="partner-hivetalk" target="_blank" rel="noopener"><img class="patron-img" src="<?= e(asset('img/hivetalk-logo-dark.png')) ?>" alt="HiveTalk"></a>
      </div>
    </div>

    <div class="cta">
      <p class="cta-title">Want your name on the show?</p>
      <p class="cta-body">Founding partner seasons are open —
        <a href="/sponsors/">read the partner one-pager →</a></p>
    </div>
  </div>
</section>

<!-- ═══════════ 4 · what gets on stage ═══════════ -->
<section class="s s-stage" id="stage">
  <div class="wrap">
    <h2 class="h"><span class="h-mark">What gets on stage</span></h2>
    <p class="prose">Anything interesting being built on Bitcoin, Lightning, or Nostr — working
      demos only, beta and alpha welcome.</p>

    <ul class="tags">
      <li class="tag">⚡ Lightning wallets &amp; hubs</li>
      <li class="tag">🟣 Nostr clients &amp; relays</li>
      <li class="tag">🔐 Hardware &amp; signers</li>
      <li class="tag">👛 Self-custody &amp; wallets</li>
      <li class="tag">💸 Payments &amp; zaps</li>
      <li class="tag">🔌 NWC &amp; Lightning apps</li>
      <li class="tag">🎵 Music &amp; media apps</li>
      <li class="tag">🎬 Bitcoin films &amp; art</li>
      <li class="tag">🎮 Gaming</li>
      <li class="tag">🎓 Education</li>
      <li class="tag">🧠 AI on Bitcoin</li>
      <li class="tag">🗣️ Social &amp; messaging</li>
      <li class="tag">📻 Podcasts &amp; media</li>
      <li class="tag">🪙 Ecash &amp; Cashu</li>
      <li class="tag">🏗️ Infrastructure &amp; dev tools</li>
      <li class="tag">🖥️ Self-hosted &amp; Umbrel</li>
      <li class="tag">🛒 Marketplaces &amp; merchants</li>
      <li class="tag">📰 News &amp; content</li>
      <li class="tag">⛏️ Mining &amp; nodes</li>
      <li class="tag">🔏 Privacy &amp; self-hosting</li>
      <li class="tag">🌍 Meetups, hubs &amp; circular economies</li>
      <li class="tag">🤝 Community &amp; conferences</li>
      <li class="tag">🪙 Stablecoins &amp; L2</li>
      <li class="tag">🔗 Protocol &amp; core dev</li>
      <li class="tag">🔓 Open source (FOSS)</li>
      <li class="tag">💳 Merchant &amp; POS tools</li>
      <li class="tag">💰 Tipping &amp; value-for-value</li>
      <li class="tag">📺 Streaming &amp; content monetization</li>
    </ul>
  </div>
</section>

<!-- ═══════════ 5 · fresh releases — the monthly release slot, on paper ═══════════ -->
<section class="s s-releases" id="releases">
  <div class="wrap">
    <h2 class="h"><span class="h-mark">Fresh releases, every month</span></h2>

    <p class="prose">Every expedition puts at least one well-known project on stage with a
      <strong>fresh release</strong>, and the rest of the line-up is whatever just shipped: a new
      wallet build, a new Nostr client, a signer that finally works, a Bitcoin film that just got
      finished, a hub opening its circular economy. New Bitcoin projects and new versions of
      familiar ones get the same slot and the same time.</p>

    <p class="prose">If it runs, it is ready. Alpha and beta are welcome, and so is a project nobody
      has heard of yet — one slot every month is deliberately kept for exactly that. What we do not
      take is slideware: the demo is the real thing running on the builder's screen, in front of a
      room that asks questions.</p>

    <ul class="tags">
      <li class="tag">A new app, or a first public demo</li>
      <li class="tag">A new version of something people already use</li>
      <li class="tag">Protocol, tooling or open-source releases</li>
      <li class="tag">Hardware, signers &amp; new revisions</li>
      <li class="tag">Bitcoin films, music &amp; art</li>
      <li class="tag">A meetup, hub or circular economy launching</li>
    </ul>

    <p class="prose">Catching up is easy: every release that gets demoed is published afterwards as
      an episode or a clip on
      <a href="https://www.youtube.com/@BitcoinShipshow" target="_blank" rel="noopener" style="border-bottom:2px dashed var(--co-ink)">YouTube</a>
      and announced on Nostr and X under
      <a href="https://x.com/bitcoinshipshow" target="_blank" rel="noopener" style="border-bottom:2px dashed var(--co-ink)">#bitcoinshipshow</a>.</p>
  </div>
</section>

<!-- ═══════════ 6 · questions people actually ask — the ONLY Q&A block on the site ═══════════ -->
<section class="s s-faq" id="faq">
  <div class="wrap">
    <h2 class="h"><span class="h-mark">Questions, answered</span></h2>
    <p class="prose">The short version of everything above — what Bitcoin Shipshow is, when it runs,
      who it is for and how to get your project on stage.</p>

    <div class="faq">
<?php foreach ($FAQ as $item): ?>
      <div class="faq-item">
        <h3 class="faq-q"><?= e($item['q']) ?></h3>
        <p class="faq-a"><?= e($item['a']) ?></p>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════ 7 · what it looks like ═══════════ -->
<section class="s s-gallery" id="gallery">
  <div class="wrap">
    <h2 class="h"><span class="h-mark">What it looks like</span></h2>

    <p class="gallery-cap">Expedition replays — newest first, then scenes from the community calls we ran before.</p>

    <ul class="gallery">
      <li class="shot shot-ep"><a class="shot-link" href="https://www.youtube.com/watch?v=wkG0b-86teM" data-goatcounter-click="episode-e01-replay" target="_blank" rel="noopener" title="Bitcoin Shipshow E01 replay on YouTube"><span class="shot-media"><img class="shot-img" loading="lazy" src="<?= e(asset('img/episodes/e01.jpg')) ?>" alt="Bitcoin Shipshow E01 — the replay"><span class="shot-play" aria-hidden="true">▶</span></span><span class="shot-cap">E01 · Tue 6 Oct 2026 — Pakstr, IndeeHub, Sidecar, Angor · <b>watch the replay</b></span></a></li>
      <li class="shot"><img class="shot-img" loading="lazy" src="<?= e(asset('img/call-countdown.jpg')) ?>" alt="Pre-show countdown with live mempool blocks and guest lineup"><span class="shot-cap">pre-show countdown — guest lineup, live on-chain data</span></li>
      <li class="shot"><img class="shot-img" loading="lazy" src="<?= e(asset('img/call-demo-bitrefill.jpg')) ?>" alt="Live product demo with chat and the full participant grid"><span class="shot-cap">live demo, chat + the whole room on camera</span></li>
      <li class="shot"><img class="shot-img" loading="lazy" src="<?= e(asset('img/call-zaps-35viewers.jpg')) ?>" alt="Live stream with the zap overlay and top zappers on screen"><span class="shot-cap">zaps flowing in live — value-for-value</span></li>
      <li class="shot"><img class="shot-img" loading="lazy" src="<?= e(asset('img/call-wallet-zappers.jpg')) ?>" alt="Wallet walkthrough with the top zappers sidebar"><span class="shot-cap">wallet walkthrough, top zappers on screen</span></li>
      <li class="shot"><img class="shot-img" loading="lazy" src="<?= e(asset('img/call-music-demo.jpg')) ?>" alt="Music app demo during a live call"><span class="shot-cap">not just code — music, art &amp; media demos</span></li>
      <li class="shot"><img class="shot-img" loading="lazy" src="<?= e(asset('img/call-community-grid.jpg')) ?>" alt="Grid of community call participants"><span class="shot-cap">the room: builders, founders, regulars</span></li>
    </ul>
  </div>
</section>

<!-- ═══════════ 8 · how to get in, and how a meetup brings its crew ═══════════ -->
<section class="s s-join" id="how">
  <div class="wrap">
    <h2 class="h"><span class="h-mark">How to get in</span></h2>

    <ol class="steps">
      <li class="step">
        <span class="step-num">01</span>
        <h3 class="step-title">Watch</h3>
        <p class="step-body">The stream is public and published everywhere. Follow
          <a href="https://x.com/bitcoinshipshow" style="border-bottom:2px dashed var(--co-ink)">#bitcoinshipshow</a>
          and tune in monthly on <a href="https://www.youtube.com/@BitcoinShipshow" target="_blank" rel="noopener" style="border-bottom:2px dashed var(--co-ink)">YouTube →</a></p>
      </li>
      <li class="step">
        <span class="step-num">02</span>
        <h3 class="step-title">Join the room</h3>
        <p class="step-body">Leave your email on the <a href="/" style="border-bottom:2px dashed var(--co-ink)">front page</a>
          or in the form above. The invitation and the room link arrive by email — the room link is
          never posted on the public site.</p>
      </li>
      <li class="step">
        <span class="step-num">03</span>
        <h3 class="step-title">Demo</h3>
        <p class="step-body">Got something working — even rough? Working demos only, no slideware.
          Early and unknown is exactly what we want.</p>
      </li>
    </ol>

    <p class="prose">Grassroots by design: no corporate machine behind this. Hosts spread it through
      their own networks, projects bring their communities, and one demo slot every month is reserved
      for a project with no partner behind it.</p>

    <h2 class="h" id="meetups"><span class="h-mark">Bringing a meetup?</span></h2>

    <p class="prose"><strong>Bitcoin meetups, hubs and communities do not need a venue to host us.</strong>
      Bring your members to an expedition, or use a replay as the programme for one of your own
      nights — the <a href="/assets/" style="border-bottom:2px dashed var(--co-gold)">brand kit</a> has
      the posters, logos and copy blocks to run with it. A local meetup, a hub, a circular economy or
      a study group all count: the show is the online Bitcoin meetup that travels, and your crew is
      welcome in the room.</p>

    <p class="explore">
      Keep reading:
      <a href="/">the front page &amp; email invite</a> ·
      <a href="/live/">the next expedition</a> ·
      <a href="/assets/">brand kit, logos and poster downloads</a> ·
      <a href="/game/">the mini-game</a> ·
      <a href="https://www.youtube.com/@BitcoinShipshow">episode replays on YouTube</a> ·
      <a href="https://njump.me/<?= e($NPUB) ?>">Nostr</a> ·
      <a href="https://x.com/bitcoinshipshow">X</a>
    </p>

    <p class="pagefoot">BITCOIN SHIPSHOW · monthly online Bitcoin meetup · live demos, not pitches ·
      <a href="https://x.com/bitcoinshipshow">@bitcoinshipshow</a> · #bitcoinshipshow ·
      <a href="/privacy-policy/">Privacy</a> · <a href="/terms-of-service/">Terms</a></p>
  </div>
</section>

<script src="<?= e(asset('js/shipshow.js')) ?>"></script>
<script data-goatcounter="https://bitcoinshipshow.goatcounter.com/count" async src="//gc.zgo.at/count.js"></script>
</body>
</html>
