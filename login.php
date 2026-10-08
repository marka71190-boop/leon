<?php
require __DIR__ . '/inc/bootstrap.php';

$mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';
$backTo = ($_GET['back'] ?? '') === 'cart' ? 'cart.php' : 'account.php';
$errors = [];
$old = [];

if (current_user()) {
    redirect($backTo);
}

if (is_post()) {
    csrf_check();
    if (post('mode') === 'register') {
        $mode = 'register';
        $old = ['email' => mb_strtolower(post('email')), 'phone' => post('phone'), 'company' => post('company')];
        $phone = $old['phone'] !== '' ? normalize_phone($old['phone']) : '';
        $pass = (string)($_POST['password'] ?? '');
        if ($old['email'] === '' && $phone === '') $errors[] = 'Укажите email или телефон.';
        if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Проверьте email.';
        if ($phone !== '' && strlen($phone) !== 11) $errors[] = 'Проверьте номер телефона.';
        if (mb_strlen($pass) < 6) $errors[] = 'Пароль — не короче 6 символов.';
        if (!$errors) {
            if ($old['email'] !== '' && q('SELECT 1 FROM users WHERE email = ?', [$old['email']])->fetchColumn()) $errors[] = 'Этот email уже зарегистрирован — войдите.';
            if ($phone !== '' && q('SELECT 1 FROM users WHERE phone = ?', [$phone])->fetchColumn()) $errors[] = 'Этот телефон уже зарегистрирован — войдите.';
        }
        if (!$errors) {
            q('INSERT INTO users (email, phone, password_hash, company) VALUES (?,?,?,?)', [
                $old['email'] ?: null, $phone ?: null, password_hash($pass, PASSWORD_DEFAULT), $old['company'],
            ]);
            session_regen();
            $_SESSION['user_id'] = (int)db()->lastInsertId();
            flash('Вы зарегистрированы. Заполните реквизиты компании — они будут подставляться в заявки.');
            redirect($backTo === 'cart.php' ? 'cart.php' : 'account.php?tab=profile');
        }
    } else {
        $login = post('login');
        $old = ['login' => $login];
        $pass = (string)($_POST['password'] ?? '');
        $u = null;
        if (str_contains($login, '@')) {
            $u = q('SELECT * FROM users WHERE email = ?', [mb_strtolower($login)])->fetch();
        } elseif ($login !== '') {
            $u = q('SELECT * FROM users WHERE phone = ?', [normalize_phone($login)])->fetch();
        }
        if ($u && password_verify($pass, $u['password_hash'])) {
            session_regen();
            $_SESSION['user_id'] = (int)$u['id'];
            redirect($backTo);
        }
        $errors[] = 'Неверный логин или пароль.';
    }
}

page_header($mode === 'register' ? 'Регистрация' : 'Вход');
$qs = $backTo === 'cart.php' ? '&back=cart' : '';
?>
<div class="container page-pad">
  <div style="max-width:480px; margin:40px auto 0">
    <div class="chips">
      <a class="chip <?= $mode === 'login' ? 'active' : '' ?>" href="<?= url('login.php?mode=login' . $qs) ?>">Вход</a>
      <a class="chip <?= $mode === 'register' ? 'active' : '' ?>" href="<?= url('login.php?mode=register' . $qs) ?>">Регистрация</a>
    </div>
    <h1><?= $mode === 'register' ? 'Регистрация' : 'Вход в личный кабинет' ?></h1>
    <?php if ($errors): ?><div class="flash flash-error" role="alert" style="margin:0 0 16px"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

    <?php if ($mode === 'login'): ?>
      <form class="form-narrow" method="post">
        <?= csrf_field() ?><input type="hidden" name="mode" value="login">
        <label class="field">Email или телефон<input name="login" required autocomplete="username" value="<?= e($old['login'] ?? '') ?>"></label>
        <label class="field">Пароль<input name="password" type="password" required autocomplete="current-password"></label>
        <button class="btn btn-accent btn-lg" type="submit">Войти</button>
        <p class="muted small" style="margin:0">Забыли пароль? Позвоните <?= e(setting('phone')) ?><?php if (setting('email')): ?> или напишите на <?= e(setting('email')) ?><?php endif; ?> — менеджер поможет восстановить доступ.</p>
      </form>
    <?php else: ?>
      <form class="form-narrow" method="post">
        <?= csrf_field() ?><input type="hidden" name="mode" value="register">
        <label class="field">Email<input name="email" type="email" autocomplete="email" value="<?= e($old['email'] ?? '') ?>"></label>
        <label class="field">Телефон<input name="phone" type="tel" autocomplete="tel" placeholder="+7" value="<?= e($old['phone'] ?? '') ?>"><span class="hint">Укажите email, телефон или оба — входить можно по любому из них.</span></label>
        <label class="field">Название компании<input name="company" value="<?= e($old['company'] ?? '') ?>"></label>
        <label class="field">Пароль<input name="password" type="password" required minlength="6" autocomplete="new-password"><span class="hint">Не короче 6 символов</span></label>
        <button class="btn btn-accent btn-lg" type="submit">Зарегистрироваться</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php page_footer();
