<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }
    $file = db_file();
    $isNew = !file_exists($file);
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    if ($isNew || !table_exists($pdo, 'settings')) {
        db_install($pdo);
        if (IS_DEMO) {
            demo_seed($pdo);
        }
    }
    db_migrate($pdo);
    return $pdo;
}

/** Файл базы со случайным именем — его нельзя угадать и скачать по прямой ссылке */
function db_file(): string
{
    $found = glob(DATA_DIR . '/db-*.sqlite') ?: [];
    return $found[0] ?? DATA_DIR . '/db-' . bin2hex(random_bytes(12)) . '.sqlite';
}

/** Обновления базы для сайтов, установленных на прошлой версии */
function db_migrate(PDO $pdo): void
{
    $v = (int)($pdo->query("SELECT value FROM settings WHERE key = 'db_version'")->fetchColumn() ?: 1);
    if ($v < 2) {
        // Реквизиты заказчика вместо заглушек в текстах страниц
        $repl = [
            '<p>[Наименование организации]<br>ИНН [ИНН] / КПП [КПП]<br>ОГРН [ОГРН]<br>Р/с [счёт] в [банк]<br>К/с [корр. счёт], БИК [БИК]</p>' => '{{реквизиты}}',
            '<p>[Наименование организации] (далее' => '<p>{{продавец}} (далее',
            '<p>[Реквизиты]</p>' => '{{реквизиты}}',
            'пользователей сайта [домен] в' => 'пользователей этого сайта в',
            '<p>[Наименование организации, адрес, контакты]</p>' => '{{реквизиты}}' . "\n" . '<p>Телефон: {{телефон}}</p>',
        ];
        $st = $pdo->prepare('UPDATE pages SET content = REPLACE(content, ?, ?)');
        foreach ($repl as $a => $b) {
            $st->execute([$a, $b]);
        }
        // Документы заказчика в раздел «Сертификаты»
        $docs = [
            ['Свидетельство на товарный знак LEONPRO № 893413', 'assets/docs/leonpro-tovarnyi-znak-893413.pdf', 1],
            ['Свидетельство DiSAI № 2437232 о присвоении штрихкодов EAN-13', 'assets/docs/leonpro-svidetelstvo-ean13-2437232.pdf', 2],
        ];
        $chk = $pdo->prepare('SELECT 1 FROM documents WHERE file = ?');
        $ins = $pdo->prepare('INSERT INTO documents (title, file, product_id, sort) VALUES (?, ?, NULL, ?)');
        foreach ($docs as $d) {
            $chk->execute([$d[1]]);
            if (!$chk->fetchColumn()) {
                $ins->execute($d);
            }
        }
        // Заглушки контактов прошлой версии больше не нужны
        $pdo->exec("DELETE FROM settings WHERE key IN ('phone','email','address','work_hours') AND value LIKE '%[%'");
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('db_version', '2') ON CONFLICT(key) DO UPDATE SET value = '2'");
    }
    if ($v < 3) {
        // Журнал согласий: кто, когда, с какого IP и на какой текст документа поставил галочку.
        // Без внешних ключей: запись должна остаться, даже если клиента или заказ удалят.
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS consents (
  id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL,
  source TEXT NOT NULL, kind TEXT NOT NULL, user_id INTEGER, order_id INTEGER,
  company TEXT DEFAULT '', contact TEXT DEFAULT '', email TEXT DEFAULT '', phone TEXT DEFAULT '',
  ip TEXT DEFAULT '', user_agent TEXT DEFAULT '', checkbox_text TEXT DEFAULT '', doc_hash TEXT DEFAULT '',
  revoked_at TEXT, revoked_note TEXT DEFAULT '');
CREATE INDEX IF NOT EXISTS consents_user ON consents(user_id);
CREATE INDEX IF NOT EXISTS consents_order ON consents(order_id);
CREATE TABLE IF NOT EXISTS consent_docs (
  hash TEXT PRIMARY KEY, slug TEXT, title TEXT, content TEXT, created_at TEXT NOT NULL);
SQL);
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('db_version', '3') ON CONFLICT(key) DO UPDATE SET value = '3'");
    }
    if ($v < 4) {
        // Этапы заказа: новый → связались → доставка → оплата → отгружен → закрыт, история смены этапов
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS order_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, created_at TEXT NOT NULL,
  status TEXT NOT NULL, who TEXT DEFAULT '', note TEXT DEFAULT '');
