<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';

$hasAdmins = (int)q('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
$error = '';

// Простая защита от перебора: пауза после 5 неудачных попыток
$_SESSION['admin_fails'] = $_SESSION['admin_fails'] ?? 0;

if (is_post()) {
    csrf_check();
    $login = post('login');
    $pass = (string)($_POST['password'] ?? '');
    if (!$hasAdmins) {
        // Первый запуск: создание главного администратора
        if (!preg_match('~^[a-zA-Z0-9_.@-]{3,64}$~', $login)) {
            $error = 'Логин: от 3 символов, латиница, цифры, точка, дефис.';
        } elseif (mb_strlen($pass) < 8) {
            $error = 'Пароль — не короче 8 символов.';
        } elseif ($pass !== (string)($_POST['password2'] ?? '')) {
            $error = 'Пароли не совпадают.';
        } else {
            q('INSERT INTO admins (login, password_hash) VALUES (?, ?)', [$login, password_hash($pass, PASSWORD_DEFAULT)]);
            session_regen();
            $_SESSION['admin_id'] = (int)db()->lastInsertId();
            flash('Администратор создан. Начните с раздела «Настройки сайта»: телефон, email, адрес.');
            redirect('admin/settings.php');
        }
    } else {
        if ($_SESSION['admin_fails'] >= 5) {
            sleep(3);
        }
        $a = q('SELECT * FROM admins WHERE login = ?', [$login])->fetch();
        if ($a && password_verify($pass, $a['password_hash'])) {
            session_regen();
            $_SESSION['admin_id'] = (int)$a['id'];
            $_SESSION['admin_fails'] = 0;
            redirect('admin/index.php');
        }
        $_SESSION['admin_fails']++;
        $error = 'Неверный логин или пароль.';
    }
}

if (current_admin()) {
    redirect('admin/index.php');
}

admin_header('Вход');
?>
<div class="a-card">
  <h1 style="margin-bottom:6px"><span style="color:var(--accent)">L</span>eon_pro</h1>
  <p class="a-muted" style="margin:0 0 18px"><?= $hasAdmins ? 'Вход в админку' : 'Первый запуск: создайте главного администратора' ?></p>
  <?php if (IS_DEMO && $hasAdmins): ?><div class="a-flash a-flash-info">Демо-доступ: логин <b>demo</b>, пароль <b>leonpro2026</b></div><?php endif; ?>
  <?php if ($error): ?><div class="a-flash a-flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="a-form">
    <?= csrf_field() ?>
    <?= a_input('login', 'Логин', post('login'), 'text', '', ['required' => true, 'autocomplete' => 'username']) ?>
    <?= a_input('password', 'Пароль', '', 'password', $hasAdmins ? '' : 'Не короче 8 символов', ['required' => true, 'autocomplete' => $hasAdmins ? 'current-password' : 'new-password']) ?>
    <?php if (!$hasAdmins): ?>
      <?= a_input('password2', 'Повторите пароль', '', 'password', '', ['required' => true, 'autocomplete' => 'new-password']) ?>
    <?php endif; ?>
    <button class="a-btn" type="submit"><?= $hasAdmins ? 'Войти' : 'Создать и войти' ?></button>
  </form>
</div>
<p style="text-align:center"><a href="<?= url() ?>">← На сайт</a></p>
<?php admin_footer();
