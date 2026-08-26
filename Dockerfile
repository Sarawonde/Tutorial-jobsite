FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers

COPY . /var/www/html/

RUN mkdir -p /data \
    && chown -R www-data:www-data /data /var/www/html

ENV DATA_DIR=/data

EXPOSE 80

