<?php
/**
 * Клиенты — GET (список/поиск/по id/по телефону), POST (создание). Требует токен.
 *
 * GET  /api/mobile/clients.php?q=Екатерина        — нечёткий поиск по имени/телефону (LIKE)
 * GET  /api/mobile/clients.php?phone=+79001234567 — точный поиск по телефону (для подсказки «Найден: ...» при вводе)
 * GET  /api/mobile/clients.php?id=5                — один клиент
 * GET  /api/mobile/clients.php                      — последние 50
 * POST /api/mobile/clients.php  {full_name, phone, email?, address?, notes?, source?}
 */
require __DIR__ . '/../../../src/bootstrap.php';
require __DIR__ . '/../../../src/api_helpers.php';
api_bootstrap();
$user = api_require_auth();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = db()->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $client = $stmt->fetch();
        if (!$client) {
            api_error('Клиент не найден', 404);
        }
        api_json(['ok' => true, 'client' => $client]);
    }

    // Точный поиск по телефону (не путать с ?q= ниже — тот нечёткий,
    // LIKE по подстроке; этот — сравнение по нормализованным цифрам,
    // находит клиента даже если телефон записан в другом формате —
    // "+7 900..." vs "8900..." vs "900..."). Для подсказки «Найден:
    // ...» на экране создания заказа в приложении — см. §7
    // PROJECT_STATE.md репозитория avior_crm_apk.
    $phoneQuery = trim((string) ($_GET['phone'] ?? ''));
    if ($phoneQuery !== '') {
        $targetDigits = normalize_phone_digits($phoneQuery);
        $found = null;
        if ($targetDigits !== '') {
            foreach (db()->query('SELECT * FROM clients')->fetchAll() as $row) {
                if (normalize_phone_digits($row['phone']) === $targetDigits) {
                    $found = $row;
                    break;
                }
            }
        }
        api_json(['ok' => true, 'client' => $found]);
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . $q . '%';
        $stmt = db()->prepare('SELECT * FROM clients WHERE full_name LIKE ? OR phone LIKE ? ORDER BY full_name LIMIT 50');
        $stmt->execute([$like, $like]);
    } else {
        $stmt = db()->query('SELECT * FROM clients ORDER BY created_at DESC LIMIT 50');
    }
    api_json(['ok' => true, 'clients' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $body = json_body();
    $fullName = trim((string) ($body['full_name'] ?? ''));
    $phone = trim((string) ($body['phone'] ?? ''));
    $email = trim((string) ($body['email'] ?? ''));
    $address = trim((string) ($body['address'] ?? ''));
    $notes = trim((string) ($body['notes'] ?? ''));
    $source = (string) ($body['source'] ?? '');

    if ($fullName === '' || $phone === '') {
        api_error('Укажите full_name и phone', 422);
    }
    if ($source !== '' && !array_key_exists($source, client_sources())) {
        api_error('Недопустимое значение source. Разрешены: ' . implode(', ', array_keys(client_sources())), 422);
    }

    $stmt = db()->prepare(
        'INSERT INTO clients (full_name, phone, email, address, notes, source) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$fullName, $phone, $email ?: null, $address ?: null, $notes ?: null, $source ?: null]);
    $id = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT * FROM clients WHERE id = ?');
    $stmt->execute([$id]);
    api_json(['ok' => true, 'client' => $stmt->fetch()], 201);
}

api_error('Метод не поддерживается', 405);
