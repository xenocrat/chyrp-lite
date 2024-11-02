<?php header("Status: 403"); exit("Access denied."); ?>
# Template to enable clean URLs for the Caddy web server v2.
#
# Usage:
#
#     example.com {
#         #...
#         import filesystem/path/to/caddyfile
#         #...
#     }

@twigs {
    path *.twig
}

@admin {
    path /{chyrp_path}/admin/*
    file {
        try_files {path} {path}/ /{chyrp_path}/admin/index.php
    }
}

@chyrp {
    path /{chyrp_path}/*
    file {
        try_files {path} {path}/ /{chyrp_path}/index.php
    }
}

rewrite @twigs /{chyrp_path}/index.php
rewrite @admin {http.matchers.file.relative}
rewrite @chyrp {http.matchers.file.relative}
