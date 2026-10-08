<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$statuses = order_statuses();

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    $action = post('action');
    $order = q('SELECT * FROM orders WHERE id = ?', [$id])->fetch();
    if (!$order) {
        redirect('admin/orders.php');
    }
    if ($action === 'delete') {
        q('DELETE FROM orders WHERE id = ?', [$id]);
        flash("Заказ № $id удалён.");
        redirect('admin/orders.php');
    }
    if ($action === 'save') {
        // Состав заказа
        foreach ((array)($_POST['items'] ?? []) as $itemId => $row) {
            $qty = max(0, (int)($row['qty'] ?? 0));
            $price = max(0, (float)str_replace([',', ' '], ['.', ''], (string)($row['price'] ?? '0')));
            if ($qty === 0 || !empty($row['remove'])) {
                q('DELETE FROM order_items WHERE id = ? AND order_id = ?', [(int)$itemId, $id]);
            } else {
                q('UPDATE order_items SET qty = ?, price = ? WHERE id = ? AND order_id = ?', [$qty, $price, (int)$itemId, $id]);
            }
        }
        $tot = q('SELECT COALESCE(SUM(qty),0) AS q, COALESCE(SUM(qty*price),0) AS s FROM order_items WHERE order_id = ?', [$id])->fetch();
        $status = array_key_exists(post('status'), $statuses) ? post('status') : $order['status'];
        q('UPDATE orders SET status=?, manager_note=?, company=?, inn=?, contact=?, phone=?, email=?, city=?, total_qty=?, total_sum=?, updated_at=? WHERE id=?', [
            $status, post('manager_note'), post('company'), post('inn'), post('contact'), post('phone'), post('email'), post('city'),
            (int)$tot['q'], (float)$tot['s'], date('Y-m-d H:i:s'), $id,
        ]);
        if ($status !== $order['status'] && !empty($_POST['notify']) && filter_var(post('email'), FILTER_VALIDATE_EMAIL)) {
            send_mail(post('email'), 'Заказ № ' . $id . ': ' . $statuses[$status],
                "Здравствуйте!\n\nСтатус вашего заказа № $id: " . $statuses[$status] . '.'
                . (post('manager_note') ? "\n\nКомментарий менеджера: " . post('manager_note') : '')
                . "\n\n" . setting('site_name') . "\n" . setting('phone'));
        }
        flash('Заказ сохранён.');
        redirect('admin/orders.php?id=' . $id);
    }
}

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $o = q('SELECT o.*, u.email AS u_email, u.phone AS u_phone FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?', [$id])->fetch();
    if (!$o) redirect('admin/orders.php');
    $items = q('SELECT * FROM order_items WHERE order_id = ?', [$id])->fetchAll();
    admin_header('Заказ № ' . $id, 'orders');
    ?>
    <div class="a-head"><h1>Заказ № <?= $id ?> <span class="pill pill-<?= e($o['status']) ?>" style="vertical-align:middle"><?= e(status_label($o['status'])) ?></span></h1><a href="<?= url('admin/orders.php') ?>">← Все заказы</a></div>
    <p class="a-muted">Создан <?= date('d.m.Y H:i', strtotime($o['created_at'])) ?><?= $o['user_id'] ? ' · клиент зарегистрирован (' . e($o['u_email'] ?: $o['u_phone']) . ')' : ' · без регистрации' ?></p>
    <form method="post" class="a-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="save">
      <div class="a-card">
        <h2>Статус</h2>
        <div class="a-grid">
          <label class="a-field"><span>Статус заказа</span><select name="status"><?php foreach ($statuses as $k => $l): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
          <?= a_textarea('manager_note', 'Комментарий менеджера (виден клиенту в кабинете)', $o['manager_note'], '', false, 2) ?>
        </div>
        <label class="a-check" style="margin-top:10px"><input type="checkbox" name="notify" value="1" checked> Отправить клиенту письмо при смене статуса</label>
      </div>
      <div class="a-card">
        <h2>Покупатель</h2>
        <div class="a-grid">
          <?= a_input('company', 'Компания', $o['company']) ?>
          <?= a_input('inn', 'ИНН', $o['inn']) ?>
          <?= a_input('contact', 'Контактное лицо', $o['contact']) ?>
          <?= a_input('phone', 'Телефон', $o['phone']) ?>
          <?= a_input('email', 'Email', $o['email']) ?>
          <?= a_input('city', 'Город доставки', $o['city']) ?>
        </div>
        <?php if ($o['comment']): ?><p style="margin:14px 0 0"><b>Комментарий клиента:</b> <?= nl2br(e($o['comment'])) ?></p><?php endif; ?>
        <p style="margin:10px 0 0"><a href="tel:<?= e(preg_replace('~[^\d+]~', '', $o['phone'])) ?>">Позвонить</a> · <a href="mailto:<?= e($o['email']) ?>">Написать</a></p>
      </div>
      <div class="a-card">
        <h2>Состав заказа</h2>
        <p class="a-muted" style="margin-top:-6px">После согласования с клиентом можно изменить количество и цену. 0 или «Убрать» — позиция удаляется.</p>
        <div class="a-table-wrap"><table class="a-table">
          <thead><tr><th>Товар</th><th>Цена, ₽/шт.</th><th>Количество</th><th class="num">Сумма</th><th>Убрать</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><?= e($it['name']) ?> <?= e($it['size']) ?></td>
              <td><input class="a-input" style="width:120px" name="items[<?= $it['id'] ?>][price]" value="<?= e(rtrim(rtrim(number_format((float)$it['price'], 2, '.', ''), '0'), '.')) ?>"></td>
              <td><input class="a-input" style="width:120px" type="number" min="0" name="items[<?= $it['id'] ?>][qty]" value="<?= (int)$it['qty'] ?>"></td>
              <td class="num"><?= (float)$it['price'] > 0 ? fmt_money($it['price'] * $it['qty']) : '—' ?></td>
              <td><input type="checkbox" name="items[<?= $it['id'] ?>][remove]" value="1" aria-label="Убрать позицию"></td>
            </tr>
          <?php endforeach; ?>
          <tr><td colspan="2"><b>Итого</b></td><td><b><?= fmt_qty($o['total_qty']) ?> шт.</b></td><td class="num"><b><?= $o['total_sum'] > 0 ? fmt_money($o['total_sum']) : '—' ?></b></td><td></td></tr>
          </tbody>
        </table></div>
      </div>
      <div class="a-save"><button class="a-btn" type="submit">Сохранить заказ</button></div>
    </form>
    <form method="post" data-confirm="Удалить заказ № <?= $id ?> без возможности восстановления?">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete">
      <button class="a-btn a-btn-danger a-btn-sm" type="submit">Удалить заказ</button>
    </form>
    <?php
    admin_footer();
    exit;
}

