<?php
/**
 * Шаг 2 входа по отпечатку (login.php): принимает результат
 * navigator.credentials.get(), проверяет подпись и, если всё сошлось,
 * логинит пользователя (то же самое, что делает attempt_login() при
 * верном пароле).
 */
require __DIR__ . '/../../../src/bootstrap.php';
require __DIR__ . '/../../../src/api_helpers.php';
api_bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Используйте POST', 405);
}

if (current_user()) {
    api_json(['ok' => true, 'redirect' => 'index.php']);
}

$body = json_body();

$challenge = $_SESSION['webauthn_login_challenge'] ?? null;
$userId = $_SESSION['webauthn_login_user_id'] ?? null;
unset($_SESSION['webauthn_login_challenge'], $_SESSION['webauthn_login_user_id']);

if ($challenge === null || $userId === null) {
    api_error('Сессия входа истекла, попробуйте снова.', 400);
}

$credentialIdB64 = (string) ($body['id'] ?? '');
if ($credentialIdB64 === '') {
    api_error('Некорректные данные от браузера.');
}

$stmt = db()->prepare(
    'SELECT id, public_key, sign_count FROM webauthn_credentials WHERE user_id = ? AND credential_id = ? LIMIT 1'
);
$stmt->execute([$userId, $credentialIdB64]);
$cred = $stmt->fetch();

if (!$cred) {
    api_error('Это устройство не привязано к аккаунту.', 404);
}

$clientDataJSON = isset($body['clientDataJSON']) ? base64url_decode((string) $body['clientDataJSON']) : '';
$authenticatorData = isset($body['authenticatorData']) ? base64url_decode((string) $body['authenticatorData']) : '';
$signature = isset($body['signature']) ? base64url_decode((string) $body['signature']) : '';

if ($clientDataJSON === '' || $authenticatorData === '' || $signature === '') {
    api_error('Некорректные данные от браузера.');
}

try {
    $webauthn = webauthn_instance();
    $webauthn->processGet(
        $clientDataJSON,
        $authenticatorData,
        $signature,
        $cred['public_key'],
        $challenge,
        (int) $cred['sign_count'],
        true,
        true
    );

    $newSignCount = $webauthn->getSignatureCounter();
    db()->prepare('UPDATE webauthn_credentials SET sign_count = ?, last_used_at = NOW() WHERE id = ?')
        ->execute([$newSignCount ?? $cred['sign_count'], $cred['id']]);

    if (!webauthn_login_user_by_id((int) $userId)) {
        api_error('Учётная запись недоступна.', 403);
    }

    api_json(['ok' => true, 'redirect' => 'index.php']);
} catch (\lbuchs\WebAuthn\WebAuthnException $e) {
    api_error('Не удалось подтвердить вход: ' . $e->getMessage(), 401);
}
