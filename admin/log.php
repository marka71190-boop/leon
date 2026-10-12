<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin('owner');

// Журнал действий администраторов: кто, когда и что менял. Видит только главный администратор.
$pagesNames = [
    'orders.php' => 'Заказы', 'products.php' => 'Товары', 'categories.php' => 'Разделы каталога', 'documents.php' => 'Документы',
    'pages.php' => 'Страницы', 'clients.php' => 'Клиенты', 'consents.php' => 'Согласия', 'settings.php' => 'Настройки сайта',
    'admins.php' => 'Администраторы', 'login.php' => 'Вход', 'logout.php' => 'Выход',
];
$actions = [
    'save' => 'сохранение', 'delete' => 'удаление', 'advance' => 'следующий этап заказа', 'cancel' => 'отмена заказа',
    'restore' => 'возврат заказа в работу', 'copy' => 'копирование', 'toggle' => 'показать / скрыть', 'bulk' => 'цены списком',
    'doc_delete' => 'удаление документа', 'password' => 'смена своего пароля', 'reset' => 'новый пароль администратору',
    'add' => 'добавление', 'rights' => 'изменение прав', 'login' => 'вход в админку', 'setup' => 'создание главного администратора',
    'revoke' => 'отметка об отзыве согласия', 'unrevoke' => 'снятие отметки об отзыве', 'tg_test' => 'тест Telegram',
    'tg_find' => 'поиск чата Telegram', 'img_delete' => 'удаление фото', 'img_main' => 'главное фото', 'img_move' => 'порядок фото',
    'myname' => 'смена своего имени', 'password_reset' => 'сброс пароля клиента', '' => 'сохранение',
];

$adminId = is_string($_GET['admin'] ?? null) ? (int)$_GET['admin'] : 0;
$where = $adminId ? ' WHERE admin_id = ?' : '';
$params = $adminId ? [$adminId] : [];
$total = (int)q('SELECT COUNT(*) FROM admin_log' . $where, $params)->fetchColumn();
$perPage = 100;
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, is_string($_GET['page'] ?? null) ? (int)$_GET['page'] : 1));
$rows = q('SELECT * FROM admin_log' . $where . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params)->fetchAll();
$admins = q('SELECT id, login, name FROM admins ORDER BY id')->fetchAll();

admin_header('Журнал действий', 'log');
?>
<div class="a-head"><h1>Журнал действий</h1></div>
<p class="a-muted" style="margin-top:-12px">Кто из администраторов и когда что-то менял в админке. Записи появляются автоматически.</p>
<div class="a-filters">
  <a class="a-chip <?= !$adminId ? 'on' : '' ?>" href="<?= url('admin/log.php') ?>">Все</a>
  <?php foreach ($admins as $a): ?><a class="a-chip <?= $adminId === (int)$a['id'] ? 'on' : '' ?>" href="<?= url('admin/log.php?admin=' . (int)$a['id']) ?>"><?= e($a['name'] ?: $a['login']) ?></a><?php endforeach; ?>
</div>
<?php if (!$rows): ?>
  <div class="a-card a-muted">Записей пока нет.</div>
<?php else: ?>
<div class="a-table-wrap"><table class="a-table">
  <thead><tr><th>Когда</th><th>Кто</th><th>Раздел</th><th>Действие</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
      $target = $r['target'] !== '' ? ($r['page'] === 'orders.php' ? '<a href="' . url('admin/orders.php?id=' . (int)$r['target']) . '">заказ № ' . (int)$r['target'] . '</a>'
          : ($r['page'] === 'products.php' && (int)$r['target'] ? '<a href="' . url('admin/products.php?edit=' . (int)$r['target']) . '">товар № ' . (int)$r['target'] . '</a>' : '№ ' . e($r['target']))) : ''; ?>
    <tr>
      <td style="white-space:nowrap"><?= date('d.m.Y H:i:s', strtotime($r['created_at'])) ?></td>
      <td><?= e($r['login']) ?></td>
      <td><?= e($pagesNames[$r['page']] ?? $r['page']) ?></td>
      <td><?= e($actions[$r['action']] ?? $r['action']) ?><?= $target ? ' · ' . $target : '' ?></td>
      <td class="a-muted" style="font-size:13px"><?= e($r['ip']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($pages > 1): ?>
  <div class="a-filters" style="margin-top:14px"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="a-chip <?= $i === $page ? 'on' : '' ?>" href="<?= url('admin/log.php?' . http_build_query(array_filter(['admin' => $adminId ?: null, 'page' => $i]))) ?>"><?= $i ?></a><?php endfor; ?></div>
<?php endif; ?>
<?php endif; ?>
<?php admin_footer();
