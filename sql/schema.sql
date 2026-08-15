-- АВИОР CMS — схема базы данных (MySQL 5.7+/8.0, InnoDB, utf8mb4)
-- Импорт: mysql -u USER -p DBNAME < schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Пользователи CMS (сотрудники сервиса)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(150) NOT NULL,
    role          ENUM('admin', 'manager') NOT NULL DEFAULT 'manager',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Клиенты
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(150) NOT NULL,
    phone       VARCHAR(32)  NOT NULL,
    email       VARCHAR(150) NULL,
    address     VARCHAR(255) NULL,
    notes       TEXT NULL,
    source      ENUM('avito', 'yandex', '2gis', 'google_maps', 'referral', 'walkin') NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_clients_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Заказы на ремонт / сборку
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS repairs (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no            VARCHAR(20) NOT NULL UNIQUE,
    order_type          ENUM('repair', 'pc_build', 'account_memo') NOT NULL DEFAULT 'repair',
    client_id           INT UNSIGNED NOT NULL,
    device_type         VARCHAR(100) NOT NULL,      -- ноутбук / смартфон / планшет / ПК и т.д.
    device_model        VARCHAR(150) NULL,
    device_serial       VARCHAR(100) NULL,          -- серийный номер (для квитанции о приёмке)
    device_complete     VARCHAR(255) NULL,           -- комплектация (зарядка, чехол и т.п.)
    device_condition    VARCHAR(255) NULL,           -- внешний вид на момент приёма
    problem_description TEXT NULL,
    status              ENUM(
                            'принят',
                            'диагностика',
                            'согласование',
                            'в ремонте',
                            'готов',
                            'выдан',
                            'отказ'
                        ) NOT NULL DEFAULT 'принят',
    price_estimate      DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_final         DECIMAL(10,2) NULL,
    prepayment          DECIMAL(10,2) NOT NULL DEFAULT 0,  -- предоплата при приёме
    deadline_date        DATE NULL,                  -- ориентировочная дата готовности
    receipt_note         VARCHAR(500) NULL,           -- примечание в квитанции о приёмке
    manager_name          VARCHAR(150) NULL,          -- ФИО мастера/менеджера, оформившего приём
    receipt_ready         TINYINT(1) NOT NULL DEFAULT 0, -- квитанция о приёмке заполнена
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_repairs_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    INDEX idx_repairs_status (status),
    INDEX idx_repairs_order_no (order_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Комплектующие / работы, использованные в заказе
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS repair_parts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repair_id  INT UNSIGNED NOT NULL,
    category   ENUM('part', 'service') NOT NULL DEFAULT 'part',
    name       VARCHAR(255) NOT NULL,
    qty        DECIMAL(10,2) NOT NULL DEFAULT 1,
    price      DECIMAL(10,2) NOT NULL DEFAULT 0,
    cost       DECIMAL(10,2) NOT NULL DEFAULT 0,   -- закупочная цена (для расчёта прибыли), необязательно
    CONSTRAINT fk_repair_parts_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Расходы бизнеса (аренда, зарплаты, закупки не по конкретному заказу)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category     VARCHAR(100) NOT NULL,
    description  VARCHAR(255) NULL,
    amount       DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_expenses_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Каталог комплектующих (общая база названий/цен для подсказок)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS parts_catalog (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL UNIQUE,
    price      DECIMAL(10,2) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- История изменений статуса заказа
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS repair_status_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repair_id  INT UNSIGNED NOT NULL,
    status     VARCHAR(50) NOT NULL,
    comment    VARCHAR(255) NULL,
    changed_by INT UNSIGNED NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_status_log_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_log_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Журнал SMS клиентам (провайдер подключается позже в config.php)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sms_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repair_id  INT UNSIGNED NULL,
    phone      VARCHAR(32) NOT NULL,
    message    TEXT NOT NULL,
    status     ENUM('sent', 'failed', 'not_configured') NOT NULL DEFAULT 'not_configured',
    sent_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sms_log_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Примечание: администратора CMS создавать НЕ здесь.
-- После импорта схемы откройте /public/setup.php в браузере один раз —
-- он создаст первого администратора и сам заблокирует себя.
