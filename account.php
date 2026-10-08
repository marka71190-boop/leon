<?php
require __DIR__ . '/inc/bootstrap.php';

$user = require_user();
$tab = in_array($_GET['tab'] ?? '', ['orders', 'profile'], true) ? $_GET['tab'] : 'orders';
$errors = [];

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'repeat') {
        $order = q('SELECT * FROM orders WHERE id = ? AND user_id = ?', [(int)post('order_id'), $user['id']])->fetch();
        if ($order) {
            $added = 0;
            $items = q('SELECT oi.qty, p.id, p.active FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?', [$order['id']])->fetchAll();
            foreach ($items as $it) {
                if ($it['active']) {
                    cart_set((int)$it['id'], (int)(cart_raw()[$it['id']] ?? 0) + (int)$it['qty']);
                    $added++;
                }
            }
            flash($added ? "Товары из заказа № {$order['id']} добавлены в корзину ($added поз.)." : 'Товары из этого заказа больше не продаются.', $added ? 'ok' : 'error');
            redirect($added ? 'cart.php' : 'account.php');
        }
        redirect('account.php');
    }

    if ($action === 'profile') {
        $tab = 'profile';
        $data = ['company' => post('company'), 'inn' => post('inn'), 'contact' => post('contact'), 'city' => post('city'),
                 'email' => mb_strtolower(post('email')), 'phone' => post('phone') !== '' ? normalize_phone(post('phone')) : ''];
        if ($data['inn'] !== '' && !preg_match('~^\d{10}(\d{2})?$~', $data['inn'])) $errors[] = 'ИНН должен содержать 10 или 12 цифр.';
        if ($data['email'] === '' && $data['phone'] === '') $errors[] = 'Нужен email или телефон для входа.';
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Проверьте email.';
        if ($data['email'] !== '' && q('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$data['email'], $user['id']])->fetchColumn()) $errors[] = 'Этот email уже используется.';
        if ($data['phone'] !== '' && q('SELECT 1 FROM users WHERE phone = ? AND id <> ?', [$data['phone'], $user['id']])->fetchColumn()) $errors[] = 'Этот телефон уже используется.';
        $newPass = (string)($_POST['new_password'] ?? '');
        if ($newPass !== '' && mb_strlen($newPass) < 6) $errors[] = 'Новый пароль — не короче 6 символов.';
        if (!$errors) {
            q('UPDATE users SET company=?, inn=?, contact=?, city=?, email=?, phone=? WHERE id=?',
                [$data['company'], $data['inn'], $data['contact'], $data['city'], $data['email'] ?: null, $data['phone'] ?: null, $user['id']]);
            if ($newPass !== '') {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($newPass, PASSWORD_DEFAULT), $user['id']]);
            }
            flash('Данные сохранены.');
            redirect('account.php?tab=profile');
        }
        $user = array_merge($user, $data);
    }
}

$orders = q('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC', [$user['id']])->fetchAll();
$active = array_values(array_filter($orders, fn($o) => !in_array($o['status'], ['done', 'cancelled'], true)));
$stages = array_diff_key(order_statuses(), ['cancelled' => 1]);
$stageKeys = array_keys($stages);

