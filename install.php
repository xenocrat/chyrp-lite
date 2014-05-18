<?php
    header("Content-type: text/html; charset=UTF-8");

    define('DEBUG',        true);
    define('JAVASCRIPT',   false);
    define('ADMIN',        false);
    define('AJAX',         false);
    define('XML_RPC',      false);
    define('TRACKBACK',    false);
    define('UPGRADING',    false);
    define('INSTALLING',   true);
    define('TESTER',       isset($_SERVER['HTTP_USER_AGENT']) and $_SERVER['HTTP_USER_AGENT'] == "tester.rb");
    define('MAIN_DIR',     dirname(__FILE__));
    define('INCLUDES_DIR', MAIN_DIR."/includes");
    define('USE_ZLIB',     false);

    # Make sure E_STRICT is on so Chyrp remains errorless.
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', true);

    ob_start();

    if (version_compare(PHP_VERSION, "5.3.0", "<"))
        exit("Chyrp requires PHP 5.3.0 or greater. Installation cannot continue.");

    require_once INCLUDES_DIR."/helpers.php";

    require_once INCLUDES_DIR."/lib/gettext/gettext.php";
    require_once INCLUDES_DIR."/lib/gettext/streams.php";
    require_once INCLUDES_DIR."/lib/YAML.php";
    require_once INCLUDES_DIR."/lib/PasswordHash.php";

    require_once INCLUDES_DIR."/class/Config.php";
    require_once INCLUDES_DIR."/class/SQL.php";
    require_once INCLUDES_DIR."/class/Model.php";

    require_once INCLUDES_DIR."/model/User.php";

    # Prepare the Config interface.
    $config = Config::current();

    # Atlantic/Reykjavik is 0 offset. Set it so the timezones() function is
    # always accurate, even if the server has its own timezone settings.
    $default_timezone = oneof(ini_get("date.timezone"), "Atlantic/Reykjavik");
    set_timezone($default_timezone);

    # Sanitize all input depending on magic_quotes_gpc's enabled status.
    sanitize_input($_GET);
    sanitize_input($_POST);
    sanitize_input($_COOKIE);
    sanitize_input($_REQUEST);

    $protocol = (!empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== "off" or $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://" ;
    $url = $protocol.$_SERVER['HTTP_HOST'].str_replace("/install.php", "", $_SERVER['REQUEST_URI']);
    $index = (parse_url($url, PHP_URL_PATH)) ? "/".trim(parse_url($url, PHP_URL_PATH), "/")."/" : "/" ;
    $htaccess = "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase {$index}\nRewriteCond %{REQUEST_FILENAME} !-f\n".
                "RewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^.+$ index.php [L]\n</IfModule>";

    $path = preg_quote($index, "/");
    $htaccess_has_chyrp = (file_exists(MAIN_DIR."/.htaccess") and
                           preg_match("/<IfModule mod_rewrite\.c>\n([\s]*)RewriteEngine On\n([\s]*)RewriteBase {$path}\n".
                                      "([\s]*)RewriteCond %\{REQUEST_FILENAME\} !-f\n([\s]*)RewriteCond %\{REQUEST_FILENAME\}".
                                      " !-d\n([\s]*)RewriteRule \^\.\+\\$ index\.php \[L\]\n([\s]*)<\/IfModule>/",
                                      file_get_contents(MAIN_DIR."/.htaccess")));

    $errors = array();
    $installed = false;

    if (file_exists(INCLUDES_DIR."/config.yaml.php") and file_exists(MAIN_DIR."/.htaccess")) {
        $sql = SQL::current(true);
        if ($sql->connect(true) and !empty($config->url) and $sql->count("users"))
            error(__("Already Installed"), __("Chyrp is already correctly installed and configured."));
    }

    if ((!is_writable(MAIN_DIR) and !file_exists(MAIN_DIR."/.htaccess")) or
        (file_exists(MAIN_DIR."/.htaccess") and !is_writable(MAIN_DIR."/.htaccess") and !$htaccess_has_chyrp))
        $errors[] = _f("STOP! Before you go any further, you must create a .htaccess file in Chyrp's install directory and put this in it:\n<pre>%s</pre>", array(fix($htaccess)));

    if (!is_writable(INCLUDES_DIR))
        $errors[] = __("Chyrp's includes directory is not writable by the server. In order for the installer to generate your configuration files, please CHMOD or CHOWN it so that Chyrp can write to it.");

    if (!empty($_POST)) {
        if ($_POST['adapter'] == "sqlite" and !@is_writable(dirname($_POST['database'])))
            $errors[] = __("SQLite database file could not be created. Please make sure your server has write permissions to the location for the database.");
        else {
            $sql = SQL::current(array("host"     => $_POST['host'],
                                      "username" => $_POST['username'],
                                      "password" => $_POST['password'],
                                      "database" => $_POST['database'],
                                      "prefix"   => $_POST['prefix'],
                                      "adapter"  => $_POST['adapter']));

            if (!$sql->connect(true))
                $errors[] = _f("Could not connect to the specified database:\n<pre>%s</pre>", array($sql->error));
            elseif ($_POST['adapter'] == "pgsql") {
                new Query($sql, "CREATE FUNCTION year(timestamp) RETURNS double precision AS 'select extract(year from $1);' LANGUAGE SQL IMMUTABLE RETURNS NULL ON NULL INPUT");
                new Query($sql, "CREATE FUNCTION month(timestamp) RETURNS double precision AS 'select extract(month from $1);' LANGUAGE SQL IMMUTABLE RETURNS NULL ON NULL INPUT");
                new Query($sql, "CREATE FUNCTION day(timestamp) RETURNS double precision AS 'select extract(day from $1);' LANGUAGE SQL IMMUTABLE RETURNS NULL ON NULL INPUT");
                new Query($sql, "CREATE FUNCTION hour(timestamp) RETURNS double precision AS 'select extract(hour from $1);' LANGUAGE SQL IMMUTABLE RETURNS NULL ON NULL INPUT");
                new Query($sql, "CREATE FUNCTION minute(timestamp) RETURNS double precision AS 'select extract(minute from $1);' LANGUAGE SQL IMMUTABLE RETURNS NULL ON NULL INPUT");
                new Query($sql, "CREATE FUNCTION second(timestamp) RETURNS double precision AS 'select extract(second from $1);' LANGUAGE SQL IMMUTABLE RETURNS NULL ON NULL INPUT");
            }
        }

        if (empty($_POST['name']))
            $errors[] = __("Please enter a name for your website.");

        if (!isset($_POST['timezone']))
            $errors[] = __("Time zone cannot be blank.");

        if (empty($_POST['login']))
            $errors[] = __("Please enter a username for your account.");

        if (empty($_POST['password_1']))
            $errors[] = __("Password cannot be blank.");

        if ($_POST['password_1'] != $_POST['password_2'])
            $errors[] = __("Passwords do not match.");

        if (empty($_POST['email']))
            $errors[] = __("E-Mail address cannot be blank.");

        if (empty($errors)) {

            if (!$htaccess_has_chyrp)
                if (!file_exists(MAIN_DIR."/.htaccess")) {
                    if (!@file_put_contents(MAIN_DIR."/.htaccess", $htaccess))
                        $errors[] = _f("Could not generate .htaccess file. Clean URLs will not be available unless you create it and put this in it:\n<pre>%s</pre>", array(fix($htaccess)));
                } elseif (!@file_put_contents(MAIN_DIR."/.htaccess", "\n\n".$htaccess, FILE_APPEND)) {
                    $errors[] = _f("Could not generate .htaccess file. Clean URLs will not be available unless you create it and put this in it:\n<pre>%s</pre>", array(fix($htaccess)));
                }

            $config->set("sql", array());
            $config->set("name", $_POST['name']);
            $config->set("description", $_POST['description']);
            $config->set("url", $url);
            $config->set("chyrp_url", $url);
            $config->set("email", $_POST['email']);
            $config->set("timezone", $_POST['timezone']);
            $config->set("locale", "en_US");
            $config->set("check_updates", true);
            $config->set("theme", "blossom");
            $config->set("admin_theme", "default");
            $config->set("posts_per_page", 5);
            $config->set("feed_items", 20);
            $config->set("feed_url", "");
            $config->set("uploads_path", "/uploads/");
            $config->set("enable_trackbacking", true);
            $config->set("send_pingbacks", false);
            $config->set("enable_xmlrpc", true);
            $config->set("enable_ajax", true);
            $config->set("enable_emoji", true);
            $config->set("can_register", false);
            $config->set("email_activation", false);
            $config->set("enable_recaptcha", false);
            $config->set("default_group", 0);
            $config->set("guest_group", 0);
            $config->set("clean_urls", false);
            $config->set("post_url", "(year)/(month)/(day)/(url)/");
            $config->set("enabled_modules", array());
            $config->set("enabled_feathers", array("text"));
            $config->set("routes", array());
            $config->set("secure_hashkey", md5(random(32, true)));

            foreach (array("host", "username", "password", "database", "prefix", "adapter") as $field)
                $sql->set($field, $_POST[$field], true);

            if ($sql->adapter == "mysql" and class_exists("MySQLi"))
                $sql->method = "mysqli";
            elseif ($sql->adapter == "mysql" and function_exists("mysql_connect"))
                $sql->method = "mysql";
            elseif ($sql->adapter == "sqlite" and in_array("sqlite", PDO::getAvailableDrivers()))
                $sql->method = "pdo";

            $sql->connect();

            # Posts table
            $sql->query("CREATE TABLE IF NOT EXISTS __posts (
                             id INTEGER PRIMARY KEY AUTO_INCREMENT,
                             feather VARCHAR(32) DEFAULT '',
                             clean VARCHAR(128) DEFAULT '',
                             url VARCHAR(128) DEFAULT '',
                             pinned BOOLEAN DEFAULT FALSE,
                             status VARCHAR(32) DEFAULT 'public',
                             user_id INTEGER DEFAULT 0,
                             created_at DATETIME DEFAULT NULL,
                             updated_at DATETIME DEFAULT NULL
                         ) DEFAULT CHARSET=utf8");

            # Post attributes table.
            $sql->query("CREATE TABLE IF NOT EXISTS __post_attributes (
                             post_id INTEGER NOT NULL ,
                             name VARCHAR(100) DEFAULT '',
                             value LONGTEXT,
                             PRIMARY KEY (post_id, name)
                         ) DEFAULT CHARSET=utf8");

            # Pages table
            $sql->query("CREATE TABLE IF NOT EXISTS __pages (
                             id INTEGER PRIMARY KEY AUTO_INCREMENT,
                             title VARCHAR(250) DEFAULT '',
                             body LONGTEXT,
                             show_in_list BOOLEAN DEFAULT '1',
                             list_order INTEGER DEFAULT 0,
                             clean VARCHAR(128) DEFAULT '',
                             url VARCHAR(128) DEFAULT '',
                             user_id INTEGER DEFAULT 0,
                             parent_id INTEGER DEFAULT 0,
                             created_at DATETIME DEFAULT NULL,
                             updated_at DATETIME DEFAULT NULL
                         ) DEFAULT CHARSET=utf8");

            # Users table
            $sql->query("CREATE TABLE IF NOT EXISTS __users (
                             id INTEGER PRIMARY KEY AUTO_INCREMENT,
                             login VARCHAR(64) DEFAULT '',
                             password VARCHAR(60) DEFAULT '',
                             full_name VARCHAR(250) DEFAULT '',
                             email VARCHAR(128) DEFAULT '',
                             website VARCHAR(128) DEFAULT '',
                             group_id INTEGER DEFAULT 0,
                             approved BOOLEAN DEFAULT '1',
                             joined_at DATETIME DEFAULT NULL,
                             UNIQUE (login)
                         ) DEFAULT CHARSET=utf8");

            # Groups table
            $sql->query("CREATE TABLE IF NOT EXISTS __groups (
                             id INTEGER PRIMARY KEY AUTO_INCREMENT,
                             name VARCHAR(100) DEFAULT '',
                             UNIQUE (name)
                         ) DEFAULT CHARSET=utf8");

            # Permissions table
            $sql->query("CREATE TABLE IF NOT EXISTS __permissions (
                             id VARCHAR(100) DEFAULT '',
                             name VARCHAR(100) DEFAULT '',
                             group_id INTEGER DEFAULT 0,
                             PRIMARY KEY (id, group_id)
                         ) DEFAULT CHARSET=utf8");

            # Sessions table
            $sql->query("CREATE TABLE IF NOT EXISTS __sessions (
                             id VARCHAR(40) DEFAULT '',
                             data LONGTEXT,
                             user_id INTEGER DEFAULT 0,
                             created_at DATETIME DEFAULT NULL,
                             updated_at DATETIME DEFAULT NULL,
                             PRIMARY KEY (id)
                         ) DEFAULT CHARSET=utf8");

            # This is to let the gettext scanner add these strings to the .pot file.
            # They are translated on display via Twig.
            # We don't want translated strings in the database.
            /* $translations = array(__("Change Settings"),
                                     __("Toggle Extensions"),
                                     __("View Site"),
                                     __("View Private Posts"),
                                     __("View Scheduled Posts"),
                                     __("View Drafts"),
                                     __("View Own Drafts"),
                                     __("Add Posts"),
                                     __("Add Drafts"),
                                     __("Edit Posts"),
                                     __("Edit Drafts"),
                                     __("Edit Own Posts"),
                                     __("Edit Own Drafts"),
                                     __("Delete Posts"),
                                     __("Delete Drafts"),
                                     __("Delete Own Posts"),
                                     __("Delete Own Drafts"),
                                     __("Add Pages"),
                                     __("Edit Pages"),
                                     __("Delete Pages"),
                                     __("Add Users"),
                                     __("Edit Users"),
                                     __("Delete Users"),
                                     __("Add Groups"),
                                     __("Edit Groups"),
                                     __("Delete Groups")); */

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
                           "add_page" => "Add Pages",
                           "edit_page" => "Edit Pages",
                           "delete_page" => "Delete Pages",
                           "add_user" => "Add Users",
                           "edit_user" => "Edit Users",
                           "delete_user" => "Delete Users",
                           "add_group" => "Add Groups",
                           "edit_group" => "Edit Groups",
                           "delete_group" => "Delete Groups");

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

            # Insert the default groups (see above)
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

            $config->set("default_group", $group_id["member"]);
            $config->set("guest_group", $group_id["guest"]);

            if (!$sql->select("users", "id", array("login" => $_POST['login']))->fetchColumn())
                $sql->insert("users",
                             array("login" => $_POST['login'],
                                   "password" => User::hashPassword($_POST['password_1']),
                                   "email" => $_POST['email'],
                                   "website" => $config->url,
                                   "group_id" => $group_id["admin"],
                                   "approved" => true,
                                   "joined_at" => datetime()));

            $installed = true;
        }
    }

    function value_fallback($index, $fallback = "") {
        echo (isset($_POST[$index])) ? fix($_POST[$index]) : $fallback ;
    }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
        <title>Chyrp Installer</title>
        <style type="text/css" media="screen">
            html, body, ul, ol, li,
            h1, h2, h3, h4, h5, h6,
            form, fieldset, a, p {
                margin: 0;
                padding: 0;
                border: 0;
            }
            html {
                font-size: 62.5%;
            }
            body {
                font: 1.25em/1.5em normal Verdana, Helvetica, Arial, sans-serif;
                color: #626262;
                background: #e8e8e8;
                padding: 0 0 5em;
            }
            h2 {
                font-size: 1.25em;
                font-weight: bold;
            }
            input[type="password"], input[type="text"], textarea, select {
                font-size: 1.25em;
                width: 23.3em;
                padding: .3em;
                border: .1em solid #ddd;
            }
            textarea {
                width: 97.75%;
            }
            select {
                width: 100%;
            }
            form hr {
                border: 0;
                padding-bottom: 1em;
                margin-bottom: 4em;
                border-bottom: .1em dashed #ddd;
            }
            form p {
                padding-bottom: 1em;
            }
            .sub {
                font-size: .8em;
                color: #777;
                font-weight: normal;
            }
            .sub.inline {
                float: left;
                margin-top: -1.5em !important;
            }
            .error {
                padding: .6em .8em .5em 2.75em;
                border-bottom: .1em solid #FBC2C4;
                color: #D12F19;
                background: #FBE3E4 url('./admin/themes/default/images/icons/failure.png') no-repeat .7em center;
            }
            .error.last {
                margin: 0 0 1em 0;
            }
            .window {
                width: 30em;
                background: #fff;
                padding: 2em;
                margin: 5em auto 0;
                -webkit-border-radius: 2em;
                -moz-border-radius: 2em;
            }
            h1 {
                color: #ccc;
                font-size: 3em;
                margin: .25em 0 .5em;
                text-align: center;
                line-height: 1;
            }
            code {
                color: #06B;
                font-family: Monaco, monospace;
            }
            label {
                display: block;
                font-weight: bold;
                border-bottom: .1em dotted #ddd;
                margin-bottom: .2em;
            }
            .footer {
                color: #777;
                margin-top: 1em;
                font-size: .9em;
                text-align: center;
            }
            .error {
                color: #F22;
            }
            a:link, a:visited {
                color: #6B0;
            }
            a.big,
            button {
                background: #eee;
                margin-top: 2em;
                display: block;
                text-align: left;
                padding: .75em 1em;
                color: #777;
                text-shadow: #fff .1em .1em 0;
                font: 1em normal "Lucida Grande", Verdana, Helvetica, Arial, sans-serif;
                text-decoration: none;
                border: 0;
                cursor: pointer;
                -webkit-border-radius: .5em;
                -moz-border-radius: .5em;
            }
            button {
                width: 100%;
            }
            a.big:hover,
            button:hover {
                background: #f5f5f5;
            }
            a.big:active,
            button:active {
                background: #e0e0e0;
            }
            strong {
                font-weight: normal;
                color: #f00;
            }
            ol {
                margin: 0 0 2em 2em;
            }
            p {
                margin-bottom: 1em;
            }
            .center {
                text-align: center;
            }
        </style>
        <script src="includes/lib/jquery.js" type="text/javascript" charset="utf-8"></script>
        <script src="includes/lib/plugins.js" type="text/javascript" charset="utf-8"></script>
        <script type="text/javascript">
            $(function(){
                $("#adapter").change(function(){
                    if ($(this).val() == "sqlite") {
                        $(document.createElement("span"))
                            .addClass("sub")
                            .css("display", "none")
                            .text("<?php echo __("(full path)"); ?>")
                            .appendTo("#database_field label")
                            .animate({ opacity: "show" })

                        $("#host_field, #username_field, #password_field, #prefix_field")
                            .children()
                                .val("")
                                    .closest("div")
                                        .animate({ height: "hide", opacity: "hide" })
                    } else {
                        $("#database_field label .sub")
                            .animate({ opacity: "hide" },
                                function(){ $(this).remove() })

                        $("#host_field, #username_field, #password_field, #prefix_field")
                            .parent()
                                .animate({ height: "show", opacity: "show" })
                    }
                })
            })
        </script>
    </head>
    <body>
        <?php foreach ($errors as $error): ?>
        <div class="error<?php if ($index + 1 == count($errors)) echo " last"; ?>"><?php echo $error; ?></div>
        <?php endforeach; ?>
        <div class="window">
        <?php if (!$installed): ?>
            <form action="install.php" method="post" accept-charset="utf-8">
                <h1><?php echo __("Database Setup"); ?></h1>
                <p id="adapter_field">
                    <label for="adapter"><?php echo __("Adapter"); ?></label>
                    <select name="adapter" id="adapter">
                        <?php if ((class_exists("PDO") and in_array("mysql", PDO::getAvailableDrivers())) or
                                  class_exists("MySQLi") or function_exists("mysql_query")): ?>
                        <option value="mysql"<?php selected("mysql", fallback($_POST['adapter'], "mysql")); ?>>MySQL</option>
                        <?php endif; ?>
                        <?php if (class_exists("PDO") and in_array("sqlite", PDO::getAvailableDrivers())): ?>
                        <option value="sqlite"<?php selected("sqlite", fallback($_POST['adapter'], "mysql")); ?>>SQLite 3</option>
                        <?php endif; ?>
                        <?php if (class_exists("PDO") and in_array("pgsql", PDO::getAvailableDrivers())): ?>
                        <option value="pgsql"<?php selected("pgsql", oneof(@$_POST['adapter'], "mysql")); ?>>PostgreSQL</option>
                        <?php endif; ?>
                    </select>
                </p>
                <div<?php echo (isset($_POST['adapter']) and $_POST['adapter'] == "sqlite") ? ' style="display: none"' : "" ; ?>>
                    <p id="host_field">
                        <label for="host"><?php echo __("Host"); ?> <span class="sub"><?php echo __("(usually ok as \"localhost\")"); ?></span></label>
                        <input type="text" name="host" value="<?php value_fallback("host", ((isset($_ENV['DATABASE_SERVER'])) ? $_ENV['DATABASE_SERVER'] : "localhost")); ?>" id="host" />
                    </p>
                </div>
                <div<?php echo (isset($_POST['adapter']) and $_POST['adapter'] == "sqlite") ? ' style="display: none"' : "" ; ?>>
                    <p id="username_field">
                        <label for="username"><?php echo __("Username"); ?></label>
                        <input type="text" name="username" value="<?php value_fallback("username"); ?>" id="username" />
                    </p>
                </div>
                <div<?php echo (isset($_POST['adapter']) and $_POST['adapter'] == "sqlite") ? ' style="display: none"' : "" ; ?>>
                    <p id="password_field">
                        <label for="password"><?php echo __("Password"); ?></label>
                        <input type="password" name="password" value="<?php value_fallback("password"); ?>" id="password" />
                    </p>
                </div>
                <p id="database_field">
                    <label for="database"><?php echo __("Database"); ?> <?php echo (isset($_POST['adapter']) and $_POST['adapter'] == "sqlite") ? '<span class="sub">'.__("(full path)").'</span>' : "" ; ?></label>
                    <input type="text" name="database" value="<?php value_fallback("database"); ?>" id="database" />
                </p>
                <div<?php echo (isset($_POST['adapter']) and $_POST['adapter'] == "sqlite") ? ' style="display: none"' : "" ; ?>>
                    <p id="prefix_field">
                        <label for="prefix"><?php echo __("Table Prefix"); ?> <span class="sub"><?php echo __("(optional)"); ?></span></label>
                        <input type="text" name="prefix" value="<?php value_fallback("prefix"); ?>" id="prefix" />
                    </p>
                </div>

                <hr />

                <h1><?php echo __("Website Setup"); ?></h1>
                <p id="name_field">
                    <label for="name"><?php echo __("Site Name"); ?></label>
                    <input type="text" name="name" value="<?php value_fallback("name", __("My Awesome Site")); ?>" id="name" />
                </p>
                <p id="description_field">
                    <label for="description"><?php echo __("Description"); ?></label>
                    <textarea name="description" rows="2" cols="40"><?php value_fallback("description"); ?></textarea>
                </p>
                <p id="timezone_field">
                    <label for="timezone"><?php echo __("What time is it?"); ?></label>
                    <select name="timezone" id="timezone">
                    <?php foreach (timezones() as $zone): ?>
                        <option value="<?php echo $zone["name"]; ?>"<?php selected($zone["name"], oneof(@$_POST['timezone'], $default_timezone)); ?>>
                            <?php echo strftime("%I:%M %p on %B %d, %Y", $zone["now"]); ?> &mdash;
                            <?php echo str_replace(array("_", "St "), array(" ", "St. "), $zone["name"]); ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
                </p>

                <hr />

                <h1><?php echo __("Admin Account"); ?></h1>
                <p id="login_field">
                    <label for="login"><?php echo __("Username"); ?></label>
                    <input type="text" name="login" value="<?php value_fallback("login", "Admin"); ?>" id="login" />
                </p>
                <p id="password_1_field">
                    <label for="password_1"><?php echo __("Password"); ?></label>
                    <input type="password" name="password_1" value="<?php value_fallback("password_1"); ?>" id="password_1" />
                </p>
                <p id="password_2_field">
                    <label for="password_2"><?php echo __("Password"); ?> <span class="sub"><?php echo __("(again)"); ?></span></label>
                    <input type="password" name="password_2" value="<?php value_fallback("password_2"); ?>" id="password_2" />
                </p>
                <p id="email_field">
                    <label for="email"><?php echo __("E-Mail Address"); ?></label>
                    <input type="text" name="email" value="<?php value_fallback("email"); ?>" id="email" />
                </p>

                <button type="submit"><?php echo __("Install! &rarr;"); ?></button>
            </form>
        <?php else: ?>
            <h1><?php echo __("Done!"); ?></h1>
            <p>
                <?php echo __("Chyrp has been successfully installed and you have been logged in."); ?>
            </p>
            <h2><?php echo __("So, what now?"); ?></h2>
            <ol>
                <li><?php echo __("<strong>Delete install.php</strong>, you won't need it anymore."); ?></li>
            <?php if (!is_writable(INCLUDES_DIR."/caches")): ?>
                <li><?php echo __("CHMOD <code>/includes/caches</code> to 777."); ?></li>
            <?php endif; ?>
                <li><a href="http://chyrp.net/extend/type/translation"><?php echo __("Look for a translation for your language."); ?></a></li>
                <li><a href="http://chyrp.net/extend/type/module"><?php echo __("Install some Modules."); ?></a></li>
                <li><a href="http://chyrp.net/extend/type/feather"><?php echo __("Find some Feathers you want."); ?></a></li>
                <li><a href="README.markdown"><?php echo __("Read &#8220;Getting Started&#8221;"); ?></a></li>
            </ol>
            <a class="big" href="<?php echo $config->chyrp_url; ?>"><?php echo __("Take me to my site! &rarr;"); ?></a>
        <?php endif; ?>
        </div>
    </body>
</html>
