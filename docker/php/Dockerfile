FROM php:8.5-fpm-alpine

RUN apk add --no-cache postgresql-dev

RUN docker-php-ext-install pdo pdo_pgsql pgsql

COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer