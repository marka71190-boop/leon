<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$system = ['oferta', 'privacy', 'delivery']; // на них ссылается сайт — удалять нельзя

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int)post('id');
    if ($action === 'save') {
        $slug = strtolower(preg_replace('~[^a-z0-9-]+~i', '-', post('slug')));
        $slug = trim($slug, '-');
        $title = post('title');
        $old = $id ? q('SELECT * FROM pages WHERE id = ?', [$id])->fetch() : null;
        if ($old && in_array($old['slug'], $system, true)) {
            $slug = $old['slug'];
        }
        if ($title === '' || $slug === '') {
            flash('Укажите заголовок и адрес страницы (латиницей).', 'error');
            back('admin/pages.php');
        }
        if (q('SELECT 1 FROM pages WHERE slug = ? AND id <> ?', [$slug, $id])->fetchColumn()) {
            flash('Страница с таким адресом уже есть.', 'error');
            back('admin/pages.php');
        }
        $vals = [$slug, $title, (string)($_POST['content'] ?? ''), post('in_menu') === '1' ? 1 : 0, (int)post('sort')];
        if ($old) {
            q('UPDATE pages SET slug=?, title=?, content=?, in_menu=?, sort=? WHERE id=?', [...$vals, $id]);
        } else {
            q('INSERT INTO pages (slug, title, content, in_menu, sort) VALUES (?,?,?,?,?)', $vals);
            $id = (int)db()->lastInsertId();
        }
        flash('Страница сохранена.');
        redirect('admin/pages.php?edit=' . $id);
    }
    if ($action === 'delete') {
        q('DELETE FROM pages WHERE id = ? AND slug NOT IN (' . implode(',', array_fill(0, count($system), '?')) . ')', [$id, ...$system]);
        flash('Страница удалена.');
        redirect('admin/pages.php');
    }
}

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $p = $id ? q('SELECT * FROM pages WHERE id = ?', [$id])->fetch() : null;
    $p = $p ?: ['id' => 0, 'slug' => '', 'title' => '', 'content' => '', 'in_menu' => 1, 'sort' => 10];
    $isSystem = in_array($p['slug'], $system, true);
    admin_header($p['id'] ? 'Страница' : 'Новая страница', 'pages', true);
    ?>
    <div class="a-head"><h1><?= $p['id'] ? e($p['title']) : 'Новая страница' ?></h1><div class="a-actions"><?php if ($p['id']): ?><a class="a-btn a-btn-out a-btn-sm" href="<?= url('page.php?p=' . urlencode($p['slug'])) ?>" target="_blank">Открыть на сайте ↗</a><?php endif; ?><a href="<?= url('admin/pages.php') ?>">← Все страницы</a></div></div>
    <form method="post" class="a-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <div class="a-card a-form">
        <div class="a-grid">
          <?= a_input('title', 'Заголовок *', $p['title'], 'text', '', ['required' => true]) ?>
          <?= a_input('slug', 'Адрес страницы', $p['slug'], 'text', $isSystem ? 'Системная страница — адрес не меняется' : 'Латиницей, например «about». Ссылка: page.php?p=адрес', $isSystem ? ['readonly' => true] : ['required' => true, 'pattern' => '[a-zA-Z0-9-]+']) ?>
          <?= a_input('sort', 'Порядок в меню', $p['sort'], 'number') ?>
        </div>
        <?= a_check('in_menu', 'Показывать в верхнем меню', (bool)$p['in_menu']) ?>
        <?= a_textarea('content', 'Текст страницы', $p['content'], 'Метки, которые сайт заменит сам: {{реквизиты}} — блок реквизитов из настроек, {{продавец}} — название ИП, {{телефон}} — телефон', true, 16) ?>
      </div>
      <div class="a-save"><button class="a-btn" type="submit">Сохранить страницу</button></div>
    </form>
    <?php if ($p['id'] && !$isSystem): ?>
      <form method="post" data-confirm="Удалить страницу?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="a-btn a-btn-danger a-btn-sm" type="submit">Удалить страницу</button></form>
    <?php endif; ?>
    <?php
    admin_footer();
    exit;
}

$pages = q('SELECT * FROM pages ORDER BY sort, id')->fetchAll();
admin_header('Страницы', 'pages');
?>
<div class="a-head"><h1>Страницы</h1><a class="a-btn" href="<?= url('admin/pages.php?edit=0') ?>">+ Новая страница</a></div>
<div class="a-table-wrap"><table class="a-table">
  <thead><tr><th>Заголовок</th><th>Адрес</th><th>В меню</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($pages as $p): ?>
    <tr><td><a href="<?= url('admin/pages.php?edit=' . $p['id']) ?>"><b><?= e($p['title']) ?></b></a></td><td class="a-muted">page.php?p=<?= e($p['slug']) ?></td><td><?= $p['in_menu'] ? 'да' : '—' ?></td>
      <td><div class="a-actions"><a class="a-btn a-btn-out a-btn-sm" href="<?= url('admin/pages.php?edit=' . $p['id']) ?>">Изменить</a></div></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p class="a-muted">Тексты на главной (заголовок, описание, слоган), контакты и условия заказа меняются в разделе <a href="<?= url('admin/settings.php') ?>">«Настройки сайта»</a>.</p>
<?php admin_footer();
