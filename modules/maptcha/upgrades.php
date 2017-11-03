<?php
    if (!defined('UPGRADING') or !UPGRADING)
        exit;

    /**
     * Function: maptcha_migrate_config
     * Moves config settings into an array.
     *
     * Versions: 2017.01 => 2017.02
     */
    function maptcha_migrate_config() {
        $config = Config::current();

        if (isset($config->maptcha_hashkey)) {
            $set = $config->set("module_maptcha", array("maptcha_hashkey" => $config->maptcha_hashkey));

            if ($set !== false)
                $set = $config->remove("maptcha_hashkey");

            if ($set === false)
                error(__("Error"), __("Could not write the configuration file."));
        }
    }

    maptcha_migrate_config();
