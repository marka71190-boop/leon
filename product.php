<?php
require __DIR__ . '/inc/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$p = q('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.active = 1', [$id])->fetch();
if (!$p) {
    http_response_code(404);
    page_header('Товар не найден');
    echo '<div class="container page-pad"><h1 style="margin-top:32px">Товар не найден</h1><p><a href="' . url('catalog.php') . '">Вернуться в каталог</a></p></div>';
    page_footer();
    exit;
}
$docs = q('SELECT * FROM documents WHERE product_id = ? ORDER BY sort, id', [$id])->fetchAll();
$related = q('SELECT * FROM products WHERE category_id IS ? AND id <> ? AND active = 1 ORDER BY sort, id LIMIT 4', [$p['category_id'], $id])->fetchAll();
$step = product_step($p);
$cart = cart_raw();
$inCart = isset($cart[$id]);

$specs = [];
if ($p['size']) $specs[] = ['Размер', $p['size']];
if ($p['type']) $specs[] = ['Тип', $p['type']];
if ($p['material']) $specs[] = ['Материал', $p['material']];
if ($p['pack_qty']) $specs[] = ['Фасовка', fmt_qty($p['pack_qty']) . ' шт. в упаковке'];
$specs[] = ['Кратность заказа', fmt_qty($step) . ' шт.'];
foreach (preg_split('~\R~u', (string)$p['specs']) as $line) {
    if (str_contains($line, ':')) {
        [$k, $v] = array_map('trim', explode(':', $line, 2));
        if ($k !== '') $specs[] = [$k, $v];
    }
}

page_header($p['name'] . ($p['size'] ? ' ' . $p['size'] : ''), ['description' => mb_substr(strip_tags($p['description']) ?: $p['name'], 0, 160)]);
?>
<div class="container page-pad">
  <nav class="crumbs" aria-label="Навигация"><a href="<?= url() ?>">Главная</a> / <a href="<?= url('catalog.php') ?>">Каталог</a><?php if ($p['category_id']): ?> / <a href="<?= url('catalog.php?cat=' . $p['category_id']) ?>"><?= e($p['category_name']) ?></a><?php endif; ?></nav>

  <div class="product">
    <div class="product-gallery">
      <?php if ($p['image']): ?>
        <img class="main-img" src="<?= e(upload_url($p['image'])) ?>" alt="<?= e($p['name']) ?>">
      <?php else: ?>
        <div class="main-img"><span style="opacity:.25"><?= logo_mark(160) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="product-info">
      <div>
        <?php if ($p['sku']): ?><div class="muted small">Артикул: <?= e($p['sku']) ?></div><?php endif; ?>
        <h1 style="margin:6px 0 0; font-size: clamp(24px,3vw,32px)"><?= e($p['name']) ?><?= $p['size'] ? ' ' . e($p['size']) : '' ?></h1>
      </div>

      <form class="buy-box" method="post" action="<?= url('cart.php') ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= $id ?>">
        <div class="buy-price"><span class="muted"><?= e(setting('price_note')) ?></span><b><?= price_label($p) ?><?php if (price_label($p) !== 'По запросу'): ?><span class="muted" style="font:500 15px var(--font)"> / шт.</span><?php endif; ?></b></div>
        <div class="buy-row">
          <?= qty_input('qty', max($step, (int)($cart[$id] ?? ceil(min_order() / $step) * $step)), $step) ?>
          <button class="btn btn-accent btn-lg" type="submit"><?= $inCart ? 'Обновить в корзине' : 'Добавить в корзину' ?></button>
        </div>
        <?php if ($inCart): ?><a href="<?= url('cart.php') ?>"><b>Товар в корзине — перейти к оформлению →</b></a><?php endif; ?>
        <span class="muted small">Минимальный заказ — <?= fmt_qty(min_order()) ?> шт. по всей корзине. Заказ кратен <?= fmt_qty($step) ?> шт.</span>
      </form>

      <div data-tabs>
        <div class="tabs" role="tablist" aria-label="Информация о товаре">
          <button class="tab" type="button" role="tab" aria-selected="true" aria-controls="t-spec" id="b-spec">Характеристики</button>
          <button class="tab" type="button" role="tab" aria-selected="false" aria-controls="t-docs" id="b-docs">Документы (<?= count($docs) ?>)</button>
          <button class="tab" type="button" role="tab" aria-selected="false" aria-controls="t-ship" id="b-ship">Доставка</button>
        </div>
        <div id="t-spec" role="tabpanel" aria-labelledby="b-spec">
          <table class="specs"><tbody>
            <?php foreach ($specs as $s): ?><tr><th scope="row"><?= e($s[0]) ?></th><td><?= e($s[1]) ?></td></tr><?php endforeach; ?>
          </tbody></table>
          <?php if ($p['description']): ?><div class="content" style="margin-top:20px; font-size:16px"><?= $p['description'] ?></div><?php endif; ?>
        </div>
        <div id="t-docs" role="tabpanel" aria-labelledby="b-docs" hidden>
          <?php if ($docs): ?>
          <ul class="doc-list">
            <?php foreach ($docs as $d): ?>
              <li><a class="doc" href="<?= e(upload_url($d['file'])) ?>" target="_blank" rel="noopener"><?= icon('doc', 26) ?><span><?= e($d['title']) ?></span><em>Скачать</em></a></li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
            <p class="muted">Документы на этот товар скоро появятся. Общие сертификаты — в разделе <a href="<?= url('certificates.php') ?>">«Сертификаты»</a>.</p>
          <?php endif; ?>
        </div>
        <div id="t-ship" role="tabpanel" aria-labelledby="b-ship" hidden>
          <p><?= e(setting('delivery_note')) ?></p>
          <p>Оплата — по счёту после подтверждения заказа менеджером. Онлайн-оплата на сайте не предусмотрена. <a href="<?= url('page.php?p=delivery') ?>">Подробнее</a></p>
        </div>
      </div>
    </div>
  </div>

  <?php if ($related): ?>
  <section style="margin-top:56px">
    <h2>Другие позиции раздела</h2>
    <div class="prod-grid">
      <?php foreach ($related as $r): ?>
        <a class="prod-card" href="<?= url('product.php?id=' . $r['id']) ?>">
          <?= product_thumb($r) ?>
          <b><?= e($r['name']) ?> <?= e($r['size']) ?></b>
          <span><b><?= price_label($r) ?></b></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
<?php page_footer();
