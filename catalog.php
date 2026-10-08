<?php
require __DIR__ . '/inc/bootstrap.php';

$cats = q('SELECT * FROM categories WHERE active = 1 ORDER BY sort, id')->fetchAll();
$catId = (int)($_GET['cat'] ?? 0);
$search = trim((string)($_GET['q'] ?? ''));
$selSizes = array_filter((array)($_GET['size'] ?? []), 'is_string');
$selTypes = array_filter((array)($_GET['type'] ?? []), 'is_string');
$sort = (string)($_GET['sort'] ?? '');

$where = ['p.active = 1'];
$params = [];
if ($catId) {
    $where[] = 'p.category_id = ?';
    $params[] = $catId;
}
if ($selSizes) {
    $where[] = 'p.size IN (' . implode(',', array_fill(0, count($selSizes), '?')) . ')';
    array_push($params, ...$selSizes);
}
if ($selTypes) {
    $where[] = 'p.type IN (' . implode(',', array_fill(0, count($selTypes), '?')) . ')';
    array_push($params, ...$selTypes);
}
$order = match ($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name'       => 'p.name ASC',
    default      => 'p.sort, p.id',
};
$rows = q('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id
           WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order, $params)->fetchAll();

// Поиск без учёта регистра (в SQLite LOWER не работает с кириллицей)
if ($search !== '') {
    $needle = mb_strtolower(str_replace(['x', 'х', '*'], '×', $search));
    $rows = array_values(array_filter($rows, function ($p) use ($needle) {
        $hay = mb_strtolower($p['name'] . ' ' . $p['size'] . ' ' . $p['sku'] . ' ' . $p['material'] . ' ' . $p['type']);
        $hay = str_replace(['x', 'х', '*'], '×', $hay);
        return mb_strpos($hay, $needle) !== false;
    }));
}

// Значения для фильтров (в пределах раздела)
$fParams = $catId ? [$catId] : [];
$fWhere = 'active = 1' . ($catId ? ' AND category_id = ?' : '');
$sizes = q("SELECT DISTINCT size FROM products WHERE $fWhere AND size <> '' ORDER BY size", $fParams)->fetchAll(PDO::FETCH_COLUMN);
$types = q("SELECT DISTINCT type FROM products WHERE $fWhere AND type <> '' ORDER BY type", $fParams)->fetchAll(PDO::FETCH_COLUMN);
natsort($sizes);

$curCat = null;
foreach ($cats as $c) {
    if ((int)$c['id'] === $catId) {
        $curCat = $c;
    }
}
$cart = cart_raw();
$min = min_order();
$inCart = cart_total_qty();

page_header($curCat['name'] ?? 'Каталог');
?>
<div class="container page-pad">
  <nav class="crumbs" aria-label="Навигация"><a href="<?= url() ?>">Главная</a> / <?php if ($curCat): ?><a href="<?= url('catalog.php') ?>">Каталог</a> / <?= e($curCat['name']) ?><?php else: ?>Каталог<?php endif; ?></nav>
  <div class="section-head">
    <h1 style="margin:0"><?= e($curCat['name'] ?? 'Каталог') ?></h1>
    <span class="muted small">Найдено позиций: <?= count($rows) ?></span>
  </div>

  <div class="chips">
    <a class="chip <?= !$catId ? 'active' : '' ?>" href="<?= url('catalog.php') ?>">Все товары</a>
    <?php foreach ($cats as $c): ?>
      <a class="chip <?= $catId === (int)$c['id'] ? 'active' : '' ?>" href="<?= url('catalog.php?cat=' . $c['id']) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="catalog-layout">
    <details class="filters" data-filters open>
      <summary>Фильтры и поиск</summary>
      <form class="filters-form" method="get" action="<?= url('catalog.php') ?>">
      <?php if ($catId): ?><input type="hidden" name="cat" value="<?= $catId ?>"><?php endif; ?>
      <label class="field">Поиск по названию
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Например, 10×120">
      </label>
      <?php if ($sizes): ?>
      <fieldset><legend>Размер</legend>
        <?php foreach ($sizes as $s): ?>
          <label class="check"><input type="checkbox" name="size[]" value="<?= e($s) ?>" <?= in_array($s, $selSizes, true) ? 'checked' : '' ?>> <?= e($s) ?></label>
        <?php endforeach; ?>
      </fieldset>
      <?php endif; ?>
      <?php if ($types): ?>
      <fieldset><legend>Тип</legend>
        <?php foreach ($types as $t): ?>
          <label class="check"><input type="checkbox" name="type[]" value="<?= e($t) ?>" <?= in_array($t, $selTypes, true) ? 'checked' : '' ?>> <?= e($t) ?></label>
        <?php endforeach; ?>
      </fieldset>
      <?php endif; ?>
      <label class="field">Сортировка
        <select name="sort">
          <option value="">По умолчанию</option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>По названию</option>
          <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Сначала дешевле</option>
          <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Сначала дороже</option>
        </select>
      </label>
      <button class="btn btn-dark" type="submit">Применить</button>
      <a class="btn btn-outline" href="<?= url('catalog.php' . ($catId ? '?cat=' . $catId : '')) ?>">Сбросить</a>
      </form>
    </details>

    <div class="catalog-main">
      <div class="notice">
        <span><b>Минимальный заказ — <?= fmt_qty($min) ?> шт.</b> по всей корзине</span>
        <span>В корзине: <b><?= fmt_qty($inCart) ?> шт.</b></span>
        <a class="push" href="<?= url('cart.php') ?>">Перейти в корзину →</a>
      </div>
      <?php if (!$rows): ?>
        <p class="empty">Ничего не найдено — измените запрос или фильтры.</p>
      <?php else: ?>
      <div class="table-wrap has-cards">
        <table class="table cards">
          <thead><tr><th><span class="sr-only">Фото</span></th><th>Наименование</th><th>Размер</th><th>Фасовка</th><th class="num">Цена, опт</th><th>Количество, шт.</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $p): $step = product_step($p); ?>
            <tr>
              <td class="c-img"><a href="<?= url('product.php?id=' . $p['id']) ?>"><?= product_thumb($p) ?></a></td>
              <td><a class="pname" href="<?= url('product.php?id=' . $p['id']) ?>"><?= e($p['name']) ?></a>
                <div class="sub"><?= e($p['category_name']) ?><?= $p['sku'] ? ' · арт. ' . e($p['sku']) : '' ?><?= $p['material'] ? ' · ' . e($p['material']) : '' ?></div></td>
              <td><?= e($p['size'] ?: '—') ?></td>
              <td class="muted c-hide"><?= $p['pack_qty'] ? fmt_qty($p['pack_qty']) . ' шт./уп.' : '—' ?></td>
              <td class="num price"><?= price_label($p) ?></td>
              <td class="c-act">
                <form class="row-form" method="post" action="<?= url('cart.php') ?>">
                  <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                  <?= qty_input('qty', max($step, (int)($cart[$p['id']] ?? $step * 2)), $step) ?>
                  <?php if (isset($cart[$p['id']])): ?>
                    <button class="btn btn-outline" type="submit">Обновить</button>
                  <?php else: ?>
                    <button class="btn btn-accent" type="submit">В корзину</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php page_footer();
