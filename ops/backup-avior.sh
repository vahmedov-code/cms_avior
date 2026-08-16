#!/bin/bash
#
# backup-avior.sh — единый бэкап всей инфраструктуры на VPS: база CRM,
# её config.php (единственное, что НЕ хранится в git — там пароли),
# и файлы сайтов (avior.moscow, shop.avior.moscow, ux.avior.moscow).
#
# Запускать по cron (пример ниже, см. docs/BACKUP.md для полной установки).
# Бэкапы складываются локально на VPS с ротацией (хранятся $KEEP_DAYS дней)
# — этого недостаточно самого по себе (если сервер целиком умрёт, умрут
# и бэкапы на нём же), поэтому в конце файла есть необязательный блок
# синхронизации в облако (rclone) — раскомментировать и настроить, когда
# определитесь с провайдером (Яндекс Object Storage — самый простой
# S3-совместимый вариант для РФ).

set -euo pipefail

# ========================= НАСТРОЙКИ (заполнить) =========================

BACKUP_ROOT="/var/backups/avior"
KEEP_DAYS=14                    # сколько дней хранить локальные бэкапы

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

# ux.avior.moscow — ЗАПОЛНИТЬ путь (домен известен, путь/БД на сервере пока нет):
# UXSRC_PATH=""            # например /var/www/ux
# UXSRC_DB_NAME=""         # если сайт использует БД

# ===========================================================================

DATE=$(date +%Y%m%d-%H%M)
DEST="$BACKUP_ROOT/$DATE"
mkdir -p "$DEST"
cd "$DEST"

echo "[$(date)] Бэкап начат: $DEST"

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

# --- 5. ux.avior.moscow — раскомментировать и заполнить пути выше, когда понадобится ---
# if [ -d "$UXSRC_PATH" ]; then
#     tar czf uxsrc-site.tar.gz -C "$(dirname "$UXSRC_PATH")" "$(basename "$UXSRC_PATH")"
# fi

# --- ротация: удаляем локальные бэкапы старше $KEEP_DAYS дней ---
find "$BACKUP_ROOT" -maxdepth 1 -mindepth 1 -type d -mtime +"$KEEP_DAYS" -exec rm -rf {} \;

echo "[$(date)] Бэкап завершён: $DEST ($(du -sh "$DEST" | cut -f1))"

# ==================== НЕОБЯЗАТЕЛЬНО: синхронизация в облако ====================
# Локальный бэкап на том же VPS не спасёт, если сервер целиком выйдет из
# строя. Когда определитесь с облачным хранилищем — раскомментируйте:
#
# 1) Установить rclone: curl https://rclone.org/install.sh | sudo bash
# 2) Настроить удалённый диск: rclone config (для Яндекс Object Storage —
#    тип "s3", provider "Other", endpoint storage.yandexcloud.net)
# 3) Раскомментировать:
#
# rclone sync "$DEST" "yandex-remote:avior-backups/$DATE" --quiet
#
# =================================================================================
