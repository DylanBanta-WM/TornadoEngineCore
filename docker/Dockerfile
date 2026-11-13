FROM php:8.4.26-fpm-trixie

ARG APP_UID=1000
ARG APP_GID=1000

# git and Composer: the updater service installs modules and Core updates with them.
COPY --from=composer:2.8.12 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && groupmod -o -g "${APP_GID}" www-data \
    && usermod -o -u "${APP_UID}" -g "${APP_GID}" www-data

WORKDIR /var/www/html
