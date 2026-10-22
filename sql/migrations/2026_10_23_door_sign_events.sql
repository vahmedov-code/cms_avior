-- Журнал самой вывески AviorSign (прошивка 0.4.0+): перезапуски и их причины, пропадания
-- Wi-Fi, ошибки связи с CRM, обновления экрана и прошивки. Вывеска присылает события с
-- номерами, CRM пишет их в door_sign_log и запоминает номер последнего сохранённого.
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_10_23_door_sign_events.sql
-- Повторный запуск выдаст «Duplicate column name» — это значит, что миграция уже применена.
-- Не критична: без неё CRM работает как раньше, только события вывески не сохраняются.

ALTER TABLE door_signs ADD COLUMN events_seq INT UNSIGNED NOT NULL DEFAULT 0;
