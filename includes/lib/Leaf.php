<?php
    /**
     * Class: Leaf
     * Extends the Twig template engine.
     */
    class Leaf extends Twig_Extension {
        /**
         * Function: getName
         * Returns the name of the extension.
         */
        public function getName() {
            return "Leaf";
        }

        /**
         * Function: getFunctions
         * Returns a list of operators to add to the existing list.
         */
        public function getFunctions() {
            return array(   
                # Helpers:
                new Twig_SimpleFunction("url",                 "url"),
                new Twig_SimpleFunction("self_url",            "self_url"),
                new Twig_SimpleFunction("authenticate",        "authenticate"),
                new Twig_SimpleFunction("module_enabled",      "module_enabled"),
                new Twig_SimpleFunction("feather_enabled",     "feather_enabled"),
                new Twig_SimpleFunction("password_strength",   "password_strength"),
                new Twig_SimpleFunction("is_url",              "is_url"),
                new Twig_SimpleFunction("is_email",            "is_email"),
                new Twig_SimpleFunction("generate_captcha",    "generate_captcha"),

                # Custom functions:
                new Twig_SimpleFunction("paginate",            "twig_function_paginate"),
                new Twig_SimpleFunction("mailto",              "twig_function_mailto")
            );
        }

        /**
         * Function: getFilters
         * Returns a list of filters to add to the existing list.
         */
        public function getFilters() {
            return array(
                # Internal:
                new Twig_SimpleFilter("repeat",                "str_repeat"),

                # Helpers:
                new Twig_SimpleFilter("camelize",              "camelize"),
                new Twig_SimpleFilter("decamelize",            "decamelize"),
                new Twig_SimpleFilter("normalize",             "normalize"),
                new Twig_SimpleFilter("truncate",              "truncate"),
                new Twig_SimpleFilter("pluralize",             "pluralize"),
                new Twig_SimpleFilter("depluralize",           "depluralize"),
                new Twig_SimpleFilter("emote",                 "emote"),
                new Twig_SimpleFilter("oneof",                 "oneof"),
                new Twig_SimpleFilter("fix",                   "fix"),
                new Twig_SimpleFilter("unfix",                 "unfix"),
                new Twig_SimpleFilter("sanitize",              "sanitize"),
                new Twig_SimpleFilter("sanitize_html",         "sanitize_html"),
                new Twig_SimpleFilter("token",                 "token"),
                new Twig_SimpleFilter("uploaded",              "uploaded"),
                new Twig_SimpleFilter("gravatar",              "get_gravatar"),
                new Twig_SimpleFilter("add_scheme",            "add_scheme"),

                # Custom filters:
                new Twig_SimpleFilter("translate",             "twig_filter_translate"),
                new Twig_SimpleFilter("translate_plural",      "twig_filter_translate_plural"),
                new Twig_SimpleFilter("strftimeformat",        "twig_filter_strftime_format"),
                new Twig_SimpleFilter("filesizeformat",        "twig_filter_filesize_format"),
                new Twig_SimpleFilter("preg_match",            "twig_filter_preg_match"),
                new Twig_SimpleFilter("preg_replace",          "twig_filter_preg_replace"),
                new Twig_SimpleFilter("contains",              "twig_filter_contains"),
                new Twig_SimpleFilter("inspect",               "twig_filter_inspect"),
                new Twig_SimpleFilter("selected",              "twig_filter_selected"),
                new Twig_SimpleFilter("checked",               "twig_filter_checked"),
                new Twig_SimpleFilter("download",              "twig_filter_download"),
                new Twig_SimpleFilter("thumbnail",             "twig_filter_thumbnail")
            );
        }
    }

    /**
     * Function: twig_callback_missing_function
     * Scans enabled modules for a callable method matching the name of a missing Twig function.
     */
    function twig_callback_missing_function($name) {
        foreach (Modules::$instances as $module)
            if (is_callable(array($module, "twig_function_".$name)))
                return new Twig_SimpleFunction($name, array($module, "twig_function_".$name));

        return false;
    }

    /**
     * Function: twig_callback_missing_filter
     * Scans enabled modules for a callable method matching the name of a missing Twig filter.
     */
    function twig_callback_missing_filter($name) {
        foreach (Modules::$instances as $module)
            if (is_callable(array($module, "twig_filter_".$name)))
                return new Twig_SimpleFilter($name, array($module, "twig_filter_".$name));

        return false;
    }

    /**
     * Function: twig_function_paginate
     * Paginates an array of items using the Paginator class.
     */
    function twig_function_paginate($array, $per_page = 10, $name = "twig") {
        # This is important for clean URL parsing in MainController.
        $name = str_replace("_", "-", $name)."_page";

        $count = 1;
        $unique = $name;

        while (in_array($unique, Paginator::$names)) {
            $count++;
            $unique = $name."-".$count;
        }

        return new Paginator($array, $per_page, $unique);
    }

    /**
     * Function: twig_function_mailto
     * Returns an obfuscated mailto: URL.
     */
    function twig_function_mailto($email) {
        if (!is_email($email))
            return false;

        # "mailto:" composed in HTML entities.
        $mailto = "&#x0006D;&#x00061;&#x00069;&#x0006C;&#x00074;&#x0006F;&#x0003A;";

        # Substitute common ASCII chars for URL encodings composed in HTML entities.
        $double_encode = array("/^-$/"  => "&#x00025;&#x00032;&#x00044;",
                               "/^\.$/" => "&#x00025;&#x00032;&#x00045;",
                               "/^0$/"  => "&#x00025;&#x00033;&#x00030;",
                               "/^1$/"  => "&#x00025;&#x00033;&#x00031;",
                               "/^2$/"  => "&#x00025;&#x00033;&#x00032;",
                               "/^3$/"  => "&#x00025;&#x00033;&#x00033;",
                               "/^4$/"  => "&#x00025;&#x00033;&#x00034;",
                               "/^5$/"  => "&#x00025;&#x00033;&#x00035;",
                               "/^6$/"  => "&#x00025;&#x00033;&#x00036;",
                               "/^7$/"  => "&#x00025;&#x00033;&#x00037;",
                               "/^8$/"  => "&#x00025;&#x00033;&#x00038;",
                               "/^9$/"  => "&#x00025;&#x00033;&#x00039;",
                               "/^@$/"  => "&#x00025;&#x00034;&#x00030;",
                               "/^A$/"  => "&#x00025;&#x00034;&#x00031;",
                               "/^B$/"  => "&#x00025;&#x00034;&#x00032;",
                               "/^C$/"  => "&#x00025;&#x00034;&#x00033;",
                               "/^D$/"  => "&#x00025;&#x00034;&#x00034;",
                               "/^E$/"  => "&#x00025;&#x00034;&#x00035;",
                               "/^F$/"  => "&#x00025;&#x00034;&#x00036;",
                               "/^G$/"  => "&#x00025;&#x00034;&#x00037;",
                               "/^H$/"  => "&#x00025;&#x00034;&#x00038;",
                               "/^I$/"  => "&#x00025;&#x00034;&#x00039;",
                               "/^J$/"  => "&#x00025;&#x00034;&#x00041;",
                               "/^K$/"  => "&#x00025;&#x00034;&#x00042;",
                               "/^L$/"  => "&#x00025;&#x00034;&#x00043;",
                               "/^M$/"  => "&#x00025;&#x00034;&#x00044;",
                               "/^N$/"  => "&#x00025;&#x00034;&#x00045;",
                               "/^O$/"  => "&#x00025;&#x00034;&#x00046;",
                               "/^P$/"  => "&#x00025;&#x00035;&#x00030;",
                               "/^Q$/"  => "&#x00025;&#x00035;&#x00031;",
                               "/^R$/"  => "&#x00025;&#x00035;&#x00032;",
                               "/^S$/"  => "&#x00025;&#x00035;&#x00033;",
                               "/^T$/"  => "&#x00025;&#x00035;&#x00034;",
                               "/^U$/"  => "&#x00025;&#x00035;&#x00035;",
                               "/^V$/"  => "&#x00025;&#x00035;&#x00036;",
                               "/^W$/"  => "&#x00025;&#x00035;&#x00037;",
                               "/^X$/"  => "&#x00025;&#x00035;&#x00038;",
                               "/^Y$/"  => "&#x00025;&#x00035;&#x00039;",
                               "/^Z$/"  => "&#x00025;&#x00035;&#x00041;",
                               "/^_$/"  => "&#x00025;&#x00035;&#x00046;",
                               "/^a$/"  => "&#x00025;&#x00036;&#x00031;",
                               "/^b$/"  => "&#x00025;&#x00036;&#x00032;",
                               "/^c$/"  => "&#x00025;&#x00036;&#x00033;",
                               "/^d$/"  => "&#x00025;&#x00036;&#x00034;",
                               "/^e$/"  => "&#x00025;&#x00036;&#x00035;",
                               "/^f$/"  => "&#x00025;&#x00036;&#x00036;",
                               "/^g$/"  => "&#x00025;&#x00036;&#x00037;",
                               "/^h$/"  => "&#x00025;&#x00036;&#x00038;",
                               "/^i$/"  => "&#x00025;&#x00036;&#x00039;",
                               "/^j$/"  => "&#x00025;&#x00036;&#x00041;",
                               "/^k$/"  => "&#x00025;&#x00036;&#x00042;",
                               "/^l$/"  => "&#x00025;&#x00036;&#x00043;",
                               "/^m$/"  => "&#x00025;&#x00036;&#x00044;",
                               "/^n$/"  => "&#x00025;&#x00036;&#x00045;",
                               "/^o$/"  => "&#x00025;&#x00036;&#x00046;",
                               "/^p$/"  => "&#x00025;&#x00037;&#x00030;",
                               "/^q$/"  => "&#x00025;&#x00037;&#x00031;",
                               "/^r$/"  => "&#x00025;&#x00037;&#x00032;",
                               "/^s$/"  => "&#x00025;&#x00037;&#x00033;",
                               "/^t$/"  => "&#x00025;&#x00037;&#x00034;",
                               "/^u$/"  => "&#x00025;&#x00037;&#x00035;",
                               "/^v$/"  => "&#x00025;&#x00037;&#x00036;",
                               "/^w$/"  => "&#x00025;&#x00037;&#x00037;",
                               "/^x$/"  => "&#x00025;&#x00037;&#x00038;",
                               "/^y$/"  => "&#x00025;&#x00037;&#x00039;",
                               "/^z$/"  => "&#x00025;&#x00037;&#x00041;");

        $chars = str_split($email);

        foreach ($chars as &$char)
            $char = preg_replace(array_keys($double_encode), array_values($double_encode), $char);

        return $mailto.implode("", $chars);
    }

    /**
     * Function: twig_filter_translate
     * Returns a translated string.
     */
    function twig_filter_translate($string, $domain = null) {
        if (!isset($domain))
            $domain = (ADMIN) ? "admin" : Theme::current()->safename ;

        return __($string, $domain);
    }

    /**
     * Function: twig_filter_translate_plural
     * Returns a plural (or not) form of a translated string.
     */
    function twig_filter_translate_plural($single, $plural, $number, $domain = null) {
        if (!isset($domain))
            $domain = (ADMIN) ? "admin" : Theme::current()->safename ;

        return _p($single, $plural, $number, $domain);
    }

    /**
     * Function: twig_filter_strftime_format
     * Returns date formatting for a string that isn't a regular time() value.
     */
    function twig_filter_strftime_format($timestamp, $format="%c") {
        return when($format, $timestamp, true);
    }

    /**
     * Function: twig_filter_filesize_format
     * Returns a string containing a formatted filesize value.
     */
    function twig_filter_filesize_format($bytes) {
        if (is_array($bytes))
            $bytes = max($bytes);

        if (is_string($bytes))
            $bytes = intval($bytes);

        if ($bytes >= 1000000000) {
            $value = number_format($bytes / 1000000000, 1);
            return _f("%s GB", $value);
        }

        if ($bytes >= 1048576) {
            $value = number_format($bytes / 1000000, 1);
            return _f("%s MB", $value);
        }

        $value = number_format($bytes / 1000, 1);
        return _f("%s KB", $value);
    }

    /**
     * Function: twig_filter_match
     * Try to match a string against an array of regular expressions, or a single regular expression.
     */
    function twig_filter_preg_match($haystack, $try) {
        return match($try, $haystack);
    }

    /**
     * Function: twig_filter_preg_replace
     * Performs a <preg_replace> on the supplied string or array.
     */
    function twig_filter_preg_replace($subject, $pattern, $replacement, $limit = -1) {
        return preg_replace($pattern, $replacement, $subject, $limit);
    }

    /**
     * Function: twig_filter_contains
     * Does the haystack variable contain the needle variable?
     */
    function twig_filter_contains($haystack, $needle) {
        if (is_array($haystack))
            return in_array($needle, $haystack);

        if (is_string($haystack))
            return substr_count($haystack, $needle);

        if (is_object($haystack))
            return in_array($needle, get_object_vars($haystack));

        return false;
    }

    /**
     * Function: twig_filter_inspect
     * Exports a variable for inspection.
     */
    function twig_filter_inspect($variable) {
        return '<pre class="chyrp_inspect"><code>'.fix(var_export($variable, true)).'</code></pre>';
    }

    /**
     * Function: twig_filter_checked
     * Returns a HTML @checked@ attribute if the test evalutaes to true.
     */
    function twig_filter_checked($test) {
        if ($test)
            return " checked";
    }

    /**
     * Function: twig_filter_selected
     * Returns a HTML @selected@ attribute if the test matches any of the supplied arguments.
     */
    function twig_filter_selected($test) {
        $try = func_get_args();
        array_shift($try);

        foreach ($try as $value)
            if ((is_array($value) and in_array($test, $value)) or ($test == $value))
                return " selected";
    }

    /**
     * Function: twig_filter_download
     * Returns a download link for a file located in the uploads directory.
     */
    function twig_filter_download($filename) {
        return Config::current()->chyrp_url."/includes/download.php?file=".urlencode($filename);
    }

    /**
     * Function: twig_filter_thumbnail
     * Returns a thumbnail <img> tag for an uploaded image, optionally with enclosing <a> tag.
     */
    function twig_filter_thumbnail($filename, $alt_text = "", $url = null, $args = array(), $sizes = "100vw") {
        fallback($alt_text, $filename);
        $filepath = Config::current()->chyrp_url."/includes/thumb.php?file=".urlencode($filename);
        $src_args = implode("&amp;", $args);
        $set_args = preg_replace(array("/max_width=[^&]*(&amp;)?/i",
                                       "/max_height=[^&]*(&amp;)?/i"),
                                 "",
                                 $src_args);

        $src_args = !empty($src_args) ? "&amp;".$src_args : $src_args ;
        $set_args = !empty($set_args) ? "&amp;".$set_args : $set_args ;

        # Source set for responsive images.
        $srcset = array($filepath.$src_args." 1x",
                        $filepath."&amp;max_width=960".$set_args." 960w",
                        $filepath."&amp;max_width=640".$set_args." 640w",
                        $filepath."&amp;max_width=320".$set_args." 320w");

        $img = '<img src="'.$filepath.$src_args.'" srcset="'.implode(", ", $srcset).
               '" sizes="'.$sizes.'" alt="'.fix($alt_text, true).'" class="image">';

        # Enclose in <a> tag? Provide @true@ or a candidate URL.
        if (isset($url) and $url !== false)
            $href = (is_string($url) and is_url($url)) ? $url : uploaded($filename) ;

        return isset($href) ?
            '<a href="'.fix($href, true).'" class="image_link">'.$img.'</a>' : $img ;
    }
