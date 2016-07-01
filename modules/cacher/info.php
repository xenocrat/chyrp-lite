<?php
return array(
    "name"          => __("Cacher", "cacher"),
    "url"           => "http://chyrplite.net/",
    "version"       => 1.2,
    "description"   => __("Caches pages, drastically reducing server load.", "cacher"),
    "author"        => array(
        "name"      => "Chyrp Team",
        "url"       => "http://chyrp.net/"),
    "notifications" => array(
                        _f("Please make sure that <em>%s</em> is writable by the server.", CACHES_DIR, "cacher"))
);