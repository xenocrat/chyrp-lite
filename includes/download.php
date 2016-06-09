<?php
    /**
     * File: Download
     * Send an uploaded file to the visitor.
     */

    define('USE_OB', false);

    require_once "common.php";

    if (isset($_SERVER["REQUEST_METHOD"]) and $_SERVER["REQUEST_METHOD"] !== "GET")
        error(__("Error"), __("This resource accepts GET requests only."), null, 405);

    if (empty($_GET['file']))
        error(__("Error"), __("Missing argument."), null, 400);

    $filename = oneof(trim($_GET['file']), DIR);
    $filepath = uploaded($filename, false);

    if (substr_count($filename, DIR))
        error(__("Error"), __("Malformed URI."), null, 400);

    if (!is_readable($filepath) or !is_file($filepath))
        error(__("Not Found"), __("File not found."), null, 404);

    if (DEBUG)
        error_log("SERVING file download for ".$filename);

    header("Last-Modified: ".gmdate("D, d M Y H:i:s", filemtime($filepath))." GMT");
    header("Content-type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"".$filename."\"");
    header("Content-length: ".filesize($filepath));
    readfile($filepath);
    flush();
