<?php
require __DIR__ . '/partials/boot.php';

$PAGE_SLUG  = 'home';
$PAGE_TITLE = "Bitcoin Shipshow — show us what you're building";
$PAGE_DESC  = "Bitcoin Shipshow — a monthly live show where builders demo what they're shipping in Bitcoin, Lightning and Nostr. Give your email, get the invite.";
?><!DOCTYPE html>
<html lang="en" class="locked">
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
</head>

<!--
  NOTHING IN THIS FILE IS DESIGN-SPECIFIC.
  Every class here is a semantic hook documented in DESIGN-SYSTEM.md.
  If you find yourself wanting to add a class like "big-red-box", stop:
  style the existing hook from inside your design's CSS file instead.
-->
<body class="page-home design-<?= e($DESIGN) ?>">

<!-- decoration layer: designs hang grain / halftone / texture overlays here -->
<div class="fx" aria-hidden="true"></div>

<!-- ═══════════ 1 · hero — the only thing an unknown visitor sees ═══════════ -->
<section class="s s-hero" id="hero">
  <div class="wrap">

    <div class="brand">
      <img class="brand-mark" src="<?= e(asset('img/shipshow-boat.png')) ?>"
           srcset="<?= e(asset('img/shipshow-boat.png')) ?> 1x, <?= e(asset('img/shipshow-boat@2x.png')) ?> 2x"
           width="433" height="258" fetchpriority="high"
           alt="A paper boat folded from the front page of The Times, 3 January 2009 — “Chancellor on brink of second bailout for banks”">
    </div>

    <p class="eyebrow">Monthly · Live · Value-for-value</p>

    <h1 class="title"><span class="title-a">Bitcoin</span><span class="title-b">Shipshow</span></h1>

    <div class="hero-right">
    <div class="countdown" data-countdown>
      <p class="cd-eyebrow" data-cd="label">Next expedition in</p>
      <ol class="cd-list" data-cd="list">
        <li class="cd-unit"><span class="cd-num" data-cd="d">--</span><span class="cd-lbl">days</span></li>
        <li class="cd-unit"><span class="cd-num" data-cd="h">--</span><span class="cd-lbl">hours</span></li>
        <li class="cd-unit"><span class="cd-num" data-cd="m">--</span><span class="cd-lbl">min</span></li>
        <li class="cd-unit"><span class="cd-num" data-cd="s">--</span><span class="cd-lbl">sec</span></li>
      </ol>
      <p class="cd-when" data-cd="when">E02 · Tue 3 Nov · 13:00 ET · 19:00 CET</p>
    </div>
    <div class="hero-guests">
      <span class="hero-guests-cap">E02 guests</span>
      <span class="hero-guests-row">
        <a href="https://lacrypta.ar?utm_source=bitcoinshipshow&amp;utm_medium=site&amp;utm_campaign=guest-lacrypta" data-goatcounter-click="guest-lacrypta" target="_blank" rel="noopener" title="La Crypta"><img src="<?= e(asset('img/guests/lacrypta-lockup.svg')) ?>" alt="La Crypta"></a>
        <a href="https://antidotebtc.com?utm_source=bitcoinshipshow&amp;utm_medium=site&amp;utm_campaign=guest-antidote" data-goatcounter-click="guest-antidote" target="_blank" rel="noopener" title="Antidote"><img src="<?= e(asset('img/guests/antidote-lockup.png')) ?>" alt="Antidote"></a>
        <a href="https://brainstorm.world?utm_source=bitcoinshipshow&amp;utm_medium=site&amp;utm_campaign=guest-brainstorm" data-goatcounter-click="guest-brainstorm" target="_blank" rel="noopener" title="Brainstorm"><img src="<?= e(asset('img/guests/brainstorm-lockup.svg')) ?>" alt="Brainstorm"></a>
        <span class="guest-tba" title="Open dock: an open slot to show what you are building">Open dock</span>
      </span>
    </div>
    <a class="hero-thumb" href="https://www.youtube.com/watch?v=_b5IVyO-AJw" data-goatcounter-click="e02-stream-thumb" target="_blank" rel="noopener" title="Expedition 002 on YouTube"><img src="<?= e(asset('img/episodes/e02-stream-thumb.jpg')) ?>" width="640" height="336" loading="lazy" alt="Bitcoin Shipshow Expedition 002 stream: pirates building a ship, first Tuesday of every month"></a>
    </div>

    <form class="signup" id="signup" data-signup method="POST" action="/gate/gate.php">
      <input type="hidden" name="slug" value="shipshow">
      <input type="hidden" name="consent" value="1">
      <input type="hidden" name="design" value="<?= e($DESIGN) ?>">
