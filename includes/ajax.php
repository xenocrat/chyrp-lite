<?php
    define('AJAX', true);

    require_once "common.php";

    # Prepare the controller.
    $main = MainController::current();

    # Parse the route.
    $route = Route::current($main);

    if (isset($_SERVER["REQUEST_METHOD"]) and $_SERVER["REQUEST_METHOD"] !== "POST")
        error(__("Error"), __("This resource accepts POST requests only."), null, 405);

    if (empty($_POST['action']))
        error(__("Error"), __("Missing argument."), null, 400);

    if (!$visitor->group->can("view_site"))
        show_403(__("Access Denied"), __("You are not allowed to view this site."));

    switch($_POST['action']) {
        case "destroy_post":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a post."), null, 400);

            $post = new Post($_POST['id'], array("drafts" => true));

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            if (!$post->deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this post."));

            Post::delete($post->id);
            json_response(__("Post deleted."));
        case "destroy_page":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a page."), null, 400);

            $page = new Page($_POST['id']);

            if ($page->no_results)
                show_404(__("Not Found"), __("Page not found."));

            if (!$visitor->group->can("delete_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete pages."));

            Page::delete($page->id, true);
            json_response(__("Page deleted."));
        case "preview":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("add_post", "add_draft", "add_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to add content."));

            if (empty($_POST['filter']))
                error(__("No Filter Specified"), __("A filter is required to preview content."), null, 400);

            fallback($_POST['content'], "Lorem ipsum dolor sit amet.");

            header("Cache-Control: no-cache, must-revalidate");
            header("Expires: Mon, 03 Jun 1991 05:30:00 GMT");

            $sanitized = sanitize_html($_POST['content']);

            Trigger::current()->filter($sanitized, $_POST['filter']);

            $main->display("content".DIR."preview",
                           array("content" => $sanitized,
                                 "filter" => $_POST['filter']), __("Preview"));
            exit;
        case "enable":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to toggle extensions."));

            if (empty($_POST['extension']) or empty($_POST['type']))
                error(__("No Extension Specified"), __("You did not specify an extension to enable."), null, 400);

            $type          = ($_POST['type'] == "module") ? "module" : "feather" ;
            $name          = str_replace(array(".", DIR), "", $_POST['extension']);
            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $folder        = ($type == "module") ? MODULES_DIR : FEATHERS_DIR ;
            $class_name    = camelize($name);

            if (in_array($name, (array) $config->$enabled_array))
                error(__("Error"), __("Extension already enabled."), null, 409);

            if (!file_exists($folder.DIR.$name.DIR.$name.".php"))
                show_404(__("Not Found"), __("Extension not found."));

            load_translator($name, $folder.DIR.$name.DIR."locale".DIR.$config->locale.".mo");

            require $folder.DIR.$name.DIR.$name.".php";

            if (method_exists($class_name, "__install"))
                call_user_func(array($class_name, "__install"));

            $config->set($enabled_array, array_merge((array) $config->$enabled_array, array($name)));

            json_response(__("Extension enabled."), load_info($folder.DIR.$name.DIR."info.php")["notifications"]);
        case "disable":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to toggle extensions."));

            if (empty($_POST['extension']) or empty($_POST['type']))
                error(__("No Extension Specified"), __("You did not specify an extension to disable."), null, 400);

            $type          = ($_POST['type'] == "module") ? "module" : "feather" ;
            $name          = str_replace(array(".", DIR), "", $_POST['extension']);
            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $folder        = ($type == "module") ? MODULES_DIR : FEATHERS_DIR ;
            $class_name    = camelize($name);

            if (!in_array($name, (array) $config->$enabled_array))
                error(__("Error"), __("Extension already disabled."), null, 409);

            if (!file_exists($folder.DIR.$name.DIR.$name.".php"))
                show_404(__("Not Found"), __("Extension not found."));

            if (method_exists($class_name, "__uninstall"))
                call_user_func(array($class_name, "__uninstall"), !empty($_POST['confirm']));

            $config->set($enabled_array, array_diff((array) $config->$enabled_array, array($name)));

            if ($type == "feather" and isset($_SESSION['latest_feather']) and $_SESSION['latest_feather'] == $name)
                unset($_SESSION['latest_feather']);

            json_response(__("Extension disabled."));
    }

    $trigger->call("ajax");
    $trigger->call("ajax_".$_POST['action']);

    # Serve an error if no responders were found.
    error(__("Error"), __("Invalid action."), null, 400);
