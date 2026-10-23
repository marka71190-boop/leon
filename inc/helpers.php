<?php
declare(strict_types=1);

/* ---------- Настройки сайта (всё редактируется в админке) ---------- */

function settings_schema(): array
{
    // key => [группа, подпись, тип, значение по умолчанию, подсказка]
    return [
        'site_name'        => ['Компания', 'Название сайта', 'text', 'Leon_pro', ''],
        'slogan'           => ['Компания', 'Слоган', 'text', 'Каждый проект с нами безупречен', ''],
        'company_short'    => ['Компания', 'Описание в подвале', 'textarea', 'Производитель тарельчатых дюбелей, площадок под стяжку, якорных скоб и СВП.', ''],
        'logo_image'       => ['Компания', 'Логотип (картинка)', 'image', '', 'Если не загружен — используется встроенный знак'],
        'accent_color'     => ['Компания', 'Фирменный цвет', 'color', '#F07F1F', 'Цвет кнопок и акцентов'],

        'phone'            => ['Контакты', 'Телефон', 'text', '+7 918-133-73-07', ''],
        'email'            => ['Контакты', 'Email', 'text', '', 'Если пусто — на сайте не показывается'],
        'address'          => ['Контакты', 'Адрес производства и склада', 'text', 'г. Краснодар', 'Улица и дом склада, куда приезжать за товаром'],
        'work_hours'       => ['Контакты', 'Часы работы', 'text', '', 'Например, Пн–Пт 9:00–18:00. Если пусто — не показывается'],
        'telegram'         => ['Контакты', 'Ссылка на Telegram', 'text', '', 'Например, https://t.me/username'],
        'whatsapp'         => ['Контакты', 'Ссылка на WhatsApp', 'text', '', 'Например, https://wa.me/79000000000'],
        'map_embed'        => ['Контакты', 'Код карты (Яндекс.Карты)', 'code', '', 'Вставьте iframe из конструктора карт'],

        'legal_name'       => ['Реквизиты', 'Продавец', 'text', 'ИП Леонов Александр Геннадьевич', ''],
        'legal_inn'        => ['Реквизиты', 'ИНН', 'text', '234207517608', ''],
        'legal_ogrn'       => ['Реквизиты', 'ОГРНИП', 'text', '1212300034308', ''],
        'legal_okpo'       => ['Реквизиты', 'ОКПО', 'text', '2013390270', ''],
        'legal_address'    => ['Реквизиты', 'Юридический адрес', 'text', '350000, Краснодарский край, г. Краснодар, ул. Чкалова, 123/1', ''],
        'bank_name'        => ['Реквизиты', 'Банк', 'text', 'Краснодарское отделение № 8619 ПАО Сбербанк', ''],
        'bank_account'     => ['Реквизиты', 'Расчётный счёт', 'text', '40802810730000158232', ''],
        'bank_corr'        => ['Реквизиты', 'Корр. счёт', 'text', '30101810100000000602', ''],
        'bank_bik'         => ['Реквизиты', 'БИК', 'text', '040349602', 'Реквизиты подставляются на страницы вместо метки {{реквизиты}}'],

        'topbar_text'      => ['Главная', 'Текст верхней полосы', 'text', 'Производство и склад — Краснодар · Отгрузка транспортными компаниями', ''],
        'hero_eyebrow'     => ['Главная', 'Надзаголовок', 'text', 'Производитель · Краснодар', ''],
        'hero_title'       => ['Главная', 'Заголовок первого экрана', 'text', 'Крепёж для теплоизоляции и стяжки — оптом от производителя', ''],
        'hero_text'        => ['Главная', 'Текст первого экрана', 'textarea', 'Тарельчатые дюбели, площадки под стяжку, якорные скобы и СВП со склада в Краснодаре. Оптовые цены для строительных компаний, подрядчиков и бригад.', ''],
        'hero_image'       => ['Главная', 'Картинка первого экрана', 'image', '', 'Если не загружена — показывается логотип на тёмном фоне'],

        'min_order_qty'    => ['Заказы', 'Минимальный заказ, шт.', 'int', '5000', 'Суммарно по корзине. 0 — без минимального заказа: упоминания о нём исчезнут со всех страниц сайта'],
        'default_step'     => ['Заказы', 'Шаг количества по умолчанию, шт.', 'int', '500', 'Если у товара не указана кратность'],
        'show_prices'      => ['Заказы', 'Показывать цены', 'bool', '1', 'Если выключено — «Цена по запросу»'],
        'price_note'       => ['Заказы', 'Пометка к ценам', 'text', 'Цена', 'Например, «Цены с НДС» после перехода на НДС'],
        'delivery_note'    => ['Заказы', 'Коротко о доставке', 'textarea', 'Отгрузка со склада в Краснодаре через транспортные компании. Доставку оплачивает покупатель.', ''],
        'order_success'    => ['Заказы', 'Текст после отправки заявки', 'textarea', 'Менеджер свяжется с вами, подтвердит состав заказа, согласует стоимость и транспортную компанию.', ''],

        'tg_token'         => ['Уведомления', 'Токен Telegram-бота', 'secret', '', 'Выдаёт @BotFather при создании бота. Хранится скрыто'],
        'tg_chats'         => ['Уведомления', 'ID чатов Telegram для заявок', 'text', '', 'Через запятую. Нажмите «Найти ID чата» ниже'],
        'tg_hide_pd'       => ['Уведомления', 'Не отправлять в Telegram персональные данные клиентов', 'bool', '1', 'Включено: в Telegram уходят номер заявки, город, состав и сумма, а контакты клиента — по ссылке в админке. Telegram — иностранный сервис: передача туда имён, телефонов и email считается трансграничной и требует отдельного уведомления Роскомнадзора'],
        'tg_api_url'       => ['Уведомления', 'Адрес-посредник для Telegram', 'text', '', 'Только если хостинг не видит Telegram (заявки не доходят). Например, https://leonpro-tg.имя.workers.dev. Пусто — напрямую'],
        'manager_email'    => ['Уведомления', 'Email менеджера для новых заказов', 'text', '', 'Необязательно, дублирует заявки на почту. Можно несколько через запятую'],
        'mail_from'        => ['Уведомления', 'Адрес отправителя писем', 'text', '', 'Например, noreply@ваш-домен.ru (ящик на вашем домене)'],

        'maintenance'      => ['Тех. перерыв', 'Технический перерыв', 'bool', '0', 'Включено: посетители видят заглушку вместо сайта, заказы не принимаются. Администраторы, вошедшие в админку, видят сайт как обычно'],
        'maintenance_text' => ['Тех. перерыв', 'Текст на заглушке', 'textarea', 'Сайт временно закрыт на технические работы. Скоро всё заработает — а пока заказы и вопросы по телефону.', ''],
        'invoice_vat'      => ['Счёт', 'НДС в счёте', 'text', 'Без НДС', 'Например, «Без НДС» или «В том числе НДС 5%» — печатается под итогом'],
        'invoice_signer'   => ['Счёт', 'Подпись в счёте (Фамилия И. О.)', 'text', '', 'Пусто — берётся из названия ИП, например «Леонов А. Г.»'],
        'invoice_days'     => ['Счёт', 'Срок оплаты счёта, банковских дней', 'int', '5', ''],
        'invoice_terms'    => ['Счёт', 'Условия внизу счёта', 'textarea', 'Оплата счёта означает согласие с условиями поставки (публичная оферта на сайте). Товар отгружается после поступления денег на расчётный счёт поставщика. Доставка оплачивается покупателем по тарифам транспортной компании.', ''],
        'seo_title'        => ['SEO', 'Заголовок сайта (title)', 'text', 'Leon_pro — тарельчатые дюбели, площадки под стяжку, якорные скобы и СВП оптом', ''],
        'seo_description'  => ['SEO', 'Описание (description)', 'textarea', 'Производитель крепежа для теплоизоляции и стяжки. Оптовые поставки со склада в Краснодаре.', ''],
        'site_url'         => ['SEO', 'Адрес сайта', 'text', '', 'Например, https://leonpro.ru — для карты сайта и ссылок в поисковиках. Пусто — определяется сам'],
        'seo_og_image'     => ['SEO', 'Картинка для соцсетей и мессенджеров', 'image', '', 'Показывается при отправке ссылки на сайт. Лучше 1200×630. Пусто — логотип'],
        'yandex_verification' => ['SEO', 'Код подтверждения Яндекс.Вебмастера', 'text', '', 'Только код из meta-тега: content="…"'],
        'google_verification' => ['SEO', 'Код подтверждения Google Search Console', 'text', '', 'Только код из meta-тега: content="…"'],
        'seo_noindex'      => ['SEO', 'Скрыть сайт от поисковиков', 'bool', '0', 'Включите, пока сайт не готов. После запуска выключите'],
        'head_code'        => ['SEO', 'Код счётчиков (Яндекс.Метрика и т. п.)', 'code', '', 'Вставляется в <head> всех страниц'],
    ];
}

