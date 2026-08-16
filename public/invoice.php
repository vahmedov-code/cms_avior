<?php
/**
 * Счёт на оплату — формируется прямо из данных заказа (комплектующие/
 * услуги, итог), без отдельной формы — как и акт. Показывается в
 * «Печатные документы» только для клиентов-юрлиц (см. repair_view.php).
 */
require __DIR__ . '/../src/bootstrap.php';
require_login();

$id = (int) get('id');

$stmt = db()->prepare(
    'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone,
            c.client_type AS client_type, c.contact_person AS client_contact_person,
            c.inn AS client_inn, c.kpp AS client_kpp
     FROM repairs r JOIN clients c ON c.id = r.client_id
     WHERE r.id = ?'
);
$stmt->execute([$id]);
$repair = $stmt->fetch();

if (!$repair) {
    flash_set('Заказ не найден.', 'error');
    redirect('repairs.php');
}

$partsStmt = db()->prepare('SELECT * FROM repair_parts WHERE repair_id = ? ORDER BY id');
$partsStmt->execute([$id]);
$parts = $partsStmt->fetchAll();

echo render_invoice_page($repair, $parts);
