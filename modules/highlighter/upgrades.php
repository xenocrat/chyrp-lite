<?php
    if (!defined('UPGRADING') or !UPGRADING)
        exit;

    /**
     * Function: highlighter_add_config
     * Adds the config setting for v1.2 and upwards.
     *
     * Versions: 2019.03 => 2019.04
     */
    function highlighter_add_config(
    ): void {
        $config = Config::current();

        if (!isset($config->module_highlighter)) {
            $set = $config->set(
                "module_highlighter",
                array("stylesheet" => "monokai-sublime.css")
            );

            if ($set === false)
                error(
                    __("Error"),
                    __("Could not write the configuration file.")
                );
        }
    }

    /**
     * Function: highlighter_add_copy_to_clipboard
     * Adds the copy_to_clipboard config setting.
     *
     * Versions: 2025.01 => 2025.02
     */
    function highlighter_add_copy_to_clipboard(
    ): void {
        $config = Config::current();
        $array = $config->module_highlighter;

        fallback($array["copy_to_clipboard"], false);

        $set = $config->set("module_highlighter", $array);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    highlighter_add_config();
    highlighter_add_copy_to_clipboard();
