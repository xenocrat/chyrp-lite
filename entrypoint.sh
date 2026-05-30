#!/bin/bash

upgrade() {
    until curl -Ifso /dev/null http://localhost/upgrade.php; do
        sleep 0.1
    done

    curl -fso /dev/null -X POST -d "upgrade=yes" http://localhost/upgrade.php
    rm /var/www/html/upgrade.php
    echo "Upgrade complete."
}

inc_dir="/var/www/html/includes"
storage_dir="${CHYRP_STORAGE_DIR:-$inc_dir}"
have_config="no"

if [[ -f "$storage_dir/config.json.php" ]]; then
  have_config="yes"
fi

if [[ $have_config == "yes" && -f "$inc_dir/install.php" ]]; then
  rm $inc_dir/install.php
fi

if [[ $have_config == "yes" && -f "$inc_dir/upgrade.php" ]]; then
  upgrade &
fi

exec apache2-foreground
