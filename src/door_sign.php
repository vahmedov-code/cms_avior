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
    if ((int) $sign['power_seq'] > 0) {
        $out['power'] = ['seq' => (int) $sign['power_seq'], 'enabled' => (bool) $sign['power_save']];
        if (array_key_exists('power_auto', $sign)) {
            $out['power']['auto'] = (bool) $sign['power_auto'];
        }
    }
    if ((int) ($sign['battery_seq'] ?? 0) > 0) {
        $curve = json_decode((string) ($sign['battery_curve'] ?? ''), true);
        $out['battery'] = ['seq' => (int) $sign['battery_seq'], 'scale' => (int) $sign['battery_scale'], 'curve' => is_array($curve) ? $curve : null];
    }
    if ((int) $sign['wake_seq'] > 0) {
        $out['wake'] = ['seq' => (int) $sign['wake_seq']];
    }
    if ((int) $sign['qr_seq'] > 0) {
        $out['qr'] = ['seq' => (int) $sign['qr_seq'], 'enabled' => (bool) $sign['qr_enabled'], 'url' => $sign['qr_url']];
    }
    if ((int) ($sign['panel_seq'] ?? 0) > 0) {
        $out['panel'] = ['seq' => (int) $sign['panel_seq'], 'user' => $sign['panel_user'], 'salt' => $sign['panel_salt'], 'hash' => $sign['panel_hash']];
    }
    if ((int) ($sign['firmware_seq'] ?? 0) > 0 && ($fw = door_sign_firmware_info((int) $sign['id']))) {
        $out['firmware'] = ['seq' => (int) $sign['firmware_seq'], 'version' => $fw['version'], 'size' => (int) $fw['size'], 'sha256' => $fw['sha256']];
    }
    $stmt = db()->prepare('SELECT slot, sha256, seq, frame IS NOT NULL AS present FROM door_sign_assets WHERE sign_id = ?');
    $stmt->execute([$sign['id']]);
    foreach ($stmt->fetchAll() as $a) {
        $out['assets'][$a['slot']] = ['seq' => (int) $a['seq'], 'present' => (bool) $a['present'], 'sha256' => $a['sha256']];
    }
    return $out;
}

/** Соль и хеш пароля панели так же, как их проверяет прошивка: PBKDF2-SHA256, 2000 раундов, 32 байта. */
function door_sign_panel_hash(string $password, string $saltHex): string
{
    return bin2hex(hash_pbkdf2('sha256', $password, (string) hex2bin($saltHex), 2000, 32, true));
}

