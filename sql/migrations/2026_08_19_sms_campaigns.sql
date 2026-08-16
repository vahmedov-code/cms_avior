-- Массовые SMS-рассылки: таблица кампаний (для истории — что и когда
-- рассылали, скольким клиентам) + связь с уже существующим sms_log
-- (там остаётся детальная запись по каждому получателю, как и раньше).
--
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_19_sms_campaigns.sql

CREATE TABLE IF NOT EXISTS sms_campaigns (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message          TEXT NOT NULL,
    recipients_count INT UNSIGNED NOT NULL DEFAULT 0,
    sent_count       INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count     INT UNSIGNED NOT NULL DEFAULT 0,
    created_by       INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sms_campaigns_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE sms_log
    ADD COLUMN IF NOT EXISTS campaign_id INT UNSIGNED NULL AFTER repair_id;

-- Отдельным шагом (без IF NOT EXISTS — не для всех версий MariaDB
-- гарантированно поддерживается синтаксис для ADD CONSTRAINT; если
-- вдруг придётся перезапускать эту миграцию и constraint уже есть —
-- просто закомментируйте следующий блок):
ALTER TABLE sms_log
    ADD CONSTRAINT fk_sms_log_campaign FOREIGN KEY (campaign_id) REFERENCES sms_campaigns(id) ON DELETE SET NULL;
