<?php
    /**
     * File: gettext
     * Scans the installation and creates .pot files for all translated strings.
     */

    header("Content-Type: text/html; charset=UTF-8");

    define('DEBUG',          true);
    define('CHYRP_VERSION',  "2022.02");
    define('CHYRP_CODENAME', "Coal");
    define('CHYRP_IDENTITY', "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");
    define('MAIN',           false);
    define('ADMIN',          false);
    define('AJAX',           false);
    define('XML_RPC',        false);
    define('UPGRADING',      false);
    define('INSTALLING',     false);
    define('DIR',            DIRECTORY_SEPARATOR);
    define('MAIN_DIR',       dirname(dirname(__FILE__)));
    define('INCLUDES_DIR',   MAIN_DIR.DIR."includes");
    define('CACHES_DIR',     INCLUDES_DIR.DIR."caches");
    define('MODULES_DIR',    MAIN_DIR.DIR."modules");
    define('FEATHERS_DIR',   MAIN_DIR.DIR."feathers");
    define('THEMES_DIR',     MAIN_DIR.DIR."themes");
    define('CACHE_TWIG',     false);
    define('CACHE_THUMBS',   false);
    define('USE_OB',         true);
    define('CAN_USE_ZLIB',   false);
    define('USE_ZLIB',       false);

    ob_start();
    define('OB_BASE_LEVEL', ob_get_level());

    # File: error
    # Functions for handling and reporting errors.
    require_once INCLUDES_DIR.DIR."error.php";

    # File: helpers
    # Various functions used throughout the codebase.
    require_once INCLUDES_DIR.DIR."helpers.php";

    # Array: $domains
    # An associative array of domains and their paths.
    $domains = array("chyrp" => MAIN_DIR,
                     "admin" => MAIN_DIR.DIR."admin");

    # Array: $exclude
    # Paths to be excluded from directory recursion.
    $exclude = array(MAIN_DIR.DIR."admin",
                     MAIN_DIR.DIR."modules",
                     MAIN_DIR.DIR."feathers",
                     MAIN_DIR.DIR."themes",
                     MAIN_DIR.DIR."tools",
                     MAIN_DIR.DIR."uploads",
                     MAIN_DIR.DIR."includes".DIR."caches",
                     MAIN_DIR.DIR."includes".DIR."lib".DIR."Twig",
                     MAIN_DIR.DIR."includes".DIR."lib".DIR."TwigLegacy",
                     MAIN_DIR.DIR."includes".DIR."lib".DIR."IXR",
                     MAIN_DIR.DIR."includes".DIR."lib".DIR."cebe");

    # Array: $strings
    # Contains the translations for each gettext domain.
    $strings = array();

    # String: $str_reg
    # Regular expression representing a string.
    $str_reg = '(\"[^\"]+\"|\'[^\']+\')';

    /**
     * Function: find_domains
     * Find domains for installed extensions.
     */
    function find_domains() {
        global $domains;
        global $strings;

        $modules_dir = new DirectoryIterator(MODULES_DIR);

        foreach ($modules_dir as $item) {
            if ($item->isDir() and !$item->isDot())
                $domains[$item->getFilename()] = $item->getPathname();
        }

        $feathers_dir = new DirectoryIterator(FEATHERS_DIR);

        foreach ($feathers_dir as $item) {
            if ($item->isDir() and !$item->isDot())
                $domains[$item->getFilename()] = $item->getPathname();
        }

        $themes_dir = new DirectoryIterator(THEMES_DIR);

        foreach ($themes_dir as $item) {
            if ($item->isDir() and !$item->isDot())
                $domains[$item->getFilename()] = $item->getPathname();
        }

        foreach ($domains as $filename => $pathname)
            $strings[$filename] = array();
    }

    /**
     * Function: scan_dir
     * Scans a directory in search of files or subdirectories.
     */
    function scan_dir($domain, $pathname) {
        global $exclude;

        $dir = new DirectoryIterator($pathname);

        foreach ($dir as $item) {
            if (!$item->isDot()) {
                $item_path = $item->getPathname();
                $extension = $item->getExtension();

                switch ($item->getType()) {
                    case "file":
                        scan_file($domain, $item_path, $extension);
                        break;
                    case "dir":
                        if (!in_array($item_path, $exclude))
                            scan_dir($domain, $item_path);

                        break;
                }
            }
        }
    }

    /**
     * Function: scan_file
     * Scans a file in search of translation strings.
     */
    function scan_file($domain, $pathname, $extension) {
        if ($extension != "php" and $extension != "twig")
            return;

        $file = fopen($pathname, "r");
        $line = 1;

        if ($file === false)
            return;

        while (!feof($file)) {
            $text = fgets($file);

            switch ($extension) {
                case "php":
                    scan__($domain, $pathname, $line, $text);
                    scan_f($domain, $pathname, $line, $text);
                    scan_p($domain, $pathname, $line, $text);
                    break;
                case "twig":
                    scan_translate($domain, $pathname, $line, $text);
                    scan_translate_format($domain, $pathname, $line, $text);
                    scan_translate_plural($domain, $pathname, $line, $text);
                    break;
            }

            $line++;
        }

        fclose($file);
    }

    /**
     * Function: make_place
     * Makes a string detailing the place a translation was found.
     */
    function make_place($pathname, $line) {
        return str_replace(array(MAIN_DIR.DIR, DIR), array("", "/"), $pathname).":".$line;
    }

    /**
     * Function: is_theme
     * Checks if a pathname is part of a theme.
     */
    function is_theme($pathname) {
        return (strpos($pathname, THEMES_DIR) === 0 or strpos($pathname, MAIN_DIR.DIR."admin") === 0);
    }

    /**
     * Function: scan__
     * Scans text for occurrences of the __() function.
     */
    function scan__($domain, $pathname, $line, $text) {
        global $domains;
        global $strings;
        global $str_reg;

        $escaped = preg_quote($domain, "/");
        $dom_reg = ($domain == "chyrp") ? '' : ',\s*(\"'.$escaped.'\"|\''.$escaped.'\')' ;

        if (preg_match_all("/__\($str_reg$dom_reg\)/",
                           $text, $matches, PREG_SET_ORDER)) {

            foreach ($matches as $match) {
                $string = trim($match[1], "'\"");

                if (isset($strings[$domain][$string]))
                    $strings[$domain][$string]["places"][] = make_place($pathname, $line);
                else
                    $strings[$domain][$string] = array("places" => array(make_place($pathname, $line)),
                                                       "filter" => false,
                                                       "plural" => false);
            }
        }
    }

    /**
     * Function: scan_f
     * Scans text for occurrences of the _f() function.
     */
    function scan_f($domain, $pathname, $line, $text) {
        global $domains;
        global $strings;
        global $str_reg;

        $escaped = preg_quote($domain, "/");
        $dom_reg = ($domain == "chyrp") ? '.+?' : '.+?,\s*(\"'.$escaped.'\"|\''.$escaped.'\')' ;

        if (preg_match_all("/_f\($str_reg$dom_reg\)/",
                           $text, $matches, PREG_SET_ORDER)) {

            foreach ($matches as $match) {
                $string = trim($match[1], "'\"");

                if (isset($strings[$domain][$string]))
                    $strings[$domain][$string]["places"][] = make_place($pathname, $line);
                else
                    $strings[$domain][$string] = array("places" => array(make_place($pathname, $line)),
                                                       "filter" => true,
                                                       "plural" => false);
            }
        }
    }

    /**
     * Function: scan_p
     * Scans text for occurrences of the _p() function.
     */
    function scan_p($domain, $pathname, $line, $text) {
        global $domains;
        global $strings;
        global $str_reg;

        $escaped = preg_quote($domain, "/");
        $dom_reg = ($domain == "chyrp") ? '.+?' : '.+?,\s*(\"'.$escaped.'\"|\''.$escaped.'\')' ;

        if (preg_match_all("/_p\($str_reg,\s*$str_reg$dom_reg\)/",
                           $text, $matches, PREG_SET_ORDER)) {

            foreach ($matches as $match) {
                $string = trim($match[1], "'\"");
                $plural = trim($match[2], "'\"");

                if (isset($strings[$domain][$string]))
                    $strings[$domain][$string]["places"][] = make_place($pathname, $line);
                else
                    $strings[$domain][$string] = array("places" => array(make_place($pathname, $line)),
                                                       "filter" => true,
                                                       "plural" => $plural);
            }
        }
    }

    /**
     * Function: scan_translate
     * Scans text for occurrences of the translate() filter.
     */
    function scan_translate($domain, $pathname, $line, $text) {
        global $domains;
        global $strings;
        global $str_reg;

        $escaped = preg_quote($domain, "/");
        $dom_reg = '(\(\s*(\"'.$escaped.'\"|\''.$escaped.'\')\s*\))';

        if (is_theme($pathname))
            $dom_reg.= '?';

        if (preg_match_all("/$str_reg\s*\|\s*translate(?!_plural)$dom_reg(?!\s*\|\s*format)/",
                           $text, $matches, PREG_SET_ORDER)) {

            foreach ($matches as $match) {
                $string = trim($match[1], "'\"");

                if (isset($strings[$domain][$string]))
                    $strings[$domain][$string]["places"][] = make_place($pathname, $line);
                else
                    $strings[$domain][$string] = array("places" => array(make_place($pathname, $line)),
                                                       "filter" => false,
                                                       "plural" => false);
            }
        }
    }

    /**
     * Function: scan_translate_format
     * Scans text for occurrences of the translate() | format() filter combination.
     */
    function scan_translate_format($domain, $pathname, $line, $text) {
        global $domains;
        global $strings;
        global $str_reg;

        $escaped = preg_quote($domain, "/");
        $dom_reg = '(\(\s*(\"'.$escaped.'\"|\''.$escaped.'\')\s*\))';

        if (is_theme($pathname))
            $dom_reg.= '?';

        if (preg_match_all("/$str_reg\s*\|\s*translate$dom_reg\s*\|\s*format/",
                           $text, $matches, PREG_SET_ORDER)) {

            foreach ($matches as $match) {
                $string = trim($match[1], "'\"");

                if (isset($strings[$domain][$string]))
                    $strings[$domain][$string]["places"][] = make_place($pathname, $line);
                else
                    $strings[$domain][$string] = array("places" => array(make_place($pathname, $line)),
                                                       "filter" => true,
                                                       "plural" => false);
            }
        }
    }

    /**
     * Function: scan_translate_plural
     * Scans text for occurrences of the translate_plural() filter.
     */
    function scan_translate_plural($domain, $pathname, $line, $text) {
        global $domains;
        global $strings;
        global $str_reg;

        $escaped = preg_quote($domain, "/");
        $dom_reg = '(,\s*(\"'.$escaped.'\"|\''.$escaped.'\'))';

        if (is_theme($pathname))
            $dom_reg.= '?';

        if (preg_match_all("/$str_reg\s*\|\s*translate_plural\(\s*$str_reg\s*,.+?$dom_reg\s*\)\s*\|\s*format/",
                           $text, $matches, PREG_SET_ORDER)) {

            foreach ($matches as $match) {
                $string = trim($match[1], "'\"");
                $plural = trim($match[2], "'\"");

                if (isset($strings[$domain][$string]))
                    $strings[$domain][$string]["places"][] = make_place($pathname, $line);
                else
                    $strings[$domain][$string] = array("places" => array(make_place($pathname, $line)),
                                                       "filter" => true,
                                                       "plural" => $plural);
            }
        }
    }

    /**
     * Function: create_files
     * Generates the .pot files and writes them to disk.
     */
    function create_files() {
        global $domains;
        global $strings;

        foreach ($domains as $filename => $pathname) {
            $contents = "#. This file is distributed under the same license as the Chyrp Lite package.\n\n";

            foreach ($strings[$filename] as $string => $attributes) {
                foreach ($attributes["places"] as $place)
                    $contents.= "#: ".$place."\n";

                if ($attributes["filter"] === true)
                    $contents.= "#, php-format\n";

                $contents.= "msgid \"".$string."\"\n";

                if ($attributes["plural"] !== false) {
                    $contents.= "msgid_plural \"".$attributes["plural"]."\"\n";
                    $contents.= "msgstr[0] \"\"\n";
                    $contents.= "msgstr[1] \"\"\n";
                } else {
                    $contents.= "msgstr \"\"\n";
                }

                $contents.= "\n";
            }

            $pot_file = $pathname.DIR.(($filename == "chyrp") ? "includes".DIR : "").
                        "locale".DIR."en_US".DIR."LC_MESSAGES".DIR.$filename.".pot";

            $result = @file_put_contents($pot_file, $contents);

            echo $filename.".pot ".(($result === false) ?
                                    '<span style="background-color:#c11600;">Boo!</span>' :
                                    '<span style="background-color:#108600;">Yay!</span>')."\n";
        }
    }

    #---------------------------------------------
    # Output Starts
    #---------------------------------------------
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo "Gettext"; ?></title>
        <meta name="viewport" content="width = 800">
        <style type="text/css">
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-SemiBold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-SemiBoldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('../fonts/Hack-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('../fonts/Hack-Bold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('../fonts/Hack-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('../fonts/Hack-BoldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            *::selection {
                color: #ffffff;
                background-color: #ff7f00;
            }
            html {
                font-size: 14px;
            }
            html, body, ul, ol, li,
            h1, h2, h3, h4, h5, h6,
            form, fieldset, a, p {
                margin: 0em;
                padding: 0em;
                border: 0em;
            }
            body {
                font-size: 1rem;
                font-family: "Open Sans webfont", sans-serif;
                line-height: 1.5;
                color: #4a4747;
                background: #efefef;
                padding: 2rem;
            }
            h1 {
                font-size: 2em;
                text-align: center;
                font-weight: bold;
                margin: 1rem 0rem;
                line-height: 1;
            }
            h1:first-child {
                margin-top: 0em;
            }
            h2 {
                font-size: 1.5em;
                text-align: center;
                font-weight: bold;
                margin: 1rem 0rem;
            }
            h3 {
                font-size: 1em;
                font-weight: bold;
                margin: 1rem 0rem;
                border-bottom: 1px solid #cfcfcf;
            }
            p {
                margin-bottom: 1rem;
            }
            p:last-child,
            p:empty {
                margin-bottom: 0rem;
            }
            code {
                font-family: "Hack webfont", monospace;
                font-style: normal;
                font-size: 0.8rem;
                word-wrap: break-word;
                background-color: #efefef;
                padding: 0px 2px;
                color: #4f4f4f;
                border: 1px solid #cfcfcf;
            }
            strong {
                font-weight: normal;
                color: #d94c4c;
            }
            ul, ol {
                margin: 0rem 0rem 2rem 2rem;
                list-style-position: outside;
            }
            li {
                margin-bottom: 1rem;
            }
            pre.pane {
                overflow: auto;
                margin: 1rem -2rem 1rem -2rem;
                padding: 2rem;
                background: #4a4747;
                color: #ffffff;
            }
            pre.pane:empty {
                display: none;
            }
            pre.pane:empty + h1 {
                margin-top: 0rem;
            }
            a:link,
            a:visited {
                color: #4a4747;
                text-decoration: underline;
            }
            a:focus {
                outline: #ff7f00 dashed 2px;
            }
            a:hover,
            a:focus,
            a:active {
                color: #2f61c4;
                text-decoration: underline;
            }
            pre.pane a {
                color: #ffffff;
                font-weight: bold;
                font-style: italic;
                text-decoration: none;
            }
            pre.pane a:hover,
            pre.pane a:focus,
            pre.pane a:active {
                text-decoration: underline;
            }
            a.big,
            button {
                box-sizing: border-box;
                display: block;
                font-size: 1.25em;
                text-align: center;
                color: #4a4747;
                text-decoration: none;
                line-height: 1.25;
                margin: 1rem 0rem;
                padding: 0.4em 0.6em;
                background-color: #f2fbff;
                border: 2px solid #b8cdd9;
                border-radius: 0.3em;
                cursor: pointer;
            }
            button {
                width: 100%;
            }
            a.big:last-child,
            button:last-child {
                margin-bottom: 0em;
            }
            a.big:hover,
            button:hover,
            a.big:focus,
            button:focus,
            a.big:active,
            button:active {
                border-color: #1e57ba;
                outline: none;
            }
            aside {
                margin-bottom: 1rem;
                padding: 0.5em 1em;
                border: 1px solid #e5d7a1;
                border-radius: 0.25em;
                background-color: #fffecd;
            }
            .window {
                width: 30rem;
                background: #ffffff;
                padding: 2rem;
                margin: 0rem auto 0rem auto;
                border-radius: 2rem;
            }
        </style>
    </head>
    <body>
        <div class="window">
            <pre role="status" class="pane"><?php

    #---------------------------------------------
    # Processing Starts
    #---------------------------------------------

    find_domains();

    foreach ($domains as $filename => $pathname)
        scan_dir($filename, $pathname);

    create_files();

    #---------------------------------------------
    # Processing Ends
    #---------------------------------------------

            ?></pre>
        </div>
    </body>
</html>
