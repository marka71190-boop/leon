<?php
declare(strict_types=1);

/* ---------- Человекопонятные адреса (ЧПУ) ---------- */

/** Транслитерация в адрес: «Дюбель тарельчатый 10×120» → «dyubel-tarelchatyy-10x120» */
function translit_slug(string $s, int $max = 80): string
{
    static $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya', '×' => 'x', '№' => 'n', '+' => '-plus-',
    ];
    $s = strtr(mb_strtolower($s), $map);
    $s = preg_replace('~[^a-z0-9]+~', '-', $s);
    $s = trim((string)$s, '-');
    if (strlen($s) > $max) {
        $s = substr($s, 0, $max);
        $s = strpos($s, '-') !== false ? substr($s, 0, (int)strrpos($s, '-')) : $s;
    }
    return trim($s, '-');
}

/** Адреса, которые заняты разделами сайта — страницам их давать нельзя */
function slug_reserved(): array
{
    return ['catalog', 'product', 'contacts', 'certificates', 'cart', 'account', 'login', 'logout', 'order', 'invoice',
            'admin', 'uploads', 'assets', 'api', 'inc', 'data', 'deploy', 'docker', 'index', 'route', 'search', 'sitemap', 'robots', 'favicon'];
}

/** Ссылки для url()/abs_url(): раздел, товар, страница */
function cat_url(array $c): string
{
    return ($c['slug'] ?? '') !== '' ? 'catalog/' . $c['slug'] : 'catalog.php?cat=' . (int)$c['id'];
}

function product_url(array $p): string
{
    return ($p['slug'] ?? '') !== '' ? 'product/' . $p['slug'] : 'product.php?id=' . (int)$p['id'];
}

function page_url(string $slug): string
{
    return in_array($slug, slug_reserved(), true) ? 'page.php?p=' . rawurlencode($slug) : rawurlencode($slug);
}

/** Свободный адрес в таблице: при совпадении добавляется -2, -3… */
function slug_unique(string $table, string $slug, int $id): string
{
    $slug = $slug !== '' ? $slug : ($table === 'products' ? 'tovar' : 'razdel');
    $try = $slug;
    for ($i = 2; q("SELECT 1 FROM $table WHERE slug = ? AND id <> ?", [$try, $id])->fetchColumn(); $i++) {
        $try = $slug . '-' . $i;
    }
    return $try;
}

/** Адрес товара по умолчанию: название + размер */
function product_slug_source(array $p): string
{
    return trim(($p['name'] ?? '') . ' ' . ($p['size'] ?? ''));
}

/** Проставить адреса всем разделам и товарам, у которых их ещё нет (после импорта из Excel, копирования и т. п.) */
function slugs_fill(): void
{
    foreach (q("SELECT id, name FROM categories WHERE COALESCE(slug, '') = '' ORDER BY id")->fetchAll() as $c) {
        q('UPDATE categories SET slug = ? WHERE id = ?', [slug_unique('categories', translit_slug($c['name']), (int)$c['id']), $c['id']]);
    }
    foreach (q("SELECT id, name, size FROM products WHERE COALESCE(slug, '') = '' ORDER BY id")->fetchAll() as $p) {
        q('UPDATE products SET slug = ? WHERE id = ?', [slug_unique('products', translit_slug(product_slug_source($p)), (int)$p['id']), $p['id']]);
    }
}

/** Адрес из поля в админке: латиница, цифры и дефис; пусто — из названия */
function slug_from_input(string $input, string $fallbackSource): string
{
    $s = translit_slug(trim($input) !== '' ? $input : $fallbackSource);
    return $s;
}

/**
 * Запомнить переезд страницы: старый адрес будет отвечать 301-переадресацией на новый.
 * Пути — как у url(): «product/staryy-adres» → «product/novyy-adres».
 */
