<?php
/**
 * Эндпоинт для меток на фото (создать/изменить/удалить), отвечает JSON.
 * scope=board  -> board_markers / board_photos
 * scope=repair -> repair_photo_markers / repair_photos
 */
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/board_helpers.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

function api_fail(string $message): void
{
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_fail('Только POST');
}

$sessionToken = $_SESSION['csrf_token'] ?? '';
$submitted = $_POST['csrf_token'] ?? '';
if ($sessionToken === '' || $submitted === '' || !hash_equals($sessionToken, $submitted)) {
    api_fail('Сессия обновилась, перезагрузите страницу.');
}

$scope = post('scope');
if ($scope === 'board') {
    $markerTable = 'board_markers';
    $photoTable = 'board_photos';
} elseif ($scope === 'repair') {
    $markerTable = 'repair_photo_markers';
    $photoTable = 'repair_photos';
} else {
    api_fail('Неизвестный тип фото');
}

$action = post('action');
$photoId = (int) post('photo_id');
$userId = current_user()['id'] ?? null;

$check = db()->prepare("SELECT id FROM {$photoTable} WHERE id = ?");
$check->execute([$photoId]);
if (!$check->fetchColumn()) {
    api_fail('Фото не найдено');
}

$designator = trim(post('designator')) ?: null;
$value = trim(post('value')) ?: null;
$note = trim(post('note')) ?: null;

if ($action === 'create') {
    $x = (float) str_replace(',', '.', post('x', '0'));
    $y = (float) str_replace(',', '.', post('y', '0'));
    if ($x < 0 || $x > 100 || $y < 0 || $y > 100) {
        api_fail('Метка вне изображения');
    }
    $stmt = db()->prepare(
        "INSERT INTO {$markerTable} (photo_id, x, y, designator, value, note, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$photoId, $x, $y, $designator, $value, $note, $userId]);
    echo json_encode(['ok' => true, 'id' => (int) db()->lastInsertId()]);
    exit;
}

if ($action === 'update') {
    $markerId = (int) post('marker_id');
    db()->prepare("UPDATE {$markerTable} SET designator = ?, value = ?, note = ? WHERE id = ? AND photo_id = ?")
        ->execute([$designator, $value, $note, $markerId, $photoId]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    $markerId = (int) post('marker_id');
    db()->prepare("DELETE FROM {$markerTable} WHERE id = ? AND photo_id = ?")->execute([$markerId, $photoId]);
    echo json_encode(['ok' => true]);
    exit;
}

api_fail('Неизвестное действие');
