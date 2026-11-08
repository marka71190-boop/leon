<?php
// Карта сайта для Яндекса и Google: /sitemap.xml (с датами изменений и фото товаров)
require_once __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$date = static fn(?string $d): string => $d ? date('c', strtotime($d)) : '';
$latest = static function (string $sql): string {
    return (string)(q($sql)->fetchColumn() ?: '');
};
$prodMax = $latest('SELECT MAX(updated_at) FROM products WHERE active = 1');
$catMax = $latest('SELECT MAX(updated_at) FROM categories WHERE active = 1');
$homeMod = max($prodMax, $catMax);

// [путь, дата изменения, картинки]
$urls = [['', $homeMod, []], ['catalog', $homeMod, []]];
foreach (q('SELECT c.*, (SELECT MAX(updated_at) FROM products p WHERE p.category_id = c.id AND p.active = 1) AS pmax
            FROM categories c WHERE c.active = 1 ORDER BY sort, id')->fetchAll() as $c) {
    $urls[] = [cat_url($c), max((string)$c['updated_at'], (string)$c['pmax']), $c['image'] ? [[$c['image'], $c['name']]] : []];
}
$imgs = [];
foreach (q("SELECT product_id, file FROM product_images WHERE COALESCE(kind, 'image') = 'image' ORDER BY sort, id")->fetchAll() as $im) {
    $imgs[$im['product_id']][] = $im['file'];
}
foreach (q('SELECT p.* FROM products p LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.active = 1 AND (c.id IS NULL OR c.active = 1) ORDER BY p.sort, p.id')->fetchAll() as $p) {
    $name = trim($p['name'] . ' ' . $p['size']);
    $files = $imgs[$p['id']] ?? ($p['image'] ? [$p['image']] : []);
    $urls[] = [product_url($p), (string)$p['updated_at'], array_map(fn($f) => [$f, $name], array_slice($files, 0, 10))];
}
foreach (q('SELECT slug, updated_at FROM pages ORDER BY sort, id')->fetchAll() as $pg) {
    $urls[] = [page_url($pg['slug']), (string)$pg['updated_at'], []];
}
$urls[] = ['certificates', $latest('SELECT MAX(created_at) FROM documents'), []];
$urls[] = ['contacts', '', []];

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">', "\n";
if (setting('seo_noindex') !== '1') {
    foreach ($urls as [$path, $mod, $images]) {
        echo '  <url><loc>', htmlspecialchars(abs_url($path), ENT_XML1), '</loc>';
        if ($mod !== '') {
            echo '<lastmod>', $date($mod), '</lastmod>';
        }
        foreach ($images as [$file, $title]) {
            $src = str_starts_with($file, 'assets/') ? abs_url($file) : abs_url('uploads/' . $file);
            echo '<image:image><image:loc>', htmlspecialchars($src, ENT_XML1), '</image:loc></image:image>';
        }
        echo '</url>', "\n";
    }
}
echo '</urlset>', "\n";
