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

    switch($_POST['action']) {
        case "edit_post":
            if (!isset($_POST['id']))
                error(__("No ID Specified"), __("Please specify an ID of the post you would like to edit."));

            $post = new Post($_POST['id'], array("filter" => false, "drafts" => true));

            if ($post->no_results) {
                header("HTTP/1.1 404 Not Found");
                $trigger->call("not_found");
                exit;
            }

            if (!$post->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit posts."));

            $title = $post->title();
            $theme_file = THEME_DIR."/forms/feathers/".$post->feather.".php";
            $default_file = FEATHERS_DIR."/".$post->feather."/fields.php";

            $options = array();
            Trigger::current()->filter($options, array("edit_post_options", "post_options"), $post);

            $main->display("forms/post/edit", array("post" => $post,
                                                    "feather" => Feathers::$instances[$post->feather],
                                                    "options" => $options,
                                                    "groups" => Group::find(array("order" => "id ASC"))));
            break;

        case "delete_post":
            $post = new Post($_POST['id'], array("drafts" => true));

            if ($post->no_results) {
                header("HTTP/1.1 404 Not Found");
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

            if (isset($_POST['id']))
                $post = new Post($_POST['id'], array("drafts" => true));

            if ($post->no_results) {
                header("HTTP/1.1 404 Not Found");
                $trigger->call("not_found");
                exit;
            }

            $main->display("feathers/".$post->feather, array("post" => $post, "ajax_reason" => $reason));
            break;

        case "check_confirm":
            if (!$visitor->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable extensions."));

            $dir = ($_POST['type'] == "module") ? MODULES_DIR : FEATHERS_DIR ;
            $info = YAML::load($dir."/".$_POST['check']."/info.yaml");
            fallback($info["confirm"], "");

            if (!empty($info["confirm"]))
                echo __($info["confirm"], $_POST['check']);

            break;

        case "organize_pages":
            foreach ($_POST['parent'] as $id => $parent)
                $sql->update("pages", array("id" => $id), array("parent_id" => $parent));

            foreach ($_POST['page_list'] as $index => $page)
                $sql->update("pages", array("id" => $page), array("list_order" => $index));

            break;

        case "enable_module": case "enable_feather":
            $type = ($_POST['action'] == "enable_module") ? "module" : "feather" ;

            if (!$visitor->group->can("change_settings"))
                if ($type == "module")
                    exit("{ \"notifications\": [\"".__("You do not have sufficient privileges to enable/disable modules.")."\"] }");
                else
                    exit("{ \"notifications\": [\"".__("You do not have sufficient privileges to enable/disable feathers.")."\"] }");

            if (($type == "module" and module_enabled($_POST['extension'])) or
                ($type == "feather" and feather_enabled($_POST['extension'])))
                exit("{ \"notifications\": [] }");

            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $folder        = ($type == "module") ? MODULES_DIR : FEATHERS_DIR ;

            if (file_exists($folder."/".$_POST["extension"]."/locale/".$config->locale.".mo"))
                load_translator($_POST["extension"], $folder."/".$_POST["extension"]."/locale/".$config->locale.".mo");

            $info = YAML::load($folder."/".$_POST["extension"]."/info.yaml");
            fallback($info["uploader"], false);
            fallback($info["notifications"], array());

            foreach ($info["notifications"] as &$notification)
                $notification = addslashes(__($notification, $_POST["extension"]));

            require $folder."/".$_POST["extension"]."/".$_POST["extension"].".php";

            if ($info["uploader"])
                if (!file_exists(MAIN_DIR.$config->uploads_path))
                    $info["notifications"][] = _f("Please create the <code>%s</code> directory at your Chyrp install's root and CHMOD it to 777.", array($config->uploads_path));
                elseif (!is_writable(MAIN_DIR.$config->uploads_path))
                    $info["notifications"][] = _f("Please CHMOD <code>%s</code> to 777.", array($config->uploads_path));

            $class_name = camelize($_POST["extension"]);

            if ($type == "module" and !is_subclass_of($class_name, "Modules"))
                error("", __("Item is not a module."));

            if ($type == "feather" and !is_subclass_of($class_name, "Feathers"))
                error("", __("Item is not a feather."));

            if (method_exists($class_name, "__install"))
                call_user_func(array($class_name, "__install"));

            $new = $config->$enabled_array;
            array_push($new, $_POST["extension"]);
            $config->set($enabled_array, $new);

            exit('{ "notifications": ['.
                 (!empty($info["notifications"]) ? '"'.implode('", "', $info["notifications"]).'"' : "").
                 '] }');

            break;

        case "disable_module": case "disable_feather":
            $type = ($_POST['action'] == "disable_module") ? "module" : "feather" ;

            if (!$visitor->group->can("change_settings"))
                if ($type == "module")
                    exit("{ \"notifications\": [\"".__("You do not have sufficient privileges to enable/disable modules.")."\"] }");
                else
                    exit("{ \"notifications\": [\"".__("You do not have sufficient privileges to enable/disable feathers.")."\"] }");

            if (($type == "module" and !module_enabled($_POST['extension'])) or
                ($type == "feather" and !feather_enabled($_POST['extension'])))
                exit("{ \"notifications\": [] }");

            $class_name = camelize($_POST["extension"]);
            if (method_exists($class_name, "__uninstall"))
                call_user_func(array($class_name, "__uninstall"), ($_POST['confirm'] == "1"));

            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $config->set($enabled_array,
                         array_diff($config->$enabled_array, array($_POST['extension'])));

            exit('{ "notifications": [] }');

            break;

        case "reorder_feathers":
            $reorder = oneof(@$_POST['list'], $config->enabled_feathers);
            foreach ($reorder as &$value)
                $value = preg_replace("/feathers\[([^\]]+)\]/", "\\1", $value);

            $config->set("enabled_feathers", $reorder);
            break;
    }

    $trigger->call("ajax");

    if (!empty($_POST['action']))
        $trigger->call("ajax_".$_POST['action']);
