-- Переработка ролей: было admin/manager (бинарно, разница только в
-- доступе к сотрудникам/настройкам), стало owner/admin/engineer:
--   owner    — полный доступ ко всему (это то, что раньше называлось admin)
--   admin    — как owner, кроме Сотрудников/Настроек (это то, что раньше
--              называлось manager — у него и так уже был такой набор прав)
--   engineer — заказы (создание/статус/комплектующие) + клиенты, БЕЗ
--              финансов/аналитики/склада/удаления заказов/сотрудников/настроек
--              (новая, более узкая роль — для инженера-приёмщика)
--
-- Порядок важен: сначала расширяем ENUM, чтобы можно было хранить и старые,
-- и новые значения одновременно, потом переносим данные, потом сужаем
-- ENUM до финального набора. Новые сотрудники по умолчанию — 'engineer'
-- (самая безопасная роль по умолчанию).
--
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_19_role_hierarchy.sql

ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'owner', 'engineer') NOT NULL DEFAULT 'engineer';

UPDATE users SET role = 'owner' WHERE role = 'admin';
UPDATE users SET role = 'admin' WHERE role = 'manager';

ALTER TABLE users MODIFY COLUMN role ENUM('owner', 'admin', 'engineer') NOT NULL DEFAULT 'engineer';
