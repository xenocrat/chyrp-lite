<?php
return array(
    "name"          => __("Comments", "comments"),
    "url"           => "http://chyrplite.net/",
    "version"       => 1.8,
    "description"   => __("Adds commenting functionality to your posts, with pingback support.", "comments"),
    "author"        => array(
        "name"      => "Chyrp Team",
        "url"       => "http://chyrp.net/"),
    "notifications" => array(
                        __("Please remember to update the permission settings for each group.", "comments")),
    "confirm"       => __("Do you want to remove comments from the database?", "comments"),
    "conflicts"     => array(
                       "pingable")
);