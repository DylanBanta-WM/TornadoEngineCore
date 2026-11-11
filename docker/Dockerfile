FROM php:8.4.26-fpm-trixie

ARG APP_UID=1000
ARG APP_GID=1000

RUN docker-php-ext-install pdo_mysql \
    && groupmod -o -g "${APP_GID}" www-data \
    && usermod -o -u "${APP_UID}" -g "${APP_GID}" www-data

WORKDIR /var/www/html
