<?php
/**
 * Шаг 2 привязки устройства (profile.php): принимает результат
 * navigator.credentials.create(), проверяет подпись/аттестацию и
 * сохраняет публичный ключ в webauthn_credentials.
 */
require __DIR__ . '/../../../src/bootstrap.php';
require __DIR__ . '/../../../src/api_helpers.php';
api_bootstrap();

$me = current_user();
if (!$me) {
    api_error('Требуется вход в CRM.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Используйте POST', 405);
}

$body = json_body();

try {
    webauthn_check_csrf($body['csrfToken'] ?? null);
} catch (\RuntimeException $e) {
    api_error($e->getMessage(), 403);
}

$challenge = $_SESSION['webauthn_reg_challenge'] ?? null;
unset($_SESSION['webauthn_reg_challenge']);
if ($challenge === null) {
    api_error('Сессия регистрации истекла, начните заново.', 400);
}

$clientDataJSON = isset($body['clientDataJSON']) ? base64url_decode((string) $body['clientDataJSON']) : '';
$attestationObject = isset($body['attestationObject']) ? base64url_decode((string) $body['attestationObject']) : '';
$deviceLabel = trim((string) ($body['deviceLabel'] ?? '')) ?: 'Устройство без названия';

if ($clientDataJSON === '' || $attestationObject === '') {
    api_error('Некорректные данные от браузера.');
}

try {
    $webauthn = webauthn_instance();
    $data = $webauthn->processCreate($clientDataJSON, $attestationObject, $challenge, true, true, false);

    $credentialId = base64url_encode($data->credentialId);

    $stmt = db()->prepare(
        'INSERT INTO webauthn_credentials (user_id, credential_id, public_key, sign_count, device_label)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $me['id'],
        $credentialId,
        $data->credentialPublicKey,
        $data->signatureCounter ?? 0,
        mb_substr($deviceLabel, 0, 100),
    ]);

    api_json(['ok' => true, 'message' => 'Устройство привязано.']);
} catch (\lbuchs\WebAuthn\WebAuthnException $e) {
    api_error('Не удалось подтвердить устройство: ' . $e->getMessage());
} catch (\PDOException $e) {
    if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate')) {
        api_error('Это устройство уже привязано.');
    }
    throw $e;
}
