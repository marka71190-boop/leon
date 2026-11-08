<?php
// Человекопонятные адреса: /catalog, /catalog/razdel, /product/tovar, /contacts, /about …
// Сюда приходят все запросы, для которых на диске нет файла (.htaccess).
require_once __DIR__ . '/inc/bootstrap.php';
define('LP_ROUTED', true);

$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (BASE !== '' && str_starts_with($path, BASE . '/')) {
    $path = substr($path, strlen(BASE));
}
$path = trim($path, '/');
$query = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);

// Один адрес — одна страница: без слэша в конце, строчными буквами
$raw = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '' && (str_ends_with($raw, '/') || $path !== mb_strtolower($path) || str_contains($raw, '//'))) {
    redirect_301(url(mb_strtolower($path)) . ($query !== '' ? '?' . $query : ''));
}

$serve = static function (string $script, array $get = []): never {
    foreach ($get as $k => $v) {
        $_GET[$k] = $v;
    }
    require __DIR__ . '/' . $script;
    exit;
};

$parts = $path === '' ? [] : explode('/', $path);
$one = $parts[0] ?? '';

if (!$parts) $serve('index.php');
if (count($parts) === 1) {
    if ($one === 'catalog') $serve('catalog.php');
    if ($one === 'contacts') $serve('contacts.php');
    if ($one === 'certificates') $serve('certificates.php');
    if ($one === 'sitemap.xml') $serve('sitemap.php');
    if ($one === 'robots.txt') $serve('robots.php');
    if ($one === 'favicon.ico' && is_file(__DIR__ . '/assets/favicon.ico')) {
        header('Content-Type: image/x-icon');
        header('Cache-Control: public, max-age=2592000');
        readfile(__DIR__ . '/assets/favicon.ico');
        exit;
    }
    if (!in_array($one, slug_reserved(), true)) {
        $page = q('SELECT slug FROM pages WHERE slug = ?', [$one])->fetchColumn();
        if ($page !== false) $serve('page.php', ['p' => $page]);
    }
}
if (count($parts) === 2 && $one === 'catalog') {
    $id = q('SELECT id FROM categories WHERE slug = ? AND active = 1', [$parts[1]])->fetchColumn();
    if ($id !== false) $serve('catalog.php', ['cat' => (int)$id]);
}
if (count($parts) === 2 && $one === 'product') {
    $id = q('SELECT id FROM products WHERE slug = ? AND active = 1', [$parts[1]])->fetchColumn();
    if ($id !== false) $serve('product.php', ['id' => (int)$id]);
}

// Адрес переехал (поменяли название, удалили товар) — 301 на новый
$to = q('SELECT new_path FROM redirects WHERE old_path = ?', [$path])->fetchColumn();
if ($to !== false) {
    redirect_301(url((string)$to) . ($query !== '' ? '?' . $query : ''));
}

page_404();
