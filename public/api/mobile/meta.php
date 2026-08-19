<?php
/**
 * Справочники для выпадающих списков в приложении — статусы заказов,
 * типы заказов, источники клиентов, типы устройств, каталог моделей.
 * GET, требует токен.
 */
require __DIR__ . '/../../../src/bootstrap.php';
require __DIR__ . '/../../../src/api_helpers.php';
api_bootstrap();
api_require_auth();

// Каталог моделей — та же таблица, что питает подсказки в веб-CRM
// (repair_new.php). ~200 записей — приложение фильтрует локально по мере
// ввода, отдельный поисковый эндпоинт не нужен при таком объёме.
$modelsStmt = db()->query('SELECT name FROM device_model_catalog ORDER BY name');
$deviceModels = array_column($modelsStmt->fetchAll(), 'name');

api_json([
    'ok'             => true,
    'statuses'       => ['принят', 'диагностика', 'согласование', 'ждёт детали', 'в ремонте', 'готов', 'выдан', 'отказ'],
    'order_types'    => order_types(),
    'client_sources' => client_sources(),
    'device_types'   => array_keys(device_type_options()),
    'device_models'  => $deviceModels,
]);
