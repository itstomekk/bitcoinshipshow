/**
 * Shipshow behaviour. Shared by every design.
 *
 * Rule: this file touches data-* attributes and state classes only. It must never
 * reference a class that a design is allowed to restyle, otherwise renaming a class
 * in one skin silently breaks the countdown in all of them.
 *
 *   [data-countdown]        the countdown block
 *   [data-cd="d|h|m|s"]     the four numbers
 *   [data-cd="label"]       "Next episode in" / "Happening"
 *   [data-cd="list"]        the <ol>, replaced wholesale when the show is live
 *   [data-cd="when"]        the human-readable date line
 *   [data-signup]           the email form
 *   [data-locked]           removed from every element once an email is given
 *   .is-revealed            added to those elements, for the entrance animation
 *   .is-live                added to <body> when an episode is happening now
 */
(function () {
  'use strict';

  /* ── schedule ─────────────────────────────────────────────────────────────
     FIXED_START wins when set; the monthly rule below is the fallback.

     Set by Tomek 2026-09-21: Tue 6 Oct 2026, 11:00 Roatan (UTC-6).
     That is 17:00 UTC and 19:00 Berlin. Roatan has no DST, so the -06:00
     offset is literal and correct all year.

     6 Oct is the FIRST Tuesday, which the "last Tuesday" rule cannot produce —
     hence the override rather than a tweak to HOUR/WEEKDAY.

     This mirrors 'event_start' in gate/config.php. Change BOTH or the countdown
     and the invite will disagree. To resume the monthly cadence, set
     FIXED_START to null here and clear 'event_start' there. */
  /* No one-off override — the rule below produces 6 Oct 2026 by itself.
     Set this only for a genuine exception, and clear it once that date passes. */
  var FIXED_START = null;

  /* FIRST TUESDAY monthly, 10:00 America/Los_Angeles (Tomek, 2026-09-21).
     WEEKDAY 2 = Tuesday.

     Anchored to SF because the US and Europe change clocks within a week of each
     other, so this single anchor holds BOTH steady: Berlin 19:00, SF 10:00, all
     year. Roatan (no DST) is the one that drifts — 11:00 summer, 12:00 winter.

     Mirrors event_rule in gate/config.php. Change BOTH or the countdown and the
     calendar invite will disagree. */
  var TZ = 'America/Los_Angeles', HOUR = 10, MIN = 0, DURATION_MIN = 120,
      WEEKDAY = 2, ORDINAL = 'first'; /* 0 = Sunday */

  /* the UTC instant whose Berlin wall-clock reads y-m-d hh:mm — DST-safe */
  function instant(y, m, d, hh, mm) {
    var want = Date.UTC(y, m, d, hh, mm), ts = want;
    for (var i = 0; i < 3; i++) {
      var f = new Intl.DateTimeFormat('en-GB', {
        timeZone: TZ, hour12: false, year: 'numeric', month: '2-digit',
        day: '2-digit', hour: '2-digit', minute: '2-digit'
      });
      var p = {};
      f.formatToParts(new Date(ts)).forEach(function (x) { p[x.type] = x.value; });
      var asLocal = Date.UTC(+p.year, +p.month - 1, +p.day, +(p.hour % 24), +p.minute);
      var diff = want - asLocal;
      if (diff === 0) break;
      ts += diff;
    }
    return ts;
  }

  /* Nth weekday of a month. ORDINAL 'last' walks back from the final day,
     anything else walks forward from the 1st. */
  function nthWeekdayOfMonth(y, m, wd, ordinal) {
    if (ordinal === 'last') {
      var d = new Date(Date.UTC(y, m + 1, 0));
      while (d.getUTCDay() !== wd) { d.setUTCDate(d.getUTCDate() - 1); }
      return d.getUTCDate();
    }
    var n = { first: 1, second: 2, third: 3, fourth: 4 }[ordinal] || 1;
    var f = new Date(Date.UTC(y, m, 1));
    while (f.getUTCDay() !== wd) { f.setUTCDate(f.getUTCDate() + 1); }
    return f.getUTCDate() + (n - 1) * 7;
  }

  function nextEpisode() {
    if (FIXED_START) {
      var fixed = Date.parse(FIXED_START);
      /* Once the one-off has finished, fall through to the monthly rule rather
         than showing a countdown that has gone negative forever. */
      if (!isNaN(fixed) && fixed + DURATION_MIN * 60000 > Date.now()) { return fixed; }
    }
    var now = Date.now(), probe = new Date();
    for (var i = 0; i < 3; i++) {
      var m = probe.getUTCMonth() + i, y = probe.getUTCFullYear();
      var yy = y + Math.floor(m / 12), mm = ((m % 12) + 12) % 12;
      var start = instant(yy, mm, nthWeekdayOfMonth(yy, mm, WEEKDAY, ORDINAL), HOUR, MIN);
      if (start + DURATION_MIN * 60000 > now) return start;
    }
    return null;
  }

  function cd(key) { return document.querySelector('[data-cd="' + key + '"]'); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }

  /* ── live window ────────────────────────────────────────────────────────
     The Watch Live section (first in the DOM) is hidden by CSS until the
     show is on air. It appears from the episode's start until five hours
     later — body.is-live-window is toggled here, the design styles react. */
  function inLiveWindow() {
    var now = Date.now(), probe = new Date(now);
    /* check this month and the previous two: a 5h window never straddles
       more than one month boundary, and the first-Tuesday rule means the
       show is always inside the current (or just-finished) month. */
    for (var back = 0; back < 3; back++) {
      var m = probe.getUTCMonth() - back, y = probe.getUTCFullYear();
      var yy = y + Math.floor(m / 12), mm = ((m % 12) + 12) % 12;
      var start = instant(yy, mm, nthWeekdayOfMonth(yy, mm, WEEKDAY, ORDINAL), HOUR, MIN);
      var end = start + 5 * 3600 * 1000;
      if (now >= start && now < end) return true;
    }
    return false;
  }

  function updateLiveWindow() {
    document.body.classList.toggle('is-live-window', inLiveWindow());
  }
  updateLiveWindow();
  setInterval(updateLiveWindow, 60000);

  /* ── countdown ──────────────────────────────────────────────────────────── */
  var target = nextEpisode();

  if (target && cd('list')) {
    var elList = cd('list'), elLabel = cd('label'), elWhen = cd('when'),
        elD = cd('d'), elH = cd('h'), elM = cd('m'), elS = cd('s');

    var when = new Date(target);
    var mine = new Intl.DateTimeFormat(undefined, {
      weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'
    }).format(when);
    /* US east coast and central Europe. The abbreviation is derived from the real
       UTC offset on the day, not from Intl's timeZoneName: in an en-US locale Intl
       renders Berlin as "GMT+2" rather than "CEST". And it cannot be hardcoded
       either — EDT/EST and CEST/CET swap over every autumn. */
    function offsetMinutes(tz) {
      var f = new Intl.DateTimeFormat('en-US', {
        timeZone: tz, hour12: false, year: 'numeric', month: '2-digit',
        day: '2-digit', hour: '2-digit', minute: '2-digit'
      });
      var p = {};
      f.formatToParts(when).forEach(function (x) { p[x.type] = x.value; });
      var asUTC = Date.UTC(+p.year, +p.month - 1, +p.day, +(p.hour % 24), +p.minute);
      return Math.round((asUTC - when.getTime()) / 60000);
    }
    function clock(tz) {
      return new Intl.DateTimeFormat('en-GB', {
        timeZone: tz, hour: '2-digit', minute: '2-digit', hour12: false
      }).format(when);
    }
    var east = clock('America/New_York') + (offsetMinutes('America/New_York') === -240 ? ' EDT' : ' EST');
    var euro = clock('Europe/Berlin')    + (offsetMinutes('Europe/Berlin')    === 120  ? ' CEST' : ' CET');

    var cadence = FIXED_START ? '' : ' · first Tuesday of every month';
    if (elWhen) {
      elWhen.textContent = mine + ' your time · ' + east + ' · ' + euro + cadence;
    }

    var timer = setInterval(tick, 1000);
    tick();
  }

  function tick() {
    var left = target - Date.now();
    if (left <= 0) {
      clearInterval(timer);
      document.body.classList.add('is-live');
      var list = cd('list');
      if (list) list.innerHTML = '<li class="cd-live">Live right now</li>';
      var lab = cd('label');
      if (lab) lab.textContent = 'Happening';
      return;
    }
    var s = Math.floor(left / 1000);
    if (cd('d')) cd('d').textContent = Math.floor(s / 86400);
    if (cd('h')) cd('h').textContent = pad(Math.floor(s / 3600) % 24);
    if (cd('m')) cd('m').textContent = pad(Math.floor(s / 60) % 60);
    if (cd('s')) cd('s').textContent = pad(s % 60);
  }

  /* ── the reveal ─────────────────────────────────────────────────────────── */
  var KEY = 'bss_joined';

  /* Reveals the rest of the page and releases the scroll lock. Stay at the
     signup confirmation; the room link is never displayed on the website. */
  function unlock() {
    document.documentElement.classList.remove('locked');
    var els = document.querySelectorAll('[data-locked]');
    for (var i = 0; i < els.length; i++) {
      els[i].removeAttribute('data-locked');
      els[i].classList.add('is-revealed');
    }
  }

  try { if (localStorage.getItem(KEY)) unlock(); } catch (e) {}

  /* ── signup: inline confirmation with email-only join link ───────────────
     JSON shape: { ok, status, invite_sent, event_label, event_human, event_iso }.
     Never read or render a room, calendar, or ICS URL from the browser response. */

  function sentEl(key) { return document.querySelector('[data-sent="' + key + '"]'); }

  var form = document.querySelector('[data-signup]');
  var errEl = document.querySelector('[data-error]');
  var sent = document.querySelector('[data-sent]');

  function showSent(d) {
    var dup = d.status === 'dup';

    var invited = d.invite_sent === true;
    sentEl('head').innerHTML = dup ? 'Already <em>in</em>' : (invited ? 'Check your <em>inbox</em>' : 'Signup <em>saved</em>');
    sentEl('sub').textContent = dup
      ? 'This address is already on the list. Check your inbox for the call invitation.'
      : (invited
        ? 'Your call invitation is on its way by email. Check your inbox, including spam.'
        : 'Your signup was saved, but the invitation could not be sent. Contact the Shipshow team.');

    var when = sentEl('when');
    when.textContent = d.event_human ? (d.event_label + ' · ' + d.event_human) : '';
    when.hidden = !d.event_human;

    form.hidden = true;
    sent.hidden = false;
    sent.classList.add('is-revealed');
  }

  if (form) {
    form.addEventListener('submit', function (ev) {
      if (form.checkValidity && !form.checkValidity()) return;
      /* Browsers without fetch submit normally to the safe, link-free HTML confirmation. */
      if (!window.fetch || !window.FormData) return;
      ev.preventDefault();

      var btn = form.querySelector('button');
      var label = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }
      if (errEl) errEl.hidden = true;

      var fd = new FormData(form);
      fd.append('format', 'json');

      fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
        .then(function (res) {
          if (!res.body || res.body.ok !== true) {
            throw new Error((res.body && res.body.error) || 'Something went wrong. Try again?');
          }
          try { localStorage.setItem(KEY, '1'); } catch (e) {}
          showSent(res.body);
          unlock();
        })
        .catch(function (e) {
          if (btn) { btn.disabled = false; btn.textContent = label; }
          if (errEl) {
            errEl.textContent = e.message || 'Could not reach the server. Try again?';
            errEl.hidden = false;
          }
        });
    });
  }
})();

