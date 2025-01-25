<?php
    /**
     * File: install
     * Creates the SQL tables and builds the site configuration.
     */

    header("Content-Type: text/html; charset=UTF-8");

    define('DEBUG',                         true);
    define('CHYRP_VERSION',                 "2025.01.01");
    define('CHYRP_CODENAME',                "Boreal");
    define('CHYRP_IDENTITY',                "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");
    define('MAIN',                          false);
    define('ADMIN',                         false);
    define('AJAX',                          false);
    define('UPGRADING',                     false);
    define('INSTALLING',                    true);
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
    define('USE_GETTEXT_SHIM',              stripos(PHP_OS, "Win") === 0);
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

    if (version_compare(PHP_VERSION, "8.1", "<"))
        exit("Chyrp Lite requires PHP 8.1 or greater. Installation cannot continue.");

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

    # File: Model
    # See Also:
    #     <Model>
    require_once INCLUDES_DIR.DIR."class".DIR."Model.php";

    # File: User
    # See Also:
    #     <User>
    require_once INCLUDES_DIR.DIR."model".DIR."User.php";

    # File: Translation
    # See Also:
    #     <Translation>
    require_once INCLUDES_DIR.DIR."class".DIR."Translation.php";

    # Register our autoloader.
    spl_autoload_register("autoload");

    # Boolean: $installed
    # Has Chyrp Lite been installed?
    $installed = false;

    # Prepare the Config interface.
    $config = Config::current();

    # Get the timezone.
    $timezone = get_timezone();

    # Get the locale.
    $locale = get_locale();

    # List of discovered drivers.
    $drivers = array();

    # Currently selected adapter.
    $adapter = isset($_POST['adapter']) ? $_POST['adapter'] : "mysql" ;

    # Where are we?
    $url = preg_replace("/\/install\.php.*$/i", "", guess_url());

    # Set the timezone.
    set_timezone($timezone);

    # Set the locale.
    set_locale($locale);

    # Try to load an appropriate translation.
    load_translator("chyrp", INCLUDES_DIR.DIR."locale");

    # Already installed?
    if (file_exists(INCLUDES_DIR.DIR."config.json.php"))
        redirect($config->url);

    if (class_exists("PDO")) {
        $pdo_available_drivers = PDO::getAvailableDrivers();

        if (in_array("sqlite", $pdo_available_drivers))
            $drivers[] = "sqlite";

        if (in_array("mysql", $pdo_available_drivers))
            $drivers[] = "mysql";

        if (in_array("pgsql", $pdo_available_drivers))
            $drivers[] = "pgsql";
    }

    # Test for basic database access requirements.
    if (empty($drivers))
        alert(
            __("PDO is required for database access."));

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
     * Function: guess_url
     * Returns a best guess of the current URL.
     */
    function guess_url(
    ): string {
        $scheme = (!empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== "off") ?
            "https" :
            "http" ;

        $host = isset($_SERVER['HTTP_HOST']) ?
            $_SERVER['HTTP_HOST'] :
            $_SERVER['SERVER_NAME'] ;

        return $scheme."://".$host.$_SERVER['REQUEST_URI'];
    }

    /**
     * Function: posted
     * Echoes a $_POST value if set, otherwise echoes the fallback value.
     *
     * Parameters:
     *     $key - The key to test in the $_POST array.
     *     $fallback - The value to echo if the $_POST value is not set.
     */
    function posted(
        $key,
        $fallback = ""
    ): void {
        echo fix(
            isset($_POST[$key]) ? $_POST[$key] : $fallback, true
        );
    }

    /**
     * Function: selected
     * Echoes " selected" HTML attribute if the supplied values are equal.
     *
     * Parameters:
     *     $val1 - Compare this value...
     *     $val2 - ... with this value.
     */
    function selected(
        $val1,
        $val2
    ): void {
        if ($val1 == $val2)
            echo " selected";
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
        <title><?php echo __("Chyrp Lite Installer"); ?></title>
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
                --chyrp-border-yellow: #e5d7a1;
                --chyrp-border-red: #d6bdb5;
                --chyrp-border-green: #bdd6b5;
                --chyrp-border-blue: #b8cdd9;
                --chyrp-border-purple: #d6b5d6;
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
                text-align: center;
                font-weight: bold;
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
            label {
                display: block;
                font-weight: 600;
            }
            textarea {
                display: block;
                resize: vertical;
            }
            input, select {
                display: inline-block;
            }
            input[type="text"],
            input[type="email"],
            input[type="url"],
            input[type="number"],
            input[type="password"],
            select,
            textarea {
                box-sizing: border-box;
                width: 100%;
                margin: 0rem;
                color: var(--chyrp-inky-black);
                font: inherit;
                font-size: 1.25em;
                padding: 0.5rem;
                border-radius: 0em;
                border: 1px solid var(--chyrp-irish-grey);
                background-color: var(--chyrp-pure-white);
            }
            select {
                appearance: none;
                padding-right: 1em;
                background-image: url(admin/images/icons/select.svg);
                background-position: center right 0.1em;
                background-repeat: no-repeat;
            }
            input:invalid,
            textarea:invalid {
                border-color: var(--chyrp-strong-orange);
            }
            input[type="text"]:focus,
            input[type="email"]:focus,
            input[type="url"]:focus,
            input[type="number"]:focus,
            input[type="password"]:focus,
            select:focus,
            textarea:focus {
                border-color: var(--chyrp-strong-blue);
                outline: var(--chyrp-strong-blue) solid 2px;
                outline-offset: -2px;
            }
            input[type="text"].error,
            input[type="email"].error,
            input[type="url"].error,
            input[type="number"].error,
            input[type="password"].error,
            textarea.error {
                background-color: var(--chyrp-light-red);
            }
            input[type="text"].error:focus,
            input[type="email"].error:focus,
            input[type="url"].error:focus,
            input[type="number"].error:focus,
            input[type="password"].error:focus,
            textarea.error:focus {
                border: 1px solid var(--chyrp-strong-red);
                outline-color: var(--chyrp-strong-red);
            }
            input[type="password"].strong {
                background-color: var(--chyrp-light-green);
            }
            input[type="password"].strong:focus {
                border: 1px solid var(--chyrp-strong-green);
                outline-color: var(--chyrp-strong-green);
            }
            form:has(#adapter > option[value="sqlite"]:checked) *.not-sqlite {
                display: none;
            }
            form:has(#adapter > option[value="mysql"]:checked) *.not-mysql {
                display: none;
            }
            form:has(#adapter > option[value="pgsql"]:checked) *.not-pgsql {
                display: none;
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
            a:focus {
                outline: var(--chyrp-strong-orange) dashed 2px;
                outline-offset: 0px;
            }
            a:hover,
            a:focus,
            a:active {
                color: var(--chyrp-strong-blue);
                text-decoration: underline;
                text-underline-offset: 0.125em;
            }
            a.big,
            button {
                box-sizing: border-box;
                display: block;
                clear: both;
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
                    border-color: var(--chyrp-scottish-grey);
                }
                pre {
                    background-color: var(--chyrp-welsh-grey);
                }
                code {
                    background-color: var(--chyrp-welsh-grey);
                    border-color: var(--chyrp-scottish-grey);
                }
                select,
                textarea,
                input[type="text"],
                input[type="email"],
                input[type="url"],
                input[type="number"],
                input[type="password"] {
                    background-color: var(--chyrp-welsh-grey);
                    border-color: var(--chyrp-scottish-grey);
                }
                input:invalid {
                    border-color: var(--chyrp-strong-orange);
                }
            }
        </style>
        <script src="includes/common.js" type="text/javascript" charset="UTF-8"></script>
        <script type="text/javascript">
            'use strict';

            $(function() {
                $("#password1").keyup(function(e) {
                    var password = $(this).val();

                    if (passwordStrength(password) > 99)
                        $(this).addClass("strong");
                    else
                        $(this).removeClass("strong");
                });

                $("#password1, #password2").keyup(function(e) {
                    var password1 = $("#password1").val();
                    var password2 = $("#password2").val();

                    if (password1 != "" && password1 != password2)
                        $("#password2").addClass("error");
                    else
                        $("#password2").removeClass("error");
                });

                $("#installer").on("submit", function(e) {
                    var password1 = $("#password1").val();
                    var password2 = $("#password2").val();

                    if (password1 != password2) {
                        e.preventDefault();
                        alert('<?php echo __("Passwords do not match."); ?>');
                    }
                });

                $("#url").keyup(function(e) {
                    var text = $(this).val();

                    if (text != "" && !isURL(text))
                        $(this).addClass("error");
                    else
                        $(this).removeClass("error");
                });

                $("#url").on("change", function(e) {
                    var text = $(this).val();

                    if (isURL(text))
                        $(this).val(addScheme(text));
                });

                $("#email").keyup(function(e) {
                    var text = $(this).val();

                    if (text != "" && !isEmail(text))
                        $(this).addClass("error");
                    else
                        $(this).removeClass("error");
                });

                $("#locale").change(function(e) {
                    $("#installer").submit();
                });
            });
        </script>
    </head>
    <body>
        <div class="window">
            <pre class="pane"><?php

    #---------------------------------------------
    # Installation Starts
    #---------------------------------------------

    if (isset($_POST['install']) and $_POST['install'] == "yes") {
        if (empty($_POST['database']))
            alert(__("Database cannot be blank."));

        if (empty($_POST['url']))
            alert(__("Chyrp URL cannot be blank."));
        elseif (!is_url($_POST['url']))
            alert(__("Invalid Chyrp URL."));

        if (empty($_POST['name']))
            alert(__("Please enter a name for your website."));

        if (empty($_POST['timezone']))
            alert(__("Time zone cannot be blank."));

        if (empty($_POST['locale']))
            alert(__("Language cannot be blank."));

        if (empty($_POST['login']))
            alert(__("Please enter a username for your account."));

        $login = sanitize_db_string($_POST['login'], 64);

        if (empty($login))
            alert(__("Invalid username."));

        if (empty($_POST['password1']) or empty($_POST['password2']))
            alert(__("Passwords cannot be blank."));
        elseif ($_POST['password1'] != $_POST['password2'])
            alert(__("Passwords do not match."));

        if (empty($_POST['email']))
            alert(__("Email address cannot be blank."));
        elseif (!is_email($_POST['email']))
            alert(__("Invalid email address."));

        if (!alert() and $_POST['adapter'] == "sqlite") {
            $realpath = realpath(dirname($_POST['database']));

            if ($realpath === false)
                alert(__("Could not determine the absolute path to the database."));
            else
                $_POST['database'] = $realpath.DIR.basename($_POST['database']);
        }

        if (!alert() and $_POST['adapter'] == "sqlite")
            if (!is_writable(dirname($_POST['database'])))
                alert(__("Please make the database writable by the server."));

        if (!alert() and $_POST['adapter'] != "sqlite")
            if (empty($_POST['username']) or empty($_POST['password']))
                alert(__("Please enter a username and password for the database."));

        if (!alert()) {
            # Build the SQL settings based on user input.
            $settings = ($_POST['adapter'] == "sqlite") ?
                array(
                    "host"     => "",
                    "port"     => "",
                    "username" => "",
                    "password" => "",
                    "database" => $_POST['database'],
                    "prefix"   => "",
                    "adapter"  => $_POST['adapter']
                )
                :
                array(
                    "host"     => $_POST['host'],
                    "port"     => $_POST['port'],
                    "username" => $_POST['username'],
                    "password" => $_POST['password'],
                    "database" => $_POST['database'],
                    "prefix"   => $_POST['prefix'],
                    "adapter"  => $_POST['adapter']
                )
                ;

            # Configure the SQL interface.
            $sql = SQL::current($settings);

            # Test the database connection.
            if (!$sql->connect(true))
                alert(_f("Database error: %s", fix($sql->error, false, true)));
        }

        if (!alert()) {
            # Reconnect to the database.
            $sql->connect();

            # Posts table.
            $sql->create(
                table:"posts",
                cols:array(
                    "id INTEGER PRIMARY KEY AUTO_INCREMENT",
                    "feather VARCHAR(32) DEFAULT ''",
                    "clean VARCHAR(128) DEFAULT ''",
                    "url VARCHAR(128) DEFAULT ''",
                    "pinned BOOLEAN DEFAULT FALSE",
                    "status VARCHAR(32) DEFAULT 'public'",
                    "user_id INTEGER DEFAULT 0",
                    "created_at DATETIME DEFAULT NULL",
                    "updated_at DATETIME DEFAULT NULL"
                )
            );

            # Post attributes table.
            $sql->create(
                table:"post_attributes",
                cols:array(
                    "post_id INTEGER NOT NULL",
                    "name VARCHAR(100) DEFAULT ''",
                    "value LONGTEXT",
                    "PRIMARY KEY (post_id, name)"
                )
            );

            # Pages table.
            $sql->create(
                table:"pages",
                cols:array(
                    "id INTEGER PRIMARY KEY AUTO_INCREMENT",
                    "title VARCHAR(250) DEFAULT ''",
                    "body LONGTEXT",
                    "public BOOLEAN DEFAULT '1'",
                    "show_in_list BOOLEAN DEFAULT '1'",
                    "list_order INTEGER DEFAULT 0",
                    "clean VARCHAR(128) DEFAULT ''",
                    "url VARCHAR(128) DEFAULT ''",
                    "user_id INTEGER DEFAULT 0",
                    "parent_id INTEGER DEFAULT 0",
                    "created_at DATETIME DEFAULT NULL",
                    "updated_at DATETIME DEFAULT NULL"
                )
            );

            # Users table.
            $sql->create(
                table:"users",
                cols:array(
                    "id INTEGER PRIMARY KEY AUTO_INCREMENT",
                    "login VARCHAR(64) DEFAULT ''",
                    "password VARCHAR(128) DEFAULT ''",
                    "full_name VARCHAR(250) DEFAULT ''",
                    "email VARCHAR(128) DEFAULT ''",
                    "website VARCHAR(128) DEFAULT ''",
                    "group_id INTEGER DEFAULT 0",
                    "approved BOOLEAN DEFAULT '1'",
                    "joined_at DATETIME DEFAULT NULL",
                    "UNIQUE (login)"
                )
            );

            # Groups table.
            $sql->create(
                table:"groups",
                cols:array(
                    "id INTEGER PRIMARY KEY AUTO_INCREMENT",
                    "name VARCHAR(100) DEFAULT ''",
                    "UNIQUE (name)"
                )
            );

            # Permissions table.
            $sql->create(
                table:"permissions",
                cols:array(
                    "id VARCHAR(100) DEFAULT ''",
                    "name VARCHAR(100) DEFAULT ''",
                    "group_id INTEGER DEFAULT 0",
                    "PRIMARY KEY (id, group_id)"
                )
            );

            # Sessions table.
            $sql->create(
                table:"sessions",
                cols:array(
                    "id VARCHAR(40) DEFAULT ''",
                    "data LONGTEXT",
                    "user_id INTEGER DEFAULT 0",
                    "created_at DATETIME DEFAULT NULL",
                    "updated_at DATETIME DEFAULT NULL",
                    "PRIMARY KEY (id)"
                )
            );

            # Define and insert the default permissions.
            $names = array(
                "change_settings" => "Change Settings",
                "toggle_extensions" => "Toggle Extensions",
                "view_site" => "View Site",
                "view_private" => "View Private Posts",
                "view_scheduled" => "View Scheduled Posts",
                "view_draft" => "View Drafts",
                "view_own_draft" => "View Own Drafts",
                "add_post" => "Add Posts",
                "add_draft" => "Add Drafts",
                "edit_post" => "Edit Posts",
                "edit_draft" => "Edit Drafts",
                "edit_own_post" => "Edit Own Posts",
                "edit_own_draft" => "Edit Own Drafts",
                "delete_post" => "Delete Posts",
                "delete_draft" => "Delete Drafts",
                "delete_own_post" => "Delete Own Posts",
                "delete_own_draft" => "Delete Own Drafts",
                "view_page" => "View Pages",
                "add_page" => "Add Pages",
                "edit_page" => "Edit Pages",
                "delete_page" => "Delete Pages",
                "add_user" => "Add Users",
                "edit_user" => "Edit Users",
                "delete_user" => "Delete Users",
                "add_group" => "Add Groups",
                "edit_group" => "Edit Groups",
                "delete_group" => "Delete Groups",
                "import_content" => "Import Content",
                "export_content" => "Export Content"
            );

            # Delete all existing permissions.
            $sql->delete(
                table:"permissions",
                conds:false
            );

            # Insert the new default permissions.
            foreach ($names as $id => $name) {
                $sql->insert(
                    table:"permissions",
                    data:array(
                        "id" => $id,
                        "name" => $name,
                        "group_id" => 0
                    )
                );
            }

            # Define and insert the default groups.
            $groups = array(
                "Admin"  => array_keys($names),
                "Member" => array("view_site"),
                "Friend" => array(
                    "view_site",
                    "view_private",
                    "view_scheduled"
                ),
                "Banned" => array(),
                "Guest"  => array("view_site")
            );

            $group_id = array();

            foreach ($groups as $name => $permissions) {
                # Insert the group if it does not exist.
                if (
                    !$sql->count(
                        tables:"groups",
                        conds:array("name" => $name)
                    )
                ) {
                    $sql->insert(
                        table:"groups",
                        data:array("name" => $name)
                    );
                }

                # Fetch the group's ID for permission creation.
                $group_id[$name] = $sql->select(
                    tables:"groups",
                    fields:"id",
                    conds:array("name" => $name),
                )->fetchColumn();

                # Insert the new permissions for this group.
                foreach ($permissions as $permission) {
                    $sql->insert(
                        table:"permissions",
                        data:array(
                            "id" => $permission,
                            "name" => $names[$permission],
                            "group_id" => $group_id[$name]
                        )
                    );
                }
            }

            # Add the admin user account if it does not exist.
            if (
                !$sql->count(
                    tables:"users",
                    conds:array("login" => $login)
                )
            ) {
                $sql->insert(
                    table:"users",
                    data:array(
                        "login" => $login,
                        "password" => User::hash_password($_POST['password1']),
                        "email" => sanitize_db_string($_POST['email'], 128),
                        "group_id" => $group_id["Admin"],
                        "approved" => true,
                        "joined_at" => datetime()
                    )
                );
            }

            # Rename cacert.pem file to thwart discovery.
            do {
                $cacert_pem = random(32).".pem";
            } while (
                file_exists(INCLUDES_DIR.DIR.$cacert_pem)
            );

            @rename(
                INCLUDES_DIR.DIR."cacert.pem",
                INCLUDES_DIR.DIR.$cacert_pem
            );

            # Normalize the Chyrp URL.
            $chyrp_url = rtrim(add_scheme($_POST['url']), "/");

            # Build the configuration file.
            $set = array(
                $config->set("sql", $settings),
                $config->set("name", strip_tags($_POST['name'])),
                $config->set("description", strip_tags($_POST['description'])),
                $config->set("url", $chyrp_url),
                $config->set("chyrp_url", $chyrp_url),
                $config->set("email", $_POST['email']),
                $config->set("timezone", $_POST['timezone']),
                $config->set("locale", $_POST['locale']),
                $config->set("monospace_font", false),
                $config->set("check_updates", true),
                $config->set("check_updates_last", 0),
                $config->set("theme", "blossom"),
                $config->set("posts_per_page", 5),
                $config->set("admin_per_page", 25),
                $config->set("default_post_status", "public"),
                $config->set("default_page_status", "listed"),
                $config->set("feed_format", "AtomFeed"),
                $config->set("feed_items", 20),
                $config->set("uploads_path", DIR."uploads".DIR),
                $config->set("uploads_limit", 10),
                $config->set("search_pages", false),
                $config->set("send_pingbacks", false),
                $config->set("enable_emoji", true),
                $config->set("enable_markdown", true),
                $config->set("can_register", false),
                $config->set("email_activation", false),
                $config->set("email_correspondence", true),
                $config->set("default_group", $group_id["Member"]),
                $config->set("guest_group", $group_id["Guest"]),
                $config->set("clean_urls", false),
                $config->set("enable_homepage", false),
                $config->set("post_url", "(year)/(month)/(day)/(url)/"),
                $config->set("enabled_modules", array()),
                $config->set("enabled_feathers", array("text")),
                $config->set("routes", array()),
                $config->set("secure_hashkey", random(32)),
                $config->set("cacert_pem", $cacert_pem)
            );

            if (in_array(false, $set, true))
                error(
                    __("Error"),
                    __("Could not write the configuration file.")
                );

            # Clean up.
            @unlink(INCLUDES_DIR.DIR."upgrading.lock");
            @unlink(MAIN_DIR.DIR."Dockerfile");
            @unlink(MAIN_DIR.DIR."docker-compose.yaml");
            @unlink(MAIN_DIR.DIR."entrypoint.sh");
            @unlink(MAIN_DIR.DIR.".gitignore");
            @unlink(MAIN_DIR.DIR.".dockerignore");
            $installed = true;
        }
    }

    #---------------------------------------------
    # Installation Ends
    #---------------------------------------------

    foreach ((array) alert() as $message)
        echo '<span role="alert">'.sanitize_html($message).'</span>'."\n";

          ?></pre>
<?php if (!$installed): ?>
            <form action="install.php" method="post" accept-charset="UTF-8" id="installer">
                <h1><?php echo __("Database Setup"); ?></h1>
                <p id="adapter_field">
                    <label for="adapter"><?php echo __("Adapter"); ?></label>
                    <select name="adapter" id="adapter">
                        <?php if (in_array("sqlite", $drivers)): ?>
                        <option value="sqlite"<?php selected("sqlite", $adapter); ?>>SQLite</option>
                        <?php endif; ?>
                        <?php if (in_array("mysql", $drivers)): ?>
                        <option value="mysql"<?php selected("mysql", $adapter); ?>>MySQL</option>
                        <?php endif; ?>
                        <?php if (in_array("pgsql", $drivers)): ?>
                        <option value="pgsql"<?php selected("pgsql", $adapter); ?>>PostgreSQL</option>
                        <?php endif; ?>
                    </select>
                </p>
                <p id="host_field" class="not-sqlite">
                    <label for="host"><?php echo __("Host"); ?></label>
                    <input type="text" name="host" value="<?php posted("host", (isset($_ENV['DATABASE_SERVER']) ? $_ENV['DATABASE_SERVER'] : "localhost")); ?>" id="host">
                </p>
                <p id="port_field" class="not-sqlite">
                    <label for="port"><?php echo __("Port"); ?> <span class="sub"><?php echo __("(optional)"); ?></span></label>
                    <input type="text" name="port" value="<?php posted("port"); ?>" id="port">
                </p>
                <p id="username_field" class="not-sqlite">
                    <label for="username"><?php echo __("Username"); ?></label>
                    <input type="text" name="username" value="<?php posted("username"); ?>" id="username">
                </p>
                <p id="password_field" class="not-sqlite">
                    <label for="password"><?php echo __("Password"); ?></label>
                    <input type="password" name="password" value="<?php posted("password"); ?>" id="password">
                </p>
                <p id="database_field">
                    <label for="database"><?php echo __("Database"); ?>
                        <span id="database_sub" class="sub not-mysql not-pgsql">
                            <?php echo __("(absolute or relative path)"); ?>
                        </span>
                    </label>
                    <input type="text" name="database" value="<?php posted("database"); ?>" id="database">
                </p>
                <aside id="db_aside_pgsql" class="not-sqlite not-mysql">
                    <?php echo __("Make sure your PostgreSQL database uses UTF-8 encoding."); ?>
                </aside>
                <aside id="db_aside_mysql" class="not-sqlite not-pgsql">
                    <?php echo __("The collation <code>utf8mb4_general_ci</code> is recommended for your MySQL database."); ?>
                </aside>
                <aside id="db_aside_sqlite" class="not-mysql not-pgsql">
                    <?php echo __("Be sure to put your SQLite database outside the document root directory, otherwise visitors will be able to download it."); ?>
                </aside>
                <p id="prefix_field">
                    <label for="prefix"><?php echo __("Table Prefix"); ?> <span class="sub"><?php echo __("(optional)"); ?></span></label>
                    <input type="text" name="prefix" value="<?php posted("prefix"); ?>" id="prefix">
                </p>
                <h1><?php echo __("Website Setup"); ?></h1>
                <p id="url_field">
                    <label for="url"><?php echo __("Chyrp URL"); ?></label>
                    <input type="url" name="url" value="<?php posted("url", $url); ?>" id="url">
                </p>
                <p id="name_field">
                    <label for="name"><?php echo __("Site Name"); ?></label>
                    <input type="text" name="name" value="<?php posted("name", __("My Awesome Site")); ?>" id="name">
                </p>
                <p id="description_field">
                    <label for="description"><?php echo __("Description"); ?></label>
                    <input type="text" name="description" value="<?php posted("description"); ?>" id="description">
                </p>
                <p id="timezone_field">
                    <label for="timezone"><?php echo __("Time Zone"); ?></label>
                    <select name="timezone" id="timezone">
                    <?php foreach (timezones() as $timezones): ?>
                        <option value="<?php echo $timezones['code']; ?>"<?php selected($timezones['code'], $timezone); ?>>
                            <?php echo $timezones['name']; ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
                </p>
                <p id="locale_field">
                    <label for="locale"><?php echo __("Language"); ?></label>
                    <select name="locale" id="locale">
                        <?php foreach (locales() as $locales): ?>
                            <option value="<?php echo $locales['code']; ?>"<?php selected($locales['code'], $locale); ?>>
                                <?php echo $locales['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <h1><?php echo __("Admin Account"); ?></h1>
                <p id="login_field">
                    <label for="login"><?php echo __("Username"); ?></label>
                    <input type="text" name="login" value="<?php posted("login", "Admin"); ?>" id="login" maxlength="64">
                </p>
                <p id="password1_field">
                    <label for="password1"><?php echo __("Password"); ?></label>
                    <input type="password" name="password1" value="<?php posted("password1"); ?>" id="password1" maxlength="128">
                </p>
                <p id="password2_field">
                    <label for="password2"><?php echo __("Password"); ?> <span class="sub"><?php echo __("(again)"); ?></span></label>
                    <input type="password" name="password2" value="<?php posted("password2"); ?>" id="password2" maxlength="128">
                </p>
                <p id="email_field">
                    <label for="email"><?php echo __("Email Address"); ?></label>
                    <input type="email" name="email" value="<?php posted("email"); ?>" id="email" maxlength="128">
                </p>
                <button type="submit" name="install" value="yes"><?php echo __("Install me!"); ?></button>
            </form>
<?php else: ?>
            <h1><?php echo __("Installation Complete"); ?></h1>
            <h2><?php echo __("What now?"); ?></h2>
            <ol>
                <li><?php echo __("Delete <em>install.php</em>, you won't need it anymore."); ?></li>
                <li><?php echo __("Log in to your site and configure things to your liking."); ?></a></li>
            </ol>
            <hr>
            <a class="big" href="<?php echo $config->url.'/'; ?>"><?php echo __("Take me to my site!"); ?></a>
<?php endif; ?>
        </div>
    </body>
</html>
