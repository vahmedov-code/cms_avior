<?php
/**
 * Отправка SMS клиентам. Провайдер пока не выбран — функция логирует
 * сообщение в sms_log со статусом 'not_configured' и ничего не отправляет.
 *
 * Когда определитесь с провайдером (например, SMS.ru или SMSC.ru):
 *  1. Впишите 'provider' и 'api_key' (или login/password) в config/config.php.
 *  2. Раскомментируйте и доработайте соответствующий блок ниже.
 *  3. Функция send_sms() начнёт реально отправлять сообщения — менять
 *     код на страницах (repair_view.php и т.д.) не потребуется.
 */

function send_sms(string $phone, string $message, ?int $repairId = null): bool
{
    $sms = config()['sms'] ?? ['provider' => null];
    $provider = $sms['provider'] ?? null;
    $status = 'not_configured';
    $success = false;

    if ($provider === 'smsru') {
        // Пример интеграции с SMS.ru (https://sms.ru/api):
        // $url = 'https://sms.ru/sms/send?' . http_build_query([
        //     'api_id' => $sms['api_key'],
        //     'to'     => $phone,
        //     'msg'    => $message,
        //     'json'   => 1,
        // ]);
        // $response = json_decode(file_get_contents($url), true);
        // $success  = ($response['status'] ?? '') === 'OK';
        // $status   = $success ? 'sent' : 'failed';
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
