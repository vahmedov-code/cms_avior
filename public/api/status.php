<?php
/**
 * Публичный API проверки статуса ремонта — для виджета на сайте.
 *
 * GET /api/status.php?order_no=26-001&phone=9991234567
 *
 * Требует ОБА параметра (номер заказа + телефон), чтобы нельзя было
 * перебором номеров узнать чужой статус/данные клиента.
 *
 * Пример виджета для сайта (вставить в HTML-страницу):
 *
 * <script>
 * fetch('https://ваш-сайт/cms/api/status.php?order_no=' + orderNo + '&phone=' + phone)
 *   .then(r => r.json())
 *   .then(data => { if (data.found) { ... показать data.status ... } });
 * </script>
 */

require __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

function only_digits(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}

$orderNo = get('order_no');
$phone = get('phone');

if ($orderNo === '' || $phone === '') {
    http_response_code(400);
    echo json_encode(['found' => false, 'error' => 'order_no and phone are required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$phoneDigits = only_digits($phone);

$stmt = db()->prepare(
    'SELECT r.order_no, r.status, r.device_type, r.device_model, r.updated_at, c.phone
     FROM repairs r JOIN clients c ON c.id = r.client_id
     WHERE r.order_no = ?
     LIMIT 1'
);
$stmt->execute([$orderNo]);
$repair = $stmt->fetch();

if (!$repair || only_digits($repair['phone']) !== $phoneDigits) {
    echo json_encode(['found' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'found'        => true,
    'order_no'     => $repair['order_no'],
    'status'       => $repair['status'],
    'device'       => trim($repair['device_type'] . ' ' . ($repair['device_model'] ?? '')),
    'updated_at'   => $repair['updated_at'],
], JSON_UNESCAPED_UNICODE);
