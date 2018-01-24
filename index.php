<?php
    define('MAIN', true);

    require_once "includes".DIRECTORY_SEPARATOR."common.php";

    # Prepare the controller.
    $main = MainController::current();

    # Parse the route.
    $route = Route::current($main);

    # Execute the appropriate Controller responder.
    $route->init();

    $trigger->call("end", $route);

    ob_end_flush();
