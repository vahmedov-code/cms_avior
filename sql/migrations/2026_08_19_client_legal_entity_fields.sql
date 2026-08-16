-- Разделение клиентов на физлиц/юрлиц + поля реквизитов компании.
-- Обратная совместимость: DEFAULT 'individual' — все существующие клиенты
-- остаются физлицами автоматически, ничего не ломается.
--
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_08_19_client_legal_entity_fields.sql

ALTER TABLE clients
    ADD COLUMN IF NOT EXISTS client_type ENUM('individual', 'legal_entity') NOT NULL DEFAULT 'individual' AFTER full_name,
    ADD COLUMN IF NOT EXISTS contact_person VARCHAR(150) NULL AFTER client_type,
    ADD COLUMN IF NOT EXISTS inn VARCHAR(12) NULL AFTER contact_person,
    ADD COLUMN IF NOT EXISTS kpp VARCHAR(9) NULL AFTER inn,
    ADD COLUMN IF NOT EXISTS ogrn VARCHAR(15) NULL AFTER kpp,
    ADD COLUMN IF NOT EXISTS legal_address VARCHAR(255) NULL AFTER ogrn,
    ADD COLUMN IF NOT EXISTS bank_name VARCHAR(150) NULL AFTER legal_address,
    ADD COLUMN IF NOT EXISTS bank_account VARCHAR(20) NULL AFTER bank_name,
    ADD COLUMN IF NOT EXISTS bank_bik VARCHAR(9) NULL AFTER bank_account,
    ADD COLUMN IF NOT EXISTS bank_corr_account VARCHAR(20) NULL AFTER bank_bik;

-- Перенос реквизитов трёх компаний, уже импортированных из ЛайвСклад
-- 19.08.2026 (миграция 2026_08_19_import_livesklad_clients.sql) — тогда
-- отдельных полей ещё не было, реквизиты лежали текстом в notes.
-- Четвёртая компания из того файла (ООО АКИД-ПРОЕКТ) в базу не попала —
-- у неё не было телефона, соответствующая строка была пропущена целиком.
-- WHERE по phone — безопасно перезапускать (UPDATE идемпотентен).

UPDATE clients SET
    client_type = 'legal_entity',
    inn = '7703402228',
    bank_account = '40702810838000082763',
    bank_corr_account = '30101810400000000225',
    bank_bik = '044525225'
WHERE phone = '+7 (926) 613-13-62';

UPDATE clients SET
    client_type = 'legal_entity',
    inn = '7725382984',
    bank_account = '40702810801500088173',
    bank_corr_account = '30101810745374525104',
    bank_bik = '044525104',
    bank_name = 'ООО «Банк Точка»'
WHERE phone = '+7 (985) 387-75-50';

UPDATE clients SET
    client_type = 'legal_entity',
    inn = '9718250074',
    bank_account = '40702810520000086121',
    bank_corr_account = '30101810745374525104',
    bank_bik = '044525104',
    bank_name = 'ООО «Банк Точка»'
WHERE phone = '+7 (916) 616-43-47';
