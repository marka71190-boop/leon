<?php
// Карта сайта для Яндекса и Google: /sitemap.xml
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
$urls = [['', '1.0'], ['catalog.php', '0.9']];
foreach (q('SELECT id FROM categories WHERE active = 1 ORDER BY sort, id')->fetchAll(PDO::FETCH_COLUMN) as $id) {
    $urls[] = ['catalog.php?cat=' . $id, '0.8'];
}
foreach (q('SELECT id FROM products WHERE active = 1 ORDER BY sort, id')->fetchAll(PDO::FETCH_COLUMN) as $id) {
    $urls[] = ['product.php?id=' . $id, '0.7'];
}
foreach (q('SELECT slug FROM pages ORDER BY sort, id')->fetchAll(PDO::FETCH_COLUMN) as $slug) {
    $urls[] = ['page.php?p=' . rawurlencode($slug), '0.5'];
}
$urls[] = ['certificates.php', '0.5'];
$urls[] = ['contacts.php', '0.6'];

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n", '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
if (setting('seo_noindex') !== '1') {
    foreach ($urls as [$path, $prio]) {
        echo '  <url><loc>', htmlspecialchars(abs_url($path), ENT_XML1), '</loc><priority>', $prio, '</priority></url>', "\n";
    }
}
echo '</urlset>', "\n";
