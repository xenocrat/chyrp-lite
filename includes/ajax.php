<?php
    define('AJAX', true);

    require_once "common.php";

    # Prepare the controller.
    $main = MainController::current();

    # Parse the route.
    $route = Route::current($main);

    if (isset($_SERVER["REQUEST_METHOD"]) and $_SERVER["REQUEST_METHOD"] !== "POST") {
        header($_SERVER["SERVER_PROTOCOL"]." 405 Method Not Allowed");
        exit("Invalid Method.");
    }

    if (empty($_POST['action'])) {
        header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request");
        exit("Missing Argument.");
    }

    if (!$visitor->group->can("view_site"))
        show_403(__("Access Denied"), __("You are not allowed to view this site."));

    switch($_POST['action']) {
        case "destroy_post":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a post."));

            $post = new Post($_POST['id'], array("drafts" => true));

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            if (!$post->deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this post."));

            Post::delete($post->id);
            exit;

        case "destroy_page":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a page."));

            $page = new Page($_POST['id']);

            if ($page->no_results)
                show_404(__("Not Found"), __("Page not found."));

            if (!Visitor::current()->group->can("delete_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete pages."));

            Page::delete($page->id, true);
            exit;

        case "preview":
            if (!logged_in())
                show_403(__("Access Denied"), __("You must be logged in to preview content."));

            if (!isset($_POST['content']) or !isset($_POST['filter']))
                exit;

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

            if (!empty($info["confirm"]))
                echo $info["confirm"];

            exit;

        case "enable":
            header("Content-type: application/json; charset=UTF-8");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("toggle_extensions"))
                exit(json_encode(array("notifications" => array(__("You do not have sufficient privileges to enable extensions.")))));

            if (empty($_POST['extension']) or empty($_POST['type']))
                exit(json_encode(array("notifications" => array(__("You did not specify an extension to enable.")))));

            $type          = ($_POST['type'] == "module") ? "module" : "feather" ;
            $name          = $_POST['extension'];
            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $updated_array = $config->$enabled_array;
            $folder        = ($type == "module") ? MODULES_DIR : FEATHERS_DIR ;
            $class_name    = camelize($name);

            if ($type == "module" and !empty(Modules::$instances[$name]))
                exit(json_encode(array("notifications" => array(__("Module already enabled.")))));

            if ($type == "feather" and !empty(Feathers::$instances[$name]))
                exit(json_encode(array("notifications" => array(__("Feather already enabled.")))));

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

            exit(json_encode(array("notifications" => $info["notifications"])));

        case "disable":
            header("Content-type: application/json; charset=UTF-8");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("toggle_extensions"))
                exit(json_encode(array("notifications" => array(__("You do not have sufficient privileges to disable extensions.")))));

            if (empty($_POST['extension']) or empty($_POST['type']))
                exit(json_encode(array("notifications" => array(__("You did not specify an extension to disable.")))));

            $type          = ($_POST['type'] == "module") ? "module" : "feather" ;
            $name          = $_POST['extension'];
            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $updated_array = array();
            $class_name    = camelize($name);

            if ($type == "module" and empty(Modules::$instances[$name]))
                exit(json_encode(array("notifications" => array(__("Module already disabled.")))));

            if ($type == "feather" and empty(Feathers::$instances[$name]))
                exit(json_encode(array("notifications" => array(__("Feather already disabled.")))));

            if ($type == "module" and !is_subclass_of($class_name, "Modules"))
                show_404(__("Not Found"), __("Module not found."));

            if ($type == "feather" and !is_subclass_of($class_name, "Feathers"))
                show_404(__("Not Found"), __("Feather not found."));

            if (method_exists($class_name, "__uninstall"))
                call_user_func(array($class_name, "__uninstall"), ($_POST['confirm'] == "1"));

            foreach ($config->$enabled_array as $extension) {
                if ($extension != $name)
                    $updated_array[] = $extension;
            }

            $config->set($enabled_array, $updated_array);

            if ($type == "feather" and isset($_SESSION['latest_feather']) and $_SESSION['latest_feather'] == $name)
                unset($_SESSION['latest_feather']);

            exit(json_encode(array("notifications" => array())));

        case "latest_feather":
            if (empty($_POST['feather']) or !in_array($_POST['feather'], $config->enabled_feathers))
                show_404(__("Not Found"), __("Feather not found."));

            $_SESSION['latest_feather'] = $_POST['feather'];
            exit;
    }

    $trigger->call("ajax");
    $trigger->call("ajax_".$_POST['action']);

    # Serve an error if no responders were found.
    header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request");
    exit("Invalid Action.");