function setting(string $key): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT key, value FROM settings')->fetchAll() as $r) {
            $cache[$r['key']] = (string)$r['value'];
        }
    }
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $schema = settings_schema();
    return isset($schema[$key]) ? (string)$schema[$key][3] : '';
}

function setting_save(string $key, string $value): void
{
    q('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$key, $value]);
}

function min_order(): int
{
    return max(0, (int)setting('min_order_qty'));
}

/* ---------- Общие функции ---------- */

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 0;
    return url($path) . '?v=' . $v;
}

function upload_url(?string $rel): string
{
    if (!$rel) {
        return '';
    }
    // Файлы, которые идут вместе с сайтом (assets/...), отдаются напрямую
    return str_starts_with($rel, 'assets/') ? url($rel) : url('uploads/' . $rel);
}

/**
 * Блок реквизитов для страниц сайта (метки {{реквизиты}} и {{оператор}}) — без банковских реквизитов.
 * Банк, расчётный и корреспондентский счёт и БИК печатаются только в счёте на оплату.
 */
function requisites_html(bool $withBank = false): string
{
    $rows = [
        [setting('legal_name'), ''],
        ['ИНН', setting('legal_inn')], ['ОГРНИП', setting('legal_ogrn')], ['ОКПО', setting('legal_okpo')],
        ['Юридический адрес', setting('legal_address')],
    ];
    if ($withBank) {
        array_push($rows, ['Банк', setting('bank_name')], ['Р/с', setting('bank_account')], ['К/с', setting('bank_corr')], ['БИК', setting('bank_bik')]);
    }
    $h = '<dl class="requisites">';
    foreach ($rows as [$k, $v]) {
        if ($v === '' && $k === setting('legal_name')) {
            $h .= '<dt class="req-name">' . e($k) . '</dt>';
        } elseif ($v !== '') {
            $h .= '<dt>' . e($k) . '</dt><dd>' . e($v) . '</dd>';
        }
    }
    return $h . '</dl>';
}

