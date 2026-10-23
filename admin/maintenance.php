<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_admin('settings');

// Кнопка «Технический перерыв»: включить или выключить заглушку для посетителей
if (is_post()) {
    csrf_check();
    $on = post('action') === 'on';
    setting_save('maintenance', $on ? '1' : '0');
    flash($on
        ? 'Технический перерыв включён: посетители видят заглушку, заказы не принимаются. Вы, как администратор, видите сайт как обычно.'
        : 'Технический перерыв выключен: сайт снова открыт для всех.');
}
back('admin/index.php');
