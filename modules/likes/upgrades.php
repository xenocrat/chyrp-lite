<?php
    if (!defined('UPGRADING') or !UPGRADING)
        exit;

    /**
     * Function: likes_migrate_config
     * Moves config settings into a properly named array.
     *
     * Versions: 2017.01 => 2017.02
     */
    function likes_migrate_config() {
        $config = Config::current();

        if (isset($config->module_like)) {
            $set = $config->set("module_likes",
                                array("show_on_index" => $config->module_like["showOnFront"],
                                      "like_with_text" => $config->module_like["likeWithText"],
                                      "like_image" => $config->module_like["likeImage"]));

            if ($set !== false)
                $config->remove("module_like");
        }
    }

    likes_migrate_config();
