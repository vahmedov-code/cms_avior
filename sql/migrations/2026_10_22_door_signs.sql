-- Вывеска на двери AviorSign (электронная бумага на ESP32) — «Инструменты → Вывеска на двери».
-- Вывеска стоит в офисной сети, CRM её не видит, поэтому вывеска сама раз в несколько
-- секунд присылает своё состояние в api/sign/sync.php и забирает изменения отсюда.
-- Каждое изменение из CRM получает номер (seq); вывеска применяет только то, что новее
-- уже применённого, поэтому её собственные (локальные) изменения CRM не затирает.
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_10_22_door_signs.sql
-- Не критична: без неё вся CRM работает как раньше, страница вывески подскажет, что делать.

-- Русские значения по умолчанию: без этого клиент mysql с latin1 по умолчанию запишет их кракозябрами.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS door_signs (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL DEFAULT 'Вывеска на двери',
    token_hash     CHAR(64) NOT NULL,                 -- SHA-256 токена вывески; сам токен показывается один раз
    seq            INT UNSIGNED NOT NULL DEFAULT 0,   -- счётчик изменений из CRM
    status         VARCHAR(10) NULL,                  -- open|closed|soon|image, заданный из CRM
    status_seq     INT UNSIGNED NOT NULL DEFAULT 0,
    schedule_json  TEXT NULL,                         -- {"enabled":..,"utcOffsetMinutes":..,"events":[..]}
    schedule_seq   INT UNSIGNED NOT NULL DEFAULT 0,
    qr_enabled     TINYINT(1) NOT NULL DEFAULT 1,
    qr_url         VARCHAR(160) NOT NULL DEFAULT 'https://avior.moscow/na-svyazi/',
    qr_seq         INT UNSIGNED NOT NULL DEFAULT 0,
    reported_json  TEXT NULL,                         -- последнее состояние, присланное вывеской
    last_seen_at   DATETIME NULL,
    last_ip        VARCHAR(45) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_door_signs_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS door_sign_assets (
    sign_id     INT UNSIGNED NOT NULL,
    slot        VARCHAR(10) NOT NULL,                 -- open|closed|soon|image
    frame       MEDIUMBLOB NULL,                      -- 96000 байт (800×480, 4 цвета); NULL = вернуть стандартную
    sha256      CHAR(64) NULL,
    seq         INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (sign_id, slot),
    CONSTRAINT fk_door_sign_assets_sign FOREIGN KEY (sign_id) REFERENCES door_signs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS door_sign_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sign_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NULL,                    -- NULL — событие от самой вывески
    action      VARCHAR(40) NOT NULL,
    detail      VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_door_sign_log_sign (sign_id, created_at),
    CONSTRAINT fk_door_sign_log_sign FOREIGN KEY (sign_id) REFERENCES door_signs(id) ON DELETE CASCADE,
    CONSTRAINT fk_door_sign_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