CREATE INDEX IF NOT EXISTS order_events_order ON order_events(order_id);
SQL);
        $map = ['confirmed' => 'delivery', 'payment' => 'delivery', 'done' => 'closed'];
        $st = $pdo->prepare('UPDATE orders SET status = ? WHERE status = ?');
        foreach ($map as $old => $new) {
            $st->execute([$new, $old]);
        }
        $pdo->exec("INSERT INTO order_events (order_id, created_at, status, who) SELECT id, created_at, 'new', 'сайт' FROM orders");
        $pdo->exec("INSERT INTO order_events (order_id, created_at, status, who) SELECT id, COALESCE(updated_at, created_at), status, '' FROM orders WHERE status <> 'new'");
        // Фасовка на момент заказа — чтобы показывать упаковки, даже если фасовку товара потом поменяют
        $cols = array_column($pdo->query('PRAGMA table_info(order_items)')->fetchAll(), 'name');
        if (!in_array('pack_qty', $cols, true)) {
            $pdo->exec('ALTER TABLE order_items ADD COLUMN pack_qty INTEGER DEFAULT 0');
            $pdo->exec('UPDATE order_items SET pack_qty = COALESCE((SELECT pack_qty FROM products p WHERE p.id = order_items.product_id), 0)');
        }
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('db_version', '4') ON CONFLICT(key) DO UPDATE SET value = '4'");
    }
    if ($v < 5) {
        // Адрес сайта, на котором дано согласие (для документа-согласия)
        $cols = array_column($pdo->query('PRAGMA table_info(consents)')->fetchAll(), 'name');
        if (!in_array('site', $cols, true)) {
            $pdo->exec("ALTER TABLE consents ADD COLUMN site TEXT DEFAULT ''");
        }
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('db_version', '5') ON CONFLICT(key) DO UPDATE SET value = '5'");
    }
    if ($v < 6) {
        // В политике персональных данных у оператора — без банковских реквизитов
        $pdo->exec("UPDATE pages SET content = REPLACE(content, '{{реквизиты}}', '{{оператор}}') WHERE slug = 'privacy'");
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('db_version', '6') ON CONFLICT(key) DO UPDATE SET value = '6'");
    }
    if ($v < 7) {
        $addCol = static function (string $table, string $col, string $def) use ($pdo): void {
            $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
            if (!in_array($col, $cols, true)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
            }
        };
        // Права администраторов: главный администратор и разделы для остальных
        $addCol('admins', 'is_owner', 'INTEGER DEFAULT 0');
        $addCol('admins', 'perms', "TEXT DEFAULT ''");
        $addCol('admins', 'name', "TEXT DEFAULT ''");
        $addCol('admins', 'last_login', 'TEXT');
        // Все, кто уже был администратором, сохраняют полный доступ
        $pdo->exec('UPDATE admins SET is_owner = 1');
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS admin_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, admin_id INTEGER, login TEXT DEFAULT '',
  page TEXT DEFAULT '', action TEXT DEFAULT '', target TEXT DEFAULT '', ip TEXT DEFAULT '');
CREATE INDEX IF NOT EXISTS admin_log_created ON admin_log(created_at);
CREATE TABLE IF NOT EXISTS product_images (
  id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  file TEXT NOT NULL, sort INTEGER DEFAULT 0);
