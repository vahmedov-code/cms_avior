<?php
/**
 * Отправка SMS клиентам через SMS.ru (https://sms.ru/api) — базовая схема
 * без своего зарегистрированного имени отправителя (сообщение уходит с
 * общего/бесплатного имени): от 25 коп/SMS, от 7 коп при расходе
 * >5000₽/мес. Никакой абонентской платы, платите по факту отправленных
 * сообщений — подходит для разовых уведомлений по заказу.
 *
 * Включить:
 *  1. Зарегистрироваться на sms.ru, получить api_id (личный кабинет →
 *     Настройки → API).
 *  2. В config/config.php прописать:
 *       'sms' => ['provider' => 'smsru', 'api_key' => 'ВАШ_API_ID'],
 *  3. Всё — функция send_sms() сразу начнёт реально отправлять,
 *     менять код на страницах (repair_view.php и т.д.) не требуется.
 *
 * Если 'provider' не задан (null) — сообщение просто логируется в
 * sms_log со статусом 'not_configured', ничего никуда не уходит
 * (безопасно для разработки/тестов).
 */

function send_sms(string $phone, string $message, ?int $repairId = null): bool
{
    $sms = config()['sms'] ?? ['provider' => null];
    $provider = $sms['provider'] ?? null;
    $status = 'not_configured';
    $success = false;

    if ($provider === 'smsru') {
        $apiId = $sms['api_key'] ?? '';
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
