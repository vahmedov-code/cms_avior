<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();
require_admin(); // финансы/аналитика/склад — не для инженера-приёмщика

$pageTitle = 'Финансы';
$activeNav = 'finance';

// ---- добавление расхода ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'add_expense') {
    $category = post('category');
    $description = post('description');
    $amount = (float) str_replace(',', '.', post('amount', '0'));
    $expenseDate = post('expense_date') ?: date('Y-m-d');

    if ($category === '' || $amount <= 0) {
        flash_set('Укажите категорию и сумму расхода.', 'error');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO expenses (category, description, amount, expense_date, created_by) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$category, $description ?: null, $amount, $expenseDate, current_user()['id']]);
        flash_set('Расход добавлен.', 'success');
    }
    redirect('finance.php' . (get('period') ? '?period=' . urlencode(get('period')) : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete_expense') {
    $stmt = db()->prepare('DELETE FROM expenses WHERE id = ?');
    $stmt->execute([(int) post('expense_id')]);
    flash_set('Расход удалён.', 'success');
    redirect('finance.php');
}

// ---- период ----
$period = get('period', 'month');
$today = new DateTime();
switch ($period) {
    case 'last_month':
        $from = (new DateTime('first day of last month'))->format('Y-m-d');
        $to = (new DateTime('last day of last month'))->format('Y-m-d');
        $periodLabel = 'Прошлый месяц';
        break;
    case 'year':
        $from = $today->format('Y') . '-01-01';
        $to = $today->format('Y') . '-12-31';
        $periodLabel = 'Этот год';
        break;
    case 'all':
        $from = '2000-01-01';
        $to = '2100-01-01';
        $periodLabel = 'Всё время';
        break;
    case 'month':
    default:
        $from = $today->format('Y-m-01');
        $to = $today->format('Y-m-t');
        $periodLabel = 'Этот месяц';
        $period = 'month';
        break;
}

// ---- выручка и себестоимость по завершённым заказам (статус "выдан") ----
$stmt = db()->prepare(
    "SELECT rp.category,
            SUM(rp.qty * rp.price * (1 - COALESCE(rp.discount, 0) / 100)) AS revenue,
            SUM(rp.qty * rp.cost) AS cogs,
            SUM(rp.qty * rp.price * COALESCE(rp.discount, 0) / 100) AS discount_amount
     FROM repair_parts rp
     JOIN repairs r ON r.id = rp.repair_id
     WHERE r.status = 'выдан' AND DATE(r.created_at) BETWEEN ? AND ?
     GROUP BY rp.category"
);
$stmt->execute([$from, $to]);
$revenueByCategory = ['part' => ['revenue' => 0, 'cogs' => 0, 'discount' => 0], 'service' => ['revenue' => 0, 'cogs' => 0, 'discount' => 0]];
foreach ($stmt->fetchAll() as $row) {
    $revenueByCategory[$row['category']] = [
        'revenue' => (float) $row['revenue'],
        'cogs' => (float) $row['cogs'],
        'discount' => (float) $row['discount_amount'],
    ];
}
$totalRevenue = $revenueByCategory['part']['revenue'] + $revenueByCategory['service']['revenue'];
$totalCogs = $revenueByCategory['part']['cogs'] + $revenueByCategory['service']['cogs'];
$totalDiscount = $revenueByCategory['part']['discount'] + $revenueByCategory['service']['discount'];
$grossProfit = $totalRevenue - $totalCogs;

// ---- выручка по типу заказа ----
$stmt = db()->prepare(
    "SELECT r.order_type, COUNT(DISTINCT r.id) AS orders_count, COALESCE(SUM(rp.qty * rp.price * (1 - COALESCE(rp.discount, 0) / 100)), 0) AS revenue
     FROM repairs r
     LEFT JOIN repair_parts rp ON rp.repair_id = r.id
     WHERE r.status = 'выдан' AND DATE(r.created_at) BETWEEN ? AND ?
     GROUP BY r.order_type"
);
$stmt->execute([$from, $to]);
$byType = $stmt->fetchAll();

// ---- расходы ----
$stmt = db()->prepare('SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC, id DESC');
$stmt->execute([$from, $to]);
$expenses = $stmt->fetchAll();
$totalExpenses = array_sum(array_column($expenses, 'amount'));

$netProfit = $grossProfit - $totalExpenses;

require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>Финансы</h2>
  <div style="display:flex;gap:6px;">
    <a href="finance.php?period=month" class="btn btn-sm <?= $period === 'month' ? 'btn-primary' : '' ?>">Этот месяц</a>
    <a href="finance.php?period=last_month" class="btn btn-sm <?= $period === 'last_month' ? 'btn-primary' : '' ?>">Прошлый месяц</a>
    <a href="finance.php?period=year" class="btn btn-sm <?= $period === 'year' ? 'btn-primary' : '' ?>">Этот год</a>
    <a href="finance.php?period=all" class="btn btn-sm <?= $period === 'all' ? 'btn-primary' : '' ?>">Всё время</a>
  </div>
</div>

<p style="color:var(--muted);font-size:13px;margin-top:-10px;">
  Период: <?= e($periodLabel) ?> (<?= e($from) ?> — <?= e($to) ?>). Выручка считается по заказам
  со статусом «выдан» — то есть завершённым и полученным клиентом.
</p>

<div class="modules" style="grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));margin-bottom:28px;">
  <div class="module-card">
    <div class="module-title" style="color:var(--muted);font-size:13px;font-weight:600;">Выручка</div>
    <div style="font-size:24px;font-weight:700;color:var(--navy);"><?= money($totalRevenue) ?></div>
  </div>
  <div class="module-card">
    <div class="module-title" style="color:var(--muted);font-size:13px;font-weight:600;">Себестоимость</div>
    <div style="font-size:24px;font-weight:700;color:var(--muted);"><?= money($totalCogs) ?></div>
  </div>
  <div class="module-card">
    <div class="module-title" style="color:var(--muted);font-size:13px;font-weight:600;">Сумма скидок</div>
    <div style="font-size:24px;font-weight:700;color:var(--danger);"><?= money($totalDiscount) ?></div>
  </div>
  <div class="module-card">
    <div class="module-title" style="color:var(--muted);font-size:13px;font-weight:600;">Расходы</div>
    <div style="font-size:24px;font-weight:700;color:var(--danger);"><?= money($totalExpenses) ?></div>
  </div>
  <div class="module-card">
    <div class="module-title" style="color:var(--muted);font-size:13px;font-weight:600;">Чистая прибыль</div>
    <div style="font-size:24px;font-weight:700;color:<?= $netProfit >= 0 ? 'var(--good)' : 'var(--danger)' ?>;"><?= money($netProfit) ?></div>
  </div>
</div>

<div class="finance-grid">
  <div>
    <h3 style="color:var(--navy);font-size:16px;">Выручка по типу заказа</h3>
    <div class="table-card" style="margin-bottom:28px;">
      <table>
        <thead><tr><th>Тип</th><th>Заказов</th><th>Выручка</th></tr></thead>
        <tbody>
          <?php if (!$byType): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--muted);">Нет данных за период.</td></tr>
          <?php endif; ?>
          <?php foreach ($byType as $row): ?>
            <tr>
              <td data-label="Тип"><?= e(order_type_label($row['order_type'])) ?></td>
              <td data-label="Заказов"><?= (int) $row['orders_count'] ?></td>
              <td data-label="Выручка"><?= money((float) $row['revenue']) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td data-label="Тип"><strong>Комплектующие / услуги</strong></td>
            <td data-label=""></td>
            <td data-label="Выручка"><?= money($revenueByCategory['part']['revenue']) ?> / <?= money($revenueByCategory['service']['revenue']) ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <h3 style="color:var(--navy);font-size:16px;">Добавить расход</h3>
    <form method="post" class="form-grid" style="max-width:420px;margin-bottom:20px;">
      <input type="hidden" name="action" value="add_expense">
      <label class="field full">Категория
        <input type="text" name="category" placeholder="Аренда / Зарплата / Закупка / Реклама..." required>
      </label>
      <label class="field">Сумма, ₽
        <input type="number" name="amount" min="0" step="1" required>
      </label>
      <label class="field">Дата
        <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>">
      </label>
      <label class="field full">Комментарий
        <input type="text" name="description">
      </label>
      <div class="field full">
        <button type="submit" class="btn btn-primary">Добавить расход</button>
      </div>
    </form>
  </div>
</div>

<h3 style="color:var(--navy);font-size:16px;">Расходы за период</h3>
<div class="table-card">
  <table>
    <thead><tr><th>Дата</th><th>Категория</th><th>Комментарий</th><th>Сумма</th><th></th></tr></thead>
    <tbody>
      <?php if (!$expenses): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--muted);">Расходов за этот период нет.</td></tr>
      <?php endif; ?>
      <?php foreach ($expenses as $ex): ?>
        <tr>
          <td data-label="Дата"><?= date('d.m.Y', strtotime($ex['expense_date'])) ?></td>
          <td data-label="Категория"><?= e($ex['category']) ?></td>
          <td data-label="Комментарий"><?= e($ex['description'] ?? '—') ?></td>
          <td data-label="Сумма"><?= money((float) $ex['amount']) ?></td>
          <td data-label="">
            <form method="post" onsubmit="return confirm('Удалить расход?');">
              <input type="hidden" name="action" value="delete_expense">
              <input type="hidden" name="expense_id" value="<?= (int) $ex['id'] ?>">
              <button type="submit" class="btn btn-sm btn-warn">✕</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
