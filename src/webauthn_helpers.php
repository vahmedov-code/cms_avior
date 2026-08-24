<?php
/**
 * Вход по отпечатку пальца / Face ID (WebAuthn) — обёртка над вендоренной
 * библиотекой src/vendor/webauthn/ (см. VENDORED.md там же) под наши
 * сессии/БД. Используется login.php, profile.php и public/api/webauthn/*.php.
 *
 * Ограничиваем только "platform"-аутентификаторами (crossPlatformAttachment
 * = false при регистрации) — то есть встроенным отпечатком/Face ID самого
 * телефона/ноутбука, а не внешними USB/NFC-ключами типа Yubico: ровно то,
 * что просил Вейс. requireUserVerification везде 'required'/true — без
 * этого устройство могло бы засчитать просто "прикосновение" без реальной
 * биометрической проверки.
 *
 * Root-сертификаты производителей НЕ подключены (addRootCertificates() не
 * вызывается) — нам не важно, какой именно чип у телефона, важно только,
 * что вход идёт с того же устройства, что было привязано при регистрации.
 */

require_once __DIR__ . '/vendor/webauthn/WebAuthn.php';

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

function webauthn_rp_id(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return preg_replace('/:\d+$/', '', $host) ?? $host;
}

function webauthn_instance(): WebAuthn
{
    // null формат = все поддерживаемые (none/packed/apple/android-*/tpm/u2f) —
    // разные телефоны/браузеры присылают разный формат аттестации, не
    // хотим ронять регистрацию из-за этого. true = отдавать бинарные поля
    // в JSON как base64url-строки (проще разобрать в JS, без ручного
    // парсинга RFC1342-конструкции по умолчанию у библиотеки).
    return new WebAuthn('АВИОР CRM', webauthn_rp_id(), null, true);
}

function base64url_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function base64url_decode(string $b64url): string
{
    $b64 = strtr($b64url, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad > 0) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($b64, true);
    return $decoded === false ? '' : $decoded;
}

/**
 * Логинит пользователя по ID — тот же результат в сессии, что и
 * attempt_login(), но без проверки пароля (вызывается после успешной
 * проверки WebAuthn-подписи). Возвращает false, если пользователя с
 * таким ID больше нет (уволен/удалён между регистрацией ключа и входом).
 */
function webauthn_login_user_by_id(int $userId): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return false;
    }
    $_SESSION['user'] = [
        'id'        => (int) $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
    ];
    session_regenerate_id(true);
    return true;
}

/**
 * Проверка CSRF-токена для наших JSON (fetch) эндпоинтов — в отличие от
 * csrf_verify() из functions.php не делает redirect, а кидает исключение,
 * которое api_error() в вызывающем коде превращает в JSON-ответ 403.
 * Токен фронт берёт из data-csrf на странице (значение csrf_token()).
 */
function webauthn_check_csrf(?string $token): void
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if ($sessionToken === '' || $token === null || $token === '' || !hash_equals($sessionToken, $token)) {
        throw new \RuntimeException('Сессия устарела, обновите страницу и попробуйте снова.');
    }
}

/** Список привязанных устройств пользователя — для profile.php. */
function webauthn_list_credentials(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT id, device_label, created_at, last_used_at FROM webauthn_credentials
         WHERE user_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
