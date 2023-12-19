<?php
    /**
     * File: docgen
     * Scans the installation for API documentation.
     */

    header("Content-Type: text/html; charset=UTF-8");

    define('DEBUG',                         true);
    define('CHYRP_VERSION',                 "2023.03");
    define('CHYRP_CODENAME',                "Varied");
    define('CHYRP_IDENTITY',                "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");
    define('MAIN',                          false);
    define('ADMIN',                         false);
    define('AJAX',                          false);
    define('UPGRADING',                     false);
    define('INSTALLING',                    false);
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
    define('GET_REMOTE_UNSAFE',             false);
    define('USE_GETTEXT_SHIM',              stripos(PHP_OS, "Win") === 0);
    define('USE_OB',                        true);
    define('HTTP_ACCEPT_DEFLATE',           false);
    define('HTTP_ACCEPT_GZIP',              false);
    define('CAN_USE_ZLIB',                  false);
    define('USE_ZLIB',                      false);
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
    function scan_dir($pathname) {
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
    function scan_file($pathname, $extension) {
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
    function make_place($pathname) {
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
    function scan_docs($pathname, $file) {
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
        <meta name="viewport" content="width = 640">
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
            *::selection {
                color: #ffffff;
                background-color: #ff7f00;
            }
            html, body, div, dl, dt, dd, ul, ol, li, p,
            h1, h2, h3, h4, h5, h6, img, pre, code,
            form, fieldset, input, select, textarea,
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
                color: #1f1f23;
                background: #efefef;
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
                border-bottom: 1px solid #cfcfcf;
            }
            p {
                margin-bottom: 1rem;
            }
            strong, address {
                font: inherit;
                font-weight: bold;
                color: #c11600;
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
                background-color: #efefef;
                margin: 1rem 0rem;
                padding: 1rem;
                overflow-x: auto;
            }
            code {
                font-family: "Cousine webfont", monospace;
                font-size: 0.85em;
                background-color: #efefef;
                padding: 0px 2px;
                border: 1px solid #cfcfcf;
                vertical-align: bottom;
            }
            pre > code {
                font-size: 0.85rem;
                display: block;
                border: none;
                padding: 0px;
            }
            a:link,
            a:visited {
                color: #1f1f23;
                text-decoration: underline;
                text-underline-offset: 0.125em;
            }
            a:focus {
                outline: #ff7f00 dashed 2px;
                outline-offset: 1px;
            }
            a:hover,
            a:focus,
            a:active {
                color: #1e57ba;
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
                color: #1f1f23;
                text-decoration: none;
                margin: 1rem 0rem;
                padding: 0.4em 0.6em;
                background-color: #f2fbff;
                border: 2px solid #b8cdd9;
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
                border-color: #1e57ba;
                outline: none;
            }
            aside {
                margin-bottom: 1rem;
                padding: 0.5rem 1rem;
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
            .window > *:first-child {
                margin-top: 0rem;
            }
            .window > *:last-child {
                margin-bottom: 0rem;
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
