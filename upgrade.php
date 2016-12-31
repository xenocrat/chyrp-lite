<?php
    /**
     * File: Upgrader
     * A task-based gerneral purpose upgrader for Chyrp Lite, enabled modules and enabled feathers.
     */

    header("Content-Type: text/html; charset=UTF-8");

    define('DEBUG',          true);
    define('CHYRP_VERSION',  "2017.01");
    define('CHYRP_CODENAME', "Swainson");
    define('CHYRP_IDENTITY', "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");
    define('CACHE_TWIG',     false);
    define('JAVASCRIPT',     false);
    define('MAIN',           false);
    define('ADMIN',          false);
    define('AJAX',           false);
    define('XML_RPC',        false);
    define('UPGRADING',      true);
    define('INSTALLING',     false);
    define('TESTER',         isset($_SERVER['HTTP_USER_AGENT']) and $_SERVER['HTTP_USER_AGENT'] == "TESTER");
    define('DIR',            DIRECTORY_SEPARATOR);
    define('MAIN_DIR',       dirname(__FILE__));
    define('INCLUDES_DIR',   MAIN_DIR.DIR."includes");
    define('CACHES_DIR',     INCLUDES_DIR.DIR."caches");
    define('MODULES_DIR',    MAIN_DIR.DIR."modules");
    define('FEATHERS_DIR',   MAIN_DIR.DIR."feathers");
    define('THEMES_DIR',     MAIN_DIR.DIR."themes");
    define('USE_OB',         true);
    define('USE_ZLIB',       false);

    # Constant: JSON_PRETTY_PRINT
    # Define a safe value to avoid warnings pre-5.4.
    if (!defined('JSON_PRETTY_PRINT'))
        define('JSON_PRETTY_PRINT', 0);

    # Constant: JSON_UNESCAPED_SLASHES
    # Define a safe value to avoid warnings pre-5.4.
    if (!defined('JSON_UNESCAPED_SLASHES'))
        define('JSON_UNESCAPED_SLASHES', 0);

    ob_start();

    # File: Error
    # Error handling functions.
    require_once INCLUDES_DIR.DIR."error.php";

    # File: Helpers
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

    # Register our autoloader.
    spl_autoload_register("autoload");

    # Boolean: $upgraded
    # Has Chyrp Lite been upgraded?
    $upgraded = false;

    # Handle a missing config file with redirect.
    if (!file_exists(INCLUDES_DIR.DIR."config.json.php"))
        redirect("install.php");

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
     * Function: test
     * Displays a "success" or "failed" message determined by the value.
     *
     * Parameters:
     *     $value - Something that evaluates to true or false.
     */
    function test($value) {
        if ($value)
            return " <span class=\"yay\">".__("success!")."</span>\n";
        else
            return " <span class=\"boo\">".__("failed!")."</span>\n";
    }

    /**
     * Function: add_markdown
     * Adds the enable_markdown config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function add_markdown() {
        Config::current()->set("enable_markdown", true, true);
    }

    /**
     * Function: add_homepage
     * Adds the enable_homepage config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function add_homepage() {
        Config::current()->set("enable_homepage", false, true);
    }

    /**
     * Function: add_uploads_limit
     * Adds the uploads_limit config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function add_uploads_limit() {
        Config::current()->set("uploads_limit", 10, true);
    }

    /**
     * Function: remove_trackbacking
     * Removes the enable_trackbacking config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function remove_trackbacking() {
        Config::current()->remove("enable_trackbacking");
    }

    /**
     * Function: add_admin_per_page
     * Adds the admin_per_page config setting.
     *
     * Versions: 2015.07 => 2016.01
     */
    function add_admin_per_page() {
        Config::current()->set("admin_per_page", 25, true);
    }

    /**
     * Function: disable_importers
     * Disables the importers module.
     *
     * Versions: 2016.03 => 2016.04
     */
    function disable_importers() {
        $config = Config::current();
        $config->set("enabled_modules", array_diff((array) $config->enabled_modules, array("importers")));
    }

    /**
     * Function: add_export_content
     * Adds the export_content permission.
     *
     * Versions: 2016.03 => 2016.04
     */
    function add_export_content() {
        $sql = SQL::current();

        if (!$sql->count("permissions", array("id" => "export_content", "group_id" => 0)))
            $sql->insert("permissions", array("id" => "export_content", "name" => "Export Content", "group_id" => 0));
    }

    #---------------------------------------------
    # Output Starts
    #---------------------------------------------
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
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
                src: url('./fonts/Hack-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('./fonts/Hack-BoldItalic.woff') format('woff');
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
            h2 {
                font-size: 1.25em;
                text-align: center;
                font-weight: bold;
                margin: 0.75em 0em;
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
            span.yay {
                color: #76b362;
            }
            span.boo {
                color: #d94c4c;
            }
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
            aside {
                margin-bottom: 1em;
                padding: 0.5em 1em;
                border: 1px solid #e5d7a1;
                border-radius: 0.25em;
                background-color: #fffecd;
            }
        </style>
    </head>
    <body>
        <div class="window">
            <pre role="status" class="pane"><?php

    #---------------------------------------------
    # Upgrading Starts
    #---------------------------------------------

    if ((isset($_POST['upgrade']) and $_POST['upgrade'] == "yes") or (isset($_GET['upgrade']) and $_GET['upgrade'] == "yes")) {
        # Perform core upgrade tasks.
        add_markdown();
        add_homepage();
        add_uploads_limit();
        remove_trackbacking();
        add_admin_per_page();
        disable_importers();
        add_export_content();

        # Perform module upgrades.
        foreach ((array) $config->enabled_modules as $module)
            if (file_exists(MAIN_DIR.DIR."modules".DIR.$module.DIR."upgrades.php"))
                require MAIN_DIR.DIR."modules".DIR.$module.DIR."upgrades.php";

        # Perform feather upgrades.
        foreach ((array) $config->enabled_feathers as $feather)
            if (file_exists(MAIN_DIR.DIR."feathers".DIR.$feather.DIR."upgrades.php"))
                require MAIN_DIR.DIR."feathers".DIR.$feather.DIR."upgrades.php";

        $upgraded = true;
    }

    #---------------------------------------------
    # Upgrading Ends
    #---------------------------------------------

    foreach ($errors as $error)
        echo '<span role="alert">'.$error."</span>\n";

            ?></pre>
<?php if (!$upgraded): ?>
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
<?php else: ?>
            <h1><?php echo __("Chyrp Lite has been upgraded"); ?></h1>
            <h2><?php echo __("What now?"); ?></h2>
            <ol>
                <li><?php echo __("Look above for any reports of failed tasks or errors."); ?></li>
                <li><?php echo __("Fix any problems reported."); ?></li>
                <li><?php echo __("Execute this upgrader again until all tasks succeed."); ?></li>
                <li><?php echo __("You can delete <em>upgrade.php</em> once you are finished."); ?></li>
            </ol>
            <a class="big" href="<?php echo $config->url; ?>"><?php echo __("Take me to my site!"); ?></a>
<?php endif; ?>
        </div>
    </body>
</html>
