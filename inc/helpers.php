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

        'min_order_qty'    => ['Заказы', 'Минимальный заказ, шт.', 'int', '5000', 'Суммарно по корзине'],
        'default_step'     => ['Заказы', 'Шаг количества по умолчанию, шт.', 'int', '500', 'Если у товара не указана кратность'],
        'show_prices'      => ['Заказы', 'Показывать цены', 'bool', '1', 'Если выключено — «Цена по запросу»'],
        'price_note'       => ['Заказы', 'Пометка к ценам', 'text', 'Оптовая цена за штуку', 'Например, «Цены с НДС» после перехода на НДС'],
        'delivery_note'    => ['Заказы', 'Коротко о доставке', 'textarea', 'Отгрузка со склада в Краснодаре через транспортные компании. Доставку оплачивает покупатель.', ''],
        'order_success'    => ['Заказы', 'Текст после отправки заявки', 'textarea', 'Менеджер свяжется с вами, подтвердит состав заказа, согласует стоимость и транспортную компанию.', ''],

        'tg_token'         => ['Уведомления', 'Токен Telegram-бота', 'secret', '', 'Выдаёт @BotFather при создании бота. Хранится скрыто'],
        'tg_chats'         => ['Уведомления', 'ID чатов Telegram для заявок', 'text', '', 'Через запятую. Нажмите «Найти ID чата» ниже'],
        'manager_email'    => ['Уведомления', 'Email менеджера для новых заказов', 'text', '', 'Необязательно, дублирует заявки на почту. Можно несколько через запятую'],
        'mail_from'        => ['Уведомления', 'Адрес отправителя писем', 'text', '', 'Например, noreply@ваш-домен.ru (ящик на вашем домене)'],

        'seo_title'        => ['SEO', 'Заголовок сайта (title)', 'text', 'Leon_pro — тарельчатые дюбели, площадки под стяжку, якорные скобы и СВП оптом', ''],
        'seo_description'  => ['SEO', 'Описание (description)', 'textarea', 'Производитель крепежа для теплоизоляции и стяжки. Оптовые поставки со склада в Краснодаре.', ''],
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

/** Блок реквизитов для страниц (метка {{реквизиты}} в тексте страницы) */
function requisites_html(): string
{
    $rows = [
        [setting('legal_name'), ''],
        ['ИНН', setting('legal_inn')], ['ОГРНИП', setting('legal_ogrn')], ['ОКПО', setting('legal_okpo')],
        ['Юридический адрес', setting('legal_address')],
        ['Банк', setting('bank_name')], ['Р/с', setting('bank_account')], ['К/с', setting('bank_corr')], ['БИК', setting('bank_bik')],
    ];
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
    return str_replace(['{{реквизиты}}', '{{requisites}}', '{{продавец}}', '{{телефон}}'],
        [requisites_html(), requisites_html(), e(setting('legal_name')), e(setting('phone'))], $html);
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

function price_label(array $p): string
{
    if (setting('show_prices') !== '1' || (float)$p['price'] <= 0) {
        return 'По запросу';
    }
    return fmt_money($p['price']);
}

function product_step(array $p): int
{
    $s = (int)($p['step'] ?? 0);
    return $s > 0 ? $s : max(1, (int)setting('default_step'));
}

function order_statuses(): array
{
    return [
        'new'       => 'Заявка принята',
        'confirmed' => 'Согласован',
        'payment'   => 'Ожидает оплаты',
        'shipped'   => 'Передан в ТК',
        'done'      => 'Получен',
        'cancelled' => 'Отменён',
    ];
}

function status_label(string $s): string
{
    return order_statuses()[$s] ?? $s;
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
        $r['line_sum'] = (float)$r['price'] * $r['qty'];
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

function require_admin(): array
{
    $a = current_admin();
    if (!$a) {
        redirect('admin/login.php');
    }
    return $a;
}

/* ---------- Telegram ---------- */

/** Запрос к Telegram Bot API. Возвращает расшифрованный ответ или ['ok'=>false,'description'=>...] */
function tg_api(string $method, array $params = []): array
{
    $token = trim(setting('tg_token'));
    if ($token === '') {
        return ['ok' => false, 'description' => 'Не указан токен бота'];
    }
    $url = (getenv('LEONPRO_TG_API') ?: 'https://api.telegram.org') . '/bot' . $token . '/' . $method;
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
