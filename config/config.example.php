<?php
/**
 * АВИОР CMS — конфигурация.
 *
 * 1. Скопируйте этот файл в config.php (в той же папке):
 *      cp config.example.php config.php
 * 2. Заполните реальные данные подключения к MySQL.
 * 3. config.php НЕ должен попадать в git (уже в .gitignore).
 */

return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'avior_cms',
        'user'    => 'avior_user',
        'pass'    => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],

    // Публичный адрес сайта — используется для ссылок и API статуса ремонта
    'site_url' => 'https://example.ru',

    // SMS-провайдер подключим позже. Поддерживаемые варианты: 'smsru', 'smsc', null (выключено).
    'sms' => [
        'provider' => null,
        'api_key'  => '',
        // Для smsc.ru может понадобиться login/password вместо api_key —
        // добавьте нужные поля здесь, когда определитесь с провайдером.
    ],
];