/** Подстановка меток в тексты страниц */
function render_content(string $html): string
{
    $min = min_order();
    // Контакты оператора одной фразой: пустые (например, email) не показываются
    $contacts = array_filter([
        setting('phone') !== '' ? 'по телефону ' . e(setting('phone')) : '',
        setting('email') !== '' ? 'по электронной почте ' . e(setting('email')) : '',
        setting('legal_address') !== '' ? 'письмом по адресу: ' . e(setting('legal_address')) : '',
    ]);
    $site = preg_replace('~^https?://~', '', seo_base());
    return str_replace(['{{реквизиты}}', '{{requisites}}', '{{оператор}}', '{{продавец}}', '{{телефон}}', '{{контакты}}', '{{сайт}}', '{{адрес}}',
                        '{{минимальный заказ}}', '{{минимальный заказ в оферте}}'],
        [requisites_html(), requisites_html(), requisites_html(), e(setting('legal_name')), e(setting('phone')),
         implode(', ', $contacts), e($site), e(setting('legal_address')),
         $min > 0 ? ' Минимальный заказ — от ' . fmt_qty($min) . ' шт. по всей корзине.' : '',
         $min > 0 ? 'Минимальный объём заказа — ' . fmt_qty($min) . ' шт. по всей заявке.' : 'Заказ можно оформить на любое количество товара.'], $html);
}

function redirect(string $path): never
{
    $to = preg_match('~^https?://~', $path) ? $path : url($path);
    header('Location: ' . $to);
    exit;
}

function back(string $fallback = ''): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = preg_replace('~:\d+$~', '', $_SERVER['HTTP_HOST'] ?? '');
    if ($ref && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) {
        $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Сессия устарела. Обновите страницу и попробуйте ещё раз.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $k, string $default = ''): string
{
    return trim((string)($_POST[$k] ?? $default));
}

function fmt_qty($n): string
{
    return number_format((float)$n, 0, ',', ' ');
}

function fmt_money($n): string
{
    $n = (float)$n;
    $dec = abs($n - round($n)) > 0.0001 ? 2 : 0;
    return number_format($n, $dec, ',', ' ') . ' ₽';
}

/**
 * Цена товара как её указали в админке: за упаковку, если задана фасовка, иначе за штуку.
 * Ничего не умножается на фасовку.
 */
