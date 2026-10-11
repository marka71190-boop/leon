<?php
require __DIR__ . '/inc/bootstrap.php';

// Страница «Ваш заказ оформлен». Открывается сразу после отправки заявки.
// Показываем заказ только тому, кто его оформил (в этой же сессии) или владельцу аккаунта.
$id = (int)($_GET['id'] ?? 0);
$user = current_user();
$mine = in_array($id, array_map('intval', (array)($_SESSION['my_orders'] ?? [])), true)
    || (int)($_SESSION['last_order'] ?? 0) === $id;
$order = $id ? q('SELECT * FROM orders WHERE id = ?', [$id])->fetch() : null;
if ($order && !$mine && !($user && (int)$order['user_id'] === (int)$user['id'])) {
    $order = null;
}

if (!$order) {
    page_header('Заказ не найден');
    ?>
    <div class="container page-pad">
      <h1 style="margin-top:28px">Заказ не найден</h1>
      <p>Если вы оформляли заказ, его статус виден в личном кабинете. Или позвоните нам: <a href="tel:<?= e(preg_replace('~[^\d+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a>.</p>
      <div class="hero-actions"><a class="btn btn-accent" href="<?= url('account.php') ?>">Личный кабинет</a><a class="btn btn-outline" href="<?= url('catalog.php') ?>">Каталог</a></div>
    </div>
    <?php
    page_footer();
    exit;
}

$items = q('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$id])->fetchAll();
$showSum = setting('show_prices') === '1' && (float)$order['total_sum'] > 0;
$phone = setting('phone');
$tel = preg_replace('~[^\d+]~', '', $phone);
$owner = $user && (int)$order['user_id'] === (int)$user['id'];

page_header('Заказ № ' . $id . ' оформлен', ['robots' => 'noindex']);
?>
<div class="container page-pad">
  <section class="od-head" aria-labelledby="od-title">
    <span class="done-icon"><?= icon('check', 26) ?></span>
    <p class="eyebrow" style="margin:0">Заказ № <?= $id ?> · <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
    <h1 id="od-title">Ваш заказ оформлен</h1>
    <p class="od-lead">Спасибо! Менеджер свяжется с вами по телефону <b><?= e($order['phone']) ?></b>, подтвердит состав заказа, согласует доставку и выставит счёт. Номер заказа — <b>№ <?= $id ?></b>, назовите его, если будете звонить сами.</p>
  </section>

  <div class="od-grid">
    <div class="od-main">
      <section class="od-card">
        <h2>Что будет дальше</h2>
        <ol class="od-steps">
          <li><b>Менеджер свяжется с вами</b><span>Позвонит в рабочее время<?= setting('work_hours') ? ' (' . e(setting('work_hours')) . ')' : '' ?>, уточнит состав и реквизиты.</span></li>
          <li><b>Согласуем доставку</b><span>Транспортную компанию, адрес и стоимость доставки. Город доставки: <?= e($order['city']) ?>.</span></li>
          <li><b>Счёт и оплата</b><span>Пришлём счёт на <?= e($order['company']) ?>. Онлайн-оплаты на сайте нет — оплата по счёту.</span></li>
          <li><b>Отгрузка</b><span>Передадим заказ в транспортную компанию и сообщим номер накладной.</span></li>
        </ol>
      </section>

      <section class="od-card">
        <h2>Состав заказа</h2>
        <ul class="od-items">
          <?php foreach ($items as $it): ?>
            <li><span><?= e($it['name']) ?><?= $it['size'] ? ' <span class="muted">' . e($it['size']) . '</span>' : '' ?></span><b><?= fmt_packs((int)$it['qty'], (int)$it['pack_qty']) ?></b></li>
          <?php endforeach; ?>
        </ul>
        <div class="od-total"><span>Всего</span><b><?= fmt_qty($order['total_qty']) ?> шт.<?= $showSum ? ' · ориентировочно ' . fmt_money($order['total_sum']) : '' ?></b></div>
        <p class="small muted" style="margin:0">Окончательную стоимость с доставкой подтвердит менеджер.</p>
      </section>
    </div>

    <aside class="od-card od-contacts" aria-label="Контакты">
      <h2>Контакты</h2>
      <a class="od-phone" href="tel:<?= e($tel) ?>"><?= e($phone) ?></a>
      <?php if (setting('work_hours')): ?><span class="muted"><?= e(setting('work_hours')) ?></span><?php endif; ?>
      <?php if (setting('email')): ?><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a><?php endif; ?>
      <?php if (setting('address')): ?><span><?= e(setting('address')) ?></span><?php endif; ?>
      <?php if (setting('telegram') || setting('whatsapp')): ?>
        <div class="messengers">
          <?php if (setting('telegram')): ?><a class="btn btn-outline" href="<?= e(setting('telegram')) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
          <?php if (setting('whatsapp')): ?><a class="btn btn-outline" href="<?= e(setting('whatsapp')) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
        </div>
      <?php endif; ?>
      <p class="small muted" style="margin:0">Нужно что-то изменить в заказе? Позвоните и назовите номер № <?= $id ?>.</p>
    </aside>
  </div>

  <div class="hero-actions od-actions">
    <?php if ($owner): ?>
      <a class="btn btn-accent btn-lg" href="<?= url('account.php') ?>">Статус заказа в личном кабинете</a>
    <?php endif; ?>
    <a class="btn <?= $owner ? 'btn-outline' : 'btn-accent' ?> btn-lg" href="<?= url('catalog.php') ?>">Продолжить покупки</a>
    <?php if (!$user): ?>
      <a class="btn btn-outline btn-lg" href="<?= url('login.php?mode=register') ?>">Зарегистрироваться</a>
    <?php endif; ?>
  </div>
  <?php if (!$user): ?><p class="small muted">С аккаунтом реквизиты подставляются в заявку сами, а статусы заказов видны в личном кабинете.</p><?php endif; ?>
</div>
<?php page_footer();
