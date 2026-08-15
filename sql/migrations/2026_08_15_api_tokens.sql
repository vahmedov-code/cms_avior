-- Токены для мобильного приложения (Android). Один пользователь может
-- иметь несколько токенов (несколько устройств) — можно отзывать по одному.
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_15_api_tokens.sql

CREATE TABLE IF NOT EXISTS api_tokens (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    token         CHAR(64) NOT NULL UNIQUE,
    device_label  VARCHAR(150) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at  DATETIME NULL,
    CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
