<?php
    if (!defined('UPGRADING') or !UPGRADING)
        exit;

    /**
     * Function: read_more_add_config
     * Adds config settings into an array.
     *
     * Versions: 2022.02 => 2022.03
     */
    function read_more_add_config(
    ): void {
        $set = Config::current()->set(
            "module_read_more",
            array(
                "apply_to_feeds" => false,
                "default_text" => ""
            ),
            true
        );

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    read_more_add_config();
