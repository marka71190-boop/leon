<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin('settings');

// SEO: проверка сайта, что поправить, и переадресации со старых адресов
if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'images') {
        // Уменьшенные копии всех фото (делаются порциями, чтобы не упереться во время ожидания сервера)
        $start = microtime(true);
        $done = $left = 0;
        $files = array_merge(
            q("SELECT file FROM product_images WHERE COALESCE(kind, 'image') = 'image'")->fetchAll(PDO::FETCH_COLUMN),
            q("SELECT image FROM products WHERE image <> ''")->fetchAll(PDO::FETCH_COLUMN),
            q("SELECT image FROM categories WHERE image <> ''")->fetchAll(PDO::FETCH_COLUMN),
            array_filter([setting('hero_image')])
        );
        foreach (array_unique($files) as $f) {
            if (microtime(true) - $start > 40) {
                $left++;
                continue;
            }
            foreach ([80, 160, 280, 480, 560, 800, 960, 1200, 1600] as $w) img_variant($f, $w);
            $done++;
        }
        flash($left ? "Подготовлено фото: $done. Осталось: $left — нажмите кнопку ещё раз." : "Готово: подготовлено фото — $done. Страницы будут открываться быстрее.");
    }
    if ($action === 'redirect_delete') {
        q('DELETE FROM redirects WHERE old_path = ?', [post('old')]);
        flash('Переадресация удалена.');
    }
    if ($action === 'redirect_add') {
        $old = trim((string)parse_url(post('old'), PHP_URL_PATH), '/');
        $new = trim(post('new'));
        $new = preg_match('~^https?://~', $new) ? trim((string)parse_url($new, PHP_URL_PATH), '/') : trim($new, '/');
        if ($old === '' || str_ends_with($old, '.php')) {
            flash('Укажите старый адрес, например /old-page (адреса .php сайт переадресует сам).', 'error');
        } else {
            redirect_add($old, $new);
            flash('Переадресация добавлена: /' . $old . ' → /' . $new);
        }
    }
    redirect('admin/seo.php');
}

$gd = function_exists('imagewebp');
$https = str_starts_with(seo_base(), 'https://');
$checks = [
    ['Сайт открыт для поисковиков', setting('seo_noindex') !== '1', 'В настройках включено «Скрыть сайт от поисковиков» — выключите, иначе сайт не попадёт в поиск.'],
    ['Адрес сайта указан в настройках', preg_match('~^https://~', setting('site_url')) === 1, 'Укажите в настройках «Адрес сайта»: https://leon-pro.ru — по нему строятся ссылки в карте сайта и для соцсетей.'],
    ['Сайт работает по HTTPS', $https, 'Ссылки строятся с http:// — проверьте «Адрес сайта» в настройках.'],
    ['Подтверждён в Яндекс.Вебмастере', setting('yandex_verification') !== '', 'Добавьте сайт в webmaster.yandex.ru, выберите подтверждение «Мета-тег» и вставьте код в настройки.'],
    ['Подтверждён в Google Search Console', setting('google_verification') !== '', 'Добавьте сайт в search.google.com/search-console (ресурс «С префиксом в URL»), подтверждение «Тег HTML» — код в настройки.'],
    ['Подключена Яндекс Метрика', metrika_id() !== '', 'Создайте счётчик на metrika.yandex.ru и укажите его номер в настройках — увидите, откуда приходят клиенты.'],
    ['Фото уменьшаются для быстрой загрузки', $gd, 'На сервере нет модуля обработки картинок — после обновления сайта он появится сам.'],
    ['Текст на главной странице', trim(strip_tags(setting('home_seo_text'))) !== '', 'Заполните «Текст внизу главной страницы» в настройках.'],
    ['Картинка для соцсетей и мессенджеров', setting('seo_og_image') !== '', 'Загрузите в настройках картинку 1200×630 — она показывается, когда ссылку на сайт отправляют в мессенджере.'],
];

