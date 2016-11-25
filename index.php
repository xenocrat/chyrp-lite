<?php
    define('MAIN', true);

    require_once "includes".DIRECTORY_SEPARATOR."common.php";

    # Prepare the controller.
    $main = MainController::current();

    # Parse the route.
    $route = Route::current($main);

    # Execute the appropriate Controller responder.
    $route->init();

    # If the route failed or nothing was displayed, show a 404 page.
    if (!$route->success and !$main->displayed)
        show_404();

    $trigger->call("end", $route);

    ob_end_flush();
