-- Библиотека плат + диагностика по заказу.
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_09_11_board_library.sql

CREATE TABLE IF NOT EXISTS board_models (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    device_type VARCHAR(100) NULL,
    board_code  VARCHAR(255) NULL,
    notes       TEXT NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_board_model_name (name),
    KEY idx_board_models_type (device_type),
    CONSTRAINT fk_board_models_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS board_photos (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    board_model_id INT UNSIGNED NOT NULL,
    file_path      VARCHAR(255) NOT NULL,
    title          VARCHAR(255) NULL,
    side           ENUM('top', 'bottom', 'other') NOT NULL DEFAULT 'top',
    created_by     INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_board_photos_model (board_model_id),
    CONSTRAINT fk_board_photos_model FOREIGN KEY (board_model_id) REFERENCES board_models(id) ON DELETE CASCADE,
    CONSTRAINT fk_board_photos_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS board_markers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photo_id   INT UNSIGNED NOT NULL,
    x          DECIMAL(6,3) NOT NULL,
    y          DECIMAL(6,3) NOT NULL,
    designator VARCHAR(64) NULL,
    value      VARCHAR(128) NULL,
    note       VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_board_markers_photo (photo_id),
    CONSTRAINT fk_board_markers_photo FOREIGN KEY (photo_id) REFERENCES board_photos(id) ON DELETE CASCADE,
    CONSTRAINT fk_board_markers_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE repairs
    ADD COLUMN IF NOT EXISTS customer_complaint TEXT NULL,
    ADD COLUMN IF NOT EXISTS diagnosis TEXT NULL,
    ADD COLUMN IF NOT EXISTS board_model_id INT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS repair_steps (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repair_id  INT UNSIGNED NOT NULL,
    step_text  TEXT NOT NULL,
    result     VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_repair_steps_repair (repair_id),
    CONSTRAINT fk_repair_steps_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    CONSTRAINT fk_repair_steps_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS repair_photos (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repair_id      INT UNSIGNED NOT NULL,
    file_path      VARCHAR(255) NOT NULL,
    title          VARCHAR(255) NULL,
    board_model_id INT UNSIGNED NULL,
    created_by     INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_repair_photos_repair (repair_id),
    CONSTRAINT fk_repair_photos_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    CONSTRAINT fk_repair_photos_model FOREIGN KEY (board_model_id) REFERENCES board_models(id) ON DELETE SET NULL,
    CONSTRAINT fk_repair_photos_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS repair_photo_markers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photo_id   INT UNSIGNED NOT NULL,
    x          DECIMAL(6,3) NOT NULL,
    y          DECIMAL(6,3) NOT NULL,
    designator VARCHAR(64) NULL,
    value      VARCHAR(128) NULL,
    note       VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_repair_photo_markers_photo (photo_id),
    CONSTRAINT fk_repair_photo_markers_photo FOREIGN KEY (photo_id) REFERENCES repair_photos(id) ON DELETE CASCADE,
    CONSTRAINT fk_repair_photo_markers_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
