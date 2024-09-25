FROM php:8.3-apache

COPY --chown=www-data . /var/www/html/

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
    # Setup script
    && mv /var/www/html/entrypoint.sh / \
    && chmod u+x /entrypoint.sh \
    # Use production config for PHP
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    # Raise upload_max_size to 100M
    && sed -i 's/upload_max_filesize = .*/upload_max_filesize = 100M/' "$PHP_INI_DIR/php.ini" \
    # Raise post_max_size to 100M
    && sed -i 's/post_max_size = .*/post_max_size = 100M/' "$PHP_INI_DIR/php.ini"

USER www-data

EXPOSE 80/tcp

VOLUME /data
VOLUME /var/www/html/uploads

ENTRYPOINT ["/entrypoint.sh"]