function price_label(array $p): string
{
    if (setting('show_prices') !== '1' || (float)$p['price'] <= 0) {
        return 'По запросу';
    }
    return fmt_money($p['price']);
}

/** За что указана цена: «уп.» для товаров с фасовкой, иначе «шт.» */
function price_unit(array $p): string
{
    return product_pack($p) > 0 ? 'уп.' : 'шт.';
}

/** Цена для таблиц: «250 ₽» и под ней «за уп.» */
function price_cell(array $p): string
{
    $label = price_label($p);
    return $label === 'По запросу' ? $label : $label . '<span class="price-unit">за ' . price_unit($p) . '</span>';
}

/** Цена одной штуки (для подсчёта суммы заказа): цена упаковки делится на фасовку */
function price_per_piece(array $p): float
{
    $pack = product_pack($p);
    return $pack > 0 ? (float)$p['price'] / $pack : (float)$p['price'];
}

function product_step(array $p): int
{
    $s = (int)($p['step'] ?? 0);
    return $s > 0 ? $s : max(1, (int)setting('default_step'));
}

/**
 * Этапы заказа по порядку: название для менеджера, название для клиента, что сделать менеджеру на этом этапе.
 * Менеджер переводит заказ на следующий этап одной кнопкой в карточке заказа.
 */
function order_steps(): array
{
    return [
        'new'       => ['Новый', 'Заявка принята', 'Позвоните клиенту: подтвердите состав, количество и реквизиты.'],
        'contacted' => ['Связались', 'Менеджер связался с вами', 'Согласуйте транспортную компанию, адрес и стоимость доставки.'],
        'delivery'  => ['Доставка согласована', 'Доставка согласована', 'Выставьте счёт и дождитесь оплаты.'],
        'paid'      => ['Оплата подтверждена', 'Оплата получена', 'Соберите и отгрузите заказ. Номер накладной впишите в комментарий для клиента.'],
        'shipped'   => ['Отгружен', 'Отгружен', 'Убедитесь, что клиент получил груз, и закройте заказ.'],
        'closed'    => ['Закрыт', 'Выполнен', 'Заказ выполнен.'],
    ];
}

/** Все статусы для менеджера, включая «Отменён» */
function order_statuses(): array
{
    return array_map(fn($s) => $s[0], order_steps()) + ['cancelled' => 'Отменён'];
}

function status_label(string $s): string
{
    return order_statuses()[$s] ?? $s;
}

/** Название статуса для клиента (личный кабинет, письма) */
function status_label_client(string $s): string
{
    return order_steps()[$s][1] ?? ($s === 'cancelled' ? 'Отменён' : $s);
}

/** Следующий этап или null, если заказ закрыт или отменён */
function order_next_status(string $s): ?string
{
    $keys = array_keys(order_steps());
    $i = array_search($s, $keys, true);
    return ($i !== false && isset($keys[$i + 1])) ? $keys[$i + 1] : null;
}

/** Запись в историю заказа: кто и когда перевёл на этап */
function order_event(int $orderId, string $status, string $who, string $note = ''): void
{
    q('INSERT INTO order_events (order_id, created_at, status, who, note) VALUES (?,?,?,?,?)', [$orderId, date('Y-m-d H:i:s'), $status, $who, $note]);
}

/* ---------- Упаковки ---------- */

/** Штук в упаковке (0 — товар продаётся поштучно) */
function product_pack(array $p): int
{
    return max(0, (int)($p['pack_qty'] ?? 0));
}

/** Количество в штуках из формы: для товаров с фасовкой покупатель указывает упаковки */
function qty_from_input(array $p, $value): int
{
    $pack = product_pack($p);
    if ($pack > 0) {
        return max(1, (int)$value) * $pack;
    }
    $step = product_step($p);
    return max($step, (int)ceil(max(1, (int)$value) / $step) * $step);
}

/** «10 уп. (5 000 шт.)», для штучных — «5 000 шт.» */
function fmt_packs(int $qty, int $pack): string
{
    if ($pack <= 0) {
        return fmt_qty($qty) . ' шт.';
    }
    $full = intdiv($qty, $pack);
    $rest = $qty % $pack;
    return fmt_qty($full) . ' уп.' . ($rest ? ' + ' . fmt_qty($rest) . ' шт.' : '') . ' (' . fmt_qty($qty) . ' шт.)';
}

