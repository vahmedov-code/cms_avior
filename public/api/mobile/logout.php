<?php
/**
 * Выход из мобильного приложения — POST, требует токен, отзывает именно
 * его (остальные устройства/токены этого же пользователя не трогает).
 */
require __DIR__ . '/../../../src/bootstrap.php';
require __DIR__ . '/../../../src/api_helpers.php';
api_bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Используйте POST', 405);
}

$user = api_require_auth();

db()->prepare('DELETE FROM api_tokens WHERE id = ?')->execute([(int) $user['token_id']]);

api_json(['ok' => true]);
