<?php
require __DIR__ . '/inc/bootstrap.php';

page_header('Контакты');
$tel = preg_replace('~[^\d+]~', '', setting('phone'));
?>
<div class="container page-pad">
  <nav class="crumbs" aria-label="Навигация"><a href="<?= url() ?>">Главная</a> / Контакты</nav>
  <h1>Контакты</h1>
  <div class="two-col" style="margin-bottom:28px">
    <div class="panel">
      <h2>Производство и склад</h2>
      <p><?= e(setting('address')) ?></p>
      <?php if (setting('work_hours')): ?><p class="muted"><?= e(setting('work_hours')) ?></p><?php endif; ?>
    </div>
    <div class="panel">
      <h2>Связаться с нами</h2>
      <p><a href="tel:<?= e($tel) ?>" style="font-size:22px; font-weight:700; color:var(--ink); text-decoration:none"><?= e(setting('phone')) ?></a></p>
      <?php if (setting('email')): ?><p><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></p><?php endif; ?>
      <div class="hero-actions">
        <?php if (setting('telegram')): ?><a class="btn btn-outline" href="<?= e(setting('telegram')) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
        <?php if (setting('whatsapp')): ?><a class="btn btn-outline" href="<?= e(setting('whatsapp')) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="panel" style="margin-bottom:28px">
    <h2>Реквизиты</h2>
    <?= requisites_html() ?>
  </div>
  <?php if (setting('map_embed')): ?><div class="map"><?= setting('map_embed') ?></div><?php endif; ?>
</div>
<?php page_footer();
