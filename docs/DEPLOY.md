# Установка на сервер (SSH)

## 0. Требования

- PHP 7.4+ (лучше 8.x) с расширением `pdo_mysql`.
- MySQL 5.7+ или 8.0 (или MariaDB).
- Apache с `mod_rewrite`/`.htaccess` **или** Nginx (см. пример конфига ниже).
- SSH-доступ на сервер.

## 1. Первая установка

```bash
ssh ваш_логин@ваш_сервер
cd /путь/к/проектам   # например /var/www
git clone https://github.com/vahmedov-code/cms_avior.git cms-avior
cd cms-avior
```

### 1.1 База данных

```bash
mysql -u root -p
```
```sql
CREATE DATABASE avior_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'avior_user'@'localhost' IDENTIFIED BY 'ПРИДУМАЙТЕ_СЛОЖНЫЙ_ПАРОЛЬ';
GRANT ALL PRIVILEGES ON avior_cms.* TO 'avior_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Импортируйте схему:

```bash
mysql -u avior_user -p avior_cms < sql/schema.sql
```

### 1.2 Конфигурация

```bash
cp config/config.example.php config/config.php
nano config/config.php   # впишите данные подключения к БД и site_url
```

`config/config.php` уже в `.gitignore` — он никогда не попадёт в репозиторий,
секреты останутся только на сервере.

### 1.3 Document root

**Важно:** веб-сервер должен смотреть на папку `public/`, а не на корень
проекта — иначе файлы из `src/`, `config/`, `sql/` могут оказаться доступны
по прямой ссылке.

#### Вариант А — Apache (виртуальный хост)

```apache
<VirtualHost *:80>
    ServerName cms.ваш-домен.ру
    DocumentRoot /var/www/cms-avior/public

    <Directory /var/www/cms-avior/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Перезапустите Apache: `sudo systemctl reload apache2`.

#### Вариант Б — Nginx

```nginx
server {
    listen 80;
    server_name cms.ваш-домен.ру;
    root /var/www/cms-avior/public;
    index index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # поправьте версию PHP
    }

    # На всякий случай — запрет доступа к служебным папкам,
    # если кто-то зайдёт на уровень выше public/
    location ~ ^/(src|config|sql)/ {
        deny all;
    }
}
```

(`.htaccess`-файлы в `config/`, `src/`, `sql/` работают только под Apache;
если у вас Nginx — используйте блок `location` выше, он уже добавлен в
пример конфига.)

#### Вариант В — если своего vhost/nginx-конфига нет (обычный шаред-хостинг)

Если хостинг даёт только одну папку `public_html` без возможности сменить
document root — переместите **содержимое** папки `public/` в `public_html`,
а `src/`, `config/`, `sql/`, `docs/`, `.git` оставьте на уровень выше, вне
`public_html`. Тогда пути `require __DIR__ . '/../src/...'` в PHP-файлах
продолжат работать (структура на уровень выше сохраняется), а из браузера
будет доступен только контент `public/`.

### 1.4 Первый администратор

Откройте в браузере: `https://cms.ваш-домен.ру/setup.php`

Введите логин, имя и пароль — учётка администратора создастся, а сама
страница `setup.php` заблокируется (создаст файл `config/setup.lock`) и
больше не сработает. После этого можно (не обязательно) удалить
`public/setup.php` с сервера.

Войти: `https://cms.ваш-домен.ру/login.php`

## 2. Обновление после каждого пуша в git

```bash
ssh ваш_логин@ваш_сервер
cd /var/www/cms-avior
git pull
```

`config/config.php` и `config/setup.lock` не в git — при `git pull` они не
затронутся, обновятся только код и стили. Если появятся изменения схемы
БД — они будут отдельным файлом в `sql/` с инструкцией в описании коммита.

## 3. Интеграция статуса ремонта с сайтом

Публичный API: `GET /api/status.php?order_no=26-001&phone=9991234567`
(нужно и то, и другое — так нельзя перебором узнать чужой заказ).

Готовый пример виджета — `docs/status-widget-example.html`. Скопируйте
код на страницу основного сайта и поправьте `API_URL` на реальный адрес
после деплоя.

## 4. SMS клиентам

Провайдер пока не выбран. Когда определитесь (например, SMS.ru или
SMSC.ru): впишите `provider` и API-ключ в `config/config.php`, затем
раскомментируйте соответствующий блок в `src/sms.php` — остальной код
менять не нужно, функция `send_sms()` уже используется в CMS.
