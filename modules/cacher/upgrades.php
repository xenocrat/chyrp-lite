<?php
    /**
     * Function: remove_trackbacking
     * Removes the enable_trackbacking config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function remove_memcached_hosts() {
        Config::remove("cache_memcached_hosts");
    }

    remove_memcached_hosts();
