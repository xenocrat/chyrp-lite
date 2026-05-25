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

inc_dir="/var/www/html/includes"
have_config="no"

if [[ -f /data/config.json.php ]]; then
  cp /data/config.json.php "$inc_dir"
fi

if [[ -f "$inc_dir/config.json.php" ]]; then
  have_config="yes"
  sync_config &
fi

if [[ $have_config == "yes" && -f "$inc_dir/install.php" ]]; then
  rm $inc_dir/install.php
fi

if [[ $have_config == "yes" && -f "$inc_dir/upgrade.php" ]]; then
  upgrade &
fi

exec apache2-foreground
