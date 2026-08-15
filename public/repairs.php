<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$pageTitle = 'Ремонты';
$activeNav = 'repairs';

$statusFilter = get('status');
$statuses = ['принят', 'диагностика', 'согласование', 'в ремонте', 'готов', 'выдан', 'отказ'];

$sql = 'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone
        FROM repairs r JOIN clients c ON c.id = r.client_id';
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, $statuses, true)) {
    $sql .= ' WHERE r.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY r.created_at DESC LIMIT 300';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$repairs = $stmt->fetchAll();

require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>Ремонты</h2>
  <a href="repair_new.php" class="btn btn-primary">+ Новый заказ</a>
</div>

<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;">
  <a href="repairs.php" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : '' ?>">Все</a>
  <?php foreach ($statuses as $s): ?>
    <a href="repairs.php?status=<?= urlencode($s) ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-primary' : '' ?>"><?= e($s) ?></a>
  <?php endforeach; ?>
</div>

<div class="table-card">
  <table>
    <thead>
      <tr>
        <th>№ заказа</th>
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
        <tr><td colspan="7" style="text-align:center;color:var(--muted);">Заказов не найдено.</td></tr>
      <?php endif; ?>
      <?php foreach ($repairs as $r): ?>
        <tr>
          <td data-label="№ заказа"><a href="repair_view.php?id=<?= (int) $r['id'] ?>"><?= e($r['order_no']) ?></a></td>
          <td data-label="Клиент"><?= e($r['client_name']) ?><br><span style="color:var(--muted);font-size:12px;"><?= e($r['client_phone']) ?></span></td>
          <td data-label="Устройство"><?= e($r['device_type']) ?> <?= e($r['device_model'] ?? '') ?></td>
          <td data-label="Статус"><span class="status-badge" data-status="<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
          <td data-label="Сумма"><?= money((float) ($r['price_final'] ?? $r['price_estimate'])) ?></td>
          <td data-label="Обновлён"><?= date('d.m.Y H:i', strtotime($r['updated_at'])) ?></td>
          <td data-label=""><a href="repair_view.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm">Открыть</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
