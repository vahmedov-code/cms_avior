<?php
/**
 * Картинка статуса для вывески AviorSign: 96000 байт (800×480, 4 цвета,
 * формат прошивки). Вывеска скачивает её, когда в sync.php у слота новый seq.
 *
 * GET /api/sign/asset.php?slot=open|closed|soon|image
 * Заголовок: Authorization: Bearer <токен> (или X-Sign-Token: <токен>)
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Moscow');
require_once __DIR__ . '/../../../src/db.php';
require_once __DIR__ . '/../../../src/door_sign.php';

$sign = door_sign_api_auth();
$slot = (string) ($_GET['slot'] ?? '');
if (!in_array($slot, DOOR_SIGN_SLOTS, true)) {
    door_sign_api_fail(400, 'Неизвестный слот.');
}
$stmt = db()->prepare('SELECT frame, sha256 FROM door_sign_assets WHERE sign_id = ? AND slot = ? AND frame IS NOT NULL');
$stmt->execute([$sign['id'], $slot]);
$row = $stmt->fetch();
if (!$row || strlen($row['frame']) !== DOOR_SIGN_FRAME_BYTES) {
    door_sign_api_fail(404, 'Для этого статуса в CRM нет картинки.');
}
header('Content-Type: application/octet-stream');
header('Content-Length: ' . DOOR_SIGN_FRAME_BYTES);
header('X-Frame-Sha256: ' . $row['sha256']);
echo $row['frame'];
