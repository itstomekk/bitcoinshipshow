<?php
/**
 * Shared shell for the legal pages (/privacy-policy/, /terms-of-service/).
 *
 * A page sets $PAGE_SLUG, $PAGE_TITLE, $PAGE_DESC and $LEGAL = ['h1' => ..., 'updated' => ...,
 * 'intro' => html, 'sections' => [[heading, html], ...]] and then requires this file.
 * Both pages stay fully public and indexable: no gate, no scroll lock, no signup form.
 *
 * Why these pages exist: Google's OAuth brand verification needs a privacy policy and terms of
 * service on the app's own domain, linked from the home page. Keep the copy true to what the
 * code in gate/ actually stores - tests/test_legal_pages.py pins the claims that can drift.
 */
require_once __DIR__ . '/boot.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/head.php'; ?>
<style>
  .design-corsair .s-legal {
    background: var(--co-parch); background-image: var(--co-foxing); color: var(--co-ink);
    min-height: 100svh; padding: 56px 26px 80px;
  }
  .design-corsair .s-legal .h-mark { background: var(--co-ink); color: var(--co-gold); box-shadow: 10px 10px 0 0 var(--co-blood); }
  .design-corsair .s-legal .legal-brand { display: inline-block; margin-bottom: 28px; }
  .design-corsair .s-legal .legal-brand img { width: 120px; height: auto; display: block; }
  .design-corsair .s-legal .legal-meta { margin-top: 6px; font-size: 14px; letter-spacing: .12em; text-transform: uppercase; }
  .design-corsair .s-legal .legal-intro { margin-top: 22px; max-width: 68ch; font-size: 17px; line-height: 1.7; }
  .design-corsair .s-legal h2.legal-h {
    font-family: "Pirata One", serif; font-weight: 400; font-size: clamp(26px, 4vw, 36px);
    line-height: 1.05; margin: 40px 0 10px; border-top: 4px solid var(--co-ink); padding-top: 18px; max-width: 68ch;
  }
  .design-corsair .s-legal .legal-body { max-width: 68ch; font-size: 16.5px; line-height: 1.7; }
  .design-corsair .s-legal .legal-body p { margin: 0 0 12px; }
  .design-corsair .s-legal .legal-body ul { margin: 0 0 12px; padding-left: 22px; list-style: disc; }
  .design-corsair .s-legal .legal-body li { margin-bottom: 6px; }
  .design-corsair .s-legal a { color: var(--co-ink); border-bottom: 2px dashed var(--co-blood); text-decoration: none; }
  .design-corsair .s-legal a:hover { border-bottom-style: solid; }
  .design-corsair .s-legal .legal-nav { margin-top: 44px; font-size: 14px; letter-spacing: .14em; text-transform: uppercase; }
</style>
</head>

<body class="page-legal design-<?= e($DESIGN) ?>">
<div class="fx" aria-hidden="true"></div>

<section class="s s-legal" id="top">
  <div class="wrap">

    <a class="legal-brand" href="/" aria-label="Bitcoin Shipshow home">
      <img src="<?= e(asset('img/shipshow-boat.png')) ?>" width="433" height="258"
           alt="Bitcoin Shipshow, a paper boat folded from the front page of The Times, 3 January 2009">
    </a>

    <h1 class="h"><span class="h-mark"><?= e($LEGAL['h1']) ?></span></h1>
    <p class="legal-meta">Bitcoin Shipshow · last updated <?= e($LEGAL['updated']) ?></p>
    <p class="legal-intro"><?= $LEGAL['intro'] ?></p>

<?php foreach ($LEGAL['sections'] as $sec): ?>
    <h2 class="legal-h"><?= e($sec[0]) ?></h2>
    <div class="legal-body"><?= $sec[1] ?></div>
<?php endforeach; ?>

    <p class="legal-nav">
      <a href="/">Front page</a> ·
      <a href="/about/">About</a> ·
      <a href="/privacy-policy/">Privacy policy</a> ·
      <a href="/terms-of-service/">Terms of service</a> ·
      <a href="mailto:hello@bitcoinship.show">hello@bitcoinship.show</a>
    </p>
  </div>
</section>

<script data-goatcounter="https://bitcoinshipshow.goatcounter.com/count" async src="//gc.zgo.at/count.js"></script>
</body>
</html>
