-- Добавляет поля, нужные для печатной «Квитанции о приёмке» устройства в ремонт.
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_15_receipt_fields.sql

ALTER TABLE repairs
    ADD COLUMN device_serial    VARCHAR(100)  NULL AFTER device_model,
    ADD COLUMN device_complete  VARCHAR(255)  NULL AFTER device_serial,
    ADD COLUMN device_condition VARCHAR(255)  NULL AFTER device_complete,
    ADD COLUMN prepayment       DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price_final,
    ADD COLUMN deadline_date    DATE          NULL AFTER prepayment,
    ADD COLUMN receipt_note     VARCHAR(500)  NULL AFTER deadline_date,
    ADD COLUMN manager_name     VARCHAR(150)  NULL AFTER receipt_note,
    ADD COLUMN receipt_ready    TINYINT(1)    NOT NULL DEFAULT 0 AFTER manager_name;
