<?php
declare(strict_types=1);

function admin_header(string $title, string $active = '', bool $editor = false): void
{
    $admin = current_admin();
    $newOrders = (int)q("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn();
    $menu = [
        'index'      => ['Обзор', 'admin/index.php'],
        'orders'     => ['Заказы' . ($newOrders ? ' <b class="nbadge">' . $newOrders . '</b>' : ''), 'admin/orders.php'],
        'products'   => ['Товары', 'admin/products.php'],
        'categories' => ['Разделы каталога', 'admin/categories.php'],
        'documents'  => ['Документы', 'admin/documents.php'],
        'pages'      => ['Страницы', 'admin/pages.php'],
        'clients'    => ['Клиенты', 'admin/clients.php'],
        'settings'   => ['Настройки сайта', 'admin/settings.php'],
        'admins'     => ['Администраторы', 'admin/admins.php'],
    ];
    ?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — админка <?= e(setting('site_name')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@600&family=Golos+Text:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('assets/admin.css') ?>">
<?php if ($editor): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<?php endif; ?>
</head>
<body>
<?php if ($admin): ?>
<div class="a-wrap">
  <aside class="a-side">
    <a class="a-brand" href="<?= url('admin/index.php') ?>"><span>L</span>eon_pro <small>админка</small></a>
    <nav>
      <?php foreach ($menu as $k => [$label, $href]): ?>
        <a href="<?= url($href) ?>" class="<?= $active === $k ? 'on' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="a-side-foot">
      <a href="<?= url() ?>" target="_blank">Открыть сайт ↗</a>
      <span><?= e($admin['login']) ?></span>
      <form method="post" action="<?= url('admin/logout.php') ?>"><?= csrf_field() ?><button type="submit">Выйти</button></form>
    </div>
  </aside>
  <main class="a-main">
<?php else: ?>
<div class="a-auth"><main class="a-main">
<?php endif; ?>
<?php foreach (flash() as $f): ?>
  <div class="a-flash a-flash-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<?php
}

function admin_footer(): void
{
    ?>
  </main>
</div>
<script>
// Подтверждение удаления
document.querySelectorAll('[data-confirm]').forEach(function (f) {
  f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
});
// Визуальный редактор для полей с data-editor
document.querySelectorAll('textarea[data-editor]').forEach(function (ta) {
  if (!window.Quill) return;
  var box = document.createElement('div');
  box.className = 'a-editor';
  ta.style.display = 'none';
  ta.parentNode.insertBefore(box, ta.nextSibling);
  var q = new Quill(box, {
    theme: 'snow',
    modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline', 'link'], [{ list: 'ordered' }, { list: 'bullet' }], ['image', 'clean']] }
  });
  q.clipboard.dangerouslyPasteHTML(ta.value);
  var toolbar = box.previousElementSibling;
  var htmlMode = false;
  ta.form.addEventListener('submit', function () {
    if (!htmlMode) ta.value = q.root.innerHTML === '<p><br></p>' ? '' : q.root.innerHTML;
  });
  var toggle = document.createElement('button');
  toggle.type = 'button'; toggle.className = 'a-link'; toggle.textContent = 'Редактировать HTML-код';
  toggle.addEventListener('click', function () {
    htmlMode = !htmlMode;
    if (htmlMode) { ta.value = q.root.innerHTML; ta.classList.add('mono'); }
    else { q.clipboard.dangerouslyPasteHTML(ta.value); }
    ta.style.display = htmlMode ? '' : 'none';
    box.style.display = htmlMode ? 'none' : '';
    if (toolbar) toolbar.style.display = htmlMode ? 'none' : '';
    toggle.textContent = htmlMode ? 'Визуальный редактор' : 'Редактировать HTML-код';
  });
  ta.parentNode.appendChild(toggle);
});
</script>
</body>
</html>
<?php
}

function a_input(string $name, string $label, $value = '', string $type = 'text', string $hint = '', array $attr = []): string
{
    $a = '';
    foreach ($attr as $k => $v) {
        $a .= ' ' . $k . ($v === true ? '' : '="' . e($v) . '"');
    }
    return '<label class="a-field"><span>' . e($label) . '</span><input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . $a . '>'
        . ($hint ? '<small>' . e($hint) . '</small>' : '') . '</label>';
}

function a_textarea(string $name, string $label, $value = '', string $hint = '', bool $editor = false, int $rows = 4): string
{
    return '<label class="a-field"><span>' . e($label) . '</span><textarea name="' . e($name) . '" rows="' . $rows . '"' . ($editor ? ' data-editor' : '') . '>' . e($value) . '</textarea>'
        . ($hint ? '<small>' . e($hint) . '</small>' : '') . '</label>';
}

function a_check(string $name, string $label, bool $on): string
{
    return '<label class="a-check"><input type="hidden" name="' . e($name) . '" value="0"><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . '> ' . e($label) . '</label>';
}

function a_image(string $name, string $label, ?string $current, string $hint = ''): string
{
    $h = '<div class="a-field"><span>' . e($label) . '</span>';
    if ($current) {
        $h .= '<div class="a-img-cur"><img src="' . e(upload_url($current)) . '" alt=""><label class="a-check"><input type="checkbox" name="' . e($name) . '_remove" value="1"> Удалить</label></div>';
    }
    $h .= '<input type="file" name="' . e($name) . '" accept=".jpg,.jpeg,.png,.webp,.svg">';
    $h .= '<small>' . e($hint ?: 'JPG, PNG, WEBP или SVG') . '</small></div>';
    return $h;
}
