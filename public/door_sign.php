<?php
/**
 * Вывеска на двери AviorSign — управление из CRM («Инструменты»).
 *
 * Вывеска (ESP32 + электронная бумага) стоит в офисной сети, CRM её
 * напрямую не видит: вывеска сама раз в ~10 с обращается к
 * api/sign/sync.php, присылает своё состояние и забирает то, что задано
 * здесь. Поэтому изменение доходит до двери за 10–40 секунд (до 10 с на
 * опрос и около 25 с на перерисовку экрана).
 *
 * Здесь: статус, картинки для каждого статуса, недельное расписание,
 * QR-код, состояние вывески (связь, заряд, версия) и журнал. Wi-Fi и
 * обновление прошивки — только в локальной панели самой вывески.
 */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/door_sign.php';
require_login();

$pageTitle = 'Вывеска на двери';
$activeNav = 'door_sign';
$ready = door_sign_tables_ready();
$sign = $ready ? door_sign_current() : null;
$userId = current_user()['id'] ?? null;

// Сохранённая в CRM картинка статуса — 96000 байт как есть, рисует JS ниже.
if ($sign && isset($_GET['frame'])) {
    $stmt = db()->prepare('SELECT frame FROM door_sign_assets WHERE sign_id = ? AND slot = ? AND frame IS NOT NULL');
    $stmt->execute([$sign['id'], (string) $_GET['frame']]);
    $frame = $stmt->fetchColumn();
    if ($frame === false) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: application/octet-stream');
    header('Cache-Control: no-store');
    echo $frame;
    exit;
}

