<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <meta http-equiv="Content-Type" content="$theme.type; charset=utf-8"/>
        <title>Chyrp: <?php echo $title; ?></title>
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
                background: #fff;
                padding: 1em 0 1em;
                overflow: auto;
            }
            code {
                color: #06B;
                font-family: Monaco, monospace;
            }
            h2 {
                margin-bottom: .75em;
            }
            .title {
                color: #aaa;
                font-size: 2em;
                font-weight: bold;
                margin: .25em 0 .5em;
                text-align: center;
            }
            .body {
                padding: 1em;
            }
            .body p {
                margin: 0 0 1em;
            }
            .body cite,
            .body pre {
                font-style: normal;
                display: block;
                padding: .25em 1em;
                background: #f0f0f0;
                margin: 0 -1em 1em;
            }
            .body ul,
            .body ol {
                margin: 0 0 1em 2em;
            }
            .body li {
                margin: 0;
            }
            a:link, a:visited {
                color: #6B0;
            }
            a:hover {
                text-decoration: underline;
            }
            a.big {
                font-size: 16px;
                color: #6B0;
                font-weight: bold;
            }
            a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="title"><?php echo $title; ?></div>
        <div class="body">
            <?php echo $body; ?>
        </div>
    </body>
</html>
