<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int)post('id');
    try {
        if ($action === 'save') {
            if (post('name') === '') throw new RuntimeException('Укажите название раздела.');
            $old = $id ? q('SELECT * FROM categories WHERE id = ?', [$id])->fetch() : null;
            $image = $old['image'] ?? '';
            if ($new = handle_upload('image', ['jpg', 'png', 'webp'], 'categories')) {
                delete_upload($image);
                $image = $new;
            } elseif (post('image_remove') === '1') {
                delete_upload($image);
                $image = '';
            }
            $vals = [post('name'), post('description'), $image, (int)post('sort'), post('active') === '1' ? 1 : 0];
            if ($old) {
                q('UPDATE categories SET name=?, description=?, image=?, sort=?, active=? WHERE id=?', [...$vals, $id]);
            } else {
                q('INSERT INTO categories (name, description, image, sort, active) VALUES (?,?,?,?,?)', $vals);
            }
            flash('Раздел сохранён.');
        }
        if ($action === 'delete' && $id) {
            $c = q('SELECT * FROM categories WHERE id = ?', [$id])->fetch();
            delete_upload($c['image'] ?? '');
            q('DELETE FROM categories WHERE id = ?', [$id]);
            flash('Раздел удалён. Его товары остались без раздела — назначьте им новый.');
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'error');
    }
    redirect('admin/categories.php');
}

$cats = q('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS cnt FROM categories c ORDER BY sort, id')->fetchAll();
$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$cur = ['id' => 0, 'name' => '', 'description' => '', 'image' => '', 'sort' => count($cats) + 1, 'active' => 1];
foreach ($cats as $c) if ((int)$c['id'] === $edit) $cur = $c;

admin_header('Разделы каталога', 'categories');
?>
<div class="a-head"><h1>Разделы каталога</h1><a class="a-btn" href="<?= url('admin/categories.php?edit=0') ?>">+ Добавить раздел</a></div>
<div class="a-table-wrap" style="margin-bottom:20px"><table class="a-table">
  <thead><tr><th></th><th>Название</th><th>Описание</th><th>Товаров</th><th>Порядок</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($cats as $c): ?>
    <tr class="<?= $c['active'] ? '' : 'a-off' ?>">
      <td><?= $c['image'] ? '<img src="' . e(upload_url($c['image'])) . '" alt="">' : '' ?></td>
      <td><b><?= e($c['name']) ?></b><?= $c['active'] ? '' : ' <span class="pill">скрыт</span>' ?></td>
      <td class="a-muted"><?= e($c['description']) ?></td>
      <td><a href="<?= url('admin/products.php?cat=' . $c['id']) ?>"><?= (int)$c['cnt'] ?></a></td>
      <td><?= (int)$c['sort'] ?></td>
      <td><div class="a-actions">
        <a class="a-btn a-btn-out a-btn-sm" href="<?= url('admin/categories.php?edit=' . $c['id']) ?>">Изменить</a>
        <form method="post" data-confirm="Удалить раздел «<?= e($c['name']) ?>»? Товары останутся без раздела."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="a-btn a-btn-danger a-btn-sm" type="submit">Удалить</button></form>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<?php if ($edit !== null): ?>
<div class="a-card" id="form">
  <h2><?= $cur['id'] ? 'Изменить раздел' : 'Новый раздел' ?></h2>
  <form method="post" enctype="multipart/form-data" class="a-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$cur['id'] ?>">
    <div class="a-grid">
      <?= a_input('name', 'Название *', $cur['name'], 'text', '', ['required' => true]) ?>
      <?= a_input('description', 'Короткое описание', $cur['description'], 'text', 'Показывается в карточке раздела на главной') ?>
      <?= a_input('sort', 'Порядок', $cur['sort'], 'number') ?>
      <?= a_image('image', 'Картинка раздела', $cur['image']) ?>
    </div>
    <?= a_check('active', 'Показывать на сайте', (bool)$cur['active']) ?>
    <div><button class="a-btn" type="submit">Сохранить</button> <a href="<?= url('admin/categories.php') ?>">Отмена</a></div>
  </form>
</div>
<?php endif; ?>
<?php admin_footer();
