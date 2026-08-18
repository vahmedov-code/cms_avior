<?php
/**
 * Настройки CRM — только владельцу. Хранятся в таблице settings
 * (см. get_setting()/set_setting() в functions.php), применяются сразу,
 * без правки файлов на сервере. Задел под white-label: чтобы развернуть
 * CRM для другого сервиса, достаточно один раз заполнить эту форму —
 * менять код/config.php не нужно.
 */
require __DIR__ . '/../src/bootstrap.php';
require_owner();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'generate_ai_token') {
    try {
        $newToken = bin2hex(random_bytes(24));
        set_setting('ai_api_token', $newToken);
        flash_set('Новый токен сгенерирован. Старый больше не действует.', 'success');
    } catch (PDOException $e) {
        flash_set('Не удалось сохранить: таблица settings ещё не создана. Примените миграцию.', 'error');
    }
    redirect('settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'revoke_ai_token') {
    try {
        set_setting('ai_api_token', '');
        flash_set('Токен отозван. AI-сводка отключена.', 'success');
    } catch (PDOException $e) {
        flash_set('Не удалось сохранить: таблица settings ещё не создана. Примените миграцию.', 'error');
    }
    redirect('settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'generate_lead_secret') {
    try {
        $newSecret = bin2hex(random_bytes(24));
        set_setting('lead_intake_secret', $newSecret);
        flash_set('Новый секрет сгенерирован. Не забудьте обновить его в lead.php на avior.moscow (config.php сайта, ключ crm_lead_secret) — иначе заявки с сайта перестанут долетать в CRM.', 'success');
    } catch (PDOException $e) {
        flash_set('Не удалось сохранить: таблица settings ещё не создана. Примените миграцию.', 'error');
    }
    redirect('settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pairs = [
        'company_name'         => trim(post('company_name')),
        'company_address'      => trim(post('company_address')),
        'company_phone'        => trim(post('company_phone')),
        'site_url'             => rtrim(trim(post('site_url')), '/'),
        'sms_provider'         => post('sms_provider') === 'none' ? '' : post('sms_provider'),
        'sms_api_key'          => trim(post('sms_api_key')),
        'sms_gateway_login'    => trim(post('sms_gateway_login')),
        'sms_gateway_password' => trim(post('sms_gateway_password')),
        'legal_name'           => trim(post('legal_name')),
        'legal_inn'            => trim(post('legal_inn')),
        'legal_kpp'            => trim(post('legal_kpp')),
        'legal_ogrn'           => trim(post('legal_ogrn')),
        'legal_email'          => trim(post('legal_email')),
        'bank_name'            => trim(post('bank_name')),
        'bank_account'         => trim(post('bank_account')),
        'bank_bik'             => trim(post('bank_bik')),
        'bank_corr_account'    => trim(post('bank_corr_account')),
        'yandex_reviews_url'   => trim(post('yandex_reviews_url')),
        'tax_operator'         => post('tax_operator'),
        'tax_operator_login'   => trim(post('tax_operator_login')),
        'tax_operator_token'   => trim(post('tax_operator_token')),
        'bulk_sms_api_key'     => trim(post('bulk_sms_api_key')),
        'kkm_server_url'       => rtrim(trim(post('kkm_server_url')), '/'),
        'kkm_login'            => trim(post('kkm_login')),
        'kkm_password'         => trim(post('kkm_password')),
        'kkm_num_device'       => trim(post('kkm_num_device')),
        'ypay_merchant_id'     => trim(post('ypay_merchant_id')),
        'ypay_api_key'         => trim(post('ypay_api_key')),
        'ypay_software_auth'   => trim(post('ypay_software_auth')),
        'ypay_sandbox'         => post('ypay_sandbox') === '1' ? '1' : '0',
    ];
    try {
        foreach ($pairs as $key => $value) {
            set_setting($key, (string) $value);
        }
        flash_set('Настройки сохранены.', 'success');
    } catch (PDOException $e) {
        flash_set(
            'Не удалось сохранить: таблица settings ещё не создана на сервере. '
            . 'Примените миграцию sql/migrations/2026_08_17_settings.sql и попробуйте снова.',
            'error'
        );
    }
    redirect('settings.php');
}

$company = company_info();
$currentSiteUrl = get_setting('site_url') ?? (config()['site_url'] ?? '');
$currentProvider = get_setting('sms_provider');
if ($currentProvider === null) {
    $currentProvider = config()['sms']['provider'] ?? '';
}
$currentApiKey = get_setting('sms_api_key') ?? (config()['sms']['api_key'] ?? '');
$currentGatewayLogin = get_setting('sms_gateway_login') ?? '';
$currentGatewayPassword = get_setting('sms_gateway_password') ?? '';
$currentAiToken = get_setting('ai_api_token') ?? '';
$currentLegalName = get_setting('legal_name') ?? '';
$currentLegalInn = get_setting('legal_inn') ?? '';
$currentLegalKpp = get_setting('legal_kpp') ?? '';
$currentLegalOgrn = get_setting('legal_ogrn') ?? '';
$currentLegalEmail = get_setting('legal_email') ?? '';
$currentBankName = get_setting('bank_name') ?? '';
$currentBankAccount = get_setting('bank_account') ?? '';
$currentBankBik = get_setting('bank_bik') ?? '';
$currentBankCorrAccount = get_setting('bank_corr_account') ?? '';
$currentYandexReviewsUrl = get_setting('yandex_reviews_url') ?? '';
$currentLeadIntakeSecret = get_setting('lead_intake_secret') ?? '';
$currentTaxOperator = get_setting('tax_operator') ?? 'sbis';
$currentTaxOperatorLogin = get_setting('tax_operator_login') ?? '';
$currentTaxOperatorToken = get_setting('tax_operator_token') ?? '';
$currentBulkSmsApiKey = get_setting('bulk_sms_api_key') ?? '';
$currentKkmServerUrl = get_setting('kkm_server_url') ?? 'http://localhost:5893';
$currentKkmLogin = get_setting('kkm_login') ?? 'User';
$currentKkmPassword = get_setting('kkm_password') ?? '';
$currentKkmNumDevice = get_setting('kkm_num_device') ?? '1';
$currentYpayMerchantId = get_setting('ypay_merchant_id') ?? '';
$currentYpayApiKey = get_setting('ypay_api_key') ?? '';
$currentYpaySoftwareAuth = get_setting('ypay_software_auth') ?? '';
$currentYpaySandbox = get_setting('ypay_sandbox') === '1';

$pageTitle = 'Настройки';
$activeNav = 'settings';
require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>⚙️ Настройки</h2>
</div>

<p style="color:var(--muted);font-size:13px;max-width:640px;margin-bottom:20px;">
  Хранятся в базе данных и применяются сразу. Если поле оставить пустым —
  используется значение по умолчанию (для реквизитов) или значение из
  <code>config/config.php</code> на сервере (для site_url и SMS, если там
  уже что-то настроено вручную).
</p>

<form method="post" class="form-grid" style="max-width:640px;">
  <h3 style="grid-column:1/-1;color:var(--navy);font-size:15px;margin:0 0 4px;">Реквизиты компании</h3>
  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:-6px 0 0;">
    Используются в шапке квитанции, акта и других печатных документов.
  </p>
  <label class="field full">Название
    <input type="text" name="company_name" value="<?= e($company['name']) ?>">
  </label>
  <label class="field full">Адрес
    <input type="text" name="company_address" value="<?= e($company['address']) ?>">
  </label>
  <label class="field">Телефон
    <input type="text" name="company_phone" value="<?= e($company['phone']) ?>">
  </label>

  <h3 style="grid-column:1/-1;color:var(--navy);font-size:15px;margin:20px 0 4px;">Юридические реквизиты</h3>
  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:-6px 0 0;">
    Отдельно от названия для клиентов (АВИОР) выше — это точное
    юридическое наименование ИП/ООО, для документов, где нужны официальные
    реквизиты. Пока нигде в печатных формах не используется — задел на
    будущее, чтобы данные не пришлось вводить второй раз.
  </p>
  <label class="field full">Наименование (юр. лицо)
    <input type="text" name="legal_name" value="<?= e($currentLegalName) ?>" placeholder="ИП Иванов Иван Иванович">
  </label>
  <label class="field">ИНН
    <input type="text" name="legal_inn" value="<?= e($currentLegalInn) ?>">
  </label>
  <label class="field">КПП (если есть)
    <input type="text" name="legal_kpp" value="<?= e($currentLegalKpp) ?>">
  </label>
  <label class="field">ОГРН / ОГРНИП
    <input type="text" name="legal_ogrn" value="<?= e($currentLegalOgrn) ?>">
  </label>
  <label class="field">Email
    <input type="email" name="legal_email" value="<?= e($currentLegalEmail) ?>">
  </label>

  <h3 style="grid-column:1/-1;color:var(--navy);font-size:15px;margin:20px 0 4px;">Банковские реквизиты</h3>
  <label class="field full">Банк
    <input type="text" name="bank_name" value="<?= e($currentBankName) ?>">
  </label>
  <label class="field">Расчётный счёт
    <input type="text" name="bank_account" value="<?= e($currentBankAccount) ?>">
  </label>
  <label class="field">БИК
    <input type="text" name="bank_bik" value="<?= e($currentBankBik) ?>">
  </label>
  <label class="field">Корр. счёт
    <input type="text" name="bank_corr_account" value="<?= e($currentBankCorrAccount) ?>">
  </label>

  <h3 style="grid-column:1/-1;color:var(--navy);font-size:15px;margin:20px 0 4px;">Публичный адрес сайта</h3>
  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:-6px 0 0;">
    Нужен для QR-кодов на квитанции/акте и публичных ссылок на них
    (WhatsApp/Telegram/Email). Без этого поля QR-коды и кнопки отправки
    просто не показываются.
  </p>
  <label class="field full">Site URL
    <input type="text" name="site_url" value="<?= e($currentSiteUrl) ?>" placeholder="https://cms.avior.moscow">
  </label>

  <h3 style="grid-column:1/-1;color:var(--navy);font-size:15px;margin:20px 0 4px;">SMS-провайдер</h3>
  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:-6px 0 0;">
    SMS.ru — дешёвая схема без своего имени отправителя (от 25 коп/SMS),
    но требует одобрения буквенного имени (5-7 рабочих дней). Android-шлюз —
    свой телефон с SIM-картой отправляет SMS через себя, без модерации,
    но телефон должен быть постоянно на связи (интернет + SIM).
  </p>
  <label class="field full">Провайдер
    <select name="sms_provider">
      <option value="none" <?= $currentProvider === '' || $currentProvider === null ? 'selected' : '' ?>>Не подключено</option>
      <option value="smsru" <?= $currentProvider === 'smsru' ? 'selected' : '' ?>>SMS.ru</option>
      <option value="smsc" <?= $currentProvider === 'smsc' ? 'selected' : '' ?>>SMSC.ru</option>
      <option value="android_gateway" <?= $currentProvider === 'android_gateway' ? 'selected' : '' ?>>Android-шлюз (свой телефон)</option>
    </select>
  </label>

  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:16px 0 0;font-weight:600;">Для SMS.ru:</p>
  <label class="field full">API-ключ (api_id из личного кабинета sms.ru → Настройки → API)
    <input type="text" name="sms_api_key" value="<?= e($currentApiKey) ?>">
  </label>

  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:16px 0 0;font-weight:600;">Для Android-шлюза (облачный режим приложения SMS Gateway for Android):</p>
  <label class="field">Логин
    <input type="text" name="sms_gateway_login" value="<?= e($currentGatewayLogin) ?>" autocomplete="off">
  </label>
  <label class="field">Пароль
    <input type="text" name="sms_gateway_password" value="<?= e($currentGatewayPassword) ?>" autocomplete="off">
  </label>
  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:-6px 0 0;">
    В приложении на телефоне: включить переключатель «Cloud Server» →
    нажать «Online» внизу экрана → логин и пароль появятся в разделе
    Cloud Server — скопировать сюда как есть.
  </p>

  <h3 style="grid-column:1/-1;color:var(--navy);font-size:15px;margin:20px 0 4px;">Ссылка на отзыв (Яндекс Карты)</h3>
  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:-6px 0 0;">
    Используется в шаблоне SMS-просьбы об отзыве — кнопка «📝 Попросить
    отзыв» на странице заказа подставит эту ссылку в текст сообщения.
    Найти свою: откройте карточку организации на Яндекс Картах → «Оставить
    отзыв» → скопируйте ссылку из адресной строки.
  </p>
  <label class="field full">Ссылка
    <input type="text" name="yandex_reviews_url" value="<?= e($currentYandexReviewsUrl) ?>" placeholder="https://yandex.ru/maps/org/.../reviews/">
  </label>

  <h3 style="grid-column:1/-1;color:var(--navy);font-size:15px;margin:20px 0 4px;">Массовые SMS-рассылки</h3>
  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:-6px 0 0;">
    Отдельный ключ SMS.ru — только для массовых рассылок клиентам
    (раздел «SMS-рассылки» на панели). Обычные уведомления по заказу
    (выше) продолжают идти через провайдер из блока «SMS-провайдер» —
    эти два ключа независимы друг от друга. Массовая рассылка
    <strong>всегда</strong> идёт только через SMS.ru — с личного номера/
    Android-шлюза рассылать нельзя (риск блокировки номера оператором
    за спам-паттерн, обсуждали отдельно).
  </p>
  <label class="field full">api_id (SMS.ru)
    <input type="text" name="bulk_sms_api_key" value="<?= e($currentBulkSmsApiKey) ?>">
  </label>

  <h3 style="grid-column:1/-1;color:var(--navy);font-size:15px;margin:20px 0 4px;">Налоговая отчётность (оператор ЭДО)</h3>
  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:-6px 0 0;">
    Задел на будущее (обсуждали 19.08) — реальной отправки документов
    через оператора пока нет, только КУДиР (раздел «КУДиР» на панели,
    считается сама из оплат, без внешних сервисов). Когда будет готова
    интеграция с СБИС (или другим оператором) — учётные данные уже
    будут на месте, менять код не придётся.
  </p>
  <label class="field">Оператор
    <select name="tax_operator">
      <option value="sbis" <?= $currentTaxOperator === 'sbis' ? 'selected' : '' ?>>СБИС</option>
      <option value="kontur" <?= $currentTaxOperator === 'kontur' ? 'selected' : '' ?>>Контур.Экстерн</option>
      <option value="taxcom" <?= $currentTaxOperator === 'taxcom' ? 'selected' : '' ?>>Такском</option>
    </select>
  </label>
  <label class="field">Логин
    <input type="text" name="tax_operator_login" value="<?= e($currentTaxOperatorLogin) ?>">
  </label>
  <label class="field">Токен / пароль API
    <input type="text" name="tax_operator_token" value="<?= e($currentTaxOperatorToken) ?>">
  </label>

  <h3 style="grid-column:1/-1;color:var(--navy);font-size:15px;margin:20px 0 4px;">Касса АТОЛ (KkmServer)</h3>
  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:-6px 0 0;">
    Печать чеков идёт напрямую из браузера сотрудника на локальный
    адрес KkmServer — работает только с того компьютера, где стоит
    касса и запущен KkmServer. Логин/пароль — из вкладки KkmServer
    (по умолчанию логин <code>User</code>, пароль пустой).
    Если адрес по умолчанию (5893, обычный HTTP) не сработает из-за
    блокировки браузером — попробуйте порт 5894 (<code>https://localhost:5894</code>),
    там свой сертификат KkmServer, его нужно один раз принять в браузере.
  </p>
  <label class="field full">Адрес KkmServer
    <input type="text" name="kkm_server_url" value="<?= e($currentKkmServerUrl) ?>" placeholder="http://localhost:5893">
  </label>
  <label class="field">Логин
    <input type="text" name="kkm_login" value="<?= e($currentKkmLogin) ?>">
  </label>
  <label class="field">Пароль
    <input type="text" name="kkm_password" value="<?= e($currentKkmPassword) ?>">
  </label>
  <label class="field">№ устройства (NumDevice)
    <input type="text" name="kkm_num_device" value="<?= e($currentKkmNumDevice) ?>" style="max-width:100px;">
  </label>

  <h3 style="grid-column:1/-1;color:var(--navy);font-size:15px;margin:20px 0 4px;">Яндекс Пэй / Сплит (Cash Register API)</h3>
  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:-6px 0 0;">
    Нужна регистрация в личном кабинете Яндекс Пэй (console.pay.yandex.ru) —
    заявка на подключение «QR-код от Яндекс Пэй» (поле «Кассовое ПО» —
    выбрать «Другое»). Merchant API Key выпускается там же в Настройках,
    Software-Authorization токен и Merchant ID выдаёт менеджер интеграции.
    Без этих трёх значений кнопка «Яндекс Сплит» работать не будет.
  </p>
  <label class="field full">Merchant ID
    <input type="text" name="ypay_merchant_id" value="<?= e($currentYpayMerchantId) ?>">
  </label>
  <label class="field full">Merchant API Key
    <input type="text" name="ypay_api_key" value="<?= e($currentYpayApiKey) ?>">
  </label>
  <label class="field full">Software-Authorization (токен кассового ПО)
    <input type="text" name="ypay_software_auth" value="<?= e($currentYpaySoftwareAuth) ?>">
  </label>
  <label class="field full" style="flex-direction:row;align-items:center;gap:8px;">
    <input type="checkbox" name="ypay_sandbox" value="1" <?= $currentYpaySandbox ? 'checked' : '' ?>>
    <span>Тестовая среда (sandbox) — включить на время проверки, перед боевой работой выключить</span>
  </label>

  <div class="field full">
    <button type="submit" class="btn btn-primary">Сохранить настройки</button>
  </div>
</form>

<h3 style="color:var(--navy);font-size:15px;margin:28px 0 4px;">Приём заявок с сайта (avior.moscow)</h3>
<p style="color:var(--muted);font-size:13px;max-width:640px;margin:0 0 12px;">
  Лид-форма на avior.moscow (<code>lead.php</code>) отправляет заявки в
  MAX-мессенджер и, если секрет ниже настроен, ОДНОВРЕМЕННО создаёт
  новый заказ прямо в CRM (эндпоинт <code>api/lead_intake.php</code>) —
  без ручного переноса. Секрет — общий с <code>config.php</code> сайта
  avior.moscow (ключ <code>crm_lead_secret</code>) — если поменяете
  здесь, обязательно обновите и там, иначе перестанет работать именно
  вторая часть (MAX продолжит приходить в любом случае, это отдельный
  канал).
</p>
<?php if ($currentLeadIntakeSecret): ?>
  <div class="table-card" style="padding:14px 16px;max-width:640px;margin-bottom:12px;">
    <div style="font-size:12px;color:var(--muted);margin-bottom:4px;">Текущий секрет</div>
    <code style="word-break:break-all;font-size:13px;"><?= e($currentLeadIntakeSecret) ?></code>
  </div>
  <div style="display:flex;gap:8px;margin-bottom:24px;">
    <form method="post" onsubmit="return confirm('Старый секрет перестанет работать, заявки с сайта в CRM прекратятся, пока не обновите его и в config.php сайта. Продолжить?');">
      <input type="hidden" name="action" value="generate_lead_secret">
      <button type="submit" class="btn btn-sm">🔄 Перевыпустить секрет</button>
    </form>
  </div>
<?php else: ?>
  <form method="post" style="margin-bottom:24px;">
    <input type="hidden" name="action" value="generate_lead_secret">
    <button type="submit" class="btn btn-primary btn-sm">Сгенерировать секрет</button>
  </form>
<?php endif; ?>

<h3 style="color:var(--navy);font-size:15px;margin:28px 0 4px;">AI-сводка (для анализа ассистентом)</h3>
<p style="color:var(--muted);font-size:13px;max-width:640px;margin:0 0 12px;">
  Отдельный токен для read-only эндпоинта <code>/api/ai/summary.php</code> —
  отдаёт сводку по заказам/финансам/клиентам в JSON, ничего не создаёт и
  не меняет. Дайте этот токен ассистенту (Claude), чтобы он мог отвечать
  на вопросы вроде «как дела в сервисе» без входа в CRM.
</p>
<?php if ($currentAiToken): ?>
  <div class="table-card" style="padding:14px 16px;max-width:640px;margin-bottom:12px;">
    <div style="font-size:12px;color:var(--muted);margin-bottom:4px;">Текущий токен</div>
    <code style="word-break:break-all;font-size:13px;"><?= e($currentAiToken) ?></code>
    <div style="font-size:12px;color:var(--muted);margin-top:10px;">Пример запроса:</div>
    <code style="word-break:break-all;font-size:12px;display:block;margin-top:2px;">
      <?= e(rtrim($currentSiteUrl ?: 'https://cms.avior.moscow', '/')) ?>/api/ai/summary.php?token=<?= e($currentAiToken) ?>&period=month
    </code>
  </div>
  <div style="display:flex;gap:8px;margin-bottom:24px;">
    <form method="post" onsubmit="return confirm('Старый токен перестанет работать. Продолжить?');">
      <input type="hidden" name="action" value="generate_ai_token">
      <button type="submit" class="btn btn-sm">🔄 Перевыпустить токен</button>
    </form>
    <form method="post" onsubmit="return confirm('AI-сводка перестанет отвечать. Продолжить?');">
      <input type="hidden" name="action" value="revoke_ai_token">
      <button type="submit" class="btn btn-sm btn-warn">Отозвать</button>
    </form>
  </div>
<?php else: ?>
  <form method="post" style="margin-bottom:24px;">
    <input type="hidden" name="action" value="generate_ai_token">
    <button type="submit" class="btn btn-primary btn-sm">Сгенерировать токен</button>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
