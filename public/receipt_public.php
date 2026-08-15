<?php
/**
 * Публичная ссылка на квитанцию о приёмке (для WhatsApp/Telegram/Email) —
 * без входа в CMS. Требует order_no + phone (как order_status.php и
 * api/status.php), чтобы нельзя было перебором номеров посмотреть чужую
 * квитанцию.
 */
require __DIR__ . '/../src/bootstrap.php';

function only_digits_rp(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}

$orderNo = get('order_no');
$phone = get('phone');

if ($orderNo === '' || $phone === '') {
    http_response_code(404);
    echo 'Не найдено.';
    exit;
}

$stmt = db()->prepare(
    'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone
     FROM repairs r JOIN clients c ON c.id = r.client_id
     WHERE r.order_no = ? LIMIT 1'
);
$stmt->execute([$orderNo]);
$repair = $stmt->fetch();

if (!$repair || only_digits_rp($repair['client_phone']) !== only_digits_rp($phone)) {
    http_response_code(404);
    echo 'Квитанция не найдена — проверьте ссылку.';
    exit;
}

if (!$repair['receipt_ready']) {
    http_response_code(404);
    echo 'Квитанция по этому заказу ещё не оформлена.';
    exit;
}

echo render_receipt_page($repair, true);
