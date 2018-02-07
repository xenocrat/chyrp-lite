<?php
    define('AJAX', true);

    require_once dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."includes".DIRECTORY_SEPARATOR."common.php";

    # Prepare the controller.
    $ajax = AjaxController::current();

    # Parse the route.
    $route = Route::current($ajax);

    # Respond to the request.
    $route->init();

    $trigger->call("end", $route);

    ob_end_flush();