// Для автообновления блока «Сейчас на двери» без перезагрузки страницы.
if ($sign && isset($_GET['state'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'online' => door_sign_is_online($sign),
        'secondsAgo' => door_sign_seconds_since_seen($sign),
        'reported' => door_sign_reported($sign),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $ajax = post('ajax') === '1';
    $finish = function (string $message, bool $ok = true) use ($ajax): void {
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$ok) {
                http_response_code(400);
            }
            echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }
        flash_set($message, $ok ? 'success' : 'error');
        redirect('door_sign.php');
    };

    if (!$ready) {
        $finish('Модуль вывески ещё не включён: примените миграцию 2026_10_22_door_signs.sql.', false);
    }

    if ($action === 'pair' || $action === 'new_token') {
        if (!is_admin()) {
            $finish('Подключать вывеску может владелец или администратор.', false);
        }
        $token = door_sign_new_token();
        if ($action === 'pair' && !$sign) {
            db()->prepare('INSERT INTO door_signs (token_hash) VALUES (?)')->execute([hash('sha256', $token)]);
            $signId = (int) db()->lastInsertId();
            door_sign_log($signId, $userId, 'paired', 'Вывеска добавлена в CRM');
        } elseif ($sign) {
            db()->prepare('UPDATE door_signs SET token_hash = ? WHERE id = ?')->execute([hash('sha256', $token), $sign['id']]);
            door_sign_log((int) $sign['id'], $userId, 'token', 'Выдан новый токен, старый отозван');
        } else {
            $finish('Сначала подключите вывеску.', false);
        }
        // Токен показывается один раз; в базе только его хеш.
        $_SESSION['door_sign_token'] = $token;
        redirect('door_sign.php');
    }

    if (!$sign) {
        $finish('Сначала подключите вывеску.', false);
    }
    $signId = (int) $sign['id'];

    if ($action === 'set_status') {
        $status = post('status');
        if (!in_array($status, DOOR_SIGN_SLOTS, true)) {
            $finish('Неизвестный статус.', false);
        }
        $seq = door_sign_next_seq($signId);
        db()->prepare('UPDATE door_signs SET status = ?, status_seq = ? WHERE id = ?')->execute([$status, $seq, $signId]);
        door_sign_log($signId, $userId, 'status', 'Статус: ' . door_sign_label($status));
        $finish('Статус «' . door_sign_label($status) . '» отправлен. На двери он появится примерно через 10–40 секунд.');
    }

    if ($action === 'save_schedule') {
        $schedule = door_sign_validate_schedule(json_decode(post('schedule'), true));
        if ($schedule === null) {
            $finish('Проверьте расписание: дни, время, статусы и отсутствие одинаковых событий.', false);
        }
        $seq = door_sign_next_seq($signId);
        db()->prepare('UPDATE door_signs SET schedule_json = ?, schedule_seq = ? WHERE id = ?')
            ->execute([json_encode($schedule, JSON_UNESCAPED_UNICODE), $seq, $signId]);
        door_sign_log($signId, $userId, 'schedule', ($schedule['enabled'] ? 'Расписание включено, событий: ' : 'Расписание выключено, событий: ') . count($schedule['events']));
        $finish($schedule['enabled'] ? 'Расписание сохранено и включено.' : 'Расписание сохранено и выключено.');
    }

    if ($action === 'save_qr') {
        $url = trim(post('qr_url'));
        $enabled = post('qr_enabled') === '1';
        if (!door_sign_valid_qr_url($url)) {
            $finish('Ссылка для QR-кода должна начинаться с https:// и быть не длиннее 160 символов.', false);
        }
        $seq = door_sign_next_seq($signId);
        db()->prepare('UPDATE door_signs SET qr_enabled = ?, qr_url = ?, qr_seq = ? WHERE id = ?')->execute([$enabled ? 1 : 0, $url, $seq, $signId]);
        door_sign_log($signId, $userId, 'qr', $enabled ? 'QR-код: ' . $url : 'QR-код выключен');
        $finish($enabled ? 'QR-код сохранён.' : 'QR-код выключен.');
    }

    if ($action === 'upload_asset' || $action === 'reset_asset') {
        $slot = post('slot');
        if (!in_array($slot, DOOR_SIGN_SLOTS, true)) {
            $finish('Неизвестный статус.', false);
        }
        $frame = null;
        if ($action === 'upload_asset') {
            $file = $_FILES['frame'] ?? null;
            if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || (int) $file['size'] !== DOOR_SIGN_FRAME_BYTES) {
                $finish('Картинка не загрузилась: нужен кадр 800×480 из этой страницы.', false);
            }
            $frame = (string) file_get_contents($file['tmp_name']);
        } elseif ($slot === 'image' && $sign['status'] === 'image') {
            $finish('Сначала выберите другой статус, потом удаляйте своё изображение.', false);
        }
        $seq = door_sign_next_seq($signId);
        db()->prepare(
            'INSERT INTO door_sign_assets (sign_id, slot, frame, sha256, seq) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE frame = VALUES(frame), sha256 = VALUES(sha256), seq = VALUES(seq)'
        )->execute([$signId, $slot, $frame, $frame !== null ? hash('sha256', $frame) : null, $seq]);
        door_sign_log($signId, $userId, $frame !== null ? 'picture' : 'picture_reset',
            ($frame !== null ? 'Новая картинка: ' : 'Стандартная картинка: ') . door_sign_label($slot));
        $finish($frame !== null ? 'Картинка для «' . door_sign_label($slot) . '» сохранена и уйдёт на вывеску.'
            : 'Для «' . door_sign_label($slot) . '» снова стандартная картинка.');
    }

    if ($action === 'rename') {
        $name = trim(post('name'));
        if ($name === '' || mb_strlen($name) > 100) {
            $finish('Название — от 1 до 100 символов.', false);
        }
        db()->prepare('UPDATE door_signs SET name = ? WHERE id = ?')->execute([$name, $signId]);
        $finish('Название сохранено.');
    }

    $finish('Неизвестное действие.', false);
}

