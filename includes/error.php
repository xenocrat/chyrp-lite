<?php
    /**
     * File: error
     * Functions for handling and reporting errors.
     */

    ini_set("display_errors", false);
    ini_set("error_log", MAIN_DIR.DIR."error_log.txt");

    # Array: $errors
    # Stores errors encountered when installing or upgrading.
    $errors = array();

    # Set the appropriate error reporting level.
    if (DEBUG)
        error_reporting(E_ALL | E_STRICT);
    else
        error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_USER_NOTICE);

    # Set the error and exception handlers.
    set_error_handler("error_composer");
    set_exception_handler("exception_composer");

    /**
     * Function: error_composer
     * Composes a message for the error() function to display.
     */
    function error_composer($errno, $message, $file, $line) {
        # Test for suppressed errors and excluded error levels.
        if (!(error_reporting() & $errno))
            return true;

        $normalized = str_replace(array("\t", "\n", "\r", "\0", "\x0B"), " ", $message);

        if (DEBUG)
            error_log("ERROR: ".$errno." ".strip_tags($normalized)." (".$file." on line ".$line.")");

        error(null, $message, debug_backtrace());
    }

    /**
     * Function: exception_composer
     * Composes a message for the error() function to display.
     */
    function exception_composer($e) {
        $errno = $e->getCode();
        $message = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();
        $normalized = str_replace(array("\t", "\n", "\r", "\0", "\x0B"), " ", $message);

        if (DEBUG)
            error_log("ERROR: ".$errno." ".strip_tags($normalized)." (".$file." on line ".$line.")");

        error(null, $message, $e->getTrace());
    }

    /**
     * Function: error
     * Displays an error message via direct call or handler.
     *
     * Parameters:
     *     $title - The title for the error dialog.
     *     $body - The message for the error dialog.
     *     $backtrace - The trace of the error.
     *     $code - Numeric HTTP status code to set.
     */
    function error($title = "", $body = "", $backtrace = array(), $code = 500) {
        # Clean the output buffer before we begin.
        if (ob_get_contents() !== false)
            ob_clean();

        # Attempt to set headers to sane values and send a status code.
        if (!headers_sent()) {
            header("Content-Type: text/html; charset=UTF-8");
            header("Cache-Control: no-cache, must-revalidate");
            header("Expires: Mon, 03 Jun 1991 05:30:00 GMT");

            switch ($code) {
                case 400:
                    header($_SERVER['SERVER_PROTOCOL']." 400 Bad Request");
                    break;
                case 401:
                    header($_SERVER['SERVER_PROTOCOL']." 401 Unauthorized");
                    break;
                case 403:
                    header($_SERVER['SERVER_PROTOCOL']." 403 Forbidden");
                    break;
                case 404:
                    header($_SERVER['SERVER_PROTOCOL']." 404 Not Found");
                    break;
                case 405:
                    header($_SERVER['SERVER_PROTOCOL']." 405 Method Not Allowed");
                    break;
                case 409:
                    header($_SERVER['SERVER_PROTOCOL']." 409 Conflict");
                    break;
                case 410:
                    header($_SERVER['SERVER_PROTOCOL']." 410 Gone");
                    break;
                case 413:
                    header($_SERVER['SERVER_PROTOCOL']." 413 Payload Too Large");
                    break;
                case 422:
                    header($_SERVER['SERVER_PROTOCOL']." 422 Unprocessable Entity");
                    break;
                case 501:
                    header($_SERVER['SERVER_PROTOCOL']." 501 Not Implemented");
                    break;
                case 502:
                    header($_SERVER['SERVER_PROTOCOL']." 502 Bad Gateway");
                    break;
                case 503:
                    header($_SERVER['SERVER_PROTOCOL']." 503 Service Unavailable");
                    break;
                case 504:
                    header($_SERVER['SERVER_PROTOCOL']." 504 Gateway Timeout");
                    break;
                default:
                    header($_SERVER['SERVER_PROTOCOL']." 500 Internal Server Error");
            }
        }

        # Report in plain text if desirable or necessary because of a deep error.
        if (TESTER or XML_RPC or AJAX or JAVASCRIPT or
            !function_exists("__") or
            !function_exists("_f") or
            !function_exists("fallback") or
            !function_exists("fix") or
            !function_exists("sanitize_html") or
            !function_exists("logged_in") or
            !file_exists(INCLUDES_DIR.DIR."config.json.php") or
            !class_exists("Config") or
            !method_exists("Config", "current") or
            !property_exists(Config::current(), "chyrp_url")) {

            exit("ERROR: ".strip_tags($body));
        }

        # We need this for the pretty error page.
        $chyrp_url = fix(Config::current()->chyrp_url, true);

        # Set fallbacks.
        fallback($title, __("Error"));
        fallback($body, __("An unspecified error has occurred."));
        fallback($backtrace, array());

        # Redact and escape the backtrace for display.
        foreach ($backtrace as $index => &$trace)
            if (!isset($trace["file"]) or !isset($trace["line"]))
                unset($backtrace[$index]);
            else
                $trace["file"] = fix(str_replace(MAIN_DIR.DIR, "", $trace["file"]), false, true);

        #---------------------------------------------
        # Output Starts
        #---------------------------------------------
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo strip_tags($title); ?></title>
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
                src: url('<?php echo $chyrp_url; ?>/fonts/Hack-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Hack webfont';
                src: url('<?php echo $chyrp_url; ?>/fonts/Hack-BoldItalic.woff') format('woff');
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
                padding: 0rem 0rem 5rem;
            }
            h1 {
                font-size: 2em;
                margin: 1rem 0em;
                text-align: left;
                line-height: 1;
            }
            h1:first-child {
                margin-top: 0em;
            }
            h2 {
                font-size: 1.25em;
                font-weight: bold;
                margin: 1rem 0em;
            }
            p {
                margin-bottom: 1rem;
            }
            p:last-child,
            p:empty {
                margin-bottom: 0em;
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
                color: #d94c4c;
            }
            ul, ol {
                margin: 0rem 0rem 2rem 2rem;
                list-style-position: outside;
            }
            ul:last-child,
            ol:last-child {
                margin-bottom: 0em;
            }
            ol.backtrace {
                margin-top: 0.5em;
            }
            a:link,
            a:visited {
                color: #4a4747;
                text-decoration: underline;
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
                clear: both;
                font-size: 1.25em;
                text-align: center;
                color: #4a4747;
                text-decoration: none;
                line-height: 1.25;
                margin: 1rem 0rem;
                padding: 0.4em 0.6em;
                background-color: #f2fbff;
                border: 1px solid #b8cdd9;
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
            .window {
                width: 30rem;
                background: #ffffff;
                padding: 2rem;
                margin: 5rem auto 0rem auto;
                border-radius: 2rem;
            }
        </style>
    </head>
    <body>
        <div class="window">
            <h1><?php echo sanitize_html($title); ?></h1>
            <div role="alert" class="message">
                <?php echo sanitize_html($body); ?>
            <?php if (!empty($backtrace) and DEBUG): ?>
                <h2><?php echo __("Backtrace"); ?></h2>
                <ol class="backtrace">
                <?php foreach ($backtrace as $trace): ?>
                    <li><code><?php echo _f("%s on line %d", array($trace["file"], (int) $trace["line"])); ?></code></li>
                <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <?php if (!logged_in() and ADMIN and $code == 403): ?>
                <a href="<?php echo $chyrp_url.'/admin/?action=login'; ?>" class="big login"><?php echo __("Log in"); ?></a>
            <?php endif; ?>
            </div>
        </div>
    </body>
</html>
<?php
        #---------------------------------------------
        # Output Ends
        #---------------------------------------------

        # Terminate execution.
        exit;
    }