function normalize_phone(string $p): string
{
    $d = preg_replace('~\D+~', '', $p);
    if (strlen($d) === 11 && ($d[0] === '8' || $d[0] === '7')) {
        $d = '7' . substr($d, 1);
    } elseif (strlen($d) === 10) {
        $d = '7' . $d;
    }
    return $d;
}

/* ---------- Загрузка файлов ---------- */

function handle_upload(string $field, array $allowedExt, string $subdir): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Файл не загрузился (код ' . $f['error'] . '). Проверьте размер файла.');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Недопустимый тип файла. Разрешено: ' . implode(', ', $allowedExt));
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $okMime = [
        'jpg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'],
        'svg' => ['image/svg+xml', 'text/xml', 'text/plain', 'application/xml'], 'pdf' => ['application/pdf'],
        'mp4' => ['video/mp4', 'application/mp4'], 'webm' => ['video/webm', 'audio/webm'], 'mov' => ['video/quicktime', 'video/mp4'],
    ];
    if (isset($okMime[$ext]) && !in_array($mime, $okMime[$ext], true)) {
        throw new RuntimeException('Содержимое файла не соответствует расширению.');
    }
    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $base = preg_replace('~[^a-z0-9_-]+~i', '-', pathinfo($f['name'], PATHINFO_FILENAME));
    $base = trim(substr((string)$base, 0, 40), '-') ?: 'file';
    $name = $base . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Не удалось сохранить файл. Проверьте права на папку uploads.');
    }
    return $subdir . '/' . $name;
}

function delete_upload(?string $rel): void
{
    if (!$rel) {
        return;
    }
    $path = realpath(UPLOAD_DIR . '/' . $rel);
    $root = realpath(UPLOAD_DIR);
    if ($path && $root && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path)) {
        @unlink($path);
    }
}

/* ---------- Корзина ---------- */

function cart_raw(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_set(int $productId, int $qty): void
{
    if ($qty <= 0) {
        unset($_SESSION['cart'][$productId]);
    } else {
        $_SESSION['cart'][$productId] = $qty;
    }
}

function cart_items(): array
{
    $raw = cart_raw();
    if (!$raw) {
        return [];
    }
    $ids = array_map('intval', array_keys($raw));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rows = q("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id IN ($in) AND p.active = 1", $ids)->fetchAll();
    $items = [];
    foreach ($rows as $r) {
        $r['qty'] = (int)$raw[$r['id']];
        $r['line_sum'] = price_per_piece($r) * $r['qty'];
        $items[] = $r;
    }
    return $items;
}

function cart_count(): int
{
    return count(cart_raw());
}

function cart_total_qty(): int
{
    return array_sum(cart_raw());
}

/* ---------- Пользователи ---------- */

function current_user(): ?array
{
    static $u = false;
    if ($u !== false) {
        return $u;
    }
    $u = null;
    if (!empty($_SESSION['user_id'])) {
        $u = q('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']])->fetch() ?: null;
    }
    return $u;
}

function require_user(): array
{
    $u = current_user();
    if (!$u) {
        flash('Войдите или зарегистрируйтесь, чтобы открыть личный кабинет.', 'info');
        redirect('login.php');
    }
    return $u;
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    return q('SELECT * FROM admins WHERE id = ?', [$_SESSION['admin_id']])->fetch() ?: null;
}

/** Разделы админки, доступ к которым можно выдать администратору */
function admin_perms(): array
{
    return [
        'orders'   => 'Заказы',
        'products' => 'Товары, разделы каталога и документы',
        'pages'    => 'Страницы сайта',
        'clients'  => 'Клиенты',
        'consents' => 'Журнал согласий',
        'settings' => 'Настройки сайта (контакты, реквизиты, Telegram, SEO)',
    ];
}

/** Есть ли у администратора доступ к разделу. Главный администратор может всё */
function admin_can(?array $a, string $perm): bool
{
    if (!$a) {
        return false;
    }
    if (!empty($a['is_owner'])) {
        return true;
    }
    if ($perm === 'owner') {
        return false;
    }
    return in_array($perm, array_filter(explode(',', (string)($a['perms'] ?? ''))), true);
}

/**
 * Проверка входа в админку и доступа к разделу ($perm: orders, products, …, owner).
 * Каждое действие (отправка формы) записывается в журнал действий администраторов.
 */
function require_admin(string $perm = ''): array
{
    $a = current_admin();
    if (!$a) {
        redirect('admin/login.php');
    }
    if ($perm !== '' && !admin_can($a, $perm)) {
        flash('Нет доступа к этому разделу. Обратитесь к главному администратору.', 'error');
        redirect('admin/index.php');
    }
    if (is_post()) {
        admin_log_action($a, (string)($_POST['action'] ?? ''), (string)($_POST['id'] ?? ''));
    }
    return $a;
}

