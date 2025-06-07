<?php
    /**
     * File: thumbnail
     * Serves compressed image thumbnails for uploaded files.
     */

    define('USE_COMPRESSION', false);

    require_once "common.php";
    $trigger->call("serve_thumbnail");
    set_max_memory();

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

    $quality = abs((int) fallback($_GET["quality"], 80));

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
    $thumb_w = abs((int) fallback($_GET["max_width"], 960));
    $thumb_h = abs((int) fallback($_GET["max_height"], 0));

    if (!is_readable($filepath) or !is_file($filepath))
        show_404(
            __("Not Found"),
            __("File not found.")
        );

    # Halve the quality if reduced data usage is preferred.
    if (isset($_SERVER['HTTP_SAVE_DATA'])) {
        if (!preg_match("/^(off|0)$/i", $_SERVER['HTTP_SAVE_DATA']))
           $quality = floor($quality * 0.5);
    }

    $thumb = new ThumbnailFile(
        $filename,
        $thumb_w,
        $thumb_h,
        $quality,
        !empty($_GET['square'])
    );

    # Redirect to original if thumbnail cannot or should not be created.
    if (!$thumb->creatable() or $thumb->upscaling()) {
        header_remove("Pragma");
        header("Cache-Control: public, must-revalidate, stale-if-error");
        header("Expires: ".date("r", now("+7 days")));
        redirect(uploaded($filename), code:301);
    }

    # Respond to If-Modified-Since so the user agent will use cache.
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $lastmod = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);

        if ($lastmod >= filemtime($filepath)) {
            header_remove();
            header($_SERVER['SERVER_PROTOCOL']." 304 Not Modified");
            header("Vary: Accept-Encoding, Save-Data");
            header("Cache-Control: public, must-revalidate, stale-if-error");
            header("Expires: ".date("r", now("+30 days")));
            exit;
        }
    }

    $safename = addslashes($thumb->name());
    header_remove("Pragma");
    header("Last-Modified: ".date("r", filemtime($filepath)));
    header("Vary: Accept-Encoding, Save-Data");
    header("Cache-Control: public, must-revalidate, stale-if-error");
    header("Expires: ".date("r", now("+30 days")));
    header("Content-Disposition: inline; filename=\"".$safename."\"");
    $thumb->create();
    $thumb->serve();

    $trigger->call("end");
    ob_end_flush();
