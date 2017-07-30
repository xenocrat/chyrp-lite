<?php
    if (!defined('UPGRADING') or !UPGRADING)
        exit;

    /**
     * Function: comments_migrate_config
     * Moves config settings into an array.
     *
     * Versions: 2017.01 => 2017.02
     */
    function comments_migrate_config() {
        global $errors;

        $config = Config::current();

        if (isset($config->default_comment_status) and
            isset($config->allowed_comment_html) and
            isset($config->comments_per_page) and
            property_exists($config, "akismet_api_key") and
            isset($config->auto_reload_comments) and
            isset($config->enable_reload_comments)) {

            $set = $config->set("module_comments",
                                array("default_comment_status" => $config->default_comment_status,
                                      "allowed_comment_html" => $config->allowed_comment_html,
                                      "comments_per_page" => $config->comments_per_page,
                                      "akismet_api_key" => $config->akismet_api_key,
                                      "auto_reload_comments" => $config->auto_reload_comments,
                                      "enable_reload_comments" => $config->enable_reload_comments));

            if ($set !== false) {
                $set = array($config->remove("default_comment_status"),
                             $config->remove("allowed_comment_html"),
                             $config->remove("comments_per_page"),
                             $config->remove("akismet_api_key"),
                             $config->remove("auto_reload_comments"),
                             $config->remove("enable_reload_comments"));
            }

            if (in_array(false, (array) $set, true))
                $errors[] = __("Could not write the configuration file.");
        }
    }

    comments_migrate_config();
