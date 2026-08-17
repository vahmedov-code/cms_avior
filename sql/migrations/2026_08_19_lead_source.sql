-- Новый источник клиента — "Заявка с сайта" (avior.moscow, lead.php),
-- для автоматического приёма заявок с сайта напрямую в CRM (см. новый
-- эндпоинт public/api/lead_intake.php).
--
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_19_lead_source.sql

ALTER TABLE clients
    MODIFY COLUMN source ENUM('avito', 'yandex', '2gis', 'google_maps', 'referral', 'walkin', 'site') NULL;
