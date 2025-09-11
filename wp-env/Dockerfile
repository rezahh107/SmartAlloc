FROM wordpress:6.3-php8.1

# Configure PHP to use UTC timezone
RUN echo 'date.timezone=UTC' > /usr/local/etc/php/conf.d/zz-timezone.ini

# Run as non-root user
USER www-data
