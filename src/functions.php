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
 * Настройки, редактируемые через интерфейс (см. settings.php, только
 * администратор) — хранятся в БД, а не в файлах на сервере. Это задел
 * под white-label: чтобы развернуть CMS для другого клиента, не нужно
 * лезть в код/конфиг руками — только заполнить форму настроек один раз.
 *
 * Graceful-деградация: если таблица settings ещё не создана (миграция
 * не применена) — get_setting() тихо возвращает $default вместо падения
 * с ошибкой SQL. Так старые страницы (квитанция, SMS и т.д.) продолжают
 * работать как раньше даже без миграции — в отличие от, например,
 * device_model_catalog, тут нет жёсткой зависимости.
 */
function get_setting(string $key, ?string $default = null): ?string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = db()->prepare('SELECT value FROM settings WHERE `key` = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $value = ($row && $row['value'] !== '') ? $row['value'] : $default;
    } catch (PDOException $e) {
        $value = $default;
    }
    $cache[$key] = $value;
    return $value;
}

/** @throws PDOException если таблица settings не создана — ловите на странице настроек. */
function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (`key`, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    $stmt->execute([$key, $value]);
}

/**
 * Реквизиты сервиса — используются в печатных формах (квитанция, памятка и т.д.).
 * Значения по умолчанию — текущие реквизиты АВИОР (пока настройки не заполнены
 * через settings.php, ничего не меняется по сравнению с тем, как было раньше).
 */
function company_info(): array
{
    return [
        'name'    => get_setting('company_name', 'АВИОР'),
        'address' => get_setting('company_address', 'Можайское шоссе, 4к1, Москва'),
        'phone'   => get_setting('company_phone', '+7 (901) 222-81-11'),
    ];
}

/** Деньги без символа валюты, с копейками через запятую — как в печатных бланках («6 500,00»). */
function money_plain(float $n): string
{
    return number_format($n, 2, ',', ' ');
}

/**
 * Склонение существительного по числу (1/2-4/5-20 и т.д.) — «1 рубль»,
 * «2 рубля», «5 рублей». $one/$few/$many — формы для 1 / 2-4 / 5-20,0.
 */
function ru_plural(int $n, string $one, string $few, string $many): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return $many;
    }
    if ($n1 > 1 && $n1 < 5) {
        return $few;
    }
    if ($n1 === 1) {
        return $one;
    }
    return $many;
}

