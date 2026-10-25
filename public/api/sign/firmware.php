<?php
/**
 * Прошивка для вывески AviorSign, загруженная на странице «Вывеска на двери».
 * GET /api/sign/firmware.php?seq=<номер> с токеном вывески. Отдаёт firmware.bin
 * с Content-Length: вывеска сверяет размер и SHA-256 из door_sign_commands().
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Moscow');
require_once __DIR__ . '/../../../src/db.php';
require_once __DIR__ . '/../../../src/door_sign.php';

$sign = door_sign_api_auth();
try {
    $stmt = db()->prepare('SELECT version, data FROM door_sign_firmware WHERE sign_id = ?');
    $stmt->execute([$sign['id']]);
    $firmware = $stmt->fetch();
} catch (PDOException $e) {
    door_sign_api_fail(503, 'Обновление из CRM не включено: примените миграцию 2026_10_26_door_sign_firmware.sql.');
}
if (!$firmware || (int) ($_GET['seq'] ?? 0) !== (int) $sign['firmware_seq']) {
    door_sign_api_fail(404, 'Этой прошивки в CRM уже нет.');
}
door_sign_log((int) $sign['id'], null, 'firmware_download', 'Вывеска скачивает прошивку ' . $firmware['version']);
header('Content-Type: application/octet-stream');
header('Content-Length: ' . strlen($firmware['data']));
echo $firmware['data'];
