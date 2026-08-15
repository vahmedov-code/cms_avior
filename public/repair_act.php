<?php
/**
 * Акт сдачи-приёмки выполненных работ — формируется прямо из данных заказа
 * (комплектующие/услуги, итог), без отдельной формы: то, что добавлено в
 * заказ на странице repair_view.php, попадает в акт автоматически.
 * Стиль и текст — по образцу, который прислал Вейс (акт.pdf).
 */
require __DIR__ . '/../src/bootstrap.php';
require_login();

$id = (int) get('id');

$stmt = db()->prepare(
    'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone
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

echo render_act_page($repair, $parts);
