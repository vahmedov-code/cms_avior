-- Обновление прошивки вывески AviorSign из CRM (прошивка 0.4.3+): firmware.bin загружается
-- на странице «Вывеска на двери», вывеска скачивает его при следующей связи, проверяет
-- SHA-256 и перезапускается; если новая версия не выйдет на связь с CRM, вывеска сама
-- вернётся к прежней. Здесь же — ключ локальной панели, который присылает вывеска
-- (показывается только владельцу и администраторам).
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_10_26_door_sign_firmware.sql
-- Повторный запуск выдаст «Duplicate column name» на ALTER — это значит, что миграция уже применена.
-- Не критична: без неё всё работает как раньше, только без обновления из CRM.

CREATE TABLE IF NOT EXISTS door_sign_firmware (
    sign_id      INT UNSIGNED NOT NULL PRIMARY KEY,   -- одна загруженная прошивка на вывеску
    version      VARCHAR(40) NOT NULL,                -- из метки AVIORSIGN_VERSION= в файле
    size         INT UNSIGNED NOT NULL,
    sha256       CHAR(64) NOT NULL,
    data         MEDIUMBLOB NOT NULL,                 -- firmware.bin, около 1,2 МБ
    uploaded_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id      INT UNSIGNED NULL,
    CONSTRAINT fk_door_sign_firmware_sign FOREIGN KEY (sign_id) REFERENCES door_signs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE door_signs
    ADD COLUMN firmware_seq INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN local_key VARCHAR(64) NULL;            -- ключ локальной панели вывески
