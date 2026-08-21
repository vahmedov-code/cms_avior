<?php
/**
 * Заказы — GET (список с фильтрами / деталь по id), POST (создание заказа
 * на ремонт — order_type всегда 'repair'; сборка ПК и памятка по
 * аккаунту через API пока не создаются, у них своя логика в веб-CRM).
 * Требует токен.
 *
 * GET  /api/mobile/orders.php                              — последние 50
 * GET  /api/mobile/orders.php?status=в ремонте&type=repair  — с фильтрами
 * GET  /api/mobile/orders.php?id=12                          — деталь + позиции + история статусов
 * POST /api/mobile/orders.php
 *   { "client_id": 5, "device_type": "Ноутбук", "device_model": "...",
 *     "problem_description": "...", "price_estimate": 3000 }
 *   — либо вместо client_id передать новый клиент:
 *   { "new_client": {"full_name": "...", "phone": "...", "source": "avito"},
 *     "device_type": "..." , ... }
 */
require __DIR__ . '/../../../src/bootstrap.php';
require __DIR__ . '/../../../src/api_helpers.php';
api_bootstrap();
$user = api_require_auth();

/**
 * Ссылки на печатные документы заказа (публичные, без входа в CRM —
 * те же страницы, что используются для отправки в WhatsApp/Telegram/
 * Email из веб-CRM, см. src/print_templates.php). Адресуются по
 * id+public_token (уникальный токен заказа, колонка repairs.public_token) —
 * так ссылка не палит телефон клиента и её не подобрать перебором.
 * null, если документ ещё не сформирован:
 * receipt_url — пока не заполнена квитанция (receipt_ready = 0),
 * report_url — пока в заказе нет ни одной позиции (нечего включать в акт).
 * Оба поля также null, если у заказа почему-то нет public_token (не
 * должно случаться для новых заказов — токен генерируется при создании)
 * или если на сервере не настроен site_url в config/config.php.
 */
function order_document_urls(array $repair, int $partsCount): array
{
    if (empty($repair['public_token'])) {
        return ['receipt_url' => null, 'report_url' => null];
    }

    $receiptUrl = null;
    if (!empty($repair['receipt_ready'])) {
        $receiptUrl = public_site_url(
            'receipt_public.php?id=' . (int) $repair['id'] . '&token=' . urlencode($repair['public_token'])
        );
    }

    $reportUrl = null;
    if ($partsCount > 0) {
        $reportUrl = public_site_url(
            'act_public.php?id=' . (int) $repair['id'] . '&token=' . urlencode($repair['public_token'])
        );
    }

    return ['receipt_url' => $receiptUrl, 'report_url' => $reportUrl];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);

    if ($id > 0) {
        $stmt = db()->prepare(
            'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone
             FROM repairs r JOIN clients c ON c.id = r.client_id
             WHERE r.id = ?'
        );
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) {
            api_error('Заказ не найден', 404);
        }

        $partsStmt = db()->prepare(
            'SELECT id, category, name, qty, price, warranty, discount FROM repair_parts WHERE repair_id = ? ORDER BY id'
        );
        $partsStmt->execute([$id]);
        $order['parts'] = $partsStmt->fetchAll();

        $logStmt = db()->prepare(
            'SELECT status, comment, changed_at FROM repair_status_log WHERE repair_id = ? ORDER BY changed_at DESC'
        );
        $logStmt->execute([$id]);
        $order['status_log'] = $logStmt->fetchAll();

        $order['receipt_ready'] = (bool) ($order['receipt_ready'] ?? false);
        $urls = order_document_urls($order, count($order['parts']));
        $order['receipt_url'] = $urls['receipt_url'];
        $order['report_url'] = $urls['report_url'];

        api_json(['ok' => true, 'order' => $order]);
    }

    $status = trim((string) ($_GET['status'] ?? ''));
    $type = trim((string) ($_GET['type'] ?? ''));
    $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));

    $sql = "SELECT r.id, r.order_no, r.order_type, r.status, r.device_type, r.device_model,
                   r.created_at, r.updated_at, r.receipt_ready, r.public_token,
                   c.full_name AS client_name, c.phone AS client_phone,
                   COALESCE((SELECT SUM(qty * price * (1 - COALESCE(discount, 0) / 100)) FROM repair_parts WHERE repair_id = r.id), 0) AS total,
                   (SELECT COUNT(*) FROM repair_parts WHERE repair_id = r.id) AS parts_count
            FROM repairs r JOIN clients c ON c.id = r.client_id";
    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 'r.status = ?';
        $params[] = $status;
    }
    if ($type !== '') {
        $where[] = 'r.order_type = ?';
        $params[] = $type;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY r.created_at DESC LIMIT ' . $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $orders = [];
    foreach ($stmt->fetchAll() as $row) {
        $partsCount = (int) $row['parts_count'];
        unset($row['parts_count']);
        $row['receipt_ready'] = (bool) $row['receipt_ready'];
        $urls = order_document_urls($row, $partsCount);
        $row['receipt_url'] = $urls['receipt_url'];
        $row['report_url'] = $urls['report_url'];
        $orders[] = $row;
    }

    api_json(['ok' => true, 'orders' => $orders]);
}

