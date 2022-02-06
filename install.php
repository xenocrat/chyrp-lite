<?php
    /**
     * File: install
     * Creates the SQL tables and builds the site configuration.
     */

    header("Content-Type: text/html; charset=UTF-8");

    define('DEBUG',            true);
    define('CHYRP_VERSION',    "2022.02");
    define('CHYRP_CODENAME',   "Coal");
    define('CHYRP_IDENTITY',   "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");
    define('MAIN',             false);
    define('ADMIN',            false);
    define('AJAX',             false);
    define('XML_RPC',          false);
    define('UPGRADING',        false);
    define('INSTALLING',       true);
    define('DIR',              DIRECTORY_SEPARATOR);
    define('MAIN_DIR',         dirname(__FILE__));
    define('INCLUDES_DIR',     MAIN_DIR.DIR."includes");
    define('CACHES_DIR',       INCLUDES_DIR.DIR."caches");
    define('CACHE_TWIG',       false);
    define('CACHE_THUMBS',     false);
    define('USE_GETTEXT_SHIM', stripos(PHP_OS, "Win") === 0);
    define('USE_OB',           true);
    define('CAN_USE_ZLIB',     false);
    define('USE_ZLIB',         false);

    if (version_compare(PHP_VERSION, "7.4", "<"))
        exit("Chyrp Lite requires PHP 7.4 or greater. Installation cannot continue.");

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
        alert(__("PDO is required for database access."));

    # Test if we can write to MAIN_DIR (needed for the .htaccess file).
    if (!is_writable(MAIN_DIR))
        alert(__("Please CHMOD or CHOWN the installation directory to make it writable."));

    # Test if we can write to INCLUDES_DIR (needed for config.json.php).
    if (!is_writable(INCLUDES_DIR))
        alert(__("Please CHMOD or CHOWN the <em>includes</em> directory to make it writable."));

    # Test if we can write to CACHES_DIR (needed by some extensions).
    if (!is_writable(CACHES_DIR))
        alert(__("Please CHMOD or CHOWN the <em>caches</em> directory to make it writable."));

    /**
     * Function: alert
     * Logs an alert message and returns the log to date.
     */
    function alert($message = null) {
        static $log = array();

        if (isset($message))
            $log[] = (string) $message;

        return empty($log) ? null : $log ;
    }

    /**
     * Function: guess_url
     * Returns a best guess of the current URL.
     */
    function guess_url() {
        $scheme = (!empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== "off") ? "https" : "http" ;
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'] ;

        return $scheme."://".$host.$_SERVER['REQUEST_URI'];
    }

    /**
     * Function: posted
     * Echoes a $_POST value if set, otherwise echoes the fallback value.
     *
     * Parameters:
     *     $index - The named index to test in the $_POST array.
     *     $fallback - The value to echo if the $_POST value is not set.
     */
    function posted($index, $fallback = "") {
        echo fix(isset($_POST[$index]) ? $_POST[$index] : $fallback, true);
    }

    /**
     * Function: selected
     * Echoes " selected" HTML attribute if the supplied values are equal.
     *
     * Parameters:
     *     $val1 - Compare this value...
     *     $val2 - ... with this value.
     */
    function selected($val1, $val2) {
        if ($val1 == $val2)
                echo " selected";
    }

    #---------------------------------------------
    # Output Starts
    #---------------------------------------------
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo __("Chyrp Lite Installer"); ?></title>
        <meta name="viewport" content="width = 800">
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
                background-color: #ff7f00;
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
                padding: 2rem;
            }
            h1 {
                font-size: 2em;
                font-weight: bold;
                margin: 1rem 0rem;
                text-align: center;
                line-height: 1;
            }
            h1:first-child {
                margin-top: 0em;
            }
            h2 {
                font-size: 1.5em;
                text-align: center;
                font-weight: bold;
                margin: 1rem 0rem;
            }
            h3 {
                font-size: 1em;
                font-weight: bold;
                margin: 1rem 0rem;
                border-bottom: 1px solid #cfcfcf;
            }
            p {
                margin-bottom: 1rem;
            }
            p:last-child,
            p:empty {
                margin-bottom: 0rem;
            }
            code {
                font-family: "Hack webfont", monospace;
                font-style: normal;
                font-size: 0.8rem;
                word-wrap: break-word;
                background-color: #efefef;
                padding: 0px 2px;
                color: #4f4f4f;
                border: 1px solid #cfcfcf;
            }
            strong {
                font-weight: normal;
                color: #d94c4c;
            }
            ul, ol {
                margin: 0rem 0rem 2rem 2rem;
                list-style-position: outside;
            }
            li {
                margin-bottom: 1rem;
            }
            label {
                display: block;
                font-weight: bold;
                line-height: 1.5;
            }
            input, textarea, select {
                font-family: inherit;
                font-size: inherit;
                font-weight: inherit;
            }
            input[type="text"],
            input[type="email"],
            input[type="url"],
            input[type="number"],
            input[type="password"],
            textarea,
            select {
                box-sizing: border-box;
                width: 100%;
                margin: 0rem;
                font-size: 1.25em;
                padding: 0.2em;
                border-radius: 0em;
                border: 1px solid #cfcfcf;
                background-color: #ffffff;
            }
            input[type="text"]:focus,
            input[type="email"]:focus,
            input[type="url"]:focus,
            input[type="number"]:focus,
            input[type="password"]:focus,
            textarea:focus,
            select:focus {
                border-color: #1e57ba;
                outline: none;
            }
            input[type="text"].error,
            input[type="email"].error,
            input[type="url"].error,
            input[type="number"].error,
            input[type="password"].error,
            input:invalid,
            textarea.error {
                background-color: #faebe4;
                box-shadow: none;
            }
            input[type="text"].error:focus,
            input[type="email"].error:focus,
            input[type="url"].error:focus,
            input[type="number"].error:focus,
            input[type="password"].error:focus,
            input:invalid:focus,
            textarea.error:focus {
                border: 1px solid #d51800;
            }
            input[type="password"].strong {
                background-color: #ebfae4;
            }
            input[type="password"].strong:focus {
                border: 1px solid #108600;
            }
            form hr {
                border: none;
                clear: both;
                border-top: 1px solid #cfcfcf;
                margin: 2rem 0rem;
            }
            form p {
                padding-bottom: 1rem;
            }
            pre.pane {
                height: 15rem;
                overflow: auto;
                margin: 1rem -2rem 1rem -2rem;
                padding: 2rem;
                background: #4a4747;
                color: #ffffff;
            }
            pre.pane:empty {
                display: none;
            }
            pre.pane:empty + h1 {
                margin-top: 0rem;
            }
            a:link,
            a:visited {
                color: #4a4747;
                text-decoration: underline;
            }
            a:focus {
                outline: #ff7f00 dashed 2px;
            }
            a:hover,
            a:focus,
            a:active {
                color: #2f61c4;
                text-decoration: underline;
            }
            pre.pane a {
                color: #ffffff;
                font-weight: bold;
                font-style: italic;
                text-decoration: none;
            }
            pre.pane a:hover,
            pre.pane a:focus,
            pre.pane a:active {
                text-decoration: underline;
            }
            a.big,
            button {
                box-sizing: border-box;
                display: block;
                clear: both;
                font-size: 1.25em;
                text-align: center;
                color: #4a4747;
                text-decoration: none;
                line-height: 1.25;
                margin: 1rem 0rem;
                padding: 0.4em 0.6em;
                background-color: #f2fbff;
                border: 2px solid #b8cdd9;
                border-radius: 0.3em;
                cursor: pointer;
            }
            button {
                width: 100%;
            }
            a.big:last-child,
            button:last-child {
                margin-bottom: 0rem;
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
            aside {
                margin-bottom: 1rem;
                padding: 0.5em 1em;
                border: 1px solid #e5d7a1;
                border-radius: 0.25em;
                background-color: #fffecd;
            }
            .window {
                width: 30rem;
                background: #ffffff;
                padding: 2rem;
                margin: 0rem auto 0rem auto;
                border-radius: 2rem;
            }
            .sub {
                font-size: 0.8em;
                font-weight: normal;
            }
        </style>
        <script src="includes/common.js" type="text/javascript" charset="UTF-8"></script>
        <script type="text/javascript">
            'use strict';

            function toggle_adapter() {
                var adapter = $("#adapter").val();

                if (adapter == "sqlite") {
                    $("#database_sub").fadeIn("fast");
                    $("#host_field, #username_field, #password_field, #prefix_field").fadeOut("fast");
                } else {
                    $("#database_sub").fadeOut("fast");
                    $("#host_field, #username_field, #password_field, #prefix_field").fadeIn("fast"); 
                }

                if (adapter == "sqlite") {
                    $("#db_aside_sqlite").fadeIn("fast");
                    $("#db_aside_mysql, #db_aside_pgsql").fadeOut("fast");
                } else if (adapter == "mysql") {
                    $("#db_aside_mysql").fadeIn("fast");
                    $("#db_aside_sqlite, #db_aside_pgsql").fadeOut("fast");
                } else if (adapter == "pgsql") {
                    $("#db_aside_pgsql").fadeIn("fast");
                    $("#db_aside_mysql, #db_aside_sqlite").fadeOut("fast");
                } else {
                    $("#db_aside_mysql, #db_aside_pgsql, #db_aside_sqlite").fadeOut("fast");
                }
            }
            $(function() {
                $("#adapter").change(toggle_adapter).trigger("change");

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
            <pre role="status" class="pane"><?php

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
                array("host"     => "",
                      "username" => "",
                      "password" => "",
                      "database" => $_POST['database'],
                      "prefix"   => "",
                      "adapter"  => $_POST['adapter']) :
                array("host"     => $_POST['host'],
                      "username" => $_POST['username'],
                      "password" => $_POST['password'],
                      "database" => $_POST['database'],
                      "prefix"   => $_POST['prefix'],
                      "adapter"  => $_POST['adapter']) ;

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
            $sql->create("posts",
                         array("id INTEGER PRIMARY KEY AUTO_INCREMENT",
                               "feather VARCHAR(32) DEFAULT ''",
                               "clean VARCHAR(128) DEFAULT ''",
                               "url VARCHAR(128) DEFAULT ''",
                               "pinned BOOLEAN DEFAULT FALSE",
                               "status VARCHAR(32) DEFAULT 'public'",
                               "user_id INTEGER DEFAULT 0",
                               "created_at DATETIME DEFAULT NULL",
                               "updated_at DATETIME DEFAULT NULL"));

            # Post attributes table.
            $sql->create("post_attributes",
                         array("post_id INTEGER NOT NULL",
                               "name VARCHAR(100) DEFAULT ''",
                               "value LONGTEXT",
                               "PRIMARY KEY (post_id, name)"));

            # Pages table.
            $sql->create("pages",
                         array("id INTEGER PRIMARY KEY AUTO_INCREMENT",
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
                               "updated_at DATETIME DEFAULT NULL"));

            # Users table.
            $sql->create("users",
                         array("id INTEGER PRIMARY KEY AUTO_INCREMENT",
                               "login VARCHAR(64) DEFAULT ''",
                               "password VARCHAR(128) DEFAULT ''",
                               "full_name VARCHAR(250) DEFAULT ''",
                               "email VARCHAR(128) DEFAULT ''",
                               "website VARCHAR(128) DEFAULT ''",
                               "group_id INTEGER DEFAULT 0",
                               "approved BOOLEAN DEFAULT '1'",
                               "joined_at DATETIME DEFAULT NULL",
                               "UNIQUE (login)"));

            # Groups table.
            $sql->create("groups",
                         array("id INTEGER PRIMARY KEY AUTO_INCREMENT",
                               "name VARCHAR(100) DEFAULT ''",
                               "UNIQUE (name)"));

            # Permissions table.
            $sql->create("permissions",
                         array("id VARCHAR(100) DEFAULT ''",
                               "name VARCHAR(100) DEFAULT ''",
                               "group_id INTEGER DEFAULT 0",
                               "PRIMARY KEY (id, group_id)"));

            # Sessions table.
            $sql->create("sessions",
                         array("id VARCHAR(40) DEFAULT ''",
                               "data LONGTEXT",
                               "user_id INTEGER DEFAULT 0",
                               "created_at DATETIME DEFAULT NULL",
                               "updated_at DATETIME DEFAULT NULL",
                               "PRIMARY KEY (id)"));

            # Add the default permissions.
            $names = array("change_settings" => "Change Settings",
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
                           "export_content" => "Export Content");

            foreach ($names as $id => $name)
                $sql->replace("permissions",
                              array("id", "group_id"),
                              array("id" => $id,
                                    "name" => $name,
                                    "group_id" => 0));

            $groups = array("admin"  => array_keys($names),
                            "member" => array("view_site"),
                            "friend" => array("view_site", "view_private", "view_scheduled"),
                            "banned" => array(),
                            "guest"  => array("view_site"));

            # Add the default groups.
            $group_id = array();

            foreach ($groups as $name => $permissions) {
                $sql->replace("groups", "name", array("name" => ucfirst($name)));

                $group_id[$name] = $sql->latest("groups");

                foreach ($permissions as $permission)
                    $sql->replace("permissions",
                                  array("id", "group_id"),
                                  array("id" => $permission,
                                        "name" => $names[$permission],
                                        "group_id" => $group_id[$name]));
            }

            # Normalize the Chyrp URL.
            $chyrp_url = rtrim(add_scheme($_POST['url']), "/");

            # Add the admin user account.
            if (!$sql->select("users", "id", array("login" => $_POST['login']))->fetchColumn())
                $sql->insert("users",
                             array("login" => $_POST['login'],
                                   "password" => User::hashPassword($_POST['password1']),
                                   "email" => $_POST['email'],
                                   "group_id" => $group_id["admin"],
                                   "approved" => true,
                                   "joined_at" => datetime()));

            # Build the configuration file.
            $set = array($config->set("sql", $settings),
                         $config->set("name", strip_tags($_POST['name'])),
                         $config->set("description", strip_tags($_POST['description'])),
                         $config->set("url", $chyrp_url),
                         $config->set("chyrp_url", $chyrp_url),
                         $config->set("email", $_POST['email']),
                         $config->set("timezone", $_POST['timezone']),
                         $config->set("locale", $_POST['locale']),
                         $config->set("check_updates", true),
                         $config->set("check_updates_last", 0),
                         $config->set("theme", "blossom"),
                         $config->set("posts_per_page", 5),
                         $config->set("admin_per_page", 25),
                         $config->set("feed_format", "AtomFeed"),
                         $config->set("feed_items", 20),
                         $config->set("uploads_path", DIR."uploads".DIR),
                         $config->set("uploads_limit", 10),
                         $config->set("search_pages", false),
                         $config->set("send_pingbacks", false),
                         $config->set("enable_xmlrpc", true),
                         $config->set("enable_emoji", true),
                         $config->set("enable_markdown", true),
                         $config->set("can_register", false),
                         $config->set("email_activation", false),
                         $config->set("email_correspondence", true),
                         $config->set("default_group", $group_id["member"]),
                         $config->set("guest_group", $group_id["guest"]),
                         $config->set("clean_urls", false),
                         $config->set("enable_homepage", false),
                         $config->set("post_url", "(year)/(month)/(day)/(url)/"),
                         $config->set("enabled_modules", array()),
                         $config->set("enabled_feathers", array("text")),
                         $config->set("routes", array()),
                         $config->set("secure_hashkey", random(32)));

            if (in_array(false, $set, true))
                error(__("Error"), __("Could not write the configuration file."));

            @unlink(INCLUDES_DIR.DIR."upgrading.lock");
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
                <p id="host_field">
                    <label for="host"><?php echo __("Host"); ?></label>
                    <input type="text" name="host" value="<?php posted("host", (isset($_ENV['DATABASE_SERVER']) ? $_ENV['DATABASE_SERVER'] : "localhost")); ?>" id="host">
                </p>
                <p id="username_field">
                    <label for="username"><?php echo __("Username"); ?></label>
                    <input type="text" name="username" value="<?php posted("username"); ?>" id="username">
                </p>
                <p id="password_field">
                    <label for="password"><?php echo __("Password"); ?></label>
                    <input type="password" name="password" value="<?php posted("password"); ?>" id="password">
                </p>
                <p id="database_field">
                    <label for="database"><?php echo __("Database"); ?>
                        <span id="database_sub" class="sub">
                            <?php echo __("(absolute or relative path)"); ?>
                        </span>
                    </label>
                    <input type="text" name="database" value="<?php posted("database"); ?>" id="database">
                </p>
                <aside id="db_aside_pgsql">
                    <?php echo __("Make sure your PostgreSQL database uses UTF-8 encoding."); ?>
                </aside>
                <aside id="db_aside_mysql">
                    <?php echo __("The collation <code>utf8mb4_general_ci</code> is recommended for your MySQL database."); ?>
                </aside>
                <aside id="db_aside_sqlite">
                    <?php echo __("Be sure to put your SQLite database outside the document root directory, otherwise visitors will be able to download it."); ?>
                </aside>
                <p id="prefix_field">
                    <label for="prefix"><?php echo __("Table Prefix"); ?> <span class="sub"><?php echo __("(optional)"); ?></span></label>
                    <input type="text" name="prefix" value="<?php posted("prefix"); ?>" id="prefix">
                </p>
                <hr>
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
                <hr>
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
            <a class="big" href="<?php echo $config->url.'/'; ?>"><?php echo __("Take me to my site!"); ?></a>
<?php endif; ?>
        </div>
    </body>
</html>
