<?php
    /**
     * File: Helpers
     * Various functions used throughout Chyrp's code.
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
     */
    function session() {
        session_set_save_handler(array("Session", "open"),
                                 array("Session", "close"),
                                 array("Session", "read"),
                                 array("Session", "write"),
                                 array("Session", "destroy"),
                                 array("Session", "gc"));
        $host = $_SERVER['HTTP_HOST'];
        if (is_numeric(str_replace(".", "", $host)))
            $domain = $host;
        elseif (count(explode(".", $host)) >= 2)
            $domain = preg_replace("/^www\./", ".", $host);
        else
            $domain = "";

        session_set_cookie_params(60 * 60 * 24 * 30, "/", $domain);
        session_name("ChyrpSession");
        register_shutdown_function("session_write_close");
        session_start();
    }

    /**
     * Function: error
     * Shows an error message.
     *
     * Parameters:
     *     $title - The title for the error dialog.
     *     $body - The message for the error dialog.
     *     $backtrace - The trace of the error.
     */
    function error($title, $body, $backtrace = array()) {
        if (defined('MAIN_DIR') and !empty($backtrace))
            foreach ($backtrace as $index => &$trace)
                if (!isset($trace["file"]) or !isset($trace["line"]))
                    unset($backtrace[$index]);
                else
                    $trace["file"] = str_replace(MAIN_DIR."/", "", $trace["file"]);
                # $trace["file"] = isset($trace["file"]) ?
                #                       :
                #                      (isset($trace["function"]) ?
                #                          (isset($trace["class"]) ?
                #                              $trace["class"].$trace["type"] :
                #                              "").$trace["function"] :
                #                          "[internal]");

        # Clear all output sent before this error.
        if (($buffer = ob_get_contents()) !== false) {
            ob_end_clean();

            # Since the header might already be set to gzip, start output buffering again.
            if (extension_loaded("zlib") and !ini_get("zlib.output_compression") and
                isset($_SERVER['HTTP_ACCEPT_ENCODING']) and
                substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], "gzip") and
                USE_ZLIB) {
                ob_start("ob_gzhandler");
                header("Content-Encoding: gzip");
            } else
                ob_start();
        } elseif (!UPGRADING) {
            # If output buffering is not started, assume this
            # is sent from the Session class or somewhere deep.
            error_log($title.": ".$body);

            foreach ($backtrace as $index => $trace)
                error_log("    ".($index + 1).": "._f("%s on line %d", array($trace["file"], $trace["line"])));

            exit;
        }

        if (TESTER)
            exit("ERROR: ".$body);

        if ($title == __("Access Denied"))
            $_SESSION['redirect_to'] = self_url();

        # Display the error.
        if (defined('THEME_DIR') and class_exists("Theme") and Theme::current()->file_exists("pages/error"))
            MainController::current()->display("pages/error",
                                               array("title" => $title,
                                                     "body" => $body,
                                                     "backtrace" => $backtrace));
        else
            require INCLUDES_DIR."/error.php";

        if ($buffer !== false)
            ob_end_flush();

        exit;
    }

    /**
     * Function: error_panicker
     * Exits and states where the error occurred.
     */
    function error_panicker($errno, $message, $file, $line) {
        if (error_reporting() === 0)
            return; # Suppressed error.

        exit("ERROR: ".$message." (".$file." on line ".$line.")");
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
        header("Status: 403");
        error($title, $body);
    }

    /**
     * Function: show_404
     * Shows a 404 error message and immediately exits.
     *
     * Parameters:
     *     $scope - An array of values to extract into the scope.
     */
     function show_404() {
        header("HTTP/1.1 404 Not Found");

        if (!defined('CHYRP_VERSION'))
            exit("404 Not Found");

        $theme = Theme::current();
        $main = MainController::current();

        Trigger::current()->call("not_found");

        if ($theme->file_exists("pages/404"))
            $main->display("pages/404", array(), "404");
        else
            error(__("404 Not Found"), __("The requested page could not be located."));

        exit;
    }

    /**
     * Function: deprecated
     * Returns a warning if a deprecated function has been used.
     *
     * Parameters:
     *     $f The function that was called
     *     $v Chyrp version that deprecated the function
     *     $r Optional. The function that should have been called
     */
    function deprecated($f, $v, $r = null, $trace) {    
        if (!logged_in())
            return;

        error_reporting(E_ALL);
        ini_set("display_errors", 1);

        if (DEBUG) {
    		if (!is_null($r))
    			trigger_error(_f("%s is <strong>deprecated</strong> since version %s! Use %s instead.", array($f, $v, $r)));
    		else
    			trigger_error(_f("%s is <strong>deprecated</strong> since version %s.", $f, $v));
        }

        error_reporting(E_ALL | E_STRICT);
        ini_set("display_errors", 0);
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
     * Converts emoticon symbols to the correct Unicode counterpart.
     *
     * Parameters:
     *     $text - The body of the post/page to parse.
     */
    function emote($text) {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== false)
            return $text; # User agent is Google Chrome

        $emoji = array(
            ':angry:' => '&#x1f620;',
            ':blush:' => '&#x1f633;',
            ':bored:' => '&#x1f629;',
            'B)'      => '&#x1f60e;',
            '8)'      => '&#x1f60e;',
            ':\'('    => '&#x1f622;',
            ':cry:'   => '&#x1f622;',
            ':3'      => '&#x1f638;',
            '^_^'     => '&#x1f601;',
            'x_x'     => '&#x1f635;',
            '>:-)'    => '&#x1f608;',
            'o:-)'    => '&#x1f607;',
            ':-*'     => '&#x1f618;',
            ':-|'     => '&#x1f611;',
            ':-\\'    => '&#x1f615;',
            ':-/'     => '&#x1f615;',
            ':-s'     => '&#x1f616;',
            ':-D'     => '&#x1f603;',
            ':D'      => '&#x1f603;',
            '=D'      => '&#x1f603;',
            '<3'      => '&#x1f60d;',
            ':love:'  => '&#x1f60d;',
            ':P'      => '&#x1f61b;',
            ':-P'     => '&#x1f61b;',
            ':p'      => '&#x1f61b;',
            ':-p'     => '&#x1f61b;',
            ':ooo:'   => '&#x1f62e;',
            ':-('     => '&#x1f61f;',
            ':('      => '&#x1f61f;',
            '=('      => '&#x1f61f;',
            ':('      => '&#x1f61f;',
            ':O'      => '&#x1f632;',
            ':-O'     => '&#x1f632;',
            ':)'      => '&#x1f600;',
            ':-)'     => '&#x1f600;',
            '=)'      => '&#x1f60a;',
            ':->'     => '&#x1f60f;',
            ':>'      => '&#x1f60f;',
            'O_O'     => '&#x1f632;',
            ':-x'     => '&#x1f636;',
            ';-)'     => '&#x1f609;',
            ';)'      => '&#x1f609;'
        );

        foreach($emoji as $key => $value) {
            $text =  str_ireplace($key, '<span class="emoji">'.$value.'</span>', $text);
        }
        
        return $text;
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
     * Set locale in a platform-independent way
     *
     * Parameters:
     *     $locale - the locale name (@en_US@, @uk_UA@, @fr_FR@ etc.)
     *
     * Returns:
     *     The encoding name used by locale-aware functions.
     */
    function set_locale($locale) { # originally via http://www.onphp5.com/article/22; heavily modified
        if ($locale == "en_US") return; # en_US is the default in Chyrp; their system may have
                                        # its own locale setting and no Chyrp translation available
                                        # for their locale, so let's just leave it alone.

        list($lang, $cty) = explode("_", $locale);
        $locales = array($locale.".UTF-8", $lang, "en_US.UTF-8", "en");
        $result = setlocale(LC_ALL, $locales);

        return (!strpos($result, 'UTF-8')) ? "CP".preg_replace('~\.(\d+)$~', "\\1", $result) : "UTF-8" ;
    }

    /**
     * Function: lang_code
     * Returns the passed language code (e.g. en_US) to the human-readable text (e.g. English (US))
     *
     * Parameters:
     *     $code - The language code to convert
     *
     * Author:
     *     TextPattern devs, modified to fit with Chyrp.
     */
    function lang_code($code) {
        $langs = array("ar_DZ" => "جزائري عربي",
                       "ca_ES" => "Català",
                       "cs_CZ" => "Čeština",
                       "da_DK" => "Dansk",
                       "de_DE" => "Deutsch",
                       "el_GR" => "Ελληνικά",
                       "en_GB" => "English (GB)",
                       "en_US" => "English (US)",
                       "es_ES" => "Español",
                       "et_EE" => "Eesti",
                       "fi_FI" => "Suomi",
                       "fr_FR" => "Français",
                       "gl_GZ" => "Galego (Galiza)",
                       "he_IL" => "עברית",
                       "hu_HU" => "Magyar",
                       "id_ID" => "Bahasa Indonesia",
                       "is_IS" => "Íslenska",
                       "it_IT" => "Italiano",
                       "ja_JP" => "日本語",
                       "lv_LV" => "Latviešu",
                       "nl_NL" => "Nederlands",
                       "no_NO" => "Norsk",
                       "pl_PL" => "Polski",
                       "pt_PT" => "Português",
                       "ro_RO" => "Română",
                       "ru_RU" => "Русский",
                       "sk_SK" => "Slovenčina",
                       "sq_AL" => "Shqip",
                       "sv_SE" => "Svenska",
                       "th_TH" => "ไทย",
                       "uk_UA" => "Українська",
                       "vi_VN" => "Tiếng Việt",
                       "zh_CN" => "中文(简体)",
                       "zh_TW" => "中文(繁體)",
                       "bg_BG" => "Български");
        return (isset($langs[$code])) ? str_replace(array_keys($langs), array_values($langs), $code) : $code ;
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
        return isset($l10n[$domain]) ?
                     $l10n[$domain]->ngettext($single, $plural, $number) :
                   (($number != 1) ? $plural : $single) ;
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
        $uncountable = array("moose", "sheep", "fish", "series", "species",
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
     * Returns date formatting for a string that isn't a regular time() value
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
     *     $when - An optional timestamp.
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
        return htmlspecialchars_decode($string, ENT_QUOTES, "utf-8");
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
     *     $anal - If set to *true*, will remove all non-alphanumeric characters.
     *     $trunc - Number of characters to truncate to (default 100, 0 to disable).
     */
    function sanitize($string, $force_lowercase = true, $anal = false, $trunc = 100) {
        $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
                       "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
                       "—", "–", ",", "<", ".", ">", "/", "?");
        $clean = trim(str_replace($strip, "", strip_tags($string)));
        $clean = preg_replace('/\s+/', "-", $clean);
        $clean = ($anal ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean);
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
     * Truncates a string to the given length, optionally taking into account HTML tags, and/or keeping words in tact.
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
     * Function: trackback_respond
     * Responds to a trackback request.
     *
     * Parameters:
     *     $error - Is this an error?
     *     $message - Message to return.
     */
    function trackback_respond($error = false, $message = "") {
        header("Content-Type: text/xml; charset=utf-8");

        if ($error) {
            echo '<?xml version="1.0" encoding="utf-8"?'.">\n";
            echo "<response>\n";
            echo "<error>1</error>\n";
            echo "<message>".$message."</message>\n";
            echo "</response>";
            exit;
        } else {
            echo '<?xml version="1.0" encoding="utf-8"?'.">\n";
            echo "<response>\n";
            echo "<error>0</error>\n";
            echo "</response>";
        }

        exit;
    }

    /**
     * Function: trackback_send
     * Sends a trackback request.
     *
     * Parameters:
     *     $post - The post we're sending from.
     *     $target - The URL we're sending to.
     */
    function trackback_send($post, $target) {
        if (empty($target)) return false;

        $target = parse_url($target);
        $title = $post->title();
        fallback($title, ucfirst($post->feather)." Post #".$post->id);
        $excerpt = strip_tags(truncate($post->excerpt(), 255));

        if (!empty($target["query"])) $target["query"] = "?".$target["query"];
        if (empty($target["port"])) $target["port"] = 80;

        $connect = fsockopen($target["host"], $target["port"]);
        if (!$connect) return false;

        $config = Config::current();
        $query = "url=".rawurlencode($post->url())."&".
                 "title=".rawurlencode($title)."&".
                 "blog_name=".rawurlencode($config->name)."&".
                 "excerpt=".rawurlencode($excerpt);

        fwrite($connect, "POST ".$target["path"].$target["query"]." HTTP/1.1\n");
        fwrite($connect, "Host: ".$target["host"]."\n");
        fwrite($connect, "Content-type: application/x-www-form-urlencoded\n");
        fwrite($connect, "Content-length: ". strlen($query)."\n");
        fwrite($connect, "Connection: close\n\n");
        fwrite($connect, $query);

        fclose($connect);

        return true;
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
                require_once INCLUDES_DIR."/lib/ixr.php";

                $client = new IXR_Client($ping_url);
                $client->timeout = 3;
                $client->useragent.= " -- Chyrp/".CHYRP_VERSION;
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
        if (!isset($host)) return false;

        $path = (!isset($path)) ? '/' : $path ;
        if (isset($query)) $path.= '?'.$query;
        $port = (isset($port)) ? $port : 80 ;

        # Connect
        $connect = @fsockopen($host, $port, $errno, $errstr, 2);
        if (!$connect) return false;

        # Send the GET headers
        fwrite($connect, "GET $path HTTP/1.1\r\n");
        fwrite($connect, "Host: $host\r\n");
        fwrite($connect, "User-Agent: Chyrp/".CHYRP_VERSION."\r\n\r\n");

        # Check for X-Pingback header
        $headers = "";
        while (!feof($connect)) {
            $line = fgets($connect, 512);
            if (trim($line) == "") break;
            $headers.= trim($line)."\n";

            if (preg_match("/X-Pingback: (.+)/i", $line, $matches))
                return trim($matches[1]);

            # Nothing's found so far, so grab the content-type
            # for the <link> search afterwards
            if (preg_match("/Content-Type: (.+)/i", $headers, $matches))
                $content_type = trim($matches[1]);
        }

        # No header found, check for <link>
        if (preg_match('/(image|audio|video|model)/i', $content_type)) return false;
        $size = 0;
        while (!feof($connect)) {
            $line = fgets($connect, 1024);
            if (preg_match("/<link rel=[\"|']pingback[\"|'] href=[\"|']([^\"]+)[\"|'] ?\/?>/i", $line, $link))
                return $link[1];
            $size += strlen($line);
            if ($size > 2048) return false;
        }

        fclose($connect);

        return false;
    }

    /**
     * Function: get_remote
     * Grabs the contents of a website/location.
     *
     * Parameters:
     *     $url - The URL of the location to grab.
     *
     * Returns:
     *     The response from the remote URL.
     */
    function get_remote($url) {
        extract(parse_url($url), EXTR_SKIP);

        if (ini_get("allow_url_fopen")) {
            $content = @file_get_contents($url);
            if (!$content or !strpos($http_response_header[0], " 200 OK"))
                $content = "Server returned a message: $http_response_header[0]";
        } elseif (function_exists("curl_init")) {
            $handle = curl_init();
            curl_setopt($handle, CURLOPT_URL, $url);
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($handle, CURLOPT_TIMEOUT, 60);
            $content = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);
            if ($status != 200)
                $content = "Server returned a message: $status";
        } else {
            $path = (!isset($path)) ? '/' : $path ;
            if (isset($query)) $path.= '?'.$query;
            $port = (isset($port)) ? $port : 80 ;

            $connect = @fsockopen($host, $port, $errno, $errstr, 2);
            if (!$connect) return false;

            # Send the GET headers
            fwrite($connect, "GET ".$path." HTTP/1.1\r\n");
            fwrite($connect, "Host: ".$host."\r\n");
            fwrite($connect, "User-Agent: Chyrp/".CHYRP_VERSION."\r\n\r\n");

            $content = "";
            while (!feof($connect)) {
                $line = fgets($connect, 128);
                if (preg_match("/\r\n/", $line)) continue;

                $content.= $line;
            }

            fclose($connect);
        }

        return $content;
    }

    /**
     * Function: grab_urls
     * Crawls a string for links.
     *
     * Parameters:
     *     $string - The string to crawl.
     *
     * Returns:
     *     An array of all URLs found in the string.
     */
    function grab_urls($string) {
        $regexp = "/<a[^>]+href=[\"|']([^\"]+)[\"|']>[^<]+<\/a>/";
        preg_match_all(Trigger::current()->filter($regexp, "link_regexp"), stripslashes($string), $matches);
        $matches = $matches[1];
        return $matches;
    }

    /**
     * Function: redirect
     * Redirects to the given URL and exits immediately.
     *
     * Parameters:
     *     $url - The URL to redirect to. If it begins with @/@ it will be relative to the @Config.chyrp_url@.
     *     $use_chyrp_url - Use the @Config.chyrp_url@ instead of @Config.url@ for $urls beginning with @/@?
     */
    function redirect($url, $use_chyrp_url = false) {
        # Handle URIs without domain
        if ($url[0] == "/")
            $url = (ADMIN or $use_chyrp_url) ?
                       Config::current()->chyrp_url.$url :
                       Config::current()->url.$url ;
        elseif (file_exists(INCLUDES_DIR."/config.yaml.php") and class_exists("Route") and !substr_count($url, "://"))
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
     * Function: selected
     * If $val1 == $val2, outputs or returns @ selected="selected"@
     *
     * Parameters:
     *     $val1 - First value.
     *     $val2 - Second value.
     *     $return - Return @ selected="selected"@ instead of outputting it
     */
    function selected($val1, $val2, $return = false) {
        if ($val1 == $val2)
            if ($return)
                return ' selected="selected"';
            else
                echo ' selected="selected"';
    }

    /**
     * Function: checked
     * If $val == 1 (true), outputs ' checked="checked"'
     *
     * Parameters:
     *     $val - Value to check.
     */
    function checked($val) {
        if ($val == 1) echo ' checked="checked"';
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
     */
     function cancel_module($target) {
        $this_disabled = array();

        if (isset(Modules::$instances[$target]))
            Modules::$instances[$target]->cancelled = true;

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
        foreach ($config->enabled_modules as $index => $module) {
            if (!file_exists(MODULES_DIR."/".$module."/".$module.".php")) {
                unset($config->enabled_modules[$index]);
                continue;
            }

            if (file_exists(MODULES_DIR."/".$module."/locale/".$config->locale.".mo"))
                load_translator($module, MODULES_DIR."/".$module."/locale/".$config->locale.".mo");

            require MODULES_DIR."/".$module."/".$module.".php";

            $camelized = camelize($module);
            if (!class_exists($camelized))
                continue;

            Modules::$instances[$module] = new $camelized;
            Modules::$instances[$module]->safename = $module;

            foreach (YAML::load(MODULES_DIR."/".$module."/info.yaml") as $key => $val)
                Modules::$instances[$module]->$key = (is_string($val)) ? __($val, $module) : $val ;
        }

        # Instantiate all Feathers.
        foreach ($config->enabled_feathers as $index => $feather) {
            if (!file_exists(FEATHERS_DIR."/".$feather."/".$feather.".php")) {
                unset($config->enabled_feathers[$index]);
                continue;
            }

            if (file_exists(FEATHERS_DIR."/".$feather."/locale/".$config->locale.".mo"))
                load_translator($feather, FEATHERS_DIR."/".$feather."/locale/".$config->locale.".mo");

            require FEATHERS_DIR."/".$feather."/".$feather.".php";

            $camelized = camelize($feather);
            if (!class_exists($camelized))
                continue;

            Feathers::$instances[$feather] = new $camelized;
            Feathers::$instances[$feather]->safename = $feather;

            foreach (YAML::load(FEATHERS_DIR."/".$feather."/info.yaml") as $key => $val)
                Feathers::$instances[$feather]->$key = (is_string($val)) ? __($val, $feather) : $val ;
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
     *     A unique version of the given $name.
     */
    function unique_filename($name, $path = "", $num = 2) {
        $path = rtrim($path, "/");
        if (!file_exists(MAIN_DIR.Config::current()->uploads_path.$path."/".$name))
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
        if (!file_exists(MAIN_DIR.Config::current()->uploads_path.$path."/".$try))
            return $try;

        return unique_filename(implode(".", $name).$ext, $path, $num + 1);
    }

    /**
     * Function: upload
     * Moves an uploaded file to the uploads directory.
     *
     * Parameters:
     *     $file - The $_FILES value.
     *     $extension - An array of valid extensions (case-insensitive).
     *     $path - A sub-folder in the uploads directory (optional).
     *     $put - Use copy() instead of move_uploaded_file()?
     *
     * Returns:
     *     The resulting filename from the upload.
     */
    function upload($file, $extension = null, $path = "", $put = false) {
        $file_split = explode(".", $file['name']);
        $path = rtrim($path, "/");
        $dir = MAIN_DIR.Config::current()->uploads_path.$path;

        if (!file_exists($dir))
            mkdir($dir, 0777, true);

        $original_ext = end($file_split);

        # Handle common double extensions
        foreach (array("tar.gz", "tar.bz", "tar.bz2") as $ext) {
            list($first, $second) = explode(".", $ext);
            $file_first =& $file_split[count($file_split) - 2];
            if ($file_first == $first and end($file_split) == $second) {
                $file_first = $first.".".$second;
                array_pop($file_split);
            }
        }

        $file_ext = end($file_split);

        if (is_array($extension)) {
            if (!in_array(strtolower($file_ext), $extension) and !in_array(strtolower($original_ext), $extension)) {
                $list = "";
                for ($i = 0; $i < count($extension); $i++) {
                    $comma = "";
                    if (($i + 1) != count($extension)) $comma = ", ";
                    if (($i + 2) == count($extension)) $comma = ", and ";
                    $list.= "<code>*.".$extension[$i]."</code>".$comma;
                }
                error(__("Invalid Extension"), _f("Only %s files are accepted.", array($list)));
            }
        } elseif (isset($extension) and
                  strtolower($file_ext) != strtolower($extension) and
                  strtolower($original_ext) != strtolower($extension))
            error(__("Invalid Extension"), _f("Only %s files are supported.", array("*.".$extension)));

        array_pop($file_split);
        $file_clean = implode(".", $file_split);
        $file_clean = sanitize($file_clean, false).".".$file_ext;
        $filename = unique_filename($file_clean, $path);

        $message = __("Couldn't upload file. CHMOD <code>".$dir."</code> to 777 and try again. If this problem persists, it's probably timing out; in which case, you must contact your system administrator to increase the maximum POST and upload sizes.");

        if ($put) {
            if (!@copy($file['tmp_name'], $dir."/".$filename))
                error(__("Error"), $message);
        } elseif (!@move_uploaded_file($file['tmp_name'], $dir."/".$filename))
            error(__("Error"), $message);

        return ($path ? $path."/".$filename : $filename);
    }

    /**
     * Function: upload_from_url
     * Copy a file from a specified URL to their upload directory.
     *
     * Parameters:
     *     $url - The URL to copy.
     *     $extension - An array of valid extensions (case-insensitive).
     *     $path - A sub-folder in the uploads directory (optional).
     *
     * See Also:
     *     <upload>
     */
    function upload_from_url($url, $extension = null, $path = "") {
        $file = tempnam(getcwd()."/tmp", "chyrp");
        file_put_contents($file, get_remote($url));

        $fake_file = array("name" => basename(parse_url($url, PHP_URL_PATH)),
                           "tmp_name" => $file);

        return upload($fake_file, $extension, $path, true);
    }

    /**
     * Function: uploaded
     * Returns a URL to an uploaded file.
     *
     * Parameters:
     *     $file - Filename relative to the uploads directory.
     */
    function uploaded($file, $url = true) {
        if (empty($file))
            return "";

        $config = Config::current();
        return ($url ? $config->chyrp_url.$config->uploads_path.$file : MAIN_DIR.$config->uploads_path.$file);
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
     * Returns an array of timezones that have unique offsets. Doesn't count deprecated timezones.
     */
    function timezones() {
        $zones = array();

        $deprecated = array("Brazil/Acre", "Brazil/DeNoronha", "Brazil/East", "Brazil/West", "Canada/Atlantic", "Canada/Central", "Canada/East-Saskatchewan", "Canada/Eastern", "Canada/Mountain", "Canada/Newfoundland", "Canada/Pacific", "Canada/Saskatchewan", "Canada/Yukon", "CET", "Chile/Continental", "Chile/EasterIsland", "CST6CDT", "Cuba", "EET", "Egypt", "Eire", "EST", "EST5EDT", "Etc/GMT", "Etc/GMT+0", "Etc/GMT+1", "Etc/GMT+10", "Etc/GMT+11", "Etc/GMT+12", "Etc/GMT+2", "Etc/GMT+3", "Etc/GMT+4", "Etc/GMT+5", "Etc/GMT+6", "Etc/GMT+7", "Etc/GMT+8", "Etc/GMT+9", "Etc/GMT-0", "Etc/GMT-1", "Etc/GMT-10", "Etc/GMT-11", "Etc/GMT-12", "Etc/GMT-13", "Etc/GMT-14", "Etc/GMT-2", "Etc/GMT-3", "Etc/GMT-4", "Etc/GMT-5", "Etc/GMT-6", "Etc/GMT-7", "Etc/GMT-8", "Etc/GMT-9", "Etc/GMT0", "Etc/Greenwich", "Etc/UCT", "Etc/Universal", "Etc/UTC", "Etc/Zulu", "Factory", "GB", "GB-Eire", "GMT", "GMT+0", "GMT-0", "GMT0", "Greenwich", "Hongkong", "HST", "Iceland", "Iran", "Israel", "Jamaica", "Japan", "Kwajalein", "Libya", "MET", "Mexico/BajaNorte", "Mexico/BajaSur", "Mexico/General", "MST", "MST7MDT", "Navajo", "NZ", "NZ-CHAT", "Poland", "Portugal", "PRC", "PST8PDT", "ROC", "ROK", "Singapore", "Turkey", "UCT", "Universal", "US/Alaska", "US/Aleutian", "US/Arizona", "US/Central", "US/East-Indiana", "US/Eastern", "US/Hawaii", "US/Indiana-Starke", "US/Michigan", "US/Mountain", "US/Pacific", "US/Pacific-New", "US/Samoa", "UTC", "W-SU", "WET", "Zulu");

        foreach (timezone_identifiers_list() as $zone)
            if (!in_array($zone, $deprecated))
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
     *     $table - If specified, the keywords will be checked against this table's columns for validity.
     *
     * Returns:
     *     An array containing the "WHERE" queries and the corresponding parameters.
     */
    function keywords($query, $plain, $table = null) {
        if (!trim($query))
            return array(array(), array());

        $search  = array();
        $matches = array();
        $where   = array();
        $params  = array();

        if ($table)
            $columns = SQL::current()->select($table)->fetch();

        $queries = explode(" ", $query);
        foreach ($queries as $query)
            if (!preg_match("/([a-z0-9_]+):(.+)/", $query))
                $search[] = $query;
            else
                $matches[] = $query;

        $times = array("year", "month", "day", "hour", "minute", "second");

        foreach ($matches as $match) {
            list($test, $equals,) = explode(":", $match);

            if ($equals[0] == '"') {
                if (substr($equals, -1) != '"')
                    foreach ($search as $index => $part) {
                        $equals.= " ".$part;

                        unset($search[$index]);

                        if (substr($part, -1) == '"')
                            break;
                    }

                $equals = ltrim(trim($equals, '"'), '"');
            }

            if (in_array($test, $times)) {
                if ($equals == "today")
                    $where["created_at like"] = date("%Y-m-d %");
                elseif ($equals == "yesterday")
                    $where["created_at like"] = date("%Y-m-d %", now("-1 day"));
                elseif ($equals == "tomorrow")
                    error(__("Error"), "Unfortunately our flux capacitor is currently having issues. Try again yesterday.");
                else
                    $where[strtoupper($test)."(created_at)"] = $equals;
            } elseif ($test == "author") {
                $user = new User(array("login" => $equals));
                if ($user->no_results and $equals == "me") {
                  !($table == "users") ? $where["user_id"] = Visitor::current()->id : $where["id"] = Visitor::current()->id;
                } else
                    !($table == "users") ? $where["user_id"] = $user->id : $where["id"] = $user->id;
            } elseif ($test == "group") {
                $group = new Group(array("name" => $equals));
                $where["group_id"] = $equals = ($group->no_results) ? 0 : $group->id;
            } else
                $where[$test] = $equals;
        }

        if ($table)
            foreach ($where as $col => $val)
                if (!isset($where[$col])) {
                    if ($table == "posts") {
                        $where["post_attributes.name"] = $col;
                        $where["post_attributes.value like"] = "%".$val."%";
                    }

                    unset($where[$col]);
                }

        if (!empty($search)) {
            $where[] = $plain;
            $params[":query"] = "%".join(" ", $search)."%";
        }

        $keywords = array($where, $params);

        Trigger::current()->filter($keywords, "keyword_search", $query, $plain);

        return $keywords;
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
                if (in_array(0, array_keys($val))) { # Numeric-indexed things need to be added as duplicates
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
     * Function: relative_time
     * Returns the difference between the given timestamps or now.
     *
     * Parameters:
     *     $time - Timestamp to compare to.
     *     $from - Timestamp to compare from. If not specified, defaults to now.
     *
     * Returns:
     *     A string formatted like "3 days ago" or "3 days from now".
     */
    function relative_time($when, $from = null) {
        fallback($from, time());

        $time = (is_numeric($when)) ? $when : strtotime($when) ;

        $difference = $from - $time;

        if ($difference < 0) {
            $word = "from now";
            $difference = -$difference;
        } elseif ($difference > 0)
            $word = "ago";
        else
            return "just now";

        $units = array("second"     => 1,
                       "minute"     => 60,
                       "hour"       => 60 * 60,
                       "day"        => 60 * 60 * 24,
                       "week"       => 60 * 60 * 24 * 7,
                       "month"      => 60 * 60 * 24 * 30,
                       "year"       => 60 * 60 * 24 * 365,
                       "decade"     => 60 * 60 * 24 * 365 * 10,
                       "century"    => 60 * 60 * 24 * 365 * 100,
                       "millennium" => 60 * 60 * 24 * 365 * 1000);

        $possible_units = array();
        foreach ($units as $name => $val)
            if (($name == "week" and $difference >= ($val * 2)) or # Only say "weeks" after two have passed.
                ($name != "week" and $difference >= $val))
                $unit = $possible_units[] = $name;

        $precision = (int) in_array("year", $possible_units);
        $amount = round($difference / $units[$unit], $precision);

        return $amount." ".pluralize($unit, $amount)." ".$word;
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
            $string = (is_string($item) and $quotes) ? "&#8220;".$item."&#8221;" : $item ;
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
     *
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
     * Function: delete_dir
     * Removes directories recursively.
     *
     * License GPLv2, Source http://candycms.org/
     */
    function delete_dir($dir) {
       if (substr($dir, strlen($dir)-1, 1) != '/')
           $dir .= '/';

       if ($handle = opendir($dir)) {
           while ($obj = readdir($handle)) {
               if ($obj != '.' && $obj != '..')
                   if (is_dir($dir.$obj))
                       if (!delete_dir($dir.$obj))
                           return false;
                   elseif (is_file($dir.$obj))
                       if (!unlink($dir.$obj)) 
                           return false; 
           }

           closedir($handle);

           if (!@rmdir($dir))
               return false;
           return true;
       }
       return false;
    }

    /**
     * Function: generate_captcha
     * Generates a captcha form element.
     *
     * Returns:
     *     A string containing an form input type
     */
    function generate_captcha() {
        global $captchaHooks;
        if (!$captchaHooks)
           return 0;
        return call_user_func($captchaHooks[0] . "::getCaptcha");
    }

    /**
     * Function: check_captcha
     * Checks if the answer to a captcha is right.
     *
     * Returns:
     *     A string containing an form input type
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
     *     $atts - Optional, additional key/value attributes to include in the IMG tag
     *
     * Returns:
     *     String containing either just a URL or a complete image tag
     *
     * Source:
     *     http://gravatar.com/site/implement/images/php/
     */
    function get_gravatar($email, $s = 80, $d = "mm", $r = "g", $img = false, $atts = array()) {
    	$url = "http://www.gravatar.com/avatar/".md5(strtolower(trim($email)))."?s=$s&d=$d&r=$r";
    	if ($img) {
    		$url = '<img src="' . $url . '"';
    		foreach ($atts as $key => $val)
    			$url .= ' ' . $key . '="' . $val . '"';
    		$url .= " />";
    	}
    	return $url;
    }