CREATE INDEX IF NOT EXISTS product_images_product ON product_images(product_id);
SQL);
        // Уже загруженное фото товара становится первым (главным) в галерее
        $pdo->exec("INSERT INTO product_images (product_id, file, sort) SELECT id, image, 0 FROM products WHERE COALESCE(image, '') <> ''");
        // SEO-поля у товаров, разделов и страниц
        foreach (['products', 'categories', 'pages'] as $t) {
            $addCol($t, 'seo_title', "TEXT DEFAULT ''");
            $addCol($t, 'seo_description', "TEXT DEFAULT ''");
        }
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('db_version', '7') ON CONFLICT(key) DO UPDATE SET value = '7'");
    }
    if ($v < 8) {
        // Минимальный заказ в текстах страниц берётся из настроек (0 — фраза не показывается)
        $st = $pdo->prepare('UPDATE pages SET content = REPLACE(content, ?, ?)');
        $st->execute(['Добавьте товары в корзину. Минимальный заказ — от 5 000 шт. по всей корзине.', 'Добавьте товары в корзину.{{минимальный заказ}}']);
        $st->execute(['2.1. Минимальный объём заказа — 5 000 шт. по всей заявке.', '2.1. {{минимальный заказ в оферте}}']);
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('db_version', '8') ON CONFLICT(key) DO UPDATE SET value = '8'");
    }
    if ($v < 9) {
        // Цена товара с фасовкой — за упаковку; пометка «Оптовая цена за штуку» больше не верна
        $pdo->exec("UPDATE settings SET value = 'Цена' WHERE key = 'price_note' AND value = 'Оптовая цена за штуку'");
        // Фото и видео товара: тип записи (image — фото, video — видеофайл, embed — ссылка на видео)
        $cols = array_column($pdo->query('PRAGMA table_info(product_images)')->fetchAll(), 'name');
        if (!in_array('kind', $cols, true)) {
            $pdo->exec("ALTER TABLE product_images ADD COLUMN kind TEXT DEFAULT 'image'");
        }
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('db_version', '9') ON CONFLICT(key) DO UPDATE SET value = '9'");
    }
    if ($v < 10) {
        // Документы: новая редакция политики и оферты, отдельное согласие на обработку ПДн,
        // страницы без заглушек и без пометки «Черновик»
        $get = $pdo->prepare('SELECT content FROM pages WHERE slug = ?');
        $set = $pdo->prepare('UPDATE pages SET content = ? WHERE slug = ?');
        $marks = ['privacy' => 'Черновик. Перед публикацией', 'oferta' => 'Черновик. Перед публикацией',
                  'delivery' => '[перечислите ТК]', 'about' => '[Расскажите о производстве'];
        $seeds = ['privacy' => page_seed_privacy(), 'oferta' => page_seed_oferta(), 'delivery' => page_seed_delivery(), 'about' => page_seed_about()];
        foreach ($marks as $slug => $mark) {
            $get->execute([$slug]);
            $content = $get->fetchColumn();
            if ($content !== false && str_contains((string)$content, $mark)) {
                $set->execute([$seeds[$slug], $slug]);
            }
        }
        $pdo->exec("UPDATE pages SET content = REPLACE(content, '<p><em>Черновик. Перед публикацией текст должен проверить юрист.</em></p>', '')");
        $pdo->exec("UPDATE pages SET content = REPLACE(content, '<h2>Реквизиты для оплаты</h2>', '<h2>Реквизиты</h2>')");
        $get->execute(['consent']);
        if ($get->fetchColumn() === false) {
            $pdo->prepare('INSERT INTO pages (slug, title, content, in_menu, sort) VALUES (?,?,?,?,?)')
                ->execute(['consent', 'Согласие на обработку персональных данных', page_seed_consent(), 0, 5]);
        }
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('db_version', '10') ON CONFLICT(key) DO UPDATE SET value = '10'");
    }
    if ($v < 11) {
        // Дата выставления счёта по заказу (номер счёта = номер заказа)
        $cols = array_column($pdo->query('PRAGMA table_info(orders)')->fetchAll(), 'name');
        if (!in_array('invoice_date', $cols, true)) {
            $pdo->exec('ALTER TABLE orders ADD COLUMN invoice_date TEXT');
        }
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('db_version', '11') ON CONFLICT(key) DO UPDATE SET value = '11'");
    }
}

function table_exists(PDO $pdo, string $name): bool
{
    $st = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $st->execute([$name]);
    return (bool)$st->fetchColumn();
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function db_install(PDO $pdo): void
{
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT);
CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT, login TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, description TEXT DEFAULT '',
  image TEXT DEFAULT '', sort INTEGER DEFAULT 0, active INTEGER DEFAULT 1);
CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  name TEXT NOT NULL, sku TEXT DEFAULT '', size TEXT DEFAULT '', material TEXT DEFAULT '',
  type TEXT DEFAULT '', pack_qty INTEGER DEFAULT 0, price REAL DEFAULT 0, step INTEGER DEFAULT 0,
  description TEXT DEFAULT '', specs TEXT DEFAULT '', image TEXT DEFAULT '',
  popular INTEGER DEFAULT 0, active INTEGER DEFAULT 1, sort INTEGER DEFAULT 0,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, file TEXT NOT NULL,
  product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
  sort INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS pages (
  id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE NOT NULL, title TEXT NOT NULL,
  content TEXT DEFAULT '', in_menu INTEGER DEFAULT 1, sort INTEGER DEFAULT 0);
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, phone TEXT UNIQUE,
  password_hash TEXT NOT NULL, company TEXT DEFAULT '', inn TEXT DEFAULT '',
  contact TEXT DEFAULT '', city TEXT DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  company TEXT, inn TEXT, contact TEXT, phone TEXT, email TEXT, city TEXT, comment TEXT,
  total_qty INTEGER DEFAULT 0, total_sum REAL DEFAULT 0, status TEXT DEFAULT 'new',
  manager_note TEXT DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT);
