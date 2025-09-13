FROM wordpress:6.3-php8.1

# Configure PHP to use UTC timezone
RUN echo 'date.timezone=UTC' > /usr/local/etc/php/conf.d/zz-timezone.ini

# Install Composer and basic tools
USER root
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends curl git unzip ca-certificates jq default-mysql-client; \
    rm -rf /var/lib/apt/lists/*; \
    php -r "copy('https://getcomposer.org/installer','composer-setup.php');"; \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer; \
    rm composer-setup.php

# Run as non-root user
USER www-data
