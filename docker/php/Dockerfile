FROM php:8.5-fpm-alpine

RUN apk add --no-cache postgresql-dev $PHPIZE_DEPS

RUN docker-php-ext-install pdo pdo_pgsql pgsql 

RUN pecl install redis \
    && docker-php-ext-enable redis 

RUN apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear

COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN mkdir -p /var/tmp/php-uploads \
    && chown www-data:www-data /var/tmp/php-uploads \
    && chmod 700 /var/tmp/php-uploads

WORKDIR /var/www/html

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

USER www-data