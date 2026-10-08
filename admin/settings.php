<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$schema = settings_schema();

if (is_post() && (in_array(post('action'), ['tg_test', 'tg_find'], true) || post('chat_add') !== '')) {
    csrf_check();
    $action = post('chat_add') !== '' ? 'tg_add' : post('action');
    if ($action === 'tg_test') {
        $errs = tg_notify('<b>Проверка связи</b>' . "\n" . 'Бот сайта ' . tg_h(setting('site_name')) . ' подключён. Сюда будут приходить новые заявки.');
        $errs ? flash('Не удалось отправить: ' . implode('; ', $errs), 'error') : flash('Тестовое сообщение отправлено — проверьте Telegram.');
    }
    if ($action === 'tg_find') {
        $r = tg_api('getUpdates', ['limit' => 100]);
        if (empty($r['ok'])) {
            flash('Telegram ответил ошибкой: ' . ($r['description'] ?? 'неизвестно') . '. Проверьте токен.', 'error');
        } else {
            $found = [];
            foreach ($r['result'] as $u) {
                $chat = $u['message']['chat'] ?? $u['my_chat_member']['chat'] ?? $u['channel_post']['chat'] ?? null;
                if ($chat) {
                    $name = $chat['title'] ?? trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')) ?: ($chat['username'] ?? '');
                    $found[(string)$chat['id']] = ['id' => (string)$chat['id'], 'name' => $name, 'type' => $chat['type'] ?? ''];
                }
            }
            $_SESSION['tg_found'] = array_values($found);
            $found ? flash('Найдено чатов: ' . count($found) . '. Добавьте нужный в список ниже.')
                   : flash('Чатов не найдено. Напишите боту /start (или добавьте его в группу и напишите там сообщение), затем нажмите «Найти ID чата» ещё раз.', 'info');
        }
    }
    if ($action === 'tg_add') {
        $id = preg_replace('~[^0-9-]~', '', post('chat_add'));
        $list = array_filter(array_map('trim', explode(',', setting('tg_chats'))));
        if ($id !== '' && !in_array($id, $list, true)) {
            $list[] = $id;
            setting_save('tg_chats', implode(', ', $list));
        }
        unset($_SESSION['tg_found']);
        flash('Чат добавлен. Нажмите «Отправить тест», чтобы проверить.');
    }
    redirect('admin/settings.php#notify');
}

if (is_post()) {
    csrf_check();
    try {
        foreach ($schema as $key => [$group, $label, $type]) {
            switch ($type) {
                case 'image':
                    if ($new = handle_upload($key, ['jpg', 'png', 'webp', 'svg'], 'site')) {
                        delete_upload(setting($key));
                        setting_save($key, $new);
                    } elseif (post($key . '_remove') === '1') {
                        delete_upload(setting($key));
                        setting_save($key, '');
                    }
                    break;
                case 'bool':
                    setting_save($key, post($key) === '1' ? '1' : '0');
                    break;
                case 'int':
                    setting_save($key, (string)max(0, (int)post($key)));
                    break;
                case 'color':
                    $c = post($key);
                    setting_save($key, preg_match('~^#[0-9a-f]{6}$~i', $c) ? $c : '#F07F1F');
                    break;
                case 'code':
                    setting_save($key, trim((string)($_POST[$key] ?? '')));
                    break;
                case 'secret':
                    if (post($key) !== '') {
                        setting_save($key, post($key));
                    } elseif (post($key . '_clear') === '1') {
                        setting_save($key, '');
                    }
                    break;
                default:
                    setting_save($key, post($key));
            }
        }
        flash('Настройки сохранены.');
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'error');
    }
    redirect('admin/settings.php');
}

$groups = [];
foreach ($schema as $key => $def) {
    $groups[$def[0]][$key] = $def;
}

