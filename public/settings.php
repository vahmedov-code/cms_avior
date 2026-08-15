<?php
/**
 * Настройки CMS — только администратор. Хранятся в таблице settings
 * (см. get_setting()/set_setting() в functions.php), применяются сразу,
 * без правки файлов на сервере. Задел под white-label: чтобы развернуть
 * CMS для другого сервиса, достаточно один раз заполнить эту форму —
 * менять код/config.php не нужно.
 */
require __DIR__ . '/../src/bootstrap.php';
require_admin();

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

  <div class="field full">
    <button type="submit" class="btn btn-primary">Сохранить настройки</button>
  </div>
</form>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
