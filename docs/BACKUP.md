# Автоматический бэкап всей инфраструктуры (VPS)

Единый скрипт `ops/backup-avior.sh` бэкапит:
- базу CRM (`avior_cms`) — дамп через `mysqldump`, сжатый
- `config/config.php` CRM — единственный файл с паролями, который не в git
- файлы `avior.moscow` (хоть сайт и в git, бэкапим то, что реально отдаётся сейчас)
- базу и файлы `shop.avior.moscow` (после заполнения настроек — см. ниже)

Хранится локально на VPS с ротацией (по умолчанию 14 дней). Это **не
полноценная защита от потери сервера** — см. раздел «Облако» ниже.

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

Проверить вручную один раз:
```bash
sudo /root/backup-avior.sh
ls -la /var/backups/avior/
```
Должна появиться папка с датой внутри, содержащая `cms_db.sql.gz`,
`cms_config.php`, `avior-site.tar.gz` (и `shop_*`, если заполнили).

## Автозапуск по cron

```bash
sudo crontab -e
```
Добавить строку (бэкап каждую ночь в 3:00):
```
0 3 * * * /root/backup-avior.sh >> /var/log/avior-backup.log 2>&1
```

Проверить, что записалось:
```bash
sudo crontab -l
```

## Облако (рекомендуется, но не обязательно сразу)

Локальный бэкап хранится на том же VPS — если сервер выйдет из строя
целиком (авария, взлом, ошибка провайдера), бэкапы пропадут вместе с
ним. Чтобы этого избежать, нужно синхронизировать папку `/var/backups/avior`
куда-то ещё. Самый простой вариант для РФ — **Яндекс Object Storage**
(S3-совместимое облачное хранилище) через `rclone`. Инструкция и готовый
(закомментированный) блок — в конце самого `ops/backup-avior.sh`.

Альтернатива подешевле/попроще — второй недорогой VPS/облачный диск и
обычный `rsync` вместо rclone.

## Если нужно восстановить бэкап

```bash
# распаковать БД CRM:
gunzip -c cms_db.sql.gz | mysql -u avior_user -p avior_cms

# вернуть config.php:
cp cms_config.php /var/www/cms/config/config.php

# распаковать файлы сайта avior.moscow:
tar xzf avior-site.tar.gz -C /var/www/
```
