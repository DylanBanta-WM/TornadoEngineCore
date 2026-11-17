FROM php:8.4.26-fpm-trixie

ARG APP_UID=1000
ARG APP_GID=1000

# git and Composer: the updater service installs modules and Core updates with them.
COPY --from=composer:2.8.12 /usr/bin/composer /usr/bin/composer

# poppler-utils, tesseract and zip: the Uploads module reads the text in PDFs, images and Office files;
# OpenCV looks for faces in images before Claude may see them.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip poppler-utils tesseract-ocr tesseract-ocr-eng python3-opencv opencv-data libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql zip \
    && groupmod -o -g "${APP_GID}" www-data \
    && usermod -o -u "${APP_UID}" -g "${APP_GID}" www-data

# PHP extensions modules ask for (see php-extensions.sh). Rebuilt with docker compose up -d --build.
COPY php-extensions.sh php-extensions.txt /tornado/
RUN sh /tornado/php-extensions.sh docker

# Uploads up to 1024 MB (the Uploads module's own limit is set lower in Administration).
RUN printf 'upload_max_filesize = 1024M\npost_max_size = 1040M\nmax_file_uploads = 20\nmax_input_time = 600\n' > /usr/local/etc/php/conf.d/tornado-uploads.ini

WORKDIR /var/www/html