function redirect_add(string $old, string $new): void
{
    $old = trim($old, '/');
    $new = trim($new, '/');
    if ($old === '' || $old === $new) {
        return;
    }
    // Цепочки сразу ведут на последний адрес, а вернувшийся адрес перестаёт переадресовываться
    q('UPDATE redirects SET new_path = ? WHERE new_path = ?', [$new, $old]);
    q('DELETE FROM redirects WHERE old_path = ? OR old_path = new_path', [$new]);
    q('INSERT INTO redirects (old_path, new_path, created_at) VALUES (?, ?, ?)
       ON CONFLICT(old_path) DO UPDATE SET new_path = excluded.new_path, created_at = excluded.created_at', [$old, $new, date('Y-m-d H:i:s')]);
}

function redirect_301(string $location): never
{
    header('Location: ' . $location, true, 301);
    exit;
}

/**
 * Страница открыта по старому адресу (catalog.php?cat=1, product.php?id=5, page.php?p=about…) —
 * отправляем поисковик и посетителя на человекопонятный адрес с кодом 301.
 * $drop — параметры, которые уже вошли в новый адрес.
 */
function seo_canonical_redirect(string $path, array $drop = []): void
{
    if (defined('LP_ROUTED') || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || str_contains($path, '.php')) {
        return;
    }
    $rest = array_diff_key($_GET, array_flip($drop));
    $qs = preg_replace('~%5B\d+%5D=~', '%5B%5D=', http_build_query($rest));
    redirect_301(url($path) . ($qs !== '' ? '?' . $qs : ''));
}

/** Страница 404 в оформлении сайта, закрытая от индексации */
function page_404(string $title = 'Страница не найдена', string $text = 'Возможно, адрес изменился или страница удалена.'): never
{
    http_response_code(404);
    page_header($title, ['robots' => 'noindex, follow', 'canonical' => null]);
    $cats = q('SELECT id, name, slug FROM categories WHERE active = 1 ORDER BY sort, id')->fetchAll();
    ?>
    <div class="container page-pad">
      <h1 style="margin-top:28px"><?= e($title) ?></h1>
      <p><?= e($text) ?> Начните с каталога или позвоните нам: <a href="tel:<?= e(preg_replace('~[^\d+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a>.</p>
      <div class="chips" style="margin-top:18px">
        <a class="chip active" href="<?= url('catalog') ?>">Весь каталог</a>
        <?php foreach ($cats as $c): ?><a class="chip" href="<?= url(cat_url($c)) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
      </div>
      <p style="margin-top:18px"><a href="<?= url() ?>">На главную</a></p>
    </div>
    <?php
    page_footer();
    exit;
}

/* ---------- Картинки: уменьшенные копии в WebP ---------- */

/**
 * Уменьшенная копия загруженной картинки шириной до $w px в формате WebP.
 * Делается один раз при первом показе и лежит в uploads/_img/<ширина>/…
 * Возвращает ['src' => ссылка, 'w' => ширина, 'h' => высота]; если уменьшить нельзя — оригинал.
 */
function img_variant(?string $rel, int $w, string $fmt = 'webp'): array
{
    $rel = (string)$rel;
    $orig = ['src' => upload_url($rel), 'w' => null, 'h' => null];
    if ($rel === '' || str_starts_with($rel, 'assets/') || str_contains($rel, '..')) {
        return $orig;
    }
    $src = UPLOAD_DIR . '/' . $rel;
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !is_file($src)) {
        return $orig;
    }
    $fmt = $fmt === 'jpg' ? 'jpg' : 'webp';
    $outRel = '_img/' . $w . '/' . preg_replace('~\.[a-z0-9]+$~i', '', $rel) . '.' . $fmt;
    $out = UPLOAD_DIR . '/' . $outRel;
    if ((!is_file($out) || filemtime($out) < filemtime($src)) && !img_make($src, $out, $w, $fmt)) {
        $sz = @getimagesize($src);
        return ['src' => $orig['src'], 'w' => $sz[0] ?? null, 'h' => $sz[1] ?? null];
    }
    $sz = @getimagesize($out);
    return ['src' => upload_url($outRel) . '?v=' . filemtime($out), 'w' => $sz[0] ?? null, 'h' => $sz[1] ?? null];
}

/** Уменьшение картинки (GD). С учётом поворота фото с телефона (EXIF) */
function img_make(string $src, string $out, int $w, string $fmt = 'webp'): bool
{
    if (!function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
        return false;
    }
    $info = @getimagesize($src);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 50_000_000) {
        return false;
    }
    @ini_set('memory_limit', '512M');
    $im = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default        => false,
    };
    if (!$im) {
        return false;
    }
    if ($info[2] === IMAGETYPE_JPEG) {
        $o = function_exists('exif_read_data') ? (int)(@exif_read_data($src)['Orientation'] ?? 1) : 1;
        if ($o === 3) $im = imagerotate($im, 180, 0);
        if ($o === 6) $im = imagerotate($im, -90, 0);
        if ($o === 8) $im = imagerotate($im, 90, 0);
    }
    $sw = imagesx($im);
    $sh = imagesy($im);
    $tw = min($w, $sw);
    $th = max(1, (int)round($sh * $tw / $sw));
    $dst = imagecreatetruecolor($tw, $th);
    if ($fmt === 'jpg') {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // прозрачный фон PNG → белый
    } else {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
    }
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $tw, $th, $sw, $sh);
    imagedestroy($im);
    if (!is_dir(dirname($out))) {
        @mkdir(dirname($out), 0775, true);
    }
    $tmp = $out . '.' . bin2hex(random_bytes(3)) . '.tmp';
    $ok = $fmt === 'jpg' ? @imagejpeg($dst, $tmp, 85) : @imagewebp($dst, $tmp, 80);
    imagedestroy($dst);
    return $ok && @rename($tmp, $out);
}

