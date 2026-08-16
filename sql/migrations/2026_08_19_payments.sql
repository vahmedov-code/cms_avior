-- Оплата заказов (касса АТОЛ через KkmServer + ручная отметка оплаты).
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_19_payments.sql

CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repair_id       INT UNSIGNED NOT NULL,
    method          ENUM('cash', 'card', 'manual') NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    receipt_printed TINYINT(1) NOT NULL DEFAULT 0,
    kkm_response    TEXT NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
