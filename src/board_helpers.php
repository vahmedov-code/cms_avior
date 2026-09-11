<?php
/**
 * Библиотека плат и разметка фото — общие помощники.
 * Используется board_library.php (board_*) и repair_view.php (repair_photo*).
 */

function board_upload_dir(string $subdir): string
{
    return __DIR__ . '/../public/uploads/' . $subdir;
}

function board_store_upload(array $file, string $subdir, ?string &$error = null): ?string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Файл не загрузился, попробуйте ещё раз.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        $error = 'Поддерживаются только JPG, PNG и WEBP.';
        return null;
    }

    $dir = board_upload_dir($subdir);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        $error = 'Не удалось создать папку для загрузок: ' . $subdir;
        return null;
    }

    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        $error = 'Не удалось сохранить файл (проверьте права на public/uploads).';
        return null;
    }

    return 'uploads/' . $subdir . '/' . $name;
}

function board_delete_file(?string $relPath): void
{
    if (!$relPath) {
        return;
    }
    $full = __DIR__ . '/../public/' . $relPath;
    if (is_file($full)) {
        @unlink($full);
    }
}

function render_marker_editor(string $scope, int $photoId, string $src, array $markers, bool $editable = true): string
{
    $uid = 'mk' . $scope . $photoId;
    $json = htmlspecialchars(json_encode(array_map(static function (array $m): array {
        return [
            'id'         => (int) $m['id'],
            'x'          => (float) $m['x'],
            'y'          => (float) $m['y'],
            'designator' => (string) ($m['designator'] ?? ''),
            'value'      => (string) ($m['value'] ?? ''),
            'note'       => (string) ($m['note'] ?? ''),
        ];
    }, $markers), JSON_UNESCAPED_UNICODE), ENT_QUOTES);

    ob_start();
    ?>
<div class="board-editor" id="<?= e($uid) ?>" data-scope="<?= e($scope) ?>" data-photo="<?= $photoId ?>"
     data-editable="<?= $editable ? '1' : '0' ?>" data-markers="<?= $json ?>">
  <?php if ($editable): ?>
    <p class="board-hint">Нажмите на плату в нужной точке, чтобы поставить метку.</p>
  <?php endif; ?>
  <div class="board-canvas">
    <img src="<?= e($src) ?>" alt="Фото платы" class="board-img">
    <div class="board-pins"></div>
  </div>
  <?php if ($editable): ?>
    <div class="board-form" hidden>
      <div class="board-form-row">
        <input type="text" class="board-designator" placeholder="L1 / R5 / C12" maxlength="64">
        <input type="text" class="board-value" placeholder="Значение (напр. 2.2 Ом)" maxlength="128">
      </div>
      <input type="text" class="board-note" placeholder="Комментарий (необязательно)" maxlength="255">
      <div class="board-form-row">
        <button type="button" class="btn btn-primary btn-sm board-save">Сохранить</button>
        <button type="button" class="btn btn-sm board-delete" hidden>Удалить</button>
        <button type="button" class="btn btn-sm board-cancel">Отмена</button>
      </div>
    </div>
  <?php endif; ?>
</div>
    <?php
    return (string) ob_get_clean();
}