$reported = $sign ? door_sign_reported($sign) : null;
$assets = [];
$log = [];
$shownToken = $_SESSION['door_sign_token'] ?? null;
unset($_SESSION['door_sign_token']);
if ($sign) {
    $stmt = db()->prepare('SELECT slot, frame IS NOT NULL AS present, seq FROM door_sign_assets WHERE sign_id = ?');
    $stmt->execute([$sign['id']]);
    foreach ($stmt->fetchAll() as $a) {
        $assets[$a['slot']] = (bool) $a['present'];
    }
    $stmt = db()->prepare(
        'SELECT l.created_at, l.action, l.detail, u.full_name FROM door_sign_log l
         LEFT JOIN users u ON u.id = l.user_id WHERE l.sign_id = ? ORDER BY l.id DESC LIMIT 25'
    );
    $stmt->execute([$sign['id']]);
    $log = $stmt->fetchAll();
}
// Расписание для редактора: заданное в CRM, иначе то, что сейчас на вывеске.
$schedule = $sign && $sign['schedule_json'] ? json_decode($sign['schedule_json'], true)
    : ($reported['schedule'] ?? ['enabled' => false, 'utcOffsetMinutes' => 180, 'events' => []]);
$crmUrl = rtrim((string) (config()['crm_url'] ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? 'cms.avior.moscow'))), '/');

