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
 *
 * ВАЖНО про ответ SMS.ru: верхнеуровневый "status":"OK" означает только,
 * что сам HTTP-запрос выполнен (нет ошибок авторизации/отправителя) — это
 * НЕ означает, что конкретное сообщение реально принято к отправке.
 * Настоящий статус лежит в sms.<номер>.status (OK/ERROR) — именно его
 * нужно проверять, иначе CMS будет бодро писать «отправлено», даже если
 * SMS.ru молча отклонил конкретный номер (например, из-за модерации
 * текста, пока не одобрено своё имя отправителя, и т.п.).
 */

/**
 * @param string      $phone         Телефон клиента (в любом формате — нормализуется внутри).
 * @param string      $message       Текст сообщения.
 * @param int|null    $repairId      ID заказа (для привязки записи в sms_log).
 * @param string|null $errorMessage  Заполняется причиной ошибки при неудаче (для показа в интерфейсе).
 */
function send_sms(string $phone, string $message, ?int $repairId = null, ?string &$errorMessage = null): bool
{
    $fallback = config()['sms'] ?? ['provider' => null, 'api_key' => ''];
    $provider = get_setting('sms_provider') ?: ($fallback['provider'] ?? null);
    $apiId = get_setting('sms_api_key') ?: ($fallback['api_key'] ?? '');
    $status = 'not_configured';
    $success = false;
    $errorMessage = null;

    // Нормализация номера: SMS.ru ожидает только цифры, формат 7XXXXXXXXXX
    // (тот же паттерн, что уже используется в build_share_links() для wa.me).
    $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($phoneDigits !== '' && $phoneDigits[0] === '8' && strlen($phoneDigits) === 11) {
        $phoneDigits = '7' . substr($phoneDigits, 1);
    }

    if ($provider === 'smsru') {
        if ($apiId !== '' && $phoneDigits !== '') {
            $url = 'https://sms.ru/sms/send?' . http_build_query([
                'api_id' => $apiId,
                'to'     => $phoneDigits,
                'msg'    => $message,
                'json'   => 1,
            ]);
            $raw = @file_get_contents($url);
            $response = $raw !== false ? json_decode($raw, true) : null;

            $requestOk = ($response['status'] ?? '') === 'OK';
            $perNumber = $response['sms'][$phoneDigits] ?? null;
            $success = $requestOk && $perNumber !== null && ($perNumber['status'] ?? '') === 'OK';
            $status = $success ? 'sent' : 'failed';

            if (!$success) {
                if (!$requestOk) {
                    // Ошибка на уровне всего запроса — неверный api_id,
                    // проблема с отправителем и т.п.
                    $errorMessage = $response['status_text'] ?? ('Ошибка запроса (код ' . ($response['status_code'] ?? '?') . ')');
                } elseif ($perNumber) {
                    // Запрос выполнен, но конкретный номер отклонён
                    $errorMessage = ($perNumber['status_text'] ?? null)
                        ?: ('Ошибка отправки на номер (код ' . ($perNumber['status_code'] ?? '?') . ')');
                } elseif ($raw === false) {
                    $errorMessage = 'Не удалось связаться с sms.ru (сеть/файервол на сервере).';
                } else {
                    $errorMessage = 'Не удалось разобрать ответ sms.ru.';
                }
            }
        } elseif ($phoneDigits === '') {
            $status = 'failed';
            $errorMessage = 'У клиента не указан телефон или он в нераспознаваемом формате.';
        } else {
            $status = 'not_configured';
        }
    } elseif ($provider === 'smsc') {
        // Пример интеграции с SMSC.ru (https://smsc.ru/api/):
        // $url = 'https://smsc.ru/sys/send.php?' . http_build_query([
        //     'login' => $sms['login'] ?? '',
        //     'psw'   => $sms['password'] ?? '',
        //     'phones'=> $phoneDigits,
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
