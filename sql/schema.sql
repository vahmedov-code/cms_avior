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
    role          ENUM('owner', 'admin', 'engineer') NOT NULL DEFAULT 'engineer',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Клиенты
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name          VARCHAR(150) NOT NULL,
    client_type        ENUM('individual', 'legal_entity') NOT NULL DEFAULT 'individual',
    contact_person     VARCHAR(150) NULL,
    phone              VARCHAR(32)  NOT NULL,
    email              VARCHAR(150) NULL,
    address            VARCHAR(255) NULL,
    inn                VARCHAR(12) NULL,
    kpp                VARCHAR(9) NULL,
    ogrn               VARCHAR(15) NULL,
    legal_address      VARCHAR(255) NULL,
    bank_name          VARCHAR(150) NULL,
    bank_account       VARCHAR(20) NULL,
    bank_bik           VARCHAR(9) NULL,
    bank_corr_account  VARCHAR(20) NULL,
    notes              TEXT NULL,
    source             ENUM('avito', 'yandex', '2gis', 'google_maps', 'referral', 'walkin') NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
    public_token          VARCHAR(64) NULL UNIQUE,     -- токен для публичных ссылок на квитанцию/акт (без входа в CMS)
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
    warranty   VARCHAR(50) NULL,                    -- гарантия по позиции («нет», «30 дней» и т.п.) — для акта
    CONSTRAINT fk_repair_parts_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Оплата заказов — касса АТОЛ через KkmServer (нал/безнал с печатью
-- фискального чека) либо ручная отметка «оплачено, чек пробью отдельно».
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repair_id       INT UNSIGNED NOT NULL,
    method          ENUM('cash', 'card', 'manual') NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    receipt_printed TINYINT(1) NOT NULL DEFAULT 0,
    kkm_response    TEXT NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
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
    stock_qty  DECIMAL(10,2) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Склад: журнал движений (приход/расход) по комплектующим. Расход
-- создаётся автоматически при добавлении позиции в заказ (repair_view.php,
-- category='part') — repair_id указывает, для какого заказа списано.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_movements (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    part_id    INT UNSIGNED NOT NULL,
    type       ENUM('in', 'out') NOT NULL,
    qty        DECIMAL(10,2) NOT NULL,
    reason     VARCHAR(255) NULL,
    repair_id  INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_movements_part FOREIGN KEY (part_id) REFERENCES parts_catalog(id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_movements_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE SET NULL,
    CONSTRAINT fk_stock_movements_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Справочник известных моделей устройств (для подсказок автозаполнения,
-- заполняется миграцией sql/migrations/2026_08_17_device_model_catalog.sql
-- на ~200 распространённых моделей смартфонов/ноутбуков; на чистой
-- установке таблица создаётся пустой — сиды применяются отдельно)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS device_model_catalog (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Настройки, редактируемые через интерфейс (settings.php, только admin):
-- реквизиты компании, site_url, SMS-провайдер/ключ. Задел под white-label.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    `key`      VARCHAR(100) NOT NULL PRIMARY KEY,
    value      TEXT NULL,
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
-- Массовые SMS-рассылки — история кампаний (§5 PROJECT_STATE.md).
-- Должна идти ДО sms_log (там на неё внешний ключ campaign_id).
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- Журнал SMS клиентам (провайдер подключается позже в config.php)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sms_log (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repair_id    INT UNSIGNED NULL,
    campaign_id  INT UNSIGNED NULL,
    phone        VARCHAR(32) NOT NULL,
    message      TEXT NOT NULL,
    status       ENUM('sent', 'failed', 'not_configured') NOT NULL DEFAULT 'not_configured',
    sent_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sms_log_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE SET NULL,
    CONSTRAINT fk_sms_log_campaign FOREIGN KEY (campaign_id) REFERENCES sms_campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Токены мобильного приложения (Android). Несколько токенов на юзера —
-- по одному на устройство, можно отзывать по отдельности.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS api_tokens (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    token         CHAR(64) NOT NULL UNIQUE,
    device_label  VARCHAR(150) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at  DATETIME NULL,
    CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Примечание: администратора CMS создавать НЕ здесь.
-- После импорта схемы откройте /public/setup.php в браузере один раз —
-- он создаст первого администратора и сам заблокирует себя.
