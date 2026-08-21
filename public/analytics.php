<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();
require_admin(); // финансы/аналитика/склад — не для инженера-приёмщика

$pageTitle = 'Аналитика';
$activeNav = 'analytics';

// ---- период (для устройств/прибыльности; источники клиентов — за всё время) ----
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

// ---- откуда приходят клиенты (за всё время) ----
$stmt = db()->query('SELECT source, COUNT(*) AS c FROM clients GROUP BY source ORDER BY c DESC');
$sources = $stmt->fetchAll();
$totalClients = array_sum(array_column($sources, 'c'));

// ---- выручка по источнику клиента (заказы со статусом "выдан") ----
$stmt = db()->prepare(
    "SELECT c.source, COALESCE(SUM(rp.qty * rp.price * (1 - COALESCE(rp.discount, 0) / 100)), 0) AS revenue, COUNT(DISTINCT r.id) AS orders_count
     FROM clients c
     LEFT JOIN repairs r ON r.client_id = c.id AND r.status = 'выдан' AND DATE(r.created_at) BETWEEN ? AND ?
     LEFT JOIN repair_parts rp ON rp.repair_id = r.id
     GROUP BY c.source
     ORDER BY revenue DESC"
);
$stmt->execute([$from, $to]);
$revenueBySource = $stmt->fetchAll();

// ---- что чаще несут в ремонт ----
$stmt = db()->prepare(
    'SELECT device_type, COUNT(*) AS c
     FROM repairs
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY device_type
     ORDER BY c DESC
     LIMIT 15'
);
$stmt->execute([$from, $to]);
$deviceTypes = $stmt->fetchAll();
$totalDevices = array_sum(array_column($deviceTypes, 'c'));

// ---- прибыльность по типу устройства (заказы "выдан") ----
$stmt = db()->prepare(
    "SELECT r.device_type,
            COUNT(DISTINCT r.id) AS orders_count,
            COALESCE(SUM(rp.qty * rp.price * (1 - COALESCE(rp.discount, 0) / 100)), 0) AS revenue,
            COALESCE(SUM(rp.qty * rp.price * (1 - COALESCE(rp.discount, 0) / 100) - rp.qty * rp.cost), 0) AS margin
     FROM repairs r
     LEFT JOIN repair_parts rp ON rp.repair_id = r.id
     WHERE r.status = 'выдан' AND DATE(r.created_at) BETWEEN ? AND ?
     GROUP BY r.device_type
     ORDER BY margin DESC
     LIMIT 15"
);
$stmt->execute([$from, $to]);
$profitByDevice = $stmt->fetchAll();

require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>Аналитика</h2>
  <div style="display:flex;gap:6px;">
    <a href="analytics.php?period=month" class="btn btn-sm <?= $period === 'month' ? 'btn-primary' : '' ?>">Этот месяц</a>
    <a href="analytics.php?period=last_month" class="btn btn-sm <?= $period === 'last_month' ? 'btn-primary' : '' ?>">Прошлый месяц</a>
    <a href="analytics.php?period=year" class="btn btn-sm <?= $period === 'year' ? 'btn-primary' : '' ?>">Этот год</a>
    <a href="analytics.php?period=all" class="btn btn-sm <?= $period === 'all' ? 'btn-primary' : '' ?>">Всё время</a>
  </div>
</div>
<p style="color:var(--muted);font-size:13px;margin-top:-10px;">
  Устройства и прибыльность — за период «<?= e($periodLabel) ?>» (<?= e($from) ?> — <?= e($to) ?>).
  Источники клиентов показаны за всё время — это база, а не срез по датам.
</p>

<div class="finance-grid" style="margin-bottom:28px;">
  <div>
    <h3 style="color:var(--navy);font-size:16px;">Откуда приходят клиенты</h3>
    <div class="table-card">
      <table>
        <thead><tr><th>Источник</th><th>Клиентов</th><th>Доля</th></tr></thead>
        <tbody>
          <?php if (!$sources): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--muted);">Клиентов пока нет.</td></tr>
          <?php endif; ?>
          <?php foreach ($sources as $s): ?>
            <tr>
              <td data-label="Источник"><?= e(client_source_label($s['source'])) ?></td>
              <td data-label="Клиентов"><?= (int) $s['c'] ?></td>
              <td data-label="Доля"><?= $totalClients > 0 ? round($s['c'] / $totalClients * 100) : 0 ?>%</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <h3 style="color:var(--navy);font-size:16px;">Выручка по источнику клиента</h3>
    <div class="table-card">
      <table>
        <thead><tr><th>Источник</th><th>Заказов</th><th>Выручка</th></tr></thead>
        <tbody>
          <?php if (!$revenueBySource): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--muted);">Нет данных за период.</td></tr>
          <?php endif; ?>
          <?php foreach ($revenueBySource as $r): ?>
            <tr>
              <td data-label="Источник"><?= e(client_source_label($r['source'])) ?></td>
              <td data-label="Заказов"><?= (int) $r['orders_count'] ?></td>
              <td data-label="Выручка"><?= money((float) $r['revenue']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="finance-grid">
    <div class="table-card">
      <table>
        <thead><tr><th>Устройство</th><th>Заказов</th><th>Доля</th></tr></thead>
        <tbody>
          <?php if (!$deviceTypes): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--muted);">Нет данных за период.</td></tr>
          <?php endif; ?>
          <?php foreach ($deviceTypes as $d): ?>
            <tr>
              <td data-label="Устройство"><?= e($d['device_type']) ?></td>
              <td data-label="Заказов"><?= (int) $d['c'] ?></td>
              <td data-label="Доля"><?= $totalDevices > 0 ? round($d['c'] / $totalDevices * 100) : 0 ?>%</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <h3 style="color:var(--navy);font-size:16px;">От чего больше прибыли</h3>
    <div class="table-card">
      <table>
        <thead><tr><th>Устройство</th><th>Выручка</th><th>Прибыль</th></tr></thead>
        <tbody>
          <?php if (!$profitByDevice): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--muted);">Нет завершённых заказов за период.</td></tr>
          <?php endif; ?>
          <?php foreach ($profitByDevice as $p): ?>
            <tr>
              <td data-label="Устройство"><?= e($p['device_type']) ?></td>
              <td data-label="Выручка"><?= money((float) $p['revenue']) ?></td>
              <td data-label="Прибыль" style="color:var(--good);font-weight:600;"><?= money((float) $p['margin']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="font-size:12px;color:var(--muted);margin-top:8px;">
      Прибыль считается только если у комплектующих/услуг заполнена «себестоимость»
      при добавлении в заказ — иначе она будет казаться равной выручке.
    </p>
  </div>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