<?= utm_fields() ?>
      <input class="hp" type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="signup-row">
        <input class="signup-input" type="email" name="email" required
               placeholder="you@wherever.com" autocomplete="email" aria-label="Your email address">
        <button class="signup-btn" type="submit">Get invited ⚡</button>
      </div>
      <p class="formerror" data-error hidden></p>
    </form>

    <!-- the confirmation, filled in from gate.php's JSON reply -->
    <div class="sent" data-sent hidden>
      <h3 class="sent-head" data-sent="head">Invite <em>sent</em></h3>
      <p class="sent-sub" data-sent="sub">Check your inbox for the link to the call.</p>
      <p class="sent-when" data-sent="when"></p>
    </div>

    <p class="stream-cta">New to this? <a href="/about/">What is Bitcoin Shipshow?</a>
      — the monthly online Bitcoin meetup, the format, and the questions people ask.</p>

  </div>

  <!-- the ticker belongs to screen one and shows before any email is given -->
  <div class="ticker" aria-hidden="true">
    <img class="ticker-logo" src="<?= e(asset('img/shipshow-boat.png')) ?>" alt="">
    <div class="ticker-track">
    <a class="ticker-item" href="https://njump.me/npub1mtjn0yp705hvgszssf2ddjpzk3zzjzgvu58dwwpkxqud0hde6fxss7cwyw" target="_blank" rel="noopener">Streamed on Nostr</a><span class="ticker-item">Show us what you're building</span><span class="ticker-item">Off-record afterparty</span><span class="ticker-item">#bitcoinshipshow</span><a class="ticker-item" href="https://njump.me/npub1mtjn0yp705hvgszssf2ddjpzk3zzjzgvu58dwwpkxqud0hde6fxss7cwyw" target="_blank" rel="noopener">Streamed on Nostr</a><span class="ticker-item">Show us what you're building</span><span class="ticker-item">Off-record afterparty</span><span class="ticker-item">#bitcoinshipshow</span>
  </div></div>

    </section>

<!-- ═══════════ 2 · the show — unlocked once an email is given ═══════════ -->
<section class="s s-show" id="show" data-locked>
  <div class="wrap">
    <h2 class="h"><span class="h-mark">The show</span></h2>

    <ul class="stats">
      <li class="stat"><span class="stat-num">3–5</span><span class="stat-lbl">live project demos per expedition</span></li>
      <li class="stat"><span class="stat-num">~90</span><span class="stat-lbl">min public show, streamed &amp; published</span></li>
      <li class="stat"><span class="stat-num">~30</span><span class="stat-lbl">min private off-record room</span></li>
      <li class="stat"><span class="stat-num">3</span><span class="stat-lbl">years of Community Calls legacy</span></li>
    </ul>

    <p class="prose">About building and creating — not price, not punditry. Each expedition mixes early,
      unknown builders with one well-known project showing a fresh release, plus a short slot for
      someone whose thing is still too early to demo. Every expedition becomes quality video content:
      full episodes and clips, published across platforms. The last half hour is off the record —
      no recording, no clips, just the room.</p>

    <ul class="platforms">
      <li class="chip"><a href="https://x.com/bitcoinshipshow" data-goatcounter-click="social-x">@bitcoinshipshow</a></li>
            <li class="chip"><a href="https://njump.me/npub1mtjn0yp705hvgszssf2ddjpzk3zzjzgvu58dwwpkxqud0hde6fxss7cwyw" data-goatcounter-click="social-nostr" title="npub1mtjn0yp705hvgszssf2ddjpzk3zzjzgvu58dwwpkxqud0hde6fxss7cwyw">nostr</a></li>
      <li class="chip"><a href="https://www.youtube.com/@BitcoinShipshow" data-goatcounter-click="social-youtube">YouTube</a></li>
    </ul>
  </div>
</section>

