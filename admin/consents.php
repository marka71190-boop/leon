<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$kinds = consent_kinds();
$sources = consent_sources();

// Отметка об отзыве согласия (клиент написал или позвонил с просьбой прекратить обработку данных)
if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    if (post('action') === 'revoke') {
        q('UPDATE consents SET revoked_at = ?, revoked_note = ? WHERE id = ? AND revoked_at IS NULL', [date('Y-m-d H:i:s'), post('note'), $id]);
        flash('Отзыв согласия отмечен. Запись осталась в журнале.');
    }
    if (post('action') === 'unrevoke') {
        q("UPDATE consents SET revoked_at = NULL, revoked_note = '' WHERE id = ?", [$id]);
        flash('Отметка об отзыве снята.');
    }
    back('admin/consents.php');
}

// Текст документа в той редакции, которую принял человек
if (isset($_GET['doc'])) {
    $doc = q('SELECT * FROM consent_docs WHERE hash = ?', [is_string($_GET['doc']) ? $_GET['doc'] : ''])->fetch();
    if (!$doc) redirect('admin/consents.php');
    $cnt = (int)q('SELECT COUNT(*) FROM consents WHERE doc_hash = ?', [$doc['hash']])->fetchColumn();
    admin_header('Редакция документа', 'consents');
    ?>
    <div class="a-head"><h1><?= e($doc['title']) ?></h1><a href="<?= url('admin/consents.php') ?>">← Журнал согласий</a></div>
    <p class="a-muted">Редакция <b><?= e($doc['hash']) ?></b> · впервые принята <?= date('d.m.Y H:i', strtotime($doc['created_at'])) ?> · согласий с этой редакцией: <?= $cnt ?></p>
    <div class="a-card a-doc"><?= $doc['content'] ?: '<p class="a-muted">Текст страницы был пустым.</p>' /* HTML из админки, сохранён при согласии */ ?></div>
    <?php
    admin_footer();
    exit;
}

// Фильтры
$get = static fn(string $k): string => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
$search = $get('q');
$source = array_key_exists($get('source'), $sources) ? $get('source') : '';
$kind = array_key_exists($get('kind'), $kinds) ? $get('kind') : '';
$userId = (int)$get('user');
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(c.company LIKE ? OR c.contact LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.ip LIKE ? OR CAST(c.order_id AS TEXT) = ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%", "%$search%", $search);
}
if ($source !== '') { $where[] = 'c.source = ?'; $params[] = $source; }
if ($kind !== '') { $where[] = 'c.kind = ?'; $params[] = $kind; }
if ($userId) { $where[] = 'c.user_id = ?'; $params[] = $userId; }
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$base = 'FROM consents c LEFT JOIN users u ON u.id = c.user_id LEFT JOIN orders o ON o.id = c.order_id' . $sqlWhere;

// Выгрузка в Excel (CSV)
if (isset($_GET['export'])) {
    $rows = q('SELECT c.* ' . $base . ' ORDER BY c.id DESC', $params)->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="soglasiya-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // чтобы Excel понял кириллицу
    fputcsv($out, ['№', 'Дата и время', 'Где', 'Заявка №', 'Согласие', 'Текст галочки', 'Компания', 'Контактное лицо', 'Email', 'Телефон', 'IP', 'Браузер', 'Редакция документа', 'Отозвано', 'Комментарий к отзыву'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], date('d.m.Y H:i:s', strtotime($r['created_at'])), $sources[$r['source']] ?? $r['source'], $r['order_id'] ?: '',
            $kinds[$r['kind']][0] ?? $r['kind'], $r['checkbox_text'], $r['company'], $r['contact'], $r['email'], $r['phone'],
            $r['ip'], $r['user_agent'], $r['doc_hash'], $r['revoked_at'] ? date('d.m.Y H:i', strtotime($r['revoked_at'])) : '', $r['revoked_note'],
        ], ';');
    }
    fclose($out);
    exit;
}

