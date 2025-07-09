<?php
    /**
     * File: upgrade
     * A task-based general purpose upgrader for Chyrp Lite, enabled modules and enabled feathers.
     */

    header("Content-Type: text/html; charset=UTF-8");

    define('DEBUG',                         true);
    define('CHYRP_VERSION',                 "2025.03");
    define('CHYRP_CODENAME',                "Bridled");
    define('CHYRP_IDENTITY',                "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");
    define('MAIN',                          false);
    define('ADMIN',                         false);
    define('AJAX',                          false);
    define('UPGRADING',                     true);
    define('INSTALLING',                    false);
    define('COOKIE_LIFETIME',               2592000);
    define('PASSWORD_RESET_TOKEN_LIFETIME', 3600);
    define('MAX_TIME_LIMIT',                600);
    define('MAX_MEMORY_LIMIT',              "100M");
    define('SQL_DATETIME_ZERO',             "1000-01-01 00:00:00");
    define('SQL_DATETIME_ZERO_VARIANTS',
                                            array(
                                                "0000-00-00 00:00:00",
                                                "0001-01-01 00:00:00",
                                                "1000-01-01 00:00:00"
                                            )
    );
    define('BOT_UA',                        false);
    define('DIR',                           DIRECTORY_SEPARATOR);
    define('MAIN_DIR',                      dirname(__FILE__));
    define('INCLUDES_DIR',                  MAIN_DIR.DIR."includes");
    define('CACHES_DIR',                    INCLUDES_DIR.DIR."caches");
    define('MODULES_DIR',                   MAIN_DIR.DIR."modules");
    define('FEATHERS_DIR',                  MAIN_DIR.DIR."feathers");
    define('THEMES_DIR',                    MAIN_DIR.DIR."themes");
    define('UPDATE_XML',                    null);
    define('UPDATE_INTERVAL',               null);
    define('UPDATE_PAGE',                   null);
    define('SESSION_DENY_BOT',              true);
    define('SLUG_STRICT',                   true);
    define('GET_REMOTE_UNSAFE',             false);
    define('USE_GETTEXT_SHIM',              true);
    define('USE_OB',                        true);
    define('HTTP_ACCEPT_ZSTD',              false);
    define('HTTP_ACCEPT_DEFLATE',           false);
    define('HTTP_ACCEPT_GZIP',              false);
    define('CAN_USE_ZSTD',                  false);
    define('CAN_USE_ZLIB',                  false);
    define('USE_COMPRESSION',               false);
    define('PREVIEWING',                    false);
    define('THEME_DIR',                     null);
    define('THEME_URL',                     null);

    ob_start();
    define('OB_BASE_LEVEL', ob_get_level());

    # File: error
    # Functions for handling and reporting errors.
    require_once INCLUDES_DIR.DIR."error.php";

    # File: helpers
    # Various functions used throughout the codebase.
    require_once INCLUDES_DIR.DIR."helpers.php";

    # File: Config
    # See Also:
    #     <Config>
    require_once INCLUDES_DIR.DIR."class".DIR."Config.php";

    # File: SQL
    # See Also:
    #     <SQL>
    require INCLUDES_DIR.DIR."class".DIR."SQL.php";

    # File: Translation
    # See Also:
    #     <Translation>
    require_once INCLUDES_DIR.DIR."class".DIR."Translation.php";

    # Register our autoloader.
    spl_autoload_register("autoload");

    # Boolean: $upgraded
    # Has Chyrp Lite been upgraded?
    $upgraded = false;

    # Load the config settings.
    $config = Config::current();

    # Prepare the SQL interface.
    $sql = SQL::current();

    # Initialize connection to SQL server.
    $sql->connect();

    # Set the locale.
    set_locale($config->locale);

    # Load the translation engine.
    load_translator("chyrp", INCLUDES_DIR.DIR."locale");

    /**
     * Function: alert
     * Logs an alert message and returns the log to date.
     */
    function alert(
        $message = null
    ): ?array {
        static $log = array();

        if (isset($message))
            $log[] = (string) $message;

        return empty($log) ? null : $log ;
    }

    /**
     * Function: test_directories
     * Tests whether or not the directories that need write access have it.
     */
    function test_directories(
    ): void {
        # Test if we can write to MAIN_DIR (needed for the .htaccess file).
        if (!is_writable(MAIN_DIR))
            alert(
                __("Please CHMOD or CHOWN the installation directory to make it writable.")
            );

        # Test if we can write to INCLUDES_DIR (needed for config.json.php).
        if (!is_writable(INCLUDES_DIR))
            alert(
                __("Please CHMOD or CHOWN the <em>includes</em> directory to make it writable.")
            );

        # Test if we can write to CACHES_DIR (needed by some extensions).
        if (!is_writable(CACHES_DIR))
            alert(
                __("Please CHMOD or CHOWN the <em>caches</em> directory to make it writable.")
            );

        # Test if we can write to twig cache.
        if (!is_writable(CACHES_DIR.DIR."twig"))
            alert(
                __("Please CHMOD or CHOWN the <em>twig</em> directory to make it writable.")
            );

        # Test if we can write to thumbs cache.
        if (!is_writable(CACHES_DIR.DIR."thumbs"))
            alert(
                __("Please CHMOD or CHOWN the <em>thumbs</em> directory to make it writable.")
            );
    }

    /**
     * Function: add_markdown
     * Adds the enable_markdown config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function add_markdown(
    ): void {
        $set = Config::current()->set("enable_markdown", true, true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_homepage
     * Adds the enable_homepage config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function add_homepage(
    ): void {
        $set = Config::current()->set("enable_homepage", false, true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_uploads_limit
     * Adds the uploads_limit config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function add_uploads_limit(
    ): void {
        $set = Config::current()->set("uploads_limit", 10, true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: remove_trackbacking
     * Removes the enable_trackbacking config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function remove_trackbacking(
    ): void {
        $set = Config::current()->remove("enable_trackbacking");

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_admin_per_page
     * Adds the admin_per_page config setting.
     *
     * Versions: 2015.07 => 2016.01
     */
    function add_admin_per_page(
    ): void {
        $set = Config::current()->set("admin_per_page", 25, true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: disable_importers
     * Disables the importers module.
     *
     * Versions: 2016.03 => 2016.04
     */
    function disable_importers(
    ): void {
        $config = Config::current();
        $set = $config->set(
            "enabled_modules",
            array_diff($config->enabled_modules, array("importers"))
        );

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_export_content
     * Adds the export_content permission.
     *
     * Versions: 2016.03 => 2016.04
     */
    function add_export_content(
    ): void {
        $sql = SQL::current();

        if (
            !$sql->count(
                "permissions",
                array(
                    "id" => "export_content",
                    "group_id" => 0
                )
            )
        )
            $sql->insert(
                "permissions",
                array(
                    "id" => "export_content",
                    "name" => "Export Content",
                    "group_id" => 0
                )
            );
    }

    /**
     * Function: add_feed_format
     * Adds the feed_format config setting.
     *
     * Versions: 2017.02 => 2017.03
     */
    function add_feed_format(
    ): void {
        $set = Config::current()->set("feed_format", "AtomFeed", true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: remove_captcha
     * Removes the enable_captcha config setting.
     *
     * Versions: 2017.03 => 2018.01
     */
    function remove_captcha(
    ): void {
        $set = Config::current()->remove("enable_captcha");

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: disable_recaptcha
     * Disables the recaptcha module.
     *
     * Versions: 2017.03 => 2018.01
     */
    function disable_recaptcha(
    ): void {
        $config = Config::current();
        $set = $config->set(
            "enabled_modules",
            array_diff($config->enabled_modules, array("recaptcha"))
        );

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: remove_feed_url
     * Removes the feed_url config setting.
     *
     * Versions: 2018.03 => 2018.04
     */
    function remove_feed_url(
    ): void {
        $set = Config::current()->remove("feed_url");

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: remove_cookies_notification
     * Removes the cookies_notification config setting.
     *
     * Versions: 2019.01 => 2019.02
     */
    function remove_cookies_notification(
    ): void {
        $set = Config::current()->remove("cookies_notification");

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: remove_ajax
     * Removes the enable_ajax config setting.
     *
     * Versions: 2019.02 => 2019.03
     */
    function remove_ajax(
    ): void {
        $set = Config::current()->remove("enable_ajax");

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: disable_simplemde
     * Disables the simplemde module.
     *
     * Versions: 2019.03 => 2019.04
     */
    function disable_simplemde(
    ): void {
        $config = Config::current();
        $set = $config->set(
            "enabled_modules",
            array_diff($config->enabled_modules, array("simplemde"))
        );

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_search_pages
     * Adds the search_pages config setting.
     *
     * Versions: 2020.03 => 2020.04
     */
    function add_search_pages(
    ): void {
        $set = Config::current()->set("search_pages", false, true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: fix_sqlite_post_pinned
     * Fixes the pinned status of posts created without bool-to-int conversion.
     *
     * Versions: 2021.01 => 2021.02
     */
    function fix_sqlite_post_pinned(
    ): void {
        $sql = SQL::current();

        if ($sql->adapter != "sqlite")
            return;

        $results = $sql->select(
            tables:"posts",
            fields:"id",
            conds:array("pinned" => "")
        )->fetchAll();

        foreach ($results as $result)
            $sql->update(
                table:"posts",
                conds:array("id" => $result["id"]),
                data:array("pinned" => false)
            );
    }

    /**
     * Function: fix_post_updated
     * Normalizes updated_at values to "1000-01-01 00:00:00".
     *
     * Versions: 2022.01 => 2022.02, 2024.01
     */
    function fix_post_updated(
    ): void {
        $sql = SQL::current();

        $values = ($sql->adapter == "pgsql") ?
            array(
                "0001-01-01 00:00:00"
            )
            :
            array(
                "0000-00-00 00:00:00",
                "0001-01-01 00:00:00"
            )
            ;

        $results = $sql->select(
            tables:"posts",
            fields:"id",
            conds:array("updated_at" => $values)
        )->fetchAll();

        foreach ($results as $result)
            $sql->update(
                table:"posts",
                conds:array("id" => $result["id"]),
                data:array("updated_at" => SQL_DATETIME_ZERO)
            );
    }

    /**
     * Function: mysql_utf8mb4
     * Upgrades MySQL database tables and columns to utf8mb4.
     *
     * Versions: 2022.01 => 2022.02
     */
    function mysql_utf8mb4(
    ): void {
        $sql = SQL::current();

        if ($sql->adapter != "mysql")
            return;

        $tables = $sql->query("SHOW TABLE STATUS")->fetchAll();

        foreach ($tables as $table) {
            if (strpos($table["Collation"], "utf8mb4_") === 0)
                continue;

            $sql->query(
                "ALTER TABLE \"".$table["Name"].
                "\" CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
            );
        }
    }

    /**
     * Function: add_import_content
     * Adds the import_content permission.
     *
     * Versions: 2022.02 => 2022.03
     */
    function add_import_content(
    ): void {
        $sql = SQL::current();

        if (
            !$sql->count(
                "permissions",
                array(
                    "id" => "import_content",
                    "group_id" => 0
                )
            )
        )
            $sql->insert(
                "permissions",
                array(
                    "id" => "import_content",
                    "name" => "Import Content",
                    "group_id" => 0
                )
            );
    }

    /**
     * Function: remove_xmlrpc
     * Removes the enable_xmlrpc config setting.
     *
     * Versions: 2022.03 => 2023.01
     */
    function remove_xmlrpc(
    ): void {
        $set = Config::current()->remove("enable_xmlrpc");

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_monospace_font
     * Adds the monospace_font config setting.
     *
     * Versions: 2023.03 => 2024.01
     */
    function add_monospace_font(
    ): void {
        $set = Config::current()->set("monospace_font", false, true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_default_statuses
     * Adds the default_post_status and default_page_status config settings.
     *
     * Versions: 2024.03 => 2025.01
     */
    function add_default_statuses(
    ): void {
        $config = Config::current();

        $set = array(
            $config->set("default_post_status", "public", true),
            $config->set("default_page_status", "listed", true)
        );

        if (in_array(false, $set, true))
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: rename_cacert_pem
     * Renames the cacert.pem file and adds/updates the config setting.
     *
     * Versions: 2024.03 => 2025.01
     */
    function rename_cacert_pem(
    ): void {
        $config = Config::current();

        if (!file_exists(INCLUDES_DIR.DIR."cacert.pem"))
            return;

        do {
            $cacert_pem = random(32).".pem";
        } while (
            file_exists(INCLUDES_DIR.DIR.$cacert_pem)
        );

        @rename(
            INCLUDES_DIR.DIR."cacert.pem",
            INCLUDES_DIR.DIR.$cacert_pem
        );

        $set = $config->set("cacert_pem", $cacert_pem);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_view_upload
     * Adds the view_upload permission.
     *
     * Versions: 2025.01 => 2025.02
     */
    function add_view_upload(
    ): void {
        $sql = SQL::current();

        if (
            !$sql->count(
                "permissions",
                array(
                    "id" => "view_upload",
                    "group_id" => 0
                )
            )
        )
            $sql->insert(
                "permissions",
                array(
                    "id" => "view_upload",
                    "name" => "View Uploads",
                    "group_id" => 0
                )
            );
    }

    /**
     * Function: add_add_upload
     * Adds the add_upload permission.
     *
     * Versions: 2025.01 => 2025.02
     */
    function add_add_upload(
    ): void {
        $sql = SQL::current();

        if (
            !$sql->count(
                "permissions",
                array(
                    "id" => "add_upload",
                    "group_id" => 0
                )
            )
        )
            $sql->insert(
                "permissions",
                array(
                    "id" => "add_upload",
                    "name" => "Add Uploads",
                    "group_id" => 0
                )
            );
    }

    /**
     * Function: add_edit_upload
     * Adds the edit_upload permission.
     *
     * Versions: 2025.01 => 2025.02
     */
    function add_edit_upload(
    ): void {
        $sql = SQL::current();

        if (
            !$sql->count(
                "permissions",
                array(
                    "id" => "edit_upload",
                    "group_id" => 0
                )
            )
        )
            $sql->insert(
                "permissions",
                array(
                    "id" => "edit_upload",
                    "name" => "Edit Uploads",
                    "group_id" => 0
                )
            );
    }

    /**
     * Function: add_delete_upload
     * Adds the delete_upload permission.
     *
     * Versions: 2025.01 => 2025.02
     */
    function add_delete_upload(
    ): void {
        $sql = SQL::current();

        if (
            !$sql->count(
                "permissions",
                array(
                    "id" => "delete_upload",
                    "group_id" => 0
                )
            )
        )
            $sql->insert(
                "permissions",
                array(
                    "id" => "delete_upload",
                    "name" => "Delete Uploads",
                    "group_id" => 0
                )
            );
    }

    #---------------------------------------------
    # Output Starts
    #---------------------------------------------
?>
<!DOCTYPE html>
<html dir="auto">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=640">
        <title><?php echo __("Chyrp Lite Upgrader"); ?></title>
        <style type="text/css">
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-SemiBold.woff') format('woff');
                font-weight: 600;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-Bold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-SemiBoldItalic.woff') format('woff');
                font-weight: 600;
                font-style: italic;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-BoldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('./fonts/Cousine-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('./fonts/Cousine-Bold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('./fonts/Cousine-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('./fonts/Cousine-BoldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            :root {
                color-scheme: light dark;
                --chyrp-pure-white: #ffffff;
                --chyrp-pure-black: #000000;
                --chyrp-inky-black: #1f1f23;
                --chyrp-summer-grey: #fbfbfb;
                --chyrp-english-grey: #efefef;
                --chyrp-welsh-grey: #dfdfdf;
                --chyrp-irish-grey: #cfcfcf;
                --chyrp-scottish-grey: #afafaf;
                --chyrp-winter-grey: #656565;
                --chyrp-strong-yellow: #ffdd00;
                --chyrp-strong-orange: #ff7f00;
                --chyrp-strong-red: #c11600;
                --chyrp-strong-green: #108600;
                --chyrp-strong-blue: #1e57ba;
                --chyrp-strong-purple: #ba1eba;
                --chyrp-light-yellow: #fffde6;
                --chyrp-light-red: #faebe4;
                --chyrp-light-green: #ebfae4;
                --chyrp-light-blue: #f2fbff;
                --chyrp-light-purple: #fae4fa;
                --chyrp-medium-yellow: #fffbcc;
                --chyrp-medium-red: #fcddcf;
                --chyrp-medium-green: #daf1d0;
                --chyrp-medium-blue: #e1f2fa;
                --chyrp-medium-purple: #f6d5f6;
                --chyrp-border-yellow: #e0ce8d;
                --chyrp-border-red: #cdaea5;
                --chyrp-border-green: #aecda5;
                --chyrp-border-blue: #a7c1d0;
                --chyrp-border-purple: #cda5cd;
            }
            *::selection {
                color: var(--chyrp-inky-black);
                background-color: var(--chyrp-strong-yellow);
            }
            html, body, div, dl, dt, dd, ul, ol, li, p,
            h1, h2, h3, h4, h5, h6, img, pre, code,
            form, fieldset, input, select, svg, textarea,
            table, tbody, tr, th, td, legend, caption,
            blockquote, aside, figure, figcaption {
                margin: 0em;
                padding: 0em;
                border: 0em;
            }
            html {
                font-size: 16px;
            }
            body {
                font-size: 1rem;
                font-family: "Open Sans webfont", sans-serif;
                line-height: 1.5;
                color: var(--chyrp-inky-black);
                tab-size: 4;
                background: var(--chyrp-english-grey);
                margin: 2rem;
            }
            h1 {
                font-size: 2em;
                font-weight: bold;
                margin: 1rem 0rem;
                text-align: center;
            }
            h2 {
                font-size: 1.5em;
                font-weight: bold;
                text-align: center;
                margin: 1rem 0rem;
            }
            h3 {
                font-size: 1em;
                font-weight: 600;
                margin: 1rem 0rem;
                border-bottom: 1px solid var(--chyrp-irish-grey);
            }
            p {
                margin-bottom: 1rem;
            }
            strong {
                font: inherit;
                font-weight: bold;
                color: var(--chyrp-strong-red);
            }
            em, dfn, cite, var {
                font: inherit;
                font-style: italic;
            }
            ul, ol {
                margin-bottom: 1rem;
                margin-inline-start: 2rem;
                list-style-position: outside;
            }
            pre {
                font-family: "Cousine webfont", monospace;
                font-size: 0.85em;
                background-color: var(--chyrp-english-grey);
                margin: 1rem 0rem;
                padding: 1rem;
                overflow-x: auto;
                white-space: pre;
            }
            pre:focus-visible {
                outline: var(--chyrp-strong-orange) dashed 2px;
            }
            code {
                font-family: "Cousine webfont", monospace;
                font-size: 0.85em;
                background-color: var(--chyrp-english-grey);
                padding: 2px 4px 0px 4px;
                border: 1px solid var(--chyrp-irish-grey);
                vertical-align: bottom;
                white-space: break-spaces;
            }
            pre > code {
                font-size: 0.85rem;
                display: block;
                border: none;
                padding: 0px;
                white-space: inherit;
            }
            pre.pane {
                height: 15rem;
                overflow: auto;
            }
            pre.pane:empty {
                display: none;
            }
            pre.pane:empty + h1 {
                margin-top: 0rem;
            }
            a:link,
            a:visited {
                color: var(--chyrp-inky-black);
                text-decoration: underline;
                text-underline-offset: 0.125em;
            }
            a:hover,
            a:focus,
            a:active {
                outline: none;
                color: var(--chyrp-strong-blue);
                text-decoration: underline;
                text-underline-offset: 0.125em;
            }
            a:focus-visible {
                outline: var(--chyrp-strong-orange) dashed 2px;
                outline-offset: 2px;
            }
            a.big,
            button {
                box-sizing: border-box;
                display: block;
                font: inherit;
                font-size: 1.25em;
                text-align: center;
                color: var(--chyrp-inky-black);
                text-decoration: none;
                margin: 1rem 0rem;
                padding: 0.5rem 1rem;
                background-color: var(--chyrp-light-blue);
                border: 2px solid var(--chyrp-border-blue);
                border-radius: 0.25em;
                cursor: pointer;
            }
            button {
                width: 100%;
            }
            a.big:hover,
            button:hover,
            a.big:focus,
            button:focus,
            a.big:active,
            button:active {
                border-color: var(--chyrp-strong-blue);
                outline: none;
            }
            hr {
                border: none;
                clear: both;
                border-top: 1px solid var(--chyrp-irish-grey);
                margin: 2rem 0rem;
            }
            aside {
                margin-bottom: 1rem;
                padding: 0.5rem;
                border: 1px solid var(--chyrp-border-yellow);
                border-radius: 0.25em;
                background-color: var(--chyrp-light-yellow);
            }
            .window {
                width: 30rem;
                background: var(--chyrp-pure-white);
                padding: 2rem;
                margin: 0rem auto 0rem auto;
                border-radius: 2rem;
            }
            .window > *:first-child,
            form > *:first-child {
                margin-top: 0rem;
            }
            .window > *:last-child,
            form > *:last-child {
                margin-bottom: 0rem;
            }
            @media (prefers-color-scheme: dark) {
                body {
                    color: var(--chyrp-pure-white);
                    background-color: var(--chyrp-inky-black);
                }
                .window {
                    color: var(--chyrp-inky-black);
                    background-color: var(--chyrp-english-grey);
                }
                h3 {
                    border-color: var(--chyrp-scottish-grey);
                }
                hr {
                    border-color: var(--chyrp-scottish-grey);
                }
                aside {
                    background-color: var(--chyrp-medium-yellow);
                    border-color: var(--chyrp-scottish-grey);
                }
                pre {
                    background-color: var(--chyrp-welsh-grey);
                }
                code {
                    background-color: var(--chyrp-welsh-grey);
                    border-color: var(--chyrp-scottish-grey);
                }
            }
        </style>
    </head>
    <body>
        <div class="window">
            <pre class="pane"><?php

    #---------------------------------------------
    # Upgrading Starts
    #---------------------------------------------

    if (isset($_POST['upgrade']) and $_POST['upgrade'] == "yes") {
        # Perform core upgrade tasks.
        test_directories();
        add_markdown();
        add_homepage();
        add_uploads_limit();
        remove_trackbacking();
        add_admin_per_page();
        disable_importers();
        add_export_content();
        add_feed_format();
        remove_captcha();
        disable_recaptcha();
        remove_feed_url();
        remove_cookies_notification();
        remove_ajax();
        disable_simplemde();
        add_search_pages();
        fix_sqlite_post_pinned();
        fix_post_updated();
        mysql_utf8mb4();
        add_import_content();
        remove_xmlrpc();
        add_monospace_font();
        add_default_statuses();
        rename_cacert_pem();
        add_view_upload();
        add_add_upload();
        add_edit_upload();
        add_delete_upload();

        # Perform module upgrades.
        foreach ($config->enabled_modules as $module) {
            if (file_exists(MAIN_DIR.DIR."modules".DIR.$module.DIR."upgrades.php"))
                require MAIN_DIR.DIR."modules".DIR.$module.DIR."upgrades.php";
        }

        # Perform feather upgrades.
        foreach ($config->enabled_feathers as $feather) {
            if (file_exists(MAIN_DIR.DIR."feathers".DIR.$feather.DIR."upgrades.php"))
                require MAIN_DIR.DIR."feathers".DIR.$feather.DIR."upgrades.php";
        }

        # Clean up.
        @unlink(INCLUDES_DIR.DIR."upgrading.lock");
        @unlink(MAIN_DIR.DIR."Dockerfile");
        @unlink(MAIN_DIR.DIR."docker-compose.yaml");
        @unlink(MAIN_DIR.DIR."entrypoint.sh");
        @unlink(MAIN_DIR.DIR.".gitignore");
        @unlink(MAIN_DIR.DIR.".dockerignore");
        $upgraded = true;
    }

    #---------------------------------------------
    # Upgrading Ends
    #---------------------------------------------

    foreach ((array) alert() as $message)
        echo '<span role="alert">'.sanitize_html($message).'</span>'."\n";

            ?></pre>
<?php if (!$upgraded): ?>
            <h1><?php echo __("Halt!"); ?></h1>
            <p><?php echo __("Please take these precautionary measures before you upgrade:"); ?></p>
            <ol>
                <li><?php echo __("<strong>Backup your database before proceeding!</strong>"); ?></li>
                <li><?php echo __("Tell your users that your site is offline for maintenance."); ?></li>
            </ol>
            <hr>
            <form action="upgrade.php" method="post">
                <button type="submit" name="upgrade" value="yes"><?php echo __("Upgrade me!"); ?></button>
            </form>
<?php else: ?>
            <h1><?php echo __("Upgrade Complete"); ?></h1>
            <h2><?php echo __("What now?"); ?></h2>
            <ol>
                <li><?php echo __("Take action to resolve any errors reported on this page."); ?></li>
                <li><?php echo __("Run this upgrader again if you need to."); ?></li>
                <li><?php echo __("Delete <em>upgrade.php</em> once you are finished upgrading."); ?></li>
            </ol>
            <hr>
            <a class="big" href="<?php echo $config->url.'/'; ?>"><?php echo __("Take me to my site!"); ?></a>
<?php endif; ?>
        </div>
    </body>
</html>
