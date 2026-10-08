<?php
// Точка входа для Vercel (демо-показ). На обычном хостинге (Timeweb) этот файл не используется.
$path = '/' . trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if ($path === '/') $path = '/index.php';
if ($path === '/admin') $path = '/admin/index.php';

// Загруженные в демо файлы живут во временной папке
if (str_starts_with($path, '/uploads/')) {
    $base = realpath('/tmp/leonpro/uploads');
    $f = $base ? realpath($base . substr($path, 8)) : false;
    $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'pdf' => 'application/pdf'];
    $ext = strtolower(pathinfo((string)$f, PATHINFO_EXTENSION));
    if ($f && str_starts_with($f, $base . '/') && isset($types[$ext])) {
        header('Content-Type: ' . $types[$ext]);
        header('X-Content-Type-Options: nosniff');
        readfile($f);
        exit;
    }
    http_response_code(404);
    exit('Файл не найден (в демо-версии загрузки хранятся недолго)');
}

$root = realpath(dirname(__DIR__));
$file = realpath($root . $path);
$rel = $file ? substr($file, strlen($root)) : '';
if (!$file || !str_starts_with($file, $root . '/') || substr($file, -4) !== '.php'
    || preg_match('~^/(inc|api|data|uploads)/~', $rel) || basename($file)[0] === '_') {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    exit('<p style="font-family:sans-serif">Страница не найдена. <a href="/">На главную</a></p>');
}
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $path;
$_SERVER['SCRIPT_FILENAME'] = $file;
chdir(dirname($file));
require $file;
