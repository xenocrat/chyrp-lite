<?php header("Status: 403"); exit("Access denied."); ?>
# Template to enable clean URLs for the nginx web server.
#
# Usage:
#
#     server {
#         #...
#         include filesystem/path/to/nginx.conf;
#         #...
#     }

location /$chyrp_path/ {
    index index.php;
    rewrite \.twig$ /$chyrp_path/index.php;

    location  ^~ /$chyrp_path/admin/ {
        try_files $uri $uri/ /$chyrp_path/admin/index.php;
    }

    try_files $uri $uri/ /$chyrp_path/index.php;
}