/* Footbar: no marquee loop - the items are spread once, and the whole bar
   drifts slightly sideways as you scroll (scroll-coupled, subtle). */
(function () {
  var tk = document.querySelector('.ticker-track');
  if (!tk) return;
  var ticking = false;
  function drift() {
    ticking = false;
    var y = window.scrollY || 0;
    tk.style.transform = 'translateX(-' + (y * 0.06) + 'px)';
  }
  window.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; requestAnimationFrame(drift); }
  }, { passive: true });
  drift();
})();

/* Gallery "eye": on hover the strip sweeps left and right on its own,
   like a radar/eye scan. Stops on mouse leave and on touch. */
(function () {
  var gal = document.querySelector('.gallery');
  if (!gal) return;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var raf = null, t0 = null, playing = false;

  function maxScroll() { return gal.scrollWidth - gal.clientWidth; }

  function step(ts) {
    if (!playing) return;
    if (t0 === null) t0 = ts;
    var t = (ts - t0) / 1000;
    var ph = (t / 4) * Math.PI * 2;
    var m = maxScroll();
    gal.scrollLeft = m / 2 + (m / 2) * Math.sin(ph);
    raf = requestAnimationFrame(step);
  }
  function start() {
    playing = true; t0 = null;
    gal.style.scrollSnapType = 'none';
    raf = requestAnimationFrame(step);
  }
  function stop() {
    playing = false;
    if (raf) { cancelAnimationFrame(raf); raf = null; }
    gal.style.scrollSnapType = '';
  }
  gal.addEventListener('mouseenter', start);
  gal.addEventListener('mouseleave', stop);
  gal.addEventListener('touchstart', stop, { passive: true });
})();
