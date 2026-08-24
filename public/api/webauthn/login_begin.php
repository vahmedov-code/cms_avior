<?php
/**
 * Шаг 1 входа по отпечатку (login.php): по логину отдаёт список
 * привязанных к нему устройств для navigator.credentials.get().
 * Работает ДО входа в CRM — по определению.
 */
require __DIR__ . '/../../../src/bootstrap.php';
require __DIR__ . '/../../../src/api_helpers.php';
api_bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Используйте POST', 405);
}

$body = json_body();
$username = trim((string) ($body['username'] ?? ''));

if ($username === '') {
    api_error('Введите логин.');
}

$stmt = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

// Один и тот же ответ независимо от причины — не даём угадывать
// существующие логины перебором через этот эндпоинт.
$notFoundMsg = 'Для этого логина не привязан вход по отпечатку. Войдите паролем.';

if (!$user) {
    api_error($notFoundMsg, 404);
}

$stmt = db()->prepare('SELECT credential_id FROM webauthn_credentials WHERE user_id = ?');
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

if (!$rows) {
    api_error($notFoundMsg, 404);
}

try {
    $webauthn = webauthn_instance();
    $ids = array_map(
        static fn(array $row) => base64url_decode($row['credential_id']),
        $rows
    );

    $args = $webauthn->getGetArgs($ids, 60, true, true, true, true, true, true);

    $_SESSION['webauthn_login_challenge'] = $webauthn->getChallenge()->getBinaryString();
    $_SESSION['webauthn_login_user_id'] = (int) $user['id'];

    api_json(['ok' => true, 'options' => $args->publicKey]);
} catch (Throwable $e) {
    api_error('Не удалось подготовить вход: ' . $e->getMessage());
}