/** Загруженная в CRM прошивка без самого файла (или null; null и без миграции). */
function door_sign_firmware_info(int $signId): ?array
{
    try {
        $stmt = db()->prepare('SELECT version, size, sha256, uploaded_at FROM door_sign_firmware WHERE sign_id = ?');
        $stmt->execute([$signId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Проверка firmware.bin перед выдачей вывеске: образ ESP32 (первый байт 0xE9), влезает в
 * раздел OTA 0x140000, несёт метку AVIORSIGN_VERSION=…; (прошивка 0.4.3+) и это не
 * диагностическая сборка — та отключает связь с CRM. Возвращает версию или текст ошибки.
 */
function door_sign_check_firmware(string $data, int $slot = 0x140000): array
{
    // $slot — размер раздела прошивки, который присылает вывеска (0.4.7+); у старых — 1,25 МБ.
    if (strlen($data) < 100000 || strlen($data) > $slot || ord($data[0]) !== 0xE9) {
        return ['error' => 'Это не firmware.bin для ESP32 или файл больше раздела прошивки на вывеске (' . number_format($slot / 1048576, 2, ',', '') . ' МБ).'];
    }
    if (!preg_match('/AVIORSIGN_VERSION=([0-9A-Za-z.\-]{1,40});/', $data, $m)) {
        return ['error' => 'В файле нет метки версии AviorSign. Из CRM ставятся прошивки 0.4.3 и новее; более старые — через локальную панель.'];
    }
    if (strpos($m[1], 'recovery') !== false) {
        return ['error' => 'Диагностическую (recovery) прошивку из CRM ставить нельзя: она отключает связь с CRM. Ставьте её через USB.'];
    }
    return ['version' => $m[1]];
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

/**
 * «На связи»: обычная вывеска опрашивает CRM раз в ~10 с, в режиме экономии —
 * раз в wakeMinutes (15) минут, поэтому для неё и порог другой.
 */
/**
 * Журнал самой вывески (прошивка 0.4.0+): в каждом обмене она присылает до 8 своих событий,
 * ещё не подтверждённых CRM, — перезапуски и их причины, пропадания Wi-Fi, ошибки связи,
 * обновления экрана. Сохраняем их в door_sign_log (action «device:…», без пользователя) и
 * возвращаем номер последнего сохранённого: вывеска больше их не присылает. Без миграции
 * 2026_10_23_door_sign_events.sql (нет столбца events_seq) возвращает null и ничего не пишет.
 */
function door_sign_store_events(array $sign, $log): ?int
{
    if (!array_key_exists('events_seq', $sign) || !is_array($log)) {
        return null;
    }
    $stored = (int) $sign['events_seq'];
    // Счётчик на вывеске пошёл заново (стёрта её память) — принимаем события с начала.
    $next = (int) ($log['next'] ?? 0);
    if ($next > 0 && $next <= $stored) {
        $stored = 0;
    }
    $last = $stored;
    $insert = db()->prepare('INSERT INTO door_sign_log (sign_id, user_id, action, detail, created_at)
        VALUES (?, NULL, ?, ?, COALESCE(FROM_UNIXTIME(?), CURRENT_TIMESTAMP))');
    foreach (is_array($log['events'] ?? null) ? $log['events'] : [] as $e) {
        $seq = is_array($e) ? (int) ($e['seq'] ?? 0) : 0;
        if ($seq <= $last) {
            continue;
        }
        $kind = preg_replace('/[^a-z_]/', '', (string) ($e['kind'] ?? '')) ?: 'event';
        $code = (int) ($e['code'] ?? 0);
        // Сбойные перезапуски и низкий заряд отмечаем отдельно, чтобы выделить их в журнале.
        if ($kind === 'boot' && in_array($code, [4, 5, 6, 7, 9], true)) {
            $kind = 'boot_fault';
        } elseif ($kind === 'battery' && $code <= 15) {
            $kind = 'battery_low';
        }
        $time = (int) ($e['time'] ?? 0);
        // Время с вывески — если её часы были установлены; иначе момент получения.
        $at = !empty($e['timeValid']) && $time > 1700000000 && $time < time() + 3600 ? $time : null;
        $text = trim((string) ($e['text'] ?? ''));
        $insert->execute([$sign['id'], 'device:' . substr($kind, 0, 30), mb_substr($text !== '' ? $text : $kind, 0, 255), $at]);
        $last = $seq;
    }
    if ($last !== (int) $sign['events_seq']) {
        db()->prepare('UPDATE door_signs SET events_seq = ? WHERE id = ?')->execute([$last, $sign['id']]);
    }
    return $last;
}

/**
 * История заряда (миграция 2026_10_25_door_sign_battery.sql): точка раз в 10 минут, при смене
 * состояния зарядки и при скачке напряжения. Без таблицы ничего не делает.
 */
function door_sign_store_battery(array $sign, array $report): void
{
    $b = $report['battery'] ?? null;
    if (!is_array($b) || !isset($b['mv']) || (int) $b['mv'] < 2000 || (int) $b['mv'] > 5000) {
        return;
    }
    $raw = (int) ($b['raw'] ?? $b['mv']);
    $charge = isset($b['charge']) ? substr(preg_replace('/[^a-z]/', '', (string) $b['charge']), 0, 12) : null;
    try {
        $stmt = db()->prepare('SELECT ts, raw_mv, charge FROM door_sign_battery WHERE sign_id = ? ORDER BY ts DESC LIMIT 1');
        $stmt->execute([$sign['id']]);
        $last = $stmt->fetch();
        if ($last && time() - (int) $last['ts'] < 600 && $last['charge'] === $charge && abs((int) $last['raw_mv'] - $raw) < 40) {
            return;
        }
        db()->prepare('INSERT INTO door_sign_battery (sign_id, ts, mv, raw_mv, percent, charge, power_save) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$sign['id'], time(), (int) $b['mv'], $raw, isset($b['percent']) ? max(0, min(100, (int) $b['percent'])) : null,
                $charge, !empty($report['powerSave']) ? 1 : 0]);
    } catch (PDOException $e) {
        // Миграция истории заряда ещё не применена.
    }
}

/** Точки истории заряда начиная с $since, по времени. Пустой массив без миграции. */
function door_sign_battery_points(int $signId, int $since): array
{
    try {
        $stmt = db()->prepare('SELECT ts, mv, raw_mv, percent, charge, power_save FROM door_sign_battery WHERE sign_id = ? AND ts >= ? ORDER BY ts');
        $stmt->execute([$signId, $since]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Последний цикл разряда для калибровки процента: от отключения USB после зарядки до
 * следующей зарядки или до последней точки. Нагрузка в работе почти постоянная, поэтому
 * доля прошедшего времени ≈ доле израсходованного заряда: напряжение в момент, когда прошло
 * 10 % времени цикла, — это 90 % и т.д. Кривая — в raw мВ (до поправки напряжения), чтобы
 * поправка её не сдвигала. Цикл годится, если начался не ниже 4,10 В, длился не меньше
 * 6 часов и дошёл до 3,50 В или ниже.
 */
function door_sign_battery_cycle(array $points): ?array
{
    $usb = function ($p): bool { return in_array($p['charge'], ['charging', 'full'], true); };
    $start = null;
    for ($i = count($points) - 1; $i > 0; $i--) {
        if (!$usb($points[$i]) && $usb($points[$i - 1])) {
            $start = $i;
            break;
        }
    }
    if ($start === null) {
        return null;
    }
    $cycle = [];
    for ($i = $start; $i < count($points) && !$usb($points[$i]); $i++) {
        $cycle[] = ['t' => (int) $points[$i]['ts'], 'mv' => (int) $points[$i]['raw_mv']];
    }
    // Медиана по 5 соседним точкам убирает провалы под нагрузкой.
    $smooth = [];
    foreach ($cycle as $k => $p) {
        $win = array_column(array_slice($cycle, max(0, $k - 2), 5), 'mv');
        sort($win);
        $smooth[] = ['t' => $p['t'], 'mv' => $win[intdiv(count($win), 2)]];
    }
    $first = $smooth[0];
    $last = $smooth[count($smooth) - 1];
    $hours = ($last['t'] - $first['t']) / 3600;
    $result = ['start' => $first['t'], 'end' => $last['t'], 'hours' => round($hours, 1), 'fromMv' => $first['mv'],
        'toMv' => min(array_column($smooth, 'mv')), 'ended' => $i < count($points), 'curve' => null,
        'complete' => $first['mv'] >= 4100 && $hours >= 6 && min(array_column($smooth, 'mv')) <= 3500];
    if (!$result['complete']) {
        return $result;
    }
    $curve = [];
    for ($k = 0; $k <= 10; $k++) {
        $t = $first['t'] + (1 - $k / 10) * ($last['t'] - $first['t']);
        $mv = $last['mv'];
        for ($j = 1; $j < count($smooth); $j++) {
            if ($smooth[$j]['t'] >= $t) {
                $a = $smooth[$j - 1];
                $b = $smooth[$j];
                $mv = $b['t'] > $a['t'] ? $a['mv'] + ($b['mv'] - $a['mv']) * ($t - $a['t']) / ($b['t'] - $a['t']) : $b['mv'];
                break;
            }
        }
        $curve[$k] = (int) round($mv);
    }
    $curve[0] = min($curve[0], $result['toMv']);
    for ($k = 1; $k <= 10; $k++) {
        $curve[$k] = max($curve[$k], $curve[$k - 1] + 1);   // строго по возрастанию, как требует прошивка
    }
    $result['curve'] = $curve;
    return $result;
}

/** События вывески, которые стоит выделить в журнале. */
function door_sign_event_is_fault(string $action): bool
{
    return in_array($action, ['device:boot_fault', 'device:before_reset', 'device:render_abort', 'device:wifi_down',
        'device:crm_error', 'device:refresh_fail', 'device:battery_low', 'device:dropped'], true);
}

function door_sign_is_online(array $sign): bool
{
    $ago = door_sign_seconds_since_seen($sign);
    $reported = door_sign_reported($sign);
    $limit = !empty($reported['powerSave']) ? (int) ($reported['wakeMinutes'] ?? 15) * 60 + 120 : DOOR_SIGN_ONLINE_SECONDS;
    return $ago !== null && $ago <= $limit;
}
