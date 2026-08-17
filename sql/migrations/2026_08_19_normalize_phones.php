<?php
/**
 * Разовая очистка уже сохранённых номеров телефонов клиентов — приводит
 * все к единому формату «+7 (960) 123-45-67» (см. format_phone_ru() в
 * src/functions.php). Нужно выполнить один раз после обновления кода —
 * иначе уже существующие клиенты, сохранённые в разных форматах
 * (8960..., 7960..., просто 9601234567), так и останутся неправильными,
 * и WhatsApp/Telegram по ним продолжат не находиться (обсуждали 19.08).
 *
 * Запуск на сервере (это PHP-скрипт, НЕ SQL-файл — логика форматирования
 * написана на PHP, в чистом SQL так аккуратно не сделать):
 *   cd /var/www/cms
 *   php sql/migrations/2026_08_19_normalize_phones.php
 *
 * Безопасно перезапускать — номера, уже приведённые к нужному формату,
 * просто пропускаются (в выводе — «без изменений»).
 */
require __DIR__ . '/../../src/bootstrap.php';

$rows = db()->query('SELECT id, full_name, phone FROM clients')->fetchAll();

$updated = 0;
$skipped = 0;
$newPhoneCounts = [];

foreach ($rows as $row) {
    $newPhone = format_phone_ru($row['phone']);
    $newPhoneCounts[$newPhone] = ($newPhoneCounts[$newPhone] ?? 0) + 1;

    if ($newPhone === $row['phone']) {
        $skipped++;
        continue;
    }
    db()->prepare('UPDATE clients SET phone = ? WHERE id = ?')->execute([$newPhone, $row['id']]);
    echo "#{$row['id']} {$row['full_name']}: «{$row['phone']}» -> «{$newPhone}»\n";
    $updated++;
}

echo "\nГотово. Обновлено: {$updated}, без изменений: {$skipped}, всего: " . count($rows) . "\n";

// После нормализации у некоторых номеров могло обнаружиться совпадение
// (например, один и тот же клиент когда-то был случайно заведён дважды
// в разных форматах телефона) — такое НЕ удаляется автоматически,
// только показывается, чтобы посмотреть и решить руками при желании.
$duplicates = array_filter($newPhoneCounts, fn($count) => $count > 1);
if ($duplicates) {
    echo "\nВозможные дубли по телефону после нормализации (проверьте вручную, ничего не удалялось само):\n";
    foreach ($duplicates as $phone => $count) {
        echo "  {$phone} — встречается {$count} раз(а)\n";
    }
}
