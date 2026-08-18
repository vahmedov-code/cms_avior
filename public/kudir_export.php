<?php
/**
 * КУДиР (книга учёта доходов) — для ИП на патенте (ПСН) обязательна к
 * ведению, но не сдаётся проактивно, только предъявляется по запросу
 * при проверке. Форма — «Дата и номер документа / Содержание операции /
 * Сумма», по приказу Минфина для упрощённой книги на ПСН (без графы
 * расходов — на патенте расходы для налога не важны, платёж
 * фиксированный).
 *
 * Источник данных — таблица payments (реально полученные деньги, не
 * оценка/не выставленный счёт): каждая фактическая оплата — отдельная
 * строка книги. Экспорт — CSV с BOM (открывается в Excel без вопросов
 * с кодировкой кириллицы).
 *
 * Часть будущей интеграции с налоговыми операторами (СБИС и др. —
 * обсуждали 19.08) — начали с того, что не требует внешнего API:
 * сама книга уже у нас есть в данных, просто раньше не было готового
 * экспорта в нужном виде.
 */
require __DIR__ . '/../src/bootstrap.php';
require_login();
require_admin(); // финансовый документ — не для инженера-приёмщика

$pageTitle = 'КУДиР';
$activeNav = 'kudir';

$period = get('period', 'year');
$today = new DateTime();
switch ($period) {
    case 'last_year':
        $from = ($today->format('Y') - 1) . '-01-01';
        $to = ($today->format('Y') - 1) . '-12-31';
        $periodLabel = 'Прошлый год';
        break;
    case 'all':
        $from = '2000-01-01';
        $to = '2100-01-01';
        $periodLabel = 'Всё время';
        break;
    case 'year':
    default:
        $from = $today->format('Y') . '-01-01';
        $to = $today->format('Y') . '-12-31';
        $periodLabel = 'Этот год';
        $period = 'year';
        break;
}

$methodLabels = ['cash' => 'наличные', 'card' => 'безналичный расчёт', 'manual' => 'без чека (отмечено вручную)'];

$stmt = db()->prepare(
    'SELECT p.id, p.created_at, p.amount, p.method, r.id AS repair_id, r.order_no, c.full_name
     FROM payments p
     JOIN repairs r ON r.id = p.repair_id
     JOIN clients c ON c.id = r.client_id
     WHERE p.created_at BETWEEN ? AND ?
     ORDER BY p.created_at'
);
$stmt->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
$rows = $stmt->fetchAll();

$total = 0.0;
foreach ($rows as $r) {
    $total += (float) $r['amount'];
}

if (get('download') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="KUDiR_' . $period . '_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM — чтобы Excel сразу правильно показал кириллицу

    $out = fopen('php://output', 'w');
    fputcsv($out, ['№ п/п', 'Дата и номер документа', 'Содержание операции', 'Сумма, руб.'], ';');
    $i = 1;
    foreach ($rows as $r) {
        $docDate = date('d.m.Y', strtotime($r['created_at']));
        $docNumber = 'Заказ ' . $r['order_no'];
        $content = 'Оплата по заказу ' . $r['order_no'] . ' (' . $r['full_name'] . ', ' . ($methodLabels[$r['method']] ?? $r['method']) . ')';
        fputcsv($out, [$i, $docDate . ', ' . $docNumber, $content, money_plain((float) $r['amount'])], ';');
        $i++;
    }
    fputcsv($out, ['', '', 'Итого:', money_plain($total)], ';');
    fclose($out);
    exit;
}

require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>📗 КУДиР</h2>
</div>

<p style="color:var(--muted);font-size:13px;max-width:640px;">
  Книга учёта доходов — обязательна к ведению на патенте (ПСН), но не
  сдаётся проактивно в налоговую, только предъявляется по требованию
  при проверке. Строится автоматически из фактических поступлений
  (раздел «Оплата» в заказах) — ничего вручную вести не нужно.
</p>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin:16px 0;">
  <a href="kudir_export.php?period=year" class="btn btn-sm <?= $period === 'year' ? 'btn-primary' : '' ?>">Этот год</a>
  <a href="kudir_export.php?period=last_year" class="btn btn-sm <?= $period === 'last_year' ? 'btn-primary' : '' ?>">Прошлый год</a>
  <a href="kudir_export.php?period=all" class="btn btn-sm <?= $period === 'all' ? 'btn-primary' : '' ?>">Всё время</a>
  <a href="kudir_export.php?period=<?= e($period) ?>&download=1" class="btn btn-primary btn-sm">⬇️ Скачать CSV</a>
</div>

<p style="color:var(--muted);font-size:13px;">
  Период: <?= e($periodLabel) ?> (<?= e($from) ?> — <?= e($to) ?>). Всего записей: <?= count($rows) ?>. Сумма: <strong><?= money($total) ?></strong>
</p>

<div class="table-card">
  <table>
    <thead><tr><th>№</th><th>Дата и номер документа</th><th>Содержание операции</th><th>Сумма</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--muted);">Оплат за период не найдено.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td data-label="№"><?= $i + 1 ?></td>
          <td data-label="Документ"><?= e(date('d.m.Y', strtotime($r['created_at']))) ?>, заказ <a href="repair_view.php?id=<?= (int) $r['repair_id'] ?>"><?= e($r['order_no']) ?></a></td>
          <td data-label="Содержание">Оплата по заказу <?= e($r['order_no']) ?> (<?= e($r['full_name']) ?>, <?= e($methodLabels[$r['method']] ?? $r['method']) ?>)</td>
          <td data-label="Сумма"><?= money((float) $r['amount']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
