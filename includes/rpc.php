<?php
    /**
     * File: RPC
     * Handles XML-RPC requests.
     */

    define('XML_RPC', true);

    require_once "common.php";

    # XML_RPC_FEATHER must support XML_RPC_TITLE and XML_RPC_DESCRIPTION post attributes.
    if (!defined('XML_RPC_FEATHER'))
        define('XML_RPC_FEATHER', "text");

    if (!defined('XML_RPC_TITLE'))
        define('XML_RPC_TITLE', "title");

    if (!defined('XML_RPC_DESCRIPTION'))
        define('XML_RPC_DESCRIPTION', "body");

    # Use the Main controller for any Route calls.
    Route::current(MainController::current());

    $server = new XMLRPC();
