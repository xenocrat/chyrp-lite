<?php
    require_once "model".DIR."Category.php";

    class Categorize extends Modules {
        # Array: $caches
        # Query caches for methods.
        private $caches = array();

        public static function __install(): void {
            Category::install();

            Group::add_permission("manage_categorize", "Manage Categories");
            Route::current()->add("category/(name)/", "category");
        }

        public static function __uninstall($confirm): void {
            if ($confirm)
                Category::uninstall();

            Group::remove_permission("manage_categorize");
            Route::current()->remove("category/(name)/");
        }

        public function list_permissions($names = array()): array {
            $names["manage_categorize"] = __("Manage Categories", "categorize");
            return $names;
        }

        public function feed_item($post, $feed): void {
            if (!empty($post->category))
                $feed->category(
                    $post->category->clean,
                    url("category", MainController::current()),
                    $post->category->name
                );
        }

        public function related_posts($ids, $post, $limit): array {
            if (empty($post->category_id))
                return $ids;

            $results = SQL::current()->select(
                tables:"post_attributes",
                fields:array("post_id"),
                conds:array(
                    "name" => "category_id",
                    "value" => $post->category_id,
                    "post_id !=" => $post->id
                ),
                order:array("post_id DESC"),
                limit:$limit
            )->fetchAll();

            foreach ($results as $result)
                $ids[] = $result["post_id"];

            return $ids;
        }

        public function parse_urls($urls): array {
            $urls['|/category/([^/]+)/|'] = '/?action=category&amp;name=$1';
            return $urls;
        }

        public function manage_posts_column_header(): string {
            return '<th class="post_category value">'.
                   __("Category", "categorize").'</th>';
        }

        public function manage_posts_column($post): string {
            $td = '<td class="post_category value">';

            if (isset($post->category->name))
                $td.= '<a href="'.
                      url("manage_category/query/".urlencode("id:".$post->category->id)).
                      '">'.
                      $post->category->name.
                      '</a>';

            $td.= '</td>';

            return $td;
        }

        public function post_options($fields, $post = null): array {
            $options[0]["value"] = "0";
            $options[0]["name"] = __("[None]", "categorize");
            $options[0]["selected"] = empty($post->category_id);

            foreach (Category::find() as $category) {
                $name = oneof($category->name, __("[Untitled]"));
                $selected = (isset($post) and ($post->category_id == $category->id));

                $options[$category->id]["value"] = $category->id;
                $options[$category->id]["name"] = $name;
                $options[$category->id]["selected"] = $selected;
            }

            $fields[] = array(
                "attr" => "option[category_id]",
                "label" => __("Category", "categorize"),
                "help" => "categorizing_posts",
                "type" => "select",
                "options" => $options
            );

            return $fields;
        }

        public function post($post): void {
            if (!empty($post->category_id)) {
                $category = new Category($post->category_id);

                if (!$category->no_results)
                    $post->category = $category;
            }
        }

        private function get_category_post_count($category_id): int {
            if (!isset($this->caches["category_post_counts"])) {
                $counts = SQL::current()->select(
                    tables:"post_attributes",
                    fields:array("COUNT(value) AS total", "value AS category_id"),
                    conds:array("name" => "category_id"),
                    group:"value"
                )->fetchAll();

                $this->caches["category_post_counts"] = array();

                foreach ($counts as $count) {
                    $id = $count["category_id"];
                    $total = (int) $count["total"];
                    $this->caches["category_post_counts"][$id] = $total;
                }
            }

            return fallback($this->caches["category_post_counts"][$category_id], 0);
        }

        public function category_post_count_attr($attr, $category): int {
            if ($category->no_results)
                return 0;

            return $this->get_category_post_count($category->id);
        }

        public function twig_context_main($context): array {
            $context["categorize"] = array();

            foreach (Category::find() as $category) {
                if ($category->show_on_home)
                    $context["categorize"][] = $category;
            }

            return $context;
        }

        public function main_category($main): bool {
            if (!isset($_GET['name']))
                Flash::warning(
                    __("You did not specify a category.", "categorize"),
                    "/"
                );

            $category = new Category(
                array("clean" => $_GET['name'])
            );

            if ($category->no_results)
                show_404(
                    __("Not Found"),
                    __("The category you specified was not found.", "categorize")
                );

            $results = SQL::current()->select(
                tables:"post_attributes",
                fields:array("post_id"),
                conds:array(
                    "name" => "category_id",
                    "value" => $category->id
                )
            )->fetchAll();

            $ids = array();

            foreach ($results as $result)
                $ids[] = $result["post_id"];

            if (empty($ids))
                show_404(
                    __("Not Found"),
                    __("There are no posts in the category you specified.", "categorize")
                );

            $posts = new Paginator(
                Post::find(
                    array(
                        "placeholders" => true,
                        "drafts" => true,
                        "where" => array("id" => $ids)
                    )
                ),
                $main->post_limit
            );

            if (!$posts->total)
                return false;

            $main->display(
                array("pages".DIR."category", "pages".DIR."index"),
                array(
                    "posts" => $posts,
                    "category" => $category->name
                ),
                _f("Posts in category &#8220;%s&#8221;", fix($category->name), "categorize")
            );

            return true;
        }

        public function manage_nav($navs): array {
            if (Visitor::current()->group->can("manage_categorize"))
                $navs["manage_category"] = array(
                    "title" => __("Categories", "categorize"),
                    "selected" => array(
                        "new_category",
                        "delete_category",
                        "edit_category"
                    )
                );

            return $navs;
        }

        public function admin_determine_action($action): ?string {
            $visitor = Visitor::current();

            if ($action == "manage" and $visitor->group->can("manage_categorize"))
                return "manage_category";

            return null;
        }

        public function admin_manage_category($admin): void {
            if (!Visitor::current()->group->can("manage_categorize"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to manage categories.", "categorize")
                );

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect(
                    "manage_category/query/".
                    str_ireplace(
                        array("%2F", "%5C"),
                        "%5F",
                        urlencode($_POST['query'])
                    ).
                    "/"
                );

            fallback($_GET['query'], "");
            list($where, $params, $order) = keywords(
                $_GET['query'],
                "name LIKE :query",
                "categorize"
            );

            $categorize = Category::find(
                array(
                    "where" => $where,
                    "params" => $params,
                    "order" => $order
                )
            );

            $admin->display(
                "pages".DIR."manage_category",
                array("categorize" => $categorize)
            );
        }

        public function admin_new_category($admin): void {
            if (!Visitor::current()->group->can("manage_categorize"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to add categories.", "categorize")
                );

            $admin->display("pages".DIR."new_category");
        }

        public function admin_add_category($admin)/*: never */ {
            if (!Visitor::current()->group->can("manage_categorize"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to add categories.", "categorize")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['name']))
                error(
                    __("No Name Specified", "categorize"),
                    __("A name is required to add a category.", "categorize"),
                    code:400
                );

            $clean = empty($_POST['clean']) ?
                $_POST['name'] :
                $_POST['clean'] ;

            $clean = sanitize($clean, true, SLUG_STRICT, 128);

            if (!preg_match("/[^\-0-9]+/", $clean))
                $clean = md5($clean);

            $clean = Category::check_clean($clean);

            Category::add(
                name:$_POST['name'],
                clean:$clean,
                show_on_home:!empty($_POST['show_on_home'])
            );

            Flash::notice(
                __("Category added.", "categorize"),
                "manage_category"
            );
        }

        public function admin_edit_category($admin): void {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to edit a category.", "categorize"),
                    code:400
                );

            $category = new Category($_GET['id']);

            if ($category->no_results)
                show_404(
                    __("Not Found"),
                    __("Category not found.", "categorize")
                );

            if (!$category->editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit this category.", "categorize")
                );

            $admin->display(
                "pages".DIR."edit_category",
                array("category" => $category)
            );
        }

        public function admin_update_category($admin)/*: never */ {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to update a category.", "categorize"),
                    code:400
                );

            if (empty($_POST['name']))
                error(
                    __("No Name Specified", "categorize"),
                    __("A name is required to update a category.", "categorize"),
                    code:400
                );

            $category = new Category($_POST['id']);

            if ($category->no_results)
                show_404(
                    __("Not Found"),
                    __("Category not found.", "categorize")
                );

            if (!$category->editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit this category.", "categorize")
                );

            $clean = empty($_POST['clean']) ?
                $_POST['name'] :
                $_POST['clean'] ;

            if ($clean != $category->clean) {
                $clean = sanitize($clean, true, SLUG_STRICT, 128);

                if (!preg_match("/[^\-0-9]+/", $clean))
                    $clean = md5($clean);

                $clean = Category::check_clean($clean);
            }

            $category = $category->update(
                name:$_POST['name'],
                clean:$clean,
                show_on_home:!empty($_POST['show_on_home'])
            );

            Flash::notice(
                __("Category updated.", "categorize"),
                "manage_category"
            );
        }

        public function admin_delete_category($admin): void {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a category.", "categorize"),
                    code:400
                );

            $category = new Category($_GET['id']);

            if ($category->no_results)
                show_404(
                    __("Not Found"),
                    __("Category not found.", "categorize")
                );

            if (!$category->deletable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete this category.", "categorize")
                );

            $admin->display(
                "pages".DIR."delete_category",
                array("category" => $category)
            );
        }

        public function admin_destroy_category()/*: never */ {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a category.", "categorize"),
                    code:400
                );

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_category");

            $category = new Category($_POST['id']);

            if ($category->no_results)
                show_404(
                    __("Not Found"),
                    __("Category not found.", "categorize")
                );

            if (!$category->deletable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete this category.", "categorize")
                );

            Category::delete($category->id);
            Flash::notice(
                __("Category deleted.", "categorize"),
                "manage_category"
            );
        }
    }
