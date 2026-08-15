-- Миграция: тип заказа, себестоимость комплектующих, расходы.
-- Применить на уже развёрнутой базе:
--   mysql -u avior_user -p avior_cms < sql/migrations/2026_08_15_finance_analytics.sql

ALTER TABLE repairs
    ADD COLUMN order_type ENUM('repair', 'pc_build', 'account_memo') NOT NULL DEFAULT 'repair'
    AFTER order_no;

ALTER TABLE repair_parts
    ADD COLUMN cost DECIMAL(10,2) NOT NULL DEFAULT 0
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