// Что поправить в товарах, разделах и страницах
$issues = [];
$titles = [];
foreach (q('SELECT p.*, (SELECT COUNT(*) FROM documents d WHERE d.product_id = p.id) AS docs FROM products p WHERE p.active = 1 ORDER BY p.sort, p.id')->fetchAll() as $p) {
    $name = trim($p['name'] . ' ' . $p['size']);
    $link = url('admin/products.php?edit=' . $p['id']);
    $list = [];
    if (!$p['image']) $list[] = 'нет фото — без фото товар хуже показывается в Яндекс Картинках и в выдаче';
    if (trim(strip_tags((string)$p['description'])) === '') $list[] = 'нет описания (сайт подставил короткий текст сам — лучше написать 2–3 абзаца своими словами)';
    if ((float)$p['price'] <= 0 && setting('show_prices') === '1') $list[] = 'нет цены — товары с ценой поисковики показывают выше';
    if (!(int)$p['docs']) $list[] = 'нет документов (сертификат, паспорт качества)';
    if ($p['seo_title'] !== '' && (mb_strlen($p['seo_title']) < 30 || mb_strlen($p['seo_title']) > 80)) $list[] = 'заголовок для поисковиков ' . mb_strlen($p['seo_title']) . ' симв. — лучше 50–80';
    if ($p['seo_description'] !== '' && mb_strlen($p['seo_description']) < 70) $list[] = 'описание для поисковиков короткое';
    $t = mb_strtolower($p['seo_title'] ?: $name);
    $titles[$t][] = $name;
    if ($list) $issues[] = ['Товар', $name, $link, $list];
}
foreach (q('SELECT * FROM categories WHERE active = 1 ORDER BY sort, id')->fetchAll() as $c) {
    $list = [];
    if (trim(strip_tags((string)$c['seo_text'])) === '') $list[] = 'нет текста о разделе — страница раздела состоит только из таблицы';
    if (!$c['image']) $list[] = 'нет картинки раздела';
    if ($c['description'] === '') $list[] = 'нет короткого описания';
    if ($list) $issues[] = ['Раздел', $c['name'], url('admin/categories.php?edit=' . $c['id'] . '#form'), $list];
}
foreach ($titles as $t => $names) {
    if (count($names) > 1) $issues[] = ['Дубли', implode(', ', $names), url('admin/products.php'), ['одинаковые заголовки у нескольких товаров — различите их размером, типом или заголовком для поисковиков']];
}

$redirects = q('SELECT * FROM redirects ORDER BY created_at DESC LIMIT 200')->fetchAll();
$okCount = count(array_filter($checks, fn($c) => $c[1]));

admin_header('SEO', 'seo');
?>
<div class="a-head"><h1>SEO и продвижение</h1><div class="a-actions">
  <a class="a-btn a-btn-out a-btn-sm" href="<?= url('sitemap.xml') ?>" target="_blank">Карта сайта ↗</a>
  <a class="a-btn a-btn-out a-btn-sm" href="<?= url('robots.txt') ?>" target="_blank">robots.txt ↗</a>
  <a class="a-btn a-btn-sm" href="<?= url('admin/settings.php#g-SEO') ?>">Настройки SEO</a>
</div></div>

<div class="a-card">
  <h2>Проверка сайта <span class="a-muted" style="font-weight:400; font-size:15px"><?= $okCount ?> из <?= count($checks) ?></span></h2>
  <ul class="a-checks">
    <?php foreach ($checks as [$label, $ok, $fix]): ?>
      <li class="<?= $ok ? 'ok' : 'bad' ?>"><b><?= $ok ? '✓' : '!' ?></b><span><?= e($label) ?><?php if (!$ok): ?><small><?= e($fix) ?></small><?php endif; ?></span></li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="a-card">
  <h2>Что сделать один раз вне сайта</h2>
  <ol class="a-todo">
    <li><b>Яндекс.Вебмастер</b> (webmaster.yandex.ru): добавить сайт, подтвердить права (код — в настройки), в «Индексирование → Файлы Sitemap» добавить <code><?= e(abs_url('sitemap.xml')) ?></code>, в «Информация о сайте → Регион» указать Краснодар.</li>
    <li><b>Google Search Console</b>: добавить сайт, подтвердить, отправить ту же карту сайта.</li>
    <li><b>Яндекс Бизнес</b> (business.yandex.ru): карточка организации с адресом склада, телефоном, часами работы, фото и ссылкой на сайт — по ней вас находят в Картах и в поиске «рядом».</li>
    <li><b>2ГИС</b> и <b>Google Бизнес-профиль</b>: такие же карточки с тем же адресом и телефоном, что на сайте.</li>
    <li>Через 1–2 недели в Вебмастере: «Индексирование → Страницы в поиске» — проверить, что товары и разделы попали в поиск по новым адресам.</li>
  </ol>
