<?php
    if (!class_exists("MainController")) {
        if (defined("INCLUDES_DIR")) {
            require INCLUDES_DIR."/controller/Main.php";
        } else {
            header("Status: 403"); exit("Access denied."); # Undefined constants: xss protection.
        }
    }

    if (class_exists("Route"))
        Route::current(MainController::current());

    if (defined('AJAX') and AJAX or isset($_POST['ajax'])) {
        foreach ($backtrace as $trace)
            $body.= "\n"._f("%s on line %d", array($trace["file"], fallback($trace["line"], 0)));
        exit($body."HEY_JAVASCRIPT_THIS_IS_AN_ERROR_JUST_SO_YOU_KNOW");
    }

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title><?php echo $title; ?></title>
        <meta name="viewport" content="width = 520, user-scalable = no">
        <style type="text/css">
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-Regular.woff') format('woff'),
                     url('../fonts/OpenSans-Regular.ttf') format('truetype');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-Semibold.woff') format('woff'),
                     url('../fonts/OpenSans-Semibold.ttf') format('truetype');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-Italic.woff') format('woff'),
                     url('../fonts/OpenSans-Italic.ttf') format('truetype');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-SemiboldItalic.woff') format('woff'),
                     url('../fonts/OpenSans-SemiboldItalic.ttf') format('truetype');
                font-weight: bold;
                font-style: italic;
            }
            *::-moz-selection {
                color: #ffffff;
                background-color: #4f4f4f;
            }
            *::selection {
                color: #ffffff;
                background-color: #4f4f4f;
            }
            html {
                font-size: 16px;
            }
            html, body, ul, ol, li,
            h1, h2, h3, h4, h5, h6,
            form, fieldset, a, p {
                margin: 0em;
                padding: 0em;
                border: 0em;
            }
            body {
                font-size: 14px;
                font-family: "Open Sans webfont", sans-serif;
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
                margin: 1em 0em;
                text-align: center;
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
                font-family: monospace;
                font-style: normal;
                word-wrap: break-word;
                background-color: #efefef;
                padding: 2px;
                color: #4f4f4f;
            }
            ul, ol {
                margin: 0em 0em 2em 2em;
                list-style-position: inside;
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
                line-height: 1.25em;
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
        </style>
    </head>
    <body role="document">
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
            <?php if (class_exists("Route") and !logged_in() and $body != __("Route was initiated without a Controller.")): ?>
                <a href="<?php echo url("login", MainController::current()); ?>" class="big login"><?php echo __("Log in"); ?></a>
            <?php endif; ?>
            </div>
        </div>
    </body>
</html>
