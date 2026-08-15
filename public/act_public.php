<?php
/**
 * Публичная ссылка на акт выполненных работ (для WhatsApp/Telegram/Email) —
 * без входа в CMS. Требует order_no + phone, как и остальные публичные
 * страницы заказа (order_status.php, receipt_public.php).
 */
require __DIR__ . '/../src/bootstrap.php';

function only_digits_ap(string $s): string
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

if (!$repair || only_digits_ap($repair['client_phone']) !== only_digits_ap($phone)) {
    http_response_code(404);
    echo 'Акт не найден — проверьте ссылку.';
    exit;
}

$partsStmt = db()->prepare('SELECT * FROM repair_parts WHERE repair_id = ? ORDER BY id');
$partsStmt->execute([(int) $repair['id']]);
$parts = $partsStmt->fetchAll();

echo render_act_page($repair, $parts, true);
