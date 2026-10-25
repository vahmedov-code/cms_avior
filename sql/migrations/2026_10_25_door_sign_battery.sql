-- Аккумулятор вывески AviorSign: история заряда для графика и калибровки процента,
-- калибровка (поправка напряжения по мультиметру и своя кривая разряда) и автоматический
-- режим экономии (прошивка 0.4.2+: от USB без экономии, от аккумулятора — с экономией).
-- Применить: mysql -u avior_user -p avior_cms < sql/migrations/2026_10_25_door_sign_battery.sql
-- Повторный запуск выдаст «Duplicate column name» на ALTER — это значит, что миграция уже применена.
-- Не критична: без неё вывеска и CRM работают как раньше, только без истории и калибровки.

CREATE TABLE IF NOT EXISTS door_sign_battery (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sign_id     INT UNSIGNED NOT NULL,
    ts          INT UNSIGNED NOT NULL,              -- время получения, unix
    mv          SMALLINT UNSIGNED NOT NULL,         -- напряжение, как его показывает вывеска (с поправкой)
    raw_mv      SMALLINT UNSIGNED NOT NULL,         -- до поправки: по нему строится кривая разряда
    percent     TINYINT UNSIGNED NULL,
    charge      VARCHAR(12) NULL,                   -- charging | full | discharging | unknown
    power_save  TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_door_sign_battery (sign_id, ts),
    CONSTRAINT fk_door_sign_battery_sign FOREIGN KEY (sign_id) REFERENCES door_signs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE door_signs
    ADD COLUMN battery_scale SMALLINT UNSIGNED NOT NULL DEFAULT 1000,  -- поправка напряжения, тысячные
    ADD COLUMN battery_curve VARCHAR(120) NULL,                         -- JSON: 11 значений raw мВ для 0..100 %
    ADD COLUMN battery_seq INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN power_auto TINYINT(1) NOT NULL DEFAULT 1;
