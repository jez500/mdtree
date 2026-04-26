#!/usr/bin/env bash

set -euo pipefail

cd /var/www/html

cd storage/
mkdir -p framework/{sessions,views,cache}
chmod -R 775 framework
cd /var/www/html

mkdir -p bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
touch storage/database.sqlite
chmod 755 storage/database.sqlite

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force

exec apache2-foreground