CREATE TABLE IF NOT EXISTS order_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
  product_id INTEGER, name TEXT, size TEXT, price REAL, qty INTEGER);
SQL);

    // Стартовые данные — всё редактируется в админке
    $cats = [
        ['Тарельчатые дюбели', 'Для крепления утеплителя к основанию', 1],
        ['Площадки под стяжку', 'Фиксация арматуры и сетки в стяжке', 2],
        ['Якорные скобы', 'Крепление труб тёплого пола', 3],
        ['СВП', 'Система выравнивания плитки', 4],
    ];
    $st = $pdo->prepare('INSERT INTO categories (name, description, sort) VALUES (?,?,?)');
    foreach ($cats as $c) {
        $st->execute($c);
    }
    $products = [
        [1, 'Дюбель тарельчатый с пластиковым гвоздём', '10×90', 'Пластиковый гвоздь', 1],
        [1, 'Дюбель тарельчатый с пластиковым гвоздём', '10×120', 'Пластиковый гвоздь', 1],
        [1, 'Дюбель тарельчатый с металлическим гвоздём', '10×160', 'Металлический гвоздь', 0],
        [1, 'Дюбель тарельчатый с металлическим гвоздём', '10×200', 'Металлический гвоздь', 0],
        [2, 'Площадка под стяжку', '', '', 1],
        [2, 'Площадка под стяжку усиленная', '', '', 0],
        [3, 'Скоба якорная для тёплого пола', '', '', 1],
        [4, 'СВП — зажим', '', 'Зажим', 1],
        [4, 'СВП — клин', '', 'Клин', 0],
    ];
    $st = $pdo->prepare('INSERT INTO products (category_id, name, size, type, popular, sort, specs) VALUES (?,?,?,?,?,?,?)');
    foreach ($products as $i => $p) {
        $st->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $i + 1, "Производство: Краснодар"]);
    }

    $pages = [
        ['about', 'О компании', page_seed_about(), 1, 1],
        ['delivery', 'Доставка и оплата', page_seed_delivery(), 1, 2],
        ['oferta', 'Публичная оферта', page_seed_oferta(), 0, 3],
        ['privacy', 'Политика обработки персональных данных', page_seed_privacy(), 0, 4],
        ['consent', 'Согласие на обработку персональных данных', page_seed_consent(), 0, 5],
    ];
    $st = $pdo->prepare('INSERT INTO pages (slug, title, content, in_menu, sort) VALUES (?,?,?,?,?)');
    foreach ($pages as $p) {
        $st->execute($p);
    }
}

/** Демо-данные для показа на Vercel: администратор, покупатель и пример заказа */
function demo_seed(PDO $pdo): void
{
    $pdo->prepare('INSERT INTO admins (login, password_hash) VALUES (?, ?)')
        ->execute(['demo', password_hash('leonpro2026', PASSWORD_DEFAULT)]);
    $pdo->prepare('INSERT INTO users (email, password_hash, company, inn, contact, city) VALUES (?,?,?,?,?,?)')
        ->execute(['demo@leonpro.ru', password_hash('demo2026', PASSWORD_DEFAULT), 'ООО «Пример» (демо)', '2300000000', 'Демо Покупатель', 'Краснодар']);
    $uid = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO orders (user_id, company, inn, contact, phone, email, city, comment, total_qty, total_sum, status, manager_note, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$uid, 'ООО «Пример» (демо)', '2300000000', 'Демо Покупатель', '+7 900 000-00-00', 'demo@leonpro.ru', 'Краснодар',
                   'Демонстрационный заказ', 6000, 0, 'contacted', 'Это пример заказа для демонстрации', date('Y-m-d H:i:s')]);
    $oid = (int)$pdo->lastInsertId();
    $st = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, size, price, qty) VALUES (?,?,?,?,?,?)');
    $st->execute([$oid, 2, 'Дюбель тарельчатый с пластиковым гвоздём', '10×120', 0, 4000]);
    $st->execute([$oid, 7, 'Скоба якорная для тёплого пола', '', 0, 2000]);
}