/** Целое число прописью на русском (используется только для сумм в акте). */
function ru_number_to_words(int $number): string
{
    if ($number === 0) {
        return 'ноль';
    }

    $onesMasc = ['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
    $onesFem  = ['', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
    $teens    = ['десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать'];
    $tens     = ['', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто'];
    $hundreds = ['', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот'];

    // [форма-1, форма-2..4, форма-5..20/0, пол (0 — муж., 1 — жен.)]
    $scales = [
        0 => ['', '', '', 0],
        1 => ['тысяча', 'тысячи', 'тысяч', 1],
        2 => ['миллион', 'миллиона', 'миллионов', 0],
        3 => ['миллиард', 'миллиарда', 'миллиардов', 0],
    ];

    $groups = [];
    $n = $number;
    while ($n > 0) {
        $groups[] = $n % 1000;
        $n = intdiv($n, 1000);
    }

    $parts = [];
    for ($i = count($groups) - 1; $i >= 0; $i--) {
        $g = $groups[$i];
        if ($g === 0) {
            continue;
        }
        $gender = $scales[$i][3] ?? 0;
        $ones = $gender === 1 ? $onesFem : $onesMasc;

        $words = [];
        $h = intdiv($g, 100);
        $rem = $g % 100;
        if ($h > 0) {
            $words[] = $hundreds[$h];
        }
        if ($rem >= 10 && $rem < 20) {
            $words[] = $teens[$rem - 10];
        } else {
            $t = intdiv($rem, 10);
            $o = $rem % 10;
            if ($t > 0) {
                $words[] = $tens[$t];
            }
            if ($o > 0) {
                $words[] = $ones[$o];
            }
        }
        if ($i > 0 && isset($scales[$i])) {
            $words[] = ru_plural($g, $scales[$i][0], $scales[$i][1], $scales[$i][2]);
        }
        $parts[] = implode(' ', $words);
    }

    return implode(' ', $parts);
}

/**
 * Сумма прописью для акта — «Шесть тысяч пятьсот рублей 00 копеек»
 * (первая буква заглавная, остальное как в оригинале «прописи»).
 */
function money_in_words_rub(float $amount): string
{
    $rubles = (int) floor($amount + 0.001);
    $kopecks = (int) round(($amount - $rubles) * 100);
    if ($kopecks >= 100) {
        $rubles++;
        $kopecks -= 100;
    }

    $rubWords = $rubles === 0 ? 'ноль' : ru_number_to_words($rubles);
    $rubLabel = ru_plural($rubles, 'рубль', 'рубля', 'рублей');
    $kopLabel = ru_plural($kopecks, 'копейка', 'копейки', 'копеек');

    $sentence = trim($rubWords . ' ' . $rubLabel);
    $sentence = mb_strtoupper(mb_substr($sentence, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($sentence, 1, null, 'UTF-8');

    return $sentence . ' ' . sprintf('%02d', $kopecks) . ' ' . $kopLabel;
}

/**
 * Случайный уникальный токен для публичных ссылок на квитанцию/акт заказа
 * (без входа в CMS) — 64 hex-символа, криптографически случайный.
 * Присваивается заказу один раз при создании (см. INSERT в repair_new.php,
 * pc_build_new.php, account_memo_new.php, api/mobile/orders.php).
 */
function generate_public_token(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Ссылки «отправить» для печатных документов — WhatsApp/Telegram/Email.
 * $publicUrl — ссылка на публичный просмотр документа (без входа в CMS).
 */
function build_share_links(string $publicUrl, string $message, string $subject, string $clientPhone, ?string $clientEmail): array
{
    $textWithLink = $message . "\n" . $publicUrl;

    $phoneDigits = preg_replace('/\D+/', '', $clientPhone) ?? '';
    if ($phoneDigits !== '' && $phoneDigits[0] === '8' && strlen($phoneDigits) === 11) {
        $phoneDigits = '7' . substr($phoneDigits, 1);
    }

    return [
        'whatsapp' => 'https://wa.me/' . $phoneDigits . '?text=' . rawurlencode($textWithLink),
        'telegram' => 'https://t.me/share/url?url=' . rawurlencode($publicUrl) . '&text=' . rawurlencode($message),
        'email'    => 'mailto:' . ($clientEmail ?: '') . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($textWithLink),
    ];
}

/**
 * Собирает публичный URL страницы (order_status.php, receipt_public.php,
 * act_public.php) на реальном домене сайта. Возвращает null, если
 * site_url в config.php ещё не настроен (стоит заглушка example.ru) —
 * тогда QR/ссылки на отправку просто не показываются.
 */
function public_site_url(string $path): ?string
{
    $siteUrl = rtrim((string) (get_setting('site_url') ?? (config()['site_url'] ?? '')), '/');
    if ($siteUrl === '' || strpos($siteUrl, 'example.ru') !== false) {
        return null;
    }
    return $siteUrl . '/' . ltrim($path, '/');
}

/**
 * Подсказки для автозаполнения форм — построены на истории уже введённых
 * значений (никакого отдельного справочника вести не нужно, база учится
 * сама по мере работы). Сортировка по частоте использования — то, что
 * вводили чаще, показывается первым.
 */
function suggest_device_types(int $limit = 30): array
{
    $stmt = db()->prepare(
        "SELECT device_type FROM repairs WHERE device_type IS NOT NULL AND device_type <> ''
         GROUP BY device_type ORDER BY COUNT(*) DESC LIMIT " . (int) $limit
    );
    $stmt->execute();
    return array_column($stmt->fetchAll(), 'device_type');
}

/**
 * Подсказки моделей устройств — объединяет то, что реально чинили
 * (`repairs.device_model`, приоритет по частоте использования), со
 * справочником известных моделей (`device_model_catalog`, см. миграцию
 * 2026_08_17_device_model_catalog.sql) — так подсказки работают сразу,
 * даже пока своя история заказов небольшая, а часто встречающиеся модели
 * всё равно поднимаются наверх списка по мере накопления заказов.
 */
function suggest_device_models(int $limit = 300): array
{
    $stmt = db()->prepare(
        "SELECT model, MAX(freq) AS freq FROM (
            SELECT device_model AS model, COUNT(*) AS freq
                FROM repairs WHERE device_model IS NOT NULL AND device_model <> ''
                GROUP BY device_model
            UNION ALL
            SELECT name AS model, 0 AS freq FROM device_model_catalog
         ) t
         GROUP BY model
         ORDER BY freq DESC, model ASC
         LIMIT " . (int) $limit
    );
    $stmt->execute();
    return array_column($stmt->fetchAll(), 'model');
}

/** Топ частых формулировок поломки — для кнопок-подсказок над textarea. */
function suggest_problem_descriptions(int $limit = 20): array
{
    $stmt = db()->prepare(
        "SELECT problem_description FROM repairs
         WHERE problem_description IS NOT NULL AND problem_description <> ''
         GROUP BY problem_description ORDER BY COUNT(*) DESC LIMIT " . (int) $limit
    );
    $stmt->execute();
    return array_column($stmt->fetchAll(), 'problem_description');
}

/** Названия комплектующих/услуг из общего каталога (parts_catalog). */
function suggest_part_names(int $limit = 150): array
{
    $stmt = db()->prepare('SELECT name FROM parts_catalog ORDER BY updated_at DESC LIMIT ' . (int) $limit);
    $stmt->execute();
    return array_column($stmt->fetchAll(), 'name');
}

/**
 * Готовый текст SMS для формы «SMS клиенту» — подставляется по умолчанию,
 * сотрудник может отредактировать перед отправкой. $totalDue — сумма к
 * оплате (обычно: комплектующие+услуги минус уже внесённая предоплата).
 */
/**
 * Реально ли настроен SMS-провайдер (для показа/скрытия предупреждения
 * в интерфейсе) — та же логика приоритета, что и в send_sms() (sms.php):
 * настройка из БД → config.php → ничего не настроено.
 */
function sms_active_provider(): ?string
{
    $provider = get_setting('sms_provider') ?: (config()['sms']['provider'] ?? null);
    return $provider ?: null;
}

/** Человекочитаемое название провайдера — для отображения в интерфейсе. */
function sms_provider_label(string $provider): string
{
    $labels = [
        'smsru'           => 'SMS.ru',
        'smsc'            => 'SMSC.ru',
        'android_gateway' => 'Android-шлюз (свой телефон)',
    ];
    return $labels[$provider] ?? $provider;
}

function default_sms_message(array $repair, float $totalDue): string
{
    if ($repair['status'] === 'готов') {
        $sum = number_format($totalDue, 0, '.', ' ');
        return 'Здравствуйте! Вас приветствует сервис АВИОР. Устройство готово. С вас ' . $sum . ' ₽.';
    }

    if ($repair['status'] === 'принят') {
        $text = 'Здравствуйте! Вас приветствует сервис АВИОР. Заказ ' . $repair['order_no'] . ' принят в работу.';
        return $text . ' Статус можно отследить здесь: https://avior.moscow/#status';
    }

    return 'Заказ ' . $repair['order_no'] . ': статус — ' . $repair['status'] . '.';
}

/** Частые категории устройств с иконками — для визуального пикера типа устройства. */
function device_type_options(): array
{
    return [
        'Настольный ПК'   => '🖥️',
        'Моноблок'        => '🖥',
        'Ноутбук'         => '💻',
        'Смартфон'        => '📱',
        'Планшет'         => '🔲',
        'Игровая консоль' => '🎮',
        'Аксессуары'      => '🔌',
    ];
}

/**
 * Рендерит визуальный пикер типа устройства: кликабельные карточки
 * иконка+подпись (по сути — радио-выбор, скрытые нативные radio под
 * CSS-стилизованными "чипами") плюс пункт «Другое», открывающий обычный
 * текстовый ввод. Итоговое значение всегда попадает в текстовое поле
 * с id=$fieldId (тот же input, что отправляется формой как device_type) —
 * JS-логика выбора одна на всё приложение, лежит в layout_footer.php
 * (функция selectDeviceType), здесь только разметка.
 *
 * $currentValue — текущее значение поля (для формы редактирования):
 * если совпадает с одним из пресетов — та карточка будет выделена сразу.
 */
function render_device_type_picker(string $fieldId, string $currentValue = ''): string
{
    $options = device_type_options();
    $matchesPreset = array_key_exists($currentValue, $options);

    $html = '<div class="device-type-picker">';
    foreach ($options as $label => $icon) {
        $checked = ($currentValue === $label) ? ' checked' : '';
        $js = 'selectDeviceType(this, ' . json_encode($fieldId) . ', false)';
        $html .= '<label class="device-type-option">'
            . '<input type="radio" name="' . e($fieldId) . '_radio" value="' . e($label) . '"'
            . ' onclick="' . e($js) . '"' . $checked . '>'
            . '<span class="dt-chip"><span class="dt-icon">' . $icon . '</span>' . e($label) . '</span>'
            . '</label>';
    }
    $otherChecked = ($currentValue !== '' && !$matchesPreset) ? ' checked' : '';
    $jsOther = 'selectDeviceType(this, ' . json_encode($fieldId) . ', true)';
    $html .= '<label class="device-type-option">'
        . '<input type="radio" name="' . e($fieldId) . '_radio" value="__other__"'
        . ' onclick="' . e($jsOther) . '"' . $otherChecked . '>'
        . '<span class="dt-chip"><span class="dt-icon">✏️</span>Другое</span>'
        . '</label>';
    $html .= '</div>';

    return $html;
}

/** Рендерит <datalist> с готовыми <option> из массива строк. */
function render_datalist(string $id, array $values): string
{
    $html = '<datalist id="' . e($id) . '">';
    foreach ($values as $v) {
        $html .= '<option value="' . e($v) . '">';
    }
    return $html . '</datalist>';
}

/**
 * Рендерит кнопки-подсказки над textarea/input: клик подставляет готовый
 * текст в поле с указанным id. Используется там, где list=datalist не
 * подходит (textarea их не поддерживает браузерами).
 */
function render_suggestion_chips(string $targetFieldId, array $values): string
{
    if (!$values) {
        return '';
    }
    $html = '<div class="chip-suggestions">';
    foreach ($values as $v) {
        $short = mb_strlen($v) > 40 ? mb_substr($v, 0, 40) . '…' : $v;
        $js = 'document.getElementById(' . json_encode($targetFieldId) . ').value = ' . json_encode($v) . ';';
        $html .= '<button type="button" class="btn btn-sm chip" onclick="' . e($js) . '">' . e($short) . '</button>';
    }
    return $html . '</div>';
}