function render_marker_editor_assets(): string
{
    ob_start();
    ?>
<style>
.board-canvas { position: relative; display: inline-block; max-width: 100%; line-height: 0; }
.board-img { max-width: 100%; height: auto; display: block; border-radius: 8px; }
.board-pins { position: absolute; inset: 0; }
.board-pin { position: absolute; transform: translate(-50%, -50%); display: flex; align-items: center; gap: 4px; cursor: pointer; line-height: 1; }
.board-pin .dot { width: 14px; height: 14px; border-radius: 50%; background: #e53935; border: 2px solid #fff; box-shadow: 0 0 3px rgba(0,0,0,.6); flex: none; }
.board-pin .lbl { background: rgba(0,0,0,.78); color: #fff; font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 4px; white-space: nowrap; }
.board-hint { color: var(--muted); font-size: 12px; margin: 0 0 8px; }
.board-form { margin-top: 10px; max-width: 420px; display: flex; flex-direction: column; gap: 8px; }
.board-form-row { display: flex; gap: 8px; flex-wrap: wrap; }
.board-form input { flex: 1 1 140px; padding: 8px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
@media print { .board-hint, .board-form { display: none !important; } .board-pin .lbl { background: #000; } }
</style>
<script>
(function () {
  function initEditor(root) {
    var canvas   = root.querySelector('.board-canvas');
    var pinsBox  = root.querySelector('.board-pins');
    var form     = root.querySelector('.board-form');
    var editable = root.dataset.editable === '1';
    var scope    = root.dataset.scope;
    var photoId  = root.dataset.photo;
    var markers  = JSON.parse(root.dataset.markers || '[]');
    var pending  = null;
    var editing  = null;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function label(m) {
      var parts = [];
      if (m.designator) parts.push(m.designator);
      if (m.value) parts.push(m.value);
      return parts.join(': ') || '—';
    }

    function draw() {
      pinsBox.innerHTML = '';
      markers.forEach(function (m) {
        var pin = document.createElement('div');
        pin.className = 'board-pin';
        pin.style.left = m.x + '%';
        pin.style.top  = m.y + '%';
        pin.innerHTML = '<span class="dot"></span><span class="lbl"></span>';
        pin.querySelector('.lbl').textContent = label(m);
        if (m.note) pin.title = m.note;
        if (editable) {
          pin.addEventListener('click', function (ev) { ev.stopPropagation(); openForm(m, null); });
        }
        pinsBox.appendChild(pin);
      });
    }

    function openForm(marker, point) {
      if (!form) return;
      editing = marker;
      pending = point;
      form.hidden = false;
      form.querySelector('.board-designator').value = marker ? marker.designator : '';
      form.querySelector('.board-value').value      = marker ? marker.value : '';
      form.querySelector('.board-note').value       = marker ? marker.note : '';
      form.querySelector('.board-delete').hidden    = !marker;
      form.querySelector('.board-designator').focus();
    }

    function closeForm() {
      if (!form) return;
      form.hidden = true;
      editing = null;
      pending = null;
    }

    function send(action, payload, done) {
      payload.action = action;
      payload.scope  = scope;
      payload.photo_id = photoId;
      payload.csrf_token = csrf;
      fetch('board_markers_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload).toString()
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok) { alert(data.error || 'Ошибка сохранения'); return; }
          done(data);
        })
        .catch(function () { alert('Сеть недоступна, метка не сохранена'); });
    }

    if (editable) {
      canvas.addEventListener('click', function (ev) {
        if (ev.target.closest('.board-pin')) return;
        var rect = canvas.getBoundingClientRect();
        openForm(null, {
          x: +(((ev.clientX - rect.left) / rect.width) * 100).toFixed(3),
          y: +(((ev.clientY - rect.top) / rect.height) * 100).toFixed(3)
        });
      });

      form.querySelector('.board-save').addEventListener('click', function () {
        var data = {
          designator: form.querySelector('.board-designator').value.trim(),
          value:      form.querySelector('.board-value').value.trim(),
          note:       form.querySelector('.board-note').value.trim()
        };
        if (!data.designator && !data.value) { alert('Укажите обозначение или значение.'); return; }
        if (editing) {
          data.marker_id = editing.id;
          send('update', data, function () {
            editing.designator = data.designator;
            editing.value = data.value;
            editing.note = data.note;
            draw();
            closeForm();
          });
        } else {
          data.x = pending.x;
          data.y = pending.y;
          send('create', data, function (res) {
            markers.push({ id: res.id, x: pending.x, y: pending.y, designator: data.designator, value: data.value, note: data.note });
            draw();
            closeForm();
          });
        }
      });

      form.querySelector('.board-delete').addEventListener('click', function () {
        if (!editing || !confirm('Удалить метку?')) return;
        var id = editing.id;
        send('delete', { marker_id: id }, function () {
          markers = markers.filter(function (m) { return m.id !== id; });
          draw();
          closeForm();
        });
      });

      form.querySelector('.board-cancel').addEventListener('click', closeForm);
    }

    draw();
  }

  document.querySelectorAll('.board-editor').forEach(initEditor);
})();
</script>
    <?php
    return (string) ob_get_clean();
}
