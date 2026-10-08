<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

if (is_post()) {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'upload') {
            $file = handle_upload('file', ['pdf', 'jpg', 'png'], 'docs');
            if (!$file) throw new RuntimeException('Выберите файл.');
            $pid = (int)post('product_id') ?: null;
            q('INSERT INTO documents (title, file, product_id, sort) VALUES (?,?,?,?)', [post('title') ?: 'Документ', $file, $pid, (int)post('sort')]);
            flash('Документ загружен.');
        }
        if ($action === 'save') {
            foreach ((array)($_POST['doc'] ?? []) as $id => $d) {
                q('UPDATE documents SET title = ?, sort = ?, product_id = ? WHERE id = ?', [
                    trim((string)($d['title'] ?? '')) ?: 'Документ', (int)($d['sort'] ?? 0), (int)($d['product_id'] ?? 0) ?: null, (int)$id,
                ]);
            }
            flash('Изменения сохранены.');
        }
        if ($action === 'delete') {
            $d = q('SELECT * FROM documents WHERE id = ?', [(int)post('id')])->fetch();
            if ($d) {
                delete_upload($d['file']);
                q('DELETE FROM documents WHERE id = ?', [$d['id']]);
            }
            flash('Документ удалён.');
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'error');
    }
    redirect('admin/documents.php');
}

$products = q('SELECT id, name, size FROM products ORDER BY sort, id')->fetchAll();
$docs = q('SELECT * FROM documents ORDER BY product_id IS NOT NULL, sort, id')->fetchAll();
$opts = function ($sel) use ($products) {
    $h = '<option value="">Общий документ (раздел «Сертификаты»)</option>';
    foreach ($products as $p) {
        $h .= '<option value="' . $p['id'] . '"' . ((int)$sel === (int)$p['id'] ? ' selected' : '') . '>' . e($p['name'] . ' ' . $p['size']) . '</option>';
    }
    return $h;
};

admin_header('Документы', 'documents');
?>
<h1>Документы и сертификаты</h1>
<div class="a-card">
  <h2>Загрузить документ</h2>
  <form method="post" enctype="multipart/form-data" class="a-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload">
    <div class="a-grid">
      <?= a_input('title', 'Название', '', 'text', 'Например, «Сертификат соответствия»', ['required' => true]) ?>
      <label class="a-field"><span>Привязать к товару</span><select name="product_id"><?= $opts(0) ?></select></label>
      <label class="a-field"><span>Файл *</span><input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPG или PNG</small></label>
      <?= a_input('sort', 'Порядок', '0', 'number') ?>
    </div>
    <div><button class="a-btn" type="submit">Загрузить</button></div>
  </form>
</div>

<?php if ($docs): ?>
<form method="post" id="docs-save"><?= csrf_field() ?><input type="hidden" name="action" value="save"></form>
<div class="a-table-wrap"><table class="a-table">
  <thead><tr><th>Название</th><th>Где показывается</th><th>Порядок</th><th>Файл</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($docs as $d): ?>
    <tr>
      <td><input class="a-input" form="docs-save" name="doc[<?= $d['id'] ?>][title]" value="<?= e($d['title']) ?>"></td>
      <td><select class="a-input" form="docs-save" name="doc[<?= $d['id'] ?>][product_id]"><?= $opts($d['product_id']) ?></select></td>
      <td><input class="a-input" form="docs-save" type="number" name="doc[<?= $d['id'] ?>][sort]" value="<?= (int)$d['sort'] ?>" style="width:70px"></td>
      <td><a href="<?= e(upload_url($d['file'])) ?>" target="_blank">Открыть</a></td>
      <td><form method="post" data-confirm="Удалить документ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="a-btn a-btn-danger a-btn-sm" type="submit">Удалить</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<div class="a-save"><button class="a-btn" type="submit" form="docs-save">Сохранить изменения</button></div>
<?php else: ?>
  <div class="a-card a-muted">Документов пока нет.</div>
<?php endif; ?>
<?php admin_footer();