function admin_log_action(array $a, string $action, string $target = ''): void
{
    q('INSERT INTO admin_log (created_at, admin_id, login, page, action, target, ip) VALUES (?,?,?,?,?,?,?)', [
        date('Y-m-d H:i:s'), (int)$a['id'], (string)$a['login'], basename((string)($_SERVER['SCRIPT_NAME'] ?? '')),
        mb_substr($action, 0, 40), mb_substr($target, 0, 40), client_ip(),
    ]);
}

/* ---------- Фото товаров ---------- */

/** Все фото товара по порядку; первое — главное */
function product_images(int $productId): array
{
    return q('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort, id', [$productId])->fetchAll();
}

/** Главное фото товара = первое фото в галерее (видео пропускаются), хранится в products.image для каталога */
function product_sync_cover(int $productId): void
{
    $first = q("SELECT file FROM product_images WHERE product_id = ? AND COALESCE(kind, 'image') = 'image' ORDER BY sort, id LIMIT 1", [$productId])->fetchColumn();
    q('UPDATE products SET image = ? WHERE id = ?', [$first ?: '', $productId]);
}

/** Загрузка нескольких файлов из одного поля <input type="file" multiple> */
function handle_uploads(string $field, array $allowedExt, string $subdir): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['name'])) {
        return [];
    }
    $saved = [];
    foreach (array_keys($f['name']) as $i) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $_FILES['__one'] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        $file = handle_upload('__one', $allowedExt, $subdir);
        if ($file) {
            $saved[] = $file;
        }
    }
    unset($_FILES['__one']);
    return $saved;
}

/* ---------- SEO ---------- */

/** Адрес сайта для поисковиков: из настроек или текущий */
function seo_base(): string
{
    $u = rtrim(trim(setting('site_url')), '/');
    return preg_match('~^https?://~', $u) ? $u : rtrim(site_base_url(), '/');
}

/** Абсолютная ссылка (путь как у url(): «product.php?id=1») для canonical, карты сайта и соцсетей */
function abs_url(string $path = ''): string
{
    if (preg_match('~^https?://~', $path)) {
        return $path;
    }
    return seo_base() . '/' . ltrim($path, '/');
}

/** Обрезка текста для description по границе слова */
function seo_cut(string $text, int $max = 160): string
{
    $t = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8')));
    if (mb_strlen($t) <= $max) {
        return $t;
    }
    $cut = mb_substr($t, 0, $max - 1);
    $sp = mb_strrpos($cut, ' ');
    return rtrim($sp > $max * 0.6 ? mb_substr($cut, 0, $sp) : $cut, ' ,.;:—-') . '…';
}

/* ---------- Telegram ---------- */

