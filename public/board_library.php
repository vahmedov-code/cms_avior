<?php
/**
 * Библиотека плат — справочник моделей с фото и метками номиналов.
 * Без параметров — список моделей; ?model=ID — карточка модели.
 */
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/board_helpers.php';
require_login();

$pageTitle = 'Библиотека плат';
$activeNav = 'board_library';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    if ($action === 'add_model') {
        $name = trim(post('name'));
        $deviceType = trim(post('device_type')) ?: null;
        $boardCode = trim(post('board_code')) ?: null;

        if ($name === '') {
            flash_set('Укажите название модели.', 'error');
            redirect('board_library.php');
        }

        db()->prepare(
            'INSERT INTO board_models (name, device_type, board_code, created_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                device_type = IF(? IS NOT NULL, ?, device_type),
                board_code  = IF(? IS NOT NULL, ?, board_code)'
        )->execute([$name, $deviceType, $boardCode, current_user()['id'] ?? null,
            $deviceType, $deviceType, $boardCode, $boardCode]);

        $idStmt = db()->prepare('SELECT id FROM board_models WHERE name = ?');
        $idStmt->execute([$name]);
        $modelId = (int) $idStmt->fetchColumn();

        flash_set('Модель добавлена: ' . $name, 'success');
        redirect('board_library.php?model=' . $modelId);
    }

    if ($action === 'upload_photo') {
        $modelId = (int) post('model_id');
        $title = trim(post('title')) ?: null;
        $side = in_array(post('side'), ['top', 'bottom', 'other'], true) ? post('side') : 'top';

        $error = null;
        $path = isset($_FILES['photo']) ? board_store_upload($_FILES['photo'], 'boards', $error) : null;

        if ($path === null) {
            flash_set($error ?? 'Файл не выбран.', 'error');
        } else {
            db()->prepare(
                'INSERT INTO board_photos (board_model_id, file_path, title, side, created_by) VALUES (?, ?, ?, ?, ?)'
            )->execute([$modelId, $path, $title, $side, current_user()['id'] ?? null]);
            flash_set('Фото загружено — можно расставлять метки.', 'success');
        }
        redirect('board_library.php?model=' . $modelId);
    }

    if ($action === 'delete_photo') {
        $photoId = (int) post('photo_id');
        $modelId = (int) post('model_id');

        $stmt = db()->prepare('SELECT file_path FROM board_photos WHERE id = ?');
        $stmt->execute([$photoId]);
        $path = $stmt->fetchColumn();

        if ($path) {
            board_delete_file((string) $path);
            db()->prepare('DELETE FROM board_photos WHERE id = ?')->execute([$photoId]);
            flash_set('Фото удалено.', 'success');
        }
        redirect('board_library.php?model=' . $modelId);
    }

    if ($action === 'update_notes') {
        $modelId = (int) post('model_id');
        db()->prepare('UPDATE board_models SET notes = ? WHERE id = ?')
            ->execute([trim(post('notes')) ?: null, $modelId]);
        flash_set('Заметки сохранены.', 'success');
        redirect('board_library.php?model=' . $modelId);
    }
}

$modelId = (int) get('model', '0');

