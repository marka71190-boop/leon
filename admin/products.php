<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$cats = q('SELECT * FROM categories ORDER BY sort, id')->fetchAll();

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int)post('id');

    try {
        if ($action === 'save') {
            $name = post('name');
            if ($name === '') {
                throw new RuntimeException('Укажите наименование товара.');
            }
            $data = [
                'category_id' => (int)post('category_id') ?: null,
                'name'        => $name,
                'sku'         => post('sku'),
                'size'        => str_replace(['x', 'х', '*'], '×', post('size')),
                'material'    => post('material'),
                'type'        => post('type'),
                'pack_qty'    => max(0, (int)post('pack_qty')),
                'price'       => max(0, (float)str_replace([',', ' '], ['.', ''], post('price'))),
                'step'        => max(0, (int)post('step')),
                'description' => (string)($_POST['description'] ?? ''),
                'specs'       => post('specs'),
                'popular'     => post('popular') === '1' ? 1 : 0,
                'active'      => post('active') === '1' ? 1 : 0,
                'sort'        => (int)post('sort'),
            ];
            $old = $id ? q('SELECT * FROM products WHERE id = ?', [$id])->fetch() : null;
            $img = handle_upload('image', ['jpg', 'png', 'webp'], 'products');
            if ($img) {
                $data['image'] = $img;
                if ($old) delete_upload($old['image']);
            } elseif ($old && post('image_remove') === '1') {
                delete_upload($old['image']);
                $data['image'] = '';
            }
            if ($old) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
                q("UPDATE products SET $set WHERE id = ?", [...array_values($data), $id]);
            } else {
                $cols = implode(', ', array_keys($data));
                q("INSERT INTO products ($cols) VALUES (" . implode(',', array_fill(0, count($data), '?')) . ')', array_values($data));
                $id = (int)db()->lastInsertId();
            }
            // Документы товара
            $doc = handle_upload('doc_file', ['pdf', 'jpg', 'png'], 'docs');
            if ($doc) {
                q('INSERT INTO documents (title, file, product_id) VALUES (?,?,?)', [post('doc_title') ?: 'Документ', $doc, $id]);
            }
            flash('Товар сохранён.');
            redirect('admin/products.php?edit=' . $id);
        }
        if ($action === 'copy' && $id) {
            $p = q('SELECT * FROM products WHERE id = ?', [$id])->fetch();
            unset($p['id'], $p['created_at']);
            $p['name'] .= ' (копия)';
            $p['image'] = '';
            $p['active'] = 0;
            q('INSERT INTO products (' . implode(',', array_keys($p)) . ') VALUES (' . implode(',', array_fill(0, count($p), '?')) . ')', array_values($p));
            $new = (int)db()->lastInsertId();
            flash('Копия создана и скрыта с сайта. Отредактируйте и включите её.');
            redirect('admin/products.php?edit=' . $new);
        }
        if ($action === 'toggle' && $id) {
            q('UPDATE products SET active = 1 - active WHERE id = ?', [$id]);
            back('admin/products.php');
        }
        if ($action === 'delete' && $id) {
            $p = q('SELECT * FROM products WHERE id = ?', [$id])->fetch();
            foreach (q('SELECT file FROM documents WHERE product_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN) as $f) delete_upload($f);
            delete_upload($p['image'] ?? '');
            q('DELETE FROM products WHERE id = ?', [$id]);
            flash('Товар удалён.');
            redirect('admin/products.php');
        }
        if ($action === 'doc_delete') {
            $d = q('SELECT * FROM documents WHERE id = ?', [(int)post('doc_id')])->fetch();
            if ($d) {
                delete_upload($d['file']);
                q('DELETE FROM documents WHERE id = ?', [$d['id']]);
            }
            flash('Документ удалён.');
            redirect('admin/products.php?edit=' . $id);
        }
        if ($action === 'bulk') {
            // Быстрое редактирование цен и сортировки из списка
            foreach ((array)($_POST['price'] ?? []) as $pid => $price) {
                q('UPDATE products SET price = ?, sort = ? WHERE id = ?', [
                    max(0, (float)str_replace([',', ' '], ['.', ''], (string)$price)),
                    (int)($_POST['sort'][$pid] ?? 0), (int)$pid,
                ]);
            }
            flash('Цены сохранены.');
            back('admin/products.php');
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'error');
        back('admin/products.php');
    }
}

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $p = $id ? q('SELECT * FROM products WHERE id = ?', [$id])->fetch() : null;
    if ($id && !$p) redirect('admin/products.php');
    $p = $p ?: ['id' => 0, 'category_id' => (int)($_GET['cat'] ?? 0), 'name' => '', 'sku' => '', 'size' => '', 'material' => '', 'type' => '',
                'pack_qty' => 0, 'price' => 0, 'step' => 0, 'description' => '', 'specs' => '', 'image' => '', 'popular' => 0, 'active' => 1, 'sort' => 0];
    $docs = $id ? q('SELECT * FROM documents WHERE product_id = ? ORDER BY sort, id', [$id])->fetchAll() : [];
    admin_header($id ? 'Товар' : 'Новый товар', 'products', true);
    ?>
    <div class="a-head"><h1><?= $id ? e($p['name'] . ' ' . $p['size']) : 'Новый товар' ?></h1><div class="a-actions"><?php if ($id): ?><a class="a-btn a-btn-out a-btn-sm" href="<?= url('product.php?id=' . $id) ?>" target="_blank">Открыть на сайте ↗</a><?php endif; ?><a href="<?= url('admin/products.php') ?>">← Все товары</a></div></div>
    <form method="post" enctype="multipart/form-data" class="a-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <div class="a-card">
        <h2>Основное</h2>
        <div class="a-grid">
          <div class="a-field-wide"><?= a_input('name', 'Наименование *', $p['name'], 'text', '', ['required' => true]) ?></div>
          <label class="a-field"><span>Раздел каталога</span><select name="category_id"><option value="">— без раздела —</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= (int)$p['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
          <?= a_input('sku', 'Артикул', $p['sku']) ?>
          <?= a_input('size', 'Размер', $p['size'], 'text', 'Например, 10×120 — используется в фильтре') ?>
          <?= a_input('type', 'Тип', $p['type'], 'text', 'Например, «Пластиковый гвоздь» — используется в фильтре') ?>
          <?= a_input('material', 'Материал', $p['material']) ?>
        </div>
      </div>
      <div class="a-card">
        <h2>Цена и фасовка</h2>
        <div class="a-grid">
          <?= a_input('price', 'Оптовая цена, ₽ за шт.', $p['price'] > 0 ? rtrim(rtrim(number_format((float)$p['price'], 2, '.', ''), '0'), '.') : '', 'text', '0 или пусто — «По запросу»', ['inputmode' => 'decimal']) ?>
          <?= a_input('pack_qty', 'Штук в упаковке', $p['pack_qty'] ?: '', 'number', '', ['min' => 0]) ?>
          <?= a_input('step', 'Кратность заказа, шт.', $p['step'] ?: '', 'number', 'Пусто — по умолчанию (' . setting('default_step') . ' шт.)', ['min' => 0]) ?>
        </div>
      </div>
      <div class="a-card">
        <h2>Описание и характеристики</h2>
        <div class="a-form">
          <label class="a-field"><span>Дополнительные характеристики</span><textarea name="specs" rows="5" class="mono"><?= e($p['specs']) ?></textarea><small>По одной на строку в формате «Название: значение», например «Диаметр тарелки: 60 мм»</small></label>
          <?= a_textarea('description', 'Описание товара', $p['description'], '', true) ?>
        </div>
      </div>
      <div class="a-card">
        <h2>Фото</h2>
        <?= a_image('image', 'Фото товара', $p['image'], 'JPG, PNG или WEBP, лучше квадратное от 800×800') ?>
      </div>
      <div class="a-card">
        <h2>Документы товара</h2>
        <?php if ($docs): ?>
          <div class="a-table-wrap" style="margin-bottom:14px"><table class="a-table"><tbody>
          <?php foreach ($docs as $d): ?>
            <tr><td><a href="<?= e(upload_url($d['file'])) ?>" target="_blank"><?= e($d['title']) ?></a></td><td class="num"><button class="a-btn a-btn-danger a-btn-sm" type="submit" form="docdel-<?= $d['id'] ?>">Удалить</button></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
        <div class="a-grid">
          <?= a_input('doc_title', 'Название документа', '', 'text', 'Например, «Сертификат соответствия»') ?>
          <label class="a-field"><span>Файл</span><input type="file" name="doc_file" accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPG или PNG. Загрузится при сохранении товара.</small></label>
        </div>
      </div>
      <div class="a-card">
        <h2>Показ на сайте</h2>
        <div class="a-grid">
          <div><?= a_check('active', 'Показывать на сайте', (bool)$p['active']) ?><?= a_check('popular', 'Показывать в «Ходовых позициях» на главной', (bool)$p['popular']) ?></div>
          <?= a_input('sort', 'Порядок сортировки', $p['sort'], 'number', 'Меньше — выше в списке') ?>
        </div>
      </div>
      <div class="a-save"><button class="a-btn" type="submit">Сохранить товар</button><a class="a-btn a-btn-out" href="<?= url('admin/products.php') ?>">Отмена</a></div>
    </form>
    <?php foreach ($docs as $d): ?>
      <form id="docdel-<?= $d['id'] ?>" method="post" data-confirm="Удалить документ?"><?= csrf_field() ?><input type="hidden" name="action" value="doc_delete"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="doc_id" value="<?= $d['id'] ?>"></form>
    <?php endforeach; ?>
    <?php if ($id): ?>
      <div class="a-actions" style="justify-content:flex-start; margin-top:12px">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="copy"><input type="hidden" name="id" value="<?= $id ?>"><button class="a-btn a-btn-out a-btn-sm" type="submit">Создать копию</button></form>
        <form method="post" data-confirm="Удалить товар? Он пропадёт из каталога; в старых заказах останется."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>"><button class="a-btn a-btn-danger a-btn-sm" type="submit">Удалить товар</button></form>
      </div>
    <?php endif; ?>
    <?php
    admin_footer();
    exit;
}

