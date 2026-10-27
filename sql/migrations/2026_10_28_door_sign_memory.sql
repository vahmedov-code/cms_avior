-- Команда «Перераспределить память» для вывески AviorSign (прошивка 0.4.8+): разделы
-- прошивки 1,25 → 1,625 МБ без USB. Вывеска выполняет её при следующем запуске.
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_10_28_door_sign_memory.sql
-- Повторный запуск выдаст «Duplicate column name» — это значит, что миграция уже применена.

ALTER TABLE door_signs ADD COLUMN memory_seq INT UNSIGNED NOT NULL DEFAULT 0;