require __DIR__ . '/../src/layout_header.php';
?>
<style>
.ds-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:20px;align-items:start;margin-bottom:24px;}
.ds-grid>*{min-width:0;}
.ds-box{border:1px solid var(--border);border-radius:10px;padding:18px;background:#fff;}
.ds-box .table-card{overflow-x:auto;}
.ds-box h3{margin:0 0 12px;color:var(--navy);font-size:16px;}
.ds-muted{color:var(--muted);font-size:12px;}
.ds-state{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;}
.ds-pill{font-size:13px;border:1px solid var(--border);border-radius:30px;padding:5px 12px;background:#f7f9fc;}
.ds-pill.ok{background:#e6f4ea;color:var(--good);border-color:#c6e5cf;}
.ds-pill.bad{background:#fbe9e7;color:var(--danger);border-color:#f0c4bd;}
.ds-status{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.ds-status button{justify-content:center;min-height:46px;font-weight:600;}
.ds-status button.current{outline:2px solid var(--navy);}
.ds-tiles{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;}
.ds-tile{border:1px solid var(--border);border-radius:8px;padding:8px;display:flex;flex-direction:column;gap:6px;background:#fff;}
.ds-tile canvas{width:100%;height:auto;aspect-ratio:5/3;border:1px solid var(--border);border-radius:4px;background:#fff;}
.ds-tile .btn{justify-content:center;}
.ds-event{display:grid;grid-template-columns:1fr 1fr 1.3fr 40px;gap:8px;margin-bottom:8px;}
.ds-event select,.ds-event input{padding:8px;border:1px solid var(--border);border-radius:6px;font:inherit;}
.ds-file{position:relative;overflow:hidden;}
.ds-file input{position:absolute;inset:0;opacity:0;cursor:pointer;}
.ds-token{font-family:monospace;font-size:15px;background:#fff8e1;border:1px dashed #d4a20b;padding:10px;border-radius:6px;word-break:break-all;}
@media(max-width:900px){.ds-grid{grid-template-columns:1fr;}.ds-tiles{grid-template-columns:1fr 1fr;}.ds-event{grid-template-columns:1fr 1fr;}}
</style>

<div class="page-title">
  <h2>🪧 <?= e($sign['name'] ?? 'Вывеска на двери') ?></h2>
  <?php if ($sign): ?><span class="ds-muted">Изменения доходят до двери за 10–40 секунд</span><?php endif; ?>
</div>

<?php if (!$ready): ?>
  <div class="flash flash-info">
    Модуль вывески ещё не включён — в базе нет его таблиц. Остальная CRM от этого не зависит.
    <?php if (is_owner()): ?><br>На сервере выполните:
      <code>mysql -u avior_user -p avior_cms &lt; sql/migrations/2026_10_22_door_signs.sql</code><?php endif; ?>
  </div>

<?php elseif (!$sign): ?>
  <div class="ds-box" style="max-width:640px;">
    <h3>Подключить вывеску</h3>
    <p>CRM выдаст токен. Его нужно один раз ввести в локальной панели самой вывески: «Устройство» → «Avior CRM».</p>
    <?php if (is_admin()): ?>
      <form method="post"><input type="hidden" name="action" value="pair"><button class="btn btn-primary">Подключить вывеску</button></form>
    <?php else: ?>
      <p class="ds-muted">Подключение делает владелец или администратор.</p>
    <?php endif; ?>
  </div>

<?php else: ?>
  <?php if ($shownToken): ?>
    <div class="ds-box" style="margin-bottom:20px;border-color:#d4a20b;">
      <h3>Токен вывески — показывается один раз</h3>
      <p>Откройте локальную панель вывески (в офисной сети), вкладка «Устройство» → «Avior CRM», и введите:</p>
      <p>Адрес CRM: <span class="ds-token"><?= e($crmUrl) ?></span></p>
      <p>Токен: <span class="ds-token"><?= e($shownToken) ?></span></p>
      <p class="ds-muted">В базе хранится только его хеш. Если потеряете — выдайте новый внизу страницы, старый перестанет работать.</p>
    </div>
  <?php endif; ?>

  <div class="ds-grid">
    <div class="ds-box">
      <h3>Сейчас на двери</h3>
      <div class="ds-state" id="dsState"></div>
      <div class="ds-status">
        <?php foreach (DOOR_SIGN_SLOTS as $slot): ?>
          <form method="post">
            <input type="hidden" name="action" value="set_status"><input type="hidden" name="status" value="<?= e($slot) ?>">
            <button class="btn <?= $sign['status'] === $slot ? 'btn-primary current' : '' ?>" style="width:100%;"
              <?= $slot === 'image' && empty($assets['image']) && empty($reported['assets']['image']) ? 'disabled title="Сначала загрузите своё изображение"' : '' ?>>
              <?= e(door_sign_label($slot)) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
      <p class="ds-muted" style="margin-top:10px;">Выбор статуса вручную выключает расписание на вывеске — как и в её локальной панели.</p>
    </div>

    <div class="ds-box">
      <h3>QR-код на стандартных картинках</h3>
      <form method="post">
        <input type="hidden" name="action" value="save_qr">
        <label style="display:flex;gap:8px;align-items:center;font-size:14px;margin-bottom:10px;">
          <input type="checkbox" name="qr_enabled" value="1" <?= $sign['qr_enabled'] ? 'checked' : '' ?>> Показывать QR-код
        </label>
        <label class="field">Ссылка<input type="url" name="qr_url" maxlength="160" value="<?= e($sign['qr_url']) ?>" required></label>
        <button class="btn btn-primary">Сохранить QR-код</button>
      </form>
      <p class="ds-muted" style="margin-top:10px;">На своих картинках QR-кода нет — добавьте его в саму картинку, если нужно.</p>
    </div>
  </div>

  <div class="ds-box" style="margin-bottom:24px;">
    <h3>Картинки статусов</h3>
    <div class="ds-tiles">
      <?php foreach (DOOR_SIGN_SLOTS as $slot): ?>
        <div class="ds-tile" data-slot="<?= e($slot) ?>" data-saved="<?= !empty($assets[$slot]) ? '1' : '0' ?>">
          <strong><?= e(door_sign_label($slot)) ?></strong>
          <canvas width="800" height="480"></canvas>
          <span class="ds-muted" data-kind><?= !empty($assets[$slot]) ? 'своя картинка из CRM' : ($slot === 'image' ? 'не загружено' : 'стандартная') ?></span>
          <label class="btn btn-sm ds-file">Загрузить PNG/JPEG<input type="file" accept="image/png,image/jpeg"></label>
          <form method="post" <?= empty($assets[$slot]) ? 'hidden' : '' ?>>
            <input type="hidden" name="action" value="reset_asset"><input type="hidden" name="slot" value="<?= e($slot) ?>">
            <button class="btn btn-sm btn-warn" style="width:100%;">Вернуть стандартную</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="ds-muted" style="margin-top:10px;">Картинка вписывается в 800×480 с белыми полями и переводится в чёрный, белый, жёлтый и красный прямо в браузере. Картинка текущего статуса выводится на дверь сама.</p>
  </div>

  <div class="ds-box" style="margin-bottom:24px;">
    <h3>Недельное расписание</h3>
    <form method="post" id="dsScheduleForm">
      <input type="hidden" name="action" value="save_schedule"><input type="hidden" name="schedule" id="dsScheduleJson">
      <label class="field" style="max-width:220px;">Часовой пояс
        <select id="dsTz">
          <?php foreach ([180 => 'Москва · UTC+3', 0 => 'UTC', 120 => 'UTC+2', 240 => 'UTC+4', 300 => 'UTC+5'] as $minutes => $label): ?>
            <option value="<?= $minutes ?>" <?= (int) ($schedule['utcOffsetMinutes'] ?? 180) === $minutes ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div id="dsEvents"></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" class="btn" id="dsAddEvent">+ Событие</button>
        <button type="button" class="btn" id="dsWeekdays">Шаблон: Пн–Пт 10:00–20:00</button>
        <button type="submit" class="btn btn-primary" data-enabled="1">Сохранить и включить</button>
        <button type="submit" class="btn" data-enabled="0">Сохранить выключенным</button>
      </div>
      <p class="ds-muted" style="margin-top:10px;">Статус держится до следующего события, в том числе ночью и в выходные.
        Сейчас: <?= !empty($schedule['enabled']) ? 'включено' : 'выключено' ?><?= $sign['schedule_json'] ? '' : ' (показано расписание с самой вывески)' ?>.</p>
    </form>
  </div>

  <div class="ds-grid">
    <div class="ds-box">
      <h3>Журнал</h3>
      <?php if (!$log): ?><p class="ds-muted">Пока пусто.</p><?php else: ?>
        <div class="table-card"><table>
          <thead><tr><th>Когда</th><th>Что</th><th>Кто</th></tr></thead>
          <tbody>
          <?php foreach ($log as $row): ?>
            <tr><td><?= e(date('d.m H:i', strtotime((string) $row['created_at']))) ?></td><td><?= e($row['detail'] ?? $row['action']) ?></td><td><?= e($row['full_name'] ?? 'вывеска') ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
    <div class="ds-box">
      <h3>Вывеска</h3>
      <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:12px;">
        <input type="hidden" name="action" value="rename">
        <label class="field" style="flex:1;min-width:160px;margin:0;">Название<input name="name" maxlength="100" value="<?= e($sign['name']) ?>"></label>
        <button class="btn">Сохранить</button>
      </form>
      <p class="ds-muted" id="dsLocal">Wi-Fi и обновление прошивки — в локальной панели вывески, она открывается только из офисной сети.</p>
      <?php if (is_admin()): ?>
        <form method="post" onsubmit="return confirm('Старый токен перестанет работать. Новый нужно будет ввести в панели вывески. Продолжить?');">
          <input type="hidden" name="action" value="new_token"><button class="btn btn-sm btn-warn">Выдать новый токен</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

<script>
(function () {
  'use strict';
  var labels = {open: 'Открыто', closed: 'Закрыто', soon: 'Скоро буду', image: 'Своё изображение'};
  var palette = [[0, 0, 0], [255, 255, 255], [255, 213, 0], [196, 30, 35]];
  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  // Формат кадра прошивки: 4 пикселя на байт, старшие биты — левый пиксель.
  function pack(ctx) {
    var px = ctx.getImageData(0, 0, 800, 480).data, out = new Uint8Array(96000);
    for (var p = 0; p < 384000; p++) {
      var r = px[p * 4], g = px[p * 4 + 1], b = px[p * 4 + 2], best = 0;
      // Почти серые пиксели — только в чёрный/белый, иначе края букв краснеют.
      if (Math.max(r, g, b) - Math.min(r, g, b) < 60) { best = 0.299 * r + 0.587 * g + 0.114 * b < 128 ? 0 : 1; }
      else {
        var min = Infinity;
        for (var c = 0; c < 4; c++) {
          var d = Math.pow(r - palette[c][0], 2) + Math.pow(g - palette[c][1], 2) + Math.pow(b - palette[c][2], 2);
          if (d < min) { min = d; best = c; }
        }
      }
      out[p >> 2] |= best << (6 - 2 * (p % 4));
    }
    return out;
  }
  function unpack(ctx, bytes) {
    var img = ctx.createImageData(800, 480);
    for (var p = 0; p < 384000; p++) {
      var c = palette[(bytes[p >> 2] >> (6 - 2 * (p % 4))) & 3];
      img.data[p * 4] = c[0]; img.data[p * 4 + 1] = c[1]; img.data[p * 4 + 2] = c[2]; img.data[p * 4 + 3] = 255;
    }
    ctx.putImageData(img, 0, 0);
  }
  function standard(ctx, slot) {
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, 800, 480);
    if (slot === 'image') { ctx.fillStyle = '#000'; ctx.textAlign = 'center'; ctx.font = '600 34px system-ui'; ctx.fillText('Картинка не загружена', 400, 250); ctx.textAlign = 'left'; return; }
    ctx.fillStyle = slot === 'closed' ? '#c41e23' : slot === 'soon' ? '#ffd500' : '#000'; ctx.fillRect(40, 40, 720, 8);
    ctx.fillStyle = '#000'; ctx.font = '700 40px monospace'; ctx.fillText('AVIOR', 44, 125);
    ctx.font = '600 64px system-ui'; ctx.fillText(labels[slot], 44, 264, 470);
    ctx.strokeStyle = '#000'; ctx.lineWidth = 3; ctx.strokeRect(557, 130, 203, 203);
    ctx.font = '600 22px system-ui'; ctx.textAlign = 'center'; ctx.fillText('QR', 658, 240); ctx.textAlign = 'left';
  }

  document.querySelectorAll('.ds-tile').forEach(function (tile) {
    var slot = tile.dataset.slot, canvas = tile.querySelector('canvas'), ctx = canvas.getContext('2d', {willReadFrequently: true});
    standard(ctx, slot);
    if (tile.dataset.saved === '1') {
      fetch('door_sign.php?frame=' + slot, {cache: 'no-store'}).then(function (r) { return r.ok ? r.arrayBuffer() : null; })
        .then(function (b) { if (b && b.byteLength === 96000) { unpack(ctx, new Uint8Array(b)); } });
    }
    tile.querySelector('input[type=file]').addEventListener('change', function (ev) {
      var file = ev.target.files[0]; ev.target.value = '';
      if (!file) { return; }
      if (['image/png', 'image/jpeg'].indexOf(file.type) < 0 || file.size > 15 * 1024 * 1024) { alert('Нужен PNG или JPEG до 15 МБ.'); return; }
      createImageBitmap(file).then(function (img) {
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, 800, 480);
        var s = Math.min(800 / img.width, 480 / img.height), w = img.width * s, h = img.height * s;
        ctx.drawImage(img, (800 - w) / 2, (480 - h) / 2, w, h); img.close();
        var bytes = pack(ctx); unpack(ctx, bytes);
        if (!confirm('Сохранить эту картинку для «' + labels[slot] + '»? Так она будет выглядеть на двери.')) { location.reload(); return; }
        var form = new FormData();
        form.append('csrf_token', csrf); form.append('action', 'upload_asset'); form.append('slot', slot); form.append('ajax', '1');
        form.append('frame', new Blob([bytes], {type: 'application/octet-stream'}), 'frame.bin');
        return fetch('door_sign.php', {method: 'POST', body: form}).then(function (r) { return r.json(); }).then(function (d) {
          if (!d.ok) { throw new Error(d.message); }
          location.reload();
        });
      }).catch(function (e) { alert(e.message || 'Не удалось обработать картинку.'); });
    });
  });

  // Расписание: те же правила, что проверяет сервер и прошивка.
  var days = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
  var events = document.getElementById('dsEvents');
  function addEvent(e) {
    e = e || {day: 1, minute: 600, status: 'open'};
    var row = document.createElement('div'); row.className = 'ds-event';
    var day = document.createElement('select'); days.forEach(function (n, i) { day.add(new Option(n, i)); }); day.value = e.day;
    var time = document.createElement('input'); time.type = 'time'; time.required = true;
    time.value = String(Math.floor(e.minute / 60)).padStart(2, '0') + ':' + String(e.minute % 60).padStart(2, '0');
    var st = document.createElement('select'); ['open', 'closed', 'soon'].forEach(function (s) { st.add(new Option(labels[s], s)); }); st.value = e.status;
    var del = document.createElement('button'); del.type = 'button'; del.className = 'btn btn-sm'; del.textContent = '×'; del.onclick = function () { row.remove(); };
    row.append(day, time, st, del); events.append(row);
  }
  (<?= json_encode($schedule['events'] ?? [], JSON_UNESCAPED_UNICODE) ?>).forEach(addEvent);
  document.getElementById('dsAddEvent').onclick = function () { addEvent(); };
  document.getElementById('dsWeekdays').onclick = function () {
    events.replaceChildren();
    for (var d = 1; d <= 5; d++) { addEvent({day: d, minute: 600, status: 'open'}); addEvent({day: d, minute: 1200, status: 'closed'}); }
  };
  var form = document.getElementById('dsScheduleForm'), enabled = true;
  form.querySelectorAll('button[type=submit]').forEach(function (b) { b.onclick = function () { enabled = b.dataset.enabled === '1'; }; });
  form.addEventListener('submit', function (ev) {
    var list = [];
    for (var i = 0; i < events.children.length; i++) {
      var c = events.children[i].children, t = c[1].value;
      if (!t) { ev.preventDefault(); alert('Укажите время каждого события.'); return; }
      list.push({day: Number(c[0].value), minute: Number(t.split(':')[0]) * 60 + Number(t.split(':')[1]), status: c[2].value});
    }
    if (enabled && !list.length) { ev.preventDefault(); alert('Добавьте хотя бы одно событие.'); return; }
    document.getElementById('dsScheduleJson').value = JSON.stringify({enabled: enabled, utcOffsetMinutes: Number(document.getElementById('dsTz').value), events: list});
  });

  // Состояние вывески — раз в 10 секунд, без перезагрузки страницы.
  function pill(text, cls) { var s = document.createElement('span'); s.className = 'ds-pill' + (cls ? ' ' + cls : ''); s.textContent = text; return s; }
  function ago(s) {
    if (s === null || s === undefined) { return 'ни разу'; }
    return s < 60 ? s + ' с назад' : s < 3600 ? Math.round(s / 60) + ' мин назад' : s < 172800 ? Math.round(s / 3600) + ' ч назад' : Math.round(s / 86400) + ' дн назад';
  }
  function show(d) {
    var box = document.getElementById('dsState'), r = d.reported || {};
    box.replaceChildren(d.online ? pill('На связи · ' + ago(d.secondsAgo), 'ok') : pill('Нет связи · ' + ago(d.secondsAgo), 'bad'));
    if (r.displayed) { box.append(pill('На экране: ' + (labels[r.displayed] || 'неизвестно'))); }
    if (r.busy) { box.append(pill('Экран обновляется')); }
    if (r.displayError) { box.append(pill(r.displayError, 'bad')); }
    if (r.battery) { box.append(pill('АКБ ≈' + r.battery.percent + ' % · ' + (r.battery.mv / 1000).toFixed(2).replace('.', ',') + ' В', r.battery.percent <= 15 ? 'bad' : '')); }
    if (r.version) { box.append(pill('Прошивка ' + r.version)); }
    if (r.automatic) { box.append(pill('Работает по расписанию')); }
    if (r.ip) { document.getElementById('dsLocal').textContent = 'Локальная панель (только из офисной сети): http://' + r.ip + ' — там Wi-Fi и обновление прошивки.'; }
  }
  function poll() {
    fetch('door_sign.php?state=1', {cache: 'no-store'}).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) { if (d) { show(d); } }).catch(function () {});
  }
  show(<?= json_encode(['online' => door_sign_is_online($sign), 'secondsAgo' => door_sign_seconds_since_seen($sign), 'reported' => $reported], JSON_UNESCAPED_UNICODE) ?>);
  setInterval(function () { if (!document.hidden) { poll(); } }, 10000);
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
