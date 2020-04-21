<?php
    if (!defined('UPGRADING') or !UPGRADING)
        exit;

    /**
     * Function: sitemap_update_config
     * Updates config settings for for v1.2 and upwards.
     *
     * Versions: 2020.01 => 2020.02
     */
    function sitemap_update_config() {
        $config = Config::current();
        $array = $config->module_sitemap;

        fallback($array["sitemap_path"], MAIN_DIR);

        $set = $config->set("module_sitemap", $array);

        if ($set === false)
            error(__("Error"), __("Could not write the configuration file."));
    }

    sitemap_update_config();
