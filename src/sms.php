<?php
/**
 * Отправка SMS клиентам через SMS.ru (https://sms.ru/api) — базовая схема
 * без своего зарегистрированного имени отправителя (сообщение уходит с
 * общего/бесплатного имени): от 25 коп/SMS, от 7 коп при расходе
 * >5000₽/мес. Никакой абонентской платы, платите по факту отправленных
 * сообщений — подходит для разовых уведомлений по заказу.
 *
 * Включить (самый простой способ — прямо в интерфейсе):
 *  1. Зарегистрироваться на sms.ru, получить api_id (личный кабинет →
 *     Настройки → API).
 *  2. Войти в CMS администратором → Настройки (settings.php) → вписать
 *     провайдера и api_id, сохранить. Хранится в БД (таблица settings),
 *     правка файлов на сервере не нужна.
 *
 * Запасной вариант (если таблица settings ещё не создана/недоступна) —
 * старый способ через config/config.php:
 *       'sms' => ['provider' => 'smsru', 'api_key' => 'ВАШ_API_ID'],
 * get_setting() из settings.php имеет приоритет; если там пусто —
 * используется значение из config.php.
 *
 * Если провайдер нигде не задан — сообщение просто логируется в
 * sms_log со статусом 'not_configured', ничего никуда не уходит
 * (безопасно для разработки/тестов).
 */

function send_sms(string $phone, string $message, ?int $repairId = null): bool
{
    $fallback = config()['sms'] ?? ['provider' => null, 'api_key' => ''];
    $provider = get_setting('sms_provider') ?: ($fallback['provider'] ?? null);
    $apiId = get_setting('sms_api_key') ?: ($fallback['api_key'] ?? '');
    $status = 'not_configured';
    $success = false;

    if ($provider === 'smsru') {
        if ($apiId !== '') {
            $url = 'https://sms.ru/sms/send?' . http_build_query([
                'api_id' => $apiId,
                'to'     => $phone,
                'msg'    => $message,
                'json'   => 1,
            ]);
            $raw = @file_get_contents($url);
            $response = $raw !== false ? json_decode($raw, true) : null;
            $success = ($response['status'] ?? '') === 'OK';
            // Структура ответа: status_code=100 — отправлено; sms.<номер>.status
            // содержит статус конкретного сообщения (может отличаться от
            // общего status при частичной ошибке в мультиномерной отправке —
            // у нас всегда один номер, поэтому общего status достаточно).
            $status = $success ? 'sent' : 'failed';
        } else {
            $status = 'not_configured';
        }
    } elseif ($provider === 'smsc') {
        // Пример интеграции с SMSC.ru (https://smsc.ru/api/):
        // $url = 'https://smsc.ru/sys/send.php?' . http_build_query([
        //     'login' => $sms['login'] ?? '',
        //     'psw'   => $sms['password'] ?? '',
        //     'phones'=> $phone,
        //     'mes'   => $message,
        //     'fmt'   => 3,
        // ]);
        // $response = json_decode(file_get_contents($url), true);
        // $success  = isset($response['id']);
        // $status   = $success ? 'sent' : 'failed';
    }

    $stmt = db()->prepare(
        'INSERT INTO sms_log (repair_id, phone, message, status) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$repairId, $phone, $message, $status]);

    return $success;
}
