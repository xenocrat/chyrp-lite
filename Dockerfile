FROM php:8.2-cli
COPY . /usr/src/chyrp-lite
WORKDIR /usr/src/chyrp-lite
CMD [ "php", "./install.php" ]
