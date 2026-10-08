<?php
require __DIR__ . '/inc/bootstrap.php';

$min = min_order();
$errors = [];
$old = [];

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'add') {
        $pid = (int)post('product_id');
        $p = q('SELECT * FROM products WHERE id = ? AND active = 1', [$pid])->fetch();
        if ($p) {
            $step = product_step($p);
            $qty = max($step, (int)ceil(max(1, (int)post('qty')) / $step) * $step);
            $was = isset(cart_raw()[$pid]);
            cart_set($pid, $qty);
            flash(($was ? 'Количество обновлено: ' : 'Добавлено в корзину: ') . $p['name'] . ' ' . $p['size'] . ' — ' . fmt_qty($qty) . ' шт.');
        }
        back('cart.php');
    }

    if ($action === 'update') {
        foreach ((array)($_POST['qty'] ?? []) as $pid => $qty) {
            $p = q('SELECT * FROM products WHERE id = ?', [(int)$pid])->fetch();
            if ($p) {
                $step = product_step($p);
                cart_set((int)$pid, max($step, (int)ceil(max(1, (int)$qty) / $step) * $step));
            }
        }
        redirect('cart.php');
    }

    if ($action === 'remove') {
        cart_set((int)post('product_id'), 0);
        redirect('cart.php');
    }

    if ($action === 'checkout') {
        $items = cart_items();
        $total = array_sum(array_column($items, 'qty'));
        $fields = ['company', 'inn', 'contact', 'phone', 'email', 'city', 'comment'];
        foreach ($fields as $f) {
            $old[$f] = post($f);
        }
        if (!$items) $errors[] = 'Корзина пуста.';
        if ($total < $min) $errors[] = 'Минимальный заказ — ' . fmt_qty($min) . ' шт. Сейчас в корзине ' . fmt_qty($total) . ' шт.';
        if ($old['company'] === '') $errors[] = 'Укажите название компании.';
        if (!preg_match('~^\d{10}(\d{2})?$~', $old['inn'])) $errors[] = 'ИНН должен содержать 10 или 12 цифр.';
        if ($old['contact'] === '') $errors[] = 'Укажите контактное лицо.';
        if (strlen(normalize_phone($old['phone'])) < 11) $errors[] = 'Проверьте номер телефона.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Проверьте email.';
        if ($old['city'] === '') $errors[] = 'Укажите город доставки.';
        if (empty($_POST['agree_pd']) || empty($_POST['agree_oferta'])) $errors[] = 'Нужно согласие на обработку персональных данных и с офертой.';

        if (!$errors) {
            $sum = array_sum(array_column($items, 'line_sum'));
            $user = current_user();
            $pdo = db();
            $pdo->beginTransaction();
            q('INSERT INTO orders (user_id, company, inn, contact, phone, email, city, comment, total_qty, total_sum, status, updated_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [
                $user['id'] ?? null, $old['company'], $old['inn'], $old['contact'], $old['phone'], $old['email'],
                $old['city'], $old['comment'], $total, $sum, 'new', date('Y-m-d H:i:s'),
            ]);
            $orderId = (int)$pdo->lastInsertId();
            foreach ($items as $it) {
                q('INSERT INTO order_items (order_id, product_id, name, size, price, qty) VALUES (?,?,?,?,?,?)',
                    [$orderId, $it['id'], $it['name'], $it['size'], $it['price'], $it['qty']]);
            }
            // Сохраняем реквизиты в профиль, если поля пустые
            if ($user) {
                q("UPDATE users SET company = COALESCE(NULLIF(company,''), ?), inn = COALESCE(NULLIF(inn,''), ?),
                   contact = COALESCE(NULLIF(contact,''), ?), city = COALESCE(NULLIF(city,''), ?) WHERE id = ?",
                    [$old['company'], $old['inn'], $old['contact'], $old['city'], $user['id']]);
            }
            $pdo->commit();

            // Письма
            $lines = [];
            foreach ($items as $it) {
                $lines[] = '— ' . $it['name'] . ' ' . $it['size'] . ': ' . fmt_qty($it['qty']) . ' шт.' . ((float)$it['price'] > 0 ? ' × ' . fmt_money($it['price']) : '');
            }
            $body = "Заявка № $orderId от " . date('d.m.Y H:i') . "\n\n"
                . "Компания: {$old['company']}\nИНН: {$old['inn']}\nКонтактное лицо: {$old['contact']}\n"
                . "Телефон: {$old['phone']}\nEmail: {$old['email']}\nГород доставки: {$old['city']}\n"
                . "Комментарий: " . ($old['comment'] ?: '—') . "\n\nСостав:\n" . implode("\n", $lines)
                . "\n\nИтого: " . fmt_qty($total) . ' шт.' . ($sum > 0 ? ', ориентировочно ' . fmt_money($sum) : '');
            // Заявка в Telegram-бот
            $tg = '<b>Новая заявка № ' . $orderId . '</b>' . "\n\n"
                . '<b>' . tg_h($old['company']) . '</b>, ИНН ' . tg_h($old['inn']) . "\n"
                . tg_h($old['contact']) . "\n" . tg_h($old['phone']) . "\n" . tg_h($old['email']) . "\n"
                . 'Город: ' . tg_h($old['city']) . "\n"
                . ($old['comment'] !== '' ? 'Комментарий: ' . tg_h($old['comment']) . "\n" : '')
                . "\n" . tg_h(implode("\n", $lines)) . "\n\n"
                . '<b>Итого: ' . fmt_qty($total) . ' шт.</b>' . ($sum > 0 ? ', ориентировочно ' . tg_h(fmt_money($sum)) : '') . "\n\n"
                . '<a href="' . tg_h(site_base_url() . '/admin/orders.php?id=' . $orderId) . '">Открыть заявку в админке</a>';
            tg_notify($tg);
            foreach (array_filter(array_map('trim', explode(',', setting('manager_email')))) as $to) {
                send_mail($to, 'Новая заявка № ' . $orderId . ' — ' . $old['company'], $body);
            }
            send_mail($old['email'], 'Ваша заявка № ' . $orderId . ' принята — ' . setting('site_name'),
                "Здравствуйте!\n\nМы получили вашу заявку. " . setting('order_success') . "\n\n" . $body
                . "\n\n" . setting('site_name') . "\n" . setting('phone') . "\n" . setting('email'));

            $_SESSION['cart'] = [];
            $_SESSION['last_order'] = $orderId;
            redirect('cart.php?done=' . $orderId);
        }
    }
}

