<?php
    /**
     * File: download
     * Serve an uploaded file to the visitor as a file attachment.
     */

    define('USE_OB', false);

    require_once "common.php";
    $trigger->call("serve_download");

    if (empty($_GET['file']))
        error(
            __("Error"),
            __("Missing argument."),
            code:400
        );

    if (!$visitor->group->can("view_site"))
        show_403(
            __("Access Denied"),
            __("You are not allowed to view this site.")
        );

    $filename = str_replace(
        array(DIR, "/", "<", ">"),
        "",
        $_GET['file']
    );

    if ($filename == "")
        show_404(
            __("Not Found"),
            __("File not found.")
        );

    $filepath = uploaded($filename, false);

    if (!is_readable($filepath) or !is_file($filepath))
        show_404(
            __("Not Found"),
            __("File not found.")
        );

    # Respond to If-Modified-Since so the user agent will use cache.
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $lastmod = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);

        if ($lastmod >= filemtime($filepath)) {
            header_remove();
            header($_SERVER['SERVER_PROTOCOL']." 304 Not Modified");
            header("Vary: Accept-Encoding, Cookie");
            header("Cache-Control: no-cache, private, max-age=2592000");
            exit;
        }
    }

    if (!USE_COMPRESSION and !ini_get("zlib.output_compression"))
        header("Content-Length: ".filesize($filepath));

    $safename = addslashes($filename);
    header_remove("Pragma");
    header_remove("Expires");
    header_remove("Set-Cookie");
    header("Last-Modified: ".date("r", filemtime($filepath)));
    header("Vary: Accept-Encoding, Cookie");
    header("Cache-Control: no-cache, private, max-age=2592000");
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"".$safename."\"");
    readfile($filepath);

    if (DEBUG)
        error_log("DOWNLOAD served ".$filename);

    $trigger->call("end");
    flush();
