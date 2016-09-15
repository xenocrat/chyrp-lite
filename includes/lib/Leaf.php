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
                # Helpers
                new Twig_SimpleFunction("url",                 "url"),
                new Twig_SimpleFunction("admin_url",           "admin_url"),
                new Twig_SimpleFunction("self_url",            "self_url"),
                new Twig_SimpleFunction("module_enabled",      "module_enabled"),
                new Twig_SimpleFunction("feather_enabled",     "feather_enabled"),
                new Twig_SimpleFunction("is_url",              "is_url"),
                new Twig_SimpleFunction("is_email",            "is_email"),
                new Twig_SimpleFunction("password_strength",   "password_strength"),

                # Custom functions
                new Twig_SimpleFunction("paginate",             "twig_function_paginate")
            );
        }

        /**
         * Function: getFilters
         * Returns a list of filters to add to the existing list.
         */
        public function getFilters() {
            return array(
                # PHP
                new Twig_SimpleFilter("repeat",                "str_repeat"),

                # Helpers
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

                # Custom filters
                new Twig_SimpleFilter("translate",             "twig_filter_translate"),
                new Twig_SimpleFilter("translate_plural",      "twig_filter_translate_plural"),
                new Twig_SimpleFilter("strftimeformat",        "twig_filter_strftime_format"),
                new Twig_SimpleFilter("filesizeformat",        "twig_filter_filesize_format"),
                new Twig_SimpleFilter("match",                 "twig_filter_match"),
                new Twig_SimpleFilter("contains",              "twig_filter_contains"),
                new Twig_SimpleFilter("inspect",               "twig_filter_inspect"),
                new Twig_SimpleFilter("selected",              "twig_filter_selected"),
                new Twig_SimpleFilter("checked",               "twig_filter_checked"),
                new Twig_SimpleFilter("download",              "twig_filter_download")
            );
        }
    }

    /**
     * Function: twig_callback_missing_function
     * Scans enabled modules for a callable method matching the name of a missing Twig function.
     */
    function twig_callback_missing_function($name) {
        foreach (Modules::$instances as $module)
            if (is_callable(array($module, "twig_".$name)))
                return new Twig_SimpleFunction($name, get_class($module)."::twig_".$name);

        return false;
    }

    /**
     * Function: twig_callback_missing_filter
     * Scans enabled modules for a callable method matching the name of a missing Twig filter.
     */
    function twig_callback_missing_filter($name) {
        foreach (Modules::$instances as $module)
            if (is_callable(array($module, "twig_".$name)))
                return new Twig_SimpleFilter($name, get_class($module)."::twig_".$name);

        return false;
    }

    /**
     * Function: twig_function_paginate
     * Paginates an array of items using the Paginator class.
     */
    function twig_function_paginate($array, $per_page = 10, $name = "twig") {
        $name = str_replace("_", "", $name)."_page"; # This is important for clean URL parsing in MainController.

        while (in_array($name, Paginator::$names))
            $name = "-".$name;

        return new Paginator($array, $per_page, $name);
    }

    /**
     * Function: twig_filter_translate
     * Returns a translated string.
     */
    function twig_filter_translate($string, $domain = "theme") {
        $domain = ($domain == "theme" and ADMIN) ? "admin" : $domain ;
        return __($string, $domain);
    }

    /**
     * Function: twig_filter_translate_plural
     * Returns a plural (or not) form of a translated string.
     */
    function twig_filter_translate_plural($single, $plural, $number, $domain = "theme") {
        $domain = ($domain == "theme" and ADMIN) ? "admin" : $domain ;
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
    function twig_filter_match($haystack, $try) {
        return match($try, $haystack);
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