function page_seed_about(): string
{
    return <<<'HTML'
<p><strong>Leon_pro</strong> — российский производитель крепежа для теплоизоляции, стяжки и плиточных работ: тарельчатых дюбелей, площадок под стяжку, якорных скоб и систем выравнивания плитки (СВП). Производство и склад находятся в Краснодаре.</p>
<h2>С кем мы работаем</h2>
<p>Строительные компании, подрядчики, бригады и магазины стройматериалов. Отгружаем со склада в Краснодаре и отправляем транспортными компаниями по всей России.</p>
<h2>Почему с нами удобно</h2>
<ul>
<li><strong>Своё производство</strong> — цены от производителя, без посредников.</li>
<li><strong>Склад в Краснодаре</strong> — ходовые позиции в наличии.</li>
<li><strong>Документы</strong> — сертификаты и паспорта качества можно скачать в разделе «Сертификаты» и в карточке товара.</li>
<li><strong>Собственный бренд</strong> — товарный знак LEONPRO зарегистрирован (свидетельство № 893413), товары маркированы штрихкодами EAN-13.</li>
</ul>
<h2>Реквизиты</h2>
{{реквизиты}}
HTML;
}

function page_seed_delivery(): string
{
    return <<<'HTML'
<h2>Как оформить заказ</h2>
<ol>
<li>Добавьте товары в корзину.{{минимальный заказ}}</li>
<li>Заполните заявку: название компании, ИНН, контактное лицо, телефон, email и город доставки.</li>
<li>Менеджер свяжется с вами, подтвердит состав заказа, согласует стоимость и способ доставки.</li>
<li>Мы выставим счёт на оплату. Он придёт от менеджера и будет доступен в личном кабинете.</li>
<li>После поступления оплаты заказ собирается и передаётся в транспортную компанию.</li>
</ol>
<h2>Доставка</h2>
<p>Отгрузка производится со склада в Краснодаре. Заказ передаём в транспортную компанию, которую мы согласуем с вами при подтверждении заказа. Стоимость доставки рассчитывается по тарифам транспортной компании и оплачивается покупателем при получении груза или по договорённости с перевозчиком.</p>
<p>После отгрузки менеджер сообщит номер накладной — по нему можно отслеживать груз на сайте транспортной компании.</p>
<h2>Оплата</h2>
<p>Онлайн-оплаты на сайте нет. Оплата — безналичным переводом по счёту, выставленному после согласования заказа. Реквизиты для оплаты указаны в счёте.</p>
<h2>Документы</h2>
<p>С товаром передаём закрывающие документы. Сертификаты и паспорта качества можно скачать в разделе «Сертификаты» и в карточке каждого товара.</p>
HTML;
}

