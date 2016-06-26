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
            if (!$visitor->group->can("add_post", "add_draft", "add_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to create content."));

            if (empty($_POST['filter']))
                error(__("No Filter Specified"), __("A filter is required to preview content."), null, 400);

            fallback($_POST['content'], "Lorem ipsum dolor sit amet.");

            header("Cache-Control: no-cache, must-revalidate");
            header("Expires: Mon, 03 Jun 1991 05:30:00 GMT");

            $sanitized = sanitize_html($_POST['content']);

            Trigger::current()->filter($sanitized, $_POST['filter']);

            $main->display("content".DIR."preview", array("content" => $sanitized,
                                                          "filter" => $_POST['filter']), __("Preview"));
            exit;
        case "confirm":
            if (!$visitor->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to toggle extensions."));

            if (empty($_POST['extension']) or substr_count($_POST['extension'], DIR))
                show_404(__("Not Found"), __("Extension not found."));

            $dir = ($_POST['type'] == "module") ? MODULES_DIR : FEATHERS_DIR ;
            $info = include $dir.DIR.$_POST['extension'].DIR."info.php";
            fallback($info["confirm"]);
            json_response(oneof($info["confirm"], __("Confirmation is not necessary.")), !empty($info["confirm"]));
        case "enable":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to enable extensions."));

            if (empty($_POST['extension']) or empty($_POST['type']))
                error(__("No Extension Specified"), __("You did not specify an extension to enable."), null, 400);

            $type          = ($_POST['type'] == "module") ? "module" : "feather" ;
            $name          = $_POST['extension'];
            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $updated_array = $config->$enabled_array;
            $folder        = ($type == "module") ? MODULES_DIR : FEATHERS_DIR ;
            $class_name    = camelize($name);

            # We don't use the module_enabled() helper function because we want to include cancelled modules.
            if ($type == "module" and !empty(Modules::$instances[$name]))
                error(__("Error"), __("Module already enabled."), null, 409);

            # We don't use the feather_enabled() helper function because we want to include cancelled feathers.
            if ($type == "feather" and !empty(Feathers::$instances[$name]))
                error(__("Error"), __("Feather already enabled."), null, 409);

            if (!file_exists($folder.DIR.$name.DIR.$name.".php"))
                show_404(__("Not Found"), __("Extension not found."));

            require $folder.DIR.$name.DIR.$name.".php";

            if (!is_subclass_of($class_name, camelize(pluralize($type))))
                show_404(__("Not Found"), __("Extension not found."));

            load_translator($name, $folder.DIR.$name.DIR."locale".DIR.$config->locale.".mo");

            if (method_exists($class_name, "__install"))
                call_user_func(array($class_name, "__install"));

            if (!in_array($name, $updated_array))
                $updated_array[] = $name;

            $config->set($enabled_array, $updated_array);

            $info = include $folder.DIR.$name.DIR."info.php";
            fallback($info["uploader"], false);
            fallback($info["notifications"], array());

            if ($info["uploader"])
                if (!file_exists(MAIN_DIR.$config->uploads_path))
                    $info["notifications"][] = _f("Please create the directory <em>%s</em> in your install directory.", array($config->uploads_path));
                elseif (!is_writable(MAIN_DIR.$config->uploads_path))
                    $info["notifications"][] = _f("Please make <em>%s</em> writable by the server.", array($config->uploads_path));

            json_response(__("Extension enabled."), $info["notifications"]);
        case "disable":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to disable extensions."));

            if (empty($_POST['extension']) or empty($_POST['type']))
                error(__("No Extension Specified"), __("You did not specify an extension to disable."), null, 400);

            $type          = ($_POST['type'] == "module") ? "module" : "feather" ;
            $name          = $_POST['extension'];
            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $updated_array = array();
            $class_name    = camelize($name);

            # We don't use the module_enabled() helper function because we want to exclude cancelled modules.
            if ($type == "module" and empty(Modules::$instances[$name]))
                error(__("Error"), __("Module already disabled."), null, 409);

            # We don't use the feather_enabled() helper function because we want to exclude cancelled feathers.
            if ($type == "feather" and empty(Feathers::$instances[$name]))
                error(__("Error"), __("Feather already disabled."), null, 409);

            if ($type == "module" and !is_subclass_of($class_name, "Modules"))
                show_404(__("Not Found"), __("Module not found."));

            if ($type == "feather" and !is_subclass_of($class_name, "Feathers"))
                show_404(__("Not Found"), __("Feather not found."));

            if (method_exists($class_name, "__uninstall"))
                call_user_func(array($class_name, "__uninstall"), !empty($_POST['confirm']));

            foreach ($config->$enabled_array as $extension)
                if ($extension != $name)
                    $updated_array[] = $extension;

            $config->set($enabled_array, $updated_array);

            if ($type == "feather" and isset($_SESSION['latest_feather']) and $_SESSION['latest_feather'] == $name)
                unset($_SESSION['latest_feather']);

            json_response(__("Extension disabled."));
    }

    $trigger->call("ajax");
    $trigger->call("ajax_".$_POST['action']);

    # Serve an error if no responders were found.
    error(__("Error"), __("Invalid action."), null, 400);
