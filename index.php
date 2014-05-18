<?php
    if (version_compare(PHP_VERSION, "5.3.0", "<"))
        exit("Chyrp requires PHP 5.3.0 or greater.");

    require_once "includes/common.php";

    # Prepare the controller.
    $main = MainController::current();

    # Parse the route.
    $route = Route::current($main);

    # Check if the user can view the site.
    if (!$visitor->group->can("view_site") and
        !in_array($route->action, array("login", "logout", "register", "lost_password")))
        if ($trigger->exists("can_not_view_site"))
            $trigger->call("can_not_view_site");
        else
            show_403(__("Access Denied"), __("You are not allowed to view this site."));

    # Execute the appropriate Controller responder.
    $route->init();

    # If the route failed or nothing was displayed, check for:
    # 1. Module-provided pages.
    # 2. Feather-provided pages.
    # 3. Theme-provided pages.
    if (!$route->success and !$main->displayed) {
        $displayed = false;

        foreach ($config->enabled_modules as $module)
            if (file_exists(MODULES_DIR."/".$module."/pages/".$route->action.".php"))
                $displayed = require MODULES_DIR."/".$module."/pages/".$route->action.".php";

        if (!$displayed)
            foreach ($config->enabled_feathers as $feather)
                if (file_exists(FEATHERS_DIR."/".$feather."/pages/".$route->action.".php"))
                    $displayed = require FEATHERS_DIR."/".$feather."/pages/".$route->action.".php";

        if (!$displayed and $theme->file_exists("pages/".$route->action))
            $main->display("pages/".$route->action);
        elseif (!$displayed)
            show_404();
    }

    $trigger->call("end", $route);

    ob_end_flush();