if ($modelId > 0) {
    $stmt = db()->prepare('SELECT * FROM board_models WHERE id = ?');
    $stmt->execute([$modelId]);
    $model = $stmt->fetch();

    if (!$model) {
        flash_set('Модель не найдена.', 'error');
        redirect('board_library.php');
    }

    $photosStmt = db()->prepare('SELECT * FROM board_photos WHERE board_model_id = ? ORDER BY created_at');
    $photosStmt->execute([$modelId]);
    $photos = $photosStmt->fetchAll();

    $markersByPhoto = [];
    if ($photos) {
        $ids = array_column($photos, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $mStmt = db()->prepare("SELECT * FROM board_markers WHERE photo_id IN ($in) ORDER BY id");
        $mStmt->execute($ids);
        foreach ($mStmt->fetchAll() as $m) {
            $markersByPhoto[(int) $m['photo_id']][] = $m;
        }
    }

    $sideLabels = ['top' => 'Верх платы', 'bottom' => 'Низ платы', 'other' => 'Другое'];

    require __DIR__ . '/../src/layout_header.php';
    ?>

    <div class="page-title">
      <h2>Плата: <?= e($model['name']) ?></h2>
      <a href="board_library.php" class="btn btn-sm">← Все модели</a>
    </div>

    <p style="color:var(--muted);font-size:13px;margin-top:-8px;">
      <?= e($model['device_type'] ?: 'Тип не указан') ?>
      <?= $model['board_code'] ? ' · плата ' . e($model['board_code']) : '' ?>
    </p>

    <div style="margin:20px 0 28px;">
      <h3 style="color:var(--navy);font-size:16px;">Добавить фото платы</h3>
      <form method="post" enctype="multipart/form-data" class="form-grid" style="max-width:520px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_photo">
        <input type="hidden" name="model_id" value="<?= $modelId ?>">
        <label class="field full">Файл (можно снять камерой телефона)
          <input type="file" name="photo" accept="image/*" required>
        </label>
        <label class="field">Сторона
          <select name="side">
            <option value="top">Верх платы</option>
            <option value="bottom">Низ платы</option>
            <option value="other">Другое</option>
          </select>
        </label>
        <label class="field">Подпись (необязательно)
          <input type="text" name="title" placeholder="Например: цепь питания">
        </label>
        <div class="field full">
          <button type="submit" class="btn btn-primary">Загрузить</button>
        </div>
      </form>
    </div>

    <?php if (!$photos): ?>
      <p style="color:var(--muted);">Фото пока нет. Загрузите первое — потом по нему можно расставлять номиналы.</p>
    <?php endif; ?>

    <?php foreach ($photos as $photo): ?>
      <?php $markers = $markersByPhoto[(int) $photo['id']] ?? []; ?>
      <div style="margin-bottom:36px;padding-bottom:28px;border-bottom:1px solid #e5e5e5;">
        <h3 style="color:var(--navy);font-size:15px;margin-bottom:4px;">
          <?= e($sideLabels[$photo['side']] ?? 'Фото') ?>
          <?= $photo['title'] ? ' — ' . e($photo['title']) : '' ?>
          <span style="color:var(--muted);font-weight:400;font-size:12px;">(меток: <?= count($markers) ?>)</span>
        </h3>

        <?= render_marker_editor('board', (int) $photo['id'], $photo['file_path'], $markers) ?>

        <?php if ($markers): ?>
          <details style="margin-top:12px;">
            <summary style="cursor:pointer;color:var(--navy);font-size:14px;">Список значений</summary>
            <table class="data-table" style="margin-top:8px;">
              <thead><tr><th>Обозначение</th><th>Значение</th><th>Комментарий</th></tr></thead>
              <tbody>
                <?php foreach ($markers as $m): ?>
                  <tr>
                    <td data-label="Обозначение"><?= e($m['designator'] ?: '—') ?></td>
                    <td data-label="Значение"><?= e($m['value'] ?: '—') ?></td>
                    <td data-label="Комментарий"><?= e($m['note'] ?: '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </details>
        <?php endif; ?>

        <form method="post" style="margin-top:10px;" onsubmit="return confirm('Удалить фото вместе со всеми метками?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_photo">
          <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
          <input type="hidden" name="model_id" value="<?= $modelId ?>">
          <button type="submit" class="btn btn-sm">Удалить фото</button>
        </form>
      </div>
    <?php endforeach; ?>

    <div style="margin-top:24px;">
      <h3 style="color:var(--navy);font-size:16px;">Заметки по модели</h3>
      <form method="post" style="max-width:640px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_notes">
        <input type="hidden" name="model_id" value="<?= $modelId ?>">
        <textarea name="notes" rows="5" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;"
                  placeholder="Типовые неисправности, особенности, что проверять в первую очередь"><?= e($model['notes'] ?? '') ?></textarea>
        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;">Сохранить заметки</button>
      </form>
    </div>

    <?= render_marker_editor_assets() ?>

    <?php
    require __DIR__ . '/../src/layout_footer.php';
    exit;
}

$q = trim(get('q'));
if ($q !== '') {
    $stmt = db()->prepare(
        'SELECT m.*, COUNT(DISTINCT p.id) AS photo_count
         FROM board_models m
         LEFT JOIN board_photos p ON p.board_model_id = m.id
         WHERE m.name LIKE ? OR m.board_code LIKE ? OR m.device_type LIKE ?
         GROUP BY m.id ORDER BY m.name'
    );
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like, $like]);
    $models = $stmt->fetchAll();
} else {
    $models = db()->query(
        'SELECT m.*, COUNT(DISTINCT p.id) AS photo_count
         FROM board_models m
         LEFT JOIN board_photos p ON p.board_model_id = m.id
         GROUP BY m.id ORDER BY m.name'
    )->fetchAll();
}

require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>Библиотека плат</h2>
</div>

<p style="color:var(--muted);font-size:13px;margin-top:-8px;">
  Справочник номиналов по моделям плат — фото с метками сопротивлений и других значений.
</p>

<form method="get" style="margin:16px 0;display:flex;gap:8px;flex-wrap:wrap;max-width:520px;">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Поиск по модели, коду платы, типу"
         style="flex:1 1 240px;padding:8px;border:1px solid #ccc;border-radius:6px;">
  <button type="submit" class="btn btn-sm btn-primary">Найти</button>
  <?php if ($q !== ''): ?>
    <a href="board_library.php" class="btn btn-sm">Сброс</a>
  <?php endif; ?>
</form>

<div style="margin:24px 0;">
  <h3 style="color:var(--navy);font-size:16px;">Новая модель</h3>
  <form method="post" class="form-grid" style="max-width:520px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_model">
    <label class="field full">Название
      <input type="text" name="name" required placeholder="Например: Lenovo IdeaPad 3 15ITL6">
    </label>
    <label class="field">Тип устройства
      <input type="text" name="device_type" list="boardDeviceTypes" placeholder="Ноутбук / Смартфон / Планшет">
    </label>
    <label class="field">Код платы (необязательно)
      <input type="text" name="board_code" placeholder="Например: NM-K101">
    </label>
    <div class="field full">
      <button type="submit" class="btn btn-primary">+ Добавить</button>
    </div>
  </form>
  <?= render_datalist('boardDeviceTypes', suggest_device_types()) ?>
</div>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Модель</th><th>Тип</th><th>Код платы</th><th>Фото</th></tr></thead>
    <tbody>
      <?php if (!$models): ?>
        <tr><td colspan="4" style="color:var(--muted);">
          <?= $q !== '' ? 'Ничего не найдено.' : 'Пока пусто — добавьте первую модель.' ?>
        </td></tr>
      <?php endif; ?>
      <?php foreach ($models as $m): ?>
        <tr>
          <td data-label="Модель"><a href="board_library.php?model=<?= (int) $m['id'] ?>"><?= e($m['name']) ?></a></td>
          <td data-label="Тип"><?= e($m['device_type'] ?: '—') ?></td>
          <td data-label="Код платы"><?= e($m['board_code'] ?: '—') ?></td>
          <td data-label="Фото"><?= (int) $m['photo_count'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
