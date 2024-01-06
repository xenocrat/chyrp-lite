<?php
    define('ADMIN', true);

    require_once dirname(__FILE__, 2).
                 DIRECTORY_SEPARATOR.
                 "includes".
                 DIRECTORY_SEPARATOR.
                 "common.php";

    # Prepare the controller.
    $admin = AdminController::current();

    # Parse the route.
    $route = Route::current($admin);

    # Respond to the request.
    $route->init();

    $trigger->call("end");
    ob_end_flush();
