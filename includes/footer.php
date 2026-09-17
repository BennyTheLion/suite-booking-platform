<?php /** Expects $site, $L */ ?>
</div>

<?php if (!empty($site['whatsapp'])): ?>
<a class="fab-whatsapp" target="_blank" rel="noopener"
   href="<?= h(whatsapp_link($site['whatsapp'], 'שלום, אשמח לפרטים נוספים על ' . $site['name'] . '.')) ?>"
   aria-label="וואטסאפ">
  <svg viewBox="0 0 32 32"><path d="M16.02 3C9.4 3 4.02 8.38 4.02 15c0 2.23.6 4.32 1.65 6.12L4 29l8.06-1.63a12 12 0 0 0 3.96.67h.01c6.62 0 12-5.38 12-12S22.64 3 16.02 3Zm0 21.8h-.01a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-4.78.97 1.02-4.66-.24-.38A9.75 9.75 0 0 1 6.22 15c0-5.4 4.4-9.8 9.8-9.8 2.62 0 5.08 1.02 6.93 2.87a9.73 9.73 0 0 1 2.87 6.93c0 5.4-4.4 9.8-9.8 9.8Zm5.37-7.34c-.29-.15-1.74-.86-2.01-.96-.27-.1-.47-.15-.66.15-.2.29-.76.96-.93 1.16-.17.2-.34.22-.63.07-.29-.15-1.24-.46-2.36-1.46-.87-.78-1.46-1.73-1.63-2.02-.17-.29-.02-.45.13-.6.13-.13.29-.34.44-.51.15-.17.2-.29.29-.49.1-.2.05-.37-.02-.51-.07-.15-.66-1.59-.9-2.18-.24-.57-.48-.5-.66-.5-.17 0-.37-.02-.56-.02-.2 0-.51.07-.78.37-.27.29-1.02 1-1.02 2.44s1.05 2.83 1.19 3.02c.15.2 2.06 3.14 5 4.4.7.3 1.24.48 1.67.62.7.22 1.34.19 1.84.12.56-.08 1.74-.71 1.98-1.4.24-.68.24-1.27.17-1.4-.07-.12-.27-.2-.56-.34Z"/></svg>
</a>
<?php endif; ?>

<button class="fab-a11y" type="button" id="a11yToggle" aria-label="תפריט נגישות">
  <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7"><circle cx="12" cy="4.5" r="1.6"/><path d="M4 8.5c2.5.9 5.2 1.4 8 1.4s5.5-.5 8-1.4M12 9.9V21M8.5 21l1.6-6.5h3.8L15.5 21"/></svg>
</button>
<div class="a11y-panel" id="a11yPanel" hidden>
  <h3>נגישות</h3>
  <div class="a11y-row">
    <span>גודל טקסט</span>
    <div class="a11y-btns">
      <button type="button" id="a11yDec" aria-label="הקטן טקסט">A-</button>
      <button type="button" id="a11yInc" aria-label="הגדל טקסט">A+</button>
    </div>
  </div>
  <div class="a11y-row"><span>ניגודיות גבוהה</span><button class="a11y-toggle" type="button" id="a11yContrast">הפעלה</button></div>
  <div class="a11y-row"><span>הדגשת קישורים</span><button class="a11y-toggle" type="button" id="a11yUnderline">הפעלה</button></div>
  <div class="a11y-row"><span>עצירת אנימציות</span><button class="a11y-toggle" type="button" id="a11yMotion">הפעלה</button></div>
  <button class="a11y-reset" type="button" id="a11yReset">איפוס הגדרות</button>
</div>

<?php
$jsVer = function (string $file): string { return '?v=' . filemtime(__DIR__ . '/../assets/js/' . $file); };
?>
<script src="<?= APP_BASE_URL ?>/assets/js/accessibility.js<?= $jsVer('accessibility.js') ?>"></script>
<script src="<?= APP_BASE_URL ?>/assets/js/room.js<?= $jsVer('room.js') ?>" defer></script>
<script src="<?= APP_BASE_URL ?>/assets/js/room-cards.js<?= $jsVer('room-cards.js') ?>" defer></script>
</body>
</html>
