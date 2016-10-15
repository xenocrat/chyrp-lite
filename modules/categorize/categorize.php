<?php
    require_once "model".DIR."Category.php";

    class Categorize extends Modules {
        public function __init() {
            $this->addAlias("mt_getCategoryList", "xmlrpc_getCategoryList");
            $this->addAlias("mt_getPostCategories", "xmlrpc_getCategoryList");
            $this->addAlias("metaWeblog_getCategories", "xmlrpc_getCategoryList");
        }

        static function __install() {
            Category::install();

            Group::add_permission("manage_categorize", "Manage Categories");
            Route::current()->add("category/(name)/", "category");
        }

        static function __uninstall($confirm) {
            if ($confirm)
                Category::uninstall();

            Group::remove_permission('manage_categorize');
            Route::current()->remove("category/(name)/");
        }

        public function list_permissions($names = array()) {
            $names["manage_categorize"] = __("Manage Categories", "categorize");
            return $names;
        }

        public function feed_item($post) {
            if (!empty($post->category_id) OR $post->category != 0)
               printf("        <category term=\"%s\" />\n", fix(Category::getCategory($post->category_id)->name, true));
        }

        public function metaWeblog_editPost($args, $post) {
            if (empty($args['categories'][0]))
                $category->id = 0;
            else
                $category = Category::getCategoryIDbyName($args['categories'][0]);

            SQL::current()->replace("post_attributes",
                                    array("name" => "category_id",
                                          "value" => $category->id,
                                          "post_id" => $post->id));
        }

        public function metaWeblog_newPost($args, $post) {
            if (empty($args['categories'][0]))
                return;

            $category = Category::getCategoryIDbyName($args['categories'][0]);

            SQL::current()->insert("post_attributes",
                                   array("name" => "category_id",
                                         "value" => $category->id,
                                         "post_id" => $post->id));
        }

        public function xmlrpc_getCategoryList() {
            $categories = Category::getCategoryList();

            foreach($categories as $category)
                $xml_cats[]['title'] = $category['name'];

            return $xml_cats;
        }

        public function related_posts($ids, $post, $limit) {
            if (empty($post->category_id))
                return $ids;

            $results = SQL::current()->select("post_attributes",
                                              array("post_id"),
                                              array("name" => "category_id",
                                                    "value" => $post->category_id,
                                                    "post_id !=" => $post->id),
                                              array("ORDER BY" => "post_id DESC"),
                                              array(),
                                              $limit)->fetchAll();

            foreach ($results as $result)
                if (isset($result["post_id"]))
                    $ids[] = $result["post_id"];

            return $ids;
        }

        public function parse_urls($urls) {
            $urls["|/category/(.*?)/|"] = "/?action=category&name=$1";
            return $urls;
        }

        public function manage_posts_column_header() {
            echo '<th class="post_category value">'.__("Category", "categorize").'</th>';
        }

        public function manage_posts_column($post) {
            echo (!empty($post->category_id) and isset($post->category->name))
                ? '<td class="post_category value">'.fix($post->category->name).'</td>'
                : '<td class="post_category value">&nbsp;</td>';
        }

        public function post_options($fields, $post = null) {
            $categories = Category::getCategoryList();

            $fields_list[0]["value"] = "0";
            $fields_list[0]["name"] = __("[None]", "categorize");

            if (empty($post->category_id))
                $fields_list[0]["selected"] = true;
            else
                $fields_list[0]["selected"] = false;

            if (!empty($categories)) # Make sure we don't try to process an empty list.
                foreach ($categories as $category) {
                    $fields_list[$category["id"]]["value"] = $category["id"];
                    $fields_list[$category["id"]]["name"] = $category["name"];
                    $fields_list[$category["id"]]["selected"] = ($post ? $post->category_id == $category["id"] : false);
                }

            $fields[] = array("attr" => "option[category_id]",
                              "label" => __("Category", "categorize"),
                              "help" => "categorizing_posts",
                              "type" => "select",
                              "options" => $fields_list);

            return $fields;
        }

        public function post($post) {
            if (!empty($post->category_id))
                $post->category = Category::getCategory($post->category_id);
        }

        public function main_context($context) {
            $categories = Category::getCategoryList();
            $context["categorize"] = array();

            foreach ($categories as $category)
                if ($category["show_on_home"])
                    $context["categorize"][] = $category;

            return $context;
        }

        public function main_category($main) {
            if (!isset($_GET['name']))
                return $main->resort(array("pages".DIR."category", "pages".DIR."index"),
                                     array("reason" => __("You did not specify a category.", "categorize")),
                                     __("Invalid Category", "categorize"));

            $category = Category::getCategorybyClean($_GET['name']);

            if (empty($category))
                return $main->resort(array("pages".DIR."category", "pages".DIR."index"),
                                     array("reason" => __("The category you specified was not found.", "categorize")),
                                     __("Invalid Category", "categorize"));

            $attributes = SQL::current()->select("post_attributes",
                                                 array("post_id"),
                                                 array("name" => "category_id",
                                                       "value" => $category->id));

            $ids = array();

            foreach ($attributes->fetchAll() as $index => $row)
                $ids[] = $row["post_id"];

            if (empty($ids))
                return $main->resort(array("pages".DIR."category", "pages".DIR."index"),
                                     array("reason" => __("There are no posts in the category you specified.", "categorize")),
                                     __("Invalid Category", "categorize"));

            $posts = new Paginator(Post::find(array("placeholders" => true,
                                                    "where" => array("id" => $ids))),
                                   Config::current()->posts_per_page);

            if (empty($posts))
                return false;

            $main->display(array("pages".DIR."category", "pages".DIR."index"),
                           array("posts" => $posts, "category" => $category->name),
                           _f("Posts in category %s", fix($category->name), "categorize"));
        }

        public function manage_nav($navs) {
            if (Visitor::current()->group->can('manage_categorize'))
                $navs["manage_category"] = array("title" => __("Categories", "categorize"),
                                                 "selected" => array("new_category", "delete_category", "edit_category"));

            return $navs;
        }

        public function admin_determine_action($action) {
            if ($action == "manage" and Visitor::current()->group->can("manage_categorize"))
                return "manage_category";
        }

        public function admin_manage_category($admin) {
            if (!Visitor::current()->group->can('manage_categorize'))
                show_403(__("Access Denied"), __('You do not have sufficient privileges to manage categories.', 'categorize'));

            fallback($_GET['query'], "");
            list($where, $params) = keywords($_GET['query'], "name LIKE :query", "categorize");

            $admin->display("manage_category", array("categorize" => Category::getCategoryList($where, $params)));
        }

        public function admin_new_category($admin) {
            if (!Visitor::current()->group->can('manage_categorize'))
                show_403(__("Access Denied"), __('You do not have sufficient privileges to manage categories.', 'categorize'));

            $admin->display("new_category");
        }

        public function admin_add_category($admin) {
            if (!Visitor::current()->group->can('manage_categorize'))
                show_403(__("Access Denied"), __('You do not have sufficient privileges to manage categories.', 'categorize'));

            if (empty($_POST['name']))
                error(__("No Name Specified", "categorize"), __("A name is required to add a category.", "categorize"), null, 400);

            Category::addCategory($_POST['name'],
                                  oneof(@$_POST['clean'], $_POST['name']),
                                  !empty($_POST['show_on_home']) ? 1 : 0);

            Flash::notice(__("Category added.", "categorize"), "/?action=manage_category");
        }

        public function admin_edit_category($admin) {
            if (!Visitor::current()->group->can("manage_categorize"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage categories.", "categorize"));

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to edit a category.", "categorize"), null, 400);

            $category = Category::getCategory($_GET['id']);

            if (empty($category))
                Flash::warning(__("Category not found.", "categorize"), "/?action=manage_category");

            $fields["categorize"] = $category;
            $admin->display("edit_category", $fields, "Edit category");
        }

        public function admin_update_category($admin) {
            if (!Visitor::current()->group->can("manage_categorize"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage categories.", "categorize"));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to update a category.", "categorize"), null, 400);

            if (empty($_POST['name']))
                error(__("No Name Specified", "categorize"), __("A name is required to update a category.", "categorize"), null, 400);

            $category = Category::getCategory($_POST['id']);

            if (empty($category))
                show_404(__("Not Found"), __("Category not found.", "categorize"));

            Category::updateCategory($_POST['id'],
                                     $_POST['name'],
                                     oneof(@$_POST['clean'], $_POST['name']),
                                     !empty($_POST['show_on_home']) ? 1 : 0);

            Flash::notice(__("Category updated.", "categorize"), "/?action=manage_category");
        }

        public function admin_delete_category($admin) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a category.", "categorize"), null, 400);

            $category = Category::getCategory($_GET['id']);

            if (empty($category))
                Flash::warning(__("Category not found.", "categorize"), "/?action=manage_category");

            $admin->display("delete_category", array("category" => $category));
        }

        public function admin_destroy_category() {
            if (!Visitor::current()->group->can("manage_categorize"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage categories.", "categorize"));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a category.", "categorize"), null, 400);

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("/?action=manage_category");

            $category = Category::getCategory($_POST['id']);

            if (empty($category))
                show_404(__("Not Found"), __("Category not found.", "categorize"));

            Category::deleteCategory($category->id);
            Flash::notice(__("Category deleted.", "categorize"), "/?action=manage_category");
        }
    }
