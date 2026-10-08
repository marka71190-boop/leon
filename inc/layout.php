<?php
declare(strict_types=1);

function icon(string $name, int $size = 22): string
{
    $paths = [
        'menu'   => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
        'cart'   => '<path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6"/><circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/>',
        'doc'    => '<path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/><path d="M9 13h7M9 17h5"/>',
        'trash'  => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'check'  => '<path d="M5 12l5 5 9-10"/>',
        'close'  => '<path d="M6 6l12 12M18 6L6 18"/>',
    ];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/** Знак-гора Leon_pro. $onDark — вариант для тёмного фона */
function logo_mark(int $w = 46, bool $onDark = false): string
{
    $peak = $onDark ? '#F4F1EC' : '#17171A';
    $sep  = $onDark ? '#1A1A1C' : '#FFFFFF';
    $h = (int)round($w * 0.8);
    return '<svg width="' . $w . '" height="' . $h . '" viewBox="0 0 120 96" aria-hidden="true">'
        . '<polygon points="46,6 6,74 86,74" fill="' . $peak . '"/>'
        . '<polygon points="66,14 28,74 104,74" fill="' . $peak . '" stroke="' . $sep . '" stroke-width="4" stroke-linejoin="round"/>'
        . '<polygon points="66,34 40,74 92,74" fill="var(--accent)"/>'
        . '<polygon points="14,66 106,66 116,92 4,92" fill="var(--accent)"/></svg>';
}

function logo_big(int $w = 300): string
{
    $h = (int)round($w * 470 / 600);
    return '<svg viewBox="0 0 600 470" width="' . $w . '" height="' . $h . '" role="img" aria-label="' . e(setting('site_name')) . '">'
        . '<defs><linearGradient id="lgOr" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#F59A3A"/><stop offset="1" stop-color="#D9620F"/></linearGradient></defs>'
        . '<polygon points="250,40 40,400 460,400" fill="#F4F1EC"/><polygon points="330,70 130,400 530,400" fill="#F4F1EC"/>'
        . '<polygon points="320,140 170,400 470,400" fill="url(#lgOr)"/><polygon points="90,360 510,360 560,450 40,450" fill="var(--accent)"/>'
        . '<text x="300" y="424" text-anchor="middle" fill="#17171A" font-family="Unbounded, sans-serif" font-weight="700" font-size="54">' . e(setting('site_name')) . '</text></svg>';
}

function wordmark(): string
{
    $name = setting('site_name');
    $first = mb_substr($name, 0, 1);
    $rest = mb_substr($name, 1);
    return '<span class="wordmark"><span class="accent-text">' . e($first) . '</span>' . e($rest) . '</span>';
}

function accent_css(): string
{
    $c = setting('accent_color');
    if (!preg_match('~^#[0-9a-f]{6}$~i', $c)) {
        $c = '#F07F1F';
    }
    return '<style>:root{--accent:' . $c . '}</style>';
}

function page_header(string $title = '', array $opt = []): void
{
    $siteTitle = $title ? $title . ' — ' . setting('site_name') : setting('seo_title');
    $desc = $opt['description'] ?? setting('seo_description');
    $menuPages = q('SELECT slug, title FROM pages WHERE in_menu = 1 ORDER BY sort, id')->fetchAll();
    $cats = q('SELECT id, name FROM categories WHERE active = 1 ORDER BY sort, id')->fetchAll();
    $cartCount = cart_count();
    $user = current_user();
    ?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($siteTitle) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="icon" href="<?= url('assets/favicon.svg') ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;600;700&family=Golos+Text:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('assets/style.css') ?>">
<?= accent_css() ?>
<?= setting('head_code') ?>
</head>
<body>
<a class="skip" href="#main">Перейти к содержимому</a>
<?php if (IS_DEMO): ?><div class="demo-bar">Демо-версия для просмотра: заказы и изменения в админке не сохраняются надолго.</div><?php endif; ?>
<header class="site-header">
  <div class="topbar">
    <div class="container topbar-in">
      <span><?= e(setting('topbar_text')) ?></span>
      <span>Заказ от <?= fmt_qty(min_order()) ?> шт.</span>
      <a class="topbar-phone" href="tel:<?= e(preg_replace('~[^\d+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a>
    </div>
  </div>
  <div class="container header-main">
    <button class="burger" type="button" aria-label="Меню" aria-expanded="false" data-burger><?= icon('menu') ?></button>
    <a class="brand" href="<?= url() ?>" aria-label="<?= e(setting('site_name')) ?> — на главную">
      <?php if (setting('logo_image')): ?>
        <img src="<?= e(upload_url(setting('logo_image'))) ?>" alt="<?= e(setting('site_name')) ?>" class="brand-img">
      <?php else: ?>
        <?= logo_mark() ?><?= wordmark() ?>
      <?php endif; ?>
    </a>
    <a class="btn btn-accent btn-catalog" href="<?= url('catalog.php') ?>"><?= icon('menu', 18) ?> Каталог</a>
    <form class="search" action="<?= url('catalog.php') ?>" method="get" role="search">
      <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Поиск по каталогу" placeholder="Поиск: название, размер, артикул">
      <button type="submit" aria-label="Найти"><?= icon('search', 20) ?></button>
    </form>
    <div class="header-actions">
      <a href="<?= url($user ? 'account.php' : 'login.php') ?>" class="hact"><?= icon('user') ?><span><?= $user ? 'Кабинет' : 'Войти' ?></span></a>
      <a href="<?= url('cart.php') ?>" class="hact"><?= icon('cart') ?><span>Корзина</span><?php if ($cartCount): ?><b class="badge"><?= $cartCount ?></b><?php endif; ?></a>
    </div>
  </div>
  <nav class="container mainnav" aria-label="Разделы" data-nav>
    <?php foreach ($cats as $c): ?>
      <a href="<?= url('catalog.php?cat=' . $c['id']) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
    <span class="nav-sep"></span>
    <?php foreach ($menuPages as $p): ?>
      <a class="muted-link" href="<?= url('page.php?p=' . urlencode($p['slug'])) ?>"><?= e($p['title']) ?></a>
    <?php endforeach; ?>
    <a class="muted-link" href="<?= url('certificates.php') ?>">Сертификаты</a>
    <a class="muted-link" href="<?= url('contacts.php') ?>">Контакты</a>
  </nav>
</header>
<main id="main">
<?php foreach (flash() as $f): ?>
  <div class="container"><div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div></div>
<?php endforeach; ?>
<?php
}

function page_footer(): void
{
    $cats = q('SELECT id, name FROM categories WHERE active = 1 ORDER BY sort, id')->fetchAll();
    $pages = q('SELECT slug, title FROM pages ORDER BY sort, id')->fetchAll();
    ?>
</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-about">
      <span class="wordmark wordmark-light"><?= wordmark() ?></span>
      <p><?= e(setting('company_short')) ?></p>
      <p class="footer-slogan"><?= e(setting('slogan')) ?></p>
    </div>
    <nav aria-label="Каталог">
      <h2>Каталог</h2>
      <?php foreach ($cats as $c): ?><a href="<?= url('catalog.php?cat=' . $c['id']) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
    </nav>
    <nav aria-label="Покупателям">
      <h2>Покупателям</h2>
      <?php foreach ($pages as $p): ?><a href="<?= url('page.php?p=' . urlencode($p['slug'])) ?>"><?= e($p['title']) ?></a><?php endforeach; ?>
      <a href="<?= url('certificates.php') ?>">Сертификаты</a>
      <a href="<?= url('account.php') ?>">Личный кабинет</a>
    </nav>
    <address>
      <h2>Контакты</h2>
      <span><?= e(setting('address')) ?></span>
      <a class="footer-phone" href="tel:<?= e(preg_replace('~[^\d+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a>
      <?php if (setting('email')): ?><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a><?php endif; ?>
      <?php if (setting('work_hours')): ?><span><?= e(setting('work_hours')) ?></span><?php endif; ?>
      <span class="messengers">
        <?php if (setting('telegram')): ?><a class="btn btn-ghost-light" href="<?= e(setting('telegram')) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
        <?php if (setting('whatsapp')): ?><a class="btn btn-ghost-light" href="<?= e(setting('whatsapp')) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      </span>
    </address>
  </div>
  <div class="container footer-bottom">
    <span>© <?= e(setting('site_name')) ?>, <?= date('Y') ?><?php if (setting('legal_name')): ?> · <?= e(setting('legal_name')) ?><?php endif; ?><?php if (setting('legal_inn')): ?> · ИНН <?= e(setting('legal_inn')) ?><?php endif; ?><?php if (setting('legal_ogrn')): ?> · ОГРНИП <?= e(setting('legal_ogrn')) ?><?php endif; ?></span>
    <a href="<?= url('page.php?p=privacy') ?>">Политика обработки персональных данных</a>
    <a href="<?= url('page.php?p=oferta') ?>">Публичная оферта</a>
  </div>
</footer>
<script src="<?= asset('assets/app.js') ?>"></script>
</body>
</html>
<?php
}

/** Поле количества со степпером */
function qty_input(string $name, int $value, int $step, string $id = ''): string
{
    $id = $id ?: $name . '-' . bin2hex(random_bytes(3));
    return '<div class="stepper" data-stepper data-step="' . $step . '">'
        . '<button type="button" data-dec aria-label="Уменьшить на ' . $step . '">−</button>'
        . '<input type="number" id="' . e($id) . '" name="' . e($name) . '" value="' . $value . '" min="' . $step . '" step="' . $step . '" inputmode="numeric" aria-label="Количество, шт.">'
        . '<button type="button" data-inc aria-label="Увеличить на ' . $step . '">+</button></div>';
}

function product_thumb(array $p, string $class = 'thumb'): string
{
    if (!empty($p['image'])) {
        return '<img class="' . $class . '" src="' . e(upload_url($p['image'])) . '" alt="' . e($p['name']) . '" loading="lazy">';
    }
    return '<div class="' . $class . ' thumb-empty" aria-hidden="true">' . logo_mark(34) . '</div>';
}
