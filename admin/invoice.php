<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/invoice.php';
require_admin('orders');

// Счёт на оплату по заказу. При первом открытии фиксируется дата счёта —
// после этого счёт виден клиенту в личном кабинете.
$id = is_string($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;
$o = $id ? q('SELECT * FROM orders WHERE id = ?', [$id])->fetch() : null;
if (!$o) {
    redirect('admin/orders.php');
}
if (!$o['invoice_date']) {
    q('UPDATE orders SET invoice_date = ? WHERE id = ?', [date('Y-m-d'), $id]);
    order_event($id, $o['status'], (string)(current_admin()['login'] ?? ''), 'Выставлен счёт № ' . $id);
    $o['invoice_date'] = date('Y-m-d');
}
$items = q('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$id])->fetchAll();
render_invoice($o, $items, url('admin/orders.php?id=' . $id), 'Заказ № ' . $id);
