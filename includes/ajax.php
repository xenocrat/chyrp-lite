<?php
    define('AJAX', true);

    require_once "common.php";

    # Prepare the controller.
    $main = MainController::current();

    # Parse the route.
    $route = Route::current($main);

    if (isset($_SERVER['REQUEST_METHOD']) and $_SERVER['REQUEST_METHOD'] !== "POST")
        error(__("Error"), __("This resource accepts POST requests only."), null, 405);

    if (empty($_POST['action']))
        error(__("Error"), __("Missing argument."), null, 400);

    if (!$visitor->group->can("view_site"))
        show_403(__("Access Denied"), __("You are not allowed to view this site."));

    switch($_POST['action']) {
        case "destroy_post":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
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
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
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
        case "preview_post":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("add_post", "add_draft"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to add posts."));

            $trigger = Trigger::current();

            $class = camelize(fallback($_POST['safename'], "text"));
            $field = fallback($_POST['field'], "body");
            $content = sanitize_html(fallback($_POST['content'], ""));

            # Custom filters.
            if (isset(Feathers::$custom_filters[$class]))
                foreach (Feathers::$custom_filters[$class] as $custom_filter)
                    if ($custom_filter["field"] == $field)
                        $content = call_user_func_array(array(Feathers::$instances[$_POST['safename']],
                                                              $custom_filter["name"]),
                                                        array($content));

            # Trigger filters.
            if (isset(Feathers::$filters[$class]))
                foreach (Feathers::$filters[$class] as $filter)
                    if ($filter["field"] == $field and !empty($content))
                        $trigger->filter($content, $filter["name"]);

            header("Cache-Control: no-cache, must-revalidate");
            header("Expires: Mon, 03 Jun 1991 05:30:00 GMT");

            $main->display("content".DIR."preview", array("content" => $content), __("Preview"));
            exit;
        case "preview_page":
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("add_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to add pages."));

            $trigger = Trigger::current();

            $field = fallback($_POST['field'], "body");
            $content = sanitize_html(fallback($_POST['content'], ""));

            # Page title filters.
            if ($field == "title")
                $trigger->filter($content, array("markup_title", "markup_page_title"));

            # Page body filters.
            if ($field == "body")
                $trigger->filter($content, array("markup_text", "markup_page_text"));

            header("Cache-Control: no-cache, must-revalidate");
            header("Expires: Mon, 03 Jun 1991 05:30:00 GMT");

            $main->display("content".DIR."preview", array("content" => $content), __("Preview"));
            exit;
    }

    $trigger->call("ajax");
    $trigger->call("ajax_".$_POST['action']);

    # Serve an error if no responders were found.
    error(__("Error"), __("Invalid action."), null, 400);
