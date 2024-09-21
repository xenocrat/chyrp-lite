<?php
    /**
     * File: docgen
     * Scans the installation for API documentation.
     */

    header("Content-Type: text/html; charset=UTF-8");

    define('DEBUG',                         true);
    define('CHYRP_VERSION',                 "2025.01");
    define('CHYRP_CODENAME',                "Boreal");
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
    define('USE_GETTEXT_SHIM',              stripos(PHP_OS, "Win") === 0);
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
        INCLUDES_DIR.DIR."caches",
        INCLUDES_DIR.DIR."lib".DIR."Twig",
        INCLUDES_DIR.DIR."lib".DIR."IXR",
        INCLUDES_DIR.DIR."lib".DIR."cebe"
    );

    # Array: $docs
    # Contains the gathered documentation.
    $docs = "";

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
     * Scans a file in search of documentation.
     */
    function scan_file(
        $pathname,
        $extension
    ) {
        if ($extension != "php")
            return;

        $file = @file_get_contents($pathname);

        if ($file === false)
            return;

        scan_docs($pathname, $file);
    }

    /**
     * Function: make_place
     * Makes a string detailing the file where the documentation was found.
     */
    function make_place(
        $pathname
    ) {
        return str_replace(
            array(MAIN_DIR.DIR, DIR),
            array("", "/"),
            $pathname
        );
    }

    /**
     * Function: scan_docs
     * Scans text for documentation.
     */
    function scan_docs(
        $pathname,
        $file
    ) {
        global $docs;

        if (
            preg_match_all(
                '/\n +\/\*\*\n.+?\n +\*\//s',
                $file,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            # Add a header for this file.
            $docs.= "==============================================\n".
                    make_place($pathname)."\n".
                    "==============================================\n\n";

            foreach ($matches as $match) {
                $doc = $match[0];

                # Underline the title.
                $doc = preg_replace_callback('/\n +\/\*\*\n +\* +(.+)\n/',
                    function ($matches) {
                        return $matches[1]."\n".str_repeat(
                            "-", strlen($matches[1])
                        )."\n";
                    }, $doc);

                # Remove leading asterisks.
                $doc = preg_replace('/\n +\* ?\/?/', "\n", $doc);

                $docs.= $doc."\n\n";
            }
        }
    }

    /**
     * Function: create_file
     * Writes the documentation to disk and echoes it.
     */
    function create_file() {
        global $docs;
        @file_put_contents(
            MAIN_DIR.DIR."tools".DIR."api_docs.txt",
            $docs
        );
        echo fix($docs);
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
        <title><?php echo "Documentation Generator"; ?></title>
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
                --chyrp-medium-purple: #fae1fa;
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
                padding: 0px 2px;
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
                    color: ar(--chyrp-pure-white);
                    background-color: var(--chyrp-inky-black);
                }
                .window {
                    color: var(--chyrp-inky-black);
                    background-color: var(--chyrp-english-grey);
                }
                hr {
                    border-color: var(--chyrp-scottish-grey);
                }
                aside {
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

    scan_dir(INCLUDES_DIR);
    create_file();

    #---------------------------------------------
    # Processing Ends
    #---------------------------------------------

            ?></pre>
        </div>
    </body>
</html>
