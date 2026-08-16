#!/bin/bash
#
# backup-avior.sh — единый еженедельный бэкап всей инфраструктуры на VPS:
# база CRM, её config.php (единственное, что НЕ хранится в git — там
# пароли), файлы сайтов (avior.moscow, shop.avior.moscow, ux.avior.moscow),
# с заливкой на Google Диск.
#
# Режим — ВСЕГДА ОДНА И ТА ЖЕ папка, перезаписывается при каждом запуске
# (не копится история версий, ни локально, ни на Google Диске). Если
# нужна история/точки восстановления за разные даты — это осознанный
# компромисс в пользу простоты, обсудить отдельно, если понадобится.
#
# Запуск — по cron, раз в неделю в 3 ночи (см. docs/BACKUP.md для полной
# установки и настройки rclone/Google Диска).

set -euo pipefail

# ========================= НАСТРОЙКИ (заполнить) =========================

BACKUP_DIR="/var/backups/avior/latest"   # одна и та же папка всегда, без дат

CMS_DB_NAME="avior_cms"
CMS_DB_USER="avior_user"
CMS_DB_PASS="ЗАПОЛНИТЬ"         # тот же пароль, что в /var/www/cms/config/config.php
CMS_PATH="/var/www/cms"

AVIOR_SITE_PATH="/var/www/avior"    # avior.moscow — статика, деплоится через curl из GitHub

# shop.avior.moscow — ЗАПОЛНИТЬ (Laravel, своя БД):
SHOP_DB_NAME=""                 # например shop_avior — оставить пустым, если ещё не выяснили
SHOP_DB_USER=""
SHOP_DB_PASS=""
SHOP_PATH="/var/www/shop"       # уточнить реальный путь на сервере

# ux.avior.moscow (репозиторий avior-ux, React SPA со сборкой — бэкапить
# нужно именно СОБРАННЫЕ файлы, папку dist/ на сервере; своей БД нет):
UXSRC_PATH=""                   # например /var/www/ux/dist — заполнить, когда узнаете путь

# Google Диск через rclone — remote нужно настроить один раз командой
# `rclone config` (см. docs/BACKUP.md), сюда — только его имя и папка:
GDRIVE_REMOTE="gdrive:avior-backups"

# ===========================================================================

mkdir -p "$BACKUP_DIR"
cd "$BACKUP_DIR"

echo "[$(date)] Бэкап начат: $BACKUP_DIR"

# --- 1. CRM: база данных ---
if [ -n "$CMS_DB_PASS" ] && [ "$CMS_DB_PASS" != "ЗАПОЛНИТЬ" ]; then
    mysqldump -u "$CMS_DB_USER" -p"$CMS_DB_PASS" "$CMS_DB_NAME" | gzip > cms_db.sql.gz
    echo "  ✓ CRM БД сохранена"
else
    echo "  ⚠ CMS_DB_PASS не заполнен — пропускаю бэкап БД CRM"
fi

# --- 2. CRM: config.php (пароли/ключи — единственное, что не в git) ---
if [ -f "$CMS_PATH/config/config.php" ]; then
    cp "$CMS_PATH/config/config.php" cms_config.php
    echo "  ✓ config.php сохранён"
fi

# --- 3. avior.moscow: файлы сайта (хоть и в git, бэкапим то, что реально отдаётся сейчас) ---
if [ -d "$AVIOR_SITE_PATH" ]; then
    tar czf avior-site.tar.gz -C "$(dirname "$AVIOR_SITE_PATH")" "$(basename "$AVIOR_SITE_PATH")"
    echo "  ✓ avior.moscow сохранён"
fi

# --- 4. shop.avior.moscow: БД + файлы ---
if [ -n "$SHOP_DB_PASS" ]; then
    mysqldump -u "$SHOP_DB_USER" -p"$SHOP_DB_PASS" "$SHOP_DB_NAME" | gzip > shop_db.sql.gz
    echo "  ✓ shop БД сохранена"
else
    echo "  ⚠ SHOP_DB_* не заполнены — пропускаю бэкап shop.avior.moscow (см. настройки в начале файла)"
fi
if [ -d "$SHOP_PATH" ]; then
    tar czf shop-site.tar.gz -C "$(dirname "$SHOP_PATH")" "$(basename "$SHOP_PATH")"
    echo "  ✓ shop файлы сохранены"
fi

# --- 5. ux.avior.moscow: собранные файлы (dist/) ---
if [ -n "$UXSRC_PATH" ] && [ -d "$UXSRC_PATH" ]; then
    tar czf ux-site.tar.gz -C "$(dirname "$UXSRC_PATH")" "$(basename "$UXSRC_PATH")"
    echo "  ✓ ux.avior.moscow сохранён"
else
    echo "  ⚠ UXSRC_PATH не заполнен — пропускаю бэкап ux.avior.moscow (см. настройки в начале файла)"
fi

echo "[$(date)] Локальный бэкап готов: $BACKUP_DIR ($(du -sh "$BACKUP_DIR" | cut -f1))"

# --- 6. Заливка на Google Диск (перезаписывает ту же папку, не копит версии) ---
if command -v rclone >/dev/null 2>&1; then
    rclone sync "$BACKUP_DIR" "$GDRIVE_REMOTE" --quiet
    echo "[$(date)] Синхронизировано с Google Диском: $GDRIVE_REMOTE"
else
    echo "  ⚠ rclone не установлен — бэкап остался только локально на сервере. См. docs/BACKUP.md."
fi
