<?php
// Правила для поисковых роботов: /robots.txt
require_once __DIR__ . '/inc/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
if (setting('seo_noindex') === '1') {
    // В настройках включено «Скрыть сайт от поисковиков»
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
$base = rtrim(BASE, '/');
// Служебные страницы и результаты поиска/фильтров. Старые адреса (*.php?id=…) не закрываем:
// по ним поисковик должен увидеть переадресацию 301 на новые адреса и перенести их «вес»
$closed = ['/admin/', '/api/', '/inc/', '/data/', '/cart.php', '/account.php', '/login.php', '/logout.php',
           '/order.php', '/invoice.php', '/*?q=', '/*&q=', '/*?*sort=', '/*?*size', '/*?*type'];
$lines = [];
foreach (['Yandex', '*'] as $bot) {
    $lines[] = 'User-agent: ' . $bot;
    foreach ($closed as $d) {
        $lines[] = 'Disallow: ' . $base . $d;
    }
    // Картинки, стили и скрипты открыты: без них поисковик не увидит страницу так, как посетитель
    $lines[] = 'Allow: ' . $base . '/assets/';
    $lines[] = 'Allow: ' . $base . '/uploads/';
    if ($bot === 'Yandex') {
        // Метки рекламы и рассылок не создают отдельных страниц
        $lines[] = 'Clean-param: utm_source&utm_medium&utm_campaign&utm_content&utm_term&yclid&gclid&fbclid&_openstat&from&ysclid&etext';
    }
    $lines[] = '';
}
$lines[] = 'Sitemap: ' . abs_url('sitemap.xml');
echo implode("\n", $lines), "\n";