$catF = (int)($_GET['cat'] ?? 0);
$products = q('SELECT p.*, c.name AS cname, (SELECT COUNT(*) FROM documents d WHERE d.product_id = p.id) AS docs
               FROM products p LEFT JOIN categories c ON c.id = p.category_id' . ($catF ? ' WHERE p.category_id = ' . $catF : '') . ' ORDER BY c.sort, p.sort, p.id')->fetchAll();

admin_header('Товары', 'products');
?>
<div class="a-head"><h1>Товары</h1><a class="a-btn" href="<?= url('admin/products.php?edit=0' . ($catF ? '&cat=' . $catF : '')) ?>">+ Добавить товар</a></div>
<div class="a-filters">
  <a class="a-chip <?= !$catF ? 'on' : '' ?>" href="<?= url('admin/products.php') ?>">Все</a>
  <?php foreach ($cats as $c): ?><a class="a-chip <?= $catF === (int)$c['id'] ? 'on' : '' ?>" href="<?= url('admin/products.php?cat=' . $c['id']) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
</div>
<form method="post" id="bulk"><?= csrf_field() ?><input type="hidden" name="action" value="bulk"></form>
<div class="a-table-wrap"><table class="a-table">
  <thead><tr><th></th><th>Наименование</th><th>Раздел</th><th>Размер</th><th>Цена, ₽</th><th>Порядок</th><th>Док.</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($products as $p): ?>
    <tr class="<?= $p['active'] ? '' : 'a-off' ?>">
      <td><?= $p['image'] ? '<img src="' . e(upload_url($p['image'])) . '" alt="">' : '' ?></td>
      <td><a href="<?= url('admin/products.php?edit=' . $p['id']) ?>"><b><?= e($p['name']) ?></b></a><?= $p['popular'] ? ' <span class="pill">на главной</span>' : '' ?><?= $p['active'] ? '' : ' <span class="pill">скрыт</span>' ?></td>
      <td><?= e($p['cname'] ?? '—') ?></td>
      <td><?= e($p['size'] ?: '—') ?></td>
      <td><input class="a-input" form="bulk" name="price[<?= $p['id'] ?>]" value="<?= $p['price'] > 0 ? e(rtrim(rtrim(number_format((float)$p['price'], 2, '.', ''), '0'), '.')) : '' ?>" placeholder="по запросу" style="width:110px" inputmode="decimal"></td>
      <td><input class="a-input" form="bulk" name="sort[<?= $p['id'] ?>]" value="<?= (int)$p['sort'] ?>" style="width:70px" type="number"></td>
      <td><?= (int)$p['docs'] ?></td>
      <td><div class="a-actions">
        <a class="a-btn a-btn-out a-btn-sm" href="<?= url('admin/products.php?edit=' . $p['id']) ?>">Изменить</a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="a-btn a-btn-out a-btn-sm" type="submit"><?= $p['active'] ? 'Скрыть' : 'Показать' ?></button></form>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($products): ?><div class="a-save"><button class="a-btn" type="submit" form="bulk">Сохранить цены и порядок</button></div><?php endif; ?>
<?php admin_footer();