function page_seed_oferta(): string
{
    return <<<'HTML'
<p>Редакция от 21.10.2026</p>
<h2>1. Общие положения</h2>
<p>1.1. Настоящий документ является публичной офертой {{продавец}} (далее — «Поставщик») и содержит условия договора поставки товаров, представленных на сайте {{сайт}} (далее — «Сайт»), в соответствии со статьями 435 и 437 Гражданского кодекса Российской Федерации.</p>
<p>1.2. Оферта адресована юридическим лицам и индивидуальным предпринимателям, приобретающим товар для использования в предпринимательской деятельности (далее — «Покупатель»).</p>
<p>1.3. Акцептом оферты является оплата Покупателем счёта, выставленного Поставщиком (п. 3 ст. 438 ГК РФ). С момента акцепта договор поставки на условиях настоящей оферты (далее — «Договор») считается заключённым. Счёт, выставленный по заказу, является неотъемлемой частью Договора.</p>
<p>1.4. Оформление заявки на Сайте не является акцептом оферты и не обязывает Покупателя к оплате.</p>
<h2>2. Предмет договора</h2>
<p>2.1. Поставщик обязуется передать в собственность Покупателя товар (тарельчатые дюбели, площадки под стяжку, якорные скобы, системы выравнивания плитки и другие товары с Сайта), а Покупатель обязуется принять товар и оплатить его.</p>
<p>2.2. Наименование, количество, цена и общая стоимость товара указываются в счёте.</p>
<p>2.3. {{минимальный заказ в оферте}}</p>
<h2>3. Порядок оформления заказа</h2>
<p>3.1. Покупатель оформляет заявку на Сайте, указывая наименование организации, ИНН, контактное лицо, телефон, адрес электронной почты и город доставки.</p>
<p>3.2. Поставщик связывается с Покупателем, подтверждает наличие товара, состав заказа, стоимость и способ доставки, после чего выставляет счёт.</p>
<p>3.3. Изменение состава заказа после выставления счёта производится по согласованию сторон с выставлением нового счёта.</p>
<h2>4. Цена и порядок оплаты</h2>
<p>4.1. Цены на Сайте носят информационный характер. Цена товара по Договору — цена, указанная в счёте. Цены товаров, расфасованных в упаковки, указываются за упаковку.</p>
<p>4.2. Оплата производится в размере 100% стоимости товара безналичным переводом на расчётный счёт Поставщика по реквизитам, указанным в счёте.</p>
<p>4.3. Счёт действителен в течение 5 (пяти) банковских дней с даты выставления, если в счёте не указан иной срок. После истечения срока Поставщик вправе изменить цену или отказаться от поставки, вернув полученные денежные средства.</p>
<p>4.4. Обязательство Покупателя по оплате считается исполненным с момента зачисления денежных средств на расчётный счёт Поставщика.</p>
<p>4.5. Налог на добавленную стоимость указывается в счёте в соответствии с системой налогообложения, применяемой Поставщиком.</p>
<p>4.6. Стоимость доставки в цену товара не входит, если иное не указано в счёте.</p>
<h2>5. Поставка</h2>
<p>5.1. Поставщик отгружает товар со склада в г. Краснодаре в течение 10 (десяти) рабочих дней с момента поступления оплаты, если иной срок не согласован сторонами.</p>
<p>5.2. Товар передаётся транспортной компании, согласованной сторонами, для доставки Покупателю. Расходы на доставку несёт Покупатель.</p>
<p>5.3. Обязанность Поставщика по передаче товара считается исполненной, а право собственности и риск случайной гибели или повреждения товара переходят к Покупателю с момента передачи товара первому перевозчику (ст. 458, 459 ГК РФ).</p>
<p>5.4. Вместе с товаром Поставщик передаёт закрывающие документы (универсальный передаточный документ или товарную накладную).</p>
<h2>6. Приёмка товара</h2>
<p>6.1. Покупатель проверяет количество грузовых мест и целостность упаковки при получении товара от перевозчика. Повреждения фиксируются в документах перевозчика.</p>
<p>6.2. Приёмка товара по количеству единиц и качеству производится Покупателем в течение 10 (десяти) рабочих дней с момента получения.</p>
<p>6.3. При обнаружении недостачи или несоответствия качества Покупатель в тот же срок направляет Поставщику претензию с описанием недостатков и фотографиями.</p>
<p>6.4. Качество товара подтверждается сертификатами и паспортами качества, размещёнными на Сайте.</p>
<h2>7. Замена и возврат</h2>
<p>7.1. Товар ненадлежащего качества или некомплектный товар Поставщик заменяет либо возвращает Покупателю уплаченные за него денежные средства в течение 10 (десяти) рабочих дней с момента признания претензии обоснованной.</p>
<p>7.2. Возврат товара надлежащего качества возможен только по соглашению сторон.</p>
<h2>8. Ответственность сторон</h2>
<p>8.1. За неисполнение или ненадлежащее исполнение Договора стороны несут ответственность в соответствии с законодательством Российской Федерации.</p>
<p>8.2. Поставщик не несёт ответственности за сроки доставки и сохранность груза после передачи товара перевозчику.</p>
<h2>9. Обстоятельства непреодолимой силы</h2>
<p>9.1. Стороны освобождаются от ответственности за неисполнение обязательств, вызванное обстоятельствами непреодолимой силы, на время действия таких обстоятельств. Сторона, для которой они наступили, уведомляет другую сторону в течение 5 (пяти) рабочих дней.</p>
<h2>10. Разрешение споров</h2>
<p>10.1. Споры решаются путём переговоров. Претензионный порядок обязателен; срок ответа на претензию — 10 (десять) рабочих дней с момента её получения.</p>
<p>10.2. При недостижении согласия спор передаётся на рассмотрение в Арбитражный суд Краснодарского края.</p>
<h2>11. Срок действия и изменение оферты</h2>
<p>11.1. Оферта действует бессрочно до её отзыва Поставщиком. Поставщик вправе изменять условия оферты, публикуя новую редакцию на Сайте.</p>
<p>11.2. К Договору применяется редакция оферты, действовавшая на дату выставления счёта.</p>
<h2>12. Персональные данные</h2>
<p>12.1. Персональные данные представителей Покупателя обрабатываются в соответствии с Политикой обработки персональных данных, размещённой на Сайте.</p>
<h2>13. Реквизиты Поставщика</h2>
{{реквизиты}}
<p>Связаться с нами: {{контакты}}.</p>
HTML;
}

