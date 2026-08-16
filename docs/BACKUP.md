# Автоматический бэкап всей инфраструктуры (VPS)

Единый скрипт `ops/backup-avior.sh` бэкапит:
- базу CRM (`avior_cms`) — дамп через `mysqldump`, сжатый
- `config/config.php` CRM — единственный файл с паролями, который не в git
- файлы `avior.moscow` (хоть сайт и в git, бэкапим то, что реально отдаётся сейчас)
- базу и файлы `shop.avior.moscow` (после заполнения настроек — см. ниже)
- собранные файлы `ux.avior.moscow` (папка `dist/`, после заполнения пути)

**Режим — одна и та же папка, перезаписывается при каждом запуске.** Ни
локально, ни на Google Диске история версий не копится — только самый
свежий снимок. Если сервер сломается прямо во время бэкапа или сразу
после — предыдущая версия будет уже перезаписана. Это осознанный выбор
ради простоты (по вашей просьбе); если захотите точки восстановления за
разные даты — можно вернуть, скажите отдельно.

## Установка

```bash
cd /var/www/cms
git pull   # подтягивает ops/backup-avior.sh, если ещё не на сервере

sudo mkdir -p /var/backups/avior
sudo cp ops/backup-avior.sh /root/backup-avior.sh
sudo chmod +x /root/backup-avior.sh
```

Откройте `/root/backup-avior.sh` и заполните в начале файла:
- `CMS_DB_PASS` — тот же пароль, что в `/var/www/cms/config/config.php`
- `SHOP_DB_NAME` / `SHOP_DB_USER` / `SHOP_DB_PASS` / `SHOP_PATH` — реквизиты
  `shop.avior.moscow` (уточнить, если не помните — можно посмотреть в
  `.env` Laravel-проекта на сервере)
- `UXSRC_PATH` — путь к папке `dist/` собранного `ux.avior.moscow` на сервере

## Настройка Google Диска (через rclone)

```bash
curl https://rclone.org/install.sh | sudo bash
rclone config
```
В интерактивном мастере:
1. `n` — новый remote
2. Имя: `gdrive` (важно — именно так, скрипт уже настроен на это имя)
3. Тип хранилища — найти `drive` (Google Drive) по номеру в списке
4. `client_id`/`client_secret` — можно оставить пустыми (использовать
   встроенные rclone)
5. Scope — `1` (полный доступ)
6. На вопрос про автоматическое открытие браузера — если вы на сервере
   без графического интерфейса (обычно так и есть), выбрать **No** —
   rclone даст ссылку, которую нужно открыть на телефоне/компьютере,
   авторизоваться в Google-аккаунте и вставить обратно код в терминал
7. Team drive — `n` (обычный личный Диск)

Проверить, что подключилось:
```bash
rclone lsd gdrive:
```

⚠️ **На заметку**: доступность сервисов Google из России периодически
нестабильна (не полная блокировка, но были периоды деградации доступа
в 2026 году, тема регулярно поднимается на уровне Госдумы). Стоит
протестировать синхронизацию сейчас же и время от времени проверять,
что бэкапы реально доходят (см. `/var/log/avior-backup.log`) — а не
только полагаться, что cron тихо работает. Если Google станет
недоступен из РФ — тот же скрипт легко переключить на Яндекс Object
Storage (`rclone config` поддерживает и его, тип `s3`), поменять
понадобится только одну строку `GDRIVE_REMOTE` в скрипте.

## Проверка вручную

```bash
sudo /root/backup-avior.sh
ls -la /var/backups/avior/latest/
rclone ls gdrive:avior-backups
```
Должны появиться `cms_db.sql.gz`, `cms_config.php`, `avior-site.tar.gz`
(и `shop_*`/`ux-site.tar.gz`, если заполнили пути) — и локально, и на Диске.

## Автозапуск по cron — раз в неделю, ночью в 3:00

```bash
sudo crontab -e
```
Добавить строку (по умолчанию — ночь с воскресенья на понедельник; `0`
в четвёртом поле = воскресенье, поменять на нужный день недели 0-6 при
желании):
```
0 3 * * 0 /root/backup-avior.sh >> /var/log/avior-backup.log 2>&1
```

Проверить, что записалось:
```bash
sudo crontab -l
```

## Если нужно восстановить бэкап

Локально (если ещё на сервере):
```bash
gunzip -c /var/backups/avior/latest/cms_db.sql.gz | mysql -u avior_user -p avior_cms
cp /var/backups/avior/latest/cms_config.php /var/www/cms/config/config.php
tar xzf /var/backups/avior/latest/avior-site.tar.gz -C /var/www/
```

С Google Диска (если локальной копии уже нет):
```bash
rclone copy gdrive:avior-backups /var/backups/avior/latest
# дальше — те же команды восстановления, что выше
```
