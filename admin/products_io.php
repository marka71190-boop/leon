<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/xlsx.php';
require __DIR__ . '/_layout.php';
require_admin('products');

// Выгрузка товаров в Excel и загрузка изменений обратно: сначала показываем, что изменится, потом применяем.

/** Столбцы файла: ключ => [заголовок, тип, ширина] */
function io_columns(): array
{
    return [
        'id'              => ['ID', 'id', 7],
        'sku'             => ['Артикул', 'text', 12],
        'name'            => ['Наименование', 'text', 46],
        'category'        => ['Раздел', 'text', 22],
        'size'            => ['Размер', 'text', 11],
        'type'            => ['Тип', 'text', 20],
        'material'        => ['Материал', 'text', 16],
        'pack_qty'        => ['Штук в упаковке', 'int', 11],
        'price'           => ['Цена, ₽ (за упаковку, если указана фасовка)', 'money', 16],
        'step'            => ['Кратность, шт. (если нет фасовки)', 'int', 14],
        'active'          => ['Показывать на сайте', 'bool', 11],
        'popular'         => ['На главной', 'bool', 10],
        'sort'            => ['Порядок', 'int', 9],
        'specs'           => ['Характеристики (Название: значение, по одной на строку)', 'text', 40],
        'seo_title'       => ['SEO-заголовок', 'text', 30],
        'seo_description' => ['SEO-описание', 'text', 40],
    ];
}

function io_norm(string $h): string
{
    return preg_replace('~[^a-zа-яё0-9]+~u', '', mb_strtolower(preg_replace('~\(.*$~u', '', $h)));
}

/** Значение из ячейки в нужный тип. Возвращает [значение, ошибка] */
function io_parse(string $type, string $raw): array
{
    $v = trim($raw);
    switch ($type) {
        case 'int':
        case 'id':
            $n = str_replace([' ', "\xC2\xA0"], '', $v);
            if (!preg_match('~^\d+(?:[.,]0+)?$~', $n)) return [null, 'нужно целое число'];
            return [(int)$n, null];
        case 'money':
            if (mb_stripos($v, 'запрос') !== false) return [0.0, null];
            $n = str_replace([' ', "\xC2\xA0", '₽', 'руб.', 'руб'], '', $v);
            $n = str_replace(',', '.', $n);
            if (!is_numeric($n) || (float)$n < 0) return [null, 'нужна цена числом'];
            return [round((float)$n, 2), null];
        case 'bool':
            $l = mb_strtolower($v);
            if (in_array($l, ['да', '1', 'yes', 'true', 'истина', '+'], true)) return [1, null];
            if (in_array($l, ['нет', '0', 'no', 'false', 'ложь', '-'], true)) return [0, null];
            return [null, 'нужно «да» или «нет»'];
        default:
            return [$v, null];
    }
}

/** Показ значения в предпросмотре */
function io_show(string $key, $v): string
{
    if ($v === null || $v === '') return '—';
    if ($key === 'active' || $key === 'popular') return $v ? 'да' : 'нет';
    if ($key === 'price') return (float)$v > 0 ? fmt_money($v) : 'по запросу';
    return mb_strimwidth((string)$v, 0, 80, '…');
}

$cols = io_columns();
$cats = q('SELECT id, name FROM categories ORDER BY sort, id')->fetchAll();
$catByName = [];
foreach ($cats as $c) $catByName[mb_strtolower(trim($c['name']))] = (int)$c['id'];
$catName = array_column($cats, 'name', 'id');

// ---------- Выгрузка ----------
if (isset($_GET['export'])) {
    $rows = [array_map(fn($c) => $c[0], $cols)];
    $list = q('SELECT p.* FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY COALESCE(c.sort, 999), p.sort, p.id')->fetchAll();
    foreach ($list as $p) {
        $rows[] = [
            (int)$p['id'], $p['sku'], $p['name'], $catName[$p['category_id']] ?? '', $p['size'], $p['type'], $p['material'],
            (int)$p['pack_qty'] ?: '', (float)$p['price'] > 0 ? (float)$p['price'] : '', (int)$p['step'] ?: '',
            $p['active'] ? 'да' : 'нет', $p['popular'] ? 'да' : 'нет', (int)$p['sort'], $p['specs'], $p['seo_title'] ?? '', $p['seo_description'] ?? '',
        ];
    }
    $bin = xlsx_build($rows, array_column($cols, 2), 'Товары');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="tovary-' . date('Y-m-d') . '.xlsx"; filename*=UTF-8\'\'' . rawurlencode('товары-' . date('Y-m-d') . '.xlsx'));
    header('Content-Length: ' . strlen($bin));
    echo $bin;
    exit;
}

