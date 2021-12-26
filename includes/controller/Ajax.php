<?php
    /**
     * Class: AjaxController
     * The logic controlling AJAX requests.
     */
    class AjaxController implements Controller {
        # Boolean: $clean
        # Does this controller support clean URLs?
        public $clean = false;

        # Boolean: $feed
        # Is the current page a feed?
        public $feed = false;

        # String: $base
        # The base path for this controller.
        public $base = "ajax";

        /**
         * Function: parse
         * Route constructor calls this to determine the action in the case of a POST request.
         */
        public function parse($route) {
            if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) and $_SERVER['HTTP_SEC_FETCH_SITE'] != "same-origin")
                show_403();

            if (empty($route->action) and isset($_POST['action']))
                return $route->action = $_POST['action'];

            if (!isset($route->action))
                error(__("Error"), __("Missing argument."), null, 400);
        }

        /**
         * Function: exempt
         * Route constructor calls this to determine "view_site" exemptions.
         */
        public function exempt($action) {
            return false;
        }

        /**
         * Function: ajax_destroy_post
         * Destroys a post.
         */
        public function ajax_destroy_post() {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a post."), null, 400);

            $post = new Post($_POST['id'], array("drafts" => true));

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            if (!$post->deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this post."));

            Post::delete($post->id);
            json_response(__("Post deleted."), true);
        }

        /**
         * Function: ajax_destroy_page
         * Destroys a page.
         */
        public function ajax_destroy_page() {
            if (!Visitor::current()->group->can("delete_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete pages."));

            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a page."), null, 400);

            $page = new Page($_POST['id']);

            if ($page->no_results)
                show_404(__("Not Found"), __("Page not found."));

            Page::delete($page->id, true);
            json_response(__("Page deleted."), true);
        }

        /**
         * Function: ajax_preview_post
         * Previews a post.
         */
        public function ajax_preview_post() {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (!Visitor::current()->group->can("add_post", "add_draft"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to add posts."));

            $trigger = Trigger::current();
            $main = MainController::current();

            $class = camelize(fallback($_POST['safename'], "text"));
            $field = fallback($_POST['field'], "body");
            $content = fallback($_POST['content'], "");

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
        }

        /**
         * Function: ajax_preview_page
         * Previews a page.
         */
        public function ajax_preview_page() {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (!Visitor::current()->group->can("add_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to add pages."));

            $trigger = Trigger::current();
            $main = MainController::current();

            $field = fallback($_POST['field'], "body");
            $content = fallback($_POST['content'], "");

            # Page title filters.
            if ($field == "title")
                $trigger->filter($content, array("markup_page_title", "markup_title"));

            # Page body filters.
            if ($field == "body")
                $trigger->filter($content, array("markup_page_text", "markup_text"));

            header("Cache-Control: no-cache, must-revalidate");
            header("Expires: Mon, 03 Jun 1991 05:30:00 GMT");

            $main->display("content".DIR."preview", array("content" => $content), __("Preview"));
        }

        /**
         * Function: ajax_file_upload
         * Moves a file to the uploads directory.
         */
        public function ajax_file_upload() {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (!Visitor::current()->group->can("add_post", "add_page"))
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to import files."));

            if (!isset($_FILES['file']))
                error(__("Error"), __("Missing argument."), null, 400);

            if (upload_tester($_FILES['file'])) {
                $filename = upload($_FILES['file']);
                $url = Config::current()->chyrp_url.
                       "/includes/thumbnail.php?file=".urlencode($filename);

                $data = array("file" => $filename, "url" => $url);
                json_response(__("File uploaded."), $data);
            }
        }

        /**
         * Function: current
         * Returns a singleton reference to the current class.
         */
        public static function & current(): self {
            static $instance = null;
            $instance = (empty($instance)) ? new self() : $instance ;
            return $instance;
        }
    }
