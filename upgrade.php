<?php
    /**
     * File: Upgrader
     * A task-based gerneral purpose upgrader for Chyrp Lite, enabled modules and enabled extensions.
     *
     * Performs upgrade functions based on individual tasks, and checks whether or not they need to be done.
     */

    header("Content-type: text/html; charset=UTF-8");

    define('DEBUG',          true);
    define('CHYRP_VERSION',  "2016.01");
    define('CHYRP_CODENAME', "Socotra");
    define('CACHE_TWIG',     false);
    define('JAVASCRIPT',     false);
    define('ADMIN',          false);
    define('AJAX',           false);
    define('XML_RPC',        false);
    define('UPGRADING',      true);
    define('INSTALLING',     false);
    define('TESTER',         isset($_SERVER['HTTP_USER_AGENT']) and $_SERVER['HTTP_USER_AGENT'] == "TESTER");
    define('INDEX',          false);
    define('DIR',            DIRECTORY_SEPARATOR);
    define('MAIN_DIR',       dirname(__FILE__));
    define('INCLUDES_DIR',   MAIN_DIR.DIR."includes");
    define('CACHES_DIR',     INCLUDES_DIR.DIR."caches");
    define('MODULES_DIR',    MAIN_DIR.DIR."modules");
    define('FEATHERS_DIR',   MAIN_DIR.DIR."feathers");
    define('THEMES_DIR',     MAIN_DIR.DIR."themes");
    define('USE_ZLIB',       false);

    # Constant: JSON_PRETTY_PRINT
    # Define a safe value to avoid warnings pre-5.4
    if (!defined('JSON_PRETTY_PRINT'))
        define('JSON_PRETTY_PRINT', 0);

    # Constant: JSON_UNESCAPED_SLASHES
    # Define a safe value to avoid warnings pre-5.4
    if (!defined('JSON_UNESCAPED_SLASHES'))
        define('JSON_UNESCAPED_SLASHES', 0);

    if (version_compare(PHP_VERSION, "5.3.2", "<"))
        exit("Chyrp Lite requires PHP 5.3.2 or greater.");

    # Make sure E_STRICT is on so Chyrp remains errorless.
    error_reporting(E_ALL | E_STRICT);

    ob_start();

    # File: Error
    # Error handling functions.
    require_once INCLUDES_DIR.DIR."error.php";

    # File: Helpers
    # Various functions used throughout the codebase.
    require_once INCLUDES_DIR.DIR."helpers.php";

    # File: Gettext
    # Gettext library.
    require_once INCLUDES_DIR.DIR."lib".DIR."gettext".DIR."gettext.php";

    # File: Streams
    # Streams library.
    require_once INCLUDES_DIR.DIR."lib".DIR."gettext".DIR."streams.php";

    # File: SQL
    # See Also:
    #     <SQL>
    require INCLUDES_DIR.DIR."class".DIR."SQL.php";

    /**
     * Class: Config
     * Handles writing to the config file.
     */
    class Config {
        # Variable: $json
        # Holds all of the JSON settings as a $key => $val array.
        static $json = array();

        /**
         * Function: get
         * Returns a config setting.
         *
         * Parameters:
         *     $setting - The setting to return.
         */
        static function get($setting) {
            return (isset(Config::$json[$setting])) ? Config::$json[$setting] : false ;
        }

        /**
         * Function: set
         * Sets a config setting.
         *
         * Parameters:
         *     $setting - The config setting to set.
         *     $value - The value for the setting.
         *     $message - The message to display with test().
         */
        static function set($setting, $value, $message = null) {
            if (self::get($setting) == $value)
                return;

            if (!isset($message))
                $message = _f("Setting %s to %s...", array($setting, normalize(print_r($value, true))));

            Config::$json[$setting] = $value;
            $protection = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";
            $dump = $protection.json_encode(Config::$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            echo $message.test(@file_put_contents(INCLUDES_DIR.DIR."config.json.php", $dump));

        }

        /**
         * Function: check
         * Does a config exist?
         *
         * Parameters:
         *     $setting - Name of the config to check.
         */
        static function check($setting) {
            return (isset(Config::$json[$setting]));
        }

        /**
         * Function: fallback
         * Sets a config setting to $value if it does not exist.
         *
         * Parameters:
         *     $setting - The config setting to set.
         *     $value - The value for the setting.
         *     $message - The message to display with test().
         */
        static function fallback($setting, $value, $message = null) {
            if (!isset($message))
                $message = _f("Adding %s setting...", array($setting));

            if (!self::check($setting))
                echo self::set($setting, $value, $message);
        }

        /**
         * Function: remove
         * Removes a setting if it exists.
         *
         * Parameters:
         *     $setting - The setting to remove.
         */
        static function remove($setting) {
            if (!self::check($setting))
                return;

            unset(Config::$json[$setting]);
            $protection = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";
            $dump = $protection.json_encode(Config::$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            echo _f("Removing %s setting...", array($setting)).
                 test(@file_put_contents(INCLUDES_DIR.DIR."config.json.php", $dump));
        }
    }

    /**
     * Function: test
     * Attempts to perform a task, and displays a "success" or "failed" message determined by the outcome.
     *
     * Parameters:
     *     $try - The task to attempt. Should return something that evaluates to true or false.
     *     $message - Message to display for the test.
     */
    function test($try, $message = "") {
        $sql = SQL::current();

        if (!empty($sql->error)) {
            $message.= "\n".$sql->error."\n\n";
            $sql->error = "";
        }

        $info = $message;

        if ($try)
            return " <span class=\"yay\">".__("success!")."</span>\n";
        else
            return " <span class=\"boo\">".__("failed!")."</span>\n".$info;
    }

    # Attempt to load the config file and initialize the configuration.

    if (!file_exists(INCLUDES_DIR.DIR."config.json.php"))
        redirect("install.php");

    Config::$json = json_decode(preg_replace("/<\?php(.+)\?>\n?/s", "",
                                file_get_contents(INCLUDES_DIR.DIR."config.json.php")), true);

    if (json_last_error())
        $errors[] = fix(json_last_error_msg());

    # Prepare the SQL interface and initialize the connection to SQL server.

    $sql = SQL::current();

    foreach (Config::$json["sql"] as $name => $value)
        $sql->$name = $value;

    $sql->connect();

    # Load the translator

    load_translator("chyrp", INCLUDES_DIR.DIR."locale".DIR.Config::get("locale").".mo");

    #---------------------------------------------
    # Upgrading Actions
    #---------------------------------------------

    /**
     * Function: fix_htaccess
     * Repairs the .htaccess file.
     */
    function fix_htaccess() {
        $url = "http://".$_SERVER['HTTP_HOST'].str_replace("/upgrade.php", "", $_SERVER['REQUEST_URI']);
        $index = (parse_url($url, PHP_URL_PATH)) ? "/".trim(parse_url($url, PHP_URL_PATH), "/")."/" : "/" ;

        $path = preg_quote($index, "/");
        $htaccess_has_chyrp = (file_exists(MAIN_DIR.DIR.".htaccess") and
                               preg_match("/<IfModule mod_rewrite\\.c>\n".
                                          "([\\s]*)RewriteEngine On\n".
                                          "([\\s]*)RewriteBase {$path}\n".
                                          "([\\s]*)RewriteCond %\\{REQUEST_FILENAME\\} !-f\n".
                                          "([\\s]*)RewriteCond %\\{REQUEST_FILENAME\\} !-d\n".
                                          "([\\s]*)RewriteRule \\^\\.\\+\\$ index\\.php \\[L\\]\n".
                                          "(([\\s]*)RewriteRule \\^\\.\\+\\\\.twig\\$ index\\.php \\[L\\]\n)?".
                                          "([\\s]*)<\\/IfModule>/",
                                          file_get_contents(MAIN_DIR.DIR.".htaccess")));

        if ($htaccess_has_chyrp)
            return;

        $htaccess = "<IfModule mod_rewrite.c>\n".
                    "RewriteEngine On\n".
                    "RewriteBase {$index}\n".
                    "RewriteCond %{REQUEST_FILENAME} !-f\n".
                    "RewriteCond %{REQUEST_FILENAME} !-d\n".
                    "RewriteRule ^.+\$ index.php [L]\n".
                    "RewriteRule ^.+\\.twig\$ index.php [L]\n".
                    "</IfModule>";

        if (!file_exists(MAIN_DIR.DIR.".htaccess"))
            echo __("Generating .htaccess file...").
                 test(@file_put_contents(MAIN_DIR.DIR.".htaccess", $htaccess),
                      __("Please CHMOD or CHOWN the <em>.htaccess</em> file to make it writable."));
        else
            echo __("Appending to .htaccess file...").
                 test(@file_put_contents(MAIN_DIR.DIR.".htaccess", "\n\n".$htaccess, FILE_APPEND),
                      __("Please CHMOD or CHOWN the <em>.htaccess</em> file to make it writable."));
    }

    /**
     * Function: add_markdown
     * Adds the enable_markdown config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function add_markdown() {
        Config::fallback("enable_markdown", true);
    }

    /**
     * Function: add_homepage
     * Adds the enable_homepage config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function add_homepage() {
        Config::fallback("enable_homepage", false);
    }

    /**
     * Function: add_uploads_limit
     * Adds the uploads_limit config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function add_uploads_limit() {
        Config::fallback("uploads_limit", 10);
    }

    /**
     * Function: remove_trackbacking
     * Removes the enable_trackbacking config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function remove_trackbacking() {
        Config::remove("enable_trackbacking");
    }

    /**
     * Function: add_admin_per_page
     * Adds the admin_per_page config setting.
     *
     * Versions: 2015.07 => 2016.01
     */
    function add_admin_per_page() {
        Config::fallback("admin_per_page", 25);
    }
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title><?php echo __("Chyrp Lite Upgrader"); ?></title>
        <meta name="viewport" content="width = 520, user-scalable = no">
        <style type="text/css">
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-Semibold.woff') format('woff');
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
                src: url('./fonts/OpenSans-SemiboldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('./fonts/Hack-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('./fonts/Hack-Bold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('./fonts/Hack-Oblique.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('./fonts/Hack-BoldOblique.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            *::selection {
                color: #ffffff;
                background-color: #4f4f4f;
            }
            html {
                font-size: 14px;
            }
            html, body, ul, ol, li,
            h1, h2, h3, h4, h5, h6,
            form, fieldset, a, p {
                margin: 0em;
                padding: 0em;
                border: 0em;
            }
            body {
                font-size: 1rem;
                font-family: "Open Sans webfont", sans-serif;
                line-height: 1.5;
                color: #4a4747;
                background: #efefef;
                padding: 0em 0em 5em;
            }
            .window {
                width: 30em;
                background: #fff;
                padding: 2em;
                margin: 5em auto 0em;
                border-radius: 2em;
            }
            h1 {
                font-size: 2em;
                margin: 0.5em 0em;
                text-align: center;
                line-height: 1;
            }
            h1:first-child {
                margin-top: 0em;
            }
            code {
                font-family: "Hack webfont", monospace;
                font-style: normal;
                word-wrap: break-word;
                background-color: #efefef;
                padding: 2px;
                color: #4f4f4f;
            }
            strong {
                font-weight: normal;
                color: #f00;
            }
            ul, ol {
                margin: 0em 0em 2em 2em;
                list-style-position: outside;
            }
            li {
                margin-bottom: 1em;
            }
            a:link, a:visited {
                color: #4a4747;
            }
            a:hover, a:focus {
                color: #1e57ba;
            }
            pre.pane {
                height: 15em;
                overflow-y: auto;
                margin: 1em -2em 1em -2em;
                padding: 2em;
                background: #4a4747;
                color: #fff;
            }
            pre.pane:empty {
                display: none;
            }
            pre.pane:empty + h1 {
                margin-top: 0em;
            }
            span.yay { color: #76b362; }
            span.boo { color: #d94c4c; }
            a.big,
            button {
                box-sizing: border-box;
                display: block;
                font-family: inherit;
                font-size: 1.25em;
                text-align: center;
                color: #4a4747;
                text-decoration: none;
                line-height: 1.25;
                margin: 0.75em 0em;
                padding: 0.4em 0.6em;
                background-color: #f2fbff;
                border: 1px solid #b8cdd9;
                border-radius: 0.3em;
                cursor: pointer;
                text-decoration: none;
            }
            button {
                width: 100%;
            }
            a.big:last-child,
            button:last-child {
                margin-bottom: 0em;
            }
            a.big:hover,
            button:hover,
            a.big:focus,
            button:focus,
            a.big:active,
            button:active {
                border-color: #1e57ba;
                outline: none;
            }
            p {
                margin-bottom: 1em;
            }
        </style>
    </head>
    <body>
        <div class="window">
<?php if ((!empty($_POST) and $_POST['upgrade'] == "yes") or (isset($_GET['upgrade']) and $_GET['upgrade'] == "yes")) : ?>
            <pre role="status" class="pane"><?php

# Perform core upgrade tasks.

fix_htaccess();

add_markdown();

add_homepage();

add_uploads_limit();

remove_trackbacking();

add_admin_per_page();

# Perform Module/Feather upgrades.

foreach ((array) Config::get("enabled_modules") as $module)
    if (file_exists(MAIN_DIR.DIR."modules".DIR.$module.DIR."upgrades.php")) {
        ob_start();
        echo $begin = _f("Calling %s module's upgrader...", array($module))."\n";
        require MAIN_DIR.DIR."modules".DIR.$module.DIR."upgrades.php";

        if (ob_get_contents() == $begin)
            ob_end_clean();
        else
            ob_end_flush();
    }

foreach ((array) Config::get("enabled_feathers") as $feather)
    if (file_exists(MAIN_DIR.DIR."feathers".DIR.$feather.DIR."upgrades.php")) {
        ob_start();
        echo $begin = _f("Calling %s feather's upgrader...", array($feather))."\n";
        require MAIN_DIR.DIR."feathers".DIR.$feather.DIR."upgrades.php";

        if (ob_get_contents() == $begin)
            ob_end_clean();
        else
            ob_end_flush();
    }

foreach ($errors as $error)
    echo '<span role="alert">'.$error."</span>\n";

          ?></pre>
            <h1 class="what_now"><?php echo __("What now?"); ?></h1>
            <ol>
                <li><?php echo __("Look above for any reports of failed tasks or errors."); ?></li>
                <li><?php echo __("Fix any problems reported."); ?></li>
                <li><?php echo __("Execute this upgrader again until all tasks succeed."); ?></li>
                <li><?php echo __("You can delete <em>upgrade.php</em> once you are finished."); ?></li>
            </ol>
            <a class="big" href="<?php echo (Config::check("url") ? Config::get("url") : Config::get("chyrp_url")); ?>"><?php echo __("Take me to my site!"); ?></a>
<?php else: ?>
            <h1><?php echo __("Halt!"); ?></h1>
            <p><?php echo __("Please take these preemptive measures before proceeding:"); ?></p>
            <ol>
                <li><?php echo __("<strong>Make a backup of your installation and database.</strong>"); ?></li>
                <li><?php echo __("Disable any third-party Modules and Feathers."); ?></li>
                <li><?php echo __("Ensure Chyrp Lite's directory is writable by the server."); ?></li>
            </ol>
            <p><?php echo __("If any of the upgrade tasks fail, you can safely refresh and retry."); ?></p>
            <form action="upgrade.php" method="post">
                <button type="submit" name="upgrade" value="yes"><?php echo __("Upgrade me!"); ?></button>
            </form>
<?php endif; ?>
        </div>
    </body>
</html>