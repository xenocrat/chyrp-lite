<?php
return array(
    "name"          => __("Pingable", "pingable"),
    "url"           => "http://chyrplite.net/",
    "version"       => 1.1,
    "description"   => __("Register pingbacks from blogs that link to yours.", "pingable"),
    "author"        => array(
        "name"      => "Daniel Pimley",
        "url"       => "http://chyrplite.net/"),
    "notifications" => array(
                        __("Please remember to update the permission settings for each group.", "pingable")),
    "confirm"       => __("Do you want to remove pingbacks from the database?", "pingable"),
    "conflicts"     => array(
                       "comments")
);