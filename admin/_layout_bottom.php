</main>
<?php if (defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== ''): ?>
<script
  id="pushConfig"
  data-site="<?= h($site['slug']) ?>"
  data-vapid-key="<?= h(VAPID_PUBLIC_KEY) ?>"
  data-csrf="<?= h(csrf_token()) ?>"
  data-base-url="<?= h(APP_BASE_URL) ?>"
></script>
<script src="<?= APP_BASE_URL ?>/assets/js/admin-push.js" defer></script>
<?php endif; ?>
</body>
</html>
