<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$pageTitle = 'Заказы';
$activeNav = 'repairs';

$statusFilter = get('status');
$typeFilter = get('type');
$statuses = ['принят', 'диагностика', 'согласование', 'в ремонте', 'готов', 'выдан', 'отказ'];

// Поиск: номер заказа, содержимое QR-кода с квитанции/статуса (там есть
// order_no в ссылке) или клиент/телефон.
$q = trim(get('q'));
if ($q !== '') {
    $parsedQuery = parse_url($q, PHP_URL_QUERY);
    if ($parsedQuery) {
        parse_str($parsedQuery, $qsParams);
        if (!empty($qsParams['order_no'])) {
            $q = $qsParams['order_no'];
        }
    }
}

// Точное совпадение по номеру заказа — сразу открыть заказ (удобно при
// сканировании QR сканером-«клавиатурой»: ввёл — и попал прямо в заказ).
if ($q !== '') {
    $stmt = db()->prepare('SELECT id FROM repairs WHERE order_no = ? LIMIT 1');
    $stmt->execute([$q]);
    $exactMatch = $stmt->fetch();
    if ($exactMatch) {
        redirect('repair_view.php?id=' . (int) $exactMatch['id']);
    }
}

$sql = "SELECT r.*, c.full_name AS client_name, c.phone AS client_phone,
               COALESCE((SELECT SUM(qty * price) FROM repair_parts WHERE repair_id = r.id), 0) AS parts_total
        FROM repairs r JOIN clients c ON c.id = r.client_id";
$where = [];
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, $statuses, true)) {
    $where[] = 'r.status = ?';
    $params[] = $statusFilter;
}
if ($typeFilter !== '' && array_key_exists($typeFilter, order_types())) {
    $where[] = 'r.order_type = ?';
    $params[] = $typeFilter;
}
if ($q !== '') {
    $where[] = '(r.order_no LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY r.created_at DESC LIMIT 300';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$repairs = $stmt->fetchAll();

require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>Заказы</h2>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="repair_new.php" class="btn btn-primary">+ Ремонт</a>
    <a href="pc_build_new.php" class="btn">+ Сборка ПК</a>
    <a href="account_memo_new.php" class="btn">+ Памятка</a>
  </div>
</div>

<form method="get" style="margin-bottom:14px;display:flex;gap:8px;max-width:460px;flex-wrap:wrap;">
  <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
  <?php if ($typeFilter !== ''): ?><input type="hidden" name="type" value="<?= e($typeFilter) ?>"><?php endif; ?>
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Номер заказа, QR-код, клиент или телефон" autofocus style="flex:1;min-width:220px;padding:8px 10px;border:1px solid var(--border);border-radius:6px;font:inherit;">
  <button type="submit" class="btn btn-primary">Найти</button>
  <?php if ($q !== ''): ?>
    <a href="repairs.php<?= ($statusFilter !== '' || $typeFilter !== '') ? '?' . http_build_query(array_filter(['status' => $statusFilter, 'type' => $typeFilter])) : '' ?>" class="btn btn-sm">✕ Сбросить</a>
  <?php endif; ?>
</form>

<div style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap;">
  <a href="repairs.php" class="btn btn-sm <?= $typeFilter === '' ? 'btn-primary' : '' ?>">Все типы</a>
  <?php foreach (order_types() as $tKey => $tLabel): ?>
    <a href="repairs.php?type=<?= urlencode($tKey) ?>" class="btn btn-sm <?= $typeFilter === $tKey ? 'btn-primary' : '' ?>"><?= e($tLabel) ?></a>
  <?php endforeach; ?>
</div>

<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;">
  <a href="repairs.php" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : '' ?>">Все статусы</a>
  <?php foreach ($statuses as $s): ?>
    <a href="repairs.php?status=<?= urlencode($s) ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-primary' : '' ?>"><?= e($s) ?></a>
  <?php endforeach; ?>
</div>

<div class="table-card">
  <table>
    <thead>
      <tr>
        <th>№ заказа</th>
        <th>Тип</th>
        <th>Клиент</th>
        <th>Устройство</th>
        <th>Статус</th>
        <th>Сумма</th>
        <th>Обновлён</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$repairs): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--muted);">Заказов не найдено.</td></tr>
      <?php endif; ?>
      <?php foreach ($repairs as $r): ?>
        <tr>
          <td data-label="№ заказа"><a href="repair_view.php?id=<?= (int) $r['id'] ?>"><?= e($r['order_no']) ?></a></td>
          <td data-label="Тип"><?= e(order_type_label($r['order_type'] ?? 'repair')) ?></td>
          <td data-label="Клиент"><?= e($r['client_name']) ?><br><span style="color:var(--muted);font-size:12px;"><?= e($r['client_phone']) ?></span></td>
          <td data-label="Устройство"><?= e($r['device_type']) ?> <?= e($r['device_model'] ?? '') ?></td>
          <td data-label="Статус"><span class="status-badge" data-status="<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
          <td data-label="Сумма"><?= money((float) $r['parts_total']) ?></td>
          <td data-label="Обновлён"><?= date('d.m.Y H:i', strtotime($r['updated_at'])) ?></td>
          <td data-label="" style="white-space:nowrap;"><a href="repair_view.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm">Открыть</a> <a href="repair_receipt.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm" title="Квитанция о приёмке">🧾</a> <a href="repair_act.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm" title="Акт выполненных работ">📋</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
