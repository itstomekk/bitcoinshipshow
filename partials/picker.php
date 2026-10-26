<?php
/**
 * The design switcher. Deliberately self-contained and ugly-on-purpose: it uses
 * its own prefixed classes, inline styles and system fonts so that no design
 * stylesheet can accidentally restyle it, and so it never reads as part of any
 * skin being evaluated.
 *
 * Hidden with ?picker=0, or permanently by flipping PICKER_DEFAULT in boot.php.
 */
if (empty($SHOW_PICKER)) return;

/* keep every other query param when switching */
$carry = $_GET;
unset($carry['design']);
$qs = $carry ? '&' . http_build_query($carry) : '';
?>
<div class="bsspick" id="bsspick" role="group" aria-label="Design switcher">
  <span class="bsspick-lbl">design</span>
  <?php $i = 0; foreach ($DESIGNS as $slug => $meta): $i++; ?>
    <a class="bsspick-btn<?= $slug === $DESIGN ? ' is-on' : '' ?>"
       href="?design=<?= e($slug) ?><?= $qs ?>"
       title="<?= e($meta['note']) ?>"
       data-key="<?= $i ?>"><span class="bsspick-num"><?= $i ?></span><?= e($meta['name']) ?></a>
  <?php endforeach; ?>
  <button class="bsspick-x" type="button" id="bsspick-x" title="Hide the switcher for this visit" aria-label="Hide switcher">&times;</button>
</div>
<style>
.bsspick{position:fixed;z-index:2147483000;left:50%;bottom:14px;transform:translateX(-50%);
  display:flex;align-items:center;gap:6px;padding:6px 8px;max-width:calc(100vw - 20px);flex-wrap:wrap;
  background:rgba(18,18,20,.92);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.18);
  border-radius:10px;box-shadow:0 6px 26px rgba(0,0,0,.45);
  font:500 12px/1 ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#fff}
.bsspick-lbl{opacity:.5;text-transform:uppercase;letter-spacing:.13em;font-size:9.5px;padding:0 4px}
.bsspick-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 11px;border-radius:6px;
  text-decoration:none;color:#e9e9ee;background:rgba(255,255,255,.07);white-space:nowrap;
  transition:background .12s,color .12s}
.bsspick-btn:hover{background:rgba(255,255,255,.16)}
.bsspick-btn.is-on{background:#fff;color:#111;font-weight:700}
.bsspick-num{display:inline-block;min-width:13px;text-align:center;font-size:9.5px;opacity:.55;
  border:1px solid currentColor;border-radius:3px;padding:1px 0}
.bsspick-x{background:none;border:0;color:#fff;opacity:.45;font-size:16px;line-height:1;cursor:pointer;padding:4px 6px}
.bsspick-x:hover{opacity:1}
@media print{.bsspick{display:none}}
</style>
<script>
(function(){
  var bar = document.getElementById('bsspick');
  document.getElementById('bsspick-x').addEventListener('click', function(){ bar.style.display = 'none'; });
  /* number keys jump between designs, so you can flip fast on a call */
  document.addEventListener('keydown', function(ev){
    if (ev.metaKey || ev.ctrlKey || ev.altKey) return;
    var t = ev.target.tagName;
    if (t === 'INPUT' || t === 'TEXTAREA' || ev.target.isContentEditable) return;
    var hit = bar.querySelector('.bsspick-btn[data-key="' + ev.key + '"]');
    if (hit) { window.location.href = hit.getAttribute('href'); }
  });
})();
</script>
