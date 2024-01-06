<?php
    define('AJAX', true);

    require_once dirname(__FILE__, 2).
                 DIRECTORY_SEPARATOR.
                 "includes".
                 DIRECTORY_SEPARATOR.
                 "common.php";

    # Prepare the controller.
    $ajax = AjaxController::current();

    # Parse the route.
    $route = Route::current($ajax);

    # Respond to the request.
    $route->init();

    $trigger->call("end");
    ob_end_flush();