</div>

<div class="a-card">
  <h2>Что улучшить в каталоге <span class="a-muted" style="font-weight:400; font-size:15px"><?= count($issues) ?: '' ?></span></h2>
  <?php if (!$issues): ?>
    <p class="a-muted">Замечаний нет — всё заполнено.</p>
  <?php else: ?>
    <p class="a-muted" style="margin-top:-6px">Сайт работает и без этого, но чем полнее заполнены товары и разделы, тем выше они в Яндексе и Google.</p>
    <div class="a-table-wrap"><table class="a-table">
      <thead><tr><th>Где</th><th>Что</th><th>Что поправить</th></tr></thead>
      <tbody>
      <?php foreach ($issues as [$kind, $name, $link, $list]): ?>
        <tr><td class="a-muted"><?= e($kind) ?></td><td><a href="<?= e($link) ?>"><b><?= e($name) ?></b></a></td><td><?= implode('<br>', array_map('e', $list)) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="a-card">
  <h2>Скорость загрузки фото</h2>
  <p class="a-muted" style="margin-top:-6px">Сайт сам делает уменьшенные копии фото в формате WebP — страницы открываются быстрее, особенно с телефона. Копии новых фото готовятся при загрузке, старых — при первом показе. Кнопка подготовит все сразу.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="images"><button class="a-btn" type="submit"<?= $gd ? '' : ' disabled' ?>>Подготовить все фото</button></form>
</div>

<div class="a-card" id="redirects">
  <h2>Переадресации (301)</h2>
  <p class="a-muted" style="margin-top:-6px">Когда меняется адрес товара, раздела или страницы, старый адрес сам начинает вести на новый — поисковики переносят на него позиции. Старые адреса вида product.php?id=… сайт переадресует автоматически. Здесь можно добавить свою переадресацию, например со страниц старого сайта.</p>
  <form method="post" class="a-grid" style="align-items:end; margin-bottom:14px"><?= csrf_field() ?><input type="hidden" name="action" value="redirect_add">
    <?= a_input('old', 'Старый адрес', '', 'text', 'Например, /dyubeli или https://leon-pro.ru/page123', ['required' => true]) ?>
    <?= a_input('new', 'Куда вести', '', 'text', 'Например, /catalog/tarelchatye-dyubeli. Пусто — на главную') ?>
    <div><button class="a-btn a-btn-out" type="submit">Добавить</button></div>
  </form>
  <?php if ($redirects): ?>
  <div class="a-table-wrap"><table class="a-table">
    <thead><tr><th>Старый адрес</th><th>Новый адрес</th><th>Когда</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($redirects as $r): ?>
      <tr><td>/<?= e($r['old_path']) ?></td><td><a href="<?= url($r['new_path']) ?>" target="_blank">/<?= e($r['new_path']) ?></a></td><td class="a-muted"><?= $r['created_at'] ? date('d.m.Y', strtotime($r['created_at'])) : '' ?></td>
        <td><form method="post" data-confirm="Удалить переадресацию?"><?= csrf_field() ?><input type="hidden" name="action" value="redirect_delete"><input type="hidden" name="old" value="<?= e($r['old_path']) ?>"><button class="a-btn a-btn-danger a-btn-sm" type="submit">Удалить</button></form></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php admin_footer();