/** Запрос к Telegram Bot API. Возвращает расшифрованный ответ или ['ok'=>false,'description'=>...] */
function tg_api(string $method, array $params = []): array
{
    $token = trim(setting('tg_token'));
    if ($token === '') {
        return ['ok' => false, 'description' => 'Не указан токен бота'];
    }
    $base = rtrim(trim(setting('tg_api_url')) ?: (getenv('LEONPRO_TG_API') ?: 'https://api.telegram.org'), '/');
    $url = $base . '/bot' . $token . '/' . $method;
    $body = http_build_query($params);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
    } else {
        $res = @file_get_contents($url, false, stream_context_create(['http' => [
            'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body, 'timeout' => 10, 'ignore_errors' => true]]));
        $err = $res === false ? 'нет соединения' : '';
    }
    if ($res === false || $res === null || $res === '') {
        return ['ok' => false, 'description' => 'Нет связи с Telegram: ' . $err];
    }
    return json_decode((string)$res, true) ?: ['ok' => false, 'description' => 'Непонятный ответ Telegram'];
}

/** Отправить сообщение во все чаты из настроек. Возвращает список ошибок (пустой — всё ушло) */
function tg_notify(string $html): array
{
    $errors = [];
    $chats = array_filter(array_map('trim', explode(',', setting('tg_chats'))));
    if (!$chats || trim(setting('tg_token')) === '') {
        return ['Telegram не настроен'];
    }
    foreach ($chats as $chat) {
        $r = tg_api('sendMessage', ['chat_id' => $chat, 'text' => $html, 'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true']);
        if (empty($r['ok'])) {
            $errors[] = $chat . ': ' . ($r['description'] ?? 'ошибка');
        }
    }
    if ($errors) {
        @file_put_contents(preg_replace('~\.sqlite$~', '-mail.log', db_file()), date('c') . ' | TG FAIL | ' . implode('; ', $errors) . "\n", FILE_APPEND);
    }
    return $errors;
}

function site_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE;
}

function tg_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- Почта ---------- */

function send_mail(string $to, string $subject, string $body): bool
{
    $to = trim($to);
    if ($to === '') {
        return false;
    }
    $host = preg_replace('~:\d+$~', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $from = setting('mail_from') ?: ('noreply@' . $host);
    $headers = [
        'From: =?UTF-8?B?' . base64_encode(setting('site_name')) . '?= <' . $from . '>',
        'Reply-To: ' . (filter_var(setting('email'), FILTER_VALIDATE_EMAIL) ? setting('email') : $from),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . $from);
    @file_put_contents(preg_replace('~\.sqlite$~', '-mail.log', db_file()),date('c') . ' | ' . ($ok ? 'OK ' : 'FAIL') . " | $to | $subject\n", FILE_APPEND);
    return $ok;
}

/* ---------- Согласия (152-ФЗ) ---------- */

/** Виды согласий: название, страница с текстом документа, текст галочки */
function consent_kinds(): array
{
    return [
        'pd'     => ['Обработка персональных данных', 'consent', 'Даю согласие на обработку персональных данных'],
        'oferta' => ['Публичная оферта', 'oferta', 'Принимаю условия публичной оферты'],
    ];
}

function consent_sources(): array
{
    return ['register' => 'Регистрация', 'order' => 'Заявка'];
}

/** IP посетителя. За прокси (Caddy в Docker) настоящий адрес приходит в X-Forwarded-For */
function client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $fwd = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if ($fwd !== '' && !$isPublic) {
        $first = trim(explode(',', $fwd)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            $ip = $first;
        }
    }
    return $ip;
}

/**
 * Записать согласия в журнал. Текст документа (политики, оферты) сохраняется отдельной версией —
 * в админке видно, какую именно редакцию принял человек, даже если текст потом поменяли.
 */
function consent_log(string $source, array $kinds, array $who, ?int $userId = null, ?int $orderId = null): void
{
    $all = consent_kinds();
    $now = date('Y-m-d H:i:s');
    foreach ($kinds as $kind) {
        if (!isset($all[$kind])) {
            continue;
        }
        [$label, $slug, $text] = $all[$kind];
        $page = q('SELECT title, content FROM pages WHERE slug = ?', [$slug])->fetch()
            ?: ($slug === 'consent' ? q("SELECT title, content FROM pages WHERE slug = 'privacy'")->fetch() : false);
        $title = $page['title'] ?? $label;
        $content = $page ? render_content((string)$page['content']) : '';
        $hash = substr(hash('sha256', $title . "\n" . $content), 0, 16);
        q('INSERT OR IGNORE INTO consent_docs (hash, slug, title, content, created_at) VALUES (?,?,?,?,?)', [$hash, $slug, $title, $content, $now]);
        q('INSERT INTO consents (created_at, source, kind, user_id, order_id, company, contact, email, phone, ip, user_agent, checkbox_text, doc_hash, site)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $now, $source, $kind, $userId, $orderId,
            (string)($who['company'] ?? ''), (string)($who['contact'] ?? ''), (string)($who['email'] ?? ''), (string)($who['phone'] ?? ''),
            client_ip(), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300), $text, $hash, site_base_url(),
        ]);
    }
}

/** Хлебные крошки для поисковиков: [[название, путь], …] */
function ld_breadcrumbs(array $items): array
{
    $list = [];
    foreach (array_values($items) as $i => [$name, $path]) {
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => abs_url($path)];
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

/** Организация для поисковиков (главная страница) */
function ld_organization(): array
{
    $org = [
        '@context' => 'https://schema.org', '@type' => 'Organization',
        'name' => setting('site_name'), 'legalName' => setting('legal_name') ?: null,
        'url' => abs_url(''), 'logo' => abs_url('assets/logo/leonpro-mark-for-light-bg.png'),
        'telephone' => setting('phone') ?: null, 'email' => setting('email') ?: null,
        'taxID' => setting('legal_inn') ?: null,
        'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'RU', 'addressLocality' => 'Краснодар',
                      'streetAddress' => setting('address') ?: null],
    ];
    $same = array_values(array_filter([setting('telegram'), setting('whatsapp')]));
    if ($same) {
        $org['sameAs'] = $same;
    }
    return array_filter($org, fn($v) => $v !== null);
}

