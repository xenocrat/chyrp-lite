<?php
return array(
    "name"          => __("Comments", "comments"),
    "url"           => "http://chyrplite.net/",
    "version"       => "2024.01",
    "description"   => __("Adds commenting functionality to your posts, with webmention support.", "comments"),
    "author"        => array(
        "name"      => "Chyrp Team",
        "url"       => "http://chyrp.net/"
    ),
    "notifications" => array(
                        __("Please remember to update the permission settings for each group.", "comments")
    ),
    "confirm"       => __("Do you want to remove comments from the database?", "comments"),
    "conflicts"     => array(
                       "pingable"
    )
);