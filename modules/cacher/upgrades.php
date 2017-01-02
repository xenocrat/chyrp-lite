<?php
    if (!defined('UPGRADING') or !UPGRADING)
        exit;

    /**
     * Function: remove_memcached_hosts
     * Removes the cache_memcached_hosts config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function remove_memcached_hosts() {
        Config::current()->remove("cache_memcached_hosts");
    }

    remove_memcached_hosts();
