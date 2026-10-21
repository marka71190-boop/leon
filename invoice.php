<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/invoice.php';

// Счёт для клиента: только свой заказ и только после того, как менеджер выставил счёт
$user = require_user();
$id = is_string($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;
$o = $id ? q('SELECT * FROM orders WHERE id = ? AND user_id = ?', [$id, $user['id']])->fetch() : null;
if (!$o || !$o['invoice_date']) {
    flash('Счёт по этому заказу пока не выставлен. Менеджер пришлёт его после согласования заказа.', 'info');
    redirect('account.php');
}
$items = q('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$id])->fetchAll();
render_invoice($o, $items, url('account.php'), 'Личный кабинет');
