<?php
    define('MAIN', true);

    require_once "includes".DIRECTORY_SEPARATOR."common.php";

    # Prepare the controller.
    $main = MainController::current();

    # Parse the route.
    $route = Route::current($main);

    # Respond to the request.
    $route->init();

    $trigger->call("end");
    ob_end_flush();
