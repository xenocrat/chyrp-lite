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
     *     $domain - The cookie domain (optional).
     */
    function session($domain = "") {
        session_set_save_handler(array("Session", "open"),
                                 array("Session", "close"),
                                 array("Session", "read"),
                                 array("Session", "write"),
                                 array("Session", "destroy"),
                                 array("Session", "gc"));

        $parsed = parse_url(Config::current()->url, PHP_URL_HOST);
        $domain = preg_replace("~^www\.~", "", oneof($domain, $parsed, $_SERVER['SERVER_NAME']));

        session_set_cookie_params(60 * 60 * 24 * 30, "/", $domain, false, true);
        session_name("ChyrpSession");
        register_shutdown_function("session_write_close");
        session_start();
    }

    /**
     * Function: logged_in
     * Returns whether or not the visitor is logged in.
     */
    function logged_in() {
        return (class_exists("Visitor") and isset(Visitor::current()->id) and Visitor::current()->id != 0);
    }

    /**
     * Function: same_origin
     * Returns whether or not the request was referred from another resource on this site.
     */
    function same_origin() {
        $url = Config::current()->url;
        $parsed = parse_url($url);
        $origin = fallback($parsed["scheme"], "http")."://".fallback($parsed["host"], "");

        if (isset($parsed["port"]))
            $origin.= ":".$parsed["port"];

        if (isset($_SERVER['HTTP_ORIGIN']) and $_SERVER['HTTP_ORIGIN'] == $origin)
            return true;

        if (isset($_SERVER['HTTP_REFERER']) and strpos($_SERVER['HTTP_REFERER'], $url) === 0)
            return true;

        return false;
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
        # Ask the current controller to translate relative URLs.
        if (file_exists(INCLUDES_DIR.DIR."config.json.php") and class_exists("Route") and !substr_count($url, "://"))
            $url = url($url);

        header("Location: ".html_entity_decode($url));
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
        $main->display("pages".DIR."404", array("reason" => $body), $title);
        exit;
    }

    /**
     * Function: url
     * Mask for Route->url().
     */
    function url($url, $controller = null) {
        return Route::current()->url($url, $controller);
    }

    /**
     * Function: self_url
     * Returns an absolute URL for the current request.
     */
    function self_url() {
        $url = Config::current()->url;
        $parsed = parse_url($url);
        $origin = fallback($parsed["scheme"], "http")."://".fallback($parsed["host"], "");

        if (isset($parsed["port"]))
            $origin.= ":".$parsed["port"];

        return $origin.$_SERVER['REQUEST_URI'];
    }

    /**
     * Function: htaccess_conf
     * Creates the .htaccess file for Chyrp Lite or appends to an existing file.
     *
     * Parameters:
     *     $url_path - The URL path to MAIN_DIR for the RewriteBase directive.
     *
     * Returns:
     *     True if no action was needed, bytes written on success, false on failure.
     */
    function htaccess_conf($url_path = null) {
        if (!INSTALLING)
            $url_path = oneof($url_path, parse_url(Config::current()->chyrp_url, PHP_URL_PATH), "/");

        # The trim operation guarantees a string with leading and trailing slashes,
        # but it also avoids doubling up slashes if $url_path consists of only "/".
        $template = preg_replace("~%\\{CHYRP_PATH\\}~",
                                 rtrim("/".ltrim($url_path, "/"), "/")."/",
                                 file_get_contents(INCLUDES_DIR.DIR."htaccess.conf"));

        $filepath = MAIN_DIR.DIR.".htaccess";

        if (!file_exists($filepath))
            return @file_put_contents($filepath, $template);

        if (!is_file($filepath) or !is_readable($filepath))
            return false;

        if (!preg_match("~".preg_quote($template, "~")."~", file_get_contents($filepath)))
            return @file_put_contents($filepath, "\n\n".$template, FILE_APPEND);

        return true;
    }

    #---------------------------------------------
    # Localization
    #---------------------------------------------

    /**
     * Function: set_locale
     * Try to set the locale with fallbacks for platform-specific quirks.
     *
     * Parameters:
     *     $locale - The locale name, e.g. @en_US@, @uk_UA@, @fr_FR@
     */
    function set_locale($locale = "en_US") {
        # Set the ICU locale.
        if (class_exists("Locale"))
            Locale::setDefault($locale);

        # Set the PHP locale.
        @putenv("LC_ALL=".$locale);
        setlocale(LC_ALL, array($locale.".UTF-8",
                                $locale.".utf-8",
                                $locale.".UTF8",
                                $locale.".utf8",
                                $locale));
    }

    /**
     * Function: load_translator
     * Sets the path for a gettext translation domain.
     *
     * Parameters:
     *     $domain - The name of this translation domain.
     *     $locale - The path to the locale directory.
     */
    function load_translator($domain, $locale) {
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
    function lang_code($code) {
        return class_exists("Locale") ? Locale::getDisplayName($code, $code) : $code ;
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
    function __($text, $domain = "chyrp") {
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
    function _p($single, $plural, $number, $domain = "chyrp") {
        return function_exists("dngettext") ?
            dngettext($domain, $single, $plural, (int) $number) : (($number != 1) ? $plural : $single) ;
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
    function _f($string, $args = array(), $domain = "chyrp") {
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
     *     $strftime - Format using @strftime@ instead of @date@?
     *
     * Returns:
     *     A time/date string with the supplied formatting.
     */
    function when($formatting, $when, $strftime = false) {
        $time = is_numeric($when) ? $when : strtotime($when) ;

        if ($strftime)
            return strftime($formatting, $time);
        else
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
     * Function: time_in_timezone
     * Returns the appropriate time() for representing a timezone.
     */
    function time_in_timezone($timezone) {
        $orig = get_timezone();
        set_timezone($timezone);
        $time = date("F jS, Y, g:i A");
        set_timezone($orig);
        return strtotime($time);
    }

    /**
     * Function: timezones
     * Returns an array of timezones that have unique offsets.
     */
    function timezones() {
        $zones = array();

        foreach (timezone_identifiers_list(DateTimeZone::ALL) as $zone)
            $zones[] = array("name" => $zone,
                             "now" => time_in_timezone($zone));

        usort($zones, function($a, $b) { return (int) ($a["now"] > $b["now"]); });
        return $zones;
    }

    /**
     * Function: set_timezone
     * Sets the timezone for all date/time functions.
     *
     * Parameters:
     *     $timezone - The timezone to set.
     */
    function set_timezone($timezone) {
        if (function_exists("date_default_timezone_set"))
            date_default_timezone_set($timezone);
        else
            ini_set("date.timezone", $timezone);
    }

    /**
     * Function: get_timezone()
     * Returns the current timezone.
     */
    function get_timezone() {
        if (function_exists("date_default_timezone_set"))
            return date_default_timezone_get();
        else
            return ini_get("date.timezone");
    }

    #---------------------------------------------
    # Variable Manipulation
    #---------------------------------------------

    /**
     * Function: fallback
     * Sets the supplied variable if it is not already set, using the supplied arguments as candidates.
     *
     * Parameters:
     *     &$variable - The variable to return or set.
     *
     * Returns:
     *     The value that was assigned to the variable.
     *
     * Notes:
     *     The first non-empty candidate will be used, or the last, or null if no candidates are supplied.
     */
    function fallback(&$variable) {
        if (is_bool($variable))
            return $variable;

        $set = (!isset($variable) or (is_string($variable) and trim($variable) === "") or $variable === array());

        $args = func_get_args();
        array_shift($args);

        if (count($args) > 1) {
            foreach ($args as $arg) {
                $fallback = $arg;

                if (isset($arg) and (!is_string($arg) or (is_string($arg) and trim($arg) !== "")) and $arg !== array())
                    break;
            }
        } else
            $fallback = isset($args[0]) ? $args[0] : null ;

        if ($set)
            $variable = $fallback;

        return $set ? $fallback : $variable ;
    }

    /**
     * Function: oneof
     * Crawls the supplied set of arguments in search of a candidate that has a substantial value.
     *
     * Returns:
     *     The first candidate of substance, or the last, or null if no candidates are supplied.
     *
     * Notes:
     *     It will guess where to stop based on types, e.g. "" has priority over array() but not 1.
     */
    function oneof() {
        $last = null;
        $args = func_get_args();

        foreach ($args as $index => $arg) {
            if (!isset($arg) or
                (is_string($arg) and trim($arg) === "") or $arg === array() or
                (is_object($arg) and empty($arg)) or ($arg === "0000-00-00 00:00:00"))
                $last = $arg;
            else
                return $arg;

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
     * Function: token
     * Salt and hash a unique token using the supplied data.
     *
     * Parameters:
     *     $items - An array of items to hash.
     *
     * Returns:
     *     A unique token salted with the site's secure hashkey.
     */
    function token($items) {
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
    function slug($length) {
        return strtolower(random($length));
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
     *     Uses cryptographically secure methods if available.
     */
    function random($length) {
        $input = "1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
        $range = strlen($input) - 1;
        $chars = "";

        if (function_exists("random_int"))
            for($i = 0; $i < $length; $i++)
                $chars.= $input[random_int(0, $range)];
        elseif (function_exists("openssl_random_pseudo_bytes"))
            while (strlen($chars) < $length) {
                $chunk = openssl_random_pseudo_bytes(3); # 3 * 8 / 6 = 4
                $chars.= ($chunk === false) ?
                    $input[rand(0, $range)] :
                    preg_replace("/[^a-zA-Z0-9]/", "", base64_encode($chunk)) ;
            }
        elseif (function_exists("mt_rand"))
            for($i = 0; $i < $length; $i++)
                $chars.= $input[mt_rand(0, $range)];
        else
            for($i = 0; $i < $length; $i++)
                $chars.= $input[rand(0, $range)];

        return substr($chars, 0, $length);
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
     * Starts the internal timer.
     */
    function timer_start() {
        static $timer;

        if (!isset($timer))
            $timer = microtime(true);

        return $timer;
    }

    /**
     * Function: timer_stop
     * Stops the timer and returns the total elapsed time.
     *
     * Parameters:
     *     $precision - Round to n decimal places.
     *
     * Returns:
     *     A formatted number with the requested $precision.
     */
    function timer_stop($precision = 3) {
        return number_format((microtime(true) - timer_start()), $precision);
    }

    /**
     * Function: match
     * Try to match a string against an array of regular expressions.
     *
     * Parameters:
     *     $try - An array of regular expressions, or a single regular expression.
     *     $haystack - The string to test.
     *
     * Returns:
     *     Whether or not the match succeeded.
     */
    function match($try, $haystack) {
        if (is_string($try))
            return (bool) preg_match($try, $haystack);

        foreach ($try as $needle)
            if (preg_match($needle, $haystack))
                return true;

        return false;
    }

    /**
     * Function: list_notate
     * Notates an array as a list of things.
     *
     * Parameters:
     *     $array - An array of things to notate.
     *     $quotes - Wrap quotes around strings?
     *
     * Returns:
     *     A string like "foo, bar, and baz".
     */
    function list_notate($array, $quotes = false) {
        $count = 0;
        $items = array();

        foreach ($array as $item) {
            $string = (is_string($item) and $quotes) ?
                _f("&#8220;%s&#8221;", $item) : (string) $item ;

            $items[] = (count($array) == ++$count and $count !== 1) ?
                _f("and %s", $string) : $string ;
        }

        return (count($array) == 2) ? implode(" ", $items) : implode(", ", $items) ;
    }

    /**
     * Function: comma_sep
     * Converts a comma-seperated string into an array of the listed values.
     *
     * Returns:
     *     An array containing the exploded and trimmed values.
     */
    function comma_sep($string) {
        $commas = explode(",", $string);
        $trimmed = array_map("trim", $commas);
        $cleaned = array_diff(array_unique($trimmed), array(""));
        return $cleaned;
    }

    /**
     * Function: autoload
     * Autoload PSR-0 classes on demand by scanning lib directories.
     *
     * Parameters:
     *     $class - The name of the class to load.
     */
    function autoload($class) {
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
    function keywords($query, $plain, $table = null) {
        $trimmed = trim($query);

        if (empty($trimmed))
            return array(array(), array());

        $strings  = array(); # Non-keyword values found in the query.
        $keywords = array(); # Keywords (attr:val;) found in the query.
        $where    = array(); # Parameters validated and added to WHERE.
        $filters  = array(); # Table column filters to be validated.
        $params   = array(); # Parameters for the non-keyword filter.
        $columns  = !empty($table) ? SQL::current()->select($table)->fetch() : array() ;

        foreach (preg_split("/\s(?=\w+:)|;/", $query, -1, PREG_SPLIT_NO_EMPTY) as $fragment)
            if (!substr_count($fragment, ":"))
                $strings[] = trim($fragment);
            else
                $keywords[] = trim($fragment);

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
     *     $string - The string to pluralize.
     *     $number - If passed, and this number is 1, it will not pluralize.
     *
     * Returns:
     *     The supplied word with a trailing "s" added, or a non-normative pluralization.
     */
    function pluralize($string, $number = null) {
        $uncountable = array("moose", "sheep", "fish", "series", "species", "audio",
                             "rice", "money", "information", "equipment", "piss");

        if (in_array($string, $uncountable) or $number == 1)
            return $string;

        $replacements = array("/person/i" => "people",
                              "/man/i" => "men",
                              "/child/i" => "children",
                              "/cow/i" => "kine",
                              "/goose/i" => "geese",
                              "/datum$/i" => "data",
                              "/(penis)$/i" => "\\1es",
                              "/(ax|test)is$/i" => "\\1es",
                              "/(octop|vir)us$/i" => "\\1ii",
                              "/(cact)us$/i" => "\\1i",
                              "/(alias|status)$/i" => "\\1es",
                              "/(bu)s$/i" => "\\1ses",
                              "/(buffal|tomat)o$/i" => "\\1oes",
                              "/([ti])um$/i" => "\\1a",
                              "/sis$/i" => "ses",
                              "/(hive)$/i" => "\\1s",
                              "/([^aeiouy]|qu)y$/i" => "\\1ies",
                              "/^(ox)$/i" => "\\1en",
                              "/(matr|vert|ind)(?:ix|ex)$/i" => "\\1ices",
                              "/(x|ch|ss|sh)$/i" => "\\1es",
                              "/([m|l])ouse$/i" => "\\1ice",
                              "/(quiz)$/i" => "\\1zes");

        $replaced = preg_replace(array_keys($replacements), array_values($replacements), $string, 1);

        if ($replaced == $string)
            return $string."s";
        else
            return $replaced;
    }

    /**
     * Function: depluralize
     * Singularizes a word.
     *
     * Parameters:
     *     $string - The string to depluralize.
     *     $number - If passed, and this number is not 1, it will not depluralize.
     *
     * Returns:
     *     The supplied word with trailing "s" removed, or a non-normative singularization.
     */
    function depluralize($string, $number = null) {
        if (isset($number) and $number != 1)
            return $string;

        $replacements = array("/people/i" => "person",
                              "/^men/i" => "man",
                              "/children/i" => "child",
                              "/kine/i" => "cow",
                              "/geese/i" => "goose",
                              "/data$/i" => "datum",
                              "/(penis)es$/i" => "\\1",
                              "/(ax|test)es$/i" => "\\1is",
                              "/(octopi|viri|cact)i$/i" => "\\1us",
                              "/(alias|status)es$/i" => "\\1",
                              "/(bu)ses$/i" => "\\1s",
                              "/(buffal|tomat)oes$/i" => "\\1o",
                              "/([ti])a$/i" => "\\1um",
                              "/ses$/i" => "sis",
                              "/(hive)s$/i" => "\\1",
                              "/([^aeiouy]|qu)ies$/i" => "\\1y",
                              "/^(ox)en$/i" => "\\1",
                              "/(vert|ind)ices$/i" => "\\1ex",
                              "/(matr)ices$/i" => "\\1ix",
                              "/(x|ch|ss|sh)es$/i" => "\\1",
                              "/([ml])ice$/i" => "\\1ouse",
                              "/(quiz)zes$/i" => "\\1");

        $replaced = preg_replace(array_keys($replacements), array_values($replacements), $string, 1);

        if ($replaced == $string and substr($string, -1) == "s")
            return substr($string, 0, -1);
        else
            return $replaced;
    }

    /**
     * Function: normalize
     * Attempts to normalize all newlines and whitespace into single spaces.
     *
     * Returns:
     *     The normalized string.
     */
    function normalize($string) {
        $trimmed = trim($string);
        $newlines = str_replace("\n\n", " ", $trimmed);
        $newlines = str_replace("\n", "", $newlines);
        $normalized = preg_replace("/[\s\n\r\t]+/", " ", $newlines);
        return $normalized;
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
    function camelize($string, $keep_spaces = false) {
        $lower = strtolower($string);
        $deunderscore = str_replace("_", " ", $lower);
        $dehyphen = str_replace("-", " ", $deunderscore);
        $final = ucwords($dehyphen);

        if (!$keep_spaces)
            $final = str_replace(" ", "", $final);

        return $final;
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
    function decamelize($string) {
        return strtolower(preg_replace("/([a-z])([A-Z])/", "\\1_\\2", $string));
    }

    /**
     * Function: truncate
     * Truncates a string to ensure it is no longer than the requested length.
     *
     * Parameters:
     *     $text - The string to be truncated.
     *     $length - The truncated length.
     *     $ellipsis - A string to place at the truncation point.
     *     $exact - Split words to return the exact length requested?
     *     $encoding - The character encoding of the string and ellipsis.
     *
     * Returns:
     *     A truncated string with ellipsis appended.
     */
    function truncate($text, $length = 100, $ellipsis = "...", $exact = false, $encoding = "UTF-8") {
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
     */
    function markdown($text) {
        $parsedown = new Parsedown();
        return $parsedown->text($text);
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
    function emote($text) {
        $emoji = array(
            'o:-)'    => '&#x1f607;',
            '&gt;:-)' => '&#x1f608;',
            '>:-)'    => '&#x1f608;',
            ':-)'     => '&#x1f600;',
            '^_^'     => '&#x1f601;',
            ':-D'     => '&#x1f603;',
            ';-)'     => '&#x1f609;',
            '&lt;3'   => '&#x1f60d;',
            '<3'      => '&#x1f60d;',
            'B-)'     => '&#x1f60e;',
            ':-&gt;'  => '&#x1f60f;',
            ':->'     => '&#x1f60f;',
            ':-||'    => '&#x1f62c;',
            ':-|'     => '&#x1f611;',
            '-_-'     => '&#x1f612;',
            ':-/'     => '&#x1f615;',
            ':-s'     => '&#x1f616;',
            ':-*'     => '&#x1f618;',
            ':-P'     => '&#x1f61b;',
            ':-(('    => '&#x1f629;',
            ':-('     => '&#x1f61f;',
            ';_;'     => '&#x1f622;',
            ':-o'     => '&#x1f62e;',
            'O_O'     => '&#x1f632;',
            ':-$'     => '&#x1f633;',
            'x_x'     => '&#x1f635;',
            ':-x'     => '&#x1f636;'
        );

        foreach($emoji as $key => $value) {
            $text = str_replace($key, '<span class="emoji">'.$value.'</span>', $text);
        }

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
    function fix($string, $quotes = false, $double = false) {
        $quotes = ($quotes) ? ENT_QUOTES : ENT_NOQUOTES ;
        return htmlspecialchars($string, $quotes, "UTF-8", $double);
    }

    /**
     * Function: unfix
     * Undoes neutralization of HTML and quotes in strings.
     *
     * Parameters:
     *     $string - String to unfix.
     *
     * Returns:
     *     An unsanitary version of the string.
     */
    function unfix($string) {
        return htmlspecialchars_decode($string, ENT_QUOTES);
    }

    /**
     * Function: sanitize
     * Sanitizes a string of troublesome characters, typically for use in URLs.
     *
     * Parameters:
     *     $string - The string to sanitize - must be ASCII or UTF-8!
     *     $force_lowercase - Force the string to lowercase?
     *     $strict - Remove all characters except "-" and alphanumerics?
     *     $trunc - Number of characters to truncate to (default 100, 0 to disable).
     *
     * Returns:
     *     A sanitized version of the string.
     */
    function sanitize($string, $force_lowercase = true, $strict = false, $trunc = 100) {
        $strip = array("&amp;", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;", "&",
                       "~", "`", "!", "@", "#", "$", "%", "^", "*", "(", ")", "_", "=", "+", "[", "{",
                       "]", "}", "\\", "|", ";", ":", "\"", "'", "—", "–", ",", "<", ".", ">", "/", "?");

        # Strip tags, remove punctuation and HTML entities, replace spaces with hyphen-minus.
        $clean = preg_replace('/\s+/', "-", trim(str_replace($strip, "", strip_tags($string))));

        if ($strict) {
            # Discover UTF-8 multi-byte encodings and attempt substitutions.
            if (preg_match('/[\x80-\xff]/', $clean))
                $clean = strtr($clean, array(
                    # Latin-1 Supplement.
                    chr(194).chr(170) => 'a', chr(194).chr(186) => 'o', chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
                    chr(195).chr(130) => 'A', chr(195).chr(131) => 'A', chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
                    chr(195).chr(134) => 'AE',chr(195).chr(135) => 'C', chr(195).chr(136) => 'E', chr(195).chr(137) => 'E',
                    chr(195).chr(138) => 'E', chr(195).chr(139) => 'E', chr(195).chr(140) => 'I', chr(195).chr(141) => 'I',
                    chr(195).chr(142) => 'I', chr(195).chr(143) => 'I', chr(195).chr(144) => 'D', chr(195).chr(145) => 'N',
                    chr(195).chr(146) => 'O', chr(195).chr(147) => 'O', chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
                    chr(195).chr(150) => 'O', chr(195).chr(153) => 'U', chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
                    chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y', chr(195).chr(158) => 'TH',chr(195).chr(159) => 's',
                    chr(195).chr(160) => 'a', chr(195).chr(161) => 'a', chr(195).chr(162) => 'a', chr(195).chr(163) => 'a',
                    chr(195).chr(164) => 'a', chr(195).chr(165) => 'a', chr(195).chr(166) => 'ae',chr(195).chr(167) => 'c',
                    chr(195).chr(168) => 'e', chr(195).chr(169) => 'e', chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
                    chr(195).chr(172) => 'i', chr(195).chr(173) => 'i', chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
                    chr(195).chr(176) => 'd', chr(195).chr(177) => 'n', chr(195).chr(178) => 'o', chr(195).chr(179) => 'o',
                    chr(195).chr(180) => 'o', chr(195).chr(181) => 'o', chr(195).chr(182) => 'o', chr(195).chr(184) => 'o',
                    chr(195).chr(185) => 'u', chr(195).chr(186) => 'u', chr(195).chr(187) => 'u', chr(195).chr(188) => 'u',
                    chr(195).chr(189) => 'y', chr(195).chr(190) => 'th',chr(195).chr(191) => 'y', chr(195).chr(152) => 'O',
                    # Latin Extended-A.
                    chr(196).chr(128) => 'A', chr(196).chr(129) => 'a', chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
                    chr(196).chr(132) => 'A', chr(196).chr(133) => 'a', chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
                    chr(196).chr(136) => 'C', chr(196).chr(137) => 'c', chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
                    chr(196).chr(140) => 'C', chr(196).chr(141) => 'c', chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
                    chr(196).chr(144) => 'D', chr(196).chr(145) => 'd', chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
                    chr(196).chr(148) => 'E', chr(196).chr(149) => 'e', chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
                    chr(196).chr(152) => 'E', chr(196).chr(153) => 'e', chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
                    chr(196).chr(156) => 'G', chr(196).chr(157) => 'g', chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
                    chr(196).chr(160) => 'G', chr(196).chr(161) => 'g', chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
                    chr(196).chr(164) => 'H', chr(196).chr(165) => 'h', chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
                    chr(196).chr(168) => 'I', chr(196).chr(169) => 'i', chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
                    chr(196).chr(172) => 'I', chr(196).chr(173) => 'i', chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
                    chr(196).chr(176) => 'I', chr(196).chr(177) => 'i', chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
                    chr(196).chr(180) => 'J', chr(196).chr(181) => 'j', chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
                    chr(196).chr(184) => 'k', chr(196).chr(185) => 'L', chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
                    chr(196).chr(188) => 'l', chr(196).chr(189) => 'L', chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
                    chr(197).chr(128) => 'l', chr(197).chr(129) => 'L', chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
                    chr(197).chr(132) => 'n', chr(197).chr(133) => 'N', chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
                    chr(197).chr(136) => 'n', chr(197).chr(137) => 'N', chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
                    chr(197).chr(140) => 'O', chr(197).chr(141) => 'o', chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
                    chr(197).chr(144) => 'O', chr(197).chr(145) => 'o', chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
                    chr(197).chr(148) => 'R', chr(197).chr(149) => 'r', chr(197).chr(150) => 'R', chr(197).chr(151) => 'r',
                    chr(197).chr(152) => 'R', chr(197).chr(153) => 'r', chr(197).chr(154) => 'S', chr(197).chr(155) => 's',
                    chr(197).chr(156) => 'S', chr(197).chr(157) => 's', chr(197).chr(158) => 'S', chr(197).chr(159) => 's',
                    chr(197).chr(160) => 'S', chr(197).chr(161) => 's', chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
                    chr(197).chr(164) => 'T', chr(197).chr(165) => 't', chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
                    chr(197).chr(168) => 'U', chr(197).chr(169) => 'u', chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
                    chr(197).chr(172) => 'U', chr(197).chr(173) => 'u', chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
                    chr(197).chr(176) => 'U', chr(197).chr(177) => 'u', chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
                    chr(197).chr(180) => 'W', chr(197).chr(181) => 'w', chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
                    chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z', chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
                    chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z', chr(197).chr(190) => 'z', chr(197).chr(191) => 's'
                    # Additional substitution keys can be generated using: e.g. echo implode(",", unpack("C*", "€"));
                ));

            # Remove any characters that remain after substitution.
            $clean = preg_replace("/[^a-zA-Z0-9\\-]/", "", $clean);
        }

        if ($force_lowercase)
            $clean = function_exists("mb_strtolower") ? mb_strtolower($clean, "UTF-8") : strtolower($clean) ;

        if ($trunc)
            $clean = function_exists("mb_substr") ? mb_substr($clean, 0, $trunc, "UTF-8") : substr($clean, 0, $trunc) ;

        return $clean;
    }

    /**
     * Function: sanitize_html
     * Sanitize HTML to disable scripts and obnoxious attributes.
     *
     * Parameters:
     *     $string - String to sanitize.
     *
     * Returns:
     *     A sanitized version of the string.
     */
    function sanitize_html($text) {
        # Strip invalid tags.
        $text = preg_replace("/<([^a-z\/!]|\/(?![a-z])|!(?!--))[^>]*>/i", "", $text);

        # Strip script tags.
        $text = preg_replace("/<\/?script[^>]*>/i", "", $text);

        # Strip attributes from each tag, but allow attributes essential to a tag's function.
        return preg_replace_callback("/<([a-z][a-z0-9]*)[^>]*?( \/)?>/i", function ($element) {
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
                                                  "video")) and is_url($content))
                            $whitelist.= $attribute[0];

                        break;
                    case "href":
                        if (in_array($name, array("a",
                                                  "area")) and is_url($content))
                            $whitelist.= $attribute[0];

                        break;
                    case "alt":
                        if (in_array($name, array("area",
                                                  "img")))
                            $whitelist.= $attribute[0];

                        break;
                }
            }

            return "<".$element[1].$whitelist.$element[2].">";

        }, $text);
    }

    /**
     * Function: sanitize_input
     * Makes sure no inherently broken ideas such as magic_quotes break our application
     *
     * Parameters:
     *     $data - The array to be sanitized, usually one of @$_GET@, @$_POST@, @$_COOKIE@, or @$_REQUEST@
     */
    function sanitize_input(&$data) {
        foreach ($data as &$value)
            if (is_array($value))
                sanitize_input($value);
            else
                $value = get_magic_quotes_gpc() ? stripslashes($value) : $value ;
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
     *
     * Returns:
     *     The response content from the remote site.
     */
    function get_remote($url, $redirects = 0, $timeout = 10) {
        extract(parse_url($url), EXTR_SKIP);
        $content = "";

        if (ini_get("allow_url_fopen")) {
            $context = stream_context_create(array("http" => array("follow_location" => ($redirects == 0) ? 0 : 1 ,
                                                                   "max_redirects" => $redirects,
                                                                   "timeout" => $timeout,
                                                                   "protocol_version" => 1.1,
                                                                   "user_agent" => CHYRP_IDENTITY)));
            $content = @file_get_contents($url, false, $context);
        } elseif (function_exists("curl_init")) {
            $handle = curl_init();
            curl_setopt($handle, CURLOPT_URL, $url);
            curl_setopt($handle, CURLOPT_HEADER, false);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_TIMEOUT, $timeout + 60);
            curl_setopt($handle, CURLOPT_FOLLOWLOCATION, ($redirects == 0) ? false : true );
            curl_setopt($handle, CURLOPT_MAXREDIRS, $redirects);
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($handle, CURLOPT_USERAGENT, CHYRP_IDENTITY);
            $content = curl_exec($handle);
            curl_close($handle);
        } else {
            fallback($path, '/');
            fallback($port, 80);

            if (isset($query))
                $path.= '?'.$query;

            $connect = @fsockopen($host, $port, $errno, $errstr, $timeout);

            if ($connect) {
                $remote_headers = "";

                # Send the GET headers.
                fwrite($connect, "GET ".$path." HTTP/1.1\r\n");
                fwrite($connect, "Host: ".$host."\r\n");
                fwrite($connect, "User-Agent: ".CHYRP_IDENTITY."\r\n\r\n");

                while (!feof($connect) and strpos($remote_headers, "\r\n\r\n") === false)
                    $remote_headers.= fgets($connect);

                while (!feof($connect))
                    $content.= fgets($connect);

                fclose($connect);

                # Search for 301 or 302 header and recurse with new location unless redirects are exhausted.
                if ($redirects > 0 and preg_match("~^HTTP/[0-9]\.[0-9] 30[1-2]~m", $remote_headers)
                                   and preg_match("~^Location:(.+)$~mi", $remote_headers, $matches)) {

                    $location = trim($matches[1]);

                    if (is_url($location))
                        $content = get_remote($location, $redirects - 1, $timeout);
                }
            }
        }

        return $content;
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
    function grab_urls($string) {
        # These expressions capture hyperlinks in HTML and unfiltered Markdown.
        $expressions = array("/<a[^>]+href=(\"[^\"]+\"|\'[^\']+\')[^>]*>[^<]+<\/a>/i",
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

        return $urls;
    }

    /**
     * Function: send_pingbacks
     * Sends pingback requests to the URLs in a string.
     *
     * Parameters:
     *     $string - The string to crawl for pingback URLs.
     *     $post - The post we're sending from.
     */
    function send_pingbacks($string, $post) {
        foreach (grab_urls($string) as $url)
            if ($ping_url = pingback_url($url)) {
                $client = new IXR_Client($ping_url);
                $client->timeout = 3;
                $client->useragent = CHYRP_IDENTITY;
                $client->query("pingback.ping", $post->url(), $url);
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
     *     The pingback target, or false if the URL is not pingback-capable.
     */
    function pingback_url($url) {
        extract(parse_url($url), EXTR_SKIP);

        $config = Config::current();
        fallback($path, '/');
        fallback($port, 80);

        if (isset($query))
            $path.= '?'.$query;

        if (!isset($host))
            return false;

        $connect = @fsockopen($host, $port, $errno, $errstr, 2);

        if (!$connect)
            return false;

        $remote_headers = "";
        $remote_bytes = 0;

        # Send the GET headers.
        fwrite($connect, "GET ".$path." HTTP/1.1\r\n");
        fwrite($connect, "Host: $host\r\n");
        fwrite($connect, "User-Agent: ".CHYRP_IDENTITY."\r\n\r\n");

        # Check for X-Pingback header.
        while (!feof($connect)) {
            $line = fgets($connect, 512);

            if (trim($line) == "")
                break;

            $remote_headers.= trim($line)."\n";

            if (preg_match("/X-Pingback: (.+)/i", $line, $matches))
                return trim($matches[1]);
        }

        # X-Pingback header not found, <link> search if the content can be parsed.
        if (!preg_match("~Content-Type:\s+(text/html|text/sgml|text/xml|text/plain)~im", $remote_headers))
            return false;

        while (!feof($connect)) {
            $line = fgets($connect, 1024);

            if (preg_match("/<link rel=[\"|']pingback[\"|'] href=[\"|']([^\"]+)[\"|'] ?\/?>/i", $line, $link))
                return $link[1];

            $remote_bytes += strlen($line);

            if ($remote_bytes > 2048)
                return false;
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
    function load_info($filepath) {
        if (is_file($filepath) and is_readable($filepath))
            $info = include $filepath;

        if (!isset($info) or gettype($info) != "array")
            $info = array();

        fallback($info["name"],          fix(basename(dirname($filepath))));
        fallback($info["version"],       0);
        fallback($info["url"],           "");
        fallback($info["description"],   "");
        fallback($info["author"],        array("name" => "", "url" => ""));
        fallback($info["help"]);
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
    function init_extensions() {
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
    function module_enabled($name) {
        return (!empty(Modules::$instances[$name]) and empty(Modules::$instances[$name]->cancelled));
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
    function feather_enabled($name) {
        return (!empty(Feathers::$instances[$name]) and empty(Feathers::$instances[$name]->cancelled));
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
     function cancel_module($target, $reason = "") {
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
     function cancel_feather($target, $reason = "") {
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
    function upload($file, $filter = null) {
        $uploads_path = MAIN_DIR.Config::current()->uploads_path;
        $filename = upload_filename($file['name'], $filter);

        if ($filename === false)
            error(__("Error"), _f("Only %s files are accepted.", list_notate($filter)));

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
     * Copy a file from a remote URL to the uploads directory.
     *
     * Parameters:
     *     $url - The URL of the resource to be copied.
     *     $redirects - The maximum number of redirects to follow.
     *     $timeout - The maximum number of seconds to wait.
     *
     * Returns:
     *     The filename of the upload relative to the uploads directory.
     */
    function upload_from_url($url, $redirects = 3, $timeout = 10) {
        preg_match("~[^/\?]+(?=($|\?))~i", $url, $matches);
        fallback($matches[0], md5($url).".bin");

        $uploads_path = MAIN_DIR.Config::current()->uploads_path;
        $filename = upload_filename($matches[0]);
        $contents = get_remote($url, $redirects, $timeout);

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
    function uploaded($file, $url = true) {
        $config = Config::current();

        return ($url ?
                $config->chyrp_url.str_replace(DIR, "/", $config->uploads_path).urlencode($file) :
                MAIN_DIR.$config->uploads_path.$file);
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
    function upload_tester($file) {
        $success = false;
        $results = array();
        $maximum = Config::current()->uploads_limit;

        # Recurse to test multiple uploads file by file using a one-dimensional array.
        if (is_array($file['name'])) {
            for ($i=0; $i < count($file['name']); $i++)
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
     *     A sanitized unique version of the supplied filename.
     */
    function upload_filename($filename, $filter = array()) {
        $patterns = !empty($filter) ?
            implode("|", array_map("preg_quote", $filter)) : "tar\.[a-z0-9]+|[a-z0-9]+" ;

        $disallow = "htaccess|php|php3|php4|php5|php7|phps|phtml|shtml|shtm|stm|cgi|asp|aspx";

        # Extract the file's basename and extension, disallow harmful extensions.
        preg_match("/(.+?)(\.($patterns)(?<!$disallow))?$/i", $filename, $matches);

        # Return false if a valid extension was not extracted.
        if (!empty($filter) and empty($matches[3]))
            return false;

        $extension = fallback($matches[3], "bin");
        $sanitized = oneof(sanitize(fallback($matches[1], ""), true, true, 80), md5($filename));
        $count = 1;
        $unique = $sanitized.".".$extension;

        while (file_exists(uploaded($unique, false))) {
            $count++;
            $unique = $sanitized."-".$count.".".$extension;
        }

        return $unique;
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
    function password_strength($password = "") {
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
    function is_url($string) {
        return (preg_match('~^(http://|https://)?([a-z0-9][a-z0-9\-\.]*[a-z0-9]\.[a-z]{2,63}\.?)(:[0-9]{1,5})?($|/)~i', $string) or
                preg_match('~^(http://|https://)?([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})(:[0-9]{1,5})?($|/)~', $string) or
                preg_match('~^(http://|https://)?(\[[a-f0-9\:]{3,39}\])(:[0-9]{1,5})?($|/)~i', $string));
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
    function add_scheme($url, $scheme = null) {
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
    function is_email($string) {
        return (preg_match('~^[^ @]+@([a-z0-9][a-z0-9\-\.]*[a-z0-9]\.[a-z]{2,63}\.?)$~i', $string) or
                preg_match('~^[^ @]+@([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})$~', $string) or
                preg_match('~^[^ @]+@(\[[a-f0-9\:]{3,39}\])$~i', $string));
    }

    /**
     * Function: generate_captcha
     * Generates a captcha form element.
     *
     * Returns:
     *     A string containing HTML elements to add to a form.
     */
    function generate_captcha() {
        foreach (get_declared_classes() as $class)
            if (in_array("Captcha", class_implements($class)))
                return call_user_func($class."::getCaptcha");

        return false;
    }

    /**
     * Function: check_captcha
     * Checks if the answer to a captcha is right.
     *
     * Returns:
     *     Whether or not the captcha was defeated.
     */
    function check_captcha() {
        foreach (get_declared_classes() as $class)
            if (in_array("Captcha", class_implements($class)))
                return call_user_func($class."::verifyCaptcha");

        return false;
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
    function get_gravatar($email, $s = 80, $img = false, $d = "mm", $r = "g") {
        $url = "http://www.gravatar.com/avatar/".md5(strtolower(trim($email)))."?s=$s&d=$d&r=$r";
        return ($img) ? '<img class="gravatar" src="'.fix($url, true, true).'">' : $url ;
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
     *
     * Returns:
     *     A JSON encoded string or false on failure.
     */
    function json_set($value, $options = 0) {
        $encoded = json_encode($value, $options);

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
     *
     * Returns:
     *     A JSON decoded value of the appropriate PHP type.
     */
    function json_get($value, $assoc = false, $depth = 512) {
        $decoded = json_decode($value, $assoc, $depth);

        if (json_last_error())
            trigger_error(_f("JSON decoding error: %s", fix(json_last_error_msg(), false, true)), E_USER_WARNING);

        return $decoded;
    }

    /**
     * Function: json_response
     * Sends a structured JSON response and exits immediately.
     *
     * Parameters:
     *     $text - A string containing a response message.
     *     $data - Arbitrary data to be sent with the response.
     */
    function json_response($text = null, $data = null) {
        header("Content-Type: application/json; charset=UTF-8");
        exit(json_set(array("text" => $text, "data" => $data)));
    }

    /**
     * Function: file_attachment
     * Send a file attachment to the visitor.
     *
     * Parameters:
     *     $contents - The bitstream to be delivered to the visitor.
     *     $filename - The name to be applied to the content upon download.
     */
    function file_attachment($contents = "", $filename = "caconym") {
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"".addslashes($filename)."\"");

        if (!in_array("ob_gzhandler", ob_list_handlers()))
            header("Content-Length: ".strlen($contents));

        echo $contents;
    }

    /**
     * Function: email
     * Send an email. Function arguments are exactly the same as the PHP mail() function.
     * This is intended so that modules can provide an email method if the server cannot use mail().
     */
    function email() {
        $function = "mail";
        Trigger::current()->filter($function, "send_mail");
        $args = func_get_args(); # Looks redundant, but it must be so in order to meet PHP's retardation requirements.
        return call_user_func_array($function, $args);
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
                             "Reply-To: ".$config->email. "\r\n".
                             "X-Mailer: ".CHYRP_IDENTITY;

        fallback($params["subject"], "");
        fallback($params["message"], "");

        switch ($action) {
            case "activate":
                $params["subject"] = _f("Activate your account at %s", $config->name);
                $params["message"] = _f("Hello, %s.", $params["login"]).
                                     "\r\n"."\r\n".
                                     __("You are receiving this message because you registered a new account.").
                                     "\r\n"."\r\n".
                                     __("Visit this link to activate your account:").
                                     "\r\n".
                                     unfix($params["link"]);
                break;
            case "reset":
                $params["subject"] = _f("Reset your password at %s", $config->name);
                $params["message"] = _f("Hello, %s.", $params["login"]).
                                     "\r\n"."\r\n".
                                     __("You are receiving this message because you requested a new password.").
                                     "\r\n"."\r\n".
                                     __("Visit this link to reset your password:").
                                     "\r\n".
                                     unfix($params["link"]);
                break;
            case "password":
                $params["subject"] = _f("Your new password for %s", $config->name);
                $params["message"] = _f("Hello, %s.", $params["login"]).
                                     "\r\n"."\r\n".
                                     _f("Your new password is: %s", $params["password"]);
                break;
            default:
                if ($trigger->exists("correspond_".$action))
                    $trigger->filter($params, "correspond_".$action);
                else
                    return false;
        }

        return email($params["to"], $params["subject"], $params["message"], $params["headers"]);
    }
