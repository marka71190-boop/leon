<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
$me = require_admin();

// Обзор показывает только то, к чему у администратора есть доступ
$canOrders = admin_can($me, 'orders');
$canProducts = admin_can($me, 'products');
$canSettings = admin_can($me, 'settings');
$stats = [];
if ($canOrders) {
    $stats[] = ['Новых заявок', (int)q("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn(), 'admin/orders.php?status=new'];
    $stats[] = ['Заказов в работе', (int)q("SELECT COUNT(*) FROM orders WHERE status IN ('new','contacted','delivery','paid','shipped')")->fetchColumn(), 'admin/orders.php'];
}
if ($canProducts) $stats[] = ['Товаров на сайте', (int)q('SELECT COUNT(*) FROM products WHERE active=1')->fetchColumn(), 'admin/products.php'];
if (admin_can($me, 'clients')) $stats[] = ['Клиентов', (int)q('SELECT COUNT(*) FROM users')->fetchColumn(), 'admin/clients.php'];
$recent = $canOrders ? q('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll() : [];
$placeholders = [];
if ($canSettings) {
    foreach (['phone' => 'Телефон', 'email' => 'Email', 'address' => 'Адрес', 'work_hours' => 'Часы работы'] as $k => $l) {
        if (str_contains(setting($k), '[')) $placeholders[] = $l;
    }
}
$noPrice = $canProducts ? (int)q('SELECT COUNT(*) FROM products WHERE active=1 AND price<=0')->fetchColumn() : 0;
$noPack = $canProducts ? (int)q('SELECT COUNT(*) FROM products WHERE active=1 AND COALESCE(pack_qty,0)<=0')->fetchColumn() : 0;
$noIndex = $canSettings && setting('seo_noindex') === '1';

admin_header('Обзор', 'index');
?>
<h1>Обзор</h1>
<?php $notify = !$canSettings || setting('tg_chats') !== '' || setting('manager_email') !== ''; if ($placeholders || !$notify || $noPrice || $noPack || $noIndex): ?>
<div class="a-card" style="border-color:#F6C9A0; background:#FFF8F1">
  <h2>Что стоит заполнить</h2>
  <ul style="margin:0; padding-left:20px">
    <?php if ($placeholders): ?><li>В <a href="<?= url('admin/settings.php') ?>">настройках</a> остались заглушки: <?= e(implode(', ', $placeholders)) ?>.</li><?php endif; ?>
    <?php if (!$notify): ?><li>Не настроены <a href="<?= url('admin/settings.php#notify') ?>">уведомления о заявках</a>: подключите Telegram-бота, иначе новые заявки видны только здесь.</li><?php endif; ?>
    <?php if ($noPrice): ?><li>У <?= $noPrice ?> товаров нет цены — на сайте у них «По запросу». <a href="<?= url('admin/products.php') ?>">Открыть товары</a></li><?php endif; ?>
    <?php if ($noIndex): ?><li>Сайт скрыт от поисковиков. Когда сайт готов, выключите это в <a href="<?= url('admin/settings.php#g-SEO') ?>">настройках SEO</a>.</li><?php endif; ?>
    <?php if ($noPack): ?><li>У <?= $noPack ?> товаров не указано «Штук в упаковке» — их заказывают поштучно, а не упаковками. <a href="<?= url('admin/products.php') ?>">Открыть товары</a></li><?php endif; ?>
  </ul>
</div>
<?php endif; ?>
<?php if ($canSettings): ?>
<div class="a-card a-maint-card<?= maintenance_on() ? ' is-on' : '' ?>">
  <div>
    <h2 style="margin:0 0 4px">Технический перерыв</h2>
    <p class="a-muted" style="margin:0"><?= maintenance_on() ? 'Сейчас включён: посетители видят заглушку с телефоном, заказы и регистрация недоступны.' : 'Закрыть сайт для посетителей на время работ. Вы, как администратор, будете видеть сайт как обычно.' ?> <a href="<?= url('admin/settings.php#g-Тех. перерыв') ?>">Текст заглушки</a></p>
  </div>
  <form method="post" action="<?= url('admin/maintenance.php') ?>"<?= maintenance_on() ? '' : ' data-confirm="Закрыть сайт для посетителей на технический перерыв?"' ?>><?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= maintenance_on() ? 'off' : 'on' ?>">
    <button class="a-btn <?= maintenance_on() ? '' : 'a-btn-dark' ?>" type="submit"><?= maintenance_on() ? 'Выключить — открыть сайт' : 'Включить технический перерыв' ?></button>
  </form>
</div>
<?php endif; ?>
<div class="a-stats">
  <?php foreach ($stats as [$l, $n, $h]): ?>
    <a class="a-stat" href="<?= url($h) ?>"><b><?= $n ?></b><span><?= e($l) ?></span></a>
  <?php endforeach; ?>
</div>
<?php if ($canOrders): ?>
<div class="a-head"><h2 style="margin:0">Последние заявки</h2><a href="<?= url('admin/orders.php') ?>">Все заказы</a></div>
<?php if (!$recent): ?>
  <div class="a-card a-muted">Заявок пока нет.</div>
<?php else: ?>
<div class="a-table-wrap"><table class="a-table">
  <thead><tr><th>№</th><th>Дата</th><th>Компания</th><th>Город</th><th class="num">Штук</th><th>Статус</th></tr></thead>
  <tbody>
  <?php foreach ($recent as $o): ?>
    <tr><td><a href="<?= url('admin/orders.php?id=' . $o['id']) ?>"><b><?= $o['id'] ?></b></a></td><td><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td><td><?= e($o['company']) ?></td><td><?= e($o['city']) ?></td><td class="num"><?= fmt_qty($o['total_qty']) ?></td><td><span class="pill pill-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php endif; ?>
<?php if (!$stats && !$canOrders): ?><div class="a-card">Выберите раздел в меню слева.</div><?php endif; ?>
<?php admin_footer();
