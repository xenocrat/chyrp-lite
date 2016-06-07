<?php
    ini_set('display_errors', false);
    ini_set('error_log', MAIN_DIR.DIR."error_log.txt");

    $errors = array();

    # Set the appropriate error handler.
    if (TESTER)
        set_error_handler("error_panicker");
    else
        if (INSTALLING or UPGRADING)
            set_error_handler("error_snitcher");
        else
            set_error_handler("error_composer");

    /**
     * Function: error_panicker
     * Report in plain text for the automated tester and exit.
     */
    function error_panicker($errno, $message, $file, $line) {
        if (error_reporting() === 0)
            return true; # Error reporting excludes this error.

        if (DEBUG)
            error_log("ERROR: ".$errno." ".$message." (".$file." on line ".$line.")");

        if (ob_get_contents() !== false)
            ob_clean();

        exit(htmlspecialchars("ERROR: ".$message." (".$file." on line ".$line.")", ENT_QUOTES, "utf-8", false));
    }

    /**
     * Function: error_snitcher
     * Informs the user of errors when installing or upgrading.
     */
    function error_snitcher($errno, $message, $file, $line) {
        global $errors;

        if (error_reporting() === 0)
            return true; # Error suppressed by @ operator.

        if (DEBUG)
            error_log("ERROR: ".$errno." ".$message." (".$file." on line ".$line.")");

        $errors[] = htmlspecialchars($message." (".$file." on line ".$line.")", ENT_QUOTES, "utf-8", false);
        return true;
    }

    /**
     * Function: error_composer
     * Composes a message for the error() function to display.
     */
    function error_composer($errno, $message, $file, $line) {
        if (!(error_reporting() & $errno))
            return true; # Error reporting excludes this error.

        if (DEBUG)
            error_log("ERROR: ".$errno." ".$message." (".$file." on line ".$line.")");

        error(null, $message." (".$file." on line ".$line.")", debug_backtrace());
    }

    /**
     * Function: error
     * Displays an error message via direct call or error handler.
     *
     * Parameters:
     *     $title - The title for the error dialog.
     *     $body - The message for the error dialog.
     *     $backtrace - The trace of the error.
     *     $code - Numeric HTTP status code to set.
     */
    function error($title = "", $body = "", $backtrace = array(), $code = 500) {
        # Sanitize strings.
        $title = htmlspecialchars($title, ENT_QUOTES, "utf-8", false);
        $body = preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/i", "<$1$2>", $body);
        $body = preg_replace("/<script[^>]*?>/i", "&lt;script&gt;", $body);
        $body = preg_replace("/<\/script[^>]*?>/i", "&lt;/script&gt;", $body);

        if (!empty($backtrace))
            foreach ($backtrace as $index => &$trace)
                if (!isset($trace["file"]) or !isset($trace["line"])) {
                    unset($backtrace[$index]);
                } else {
                    $trace["line"] = htmlspecialchars($trace["line"], ENT_QUOTES, "utf-8", false);
                    $trace["file"] = htmlspecialchars(str_replace(MAIN_DIR.DIR, "", $trace["file"]), ENT_QUOTES, "utf-8", false);
                }

        # Clean the output buffer before we begin.
        if (ob_get_contents() !== false)
            ob_clean();

        # Attempt to set headers to sane values.
        if (!headers_sent()) {
            header("Content-type: text/html; charset=UTF-8");
            header("Cache-Control: no-cache, must-revalidate");
            header("Expires: Mon, 03 Jun 1991 05:30:00 GMT");

            switch ($code) {
                case 400:
                    header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request");
                    break;

                case 403:
                    header($_SERVER["SERVER_PROTOCOL"]." 403 Forbidden");
                    break;

                case 404:
                    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
                    break;

                case 405:
                    header($_SERVER["SERVER_PROTOCOL"]." 405 Method Not Allowed");
                    break;

                case 409:
                    header($_SERVER["SERVER_PROTOCOL"]." 409 Conflict");
                    break;

                case 410:
                    header($_SERVER["SERVER_PROTOCOL"]." 410 Gone");
                    break;

                case 501:
                    header($_SERVER["SERVER_PROTOCOL"]." 501 Not Implemented");
                    break;

                case 503:
                    header($_SERVER["SERVER_PROTOCOL"]." 503 Service Unavailable");
                    break;

                default:
                    header($_SERVER["SERVER_PROTOCOL"]." 500 Internal Server Error");
                    break;
            }
        }

        # Report in plain text for the automated tester, RPC and AJAX queries.
        if (TESTER or XML_RPC or AJAX)
            exit("ERROR: ".strip_tags($body));

        # Report and exit safely if the error is too deep in the core for a pretty error message.
        if (!function_exists("__") or
            !function_exists("_f") or
            !function_exists("fallback") or
            !function_exists("oneof") or
            !function_exists("logged_in") or
            !function_exists("admin_url") or
            !class_exists("Config") or
            !method_exists("Config", "current") or
            !property_exists(Config::current(), "url") or
            !property_exists(Config::current(), "chyrp_url"))
            exit("<!DOCTYPE html>\n<h1>ERROR:</h1>\n<p>".$body."</p>");

        $config = Config::current();
        $url = $config->url;
        $chyrp_url = $config->chyrp_url;

        # Validate title and body text.
        $title = oneof($title, __("Error"));
        $body = oneof($body, __("An unspecified error has occurred."));

        # Display the error.
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title><?php echo $title; ?></title>
        <meta name="viewport" content="width = 520, user-scalable = no">
        <style type="text/css">
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('<?php echo $chyrp_url; ?>/fonts/OpenSans-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('<?php echo $chyrp_url; ?>/fonts/OpenSans-Semibold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('<?php echo $chyrp_url; ?>/fonts/OpenSans-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('<?php echo $chyrp_url; ?>/fonts/OpenSans-SemiboldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('<?php echo $chyrp_url; ?>/fonts/Hack-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('<?php echo $chyrp_url; ?>/fonts/Hack-Bold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('<?php echo $chyrp_url; ?>/fonts/Hack-Oblique.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('<?php echo $chyrp_url; ?>/fonts/Hack-BoldOblique.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            *::selection {
                color: #ffffff;
                background-color: #4f4f4f;
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
                padding: 0em 0em 5em;
            }
            .window {
                width: 30em;
                background: #fff;
                padding: 2em;
                margin: 5em auto 0em;
                border-radius: 2em;
            }
            h1 {
                font-size: 2em;
                margin: 0.5em 0em;
                text-align: left;
                line-height: 1;
            }
            h1:first-child {
                margin-top: 0em;
            }
            h2 {
                font-size: 1.25em;
                font-weight: bold;
                margin: 0.75em 0em;
            }
            code {
                font-family: "Hack webfont", monospace;
                font-style: normal;
                word-wrap: break-word;
                background-color: #efefef;
                padding: 2px;
                color: #4f4f4f;
            }
            strong {
                font-weight: normal;
                color: #f00;
            }
            ul, ol {
                margin: 0em 0em 2em 2em;
                list-style-position: outside;
            }
            ul:last-child,
            ol:last-child {
                margin-bottom: 0em;
            }
            ol.backtrace {
                margin-top: 0.5em;
            }
            .footer {
                color: #777;
                margin-top: 1em;
                font-size: .9em;
                text-align: center;
            }
            .error {
                color: #F22;
                font-size: 12px;
            }
            a:link, a:visited {
                color: #4a4747;
            }
            a:hover, a:focus {
                color: #1e57ba;
            }
            a.big,
            button {
                box-sizing: border-box;
                display: block;
                clear: both;
                font-family: inherit;
                font-size: 1.25em;
                text-align: center;
                color: #4a4747;
                text-decoration: none;
                line-height: 1.25;
                margin: 0.75em 0em;
                padding: 0.4em 0.6em;
                background-color: #f2fbff;
                border: 1px solid #b8cdd9;
                border-radius: 0.3em;
                cursor: pointer;
                text-decoration: none;
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
            p {
                margin-bottom: 1em;
            }
            p:last-child, p:empty {
                margin-bottom: 0em
            }
        </style>
    </head>
    <body>
        <div class="window">
            <h1><?php echo $title; ?></h1>
            <div role="alert" class="message">
                <?php echo $body; ?>
            <?php if (!empty($backtrace)): ?>
                <h2><?php echo __("Backtrace"); ?></h2>
                <ol class="backtrace">
                <?php foreach ($backtrace as $trace): ?>
                    <li><code><?php echo _f("%s on line %d", array($trace["file"], fallback($trace["line"], 0))); ?></code></li>
                <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <?php if (!logged_in() and ADMIN): ?>
                <a href="<?php echo admin_url('login'); ?>" class="big login"><?php echo __("Log in"); ?></a>
            <?php endif; ?>
            </div>
        </div>
    </body>
</html>
<?php
        # Terminate execution.
        exit;
    }
