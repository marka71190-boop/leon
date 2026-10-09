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
                   'Демонстрационный заказ', 6000, 0, 'confirmed', 'Это пример заказа для демонстрации', date('Y-m-d H:i:s')]);
    $oid = (int)$pdo->lastInsertId();
    $st = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, size, price, qty) VALUES (?,?,?,?,?,?)');
    $st->execute([$oid, 2, 'Дюбель тарельчатый с пластиковым гвоздём', '10×120', 0, 4000]);
    $st->execute([$oid, 7, 'Скоба якорная для тёплого пола', '', 0, 2000]);
}

function page_seed_about(): string
{
    return <<<HTML
<p><strong>Leon_pro</strong> — производитель тарельчатых дюбелей, площадок под стяжку, якорных скоб и систем выравнивания плитки (СВП). Производство и склад находятся в Краснодаре.</p>
<h2>Производство</h2>
<p>[Расскажите о производстве: оборудование, объёмы, контроль качества.]</p>
<h2>История</h2>
<p>[Год основания, ключевые этапы развития компании.]</p>
<h2>Гарантии качества</h2>
<p>На продукцию есть сертификаты и паспорта качества — их можно скачать в разделе «Сертификаты» и в карточке каждого товара.</p>
HTML;
}

function page_seed_delivery(): string
{
    return <<<HTML
<h2>Как оформить заказ</h2>
<ol>
<li>Добавьте товары в корзину. Минимальный заказ — от 5 000 шт. по всей корзине.</li>
<li>Заполните заявку: название компании, ИНН, контактное лицо, телефон, email и город доставки.</li>
<li>Менеджер свяжется с вами, подтвердит состав заказа, согласует стоимость и транспортную компанию.</li>
<li>После оплаты по счёту заказ передаётся в транспортную компанию.</li>
</ol>
<h2>Доставка</h2>
<p>Отгрузка со склада в Краснодаре через транспортные компании: [перечислите ТК]. Стоимость доставки рассчитывается по тарифам ТК и оплачивается покупателем.</p>
<h2>Оплата</h2>
<p>Онлайн-оплаты на сайте нет. Оплата — безналичным переводом по счёту после согласования заказа.</p>
<h2>Реквизиты для оплаты</h2>
{{реквизиты}}
HTML;
}

function page_seed_oferta(): string
{
    return <<<HTML
<p><em>Черновик. Перед публикацией текст должен проверить юрист.</em></p>
<p>{{продавец}} (далее — «Продавец») предлагает юридическим лицам и индивидуальным предпринимателям (далее — «Покупатель») заключить договор поставки на условиях настоящей оферты.</p>
<h2>1. Предмет</h2>
<p>1.1. Продавец обязуется передать Покупателю товар (тарельчатые дюбели, площадки под стяжку, якорные скобы, СВП), а Покупатель — принять и оплатить его на условиях настоящей оферты.</p>
<h2>2. Условия работы с оптовыми покупателями</h2>
<p>2.1. Минимальный объём заказа — 5 000 шт. по всей заявке.</p>
<p>2.2. Цены указаны на сайте и могут быть изменены Продавцом до подтверждения заказа.</p>
<h2>3. Порядок оформления заказа</h2>
<p>3.1. Покупатель оформляет заявку на сайте, указывая реквизиты, контактные данные и город доставки.</p>
<p>3.2. Заявка не является оплатой. Менеджер Продавца связывается с Покупателем, подтверждает состав заказа, стоимость и способ доставки.</p>
<p>3.3. Договор считается заключённым с момента оплаты Покупателем счёта, выставленного Продавцом.</p>
<h2>4. Доставка</h2>
<p>4.1. Отгрузка производится со склада Продавца в г. Краснодаре через транспортные компании.</p>
<p>4.2. Все расходы на доставку (логистику) оплачивает Покупатель.</p>
<p>4.3. Риск случайной гибели товара переходит к Покупателю с момента передачи товара транспортной компании.</p>
<h2>5. Качество и документы</h2>
<p>5.1. Качество товара подтверждается сертификатами и паспортами качества.</p>
<h2>6. Реквизиты Продавца</h2>
{{реквизиты}}
HTML;
}

function page_seed_privacy(): string
{
    return <<<HTML
<p><em>Черновик. Перед публикацией текст должен проверить юрист.</em></p>
<p>Настоящая политика определяет порядок обработки персональных данных пользователей этого сайта в соответствии с Федеральным законом № 152-ФЗ «О персональных данных».</p>
<h2>Какие данные мы обрабатываем</h2>
<p>Имя контактного лица, телефон, email, название компании, ИНН, город доставки.</p>
<h2>Цели обработки</h2>
<p>Обработка заявок, связь с покупателем, исполнение договора поставки.</p>
<h2>Оператор</h2>
{{реквизиты}}
<p>Телефон: {{телефон}}</p>
HTML;
}
