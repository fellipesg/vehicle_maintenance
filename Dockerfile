FROM php:8.4-fpm

WORKDIR /var/www/html

# System libs + PHP extensions. pdo_pgsql matches production (Neon);
# pdo_sqlite ships with the base image and is used by the default test suite.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip zip \
        libpq-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libzip-dev libonig-dev libxml2-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run as www-data remapped to the host user's UID/GID so files written to the
# bind mount (storage/, vendor/, bootstrap/cache) are owned by you on Linux.
# (Docker Desktop on macOS maps ownership automatically.)
ARG WWWUSER=1000
ARG WWWGROUP=1000
RUN groupmod -o -g "${WWWGROUP}" www-data \
    && usermod -o -u "${WWWUSER}" -g "${WWWGROUP}" www-data \
    && mkdir -p /tmp/composer && chown -R www-data:www-data /tmp/composer /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-custom.ini
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Xdebug is installed but off unless XDEBUG_MODE is overridden (e.g. debug).
ENV XDEBUG_MODE=off \
    COMPOSER_HOME=/tmp/composer

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
