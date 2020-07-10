<?php
    /**
     * File: download
     * Send an uploaded file to the visitor as a file attachment.
     */

    define('USE_OB', false);

    require_once "common.php";

    if (isset($_SERVER['REQUEST_METHOD']) and $_SERVER['REQUEST_METHOD'] !== "GET")
        error(__("Error"), __("This resource accepts GET requests only."), null, 405);

    if (empty($_GET['file']))
        error(__("Error"), __("Missing argument."), null, 400);

    if (!$visitor->group->can("view_site"))
        show_403(__("Access Denied"), __("You are not allowed to view this site."));

    $filename = str_replace(array(DIR, "/"), "", $_GET['file']);
    $filepath = uploaded($filename, false);

    if (!is_readable($filepath) or !is_file($filepath))
        show_404(__("Not Found"), __("File not found."));

    if (DEBUG)
        error_log("SERVE download ".$filename);

    if (!in_array("ob_gzhandler", ob_list_handlers()) and !ini_get("zlib.output_compression"))
        header("Content-Length: ".filesize($filepath));

    header("Last-Modified: ".date("r", filemtime($filepath)));
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"".addslashes($filename)."\"");
    readfile($filepath);
    flush();