function page_seed_privacy(): string
{
    return <<<'HTML'
<p>Редакция от 21.10.2026</p>
<h2>1. Общие положения</h2>
<p>1.1. Настоящая Политика определяет порядок и условия обработки персональных данных и меры по обеспечению их безопасности, которые применяет {{продавец}} (далее — «Оператор») при работе сайта {{сайт}} (далее — «Сайт»).</p>
<p>1.2. Политика разработана в соответствии с Федеральным законом от 27.07.2006 № 152-ФЗ «О персональных данных» (далее — «Закон») и применяется ко всем персональным данным, которые Оператор получает от пользователей Сайта.</p>
<p>1.3. Политика опубликована на Сайте в свободном доступе.</p>
<h2>2. Сведения об Операторе</h2>
{{оператор}}
<p>Обращения по вопросам обработки персональных данных принимаются {{контакты}}.</p>
<h2>3. Основные понятия</h2>
<p>3.1. Персональные данные — любая информация, относящаяся прямо или косвенно к определённому или определяемому физическому лицу (субъекту персональных данных).</p>
<p>3.2. Обработка персональных данных — любое действие или совокупность действий с персональными данными, совершаемых с использованием средств автоматизации или без них.</p>
<p>3.3. Пользователь — физическое лицо, использующее Сайт, в том числе представитель организации или индивидуальный предприниматель.</p>
<h2>4. Цели обработки, состав данных и правовые основания</h2>
<p><strong>4.1. Регистрация и ведение личного кабинета.</strong> Данные: адрес электронной почты, номер телефона, наименование организации; при заполнении профиля — фамилия, имя, отчество контактного лица, ИНН, город. Субъекты: пользователи, зарегистрировавшиеся на Сайте. Основание: согласие субъекта персональных данных. Срок обработки: до удаления учётной записи или отзыва согласия.</p>
<p><strong>4.2. Приём и обработка заявок, заключение и исполнение договора поставки.</strong> Данные: фамилия, имя, отчество контактного лица, номер телефона, адрес электронной почты, наименование организации или индивидуального предпринимателя, ИНН, город доставки, сведения, указанные в комментарии к заказу. Субъекты: представители покупателей, покупатели — индивидуальные предприниматели. Основания: согласие субъекта персональных данных; заключение и исполнение договора. Срок обработки: в течение срока исполнения договора, далее — в течение срока хранения бухгалтерских документов, установленного законодательством (5 лет).</p>
<p><strong>4.3. Подтверждение получения согласия на обработку персональных данных.</strong> Данные: дата и время дачи согласия, IP-адрес, сведения о браузере, данные, указанные в форме. Основание: обязанность Оператора доказать получение согласия (ч. 3 ст. 9 Закона). Срок обработки: в течение срока обработки персональных данных по пунктам 4.1–4.2 и 3 лет после его окончания.</p>
<p><strong>4.4. Обеспечение работы и безопасности Сайта.</strong> Данные: IP-адрес, сведения о браузере и устройстве, файлы cookie, необходимые для работы корзины и входа в личный кабинет. Основание: законный интерес Оператора в обеспечении работоспособности Сайта. Сервисы веб-аналитики на Сайте не используются.</p>
<p>4.5. Оператор не обрабатывает специальные категории персональных данных и биометрические персональные данные.</p>
<h2>5. Порядок и условия обработки</h2>
<p>5.1. Обработка осуществляется с использованием средств автоматизации и без них и включает сбор, запись, систематизацию, накопление, хранение, уточнение (обновление, изменение), извлечение, использование, передачу (предоставление, доступ), блокирование, удаление и уничтожение персональных данных.</p>
<p>5.2. Запись, систематизация, накопление, хранение, уточнение и извлечение персональных данных граждан Российской Федерации осуществляются с использованием баз данных, находящихся на территории Российской Федерации.</p>
<p>5.3. Оператор может поручить обработку персональных данных другому лицу (в том числе провайдеру хостинга) на основании договора с обязательным соблюдением этим лицом принципов и правил обработки, предусмотренных Законом.</p>
<p>5.4. Персональные данные передаются транспортным компаниям в объёме, необходимом для доставки заказа (наименование получателя, контактное лицо, телефон, город), а также государственным органам в случаях, предусмотренных законодательством.</p>
<p>5.5. Оператор не осуществляет трансграничную передачу персональных данных.</p>
<p>5.6. Персональные данные уничтожаются по достижении целей обработки, по истечении сроков обработки или при отзыве согласия, если иное не предусмотрено законодательством.</p>
<h2>6. Права субъекта персональных данных</h2>
<p>6.1. Субъект персональных данных вправе получать сведения об обработке своих персональных данных, требовать их уточнения, блокирования или уничтожения, если данные неполные, устаревшие, неточные или не являются необходимыми для заявленной цели, отозвать согласие на обработку, а также обжаловать действия Оператора в Роскомнадзоре или в суде.</p>
<p>6.2. Запросы и отзыв согласия направляются Оператору способами, указанными в разделе 2. Оператор отвечает на запрос в течение 10 (десяти) рабочих дней с момента его получения. При отзыве согласия Оператор прекращает обработку и уничтожает персональные данные в течение 30 (тридцати) дней, если иное не предусмотрено законодательством.</p>
<h2>7. Меры по обеспечению безопасности</h2>
<p>7.1. Оператор принимает правовые, организационные и технические меры для защиты персональных данных от неправомерного или случайного доступа, уничтожения, изменения, блокирования, копирования, распространения, в том числе:</p>
<ul>
<li>назначает лицо, ответственное за организацию обработки персональных данных;</li>
<li>предоставляет доступ к персональным данным только уполномоченным лицам с разграничением прав доступа;</li>
<li>ведёт журнал действий администраторов и журнал согласий;</li>
<li>использует защищённое соединение (HTTPS) при передаче данных на Сайте;</li>
<li>хранит пароли пользователей только в преобразованном (хешированном) виде.</li>
</ul>
<h2>8. Заключительные положения</h2>
<p>8.1. Оператор вправе вносить изменения в Политику. Новая редакция вступает в силу с момента её размещения на Сайте.</p>
<p>8.2. Согласие на обработку персональных данных оформляется отдельным документом «Согласие на обработку персональных данных», размещённым на Сайте.</p>
HTML;
}