if ($method === 'POST') {
    $body = json_body();
    $clientId = (int) ($body['client_id'] ?? 0);

    if ($clientId <= 0 && !empty($body['new_client']) && is_array($body['new_client'])) {
        $nc = $body['new_client'];
        $newName = trim((string) ($nc['full_name'] ?? ''));
        $newPhone = trim((string) ($nc['phone'] ?? ''));
        $newSource = (string) ($nc['source'] ?? '');
        if ($newName === '' || $newPhone === '') {
            api_error('Для нового клиента укажите new_client.full_name и new_client.phone', 422);
        }
        // Дедупликация по телефону (обсуждали 19.08) — если клиент с
        // таким номером уже есть в базе, используем его, не создаём
        // дубль. См. find_or_create_client() в functions.php.
        $clientId = find_or_create_client(
            $newName,
            $newPhone,
            array_key_exists($newSource, client_sources()) ? $newSource : null
        );
    }

    if ($clientId <= 0) {
        api_error('Укажите client_id существующего клиента или объект new_client', 422);
    }

    $stmt = db()->prepare('SELECT id FROM clients WHERE id = ?');
    $stmt->execute([$clientId]);
    if (!$stmt->fetch()) {
        api_error('Клиент с таким client_id не найден', 404);
    }

    $deviceType = trim((string) ($body['device_type'] ?? ''));
    if ($deviceType === '') {
        api_error('Укажите device_type', 422);
    }
    $deviceModel = trim((string) ($body['device_model'] ?? ''));
    $problem = trim((string) ($body['problem_description'] ?? ''));
    $priceEstimate = (float) ($body['price_estimate'] ?? 0);

    $orderNo = next_order_no();
    $stmt = db()->prepare(
        'INSERT INTO repairs (order_no, client_id, device_type, device_model, problem_description, status, price_estimate, public_token)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$orderNo, $clientId, $deviceType, $deviceModel ?: null, $problem ?: null, 'принят', $priceEstimate, generate_public_token()]);
    $orderId = (int) db()->lastInsertId();

    $log = db()->prepare('INSERT INTO repair_status_log (repair_id, status, comment, changed_by) VALUES (?, ?, ?, ?)');
    $log->execute([$orderId, 'принят', 'Заказ создан через мобильное приложение', $user['id']]);

    $stmt = db()->prepare(
        'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone
         FROM repairs r JOIN clients c ON c.id = r.client_id WHERE r.id = ?'
    );
    $stmt->execute([$orderId]);
    $newOrder = $stmt->fetch();
    $newOrder['receipt_ready'] = (bool) ($newOrder['receipt_ready'] ?? false);
    // У только что созданного заказа квитанция ещё не заполнена и позиций
    // нет — оба поля всегда null сразу после создания, это ожидаемо.
    $urls = order_document_urls($newOrder, 0);
    $newOrder['receipt_url'] = $urls['receipt_url'];
    $newOrder['report_url'] = $urls['report_url'];

    api_json(['ok' => true, 'order' => $newOrder], 201);
}

api_error('Метод не поддерживается', 405);
