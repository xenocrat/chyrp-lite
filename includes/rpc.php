<?php
    /**
     * File: rpc
     * Handles XML-RPC requests.
     */

    define('XML_RPC', true);

    require_once "common.php";

    # Respond to the request.
    $server = new XMLRPC();

    ob_end_flush();
