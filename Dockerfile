FROM php:8.3-cli

# Base tools and PHP extensions (incl. GD for phpoffice/phpspreadsheet)
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip curl ca-certificates libzip-dev libicu-dev mariadb-client \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
 && docker-php-ext-install -j"$(nproc)" mysqli zip intl \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd \
 && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer','composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && php -r "unlink('composer-setup.php');"

WORKDIR /app
ENV XDEBUG_MODE=off

# Enable coverage: prefer pcov, else xdebug
RUN set -eux; \
    (pecl install pcov && docker-php-ext-enable pcov && echo "pcov.enabled=1" > /usr/local/etc/php/conf.d/pcov.ini) \
    || (pecl install xdebug && docker-php-ext-enable xdebug && echo "xdebug.mode=coverage" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini)
