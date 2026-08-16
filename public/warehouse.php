<?php
/**
 * Склад — остатки комплектующих (parts_catalog.stock_qty) + журнал
 * движений (stock_movements). Расход по заказам списывается автоматически
 * (см. adjust_stock_for_part_usage() в functions.php, вызывается из
 * add_part/delete_part в repair_view.php) — здесь только приход и ручной
 * расход (списание/коррекция), плюс обзор остатков и история.
 */
require __DIR__ . '/../src/bootstrap.php';
require_login();

$pageTitle = 'Склад';
$activeNav = 'warehouse';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'stock_in') {
    $name = trim(post('name'));
    $qty = (float) str_replace(',', '.', post('qty', '0'));
    $price = post('price') !== '' ? (float) str_replace(',', '.', post('price')) : null;
    $reason = trim(post('reason')) ?: 'Приход';

    if ($name === '' || $qty <= 0) {
        flash_set('Укажите название и положительное количество.', 'error');
    } else {
        // заводим позицию в каталоге, если её ещё нет (тот же паттерн, что в repair_view.php)
        $cat = db()->prepare(
            'INSERT INTO parts_catalog (name, price) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE price = IF(? IS NOT NULL, ?, price)'
        );
        $cat->execute([$name, $price ?? 0, $price, $price]);

        $idStmt = db()->prepare('SELECT id FROM parts_catalog WHERE name = ?');
        $idStmt->execute([$name]);
        $partId = $idStmt->fetchColumn();

        db()->prepare('UPDATE parts_catalog SET stock_qty = stock_qty + ? WHERE id = ?')->execute([$qty, $partId]);
        db()->prepare(
            'INSERT INTO stock_movements (part_id, type, qty, reason, created_by) VALUES (?, ?, ?, ?, ?)'
        )->execute([$partId, 'in', $qty, $reason, current_user()['id'] ?? null]);

        flash_set('Приход добавлен: ' . e($name) . ' +' . rtrim(rtrim((string) $qty, '0'), '.'), 'success');
    }
    redirect('warehouse.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'stock_out') {
    $partId = (int) post('part_id');
    $qty = (float) str_replace(',', '.', post('qty', '0'));
    $reason = trim(post('reason'));

    if (!$partId || $qty <= 0 || $reason === '') {
        flash_set('Выберите позицию, укажите количество и причину списания.', 'error');
    } else {
        db()->prepare('UPDATE parts_catalog SET stock_qty = stock_qty - ? WHERE id = ?')->execute([$qty, $partId]);
        db()->prepare(
            'INSERT INTO stock_movements (part_id, type, qty, reason, created_by) VALUES (?, ?, ?, ?, ?)'
        )->execute([$partId, 'out', $qty, $reason, current_user()['id'] ?? null]);
        flash_set('Списание добавлено.', 'success');
    }
    redirect('warehouse.php');
}

$filter = get('filter', 'all');
$where = '';
if ($filter === 'low') {
    $where = 'WHERE stock_qty > 0 AND stock_qty <= 2';
} elseif ($filter === 'zero') {
    $where = 'WHERE stock_qty <= 0';
}
$parts = db()->query("SELECT * FROM parts_catalog $where ORDER BY name")->fetchAll();

$movements = db()->query(
    "SELECT m.*, p.name AS part_name, r.order_no, u.full_name AS user_name
     FROM stock_movements m
     JOIN parts_catalog p ON p.id = m.part_id
     LEFT JOIN repairs r ON r.id = m.repair_id
     LEFT JOIN users u ON u.id = m.created_by
     ORDER BY m.created_at DESC LIMIT 50"
)->fetchAll();

require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>📦 Склад</h2>
</div>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
  <a href="warehouse.php" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : '' ?>">Все позиции</a>
  <a href="warehouse.php?filter=low" class="btn btn-sm <?= $filter === 'low' ? 'btn-primary' : '' ?>">Мало (≤2)</a>
  <a href="warehouse.php?filter=zero" class="btn btn-sm <?= $filter === 'zero' ? 'btn-primary' : '' ?>">Нет в наличии</a>
</div>

<div class="finance-grid" style="margin-bottom:28px;">
  <div>
    <h3 style="color:var(--navy);font-size:16px;">Приход</h3>
    <form method="post" class="form-grid" style="max-width:420px;">
      <input type="hidden" name="action" value="stock_in">
      <label class="field full">Название
        <input type="text" name="name" list="partNamesList" required>
      </label>
      <label class="field">Количество
        <input type="number" name="qty" min="0.01" step="1" required>
      </label>
      <label class="field">Цена, ₽ (необязательно — обновит цену в каталоге)
        <input type="number" name="price" min="0" step="1">
      </label>
      <label class="field full">Комментарий
        <input type="text" name="reason" placeholder="Например: закупка у поставщика">
      </label>
      <div class="field full">
        <button type="submit" class="btn btn-primary">+ Оприходовать</button>
      </div>
    </form>
    <?= render_datalist('partNamesList', suggest_part_names()) ?>
  </div>

  <div>
    <h3 style="color:var(--navy);font-size:16px;">Ручное списание</h3>
    <p style="color:var(--muted);font-size:12px;margin-top:-8px;">
      Для брака, недостачи, продажи отдельно от заказа и т.п. Расход по
      заказам списывается сам, сюда вписывать не нужно.
    </p>
    <form method="post" class="form-grid" style="max-width:420px;">
      <input type="hidden" name="action" value="stock_out">
      <label class="field full">Позиция
        <select name="part_id" required>
          <option value="">— выбрать —</option>
          <?php foreach ($parts as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> (остаток: <?= rtrim(rtrim((string) (float) $p['stock_qty'], '0'), '.') ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">Количество
        <input type="number" name="qty" min="0.01" step="1" required>
      </label>
      <label class="field full">Причина (обязательно)
        <input type="text" name="reason" placeholder="Например: брак, недостача" required>
      </label>
      <div class="field full">
        <button type="submit" class="btn btn-warn">− Списать</button>
      </div>
    </form>
  </div>
</div>

<h3 style="color:var(--navy);font-size:16px;">Остатки</h3>
<div class="table-card" style="margin-bottom:28px;">
  <table>
    <thead><tr><th>Название</th><th>Остаток</th><th>Цена</th><th>Обновлено</th></tr></thead>
    <tbody>
      <?php if (!$parts): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--muted);">Ничего не найдено.</td></tr>
      <?php endif; ?>
      <?php foreach ($parts as $p): ?>
        <?php $qtyNum = (float) $p['stock_qty']; ?>
        <tr>
          <td data-label="Название"><?= e($p['name']) ?></td>
          <td data-label="Остаток" style="<?= $qtyNum <= 0 ? 'color:var(--danger);font-weight:700;' : ($qtyNum <= 2 ? 'color:#8a6d1f;font-weight:700;' : '') ?>">
            <?= rtrim(rtrim((string) $qtyNum, '0'), '.') ?>
          </td>
          <td data-label="Цена"><?= money((float) $p['price']) ?></td>
          <td data-label="Обновлено"><?= date('d.m.Y', strtotime($p['updated_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<h3 style="color:var(--navy);font-size:16px;">История движений (последние 50)</h3>
<div class="table-card">
  <table>
    <thead><tr><th>Дата</th><th>Позиция</th><th>Тип</th><th>Кол-во</th><th>Причина / заказ</th><th>Кто</th></tr></thead>
    <tbody>
      <?php if (!$movements): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted);">Движений пока нет.</td></tr>
      <?php endif; ?>
      <?php foreach ($movements as $m): ?>
        <tr>
          <td data-label="Дата"><?= date('d.m.Y H:i', strtotime($m['created_at'])) ?></td>
          <td data-label="Позиция"><?= e($m['part_name']) ?></td>
          <td data-label="Тип"><?= $m['type'] === 'in' ? '↑ Приход' : '↓ Расход' ?></td>
          <td data-label="Кол-во"><?= rtrim(rtrim((string) (float) $m['qty'], '0'), '.') ?></td>
          <td data-label="Причина / заказ">
            <?php if ($m['order_no']): ?>
              <a href="repair_view.php?id=<?= (int) $m['repair_id'] ?>"><?= e($m['order_no']) ?></a>
            <?php else: ?>
              <?= e($m['reason'] ?? '—') ?>
            <?php endif; ?>
          </td>
          <td data-label="Кто"><?= e($m['user_name'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
