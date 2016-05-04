<?php
    define('AJAX', true);

    require_once "common.php";

    # Prepare the controller.
    $main = MainController::current();

    # Parse the route.
    $route = Route::current($main);

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

            $dir = ($_POST['type'] == "module") ? MODULES_DIR : FEATHERS_DIR ;
            $info = include $dir.DIR.$_POST['extension'].DIR."info.php";
            fallback($info["confirm"], "");

            if (!empty($info["confirm"]))
                echo $info["confirm"];

            exit;

        case "enable":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("toggle_extensions"))
                exit("{ \"notifications\": [\"".__("You do not have sufficient privileges to enable extensions.")."\"] }");

            $type = ($_POST['type'] == "module") ? "module" : "feather" ;

            if (empty($_POST["extension"]))
                exit("{ \"notifications\": [\"".__("You did not specify an extension to enable.")."\"] }");

            $name          = $_POST["extension"];
            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $folder        = ($type == "module") ? MODULES_DIR : FEATHERS_DIR ;
            $class_name    = camelize($name);

            if (!file_exists($folder.DIR.$name.DIR.$name.".php"))
                show_404(__("Not Found"), __("Extension not found."));

            require $folder.DIR.$name.DIR.$name.".php";

            if ($type == "module" and !is_subclass_of($class_name, "Modules"))
                show_404(__("Not Found"), __("Module not found."));

            if ($type == "feather" and !is_subclass_of($class_name, "Feathers"))
                show_404(__("Not Found"), __("Feather not found."));

            if ($type == "module" and (module_enabled($_POST['extension']) or !empty(Modules::$instances[$name]->cancelled)))
                exit("{ \"notifications\": [\"".__("Module already enabled.")."\"] }");

            if ($type == "feather" and feather_enabled($_POST['extension']))
                exit("{ \"notifications\": [\"".__("Feather already enabled.")."\"] }");

            if (file_exists($folder.DIR.$name.DIR."locale".DIR.$config->locale.".mo"))
                load_translator($name, $folder.DIR.$name.DIR."locale".DIR.$config->locale.".mo");

            if (method_exists($class_name, "__install"))
                call_user_func(array($class_name, "__install"));

            $new = $config->$enabled_array;
            $new[] = $name;
            $config->set($enabled_array, $new);

            $info = include $folder.DIR.$name.DIR."info.php";
            fallback($info["uploader"], false);
            fallback($info["notifications"], array());

            if ($info["uploader"])
                if (!file_exists(MAIN_DIR.$config->uploads_path))
                    $info["notifications"][] = _f("Please create the directory <em>%s</em> in your install directory.", array($config->uploads_path));
                elseif (!is_writable(MAIN_DIR.$config->uploads_path))
                    $info["notifications"][] = _f("Please make <em>%s</em> writable by the server.", array($config->uploads_path));

            foreach ($info["notifications"] as &$notification)
                $notification = addslashes(strip_tags($notification));

            exit('{ "notifications": ['.(!empty($info["notifications"]) ? '"'.implode('", "', $info["notifications"]).'"' : "").'] }');

        case "disable":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("toggle_extensions"))
                exit("{ \"notifications\": [\"".__("You do not have sufficient privileges to disable extensions.")."\"] }");

            $type = ($_POST['type'] == "module") ? "module" : "feather" ;

            if (empty($_POST["extension"]))
                exit("{ \"notifications\": [\"".__("You did not specify an extension to disable.")."\"] }");

            $name          = $_POST["extension"];
            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $class_name    = camelize($name);

            if ($type == "module" and !is_subclass_of($class_name, "Modules"))
                show_404(__("Not Found"), __("Module not found."));

            if ($type == "feather" and !is_subclass_of($class_name, "Feathers"))
                show_404(__("Not Found"), __("Feather not found."));

            if ($type == "module" and !module_enabled($name) and empty(Modules::$instances[$name]->cancelled))
                exit("{ \"notifications\": [\"".__("Module already disabled.")."\"] }");

            if ($type == "feather" and feather_enabled($name))
                exit("{ \"notifications\": [\"".__("Feather already disabled.")."\"] }");

            if (method_exists($class_name, "__uninstall"))
                call_user_func(array($class_name, "__uninstall"), ($_POST['confirm'] == "1"));

            $new = array();

            foreach ($config->$enabled_array as $extension) {
              if ($extension != $name)
                $new[] = $extension;
            }

            $config->set($enabled_array, $new);

            if ($type == "feather" and isset($_SESSION['latest_feather']) and $_SESSION['latest_feather'] == $name)
                unset($_SESSION['latest_feather']);

            exit('{ "notifications": [] }');

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
