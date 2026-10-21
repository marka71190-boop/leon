<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
$me = require_admin('clients');
$isOwner = admin_can($me, 'owner');

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    $action = post('action');
    // Удаление всех клиентов — только главный администратор, с подтверждением словом УДАЛИТЬ
    if ($action === 'purge') {
        if (!$isOwner) {
            flash('Удалить всех клиентов может только главный администратор.', 'error');
        } elseif (mb_strtoupper(trim(post('confirm'))) !== 'УДАЛИТЬ') {
            flash('Чтобы удалить всех клиентов, впишите слово УДАЛИТЬ.', 'error');
        } else {
            $n = (int)q('SELECT COUNT(*) FROM users')->fetchColumn();
            $msg = 'Удалено клиентов: ' . $n . '.';
            q('DELETE FROM users');
            if (post('with_orders') === '1') {
                $o = (int)q('SELECT COUNT(*) FROM orders')->fetchColumn();
                q('DELETE FROM order_items');
                q('DELETE FROM order_events');
                q('DELETE FROM orders');
                $msg .= ' Удалено заказов: ' . $o . '.';
            }
            if (post('with_consents') === '1') {
                $c = (int)q('SELECT COUNT(*) FROM consents')->fetchColumn();
                q('DELETE FROM consents');
                q('DELETE FROM consent_docs');
                $msg .= ' Журнал согласий очищен (' . $c . ' записей).';
            }
            flash($msg);
        }
        redirect('admin/clients.php');
    }
    if ($action === 'password') {
        $pass = (string)($_POST['password'] ?? '');
        if (mb_strlen($pass) < 6) {
            flash('Пароль — не короче 6 символов.', 'error');
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            flash('Новый пароль клиента сохранён. Сообщите его клиенту.');
        }
        redirect('admin/clients.php?id=' . $id);
    }
    if ($action === 'save') {
        q('UPDATE users SET company=?, inn=?, contact=?, city=? WHERE id=?', [post('company'), post('inn'), post('contact'), post('city'), $id]);
        flash('Данные клиента сохранены.');
        redirect('admin/clients.php?id=' . $id);
    }
    if ($action === 'delete') {
        q('DELETE FROM users WHERE id = ?', [$id]);
        flash('Клиент удалён. Его заказы сохранены.');
        redirect('admin/clients.php');
    }
}

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $u = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();
    if (!$u) redirect('admin/clients.php');
    $orders = q('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC', [$id])->fetchAll();
    $consents = q('SELECT * FROM consents WHERE user_id = ? ORDER BY id DESC LIMIT 10', [$id])->fetchAll();
    admin_header('Клиент', 'clients');
    ?>
    <div class="a-head"><h1><?= e($u['company'] ?: ($u['email'] ?: '+' . $u['phone'])) ?></h1><a href="<?= url('admin/clients.php') ?>">← Все клиенты</a></div>
    <div class="a-card">
      <h2>Реквизиты</h2>
      <form method="post" class="a-form"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $id ?>">
        <div class="a-grid">
          <?= a_input('company', 'Компания', $u['company']) ?><?= a_input('inn', 'ИНН', $u['inn']) ?>
          <?= a_input('contact', 'Контактное лицо', $u['contact']) ?><?= a_input('city', 'Город', $u['city']) ?>
        </div>
        <p class="a-muted" style="margin:0">Вход: <?= e($u['email'] ?: '—') ?> · <?= $u['phone'] ? '+' . e($u['phone']) : '—' ?> · с <?= date('d.m.Y', strtotime($u['created_at'])) ?></p>
        <div><button class="a-btn" type="submit">Сохранить</button></div>
      </form>
    </div>
    <div class="a-card">
      <h2>Сбросить пароль</h2>
      <form method="post" class="a-form" style="max-width:420px"><?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= $id ?>">
        <?= a_input('password', 'Новый пароль', '', 'text', 'Не короче 6 символов', ['required' => true, 'minlength' => 6]) ?>
        <div><button class="a-btn a-btn-dark" type="submit">Установить пароль</button></div>
      </form>
    </div>
    <div class="a-card">
      <h2>Заказы клиента</h2>
      <?php if (!$orders): ?><p class="a-muted">Заказов нет.</p><?php else: ?>
      <div class="a-table-wrap"><table class="a-table"><tbody>
        <?php foreach ($orders as $o): ?><tr><td><a href="<?= url('admin/orders.php?id=' . $o['id']) ?>">№ <?= $o['id'] ?></a></td><td><?= date('d.m.Y', strtotime($o['created_at'])) ?></td><td class="num"><?= fmt_qty($o['total_qty']) ?> шт.</td><td><span class="pill pill-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="a-card">
      <h2>Согласия</h2>
      <?php if (!$consents): ?><p class="a-muted">Согласий не записано: клиент зарегистрировался до появления журнала согласий.</p><?php else: ?>
      <div class="a-table-wrap"><table class="a-table"><tbody>
        <?php foreach ($consents as $c): ?><tr><td style="white-space:nowrap"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></td><td><?= e(consent_kinds()[$c['kind']][0] ?? $c['kind']) ?><?= $c['revoked_at'] ? ' <span style="color:#B42318">(отозвано)</span>' : '' ?></td><td><?= e(consent_sources()[$c['source']] ?? $c['source']) ?><?= $c['order_id'] ? ', заявка № ' . (int)$c['order_id'] : '' ?></td><td class="a-muted">IP <?= e($c['ip'] ?: '—') ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <p style="margin:10px 0 0; display:flex; gap:14px; flex-wrap:wrap"><a class="a-btn a-btn-out a-btn-sm" href="<?= url('admin/consent_doc.php?user=' . $id) ?>" target="_blank">Документы-согласия (печать / PDF)</a><a href="<?= url('admin/consents.php?user=' . $id) ?>">Все согласия клиента в журнале →</a></p>
      <?php endif; ?>
    </div>
    <form method="post" data-confirm="Удалить аккаунт клиента? Заказы и журнал согласий останутся."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>"><button class="a-btn a-btn-danger a-btn-sm" type="submit">Удалить клиента</button></form>
    <?php
    admin_footer();
    exit;
}

