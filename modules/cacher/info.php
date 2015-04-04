<?php
return array(
    "name"          => __("Cacher", "cacher"),
    "url"           => "http://chyrplite.net/",
    "version"       => 1.0,
    "description"   => __("Caches pages, drastically reducing server load.", "cacher"),
    "author"        => array(
        "name"      => "Chyrp Team",
        "url"       => "http://chyrp.net/"),
    "notifications" => array(
    	__("Please make sure that /includes/caches is writable by the server. If you are not certain whether it is or not, CHMOD it to 777.", "cacher"))
);