$stash = static fn(string $token): string => DATA_DIR . '/import-' . preg_replace('~[^a-f0-9]~', '', $token) . '.json';

// ---------- Применение ----------
if (is_post() && post('action') === 'apply') {
    csrf_check();
    $file = $stash(post('token'));
    $plan = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    @unlink($file);
    if (!$plan) {
        flash('Предпросмотр устарел. Загрузите файл ещё раз.', 'error');
        redirect('admin/products_io.php');
    }
    $pdo = db();
    $pdo->beginTransaction();
    $updated = $created = $newCats = 0;
    $catId = static function (string $name) use (&$catByName, &$newCats): ?int {
        $k = mb_strtolower(trim($name));
        if ($k === '') return null;
        if (!isset($catByName[$k])) {
            q('INSERT INTO categories (name, sort) VALUES (?, ?)', [trim($name), 100 + count($catByName)]);
            $catByName[$k] = (int)db()->lastInsertId();
            $newCats++;
        }
        return $catByName[$k];
    };
    foreach ($plan as $row) {
        $data = $row['set'];
        if (array_key_exists('category', $data)) {
            $data['category_id'] = $catId((string)$data['category']);
            unset($data['category']);
        }
        if ($row['op'] === 'update' && $data) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
            q("UPDATE products SET $set WHERE id = ?", [...array_values($data), (int)$row['id']]);
            $updated++;
        } elseif ($row['op'] === 'create') {
            $data += ['active' => 1];
            q('INSERT INTO products (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')', array_values($data));
            $created++;
        }
    }
    $pdo->commit();
    flash('Загрузка выполнена: обновлено товаров — ' . $updated . ', добавлено — ' . $created . ($newCats ? ', новых разделов — ' . $newCats : '') . '.');
    redirect('admin/products.php');
}

