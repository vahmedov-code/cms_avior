<?php
/**
 * API базы комплектующих для офлайн-формы сметы (smeta-sborka-pk.html).
 * Заменяет собой прежнюю интеграцию с Google Sheets (Apps Script веб-
 * приложение) — теперь и офлайн-смета, и основная CRM (подсказки в
 * repair_view.php) используют одну и ту же таблицу parts_catalog: каталог
 * общий для обоих инструментов, а не два независимых списка.
 *
 * Без авторизации — сама форма тоже без входа в CRM (тот же уровень
 * доступа, что был у прежнего Google Apps Script веб-приложения с
 * доступом «Все»; не понижение безопасности, а замена одного открытого
 * эндпоинта на другой — просто теперь свой, а не сторонний облачный).
 *
 * GET  → {ok:true, parts:[{name,price},...]}
 * POST JSON {action:"upsert", name, price}  — price=0 не затирает уже
 *      сохранённую цену (соответствует тому, как это было задумано в
 *      клиентском JS — там это тоже проверялось, но по факту терялось
 *      при пуше в Sheets; здесь исправлено на бэкенде разом).
 * POST JSON {action:"delete", name}
 * POST JSON {action:"clearAll"}
 */
require __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function parts_api_fail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = db()->query('SELECT name, price FROM parts_catalog ORDER BY name')->fetchAll();
    echo json_encode([
        'ok'    => true,
        'parts' => array_map(fn($r) => ['name' => $r['name'], 'price' => (float) $r['price']], $rows),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !isset($body['action'])) {
        parts_api_fail(400, 'Некорректный запрос.');
    }

    switch ($body['action']) {
        case 'upsert':
            $name = trim((string) ($body['name'] ?? ''));
            $price = (float) ($body['price'] ?? 0);
            if ($name === '') {
                parts_api_fail(400, 'Название не может быть пустым.');
            }
            $stmt = db()->prepare(
                'INSERT INTO parts_catalog (name, price) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE
                    price = IF(VALUES(price) > 0, VALUES(price), price),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$name, $price]);
            break;

        case 'delete':
            $name = trim((string) ($body['name'] ?? ''));
            if ($name !== '') {
                $stmt = db()->prepare('DELETE FROM parts_catalog WHERE name = ?');
                $stmt->execute([$name]);
            }
            break;

        case 'clearAll':
            db()->exec('DELETE FROM parts_catalog');
            break;

        default:
            parts_api_fail(400, 'Неизвестное действие.');
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

parts_api_fail(405, 'Метод не поддерживается.');
