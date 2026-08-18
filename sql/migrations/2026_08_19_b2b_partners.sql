-- B2B-партнёрства с магазинами электроники (аутсорс сложного ремонта —
-- BGA-пайка, восстановление данных). Трекер контактов, не путать с
-- клиентами сервиса — это другие юрлица, другая цель контакта.
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_19_b2b_partners.sql

CREATE TABLE IF NOT EXISTS b2b_partners (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    address         VARCHAR(300) NULL,
    contact_person  VARCHAR(150) NULL,
    phone           VARCHAR(32) NULL,
    channel         ENUM('telegram_whatsapp', 'call', 'in_person') NULL,
    status          ENUM('not_contacted', 'contacted', 'interested', 'partner', 'declined') NOT NULL DEFAULT 'not_contacted',
    notes           TEXT NULL,
    last_contact_at DATE NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_b2b_partners_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
