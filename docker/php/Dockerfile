FROM php:8.5-fpm-alpine

RUN apk add --no-cache postgresql-dev $PHPIZE_DEPS

RUN docker-php-ext-install pdo pdo_pgsql pgsql 

RUN pecl install redis \
    && docker-php-ext-enable redis 

RUN apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear

COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer