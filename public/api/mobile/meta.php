<?php
/**
 * Справочники для выпадающих списков в приложении — статусы заказов,
 * типы заказов, источники клиентов. GET, требует токен.
 */
require __DIR__ . '/../../../src/bootstrap.php';
require __DIR__ . '/../../../src/api_helpers.php';
api_bootstrap();
api_require_auth();

api_json([
    'ok'             => true,
    'statuses'       => ['принят', 'диагностика', 'согласование', 'в ремонте', 'готов', 'выдан', 'отказ'],
    'order_types'    => order_types(),
    'client_sources' => client_sources(),
]);
