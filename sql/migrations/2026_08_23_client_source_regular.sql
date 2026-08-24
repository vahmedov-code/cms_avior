-- Новый источник клиента — «Постоянный клиент» (для отметки уже
-- знакомых клиентов, а не первого визита через рекламный канал).
--
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_23_client_source_regular.sql

ALTER TABLE clients
    MODIFY COLUMN source ENUM('avito', 'yandex', '2gis', 'google_maps', 'referral', 'walkin', 'site', 'regular') NULL;
