FROM composer:2 AS build

WORKDIR /app

ENV WAYFINDER_COMMAND=true

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

RUN apk add --no-cache nodejs npm
RUN apk add --no-cache sqlite-dev $PHPIZE_DEPS && docker-php-ext-install pdo_sqlite

COPY package.json package-lock.json ./
RUN npm ci \
    npm run build

COPY . .
RUN rm -f bootstrap/cache/*.php
RUN APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= npm run build


FROM php:8.3-apache AS app

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev libzip-dev unzip \
    && docker-php-ext-install pdo_sqlite bcmath \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY . .
COPY --from=build /app/vendor ./vendor
COPY --from=build /app/public/build ./public/build

RUN rm -f bootstrap/cache/*.php
RUN chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R ug+rwX,o+w storage bootstrap/cache
RUN chmod +x docker/start.sh

EXPOSE 80

CMD ["/var/www/html/docker/start.sh"]
