<?php
/**
 * Записывает факт оплаты (нал/безнал) после того, как сама печать чека
 * уже произошла на клиенте (браузер → KkmServer напрямую, минуя сервер —
 * сервер физически не может достучаться до кассы в магазине). Этот
 * эндпоинт просто фиксирует результат в БД для истории/отчётности.
 */
require __DIR__ . '/../../src/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);

$repairId = (int) ($body['repair_id'] ?? 0);
$method = in_array($body['method'] ?? '', ['cash', 'card'], true) ? $body['method'] : null;
$amount = (float) ($body['amount'] ?? 0);
$receiptPrinted = !empty($body['receipt_printed']) ? 1 : 0;
$kkmResponseRaw = $body['kkm_response'] ?? null;
$kkmResponse = is_string($kkmResponseRaw) ? $kkmResponseRaw : json_encode($kkmResponseRaw, JSON_UNESCAPED_UNICODE);

if (!$repairId || !$method || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Некорректные данные запроса.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = db()->prepare(
    'INSERT INTO payments (repair_id, method, amount, receipt_printed, kkm_response, created_by) VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$repairId, $method, $amount, $receiptPrinted, $kkmResponse, current_user()['id'] ?? null]);

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
