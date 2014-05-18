<?php
    define('ADMIN', true);

    require_once "../includes/common.php";

    # Prepare the controller.
    $admin = AdminController::current();

    # Parse the route.
    $route = Route::current($admin);

    # Check if the user can view the site.
    if (!$visitor->group->can("view_site"))
        if ($trigger->exists("can_not_view_site"))
            $trigger->call("can_not_view_site");
        else
            show_403(__("Access Denied"), __("You are not allowed to view this site."));

    # Execute the appropriate Controller responder.
    $route->init();

    if (!$route->success and !$admin->displayed)
        $admin->display($route->action); # Attempt to display it; it'll go through Modules and Feathers.

    $trigger->call("end", $route);

    ob_end_flush();
