<?php
    /**
     * Class: AjaxController
     * The logic controlling AJAX requests.
     */
    class AjaxController extends Controllers implements Controller {
        # String: $base
        # The base path for this controller.
        public $base = "ajax";

        # Boolean: $clean
        # Does this controller support clean URLs?
        public $clean_urls = false;

        # Boolean: $feed
        # Serve a syndication feed?
        public $feed = false;

        /**
         * Function: parse
         * Route constructor calls this to determine the action in the case of a POST request.
         */
        public function parse(
            $route
        ): ?string {
            if (
                isset($_SERVER['HTTP_SEC_FETCH_SITE']) and
                $_SERVER['HTTP_SEC_FETCH_SITE'] != "same-origin"
            ) {
                show_403();
            }

            if (empty($route->action) and isset($_POST['action']))
                return $route->action = $_POST['action'];

            if (!isset($route->action))
                error(
                    __("Error"),
                    __("Missing argument."),
                    code:400
                );

            return null;
        }

        /**
         * Function: exempt
         * Route constructor calls this to determine "view_site" exemptions.
         */
        public function exempt(
            $action
        ): bool {
            return false;
        }

        /**
         * Function: ajax_destroy_post
         * Destroys a post.
         */
        public function ajax_destroy_post(
        ): void {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a post."),
                    code:400
                );

            $post = new Post(
                $_POST['id'], array("drafts" => true)
            );

            if ($post->no_results)
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            if (!$post->deletable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete this post.")
                );

            Post::delete($post->id);
            json_response(__("Post deleted."), true);
        }

        /**
         * Function: ajax_destroy_page
         * Destroys a page.
         */
        public function ajax_destroy_page(
        ): void {
            if (!Visitor::current()->group->can("delete_page"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete pages.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a page."),
                    code:400
                );

            $page = new Page($_POST['id']);

            if ($page->no_results)
                show_404(
                    __("Not Found"),
                    __("Page not found.")
                );

            Page::delete($page->id, true);
            json_response(__("Page deleted."), true);
        }

        /**
         * Function: ajax_preview_post
         * Previews a post.
         */
        public function ajax_preview_post(
        ): void {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (
                !Visitor::current()->group->can(
                    "add_post",
                    "add_draft",
                    "edit_post",
                    "edit_draft",
                    "edit_own_post",
                    "edit_own_draft"
                )
            ) {
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to preview content.")
                );
            }

            $trigger = Trigger::current();
            $main = MainController::current();

            if (!isset($_POST['field']) or !isset($_POST['context'])) {
                error(
                    __("Error"),
                    __("Missing argument."),
                    code:400
                );
            }

            preg_match_all(
                "/(?<=^|;)([a-z0-9_]+):([a-z0-9_]+)(?:;|$)/i",
                $_POST['context'],
                $matches,
                PREG_SET_ORDER
            );

            $content = fallback($_POST['content'], "");
            $context = array();

            foreach ($matches as $match)
                $context[$match[1]] = $match[2];

            if (!isset($context["feather"]))
                error(
                    __("Error"),
                    __("Missing argument."),
                    code:400
                );

            if (!isset(Feathers::$instances[$context["feather"]]))
                show_404(
                    __("Not Found"),
                    __("Feather not found.")
                );

            $field = Feathers::$instances
                     [$context["feather"]]->fields[$_POST['field']] ?? array() ;

            # Custom filters.
            if (isset($field["custom_filters"])) {
                foreach ($field["custom_filters"] as $custom_filter) {
                    $content = call_user_func_array(
                        array(
                            Feathers::$instances[$context["feather"]],
                            $custom_filter
                        ),
                        array($content)
                    );
                }
            }

            # Trigger filters.
            if (isset($field["filters"])) {
                foreach ($field["filters"] as $filter)
                    $trigger->filter($content, $filter);
            }

            header("Cache-Control: no-store");

            $main->display(
                "content".DIR."preview",
                array("content" => $content),
                __("Preview")
            );
        }

        /**
         * Function: ajax_preview_page
         * Previews a page.
         */
        public function ajax_preview_page(
        ): void {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (!Visitor::current()->group->can("add_page", "edit_page"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to preview content.")
                );

            $trigger = Trigger::current();
            $main = MainController::current();

            if (!isset($_POST['field']))
                error(
                    __("Error"),
                    __("Missing argument."),
                    code:400
                );

            $field = $_POST['field'];
            $content = fallback($_POST['content'], "");

            switch ($field) {
                case "title":
                    $trigger->filter($content, array("markup_page_title", "markup_title"));
                    break;

                case "body":
                    $trigger->filter($content, array("markup_page_text", "markup_text"));
                    break;
            }

            header("Cache-Control: no-store");

            $main->display(
                "content".DIR."preview",
                array("content" => $content),
                __("Preview")
            );
        }

        /**
         * Function: ajax_file_upload
         * Moves a file to the uploads directory.
         */
        public function ajax_file_upload(
        ): void {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (!Visitor::current()->group->can("add_upload"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to add uploads.")
                );

            if (!isset($_FILES['file']))
                error(
                    __("Error"),
                    __("Missing argument."),
                    code:400
                );

            $config = Config::current();


            if (upload_tester($_FILES['file'])) {
                $filename = upload($_FILES['file']);
                $filepath = uploaded($filename, false);
                $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                switch ($filetype) {
                    case "avif":
                    case "bmp":
                    case "gif":
                    case "heif":
                    case "jpe":
                    case "jpeg":
                    case "jpg":
                    case "png":
                    case "pnj":
                    case "tif":
                    case "tiff":
                    case "webp":
                        $href = $config->chyrp_url.
                                "/includes/thumbnail.php?file=".
                                urlencode($filename);

                        break;

                    case "3g2":
                    case "3gp":
                    case "aac":
                    case "aif":
                    case "aifc":
                    case "aiff":
                    case "avi":
                    case "flac":
                    case "m2ts":
                    case "m4a":
                    case "m4v":
                    case "mka":
                    case "mkv":
                    case "mov":
                    case "mp2":
                    case "mp3":
                    case "mp4":
                    case "mpeg":
                    case "mpg":
                    case "oga":
                    case "ogg":
                    case "ogv":
                    case "ts":
                    case "wav":
                    case "webm":
                        $href = uploaded($filename);
                        break;

                    default:
                        $href = $config->chyrp_url.
                                "/includes/download.php?file=".
                                urlencode($filename);
                }

                json_response(
                    __("File uploaded."),
                    array(
                        "href" => $href,
                        "name" => $filename,
                        "type" => $filetype,
                        "size" => filesize($filepath)
                    )
                );
            }
        }

        public function ajax_uploads_modal(
        ): void {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (!Visitor::current()->group->can("view_upload"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to view uploads.")
                );

            $search = fallback($_POST['search'], "");
            $filter = fallback($_POST['filter'], "");
            $sort = fallback($_SESSION['uploads_sort'], "name");

            $extensions = array();
            $exploded = explode(",", $filter);

            foreach ($exploded as $value) {
                $value = trim($value, " .");

                if ($value != "")
                    $extensions[] = $value;
            }

            $uploads = uploaded_search(
                search:$search,
                filter:$extensions,
                sort:$sort
            );

            $admin = AdminController::current();
            $admin->display(
                "partials".DIR."uploads_modal",
                array("uploads" => $uploads)
            );
        }

        /**
         * Function: current
         * Returns a singleton reference to the current class.
         */
        public static function & current(
        ): self {
            static $instance = null;

            if (empty($instance))
                $instance = new self();

            return $instance;
        }
    }
