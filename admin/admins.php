<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
$me = require_admin();
$isOwner = admin_can($me, 'owner');
$perms = admin_perms();

/** Права из формы: только известные разделы */
function posted_perms(array $perms): string
{
    return implode(',', array_values(array_intersect(array_keys($perms), (array)($_POST['perms'] ?? []))));
}

function owners_count(): int
{
    return (int)q('SELECT COUNT(*) FROM admins WHERE is_owner = 1')->fetchColumn();
}

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int)post('id');

    // Свой пароль и имя — любой администратор
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
        redirect('admin/admins.php');
    }
    if ($action === 'myname') {
        q('UPDATE admins SET name = ? WHERE id = ?', [mb_substr(post('name'), 0, 60), $me['id']]);
        flash('Имя сохранено.');
        redirect('admin/admins.php');
    }

    // Остальное — только главный администратор
    if (!$isOwner) {
        flash('Управлять администраторами может только главный администратор.', 'error');
        redirect('admin/admins.php');
    }
    if ($action === 'add') {
        $login = post('login');
        $pass = (string)($_POST['password'] ?? '');
        $owner = post('role') === 'owner' ? 1 : 0;
        if (!preg_match('~^[a-zA-Z0-9_.@-]{3,64}$~', $login)) {
            flash('Логин: от 3 символов, латиница, цифры, точка, дефис.', 'error');
        } elseif (mb_strlen($pass) < 8) {
            flash('Пароль — не короче 8 символов.', 'error');
        } elseif (q('SELECT 1 FROM admins WHERE login = ?', [$login])->fetchColumn()) {
            flash('Такой логин уже есть.', 'error');
        } elseif (!$owner && posted_perms($perms) === '') {
            flash('Отметьте хотя бы один раздел, к которому у администратора будет доступ.', 'error');
        } else {
            q('INSERT INTO admins (login, password_hash, name, is_owner, perms, created_at) VALUES (?,?,?,?,?,?)', [
                $login, password_hash($pass, PASSWORD_DEFAULT), mb_substr(post('name'), 0, 60), $owner, $owner ? '' : posted_perms($perms), date('Y-m-d H:i:s'),
            ]);
            flash('Администратор ' . $login . ' добавлен. Передайте ему логин и пароль.');
        }
        redirect('admin/admins.php');
    }
    $target = $id ? q('SELECT * FROM admins WHERE id = ?', [$id])->fetch() : null;
    if (!$target) {
        redirect('admin/admins.php');
    }
    if ($action === 'rights') {
        $owner = post('role') === 'owner' ? 1 : 0;
        if (!$owner && $target['is_owner'] && owners_count() <= 1) {
            flash('Это единственный главный администратор. Сначала назначьте главным кого-то ещё.', 'error');
        } elseif (!$owner && posted_perms($perms) === '') {
            flash('Отметьте хотя бы один раздел.', 'error');
        } else {
            q('UPDATE admins SET is_owner = ?, perms = ?, name = ? WHERE id = ?', [$owner, $owner ? '' : posted_perms($perms), mb_substr(post('name'), 0, 60), $id]);
            flash('Права администратора ' . $target['login'] . ' сохранены.');
        }
        redirect('admin/admins.php?edit=' . $id);
    }
    if ($action === 'reset') {
        $new = (string)($_POST['new'] ?? '');
        if (mb_strlen($new) < 8) {
            flash('Пароль — не короче 8 символов.', 'error');
        } else {
            q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $id]);
            flash('Новый пароль для ' . $target['login'] . ' установлен. Передайте его администратору.');
        }
        redirect('admin/admins.php?edit=' . $id);
    }
    if ($action === 'delete') {
        if ($id === (int)$me['id']) {
            flash('Нельзя удалить самого себя.', 'error');
        } elseif ($target['is_owner'] && owners_count() <= 1) {
            flash('Нельзя удалить единственного главного администратора.', 'error');
        } else {
            q('DELETE FROM admins WHERE id = ?', [$id]);
            flash('Администратор ' . $target['login'] . ' удалён.');
        }
        redirect('admin/admins.php');
    }
    redirect('admin/admins.php');
}

/** Чекбоксы ролей и разделов */
function rights_fields(array $perms, bool $owner, string $current): string
{
    $have = array_filter(explode(',', $current));
    $h = '<div class="a-roles">'
        . '<label class="a-role"><input type="radio" name="role" value="owner"' . ($owner ? ' checked' : '') . '> <span><b>Главный администратор</b><small>Полный доступ ко всему: все разделы, администраторы и их права, журнал действий</small></span></label>'
        . '<label class="a-role"><input type="radio" name="role" value="admin"' . (!$owner ? ' checked' : '') . '> <span><b>Администратор</b><small>Доступ только к отмеченным разделам</small></span></label>'
        . '</div><fieldset class="a-perms"><legend>Разделы для администратора</legend>';
    foreach ($perms as $k => $l) {
        $h .= '<label class="a-check"><input type="checkbox" name="perms[]" value="' . e($k) . '"' . (in_array($k, $have, true) ? ' checked' : '') . '> ' . e($l) . '</label>';
    }
    return $h . '</fieldset>';
}

$roleText = static function (array $a) use ($perms): string {
    if ($a['is_owner']) {
        return '<span class="pill pill-new">Главный</span>';
    }
    $list = array_values(array_intersect_key($perms, array_flip(array_filter(explode(',', (string)$a['perms'])))));
    return $list ? e(implode(', ', $list)) : '<span class="a-muted">нет доступа</span>';
};