$done = (int)($_GET['done'] ?? 0);
if ($done && ($_SESSION['last_order'] ?? 0) !== $done) {
    $done = 0;
}

$items = cart_items();
$total = array_sum(array_column($items, 'qty'));
$sum = array_sum(array_column($items, 'line_sum'));
$enough = $total >= $min;
$user = current_user();
if (!$old && $user) {
    $old = ['company' => $user['company'], 'inn' => $user['inn'], 'contact' => $user['contact'],
            'phone' => $user['phone'] ?? '', 'email' => $user['email'] ?? '', 'city' => $user['city'], 'comment' => ''];
}
$v = fn($k) => e($old[$k] ?? '');
$showSum = setting('show_prices') === '1' && $sum > 0;

page_header('Корзина');
?>
<div class="container page-pad">
  <h1 style="margin-top:28px">Корзина</h1>

<?php if ($done): ?>
  <div class="done-box">
    <span class="done-icon"><?= icon('check', 26) ?></span>
    <h2 style="margin:0">Заявка № <?= $done ?> принята</h2>
    <p style="margin:0; font-size:17px; color:var(--text-2)"><?= e(setting('order_success')) ?> Копия заявки отправлена на ваш email.</p>
    <div class="hero-actions">
      <?php if ($user): ?><a class="btn btn-accent" href="<?= url('account.php') ?>">Статус в личном кабинете</a><?php endif; ?>
      <a class="btn btn-outline" href="<?= url('catalog.php') ?>">Вернуться в каталог</a>
    </div>
  </div>

<?php elseif (!$items): ?>
  <div class="empty">
    <p style="margin:0 0 16px">В корзине пока пусто.</p>
    <a class="btn btn-accent" href="<?= url('catalog.php') ?>">Перейти в каталог</a>
  </div>

