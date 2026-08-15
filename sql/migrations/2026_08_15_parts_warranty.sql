-- Гарантия по позиции заказа (комплектующее/услуга) — для акта выполненных
-- работ (колонка «Гар-тия») и для учёта в самом заказе.
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_15_parts_warranty.sql

ALTER TABLE repair_parts
    ADD COLUMN warranty VARCHAR(50) NULL AFTER cost;
