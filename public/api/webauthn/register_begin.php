<?php
/**
 * Шаг 1 привязки устройства (profile.php): отдаёт опции для
 * navigator.credentials.create(). Требует активную сессию CRM (обычный
 * логин/пароль) — привязать новое устройство можно только уже войдя.
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

try {
    $webauthn = webauthn_instance();

    // не даём привязать один и тот же ключ дважды
    $stmt = db()->prepare('SELECT credential_id FROM webauthn_credentials WHERE user_id = ?');
    $stmt->execute([$me['id']]);
    $excludeIds = array_map(
        static fn(array $row) => base64url_decode($row['credential_id']),
        $stmt->fetchAll()
    );

    $args = $webauthn->getCreateArgs(
        (string) $me['id'],   // userId — просто opaque-метка, у нас не используется для discoverable-логина
        $me['username'],
        $me['full_name'],
        60,                   // timeout, сек
        false,                // requireResidentKey — обычный ключ, не passkey
        'required',           // requireUserVerification — обязательно биометрия/PIN
        false,                // crossPlatformAttachment=false -> только встроенный сенсор устройства (platform)
        $excludeIds
    );

    $_SESSION['webauthn_reg_challenge'] = $webauthn->getChallenge()->getBinaryString();

    api_json(['ok' => true, 'options' => $args->publicKey]);
} catch (Throwable $e) {
    api_error('Не удалось подготовить регистрацию: ' . $e->getMessage());
}