<!-- ═══════════ 2b · brought to you by — directly below the show ═══════════ -->
<section class="s s-patrons" id="patrons" aria-label="Brought to you by">
  <div class="wrap">
    <style>
      .patrons-panel{
        max-width:620px;margin:0 auto;position:relative;z-index:4;
        background:var(--co-ink);border:4px solid var(--co-gold);
        /* the offset plate is white (parchment). It was --co-blood, i.e. the
           blue signal plane, which read as a blue shadow (Tomek 2026-10-07). */
        box-shadow:12px 12px 0 0 var(--co-parch);
        padding:22px 24px 18px;text-align:center;
      }
      .patrons-cap{
        display:inline-block;font-family:"Pirata One",Georgia,serif;
        letter-spacing:.1em;text-transform:uppercase;font-size:19px;color:var(--co-gold);
        margin-bottom:16px;padding-bottom:8px;border-bottom:3px double var(--co-gold);
      }
      .patrons-row{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:20px 44px}
      .patron{text-decoration:none;text-align:center;line-height:1.05;display:flex;flex-direction:column;align-items:center;gap:10px}
      .patron-tag{
        display:inline-block;font-family:"IM Fell English",Georgia,serif;font-style:italic;
        font-size:12.5px;color:var(--co-ink);background:var(--co-gold);
        letter-spacing:.06em;padding:3px 10px;
      }
      .patron-img{display:block;margin:0 auto;opacity:1;transition:opacity .14s,transform .14s;filter:drop-shadow(0 3px 0 rgba(0,0,0,.35))}
      .patron-img-main{height:54px}
      .patron-sup .patron-img{height:26px;opacity:.95}
      .patron:hover .patron-img{opacity:1;transform:translateY(-3px)}
      .patrons-sub{margin-top:15px;padding-top:13px;border-top:1px dashed rgba(217,174,60,.4)}
    </style>

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
  </div>
</section>

<!-- ═══════════ 3 · what gets on stage ═══════════ -->
<section class="s s-stage" id="stage" data-locked>
  <div class="wrap">
    <h2 class="h"><span class="h-mark">What gets on stage</span></h2>
    <p class="prose">Anything interesting being built on Bitcoin, Lightning or Nostr — and across
      freedom tech more broadly. Fresh releases especially welcome. Working demos only; beta and
      alpha welcome.</p>

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
      <li class="tag">🏦 Exchanges &amp; on-ramps</li>
      <li class="tag">🪙 Stablecoins &amp; L2</li>
      <li class="tag">🔗 Protocol &amp; core dev</li>
      <li class="tag">🔓 Open source (FOSS)</li>
      <li class="tag">📶 Relays &amp; messaging</li>
      <li class="tag">💳 Merchant &amp; POS tools</li>
      <li class="tag">💰 Tipping &amp; value-for-value</li>
      <li class="tag">📺 Streaming &amp; content monetization</li>
      <li class="tag">🤖 Bitcoin Core &amp; node runners</li>
      <li class="tag">🧾 Non-profits &amp; funding</li>
      <li class="tag">🔑 Keys, identity &amp; signing</li>
      <li class="tag">🔍 Search &amp; discovery</li>
      <li class="tag">🗺️ Maps &amp; local tools</li>
      <li class="tag">📡 Mesh, radio &amp; off-grid comms</li>
      <li class="tag">🎟️ Ticketing &amp; events</li>
      <li class="tag">🧩 Browser extensions &amp; plugins</li>
      <li class="tag">🎨 Generative art &amp; collectibles</li>
      <li class="tag">🚚 Logistics &amp; supply chains</li>
      <li class="tag">💼 Business &amp; productivity tools</li>
      <li class="tag">🔒 Vaults, savings &amp; inheritance</li>
    </ul>
  </div>
</section>

<!-- ═══════════ 4 · what it looks like ═══════════ -->
<section class="s s-gallery" id="gallery" data-locked>
  <div class="wrap">
    <h2 class="h"><span class="h-mark">What it looks like</span></h2>

    <p class="gallery-cap">Expedition replays — newest first.</p>

    <ul class="gallery">
      <li class="shot shot-ep shot-ep-wide"><a class="shot-link" href="https://www.youtube.com/watch?v=wkG0b-86teM" data-goatcounter-click="episode-e01-replay" target="_blank" rel="noopener"><span class="shot-media"><img class="shot-img" loading="lazy" src="<?= e(asset('img/episodes/e01.jpg')) ?>" alt="Bitcoin Shipshow E01 poster — Pakstr, IndeeHub, Sidecar, Angor"><span class="shot-play" aria-hidden="true">▶</span></span><span class="shot-body"><span class="shot-kicker">Expedition 01 · Tue 6 Oct 2026</span><span class="shot-line">The first call: four guests, four demos, 90 minutes. The replay is up on YouTube.</span><span class="shot-guests"><span class="shot-guest"><img class="shot-guest-img" loading="lazy" src="<?= e(asset('img/guests/pakstr.png')) ?>" alt=""><span>Pakstr</span></span><span class="shot-guest"><img class="shot-guest-img" loading="lazy" src="<?= e(asset('img/guests/indeehub.png')) ?>" alt=""><span>IndeeHub</span></span><span class="shot-guest"><img class="shot-guest-img" loading="lazy" src="<?= e(asset('img/guests/sidecar.svg')) ?>" alt=""><span>Sidecar</span></span><span class="shot-guest"><img class="shot-guest-img" loading="lazy" src="<?= e(asset('img/guests/angor.png')) ?>" alt=""><span>Angor</span></span></span><span class="shot-watch">Watch the replay on YouTube →</span></span></a></li>
    </ul>

    <p class="gallery-cap gallery-cap-2">Scenes from the show, and from the community calls it grew out of.</p>

    <ul class="gallery">
            <li class="shot shot-color"><img class="shot-img" loading="lazy" src="<?= e(asset('img/episodes/e01-live-zap-board.jpg')) ?>" alt="Bitcoin Shipshow E01 live: the Whose Zap Is It Anyway game board with a QR code, the guest strip and the zap chat on the sea backdrop"><span class="shot-cap">E01, live — the Zap Board game in full colour: scan, play, zap</span></li>
