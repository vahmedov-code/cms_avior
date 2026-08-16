<?php
/** Авторизация: сессии + пароли на bcrypt (password_hash / password_verify). */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login.php');
    }
}

/**
 * Иерархия ролей (переработана 19.08 — раньше было бинарно admin/manager):
 *   owner    — полный доступ ко всему без ограничений.
 *   admin    — как owner, кроме Сотрудников (employees.php) и Настроек
 *              (settings.php) — это доступно только владельцу.
 *   engineer — инженер-приёмщик: заказы (создание/статус/комплектующие
 *              с ценами) и клиенты. БЕЗ финансов, аналитики, склада,
 *              удаления заказов, сотрудников, настроек.
 *
 * is_admin()/require_admin() сохранили названия ради совместимости с уже
 * написанным кодом (гейтят финансы/аналитику/склад/удаление заказа), но
 * теперь означают «owner ИЛИ admin» — то есть «расширенный доступ, не
 * инженер», а не буквально role==='admin'. Для строго «только владелец»
 * (Сотрудники/Настройки) — отдельные is_owner()/require_owner().
 *
 * NB: «филиалы» из будущего бэклога (админ видит только свой филиал)
 * сюда пока не подключены — без таблицы locations физически нечего
 * фильтровать. Когда филиалы появятся, здесь потребуется доработка —
 * см. соответствующий пункт бэклога в PROJECT_STATE.md.
 */
function has_role(array $allowed): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'] ?? '', $allowed, true);
}

function is_owner(): bool
{
    return has_role(['owner']);
}

function is_admin(): bool
{
    return has_role(['owner', 'admin']);
}

function is_engineer(): bool
{
    return has_role(['engineer']);
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash_set('Эта страница недоступна для вашей роли.', 'error');
        redirect('index.php');
    }
}

function require_owner(): void
{
    require_login();
    if (!is_owner()) {
        flash_set('Эта страница доступна только владельцу.', 'error');
        redirect('index.php');
    }
}

function attempt_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id'        => (int) $user['id'],
            'username'  => $user['username'],
            'full_name' => $user['full_name'],
            'role'      => $user['role'],
        ];
        session_regenerate_id(true);
        return true;
    }

    return false;
}

function do_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
