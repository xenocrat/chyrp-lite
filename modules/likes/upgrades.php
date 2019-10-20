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
                                      "like_image" => "pink.svg"));

            if ($set !== false)
                $set = $config->remove("module_like");

            if ($set === false)
                error(__("Error"), __("Could not write the configuration file."));
        }
    }

    likes_migrate_config();
