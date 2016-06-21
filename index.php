<?php
    define('MAIN', true);

    require_once "includes".DIRECTORY_SEPARATOR."common.php";

    # Prepare the controller.
    $main = MainController::current();

    # Parse the route.
    $route = Route::current($main);

    # Check if the user can view the site.
    if (!$visitor->group->can("view_site") and !in_array($route->action, array("login",
                                                                               "logout",
                                                                               "register",
                                                                               "activate",
                                                                               "lost_password",
                                                                               "reset"))) {
        if ($trigger->exists("can_not_view_site"))
            $trigger->call("can_not_view_site");
        else {
            if (logged_in())
                show_403(__("Access Denied"), __("You are not allowed to view this site.")); # Banned user.

            $_SESSION['redirect_to'] = self_url();
            Flash::notice(__("You must be logged in to view this site."), "login");
        }
    }

    # Execute the appropriate Controller responder.
    $route->init();

    # If the route failed or nothing was displayed, check for pages provided by the theme.
    if (!$route->success and !$main->displayed)
        if ($theme->file_exists("pages".DIR.$route->action))
            $main->display("pages".DIR.$route->action);
        else
            show_404();

    $trigger->call("end", $route);

    ob_end_flush();
