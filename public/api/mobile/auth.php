<?php
/**
 * Вход в мобильное приложение — POST { "username": "...", "password": "...",
 * "device_label": "Samsung A54" (необязательно) } -> { ok, token, user }.
 *
 * Токен передавать дальше во всех запросах как:
 *   Authorization: Bearer <token>
 */
require __DIR__ . '/../../../src/bootstrap.php';
require __DIR__ . '/../../../src/api_helpers.php';
api_bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Используйте POST', 405);
}

$body = json_body();
$username = trim((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');
$deviceLabel = trim((string) ($body['device_label'] ?? ''));

if ($username === '' || $password === '') {
    api_error('Укажите username и password', 422);
}

$stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    api_error('Неверный логин или пароль', 401);
}

$token = bin2hex(random_bytes(32));
$stmt = db()->prepare('INSERT INTO api_tokens (user_id, token, device_label) VALUES (?, ?, ?)');
$stmt->execute([(int) $user['id'], $token, $deviceLabel ?: null]);

api_json([
    'ok'    => true,
    'token' => $token,
    'user'  => [
        'id'        => (int) $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
    ],
], 201);
