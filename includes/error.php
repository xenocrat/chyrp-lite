<?php
    if (defined('AJAX') and AJAX or isset($_POST['ajax'])) {
        foreach ($backtrace as $trace)
            $body.= "\n"._f("%s on line %d", array($trace["file"], fallback($trace["line"], 0)));
        exit($body."HEY_JAVASCRIPT_THIS_IS_AN_ERROR_JUST_SO_YOU_KNOW");
    }

    $jquery = is_callable(array("Config", "current")) ?
              Config::current()->url."/includes/lib/gz.php?file=jquery.js" :
              "http://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js" ;

    if (!class_exists("MainController"))
        require INCLUDES_DIR."/controller/Main.php";

    if (class_exists("Route"))
        Route::current(MainController::current());
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>Chyrp: <?php echo $title; ?></title>
        <script src="<?php echo $jquery; ?>" type="text/javascript" charset="utf-8"></script>
        <style type="text/css">
            html, body, ul, ol, li,
            h1, h2, h3, h4, h5, h6,
            form, fieldset, a, p {
                margin: 0;
                padding: 0;
                border: 0;
            }
            html {
                font-size: 62.5%;
            }
            body {
                font: 1.25em/1.5em normal Verdana, Helvetica, Arial, sans-serif;
                color: #626262;
                background: #e8e8e8;
                padding: 0 0 5em;
            }
            .window {
                width: 30em;
                background: #fff;
                padding: 2em;
                margin: 5em auto 0;
                -webkit-border-radius: 2em;
                -moz-border-radius: 2em;
            }
            h1 {
                color: #ccc;
                font-size: 3em;
                margin: .25em 0 .5em;
                text-align: center;
                line-height: 1;
            }
            h2 {
                font-size: 1.25em;
                margin: 1em 0 0;
            }
            code {
                color: #06B;
                font-family: Monaco, monospace;
            }
            ul, ol {
                margin: 1em 3em;
            }
            ol.backtrace {
                margin-top: .5em;
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
                color: #6B0;
            }
            a:hover {
                text-decoration: underline;
            }
            a.big {
                background: #eee;
                margin-top: 2em;
                display: block;
                padding: .75em 1em;
                color: #777;
                text-shadow: #fff 1px 1px 0;
                font: 1em normal "Lucida Grande", Verdana, Helvetica, Arial, sans-serif;
                text-decoration: none;
                -webkit-border-radius: .5em;
                -moz-border-radius: .5em;
            }
            a.big:hover {
                background: #f5f5f5;
            }
            a.big:active {
                background: #e0e0e0;
            }
<?php if (!logged_in()): ?>
            a.big.back {
                -webkit-border-top-right-radius: 0 !important;
                -webkit-border-bottom-right-radius: 0 !important;
                width: 42%;
                float: left;
            }
            a.big.login {
                float: right;
                text-align: right;
                -webkit-border-top-left-radius: 0 !important;
                -webkit-border-bottom-left-radius: 0 !important;
                background: #f5f5f5;
                width: 42%;
            }
<?php endif; ?>
            .clear {
                clear: both;
            }
        </style>
        <script type="text/javascript" charset="utf-8">
            $(function(){
                $('<a class="big back" href="javascript:history.back()">&larr; <?php echo __("Back"); ?></a>').insertBefore(".clear.last")
            })
        </script>
    </head>
    <body>
        <div class="window">
            <h1><?php echo $title; ?></h1>
            <div class="message">
                <?php echo $body; ?>
            <?php if (!empty($backtrace)): ?>
                <h2><?php echo __("Backtrace"); ?></h2>
                <ol class="backtrace">
                <?php foreach ($backtrace as $trace): ?>
                    <li><code><?php echo _f("%s on line %d", array($trace["file"], fallback($trace["line"], 0))); ?></code></li>
                <?php endforeach; ?>
                </ol>
            <?php endif; ?>
                <div class="clear"></div>
            <?php if (class_exists("Route") and !logged_in() and $body != __("Route was initiated without a Controller.")): ?>
                <a href="<?php echo url("login", MainController::current()); ?>" class="big login"><?php echo __("Log In"); ?> &rarr;</a>
            <?php endif; ?>
                <div class="clear last"></div>
            </div>
        </div>
    <?php if (defined("CHYRP_VERSION")): ?>
        <p class="footer">Chyrp <?php echo CHYRP_VERSION; ?> &copy; Chyrp Team <?php echo date("Y"); ?></p>
    <?php endif; ?>
    </body>
</html>