<li class="shot"><img class="shot-img" loading="lazy" src="<?= e(asset('img/call-countdown.jpg')) ?>" alt="Pre-show countdown with live mempool blocks and guest lineup"><span class="shot-cap">pre-show countdown — guest lineup, live on-chain data</span></li>
      <li class="shot"><img class="shot-img" loading="lazy" src="<?= e(asset('img/call-demo-bitrefill.jpg')) ?>" alt="Live product demo with chat and full participant grid"><span class="shot-cap">live demo, chat + the whole room on camera</span></li>
      <li class="shot"><img class="shot-img" loading="lazy" src="<?= e(asset('img/call-zaps-35viewers.jpg')) ?>" alt="Live stream with zap overlay and top zappers"><span class="shot-cap">zaps flowing in live — value-for-value</span></li>
      <li class="shot"><img class="shot-img" loading="lazy" src="<?= e(asset('img/call-wallet-zappers.jpg')) ?>" alt="Wallet demo with top zappers sidebar"><span class="shot-cap">wallet walkthrough, top zappers on screen</span></li>
      <li class="shot"><img class="shot-img" loading="lazy" src="<?= e(asset('img/call-music-demo.jpg')) ?>" alt="Music app demo during a live call"><span class="shot-cap">not just code — music, art &amp; media demos</span></li>
      <li class="shot"><img class="shot-img" loading="lazy" src="<?= e(asset('img/call-community-grid.jpg')) ?>" alt="Grid of call participants"><span class="shot-cap">the room: builders, founders, regulars</span></li>
    </ul>
  </div>
</section>

<!-- ═══════════ 5 · how to get in ═══════════ -->
<section class="s s-join" id="join" data-locked>
  <div class="wrap">
    <h2 class="h"><span class="h-mark">How to get in</span></h2>

    <ol class="steps">
      <li class="step">
        <h3 class="step-title">Watch</h3>
        <p class="step-body">The stream is public and published everywhere. Follow #bitcoinshipshow and tune in monthly on <a href="https://www.youtube.com/@BitcoinShipshow" target="_blank" rel="noopener" style="border-bottom:2px dashed var(--co-gold)">YouTube →</a></p>
      </li>
      <li class="step">
        <h3 class="step-title">Join the room</h3>
        <p class="step-body">After you sign up, check your inbox for the link to the call. We keep the room link out of the public site.</p>
      </li>
      <li class="step">
        <h3 class="step-title">Demo</h3>
        <p class="step-body">Got something working — even rough? Working demos only, no slideware. Early and unknown is exactly what we want.</p>
      </li>
    </ol>

    <p class="prose">Grassroots by design: no corporate machine behind this. Hosts spread it
      through their own networks, projects bring their communities, and one demo slot every month
      is reserved for a project with no partner behind it.</p>

    <p class="prose">Want your name on the show? Founding partner seasons are open —
      <a href="/sponsors/">read the partner one-pager →</a></p>

    <p class="pagefoot">BITCOIN SHIPSHOW · monthly ·
          <a href="https://x.com/bitcoinshipshow">@bitcoinshipshow</a> · #bitcoinshipshow</p>
  </div>
</section>

    <?php require __DIR__ . '/partials/picker.php'; ?>
<script src="<?= e(asset('js/shipshow.js')) ?>"></script>
<script data-goatcounter="https://bitcoinshipshow.goatcounter.com/count" async src="//gc.zgo.at/count.js"></script>
</body>
</html>
