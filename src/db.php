<?php
/**
 * Подключение к MySQL через PDO. Используйте функцию db() везде,
 * она создаёт соединение один раз (singleton) и переиспользует его.
 */

function config(): array
{
    static $config = null;
    if ($config === null) {
        $path = __DIR__ . '/../config/config.php';
        if (!file_exists($path)) {
            http_response_code(500);
            die('Файл config/config.php не найден. Скопируйте config/config.example.php в config/config.php и заполните данные подключения к БД.');
        }
        $config = require $path;
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = config()['db'];
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );
        try {
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Ошибка подключения к базе данных. Проверьте config/config.php.');
        }
    }
    return $pdo;
}
