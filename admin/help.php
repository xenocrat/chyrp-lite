<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="$theme.type; charset=utf-8">
        <title>Chyrp: <?php echo $title; ?></title>
        <style type="text/css">
            html, body, ul, ol, li,
            h1, h2, h3, h4, h5, h6,
            form, fieldset, a, p {
                margin: 0em;
                padding: 0em;
                border: 0em;
            }
            body {
                font: 14px sans-serif;
                color: #4a4747;
                line-height: 1.25em;
                background: #fff;
                padding: 1em;
                overflow: auto;
            }
            code {
                color: #5f5f5f;
                font-family: monospace;
            }
            h2 {
                margin-bottom: .75em;
            }
            .title {
                color: #afafaf;
                font-size: 2em;
                font-weight: bold;
                margin: 0.5em 0em;
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
                background: #efefef;
                margin: 0 -1em 1em;
                white-space: pre-wrap;
            }
            .body ul,
            .body ol {
                margin: 0 0 1em 2em;
            }
            .body li {
                margin: 0;
            }
            a:link {
                color: #4a4747;
                text-decoration: underline;
            }
            a:visited {
                color: #4a4747;
                text-decoration: underline;
            }
            a:hover {
                color: #1e57ba;
                text-decoration: underline;
            }
            a:focus {
                outline: none;
                color: #1e57ba;
                text-decoration: underline;
            }
            a:active {
                color: #1e57ba;
                text-decoration: underline;
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
    <body role="document">
        <div class="title" role="banner">
            <?php echo $title; ?>
        </div>
        <div class="body" role="main">
            <?php echo $body; ?>
        </div>
    </body>
</html>
