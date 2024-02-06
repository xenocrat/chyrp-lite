FROM php:8.2-apache
WORKDIR /var/www/html

COPY . .
RUN chown -R www-data ./*

RUN apt-get update
RUN apt-get install -y libonig-dev
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-enable pdo_mysql

EXPOSE 80
CMD [ "apache2-foreground" ]
