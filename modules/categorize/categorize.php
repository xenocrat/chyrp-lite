<?php
    require_once "model".DIR."Category.php";

    class Categorize extends Modules {
        # Array: $caches
        # Query caches for methods.
        private $caches = array();

        public function __init() {
            $this->addAlias("metaWeblog_before_newPost", "metaWeblog_before_editPost");
        }

        static function __install() {
            Category::install();

            Group::add_permission("manage_categorize", "Manage Categories");
            Route::current()->add("category/(name)/", "category");
        }

        static function __uninstall($confirm) {
            if ($confirm)
                Category::uninstall();

            Group::remove_permission("manage_categorize");
            Route::current()->remove("category/(name)/");
        }

        public function list_permissions($names = array()) {
            $names["manage_categorize"] = __("Manage Categories", "categorize");
            return $names;
        }

        public function feed_item($post, $feed) {
            if (!empty($post->category))
                $feed->category($post->category->clean,
                                url("category", MainController::current()), $post->category->name);
        }

        public function metaWeblog_getPost($struct, $post) {
            if (!empty($post->category))
                $struct["categories"] = array($post->category->name);

            return $struct;
        }

        public function metaWeblog_before_editPost($values, $struct) {
            $category_id = 0;

            if (isset($struct["categories"][0])) {
                foreach (Category::find() as $category)
                    if ($category->name == $struct["categories"][0]) {
                        $category_id = $category->id;
                        break;
                    }
            }

            $values['category_id'] = $category_id;
            return $values;
        }

        public function metaWeblog_getCategories($struct) {
            foreach (Category::find() as $category)
                $struct[] = array("categoryId"   => $category->id,
                                  "categoryName" => $category->name,
                                  "htmlUrl"      => $category->url);

            return $struct;
        }

        public function related_posts($ids, $post, $limit) {
            if (empty($post->category_id))
                return $ids;

            if (count($ids) >= $limit)
                return $ids;

            $results = SQL::current()->select("post_attributes",
                                              array("post_id"),
                                              array("name" => "category_id",
                                                    "value" => $post->category_id,
                                                    "post_id !=" => $post->id),
                                              array("post_id DESC"),
                                              array(),
                                              $limit)->fetchAll();

            foreach ($results as $result)
                $ids[] = $result["post_id"];

            return $ids;
        }

        public function parse_urls($urls) {
            $urls['|/category/([^/]+)/|'] = '/?action=category&amp;name=$1';
            return $urls;
        }

        public function manage_posts_column_header() {
            echo '<th class="post_category value">'.__("Category", "categorize").'</th>';
        }

        public function manage_posts_column($post) {
            echo (isset($post->category->name)) ?
                '<td class="post_category value">'.fix($post->category->name).'</td>' :
                '<td class="post_category value"></td>' ;
        }

        public function post_options($fields, $post = null) {
            $options[0]["value"] = "0";
            $options[0]["name"] = __("[None]", "categorize");
            $options[0]["selected"] = empty($post->category_id);

            foreach (Category::find() as $category) {
                $selected = ($post) ? $post->category_id == $category->id : false ;
                $options[$category->id]["value"] = $category->id;
                $options[$category->id]["name"] = oneof($category->name, __("[Untitled]"));
                $options[$category->id]["selected"] = $selected;
            }

            $fields[] = array("attr" => "option[category_id]",
                              "label" => __("Category", "categorize"),
                              "help" => "categorizing_posts",
                              "type" => "select",
                              "options" => $options);

            return $fields;
        }

        public function post($post) {
            if (!empty($post->category_id)) {
                $category = new Category($post->category_id);

                if (!$category->no_results)
                    $post->category = $category;
            }
        }

        private function get_category_post_count($category_id) {
            if (!isset($this->caches["category_post_counts"])) {
                $counts = SQL::current()->select("post_attributes",
                                                 "COUNT(value) AS total, value AS category_id",
                                                 array("name" => "category_id"),
                                                 null,
                                                 array(),
                                                 null,
                                                 null,
                                                 "value")->fetchAll();

                $this->caches["category_post_counts"] = array();

                foreach ($counts as $count)
                    $this->caches["category_post_counts"][$count["category_id"]] = (int) $count["total"];
            }

            return fallback($this->caches["category_post_counts"][$category_id], 0);
        }

        public function category_post_count_attr($attr, $category) {
            if ($category->no_results)
                return 0;

            return $this->get_category_post_count($category->id);
        }

        public function twig_context_main($context) {
            $context["categorize"] = array();

            foreach (Category::find() as $category)
                if ($category->show_on_home)
                    $context["categorize"][] = $category;

            return $context;
        }

        public function main_category($main) {
            if (!isset($_GET['name']))
                return $main->display(array("pages".DIR."category", "pages".DIR."index"),
                    array("reason" => __("You did not specify a category.", "categorize")),
                    __("Invalid Category", "categorize"));

            $category = new Category(array("clean" => $_GET['name']));

            if ($category->no_results)
                return $main->display(array("pages".DIR."category", "pages".DIR."index"),
                    array("reason" => __("The category you specified was not found.", "categorize")),
                    __("Invalid Category", "categorize"));

            $results = SQL::current()->select("post_attributes",
                                              array("post_id"),
                                              array("name" => "category_id",
                                                    "value" => $category->id))->fetchAll();

            $ids = array();

            foreach ($results as $result)
                $ids[] = $result["post_id"];

            if (empty($ids))
                return $main->display(array("pages".DIR."category", "pages".DIR."index"),
                    array("reason" => __("There are no posts in the category you specified.", "categorize")),
                    __("Invalid Category", "categorize"));

            $posts = new Paginator(
                Post::find(array("placeholders" => true,
                                 "where" => array("id" => $ids))), $main->post_limit);

            if (empty($posts))
                return false;

            $main->display(array("pages".DIR."category", "pages".DIR."index"),
                array("posts" => $posts, "category" => $category->name),
                _f("Posts in category &#8220;%s&#8221;", fix($category->name), "categorize"));
        }

        public function manage_nav($navs) {
            if (Visitor::current()->group->can("manage_categorize"))
                $navs["manage_category"] = array("title" => __("Categories", "categorize"),
                                                 "selected" => array("new_category",
                                                                     "delete_category",
                                                                     "edit_category"));

            return $navs;
        }

        public function admin_determine_action($action) {
            if ($action == "manage" and Visitor::current()->group->can("manage_categorize"))
                return "manage_category";
        }

        public function admin_manage_category($admin) {
            if (!Visitor::current()->group->can("manage_categorize"))
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to manage categories.", "categorize"));

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect("manage_category/query/".str_ireplace("%2F", "", urlencode($_POST['query']))."/");

            fallback($_GET['query'], "");
            list($where, $params) = keywords($_GET['query'], "name LIKE :query", "categorize");

            $admin->display("pages".DIR."manage_category",
                            array("categorize" => Category::find(array("where" => $where,
                                                                       "params" => $params))));
        }

        public function admin_new_category($admin) {
            if (!Visitor::current()->group->can("manage_categorize"))
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to add categories.", "categorize"));

            $admin->display("pages".DIR."new_category");
        }

        public function admin_add_category($admin) {
            if (!Visitor::current()->group->can("manage_categorize"))
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to add categories.", "categorize"));

            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['name']))
                error(__("No Name Specified", "categorize"),
                      __("A name is required to add a category.", "categorize"), null, 400);

            $clean = (!empty($_POST['clean'])) ? $_POST['clean'] : $_POST['name'] ;
            $clean = Category::check_clean(sanitize($clean, true, true));

            Category::add($_POST['name'],
                          $clean,
                          !empty($_POST['show_on_home']));

            Flash::notice(__("Category added.", "categorize"), "manage_category");
        }

        public function admin_edit_category($admin) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"),
                      __("An ID is required to edit a category.", "categorize"), null, 400);

            $category = new Category($_GET['id']);

            if ($category->no_results)
                Flash::warning(__("Category not found.", "categorize"), "manage_category");

            if (!$category->editable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to edit this category.", "categorize"));

            $admin->display("pages".DIR."edit_category", array("category" => $category));
        }

        public function admin_update_category($admin) {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"),
                      __("An ID is required to update a category.", "categorize"), null, 400);

            if (empty($_POST['name']))
                error(__("No Name Specified", "categorize"),
                      __("A name is required to update a category.", "categorize"), null, 400);

            $category = new Category($_POST['id']);

            if ($category->no_results)
                show_404(__("Not Found"), __("Category not found.", "categorize"));

            if (!$category->editable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to edit this category.", "categorize"));

            $clean = (!empty($_POST['clean'])) ? $_POST['clean'] : $_POST['name'] ;

            $clean = ($clean != $category->clean) ?
                Category::check_clean(sanitize($clean, true, true)) : $category->clean ;

            $category = $category->update($_POST['name'],
                                          $clean,
                                          !empty($_POST['show_on_home']));

            Flash::notice(__("Category updated.", "categorize"), "manage_category");
        }

        public function admin_delete_category($admin) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"),
                      __("An ID is required to delete a category.", "categorize"), null, 400);

            $category = new Category($_GET['id']);

            if ($category->no_results)
                Flash::warning(__("Category not found.", "categorize"), "manage_category");

            if (!$category->deletable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to delete this category.", "categorize"));

            $admin->display("pages".DIR."delete_category", array("category" => $category));
        }

        public function admin_destroy_category() {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"),
                      __("An ID is required to delete a category.", "categorize"), null, 400);

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_category");

            $category = new Category($_POST['id']);

            if ($category->no_results)
                show_404(__("Not Found"), __("Category not found.", "categorize"));

            if (!$category->deletable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to delete this category.", "categorize"));

            Category::delete($category->id);
            Flash::notice(__("Category deleted.", "categorize"), "manage_category");
        }
    }
