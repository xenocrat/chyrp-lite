<?php
    if (!defined('UPGRADING') or !UPGRADING)
        exit;

    /**
     * Function: highlighter_add_config
     * Adds the config setting for v1.2 and upwards.
     *
     * Versions: 2019.03 => 2019.04
     */
    function highlighter_add_config() {
        $config = Config::current();

        if (!isset($config->module_highlighter)) {
            $set = $config->set("module_highlighter",
                                array("stylesheet" => "monokai-sublime.css"));

            if ($set === false)
                error(__("Error"), __("Could not write the configuration file."));
        }
    }

    highlighter_add_config();