// ---------- Разбор файла и предпросмотр ----------
$preview = null;
$errors = [];
if (is_post() && post('action') === 'preview') {
    csrf_check();
    $f = $_FILES['file'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Выберите файл Excel (.xlsx) или CSV.';
    } else {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $bin = (string)file_get_contents($f['tmp_name']);
        $table = $ext === 'csv' ? csv_read($bin) : ($ext === 'xlsx' ? xlsx_read($bin) : null);
        if ($ext === 'xls') {
            $errors[] = 'Старый формат .xls не поддерживается. В Excel выберите «Файл → Сохранить как → Книга Excel (.xlsx)».';
        } elseif (!$table) {
            $errors[] = 'Не удалось прочитать файл. Сохраните его как «Книга Excel (.xlsx)» или CSV и попробуйте ещё раз.';
        }
    }
    if (!$errors) {
        // Заголовки → столбцы
        $headerRow = array_key_first($table);
        $map = [];
        $byNorm = [];
        foreach ($cols as $k => $c) $byNorm[io_norm($c[0])] = $k;
        foreach ($table[$headerRow] as $i => $h) {
            $n = io_norm((string)$h);
            if (isset($byNorm[$n])) $map[$i] = $byNorm[$n];
        }
        unset($table[$headerRow]);
        if (!in_array('id', $map, true) && !in_array('sku', $map, true) && !in_array('name', $map, true)) {
            $errors[] = 'В первой строке не найдены заголовки столбцов. Возьмите за основу файл из кнопки «Выгрузить в Excel».';
        }
    }
    if (!$errors) {
        $existing = [];
        foreach (q('SELECT * FROM products')->fetchAll() as $p) $existing[(int)$p['id']] = $p;
        $bySku = [];
        foreach ($existing as $p) if (trim((string)$p['sku']) !== '') $bySku[mb_strtolower(trim($p['sku']))][] = (int)$p['id'];
        $plan = [];
        $rowsView = [];
        $unchanged = 0;
        foreach ($table as $rowNum => $row) {
            $vals = [];
            foreach ($map as $i => $k) $vals[$k] = (string)($row[$i] ?? '');
            if (implode('', array_map('trim', $vals)) === '') continue;
            $rowErr = [];
            $parsed = [];
            foreach ($vals as $k => $raw) {
                if (trim($raw) === '') continue; // пустая ячейка — значение не меняем
                [$v, $err] = io_parse($cols[$k][1], $raw);
                if ($err) $rowErr[] = $cols[$k][0] . ': ' . $err;
                else $parsed[$k] = $v;
            }
            if (isset($parsed['size'])) $parsed['size'] = str_replace(['x', 'х', '*', 'X', 'Х'], '×', $parsed['size']);
            // Какой товар: по ID, иначе по артикулу, иначе новый
            $target = null;
            if (isset($parsed['id'])) {
                $target = $existing[$parsed['id']] ?? null;
                if (!$target) $rowErr[] = 'товар с ID ' . $parsed['id'] . ' не найден (для нового товара оставьте ID пустым)';
            } elseif (isset($parsed['sku']) && isset($bySku[mb_strtolower($parsed['sku'])])) {
                $ids = $bySku[mb_strtolower($parsed['sku'])];
                if (count($ids) > 1) $rowErr[] = 'артикул ' . $parsed['sku'] . ' есть у нескольких товаров — укажите ID';
                else $target = $existing[$ids[0]];
            }
            unset($parsed['id']);
            $label = $parsed['name'] ?? ($target['name'] ?? '');
            if ($rowErr) {
                $rowsView[] = ['row' => $rowNum, 'name' => $label, 'status' => 'error', 'changes' => $rowErr];
                continue;
            }
            if ($target) {
                $set = [];
                $changes = [];
                foreach ($parsed as $k => $v) {
                    $old = $k === 'category' ? ($catName[$target['category_id']] ?? '') : $target[$k];
                    $same = $k === 'price' ? abs((float)$old - (float)$v) < 0.005 : ($k === 'category' ? mb_strtolower(trim((string)$old)) === mb_strtolower(trim((string)$v)) : (string)$old === (string)$v);
                    if (!$same) {
                        $set[$k] = $v;
                        $changes[] = $cols[$k][0] . ': ' . io_show($k, $old) . ' → ' . io_show($k, $v) . ($k === 'category' && !isset($catByName[mb_strtolower(trim((string)$v))]) ? ' (новый раздел)' : '');
                    }
                }
                if (!$set) { $unchanged++; continue; }
                $plan[] = ['op' => 'update', 'id' => (int)$target['id'], 'set' => $set];
                $rowsView[] = ['row' => $rowNum, 'name' => $target['name'] . ' ' . $target['size'], 'status' => 'update', 'changes' => $changes];
            } else {
                if (($parsed['name'] ?? '') === '') {
                    $rowsView[] = ['row' => $rowNum, 'name' => '', 'status' => 'error', 'changes' => ['для нового товара нужно наименование']];
                    continue;
                }
                $plan[] = ['op' => 'create', 'set' => $parsed];
                $rowsView[] = ['row' => $rowNum, 'name' => $parsed['name'] . ' ' . ($parsed['size'] ?? ''), 'status' => 'create',
                    'changes' => array_map(fn($k) => $cols[$k][0] . ': ' . io_show($k, $parsed[$k]), array_keys($parsed))];
            }
        }
        $token = bin2hex(random_bytes(12));
        if ($plan) {
            if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0775, true);
            foreach (glob(DATA_DIR . '/import-*.json') ?: [] as $old) if (filemtime($old) < time() - 3600) @unlink($old);
            file_put_contents($stash($token), json_encode($plan, JSON_UNESCAPED_UNICODE));
        }
        $preview = ['token' => $token, 'rows' => $rowsView, 'plan' => count($plan), 'unchanged' => $unchanged,
                    'cols' => array_values(array_map(fn($k) => $cols[$k][0], array_unique($map))), 'file' => $f['name']];
    }
}

admin_header('Загрузка и выгрузка товаров', 'products');
?>
<div class="a-head"><h1>Excel: выгрузка и загрузка товаров</h1><a href="<?= url('admin/products.php') ?>">← Товары</a></div>
<?php foreach ($errors as $er): ?><div class="a-flash a-flash-error"><?= e($er) ?></div><?php endforeach; ?>

