-- Таблица настроек, редактируемых через интерфейс (settings.php, только
-- администратор): реквизиты компании, site_url, SMS-провайдер/ключ.
-- Не критично для работы CMS — get_setting() в functions.php тихо
-- откатывается на значения по умолчанию / config.php, если этой таблицы
-- ещё нет (в отличие от device_model_catalog, здесь нет жёсткой
-- зависимости). Но применить всё же стоит, чтобы настройки заработали.
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_17_settings.sql

CREATE TABLE IF NOT EXISTS settings (
    `key`      VARCHAR(100) NOT NULL PRIMARY KEY,
    value      TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
