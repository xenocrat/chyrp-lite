<?php
    /**
     * File: RPC
     * Handles XML-RPC requests.
     */

    define('XML_RPC', true);

    require_once "common.php";

    # The feather to use for XML-RPC method calls.
    if (!defined('XML_RPC_FEATHER'))
        define('XML_RPC_FEATHER', "text");

    # Interpret "title" as this post attribute.
    if (!defined('XML_RPC_TITLE'))
        define('XML_RPC_TITLE', "title");

    # Interpret "description" as this post attribute.
    if (!defined('XML_RPC_DESCRIPTION'))
        define('XML_RPC_DESCRIPTION', "body");

    # Use the Main controller for any Route calls.
    Route::current(MainController::current());

    $server = new XMLRPC();