<?php if ($preview): ?>
  <?php $cnt = array_count_values(array_column($preview['rows'], 'status')); ?>
  <div class="a-card">
    <h2>Проверьте изменения — файл «<?= e($preview['file']) ?>»</h2>
    <p style="margin:0 0 6px">Будет обновлено товаров: <b><?= (int)($cnt['update'] ?? 0) ?></b> · добавлено новых: <b><?= (int)($cnt['create'] ?? 0) ?></b> · без изменений: <?= (int)$preview['unchanged'] ?><?= !empty($cnt['error']) ? ' · <b style="color:#A31515">строк с ошибками: ' . (int)$cnt['error'] . '</b> (они будут пропущены)' : '' ?></p>
    <p class="a-muted" style="margin:0">Распознаны столбцы: <?= e(implode(', ', $preview['cols'])) ?>. Пустые ячейки не меняют значения на сайте.</p>
  </div>
  <?php if ($preview['rows']): ?>
  <div class="a-table-wrap" style="margin-bottom:16px"><table class="a-table">
    <thead><tr><th>Строка</th><th>Товар</th><th>Что изменится</th></tr></thead>
    <tbody>
    <?php foreach ($preview['rows'] as $r): ?>
      <tr<?= $r['status'] === 'error' ? ' style="background:#FDECEC"' : ($r['status'] === 'create' ? ' style="background:#E9F5EE"' : '') ?>>
        <td><?= (int)$r['row'] ?></td>
        <td><b><?= e(trim($r['name']) ?: '—') ?></b><div class="a-muted" style="font-size:12px"><?= $r['status'] === 'create' ? 'новый товар' : ($r['status'] === 'error' ? 'ошибка — строка пропускается' : 'обновление') ?></div></td>
        <td style="font-size:13px"><?= implode('<br>', array_map('e', $r['changes'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:24px">
    <?php if ($preview['plan']): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="apply"><input type="hidden" name="token" value="<?= e($preview['token']) ?>"><button class="a-btn" type="submit">Применить изменения</button></form>
    <?php else: ?>
      <span class="a-muted">Применять нечего: изменений нет.</span>
    <?php endif; ?>
    <a class="a-btn a-btn-out" href="<?= url('admin/products_io.php') ?>">Отмена</a>
  </div>
<?php endif; ?>

<div class="a-grid">
  <div class="a-card">
    <h2>1. Выгрузить товары</h2>
    <p>Все товары одним файлом Excel: цены, фасовка, разделы, артикулы, показ на сайте. Откройте его в Excel, поменяйте что нужно и загрузите обратно.</p>
    <a class="a-btn a-btn-dark" href="<?= url('admin/products_io.php?export=1') ?>">Выгрузить в Excel</a>
  </div>
  <div class="a-card">
    <h2>2. Загрузить изменения</h2>
    <form method="post" enctype="multipart/form-data" class="a-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="preview">
      <label class="a-field"><span>Файл Excel (.xlsx) или CSV</span><input type="file" name="file" accept=".xlsx,.csv" required>
        <small>Сначала покажем, что изменится, — на сайте ничего не поменяется, пока вы не нажмёте «Применить».</small></label>
      <div><button class="a-btn" type="submit">Проверить файл</button></div>
    </form>
  </div>
</div>
<div class="a-card">
  <h2>Как это работает</h2>
  <ul style="margin:0; padding-left:20px; line-height:1.7">
    <li>Товар находится по <b>ID</b> (первый столбец). Если ID пустой — по <b>артикулу</b>. Если не нашёлся — это <b>новый товар</b> (нужно хотя бы наименование).</li>
    <li><b>Пустая ячейка</b> — значение на сайте не меняется. Чтобы поставить «по запросу», впишите в цену 0.</li>
    <li><b>Цена</b> — за упаковку, если указано «Штук в упаковке», иначе за штуку.</li>
    <li>«Показывать на сайте» и «На главной» — «да» или «нет». Если раздела с таким названием нет, он будет создан.</li>
    <li>Удалять товары через файл нельзя — только в админке. Фото и описания добавляются в карточке товара.</li>
    <li>Порядок столбцов не важен, лишние столбцы пропускаются. Подходят .xlsx и CSV (разделитель «;»).</li>
  </ul>
</div>
<?php admin_footer();
