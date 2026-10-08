<?php
require __DIR__ . '/inc/bootstrap.php';

$general = q('SELECT * FROM documents WHERE product_id IS NULL ORDER BY sort, id')->fetchAll();
$byProduct = q('SELECT d.*, p.name AS pname, p.size AS psize FROM documents d JOIN products p ON p.id = d.product_id
                WHERE p.active = 1 ORDER BY p.sort, p.id, d.sort, d.id')->fetchAll();

page_header('Сертификаты');
?>
<div class="container page-pad">
  <nav class="crumbs" aria-label="Навигация"><a href="<?= url() ?>">Главная</a> / Сертификаты</nav>
  <h1>Сертификаты и паспорта качества</h1>
  <?php if (!$general && !$byProduct): ?>
    <p class="empty">Документы скоро появятся.</p>
  <?php endif; ?>
  <?php if ($general): ?>
    <section style="margin-bottom:36px">
      <h2>Общие документы</h2>
      <ul class="doc-list" style="max-width:820px">
        <?php foreach ($general as $d): ?>
          <li><a class="doc" href="<?= e(upload_url($d['file'])) ?>" target="_blank" rel="noopener"><?= icon('doc', 26) ?><span><?= e($d['title']) ?></span><em>Скачать</em></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
  <?php if ($byProduct): ?>
    <section>
      <h2>По товарам</h2>
      <ul class="doc-list" style="max-width:820px">
        <?php foreach ($byProduct as $d): ?>
          <li><a class="doc" href="<?= e(upload_url($d['file'])) ?>" target="_blank" rel="noopener"><?= icon('doc', 26) ?><span><?= e($d['title']) ?><br><small class="muted" style="font-weight:400"><?= e($d['pname'] . ' ' . $d['psize']) ?></small></span><em>Скачать</em></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</div>
<?php page_footer();