function page_seed_consent(): string
{
    return <<<'HTML'
<p>Редакция от 21.10.2026</p>
<p>Отмечая на сайте {{сайт}} поле «Даю согласие на обработку персональных данных» при регистрации или оформлении заявки, я, действуя свободно, своей волей и в своём интересе, даю {{продавец}} (далее — «Оператор») согласие на обработку моих персональных данных на следующих условиях.</p>
<h2>1. Оператор</h2>
{{оператор}}
<h2>2. Перечень персональных данных</h2>
<p>Фамилия, имя, отчество; номер телефона; адрес электронной почты; наименование организации или индивидуального предпринимателя; ИНН; город доставки; сведения, которые я укажу в комментарии к заказу; а также IP-адрес и сведения о браузере, фиксируемые в момент дачи согласия.</p>
<h2>3. Цели обработки</h2>
<p>Регистрация и ведение личного кабинета на сайте; приём и обработка заявок; связь со мной по вопросам заказов; заключение и исполнение договора поставки, выставление счетов и организация доставки; подтверждение факта получения настоящего согласия.</p>
<h2>4. Действия с персональными данными</h2>
<p>Сбор, запись, систематизация, накопление, хранение, уточнение (обновление, изменение), извлечение, использование, передача (предоставление, доступ), блокирование, удаление, уничтожение — с использованием средств автоматизации и без них.</p>
<h2>5. Передача третьим лицам</h2>
<p>Я согласен(-на) на передачу моих персональных данных транспортным компаниям в объёме, необходимом для доставки заказа, а также лицам, которым Оператор поручил обработку персональных данных (в том числе провайдеру хостинга), при условии соблюдения ими конфиденциальности. Трансграничная передача персональных данных не осуществляется.</p>
<h2>6. Срок действия и отзыв согласия</h2>
<p>Согласие действует до достижения целей обработки, но не более 5 (пяти) лет с момента его дачи, либо до его отзыва. Я могу отозвать согласие, направив Оператору заявление {{контакты}}. В этом случае Оператор прекращает обработку и уничтожает персональные данные в течение 30 (тридцати) дней, если иное не предусмотрено законодательством.</p>
<h2>7. Прочее</h2>
<p>Я подтверждаю, что ознакомлен(-а) с Политикой обработки персональных данных, размещённой на сайте {{сайт}}.</p>
HTML;
}