$status = (string)($_GET['status'] ?? '');
$search = trim((string)($_GET['q'] ?? ''));
$where = [];
$params = [];
if (isset($statuses[$status])) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $where[] = '(company LIKE ? OR inn LIKE ? OR phone LIKE ? OR email LIKE ? OR CAST(id AS TEXT) = ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%", $search);
}
$orders = q('SELECT * FROM orders' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 500', $params)->fetchAll();

admin_header('Заказы', 'orders');
?>
<div class="a-head"><h1>Заказы</h1></div>
<div class="a-filters">
  <a class="a-chip <?= $status === '' ? 'on' : '' ?>" href="<?= url('admin/orders.php') ?>">Все</a>
  <?php foreach ($statuses as $k => $l): ?><a class="a-chip <?= $status === $k ? 'on' : '' ?>" href="<?= url('admin/orders.php?status=' . $k) ?>"><?= e($l) ?></a><?php endforeach; ?>
  <form method="get" style="margin-left:auto; display:flex; gap:6px"><input class="a-input" name="q" value="<?= e($search) ?>" placeholder="№, компания, ИНН, телефон" style="width:260px"><button class="a-btn a-btn-dark a-btn-sm" type="submit">Найти</button></form>
</div>
<?php if (!$orders): ?>
  <div class="a-card a-muted">Заказов не найдено.</div>
<?php else: ?>
<div class="a-table-wrap"><table class="a-table">
  <thead><tr><th>№</th><th>Дата</th><th>Компания / ИНН</th><th>Контакт</th><th>Город</th><th class="num">Штук</th><th class="num">Сумма</th><th>Статус</th></tr></thead>
  <tbody>
  <?php foreach ($orders as $o): ?>
    <tr>
      <td><a href="<?= url('admin/orders.php?id=' . $o['id']) ?>"><b><?= $o['id'] ?></b></a></td>
      <td><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
      <td><a href="<?= url('admin/orders.php?id=' . $o['id']) ?>"><?= e($o['company']) ?></a><div class="a-muted" style="font-size:13px"><?= e($o['inn']) ?></div></td>
      <td><?= e($o['contact']) ?><div class="a-muted" style="font-size:13px"><?= e($o['phone']) ?></div></td>
      <td><?= e($o['city']) ?></td>
      <td class="num"><?= fmt_qty($o['total_qty']) ?></td>
      <td class="num"><?= $o['total_sum'] > 0 ? fmt_money($o['total_sum']) : '—' ?></td>
      <td><span class="pill pill-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php admin_footer();
