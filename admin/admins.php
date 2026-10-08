<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify($cur, $me['password_hash'])) {
            flash('Текущий пароль указан неверно.', 'error');
        } elseif (mb_strlen($new) < 8) {
            flash('Новый пароль — не короче 8 символов.', 'error');
        } else {
            q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            flash('Пароль изменён.');
        }
    }
    if ($action === 'add') {
        $login = post('login');
        $pass = (string)($_POST['password'] ?? '');
        if (!preg_match('~^[a-zA-Z0-9_.@-]{3,64}$~', $login)) {
            flash('Логин: от 3 символов, латиница, цифры, точка, дефис.', 'error');
        } elseif (mb_strlen($pass) < 8) {
            flash('Пароль — не короче 8 символов.', 'error');
        } elseif (q('SELECT 1 FROM admins WHERE login = ?', [$login])->fetchColumn()) {
            flash('Такой логин уже есть.', 'error');
        } else {
            q('INSERT INTO admins (login, password_hash) VALUES (?, ?)', [$login, password_hash($pass, PASSWORD_DEFAULT)]);
            flash('Администратор добавлен.');
        }
    }
    if ($action === 'delete') {
        $id = (int)post('id');
        if ($id === (int)$me['id']) {
            flash('Нельзя удалить самого себя.', 'error');
        } else {
            q('DELETE FROM admins WHERE id = ?', [$id]);
            flash('Администратор удалён.');
        }
    }
    redirect('admin/admins.php');
}

$admins = q('SELECT * FROM admins ORDER BY id')->fetchAll();
admin_header('Администраторы', 'admins');
?>
<h1>Администраторы</h1>
<div class="a-table-wrap" style="margin-bottom:20px"><table class="a-table">
  <thead><tr><th>Логин</th><th>Создан</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($admins as $a): ?>
    <tr><td><b><?= e($a['login']) ?></b><?= (int)$a['id'] === (int)$me['id'] ? ' <span class="pill">это вы</span>' : '' ?></td><td><?= date('d.m.Y', strtotime($a['created_at'])) ?></td>
      <td><?php if ((int)$a['id'] !== (int)$me['id']): ?><form method="post" data-confirm="Удалить администратора <?= e($a['login']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="a-btn a-btn-danger a-btn-sm" type="submit">Удалить</button></form><?php endif; ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<div class="a-grid">
  <div class="a-card">
    <h2>Сменить мой пароль</h2>
    <form method="post" class="a-form"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <?= a_input('current', 'Текущий пароль', '', 'password', '', ['required' => true, 'autocomplete' => 'current-password']) ?>
      <?= a_input('new', 'Новый пароль', '', 'password', 'Не короче 8 символов', ['required' => true, 'autocomplete' => 'new-password']) ?>
      <div><button class="a-btn" type="submit">Сменить пароль</button></div>
    </form>
  </div>
  <div class="a-card">
    <h2>Добавить администратора</h2>
    <form method="post" class="a-form"><?= csrf_field() ?><input type="hidden" name="action" value="add">
      <?= a_input('login', 'Логин', '', 'text', '', ['required' => true]) ?>
      <?= a_input('password', 'Пароль', '', 'password', 'Не короче 8 символов', ['required' => true, 'autocomplete' => 'new-password']) ?>
      <div><button class="a-btn a-btn-dark" type="submit">Добавить</button></div>
    </form>
  </div>
</div>
<?php admin_footer();
