<?php
/** Мелкие утилиты, используемые на страницах CMS. */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash_set(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_get(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function money(float $n): string
{
    return number_format($n, 0, '.', ' ') . ' ₽';
}

function post(string $key, string $default = ''): string
{
    return trim($_POST[$key] ?? $default);
}

function get(string $key, string $default = ''): string
{
    return trim($_GET[$key] ?? $default);
}

/**
 * Генерирует следующий номер заказа в формате ГГ-XXX,
 * основываясь на количестве заказов, созданных в текущем году.
 */
function next_order_no(): string
{
    $yy = date('y');
    $stmt = db()->prepare("SELECT COUNT(*) AS c FROM repairs WHERE order_no LIKE ?");
    $stmt->execute([$yy . '-%']);
    $count = (int) $stmt->fetch()['c'] + 1;
    return sprintf('%s-%03d', $yy, $count);
}

/**
 * Источники, откуда пришёл клиент — для формы клиента и статистики.
 * Ключ — значение в БД (ENUM), значение — подпись в интерфейсе.
 */
function client_sources(): array
{
    return [
        'avito'       => 'Авито',
        'yandex'      => 'Яндекс',
        '2gis'        => '2ГИС',
        'google_maps' => 'Google Карты',
        'referral'    => 'Сарафанное радио',
        'walkin'      => 'С улицы',
    ];
}

function client_source_label(?string $key): string
{
    if ($key === null || $key === '') {
        return '—';
    }
    return client_sources()[$key] ?? $key;
}

/**
 * Тип заказа — откуда он создан. Все типы попадают в общий список «Заказы».
 */
function order_types(): array
{
    return [
        'repair'       => 'Ремонт',
        'pc_build'     => 'Сборка ПК',
        'account_memo' => 'Памятка по аккаунту',
    ];
}

function order_type_label(?string $key): string
{
    return order_types()[$key] ?? ($key ?? 'Ремонт');
}

/**
 * Реквизиты сервиса — используются в печатных формах (квитанция, памятка и т.д.).
 */
function company_info(): array
{
    return [
        'name'    => 'АВИОР',
        'address' => 'Можайское шоссе, 4к1, Москва',
        'phone'   => '+7 (901) 222-81-11',
    ];
}
