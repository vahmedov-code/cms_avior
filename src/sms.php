<?php
/**
 * Отправка SMS клиентам. Три поддерживаемых провайдера — see below.
 * Настраивается через CRM: администратор → Настройки (settings.php),
 * без правки файлов на сервере (хранится в таблице settings, см.
 * get_setting()/set_setting() в functions.php). Запасной вариант — старый
 * способ через config/config.php, если settings.php ещё не используется.
 *
 * 1) SMS.ru (https://sms.ru/api) — базовая схема без своего
 *    зарегистрированного имени отправителя: от 25 коп/SMS, от 7 коп при
 *    расходе >5000₽/мес. Без абонплаты. НО: без одобренного буквенного
 *    отправителя SMS.ru в принципе отказывается слать (проверено на
 *    практике — «Для работы с нашим сервисом необходимо создать
 *    буквенного отправителя»). Регистрация имени — 5-7 рабочих дней.
 *
 * 2) SMSC.ru — заглушка, интеграция не реализована (см. закомментированный
 *    пример ниже).
 *
 * 3) Android-шлюз (android_gateway) — SMS Gateway for Android
 *    (https://github.com/capcom6/android-sms-gateway), облачный режим.
 *    Свой Android-телефон с SIM-картой отправляет SMS через собственную
 *    сотовую сеть — клиент видит настоящий номер сервиса, без модерации
 *    и абонплаты за имя. Телефону нужен только интернет (Wi-Fi/мобильные
 *    данные) — команда идёт через api.sms-gate.app, который сам держит
 *    соединение с телефоном (обходит CGNAT операторов, проброс портов
 *    не нужен). SIM-карта обязательна — сама SMS всё равно уходит через
 *    сотовую сеть, интернет только доставляет команду «отправь».
 *    Настройка на телефоне: приложение → переключить «Cloud Server» →
 *    «Online» → скопировать логин/пароль в Настройки CRM.
 *
 * Если провайдер нигде не задан — сообщение просто логируется в
 * sms_log со статусом 'not_configured', ничего никуда не уходит
 * (безопасно для разработки/тестов).
 *
 * ВАЖНО про ответ SMS.ru: верхнеуровневый "status":"OK" означает только,
 * что сам HTTP-запрос выполнен (нет ошибок авторизации/отправителя) — это
 * НЕ означает, что конкретное сообщение реально принято к отправке.
 * Настоящий статус лежит в sms.<номер>.status (OK/ERROR) — именно его
 * нужно проверять, иначе CRM будет бодро писать «отправлено», даже если
 * SMS.ru молча отклонил конкретный номер.
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
    } elseif ($provider === 'android_gateway') {
        // SMS Gateway for Android (https://github.com/capcom6/android-sms-gateway),
        // облачный режим — SMS реально уходит через SIM-карту в собственном
        // телефоне (свой номер как отправитель), без модерации/абонплаты.
        // Телефону нужен только обычный интернет — команда идёт через
        // api.sms-gate.app, который держит с ним постоянное соединение
        // (обходит проблему CGNAT у мобильных операторов).
        $login = get_setting('sms_gateway_login') ?: '';
        $password = get_setting('sms_gateway_password') ?: '';
        if ($login !== '' && $password !== '' && $phoneDigits !== '') {
            $body = json_encode([
                'textMessage'  => ['text' => $message],
                'phoneNumbers' => ['+' . $phoneDigits],
            ]);
            $context = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Content-Type: application/json\r\n"
                        . 'Authorization: Basic ' . base64_encode($login . ':' . $password) . "\r\n",
                    'content'       => $body,
                    'timeout'       => 15,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = @file_get_contents('https://api.sms-gate.app/3rdparty/v1/message', false, $context);
            $httpCode = 0;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $httpCode = (int) $m[1];
            }
            $response = $raw !== false ? json_decode($raw, true) : null;
            $success = $httpCode >= 200 && $httpCode < 300;
            $status = $success ? 'sent' : 'failed';
            if (!$success) {
                $errorMessage = $response['message'] ?? $response['error']
                    ?? ('HTTP ' . $httpCode . ($raw === false ? ' — нет ответа от api.sms-gate.app' : ''));
            }
        } elseif ($phoneDigits === '') {
            $status = 'failed';
            $errorMessage = 'У клиента не указан телефон или он в нераспознаваемом формате.';
        } else {
            $status = 'not_configured';
        }
    }

    $stmt = db()->prepare(
        'INSERT INTO sms_log (repair_id, phone, message, status) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$repairId, $phone, $message, $status]);

    return $success;
}
