#!/usr/bin/env bash

set -euo pipefail

cd /var/www/html

mkdir -p storage/framework/{sessions,views,cache}
mkdir -p bootstrap/cache
touch storage/database.sqlite

if [ "$(id -u)" = "0" ]; then
    chmod -R 775 storage/framework
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R ug+rwX storage bootstrap/cache
    chmod 755 storage/database.sqlite
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force

if [ "$(id -u)" = "0" ]; then
    exec apache2-foreground
else
    exec php artisan serve --host=0.0.0.0 --port=80
fi
