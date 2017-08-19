<?php
    if (!defined('UPGRADING') or !UPGRADING)
        exit;

    /**
     * Function: cacher_migrate_config
     * Moves config settings into an array.
     *
     * Versions: 2017.01 => 2017.02
     */
    function cacher_migrate_config() {
        $config = Config::current();

        if (isset($config->cache_expire) and isset($config->cache_exclude)) {
            $set = $config->set("module_cacher",
                                array("cache_expire" => $config->cache_expire,
                                      "cache_exclude" => $config->cache_exclude));

            if ($set !== false) {
                $set = array($config->remove("cache_expire"),
                             $config->remove("cache_exclude"),
                             $config->remove("cache_memcached_hosts"));
            }

            if (in_array(false, (array) $set, true))
                error(__("Error"), __("Could not write the configuration file."));
        }
    }

    cacher_migrate_config();
