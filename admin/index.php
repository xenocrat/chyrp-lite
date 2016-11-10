<?php
    define('ADMIN', true);

    require_once dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."includes".DIRECTORY_SEPARATOR."common.php";

    # Prepare the controller.
    $admin = AdminController::current();

    # Parse the route.
    $route = Route::current($admin);

    # Execute the appropriate Controller responder.
    $route->init();

    # If the route failed or nothing was displayed, show a 404 page.
    if (!$route->success and !$admin->displayed)
        show_404();

    $trigger->call("end", $route);

    ob_end_flush();
