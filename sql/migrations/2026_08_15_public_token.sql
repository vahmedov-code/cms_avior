-- Уникальный публичный токен заказа — для ссылок на квитанцию/акт,
-- которые открываются без входа в CMS (мобильное приложение, WhatsApp/
-- Telegram/Email). Заменяет проверку по order_no+phone более простой и
-- безопасной схемой id+token (не палит телефон клиента в ссылке).
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_15_public_token.sql

ALTER TABLE repairs
    ADD COLUMN public_token VARCHAR(64) NULL UNIQUE AFTER receipt_ready;

-- Бэкафилл существующих заказов — каждому нужен свой уникальный токен.
-- Новые заказы получают токен уже при создании (см. generate_public_token()
-- в src/functions.php), это только для тех, что были созданы до миграции.
UPDATE repairs
SET public_token = SHA2(CONCAT(id, '-', UUID(), '-', NOW(6), '-', RAND()), 256)
WHERE public_token IS NULL;
