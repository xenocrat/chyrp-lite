FROM php:8.2-apache

WORKDIR /var/www/html
COPY . .

RUN chown -R www-data ./*

RUN apt-get update
RUN apt-get install -y libonig-dev libpq-dev
RUN docker-php-ext-install pdo_mysql pdo_pgsql

EXPOSE 80
VOLUME /var/www/html
CMD [ "apache2-foreground" ]
