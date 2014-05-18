<?php
    # Constant: USE_ZLIB
    # Use zlib to provide GZIP compression
    if (version_compare(PHP_VERSION, "5.4.4", "<")) define('USE_ZLIB', true);
    else define('USE_ZLIB', false);

    $valid_files = "jquery.js plugins.js";
    if (!in_array($_GET['file'], explode(" ", $valid_files)) and strpos($_GET['file'], "/themes/") === false)
        exit("Access Denied.");

    if (substr_count($_GET['file'], "..") > 0 )
        exit("GTFO.");

    if (extension_loaded('zlib') and USE_ZLIB and !ini_get("zlib.output_compression")) {
        ob_start("ob_gzhandler");
        header("Content-Encoding: gzip");
    } else
        ob_start();

    header("Content-Type: application/x-javascript");

    if (strpos($_GET['file'], "/themes/") === 0) {
        # Constant: MAIN_DIR
        # Absolute path to the Chyrp root
        define('MAIN_DIR', dirname(dirname(dirname(__FILE__))));

        header("Last-Modified: ".@date("r", filemtime(MAIN_DIR.$_GET['file'])));

        if (file_exists(MAIN_DIR.$_GET['file']))
            readfile(MAIN_DIR.$_GET['file']);
        else
            echo "alert('File not found: ".addslashes($_GET['file'])."')";
    } elseif (file_exists($_GET['file'])) {
        header("Last-Modified: ".@date("r", filemtime($_GET['file'])));
        readfile($_GET['file']);
    } else
        echo "alert('File not found: ".addslashes($_GET['file'])."')";

    ob_end_flush();
