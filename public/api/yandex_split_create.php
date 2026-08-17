<?php
/**
 * Создаёт заказ на оплату через Яндекс Пэй/Сплит (динамический QR) для
 * конкретного заказа CRM. Вызывается из repair_view.php по клику
 * «Яндекс Сплит». Автоматически добавляет позицию «Расширенная гарантия»
 * (15% от суммы позиций) — по требованию бизнеса, только для этого
 * способа оплаты.
 */
require __DIR__ . '/../../src/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);
$repairId = (int) ($body['repair_id'] ?? 0);

if (!$repairId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Некорректный ID заказа.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$partsStmt = db()->prepare('SELECT * FROM repair_parts WHERE repair_id = ? ORDER BY id');
$partsStmt->execute([$repairId]);
$parts = $partsStmt->fetchAll();

$warrantyPrice = extended_warranty_price($parts);
$result = yandex_pay_create_split_order($repairId, $parts, $warrantyPrice);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
