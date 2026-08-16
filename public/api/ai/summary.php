<?php
/**
 * Сводка по сервису для внешнего анализа (изначально — чтобы ассистент
 * Claude мог отвечать на вопросы вроде «как дела в сервисе» без входа
 * в CRM). Read-only — ничего не создаёт и не меняет.
 *
 * GET /api/ai/summary.php?token=...&period=month|last_month|year|all
 *
 * Авторизация — отдельный токен (не тот же, что api_tokens для
 * мобильного приложения): настраивается в CRM → Настройки → «Токен для
 * AI-сводки» (settings.php, ключ ai_api_token). Без токена в settings —
 * эндпоинт отключён (403), ничего не отдаёт.
 *
 * period по умолчанию — month. Список статусов, попадающих в pipeline-
 * счётчики, — та же группировка, что на панели (index.php): Новые/
 * В работе/Отложенные/Готовые/Выданные, отказ не считается.
 */
require __DIR__ . '/../../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function ai_summary_fail(int $httpCode, string $error): void
{
    http_response_code($httpCode);
    echo json_encode(['error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

$configuredToken = get_setting('ai_api_token');
if (!$configuredToken) {
    ai_summary_fail(403, 'AI-сводка не настроена. Задайте токен в CRM → Настройки.');
}

$providedToken = get('token');
if ($providedToken === '') {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (stripos($header, 'Bearer ') === 0) {
        $providedToken = substr($header, 7);
    }
}
if (!hash_equals($configuredToken, (string) $providedToken)) {
    ai_summary_fail(401, 'Неверный или отсутствующий токен.');
}

[$from, $to, $periodLabel, $periodKey] = resolve_period(get('period', 'month'));

// ---- pipeline-счётчики (та же группировка, что на панели) ----
$statusCounts = array_fill_keys(
    ['принят', 'диагностика', 'согласование', 'в ремонте', 'готов', 'выдан', 'отказ'],
    0
);
foreach (db()->query('SELECT status, COUNT(*) c FROM repairs GROUP BY status') as $row) {
    $statusCounts[$row['status']] = (int) $row['c'];
}

// ---- финансы за период (та же логика, что finance.php: выручка/себестоимость по заказам "выдан") ----
$stmt = db()->prepare(
    "SELECT rp.category, SUM(rp.qty * rp.price) AS revenue, SUM(rp.qty * rp.cost) AS cogs
     FROM repair_parts rp
     JOIN repairs r ON r.id = rp.repair_id
     WHERE r.status = 'выдан' AND DATE(r.created_at) BETWEEN ? AND ?
     GROUP BY rp.category"
);
$stmt->execute([$from, $to]);
$revenue = 0.0;
$cogs = 0.0;
foreach ($stmt->fetchAll() as $row) {
    $revenue += (float) $row['revenue'];
    $cogs += (float) $row['cogs'];
}
$stmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) AS s FROM expenses WHERE expense_date BETWEEN ? AND ?');
$stmt->execute([$from, $to]);
$expenses = (float) $stmt->fetch()['s'];
$netProfit = ($revenue - $cogs) - $expenses;

// ---- клиенты ----
$clientsTotal = (int) db()->query('SELECT COUNT(*) c FROM clients')->fetch()['c'];
$stmt = db()->prepare('SELECT COUNT(*) c FROM clients WHERE DATE(created_at) BETWEEN ? AND ?');
$stmt->execute([$from, $to]);
$newClientsInPeriod = (int) $stmt->fetch()['c'];

// ---- популярные типы устройств за период (как analytics.php) ----
$stmt = db()->prepare(
    'SELECT device_type, COUNT(*) AS c FROM repairs
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY device_type ORDER BY c DESC LIMIT 5'
);
$stmt->execute([$from, $to]);
$topDeviceTypes = $stmt->fetchAll();

// ---- последние 10 заказов ----
$recentOrders = db()->query(
    "SELECT r.order_no, r.order_type, r.status, r.device_type, r.device_model,
            c.full_name AS client_name, r.updated_at
     FROM repairs r JOIN clients c ON c.id = r.client_id
     ORDER BY r.updated_at DESC LIMIT 10"
)->fetchAll();

echo json_encode([
    'generated_at' => date('c'),
    'period'        => ['key' => $periodKey, 'label' => $periodLabel, 'from' => $from, 'to' => $to],
    'pipeline'      => [
        'new'        => $statusCounts['принят'],
        'in_progress' => $statusCounts['диагностика'] + $statusCounts['в ремонте'],
        'on_hold'    => $statusCounts['согласование'],
        'ready'      => $statusCounts['готов'],
        'issued'     => $statusCounts['выдан'],
        'refused'    => $statusCounts['отказ'],
    ],
    'finance'       => [
        'revenue'    => round($revenue, 2),
        'cogs'       => round($cogs, 2),
        'expenses'   => round($expenses, 2),
        'net_profit' => round($netProfit, 2),
        'note'       => 'Выручка/себестоимость — по заказам со статусом "выдан", созданным в этом периоде (та же логика, что в CRM на странице Финансы).',
    ],
    'clients'       => [
        'total'          => $clientsTotal,
        'new_in_period'  => $newClientsInPeriod,
    ],
    'top_device_types' => array_map(fn($r) => ['device_type' => $r['device_type'], 'count' => (int) $r['c']], $topDeviceTypes),
    'recent_orders'    => array_map(fn($r) => [
        'order_no'    => $r['order_no'],
        'order_type'  => $r['order_type'],
        'status'      => $r['status'],
        'device'      => trim($r['device_type'] . ' ' . ($r['device_model'] ?? '')),
        'client_name' => $r['client_name'],
        'updated_at'  => $r['updated_at'],
    ], $recentOrders),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
