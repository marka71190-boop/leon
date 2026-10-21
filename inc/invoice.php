<?php
declare(strict_types=1);

// Счёт на оплату по заказу: номер счёта = номер заказа, дата — день первого выставления.
// Страница открывается в браузере; кнопка «Скачать PDF» сохраняет файл, «Печать» — печатает.

/** Сумма прописью: 12345.6 → «Двенадцать тысяч триста сорок пять рублей 60 копеек» */
function money_in_words(float $sum): string
{
    $rub = (int)floor(round($sum, 2));
    $kop = (int)round((round($sum, 2) - $rub) * 100);
    $ones = [['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'],
             ['', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять']];
    $teens = ['десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать'];
    $tens = ['', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто'];
    $hundreds = ['', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот'];
    // [формы слова, род: 0 — мужской, 1 — женский]
    $units = [[['рубль', 'рубля', 'рублей'], 0], [['тысяча', 'тысячи', 'тысяч'], 1], [['миллион', 'миллиона', 'миллионов'], 0], [['миллиард', 'миллиарда', 'миллиардов'], 0]];
    $plural = static function (int $n, array $f): string {
        $n %= 100;
        if ($n >= 11 && $n <= 19) return $f[2];
        $n %= 10;
        return $n === 1 ? $f[0] : ($n >= 2 && $n <= 4 ? $f[1] : $f[2]);
    };
    $words = [];
    if ($rub === 0) {
        $words[] = 'ноль';
    }
    $groups = [];
    for ($n = $rub; $n > 0; $n = intdiv($n, 1000)) {
        $groups[] = $n % 1000;
    }
    for ($i = count($groups) - 1; $i >= 0; $i--) {
        $g = $groups[$i];
        if ($g === 0) continue;
        $w = [];
        $w[] = $hundreds[intdiv($g, 100)];
        $t = $g % 100;
        if ($t >= 10 && $t <= 19) {
            $w[] = $teens[$t - 10];
        } else {
            $w[] = $tens[intdiv($t, 10)];
            $w[] = $ones[$units[$i][1] ?? 0][$t % 10];
        }
        if ($i > 0) {
            $w[] = $plural($g, $units[$i][0]);
        }
        $words[] = implode(' ', array_filter($w));
    }
    $text = implode(' ', array_filter($words)) . ' ' . $plural($rub, $units[0][0]) . ' ' . sprintf('%02d', $kop) . ' ' . $plural($kop, ['копейка', 'копейки', 'копеек']);
    return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
}

/** «ИП Леонов Александр Геннадьевич» → «Леонов А. Г.» */
function invoice_signer(): string
{
    if (setting('invoice_signer') !== '') {
        return setting('invoice_signer');
    }
    $name = trim(preg_replace('~^(ИП|Индивидуальный предприниматель)\s+~u', '', setting('legal_name')));
    $parts = preg_split('~\s+~u', $name);
    if (count($parts) >= 2) {
        return $parts[0] . ' ' . implode(' ', array_map(fn($p) => mb_substr($p, 0, 1) . '.', array_slice($parts, 1)));
    }
    return $name;
}

/** Строки счёта: товары с фасовкой — в упаковках по цене упаковки, остальные — в штуках */
function invoice_lines(array $items): array
{
    $lines = [];
    foreach ($items as $it) {
        $qty = (int)$it['qty'];
        $pack = (int)($it['pack_qty'] ?? 0);
        $sum = round((float)$it['price'] * $qty, 2);
        if ($pack > 0 && $qty % $pack === 0) {
            $lines[] = ['name' => trim($it['name'] . ' ' . $it['size']) . ' (упаковка ' . fmt_qty($pack) . ' шт.)',
                        'qty' => intdiv($qty, $pack), 'unit' => 'уп.', 'price' => round((float)$it['price'] * $pack, 2), 'sum' => $sum];
        } else {
            $lines[] = ['name' => trim($it['name'] . ' ' . $it['size']), 'qty' => $qty, 'unit' => 'шт.', 'price' => (float)$it['price'], 'sum' => $sum];
        }
    }
    return $lines;
}

/** Деньги для счёта: 1 234,50 */
function inv_money(float $n): string
{
    return number_format($n, 2, ',', ' ');
}

/** Вывод страницы счёта. $backUrl — куда ведёт ссылка «Назад» */
function render_invoice(array $o, array $items, string $backUrl, string $backLabel): void
{
    $lines = invoice_lines($items);
    $total = array_sum(array_column($lines, 'sum'));
    $noPrice = array_filter($lines, fn($l) => $l['price'] <= 0);
    $date = $o['invoice_date'] ?: date('Y-m-d');
    $months = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $ts = strtotime($date);
    $dateText = date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts) . ' г.';
    $seller = setting('legal_name');
    $sellerFull = implode(', ', array_filter([$seller, setting('legal_inn') ? 'ИНН ' . setting('legal_inn') : '', setting('legal_address'), setting('phone') ? 'тел. ' . setting('phone') : '']));
    $buyerFull = implode(', ', array_filter([$o['company'], $o['inn'] ? 'ИНН ' . $o['inn'] : '', $o['city'] ? 'г. ' . preg_replace('~^г\.?\s*~u', '', $o['city']) : '', $o['phone'] ? 'тел. ' . $o['phone'] : '']));
    $site = preg_replace('~^https?://~', '', seo_base());
    $file = 'Счёт-' . (int)$o['id'] . '-от-' . date('d.m.Y', $ts) . '.pdf';
    ?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Счёт № <?= (int)$o['id'] ?> от <?= e(date('d.m.Y', $ts)) ?></title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; background: #EDEEF0; color: #000; font: 12px/1.35 Arial, 'Helvetica Neue', sans-serif; }
  .bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; padding: 12px 20px; background: #17171A; color: #fff; font-size: 14px; }
  .bar a { color: #F59A3A; }
  .bar button { min-height: 40px; padding: 0 16px; border: 0; border-radius: 6px; font: inherit; font-weight: 700; cursor: pointer; }
  .bar .pdf { background: #F07F1F; color: #17171A; }
  .bar .print { background: #fff; color: #17171A; }
  .warn { max-width: 210mm; margin: 14px auto 0; padding: 10px 14px; background: #FDECEC; color: #8A1C1C; border: 1px solid #F3C0C0; border-radius: 8px; font-size: 14px; }
  .sheet { width: 210mm; max-width: calc(100% - 16px); margin: 16px auto; padding: 12mm 12mm 14mm; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.12); }
  table { width: 100%; border-collapse: collapse; }
  .bank td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
  .bank .lbl { font-size: 10px; }
  h1 { font-size: 17px; margin: 18px 0 6px; padding-bottom: 6px; border-bottom: 2px solid #000; }
  .parties td { padding: 4px 0; vertical-align: top; }
  .parties td:first-child { width: 90px; }
  .items { margin-top: 10px; }
  .items th, .items td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
  .items th { text-align: center; font-weight: 700; }
  .items .n, .items .q, .items .u { text-align: center; white-space: nowrap; }
  .items .m { text-align: right; white-space: nowrap; }
  .totals { margin-top: 6px; }
  .totals td { padding: 2px 5px; text-align: right; font-weight: 700; }
  .totals td:last-child { width: 110px; white-space: nowrap; }
  .words { margin-top: 10px; }
  .words b { display: block; }
  .terms { margin-top: 10px; font-size: 11px; }
  .sign { margin-top: 18px; padding-top: 10px; border-top: 2px solid #000; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
  .sign .line { flex: 1 1 140px; border-bottom: 1px solid #000; height: 22px; }
  .muted { color: #555; }
  @page { size: A4; margin: 10mm; }
  @media print {
    body { background: #fff; }
    .bar, .warn { display: none; }
    .sheet { margin: 0; padding: 0; box-shadow: none; width: auto; max-width: none; }
  }
  @media (max-width: 600px) { .sheet { padding: 14px 10px; } }
</style>
</head>
<body>
<div class="bar">
  <span><a href="<?= e($backUrl) ?>">← <?= e($backLabel) ?></a></span>
  <span style="display:flex; gap:8px; flex-wrap:wrap">
    <button type="button" class="pdf" id="pdf-btn">Скачать PDF</button>
    <button type="button" class="print" onclick="window.print()">Печать</button>
  </span>
</div>
<?php if ($noPrice): ?><div class="warn">В заказе есть позиции без цены. Укажите цену в карточке заказа, иначе сумма счёта будет неполной.</div><?php endif; ?>

<div class="sheet" id="invoice">
  <table class="bank">
    <tr><td colspan="2" rowspan="2" style="width:58%"><?= e(setting('bank_name')) ?><div class="lbl">Банк получателя</div></td><td style="width:9%">БИК</td><td><?= e(setting('bank_bik')) ?></td></tr>
    <tr><td>Сч. №</td><td><?= e(setting('bank_corr')) ?></td></tr>
    <tr><td style="width:29%">ИНН <?= e(setting('legal_inn')) ?></td><td>КПП —</td><td rowspan="2">Сч. №</td><td rowspan="2"><?= e(setting('bank_account')) ?></td></tr>
    <tr><td colspan="2"><?= e($seller) ?><div class="lbl">Получатель</div></td></tr>
  </table>

  <h1>Счёт на оплату № <?= (int)$o['id'] ?> от <?= e($dateText) ?></h1>
  <table class="parties">
    <tr><td>Поставщик:</td><td><b><?= e($sellerFull) ?></b></td></tr>
    <tr><td>Покупатель:</td><td><b><?= e($buyerFull) ?></b></td></tr>
    <tr><td>Основание:</td><td>Заказ № <?= (int)$o['id'] ?> на сайте <?= e($site) ?>; публичная оферта (договор поставки)</td></tr>
  </table>

  <table class="items">
    <thead><tr><th>№</th><th>Товар</th><th>Кол-во</th><th>Ед.</th><th>Цена, ₽</th><th>Сумма, ₽</th></tr></thead>
    <tbody>
    <?php foreach ($lines as $i => $l): ?>
      <tr><td class="n"><?= $i + 1 ?></td><td><?= e($l['name']) ?></td><td class="q"><?= fmt_qty($l['qty']) ?></td><td class="u"><?= e($l['unit']) ?></td><td class="m"><?= $l['price'] > 0 ? inv_money($l['price']) : '—' ?></td><td class="m"><?= $l['sum'] > 0 ? inv_money($l['sum']) : '—' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <table class="totals">
    <tr><td>Итого:</td><td><?= inv_money($total) ?></td></tr>
    <?php $vat = setting('invoice_vat') ?: 'Без НДС'; $rate = preg_match('~(\d+(?:[.,]\d+)?)\s*%~u', $vat, $vm) ? (float)str_replace(',', '.', $vm[1]) : 0; ?>
    <tr><td><?= e($vat) ?>:</td><td><?= $rate > 0 && mb_stripos($vat, 'без') === false ? inv_money(round($total * $rate / (100 + $rate), 2)) : '—' ?></td></tr>
    <tr><td>Всего к оплате:</td><td><?= inv_money($total) ?></td></tr>
  </table>
  <div class="words">Всего наименований <?= count($lines) ?>, на сумму <?= inv_money($total) ?> руб.<b><?= e(money_in_words($total)) ?></b></div>
  <div class="terms">
    <p style="margin:0 0 4px">Счёт действителен к оплате в течение <?= max(1, (int)setting('invoice_days')) ?> банковских дней. В назначении платежа укажите номер счёта.</p>
    <?= nl2br(e(setting('invoice_terms'))) ?>
  </div>
  <div class="sign"><b>Индивидуальный предприниматель</b><span class="line"></span><span><?= e(invoice_signer()) ?></span></div>
</div>

<script src="<?= asset('assets/vendor/html2pdf.bundle.min.js') ?>"></script>
<script>
  document.getElementById('pdf-btn').addEventListener('click', function () {
    var btn = this;
    if (typeof html2pdf === 'undefined') { window.print(); return; }
    btn.disabled = true; btn.textContent = 'Готовим PDF…';
    html2pdf().set({
      margin: 0, filename: <?= json_encode($file, JSON_UNESCAPED_UNICODE) ?>,
      image: { type: 'jpeg', quality: 0.95 }, html2canvas: { scale: 2, backgroundColor: '#ffffff' },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    }).from(document.getElementById('invoice')).save().then(function () {
      btn.disabled = false; btn.textContent = 'Скачать PDF';
    }).catch(function () { btn.disabled = false; btn.textContent = 'Скачать PDF'; window.print(); });
  });
</script>
</body>
</html>
    <?php
}
