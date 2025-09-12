FROM php:8.3-cli

# ابزارها و اکستنشن‌ها
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip curl ca-certificates libzip-dev libicu-dev mariadb-client \
 && docker-php-ext-install -j"$(nproc)" mysqli zip intl \
 && rm -rf /var/lib/apt/lists/*

# نصب Composer
RUN php -r "copy('https://getcomposer.org/installer','composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && php -r "unlink('composer-setup.php');"

WORKDIR /app
ENV XDEBUG_MODE=off
