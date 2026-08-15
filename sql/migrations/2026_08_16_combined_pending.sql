-- Объединённая миграция: finance_analytics + api_tokens + parts_warranty + public_token
-- Порядок важен: parts_warranty.cost зависит от finance_analytics (repair_parts.cost),
-- public_token.AFTER receipt_ready зависит от receipt_fields (уже применена ранее).
--
-- Все ADD COLUMN используют "IF NOT EXISTS" (MySQL 8.0.29+ / MariaDB 10.2+) —
-- безопасно запускать, даже если часть миграций уже была применена вручную.
--
-- Применить одной командой:
--   mysql -u avior_user -p avior_cms < 2026_08_16_combined_pending.sql
--
-- Перед запуском рекомендуется сделать бэкап:
--   mysqldump -u avior_user -p avior_cms > avior_cms_backup_$(date +%Y%m%d).sql

-- === 1. finance_analytics ===================================================

ALTER TABLE repairs
    ADD COLUMN IF NOT EXISTS order_type ENUM('repair', 'pc_build', 'account_memo') NOT NULL DEFAULT 'repair'
    AFTER order_no;

ALTER TABLE repair_parts
    ADD COLUMN IF NOT EXISTS cost DECIMAL(10,2) NOT NULL DEFAULT 0
    AFTER price;

CREATE TABLE IF NOT EXISTS expenses (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category     VARCHAR(100) NOT NULL,
    description  VARCHAR(255) NULL,
    amount       DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_expenses_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- === 2. api_tokens ===========================================================

CREATE TABLE IF NOT EXISTS api_tokens (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    token         CHAR(64) NOT NULL UNIQUE,
    device_label  VARCHAR(150) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at  DATETIME NULL,
    CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- === 3. parts_warranty (зависит от repair_parts.cost выше) =================

ALTER TABLE repair_parts
    ADD COLUMN IF NOT EXISTS warranty VARCHAR(50) NULL AFTER cost;

-- === 4. public_token (зависит от repairs.receipt_ready — уже есть) =========

ALTER TABLE repairs
    ADD COLUMN IF NOT EXISTS public_token VARCHAR(64) NULL UNIQUE AFTER receipt_ready;

-- Бэкафилл существующих заказов — каждому нужен свой уникальный токен.
-- Новые заказы получают токен уже при создании (generate_public_token()
-- в src/functions.php). Условие WHERE делает UPDATE безопасным при повторном запуске.
UPDATE repairs
SET public_token = SHA2(CONCAT(id, '-', UUID(), '-', NOW(6), '-', RAND()), 256)
WHERE public_token IS NULL;

-- === Проверка после применения ==============================================
-- DESCRIBE repairs;
-- DESCRIBE repair_parts;
-- SHOW TABLES LIKE 'expenses';
-- SHOW TABLES LIKE 'api_tokens';
-- SELECT COUNT(*) AS orders_without_token FROM repairs WHERE public_token IS NULL;  -- должно быть 0
