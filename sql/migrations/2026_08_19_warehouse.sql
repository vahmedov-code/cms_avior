-- Склад: остатки комплектующих + журнал движений (приход/расход).
-- Не трогает существующие данные parts_catalog — просто добавляет
-- колонку остатка (по умолчанию 0, т.е. ничего задним числом не появится
-- на складе само по себе — приход нужно будет завести руками через
-- warehouse.php).
--
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_19_warehouse.sql

ALTER TABLE parts_catalog
    ADD COLUMN IF NOT EXISTS stock_qty DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price;

CREATE TABLE IF NOT EXISTS stock_movements (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    part_id    INT UNSIGNED NOT NULL,
    type       ENUM('in', 'out') NOT NULL,
    qty        DECIMAL(10,2) NOT NULL,
    reason     VARCHAR(255) NULL,
    repair_id  INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_movements_part FOREIGN KEY (part_id) REFERENCES parts_catalog(id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_movements_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE SET NULL,
    CONSTRAINT fk_stock_movements_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
