<?php
return array(
    "name"          => __("Post Views", "post_views"),
    "url"           => "http://chyrplite.net/",
    "version"       => 1.0,
    "description"   => __("Counts the number of times your posts have been viewed.", "post_views"),
    "author"        => array(
        "name"      => "Daniel Pimley",
        "url"       => "http://chyrplite.net/"),
    "confirm"       => __("Do you want to remove view counts from the database?", "post_views"),
    "conflicts"     => array(
                       "cacher")
);