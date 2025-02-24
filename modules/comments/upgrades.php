<?php
    if (!defined('UPGRADING') or !UPGRADING)
        exit;

    /**
     * Function: comments_migrate_config
     * Moves config settings into an array.
     *
     * Versions: 2017.01 => 2017.02
     */
    function comments_migrate_config(
    ): void {
        $config = Config::current();

        if (
            isset($config->default_comment_status) and
            isset($config->allowed_comment_html) and
            isset($config->comments_per_page) and
            property_exists($config, "akismet_api_key") and
            isset($config->auto_reload_comments) and
            isset($config->enable_reload_comments)
        ) {
            $set = $config->set(
                "module_comments",
                array(
                    "default_comment_status" => $config->default_comment_status,
                    "allowed_comment_html" => $config->allowed_comment_html,
                    "comments_per_page" => $config->comments_per_page,
                    "akismet_api_key" => $config->akismet_api_key,
                    "auto_reload_comments" => $config->auto_reload_comments,
                    "enable_reload_comments" => $config->enable_reload_comments
                )
            );

            if ($set !== false) {
                $set = array(
                    $config->remove("default_comment_status"),
                    $config->remove("allowed_comment_html"),
                    $config->remove("comments_per_page"),
                    $config->remove("akismet_api_key"),
                    $config->remove("auto_reload_comments"),
                    $config->remove("enable_reload_comments")
                );
            }

            if (in_array(false, (array) $set, true))
                error(
                    __("Error"),
                    __("Could not write the configuration file.")
                );
        }
    }

    /**
     * Function: fix_comment_updated
     * Normalizes updated_at values to "1000-01-01 00:00:00".
     *
     * Versions: 2022.01 => 2022.02, 2024.01
     */
    function fix_comment_updated(
    ): void {
        $sql = SQL::current();

        $values = ($sql->adapter == "pgsql") ?
            array(
                "0001-01-01 00:00:00"
            )
            :
            array(
                "0000-00-00 00:00:00",
                "0001-01-01 00:00:00"
            )
            ;

        $results = $sql->select(
            tables:"comments",
            fields:"id",
            conds:array("updated_at" => $values)
        )->fetchAll();

        foreach ($results as $result)
            $sql->update(
                table:"comments",
                conds:array("id" => $result["id"]),
                data:array("updated_at" => "1000-01-01 00:00:00")
            );
    }

    /**
     * Function: comments_update_config
     * Updates config settings for 2023.03 and upwards.
     *
     * Versions: 2023.02 => 2023.03, 2025.02
     */
    function comments_update_config(
    ): void {
        $config = Config::current();
        $array = $config->module_comments;

        fallback($array["notify_site_contact"], false);
        fallback($array["notify_post_author"], false);
        fallback($array["code_in_comments"], true);

        $set = $config->set("module_comments", $array);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    comments_migrate_config();
    fix_comment_updated();
    comments_update_config();
