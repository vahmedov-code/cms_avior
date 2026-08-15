<?php
/**
 * Смена статуса заказа — POST { "id": 12, "status": "в ремонте", "comment": "..." }.
 * Требует токен. Пишет запись в repair_status_log (changed_by = автор из токена).
 */
require __DIR__ . '/../../../src/bootstrap.php';
require __DIR__ . '/../../../src/api_helpers.php';
api_bootstrap();
$user = api_require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Используйте POST', 405);
}

$validStatuses = ['принят', 'диагностика', 'согласование', 'в ремонте', 'готов', 'выдан', 'отказ'];

$body = json_body();
$id = (int) ($body['id'] ?? 0);
$status = (string) ($body['status'] ?? '');
$comment = trim((string) ($body['comment'] ?? ''));

if ($id <= 0 || !in_array($status, $validStatuses, true)) {
    api_error('Укажите корректные id и status. Допустимые статусы: ' . implode(', ', $validStatuses), 422);
}

$stmt = db()->prepare('SELECT id FROM repairs WHERE id = ?');
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    api_error('Заказ не найден', 404);
}

db()->prepare('UPDATE repairs SET status = ? WHERE id = ?')->execute([$status, $id]);
db()->prepare('INSERT INTO repair_status_log (repair_id, status, comment, changed_by) VALUES (?, ?, ?, ?)')
    ->execute([$id, $status, $comment ?: null, $user['id']]);

api_json(['ok' => true]);
