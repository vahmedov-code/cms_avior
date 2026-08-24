-- Вход по отпечатку пальца / Face ID (WebAuthn). Один пользователь может
-- привязать несколько устройств (телефон, ноутбук с Windows Hello и т.п.),
-- каждое — отдельная запись, можно отвязать по одной.
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_24_webauthn_credentials.sql

CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    credential_id  VARCHAR(255) NOT NULL,
    public_key     TEXT NOT NULL,
    sign_count     INT UNSIGNED NOT NULL DEFAULT 0,
    device_label   VARCHAR(100) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at   DATETIME NULL,
    CONSTRAINT fk_webauthn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_webauthn_credential_id (credential_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
