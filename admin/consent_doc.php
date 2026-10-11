<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_admin();

// Документ-согласие для печати или сохранения в PDF: одна запись (?id=), все согласия клиента (?user=) или заказа (?order=)
$get = static fn(string $k): int => is_string($_GET[$k] ?? null) ? (int)$_GET[$k] : 0;
if ($get('id')) {
    $rows = q('SELECT * FROM consents WHERE id = ?', [$get('id')])->fetchAll();
} elseif ($get('user')) {
    $rows = q('SELECT * FROM consents WHERE user_id = ? ORDER BY id', [$get('user')])->fetchAll();
} elseif ($get('order')) {
    $rows = q('SELECT * FROM consents WHERE order_id = ? ORDER BY id', [$get('order')])->fetchAll();
} else {
    $rows = [];
}
if (!$rows) {
    redirect('admin/consents.php');
}

$kinds = consent_kinds();
$sources = consent_sources();
$titles = ['pd' => 'Согласие на обработку персональных данных', 'oferta' => 'Акцепт публичной оферты'];
$docs = [];
foreach ($rows as $r) {
    if ($r['doc_hash'] !== '' && !isset($docs[$r['doc_hash']])) {
        $docs[$r['doc_hash']] = q('SELECT * FROM consent_docs WHERE hash = ?', [$r['doc_hash']])->fetch() ?: null;
    }
}
$operator = array_filter([
    setting('legal_name'),
    setting('legal_inn') ? 'ИНН ' . setting('legal_inn') : '',
    setting('legal_ogrn') ? 'ОГРНИП ' . setting('legal_ogrn') : '',
    setting('legal_address'),
]);
$fileTitle = count($rows) === 1 ? 'Согласие № ' . $rows[0]['id'] : 'Согласия — ' . ($rows[0]['company'] ?: $rows[0]['email']);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($fileTitle) ?></title>
<style>
  :root { --ink: #17171A; --muted: #5B5F66; --line: #D9DBDF; --accent: #F07F1F; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #EDEEF0; color: var(--ink); font: 14px/1.55 'Golos Text', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; }
  .bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; padding: 12px 20px; background: var(--ink); color: #fff; }
  .bar a { color: #F59A3A; }
  .bar button { min-height: 40px; padding: 0 18px; border: 0; border-radius: 6px; background: var(--accent); color: var(--ink); font: inherit; font-weight: 700; cursor: pointer; }
  .sheet { width: 210mm; max-width: calc(100% - 24px); min-height: 297mm; margin: 20px auto; padding: 18mm 18mm 16mm; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.12); }
  .sheet + .sheet { margin-top: 24px; }
  .op { font-size: 12px; color: var(--muted); border-bottom: 1px solid var(--line); padding-bottom: 8px; margin-bottom: 18px; }
  .op b { color: var(--ink); }
  h1 { font-size: 20px; margin: 0 0 4px; }
  .no { color: var(--muted); margin: 0 0 18px; }
  h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .04em; margin: 22px 0 8px; }
  p { margin: 0 0 10px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { text-align: left; vertical-align: top; padding: 6px 8px; border: 1px solid var(--line); }
  th { width: 34%; font-weight: 600; background: #F6F6F7; }
  .mark { display: inline-flex; gap: 8px; align-items: center; padding: 8px 12px; border: 1px solid var(--ink); border-radius: 6px; font-weight: 600; margin: 4px 0 12px; }
  .mark i { width: 16px; height: 16px; border: 2px solid var(--ink); border-radius: 3px; display: inline-flex; align-items: center; justify-content: center; font-style: normal; font-size: 12px; line-height: 1; }
  .status-ok { color: #154D29; font-weight: 600; }
  .status-off { color: #A31515; font-weight: 600; }
  .appendix { margin-top: 26px; padding-top: 14px; border-top: 2px solid var(--ink); }
  .appendix .doc { font-size: 12.5px; }
  .appendix .doc h1, .appendix .doc h2, .appendix .doc h3 { font-size: 14px; text-transform: none; letter-spacing: 0; margin: 14px 0 6px; }
  .appendix .doc dl { display: grid; grid-template-columns: 150px 1fr; gap: 2px 12px; margin: 6px 0 10px; }
  .appendix .doc dt { color: var(--muted); }
  .appendix .doc dd { margin: 0; }
  .appendix .doc .req-name { grid-column: 1 / -1; font-weight: 700; color: var(--ink); }
  .foot { margin-top: 22px; font-size: 11.5px; color: var(--muted); border-top: 1px solid var(--line); padding-top: 8px; }
  .ua { font-size: 12px; color: var(--muted); word-break: break-word; }
  @page { size: A4; margin: 15mm 16mm; }
  @media print {
    body { background: #fff; }
    .bar { display: none; }
    .sheet { margin: 0; padding: 0; box-shadow: none; width: auto; max-width: none; min-height: auto; page-break-after: always; }
    table, .mark { page-break-inside: avoid; }
    .sheet:last-child { page-break-after: auto; }
  }
  @media (max-width: 600px) { .sheet { padding: 20px 16px; } th { width: 42%; } }
</style>
</head>
<body>
<div class="bar">
  <span><b><?= count($rows) === 1 ? 'Документ-согласие' : 'Документы-согласия: ' . count($rows) ?></b> · Чтобы сохранить в PDF, нажмите «Печать» и выберите «Сохранить как PDF»</span>
  <span style="display:flex; gap:14px; align-items:center"><a href="<?= url('admin/consents.php') ?>">← Журнал согласий</a><button type="button" onclick="window.print()">Печать / PDF</button></span>
</div>

<?php foreach ($rows as $r):
    $doc = $docs[$r['doc_hash']] ?? null;
    $site = $r['site'] ?: site_base_url();
    $where = $r['source'] === 'order' ? 'форма оформления заявки' . ($r['order_id'] ? ' № ' . (int)$r['order_id'] : '') : 'форма регистрации';
    $docTitle = $doc['title'] ?? ($kinds[$r['kind']][0] ?? '');
?>
<section class="sheet">
  <div class="op">Оператор: <b><?= e(implode(', ', $operator) ?: setting('site_name')) ?></b></div>
  <h1><?= e($titles[$r['kind']] ?? ($kinds[$r['kind']][0] ?? 'Согласие')) ?></h1>
  <p class="no">Запись № <?= (int)$r['id'] ?> в журнале согласий сайта <?= e($site) ?></p>

  <p>Лицо, указанное ниже, <?= date('d.m.Y', strtotime($r['created_at'])) ?> в <?= date('H:i:s', strtotime($r['created_at'])) ?> (время московское) в электронной форме на сайте <?= e($site) ?> (<?= e($where) ?>) поставило отметку:</p>
  <div class="mark"><i>✓</i> <?= e($r['checkbox_text']) ?></div>
  <?php if ($r['kind'] === 'pd'): ?>
    <p>Тем самым выражено согласие на обработку персональных данных оператором <?= e(setting('legal_name') ?: setting('site_name')) ?> на условиях документа «<?= e($docTitle) ?>» в редакции, действовавшей в момент согласия. Текст этой редакции приведён в приложении.</p>
  <?php else: ?>
    <p>Тем самым приняты условия документа «<?= e($docTitle) ?>» в редакции, действовавшей в момент согласия. Текст этой редакции приведён в приложении.</p>
  <?php endif; ?>

  <h2>Кто дал согласие</h2>
  <table><tbody>
    <tr><th>Контактное лицо</th><td><?= e($r['contact'] ?: '—') ?></td></tr>
    <tr><th>Организация</th><td><?= e($r['company'] ?: '—') ?></td></tr>
    <tr><th>Email</th><td><?= e($r['email'] ?: '—') ?></td></tr>
    <tr><th>Телефон</th><td><?= e($r['phone'] ?: '—') ?></td></tr>
    <?php if ($r['user_id']): ?><tr><th>Аккаунт на сайте</th><td>№ <?= (int)$r['user_id'] ?></td></tr><?php endif; ?>
  </tbody></table>

  <h2>Как дано согласие</h2>
  <table><tbody>
    <tr><th>Способ</th><td>Отметка в форме на сайте (<?= e($where) ?>)</td></tr>
    <tr><th>Дата и время</th><td><?= date('d.m.Y H:i:s', strtotime($r['created_at'])) ?> (МСК)</td></tr>
    <tr><th>IP-адрес</th><td><?= e($r['ip'] ?: '—') ?></td></tr>
    <tr><th>Браузер и устройство</th><td class="ua"><?= e($r['user_agent'] ?: '—') ?></td></tr>
    <tr><th>Редакция документа</th><td>«<?= e($docTitle) ?>», редакция <?= e($r['doc_hash'] ?: '—') ?><?= $doc ? ', впервые принята ' . date('d.m.Y', strtotime($doc['created_at'])) : '' ?></td></tr>
    <tr><th>Статус</th><td><?= $r['revoked_at']
        ? '<span class="status-off">Отозвано ' . date('d.m.Y H:i', strtotime($r['revoked_at'])) . '</span>' . ($r['revoked_note'] !== '' ? ' — ' . e($r['revoked_note']) : '')
        : '<span class="status-ok">Действует</span>' ?></td></tr>
  </tbody></table>

  <div class="appendix">
    <h2 style="margin-top:0">Приложение. <?= e($docTitle) ?> (редакция <?= e($r['doc_hash'] ?: '—') ?>)</h2>
    <div class="doc"><?= $doc && $doc['content'] !== '' ? $doc['content'] /* HTML страницы, сохранённый в момент согласия */ : '<p>Текст документа не сохранён.</p>' ?></div>
  </div>

  <div class="foot">Документ сформирован <?= date('d.m.Y H:i') ?> из журнала согласий сайта <?= e(setting('site_name')) ?>. Данные записаны автоматически в момент согласия и не редактируются.</div>
</section>
<?php endforeach; ?>
</body>
</html>
