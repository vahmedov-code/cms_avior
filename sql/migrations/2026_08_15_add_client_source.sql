-- Миграция: добавляет поле "источник" клиента.
-- Применить на уже развёрнутой базе:
--   mysql -u avior_user -p avior_cms < sql/migrations/2026_08_15_add_client_source.sql

ALTER TABLE clients
    ADD COLUMN source ENUM('avito', 'yandex', '2gis', 'google_maps', 'referral', 'walkin') NULL
    AFTER notes;
