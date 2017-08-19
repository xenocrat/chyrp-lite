<?php
    if (!defined('UPGRADING') or !UPGRADING)
        exit;

    /**
     * Function: cascade_migrate_config
     * Moves config settings into an array.
     *
     * Versions: 2017.01 => 2017.02
     */
    function cascade_migrate_config() {
        $config = Config::current();

        if (isset($config->ajax_scroll_auto)) {
            $set = $config->set("module_cascade", array("ajax_scroll_auto" => $config->ajax_scroll_auto));

            if ($set !== false)
                $set = $config->remove("ajax_scroll_auto");

            if ($set === false)
                error(__("Error"), __("Could not write the configuration file."));
        }
    }

    cascade_migrate_config();
