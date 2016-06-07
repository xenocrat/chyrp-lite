<?php
    /**
     * File: Download
     * Send an uploaded file to the visitor.
     */

    define('DEBUG',        false);
    define('JAVASCRIPT',   false);
    define('MAIN',         false);
    define('ADMIN',        false);
    define('AJAX',         false);
    define('XML_RPC',      false);
    define('UPGRADING',    false);
    define('INSTALLING',   false);
    define('TESTER',       isset($_SERVER['HTTP_USER_AGENT']) and $_SERVER['HTTP_USER_AGENT'] == "TESTER");
    define('DIR',          DIRECTORY_SEPARATOR);
    define('MAIN_DIR',     dirname(dirname(__FILE__)));
    define('INCLUDES_DIR', dirname(__FILE__));

    # Constant: JSON_PRETTY_PRINT
    # Define a safe value to avoid warnings pre-5.4
    if (!defined('JSON_PRETTY_PRINT'))
        define('JSON_PRETTY_PRINT', 0);

    # Constant: JSON_UNESCAPED_SLASHES
    # Define a safe value to avoid warnings pre-5.4
    if (!defined('JSON_UNESCAPED_SLASHES'))
        define('JSON_UNESCAPED_SLASHES', 0);

    require_once "error.php";
    require_once "helpers.php";
    require_once "class".DIR."Config.php";

    # Sanitize input depending on magic_quotes_gpc's enabled status.
    sanitize_input($_GET);

    if (isset($_SERVER["REQUEST_METHOD"]) and $_SERVER["REQUEST_METHOD"] !== "GET")
        error(__("Error"), __("This resource accepts GET requests only."), null, 405);

    if (empty($_GET['file']))
        error(__("Error"), __("Missing argument."), null, 400);

    $filename = oneof(trim($_GET['file']), DIR);
    $filepath = uploaded($filename, false);

    if (substr_count($filename, DIR))
        error(__("Error"), __("Malformed URI."), null, 400);

    if (!is_readable($filepath) or !is_file($filepath))
        error(__("Not Found"), __("Post not found."), null, 404);

    if (DEBUG)
        error_log("SERVING file download for ".$filename);

    header("Last-Modified: ".gmdate("D, d M Y H:i:s", filemtime($filepath))." GMT");
    header("Content-type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"".$filename."\"");
    header("Content-length: ".filesize($filepath));
    readfile($filepath);
    flush();
