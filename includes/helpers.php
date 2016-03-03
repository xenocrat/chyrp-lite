<?php
    /**
     * File: Helpers
     * Various functions used throughout the codebase.
     */

    # Integer: $time_start
    # Times Chyrp.
    $time_start = 0;

    # Array: $l10n
    # Stores loaded gettext domains.
    $l10n = array();

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

        $domain = preg_replace("~^www\.~", "",
            oneof($domain, @$_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']));

        session_set_cookie_params(60 * 60 * 24 * 30, "/", $domain);
        session_name("ChyrpSession");
        register_shutdown_function("session_write_close");
        session_start();
    }

    /**
     * Function: show_403
     * Shows an error message with a 403 HTTP header.
     *
     * Parameters:
     *     $title - The title for the error dialog.
     *     $body - The message for the error dialog.
     */
    function show_403($title, $body) {
        header($_SERVER["SERVER_PROTOCOL"]." 403 Forbidden");

        fallback($title, __("403 Forbidden"));
        fallback($body, __("You do not have permission to access this resource."));

        error($title, $body);
    }

    /**
     * Function: show_404
     * Shows an error message with a 404 HTTP header.
     *
     * Parameters:
     *     $title - The title for the error dialog.
     *     $body - The message for the error dialog.
     */
     function show_404($title, $body) {
        header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");

        fallback($title, __("404 Not Found"));
        fallback($body, __("The requested resource was not found."));

        $theme = Theme::current();
        $main = MainController::current();

        if (TESTER or ADMIN or AJAX or !$theme->file_exists("pages/404"))
            error($title, $body);

        $main->display("pages/404", array("reason" => $body), $title);
        exit;
    }

    /**
     * Function: logged_in
     * Returns whether or not they are logged in by returning the <Visitor.$id> (which defaults to 0).
     */
    function logged_in() {
        return (class_exists("Visitor") and isset(Visitor::current()->id) and Visitor::current()->id != 0);
    }

    /**
     * Function: emote
     * Converts emoticons to equivalent Unicode emoji.
     *
     * Parameters:
     *     $text - The body of the post/page to parse.
     *
     * See Also:
     *     http://www.unicode.org/charts/PDF/U1F600.pdf
     */
    function emote($text) {
        $emoji = array(
            'o:-)' => '&#x1f607;',
            '>:-)' => '&#x1f608;',
            ':-)'  => '&#x1f600;',
            '^_^'  => '&#x1f601;',
            ':-D'  => '&#x1f603;',
            ';-)'  => '&#x1f609;',
            '<3'   => '&#x1f60d;',
            'B-)'  => '&#x1f60e;',
            ':->'  => '&#x1f60f;',
            ':-||' => '&#x1f62c;',
            ':-|'  => '&#x1f611;',
            '-_-'  => '&#x1f612;',
            ':-/'  => '&#x1f615;',
            ':-s'  => '&#x1f616;',
            ':-*'  => '&#x1f618;',
            ':-P'  => '&#x1f61b;',
            ':-((' => '&#x1f629;',
            ':-('  => '&#x1f61f;',
            ';_;'  => '&#x1f622;',
            ':-o'  => '&#x1f62e;',
            'O_O'  => '&#x1f632;',
            ':-$'  => '&#x1f633;',
            'x_x'  => '&#x1f635;',
            ':-x'  => '&#x1f636;'
        );

        foreach($emoji as $key => $value) {
            $text =  str_replace($key, '<span class="emoji">'.$value.'</span>', $text);
        }
        
        return $text;
    }

    /**
     * Function: markdown
     * Implements the Markdown parsing filter.
     *
     * Parameters:
     *     $text - The body of the post/page to parse.
     */
    function markdown($text) {
        $parsedown = new Parsedown();
        return $parsedown->text($text);
    }

    /**
     * Function: load_translator
     * Loads a .mo file for gettext translation.
     *
     * Parameters:
     *     $domain - The name for this translation domain.
     *     $mofile - The .mo file to read from.
     */
    function load_translator($domain, $mofile) {
        global $l10n;

        if (isset($l10n[$domain]))
            return;

        if (is_readable($mofile))
            $input = new CachedFileReader($mofile);
        else
            return;

        $l10n[$domain] = new gettext_reader($input);
    }

    /**
     * Function: set_locale
     * Try to set the locale with fallbacks for platform-specific quirks.
     *
     * Parameters:
     *     $locale - The locale name (e.g. @en_US@, @uk_UA@, @fr_FR@)
     *
     * Notes:
     *     Precedence is given to UTF-8 with a fallback to Windows 1252.
     */
    function set_locale($locale = "en_US") {
        $posix = array($locale.".UTF-8",                  // E.g. "en_US.UTF-8"
                       $locale.".UTF8",                   // E.g. "en_US.UTF8"
                       $locale);                          // E.g. "en_US"
        $win32 = array(str_replace("_", "-", $locale));   // E.g. "en-US"

        if (class_exists("Locale")) {
            $language = Locale::getDisplayLanguage($locale, "en_US");
            $region = Locale::getDisplayRegion("en_US", "en_US");
            array_unshift($win32,
                          $language."_".$region.".1252",  // E.g. "English_United States.1252"
                          $language.".1252",              // E.g. "English.1252"
                          $language);                     // E.g. "English"

            # Set the ICU locale.
            Locale::setDefault($locale);
        }

        # Set the PHP locale.
        setlocale(LC_ALL, array_merge($posix, $win32));
    }

    /**
     * Function: lang_code
     * Converts a language code to a display name.
     *
     * Parameters:
     *     $code - The language code to convert.
     */
    function lang_code($code) {
        return (class_exists("Locale")) ? Locale::getDisplayName($code, $code) : $code ;
    }

    /**
     * Function: __
     * Returns a translated string.
     *
     * Parameters:
     *     $text - The string to translate.
     *     $domain - The translation domain to read from.
     */
    function __($text, $domain = "chyrp") {
        global $l10n;
        return (isset($l10n[$domain])) ? $l10n[$domain]->translate($text) : $text ;
    }

    /**
     * Function: _p
     * Returns a plural (or not) form of a translated string.
     *
     * Parameters:
     *     $single - Singular string.
     *     $plural - Pluralized string.
     *     $number - The number to judge by.
     *     $domain - The translation domain to read from.
     */
    function _p($single, $plural, $number, $domain = "chyrp") {
        global $l10n;
        return (isset($l10n[$domain])) ?
                     $l10n[$domain]->ngettext($single, $plural, $number) : (($number != 1) ? $plural : $single) ;
    }

    /**
     * Function: _f
     * Returns a formatted translated string.
     *
     * Parameters:
     *     $string - String to translate and format.
     *     $args - One arg or an array of arguments to format with.
     *     $domain - The translation domain to read from.
     */
    function _f($string, $args = array(), $domain = "chyrp") {
        $args = (array) $args;
        array_unshift($args, __($string, $domain));
        return call_user_func_array("sprintf", $args);
    }

    /**
     * Function: pluralize
     * Returns a pluralized string. This is a port of Rails's pluralizer.
     *
     * Parameters:
     *     $string - The string to pluralize.
     *     $number - If passed, and this number is 1, it will not pluralize.
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
                              "/(penis)$/i" => "\\1es", # Take that, Rails!
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
     * Returns a depluralized string. This is the inverse of <pluralize>.
     *
     * Parameters:
     *     $string - The string to depluralize.
     *     $number - If passed, and this number is not 1, it will not depluralize.
     */
    function depluralize($string, $number = null) {
        if (isset($number) and $number != 1)
            return $string;

        $replacements = array("/people/i" => "person",
                              "/^men/i" => "man",
                              "/children/i" => "child",
                              "/kine/i" => "cow",
                              "/geese/i" => "goose",
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
     * Function: when
     * Returns date formatting for a string that isn't a regular time() value.
     *
     * Parameters:
     *     $formatting - The formatting for date().
     *     $when - Time to base on. If it is not numeric it will be run through strtotime.
     *     $strftime - Use @strftime@ instead of @date@?
     */
    function when($formatting, $when, $strftime = false) {
        $time = (is_numeric($when)) ? $when : strtotime($when) ;

        if ($strftime)
            return strftime($formatting, $time);
        else
            return date($formatting, $time);
    }

    /**
     * Function: datetime
     * Returns a standard datetime string based on either the passed timestamp or their time offset, usually for MySQL inserts.
     *
     * Parameters:
     *     $when - A timestamp (optional).
     */
    function datetime($when = null) {
        fallback($when, time());

        $time = (is_numeric($when)) ? $when : strtotime($when) ;

        return date("Y-m-d H:i:s", $time);
    }

    /**
     * Function: fix
     * Returns a HTML-sanitized version of a string.
     *
     * Parameters:
     *     $string - String to fix.
     *     $quotes - Encode quotes?
     *     $double - Encode encoded?
     */
    function fix($string, $quotes = false, $double = false) {
        $quotes = ($quotes) ? ENT_QUOTES : ENT_NOQUOTES ;
        return htmlspecialchars($string, $quotes, "utf-8", $double);
    }

    /**
     * Function: unfix
     * Returns the reverse of fix().
     *
     * Parameters:
     *     $string - String to unfix.
     */
    function unfix($string) {
        return htmlspecialchars_decode($string, ENT_QUOTES);
    }

    /**
     * Function: sanitize_html
     * Sanitize html to disable scripts and obnoxious attributes.
     *
     * Parameters:
     *     $string - String to sanitize.
     */
    function sanitize_html($text) {
        $text = preg_replace_callback("/<([a-z][a-z0-9]*)[^>]*?( \/)?>/i", function ($element) {
            $name = strtolower($element[1]);
            fallback($element[2], "");
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

            return "<".
                 $element[1].
                 $whitelist.
                 $element[2].
                 ">";
        }, $text);

        $text = preg_replace("/<script[^>]*?>/i", "&lt;script&gt;", $text);
        $text = preg_replace("/<\/script[^>]*?>/i", "&lt;/script&gt;", $text);
        return $text;
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

    /**
     * Function: sanitize
     * Returns a sanitized string, typically for URLs.
     *
     * Parameters:
     *     $string - The string to sanitize.
     *     $force_lowercase - Force the string to lowercase?
     *     $strict - If set to *true*, will remove all non-alphanumeric characters.
     *     $trunc - Number of characters to truncate to (default 100, 0 to disable).
     */
    function sanitize($string, $force_lowercase = true, $strict = false, $trunc = 100) {
        $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
                       "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
                       "—", "–", ",", "<", ".", ">", "/", "?");
        $clean = trim(str_replace($strip, "", strip_tags($string)));
        $clean = preg_replace('/\s+/', "-", $clean);
        $clean = ($strict ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean);
        $clean = ($trunc ? substr($clean, 0, $trunc) : $clean);
        return ($force_lowercase) ?
            (function_exists('mb_strtolower')) ?
                mb_strtolower($clean, 'UTF-8') :
                strtolower($clean) :
            $clean;
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
     * Function: truncate
     * Truncates a string to the given length, optionally taking into account HTML tags, and/or keeping words intact.
     *
     * Parameters:
     *     $text - String to shorten.
     *     $length - Length to truncate to.
     *     $ending - What to place at the end, e.g. "...".
     *     $exact - Break words?
     *     $html - Auto-close cut-off HTML tags?
     *
     * Author:
     *     CakePHP team, code style modified.
     */
    function truncate($text, $length = 100, $ending = "...", $exact = false, $html = false) {
        if (is_array($ending))
            extract($ending);

        if ($html) {
            if (strlen(preg_replace("/<[^>]+>/", "", $text)) <= $length)
                return $text;

            $totalLength = strlen($ending);
            $openTags = array();
            $truncate = "";
            preg_match_all("/(<\/?([\w+]+)[^>]*>)?([^<>]*)/", $text, $tags, PREG_SET_ORDER);
            foreach ($tags as $tag) {
                if (!preg_match('/img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param/s', $tag[2])
                    and preg_match('/<[\w]+[^>]*>/s', $tag[0]))
                    array_unshift($openTags, $tag[2]);
                elseif (preg_match('/<\/([\w]+)[^>]*>/s', $tag[0], $closeTag)) {
                    $pos = array_search($closeTag[1], $openTags);
                    if ($pos !== false)
                        array_splice($openTags, $pos, 1);
                }

                $truncate .= $tag[1];

                $contentLength = strlen(preg_replace("/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i", " ", $tag[3]));
                if ($contentLength + $totalLength > $length) {
                    $left = $length - $totalLength;
                    $entitiesLength = 0;
                    if (preg_match_all("/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i", $tag[3], $entities, PREG_OFFSET_CAPTURE))
                        foreach ($entities[0] as $entity)
                            if ($entity[1] + 1 - $entitiesLength <= $left) {
                                $left--;
                                $entitiesLength += strlen($entity[0]);
                            } else
                                break;

                    $truncate .= substr($tag[3], 0 , $left + $entitiesLength);

                    break;
                } else {
                    $truncate .= $tag[3];
                    $totalLength += $contentLength;
                }

                if ($totalLength >= $length)
                    break;
            }
        } else {
            if (strlen($text) <= $length)
                return $text;
            else
                $truncate = substr($text, 0, $length - strlen($ending));
        }

        if (!$exact) {
            $spacepos = strrpos($truncate, " ");

            if (isset($spacepos)) {
                if ($html) {
                    $bits = substr($truncate, $spacepos);
                    preg_match_all('/<\/([a-z]+)>/', $bits, $droppedTags, PREG_SET_ORDER);
                    if (!empty($droppedTags))
                        foreach ($droppedTags as $closingTag)
                            if (!in_array($closingTag[1], $openTags))
                                array_unshift($openTags, $closingTag[1]);
                }

                $truncate = substr($truncate, 0, $spacepos);
            }
        }

        $truncate .= $ending;

        if ($html)
            foreach ($openTags as $tag)
                $truncate .= '</'.$tag.'>';

        return $truncate;
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
                require_once INCLUDES_DIR.DIR."lib".DIR."ixr.php";

                $client = new IXR_Client($ping_url);
                $client->timeout = 3;
                $client->useragent = "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")";
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
     *     The pingback target, if the URL is pingback-capable.
     */
    function pingback_url($url) {
        extract(parse_url($url), EXTR_SKIP);

        $config = Config::current();
        $path = (!isset($path)) ? '/' : $path ;
        $port = (isset($port)) ? $port : 80 ;
        $chyrp_host = str_replace(array("http://www.",
                                        "http://",
                                        "https://www.",
                                        "https://"), "", $config->url);

        if (isset($query))
            $path.= '?'.$query;

        if (!isset($host) or substr_count($url, $chyrp_host))
            return false;

        $connect = @fsockopen($host, $port, $errno, $errstr, 2);

        if (!$connect)
            return false;

        # Send the GET headers.
        fwrite($connect, "GET ".$path." HTTP/1.1\r\n");
        fwrite($connect, "Host: $host\r\n");
        fwrite($connect, "User-Agent: Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")\r\n\r\n");

        # Check for X-Pingback header.
        $headers = "";
        while (!feof($connect)) {
            $line = fgets($connect, 512);
            if (trim($line) == "")
                break;

            $headers.= trim($line)."\n";

            if (preg_match("/X-Pingback: (.+)/i", $line, $matches))
                return trim($matches[1]);

            # Save the content-type in case we need to <link> search.
            if (preg_match("/Content-Type: (.+)/i", $headers, $matches))
                $content_type = trim($matches[1]);
        }

        # X-Pingback header not found, <link> search if the content can be parsed.
        if (!preg_match("~(text/html|text/sgml|text/xml|text/plain)~i", $content_type))
            return false;

        $size = 0;

        while (!feof($connect)) {
            $line = fgets($connect, 1024);
            if (preg_match("/<link rel=[\"|']pingback[\"|'] href=[\"|']([^\"]+)[\"|'] ?\/?>/i", $line, $link))
                return $link[1];

            $size += strlen($line);

            if ($size > 2048)
                return false;
        }

        fclose($connect);

        return false;
    }

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
     *     The response from the remote URL.
     */
    function get_remote($url, $redirects = 0, $timeout = 10) {
        extract(parse_url($url), EXTR_SKIP);
        $content = "";

        if (ini_get("allow_url_fopen")) {
            $context = stream_context_create(array("http" => array("follow_location" => ($redirects == 0) ? 0 : 1 ,
                                                                   "max_redirects" => $redirects,
                                                                   "timeout" => $timeout,
                                                                   "protocol_version" => 1.1,
                                                                   "user_agent" => "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")")));
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
            curl_setopt($handle, CURLOPT_USERAGENT, "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");
            $content = curl_exec($handle);
            curl_close($handle);
        } else {
            $port = (isset($port)) ? $port : 80 ;
            $path = (!isset($path)) ? '/' : $path ;
            $header = "";

            if (isset($query))
                $path.= '?'.$query;

            $connect = @fsockopen($host, $port, $errno, $errstr, $timeout);

            if ($connect) {
                # Send the GET headers.
                fwrite($connect, "GET ".$path." HTTP/1.1\r\n");
                fwrite($connect, "Host: ".$host."\r\n");
                fwrite($connect, "User-Agent: Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")\r\n\r\n");

                while (!feof($connect) and strpos($header, "\r\n\r\n") === false)
                    $header.= fgets($connect);

                while (!feof($connect))
                    $content.= fgets($connect);

                fclose($connect);

                # Search for 301 or 302 header and recurse with new location unless redirects are exhausted.
                if ($redirects > 0 and preg_match("~^HTTP/[0-9]\.[0-9] 30[1-2]~m", $header) and preg_match("~^Location:(.+)$~mi", $header, $matches)) {
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
        $expressions = array("/<a[^>]+href=(\"[^\"]+\"|\'[^\']+\')[^>]*>[^<]+<\/a>/");
        $urls = array();

        if (Config::current()->enable_markdown)
            $expressions[] = "/!\[[^\]]+\]\(([^\)]+)\)/";

        Trigger::current()->filter($expressions, "link_regexp"); # Modules can support other syntaxes.

        foreach ($expressions as $expression) {
            preg_match_all($expression, stripslashes($string), $matches);
            $urls = array_merge($urls, $matches[1]);
        }

        foreach ($urls as &$url)
            $url = trim($url, " \"'");

        return $urls;
    }

    /**
     * Function: redirect
     * Redirects to the given URL and exits immediately.
     *
     * Parameters:
     *     $url - The URL to redirect to. If it begins with @/@ it will be relative to the @Config.url@.
     *     $use_chyrp_url - Use the @Config.chyrp_url@ instead of @Config.url@ for $urls beginning with @/@?
     */
    function redirect($url, $use_chyrp_url = false) {
        # Handle URIs without domain
        if (strpos($url, "/") === 0)
            $url = (ADMIN or $use_chyrp_url) ?
                       Config::current()->chyrp_url.$url :
                       Config::current()->url.$url ;
        elseif (file_exists(INCLUDES_DIR.DIR."config.json.php") and class_exists("Route") and !substr_count($url, "://"))
            $url = url($url);

        header("Location: ".html_entity_decode($url));
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
     * Function: admin_url
     * Builds an admin URL from the supplied components.
     *
     * Parameters:
     *     $action - The admin action.
     *     $params - An indexed array of parameters.
     */
    function admin_url($action = "", $params = array()) {
        $config = Config::current();

        $request = !empty($action) ? array("action=".$action) : array() ;

        foreach ($params as $key => $value)
            $request[] = urlencode($key)."=".urlencode($value);

        return $config->chyrp_url."/admin/".(!empty($request) ? "?".implode("&amp;", $request) : "");
    }

    /**
     * Function: self_url
     * Returns the current URL.
     */
    function self_url() {
        $protocol = (!empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== "off" or $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://" ;
        return $protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    }

    /**
     * Function: camelize
     * Converts a given string to camel-case.
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
     * Decamelizes a string.
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
     * Function: module_enabled
     * Returns whether the given module is enabled or not.
     *
     * Parameters:
     *     $name - The folder name of the module.
     *
     * Returns:
     *     Whether or not the requested module is enabled.
     */
    function module_enabled($name) {
        $config = Config::current();
        return in_array($name, $config->enabled_modules);
    }

    /**
     * Function: feather_enabled
     * Returns whether the given feather is enabled or not.
     *
     * Parameters:
     *     $name - The folder name of the feather.
     *
     * Returns:
     *     Whether or not the requested feather is enabled.
     */
    function feather_enabled($name) {
        $config = Config::current();
        return in_array($name, $config->enabled_feathers);
    }

    /**
     * Function: cancel_module
     * Temporarily removes a module from $config->enabled_modules.
     *
     * Parameters:
     *     $target - Module name to disable.
     *     $reason - Why was execution cancelled?
     *
     * Notes:
     *     A module can cancel itself in its __construct method.
     */
     function cancel_module($target, $reason = "") {
        $this_disabled = array();
        $message = empty($reason) ?
            _f("Execution of %s has been cancelled because the module could not continue.", camelize($target)) : $reason ;

        if (isset(Modules::$instances[$target]))
            Modules::$instances[$target]->cancelled = true;

        if (ADMIN and Visitor::current()->group->can("toggle_extensions"))
            Flash::warning($message);

        $config = Config::current();

        foreach ($config->enabled_modules as $module)
            if ($module != $target)
                $this_disabled[] = $module;

        return $config->enabled_modules = $this_disabled;
    }

    /**
     * Function: init_extensions
     * Initialize all Modules and Feathers.
     */
    function init_extensions() {
        $config = Config::current();

        # Instantiate all Modules.
        foreach ($config->enabled_modules as $module) {
            if (!file_exists(MODULES_DIR.DIR.$module.DIR.$module.".php") or !file_exists(MODULES_DIR.DIR.$module.DIR."info.php")) {
                unset($config->enabled_modules[$module]);
                continue;
            }

            if (file_exists(MODULES_DIR.DIR.$module.DIR."locale".DIR.$config->locale.".mo"))
                load_translator($module, MODULES_DIR.DIR.$module.DIR."locale".DIR.$config->locale.".mo");

            require MODULES_DIR.DIR.$module.DIR.$module.".php";

            $camelized = camelize($module);

            if (!class_exists($camelized))
                continue;

            Modules::$instances[$module] = new $camelized;
            Modules::$instances[$module]->safename = $module;

            foreach (include MODULES_DIR.DIR.$module.DIR."info.php" as $key => $val)
                Modules::$instances[$module]->$key = $val;
        }

        # Instantiate all Feathers.
        foreach ($config->enabled_feathers as $feather) {
            if (!file_exists(FEATHERS_DIR.DIR.$feather.DIR.$feather.".php") or !file_exists(FEATHERS_DIR.DIR.$feather.DIR."info.php")) {
                unset($config->enabled_feathers[$feather]);
                continue;
            }

            if (file_exists(FEATHERS_DIR.DIR.$feather.DIR."locale".DIR.$config->locale.".mo"))
                load_translator($feather, FEATHERS_DIR.DIR.$feather.DIR."locale".DIR.$config->locale.".mo");

            require FEATHERS_DIR.DIR.$feather.DIR.$feather.".php";

            $camelized = camelize($feather);

            if (!class_exists($camelized))
                continue;

            Feathers::$instances[$feather] = new $camelized;
            Feathers::$instances[$feather]->safename = $feather;

            foreach (include FEATHERS_DIR.DIR.$feather.DIR."info.php" as $key => $val)
                Feathers::$instances[$feather]->$key = $val;
        }

        # Initialize all modules.
        foreach (Feathers::$instances as $feather)
            if (method_exists($feather, "__init"))
                $feather->__init();

        foreach (Modules::$instances as $module)
            if (method_exists($module, "__init"))
                $module->__init();
    }

    /**
     * Function: fallback
     * Sets a given variable if it is not set.
     *
     * The last of the arguments or the first non-empty value will be used.
     *
     * Parameters:
     *     &$variable - The variable to return or set.
     *
     * Returns:
     *     The value of whatever was chosen.
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
     * Returns the first argument that is set and non-empty.
     *
     * It will guess where to stop based on the types of the arguments, e.g. "" has priority over array() but not 1.
     */
    function oneof() {
        $last = null;
        $args = func_get_args();

        foreach ($args as $index => $arg) {
            if (!isset($arg) or (is_string($arg) and trim($arg) === "") or $arg === array() or (is_object($arg) and empty($arg)) or ($arg === "0000-00-00 00:00:00"))
                $last = $arg;
            else
                return $arg;

            if ($index + 1 == count($args))
                break;

            $next = $args[$index + 1];

            $incomparable = ((is_array($arg) and !is_array($next)) or        # This is a big check but it should cover most "incomparable" cases.
                             (!is_array($arg) and is_array($next)) or        # Using simple type comparison wouldn't work too well, for example
                             (is_object($arg) and !is_object($next)) or      # when "" would take priority over 1 in oneof("", 1) because they're
                             (!is_object($arg) and is_object($next)) or      # different types.
                             (is_resource($arg) and !is_resource($next)) or
                             (!is_resource($arg) and is_resource($next)));

            if (isset($arg) and isset($next) and $incomparable)
                return $arg;
        }

        return $last;
    }

    /**
     * Function: random
     * Returns a random string.
     *
     * Parameters:
     *     $length - How long the string should be.
     *     $specialchars - Use special characters in the resulting string?
     */
    function random($length, $specialchars = false) {
        $pattern = "1234567890abcdefghijklmnopqrstuvwxyz";

        if ($specialchars)
            $pattern.= "!@#$%^&*()?~";

        $len = strlen($pattern) - 1;
        $key = "";

        for($i = 0; $i < $length; $i++)
            $key.= $pattern[rand(0, $len)];

        return $key;
    }

    /**
     * Function: unique_filename
     * Makes a given filename unique for the uploads directory.
     *
     * Parameters:
     *     $name - The name to check.
     *     $path - Path to check in.
     *     $num - Number suffix from which to start increasing if the filename exists.
     *
     * Returns:
     *     A unique version of the supplied filename.
     */
    function unique_filename($name, $num = 2) {
        if (!file_exists(MAIN_DIR.Config::current()->uploads_path.$name))
            return $name;

        $name = explode(".", $name);

        # Handle common double extensions
        foreach (array("tar.gz", "tar.bz", "tar.bz2") as $extension) {
            list($first, $second) = explode(".", $extension);
            $file_first =& $name[count($name) - 2];

            if ($file_first == $first and end($name) == $second) {
                $file_first = $first.".".$second;
                array_pop($name);
            }
        }

        $ext = ".".array_pop($name);
        $try = implode(".", $name)."-".$num.$ext;

        if (!file_exists(MAIN_DIR.Config::current()->uploads_path.$try))
            return $try;

        return unique_filename(implode(".", $name).$ext, $num + 1);
    }

    /**
     * Function: upload
     * Validates and moves an uploaded file to the uploads directory.
     *
     * Parameters:
     *     $file - The file array created by PHP.
     *     $filter - An array of valid extensions (case-insensitive).
     *
     * Returns:
     *     The filename of the upload relative to the uploads directory.
     */
    function upload($file, $filter = null) {
        $file_split = explode(".", $file['name']);
        $uploads_path = MAIN_DIR.Config::current()->uploads_path;

        if (!is_uploaded_file($file['tmp_name']))
            show_403(__("Access Denied"), _f("<em>%s</em> is not an uploaded file.", fix($file['name'])));

        if (!is_writable($uploads_path))
            error(__("Error"), _f("Upload destination <em>%s</em> is not writable.", fix($uploads_path)));

        $original_ext = end($file_split);

        # Handle common double extensions
        foreach (array("tar.gz", "tar.bz", "tar.bz2") as $ext) {
            list($first, $second) = explode(".", $ext);
            $file_first =& $file_split[count($file_split) - 2];
            if (strcasecmp($file_first, $first) == 0 and strcasecmp(end($file_split), $second) == 0) {
                $file_first = $first.".".$second;
                array_pop($file_split);
            }
        }

        $file_ext = end($file_split);

        if (in_array(strtolower($file_ext), array("php", "htaccess", "shtml", "shtm", "stm", "cgi")))
            $file_ext = "txt";

        if (!empty($filter)) {
            $extensions = array();

            foreach ((array) $filter as $string)
                $extensions[] = strtolower($string);

            if (!in_array(strtolower($file_ext), $extensions) and
                !in_array(strtolower($original_ext), $extensions))
                error(__("Unsupported File Type"), _f("Only files of the following types are accepted: %s.", implode(", ", $extensions)));
        }

        array_pop($file_split);
        $file_clean = implode(".", $file_split);
        $file_clean = sanitize($file_clean, false).".".$file_ext;
        $filename = unique_filename($file_clean);

        move_uploaded_file($file['tmp_name'], $uploads_path.$filename);
        return $filename;
    }

    /**
     * Function: upload_from_url
     * Copy a file from a specified URL to the uploads directory.
     *
     * Parameters:
     *     $url - The URL of the resource to be copied.
     *     $redirects - The maximum number of redirects to follow.
     *     $timeout - The maximum number of seconds to wait.
     *
     * See Also:
     *     <upload>
     */
    function upload_from_url($url, $redirects = 3, $timeout = 10) {
        preg_match("~\.[a-z0-9]+(?=($|\?))~i", $url, $file_ext);
        fallback($file_ext[0], "bin"); # Assume unknown binary file.

        $filename = unique_filename(md5($url).".".$file_ext[0]);
        $filepath = MAIN_DIR.Config::current()->uploads_path.$filename;

        file_put_contents($filepath, get_remote($url, $redirects, $timeout));
        return $filename;
    }

    /**
     * Function: uploaded
     * Returns a URL to an uploaded file.
     *
     * Parameters:
     *     $file - Filename relative to the uploads directory.
     *     $url - Whether to return a URL or a filesystem path.
     */
    function uploaded($file, $url = true) {
        if (empty($file))
            return "";

        $config = Config::current();
        return ($url ? $config->chyrp_url.str_replace(DIR, "/", $config->uploads_path).$file : MAIN_DIR.$config->uploads_path.$file);
    }

    /**
     * Function: upload_tester
     * Returns true if files were successfully uploaded.
     * Returns false if no file was selected for upload.
     * Triggers an error page for all other fail states.
     *
     * Parameters:
     *     $file - The file array created by PHP.
     */
    function upload_tester($file) {
        if (empty($file))
            return false; # Upload size exceeded post_max_size directive in ini.php.

        # Recurse to test multiple uploads file by file.
        if (is_array($file['name'])) {
            for ($i=0; $i < count($file['name']); $i++)
                if (!upload_tester(array('name' => $file['name'][$i],
                                         'type' => $file['type'][$i],
                                         'tmp_name' => $file['tmp_name'][$i],
                                         'error' => $file['error'][$i],
                                         'size' => $file['size'][$i])))
                    return false;

            return true;
        }

        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                error(__("Error"), __("The uploaded file exceeds the <code>upload_max_filesize</code> directive in php.ini."));
            case UPLOAD_ERR_FORM_SIZE:
                error(__("Error"), __("The uploaded file exceeds the <code>MAX_FILE_SIZE</code> directive that was specified in the HTML form."));
            case UPLOAD_ERR_PARTIAL:
                error(__("Error"), __("The uploaded file was only partially uploaded."));
            case UPLOAD_ERR_NO_TMP_DIR:
                error(__("Error"), __("Missing a temporary folder."));
            case UPLOAD_ERR_CANT_WRITE:
                error(__("Error"), __("Failed to write file to disk."));
            case UPLOAD_ERR_EXTENSION:
                error(__("Error"), __("File upload was stopped by a PHP extension."));
            case UPLOAD_ERR_NO_FILE:
                return false;
            case UPLOAD_ERR_OK:
                $limit = Config::current()->uploads_limit;

                if ($file['size'] > ($limit * 1048576))
                    error(__("Error"), _f("The uploaded file exceeds the maximum size of %d Megabytes allowed by this site.", $limit));

                return true;
            default:
                error(__("Error"), __("Unknown upload error."));
        }
    }

    /**
     * Function: zip_errors
     * Converts a ZipArchive error code into a human-readable message.
     *
     * Parameters:
     *     $code - The error code returned by ZipArchive.
     *
     * Returns:
     *     The error message corresponding to the supplied error code.
     */
    function zip_errors($code) {
        if (!class_exists("ZipArchive"))
            return __("ZipArchive not available.");

        switch ($code) {
            case ZipArchive::ER_EXISTS:
                return __("File already exists.");
            case ZipArchive::ER_INCONS:
                return __("Zip archive inconsistent.");
            case ZipArchive::ER_INVAL:
                return __("Invalid argument.");
            case ZipArchive::ER_MEMORY:
                return __("Malloc failure.");
            case ZipArchive::ER_NOENT:
                return __("No such file.");
            case ZipArchive::ER_NOZIP:
                return __("Not a zip archive.");
            case ZipArchive::ER_OPEN:
                return __("Cannot open file.");
            case ZipArchive::ER_READ:
                return __("Read error.");
            case ZipArchive::ER_SEEK:
                return __("Seek error.");
            default:
                return __("Unknown error.");
        }
    }

    /**
     * Function: timer_start
     * Starts the timer.
     */
    function timer_start() {
        global $time_start;
        $mtime = explode(" ", microtime());
        $mtime = $mtime[1] + $mtime[0];
        $time_start = $mtime;
    }

    /**
     * Function: timer_stop
     * Stops the timer and returns the total time.
     *
     * Parameters:
     *     $precision - Number of decimals places to round to.
     *
     * Returns:
     *     A formatted number with the given $precision.
     */
    function timer_stop($precision = 3) {
        global $time_start;
        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $time_end = $mtime;
        $time_total = $time_end - $time_start;
        return number_format($time_total, $precision);
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

        function by_time($a, $b) {
            return (int) ($a["now"] > $b["now"]);
        }

        usort($zones, "by_time");

        return $zones;
    }

    /**
     * Function: set_timezone
     * Sets the timezone.
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

    /**
     * Function: keywords
     * Handle keyword-searching.
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

        foreach (preg_split("/\s(?=\w+:)|;/", $query, -1, PREG_SPLIT_NO_EMPTY) as $fragment)
            if (!substr_count($fragment, ":"))
                $strings[] = trim($fragment);
            else
                $keywords[] = trim($fragment);

        $dates = array("year" => __("year"),
                       "month" => __("month"),
                       "day" => __("day"),
                       "hour" => __("hour"),
                       "minute" => __("minute"),
                       "second" => __("second"));

        foreach ($keywords as $keyword) {
            list($attr, $val) = explode(":", $keyword);

            if (in_array($attr, $dates)) {
                $where[strtoupper(array_search($attr, $dates))."(created_at)"] = $val;
            } elseif ($attr == "author") {
                $user = new User(array("login" => $val));
                !($table == "users") ? $where["user_id"] = $user->id : $where["id"] = $user->id;
            } elseif ($attr == "group") {
                $group = new Group(array("name" => $val));
                $where["group_id"] = $val = ($group->no_results) ? 0 : $group->id;
            } else
                $filters[$attr] = $val;
        }

        # Check the validity of keywords if a table name was supplied.
        if (!empty($table)) {
            $columns = SQL::current()->select($table)->fetch();

            foreach ($filters as $attr => $val)
                if (isset($columns[$attr]))
                    $where[$attr] = $val;
                else
                    $strings[] = $attr." ".$val; # No such column: add to non-keyword values.
        } else
            foreach ($filters as $attr => $val)
                $strings[] = $attr." ".$val; # Cannot validate: add all to non-keyword values.

        if (!empty($strings)) {
            $where[] = $plain;
            $params[":query"] = "%".join(" ", $strings)."%";
        }

        $search = array($where, $params);

        Trigger::current()->filter($search, "keyword_search", $query, $plain);

        return $search;
    }

    /**
     * Function: xml2arr
     * Recursively converts a SimpleXML object (and children) to an array.
     *
     * Parameters:
     *     $parse - The SimpleXML object to convert into an array.
     */
    function xml2arr($parse) {
        if (empty($parse))
            return "";

        $parse = (array) $parse;

        foreach ($parse as &$val)
            if (get_class($val) == "SimpleXMLElement")
                $val = xml2arr($val);

        return $parse;
    }

    /**
     * Function: arr2xml
     * Recursively adds an array (or object I guess) to a SimpleXML object.
     *
     * Parameters:
     *     &$object - The SimpleXML object to modify.
     *     $data - The data to add to the SimpleXML object.
     */
    function arr2xml(&$object, $data) {
        foreach ($data as $key => $val) {
            if (is_int($key) and (empty($val) or (is_string($val) and trim($val) == ""))) {
                unset($data[$key]);
                continue;
            }

            if (is_array($val)) {
                if (in_array(0, array_keys($val))) { # Numeric-indexed things need to be added as duplicates.
                    foreach ($val as $dup) {
                        $xml = $object->addChild($key);
                        arr2xml($xml, $dup);
                    }
                } else {
                    $xml = $object->addChild($key);
                    arr2xml($xml, $val);
                }
            } else
                $object->addChild($key, fix($val, false, false));
        }
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
            $string = (is_string($item) and $quotes) ? __("&#8220;").$item.__("&#8221;") : $item ;
            if (count($array) == ++$count and $count !== 1)
                $items[] = __("and ").$string;
            else
                $items[] = $string;
        }

        return (count($array) == 2) ? implode(" ", $items) : implode(", ", $items) ;
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
     * Function: now
     * Alias to strtotime, for prettiness like now("+1 day").
     */
    function now($when) {
        return strtotime($when);
    }

    /**
     * Function: comma_sep
     * Convert a comma-seperated string into an array of the listed values.
     */
    function comma_sep($string) {
        $commas = explode(",", $string);
        $trimmed = array_map("trim", $commas);
        $cleaned = array_diff(array_unique($trimmed), array(""));
        return $cleaned;
    }

    /**
     * Function: generate_captcha
     * Generates a captcha form element.
     *
     * Returns:
     *     A string containing an form input type.
     */
    function generate_captcha() {
        global $captchaHooks;

        if (!$captchaHooks)
           return false;

        return call_user_func($captchaHooks[0] . "::getCaptcha");
    }

    /**
     * Function: check_captcha
     * Checks if the answer to a captcha is right.
     *
     * Returns:
     *     Whether or not the captcha was defeated.
     */
    function check_captcha() {
        global $captchaHooks;

        if (!$captchaHooks)
           return true;

        return call_user_func($captchaHooks[0] . "::verifyCaptcha");
    }

    /**
     * Function: get_gravatar
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * Parameters:
     *     $email - The email address
     *     $s - Size in pixels, defaults to 80px [ 1 - 512 ]
     *     $d - Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
     *     $r - Maximum rating (inclusive) [ g | pg | r | x ]
     *     $img - True to return a complete IMG tag False for just the URL
     *     $atts - Additional key/value attributes to add to the IMG tag (optional).
     *
     * Returns:
     *     String containing either just a URL or a complete image tag.
     *
     * Source:
     *     http://gravatar.com/site/implement/images/php/
     */
    function get_gravatar($email, $s = 80, $img = false, $d = "mm", $r = "g", $atts = array()) {
        $url = "http://www.gravatar.com/avatar/".md5(strtolower(trim($email)))."?s=$s&d=$d&r=$r";
        if ($img) {
            $url = '<img class="gravatar" src="' . $url . '"';
            foreach ($atts as $key => $val)
                $url .= ' ' . $key . '="' . $val . '"';
            $url .= ">";
        }
        return $url;
    }

    /**
     * Function: token
     * Salt and hash a unique token using the supplied data.
     *
     * Parameters:
     *     $items - An array of items to hash.
     *
     * Returns:
     *     A unique token salted with the secure hashkey.
     */
    function token($items) {
        return sha1(implode((array) $items).Config::current()->secure_hashkey);
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
     * See Also:
     *     <add_scheme>
     */
    function is_url($string) {
        return (preg_match('~^(http://|https://)?([a-z0-9][a-z0-9\-\.]*[a-z0-9]\.[a-z]{2,63}\.?)($|/|:[0-9]{1,5})~i', $string) or //FQDN
                preg_match('~^(http://|https://)?([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})($|/|:[0-9]{1,5})~', $string) or //IPv4
                preg_match('~^(http://|https://)?(\[[a-f0-9\:]{3,39}\])($|/|:[0-9]{1,5})~i', $string));                           //IPv6
    }

    /**
     * Function: is_email
     * Does the string look like an email address?
     *
     * Parameters:
     *     $string - The string to analyse.
     *
     * Returns:
     *     Whether or not the string matches the criteria.
     */
    function is_email($string) {
        return (preg_match('~^[^ @]+@([a-z0-9][a-z0-9\-\.]*[a-z0-9]\.[a-z]{2,63}\.?)$~i', $string) or //FQDN
                preg_match('~^[^ @]+@([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})$~', $string) or //IPv4
                preg_match('~^[^ @]+@(\[[a-f0-9\:]{3,39}\])$~i', $string));                           //IPv6
    }

    /**
     * Function: add_scheme
     * Prefixes a URL with a HTTP scheme if none was detected.
     *
     * Parameters:
     *     $url - The URL to analyse.
     *
     * Returns:
     *     URL prefixed with scheme.
     *
     * See Also:
     *     <is_url>
     */
    function add_scheme($url) {
        return $url = preg_match('~^[a-z][a-z0-9\+\.\-]+:~i', $url) ? $url : "http://".$url ;
    }

    /**
     * Function: download
     * Send a file attachment to the visitor.
     *
     * Parameters:
     *     $contents - The bitstream to be delivered to the visitor.
     *     $filename - The name to be applied to the content upon download.
     */
    function download($contents = "", $filename = "caconym") {
        if (!headers_sent()) {
            header("Content-type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"".$filename."\"");

            if (!in_array("ob_gzhandler", ob_list_handlers()))
                header("Content-length: ".strlen($contents));

            echo $contents;
            exit;
        } else
            error(__("Error"), __("Unable to deliver file attachment because HTTP headers were already sent."));
    }

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
            return;

        $params["headers"] = "From:".$config->email."\r\n".
                             "Reply-To:".$config->email. "\r\n".
                             "X-Mailer: PHP/".phpversion();

        fallback($params["subject"], "");
        fallback($params["message"], "");

        switch ($action) {
            case "activate":
                $params["subject"] = _f("Activate your account at %s", $config->name);
                $params["message"] = _f("Hello, %s.", fix($params["login"])).
                                     PHP_EOL.PHP_EOL.
                                     __("You are receiving this message because you registered a new account.").
                                     PHP_EOL.PHP_EOL.
                                     __("Visit this link to activate your account:").
                                     PHP_EOL.
                                     $params["link"];
                break;
            case "reset":
                $params["subject"] = _f("Reset your password at %s", $config->name);
                $params["message"] = _f("Hello, %s.", fix($params["login"])).
                                     PHP_EOL.PHP_EOL.
                                     __("You are receiving this message because you requested a new password.").
                                     PHP_EOL.PHP_EOL.
                                     _f("Visit this link to reset your password:").
                                     PHP_EOL.
                                     $params["link"];
                break;
            case "password":
                $params["subject"] = _f("Your new password for %s", $config->name);
                $params["message"] = _f("Hello, %s.", fix($params["login"])).
                                     PHP_EOL.PHP_EOL.
                                     _f("Your new password is: %s", $params["password"]);
                break;
            default:
                if ($trigger->exists("correspond_".$action))
                    $trigger->filter($params, "correspond_".$action);
                else
                    return;
        }

        if (!email($params["to"], $params["subject"], $params["message"], $params["headers"]))
            error(__("Undeliverable"), __("Unable to send email."));
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

        if (file_exists(INCLUDES_DIR.DIR."lib".DIR.$filepath)) {
            require INCLUDES_DIR.DIR."lib".DIR.$filepath;
            return;
        }

        foreach (Config::current()->enabled_modules as $module)
            if (file_exists(MODULES_DIR.DIR.$module.DIR."lib".DIR.$filepath)) {
                require MODULES_DIR.DIR.$module.DIR."lib".DIR.$filepath;
                return;
            }
    }
