-- Скидка на позицию заказа (комплектующую/услугу) — процент, 0-100,
-- NULL = без скидки. Применяется именно к позиции, не ко всему заказу.
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_19_part_discount.sql

ALTER TABLE repair_parts
    ADD COLUMN IF NOT EXISTS discount DECIMAL(5,2) NULL AFTER warranty;
