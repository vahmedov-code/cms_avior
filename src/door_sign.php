<?php
/**
 * Вывеска на двери AviorSign — общие функции для страницы door_sign.php
 * и API вывески (public/api/sign/*.php). Схема и смысл seq — в миграции
 * sql/migrations/2026_10_22_door_signs.sql.
 *
 * Прошивка вывески — отдельный репозиторий vahmedov-code/e_inc_table.
 */

const DOOR_SIGN_SLOTS = ['open', 'closed', 'soon', 'image'];
const DOOR_SIGN_FRAME_BYTES = 96000; // 800×480, 4 пикселя на байт
const DOOR_SIGN_ONLINE_SECONDS = 45; // вывеска опрашивает CRM раз в ~10 с

function door_sign_label(?string $status): string
{
    $labels = ['open' => 'Открыто', 'closed' => 'Закрыто', 'soon' => 'Скоро буду', 'image' => 'Своё изображение'];
    return $labels[$status ?? ''] ?? 'неизвестно';
}

/** Без применённой миграции модуль просто выключен — остальная CRM его не касается. */
function door_sign_tables_ready(): bool
{
    try {
        db()->query('SELECT 1 FROM door_signs LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/** Пока поддерживается одна вывеска; таблица уже готова к нескольким. */
function door_sign_current(): ?array
{
    $row = db()->query('SELECT * FROM door_signs ORDER BY id LIMIT 1')->fetch();
    return $row ?: null;
}

function door_sign_new_token(): string
{
    return bin2hex(random_bytes(24));
}

/** Следующий номер изменения — атомарно, без гонок между вкладками. */
function door_sign_next_seq(int $signId): int
{
    db()->prepare('UPDATE door_signs SET seq = LAST_INSERT_ID(seq + 1) WHERE id = ?')->execute([$signId]);
    return (int) db()->query('SELECT LAST_INSERT_ID()')->fetchColumn();
}

function door_sign_log(int $signId, ?int $userId, string $action, ?string $detail = null): void
{
    db()->prepare('INSERT INTO door_sign_log (sign_id, user_id, action, detail) VALUES (?, ?, ?, ?)')
        ->execute([$signId, $userId, $action, $detail !== null ? mb_substr($detail, 0, 255) : null]);
}

/**
 * Те же правила, что у прошивки (include/schedule_json.h): до 32 событий,
 * день 0..6 (вс=0), минута 0..1439, статусы open/closed/soon, без дублей
 * день+время, включённое расписание — хотя бы с одним событием.
 * Возвращает нормализованный массив или null.
 */
function door_sign_validate_schedule($data): ?array
{
    if (!is_array($data) || !is_bool($data['enabled'] ?? null) || !is_int($data['utcOffsetMinutes'] ?? null)
        || !is_array($data['events'] ?? null)) {
        return null;
    }
    $offset = $data['utcOffsetMinutes'];
    if ($offset < -720 || $offset > 840 || count($data['events']) > 32) {
        return null;
    }
    $events = [];
    $seen = [];
    foreach ($data['events'] as $e) {
        $day = $e['day'] ?? null;
        $minute = $e['minute'] ?? null;
        $status = $e['status'] ?? null;
        if (!is_int($day) || !is_int($minute) || !in_array($status, ['open', 'closed', 'soon'], true)
            || $day < 0 || $day > 6 || $minute < 0 || $minute > 1439 || isset($seen["$day:$minute"])) {
            return null;
        }
        $seen["$day:$minute"] = true;
        $events[] = ['day' => $day, 'minute' => $minute, 'status' => $status];
    }
    if ($data['enabled'] && !$events) {
        return null;
    }
    return ['enabled' => $data['enabled'], 'utcOffsetMinutes' => $offset, 'events' => $events];
}

function door_sign_valid_qr_url(string $url): bool
{
    return mb_strlen($url) <= 160 && preg_match('~^https?://\S+$~', $url) === 1;
}

/** Токен вывески: Authorization: Bearer, либо X-Sign-Token, если nginx не пробрасывает Authorization. */
function door_sign_request_token(): ?string
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($auth !== '' && preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
        return $m[1];
    }
    $header = $_SERVER['HTTP_X_SIGN_TOKEN'] ?? '';
    return $header !== '' ? (string) $header : null;
}

function door_sign_api_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Для api/sign/*.php: находит вывеску по токену или отвечает 401/503. */
function door_sign_api_auth(): array
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!door_sign_tables_ready()) {
        door_sign_api_fail(503, 'Модуль вывески не включён в CRM: примените миграцию 2026_10_22_door_signs.sql.');
    }
    $token = door_sign_request_token();
    if ($token === null || strlen($token) > 128) {
        door_sign_api_fail(401, 'Нужен токен вывески.');
    }
    $stmt = db()->prepare('SELECT * FROM door_signs WHERE token_hash = ? LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $sign = $stmt->fetch();
    if (!$sign) {
        door_sign_api_fail(401, 'Неверный или отозванный токен вывески.');
    }
    return $sign;
}

/** Что CRM хочет от вывески: только пункты, хоть раз заданные из CRM (seq > 0). */
function door_sign_commands(array $sign): array
{
    $out = ['ok' => true, 'pollSeconds' => 10];
    if ((int) $sign['status_seq'] > 0) {
        $out['status'] = ['seq' => (int) $sign['status_seq'], 'value' => $sign['status']];
    }
    if ((int) $sign['schedule_seq'] > 0) {
        $out['schedule'] = ['seq' => (int) $sign['schedule_seq'], 'value' => json_decode((string) $sign['schedule_json'], true)];
    }
    if ((int) $sign['qr_seq'] > 0) {
        $out['qr'] = ['seq' => (int) $sign['qr_seq'], 'enabled' => (bool) $sign['qr_enabled'], 'url' => $sign['qr_url']];
    }
    $stmt = db()->prepare('SELECT slot, sha256, seq, frame IS NOT NULL AS present FROM door_sign_assets WHERE sign_id = ?');
    $stmt->execute([$sign['id']]);
    foreach ($stmt->fetchAll() as $a) {
        $out['assets'][$a['slot']] = ['seq' => (int) $a['seq'], 'present' => (bool) $a['present'], 'sha256' => $a['sha256']];
    }
    return $out;
}

/** Состояние, которое вывеска прислала последним (или null). */
function door_sign_reported(array $sign): ?array
{
    $data = json_decode((string) ($sign['reported_json'] ?? ''), true);
    return is_array($data) ? $data : null;
}

/** Секунд с последней связи (null — ни разу). last_seen_at пишется из PHP, см. api/sign/sync.php. */
function door_sign_seconds_since_seen(array $sign): ?int
{
    return empty($sign['last_seen_at']) ? null : max(0, time() - (int) strtotime((string) $sign['last_seen_at']));
}

function door_sign_is_online(array $sign): bool
{
    $ago = door_sign_seconds_since_seen($sign);
    return $ago !== null && $ago <= DOOR_SIGN_ONLINE_SECONDS;
}
