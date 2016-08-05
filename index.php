<?php
    define('MAIN', true);

    require_once "includes".DIRECTORY_SEPARATOR."common.php";

    # Prepare the controller.
    $main = MainController::current();

    # Parse the route.
    $route = Route::current($main);

    # Can the visitor view the site? Are they attempting an action that is exempt from permissions?
    if (!$visitor->group->can("view_site") and !in_array($route->action, array("login",
                                                                               "logout",
                                                                               "register",
                                                                               "activate",
                                                                               "lost_password",
                                                                               "reset"))) {
        if ($trigger->exists("can_not_view_site"))
            $trigger->call("can_not_view_site");
        elseif (logged_in())
            show_403(__("Access Denied"), __("You are not allowed to view this site.")); # Banned user.
        else {
            $_SESSION['redirect_to'] = self_url();
            Flash::notice(__("You must be logged in to view this site."), "login"); # Prompt to log in.
        }
    }

    # Execute the appropriate Controller responder.
    $route->init();

    # If the route failed or nothing was displayed, show a 404 page.
    if (!$route->success and !$main->displayed)
        show_404();

    $trigger->call("end", $route);

    ob_end_flush();
