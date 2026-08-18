<?php
/** Мелкие утилиты, используемые на страницах CRM. */

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
 * Генерирует следующий номер заказа в формате ГГ-XXX — на основе
 * МАКСИМАЛЬНОГО уже существующего номера за текущий год (не количества
 * записей — раньше считали через COUNT(*), из-за чего удаление заказа
 * (см. §5 PROJECT_STATE.md — админская функция удаления) сдвигало счётчик
 * назад и следующий созданный заказ получал уже занятый номер —
 * `SQLSTATE[23000] Duplicate entry ... for key 'order_no'`, проверено
 * на практике 17.08). MAX() устойчив к пробелам от удалённых заказов —
 * номер только растёт, независимо от того, сколько записей удалено.
 */
function next_order_no(): string
{
    $yy = date('y');
    $stmt = db()->prepare(
        "SELECT order_no FROM repairs WHERE order_no LIKE ?
         ORDER BY CAST(SUBSTRING(order_no, 4) AS UNSIGNED) DESC LIMIT 1"
    );
    $stmt->execute([$yy . '-%']);
    $last = $stmt->fetch();

    $nextNum = 1;
    if ($last && preg_match('/^\d{2}-(\d+)$/', $last['order_no'], $m)) {
        $nextNum = (int) $m[1] + 1;
    }

    return sprintf('%s-%03d', $yy, $nextNum);
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
        'site'        => 'Заявка с сайта',
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
 * Переключатель «Физлицо / Юрлицо» — два радио-баттона, при выборе
 * «Юрлицо» через onchange показывает блок реквизитов компании (id
 * зафиксирован как legalEntityFields — JS-обработчик toggleClientTypeFields()
 * в src/layout_footer.php, страница держит ровно один экземпляр, коллизий
 * id нет).
 */
function render_client_type_toggle(string $current = 'individual'): string
{
    $ind = $current !== 'legal_entity' ? ' checked' : '';
    $leg = $current === 'legal_entity' ? ' checked' : '';
    return '<div class="field full" style="flex-direction:row;gap:20px;align-items:center;">'
        . '<label style="display:flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer;">'
        . '<input type="radio" name="client_type" value="individual" onchange="toggleClientTypeFields(this)"' . $ind . '> Физическое лицо</label>'
        . '<label style="display:flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer;">'
        . '<input type="radio" name="client_type" value="legal_entity" onchange="toggleClientTypeFields(this)"' . $leg . '> Юридическое лицо</label>'
        . '</div>';
}

/**
 * Блок полей реквизитов компании — скрыт по умолчанию (стиль inline,
 * display:none), JS показывает его при выборе «Юридическое лицо».
 * $values — текущие значения (пусто для формы создания).
 */
function render_legal_entity_fields(array $values = []): string
{
    $v = fn(string $k) => e($values[$k] ?? '');
    $display = ($values['client_type'] ?? '') === 'legal_entity' ? '' : 'display:none;';
    return '<div class="field full legal-entity-fields" id="legalEntityFields" style="' . $display . 'flex-direction:row;flex-wrap:wrap;gap:12px;background:#f8f9fc;border:1px solid var(--border);border-radius:8px;padding:14px;margin:4px 0;">'
        . '<h4 style="width:100%;margin:0 0 4px;color:var(--navy);font-size:14px;">Реквизиты компании</h4>'
        . '<label class="field" style="min-width:220px;flex:1;">Контактное лицо<input type="text" name="contact_person" value="' . $v('contact_person') . '"></label>'
        . '<label class="field" style="min-width:140px;">ИНН<input type="text" name="inn" value="' . $v('inn') . '"></label>'
        . '<label class="field" style="min-width:140px;">КПП<input type="text" name="kpp" value="' . $v('kpp') . '"></label>'
        . '<label class="field" style="min-width:160px;">ОГРН<input type="text" name="ogrn" value="' . $v('ogrn') . '"></label>'
        . '<label class="field full">Юридический адрес<input type="text" name="legal_address" value="' . $v('legal_address') . '"></label>'
        . '<label class="field" style="min-width:220px;flex:1;">Банк<input type="text" name="bank_name" value="' . $v('bank_name') . '"></label>'
        . '<label class="field" style="min-width:200px;">Расчётный счёт<input type="text" name="bank_account" value="' . $v('bank_account') . '"></label>'
        . '<label class="field" style="min-width:120px;">БИК<input type="text" name="bank_bik" value="' . $v('bank_bik') . '"></label>'
        . '<label class="field" style="min-width:200px;">Корр. счёт<input type="text" name="bank_corr_account" value="' . $v('bank_corr_account') . '"></label>'
        . '</div>';
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

function role_label(?string $role): string
{
    return [
        'owner'    => 'владелец',
        'admin'    => 'администратор',
        'engineer' => 'инженер-приёмщик',
    ][$role] ?? ($role ?? '—');
}

/**
 * Настройки, редактируемые через интерфейс (см. settings.php, только
 * администратор) — хранятся в БД, а не в файлах на сервере. Это задел
 * под white-label: чтобы развернуть CRM для другого клиента, не нужно
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
 * Строка «Исполнитель» для квитанции/акта — АВИОР (бренд) для обычных
 * клиентов-физлиц, юридическое название только для клиентов-юрлиц
 * (там оно реально нужно для их бухгалтерии). Счёт на оплату (invoice)
 * сюда не входит — он и так показывается только юрлицам, там executor_name
 * используется напрямую, без этой функции.
 */
function executor_display_name(array $repair): string
{
    $company = company_info();
    if (($repair['client_type'] ?? 'individual') === 'legal_entity' && $company['legal_name'] !== '') {
        return $company['legal_name'];
    }
    return $company['name'];
}

/**
 * Строка «Заказчик» для печатных документов — для юрлиц добавляет
 * ИНН/КПП рядом с названием (по образцу счёта, который прислал Вейс:
 * «Покупатель: ООО Кибер Юниэн, ИНН 9718250074, КПП 771801001»), для
 * физлиц — просто ФИО, как и было раньше. Ожидает в $repair алиасы
 * client_name/client_type/client_inn/client_kpp (см. JOIN в repair_act.php
 * и др. — c.client_type AS client_type, c.inn AS client_inn, c.kpp AS client_kpp).
 * Возвращает готовый HTML (уже экранированный), не сырой текст.
 */
function client_display_line(array $repair): string
{
    $name = e($repair['client_name']);
    if (($repair['client_type'] ?? 'individual') !== 'legal_entity') {
        return $name;
    }
    $bits = [$name];
    if (!empty($repair['client_inn'])) {
        $bits[] = 'ИНН ' . e($repair['client_inn']);
    }
    if (!empty($repair['client_kpp'])) {
        $bits[] = 'КПП ' . e($repair['client_kpp']);
    }
    return implode(', ', $bits);
}

/**
 * Реквизиты сервиса — используются в печатных формах (квитанция, памятка и т.д.).
 * Значения по умолчанию — текущие реквизиты АВИОР (пока настройки не заполнены
 * через settings.php, ничего не меняется по сравнению с тем, как было раньше).
 *
 * executor_name — то, что должно стоять в печатных документах в строке
 * «Исполнитель» (юрлицо, не бренд-название) — по образцу, который прислал
 * Вейс (акт.pdf): «Исполнитель: ООО Мастер», а не «Исполнитель: АВИОР».
 * Откат на name, если legal_name ещё не заполнен в Настройках — чтобы
 * документы не остались без исполнителя, пока юрреквизиты не внесены.
 */
function company_info(): array
{
    $name = get_setting('company_name', 'АВИОР');
    $legalName = get_setting('legal_name', '');
    return [
        'name'              => $name,
        'legal_name'        => $legalName,
        'executor_name'     => $legalName !== '' ? $legalName : $name,
        'address'           => get_setting('company_address', 'Можайское шоссе, 4к1, Москва'),
        'phone'             => get_setting('company_phone', '+7 (901) 222-81-11'),
        'inn'               => get_setting('legal_inn', ''),
        'kpp'               => get_setting('legal_kpp', ''),
        'ogrn'              => get_setting('legal_ogrn', ''),
        'bank_name'         => get_setting('bank_name', ''),
        'bank_account'      => get_setting('bank_account', ''),
        'bank_bik'          => get_setting('bank_bik', ''),
        'bank_corr_account' => get_setting('bank_corr_account', ''),
    ];
}

/** Деньги без символа валюты, с копейками через запятую — как в печатных бланках («6 500,00»). */
function money_plain(float $n): string
{
    return number_format($n, 2, ',', ' ');
}

/**
 * Телефон в виде «только цифр, с 7 вместо 8 в начале» — для сравнения
 * номеров, введённых в разных форматах («+7 900...», «8 900...»,
 * «900...»). Раньше в проекте похожая логика была продублирована в
 * нескольких местах (only_digits_rp/ap/os в *_public.php — те просто
 * сверяют цифры для доступа по публичной ссылке, им 8→7 не нужен, не
 * трогаем их отдельно). Эта функция — общая, для новых мест, где нужна
 * настоящая дедупликация (см. find_or_create_client() ниже).
 */
/**
 * Версия CRM — считается автоматически от числа коммитов в git, вручную
 * поднимать номер при каждом пуше не нужно (обсуждали 19.08): первый
 * коммит = версия 1.00, каждый следующий = +0.01. Если shell_exec
 * недоступен (некоторые хостинги отключают выполнение shell-команд из
 * PHP из соображений безопасности) — тихо возвращает пустую строку,
 * футер просто не покажет версию, страница не ломается.
 */
function crm_version(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    if (!function_exists('shell_exec')) {
        return $cached = '';
    }
    $repoRoot = dirname(__DIR__); // src/ -> корень репозитория, где лежит .git
    $output = @shell_exec('git -C ' . escapeshellarg($repoRoot) . ' rev-list --count HEAD 2>/dev/null');
    $count = (int) trim((string) $output);
    if ($count < 1) {
        return $cached = '';
    }
    $version = 1.00 + ($count - 1) * 0.01;
    return $cached = number_format($version, 2);
}

function normalize_phone_digits(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits !== '' && $digits[0] === '8' && strlen($digits) === 11) {
        $digits = '7' . substr($digits, 1);
    }
    return $digits;
}

/**
 * Единый формат хранения телефона — «+7 (960) 123-45-67». Раньше номера
 * сохранялись как ввели (8960..., +7960..., 7960..., просто 9601234567)
 * — из-за этого, например, кнопка WhatsApp в приложении не находила
 * контакт по номеру, сохранённому с 8 в начале (обсуждали 19.08).
 * Теперь ЛЮБОЙ телефон приводится к одному виду в момент сохранения —
 * везде, где клиент создаётся или редактируется.
 *
 * Если номер не похож на российский мобильный (не 10-11 цифр с кодом
 * 7/8, например иностранный формат) — возвращает как ввели, не портит.
 */
function format_phone_ru(string $raw): string
{
    $digits = normalize_phone_digits($raw);
    if (strlen($digits) === 10 && $digits[0] !== '7') {
        // Ввели без кода страны, например "960 123-45-67" — 10 цифр,
        // начинается не с 7 (сам код 7 десятой цифрой быть не может).
        $digits = '7' . $digits;
    }
    if (strlen($digits) === 11 && $digits[0] === '7') {
        return sprintf(
            '+7 (%s) %s-%s-%s',
            substr($digits, 1, 3),
            substr($digits, 4, 3),
            substr($digits, 7, 2),
            substr($digits, 9, 2)
        );
    }
    return trim($raw);
}

/**
 * Находит клиента по телефону (сравнение по нормализованным цифрам —
 * см. normalize_phone_digits()) или создаёт нового, если не нашёл.
 * Защита от дублей клиентов (обсуждали 19.08) — используется при
 * создании заказа через мобильное приложение, где выбора «существующий/
 * новый клиент» больше нет, только поле ФИО+телефон каждый раз.
 * Возвращает id клиента (существующего или только что созданного).
 */
function find_or_create_client(string $fullName, string $phone, ?string $source = null): int
{
    $targetDigits = normalize_phone_digits($phone);

    if ($targetDigits !== '') {
        // Небольшая база (сотни, не десятки тысяч записей) — сравнение
        // в PHP после выборки надёжнее, чем ловить все варианты
        // форматирования телефона прямо в SQL.
        $stmt = db()->query('SELECT id, phone FROM clients');
        foreach ($stmt->fetchAll() as $row) {
            if (normalize_phone_digits($row['phone']) === $targetDigits) {
                return (int) $row['id'];
            }
        }
    }

    $stmt = db()->prepare('INSERT INTO clients (full_name, phone, source) VALUES (?, ?, ?)');
    $stmt->execute([$fullName, format_phone_ru($phone), $source]);
    return (int) db()->lastInsertId();
}

/**
 * Стоимость расширенной гарантии — 15% от суммы всех позиций в чеке.
 * По требованию бизнеса (обсуждали 19.08) добавляется отдельной строкой
 * в чек ТОЛЬКО при оплате через Яндекс Сплит, ни при каком другом
 * способе. Сам Сплит ещё не подключён (ждём API-документацию от Вейса)
 * — функция готова заранее, чтобы не забыть про неё, когда дойдём до
 * реализации. Подключить нужно будет в JS-построении ORDER_CHECK_STRINGS
 * в repair_view.php: если способ оплаты — yandex_split, добавить ещё
 * одну строку {Name: "Расширенная гарантия", Quantity: 1, Price: <результат
 * этой функции>, Amount: то же самое, Tax: 20} к уже существующим позициям
 * заказа перед отправкой в KkmServer.
 */
function extended_warranty_price(array $parts): float
{
    $itemsTotal = 0.0;
    foreach ($parts as $p) {
        $itemsTotal += (float) $p['qty'] * (float) $p['price'];
    }
    return round($itemsTotal * 0.15, 2);
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
 * (без входа в CRM) — 64 hex-символа, криптографически случайный.
 * Присваивается заказу один раз при создании (см. INSERT в repair_new.php,
 * pc_build_new.php, account_memo_new.php, api/mobile/orders.php).
 */
function generate_public_token(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Ссылки «отправить» для печатных документов — WhatsApp/Telegram/Email.
 * $publicUrl — ссылка на публичный просмотр документа (без входа в CRM).
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
 * Списывает/возвращает остаток на складе при добавлении/удалении позиции
 * в заказе (category='part' — услуги на склад не влияют). Ищет позицию
 * в parts_catalog по точному совпадению названия (без учёта регистра) —
 * если такой позиции в каталоге ещё нет, ничего не списывает (склад
 * ведётся только по тому, что заведено на warehouse.php, а не по любому
 * тексту, который когда-либо вписали в заказ). $qty — положительное
 * число при расходе (заказ), отрицательное — при возврате (удаление
 * позиции из заказа). Пишет запись в stock_movements для истории.
 */
function adjust_stock_for_part_usage(string $partName, float $qtyDelta, ?int $repairId, string $reason): void
{
    if ($qtyDelta == 0.0) {
        return;
    }
    $stmt = db()->prepare('SELECT id FROM parts_catalog WHERE name = ? LIMIT 1');
    $stmt->execute([$partName]);
    $catalogId = $stmt->fetchColumn();
    if (!$catalogId) {
        return; // не в каталоге склада — нечего списывать
    }

    db()->prepare('UPDATE parts_catalog SET stock_qty = stock_qty - ? WHERE id = ?')
        ->execute([$qtyDelta, $catalogId]);

    $type = $qtyDelta > 0 ? 'out' : 'in';
    $stmt = db()->prepare(
        'INSERT INTO stock_movements (part_id, type, qty, reason, repair_id, created_by) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$catalogId, $type, abs($qtyDelta), $reason, $repairId, current_user()['id'] ?? null]);
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

/**
 * Диапазон дат по ключу периода — та же логика, что дублируется в
 * finance.php и analytics.php (эти два пока не трогаем, чтобы не рисковать
 * регрессией; новый код использует эту функцию). $period: month (по
 * умолчанию) | last_month | year | all.
 */
function resolve_period(string $period): array
{
    $today = new DateTime();
    switch ($period) {
        case 'last_month':
            return [
                (new DateTime('first day of last month'))->format('Y-m-d'),
                (new DateTime('last day of last month'))->format('Y-m-d'),
                'Прошлый месяц',
                'last_month',
            ];
        case 'year':
            return [$today->format('Y') . '-01-01', $today->format('Y') . '-12-31', 'Этот год', 'year'];
        case 'all':
            return ['2000-01-01', '2100-01-01', 'Всё время', 'all'];
        case 'month':
        default:
            return [$today->format('Y-m-01'), $today->format('Y-m-t'), 'Этот месяц', 'month'];
    }
}

/**
 * Текст-шаблон «попросить отзыв» — НЕ подставляется автоматически по
 * статусу (в отличие от default_sms_message()), только по ручному клику
 * на кнопку в форме SMS (сотрудник сам решает, кому и когда отправить,
 * обычно — через несколько дней после выдачи заказа).
 */
function review_request_sms_message(array $repair): string
{
    $url = get_setting('yandex_reviews_url');
    $text = 'Здравствуйте! Вас приветствует сервис АВИОР. Если остались довольны ремонтом — будем очень благодарны за отзыв на Яндекс Картах';
    return $url ? ($text . ': ' . $url) : ($text . '.');
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