/* ---------- Видео товара ---------- */

/** Тип загруженного файла галереи по расширению: image или video */
function media_kind_by_file(string $file): string
{
    return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['mp4', 'webm', 'mov'], true) ? 'video' : 'image';
}

/**
 * Ссылка на видео Rutube, VK Видео или YouTube → адрес для встраивания на сайт.
 * null — ссылка не распознана.
 */
function video_embed_url(string $url): ?string
{
    $url = trim($url);
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('~rutube\.ru/(?:video|play/embed|shorts)/([a-f0-9]{32})~i', $url, $m)) {
        return 'https://rutube.ru/play/embed/' . strtolower($m[1]);
    }
    if (preg_match('~(?:vk\.com|vk\.ru|vkvideo\.ru)/video_ext\.php\?oid=(-?\d+)&id=(\d+)~', $url, $m)
        || preg_match('~(?:vk\.com|vk\.ru|vkvideo\.ru)/(?:[^?#]*[?&]z=)?video(-?\d+)_(\d+)~', $url, $m)) {
        return 'https://vk.com/video_ext.php?oid=' . $m[1] . '&id=' . $m[2] . '&hd=2';
    }
    return null;
}

/** Картинка-превью для встроенного видео (есть только у YouTube) */
function video_embed_thumb(string $embed): string
{
    return preg_match('~youtube\.com/embed/([A-Za-z0-9_-]{11})~', $embed, $m) ? 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg' : '';
}

/** Название сервиса для подписи */
function video_embed_service(string $embed): string
{
    return str_contains($embed, 'youtube') ? 'YouTube' : (str_contains($embed, 'rutube') ? 'Rutube' : 'VK Видео');
}

/* ---------- Технический перерыв ---------- */

function maintenance_on(): bool
{
    return setting('maintenance') === '1';
}

/**
 * При включённом техперерыве посетители видят заглушку (код 503 — поисковики поймут, что это временно).
 * Админка и вошедшие администраторы работают как обычно.
 */
function maintenance_gate(): void
{
    if (!maintenance_on() || !empty($_SESSION['admin_id'])) {
        return;
    }
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if (basename(dirname($script)) === 'admin') {
        return;
    }
    http_response_code(503);
    header('Retry-After: 3600');
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $phone = setting('phone');
    $tel = preg_replace('~[^\d+]~', '', $phone);
    ?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Технический перерыв — <?= e(setting('site_name')) ?></title>
<link rel="icon" href="<?= url('assets/favicon.svg') ?>" type="image/svg+xml">
<style>
  :root { --accent: <?= e(setting('accent_color') ?: '#F07F1F') ?>; }
  * { box-sizing: border-box; }
  body { margin: 0; min-height: 100vh; min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
         background: #17171A; color: #fff; font: 17px/1.55 'Golos Text', system-ui, -apple-system, 'Segoe UI', sans-serif; }
  main { max-width: 560px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 18px; }
  .mark svg { width: 96px; height: auto; }
  h1 { margin: 0; font-size: clamp(26px, 6vw, 36px); line-height: 1.2; }
  p { margin: 0; color: #D6D7DA; }
  .phone { display: inline-flex; align-items: center; min-height: 52px; padding: 0 26px; border-radius: 8px; background: var(--accent); color: #17171A;
           font-weight: 700; font-size: 20px; text-decoration: none; font-variant-numeric: tabular-nums; }
  .phone:focus-visible { outline: 3px solid #fff; outline-offset: 3px; }
  .muted { font-size: 14px; color: #A9ABB0; }
</style>
</head>
<body>
<main>
  <span class="mark" aria-hidden="true"><?= logo_mark(96, true) ?></span>
  <h1>Технический перерыв</h1>
  <p><?= nl2br(e(setting('maintenance_text'))) ?></p>
  <?php if ($phone !== ''): ?><a class="phone" href="tel:<?= e($tel) ?>"><?= e($phone) ?></a><?php endif; ?>
  <?php if (setting('email') !== ''): ?><p><a href="mailto:<?= e(setting('email')) ?>" style="color:#F59A3A"><?= e(setting('email')) ?></a></p><?php endif; ?>
  <p class="muted"><?= e(setting('site_name')) ?></p>
</main>
</body>
</html>
    <?php
    exit;
}

