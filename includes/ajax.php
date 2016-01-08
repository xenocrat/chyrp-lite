<?php
    define('AJAX', true);

    require_once "common.php";

    # Prepare the controller.
    $main = MainController::current();

    # Parse the route.
    $route = Route::current($main);

    if (!$visitor->group->can("view_site"))
        if ($trigger->exists("can_not_view_site"))
            $trigger->call("can_not_view_site");
        else
            show_403(__("Access Denied"), __("You are not allowed to view this site."));

    if (empty($_POST['action']))
        error("Missing Argument", "You must specify an action.");

    switch($_POST['action']) {
        case "edit_post":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to edit a post."));

            $post = new Post($_POST['id'], array("filter" => false, "drafts" => true));

            if ($post->no_results) {
                header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
                $trigger->call("not_found");
                exit;
            }

            if (!$post->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit posts."));

            $options = array();
            Trigger::current()->filter($options, array("edit_post_options", "post_options"), $post);

            $main->display("forms".DIR."post".DIR."edit", array("post" => $post,
                                                                "feather" => Feathers::$instances[$post->feather],
                                                                "options" => $options,
                                                                "groups" => Group::find(array("order" => "id ASC"))));
            break;

        case "delete_post":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a post."));

            $post = new Post($_POST['id'], array("drafts" => true));

            if ($post->no_results) {
                header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
                $trigger->call("not_found");
                exit;
            }

            if (!$post->deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this post."));

            Post::delete($_POST['id']);
            break;

        case "view_post":
            fallback($_POST['offset'], 0);
            fallback($_POST['context']);

            $reason = (isset($_POST['reason'])) ? $_POST['reason'] : "" ;

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to view a post."));

            $post = new Post($_POST['id'], array("drafts" => true));

            if ($post->no_results) {
                header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
                $trigger->call("not_found");
                exit;
            }

            $main->display("feathers".DIR.$post->feather, array("post" => $post, "ajax_reason" => $reason));
            break;

        case "preview":
            if (!logged_in())
                show_403(__("Access Denied"), __("You must be logged in to preview content."));

            if (!isset($_POST['content']) or !isset($_POST['filter']))
                break;

            $sanitized = sanitize_html($_POST['content']);
            Trigger::current()->filter($sanitized, $_POST['filter']);
            $admin = AdminController::current();
            $admin->display("standalone", array("body" => $sanitized), __("Preview"));
            break;

        case "check_confirm":
            if (!$visitor->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable extensions."));

            $dir = ($_POST['type'] == "module") ? MODULES_DIR : FEATHERS_DIR ;
            $info = include $dir.DIR.$_POST['check'].DIR."info.php";
            fallback($info["confirm"], "");

            if (!empty($info["confirm"]))
                echo $info["confirm"];

            break;

        case "enable_module": case "enable_feather":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            $type = ($_POST['action'] == "enable_module") ? "module" : "feather" ;

            if (!$visitor->group->can("toggle_extensions"))
                exit("{ \"notifications\": [\"".__("You do not have sufficient privileges to enable extensions.")."\"] }");

            if (empty($_POST["extension"]))
                exit("{ \"notifications\": [\"".__("You did not specify an extension to enable.")."\"] }");

            if (($type == "module" and module_enabled($_POST['extension'])) or
                ($type == "feather" and feather_enabled($_POST['extension'])))
                exit("{ \"notifications\": [] }");

            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $folder        = ($type == "module") ? MODULES_DIR : FEATHERS_DIR ;

            if (file_exists($folder.DIR.$_POST["extension"].DIR."locale".DIR.$config->locale.".mo"))
                load_translator($_POST["extension"], $folder.DIR.$_POST["extension"].DIR."locale".DIR.$config->locale.".mo");

            $info = include $folder.DIR.$_POST["extension"].DIR."info.php";
            fallback($info["uploader"], false);
            fallback($info["notifications"], array());

            foreach ($info["notifications"] as &$notification)
                $notification = addslashes($notification);

            if (!empty(Modules::$instances[$_POST["extension"]]->cancelled))
                error(__("Module Cancelled"), __("The module has cancelled execution because of an error."));

            require $folder.DIR.$_POST["extension"].DIR.$_POST["extension"].".php";

            if ($info["uploader"])
                if (!file_exists(MAIN_DIR.$config->uploads_path))
                    $info["notifications"][] = _f("Please create the directory <em>%s</em> in your install directory.", array($config->uploads_path));
                elseif (!is_writable(MAIN_DIR.$config->uploads_path))
                    $info["notifications"][] = _f("Please make <em>%s</em> writable by the server.", array($config->uploads_path));

            $class_name = camelize($_POST["extension"]);

            if ($type == "module" and !is_subclass_of($class_name, "Modules"))
                error(__("Error"), __("Item is not a module."));

            if ($type == "feather" and !is_subclass_of($class_name, "Feathers"))
                error(__("Error"), __("Item is not a feather."));

            if (method_exists($class_name, "__install"))
                call_user_func(array($class_name, "__install"));

            $new = $config->$enabled_array;
            $new[] = $_POST["extension"];
            $config->set($enabled_array, $new);

            exit('{ "notifications": ['.
                 (!empty($info["notifications"]) ? '"'.implode('", "', $info["notifications"]).'"' : "").
                 '] }');

        case "disable_module": case "disable_feather":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            $type = ($_POST['action'] == "disable_module") ? "module" : "feather" ;

            if (!$visitor->group->can("toggle_extensions"))
                if ($type == "module")
                exit("{ \"notifications\": [\"".__("You do not have sufficient privileges to disable extensions.")."\"] }");

            if (empty($_POST["extension"]))
                exit("{ \"notifications\": [\"".__("You did not specify an extension to disable.")."\"] }");

            if (($type == "module" and !module_enabled($_POST['extension'])) or
                ($type == "feather" and !feather_enabled($_POST['extension'])))
                exit("{ \"notifications\": [] }");

            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;

            $class_name = camelize($_POST["extension"]);

            if (method_exists($class_name, "__uninstall"))
                call_user_func(array($class_name, "__uninstall"), ($_POST['confirm'] == "1"));

            $new = array();

            foreach ($config->$enabled_array as $extension) {
              if ($extension != $_POST['extension'])
                $new[] = $extension;
            }

            $config->set($enabled_array, $new);

            exit('{ "notifications": [] }');

        case "reorder_feathers":
            if (!$visitor->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to reorder feathers."));

            $reorder = oneof(@$_POST['list'], $config->enabled_feathers);
            foreach ($reorder as &$value)
                $value = preg_replace("/feathers\[([^\]]+)\]/", "\\1", $value);

            foreach ($config->enabled_feathers as $feather)
                if (!in_array($feather, $reorder))
                    exit; # Attempt to disable feather.

            foreach ($reorder as $feather)
                if (!in_array($feather, $config->enabled_feathers))
                    exit; # Attempt to enable feather.

            $config->set("enabled_feathers", $reorder);
            break;
    }

    $trigger->call("ajax");

    if (!empty($_POST['action']))
        $trigger->call("ajax_".$_POST['action']);