$perPage = 50;
$total = (int)q('SELECT COUNT(*) ' . $base, $params)->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)$get('page')));
$rows = q('SELECT c.*, u.id AS u_exists, o.id AS o_exists ' . $base . ' ORDER BY c.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params)->fetchAll();

$qs = static function (array $over = []) use ($search, $source, $kind, $userId): string {
    $p = array_filter(array_merge(['q' => $search, 'source' => $source, 'kind' => $kind, 'user' => $userId ?: ''], $over), static fn($v) => $v !== '' && $v !== null);
    return url('admin/consents.php' . ($p ? '?' . http_build_query($p) : ''));
};

admin_header('Согласия', 'consents');
?>
<div class="a-head"><h1>Журнал согласий</h1><a class="a-btn a-btn-out a-btn-sm" href="<?= e($qs(['export' => 1])) ?>">Скачать для Excel</a></div>
<p class="a-muted" style="margin-top:-8px">Каждая галочка при регистрации и при отправке заявки: кто, когда, с какого IP и какую редакцию документа принял. Записи нельзя удалить — это доказательство согласия по 152-ФЗ. Если человек отозвал согласие, нажмите «Отметить отзыв» в его строке.</p>
<div class="a-filters">
  <a class="a-chip <?= $source === '' ? 'on' : '' ?>" href="<?= e($qs(['source' => ''])) ?>">Все</a>
  <?php foreach ($sources as $k => $l): ?><a class="a-chip <?= $source === $k ? 'on' : '' ?>" href="<?= e($qs(['source' => $k])) ?>"><?= e($l) ?></a><?php endforeach; ?>
  <span class="a-muted">·</span>
  <?php foreach ($kinds as $k => $d): ?><a class="a-chip <?= $kind === $k ? 'on' : '' ?>" href="<?= e($qs(['kind' => $kind === $k ? '' : $k])) ?>"><?= e($d[0]) ?></a><?php endforeach; ?>
  <form method="get" style="margin-left:auto; display:flex; gap:6px">
    <?php if ($source): ?><input type="hidden" name="source" value="<?= e($source) ?>"><?php endif; ?>
    <?php if ($kind): ?><input type="hidden" name="kind" value="<?= e($kind) ?>"><?php endif; ?>
    <input class="a-input" name="q" value="<?= e($search) ?>" placeholder="Компания, email, телефон, № заявки" style="width:280px">
    <button class="a-btn a-btn-dark a-btn-sm" type="submit">Найти</button>
  </form>
</div>
<?php if ($userId): ?><p>Показаны согласия одного клиента. <a href="<?= e($qs(['user' => ''])) ?>">Показать все</a></p><?php endif; ?>
<?php if (!$rows): ?>
  <div class="a-card a-muted">Согласий пока нет. Они появятся, когда клиенты начнут регистрироваться и отправлять заявки.</div>
<?php else: ?>
<div class="a-table-wrap"><table class="a-table">
  <thead><tr><th>Дата и время</th><th>Где</th><th>Согласие</th><th>Кто</th><th>IP</th><th>Документ</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr<?= $r['revoked_at'] ? ' style="opacity:.6"' : '' ?>>
      <td style="white-space:nowrap"><?= date('d.m.Y H:i:s', strtotime($r['created_at'])) ?></td>
      <td><?= e($sources[$r['source']] ?? $r['source']) ?>
        <?php if ($r['order_id']): ?><div style="font-size:13px"><?= $r['o_exists'] ? '<a href="' . url('admin/orders.php?id=' . (int)$r['order_id']) . '">заявка № ' . (int)$r['order_id'] . '</a>' : 'заявка № ' . (int)$r['order_id'] . ' (удалена)' ?></div><?php endif; ?>
      </td>
      <td><b><?= e($kinds[$r['kind']][0] ?? $r['kind']) ?></b><div class="a-muted" style="font-size:13px">«<?= e($r['checkbox_text']) ?>» ✓</div>
        <?php if ($r['revoked_at']): ?><div style="font-size:13px; color:#B42318">Отозвано <?= date('d.m.Y', strtotime($r['revoked_at'])) ?><?= $r['revoked_note'] !== '' ? ': ' . e($r['revoked_note']) : '' ?></div><?php endif; ?>
      </td>
      <td>
        <?php $name = $r['company'] ?: ($r['contact'] ?: '—'); ?>
        <?= $r['user_id'] && $r['u_exists'] ? '<a href="' . url('admin/clients.php?id=' . (int)$r['user_id']) . '">' . e($name) . '</a>' : e($name) ?>
        <div class="a-muted" style="font-size:13px"><?= e(trim($r['contact'] !== $name ? $r['contact'] : '')) ?> <?= e($r['email']) ?> <?= e($r['phone']) ?></div>
      </td>
      <td style="font-size:13px" title="<?= e($r['user_agent']) ?>"><?= e($r['ip'] ?: '—') ?></td>
      <td style="font-size:13px"><?php if ($r['doc_hash']): ?><a href="<?= url('admin/consents.php?doc=' . urlencode($r['doc_hash'])) ?>">редакция <?= e(substr($r['doc_hash'], 0, 6)) ?></a><?php endif; ?></td>
      <td>
        <?php if (!$r['revoked_at']): ?>
          <details><summary class="a-btn a-btn-out a-btn-sm">Отметить отзыв</summary>
            <form method="post" style="display:flex; gap:6px; margin-top:6px; min-width:260px"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input class="a-input" name="note" placeholder="Как и когда отозвал" style="flex:1"><button class="a-btn a-btn-dark a-btn-sm" type="submit">Отметить</button></form>
          </details>
        <?php else: ?>
          <form method="post" data-confirm="Снять отметку об отзыве?"><?= csrf_field() ?><input type="hidden" name="action" value="unrevoke"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="a-btn a-btn-out a-btn-sm" type="submit">Снять отзыв</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($pages > 1): ?>
  <div class="a-filters" style="margin-top:14px">
    <?php for ($i = 1; $i <= $pages; $i++): ?><a class="a-chip <?= $i === $page ? 'on' : '' ?>" href="<?= e($qs(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?>
    <span class="a-muted">Всего записей: <?= $total ?></span>
  </div>
<?php else: ?>
  <p class="a-muted">Всего записей: <?= $total ?></p>
<?php endif; ?>
<?php endif; ?>
<?php admin_footer();