// Редактирование прав одного администратора
if ($isOwner && isset($_GET['edit'])) {
    $t = q('SELECT * FROM admins WHERE id = ?', [(int)$_GET['edit']])->fetch();
    if (!$t) redirect('admin/admins.php');
    admin_header('Администратор ' . $t['login'], 'admins');
    ?>
    <div class="a-head"><h1><?= e($t['name'] ?: $t['login']) ?></h1><a href="<?= url('admin/admins.php') ?>">← Все администраторы</a></div>
    <p class="a-muted" style="margin-top:-12px">Логин: <b><?= e($t['login']) ?></b> · последний вход: <?= $t['last_login'] ? date('d.m.Y H:i', strtotime($t['last_login'])) : 'ещё не входил' ?> · <a href="<?= url('admin/log.php?admin=' . (int)$t['id']) ?>">действия в журнале</a></p>
    <form method="post" class="a-card a-form"><?= csrf_field() ?><input type="hidden" name="action" value="rights"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <h2 style="margin:0">Права</h2>
      <?= a_input('name', 'Имя (видно в журнале и меню)', $t['name'], 'text', 'Например, «Ольга, менеджер»') ?>
      <?= rights_fields($perms, (bool)$t['is_owner'], (string)$t['perms']) ?>
      <div><button class="a-btn" type="submit">Сохранить права</button></div>
    </form>
    <div class="a-grid">
      <form method="post" class="a-card a-form"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <h2 style="margin:0">Новый пароль</h2>
        <?= a_input('new', 'Пароль', '', 'text', 'Не короче 8 символов. Передайте его администратору', ['required' => true, 'minlength' => 8, 'autocomplete' => 'new-password']) ?>
        <div><button class="a-btn a-btn-dark" type="submit">Установить пароль</button></div>
      </form>
      <?php if ((int)$t['id'] !== (int)$me['id']): ?>
      <div class="a-card">
        <h2>Удаление</h2>
        <p class="a-muted">Администратор больше не сможет войти. Его действия останутся в журнале.</p>
        <form method="post" data-confirm="Удалить администратора <?= e($t['login']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="a-btn a-btn-danger" type="submit">Удалить администратора</button></form>
      </div>
      <?php endif; ?>
    </div>
    <?php
    admin_footer();
    exit;
}

$admins = $isOwner ? q('SELECT * FROM admins ORDER BY is_owner DESC, id')->fetchAll() : [];
admin_header($isOwner ? 'Администраторы' : 'Мой пароль', 'admins');
?>
<h1><?= $isOwner ? 'Администраторы' : 'Мой профиль' ?></h1>
<?php if ($isOwner): ?>
  <p class="a-muted" style="margin-top:-12px">Вы — главный администратор: видите и меняете всё, назначаете администраторов и выдаёте им доступ к разделам.</p>
  <div class="a-table-wrap" style="margin-bottom:20px"><table class="a-table">
    <thead><tr><th>Администратор</th><th>Доступ</th><th>Последний вход</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($admins as $a): ?>
      <tr><td><b><?= e($a['name'] ?: $a['login']) ?></b><?= $a['name'] ? '<div class="a-muted" style="font-size:13px">' . e($a['login']) . '</div>' : '' ?><?= (int)$a['id'] === (int)$me['id'] ? ' <span class="pill">это вы</span>' : '' ?></td>
        <td><?= $roleText($a) ?></td>
        <td><?= $a['last_login'] ? date('d.m.Y H:i', strtotime($a['last_login'])) : '<span class="a-muted">не входил</span>' ?></td>
        <td class="num"><a class="a-btn a-btn-out a-btn-sm" href="<?= url('admin/admins.php?edit=' . (int)$a['id']) ?>">Права и пароль</a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <form method="post" class="a-card a-form"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <h2 style="margin:0">Добавить администратора</h2>
    <div class="a-grid">
      <?= a_input('login', 'Логин *', '', 'text', 'Латиница, цифры, точка, дефис', ['required' => true, 'autocomplete' => 'off']) ?>
      <?= a_input('name', 'Имя', '', 'text', 'Например, «Ольга, менеджер»') ?>
      <?= a_input('password', 'Пароль *', '', 'text', 'Не короче 8 символов', ['required' => true, 'minlength' => 8, 'autocomplete' => 'new-password']) ?>
    </div>
    <?= rights_fields($perms, false, 'orders') ?>
    <div><button class="a-btn" type="submit">Добавить администратора</button></div>
  </form>
<?php endif; ?>
<div class="a-grid">
  <form method="post" class="a-card a-form"><?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h2 style="margin:0">Сменить мой пароль</h2>
    <?= a_input('current', 'Текущий пароль', '', 'password', '', ['required' => true, 'autocomplete' => 'current-password']) ?>
    <?= a_input('new', 'Новый пароль', '', 'password', 'Не короче 8 символов', ['required' => true, 'autocomplete' => 'new-password']) ?>
    <div><button class="a-btn" type="submit">Сменить пароль</button></div>
  </form>
  <form method="post" class="a-card a-form"><?= csrf_field() ?><input type="hidden" name="action" value="myname">
    <h2 style="margin:0">Моё имя</h2>
    <?= a_input('name', 'Имя', $me['name'], 'text', 'Показывается в меню и журнале действий') ?>
    <p class="a-muted" style="margin:0">Мой доступ: <?= $roleText($me) ?></p>
    <div><button class="a-btn a-btn-dark" type="submit">Сохранить</button></div>
  </form>
</div>
<?php admin_footer();
