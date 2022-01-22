<?php
    /**
     * File: helpers
     * Various functions used throughout the codebase.
     */

    #---------------------------------------------
    # Sessions
    #---------------------------------------------

    /**
     * Function: session
     * Begins Chyrp's custom session storage whatnots.
     *
     * Parameters:
     *     $secure - Send the cookie only over HTTPS?
     */
    function session($secure = null): void {
        if (session_status() == PHP_SESSION_ACTIVE) {
            trigger_error(__("Session cannot be started more than once."), E_USER_NOTICE);
            return;
        }

        $handler = new Session();
        session_set_save_handler($handler, true);

        $parsed = parse_url(Config::current()->url);
        fallback($parsed["scheme"], "http");
        fallback($parsed["host"], $_SERVER['SERVER_NAME']);

        if (!is_bool($secure))
            $secure = ($parsed["scheme"] == "https");

        $options = array("lifetime" => 2592000,
                         "expires"  => time() + 2592000,
                         "path"     => "/",
                         "domain"   => $parsed["host"],
                         "secure"   => $secure,
                         "httponly" => true,
                         "samesite" => "Lax");

        $options_params = $options;
        $options_cookie = $options;

        unset($options_params["expires"]);
        unset($options_cookie["lifetime"]);

        session_set_cookie_params($options_params);
        session_name("ChyrpSession");
        session_start();

        if (isset($_COOKIE['ChyrpSession']))
            setcookie(session_name(), session_id(), $options_cookie);
    }

    /**
     * Function: logged_in
     * Returns whether or not the visitor is logged in.
     */
    function logged_in(): bool {
        return (class_exists("Visitor") and 
                isset(Visitor::current()->id) and Visitor::current()->id != 0);
    }

    #---------------------------------------------
    # Routing
    #---------------------------------------------

    /**
     * Function: redirect
     * Redirects to the supplied URL and exits immediately.
     *
     * Parameters:
     *     $url - The absolute or relative URL to redirect to.
     */
    function redirect($url) {
        if (class_exists("Route") and !substr_count($url, "://"))
            $url = url($url);

        header("Location: ".unfix($url, true));
        exit;
    }

    /**
     * Function: show_403
     * Shows an error message with a 403 HTTP header.
     *
     * Parameters:
     *     $title - The title for the error dialog (optional).
     *     $body - The message for the error dialog (optional).
     */
    function show_403($title = "", $body = "") {
        $title = oneof($title, __("Forbidden"));
        $body = oneof($body, __("You do not have permission to access this resource."));

        $theme = Theme::current();
        $main = MainController::current();

        if (!MAIN or !$theme->file_exists("pages".DIR."403"))
            error($title, $body, null, 403);

        header($_SERVER['SERVER_PROTOCOL']." 403 Forbidden");
        $main->feed = false; # Tell the controller not to serve feeds.
        $main->display("pages".DIR."403", array("reason" => $body), $title);
        exit;
    }

    /**
     * Function: show_404
     * Shows an error message with a 404 HTTP header.
     *
     * Parameters:
     *     $title - The title for the error dialog (optional).
     *     $body - The message for the error dialog (optional).
     */
     function show_404($title = "", $body = "") {
        $title = oneof($title, __("Not Found"));
        $body = oneof($body, __("The requested resource was not found."));

        $theme = Theme::current();
        $main = MainController::current();

        if (!MAIN or !$theme->file_exists("pages".DIR."404"))
            error($title, $body, null, 404);

        header($_SERVER['SERVER_PROTOCOL']." 404 Not Found");
        $main->feed = false; # Tell the controller not to serve feeds.
        $main->display("pages".DIR."404", array("reason" => $body), $title);
        exit;
    }

    /**
     * Function: url
     * Mask for Route::url().
     */
    function url($url, $controller = null): string {
        return Route::url($url, $controller);
    }

    /**
     * Function: self_url
     * Returns an absolute URL for the current request.
     */
    function self_url(): string {
        $parsed = parse_url(Config::current()->url);
        $origin = fallback($parsed["scheme"], "http")."://".
                  fallback($parsed["host"], $_SERVER['SERVER_NAME']);

        if (isset($parsed["port"]))
            $origin.= ":".$parsed["port"];

        return fix($origin.$_SERVER['REQUEST_URI'], true);
    }

    /**
     * Function: htaccess_conf
     * Creates the .htaccess file for Chyrp Lite or overwrites an existing file.
     *
     * Parameters:
     *     $url_path - The URL path to MAIN_DIR for the RewriteBase directive.
     *
     * Returns:
     *     True if no action was needed, bytes written on success, false on failure.
     */
    function htaccess_conf($url_path = null) {
        $url_path = oneof($url_path,
                          parse_url(Config::current()->chyrp_url, PHP_URL_PATH),
                          "/");

        $filepath = MAIN_DIR.DIR.".htaccess";
        $template = INCLUDES_DIR.DIR."htaccess.conf";

        if (!is_file($template) or !is_readable($template))
            return false;

        $htaccess = preg_replace('~%\\{CHYRP_PATH\\}/?~',
                                 ltrim($url_path."/", "/"),
                                 file_get_contents($template));

        if (!file_exists($filepath))
            return @file_put_contents($filepath, $htaccess);

        if (!is_file($filepath) or !is_readable($filepath))
            return false;

        if (!preg_match("~".preg_quote($htaccess, "~")."~", file_get_contents($filepath)))
            return @file_put_contents($filepath, $htaccess);

        return true;
    }

    /**
     * Function: caddyfile_conf
     * Creates the caddyfile for Chyrp Lite or overwrites an existing file.
     *
     * Parameters:
     *     $url_path - The URL path to MAIN_DIR for the rewrite directive.
     *
     * Returns:
     *     True if no action was needed, bytes written on success, false on failure.
     */
    function caddyfile_conf($url_path = null) {
        $url_path = oneof($url_path,
                          parse_url(Config::current()->chyrp_url, PHP_URL_PATH),
                          "/");

        $filepath = MAIN_DIR.DIR."caddyfile";
        $template = INCLUDES_DIR.DIR."caddyfile.conf";

        if (!is_file($template) or !is_readable($template))
            return false;

        $caddyfile = preg_replace('~\\{chyrp_path\\}/?~',
                                 ltrim($url_path."/", "/"),
                                 file_get_contents($template));

        if (!file_exists($filepath))
            return @file_put_contents($filepath, $caddyfile);

        if (!is_file($filepath) or !is_readable($filepath))
            return false;

        if (!preg_match("~".preg_quote($caddyfile, "~")."~", file_get_contents($filepath)))
            return @file_put_contents($filepath, $caddyfile);

        return true;
    }

    /**
     * Function: nginx_conf
     * Creates the nginx configuration for Chyrp Lite or overwrites an existing file.
     *
     * Parameters:
     *     $url_path - The URL path to MAIN_DIR for the location directive.
     *
     * Returns:
     *     True if no action was needed, bytes written on success, false on failure.
     */
    function nginx_conf($url_path = null) {
        $url_path = oneof($url_path,
                          parse_url(Config::current()->chyrp_url, PHP_URL_PATH),
                          "/");

        $filepath = MAIN_DIR.DIR."include.conf";
        $template = INCLUDES_DIR.DIR."nginx.conf";

        if (!is_file($template) or !is_readable($template))
            return false;

        $caddyfile = preg_replace('~\\$chyrp_path/?~',
                                 ltrim($url_path."/", "/"),
                                 file_get_contents($template));

        if (!file_exists($filepath))
            return @file_put_contents($filepath, $caddyfile);

        if (!is_file($filepath) or !is_readable($filepath))
            return false;

        if (!preg_match("~".preg_quote($caddyfile, "~")."~", file_get_contents($filepath)))
            return @file_put_contents($filepath, $caddyfile);

        return true;
    }

    #---------------------------------------------
    # Localization
    #---------------------------------------------

    /**
     * Function: locales
     * Returns an array of locale choices for the "chyrp" domain.
     */
    function locales(): array {
        # Ensure the default locale is always present in the list.
        $locales = array(array("code" => "en_US",
                               "name" => lang_code("en_US")));

        $dir = new DirectoryIterator(INCLUDES_DIR.DIR."locale");

        foreach ($dir as $item) {
            if (!$item->isDot() and $item->isDir()) {
                $dirname = $item->getFilename();

                if ($dirname == "en_US")
                    continue;

                if (preg_match("/^[a-z]{2}(_|-)[a-z]{2}$/i", $dirname))
                    $locales[] = array("code" => $dirname,
                                       "name" => lang_code($dirname));
            }
        }

        return $locales;
    }

    /**
     * Function: set_locale
     * Sets the locale with fallbacks for platform-specific quirks.
     *
     * Parameters:
     *     $locale - The locale name, e.g. @en_US@, @uk_UA@, @fr_FR@
     */
    function set_locale($locale = "en_US"): void {
        $list = array($locale.".UTF-8",
                      $locale.".utf-8",
                      $locale.".UTF8",
                      $locale.".utf8");

        if (class_exists("Locale")) {
            # Generate a locale string for Windows.
            $list[] = Locale::getDisplayLanguage($locale, "en_US").
                    "_".Locale::getDisplayRegion($locale, "en_US").".utf8";

            # Set the ICU locale.
            Locale::setDefault($locale);
        }

        # Set the PHP locale.
        @putenv("LC_ALL=".$locale);
        setlocale(LC_ALL, $list);

        if (DEBUG)
            error_log("LOCALE ".setlocale(LC_CTYPE, 0));
    }

    /**
     * Function: get_locale
     * Gets the current locale setting.
     *
     * Notes:
     *     Does not use setlocale() because the return value is non-normative.
     */
    function get_locale(): string {
        if (INSTALLING or !file_exists(INCLUDES_DIR.DIR."config.json.php"))
            return isset($_REQUEST['locale']) ? $_REQUEST['locale'] : "en_US" ;

        return Config::current()->locale;
    }

    /**
     * Function: load_translator
     * Sets the path for a gettext translation domain.
     *
     * Parameters:
     *     $domain - The name of this translation domain.
     *     $locale - The path to the locale directory.
     */
    function load_translator($domain, $locale): void {
        if (USE_GETTEXT_SHIM and class_exists("Translation")) {
            Translation::current()->load($domain, $locale);
            return;
        }

        if (function_exists("bindtextdomain"))
            bindtextdomain($domain, $locale);

        if (function_exists("bind_textdomain_codeset"))
            bind_textdomain_codeset($domain, "UTF-8");
    }

    /**
     * Function: lang_code
     * Converts a language code to a localised display name.
     *
     * Parameters:
     *     $code - The language code to convert.
     *
     * Returns:
     *     A localised display name, e.g. "English (United States)".
     */
    function lang_code($code): string {
        return class_exists("Locale") ? Locale::getDisplayName($code, $code) : $code ;
    }

    /**
     * Function: lang_base
     * Extracts the primary language subtag for the supplied code.
     *
     * Parameters:
     *     $code - The language code to extract from.
     *
     * Returns:
     *     The primary subtag for this code, e.g. "en" from "en_US".
     */
    function lang_base($code): string {
        $code = str_replace("_", "-", $code);
        $tags = explode("-", $code);
        return ($tags === false) ? "en" : $tags[0] ;
    }

    /**
     * Function: __
     * Translates a string using gettext.
     *
     * Parameters:
     *     $text - The string to translate.
     *     $domain - The translation domain to read from.
     *
     * Returns:
     *     The translated string or the original.
     */
    function __($text, $domain = "chyrp"): string {
        if (USE_GETTEXT_SHIM)
            return Translation::current()->text($domain, $text);

        return function_exists("dgettext") ? dgettext($domain, $text) : $text ;
    }

    /**
     * Function: _p
     * Translates a plural (or not) form of a string.
     *
     * Parameters:
     *     $single - Singular string.
     *     $plural - Pluralized string.
     *     $number - The number to judge by.
     *     $domain - The translation domain to read from.
     *
     * Returns:
     *     The translated string or the original.
     */
    function _p($single, $plural, $number, $domain = "chyrp"): string {
        $int = (int) $number;

        if (USE_GETTEXT_SHIM)
            return Translation::current()->text($domain, $single, $plural, $int);

        return function_exists("dngettext") ?
            dngettext($domain, $single, $plural, $int) : (($int != 1) ? $plural : $single) ;
    }

    /**
     * Function: _f
     * Translates a string with sprintf() formatting.
     *
     * Parameters:
     *     $string - String to translate and format.
     *     $args - One arg or an array of arguments to format with.
     *     $domain - The translation domain to read from.
     *
     * Returns:
     *     The translated string or the original.
     */
    function _f($string, $args = array(), $domain = "chyrp"): string {
        $args = (array) $args;
        array_unshift($args, __($string, $domain));
        return call_user_func_array("sprintf", $args);
    }

    #---------------------------------------------
    # Time/Date
    #---------------------------------------------

    /**
     * Function: when
     * Formats a string that isn't a regular time() value.
     *
     * Parameters:
     *     $formatting - The formatting for date() or strftime().
     *     $when - A time value to be strtotime() converted.
     *
     * Returns:
     *     A time/date string with the supplied formatting.
     */
    function when($formatting, $when) {
        $time = is_numeric($when) ? $when : strtotime($when) ;
        return date($formatting, $time);
    }

    /**
     * Function: datetime
     * Formats datetime for SQL queries.
     *
     * Parameters:
     *     $when - A timestamp (optional).
     *
     * Returns:
     *     A standard datetime string.
     */
    function datetime($when = null) {
        fallback($when, time());

        $time = is_numeric($when) ? $when : strtotime($when) ;
        return date("Y-m-d H:i:s", $time);
    }

    /**
     * Function: now
     * Alias to strtotime, for prettiness like now("+1 day").
     */
    function now($when) {
        return strtotime($when);
    }

    /**
     * Function: timezones
     * Returns an array of timezone identifiers.
     */
    function timezones(): array {
        $timezones = array();
        $zone_list = timezone_identifiers_list(DateTimeZone::ALL);

        foreach ($zone_list as $zone) {
            $name = str_replace(array("_", "St "),
                                array(" ", "St. "), $zone);

            $timezones[] = array("code" => $zone,
                                 "name" => $name);
        }

        return $timezones;
    }

    /**
     * Function: set_timezone
     * Sets the timezone for all date/time functions.
     *
     * Parameters:
     *     $timezone - The timezone to set.
     */
    function set_timezone($timezone = "Atlantic/Reykjavik"): bool {
        $result = date_default_timezone_set($timezone);

        if (DEBUG)
            error_log("TIMEZONE ".get_timezone());

        return $result;
    }

    /**
     * Function: get_timezone
     * Gets the timezone for all date/time functions.
     */
    function get_timezone(): string {
        return date_default_timezone_get();
    }

    #---------------------------------------------
    # Variable Manipulation
    #---------------------------------------------

    /**
     * Function: fallback
     * Sets the supplied variable if it is not already set.
     *
     * Parameters:
     *     &$variable - The variable to return or set.
     *
     * Returns:
     *     The value that was assigned to the variable.
     *
     * Notes:
     *     The value will be the first non-empty argument,
     *     or the last, or null if no arguments are supplied.
     */
    function fallback(&$variable) {
        if (is_bool($variable))
            return $variable;

        $unset = (!isset($variable) or $variable === array() or
                 (is_string($variable) and trim($variable) === ""));

        if (!$unset)
            return $variable;

        $fallback = null;
        $args = func_get_args();
        array_shift($args);

        foreach ($args as $arg) {
            $fallback = $arg;

            $nonempty = (isset($arg) and $arg !== array() and
                        (!is_string($arg) or (is_string($arg) and trim($arg) !== "")));

            if ($nonempty)
                break;
        }

        return $variable = $fallback;
    }

    /**
     * Function: oneof
     * Returns a value from the supplied set of arguments.
     *
     * Returns:
     *     The first non-empty argument, or the last, or null.
     *
     * Notes:
     *     It will guess where to stop based on types,
     *     e.g. "" has priority over array() but not 1.
     */
    function oneof() {
        $last = null;
        $args = func_get_args();

        foreach ($args as $index => $arg) {
            $unset = (!isset($arg) or $arg === array() or
                     (is_string($arg) and trim($arg) === "") or
                     (is_object($arg) and empty($arg)) or
                     ($arg === "0000-00-00 00:00:00") or
                     ($arg === "0001-01-01 00:00:00"));

            if (!$unset)
                return $arg;

            $last = $arg;

            if ($index + 1 == count($args))
                break;

            $next = $args[$index + 1];

            # This is a big check but it should cover most "incomparable" cases.
            # Using simple type comparison wouldn't work too well, for example:
            # in oneof("", 1) "" would take priority over 1 because of type difference.
            $incomparable = ((is_array($arg) and !is_array($next)) or
                             (!is_array($arg) and is_array($next)) or
                             (is_object($arg) and !is_object($next)) or
                             (!is_object($arg) and is_object($next)) or
                             (is_resource($arg) and !is_resource($next)) or
                             (!is_resource($arg) and is_resource($next)));

            if (isset($arg) and isset($next) and $incomparable)
                return $arg;
        }

        return $last;
    }

    /**
     * Function: derezz
     * Strips tags and junk from the supplied variable and tests it for emptiness.
     *
     * Parameters:
     *     &$variable - The variable, supplied by reference.
     *
     * Returns:
     *     Whether or not the stripped variable is empty.
     *
     * Notes:
     *     Useful for data that will be stripped later on by its model
     *     but which needs to be tested for uniqueness/emptiness first.
     */
    function derezz(&$variable): bool {
        $variable = str_replace(array("\n", "\r", "\0"), "", strip_tags($variable));
        return empty($variable);
    }

    /**
     * Function: token
     * Salt and hash a unique token using the supplied data.
     *
     * Parameters:
     *     $items - An array of items to hash.
     *
     * Returns:
     *     A unique token salted with the site's secure hashkey.
     */
    function token($items): string {
        return sha1(implode((array) $items).Config::current()->secure_hashkey);
    }

    /**
     * Function: slug
     * Generates a random slug value for posts and pages.
     *
     * Parameters:
     *     $length - The number of characters to generate.
     *
     * Returns:
     *     A string of the requested length.
     */
    function slug($length): string {
        return strtolower(random($length));
    }

    /**
     * Function: authenticate
     * Generates or validates an authentication token for the visitor.
     *
     * Parameters:
     *     $hash - A previously generated token to be validated (optional).
     *
     * Returns:
     *     An authentication token, or the validity of the supplied token.
     */
    function authenticate($hash = null) {
        Trigger::current()->call("visitor_authenticate");

        $id = session_id();
        return isset($hash) ? (token($id) == $hash) : (($id == "") ? "" : token($id)) ;
    }

    /**
     * Function: random
     * Generates a string of alphanumeric random characters.
     *
     * Parameters:
     *     $length - The number of characters to generate.
     *
     * Returns:
     *     A string of the requested length.
     *
     * Notes:
     *     Uses a cryptographically secure pseudo-random method.
     */
    function random($length): string {
        $input = "1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
        $range = strlen($input) - 1;
        $chars = "";

        for ($i = 0; $i < $length; $i++)
            $chars.= $input[random_int(0, $range)];

        return $chars;
    }

    /**
     * Function: shorthand_bytes
     * Decode shorthand bytes notation from php.ini.
     *
     * Parameters:
     *     $value - The value returned by ini_get().
     *
     * Returns:
     *     A byte value or the input if decoding failed.
     */
    function shorthand_bytes($value) {
        switch (substr($value, -1)) {
            case "K": case "k":
                return (int) $value * 1024;
            case "M": case "m":
                return (int) $value * 1048576;
            case "G": case "g":
                return (int) $value * 1073741824;
            default:
                return $value;
        }
    }

    /**
     * Function: timer_start
     * Starts the internal timer and returns the microtime.
     */
    function timer_start(): float {
        static $timer;

        if (!isset($timer))
            $timer = microtime(true);

        return $timer;
    }

    /**
     * Function: timer_stop
     * Returns the elapsed time since the timer started.
     *
     * Parameters:
     *     $precision - Round to n decimal places.
     *
     * Returns:
     *     A formatted number with the requested $precision.
     */
    function timer_stop($precision = 3): string {
        $elapsed = microtime(true) - timer_start();
        return number_format($elapsed, $precision, ".", "");
    }

    /**
     * Function: match_any
     * Try to match a string against an array of regular expressions.
     *
     * Parameters:
     *     $try - An array of regular expressions, or a single regular expression.
     *     $haystack - The string to test.
     *
     * Returns:
     *     Whether or not the match succeeded.
     */
    function match_any($try, $haystack): bool {
        foreach ((array) $try as $needle)
            if (preg_match($needle, $haystack))
                return true;

        return false;
    }

    /**
     * Function: autoload
     * Autoload PSR-0 classes on demand by scanning lib directories.
     *
     * Parameters:
     *     $class - The name of the class to load.
     */
    function autoload($class): void {
        $filepath = str_replace(array("_", "\\", "\0"),
                                array(DIR, DIR, ""),
                                ltrim($class, "\\")).".php";

        if (is_file(INCLUDES_DIR.DIR."lib".DIR.$filepath)) {
            require INCLUDES_DIR.DIR."lib".DIR.$filepath;
            return;
        }

        if (!INSTALLING and !UPGRADING)
            foreach (Config::current()->enabled_modules as $module)
                if (is_file(MODULES_DIR.DIR.$module.DIR."lib".DIR.$filepath)) {
                    require MODULES_DIR.DIR.$module.DIR."lib".DIR.$filepath;
                    return;
                }
    }

    /**
     * Function: keywords
     * Parse keyword searches for values in specific database columns.
     *
     * Parameters:
     *     $query - The query to parse.
     *     $plain - WHERE syntax to search for non-keyword queries.
     *     $table - Check this table to ensure the keywords are valid.
     *
     * Returns:
     *     An array containing the "WHERE" queries and the corresponding parameters.
     */
    function keywords($query, $plain, $table = null): array {
        $trimmed = trim($query);

        if (empty($trimmed))
            return array(array(), array());

        $sql = SQL::current();

        # PostgreSQL: use ILIKE operator for case-insensitivity.
        if ($sql->adapter == "pgsql")
            $plain = str_replace(" LIKE ", " ILIKE ", $plain);

        $strings  = array(); # Non-keyword values found in the query.
        $keywords = array(); # Keywords (attr:val;) found in the query.
        $where    = array(); # Parameters validated and added to WHERE.
        $filters  = array(); # Table column filters to be validated.
        $params   = array(); # Parameters for the non-keyword filter.
        $columns  = !empty($table) ? $sql->select($table)->fetch() : array() ;

        foreach (preg_split("/\s(?=\w+:)|;/", $query, -1, PREG_SPLIT_NO_EMPTY) as $fragment) {
            if (!substr_count($fragment, ":"))
                $strings[] = trim($fragment);
            else
                $keywords[] = trim($fragment);
        }

        $dates = array("year", "month", "day", "hour", "minute", "second");

        $created_at = array(
            "year"   => "____",
            "month"  => "__",
            "day"    => "__",
            "hour"   => "__",
            "minute" => "__",
            "second" => "__");

        $joined_at = array(
            "year"   => "____",
            "month"  => "__",
            "day"    => "__",
            "hour"   => "__",
            "minute" => "__",
            "second" => "__");

        # Contextual conversions of some keywords.
        foreach ($keywords as $keyword) {
            list($attr, $val) = explode(":", $keyword);

            if ($attr == "password") {
                # Prevent searches for hashed passwords.
                $strings[] = $attr;
            } elseif (isset($columns["user_id"]) and $attr == "author") {
                # Filter by "author" (login).
                $user = new User(array("login" => $val));
                $where["user_id"] = ($user->no_results) ? 0 : $user->id ;
            } elseif (isset($columns["group_id"]) and $attr == "group") {
                # Filter by group name.
                $group = new Group(array("name" => $val));
                $where["group_id"] = ($group->no_results) ? 0 : $group->id ;
            } elseif (isset($columns["created_at"]) and in_array($attr, $dates)) {
                # Filter by date/time of creation.
                $created_at[$attr] = $val;
                $where["created_at LIKE"] = $created_at["year"]."-".
                                            $created_at["month"]."-".
                                            $created_at["day"]." ".
                                            $created_at["hour"].":".
                                            $created_at["minute"].":".
                                            $created_at["second"]."%";
            } elseif (isset($columns["joined_at"]) and in_array($attr, $dates)) {
                # Filter by date/time of joining.
                $joined_at[$attr] = $val;
                $where["joined_at LIKE"] = $joined_at["year"]."-".
                                           $joined_at["month"]."-".
                                           $joined_at["day"]." ".
                                           $joined_at["hour"].":".
                                           $joined_at["minute"].":".
                                           $joined_at["second"]."%";
            } else {
                # Key => Val expression.
                $filters[$attr] = $val;
            }
        }

        # Check the validity of keywords if a table name was supplied.
        foreach ($filters as $attr => $val) {
            if (isset($columns[$attr]))
                $where[$attr] = $val;
            else
                $strings[] = $attr." ".$val; # No such column: add to non-keyword values.
        }

        if (!empty($strings)) {
            $where[] = $plain;
            $params[":query"] = "%".implode(" ", $strings)."%";
        }

        $search = array($where, $params);

        Trigger::current()->filter($search, "keyword_search", $query, $plain);

        return $search;
    }

    #---------------------------------------------
    # String Manipulation
    #---------------------------------------------

    /**
     * Function: pluralize
     * Pluralizes a word.
     *
     * Parameters:
     *     $string - The lowercase string to pluralize.
     *     $number - If supplied, and this number is 1, it will not pluralize.
     *
     * Returns:
     *     The supplied word with a trailing "s" added, or a non-normative pluralization.
     */
    function pluralize($string, $number = null): string {
        $uncountable = array("audio", "equipment", "fish", "information", "money",
                             "moose", "news", "rice", "series", "sheep", "species");

        if (in_array($string, $uncountable) or $number == 1)
            return $string;

        $replacements = array(
            "/person/i"                    => "people",
            "/^(wom|m)an$/i"               => "\\1en",
            "/child/i"                     => "children",
            "/cow/i"                       => "kine",
            "/goose/i"                     => "geese",
            "/datum$/i"                    => "data",
            "/(penis)$/i"                  => "\\1es",
            "/(ax|test)is$/i"              => "\\1es",
            "/(octop|vir)us$/i"            => "\\1ii",
            "/(cact)us$/i"                 => "\\1i",
            "/(alias|status)$/i"           => "\\1es",
            "/(bu)s$/i"                    => "\\1ses",
            "/(buffal|tomat)o$/i"          => "\\1oes",
            "/([ti])um$/i"                 => "\\1a",
            "/sis$/i"                      => "ses",
            "/(hive)$/i"                   => "\\1s",
            "/([^aeiouy]|qu)y$/i"          => "\\1ies",
            "/^(ox)$/i"                    => "\\1en",
            "/(matr|vert|ind)(?:ix|ex)$/i" => "\\1ices",
            "/(x|ch|ss|sh)$/i"             => "\\1es",
            "/([m|l])ouse$/i"              => "\\1ice",
            "/(quiz)$/i"                   => "\\1zes"
        );

        $replaced = preg_replace(array_keys($replacements),
                                 array_values($replacements), $string, 1);

        if ($replaced == $string)
            $replaced = $string."s";

        return $replaced;
    }

    /**
     * Function: depluralize
     * Singularizes a word.
     *
     * Parameters:
     *     $string - The lowercase string to depluralize.
     *     $number - If supplied, and this number is not 1, it will not depluralize.
     *
     * Returns:
     *     The supplied word with trailing "s" removed, or a non-normative singularization.
     */
    function depluralize($string, $number = null): string {
        $uncountable = array("news", "series", "species");

        if (in_array($string, $uncountable) or (isset($number) and $number != 1))
            return $string;

        $replacements = array(
            "/people/i"               => "person",
            "/^(wom|m)en$/i"          => "\\1an",
            "/children/i"             => "child",
            "/kine/i"                 => "cow",
            "/geese/i"                => "goose",
            "/data$/i"                => "datum",
            "/(penis)es$/i"           => "\\1",
            "/(ax|test)es$/i"         => "\\1is",
            "/(octopi|viri|cact)i$/i" => "\\1us",
            "/(alias|status)es$/i"    => "\\1",
            "/(bu)ses$/i"             => "\\1s",
            "/(buffal|tomat)oes$/i"   => "\\1o",
            "/([ti])a$/i"             => "\\1um",
            "/ses$/i"                 => "sis",
            "/(hive)s$/i"             => "\\1",
            "/([^aeiouy]|qu)ies$/i"   => "\\1y",
            "/^(ox)en$/i"             => "\\1",
            "/(vert|ind)ices$/i"      => "\\1ex",
            "/(matr)ices$/i"          => "\\1ix",
            "/(x|ch|ss|sh)es$/i"      => "\\1",
            "/([ml])ice$/i"           => "\\1ouse",
            "/(quiz)zes$/i"           => "\\1"
        );

        $replaced = preg_replace(array_keys($replacements),
                                 array_values($replacements), $string, 1);

        if ($replaced == $string and substr($string, -1) == "s")
            $replaced = substr($string, 0, -1);

        return $replaced;
    }

    /**
     * Function: normalize
     * Attempts to normalize all newlines and whitespace into single spaces.
     *
     * Returns:
     *     The normalized string.
     */
    function normalize($string): string {
        return trim(preg_replace("/[\s\n\r\t]+/", " ", $string));
    }

    /**
     * Function: camelize
     * Converts a string to camel-case.
     *
     * Parameters:
     *     $string - The string to camelize.
     *     $keep_spaces - Whether or not to convert underscores to spaces or remove them.
     *
     * Returns:
     *     A CamelCased string.
     *
     * See Also:
     *     <decamelize>
     */
    function camelize($string, $keep_spaces = false): string {
        $lowercase = strtolower($string);
        $deunderscore = str_replace("_", " ", $lowercase);
        $dehyphen = str_replace("-", " ", $deunderscore);
        $camelized = ucwords($dehyphen);

        if (!$keep_spaces)
            $camelized = str_replace(" ", "", $camelized);

        return $camelized;
    }

    /**
     * Function: decamelize
     * Undoes camel-case conversion.
     *
     * Parameters:
     *     $string - The string to decamelize.
     *
     * Returns:
     *     A de_camel_cased string.
     *
     * See Also:
     *     <camelize>
     */
    function decamelize($string): string {
        return strtolower(preg_replace("/([a-z])([A-Z])/", "\\1_\\2", $string));
    }

    /**
     * Function: truncate
     * Truncates a string to the requested number of characters or less.
     *
     * Parameters:
     *     $text - The string to be truncated.
     *     $length - Truncate the string to this number of characters.
     *     $ellipsis - A string to place at the truncation point.
     *     $exact - Split words to return the exact length requested?
     *     $encoding - The character encoding of the string and ellipsis.
     *
     * Returns:
     *     A truncated string with ellipsis appended.
     */
    function truncate($text, $length = 100, $ellipsis = "...", $exact = false, $encoding = "UTF-8"): string {
        if (function_exists("mb_strlen") and function_exists("mb_substr")) {
            if (mb_strlen($text, $encoding) <= $length)
                return $text;

            $breakpoint = $length - mb_strlen($ellipsis, $encoding);
            $truncation = mb_substr($text, 0, $breakpoint, $encoding);
            $remainder  = mb_substr($text, $breakpoint, null, $encoding);
        } else {
            if (strlen($text) <= $length)
                return $text;

            $breakpoint = $length - strlen($ellipsis);
            $truncation = substr($text, 0, $breakpoint);
            $remainder  = substr($text, $breakpoint);
        }

        if (!$exact and !preg_match("/^\s/", $remainder))
            $truncation = preg_replace("/(.+)\s.*/s", "$1", $truncation);

        return $truncation.$ellipsis;
    }

    /**
     * Function: markdown
     * Implements the Markdown content parsing filter.
     *
     * Parameters:
     *     $text - The body of the post/page to parse.
     *
     * Returns:
     *     The text with Markdown formatting applied.
     *
     * Se Also:
     *     https://github.com/commonmark/CommonMark
     *     https://github.github.com/gfm/
     */
    function markdown($text): string {
        $parser = new \cebe\markdown\GithubMarkdown();
        $parser->html5 = true;
        $parser->keepListStartNumber = true;
        return $parser->parse($text);
    }

    /**
     * Function: emote
     * Converts emoticons to Unicode emoji HTML entities.
     *
     * Parameters:
     *     $text - The body of the post/page to parse.
     *
     * Returns:
     *     The text with emoticons replaced by emoji.
     *
     * See Also:
     *     http://www.unicode.org/charts/PDF/U1F600.pdf
     */
    function emote($text): string {
        $emoji = array(
            "o:-)"    => "&#x1f607;",
            "&gt;:-)" => "&#x1f608;",
            ">:-)"    => "&#x1f608;",
            ":-)"     => "&#x1f600;",
            "^_^"     => "&#x1f601;",
            ":-D"     => "&#x1f603;",
            ";-)"     => "&#x1f609;",
            "&lt;3"   => "&#x1f60d;",
            "<3"      => "&#x1f60d;",
            "B-)"     => "&#x1f60e;",
            ":-&gt;"  => "&#x1f60f;",
            ":->"     => "&#x1f60f;",
            ":-||"    => "&#x1f62c;",
            ":-|"     => "&#x1f611;",
            "-_-"     => "&#x1f612;",
            ":-/"     => "&#x1f615;",
            ":-s"     => "&#x1f616;",
            ":-*"     => "&#x1f618;",
            ":-P"     => "&#x1f61b;",
            ":-(("    => "&#x1f629;",
            ":-("     => "&#x1f61f;",
            ";_;"     => "&#x1f622;",
            ":-o"     => "&#x1f62e;",
            "O_O"     => "&#x1f632;",
            ":-$"     => "&#x1f633;",
            "x_x"     => "&#x1f635;",
            ":-x"     => "&#x1f636;"
        );

        foreach ($emoji as $key => $value)
            $text = str_replace($key, '<span class="emoji">'.$value.'</span>', $text);

        return $text;
    }

    /**
     * Function: fix
     * Neutralizes HTML and quotes in strings for display.
     *
     * Parameters:
     *     $string - String to fix.
     *     $quotes - Encode quotes?
     *     $double - Encode encoded?
     *
     * Returns:
     *     A sanitized version of the string.
     */
    function fix($string, $quotes = false, $double = false): string {
        $quotes = ($quotes) ? ENT_QUOTES : ENT_NOQUOTES ;
        return htmlspecialchars((string) $string, $quotes | ENT_HTML5, "UTF-8", $double);
    }

    /**
     * Function: unfix
     * Undoes neutralization of HTML and quotes in strings.
     *
     * Parameters:
     *     $string - String to unfix.
     *     $all - Decode all entities?
     *
     * Returns:
     *     An unsanitary version of the string.
     */
    function unfix($string, $all = false): string {
        return ($all) ?
            html_entity_decode((string) $string, ENT_QUOTES | ENT_HTML5, "UTF-8") :
            htmlspecialchars_decode((string) $string, ENT_QUOTES | ENT_HTML5) ;
    }

    /**
     * Function: sanitize
     * Sanitizes a string of troublesome characters, typically for use in URLs.
     *
     * Parameters:
     *     $string - The string to sanitize - must be ASCII or UTF-8!
     *     $lowercase - Force the string to lowercase?
     *     $strict - Remove all characters except "-" and alphanumerics?
     *     $truncate - Number of characters to truncate to (default 100, 0 to disable).
     *
     * Returns:
     *     A sanitized version of the string.
     */
    function sanitize($string, $lowercase = true, $strict = false, $truncate = 100): string {
        $strip = array("&amp;", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;", "&",
                       "~", "`", "!", "@", "#", "$", "%", "^", "*", "(", ")", "_", "=", "+", "[", "{",
                       "]", "}", "\\", "|", ";", ":", "\"", "'", "—", "–", ",", "<", ".", ">", "/", "?");

        # Strip tags, remove punctuation and HTML entities, replace spaces with hyphen-minus.
        $clean = preg_replace("/\s+/", "-", trim(str_replace($strip, "", strip_tags($string))));

        if ($strict) {
            # Discover UTF-8 multi-byte encodings and attempt substitutions.
            if (preg_match("/[\x80-\xff]/", $clean))
                $clean = strtr($clean, array(
                    # Latin-1 Supplement.
                    chr(194).chr(170) => "a",  chr(194).chr(186) => "o",  chr(195).chr(128) => "A",
                    chr(195).chr(129) => "A",  chr(195).chr(130) => "A",  chr(195).chr(131) => "A",
                    chr(195).chr(132) => "A",  chr(195).chr(133) => "A",  chr(195).chr(134) => "AE",
                    chr(195).chr(135) => "C",  chr(195).chr(136) => "E",  chr(195).chr(137) => "E",
                    chr(195).chr(138) => "E",  chr(195).chr(139) => "E",  chr(195).chr(140) => "I",
                    chr(195).chr(141) => "I",  chr(195).chr(142) => "I",  chr(195).chr(143) => "I",
                    chr(195).chr(144) => "D",  chr(195).chr(145) => "N",  chr(195).chr(146) => "O",
                    chr(195).chr(147) => "O",  chr(195).chr(148) => "O",  chr(195).chr(149) => "O",
                    chr(195).chr(150) => "O",  chr(195).chr(153) => "U",  chr(195).chr(154) => "U",
                    chr(195).chr(155) => "U",  chr(195).chr(156) => "U",  chr(195).chr(157) => "Y",
                    chr(195).chr(158) => "TH", chr(195).chr(159) => "s",  chr(195).chr(160) => "a",
                    chr(195).chr(161) => "a",  chr(195).chr(162) => "a",  chr(195).chr(163) => "a",
                    chr(195).chr(164) => "a",  chr(195).chr(165) => "a",  chr(195).chr(166) => "ae",
                    chr(195).chr(167) => "c",  chr(195).chr(168) => "e",  chr(195).chr(169) => "e",
                    chr(195).chr(170) => "e",  chr(195).chr(171) => "e",  chr(195).chr(172) => "i",
                    chr(195).chr(173) => "i",  chr(195).chr(174) => "i",  chr(195).chr(175) => "i",
                    chr(195).chr(176) => "d",  chr(195).chr(177) => "n",  chr(195).chr(178) => "o",
                    chr(195).chr(179) => "o",  chr(195).chr(180) => "o",  chr(195).chr(181) => "o",
                    chr(195).chr(182) => "o",  chr(195).chr(184) => "o",  chr(195).chr(185) => "u",
                    chr(195).chr(186) => "u",  chr(195).chr(187) => "u",  chr(195).chr(188) => "u",
                    chr(195).chr(189) => "y",  chr(195).chr(190) => "th", chr(195).chr(191) => "y",
                    chr(195).chr(152) => "O",
                    # Latin Extended-A.
                    chr(196).chr(128) => "A",  chr(196).chr(129) => "a",  chr(196).chr(130) => "A",
                    chr(196).chr(131) => "a",  chr(196).chr(132) => "A",  chr(196).chr(133) => "a",
                    chr(196).chr(134) => "C",  chr(196).chr(135) => "c",  chr(196).chr(136) => "C",
                    chr(196).chr(137) => "c",  chr(196).chr(138) => "C",  chr(196).chr(139) => "c",
                    chr(196).chr(140) => "C",  chr(196).chr(141) => "c",  chr(196).chr(142) => "D",
                    chr(196).chr(143) => "d",  chr(196).chr(144) => "D",  chr(196).chr(145) => "d",
                    chr(196).chr(146) => "E",  chr(196).chr(147) => "e",  chr(196).chr(148) => "E",
                    chr(196).chr(149) => "e",  chr(196).chr(150) => "E",  chr(196).chr(151) => "e",
                    chr(196).chr(152) => "E",  chr(196).chr(153) => "e",  chr(196).chr(154) => "E",
                    chr(196).chr(155) => "e",  chr(196).chr(156) => "G",  chr(196).chr(157) => "g",
                    chr(196).chr(158) => "G",  chr(196).chr(159) => "g",  chr(196).chr(160) => "G",
                    chr(196).chr(161) => "g",  chr(196).chr(162) => "G",  chr(196).chr(163) => "g",
                    chr(196).chr(164) => "H",  chr(196).chr(165) => "h",  chr(196).chr(166) => "H",
                    chr(196).chr(167) => "h",  chr(196).chr(168) => "I",  chr(196).chr(169) => "i",
                    chr(196).chr(170) => "I",  chr(196).chr(171) => "i",  chr(196).chr(172) => "I",
                    chr(196).chr(173) => "i",  chr(196).chr(174) => "I",  chr(196).chr(175) => "i",
                    chr(196).chr(176) => "I",  chr(196).chr(177) => "i",  chr(196).chr(178) => "IJ",
                    chr(196).chr(179) => "ij", chr(196).chr(180) => "J",  chr(196).chr(181) => "j",
                    chr(196).chr(182) => "K",  chr(196).chr(183) => "k",  chr(196).chr(184) => "k",
                    chr(196).chr(185) => "L",  chr(196).chr(186) => "l",  chr(196).chr(187) => "L",
                    chr(196).chr(188) => "l",  chr(196).chr(189) => "L",  chr(196).chr(190) => "l",
                    chr(196).chr(191) => "L",  chr(197).chr(128) => "l",  chr(197).chr(129) => "L",
                    chr(197).chr(130) => "l",  chr(197).chr(131) => "N",  chr(197).chr(132) => "n",
                    chr(197).chr(133) => "N",  chr(197).chr(134) => "n",  chr(197).chr(135) => "N",
                    chr(197).chr(136) => "n",  chr(197).chr(137) => "N",  chr(197).chr(138) => "n",
                    chr(197).chr(139) => "N",  chr(197).chr(140) => "O",  chr(197).chr(141) => "o",
                    chr(197).chr(142) => "O",  chr(197).chr(143) => "o",  chr(197).chr(144) => "O",
                    chr(197).chr(145) => "o",  chr(197).chr(146) => "OE", chr(197).chr(147) => "oe",
                    chr(197).chr(148) => "R",  chr(197).chr(149) => "r",  chr(197).chr(150) => "R",
                    chr(197).chr(151) => "r",  chr(197).chr(152) => "R",  chr(197).chr(153) => "r",
                    chr(197).chr(154) => "S",  chr(197).chr(155) => "s",  chr(197).chr(156) => "S",
                    chr(197).chr(157) => "s",  chr(197).chr(158) => "S",  chr(197).chr(159) => "s",
                    chr(197).chr(160) => "S",  chr(197).chr(161) => "s",  chr(197).chr(162) => "T",
                    chr(197).chr(163) => "t",  chr(197).chr(164) => "T",  chr(197).chr(165) => "t",
                    chr(197).chr(166) => "T",  chr(197).chr(167) => "t",  chr(197).chr(168) => "U",
                    chr(197).chr(169) => "u",  chr(197).chr(170) => "U",  chr(197).chr(171) => "u",
                    chr(197).chr(172) => "U",  chr(197).chr(173) => "u",  chr(197).chr(174) => "U",
                    chr(197).chr(175) => "u",  chr(197).chr(176) => "U",  chr(197).chr(177) => "u",
                    chr(197).chr(178) => "U",  chr(197).chr(179) => "u",  chr(197).chr(180) => "W",
                    chr(197).chr(181) => "w",  chr(197).chr(182) => "Y",  chr(197).chr(183) => "y",
                    chr(197).chr(184) => "Y",  chr(197).chr(185) => "Z",  chr(197).chr(186) => "z",
                    chr(197).chr(187) => "Z",  chr(197).chr(188) => "z",  chr(197).chr(189) => "Z",
                    chr(197).chr(190) => "z",  chr(197).chr(191) => "s"
                    # Generate additional substitution keys using: e.g. echo implode(",", unpack("C*", "€"));
                ));

            # Remove any characters that remain after substitution.
            $clean = preg_replace("/[^a-zA-Z0-9\\-]/", "", $clean);
        }

        if ($lowercase)
            $clean = function_exists("mb_strtolower") ?
                mb_strtolower($clean, "UTF-8") : strtolower($clean) ;

        if ($truncate)
            $clean = function_exists("mb_substr") ?
                mb_substr($clean, 0, $truncate, "UTF-8") : substr($clean, 0, $truncate) ;

        return $clean;
    }

    /**
     * Function: sanitize_html
     * Sanitizes HTML to disable scripts and obnoxious attributes.
     *
     * Parameters:
     *     $string - String containing HTML to sanitize.
     *
     * Returns:
     *     A version of the string containing only valid tags and whitelisted attributes.
     */
    function sanitize_html($text): string {
        # Strip invalid tags.
        $text = preg_replace("/<([^a-z\/!]|\/(?![a-z])|!(?!--))[^>]*>/i", " ", $text);

        # Strip style tags.
        $text = preg_replace("/<\/?style[^>]*>/i", " ", $text);

        # Strip script tags.
        $text = preg_replace("/<\/?script[^>]*>/i", " ", $text);

        # Strip attributes from each tag, but allow attributes essential to a tag's function.
        return preg_replace_callback("/<([a-z][a-z0-9]*)[^>]*?( ?\/)?>/i", function ($element) {
            fallback($element[2], "");

            $name = strtolower($element[1]);
            $whitelist = "";

            preg_match_all("/ ([a-z]+)=(\"[^\"]+\"|\'[^\']+\')/i", $element[0], $attributes, PREG_SET_ORDER);

            foreach ($attributes as $attribute) {
                $label = strtolower($attribute[1]);
                $content = trim($attribute[2], "\"'");

                switch ($label) {
                    case "src":
                        if (in_array($name, array("audio",
                                                  "iframe",
                                                  "img",
                                                  "source",
                                                  "track",
                                                  "video")) and is_url($content)) {

                            $whitelist.= $attribute[0];
                        }

                        break;

                    case "href":
                        if (in_array($name, array("a",
                                                  "area")) and is_url($content)) {

                            $whitelist.= $attribute[0];
                        }

                        break;

                    case "alt":
                        if (in_array($name, array("area",
                                                  "img"))) {

                            $whitelist.= $attribute[0];
                        }

                        break;
                }
            }

            return "<".$element[1].$whitelist.$element[2].">";

        }, $text);
    }

    #---------------------------------------------
    # Remote Fetches
    #---------------------------------------------

    /**
     * Function: get_remote
     * Retrieve the contents of a URL.
     *
     * Parameters:
     *     $url - The URL of the resource to be retrieved.
     *     $redirects - The maximum number of redirects to follow.
     *     $timeout - The maximum number of seconds to wait.
     *     $headers - Include response headers with the content?
     *
     * Returns:
     *     The response content, or false on failure.
     */
    function get_remote($url, $redirects = 0, $timeout = 10, $headers = false) {
        extract(parse_url(add_scheme($url)), EXTR_SKIP);
        fallback($path, "/");
        fallback($scheme, "http");
        fallback($port, ($scheme == "https") ? 443 : 80);

        if (isset($query))
            $path.= "?".$query;

        if (!isset($host))
            return false;

        if ($scheme == "https" and !extension_loaded("openssl"))
            return false;

        $prefix  = ($scheme == "https") ? "tls://" : "tcp://" ;
        $connect = @fsockopen($prefix.$host, $port, $errno, $errstr, $timeout);

        if (!$connect) {
            trigger_error(_f("Socket error: %s", fix($errstr, false, true)), E_USER_NOTICE);
            return false;
        }

        $remote_headers = "";
        $remote_content = "";

        # Send the GET headers.
        fwrite($connect,
            "GET ".$path." HTTP/1.0\r\n".
            "Host: ".$host."\r\n".
            "Connection: close"."\r\n".
            "User-Agent: ".CHYRP_IDENTITY."\r\n\r\n");

        # Receive response headers.
        while (!feof($connect) and strpos($remote_headers, "\r\n\r\n") === false)
            $remote_headers.= fgets($connect);

        # Search for 4XX or 5XX error codes.
        if (preg_match("~^HTTP/[0-9]\.[0-9] [4-5][0-9][0-9]~m", $remote_headers)) {
            fclose($connect);
            return false;
        }

        # Search for 301/302 and recurse with new location unless redirects are exhausted.
        if (preg_match("~^HTTP/[0-9]\.[0-9] 30[1-2]~m", $remote_headers)) {
            if ($redirects > 0) {
                if (preg_match("~^Location: (.+)$~mi", $remote_headers, $matches)) {
                    $location = trim($matches[1]);

                    if (is_url($location)) {
                        fclose($connect);
                        return get_remote($location, $redirects - 1, $timeout, $headers);
                    }
                }
            }

            fclose($connect);
            return false;
        }

        # Receive the response content.
        while (!feof($connect))
            $remote_content.= fgets($connect);

        fclose($connect);
        return ($headers) ? $remote_headers.$remote_content : $remote_content ;
    }

    /**
     * Function: grab_urls
     * Crawls a string and grabs hyperlinks from it.
     *
     * Parameters:
     *     $string - The string to crawl.
     *
     * Returns:
     *     An array of all URLs found in the string.
     */
    function grab_urls($string): array {
        # These expressions capture hyperlinks in HTML and unfiltered Markdown.
        $expressions = array("/<a[^>]* href=(\"[^\"]+\"|\'[^\']+\')[^>]*>[^<]+<\/a>/i",
                             "/\[[^\]]+\]\(([^\)]+)\)/");

        # Modules can support other syntaxes.
        Trigger::current()->filter($expressions, "link_regexp");

        $urls = array();

        foreach ($expressions as $expression) {
            preg_match_all($expression, stripslashes($string), $matches);
            $urls = array_merge($urls, $matches[1]);
        }

        foreach ($urls as &$url)
            $url = trim($url, " \"'");

        return array_filter(array_unique($urls), "is_url");
    }

    /**
     * Function: send_pingbacks
     * Sends pingback requests to the URLs in a string.
     *
     * Parameters:
     *     $string - The string to crawl for pingback URLs.
     *     $post - The post we're sending from.
     *     $limit - Timer limit for this function (optional).
     */
    function send_pingbacks($string, $post, $limit = 30): void {
        foreach (grab_urls($string) as $url) {
            if (timer_stop() > $limit)
                break;

            $ping_url = pingback_url(unfix($url, true));

            if ($ping_url !== false and is_url($ping_url)) {
                $client = new IXR_Client(add_scheme($ping_url));

                if ($client->transport == "tls" and !extension_loaded("openssl"))
                    continue;

                $client->timeout = 3;
                $client->useragent = CHYRP_IDENTITY;
                $client->query("pingback.ping", unfix($post->url()), $url);
            }
        }
    }

    /**
     * Function: pingback_url
     * Checks if a URL is pingback-capable.
     *
     * Parameters:
     *     $url - The URL to check.
     *
     * Returns:
     *     The pingback target, or false on failure.
     */
    function pingback_url($url) {
        extract(parse_url(add_scheme($url)), EXTR_SKIP);
        fallback($path, "/");
        fallback($scheme, "http");
        fallback($port, ($scheme == "https") ? 443 : 80);

        if (isset($query))
            $path.= "?".$query;

        if (!isset($host))
            return false;

        if ($scheme == "https" and !extension_loaded("openssl"))
            return false;

        $prefix  = ($scheme == "https") ? "tls://" : "tcp://" ;
        $connect = @fsockopen($prefix.$host, $port, $errno, $errstr, 3);

        if (!$connect) {
            trigger_error(_f("Socket error: %s", fix($errstr, false, true)), E_USER_NOTICE);
            return false;
        }

        $remote_headers = "";
        $remote_content = "";

        # Send the GET headers.
        fwrite($connect,
            "GET ".$path." HTTP/1.0\r\n".
            "Host: ".$host."\r\n".
            "Connection: close"."\r\n".
            "User-Agent: ".CHYRP_IDENTITY."\r\n\r\n");

        # Check for X-Pingback header.
        while (!feof($connect) and strpos($remote_headers, "\r\n\r\n") === false) {
            $line = fgets($connect);
            $remote_headers.= $line;

            if (preg_match("/^X-Pingback: (.+)/i", $line, $header)) {
                fclose($connect);
                return trim($header[1]);
            }
        }

        # Check <link> elements if the content can be parsed.
        if (preg_match("~^Content-Type: text/(html|sgml|xml|plain)~im", $remote_headers)) {
            while (!feof($connect) and strlen($remote_content) < 2048) {
                $line = fgets($connect);
                $remote_content.= $line;

                if (preg_match("/<link[^>]* href=(\"[^\"]+\"|\'[^\']+\')[^>]*>/i", $line, $link))
                    if (preg_match("/ rel=(\"pingback\"|\'pingback\')/i", $link[0])) {
                        fclose($connect);
                        return unfix(trim($link[1], "\"'"));
                    }
            }
        }

        fclose($connect);
        return false;
    }

    #---------------------------------------------
    # Extensions
    #---------------------------------------------

    /**
     * Function: load_info
     * Loads an extension's info.php file and returns an array of attributes.
     */
    function load_info($filepath): array {
        if (is_file($filepath) and is_readable($filepath))
            $info = include $filepath;

        if (!isset($info) or gettype($info) != "array")
            $info = array();

        fallback($info["name"],          fix(basename(dirname($filepath))));
        fallback($info["version"],       0);
        fallback($info["url"],           "");
        fallback($info["description"],   "");
        fallback($info["author"],        array("name" => "", "url" => ""));
        fallback($info["confirm"]);
        fallback($info["uploader"],      false);
        fallback($info["conflicts"],     array());
        fallback($info["dependencies"],  array());
        fallback($info["notifications"], array());

        $info["conflicts"]             = (array) $info["conflicts"];
        $info["dependencies"]          = (array) $info["dependencies"];
        $info["notifications"]         = (array) $info["notifications"];

        $uploads_path = MAIN_DIR.Config::current()->uploads_path;

        if ($info["uploader"])
            if (!is_dir($uploads_path))
                $info["notifications"][] = __("Please create the uploads directory.");
            elseif (!is_writable($uploads_path))
                $info["notifications"][] = __("Please make the uploads directory writable.");

        return $info;
    }

    /**
     * Function: init_extensions
     * Initialize all Modules and Feathers.
     */
    function init_extensions(): void {
        $config = Config::current();

        # Instantiate all Modules.
        foreach ($config->enabled_modules as $module) {
            $class_name = camelize($module);
            $filepath = MODULES_DIR.DIR.$module.DIR.$module.".php";

            if (!is_file($filepath) or !is_readable($filepath)) {
                cancel_module($module, _f("%s module is missing.", $class_name));
                continue;
            }

            load_translator($module, MODULES_DIR.DIR.$module.DIR."locale");

            require $filepath;

            if (!is_subclass_of($class_name, "Modules")) {
                cancel_module($module, _f("%s module is damaged.", $class_name));
                continue;
            }

            Modules::$instances[$module] = new $class_name;
            Modules::$instances[$module]->safename = $module;
        }

        # Instantiate all Feathers.
        foreach ($config->enabled_feathers as $feather) {
            $class_name = camelize($feather);
            $filepath = FEATHERS_DIR.DIR.$feather.DIR.$feather.".php";

            if (!is_file($filepath) or !is_readable($filepath)) {
                cancel_feather($feather, _f("%s feather is missing.", $class_name));
                continue;
            }

            load_translator($feather, FEATHERS_DIR.DIR.$feather.DIR."locale");

            require $filepath;

            if (!is_subclass_of($class_name, "Feathers")) {
                cancel_feather($feather, _f("%s feather is damaged.", $class_name));
                continue;
            }

            Feathers::$instances[$feather] = new $class_name;
            Feathers::$instances[$feather]->safename = $feather;
        }

        # Initialize all Modules.
        foreach (Modules::$instances as $module)
            if (method_exists($module, "__init"))
                $module->__init();

        # Initialize all Feathers.
        foreach (Feathers::$instances as $feather)
            if (method_exists($feather, "__init"))
                $feather->__init();
    }

    /**
     * Function: module_enabled
     * Determines if a module is currently enabled and not cancelled.
     *
     * Parameters:
     *     $name - The non-camelized name of the module.
     *
     * Returns:
     *     Whether or not the supplied module is enabled.
     */
    function module_enabled($name): bool {
        return (!empty(Modules::$instances[$name]) and
                 empty(Modules::$instances[$name]->cancelled));
    }

    /**
     * Function: feather_enabled
     * Determines if a feather is currently enabled and not cancelled.
     *
     * Parameters:
     *     $name - The non-camelized name of the feather.
     *
     * Returns:
     *     Whether or not the supplied feather is enabled.
     */
    function feather_enabled($name): bool {
        return (!empty(Feathers::$instances[$name]) and
                 empty(Feathers::$instances[$name]->cancelled));
    }

    /**
     * Function: cancel_module
     * Temporarily declares a module cancelled (disabled).
     *
     * Parameters:
     *     $target - The non-camelized name of the module.
     *     $reason - Why was execution cancelled?
     *
     * Notes:
     *     A module can cancel itself in its __init() method.
     */
     function cancel_module($target, $reason = ""): void {
        $message = empty($reason) ?
            _f("Execution of %s has been cancelled.", camelize($target)) : $reason ;

        if (isset(Modules::$instances[$target]))
            Modules::$instances[$target]->cancelled = true;

        trigger_error($message, E_USER_NOTICE);
    }

    /**
     * Function: cancel_feather
     * Temporarily declares a feather cancelled (disabled).
     *
     * Parameters:
     *     $target - The non-camelized name of the feather.
     *     $reason - Why was execution cancelled?
     *
     * Notes:
     *     A feather can cancel itself in its __init() method.
     */
     function cancel_feather($target, $reason = ""): void {
        $message = empty($reason) ?
            _f("Execution of %s has been cancelled.", camelize($target)) : $reason ;

        if (isset(Feathers::$instances[$target]))
            Feathers::$instances[$target]->cancelled = true;

        trigger_error($message, E_USER_NOTICE);
    }

    #---------------------------------------------
    # Upload Management
    #---------------------------------------------

    /**
     * Function: upload
     * Validates and moves an uploaded file to the uploads directory.
     *
     * Parameters:
     *     $file - The POST method upload array, e.g. $_FILES['userfile'].
     *     $filter - An array of valid extensions (case-insensitive).
     *
     * Returns:
     *     The filename of the upload relative to the uploads directory.
     */
    function upload($file, $filter = null): string {
        $uploads_path = MAIN_DIR.Config::current()->uploads_path;
        $filename = upload_filename($file['name'], $filter);

        if ($filename === false)
            error(__("Error"), __("Uploaded file is of an unsupported type."));

        if (!is_uploaded_file($file['tmp_name']))
            show_403(__("Access Denied"), __("Only uploaded files are accepted."));

        if (!is_dir($uploads_path))
            error(__("Error"), __("Upload path does not exist."));

        if (!is_writable($uploads_path))
            error(__("Error"), __("Upload path is not writable."));

        if (!move_uploaded_file($file['tmp_name'], $uploads_path.$filename))
            error(__("Error"), __("Failed to write file to disk."));

        return $filename;
    }

    /**
     * Function: upload_from_url
     * Copies a file from a remote URL to the uploads directory.
     *
     * Parameters:
     *     $url - The URL of the resource to be copied.
     *     $redirects - The maximum number of redirects to follow.
     *     $timeout - The maximum number of seconds to wait.
     *
     * Returns:
     *     The filename of the copied file, or false on failure.
     */
    function upload_from_url($url, $redirects = 3, $timeout = 10) {
        if (!preg_match("~[^ /\?]+(?=($|\?))~", $url, $match))
            return false;

        $filename = upload_filename($match[0]);

        if ($filename === false)
            return false;

        $contents = get_remote($url, $redirects, $timeout);

        if ($contents === false)
            return false;

        $uploads_path = MAIN_DIR.Config::current()->uploads_path;

        if (!is_dir($uploads_path))
            error(__("Error"), __("Upload path does not exist."));

        if (!is_writable($uploads_path))
            error(__("Error"), __("Upload path is not writable."));

        if (!@file_put_contents($uploads_path.$filename, $contents))
            error(__("Error"), __("Failed to write file to disk."));

        return $filename;
    }

    /**
     * Function: uploaded
     * Generates an absolute URL or filesystem path to an uploaded file.
     *
     * Parameters:
     *     $file - Filename relative to the uploads directory.
     *     $url - Whether to return a URL or a filesystem path.
     *
     * Returns:
     *     The supplied filename prepended with URL or filesystem path.
     */
    function uploaded($file, $url = true): string {
        $config = Config::current();

        return ($url) ?
            fix($config->chyrp_url.str_replace(DIR, "/", $config->uploads_path).urlencode($file), true) :
            MAIN_DIR.$config->uploads_path.$file ;
    }

    /**
     * Function: uploaded_search
     * Returns an array of files discovered in the uploads directory.
     *
     * Parameters:
     *     $search - A search term.
     *     $filter - An array of valid extensions (case insensitive).
     */
    function uploaded_search($search = "", $filter = array()): array {
        $config = Config::current();
        $results = array();

        if (!empty($filter))
            foreach ($filter as &$entry)
                $entry = preg_quote($entry, "/");

        $patterns = !empty($filter) ? implode("|", $filter) : ".+" ;
        $dir = new DirectoryIterator(MAIN_DIR.$config->uploads_path);

        foreach ($dir as $item) {
            if ($item->isFile()) {
                $filename = $item->getFilename();

                if (!preg_match("/.+\.($patterns)$/i", $filename))
                    continue;

                if (!($search == "") and stripos($filename, $search) === false)
                    continue;

                $results[] = $filename;
            }
        }

        return $results;
    }

    /**
     * Function: upload_tester
     * Tests uploaded file information to determine if the upload was successful.
     *
     * Parameters:
     *     $file - The POST method upload array, e.g. $_FILES['userfile'].
     *
     * Returns:
     *     True for a successful upload or false if no file was uploaded.
     *
     * Notes:
     *     $_POST and $_FILES are empty if post_max_size directive is exceeded.
     */
    function upload_tester($file): bool {
        $success = false;
        $results = array();
        $maximum = Config::current()->uploads_limit;

        # Recurse to test multiple uploads file by file using a one-dimensional array.
        if (is_array($file['name'])) {
            for ($i = 0; $i < count($file['name']); $i++)
                $results[] = upload_tester(array('name' => $file['name'][$i],
                                                 'type' => $file['type'][$i],
                                                 'tmp_name' => $file['tmp_name'][$i],
                                                 'error' => $file['error'][$i],
                                                 'size' => $file['size'][$i]));

            return (!in_array(false, $results));
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                $success = true;
                break;
            case UPLOAD_ERR_NO_FILE:
                $success = false;
                break;
            case UPLOAD_ERR_INI_SIZE:
                error(__("Error"),
                      __("The uploaded file exceeds the <code>upload_max_filesize</code> directive in php.ini."), null, 413);
            case UPLOAD_ERR_FORM_SIZE:
                error(__("Error"),
                      __("The uploaded file exceeds the <code>MAX_FILE_SIZE</code> directive in the HTML form."), null, 413);
            case UPLOAD_ERR_PARTIAL:
                error(__("Error"),
                      __("The uploaded file was only partially uploaded."), null, 400);
            case UPLOAD_ERR_NO_TMP_DIR:
                error(__("Error"),
                      __("Missing a temporary folder."));
            case UPLOAD_ERR_CANT_WRITE:
                error(__("Error"),
                      __("Failed to write file to disk."));
            case UPLOAD_ERR_EXTENSION:
                error(__("Error"),
                      __("File upload was stopped by a PHP extension."));
            default:
                error(__("Error"),
                      _f("File upload failed with error %d.", $file['error']));
        }

        if ($file['size'] > ($maximum * 1000000))
            error(__("Error"),
                  _f("The uploaded file exceeds the maximum size of %d Megabytes allowed by this site.", $maximum), null, 413);

        return $success;
    }

    /**
     * Function: upload_filename
     * Generates a sanitized unique name for an uploaded file.
     *
     * Parameters:
     *     $filename - The filename to make unique.
     *     $filter - An array of valid extensions (case insensitive).
     *
     * Returns:
     *     A sanitized unique filename, or false on failure.
     */
    function upload_filename($filename, $filter = array()) {
        if (empty($filter))
            $filter = upload_filter_whitelist();

        foreach ($filter as &$entry)
            $entry = preg_quote($entry, "/");

        $patterns = implode("|", $filter);

        # Return false if a valid basename and extension is not extracted.
        if (!preg_match("/(.+)(\.($patterns))$/i", $filename, $matches))
            return false;

        $extension = $matches[3];
        $sanitized = oneof(sanitize($matches[1], true, true, 80), md5($filename));
        $count = 1;
        $unique = $sanitized.".".$extension;

        while (file_exists(uploaded($unique, false))) {
            $count++;
            $unique = $sanitized."-".$count.".".$extension;
        }

        return $unique;
    }

    /**
     * Function: upload_filter_whitelist
     * Returns an array containing a default list of allowed extensions.
     */
    function upload_filter_whitelist(): array {
        return array(
            # Binary and text formats:
            "bin", "exe", "txt", "rtf", "md", "pdf",

            # Archive and compression formats:
            "zip", "tar", "rar", "dmg", "cab", "bz2", "gz",

            # Image formats:
            "jpg", "jpeg", "png", "gif", "webp", "avif", "tif", "tiff", "bmp",

            # Video and audio formats:
            "mp4", "ogv", "webm", "3gp", "mkv", "mov", "mp3", "m4a", "oga", "ogg", "mka", "flac", "wav"
        );
    }

    #---------------------------------------------
    # Input Validation and Processing
    #---------------------------------------------

    /**
     * Function: password_strength
     * Award a numeric score for the strength of a password.
     *
     * Parameters:
     *     $password - The password string to score.
     *
     * Returns:
     *     A numeric score for the strength of the password.
     */
    function password_strength($password = ""): int {
        $score = 0;

        if (empty($password))
            return $score;

        # Calculate the frequency of each char in the password.
        $frequency = array_count_values(str_split($password));

        # Award each unique char and punish more than 10 occurrences.
        foreach ($frequency as $occurrences)
            $score += (11 - $occurrences);

        # Award bonus points for different character types.
        $variations = array("digits" => preg_match("/\d/", $password),
                            "lower" => preg_match("/[a-z]/", $password),
                            "upper" => preg_match("/[A-Z]/", $password),
                            "nonWords" => preg_match("/\W/", $password));

        $score += (array_sum($variations) - 1) * 10;

        return intval($score);
    }

    /**
     * Function: is_url
     * Does the string look like a web URL?
     *
     * Parameters:
     *     $string - The string to analyse.
     *
     * Returns:
     *     Whether or not the string matches the criteria.
     *
     * Notes:
     *     Recognises FQDN, IPv4 and IPv6 hosts.
     *
     * See Also:
     *     <add_scheme>
     */
    function is_url($string): bool {
        return (
            preg_match('~^(https?://)?([a-z0-9][a-z0-9\-\.]*[a-z0-9]\.[a-z]{2,63}\.?)(:[0-9]{1,5})?($|/)~i', $string) or
            preg_match('~^(https?://)?([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})(:[0-9]{1,5})?($|/)~', $string) or
            preg_match('~^(https?://)?(\[[a-f0-9\:]{3,39}\])(:[0-9]{1,5})?($|/)~i', $string)
        );
    }

    /**
     * Function: add_scheme
     * Prefixes a URL with a scheme if none was detected.
     *
     * Parameters:
     *     $url - The URL to analyse.
     *     $scheme - Force this scheme (optional).
     *
     * Returns:
     *     URL prefixed with a default or supplied scheme.
     *
     * See Also:
     *     <is_url>
     */
    function add_scheme($url, $scheme = null): string {
        preg_match('~^([a-z]+://)?(.+)~i', $url, $matches);
        $matches[1] = isset($scheme) ? $scheme : oneof($matches[1], "http://") ;
        return $url = $matches[1].$matches[2];
    }

    /**
     * Function: is_email
     * Does the string look like an email address?
     *
     * Parameters:
     *     $string - The string to analyse.
     *
     * Notes:
     *     Recognises FQDN, IPv4 and IPv6 hosts.
     *
     * Returns:
     *     Whether or not the string matches the criteria.
     */
    function is_email($string): bool {
        return (
            preg_match('~^[^ <>@]+@([a-z0-9][a-z0-9\-\.]*[a-z0-9]\.[a-z]{2,63}\.?)$~i', $string) or
            preg_match('~^[^ <>@]+@([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})$~', $string) or
            preg_match('~^[^ <>@]+@(\[[a-f0-9\:]{3,39}\])$~i', $string)
        );
    }

    /**
     * Function: generate_captcha
     * Generates a captcha form element.
     *
     * Returns:
     *     A string containing HTML elements to add to a form.
     */
    function generate_captcha() {
        Trigger::current()->call("before_generate_captcha");

        foreach (get_declared_classes() as $class)
            if (in_array("CaptchaProvider", class_implements($class)))
                return call_user_func($class."::generateCaptcha");

        return false;
    }

    /**
     * Function: check_captcha
     * Checks the response to a captcha.
     *
     * Returns:
     *     Whether or not the captcha was defeated.
     */
    function check_captcha() {
        Trigger::current()->call("before_check_captcha");

        foreach (get_declared_classes() as $class)
            if (in_array("CaptchaProvider", class_implements($class)))
                return call_user_func($class."::checkCaptcha");

        return true;
    }

    /**
     * Function: get_gravatar
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * Parameters:
     *     $email - The email address.
     *     $s - Return an image of this size in pixels (512 maximum).
     *     $d - Default image set: 404/mm/identicon/monsterid/wavatar.
     *     $r - Maximum acceptable guidance rating for images: g/pg/r/x.
     *     $img - Return a complete <img> tag?
     *
     * Returns:
     *     String containing either just a URL or a complete image tag.
     *
     * Source:
     *     http://gravatar.com/site/implement/images/php/
     */
    function get_gravatar($email, $s = 80, $img = false, $d = "mm", $r = "g"): string {
        $url = "https://www.gravatar.com/avatar/".md5(strtolower(trim($email)))."?s=$s&d=$d&r=$r";
        return ($img) ? '<img class="gravatar" src="'.fix($url, true, true).'" alt="">' : $url ;
    }

    #---------------------------------------------
    # Responding to Requests
    #---------------------------------------------

    /**
     * Function: json_set
     * JSON encodes a value and checks for errors.
     *
     * Parameters:
     *     $value - The value to be encoded.
     *     $options - A bitmask of encoding options.
     *     $depth - Recursion depth for encoding.
     *
     * Returns:
     *     A JSON encoded string or false on failure.
     */
    function json_set($value, $options = 0, $depth = 512) {
        $encoded = json_encode($value, $options, $depth);

        if (json_last_error())
            trigger_error(_f("JSON encoding error: %s", fix(json_last_error_msg(), false, true)), E_USER_WARNING);

        return $encoded;
    }

    /**
     * Function: json_get
     * JSON decodes a value and checks for errors.
     *
     * Parameters:
     *     $value - The UTF-8 string to be decoded.
     *     $assoc - Convert objects into associative arrays?
     *     $depth - Recursion depth for decoding.
     *     $options - A bitmask of decoding options.
     *
     * Returns:
     *     A JSON decoded value of the appropriate PHP type.
     */
    function json_get($value, $assoc = false, $depth = 512, $options = 0) {
        $decoded = json_decode($value, $assoc, $depth, $options);

        if (json_last_error())
            trigger_error(_f("JSON decoding error: %s", fix(json_last_error_msg(), false, true)), E_USER_WARNING);

        return $decoded;
    }

    /**
     * Function: json_response
     * Send a structured JSON response.
     *
     * Parameters:
     *     $text - A string containing a response message.
     *     $data - Arbitrary data to be sent with the response.
     */
    function json_response($text = null, $data = null): void {
        header("Content-Type: application/json; charset=UTF-8");
        echo json_set(array("text" => $text, "data" => $data));
    }

    /**
     * Function: file_attachment
     * Send a file attachment to the visitor.
     *
     * Parameters:
     *     $contents - The bitstream to be delivered to the visitor.
     *     $filename - The name to be applied to the content upon download.
     */
    function file_attachment($contents = "", $filename = "caconym"): void {
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"".addslashes($filename)."\"");

        if (!in_array("ob_gzhandler", ob_list_handlers()) and !ini_get("zlib.output_compression"))
            header("Content-Length: ".strlen($contents));

        echo $contents;
    }

    /**
     * Function: zip_archive
     * Creates a basic flat Zip archive from an array of items.
     *
     * Parameters:
     *     $array - An associative array of names and contents.
     *
     * Returns:
     *     A Zip archive.
     *
     * See Also:
     *     https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT
     */
    function zip_archive($array): string {
        $file = "";
        $cdir = "";
        $eocd = "";

        # Generate MS-DOS date/time format.
        $now  = getdate();
        $time = 0 | $now["seconds"] >> 1 | $now["minutes"] << 5 | $now["hours"] << 11;
        $date = 0 | $now["mday"] | $now["mon"] << 5 | ($now["year"] - 1980) << 9;

        foreach ($array as $name => $orig) {
            # Remove directory separators.
            $name = str_replace(array("\\", "/"), "", $name);
            $comp = $orig;
            $method = "\x00\x00";

            if (strlen($name) > 0xffff or strlen($orig) > 0xffffffff)
                trigger_error(__("Failed to create Zip archive."), E_USER_WARNING);

            if (function_exists("gzcompress")) {
                $zlib = gzcompress($orig, 6, ZLIB_ENCODING_DEFLATE);

                if ($zlib !== false) {
                    # Trim ZLIB header and checksum from the deflated data.
                    $zlib = substr(substr($zlib, 0, strlen($zlib) - 4), 2);

                    if (strlen($zlib) < strlen($orig)) {
                        $comp = $zlib;
                        $method = "\x08\x00";
                    }
                }
            }

            $head = "\x50\x4b\x03\x04";         # Local file header signature.
            $head.= "\x14\x00";                 # Version needed to extract.
            $head.= "\x00\x00";                 # General purpose bit flag.
            $head.= $method;                    # Compression method.
            $head.= pack("v", $time);           # Last mod file time.
            $head.= pack("v", $date);           # Last mod file date.

            $nlen = strlen($name);
            $olen = strlen($orig);
            $clen = strlen($comp);
            $crc  = crc32($orig);

            $head.= pack("V", $crc);            # CRC-32.
            $head.= pack("V", $clen);           # Compressed size.
            $head.= pack("V", $olen);           # Uncompressed size.
            $head.= pack("v", $nlen);           # File name length.
            $head.= pack("v", 0);               # Extra field length.

            $cdir.= "\x50\x4b\x01\x02";         # Central file header signature.
            $cdir.= "\x00\x00";                 # Version made by.
            $cdir.= "\x14\x00";                 # Version needed to extract.
            $cdir.= "\x00\x00";                 # General purpose bit flag.
            $cdir.= $method;                    # Compression method.
            $cdir.= pack("v", $time);           # Last mod file time.
            $cdir.= pack("v", $date);           # Last mod file date.
            $cdir.= pack("V", $crc);            # CRC-32.
            $cdir.= pack("V", $clen);           # Compressed size.
            $cdir.= pack("V", $olen);           # Uncompressed size.
            $cdir.= pack("v", $nlen);           # File name length.
            $cdir.= pack("v", 0);               # Extra field length.
            $cdir.= pack("v", 0);               # File comment length.
            $cdir.= pack("v", 0);               # Disk number start.
            $cdir.= pack("v", 0);               # Internal file attributes.
            $cdir.= pack("V", 32);              # External file attributes.
            $cdir.= pack("V", strlen($file));   # Relative offset of local header.
            $cdir.= $name;

            $file.= $head.$name.$comp;
        }

        $eocd.= "\x50\x4b\x05\x06";             # End of central directory signature.
        $eocd.= "\x00\x00";                     # Number of this disk.
        $eocd.= "\x00\x00";                     # Disk with start of central directory.
        $eocd.= pack("v", count($array));       # Entries on this disk.
        $eocd.= pack("v", count($array));       # Total number of entries.
        $eocd.= pack("V", strlen($cdir));       # Size of the central directory.
        $eocd.= pack("V", strlen($file));       # Offset of start of central directory.
        $eocd.= "\x00\x00";                     # ZIP file comment length.

        return $file.$cdir.$eocd;
    }

    /**
     * Function: email
     * Send an email using PHP's mail() function or an alternative.
     */
    function email() {
        $function = "mail";
        Trigger::current()->filter($function, "send_mail");
        return call_user_func_array($function, func_get_args());
    }

    /**
     * Function: correspond
     * Send an email correspondence to a user about an action we took.
     *
     * Parameters:
     *     $action - About which action are we corresponding with the user?
     *     $params - An indexed array of parameters associated with this action.
     *               $params["to"] is required: the address to be emailed.
     */
    function correspond($action, $params) {
        $config  = Config::current();
        $trigger = Trigger::current();

        if (!$config->email_correspondence or !isset($params["to"]))
            return false;

        $params["headers"] = "From: ".$config->email."\r\n".
                             "Reply-To: ".$config->email."\r\n".
                             "X-Mailer: ".CHYRP_IDENTITY;

        fallback($params["subject"], "");
        fallback($params["message"], "");

        switch ($action) {
            case "activate":
                $params["subject"] = _f("Activate your account at %s", $config->name);
                $params["message"] = _f("Hello, %s.", $params["login"]).
                                     "\r\n".
                                     "\r\n".
                                     __("You are receiving this message because you registered a new account.").
                                     "\r\n".
                                     "\r\n".
                                     __("Visit this link to activate your account:").
                                     "\r\n".
                                     unfix($params["link"]);

                break;

            case "reset":
                $params["subject"] = _f("Reset your password at %s", $config->name);
                $params["message"] = _f("Hello, %s.", $params["login"]).
                                     "\r\n".
                                     "\r\n".
                                     __("You are receiving this message because you requested a new password.").
                                     "\r\n".
                                     "\r\n".
                                     __("Visit this link to reset your password:").
                                     "\r\n".
                                     unfix($params["link"]);

                break;

            case "password":
                $params["subject"] = _f("Your new password for %s", $config->name);
                $params["message"] = _f("Hello, %s.", $params["login"]).
                                     "\r\n".
                                     "\r\n".
                                     _f("Your new password is: %s", $params["password"]);

                break;

            default:
                $trigger->filter($params, "correspond_".$action);
        }

        if ($trigger->exists("send_correspondence"))
            return $trigger->call("send_correspondence", $action, $params);

        return email($params["to"], $params["subject"], $params["message"], $params["headers"]);
    }

    /**
     * Function: javascripts
     * Returns inline JavaScript for core functionality and extensions.
     */
    function javascripts(): string {
        $config = Config::current();
        $route = Route::current();
        $theme = Theme::current();
        $trigger = Trigger::current();
        $nonce = "";

        $script = (ADMIN) ?
            MAIN_DIR.DIR."admin".DIR."javascripts".DIR."admin.js.php" :
            INCLUDES_DIR.DIR."main.js.php" ;

        $common = '<script src="'.
                  fix($config->chyrp_url."/includes/common.js", true).
                  '" type="text/javascript" charset="UTF-8"></script>';

        ob_start();
        include $script;
        $ob = ob_get_clean();

        $trigger->call("javascripts_hash", $ob);
        $trigger->filter($nonce, "javascripts_nonce");

        return $common."\n<script nonce=\"".$nonce."\">".$ob."</script>\n";
    }
