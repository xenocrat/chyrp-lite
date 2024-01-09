<?php
return array(
    "name"          => __("Mentionable", "pingable"),
    "url"           => "http://chyrplite.net/",
    "version"       => "2024.01",
    "description"   => __("Register webmentions from blogs that link to yours.", "pingable"),
    "author"        => array(
        "name"      => "Daniel Pimley",
        "url"       => "http://chyrplite.net/"
    ),
    "notifications" => array(
                        __("Please remember to update the permission settings for each group.", "pingable")
    ),
    "confirm"       => __("Do you want to remove webmentions from the database?", "pingable"),
    "conflicts"     => array(
                       "comments"
    )
);