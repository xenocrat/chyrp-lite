<?php
    /**
     * Class: Leaf
     * Extends the Twig template engine.
     */
    class Leaf extends \Twig\Extension\AbstractExtension {
        /**
         * Function: getFunctions
         * Returns a list of operators to add to the existing list.
         */
        public function getFunctions() {
            return array(
                # Helpers:
                new \Twig\TwigFunction("url",               "url"),
                new \Twig\TwigFunction("self_url",          "self_url"),
                new \Twig\TwigFunction("authenticate",      "authenticate"),
                new \Twig\TwigFunction("module_enabled",    "module_enabled"),
                new \Twig\TwigFunction("feather_enabled",   "feather_enabled"),
                new \Twig\TwigFunction("password_strength", "password_strength"),
                new \Twig\TwigFunction("is_url",            "is_url"),
                new \Twig\TwigFunction("is_email",          "is_email"),
                new \Twig\TwigFunction("generate_captcha",  "generate_captcha"),
                new \Twig\TwigFunction("javascripts",       "javascripts"),

                # Custom functions:
                new \Twig\TwigFunction("paginate",          "twig_function_paginate"),
                new \Twig\TwigFunction("posted",            "twig_function_posted"),
                new \Twig\TwigFunction("mailto",            "twig_function_mailto"),
                new \Twig\TwigFunction("icon_img",          "twig_function_icon_img"),
                new \Twig\TwigFunction("uploaded_search",   "twig_function_uploaded_search"),
                new \Twig\TwigFunction("javascripts_nonce", "twig_function_javascripts_nonce"),
                new \Twig\TwigFunction("stylesheets_nonce", "twig_function_stylesheets_nonce")
            );
        }

        /**
         * Function: getFilters
         * Returns a list of filters to add to the existing list.
         */
        public function getFilters() {
            return array(
                # Internal:
                new \Twig\TwigFilter("repeat",              "str_repeat"),

                # Helpers:
                new \Twig\TwigFilter("camelize",            "camelize"),
                new \Twig\TwigFilter("decamelize",          "decamelize"),
                new \Twig\TwigFilter("normalize",           "normalize"),
                new \Twig\TwigFilter("truncate",            "truncate"),
                new \Twig\TwigFilter("pluralize",           "pluralize"),
                new \Twig\TwigFilter("depluralize",         "depluralize"),
                new \Twig\TwigFilter("markdown",            "markdown"),
                new \Twig\TwigFilter("emote",               "emote"),
                new \Twig\TwigFilter("oneof",               "oneof"),
                new \Twig\TwigFilter("fix",                 "fix"),
                new \Twig\TwigFilter("unfix",               "unfix"),
                new \Twig\TwigFilter("sanitize",            "sanitize"),
                new \Twig\TwigFilter("sanitize_html",       "sanitize_html"),
                new \Twig\TwigFilter("token",               "token"),
                new \Twig\TwigFilter("uploaded",            "uploaded"),
                new \Twig\TwigFilter("gravatar",            "get_gravatar"),
                new \Twig\TwigFilter("add_scheme",          "add_scheme"),
                new \Twig\TwigFilter("lang_base",           "lang_base"),
                new \Twig\TwigFilter("text_direction",      "text_direction"),

                # Custom filters:
                new \Twig\TwigFilter("translate",           "twig_filter_translate"),
                new \Twig\TwigFilter("translate_plural",    "twig_filter_translate_plural"),
                new \Twig\TwigFilter("time",                "twig_filter_time"),
                new \Twig\TwigFilter("dateformat",          "twig_filter_date_format"),
                new \Twig\TwigFilter("strftimeformat",      "twig_filter_strftime_format"),
                new \Twig\TwigFilter("filesizeformat",      "twig_filter_filesize_format"),
                new \Twig\TwigFilter("preg_match",          "twig_filter_preg_match"),
                new \Twig\TwigFilter("preg_replace",        "twig_filter_preg_replace"),
                new \Twig\TwigFilter("contains",            "twig_filter_contains"),
                new \Twig\TwigFilter("inspect",             "twig_filter_inspect"),
                new \Twig\TwigFilter("selected",            "twig_filter_selected"),
                new \Twig\TwigFilter("checked",             "twig_filter_checked"),
                new \Twig\TwigFilter("disabled",            "twig_filter_disabled"),
                new \Twig\TwigFilter("download",            "twig_filter_download"),
                new \Twig\TwigFilter("thumbnail",           "twig_filter_thumbnail")
            );
        }
    }

    /**
     * Function: twig_callback_missing_function
     * Scans callable methods of enabled modules in search of a missing Twig function.
     *
     * Parameters:
     *     $name - The name of the missing Twig function.
     */
    function twig_callback_missing_function($name): \Twig\TwigFunction|false {
        foreach (Modules::$instances as $module) {
            if (is_callable(array($module, "twig_function_".$name)))
                return new \Twig\TwigFunction(
                    $name,
                    array($module, "twig_function_".$name)
                );
        }

        return false;
    }

    /**
     * Function: twig_callback_missing_filter
     * Scans callable methods of enabled modules in search of a missing Twig filter.
     *
     * Parameters:
     *     $name - The name of the missing Twig filter.
     */
    function twig_callback_missing_filter($name): \Twig\TwigFilter|false {
        foreach (Modules::$instances as $module) {
            if (is_callable(array($module, "twig_filter_".$name)))
                return new \Twig\TwigFilter(
                    $name,
                    array($module, "twig_filter_".$name)
                );
        }

        return false;
    }

    /**
     * Function: twig_function_paginate
     * Paginates an array of items using the Paginator class.
     *
     * Parameters:
     *     $array - The array to paginate.
     *     $per_page - The number of items per page.
     *     $name - The $_GET value for the current page.
     */
    function twig_function_paginate(
        $array,
        $per_page = 10,
        $name = "twig"
    ): Paginator {
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
     * Function: twig_function_posted
     * Returns a $_POST value if set, otherwise returns the fallback value.
     *
     * Parameters:
     *     $key - The key to test in the $_POST array.
     *     $fallback - The value to return if the $_POST value is not set.
     */
    function twig_function_posted(
        $index,
        $fallback = ""
    ): string {
        return isset($_POST[$index]) ?
            $_POST[$index] :
            $fallback ;
    }

    /**
     * Function: twig_function_mailto
     * Returns an obfuscated mailto: URL.
     *
     * Parameters:
     *     $email - The email address to obfuscate.
     */
    function twig_function_mailto($email): ?string {
        if (!is_email($email))
            return null;

        # "mailto:" composed in HTML entities.
        $mailto = "&#x0006D;&#x00061;&#x00069;&#x0006C;&#x00074;&#x0006F;&#x0003A;";

        # Substitute common ASCII chars for URL encodings composed in HTML entities.
        $double_encode = array(
            "/^-$/"  => "&#x00025;&#x00032;&#x00044;",
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
            "/^z$/"  => "&#x00025;&#x00037;&#x00041;"
        );

        $chars = str_split($email);

        foreach ($chars as &$char)
            $char = preg_replace(
                array_keys($double_encode),
                array_values($double_encode),
                $char
            );

        return $mailto.implode("", $chars);
    }

    /**
     * Function: twig_function_icon_img
     * Returns a URL to the requested icon resource.
     *
     * Parameters:
     *     $filename - The icon filename.
     *     $alt_text - The alternative text for the image.
     *     $class - The CSS class for the link.
     */
    function twig_function_icon_img(
        $filename,
        $alt_text = "",
        $class = null
    ): string {
        $url = Config::current()->chyrp_url.
               "/admin/images/icons/".$filename;

        $img = '<img src="'.fix($url, true).
               '" alt="'.fix($alt_text, true);

        if (isset($class) and $class !== false)
            $img.= '" class="'.fix($class, true);

        $img.= '">';

        return $img;
    }

    /**
     * Function: twig_function_uploaded_search
     * Returns an array of matches, if the visitor has the "export_content" privilege.
     *
     * Parameters:
     *     $search - A search term.
     *     $filter - An array of valid extensions (case insensitive).
     */
    function twig_function_uploaded_search(
        $search = "",
        $filter = array()
    ): array {
        if (!Visitor::current()->group->can("edit_post", "edit_page", true))
            return array();

        return uploaded_search($search, $filter);
    }

    /**
     * Function: twig_function_javascripts_nonce
     * Returns a nonce value to enable inline JavaScript with a Content Security Policy.
     */
    function twig_function_javascripts_nonce(): string {
        $nonce = "";
        return Trigger::current()->filter($nonce, "javascripts_nonce");
    }

    /**
     * Function: twig_function_stylesheets_nonce
     * Returns a nonce value to enable inline stylesheets with a Content Security Policy.
     */
    function twig_function_stylesheets_nonce(): string {
        $nonce = "";
        return Trigger::current()->filter($nonce, "stylesheets_nonce");
    }

    /**
     * Function: twig_filter_translate
     * Returns a translated string.
     *
     * Parameters:
     *     $text - The string to translate.
     *     $domain - The translation domain to read from.
     */
    function twig_filter_translate(
        $string,
        $domain = null
    ): string {
        if (!isset($domain))
            $domain = (ADMIN) ?
                "admin" :
                Theme::current()->safename ;

        return __($string, $domain);
    }

    /**
     * Function: twig_filter_translate_plural
     * Returns a plural (or not) form of a translated string.
     *
     * Parameters:
     *     $single - Singular string.
     *     $plural - Pluralized string.
     *     $number - The number to judge by.
     *     $domain - The translation domain to read from.
     */
    function twig_filter_translate_plural(
        $single,
        $plural,
        $number,
        $domain = null
    ): string {
        if (!isset($domain))
            $domain = (ADMIN) ?
                "admin" :
                Theme::current()->safename ;

        return _p($single, $plural, $number, $domain);
    }

    /**
     * Function: twig_filter_time
     * Returns a <time> HTML element containing an internationalized time representation.
     *
     * Parameters:
     *     $timestamp - A time value to be strtotime() converted.
     *     $format - The formatting for the <time> representation.
     */
    function twig_filter_time(
        $timestamp,
        $format = null
    ): string {
        if (!isset($format))
            $format = (ADMIN) ? "Y-m-d" : "d F Y" ;

        $string = _w($format, $timestamp);
        $datetime = when("c", $timestamp);
        return "<time datetime=\"".$datetime."\">".$string."</time>";
    }

    /**
     * Function: twig_filter_date_format
     * Returns date formatting for a string that isn't a regular time() value.
     *
     * Parameters:
     *     $timestamp - A time value to be strtotime() converted.
     *     $formatting - The formatting for date().
     */
    function twig_filter_date_format(
        $timestamp,
        $format = null
    ): string {
        if (!isset($format))
            $format = (ADMIN) ? "Y-m-d" : "d F Y" ;

        return when($format, $timestamp);
    }

    /**
     * Function: twig_filter_strftime_format
     * Returns date formatting for a string that isn't a regular time() value.
     *
     * Parameters:
     *     $timestamp - A time value to be strtotime() converted.
     * 
     * Notes:
     *     Uses date() instead of strftime(). Retained for backwards compatibility.
     */
    function twig_filter_strftime_format(
        $timestamp,
        $format = null
    ): string {
        return when("Y-m-d H:i:s", $timestamp);
    }

    /**
     * Function: twig_filter_filesize_format
     * Returns a string containing a formatted filesize value.
     *
     * Parameters:
     *     $bytes - The filesize in bytes.
     */
    function twig_filter_filesize_format($bytes): string {
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
     * Function: twig_filter_preg_match
     * Try to match a string against an array of regular expressions.
     *
     * Parameters:
     *     $try - An array of regular expressions, or a single regular expression.
     *     $haystack - The string to test.
     */
    function twig_filter_preg_match(
        $haystack,
        $try
    ): bool {
        return match_any($try, $haystack);
    }

    /**
     * Function: twig_filter_preg_replace
     * Performs a <preg_replace> on the supplied string or array.
     *
     * Parameters:
     *     $subject - The input string.
     *     $pattern - The regular expression to match.
     *     $replacement - The replacement string.
     *     $limit - The maximum number of replacements.
     */
    function twig_filter_preg_replace(
        $subject,
        $pattern,
        $replacement,
        $limit = -1
    ): ?string {
        return preg_replace($pattern, $replacement, $subject, $limit);
    }

    /**
     * Function: twig_filter_contains
     * Does the haystack variable contain the needle variable?
     *
     * Parameters:
     *     $haystack - The variable to search within.
     *     $needle - The variable to search for.
     */
    function twig_filter_contains(
        $haystack,
        $needle
    ): int|bool {
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
     *
     * Parameters:
     *     $variable - The variable to inspect.
     */
    function twig_filter_inspect($variable): string {
        return '<pre class="chyrp_inspect"><code>'.
               fix(var_export($variable, true)).
               '</code></pre>';
    }

    /**
     * Function: twig_filter_checked
     * Returns a HTML @checked@ attribute if the test evalutaes to true.
     *
     * Parameters:
     *     $test - The variable to test.
     */
    function twig_filter_checked($test): string {
        return ($test) ? " checked" : "" ;
    }

    /**
     * Function: twig_filter_selected
     * Returns a HTML @selected@ attribute if the
     * test matches any of the supplied arguments.
     *
     * Parameters:
     *     $test - The variable to test.
     */
    function twig_filter_selected($test): string {
        $try = func_get_args();
        array_shift($try);

        foreach ($try as $value) {
            if (
                (is_array($value) and in_array($test, $value)) or
                ($test == $value)
            )
                return " selected";
        }

        return "";
    }

    /**
     * Function: twig_filter_disabled
     * Returns a HTML @disabled@ attribute if the
     * test matches any of the supplied arguments.
     *
     * Parameters:
     *     $test - The variable to test.
     */
    function twig_filter_disabled($test): string {
        $try = func_get_args();
        array_shift($try);

        foreach ($try as $value) {
            if (
                (is_array($value) and in_array($test, $value)) or
                ($test == $value)
            )
                return " disabled";
        }

        return "";
    }

    /**
     * Function: twig_filter_download
     * Returns a download link for a file located in the uploads directory.
     *
     * Parameters:
     *     $filename - The uploaded filename.
     */
    function twig_filter_download($filename): string {
        $filepath = Config::current()->chyrp_url.
                    "/includes/download.php?file=".
                    urlencode($filename);

        return fix($filepath, true);
    }

    /**
     * Function: twig_filter_thumbnail
     * Returns a thumbnail <img> tag for an uploaded image file,
     * optionally with sizes/srcset attributes and enclosing <a> tag.
     *
     * Parameters:
     *     $filename - The uploaded filename.
     *     $alt_text - The alternative text for the image.
     *     $url - A URL, @true@ to link to the uploaded file, @false@ to disable.
     *     $args - An array of additional arguments to be appended as GET parameters.
     *     $sizes - A string, @true@ to use "100vw", @false@ to disable sizes/srcset.
     *     $lazy - Specify lazy-loading for this image?
     */
    function twig_filter_thumbnail(
        $filename,
        $alt_text = "",
        $url = null,
        $args = array(),
        $sizes = null,
        $lazy = true
    ): string {
        $filepath = Config::current()->chyrp_url.
                    "/includes/thumbnail.php?file=".
                    urlencode($filename);

        $args_filtered = array();

        foreach ($args as $arg) {
            if (strpos($arg, "max_width=") === 0)
                continue;

            if (strpos($arg, "max_height=") === 0)
                continue;

            $args_filtered[] = $arg;
        }

        $src_args = implode("&", $args);
        $set_args = implode("&", $args_filtered);

        if (!empty($src_args))
            $src_args = "&".$src_args;

        if (!empty($set_args))
            $set_args = "&".$set_args;

        # Source set for responsive images.
        $srcset = array(
            $filepath."&max_width=2160".$set_args." 2160w",
            $filepath."&max_width=1440".$set_args." 1440w",
            $filepath."&max_width=1080".$set_args." 1080w",
            $filepath."&max_width=720".$set_args." 720w",
            $filepath."&max_width=360".$set_args." 360w",
            $filepath."&max_width=180".$set_args." 180w"
        );

        $img = '<img src="'.fix($filepath.$src_args, true).
               '" alt="'.fix($alt_text, true).
               '" class="image" loading="'.($lazy ? 'lazy' : 'eager');

        # Add srcset/sizes attributes? Provide @true@ or a string.
        if (isset($sizes) and $sizes !== false) {
            $sizes = is_string($sizes) ?
                $sizes :
                "100vw" ;

            $img.= '" sizes="'.fix($sizes, true).
                   '" srcset="'.fix(implode(", ", $srcset), true);
        }

        $img.= '">';

        # Enclose in <a> tag? Provide @true@ or a candidate URL.
        if (isset($url) and $url !== false) {
            $url = (is_url($url)) ?
                fix($url, true) :
                uploaded($filename) ;

            $img = '<a href="'.$url.
                   '" class="image_link" aria-label="'.
                   __("Image source").'">'.$img.'</a>';
        }

        return $img;
    }
