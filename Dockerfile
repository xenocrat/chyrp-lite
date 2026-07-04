ARG PHP_VERSION="8.3"
FROM php:${PHP_VERSION}-apache

ENV CHYRP_STORAGE_DIR="/data"

# Install dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
      inotify-tools \
      libonig-dev \
      libpq-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_pgsql pdo_mysql

RUN mkdir -p /data/ \
    && chown -R www-data /data \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/upload_max_filesize = .*/upload_max_filesize = 100M/' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/post_max_size = .*/post_max_size = 100M/' "$PHP_INI_DIR/php.ini" \
    && echo "PassEnv CHYRP_STORAGE_DIR" > /etc/apache2/conf-enabled/chyrp-env.conf

COPY --chown=www-data --exclude=entrypoint.sh . /var/www/html/
COPY --chown=www-data --chmod=744 entrypoint.sh /

EXPOSE 80/tcp

VOLUME /data
VOLUME /var/www/html/uploads

USER www-data
ENTRYPOINT ["/entrypoint.sh"]
