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
                new Twig_SimpleFunction("module_enabled",      "module_enabled"),
                new Twig_SimpleFunction("feather_enabled",     "feather_enabled"),
                new Twig_SimpleFunction("password_strength",   "password_strength")
            );
        }

        /**
         * Function: getFilters
         * Returns a list of filters to add to the existing list.
         */
        public function getFilters() {
            return array(   
                # Helpers
                new Twig_SimpleFilter("camelize",              "camelize"),
                new Twig_SimpleFilter("decamelize",            "decamelize"),
                new Twig_SimpleFilter("normalize",             "normalize"),
                new Twig_SimpleFilter("truncate",              "truncate"),
                new Twig_SimpleFilter("pluralize",             "pluralize"),
                new Twig_SimpleFilter("depluralize",           "depluralize"),
                new Twig_SimpleFilter("oneof",                 "oneof"),
                new Twig_SimpleFilter("fix",                   "fix"),
                new Twig_SimpleFilter("sanitize",              "sanitize"),
                new Twig_SimpleFilter("sanitize_html",         "sanitize_html"),
                new Twig_SimpleFilter("token",                 "token"),
                new Twig_SimpleFilter("uploaded",              "uploaded"),
                new Twig_SimpleFilter("gravatar",              "get_gravatar"),

                # Custom filters
                new Twig_SimpleFilter("translate",             "twig_filter_translate_string"),
                new Twig_SimpleFilter("translate_plural",      "twig_filter_translate_plural_string"),
                new Twig_SimpleFilter("strftimeformat",        "twig_filter_strftime_format"),
                new Twig_SimpleFilter("filesizeformat",        "twig_filter_filesize_format"),
                new Twig_SimpleFilter("repeat",                "twig_filter_repeat"),
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
            if (is_callable(array($module, $name)))
                return new Twig_SimpleFunction($name, get_class($module)."::".$name);

        return false;
    }

    /**
     * Function: twig_callback_missing_filter
     * Scans enabled modules for a callable method matching the name of a missing Twig filter.
     */
    function twig_callback_missing_filter($name) {
        foreach (Modules::$instances as $module)
            if (is_callable(array($module, $name)))
                return new Twig_SimpleFilter($name, get_class($module)."::".$name);

        return false;
    }

    function twig_filter_translate_string($string, $domain = "theme") {
        $domain = ($domain == "theme" and ADMIN) ? "admin" : $domain ;
        return __($string, $domain);
    }

    function twig_filter_translate_plural_string($single, $plural, $number, $domain = "theme") {
        $domain = ($domain == "theme" and ADMIN) ? "admin" : $domain ;
        return _p($single, $plural, $number, $domain);
    }

    function twig_filter_strftime_format($timestamp, $format='%x %X') {
        return when($format, $timestamp, true);
    }

    function twig_filter_filesize_format($bytes) {
        if (is_array($bytes))
            $bytes = max($bytes);

        if (is_string($bytes))
            $bytes = intval($bytes);

        if ($bytes >= 1073741824) {
            $value = number_format($bytes / 1073741824, 1);
            return _f("%s GB", $value);
        }

        if ($bytes >= 1048576) {
            $value = number_format($bytes / 1048576, 1);
            return _f("%s MB", $value);
        }

        $value = number_format($bytes / 1024, 1);
        return _f("%s KB", $value);
    }

    function twig_filter_repeat($string, $repetitions = 1) {
        $concat = "";

        if (is_string($string))
            for ($i=0; $i < $repetitions; $i++)
                $concat.= $string;

        return $concat;
    }

    function twig_filter_match($str, $match) {
        return preg_match($match, $str);
    }

    function twig_filter_contains($haystack, $needle) {
        if (is_array($haystack))
            return in_array($needle, $haystack);

        if (is_string($haystack))
            return substr_count($haystack, $needle);

        if (is_object($haystack))
            return in_array($needle, get_object_vars($haystack));

        return false;
    }

    function twig_filter_inspect($thing) {
        if (ini_get("xdebug.var_display_max_depth") == -1)
            return var_dump($thing);
        else
            return '<pre class="chyrp_inspect"><code>'.
                   fix(var_export($thing, true)).
                   '</code></pre>';
    }

    function twig_filter_checked($test) {
        if ($test)
            return " checked";
    }

    function twig_filter_selected($test) {
        $try = func_get_args();
        array_shift($try);

        foreach ($try as $value)
            if ((is_array($value) and in_array($test, $value)) or ($test == $value))
                return " selected";
    }

    function twig_filter_download($filename) {
        return Config::current()->chyrp_url."/includes/download.php?file=".urlencode($filename);
    }
