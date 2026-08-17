<?php
/**
 * Приём заявок с сайта avior.moscow (lead.php) — параллельно с MAX, чтобы
 * заявка сразу появлялась в CRM как новый заказ, без ручного переноса.
 *
 * Публичный эндпоинт (без входа в CRM — сайт и CRM это разные системы,
 * lead.php стучится сюда сервер-сервер). Защищён общим секретом —
 * обязательно передавать заголовок X-Lead-Secret, значение сверяется с
 * настройкой lead_intake_secret (Настройки → «Приём заявок с сайта»).
 * Без правильного секрета — 401, ничего не создаётся.
 *
 * POST /api/lead_intake.php
 * Заголовок: X-Lead-Secret: <секрет>
 * Тело (JSON): {"name": "...", "phone": "...", "text": "...", "channel": "..."}
 *   name, phone — обязательны. text — описание проблемы (может быть
 *   пустым, форма на сайте не всегда его собирает). channel —
 *   предпочитаемый способ связи (Telegram/MAX/звонок), необязателен,
 *   если есть — просто добавляется первой строкой в описание проблемы.
 *
 * Создаёт (если такого клиента ещё нет по телефону — см.
 * find_or_create_client()) клиента с source='site' и новый заказ
 * (order_type=repair, status=принят, device_type — заглушка «Заявка с
 * сайта», реальный тип устройства с общей контактной формы неизвестен,
 * уточняется потом при звонке клиенту).
 */
require __DIR__ . '/../../src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

function lead_intake_fail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lead_intake_fail(405, 'Метод не поддерживается.');
}

$configuredSecret = get_setting('lead_intake_secret');
if (!$configuredSecret) {
    lead_intake_fail(403, 'Приём заявок с сайта не настроен. Задайте секрет в CRM → Настройки.');
}

$providedSecret = $_SERVER['HTTP_X_LEAD_SECRET'] ?? '';
if (!hash_equals($configuredSecret, (string) $providedSecret)) {
    lead_intake_fail(401, 'Неверный или отсутствующий секрет.');
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    lead_intake_fail(400, 'Некорректное тело запроса.');
}

$name = trim((string) ($body['name'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$text = trim((string) ($body['text'] ?? ''));
$channel = trim((string) ($body['channel'] ?? ''));

if ($name === '' || $phone === '') {
    lead_intake_fail(422, 'Укажите name и phone.');
}

$clientId = find_or_create_client($name, $phone, 'site');

$problemDescription = $text;
if ($channel !== '') {
    $channelLabels = ['telegram' => 'Telegram', 'max' => 'MAX', 'call' => 'Звонок'];
    $channelLabel = $channelLabels[$channel] ?? $channel;
    $problemDescription = "Предпочитаемый способ связи: {$channelLabel}\n\n{$problemDescription}";
}
$problemDescription = trim($problemDescription) ?: 'Заявка с сайта avior.moscow (без описания проблемы).';

$orderNo = next_order_no();
$stmt = db()->prepare(
    'INSERT INTO repairs (order_no, client_id, device_type, problem_description, status, price_estimate, public_token)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$orderNo, $clientId, 'Заявка с сайта', $problemDescription, 'принят', 0, generate_public_token()]);
$orderId = (int) db()->lastInsertId();

$log = db()->prepare('INSERT INTO repair_status_log (repair_id, status, comment) VALUES (?, ?, ?)');
$log->execute([$orderId, 'принят', 'Заказ создан автоматически из заявки на сайте avior.moscow']);

echo json_encode(['ok' => true, 'order_no' => $orderNo], JSON_UNESCAPED_UNICODE);
