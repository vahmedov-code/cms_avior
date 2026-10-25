-- Логин и пароль локальной панели вывески AviorSign (прошивка 0.4.4+), задаются из CRM:
-- в базе и на вывеске — только соль и PBKDF2-SHA256 пароля. Здесь же пароль точки
-- доступа вывески, который она присылает (виден только владельцу и администраторам).
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_10_27_door_sign_panel.sql
-- Повторный запуск выдаст «Duplicate column name» — это значит, что миграция уже применена.

ALTER TABLE door_signs
    ADD COLUMN panel_seq INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN panel_user VARCHAR(32) NULL,
    ADD COLUMN panel_salt CHAR(32) NULL,
    ADD COLUMN panel_hash CHAR(64) NULL,
    ADD COLUMN ap_key VARCHAR(32) NULL;