/** Удалить уменьшенные копии файла (при удалении фото) */
function img_variants_delete(?string $rel): void
{
    if (!$rel) return;
    $base = preg_replace('~\.[a-z0-9]+$~i', '', $rel);
    foreach (glob(UPLOAD_DIR . '/_img/*/' . $base . '.{webp,jpg}', GLOB_BRACE) ?: [] as $f) @unlink($f);
}

/** Тег <img> с уменьшенной копией, размерами (без «прыжков» страницы) и srcset для чётких экранов */
function img_tag(?string $rel, int $w, string $alt, array $attr = []): string
{
    $v1 = img_variant($rel, $w);
    $html = '<img src="' . e($v1['src']) . '"';
    if (!isset($attr['nosrcset']) && $v1['w'] && $v1['w'] >= $w) {
        $v2 = img_variant($rel, $w * 2);
        if ($v2['w'] && $v2['w'] > $v1['w']) {
            $html .= ' srcset="' . e($v1['src']) . ' 1x, ' . e($v2['src']) . ' 2x"';
        }
    }
    unset($attr['nosrcset']);
    $html .= ' alt="' . e($alt) . '"';
    if ($v1['w'] && $v1['h']) {
        $html .= ' width="' . $v1['w'] . '" height="' . $v1['h'] . '"';
    }
    foreach ($attr as $k => $v) {
        $html .= ' ' . $k . ($v === true ? '' : '="' . e((string)$v) . '"');
    }
    return $html . '>';
}

/* ---------- Разметка для поисковиков ---------- */

/** Компания с адресом и телефоном — для страницы «Контакты» и карточки в поиске */
function ld_local_business(): array
{
    $ld = ld_organization();
    $ld['@type'] = ['Organization', 'LocalBusiness'];
    $ld['@id'] = abs_url('') . '#organization';
    $ld['image'] = abs_url('assets/logo/leonpro-horizontal-for-light-bg.png');
    if (setting('work_hours') !== '') {
        $ld['description'] = setting('company_short') . ' Часы работы: ' . setting('work_hours') . '.';
    }
    $ld['areaServed'] = ['@type' => 'Country', 'name' => 'Россия'];
    return $ld;
}

/** Список товаров раздела */
function ld_item_list(array $products, string $name): array
{
    $items = [];
    foreach (array_values($products) as $i => $p) {
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => abs_url(product_url($p)), 'name' => trim($p['name'] . ' ' . $p['size'])];
    }
    return ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $name, 'numberOfItems' => count($items), 'itemListElement' => $items];
}

/* ---------- Яндекс Метрика ---------- */

function metrika_id(): string
{
    return preg_replace('~\D~', '', setting('metrika_id'));
}

/** Код счётчика Метрики (если в настройках указан номер счётчика) */
function metrika_code(): string
{
    $id = metrika_id();
    // Визиты администраторов не считаем, чтобы не искажать статистику
    if ($id === '' || !empty($_SESSION['admin_id'])) {
        return '';
    }
    $opts = json_encode(['clickmap' => true, 'trackLinks' => true, 'accurateTrackBounce' => true, 'webvisor' => setting('metrika_webvisor') === '1', 'ecommerce' => false]);
    return '<script>(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();'
        . 'for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}'
        . 'k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})'
        . '(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");'
        . 'ym(' . $id . ',"init",' . $opts . ');window.LP_YM=' . $id . ';</script>'
        . '<noscript><div><img src="https://mc.yandex.ru/watch/' . $id . '" style="position:absolute;left:-9999px" alt=""></div></noscript>';
}

/** Фраза для политики конфиденциальности (метка {{аналитика}}) */
function analytics_policy_text(): string
{
    if (metrika_id() === '') {
        return 'Сервисы веб-аналитики на Сайте не используются.';
    }
    return 'Для анализа посещаемости на Сайте работает сервис «Яндекс Метрика» (ООО «Яндекс», Россия): он с помощью файлов cookie собирает обезличенные сведения о посещениях — '
        . 'просмотренные страницы, источник перехода, тип устройства и браузера, примерное местоположение по IP-адресу. Эти сведения используются только в обобщённом виде, чтобы улучшать Сайт. '
        . 'Посетитель может запретить cookie в настройках браузера или установить блокировщик Метрики (yandex.ru/support/metrica/general/opt-out.html).';
}
