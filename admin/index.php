<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$stats = [
    ['Новых заявок', (int)q("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn(), 'admin/orders.php?status=new'],
    ['Заказов в работе', (int)q("SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','payment','shipped')")->fetchColumn(), 'admin/orders.php'],
    ['Товаров на сайте', (int)q('SELECT COUNT(*) FROM products WHERE active=1')->fetchColumn(), 'admin/products.php'],
    ['Клиентов', (int)q('SELECT COUNT(*) FROM users')->fetchColumn(), 'admin/clients.php'],
];
$recent = q('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
$placeholders = [];
foreach (['phone' => 'Телефон', 'email' => 'Email', 'address' => 'Адрес', 'work_hours' => 'Часы работы'] as $k => $l) {
    if (str_contains(setting($k), '[')) $placeholders[] = $l;
}
$noPrice = (int)q('SELECT COUNT(*) FROM products WHERE active=1 AND price<=0')->fetchColumn();

admin_header('Обзор', 'index');
?>
<h1>Обзор</h1>
<?php $notify = setting('tg_chats') !== '' || setting('manager_email') !== ''; if ($placeholders || !$notify || $noPrice): ?>
<div class="a-card" style="border-color:#F6C9A0; background:#FFF8F1">
  <h2>Что стоит заполнить</h2>
  <ul style="margin:0; padding-left:20px">
    <?php if ($placeholders): ?><li>В <a href="<?= url('admin/settings.php') ?>">настройках</a> остались заглушки: <?= e(implode(', ', $placeholders)) ?>.</li><?php endif; ?>
    <?php if (!$notify): ?><li>Не настроены <a href="<?= url('admin/settings.php#notify') ?>">уведомления о заявках</a>: подключите Telegram-бота, иначе новые заявки видны только здесь.</li><?php endif; ?>
    <?php if ($noPrice): ?><li>У <?= $noPrice ?> товаров нет цены — на сайте у них «По запросу». <a href="<?= url('admin/products.php') ?>">Открыть товары</a></li><?php endif; ?>
  </ul>
</div>
<?php endif; ?>
<div class="a-stats">
  <?php foreach ($stats as [$l, $n, $h]): ?>
    <a class="a-stat" href="<?= url($h) ?>"><b><?= $n ?></b><span><?= e($l) ?></span></a>
  <?php endforeach; ?>
</div>
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
<?php admin_footer();
