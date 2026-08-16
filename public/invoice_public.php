<?php
/**
 * Публичная ссылка на счёт на оплату (для WhatsApp/Telegram/Email) —
 * без входа в CRM. Только по токену (?id=6&token=...) — в отличие от
 * квитанции/акта, у счёта нет старого формата ссылок order_no+phone,
 * этот документ появился позже и раздаётся только новым способом.
 */
require __DIR__ . '/../src/bootstrap.php';

$id = (int) get('id');
$token = get('token');

$repair = null;

if ($id > 0 && $token !== '') {
    $stmt = db()->prepare(
        'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone,
                c.client_type AS client_type, c.contact_person AS client_contact_person,
                c.inn AS client_inn, c.kpp AS client_kpp
         FROM repairs r JOIN clients c ON c.id = r.client_id
         WHERE r.id = ? AND r.public_token = ? LIMIT 1'
    );
    $stmt->execute([$id, $token]);
    $repair = $stmt->fetch();
}

if (!$repair) {
    http_response_code(404);
    echo 'Счёт не найден — проверьте ссылку.';
    exit;
}

$partsStmt = db()->prepare('SELECT * FROM repair_parts WHERE repair_id = ? ORDER BY id');
$partsStmt->execute([(int) $repair['id']]);
$parts = $partsStmt->fetchAll();

echo render_invoice_page($repair, $parts, true);
