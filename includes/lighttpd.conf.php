<?php header("Status: 403"); exit("Access denied."); ?>
# Template to enable clean URLs for the Lighttpd web server.

url.rewrite-once = (
    "\.twig(?:[/?]|$)" => "/{chyrp_path}/index.php"
)

url.rewrite-if-not-file = (
    "^/{chyrp_path}/admin/.*" => "/{chyrp_path}/admin/index.php",
    "^/{chyrp_path}/.*" => "/{chyrp_path}/index.php"
)
