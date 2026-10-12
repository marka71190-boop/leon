<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
$admin = require_admin('orders');

$statuses = order_statuses();
$steps = order_steps();
$stepKeys = array_keys($steps);

/** Письмо клиенту о новом этапе заказа */
function notify_client(array $order, string $status, string $note = ''): void
{
    if (!filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $label = status_label_client($status);
    send_mail($order['email'], 'Заказ № ' . $order['id'] . ': ' . $label,
        "Здравствуйте!\n\nСтатус вашего заказа № {$order['id']}: $label."
        . ($note !== '' ? "\n\nКомментарий менеджера: $note" : '')
        . "\n\n" . setting('site_name') . "\n" . setting('phone'));
}

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    $action = post('action');
    $order = q('SELECT * FROM orders WHERE id = ?', [$id])->fetch();
    if (!$order) {
        redirect('admin/orders.php');
    }
    $who = (string)$admin['login'];

    // Следующий этап одной кнопкой. Проверяем, с какого этапа нажали, чтобы двойной клик не перескочил через этап
    if ($action === 'advance') {
        $next = order_next_status($order['status']);
        if ($next && post('from') === $order['status']) {
            $note = post('note');
            q('UPDATE orders SET status = ?, updated_at = ? WHERE id = ?', [$next, date('Y-m-d H:i:s'), $id]);
            order_event($id, $next, $who, $note);
            if (!empty($_POST['notify'])) {
                notify_client($order, $next, $order['manager_note']);
            }
            flash('Заказ № ' . $id . ': этап «' . $statuses[$next] . '».' . ($next === 'closed' ? ' Заказ закрыт.' : ''));
        }
        redirect('admin/orders.php?id=' . $id);
    }
    if ($action === 'cancel' && $order['status'] !== 'cancelled') {
        q("UPDATE orders SET status = 'cancelled', updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $id]);
        order_event($id, 'cancelled', $who, post('note'));
        flash('Заказ № ' . $id . ' отменён.');
        redirect('admin/orders.php?id=' . $id);
    }
    if ($action === 'restore' && $order['status'] === 'cancelled') {
        $prev = q("SELECT status FROM order_events WHERE order_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 1", [$id])->fetchColumn() ?: 'new';
        q('UPDATE orders SET status = ?, updated_at = ? WHERE id = ?', [$prev, date('Y-m-d H:i:s'), $id]);
        order_event($id, $prev, $who, 'Заказ возвращён в работу');
        flash('Заказ № ' . $id . ' снова в работе: этап «' . $statuses[$prev] . '».');
        redirect('admin/orders.php?id=' . $id);
    }
    if ($action === 'delete') {
        q('DELETE FROM order_events WHERE order_id = ?', [$id]);
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
        if ($status !== $order['status']) {
            order_event($id, $status, $who, 'Этап изменён вручную');
            if (!empty($_POST['notify'])) {
                notify_client(array_merge($order, ['email' => post('email')]), $status, post('manager_note'));
            }
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
    $consents = q('SELECT * FROM consents WHERE order_id = ? ORDER BY id', [$id])->fetchAll();
    $events = q('SELECT * FROM order_events WHERE order_id = ? ORDER BY id', [$id])->fetchAll();
    $reached = [];
    foreach ($events as $ev) {
        $reached[$ev['status']] = $ev['created_at'];
    }
    $cancelled = $o['status'] === 'cancelled';
    $curIndex = array_search($cancelled ? (q("SELECT status FROM order_events WHERE order_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 1", [$id])->fetchColumn() ?: 'new') : $o['status'], $stepKeys, true);
    $next = order_next_status($o['status']);
    $tel = preg_replace('~[^\d+]~', '', (string)$o['phone']);
    admin_header('Заказ № ' . $id, 'orders');
    ?>
    <div class="a-head"><h1>Заказ № <?= $id ?> <span class="pill pill-<?= e($o['status']) ?>" style="vertical-align:middle"><?= e(status_label($o['status'])) ?></span></h1><a href="<?= url('admin/orders.php') ?>">← Все заказы</a></div>
    <p class="a-muted" style="margin-top:-12px"><b style="color:var(--ink)"><?= e($o['company']) ?></b> · <?= e($o['city']) ?> · <?= fmt_qty($o['total_qty']) ?> шт. · создан <?= date('d.m.Y H:i', strtotime($o['created_at'])) ?><?= $o['user_id'] ? ' · клиент зарегистрирован' : ' · без регистрации' ?></p>

    <ol class="a-steps<?= $cancelled ? ' is-cancelled' : '' ?>" aria-label="Этапы заказа">
      <?php foreach ($stepKeys as $i => $k): $state = $i < $curIndex || ($i === $curIndex && $k === 'closed') ? 'done' : ($i === $curIndex ? 'cur' : ''); ?>
        <li class="<?= $state ?>"<?= $state === 'cur' ? ' aria-current="step"' : '' ?>>
          <span class="a-step-dot"><?= $state === 'done' ? '✓' : $i + 1 ?></span>
          <b><?= e($steps[$k][0]) ?></b>
          <small><?= isset($reached[$k]) && $i <= $curIndex ? date('d.m H:i', strtotime($reached[$k])) : '' ?></small>
        </li>
      <?php endforeach; ?>
    </ol>

    <?php if ($cancelled): ?>
      <div class="a-card a-now a-now-off">
        <div><b>Заказ отменён</b><?= isset($reached['cancelled']) ? ' ' . date('d.m.Y H:i', strtotime($reached['cancelled'])) : '' ?>.</div>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="restore"><button class="a-btn a-btn-dark" type="submit">Вернуть в работу</button></form>
      </div>
    <?php elseif ($next): ?>
      <div class="a-card a-now">
        <div class="a-now-text">
          <span class="a-muted">Сейчас: <b style="color:var(--ink)"><?= e($statuses[$o['status']]) ?></b></span>
          <p class="a-now-todo"><?= e($steps[$o['status']][2]) ?></p>
          <p class="a-now-contacts">
            <?php if ($tel): ?><a href="tel:<?= e($tel) ?>">📞 <?= e($o['phone']) ?></a><?php endif; ?>
            <?php if ($o['email']): ?><a href="mailto:<?= e($o['email']) ?>">✉ <?= e($o['email']) ?></a><?php endif; ?>
            <span><?= e($o['contact']) ?></span>
          </p>
        </div>
        <form method="post" class="a-now-form">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="advance"><input type="hidden" name="from" value="<?= e($o['status']) ?>">
          <input class="a-input" name="note" placeholder="Заметка для себя (необязательно): например, ТК Деловые Линии, 12 400 ₽">
          <?php if (filter_var($o['email'], FILTER_VALIDATE_EMAIL)): ?><label class="a-check"><input type="checkbox" name="notify" value="1"> Сообщить клиенту на email</label><?php endif; ?>
          <button class="a-btn a-btn-next" type="submit">✓ Готово — следующий этап: <?= e($statuses[$next]) ?></button>
        </form>
        <details class="a-cancel"><summary>Отменить заказ</summary>
          <form method="post" style="display:flex; gap:8px; margin-top:8px; flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="cancel">
            <input class="a-input" name="note" placeholder="Причина отмены" style="flex:1 1 220px"><button class="a-btn a-btn-danger a-btn-sm" type="submit">Отменить заказ</button></form>
        </details>
      </div>
    <?php else: ?>
      <div class="a-card a-now a-now-done"><b>Заказ закрыт<?= isset($reached['closed']) ? ' ' . date('d.m.Y', strtotime($reached['closed'])) : '' ?>.</b> Все этапы пройдены.</div>
    <?php endif; ?>

    <form method="post" class="a-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="save">
      <div class="a-card">
        <h2>Состав заказа</h2>
        <p class="a-muted" style="margin-top:-6px">После согласования с клиентом можно изменить количество и цену. 0 или «Убрать» — позиция удаляется.</p>
        <div class="a-table-wrap"><table class="a-table">
          <thead><tr><th>Товар</th><th>Упаковки</th><th>Цена, ₽/шт.</th><th>Количество, шт.</th><th class="num">Сумма</th><th>Убрать</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): $pk = (int)$it['pack_qty']; ?>
            <tr>
              <td><?= e($it['name']) ?> <?= e($it['size']) ?></td>
              <td style="white-space:nowrap"><?= $pk ? fmt_qty(intdiv((int)$it['qty'], $pk)) . ' уп.' . ((int)$it['qty'] % $pk ? ' + ' . fmt_qty((int)$it['qty'] % $pk) . ' шт.' : '') . '<div class="a-muted" style="font-size:12px">по ' . fmt_qty($pk) . ' шт.</div>' : '<span class="a-muted">поштучно</span>' ?></td>
              <td><input class="a-input" style="width:110px" name="items[<?= $it['id'] ?>][price]" value="<?= e(rtrim(rtrim(number_format((float)$it['price'], 2, '.', ''), '0'), '.')) ?>"></td>
              <td><input class="a-input" style="width:120px" type="number" min="0"<?= $pk ? ' step="' . $pk . '"' : '' ?> name="items[<?= $it['id'] ?>][qty]" value="<?= (int)$it['qty'] ?>"></td>
              <td class="num"><?= (float)$it['price'] > 0 ? fmt_money($it['price'] * $it['qty']) : '—' ?></td>
              <td><input type="checkbox" name="items[<?= $it['id'] ?>][remove]" value="1" aria-label="Убрать позицию"></td>
            </tr>
          <?php endforeach; ?>
          <tr><td colspan="3"><b>Итого</b></td><td><b><?= fmt_qty($o['total_qty']) ?> шт.</b></td><td class="num"><b><?= $o['total_sum'] > 0 ? fmt_money($o['total_sum']) : '—' ?></b></td><td></td></tr>
          </tbody>
        </table></div>
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
        <p class="a-muted" style="margin:10px 0 0">Согласия: <?php if ($consents): ?><?= e(implode(', ', array_map(fn($c) => (consent_kinds()[$c['kind']][0] ?? $c['kind']) . ' ✓' . ($c['revoked_at'] ? ' (отозвано)' : ''), $consents))) ?> · <?= date('d.m.Y H:i:s', strtotime($consents[0]['created_at'])) ?> · IP <?= e($consents[0]['ip'] ?: '—') ?> · <a href="<?= url('admin/consent_doc.php?order=' . $id) ?>" target="_blank">документ-согласие</a> · <a href="<?= url('admin/consents.php?q=' . $id) ?>">в журнале</a><?php else: ?>не записаны (заявка оформлена до появления журнала)<?php endif; ?></p>
      </div>
      <div class="a-card">
        <h2>Комментарий для клиента</h2>
        <?= a_textarea('manager_note', 'Клиент видит его в личном кабинете', $o['manager_note'], 'Например: «Отгружено ТК Деловые Линии, накладная № 12-345678»', false, 2) ?>
        <details style="margin-top:14px"><summary class="a-muted" style="cursor:pointer">Исправить этап вручную</summary>
          <div class="a-grid" style="margin-top:10px">
            <label class="a-field"><span>Этап заказа</span><select name="status"><?php foreach ($statuses as $k => $l): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
          </div>
          <label class="a-check" style="margin-top:8px"><input type="checkbox" name="notify" value="1"> Сообщить клиенту на email, если этап изменился</label>
        </details>
      </div>
      <div class="a-save"><button class="a-btn" type="submit">Сохранить изменения</button></div>
    </form>

    <div class="a-card">
      <h2>История заказа</h2>
      <ol class="a-history">
        <?php foreach (array_reverse($events) as $ev): ?>
          <li><span class="a-muted"><?= date('d.m.Y H:i', strtotime($ev['created_at'])) ?></span> <span class="pill pill-<?= e($ev['status']) ?>"><?= e(status_label($ev['status'])) ?></span> <?= $ev['who'] !== '' ? e($ev['who']) : '' ?><?= $ev['note'] !== '' ? ' — ' . e($ev['note']) : '' ?></li>
        <?php endforeach; ?>
        <?php if (!$events): ?><li class="a-muted">История пуста.</li><?php endif; ?>
      </ol>
    </div>

    <form method="post" data-confirm="Удалить заказ № <?= $id ?> без возможности восстановления?">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete">
      <button class="a-btn a-btn-danger a-btn-sm" type="submit">Удалить заказ</button>
    </form>
    <?php
    admin_footer();
    exit;
}

// Список заказов
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'work';
$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$where = [];
$params = [];
if ($status === 'work') {
    $where[] = "status NOT IN ('closed', 'cancelled')";
} elseif (isset($statuses[$status])) {
    $where[] = 'status = ?';
    $params[] = $status;
} else {
    $status = 'all';
}
if ($search !== '') {
    $where[] = '(company LIKE ? OR inn LIKE ? OR phone LIKE ? OR email LIKE ? OR CAST(id AS TEXT) = ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%", $search);
}
$orders = q('SELECT * FROM orders' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 500', $params)->fetchAll();
$counts = array_column(q('SELECT status, COUNT(*) AS n FROM orders GROUP BY status')->fetchAll(), 'n', 'status');
$inWork = array_sum(array_intersect_key($counts, array_flip(['new', 'contacted', 'delivery', 'paid', 'shipped'])));

admin_header('Заказы', 'orders');
?>
<div class="a-head"><h1>Заказы</h1></div>
<div class="a-filters">
  <a class="a-chip <?= $status === 'work' ? 'on' : '' ?>" href="<?= url('admin/orders.php') ?>">В работе · <?= $inWork ?></a>
  <?php foreach ($statuses as $k => $l): ?><a class="a-chip <?= $status === $k ? 'on' : '' ?>" href="<?= url('admin/orders.php?status=' . $k) ?>"><?= e($l) ?> · <?= (int)($counts[$k] ?? 0) ?></a><?php endforeach; ?>
  <a class="a-chip <?= $status === 'all' ? 'on' : '' ?>" href="<?= url('admin/orders.php?status=all') ?>">Все · <?= array_sum($counts) ?></a>
  <form method="get" style="margin-left:auto; display:flex; gap:6px"><input type="hidden" name="status" value="all"><input class="a-input" name="q" value="<?= e($search) ?>" placeholder="№, компания, ИНН, телефон" style="width:240px"><button class="a-btn a-btn-dark a-btn-sm" type="submit">Найти</button></form>
</div>
<?php if (!$orders): ?>
  <div class="a-card a-muted"><?= $status === 'work' ? 'Заказов в работе нет. Новые заявки появятся здесь.' : 'Заказов не найдено.' ?></div>
<?php else: ?>
<div class="a-table-wrap"><table class="a-table">
  <thead><tr><th>№</th><th>Дата</th><th>Компания / ИНН</th><th>Контакт</th><th>Город</th><th class="num">Штук</th><th class="num">Сумма</th><th>Этап</th></tr></thead>
  <tbody>
  <?php foreach ($orders as $o): $si = array_search($o['status'], $stepKeys, true); ?>
    <tr<?= $o['status'] === 'new' ? ' class="a-row-new"' : '' ?>>
      <td><a href="<?= url('admin/orders.php?id=' . $o['id']) ?>"><b><?= $o['id'] ?></b></a></td>
      <td><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
      <td><a href="<?= url('admin/orders.php?id=' . $o['id']) ?>"><?= e($o['company']) ?></a><div class="a-muted" style="font-size:13px"><?= e($o['inn']) ?></div></td>
      <td><?= e($o['contact']) ?><div class="a-muted" style="font-size:13px"><?= e($o['phone']) ?></div></td>
      <td><?= e($o['city']) ?></td>
      <td class="num"><?= fmt_qty($o['total_qty']) ?></td>
      <td class="num"><?= $o['total_sum'] > 0 ? fmt_money($o['total_sum']) : '—' ?></td>
      <td><span class="pill pill-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
        <?php if ($si !== false): ?><div class="a-dots" title="Этап <?= $si + 1 ?> из <?= count($stepKeys) ?>"><?php foreach ($stepKeys as $i => $k): ?><i class="<?= $i <= $si ? 'on' : '' ?>"></i><?php endforeach; ?></div><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php admin_footer();
