<?php
require_once __DIR__ . '/inc/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$p = q('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.active = 1', [$id])->fetch();
if (!$p) {
    page_404('Товар не найден', 'Возможно, товар снят с продажи или его адрес изменился.');
}
// Старый адрес product.php?id=5 переезжает на /product/nazvanie-tovara с кодом 301
seo_canonical_redirect(product_url($p), ['id']);
$catLink = $p['category_id'] ? cat_url(['id' => $p['category_id'], 'slug' => $p['category_slug']]) : 'catalog';
$docs = q('SELECT * FROM documents WHERE product_id = ? ORDER BY sort, id', [$id])->fetchAll();
$related = q('SELECT * FROM products WHERE category_id IS ? AND id <> ? AND active = 1 ORDER BY sort, id LIMIT 4', [$p['category_id'], $id])->fetchAll();
$step = product_step($p);
$pack = product_pack($p);
$fullName = trim($p['name'] . ' ' . $p['size']);
// Галерея: фото, видеофайлы и ссылки на видео в порядке из админки
$media = product_images($id);
if (!$media && $p['image']) {
    $media = [['file' => $p['image'], 'kind' => 'image']];
}
$photos = array_values(array_column(array_filter($media, fn($m) => ($m['kind'] ?? 'image') === 'image'), 'file'));
$cart = cart_raw();
$inCart = isset($cart[$id]);
// Сколько показать в поле: уже в корзине или столько, чтобы набрать минимальный заказ
$wantQty = (int)($cart[$id] ?? max(min_order(), $pack, $step));
$qtyValue = $pack ? (int)ceil($wantQty / $pack) : max($step, (int)ceil($wantQty / $step) * $step);

$specs = [];
if ($p['size']) $specs[] = ['Размер', $p['size']];
if ($p['type']) $specs[] = ['Тип', $p['type']];
if ($p['material']) $specs[] = ['Материал', $p['material']];
if ($p['pack_qty']) $specs[] = ['Фасовка', fmt_qty($p['pack_qty']) . ' шт. в упаковке'];
$specs[] = ['Как заказать', $pack ? 'Упаковками по ' . fmt_qty($pack) . ' шт.' : 'Кратно ' . fmt_qty($step) . ' шт.'];
foreach (preg_split('~\R~u', (string)$p['specs']) as $line) {
    if (str_contains($line, ':')) {
        [$k, $v] = array_map('trim', explode(':', $line, 2));
        if ($k !== '') $specs[] = [$k, $v];
    }
}

// SEO: заголовок и описание из админки или собираются автоматически
$priceNum = (float)$p['price'];
$autoDesc = $fullName . ' оптом от производителя ' . setting('site_name')
    . ($priceNum > 0 && setting('show_prices') === '1' ? ': ' . fmt_money($priceNum) . ($pack ? ' за упаковку' : ' за шт.') : '')
    . ($pack ? ', в упаковке ' . fmt_qty($pack) . ' шт' : '')
    . '. ' . (min_order() > 0 ? 'Заказ от ' . fmt_qty(min_order()) . ' шт., отгрузка' : 'Отгрузка') . ' со склада в Краснодаре, доставка по России транспортными компаниями.';
