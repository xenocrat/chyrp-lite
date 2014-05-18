<?php
    /**
     * File: Upgrader
     * A task-based general-purpose Chyrp upgrader.
     *
     * Performs upgrade functions based on individual tasks, and checks whether or not they need to be done.
     *
     * Version-agnostic. Completely safe to be run at all times, by anyone.
     */

    header("Content-type: text/html; charset=UTF-8");

    define('DEBUG',        true);
    define('CHYRP_VERSION', "2.1.2");
    define('CACHE_TWIG',   false);
    define('JAVASCRIPT',   false);
    define('ADMIN',        false);
    define('AJAX',         false);
    define('XML_RPC',      false);
    define('TRACKBACK',    false);
    define('UPGRADING',    true);
    define('INSTALLING',   false);
    define('TESTER',       true);
    define('INDEX',        false);
    define('MAIN_DIR',     dirname(__FILE__));
    define('INCLUDES_DIR', dirname(__FILE__)."/includes");
    define('MODULES_DIR',  MAIN_DIR."/modules");
    define('FEATHERS_DIR', MAIN_DIR."/feathers");
    define('THEMES_DIR',   MAIN_DIR."/themes");
    define('USE_ZLIB',     true);

    if (!AJAX and
        extension_loaded("zlib") and
        !ini_get("zlib.output_compression") and
        isset($_SERVER['HTTP_ACCEPT_ENCODING']) and
        substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], "gzip") and
        USE_ZLIB) {
        ob_start("ob_gzhandler");
        header("Content-Encoding: gzip");
    } else
        ob_start();

    /**
     * Function: config_file
     * Returns what config file their install is set up for.
     */
    function config_file() {
        if (file_exists(INCLUDES_DIR."/config.yaml.php"))
            return INCLUDES_DIR."/config.yaml.php";

        if (file_exists(INCLUDES_DIR."/config.yml.php"))
            return INCLUDES_DIR."/config.yml.php";

        if (file_exists(INCLUDES_DIR."/config.php"))
            return INCLUDES_DIR."/config.php";

        exit("Config file not found.");
    }

    /**
     * Function: database_file
     * Returns what database config file their install is set up for.
     */
    function database_file() {
        if (file_exists(INCLUDES_DIR."/database.yaml.php"))
            return INCLUDES_DIR."/database.yaml.php";

        if (file_exists(INCLUDES_DIR."/database.yml.php"))
            return INCLUDES_DIR."/database.yml.php";

        if (file_exists(INCLUDES_DIR."/database.php"))
            return INCLUDES_DIR."/database.php";

        return false;
    }

    /**
     * Function: using_yaml
     * Are they using YAML config storage?
     */
    function using_yaml() {
        return (basename(config_file()) != "config.php" and basename(database_file()) != "database.php") or !database_file();
    }

    # Evaluate the code in their config files, but with the classes renamed, so we can safely retrieve the values.
    if (!using_yaml()) {
        eval(str_replace(array("<?php", "?>", "Config"),
                         array("", "", "OldConfig"),
                         file_get_contents(config_file())));

        if (database_file())
            eval(str_replace(array("<?php", "?>", "SQL"),
                             array("", "", "OldSQL"),
                             file_get_contents(database_file())));
    }

    # File: Helpers
    # Various functions used throughout Chyrp's code.
    require_once INCLUDES_DIR."/helpers.php";

    # File: Gettext
    # Gettext library.
    require_once INCLUDES_DIR."/lib/gettext/gettext.php";

    # File: Streams
    # Streams library.
    require_once INCLUDES_DIR."/lib/gettext/streams.php";

    # File: YAML
    # Horde YAML parsing library.
    require_once INCLUDES_DIR."/lib/YAML.php";

    # File: SQL
    # See Also:
    #     <SQL>
    require INCLUDES_DIR."/class/SQL.php";

    /**
     * Class: Config
     * Handles writing to whichever config file they're using.
     */
    class Config {
        # Array: $yaml
        # Stores all of the YAML data.
        static $yaml = array("config" => array(),
                             "database" => array());

        /**
         * Function: get
         * Returns a config setting.
         *
         * Parameters:
         *     $setting - The setting to return.
         */
        static function get($setting) {
            return (isset(Config::$yaml["config"][$setting])) ? Config::$yaml["config"][$setting] : false ;
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
            if (self::get($setting) == $value) return;

            if (!isset($message))
                $message = _f("Setting %s to %s...", array($setting, normalize(print_r($value, true))));

            Config::$yaml["config"][$setting] = $value;

            $protection = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";

            $dump = $protection.YAML::dump(Config::$yaml["config"]);

            echo $message.test(@file_put_contents(INCLUDES_DIR."/config.yaml.php", $dump));
        }

        /**
         * Function: check
         * Does a config exist?
         *
         * Parameters:
         *     $setting - Name of the config to check.
         */
        static function check($setting) {
            return (isset(Config::$yaml["config"][$setting]));
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
            if (!self::check($setting)) return;

            unset(Config::$yaml["config"][$setting]);

            $protection = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";

            $dump = $protection.YAML::dump(Config::$yaml["config"]);

            echo _f("Removing %s setting...", array($setting)).
                 test(@file_put_contents(INCLUDES_DIR."/config.yaml.php", $dump));
        }
    }

    if (using_yaml()) {
        Config::$yaml["config"] = YAML::load(preg_replace("/<\?php(.+)\?>\n?/s", "", file_get_contents(config_file())));

        if (database_file())
            Config::$yaml["database"] = YAML::load(preg_replace("/<\?php(.+)\?>\n?/s", "", file_get_contents(database_file())));
        else
            Config::$yaml["database"] = oneof(@Config::$yaml["config"]["sql"], array());
    } else {
        # $config and $sql here are loaded from the eval()'s above.

        foreach ($config as $name => $val)
            Config::$yaml["config"][$name] = $val;

        foreach ($sql as $name => $val)
            Config::$yaml["database"][$name] = $val;
    }

    load_translator("chyrp", INCLUDES_DIR."/locale/".Config::get("locale").".mo");

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

    #---------------------------------------------
    # Upgrading Actions
    #---------------------------------------------

    /**
     * Function: fix_htaccess
     * Repairs their .htaccess file.
     */
    function fix_htaccess() {
        $url = "http://".$_SERVER['HTTP_HOST'].str_replace("/upgrade.php", "", $_SERVER['REQUEST_URI']);
        $index = (parse_url($url, PHP_URL_PATH)) ? "/".trim(parse_url($url, PHP_URL_PATH), "/")."/" : "/" ;

        $path = preg_quote($index, "/");
        $htaccess_has_chyrp = (file_exists(MAIN_DIR."/.htaccess") and preg_match("/<IfModule mod_rewrite\.c>\n([\s]*)RewriteEngine On\n([\s]*)RewriteBase {$path}\n([\s]*)RewriteCond %\{REQUEST_FILENAME\} !-f\n([\s]*)RewriteCond %\{REQUEST_FILENAME\} !-d\n([\s]*)RewriteRule (\^\.\+\\$|\!\\.\(gif\|jpg\|png\|css\)) index\.php \[L\]\n([\s]*)RewriteRule \^\.\+\\\.twig\\$ index\.php \[L\]\n([\s]*)<\/IfModule>/", file_get_contents(MAIN_DIR."/.htaccess")));
        if ($htaccess_has_chyrp)
            return;

        $htaccess = "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase {$index}\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^.+\$ index.php [L]\nRewriteRule ^.+\\.twig\$ index.php [L]\n</IfModule>";

        if (!file_exists(MAIN_DIR."/.htaccess"))
            echo __("Generating .htaccess file...").
                 test(@file_put_contents(MAIN_DIR."/.htaccess", $htaccess), __("Try creating the file and/or CHMODding it to 777 temporarily."));
        else
            echo __("Appending to .htaccess file...").
                 test(@file_put_contents(MAIN_DIR."/.htaccess", "\n\n".$htaccess, FILE_APPEND), __("Try creating the file and/or CHMODding it to 777 temporarily."));
    }

    /**
     * Function: download_new_version
     * Downloads the newest version of chyrp 
     */
    function download_new_version() {
        $version = file_get_contents("http://chyrp.net/api/1/version.php");
        
        if (version_compare($version, CHYRP_VERSION, "<="))
            return;

        $e = 0;
        # delete everything from 2 versions back
        if (is_dir("old"))
            if (!rmdir("old")) 
                echo __("Please manually delete the directory root/updates");
                $e = 1;

        if (is_dir("updates"))
            if (!rmdir("updates")) 
                echo __("Please manually delete the directory root/updates");
                $e = 1;

        if (!mkdir("updates")) 
            echo __("Please manually create the directory root/updates");
            $e = 1;

        if (!mkdir("old")) 
            echo __("Please manually create the directory root/old");
            $e = 1;

        $files = array("includes",
                       "admin",
                       "index.php",
                       "upgrade.php",
                       "modules",
                       "feathers",
                       "themes");
        if ($e == 0) {
            foreach ($files as $file)
                if (file_exists($file)) {
                   # move stuff to the old dir so we can download the new one
                   rename($file, "old/".$file);
                   if (!rename($file, "old/".$file))
                        echo __("Please move the file at root/".$file." to root/old/".$file);
                }

            $fp = fopen ("updates/latest.zip", "w+");
            $ch = curl_init("http://chyrp.net/releases/chyrp_v".$version.".zip"); # Here is the file we are downloading
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_TIMEOUT, 50);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
            if (function_exists("zip_open")) {
                $zip = new ZipArchive();
                $x = $zip->open("updates/latest.zip");
                if ($x === true) {
                    $zip->extractTo("updates/");
                    $zip->close();
                    unlink("updates/latest.zip");
                }

                foreach($files as $file)
                    if (file_exists("updates/chyrp/".$file))
                       if (!rename("updates/chyrp/".$file, $file))
                            echo __("Please move the file at root/updates/chyrp/".$file." to root/".$file);

                if (file_exists("old/includes/config.yaml.php"))
                    if (!copy("old/includes/config.yaml.php", "includes/config.yaml.php"))
                        echo __("Please manually copy the file root/old/includes/config.yaml.php to root/includes/config.yaml.php");

                if (is_dir("updates"))
                    if (!rmdir("updates")) 
                        echo __("Please manually delete the directory root/updates");
            }
        }
    }

    /**
     * Function: tweets_to_posts
     * Enacts the "tweet" to "post" rename.
     *
     * Versions: 1.0.2 => 1.0.3
     */
    function tweets_to_posts() {
        if (SQL::current()->query("SELECT * FROM __tweets"))
            echo __("Renaming tweets table to posts...").
                 test(SQL::current()->query("RENAME TABLE __tweets TO __posts"));

        if (SQL::current()->query("SELECT add_tweet FROM __groups"))
            echo __("Renaming add_tweet permission to add_post...").
                 test(SQL::current()->query("ALTER TABLE __groups CHANGE add_tweet add_post TINYINT(1) NOT NULL DEFAULT '0'"));

        if (SQL::current()->query("SELECT edit_tweet FROM __groups"))
            echo __("Renaming edit_tweet permission to edit_post...").
                 test(SQL::current()->query("ALTER TABLE __groups CHANGE edit_tweet edit_post TINYINT(1) NOT NULL DEFAULT '0'"));

        if (SQL::current()->query("SELECT delete_tweet FROM __groups"))
            echo __("Renaming delete_tweet permission to delete_post...").
                 test(SQL::current()->query("ALTER TABLE __groups CHANGE delete_tweet delete_post TINYINT(1) NOT NULL DEFAULT '0'"));

        if (Config::check("tweets_per_page")) {
            Config::fallback("posts_per_page", Config::get("tweets_per_page"));
            Config::remove("tweets_per_page");
        }

        if (Config::check("tweet_url")) {
            Config::fallback("post_url", Config::get("tweet_url"));
            Config::remove("tweet_url");
        }

        if (Config::check("rss_tweets")) {
            Config::fallback("rss_posts", Config::get("rss_posts"));
            Config::remove("rss_tweets");
        }
    }

    /**
     * Function: pages_parent_id_column
     * Adds the @parent_id@ column to the "pages" table.
     *
     * Versions: 1.0.3 => 1.0.4
     */
    function pages_parent_id_column() {
        if (SQL::current()->query("SELECT parent_id FROM __pages"))
            return;

        echo __("Adding parent_id column to pages table...").
             test(SQL::current()->query("ALTER TABLE __pages ADD parent_id INT(11) NOT NULL DEFAULT '0' AFTER user_id"));
    }

    /**
     * Function: pages_list_order_column
     * Adds the @list_order@ column to the "pages" table.
     *
     * Versions: 1.0.4 => 1.1.0
     */
    function pages_list_order_column() {
        if (SQL::current()->query("SELECT list_order FROM __pages"))
            return;

        echo __("Adding list_order column to pages table...").
             test(SQL::current()->query("ALTER TABLE __pages ADD list_order INT(11) NOT NULL DEFAULT '0' AFTER show_in_list"));
    }

    /**
     * Function: remove_beginning_slash_from_post_url
     * Removes the slash at the beginning of the post URL setting.
     */
    function remove_beginning_slash_from_post_url() {
        if (substr(Config::get("post_url"), 0, 1) == "/")
            Config::set("post_url", ltrim(Config::get("post_url"), "/"));
    }

    /**
     * Function: move_yml_yaml
     * Renames config.yml.php to config.yaml.php.
     *
     * Versions: 1.1.2 => 1.1.3
     */
    function move_yml_yaml() {
        if (file_exists(INCLUDES_DIR."/config.yml.php"))
            echo __("Moving /includes/config.yml.php to /includes/config.yaml.php...").
                 test(@rename(INCLUDES_DIR."/config.yml.php", INCLUDES_DIR."/config.yaml.php"), __("Try CHMODding the file to 777."));
    }

    /**
     * Function: update_protection
     * Updates the PHP protection code in the config file.
     */
    function update_protection() {
        if (!file_exists(INCLUDES_DIR."/config.yaml.php") or
            substr_count(file_get_contents(INCLUDES_DIR."/config.yaml.php"),
                         "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>"))
            return;

        $contents = file_get_contents(INCLUDES_DIR."/config.yaml.php");
        $new_error = preg_replace("/<\?php (.+) \?>/",
                                  "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>",
                                  $contents);

        echo __("Updating protection code in config.yaml.php...").
             test(@file_put_contents(INCLUDES_DIR."/config.yaml.php", $new_error), __("Try CHMODding the file to 777."));
    }

    /**
     * Function: theme_default_to_blossom
     * Changes their theme from "default" to "blossom", or leaves it alone if they're not using "default".
     *
     * Versions: 1.1.3.2 => 2.0
     */
    function theme_default_to_blossom() {
        if (Config::get("theme") != "default") return;
        Config::set("theme", "blossom");
    }

    /**
     * Function: default_db_adapter_to_mysql
     * Adds an "adapter" SQL setting if it doesn't exist, and sets it to "mysql".
     *
     * Versions: 1.1.3.2 => 2.0
     */
    function default_db_adapter_to_mysql() {
        $sql = SQL::current();
        if (isset($sql->adapter)) return;
        $sql->set("adapter", "mysql");
    }

    /**
     * Function: move_upload
     * Renames the "upload" directory to "uploads".
     */
    function move_upload() {
        if (file_exists(MAIN_DIR."/upload") and !file_exists(MAIN_DIR."/uploads"))
            echo __("Renaming /upload directory to /uploads...").test(@rename(MAIN_DIR."/upload", MAIN_DIR."/uploads"), __("Try CHMODding the directory to 777."));
    }

    /**
     * Function: make_posts_xml
     * Updates all of the post XML data to well-formed non-CDATAized XML.
     *
     * Versions: 1.1.3.2 => 2.0
     */
    function make_posts_safe() {
        if (!$posts = SQL::current()->query("SELECT * FROM __posts"))
            return;

        if (!SQL::current()->query("SELECT xml FROM __posts"))
            return;

        function clean_xml(&$input) {
            $input = trim($input);
        }

        while ($post = $posts->fetchObject()) {
            if (!substr_count($post->xml, "<![CDATA["))
                continue;

            $post->xml = str_replace("<![CDATA[]]>", "", $post->xml);

            $xml = simplexml_load_string($post->xml, "SimpleXMLElement", LIBXML_NOCDATA);

            $parse = xml2arr($xml);

            array_walk_recursive($parse, "clean_xml");

            $new_xml = new SimpleXMLElement("<post></post>");
            arr2xml($new_xml, $parse);

            echo _f("Sanitizing XML data of post #%d...", array($post->id)).
                 test(SQL::current()->update("posts",
                                             array("id" => $post->id),
                                             array("xml" => $new_xml->asXML())));
        }
    }

    /**
     * Function: rss_posts_to_feed_items
     * Rename the feed items setting.
     *
     * Versions: 1.1.3.2 => 2.0
     */
    function rss_posts_to_feed_items() {
        if (!Config::check("rss_posts"))
            return;

        Config::fallback("feed_items", Config::get("rss_posts"));
        Config::remove("rss_posts");
    }

    /**
     * Function: update_groups_to_yaml
     * Updates the groups to use YAML-based permissions instead of table columns.
     *
     * Versions: 1.1.3.2 => 2.0
     */
    function update_groups_to_yaml() {
        if (!SQL::current()->query("SELECT view_site FROM __groups")) return;

        $get_groups = SQL::current()->query("SELECT * FROM __groups");
        echo __("Backing up current groups table...").test($get_groups);
        if (!$get_groups) return;

        $groups = array();
        # Generate an array of groups, name => permissions.
        while ($group = $get_groups->fetchObject()) {
            $groups[$group->name] = array("permissions" => array());
            foreach ($group as $key => $val)
                if ($key != "name" and $key != "id" and $val)
                    $groups[$group->name]["permissions"][] = $key;
                elseif ($key == "id")
                    $groups[$group->name]["id"] = $val;
        }

        # Convert permissions array to a YAML dump.
        foreach ($groups as $key => &$val)
            $val["permissions"] = YAML::dump($val["permissions"]);

        $drop_groups = SQL::current()->query("DROP TABLE __groups");
        echo __("Dropping old groups table...").test($drop_groups);
        if (!$drop_groups) return;

        $groups_table = SQL::current()->query("CREATE TABLE __groups (
                                                   id INTEGER PRIMARY KEY AUTO_INCREMENT,
                                                   name VARCHAR(100) DEFAULT '',
                                                   permissions LONGTEXT,
                                                   UNIQUE (name)
                                               ) DEFAULT CHARSET=utf8");
        echo __("Creating new groups table...").test($groups_table);
        if (!$groups_table) return;

        foreach($groups as $name => $values)
            echo _f("Restoring group \"%s\"...", array($name)).
                 test(SQL::current()->insert("groups",
                                             array("id" => $values["id"],
                                                   "name" => $name,
                                                   "permissions" => $values["permissions"])));
    }

    /**
     * Function: add_permissions_table
     * Creates the "permissions" table and fills it in with the default set.
     *
     * Versions: 1.1.3.2 => 2.0
     */
    function add_permissions_table() {
        if (SQL::current()->query("SELECT * FROM __permissions")) return;

        $permissions_table = SQL::current()->query("CREATE TABLE __permissions (
                                                        id VARCHAR(100) DEFAULT '' PRIMARY KEY,
                                                        name VARCHAR(100) DEFAULT ''
                                                    ) DEFAULT CHARSET=utf8");
        echo __("Creating new permissions table...").test($permissions_table);
        if (!$permissions_table) return;

        $permissions = array("change_settings" => "Change Settings",
                             "toggle_extensions" => "Toggle Extensions",
                             "view_site" => "View Site",
                             "view_private" => "View Private Posts",
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

        foreach ($permissions as $id => $name)
            echo _f("Inserting permission \"%s\"...", array($name)).
                 test(SQL::current()->insert("permissions",
                                             array("id" => $id,
                                                   "name" => $name)));
    }

    /**
     * Function: add_sessions_table
     * Creates the "sessions" table.
     *
     * Versions: 1.1.3.2 => 2.0
     */
    function add_sessions_table() {
        if (SQL::current()->query("SELECT * FROM __sessions")) return;

        echo __("Creating `sessions` table...").
             test(SQL::current()->query("CREATE TABLE __sessions (
                                             id VARCHAR(40) DEFAULT '',
                                             data LONGTEXT,
                                             user_id INTEGER DEFAULT '0',
                                             created_at DATETIME DEFAULT NULL,
                                             updated_at DATETIME DEFAULT NULL,
                                             PRIMARY KEY (id)
                                         ) DEFAULT CHARSET=utf8") or die(mysql_error()));
    }

    /**
     * Function: update_permissions_table
     * Updates the "permissions" table from ## (id) => foo_bar (name) to foo_bar (id) => Foo Bar (name).
     *
     * Versions: 2.0b => 2.0
     */
    function update_permissions_table() {
        # If there are any non-numeric IDs in the permissions database, assume this is already done.
        $check = SQL::current()->query("SELECT * FROM __permissions");
        while ($row = $check->fetchObject())
            if (!is_numeric($row->id))
                return;

        $permissions_backup = array();
        $get_permissions = SQL::current()->query("SELECT * FROM __permissions");
        echo __("Backing up current permissions table...").test($get_permissions);
        if (!$get_permissions) return;

        while ($permission = $get_permissions->fetchObject())
            $permissions_backup[] = $permission->name;

        $drop_permissions = SQL::current()->query("DROP TABLE __permissions");
        echo __("Dropping old permissions table...").test($drop_permissions);
        if (!$drop_permissions) return;

        echo __("Creating new permissions table...").
             test(SQL::current()->query("CREATE TABLE IF NOT EXISTS __permissions (
                                             id VARCHAR(100) DEFAULT '' PRIMARY KEY,
                                             name VARCHAR(100) DEFAULT ''
                                         ) DEFAULT CHARSET=utf8"));

        $permissions = array("change_settings" => "Change Settings",
                             "toggle_extensions" => "Toggle Extensions",
                             "view_site" => "View Site",
                             "view_private" => "View Private Posts",
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

        foreach ($permissions_backup as $id) {
            $name = isset($permissions[$id]) ? $permissions[$id] : camelize($id, true);
            echo _f("Restoring permission \"%s\"...", array($name)).
                 test(SQL::current()->insert("permissions",
                                             array("id" => $id,
                                                   "name" => $name)));
        }

    }

    /**
     * Function: update_custom_routes
     * Updates the custom routes to be path => action instead of # => path.
     *
     * Versions: 2.0rc1 => 2.0rc2
     */
    function update_custom_routes() {
        $custom_routes = Config::get("routes");
        if (empty($custom_routes)) return;

        $new_routes = array();
        foreach ($custom_routes as $key => $route) {
            if (!is_int($key))
                return;

            $split = array_filter(explode("/", $route));

            if (!isset($split[0]))
                return;

            echo _f("Updating custom route %s to new format...", array($route)).
                 test(isset($split[0]) and $new_routes[$route] = $split[0]);
        }

        Config::set("routes", $new_routes, "Setting new custom routes configuration...");
    }

    /**
     * Function: remove_database_config_file
     * Removes the database.yaml.php file, which is merged into config.yaml.php.
     *
     * Versions: 2.0rc1 => 2.0rc2
     */
    function remove_database_config_file() {
        if (file_exists(INCLUDES_DIR."/database.yaml.php"))
            echo __("Removing database.yaml.php file...").
                 test(@unlink(INCLUDES_DIR."/database.yaml.php"), __("Try deleting it manually."));
    }

    /**
     * Function: rename_database_setting_to_sql
     * Renames the "database" config setting to "sql".
     */
    function rename_database_setting_to_sql() {
        if (Config::check("sql")) return;
        Config::set("sql", Config::get("database"));
        Config::remove("database");
    }

    /**
     * Function: update_post_status_column
     * Updates the @status@ column on the "posts" table to be a generic varchar field instead of enum.
     *
     * Versions: 2.0rc1 => 2.0rc2
     */
    function update_post_status_column() {
        $sql = SQL::current();
        if (!$column = $sql->query("SHOW COLUMNS FROM __posts WHERE Field = 'status'"))
             return;

        if ($column->fetchObject()->Type == "varchar(32)")
            return;

        echo __("Updating `status` column on `posts` table...")."\n";

        echo " - ".__("Backing up `posts` table...").
             test($backup = $sql->select("posts"));

        if (!$backup)
            return;

        $backups = $backup->fetchAll();

        echo " - ".__("Dropping `posts` table...").
             test($drop = $sql->query("DROP TABLE __posts"));

        if (!$drop)
            return;

        echo " - ".__("Creating `posts` table...").
             test($create = $sql->query("CREATE TABLE IF NOT EXISTS __posts (
                                             id INTEGER PRIMARY KEY AUTO_INCREMENT,
                                             xml LONGTEXT,
                                             feather VARCHAR(32) DEFAULT '',
                                             clean VARCHAR(128) DEFAULT '',
                                             url VARCHAR(128) DEFAULT '',
                                             pinned TINYINT(1) DEFAULT 0,
                                             status VARCHAR(32) DEFAULT 'public',
                                             user_id INTEGER DEFAULT 0,
                                             created_at DATETIME DEFAULT NULL,
                                             updated_at DATETIME DEFAULT NULL
                                         ) DEFAULT CHARSET=utf8"));

        if (!$create) {
            echo " -".test(false, _f("Backup written to %s.", array("./_posts.bak.txt")));
            return file_put_contents("./_posts.bak.txt", var_export($backups, true));
        }

        foreach ($backups as $backup) {
            echo " - "._f("Restoring post #%d...", array($backup["id"])).
                 test($insert = $sql->insert("posts", $backup), _f("Backup written to %s.", array("./_posts.bak.txt")));

            if (!$insert)
                return file_put_contents("./_posts.bak.txt", var_export($backups, true));
        }

        echo " -".test(true);
    }

    /**
     * Function: add_post_attributes_table
     * Adds the "post_attributes" table.
     *
     * Versions: 2.0rc1 => 2.0rc2
     */
    function add_post_attributes_table() {
        $sql = SQL::current();
        if ($sql->select("post_attributes"))
            return;

        echo __("Creating `post_attributes` table...").
             test($sql->query("CREATE TABLE __post_attributes (
                                   post_id INTEGER NOT NULL ,
                                   name VARCHAR(100) DEFAULT '',
                                   value LONGTEXT,
                                   PRIMARY KEY (post_id, name)
                               ) DEFAULT CHARSET=utf8"));
    }

    /**
     * Function: post_xml_to_db
     * Migrates the XML post attributes to the "post_attributes" table.
     *
     * Versions: 2.0rc1 => 2.0rc2
     */
    function post_xml_to_db() {
        $sql = SQL::current();
        if (!$rows = $sql->query("SELECT id, xml FROM __posts"))
            return;

        function insert_attributes($sql, $row, $xml, &$inserts) {
            foreach ($xml as $name => $value) {
                if (is_array($value))
                    $value = YAML::dump($value);

                if (!$sql->insert("post_attributes",
                                  array("post_id" => $row["id"],
                                        "name" => $name,
                                        "value" => $value))) {
                    # Clear successful attribute insertions so the
                    # user can try again without primary key conflicts.
                    foreach ($inserts as $insertion)
                        $sql->delete("post_attributes",
                                     array("post_id" => $insertion["id"],
                                           "name" => $insertion["name"]));

                    return false;
                } else
                    $inserts[] = array("id" => $row["id"],
                                       "name" => $name);
            }

            return true;
        }

        $results = array();
        foreach ($rows->fetchAll() as $row) {
            if (empty($row["xml"]))
                continue;

            $xml = xml2arr(new SimpleXMLElement($row["xml"]));
            $inserts = array();
            echo _f("Migrating attributes of post #%d...", array($row["id"])).
                 test($results[] = insert_attributes($sql, $row, $xml, $inserts));
        }

        if (!in_array(false, $results)) {
            echo __("Removing `xml` column from `posts` table...")."\n";

            echo " - ".__("Backing up `posts` table...").
                 test($backup = $sql->select("posts"));

            if (!$backup)
                return;

            $backups = $backup->fetchAll();

            echo " - ".__("Dropping `posts` table...").
                 test($drop = $sql->query("DROP TABLE __posts"));

            if (!$drop)
                return;

            echo " - ".__("Creating `posts` table...").
                 test($create = $sql->query("CREATE TABLE IF NOT EXISTS __posts (
                                                 id INTEGER PRIMARY KEY AUTO_INCREMENT,
                                                 feather VARCHAR(32) DEFAULT '',
                                                 clean VARCHAR(128) DEFAULT '',
                                                 url VARCHAR(128) DEFAULT '',
                                                 pinned TINYINT(1) DEFAULT 0,
                                                 status VARCHAR(32) DEFAULT 'public',
                                                 user_id INTEGER DEFAULT 0,
                                                 created_at DATETIME DEFAULT NULL,
                                                 updated_at DATETIME DEFAULT NULL
                                             ) DEFAULT CHARSET=utf8"));

            if (!$create)
                return file_put_contents("./_posts.bak.txt", var_export($backups, true));

            foreach ($backups as $backup) {
                unset($backup["xml"]);
                echo " - "._f("Restoring post #%d...", array($backup["id"])).
                     test($insert = $sql->insert("posts", $backup));

                if (!$insert)
                    return file_put_contents("./_posts.bak.txt", var_export($backups, true));
            }

            echo " -".test(true);
        }
    }

    /**
     * Function: add_group_id_to_permissions
     * Adds the @group_id@ column to the "permissions" table.
     *
     * Versions: 2.0rc1 => 2.0rc2
     */
    function add_group_id_to_permissions() {
        $sql = SQL::current();
        if ($sql->select("permissions", "group_id"))
            return;

        echo __("Backing up permissions...").
             test($permissions = $sql->select("permissions"));

        if (!$permissions)
            return;

        $backup = $permissions->fetchAll();

        echo __("Dropping `permissions` table...").
             test($sql->query("DROP TABLE __permissions"));

        echo __("Creating `permissions` table...").
             test($sql->query("CREATE TABLE __permissions (
                                   id VARCHAR(100) DEFAULT '',
                                   name VARCHAR(100) DEFAULT '',
                                   group_id INTEGER DEFAULT 0,
                                   PRIMARY KEY (id, group_id)
                               ) DEFAULT CHARSET=utf8"));

        foreach ($backup as $permission)
            echo _f("Restoring permission `%s`...", array($permission["name"])).
                 test($sql->insert("permissions",
                                   array("id" => $permission["id"],
                                         "name" => $permission["name"],
                                         "group_id" => 0)));
    }

    /**
     * Function: group_permissions_to_db
     * Migrates the group permissions from a YAML column to the "permissions" table.
     *
     * Versions: 2.0rc1 => 2.0rc2
     */
    function group_permissions_to_db() {
        $sql = SQL::current();
        if (!$sql->select("groups", "permissions"))
            return;

        echo __("Backing up groups...").
             test($groups = $sql->select("groups"));

        if (!$groups)
            return;

        $backup = $groups->fetchAll();

        $names = array();
        foreach($backup as $group) {
            $names[$group["id"]] = $group["name"];
            $permissions[$group["id"]] = empty($group["permissions"]) ? array() : YAML::load($group["permissions"]) ;
        }

        echo __("Dropping `groups` table...").
             test($sql->query("DROP TABLE __groups"));

        echo __("Creating `groups` table...").
             test($sql->query("CREATE TABLE __groups (
                                   id INTEGER PRIMARY KEY AUTO_INCREMENT,
                                   name VARCHAR(100) DEFAULT '',
                                   UNIQUE (name)
                               ) DEFAULT CHARSET=utf8"));

        foreach ($names as $id => $name)
            echo _f("Restoring group `%s`...", array($name)).
                 test($sql->insert("groups",
                                   array("id" => $id,
                                        "name" => $name)));

        foreach ($permissions as $id => $permissions)
            foreach ($permissions as $permission)
                echo _f("Restoring permission `%s` on group `%s`...", array($permission, $names[$id])).
                     test($sql->insert("permissions",
                                       array("id" => $permission,
                                             "name" => $sql->select("permissions", "name", array("id" => $permission))->fetchColumn(),
                                             "group_id" => $id)));
    }

    /**
     * Function: remove_old_files
     * Removes old/unused files from previous installs.
     */
    function remove_old_files() {
        if (file_exists(INCLUDES_DIR."/config.php"))
            echo __("Removing `includes/config.php` file...").
                 test(@unlink(INCLUDES_DIR."/config.php"));

        if (file_exists(INCLUDES_DIR."/database.php"))
            echo __("Removing `includes/database.php` file...").
                 test(@unlink(INCLUDES_DIR."/database.php"));

        if (file_exists(INCLUDES_DIR."/rss.php"))
            echo __("Removing `includes/rss.php` file...").
                 test(@unlink(INCLUDES_DIR."/rss.php"));

        if (file_exists(INCLUDES_DIR."/bookmarklet.php"))
            echo __("Removing `includes/bookmarklet.php` file...").
                 test(@unlink(INCLUDES_DIR."/bookmarklet.php"));
    }

    /**
     * Function: update_user_password_column
     * Updates the @password@ column on the "users" table to have a length of 60.
     *
     * Versions: 2.0rc3 => 2.0
     */
    function update_user_password_column() {
        $sql = SQL::current();
        if (!$column = $sql->query("SHOW COLUMNS FROM __users WHERE Field = 'password'"))
             return;

        if ($column->fetchObject()->Type == "varchar(60)")
            return;

        echo __("Updating `password` column on `users` table...")."\n";

        echo " - ".__("Backing up `users` table...").
             test($backup = $sql->select("users"));

        if (!$backup)
            return;

        $backups = $backup->fetchAll();

        echo " - ".__("Dropping `users` table...").
             test($drop = $sql->query("DROP TABLE __users"));

        if (!$drop)
            return;

        echo " - ".__("Creating `users` table...").
             test($create = $sql->query("CREATE TABLE IF NOT EXISTS `__users` (
                                            `id` int(11) NOT NULL AUTO_INCREMENT,
                                            `login` varchar(64) DEFAULT '',
                                            `password` varchar(60) DEFAULT NULL,
                                            `full_name` varchar(250) DEFAULT '',
                                            `email` varchar(128) DEFAULT '',
                                            `website` varchar(128) DEFAULT '',
                                            `group_id` int(11) DEFAULT '0',
                                            `approved` BOOLEAN DEFAULT '1',
                                            `joined_at` datetime DEFAULT NULL,
                                            PRIMARY KEY (`id`),
                                            UNIQUE KEY `login` (`login`)
                                        ) DEFAULT CHARSET=utf8"));

        if (!$create) {
            echo " -".test(false, _f("Backup written to %s.", array("./_users.bak.txt")));
            return file_put_contents("./_users.bak.txt", var_export($backups, true));
        }

        foreach ($backups as $backup) {
            echo " - "._f("Restoring user #%d...", array($backup["id"])).
                 test($insert = $sql->insert("users", $backup), _f("Backup written to %s.", array("./_users.bak.txt")));

            if (!$insert)
                return file_put_contents("./_users.bak.txt", var_export($backups, true));
        }

        echo " -".test(true);
    }

    /**
     * Function: add_user_approved_column
     * Adds the @is_approved@ column on the "users" table, and approves all current users.
     *
     * Versions: 2.1 => 2.5
     */
    function add_user_approved_column() {
        if (SQL::current()->query("SELECT approved FROM __users"))
            return;

        echo __("Adding approved column to users table...").
             test(SQL::current()->query("ALTER TABLE __users ADD approved BOOLEAN DEFAULT '1' AFTER group_id"));
    }

    /**
     * Function: update_user_approved_column
     * Updates the @is_approved@ column on the "users" table.
     *
     * Versions: 2.5b3 => 2.5rc1
     */
    function update_user_approved_column() {
        $sql = SQL::current();
        if (!$column = $sql->query("SHOW COLUMNS FROM __users WHERE Field = 'is_approved'"))
             return;

        if ($column->fetchObject()->Type == "boolean")
            return;

        echo __("Updating `approved` column on `users` table...")."\n";

        echo " - ".__("Backing up `users` table...").
             test($backup = $sql->select("users"));

        if (!$backup) return;

        $backups = $backup->fetchAll();

        echo " - ".__("Dropping `users` table...").
             test($drop = $sql->query("DROP TABLE __users"));

        if (!$drop) return;

        echo " - ".__("Creating `users` table...").
             test($create = $sql->query("CREATE TABLE IF NOT EXISTS `__users` (
                                            `id` int(11) NOT NULL AUTO_INCREMENT,
                                            `login` varchar(64) DEFAULT '',
                                            `password` varchar(60) DEFAULT NULL,
                                            `full_name` varchar(250) DEFAULT '',
                                            `email` varchar(128) DEFAULT '',
                                            `website` varchar(128) DEFAULT '',
                                            `group_id` int(11) DEFAULT '0',
                                            `approved` tinyint(1) DEFAULT '1',
                                            `joined_at` datetime DEFAULT NULL,
                                            PRIMARY KEY (`id`),
                                            UNIQUE KEY `login` (`login`)
                                        ) DEFAULT CHARSET=utf8"));

        if (!$create) {
            echo " -".test(false, _f("Backup written to %s.", array("./_users.bak.txt")));
            return file_put_contents("./_users.bak.txt", var_export($backups, true));
        }

        foreach ($backups as $backup) {
            echo " - "._f("Restoring user #%d...", array($backup["id"])).
                 test($insert = $sql->insert("users", $backup), _f("Backup written to %s.", array("./_users.bak.txt")));

            if (!$insert)
                return file_put_contents("./_users.bak.txt", var_export($backups, true));
        }

        echo " -".test(true);
    }

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
        <title><?php echo __("Chyrp Upgrader"); ?></title>
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
                margin: 1em 0 .5em;
                text-align: center;
                line-height: 1;
            }
            h1.first {
                margin-top: .25em;
            }
            h1.what_now {
                margin-top: .5em;
            }
            code {
                color: #06B;
                font-family: Monaco, monospace;
            }
            a:link, a:visited {
                color: #6B0;
            }
            pre.pane {
                height: 15em;
                overflow-y: auto;
                margin: -2.68em -2.68em 4em;
                padding: 2.5em;
                background: #333;
                color: #fff;
                -webkit-border-top-left-radius: 2.5em;
                -webkit-border-top-right-radius: 2.5em;
                -moz-border-radius-topleft: 2.5em;
                -moz-border-radius-topright: 2.5em;
            }
            span.yay { color: #0f0; }
            span.boo { color: #f00; }
            a.big,
            button {
                background: #eee;
                display: block;
                text-align: center;
                margin-top: 2em;
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
            ul, ol {
                margin: 0 0 1em 2em;
            }
            li {
                margin-bottom: .5em;
            }
            ul {
                margin-bottom: 1.5em;
            }
            p {
                margin-bottom: 1em;
            }
        </style>
    </head>
    <body>
        <div class="window">
<?php if ((!empty($_POST) and $_POST['upgrade'] == "yes") or isset($_GET['task']) == "upgrade") : ?>
            <pre class="pane"><?php
        # Begin with file/config upgrade tasks.
        download_new_version();

        fix_htaccess();

        remove_beginning_slash_from_post_url();

        move_yml_yaml();

        update_protection();

        theme_default_to_blossom();

        Config::fallback("routes", array());
        Config::fallback("secure_hashkey", md5(random(32, true)));
        Config::fallback("enable_xmlrpc", true);
        Config::fallback("enable_ajax", true);
        Config::fallback("uploads_path", "/uploads/");
        Config::fallback("chyrp_url", Config::get("url"));
        Config::fallback("sql", Config::$yaml["database"]);
        Config::fallback("timezone", "America/New_York");

        // Added in 2.5
        Config::fallback("admin_theme", "default");
        Config::fallback("email_activation", true);
        Config::fallback("enable_recaptcha", true);
        Config::fallback("check_updates", true);
        Config::fallback("enable_emoji", true);

        Config::remove("rss_posts");
        Config::remove("time_offset");

        move_upload();

        remove_database_config_file();

        rename_database_setting_to_sql();

        update_custom_routes();

        default_db_adapter_to_mysql();

        # Perform database upgrade tasks after all the files/config upgrade tasks are done.

        # Prepare the SQL interface.
        $sql = SQL::current();

        # Set the SQL info.
        foreach (Config::$yaml["config"]["sql"] as $name => $value)
            $sql->$name = $value;

        # Initialize connection to SQL server.
        $sql->connect();

        tweets_to_posts();

        pages_parent_id_column();

        pages_list_order_column();

        make_posts_safe();

        rss_posts_to_feed_items();

        update_groups_to_yaml();

        add_permissions_table();

        add_sessions_table();

        update_permissions_table();

        update_post_status_column();

        add_post_attributes_table();

        post_xml_to_db();

        add_group_id_to_permissions();

        group_permissions_to_db();

        remove_old_files();

        update_user_password_column();

        add_user_approved_column();

        # Perform Module/Feather upgrades.

        foreach ((array) Config::get("enabled_modules") as $module)
            if (file_exists(MAIN_DIR."/modules/".$module."/upgrades.php")) {
                ob_start();
                echo $begin = _f("Calling <span class=\"yay\">%s</span> Module's upgrader...", array($module))."\n";
                require MAIN_DIR."/modules/".$module."/upgrades.php";
                $buf = ob_get_contents();
                if (ob_get_contents() == $begin)
                    ob_end_clean();
                else
                    ob_end_flush();
            }

        foreach ((array) Config::get("enabled_feathers") as $feather)
            if (file_exists(MAIN_DIR."/feathers/".$feather."/upgrades.php")) {
                ob_start();
                echo $begin = _f("Calling <span class=\"yay\">%s</span> Feather's upgrader...", array($feather))."\n";
                require MAIN_DIR."/feathers/".$feather."/upgrades.php";
                $buf = ob_get_contents();
                if (ob_get_contents() == $begin)
                    ob_end_clean();
                else
                    ob_end_flush();
            }
?>

<?php echo __("Done!"); ?>

</pre>
            <h1 class="what_now"><?php echo __("What now?"); ?></h1>
            <ol>
                <li><?php echo __("Look through the results up there for any failed tasks. If you see any and you can't figure out why, you can ask for help at the <a href=\"http://chyrp.net/discuss/\">Chyrp Community</a>."); ?></li>
                <li><?php echo __("If any of your Modules or Feathers have new versions available for this release, check if an <code>upgrades.php</code> file exists in their main directory. If that file exists, run this upgrader again after enabling the Module or Feather and it will run the upgrade tasks."); ?></li>
                <li><?php echo __("When you are done, you can delete this file. It doesn't pose any real threat on its own, but you should delete it anyway, just to be sure."); ?></li>
            </ol>
            <h1 class="tips"><?php echo __("Tips"); ?></h1>
            <ul>
                <li><?php echo __("If the admin area looks weird, try clearing your cache."); ?></li>
                <li><?php echo __("As of v2.0, Chyrp uses time zones to determine timestamps. Please set your installation to the correct timezone at <a href=\"admin/index.php?action=general_settings\">General Settings</a>."); ?></li>
                <li><?php echo __("Check the group permissions &ndash; they might have changed, and certain Admin functionality would be disabled until you enabled the permissions for the particular groups. <a href=\"admin/index.php?action=manage_groups\">Manage Groups &rarr;</a>"); ?></li>
            </ul>
            <a class="big" href="<?php echo (Config::check("url") ? Config::get("url") : Config::get("chyrp_url")); ?>"><?php echo __("All done!"); ?></a>
<?php else: ?>
            <h1 class="first"><?php echo __("Halt!"); ?></h1>
            <p><?php echo __("That button may look ready for a-clickin&rsquo;, but please take these preemptive measures before indulging:"); ?></p>
            <ol>
                <li><?php echo __("<strong>Make a backup of your installation.</strong> You never know."); ?></li>
                <li><?php echo __("Disable any third-party Modules and Feathers."); ?></li>
                <li><?php echo __("Ensure that the Chyrp installation directory is writable by the server."); ?></li>
            </ol>
            <p><?php echo __("If any of the upgrade processes fail, you can safely keep refreshing &ndash; it will only attempt to do tasks that are not already successfully completed. If you cannot figure something out, please make a topic (with details!) at the <a href=\"http://chyrp.net/discuss/\">Chyrp Community</a>."); ?></p>
            <form action="upgrade.php" method="post">
                <button type="submit" name="upgrade" value="yes"><?php echo __("Upgrade me!"); ?></button>
            </form>
<?php endif; ?>
        </div>
    </body>
</html>