admin_header('Настройки сайта', 'settings');
?>
<h1>Настройки сайта</h1>
<div class="a-filters">
  <?php foreach (array_keys($groups) as $g): ?><a class="a-chip" href="#g-<?= e($g) ?>"><?= e($g) ?></a><?php endforeach; ?>
</div>
<form method="post" enctype="multipart/form-data" class="a-form">
  <?= csrf_field() ?>
  <?php foreach ($groups as $g => $fields): ?>
    <div class="a-card" id="<?= $g === 'Уведомления' ? 'notify' : 'g-' . e($g) ?>">
      <h2><?= e($g) ?></h2>
      <div class="a-grid">
      <?php foreach ($fields as $key => [, $label, $type, , $hint]):
          $val = setting($key);
          switch ($type) {
              case 'textarea': echo '<div class="a-field-wide">' . a_textarea($key, $label, $val, $hint, false, 3) . '</div>'; break;
              case 'code': echo '<label class="a-field a-field-wide"><span>' . e($label) . '</span><textarea class="mono" name="' . e($key) . '" rows="4">' . e($val) . '</textarea><small>' . e($hint) . '</small></label>'; break;
              case 'image': echo a_image($key, $label, $val, $hint); break;
              case 'bool': echo '<div class="a-field"><span>' . e($label) . '</span>' . a_check($key, 'Включено', $val === '1') . '<small>' . e($hint) . '</small></div>'; break;
              case 'int': echo a_input($key, $label, $val, 'number', $hint, ['min' => 0]); break;
              case 'color': echo a_input($key, $label, $val, 'color', $hint); break;
              case 'secret': echo a_input($key, $label, '', 'password', $val !== '' ? 'Токен сохранён. Оставьте поле пустым, чтобы не менять' : $hint, ['autocomplete' => 'off', 'placeholder' => $val !== '' ? '••••••••' : '1234567890:AA...']); break;
              default: echo a_input($key, $label, $val, 'text', $hint);
          }
      endforeach; ?>
      </div>
      <?php if ($g === 'Уведомления'): ?>
        <div class="a-tg">
          <p style="margin:0 0 8px"><b>Как подключить Telegram:</b></p>
          <ol style="margin:0 0 12px; padding-left:20px">
            <li>В Telegram откройте <a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a>, отправьте <code>/newbot</code> и придумайте имя боту.</li>
            <li>Скопируйте токен, который пришлёт BotFather, в поле «Токен Telegram-бота» и нажмите «Сохранить настройки».</li>
            <li>Напишите своему боту <code>/start</code>. Чтобы заявки видели несколько менеджеров, создайте группу, добавьте туда бота и напишите в группе любое сообщение.</li>
            <li>Нажмите «Найти ID чата», добавьте нужный чат и отправьте тест.</li>
          </ol>
          <div class="a-actions" style="justify-content:flex-start">
            <button class="a-btn a-btn-dark a-btn-sm" type="submit" form="tg-form" name="action" value="tg_find">Найти ID чата</button>
            <button class="a-btn a-btn-out a-btn-sm" type="submit" form="tg-form" name="action" value="tg_test">Отправить тест</button>
          </div>
          <?php if (!empty($_SESSION['tg_found'])): ?>
            <div class="a-table-wrap" style="margin-top:12px"><table class="a-table"><tbody>
            <?php foreach ($_SESSION['tg_found'] as $c): ?>
              <tr><td><b><?= e($c['name'] ?: 'Без названия') ?></b> <span class="a-muted"><?= $c['type'] === 'private' ? 'личный чат' : 'группа' ?></span></td><td><code><?= e($c['id']) ?></code></td>
                <td class="num"><button class="a-btn a-btn-sm" type="submit" form="tg-form" name="chat_add" value="<?= e($c['id']) ?>">Добавить</button></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <div class="a-save"><button class="a-btn" type="submit">Сохранить настройки</button></div>
</form>
<form id="tg-form" method="post" action="<?= url('admin/settings.php') ?>"><?= csrf_field() ?></form>
<?php admin_footer();
