<?php
    require "common.php";

    if (!$visitor->group->can("add_post"))
        show_403(__("Access Denied"), __("You do not have sufficient privileges to create posts."));

    ini_set("html_errors", "0");

    $config = Config::current();

    $uploads = $config->chyrp_url.$config->uploads_path;
	$directory = MAIN_DIR.$config->uploads_path;

    if ($handle = opendir($directory)) {
    	while ($image = readdir($handle)) {
    		if (!in_array($image, array(".", "..")) and !is_dir($directory.$image)) {
    			$extension = strtolower(pathinfo($image, PATHINFO_EXTENSION));
    			if (in_array($extension, array("jpg", "jpeg", "gif", "png", "bmp")))
                    $files[] = array("thumb" => $uploads.$image, "image" => $uploads.$image);
    		}
    	}
    }

    echo stripslashes(json_encode($files));
