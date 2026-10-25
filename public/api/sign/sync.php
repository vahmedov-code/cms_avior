<?php
/**
 * Синхронизация вывески AviorSign с CRM. Вывеска стоит в офисной сети и
 * сама раз в ~10 с присылает сюда своё состояние, а в ответ получает то,
 * что задано в CRM (door_sign_commands()). Без входа в CRM — по токену
 * вывески, который выдаётся на странице «Вывеска на двери».
 *
 * POST /api/sign/sync.php
 * Заголовок: Authorization: Bearer <токен> (или X-Sign-Token: <токен>)
 * Тело (JSON): состояние вывески — version, desired, displayed, busy,
 *   displayError, battery, uptimeSeconds, applied{...} и т.д., с 0.4.0 ещё
 *   log{next, events[]} — свежие события из журнала вывески.
 * Ответ: door_sign_commands() и eventsAck — номер последнего сохранённого события.
 *
 * Сессия здесь не нужна, поэтому bootstrap.php не подключается: опрос
 * каждые 10 секунд не должен плодить файлы сессий на сервере.
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Moscow');
require_once __DIR__ . '/../../../src/db.php';
require_once __DIR__ . '/../../../src/door_sign.php';

$sign = door_sign_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    door_sign_api_fail(405, 'Метод не поддерживается.');
}
$report = json_decode((string) file_get_contents('php://input', false, null, 0, 16384), true);
if (!is_array($report)) {
    door_sign_api_fail(400, 'Ожидается JSON с состоянием вывески.');
}

// Журнал самой вывески (прошивка 0.4.0+) — в door_sign_log; в reported_json его не храним.
$deviceLog = $report['log'] ?? null;
unset($report['log']);
// Ключ локальной панели (прошивка 0.4.3+) — отдельно, не в reported_json: его видят только владелец и администраторы.
$localKey = $report['localKey'] ?? null;
unset($report['localKey']);
if (is_string($localKey) && preg_match('/^[0-9a-f]{16,64}$/', $localKey) && array_key_exists('local_key', $sign) && $localKey !== $sign['local_key']) {
    db()->prepare('UPDATE door_signs SET local_key = ? WHERE id = ?')->execute([$localKey, $sign['id']]);
}
$eventsAck = door_sign_store_events($sign, $deviceLog);

// В журнал — только переходы, чтобы он оставался коротким. Вывеска со своим журналом
// сама пишет перезапуски с причиной и обновления экрана, тогда здесь их не дублируем.
$previous = door_sign_reported($sign);
if ($previous !== null && $eventsAck === null) {
    $shown = $report['displayed'] ?? null;
    if ($shown !== ($previous['displayed'] ?? null) && in_array($shown, DOOR_SIGN_SLOTS, true)) {
        door_sign_log((int) $sign['id'], null, 'shown', 'На экране: ' . door_sign_label($shown));
    }
    if (isset($report['uptimeSeconds'], $previous['uptimeSeconds']) && (int) $report['uptimeSeconds'] < (int) $previous['uptimeSeconds']) {
        door_sign_log((int) $sign['id'], null, 'restart',
            'Перезапуск: ' . (string) ($report['resetReason'] ?? '?') . ', версия ' . (string) ($report['version'] ?? '?'));
    }
    $error = (string) ($report['displayError'] ?? '');
    if ($error !== '' && $error !== (string) ($previous['displayError'] ?? '')) {
        door_sign_log((int) $sign['id'], null, 'error', $error);
    }
}

// Время — из PHP (Europe/Moscow), как и сравнение в door_sign_is_online(): часовой пояс
// MySQL-сервера может быть другим.
db()->prepare('UPDATE door_signs SET reported_json = ?, last_seen_at = ?, last_ip = ? WHERE id = ?')
    ->execute([json_encode($report, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'] ?? null, $sign['id']]);

door_sign_store_battery($sign, $report);

$out = door_sign_commands($sign);
if ($eventsAck !== null) {
    $out['eventsAck'] = $eventsAck;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
