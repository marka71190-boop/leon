<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
// Демо-режим на Vercel: диск только для чтения, данные живут во временной папке
define('IS_DEMO', (bool)getenv('VERCEL'));
define('DATA_DIR', IS_DEMO ? '/tmp/leonpro/data' : ROOT . '/data');
define('UPLOAD_DIR', IS_DEMO ? '/tmp/leonpro/uploads' : ROOT . '/uploads');

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Moscow');

if (IS_DEMO) {
    // На Vercel несколько экземпляров сервера — сессию храним в подписанной cookie
    $secret = getenv('LEONPRO_SECRET') ?: 'leonpro-demo';
    $GLOBALS['_SESSION'] = [];
    if (!empty($_COOKIE['lp_s']) && str_contains($_COOKIE['lp_s'], '.')) {
        [$data, $sig] = explode('.', $_COOKIE['lp_s'], 2);
        if (hash_equals(hash_hmac('sha256', $data, $secret), $sig)) {
            $_SESSION = json_decode((string)base64_decode(strtr($data, '-_', '+/')), true) ?: [];
        }
    }
    ob_start();
    header_register_callback(function () use ($secret) {
        $d = rtrim(strtr(base64_encode(json_encode($_SESSION)), '+/', '-_'), '=');
        setcookie('lp_s', $d . '.' . hash_hmac('sha256', $d, $secret), [
            'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => true,
        ]);
    });
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('leonpro');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function session_regen(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

// Базовый путь сайта (работает и в корне домена, и в подпапке)
(function () {
    if (IS_DEMO) {
        define('BASE', '');
        return;
    }
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $inAdmin = basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'admin';
    $base = $inAdmin ? str_replace('\\', '/', dirname($scriptDir)) : $scriptDir;
    $base = rtrim($base, '/');
    define('BASE', $base === '.' ? '' : $base);
})();

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/layout.php';
