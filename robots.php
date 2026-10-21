<?php
// Правила для поисковых роботов: /robots.txt
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
if (setting('seo_noindex') === '1') {
    // В настройках включено «Скрыть сайт от поисковиков»
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
$base = rtrim(BASE, '/');
$lines = ['User-agent: *'];
foreach (['/admin/', '/api/', '/cart.php', '/account.php', '/login.php', '/logout.php', '/order.php', '/invoice.php', '/*?q=', '/*&q=', '/*sort='] as $d) {
    $lines[] = 'Disallow: ' . $base . $d;
}
$lines[] = '';
$lines[] = 'Sitemap: ' . abs_url('sitemap.xml');
echo implode("\n", $lines), "\n";