page_header('Личный кабинет');
?>
<div class="container page-pad">
  <h1 style="margin-top:28px">Личный кабинет</h1>
  <div class="acc-layout">
    <nav class="acc-nav" aria-label="Разделы кабинета">
      <div class="who"><b><?= e($user['company'] ?: 'Компания не указана') ?></b><div class="small muted"><?= $user['inn'] ? 'ИНН ' . e($user['inn']) : e($user['email'] ?: $user['phone']) ?></div></div>
      <a href="<?= url('account.php') ?>" class="<?= $tab === 'orders' ? 'active' : '' ?>">Мои заказы</a>
      <a href="<?= url('account.php?tab=profile') ?>" class="<?= $tab === 'profile' ? 'active' : '' ?>">Реквизиты и профиль</a>
      <a href="<?= url('cart.php') ?>">Корзина</a>
      <form method="post" action="<?= url('logout.php') ?>"><?= csrf_field() ?><button type="submit" class="muted">Выйти</button></form>
    </nav>

    <div class="acc-main">
    <?php if ($tab === 'orders'): ?>
      <?php foreach ($active as $o): $cur = array_search($o['status'], $stageKeys, true); ?>
        <section>
          <h2>Текущий заказ</h2>
          <div class="order-card">
            <div class="section-head" style="margin:0"><b style="font-size:17px">Заказ № <?= $o['id'] ?> · <?= date('d.m.Y', strtotime($o['created_at'])) ?></b><span><?= fmt_qty($o['total_qty']) ?> шт. · <?= e($o['city']) ?></span></div>
            <ol class="stages">
              <?php foreach ($stageKeys as $i => $k): ?>
                <li class="<?= $cur !== false && $i <= $cur ? 'on' : '' ?> <?= $i === $cur ? 'cur' : '' ?>"><?= e($stages[$k]) ?></li>
              <?php endforeach; ?>
            </ol>
            <?php if ($o['manager_note']): ?><p style="margin:0" class="small"><b>Комментарий менеджера:</b> <?= nl2br(e($o['manager_note'])) ?></p><?php endif; ?>
          </div>
        </section>
      <?php endforeach; ?>

      <section>
        <h2>История заказов</h2>
        <?php if (!$orders): ?>
          <p class="empty">Заказов пока нет. <a href="<?= url('catalog.php') ?>">Перейти в каталог</a></p>
        <?php else: ?>
        <div class="table-wrap">
          <table class="table" style="min-width:720px">
            <thead><tr><th>Заказ</th><th>Состав</th><th class="num">Количество</th><th>Статус</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o):
                $its = q('SELECT name, size, qty FROM order_items WHERE order_id = ?', [$o['id']])->fetchAll(); ?>
              <tr>
                <td><b>№ <?= $o['id'] ?></b><div class="sub"><?= date('d.m.Y', strtotime($o['created_at'])) ?></div></td>
                <td class="small"><?= implode('<br>', array_map(fn($i) => e($i['name'] . ' ' . $i['size']) . ' — ' . fmt_qty($i['qty']) . ' шт.', $its)) ?></td>
                <td class="num"><?= fmt_qty($o['total_qty']) ?> шт.</td>
                <td><span class="pill pill-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
                <td style="text-align:right"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="repeat"><input type="hidden" name="order_id" value="<?= $o['id'] ?>"><button class="btn btn-outline" type="submit">Повторить заказ</button></form></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>

    <?php else: ?>
      <section>
        <h2>Реквизиты и профиль</h2>
        <?php if ($errors): ?><div class="flash flash-error" role="alert" style="margin:0 0 16px"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
        <form method="post" style="display:flex; flex-direction:column; gap:20px">
          <?= csrf_field() ?><input type="hidden" name="action" value="profile">
          <fieldset class="box fields">
            <legend>Компания — подставляется в заявки</legend>
            <label class="field">Название компании<input name="company" value="<?= e($user['company']) ?>"></label>
            <label class="field">ИНН<input name="inn" inputmode="numeric" value="<?= e($user['inn']) ?>"></label>
            <label class="field">Контактное лицо<input name="contact" value="<?= e($user['contact']) ?>"></label>
            <label class="field">Город доставки по умолчанию<input name="city" value="<?= e($user['city']) ?>"></label>
          </fieldset>
          <fieldset class="box fields">
            <legend>Вход</legend>
            <label class="field">Email<input name="email" type="email" value="<?= e($user['email']) ?>"></label>
            <label class="field">Телефон<input name="phone" type="tel" value="<?= e($user['phone'] ? '+' . $user['phone'] : '') ?>"></label>
            <label class="field">Новый пароль<input name="new_password" type="password" autocomplete="new-password"><span class="hint">Оставьте пустым, чтобы не менять</span></label>
          </fieldset>
          <div><button class="btn btn-accent btn-lg" type="submit">Сохранить</button></div>
        </form>
      </section>
    <?php endif; ?>
    </div>
  </div>
</div>
<?php page_footer();
