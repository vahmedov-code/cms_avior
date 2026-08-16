<?php
/**
 * Публичная ссылка на акт выполненных работ (для WhatsApp/Telegram/Email,
 * мобильного приложения) — без входа в CRM. Два способа подтвердить
 * доступ:
 *   1) ?id=6&token=... — уникальный токен заказа (public_token), основной
 *      способ, используется мобильным приложением и новыми ссылками;
 *   2) ?order_no=26-005&phone=... — старый способ, для уже разосланных
 *      ранее ссылок.
 */
require __DIR__ . '/../src/bootstrap.php';

function only_digits_ap(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}

$id = (int) get('id');
$token = get('token');
$orderNo = get('order_no');
$phone = get('phone');

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
} elseif ($orderNo !== '' && $phone !== '') {
    $stmt = db()->prepare(
        'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone,
                c.client_type AS client_type, c.contact_person AS client_contact_person,
                c.inn AS client_inn, c.kpp AS client_kpp
         FROM repairs r JOIN clients c ON c.id = r.client_id
         WHERE r.order_no = ? LIMIT 1'
    );
    $stmt->execute([$orderNo]);
    $found = $stmt->fetch();
    if ($found && only_digits_ap($found['client_phone']) === only_digits_ap($phone)) {
        $repair = $found;
    }
}

if (!$repair) {
    http_response_code(404);
    echo 'Акт не найден — проверьте ссылку.';
    exit;
}

$partsStmt = db()->prepare('SELECT * FROM repair_parts WHERE repair_id = ? ORDER BY id');
$partsStmt->execute([(int) $repair['id']]);
$parts = $partsStmt->fetchAll();

echo render_act_page($repair, $parts, true);
