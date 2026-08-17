<?php
/**
 * Проверяет статус заказа в Яндекс Пэй/Сплит — JS опрашивает этот
 * эндпоинт каждые 2 секунды, пока покупатель не отсканирует QR и не
 * оплатит (или пока не истечёт время/не будет отменено).
 */
require __DIR__ . '/../../src/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$orderId = get('order_id');
if ($orderId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Не передан order_id.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(yandex_pay_get_order_status($orderId), JSON_UNESCAPED_UNICODE);
