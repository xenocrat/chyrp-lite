<?php
    /**
     * File: RPC
     * Handles XML-RPC requests.
     */

    define('XML_RPC', true);

    require_once 'common.php';

    if (!defined('XML_RPC_FEATHER'))
        define('XML_RPC_FEATHER', 'text');

    # Use the Main controller for any Route calls.
    Route::current(MainController::current());

    $server = new XMLRPC();
