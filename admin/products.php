<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin('products');

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
                'seo_title'   => mb_substr(post('seo_title'), 0, 120),
                'seo_description' => mb_substr(post('seo_description'), 0, 300),
            ];
            $old = $id ? q('SELECT * FROM products WHERE id = ?', [$id])->fetch() : null;
            $newImages = handle_uploads('images', ['jpg', 'png', 'webp', 'mp4', 'webm', 'mov'], 'products');
            // Ссылка на видео (Rutube, VK Видео, YouTube)
            $videoUrl = trim(post('video_url'));
            $embed = $videoUrl !== '' ? video_embed_url($videoUrl) : null;
            if ($videoUrl !== '' && !$embed) {
                flash('Ссылка на видео не распознана. Подходят ссылки Rutube, VK Видео и YouTube.', 'error');
            }
            if ($old) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
                q("UPDATE products SET $set WHERE id = ?", [...array_values($data), $id]);
            } else {
                $cols = implode(', ', array_keys($data));
                q("INSERT INTO products ($cols) VALUES (" . implode(',', array_fill(0, count($data), '?')) . ')', array_values($data));
                $id = (int)db()->lastInsertId();
            }
            // Новые фото добавляются в конец галереи; первое фото — главное
            if ($newImages || $embed) {
                $sort = (int)q('SELECT COALESCE(MAX(sort), -1) FROM product_images WHERE product_id = ?', [$id])->fetchColumn();
                foreach ($newImages as $file) {
                    q('INSERT INTO product_images (product_id, file, sort, kind) VALUES (?,?,?,?)', [$id, $file, ++$sort, media_kind_by_file($file)]);
                }
                if ($embed) {
                    q("INSERT INTO product_images (product_id, file, sort, kind) VALUES (?,?,?,'embed')", [$id, $embed, ++$sort]);
                    $newImages[] = $embed;
                }
                product_sync_cover($id);
            }
            // Документы товара
            $doc = handle_upload('doc_file', ['pdf', 'jpg', 'png'], 'docs');
            if ($doc) {
                q('INSERT INTO documents (title, file, product_id) VALUES (?,?,?)', [post('doc_title') ?: 'Документ', $doc, $id]);
            }
            flash('Товар сохранён.' . ($newImages ? ' Добавлено фото и видео: ' . count($newImages) . '.' : ''));
            redirect('admin/products.php?edit=' . $id . ($newImages ? '#photos' : ''));
        }
        // Фото: удалить, сделать главным, переставить
        if (in_array($action, ['img_delete', 'img_main', 'img_move'], true) && $id) {
            $imgs = product_images($id);
            $imgId = (int)post('img_id');
            $ids = array_map(fn($i) => (int)$i['id'], $imgs);
            $pos = array_search($imgId, $ids, true);
            if ($pos !== false) {
                if ($action === 'img_delete') {
                    if (($imgs[$pos]['kind'] ?? 'image') !== 'embed') delete_upload($imgs[$pos]['file']);
                    q('DELETE FROM product_images WHERE id = ?', [$imgId]);
                    array_splice($ids, $pos, 1);
                } elseif ($action === 'img_main') {
                    array_splice($ids, $pos, 1);
                    array_unshift($ids, $imgId);
                } else {
                    $to = $pos + (post('dir') === 'left' ? -1 : 1);
                    if (isset($ids[$to])) {
                        [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];
                    }
                }
                foreach ($ids as $i => $iid) {
                    q('UPDATE product_images SET sort = ? WHERE id = ?', [$i, $iid]);
                }
                product_sync_cover($id);
            }
            flash($action === 'img_delete' ? 'Удалено.' : ($action === 'img_main' ? 'Перенесено на первое место.' : 'Порядок изменён.'));
            redirect('admin/products.php?edit=' . $id . '#photos');
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
            foreach (product_images($id) as $im) if (($im['kind'] ?? 'image') !== 'embed') delete_upload($im['file']);
            q('DELETE FROM product_images WHERE product_id = ?', [$id]);
            if ($p && $p['image'] && !q('SELECT 1 FROM product_images WHERE file = ?', [$p['image']])->fetchColumn()) delete_upload($p['image']);
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
                'pack_qty' => 0, 'price' => 0, 'step' => 0, 'description' => '', 'specs' => '', 'image' => '', 'popular' => 0, 'active' => 1, 'sort' => 0,
                'seo_title' => '', 'seo_description' => ''];
    $images = $id ? product_images($id) : [];
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
          <?= a_input('price', 'Цена, ₽', $p['price'] > 0 ? rtrim(rtrim(number_format((float)$p['price'], 2, '.', ''), '0'), '.') : '', 'text', 'За упаковку, если указано «Штук в упаковке»; иначе за штуку. 0 или пусто — «По запросу»', ['inputmode' => 'decimal']) ?>
          <?= a_input('pack_qty', 'Штук в упаковке', $p['pack_qty'] ?: '', 'number', 'Покупатель заказывает упаковками. Пусто — товар заказывают поштучно', ['min' => 0]) ?>
          <?= a_input('step', 'Кратность заказа, шт. (если нет фасовки)', $p['step'] ?: '', 'number', 'Только для товаров без фасовки. Пусто — по умолчанию (' . setting('default_step') . ' шт.)', ['min' => 0]) ?>
        </div>
      </div>
      <div class="a-card">
        <h2>Описание и характеристики</h2>
        <div class="a-form">
          <label class="a-field"><span>Дополнительные характеристики</span><textarea name="specs" rows="5" class="mono"><?= e($p['specs']) ?></textarea><small>По одной на строку в формате «Название: значение», например «Диаметр тарелки: 60 мм»</small></label>
          <?= a_textarea('description', 'Описание товара', $p['description'], '', true) ?>
        </div>
      </div>
      <div class="a-card" id="photos">
        <h2>Фото и видео <span class="a-muted" style="font-weight:400; font-size:14px"><?= $images ? count($images) . ' шт.' : '' ?></span></h2>
        <?php if ($images): ?>
          <div class="a-photos">
            <?php foreach ($images as $i => $im): ?>
              <div class="a-photo<?= $i === 0 ? ' is-main' : '' ?>">
                <?php $kind = $im['kind'] ?? 'image'; ?>
                <?php if ($kind === 'video'): ?>
                  <video src="<?= e(upload_url($im['file'])) ?>#t=0.5" preload="metadata" muted playsinline controls></video>
                <?php elseif ($kind === 'embed'): ?>
                  <div class="a-photo-embed"><?php if ($th = video_embed_thumb($im['file'])): ?><img src="<?= e($th) ?>" alt="" loading="lazy"><?php endif; ?><span>▶ <?= e(video_embed_service($im['file'])) ?></span></div>
                <?php else: ?>
                  <img src="<?= e(upload_url($im['file'])) ?>" alt="Фото <?= $i + 1 ?>" loading="lazy">
                <?php endif; ?>
                <div class="a-photo-bar">
                  <?php if ($i === 0): ?><span class="a-photo-main">Первое</span><?php else: ?><button type="submit" form="img-main-<?= $im['id'] ?>" title="Поставить первым">★ Первым</button><?php endif; ?>
                  <?php if ($i > 0): ?><button type="submit" form="img-left-<?= $im['id'] ?>" aria-label="Левее">←</button><?php endif; ?>
                  <?php if ($i < count($images) - 1): ?><button type="submit" form="img-right-<?= $im['id'] ?>" aria-label="Правее">→</button><?php endif; ?>
                  <button type="submit" class="del" form="img-del-<?= $im['id'] ?>" aria-label="Удалить фото">✕</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="a-grid">
          <label class="a-field"><span><?= $images ? 'Добавить фото или видео' : 'Фото и видео товара' ?></span>
            <input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.mov,image/*,video/mp4,video/webm,video/quicktime">
            <small>Можно выбрать сразу несколько файлов, до 100 МБ за один раз. Фото: JPG, PNG, WEBP, лучше квадратные от 800×800. Видео: MP4 (лучше всего), WEBM или MOV. Загрузятся при сохранении товара.</small></label>
          <?= a_input('video_url', 'Или ссылка на видео', '', 'text', 'Rutube, VK Видео или YouTube — для длинных и тяжёлых роликов. Видео покажется в карусели на странице товара') ?>
        </div>
        <p class="a-muted" style="margin:10px 0 0; font-size:13px">Порядок в карусели — как здесь. В каталоге показывается первое <b>фото</b> из списка.</p>
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
        <h2>SEO</h2>
        <p class="a-muted" style="margin-top:-6px">Можно не заполнять — сайт составит заголовок и описание сам из названия, размера, цены и фасовки.</p>
        <div class="a-grid">
          <?= a_input('seo_title', 'Заголовок для поисковиков (title)', $p['seo_title'], 'text', 'До 60–70 символов. Пусто — «' . e(trim($p['name'] . ' ' . $p['size'])) . ' оптом от производителя»', ['maxlength' => 120]) ?>
          <?= a_textarea('seo_description', 'Описание для поисковиков (description)', $p['seo_description'], 'До 160 символов. Показывается под ссылкой в Яндексе и Google', false, 2) ?>
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
 <?php foreach ($images as $im): ?>
      <form id="img-main-<?= $im['id'] ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="action" value="img_main"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="img_id" value="<?= $im['id'] ?>"></form>
      <form id="img-left-<?= $im['id'] ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="action" value="img_move"><input type="hidden" name="dir" value="left"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="img_id" value="<?= $im['id'] ?>"></form>
      <form id="img-right-<?= $im['id'] ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="action" value="img_move"><input type="hidden" name="dir" value="right"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="img_id" value="<?= $im['id'] ?>"></form>
      <form id="img-del-<?= $im['id'] ?>" method="post" data-confirm="Удалить из галереи?" hidden><?= csrf_field() ?><input type="hidden" name="action" value="img_delete"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="img_id" value="<?= $im['id'] ?>"></form>
    <?php endforeach; ?>
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
<div class="a-head"><h1>Товары</h1><div class="a-actions"><a class="a-btn a-btn-out" href="<?= url('admin/products_io.php') ?>">Excel: выгрузка и загрузка</a><a class="a-btn" href="<?= url('admin/products.php?edit=0' . ($catF ? '&cat=' . $catF : '')) ?>">+ Добавить товар</a></div></div>
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
      <td><input class="a-input" form="bulk" name="price[<?= $p['id'] ?>]" value="<?= $p['price'] > 0 ? e(rtrim(rtrim(number_format((float)$p['price'], 2, '.', ''), '0'), '.')) : '' ?>" placeholder="по запросу" style="width:110px" inputmode="decimal"> <span class="a-muted" style="font-size:12px">за <?= (int)$p['pack_qty'] > 0 ? 'уп.' : 'шт.' ?></span></td>
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