// Описание для страницы, если в админке его не заполнили: чтобы у товара был текст, а не только таблица
$autoText = '';
if (trim(strip_tags((string)$p['description'])) === '') {
    $autoText = '<p>' . e($fullName) . ($p['category_name'] ? ' из раздела «' . e($p['category_name']) . '» — продукция собственного производства ' . e(setting('site_name')) . '.' : ' — продукция собственного производства ' . e(setting('site_name')) . '.')
        . ($p['material'] ? ' Материал: ' . e($p['material']) . '.' : '')
        . ($pack ? ' Поставляется упаковками по ' . fmt_qty($pack) . ' шт.' : '') . '</p>'
        . '<p>Продаём оптом строительным компаниям, подрядчикам и магазинам. Отгрузка со склада в Краснодаре, доставка по России транспортными компаниями. '
        . 'Оплата по счёту после согласования заказа с менеджером' . ($docs ? ', документы на продукцию — во вкладке «Документы».' : '.') . '</p>';
}
$showPrice = $priceNum > 0 && setting('show_prices') === '1';
$ldProduct = array_filter([
    '@context' => 'https://schema.org', '@type' => 'Product',
    'name' => $fullName, 'sku' => $p['sku'] ?: null, 'category' => $p['category_name'] ?: null,
    'description' => seo_cut($p['description'] ?: $autoDesc, 500),
    'image' => $photos ? array_map(fn($f) => str_starts_with($f, 'assets/') ? abs_url($f) : abs_url('uploads/' . $f), $photos) : null,
    'url' => abs_url(product_url($p)),
    'material' => $p['material'] ?: null,
    'size' => $p['size'] ?: null,
    'brand' => ['@type' => 'Brand', 'name' => setting('site_name')],
    'manufacturer' => ['@id' => abs_url('') . '#organization', '@type' => 'Organization', 'name' => setting('legal_name') ?: setting('site_name')],
    'additionalProperty' => array_values(array_map(fn($s) => ['@type' => 'PropertyValue', 'name' => $s[0], 'value' => $s[1]], array_filter($specs, fn($s) => $s[0] !== 'Как заказать'))) ?: null,
    'offers' => $showPrice ? array_filter([
        '@type' => 'Offer', 'url' => abs_url(product_url($p)), 'priceCurrency' => 'RUB', 'price' => round($priceNum, 2),
        'availability' => 'https://schema.org/InStock', 'itemCondition' => 'https://schema.org/NewCondition',
        'businessFunction' => 'http://purl.org/goodrelations/v1#Sell',
        'priceSpecification' => $pack ? ['@type' => 'UnitPriceSpecification', 'price' => round($priceNum, 2), 'priceCurrency' => 'RUB',
            'referenceQuantity' => ['@type' => 'QuantitativeValue', 'value' => $pack, 'unitCode' => 'H87']] : null,
        'eligibleQuantity' => min_order() > 0 ? ['@type' => 'QuantitativeValue', 'minValue' => min_order(), 'unitCode' => 'H87'] : null,
        'seller' => ['@type' => 'Organization', 'name' => setting('legal_name') ?: setting('site_name')],
    ], fn($v) => $v !== null) : null,
], fn($v) => $v !== null);
$crumbs = [['Главная', ''], ['Каталог', 'catalog']];
if ($p['category_id']) $crumbs[] = [$p['category_name'], $catLink];
$crumbs[] = [$fullName, product_url($p)];
$mainImg = $photos && ($media[0]['kind'] ?? 'image') === 'image' ? img_variant($photos[0], 800) : null;
page_header($fullName, [
    'title_raw' => $p['seo_title'] ?: $fullName . ' оптом' . ($showPrice ? ', цена ' . fmt_money($priceNum) . ($pack ? '/уп.' : '') : ' от производителя') . ' — ' . setting('site_name'),
    'description' => $p['seo_description'] ?: ($p['description'] ? seo_cut($p['description']) : $autoDesc),
    'canonical' => product_url($p),
    'image' => $photos[0] ?? '',
    'image_alt' => $fullName,
    'preload_image' => $mainImg['src'] ?? '',
    'type' => 'product',
    // Без цены разметка Product считается в Google неполной — тогда передаём только хлебные крошки
    'jsonld' => $showPrice ? [$ldProduct, ld_breadcrumbs($crumbs)] : [ld_breadcrumbs($crumbs)],
]);
?>
<div class="container page-pad">
  <nav class="crumbs" aria-label="Навигация"><a href="<?= url() ?>">Главная</a> / <a href="<?= url('catalog') ?>">Каталог</a><?php if ($p['category_id']): ?> / <a href="<?= url($catLink) ?>"><?= e($p['category_name']) ?></a><?php endif; ?></nav>

  <div class="product">
    <div class="product-gallery">
      <?php if ($media): $n = count($media); ?>
        <div class="pgal" data-carousel aria-roledescription="карусель" aria-label="Фото и видео товара">
          <div class="pgal-track" data-track tabindex="0">
            <?php foreach ($media as $i => $m): $kind = $m['kind'] ?? 'image'; ?>
              <div class="pgal-slide" data-slide role="group" aria-roledescription="слайд" aria-label="<?= $i + 1 ?> из <?= $n ?>">
                <?php if ($kind === 'video'): ?>
                  <video src="<?= e(upload_url($m['file'])) ?>#t=0.1" controls playsinline preload="metadata"></video>
                <?php elseif ($kind === 'embed'): ?>
                  <iframe data-src="<?= e($m['file']) ?>" title="Видео: <?= e($fullName) ?>" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen loading="lazy"<?= $i === 0 ? ' src="' . e($m['file']) . '"' : '' ?>></iframe>
                <?php else: ?>
                  <?php $big = img_variant($m['file'], 1600); $med = img_variant($m['file'], 800); ?>
                  <img src="<?= e($med['src']) ?>"<?php if ($big['w'] && $med['w'] && $big['w'] > $med['w']): ?> srcset="<?= e($med['src']) ?> <?= $med['w'] ?>w, <?= e($big['src']) ?> <?= $big['w'] ?>w" sizes="(max-width: 900px) 100vw, 560px"<?php endif; ?> alt="<?= e($fullName) ?><?= $n > 1 ? ' — фото ' . ($i + 1) : '' ?>"<?php if ($med['w'] && $med['h']): ?> width="<?= $med['w'] ?>" height="<?= $med['h'] ?>"<?php endif; ?><?= $i ? ' loading="lazy" decoding="async"' : ' fetchpriority="high"' ?>>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($n > 1): ?>
            <button type="button" class="pgal-nav pgal-prev" data-prev aria-label="Предыдущее">‹</button>
            <button type="button" class="pgal-nav pgal-next" data-next aria-label="Следующее">›</button>
            <span class="pgal-count" data-count aria-live="polite">1 / <?= $n ?></span>
          <?php endif; ?>
        </div>
        <?php if ($n > 1): ?>
          <div class="thumbs" aria-label="Все фото и видео">
            <?php foreach ($media as $i => $m): $kind = $m['kind'] ?? 'image'; ?>
              <button type="button" class="thumb-btn<?= $i === 0 ? ' on' : '' ?><?= $kind !== 'image' ? ' is-video' : '' ?>" data-thumb="<?= $i ?>" aria-label="<?= $kind === 'image' ? 'Фото' : 'Видео' ?> <?= $i + 1 ?> из <?= $n ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>>
                <?php if ($kind === 'image'): ?><?= img_tag($m['file'], 80, '', ['loading' => 'lazy', 'decoding' => 'async']) ?>
                <?php elseif ($kind === 'video'): ?><video src="<?= e(upload_url($m['file'])) ?>#t=0.5" preload="metadata" muted playsinline tabindex="-1"></video>
                <?php elseif ($th = video_embed_thumb($m['file'])): ?><img src="<?= e($th) ?>" alt="" loading="lazy"><?php endif; ?>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
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
        <div class="buy-price"><span class="muted"><?= e(setting('price_note')) ?></span><b><?= price_label($p) ?><?php if (price_label($p) !== 'По запросу'): ?><span class="muted" style="font:500 15px var(--font)"> / <?= $pack ? 'упаковка' : 'шт.' ?></span><?php endif; ?></b>
          <?php if ($pack): ?><span class="muted small">В упаковке <?= fmt_qty($pack) ?> шт.<?= price_label($p) !== 'По запросу' ? ' · ' . fmt_money(round(price_per_piece($p), 2)) . ' за шт.' : '' ?></span><?php endif; ?></div>
        <div class="buy-row">
          <?= qty_input('qty', $qtyValue, $step, '', $pack) ?>
          <button class="btn btn-accent btn-lg" type="submit"><?= $inCart ? 'Обновить в корзине' : 'Добавить в корзину' ?></button>
        </div>
        <?php if ($inCart): ?><a href="<?= url('cart.php') ?>"><b>Товар в корзине — перейти к оформлению →</b></a><?php endif; ?>
        <span class="muted small"><?= min_order() > 0 ? 'Минимальный заказ — ' . fmt_qty(min_order()) . ' шт. по всей корзине. ' : '' ?><?= $pack ? 'Укажите количество упаковок, в упаковке ' . fmt_qty($pack) . ' шт.' : 'Заказ кратен ' . fmt_qty($step) . ' шт.' ?></span>
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
          <?php if (trim(strip_tags((string)$p['description'])) !== ''): ?><div class="content" style="margin-top:20px; font-size:16px"><?= $p['description'] ?></div>
          <?php else: ?><div class="content" style="margin-top:20px; font-size:16px"><?= $autoText ?></div><?php endif; ?>
        </div>
        <div id="t-docs" role="tabpanel" aria-labelledby="b-docs" hidden>
          <?php if ($docs): ?>
          <ul class="doc-list">
            <?php foreach ($docs as $d): ?>
              <li><a class="doc" href="<?= e(upload_url($d['file'])) ?>" target="_blank" rel="noopener"><?= icon('doc', 26) ?><span><?= e($d['title']) ?></span><em>Скачать</em></a></li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
            <p class="muted">Документы на этот товар скоро появятся. Общие сертификаты — в разделе <a href="<?= url('certificates') ?>">«Сертификаты»</a>.</p>
          <?php endif; ?>
        </div>
        <div id="t-ship" role="tabpanel" aria-labelledby="b-ship" hidden>
          <p><?= e(setting('delivery_note')) ?></p>
          <p>Оплата — по счёту после подтверждения заказа менеджером. Онлайн-оплата на сайте не предусмотрена. <a href="<?= url(page_url('delivery')) ?>">Подробнее о доставке и оплате</a></p>
        </div>
      </div>
    </div>
  </div>

  <?php if ($related): ?>
  <section style="margin-top:56px">
    <h2>Другие позиции раздела «<?= e($p['category_name']) ?>»</h2>
    <div class="prod-grid">
      <?php foreach ($related as $r): ?>
        <a class="prod-card" href="<?= url(product_url($r)) ?>">
          <?= product_thumb($r, 'thumb', 280) ?>
          <b><?= e($r['name']) ?> <?= e($r['size']) ?></b>
          <span><b><?= price_label($r) ?></b></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
<?php page_footer();
