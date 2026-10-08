<?php
require __DIR__ . '/inc/bootstrap.php';

$cats = q('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.active = 1) AS cnt
           FROM categories c WHERE c.active = 1 ORDER BY c.sort, c.id')->fetchAll();
$popular = q('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.active = 1 AND p.popular = 1 ORDER BY p.sort, p.id LIMIT 8')->fetchAll();
$min = min_order();

page_header();
?>
<section class="container hero">
  <div class="hero-text">
    <span class="eyebrow"><?= e(setting('hero_eyebrow')) ?></span>
    <h1><?= e(setting('hero_title')) ?></h1>
    <p><?= nl2br(e(setting('hero_text'))) ?></p>
    <div class="hero-actions">
      <a class="btn btn-accent btn-lg" href="<?= url('catalog.php') ?>">Открыть каталог</a>
      <a class="btn btn-outline btn-lg" href="#order">Как оформить заказ</a>
    </div>
    <div class="facts">
      <div class="fact"><b>от <?= fmt_qty($min) ?> шт.</b><span>минимальный заказ</span></div>
      <div class="fact"><b>Своё производство</b><span>и склад в Краснодаре</span></div>
      <div class="fact"><b>Документы</b><span>сертификаты и паспорта качества</span></div>
    </div>
  </div>
  <?php if (setting('hero_image')): ?>
    <div class="hero-visual has-img"><img src="<?= e(upload_url(setting('hero_image'))) ?>" alt=""></div>
  <?php else: ?>
    <div class="hero-visual"><img class="hero-logo" src="<?= asset('assets/logo/leonpro-animated-once-for-light-bg.svg') ?>" alt="<?= e(setting('site_name')) ?>" width="380" height="302"><span class="slogan"><?= e(setting('slogan')) ?></span></div>
  <?php endif; ?>
</section>

<section class="container section" style="padding-top: 16px">
  <div class="section-head">
    <h2>Каталог продукции</h2>
    <a href="<?= url('catalog.php') ?>"><b>Весь каталог</b></a>
  </div>
  <div class="cat-grid">
    <?php foreach ($cats as $c): ?>
      <a class="cat-card" href="<?= url('catalog.php?cat=' . $c['id']) ?>">
        <div class="cat-img"><?= $c['image'] ? '<img src="' . e(upload_url($c['image'])) . '" alt="" loading="lazy">' : '<span style="opacity:.3">' . logo_mark(70) . '</span>' ?></div>
        <div class="cat-body">
          <b><?= e($c['name']) ?></b>
          <span class="muted small"><?= e($c['description']) ?></span>
          <span class="more">Позиций: <?= (int)$c['cnt'] ?> →</span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($popular): ?>
<section class="section section-soft">
  <div class="container">
    <div class="section-head">
      <h2>Ходовые позиции</h2>
      <span class="muted small"><?= e(setting('price_note')) ?></span>
    </div>
    <div class="table-wrap has-cards">
      <table class="table table-dark cards">
        <thead><tr><th><span class="sr-only">Фото</span></th><th>Наименование</th><th>Размер</th><th>Фасовка</th><th class="num">Цена, опт</th><th><span class="sr-only">Действие</span></th></tr></thead>
        <tbody>
        <?php foreach ($popular as $p): ?>
          <tr>
            <td class="c-img"><?= product_thumb($p) ?></td>
            <td><a class="pname" href="<?= url('product.php?id=' . $p['id']) ?>"><?= e($p['name']) ?></a><div class="sub"><?= e($p['category_name']) ?></div></td>
            <td><?= e($p['size'] ?: '—') ?></td>
            <td class="muted"><?= $p['pack_qty'] ? fmt_qty($p['pack_qty']) . ' шт./уп.' : '—' ?></td>
            <td class="num price"><?= price_label($p) ?></td>
            <td class="c-act" style="text-align:right"><a class="btn btn-outline" href="<?= url('product.php?id=' . $p['id']) ?>">Подробнее</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php endif; ?>

<section id="order" class="container section">
  <h2 style="margin-bottom:8px">Как проходит заказ</h2>
  <p class="muted" style="margin:0 0 28px">Онлайн-оплаты нет: стоимость и доставку менеджер согласует с вами после заявки.</p>
  <ol class="steps">
    <li><span class="n">1</span><b>Соберите корзину</b><span>Выберите позиции и укажите количество. Минимальный заказ — <?= fmt_qty($min) ?> шт.</span></li>
    <li><span class="n">2</span><b>Оформите заявку</b><span>Название компании, ИНН, контактное лицо и город доставки.</span></li>
    <li><span class="n">3</span><b>Согласование</b><span>Менеджер свяжется, подтвердит состав, стоимость и транспортную компанию.</span></li>
    <li><span class="n">4</span><b>Отгрузка</b><span><?= e(setting('delivery_note')) ?></span></li>
  </ol>
</section>

<section class="container" style="padding-bottom:40px">
  <div class="two-col">
    <div class="panel panel-dark">
      <h2>Сертификаты и паспорта качества</h2>
      <p>Документы на продукцию — в разделе «Сертификаты» и в карточке каждого товара. Скачивайте для тендеров и исполнительной документации.</p>
      <a class="btn btn-accent" href="<?= url('certificates.php') ?>">Все документы</a>
    </div>
    <div class="panel">
      <h2>Доставка и оплата</h2>
      <p><?= e(setting('delivery_note')) ?> Оплата — по счёту после согласования заказа с менеджером.</p>
      <a href="<?= url('page.php?p=delivery') ?>"><b>Подробнее об условиях</b></a>
    </div>
  </div>
</section>
<?php page_footer();
