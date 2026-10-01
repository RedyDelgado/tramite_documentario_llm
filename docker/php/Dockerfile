FROM php:8.4-fpm-alpine

# pcntl: Horizon; pdo_pgsql: PostgreSQL; redis: colas, caché y sesión.
RUN apk add --no-cache icu-libs libzip libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev postgresql-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip bcmath pcntl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/local.ini /usr/local/etc/php/conf.d/zz-local.ini

WORKDIR /var/www/html
