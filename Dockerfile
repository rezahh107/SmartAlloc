FROM php:8.2-cli
WORKDIR /app
COPY . /app
RUN php -v
CMD ["php","-v"]
