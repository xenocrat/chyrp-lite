<?php
    define('ADMIN', true);

    require_once dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."includes".DIRECTORY_SEPARATOR."common.php";

    # Prepare the controller.
    $admin = AdminController::current();

    # Parse the route.
    $route = Route::current($admin);

    # Can the visitor view the site? Are they attempting an action that is exempt from permissions?
    if (!$visitor->group->can("view_site") and !in_array($route->action, array("login", "logout"))) {
        if ($trigger->exists("can_not_view_site"))
            $trigger->call("can_not_view_site");
        else
            show_403(__("Access Denied"), __("You are not allowed to view this site."));
    }

    # Execute the appropriate Controller responder.
    $route->init();

    # If the route failed or nothing was displayed, show a 404 page.
    if (!$route->success and !$admin->displayed)
        show_404();

    $trigger->call("end", $route);

    ob_end_flush();