$users = q('SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS cnt, (SELECT MAX(created_at) FROM orders o WHERE o.user_id = u.id) AS last
            FROM users u ORDER BY u.id DESC')->fetchAll();
admin_header('Клиенты', 'clients');
?>
<h1>Клиенты</h1>
<?php if (!$users): ?><div class="a-card a-muted">Зарегистрированных клиентов пока нет.</div><?php else: ?>
<div class="a-table-wrap"><table class="a-table">
  <thead><tr><th>Компания</th><th>ИНН</th><th>Email / телефон</th><th class="num">Заказов</th><th>Последний заказ</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr><td><a href="<?= url('admin/clients.php?id=' . $u['id']) ?>"><b><?= e($u['company'] ?: 'не указана') ?></b></a></td><td><?= e($u['inn']) ?></td><td><?= e($u['email'] ?: '') ?> <?= $u['phone'] ? '+' . e($u['phone']) : '' ?></td><td class="num"><?= (int)$u['cnt'] ?></td><td><?= $u['last'] ? date('d.m.Y', strtotime($u['last'])) : '—' ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php if ($isOwner): ?>
<details class="a-card a-danger-zone" style="margin-top:24px">
  <summary><b>Удалить всех клиентов</b> <span class="a-muted">— только для главного администратора</span></summary>
  <form method="post" class="a-form" style="margin-top:14px" data-confirm="Удалить всех клиентов без возможности восстановления?">
    <?= csrf_field() ?><input type="hidden" name="action" value="purge">
    <p style="margin:0">Удаляются все аккаунты покупателей. Восстановить их нельзя. Отметьте, что удалить вместе с ними:</p>
    <label class="a-check"><input type="checkbox" name="with_orders" value="1"> Все заказы и их историю</label>
    <label class="a-check"><input type="checkbox" name="with_consents" value="1"> Журнал согласий на обработку персональных данных</label>
    <?= a_input('confirm', 'Для подтверждения впишите слово УДАЛИТЬ', '', 'text', '', ['required' => true, 'autocomplete' => 'off']) ?>
    <div><button class="a-btn a-btn-danger" type="submit">Удалить всех клиентов</button></div>
  </form>
</details>
<?php endif; ?>
<?php admin_footer();