<?php else: ?>
  <?php if ($errors): ?>
    <div class="flash flash-error" role="alert" style="margin-bottom:20px"><b>Проверьте заявку:</b><ul style="margin:6px 0 0; padding-left:20px"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <div class="cart-layout">
    <div class="cart-main">
      <div class="progress-box">
        <div class="section-head" style="margin:0; font-size:15px">
          <b>В корзине <?= fmt_qty($total) ?> шт. из минимальных <?= fmt_qty($min) ?></b>
          <?= $enough ? '<span class="ok-text">Минимальный объём набран</span>' : '<span class="warn-text">Не хватает ' . fmt_qty($min - $total) . ' шт.</span>' ?>
        </div>
        <div class="progress"><div style="width: <?= $min ? min(100, round($total / $min * 100)) : 100 ?>%"></div></div>
      </div>

      <form method="post" action="<?= url('cart.php') ?>" data-autosubmit>
        <?= csrf_field() ?><input type="hidden" name="action" value="update">
        <div class="table-wrap has-cards">
          <table class="table cards">
            <thead><tr><th><span class="sr-only">Фото</span></th><th>Товар</th><th class="num">Цена</th><th>Количество, шт.</th><th class="num">Сумма</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td class="c-img"><?= product_thumb($it) ?></td>
                <td><a class="pname" href="<?= url('product.php?id=' . $it['id']) ?>"><?= e($it['name']) ?></a><div class="sub"><?= e($it['size']) ?><?= $it['pack_qty'] ? ' · ' . fmt_qty($it['pack_qty']) . ' шт./уп.' : '' ?></div></td>
                <td class="num price"><?= price_label($it) ?></td>
                <td class="c-act"><div class="row-form" style="justify-content:flex-start">
                  <?= qty_input('qty[' . $it['id'] . ']', $it['qty'], product_step($it)) ?>
                  <button class="icon-btn" type="submit" form="rm-<?= $it['id'] ?>" aria-label="Удалить <?= e($it['name']) ?>"><?= icon('trash', 20) ?></button>
                </div></td>
                <td class="num c-hide"><?= $showSum && $it['line_sum'] > 0 ? fmt_money($it['line_sum']) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <noscript><button class="btn btn-outline" type="submit" style="margin-top:10px">Пересчитать</button></noscript>
      </form>
      <?php foreach ($items as $it): ?>
        <form id="rm-<?= $it['id'] ?>" method="post" action="<?= url('cart.php') ?>" hidden><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= $it['id'] ?>"></form>
      <?php endforeach; ?>

      <form id="checkout" method="post" action="<?= url('cart.php') ?>" style="display:flex; flex-direction:column; gap:22px" data-checkout data-enough="<?= $enough ? '1' : '0' ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="checkout">
        <div class="section-head" style="margin:0">
          <h2 style="margin:0">Оформление заявки</h2>
          <?php if (!$user): ?><a href="<?= url('login.php?back=cart') ?>"><b>Войти и подставить сохранённые реквизиты</b></a><?php endif; ?>
        </div>
        <fieldset class="box fields">
          <legend>1. Данные покупателя</legend>
          <label class="field">Название компании *<input name="company" required value="<?= $v('company') ?>" placeholder="ООО «Компания» или ИП"></label>
          <label class="field">ИНН *<input name="inn" required inputmode="numeric" pattern="\d{10}(\d{2})?" value="<?= $v('inn') ?>" placeholder="10 или 12 цифр"></label>
          <label class="field">Контактное лицо *<input name="contact" required value="<?= $v('contact') ?>" placeholder="Имя и фамилия"></label>
          <label class="field">Телефон *<input name="phone" type="tel" required value="<?= $v('phone') ?>" placeholder="+7"></label>
          <label class="field">Email *<input name="email" type="email" required value="<?= $v('email') ?>" placeholder="name@company.ru"></label>
        </fieldset>
        <fieldset class="box fields">
          <legend>2. Доставка</legend>
          <label class="field">Город доставки *<input name="city" required value="<?= $v('city') ?>" placeholder="Например, Ростов-на-Дону"></label>
          <p class="small" style="margin:0; align-self:end; color:var(--text-2)"><?= e(setting('delivery_note')) ?></p>
        </fieldset>
        <label class="field">3. Комментарий к заказу<textarea name="comment" rows="3" placeholder="Сроки, удобное время для звонка, пожелания"><?= $v('comment') ?></textarea></label>
        <div style="display:flex; flex-direction:column; gap:10px">
          <label class="check"><input type="checkbox" name="agree_pd" value="1" required> <span>Даю согласие на <a href="<?= url('page.php?p=privacy') ?>" target="_blank">обработку персональных данных</a> *</span></label>
          <label class="check"><input type="checkbox" name="agree_oferta" value="1" required> <span>Принимаю условия <a href="<?= url('page.php?p=oferta') ?>" target="_blank">публичной оферты</a> *</span></label>
        </div>
      </form>
    </div>

    <aside class="summary">
      <h2>Ваша заявка</h2>
      <div class="sum-row"><span>Позиций</span><span><?= count($items) ?></span></div>
      <div class="sum-row"><span>Всего штук</span><span><?= fmt_qty($total) ?></span></div>
      <div class="sum-row"><span>Доставка</span><span>рассчитает менеджер</span></div>
      <div class="sum-row sum-total"><span>Ориентировочно</span><span><?= $showSum ? fmt_money($sum) : 'по запросу' ?></span></div>
      <button class="btn btn-accent btn-lg btn-block" type="submit" form="checkout" data-submit <?= $enough ? '' : 'disabled' ?>>Отправить заявку</button>
      <p class="small muted" style="margin:0" data-hint><?= $enough ? 'Отметьте согласие на обработку данных и с офертой.' : 'Добавьте ещё ' . fmt_qty($min - $total) . ' шт., чтобы отправить заявку.' ?></p>
    </aside>
  </div>
<?php endif; ?>
</div>
<?php page_footer();
