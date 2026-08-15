<?php
/**
 * Общие хелперы для мобильного API (public/api/mobile/*.php) —
 * используется Android-приложением (клиенты, заказы, смена статусов).
 *
 * Аутентификация — по токену (таблица api_tokens), а не по сессии:
 * POST /api/mobile/auth.php с username/password возвращает токен,
 * дальше он передаётся в заголовке Authorization: Bearer <токен>
 * (либо X-Api-Token, либо ?token= — на случай, если прокси/nginx
 * перед PHP-FPM не пробрасывает Authorization без доп. настройки).
 */

/**
 * Вызывать первым в каждом api/mobile/*.php — настраивает CORS,
 * Content-Type и перехватывает необработанные ошибки, чтобы наружу
 * всегда уходил валидный JSON, а не HTML-страница фатальной ошибки PHP.
 */
function api_bootstrap(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Token');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    set_exception_handler(function (Throwable $e): void {
        error_log('[api/mobile] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Внутренняя ошибка сервера'], JSON_UNESCAPED_UNICODE);
        exit;
    });
}

/** Отдать JSON-ответ и завершить выполнение. */
function api_json($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Отдать JSON-ошибку в едином формате {"ok": false, "error": "..."}. */
function api_error(string $message, int $status = 400): void
{
    api_json(['ok' => false, 'error' => $message], $status);
}

/** Тело POST/PUT-запроса как JSON (Android/Retrofit шлёт application/json). */
function json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Достаёт токен из заголовка Authorization/X-Api-Token или ?token=. */
function api_bearer_token(): ?string
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($auth !== '' && preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
        return $m[1];
    }
    if (!empty($_SERVER['HTTP_X_API_TOKEN'])) {
        return (string) $_SERVER['HTTP_X_API_TOKEN'];
    }
    if (!empty($_GET['token'])) {
        return (string) $_GET['token'];
    }
    return null;
}

/**
 * Проверяет токен, возвращает пользователя (id, username, full_name, role,
 * token_id) или сразу отвечает 401 и завершает выполнение.
 */
function api_require_auth(): array
{
    $token = api_bearer_token();
    if (!$token) {
        api_error('Требуется токен авторизации (Authorization: Bearer <token>)', 401);
    }

    $stmt = db()->prepare(
        'SELECT u.id, u.username, u.full_name, u.role, t.id AS token_id
         FROM api_tokens t JOIN users u ON u.id = t.user_id
         WHERE t.token = ? LIMIT 1'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        api_error('Неверный или отозванный токен', 401);
    }

    db()->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?')->execute([$row['token_id']]);

    return $row;
}
