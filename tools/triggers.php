<?php
    /**
     * File: triggers
     * Scans the installation for Trigger calls and filters.
     */

    header("Content-Type: text/html; charset=UTF-8");

    define('DEBUG',                         true);
    define('CHYRP_VERSION',                 "2025.02");
    define('CHYRP_CODENAME',                "Acacia");
    define('CHYRP_IDENTITY',                "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");
    define('MAIN',                          false);
    define('ADMIN',                         false);
    define('AJAX',                          false);
    define('UPGRADING',                     false);
    define('INSTALLING',                    false);
    define('COOKIE_LIFETIME',               2592000);
    define('PASSWORD_RESET_TOKEN_LIFETIME', 3600);
    define('MAX_TIME_LIMIT',                600);
    define('MAX_MEMORY_LIMIT',              "100M");
    define('SQL_DATETIME_ZERO',             "1000-01-01 00:00:00");
    define('SQL_DATETIME_ZERO_VARIANTS',
                                            array(
                                                "0000-00-00 00:00:00",
                                                "0001-01-01 00:00:00",
                                                "1000-01-01 00:00:00"
                                            )
    );
    define('BOT_UA',                        false);
    define('DIR',                           DIRECTORY_SEPARATOR);
    define('MAIN_DIR',                      dirname(__FILE__));
    define('INCLUDES_DIR',                  MAIN_DIR.DIR."includes");
    define('CACHES_DIR',                    INCLUDES_DIR.DIR."caches");
    define('MODULES_DIR',                   MAIN_DIR.DIR."modules");
    define('FEATHERS_DIR',                  MAIN_DIR.DIR."feathers");
    define('THEMES_DIR',                    MAIN_DIR.DIR."themes");
    define('UPDATE_XML',                    null);
    define('UPDATE_INTERVAL',               null);
    define('UPDATE_PAGE',                   null);
    define('SESSION_DENY_BOT',              true);
    define('SLUG_STRICT',                   true);
    define('GET_REMOTE_UNSAFE',             false);
    define('USE_GETTEXT_SHIM',              true);
    define('USE_OB',                        true);
    define('HTTP_ACCEPT_ZSTD',              false);
    define('HTTP_ACCEPT_DEFLATE',           false);
    define('HTTP_ACCEPT_GZIP',              false);
    define('CAN_USE_ZSTD',                  false);
    define('CAN_USE_ZLIB',                  false);
    define('USE_COMPRESSION',               false);
    define('PREVIEWING',                    false);
    define('THEME_DIR',                     null);
    define('THEME_URL',                     null);

    ob_start();
    define('OB_BASE_LEVEL', ob_get_level());

    # File: error
    # Functions for handling and reporting errors.
    require_once INCLUDES_DIR.DIR."error.php";

    # File: helpers
    # Various functions used throughout the codebase.
    require_once INCLUDES_DIR.DIR."helpers.php";

    # Array: $exclude
    # Paths to be excluded from directory recursion.
    $exclude = array(
        MAIN_DIR.DIR."tools",
        MAIN_DIR.DIR."uploads",
        MAIN_DIR.DIR."includes".DIR."caches",
        MAIN_DIR.DIR."includes".DIR."lib".DIR."Twig",
        MAIN_DIR.DIR."includes".DIR."lib".DIR."IXR",
        MAIN_DIR.DIR."includes".DIR."lib".DIR."cebe"
    );

    # Array: $trigger
    # Contains the calls and filters.
    $trigger = array(
        "call" => array(),
        "filter" => array()
    );

    # String: $str_reg
    # Regular expression representing a string.
    $str_reg = '(\"[^\"]+\"|\'[^\']+\')';

    # String: $arr_reg
    # Regular expression representing an array construct.
    $arr_reg = 'array\(([^\)]+)\)';

    /**
     * Function: scan_dir
     * Scans a directory in search of files or subdirectories.
     */
    function scan_dir(
        $pathname
    ) {
        global $exclude;

        $dir = new DirectoryIterator($pathname);

        foreach ($dir as $item) {
            if (!$item->isDot()) {
                $item_path = $item->getPathname();
                $extension = $item->getExtension();

                switch ($item->getType()) {
                    case "file":
                        scan_file($item_path, $extension);
                        break;
                    case "dir":
                        if (!in_array($item_path, $exclude))
                            scan_dir($item_path);

                        break;
                }
            }
        }
    }

    /**
     * Function: scan_file
     * Scans a file in search of triggers.
     */
    function scan_file(
        $pathname,
        $extension
    ) {
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
                    scan_call($pathname, $line, $text);
                    scan_call_array($pathname, $line, $text);
                    scan_filter($pathname, $line, $text);
                    scan_filter_array($pathname, $line, $text);
                    break;
                case "twig":
                    scan_twig($pathname, $line, $text);
                    break;
            }

            $line++;
        }

        fclose($file);
    }

    /**
     * Function: make_place
     * Makes a string detailing the place a trigger was found.
     */
    function make_place(
        $pathname,
        $line
    ) {
        return str_replace(
            array(MAIN_DIR.DIR, DIR),
            array("", "/"),
            $pathname
        )." on line ".$line;
    }

    /**
     * Function: make_arguments
     * Makes an array from a string of arguments.
     */
    function make_arguments(
        $text
    ) {
        $array = explode(",", $text);

        foreach ($array as &$arg) {
            $arg = trim($arg, ", ");
        }

        return array_diff($array, array(""));
    }

    /**
     * Function: scan_call
     * Scans text for trigger calls.
     */
    function scan_call(
        $pathname,
        $line,
        $text
    ) {
        global $trigger;
        global $str_reg;

        if (
            preg_match_all(
                "/(\\\$trigger|Trigger::current\(\))->call\($str_reg(,\s*(.+))?\)/",
                $text,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $call = trim($match[2], "'\"");

                if (isset($trigger["call"][$call]))
                    $trigger["call"][$call]["places"][] = make_place(
                        $pathname,
                        $line
                    );
                else
                    $trigger["call"][$call] = array(
                        "places"    => array(make_place($pathname, $line)),
                        "arguments" => make_arguments(fallback($match[4], ""))
                    );
            }
        }
    }

    /**
     * Function: scan_call_array
     * Scans text for trigger call arrays.
     */
    function scan_call_array(
        $pathname,
        $line,
        $text
    ) {
        global $trigger;
        global $arr_reg;

        if (
            preg_match_all(
                "/(\\\$trigger|Trigger::current\(\))->call\($arr_reg(,\s*(.+))?\)/",
                $text,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $calls = explode(",", $match[2]);

                foreach ($calls as $call) {
                    $call = trim($call, "'\" ");

                    if (empty($call) or preg_match('/[^A-Za-z0-9_]/', $call))
                        continue;

                    if (isset($trigger["call"][$call]))
                        $trigger["call"][$call]["places"][] = make_place(
                            $pathname,
                            $line
                        );
                    else
                        $trigger["call"][$call] = array(
                            "places"    => array(make_place($pathname, $line)),
                            "arguments" => make_arguments(fallback($match[4], ""))
                        );
                }
            }
        }
    }

    /**
     * Function: scan_filter
     * Scans text for trigger filters.
     */
    function scan_filter(
        $pathname,
        $line,
        $text
    ) {
        global $trigger;
        global $str_reg;

        if (
            preg_match_all(
                "/(\\\$trigger|Trigger::current\(\))->filter\(([^,]+),\s*$str_reg(,\s*(.+))?\)/",
                $text,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $filter = trim($match[3], "'\"");

                if (isset($trigger["filter"][$filter]))
                    $trigger["filter"][$filter]["places"][] = make_place(
                        $pathname,
                        $line
                    );
                else
                    $trigger["filter"][$filter] = array(
                        "places"    => array(make_place($pathname, $line)),
                        "target"    => trim($match[2], ", "),
                        "arguments" => make_arguments(fallback($match[5], ""))
                    );
            }
        }
    }

    /**
     * Function: scan_filter_array
     * Scans text for trigger filter arrays.
     */
    function scan_filter_array(
        $pathname,
        $line,
        $text
    ) {
        global $trigger;
        global $arr_reg;

        if (
            preg_match_all(
                "/(\\\$trigger|Trigger::current\(\))->filter\(([^,]+),\s*$arr_reg(,\s*(.+))?\)/",
                $text,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $filters = explode(",", $match[3]);

                foreach ($filters as $filter) {
                    $filter = trim($filter, "'\" ");

                    if (empty($filter) or preg_match('/[^A-Za-z0-9_]/', $filter))
                        continue;

                    if (isset($trigger["filter"][$filter]))
                        $trigger["filter"][$filter]["places"][] = make_place(
                            $pathname,
                            $line
                        );
                    else
                        $trigger["filter"][$filter] = array(
                            "places"    => array(make_place($pathname, $line)),
                            "target"    => trim($match[2], ", "),
                            "arguments" => make_arguments(fallback($match[5], ""))
                        );
                }
            }
        }
    }

    /**
     * Function: scan_twig
     * Scans text for trigger calls in Twig statements.
     */
    function scan_twig(
        $pathname,
        $line,
        $text
    ) {
        global $trigger;
        global $str_reg;

        if (
            preg_match_all(
                "/\{\{\s*trigger\.call\($str_reg(,\s*(.+))?\)\s*\}\}/",
                $text,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $call = trim($match[1], "'\"");

                if (isset($trigger["call"][$call]))
                    $trigger["call"][$call]["places"][] = make_place(
                        $pathname,
                        $line
                    );
                else
                    $trigger["call"][$call] = array(
                        "places"    => array(make_place($pathname, $line)),
                        "arguments" => make_arguments(fallback($match[3], ""))
                    );
            }
        }
    }

    /**
     * Function: create_file
     * Generates the triggers list and writes it to disk.
     */
    function create_file() {
        global $trigger;

        $contents = "==============================================\n".
                    " Trigger Calls\n".
                    "==============================================\n";

        foreach ($trigger["call"] as $call => $attributes) {
            $contents.= "\n\n";
            $contents.= $call."\n";
            $contents.= str_repeat("-", strlen($call))."\n";
            $contents.= "Called from:\n";

            foreach ($attributes["places"] as $place)
                $contents.= "\t".$place."\n";

            if (!empty($attributes["arguments"])) {
                $contents.= "\nArguments:\n";

                foreach ($attributes["arguments"] as $argument)
                    $contents.= "\t".$argument."\n";            }
        }

        $contents.= "\n\n\n\n";
        $contents.= "==============================================\n".
                    " Trigger Filters\n".
                    "==============================================\n";

        foreach ($trigger["filter"] as $filter => $attributes) {
            $contents.= "\n\n";
            $contents.= $filter."\n";
            $contents.= str_repeat("-", strlen($filter))."\n";
            $contents.= "Called from:\n";

            foreach ($attributes["places"] as $place)
                $contents.= "\t".$place."\n";

            $contents.= "\nTarget:\n";
            $contents.= "\t".$attributes["target"]."\n";

            if (!empty($attributes["arguments"])) {
                $contents.= "\nArguments:\n";

                foreach ($attributes["arguments"] as $argument)
                    $contents.= "\t".$argument."\n";
            }
        }

        @file_put_contents(
            MAIN_DIR.DIR."tools".DIR."triggers_list.txt",
            $contents
        );
        echo fix($contents);
    }

    #---------------------------------------------
    # Output Starts
    #---------------------------------------------
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=640">
        <title><?php echo "Triggers"; ?></title>
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
                font-weight: 600;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-Bold.woff') format('woff');
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
                font-weight: 600;
                font-style: italic;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-BoldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('../fonts/Cousine-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('../fonts/Cousine-Bold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('../fonts/Cousine-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('../fonts/Cousine-BoldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            :root {
                color-scheme: light dark;
                --chyrp-pure-white: #ffffff;
                --chyrp-pure-black: #000000;
                --chyrp-inky-black: #1f1f23;
                --chyrp-summer-grey: #fbfbfb;
                --chyrp-english-grey: #efefef;
                --chyrp-welsh-grey: #dfdfdf;
                --chyrp-irish-grey: #cfcfcf;
                --chyrp-scottish-grey: #afafaf;
                --chyrp-winter-grey: #656565;
                --chyrp-strong-yellow: #ffdd00;
                --chyrp-strong-orange: #ff7f00;
                --chyrp-strong-red: #c11600;
                --chyrp-strong-green: #108600;
                --chyrp-strong-blue: #1e57ba;
                --chyrp-strong-purple: #ba1eba;
                --chyrp-light-yellow: #fffde6;
                --chyrp-light-red: #faebe4;
                --chyrp-light-green: #ebfae4;
                --chyrp-light-blue: #f2fbff;
                --chyrp-light-purple: #fae4fa;
                --chyrp-medium-yellow: #fffbcc;
                --chyrp-medium-red: #fcddcf;
                --chyrp-medium-green: #daf1d0;
                --chyrp-medium-blue: #e1f2fa;
                --chyrp-medium-purple: #f6d5f6;
                --chyrp-border-yellow: #e5d7a1;
                --chyrp-border-red: #d6bdb5;
                --chyrp-border-green: #bdd6b5;
                --chyrp-border-blue: #b8cdd9;
                --chyrp-border-purple: #d6b5d6;
            }
            *::selection {
                color: var(--chyrp-inky-black);
                background-color: var(--chyrp-strong-yellow);
            }
            html, body, div, dl, dt, dd, ul, ol, li, p,
            h1, h2, h3, h4, h5, h6, img, pre, code,
            form, fieldset, input, select, svg, textarea,
            table, tbody, tr, th, td, legend, caption,
            blockquote, aside, figure, figcaption {
                margin: 0em;
                padding: 0em;
                border: 0em;
            }
            html {
                font-size: 16px;
            }
            body {
                font-size: 1rem;
                font-family: "Open Sans webfont", sans-serif;
                line-height: 1.5;
                color: var(--chyrp-inky-black);
                tab-size: 4;
                background: var(--chyrp-english-grey);
                margin: 2rem;
            }
            h1 {
                font-size: 2em;
                text-align: center;
                font-weight: bold;
                margin: 1rem 0rem;
            }
            h2 {
                font-size: 1.5em;
                text-align: center;
                font-weight: bold;
                margin: 1rem 0rem;
            }
            h3 {
                font-size: 1em;
                font-weight: 600;
                margin: 1rem 0rem;
                border-bottom: 1px solid var(--chyrp-irish-grey);
            }
            p {
                margin-bottom: 1rem;
            }
            strong {
                font: inherit;
                font-weight: bold;
                color: var(--chyrp-strong-red);
            }
            em, dfn, cite, var {
                font: inherit;
                font-style: italic;
            }
            ul, ol {
                margin-bottom: 1rem;
                margin-inline-start: 2rem;
                list-style-position: outside;
            }
            pre {
                font-family: "Cousine webfont", monospace;
                font-size: 0.85em;
                background-color: var(--chyrp-english-grey);
                margin: 1rem 0rem;
                padding: 1rem;
                overflow-x: auto;
                white-space: pre;
            }
            code {
                font-family: "Cousine webfont", monospace;
                font-size: 0.85em;
                background-color: var(--chyrp-english-grey);
                padding: 2px 4px 0px 4px;
                border: 1px solid var(--chyrp-irish-grey);
                vertical-align: bottom;
                white-space: break-spaces;
            }
            pre > code {
                font-size: 0.85rem;
                display: block;
                border: none;
                padding: 0px;
                white-space: inherit;
            }
            a:link,
            a:visited {
                color: var(--chyrp-inky-black);
                text-decoration: underline;
                text-underline-offset: 0.125em;
            }
            a:focus {
                outline: var(--chyrp-strong-orange) dashed 2px;
                outline-offset: 0px;
            }
            a:hover,
            a:focus,
            a:active {
                color: var(--chyrp-strong-blue);
                text-decoration: underline;
                text-underline-offset: 0.125em;
            }
            a.big,
            button {
                box-sizing: border-box;
                display: block;
                font: inherit;
                font-size: 1.25em;
                text-align: center;
                color: var(--chyrp-inky-black);
                text-decoration: none;
                margin: 1rem 0rem;
                padding: 0.5rem 1rem;
                background-color: var(--chyrp-light-blue);
                border: 2px solid var(--chyrp-border-blue);
                border-radius: 0.25em;
                cursor: pointer;
            }
            button {
                width: 100%;
            }
            a.big:hover,
            button:hover,
            a.big:focus,
            button:focus,
            a.big:active,
            button:active {
                border-color: var(--chyrp-strong-blue);
                outline: none;
            }
            hr {
                border: none;
                clear: both;
                border-top: 1px solid var(--chyrp-irish-grey);
                margin: 2rem 0rem;
            }
            aside {
                margin-bottom: 1rem;
                padding: 0.5rem;
                border: 1px solid var(--chyrp-border-yellow);
                border-radius: 0.25em;
                background-color: var(--chyrp-light-yellow);
            }
            .window {
                width: 30rem;
                background: var(--chyrp-pure-white);
                padding: 2rem;
                margin: 0rem auto 0rem auto;
                border-radius: 2rem;
            }
            .window > *:first-child {
                margin-top: 0rem;
            }
            .window > *:last-child {
                margin-bottom: 0rem;
            }
            @media (prefers-color-scheme: dark) {
                body {
                    color: var(--chyrp-pure-white);
                    background-color: var(--chyrp-inky-black);
                }
                .window {
                    color: var(--chyrp-inky-black);
                    background-color: var(--chyrp-english-grey);
                }
                h3 {
                    border-color: var(--chyrp-scottish-grey);
                }
                hr {
                    border-color: var(--chyrp-scottish-grey);
                }
                aside {
                    background-color: var(--chyrp-medium-yellow);
                    border-color: var(--chyrp-scottish-grey);
                }
                pre {
                    background-color: var(--chyrp-welsh-grey);
                }
                code {
                    background-color: var(--chyrp-welsh-grey);
                    border-color: var(--chyrp-scottish-grey);
                }
            }
        </style>
    </head>
    <body>
        <div class="window">
            <pre role="status" class="pane"><?php

    #---------------------------------------------
    # Processing Starts
    #---------------------------------------------

    scan_dir(MAIN_DIR);
    create_file();

    #---------------------------------------------
    # Processing Ends
    #---------------------------------------------

            ?></pre>
        </div>
    </body>
</html>
