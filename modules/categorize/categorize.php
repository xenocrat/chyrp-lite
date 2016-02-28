<?php
    require_once("model.category.php");
    
    class Categorize extends Modules {
        public function __init() {
            # Overlap all the get category items
            $this->addAlias("mt_getCategoryList", "xmlrpc_getCategoryList");
            $this->addAlias("mt_getPostCategories", "xmlrpc_getCategoryList");
            $this->addAlias("metaWeblog_getCategories", "xmlrpc_getCategoryList");
        }

        static function __install() {
            Category::installCategorize();                                      # Add this string to the .pot file:
            Group::add_permission("manage_categorize", "Manage Categories");    # __("Manage Categories");
            Route::current()->add("category/(name)/", "category");
        }

        static function __uninstall($confirm) {
            if ($confirm)
                Category::uninstallCategorize();

            Group::remove_permission('manage_categorize');
            Route::current()->remove("category/(name)/");
        }

        public function feed_item($post) {
            if (!empty($post->category_id) OR $post->category != 0)
               printf("\t<category term=\"%s\" />\n", Category::getCategory($post->category_id)->name);
        }

        /* XML Stuff */
        public function metaWeblog_editPost($args, $post) {
            if (empty($args['categories'][0])) # if we don't have it, then leave.
                $category->id = 0;
            else
                $category = Category::getCategoryIDbyName($args['categories'][0]);

            SQL::current()->replace("post_attributes",
                              array("name" => "category_id",
                                    "value" => $category->id,
                                    "post_id" => $post->id));
        }

        public function metaWeblog_newPost($args, $post) {
            if (empty($args['categories'][0])) return; # if we don't have it, then leave.
            $category = Category::getCategoryIDbyName($args['categories'][0]);

            SQL::current()->insert("post_attributes",
                             array("name" => "category_id",
                                   "value" => $category->id,
                                   "post_id" => $post->id));
        }

        # return a list of categories to the XMLRPC system.
        public function xmlrpc_getCategoryList() {
            $categories = Category::getCategory();
            foreach($categories as $category) {
                $xml_cats[]['title'] = $category['name'];
            }
            return $xml_cats;
        }
        /* End XML Stuff */

        public function parse_urls($urls) {
            $urls["|/category/(.*?)/|"] = "/?action=category&name=$1";
            return $urls;
        }

        public function manage_posts_column_header() {
            echo '<th class="post_category">'.__("Category", "categorize").'</th>';
        }

        public function manage_posts_column($post) {
            echo (isset($post->category->name) && $post->category->id != false)
                ? '<td class="post_category">'.$post->category->name.'</td>'
                : '<td class="post_category">&nbsp;</td>';
        }

        public function post_options($fields, $post = null) {
            $categories = Category::getCategory();

            $fields_list[0]["value"] = "0";
            $fields_list[0]["name"] = __("[None]", "categorize");

            if (!isset($post->category_id) or $post->category_id == 0)
                $fields_list[0]["selected"] = true;
            else
                $fields_list[0]["selected"] = false;

            if (!empty($categories)) # make sure we don't try to process an empty list.
                foreach ($categories as $category) {
                    $fields_list[$category["id"]]["value"] = $category["id"];
                    $fields_list[$category["id"]]["name"] = $category["name"];
                    $fields_list[$category["id"]]["selected"] = ($post ? $post->category_id == $category["id"] : false);
                }

            $fields[] = array("attr" => "option[category_id]",
                              "label" => __("Category", "categorize"),
                              "type" => "select",
                              "options" => $fields_list
                        );

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
            # make sure we have enough information to continue.
            if (!isset($_GET['name']))
                $reason = __("You did not specify a category.", "categorize");
            elseif (!$category = Category::getCategorybyClean($_GET['name']))
                $reason = __("The category you specified was not found.", "categorize");

            if (isset($reason))
                return $main->resort(array("pages".DIR."category", "pages".DIR."index"),
                                     array("reason" => $reason),
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
                              _f("Posts in category %s", $_GET['name'], "categorize"));
        }

        static function manage_nav($navs) {
            if (!Visitor::current()->group->can('manage_categorize'))
                return $navs;

            $navs["manage_category"] = array("title" => __("Categories", "categorize"),
                                             "selected" => array("new_category", "delete_category", "edit_category"));

            return $navs;
        }

        static function manage_nav_pages($pages) {
            array_push($pages, "manage_category", "new_category", "delete_category", "edit_category");
            return $pages;
        }

        public function admin_manage_category($admin) {
            if (!Visitor::current()->group->can('manage_categorize'))
                show_403(__("Access Denied"), __('You do not have sufficient privileges to manage categories.', 'categorize'));

            $admin->display("manage_category", array("categorize" => Category::getCategory()));
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
                error(__("No Name Specified", "categorize"), __("A name is required to add a category.", "categorize"));

            Category::addCategory($_POST);
            Flash::notice(__("Category added.", "categorize"), "/admin/?action=manage_category");
        }

        public function admin_edit_category($admin) {
            if (!Visitor::current()->group->can("manage_categorize"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage categories.", "categorize"));

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to edit a category.", "categorize"));

            $category = Category::getCategory($_GET['id']);

            if (empty($category))
                Flash::warning(__("Category not found.", "categorize"), "/admin/?action=manage_category");

            $fields["categorize"] = $category;
            $admin->display("edit_category", $fields, "Edit category");
        }

        public function admin_update_category($admin) {
            if (!Visitor::current()->group->can("manage_categorize"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage categories.", "categorize"));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to update a category.", "categorize"));

            if (empty($_POST['name']))
                error(__("No Name Specified", "categorize"), __("A name is required to update a category.", "categorize"));

            $category = Category::getCategory($_POST['id']);

            if (empty($category))
                show_404();

            Category::updateCategory($_POST);
            Flash::notice(__("Category updated.", "categorize"), "/admin/?action=manage_category");
        }

        public function admin_delete_category($admin) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a category.", "categorize"));

            $category = Category::getCategory($_GET['id']);

            if (empty($category))
                Flash::warning(__("Category not found.", "categorize"), "/admin/?action=manage_category");

            $admin->display("delete_category", array("category" => $category));
        }

        public function admin_destroy_category() {
            if (!Visitor::current()->group->can("manage_categorize"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage categories.", "categorize"));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a category.", "categorize"));

            if ($_POST['destroy'] != "indubitably")
                redirect("/admin/?action=manage_category");

            $category = Category::getCategory($_POST['id']);

            if (empty($category))
                show_404();

            Category::deleteCategory($category->id);
            Flash::notice(__("Category deleted.", "categorize"), "/admin/?action=manage_category");
        }
    }
