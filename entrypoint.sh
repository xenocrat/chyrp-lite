#!/bin/bash

sync_config() {
    inotifywait -qme close_write,moved_to,create /var/www/html/includes/ --format '%f' \
        | while read -r filename; do
            if [ "$filename" = "config.json.php" ]; then
                cp /var/www/html/includes/config.json.php /data/config.json.php
            fi
        done
}

upgrade() {
    until curl -Ifso /dev/null http://localhost/upgrade.php; do
        sleep 0.1
    done

    curl -fso /dev/null -X POST -d "upgrade=yes" http://localhost/upgrade.php
    rm /var/www/html/upgrade.php
    echo "Upgrade complete."
}

if [ -f /data/config.json.php ]; then
    cp /data/config.json.php /var/www/html/includes/config.json.php
    rm /var/www/html/install.php
    upgrade=true
fi

sync_config &
[ "$upgrade" = true ] && upgrade &

exec apache2-foreground
