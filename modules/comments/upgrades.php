<?php
    function remove_signature_add_updated_at() {
        if (SQL::current()->query("SELECT signature FROM __comments"))
            echo __("Removing signature column from comments table...", "comments").
                 test(SQL::current()->query("ALTER TABLE __comments DROP COLUMN  signature"));

        if (!SQL::current()->query("SELECT updated_at FROM __comments"))
            echo __("Adding updated_at column to comments table...", "comments").
                test(SQL::current()->query("ALTER TABLE __comments ADD  updated_at DATETIME DEFAULT NULL AFTER created_at"));
    }

    function remove_defensio_set_akismet() {
        if (!Config::check("defensio_api_key"))
            Config::set("akismet_api_key", null, "Creating akismet_api_key setting...");
        else {
            Config::remove("defensio_api_key", " ", "Removing defensio_api_key...");;
            Config::set("akismet_api_key", " ", "Creating akismet_api_key setting...");
        }
    }

    function add_comment_parent_id_field() {
        if (!SQL::current()->query("SELECT parent_id FROM __comments"))
            echo __("Adding parent_id column to comments table...", "comments").
                test(SQL::current()->query("ALTER TABLE __comments ADD parent_id INTEGER DEFAULT 0 AFTER user_id"));
    }

    function add_comment_notify_field() {
        if (!SQL::current()->query("SELECT notify FROM __comments"))
            echo __("Adding notify column to comments table...", "comments").
                test(SQL::current()->query("ALTER TABLE __comments ADD notify INTEGER DEFAULT 0 AFTER parent_id"));
    }

    Config::fallback("auto_reload_comments", 30);
    Config::fallback("enable_reload_comments", false);

    remove_signature_add_updated_at();
    remove_defensio_set_akismet();
    add_comment_parent_id_field();
    add_comment_notify_field();
