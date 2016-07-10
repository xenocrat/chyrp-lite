<?php
    /**
     * Class: Admin Controller
     * The logic controlling the administration console.
     */
    class AdminController {
        # Boolean: $displayed
        # Has anything been displayed?
        public $displayed = false;

        # Array: $context
        # Contains the context for various admin pages, to be passed to the Twig templates.
        public $context = array();

        # String: $base
        # The base path for this controller.
        public $base = "admin";

        # Boolean: $feed
        # Is the current page a feed?
        public $feed = false;

        /**
         * Function: __construct
         * Loads the Twig parser and sets up the l10n domain.
         */
        private function __construct() {
            $config = Config::current();

            $cache = (is_writable(CACHES_DIR.DIR."twig") and (!DEBUG or CACHE_TWIG)) ?
                CACHES_DIR.DIR."twig" : false ;

            $loaders = array(new Twig_Loader_Filesystem(MAIN_DIR.DIR."admin"));

            foreach ($config->enabled_modules as $module)
                if (file_exists(MODULES_DIR.DIR.$module.DIR."admin"))
                    $loaders[] = new Twig_Loader_Filesystem(MODULES_DIR.DIR.$module.DIR."admin");

            foreach ($config->enabled_feathers as $feather)
                if (file_exists(FEATHERS_DIR.DIR.$feather.DIR."admin"))
                    $loaders[] = new Twig_Loader_Filesystem(FEATHERS_DIR.DIR.$feather.DIR."admin");

            $loader = new Twig_Loader_Chain($loaders);
            $this->twig = new Twig_Environment($loader, array("debug" => DEBUG,
                                                              "strict_variables" => DEBUG,
                                                              "charset" => "UTF-8",
                                                              "cache" => $cache,
                                                              "autoescape" => false));
            $this->twig->addExtension(new Leaf());
            $this->twig->registerUndefinedFunctionCallback("twig_callback_missing_function");
            $this->twig->registerUndefinedFilterCallback("twig_callback_missing_filter");

            # Load the theme translator.
            load_translator("admin", MAIN_DIR.DIR."admin".DIR."locale".DIR.$config->locale.".mo");
        }

        /**
         * Function: parse
         * Determines the action.
         */
        public function parse($route) {
            $visitor = Visitor::current();
            $config = Config::current();

            # Protect non-responder functions.
            if (in_array($route->action, array("__construct", "parse", "subnav_context", "display", "current")))
                show_404();

            if (empty($route->action) or $route->action == "write") {
                # "Write > Post", if they can add posts or drafts and at least one feather is enabled.
                if (!empty($config->enabled_feathers) and ($visitor->group->can("add_post") or
                                                           $visitor->group->can("add_draft")))
                    return $route->action = "write_post";

                # "Write > Page", if they can add pages.
                if ($visitor->group->can("add_page"))
                    return $route->action = "write_page";
            }

            if (empty($route->action) or $route->action == "manage") {
                # "Manage > Posts", if they can manage any posts.
                if (Post::any_editable() or Post::any_deletable())
                    return $route->action = "manage_posts";

                # "Manage > Pages", if they can manage pages.
                if ($visitor->group->can("edit_page") or $visitor->group->can("delete_page"))
                    return $route->action = "manage_pages";

                # "Manage > Users", if they can manage users.
                if ($visitor->group->can("edit_user") or $visitor->group->can("delete_user"))
                    return $route->action = "manage_users";

                # "Manage > Groups", if they can manage groups.
                if ($visitor->group->can("edit_group") or $visitor->group->can("delete_group"))
                    return $route->action = "manage_groups";
            }

            if (empty($route->action) or $route->action == "settings") {
                # "General Settings", if they can configure the installation.
                if ($visitor->group->can("change_settings"))
                    return $route->action = "general_settings";
            }

            if (empty($route->action) or $route->action == "extend") {
                # "Modules", if they can can enable/disable extensions.
                if ($visitor->group->can("toggle_extensions"))
                    return $route->action = "modules";
            }

            Trigger::current()->filter($route->action, "admin_determine_action");

            if (!isset($route->action))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to view this area."));
        }

        /**
         * Function: write
         * Post writing.
         */
        public function write_post() {
            if (!Visitor::current()->group->can("add_post", "add_draft"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to create posts."));

            $config = Config::current();

            if (empty($config->enabled_feathers))
                Flash::notice(__("You must enable at least one feather in order to write a post."), "/admin/?action=feathers");

            fallback($_GET['feather'], @$_SESSION['latest_feather'], reset($config->enabled_feathers));

            if (!feather_enabled($_GET['feather']))
                show_404(__("Not Found"), __("Feather not found."));

            $_SESSION['latest_feather'] = $_GET['feather'];

            Trigger::current()->filter($options, array("write_post_options", "post_options"));

            $this->display("write_post",
                           array("groups" => Group::find(array("order" => "id ASC")),
                                 "options" => $options,
                                 "feathers" => Feathers::$instances,
                                 "feather" => Feathers::$instances[$_GET['feather']]));
        }

        /**
         * Function: add_post
         * Adds a post when the form is submitted.
         */
        public function add_post() {
            $visitor = Visitor::current();

            if (!$visitor->group->can("add_post", "add_draft"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to create posts."));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!feather_enabled($_POST['feather']))
                show_404(__("Not Found"), __("Feather not found."));

            if (!isset($_POST['draft']) and !$visitor->group->can("add_post"))
                $_POST['draft'] = 'true';

            $post = Feathers::$instances[$_POST['feather']]->submit();

            if (!$post->redirect)
                $post->redirect = "/admin/?action=write_post";

            redirect($post->redirect);
        }

        /**
         * Function: edit_post
         * Post editing.
         */
        public function edit_post() {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to edit a post."), null, 400);

            $post = new Post($_GET['id'], array("drafts" => true, "filter" => false));

            if ($post->no_results)
                Flash::warning(__("Post not found."), "/admin/?action=manage_posts");

            if (!$post->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this post."));

            Trigger::current()->filter($options, array("edit_post_options", "post_options"), $post);

            $this->display("edit_post",
                           array("post" => $post,
                                 "groups" => Group::find(array("order" => "id ASC")),
                                 "options" => $options,
                                 "feather" => Feathers::$instances[$post->feather]));
        }

        /**
         * Function: update_post
         * Updates a post when the form is submitted.
         */
        public function update_post() {
            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to update a post."), null, 400);

            if (isset($_POST['publish']))
                $_POST['status'] = "public";

            $post = new Post($_POST['id'], array("drafts" => true));

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            if (!$post->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this post."));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            Feathers::$instances[$post->feather]->update($post);

            Flash::notice(__("Post updated.").' <a href="'.$post->url().'">'.__("View post &rarr;").'</a>',
                          "/admin/?action=manage_posts");
        }

        /**
         * Function: delete_post
         * Post deletion (confirm page).
         */
        public function delete_post() {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a post."), null, 400);

            $post = new Post($_GET['id'], array("drafts" => true));

            if ($post->no_results)
                Flash::warning(__("Post not found."), "/admin/?action=manage_posts");

            if (!$post->deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this post."));

            $this->display("delete_post", array("post" => $post));
        }

        /**
         * Function: destroy_post
         * Destroys a post (the real deal).
         */
        public function destroy_post() {
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a post."), null, 400);

            if ($_POST['destroy'] != "indubitably")
                redirect("/admin/?action=manage_posts");

            $post = new Post($_POST['id'], array("drafts" => true));

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            if (!$post->deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this post."));

            Post::delete($post->id);

            Flash::notice(__("Post deleted."), "/admin/?action=manage_posts");
        }

        /**
         * Function: manage_posts
         * Post managing.
         */
        public function manage_posts() {
            if (!Post::any_editable() and !Post::any_deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage any posts."));

            fallback($_GET['query'], "");

            list($where, $params) = keywords($_GET['query'], "post_attributes.value LIKE :query OR url LIKE :query", "posts");

            if (!empty($_GET['month']))
                $where["created_at like"] = $_GET['month']."-%";

            $visitor = Visitor::current();

            if (!$visitor->group->can("view_draft", "edit_draft", "edit_post", "delete_draft", "delete_post"))
                $where["user_id"] = $visitor->id;

            $results = Post::find(array("placeholders" => true,
                                        "drafts" => true,
                                        "where" => $where,
                                        "params" => $params));

            $ids = array();

            foreach ($results[0] as $result)
                $ids[] = $result["id"];

            if (!empty($ids))
                $posts = new Paginator(Post::find(array("placeholders" => true,
                                                        "drafts" => true,
                                                        "where" => array("id" => $ids))),
                                       Config::current()->admin_per_page);
            else
                $posts = new Paginator(array());

            foreach ($posts->paginated as &$post) {
                if (preg_match_all("/\{([0-9]+)\}/", $post->status, $matches)) {
                    $groups = array();
                    $groupClasses = array();

                    foreach ($matches[1] as $id) {
                        $group = new Group($id);
                        $groups[] = "<span class=\"group_prefix\">Group:</span> ".$group->name;
                        $groupClasses[] = "group-".$id;
                    }

                    $post->status_name = join(", ", $groups);
                    $post->status_class = join(" ", $groupClasses);
                } else {
                    $post->status_name = camelize($post->status, true);
                    $post->status_class = $post->status;
                }
            }

            $this->display("manage_posts", array("posts" => $posts));
        }

        /**
         * Function: write_page
         * Page creation.
         */
        public function write_page() {
            if (!Visitor::current()->group->can("add_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to create pages."));

            $this->display("write_page", array("pages" => Page::find()));
        }

        /**
         * Function: add_page
         * Adds a page when the form is submitted.
         */
        public function add_page() {
            if (!Visitor::current()->group->can("add_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to create pages."));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['title']) and empty($_POST['slug']))
                error(__("Error"), __("Title and slug cannot be blank."), null, 422);

            fallback($_POST['status'], "public");

            $public = in_array($_POST['status'], array("listed", "public"));
            $listed = in_array($_POST['status'], array("listed", "teased"));
            $list_order = empty($_POST['list_order']) ? (int) $_POST['list_priority'] : (int) $_POST['list_order'] ;

            $page = Page::add($_POST['title'],
                              $_POST['body'],
                              null,
                              $_POST['parent_id'],
                              $public,
                              $listed,
                              $list_order,
                              (!empty($_POST['slug']) ? $_POST['slug'] : sanitize($_POST['title'])));

            Flash::notice(__("Page created!"), $page->url());
        }

        /**
         * Function: edit_page
         * Page editing.
         */
        public function edit_page() {
            if (!Visitor::current()->group->can("edit_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this page."));

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to edit a page."), null, 400);

            $page = new Page($_GET['id'], array("filter" => false));

            if ($page->no_results)
                Flash::warning(__("Page not found."), "/admin/?action=manage_pages");

            $this->display("edit_page",
                           array("page" => $page,
                                 "pages" => Page::find(array("where" => array("id not" => $_GET['id'])))));
        }

        /**
         * Function: update_page
         * Updates a page when the form is submitted.
         */
        public function update_page() {
            if (!Visitor::current()->group->can("edit_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit pages."));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['title']) and empty($_POST['slug']))
                error(__("Error"), __("Title and slug cannot be blank."), null, 422);

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to edit a page."), null, 400);

            $page = new Page($_POST['id']);

            if ($page->no_results)
                show_404(__("Not Found"), __("Page not found."));

            fallback($_POST['status'], "public");

            $public = in_array($_POST['status'], array("listed", "public"));
            $listed = in_array($_POST['status'], array("listed", "teased"));
            $list_order = empty($_POST['list_order']) ? (int) $_POST['list_priority'] : (int) $_POST['list_order'] ;

            $page->update($_POST['title'],
                          $_POST['body'],
                          null,
                          $_POST['parent_id'],
                          $public,
                          $listed,
                          $list_order,
                          null,
                          (!empty($_POST['slug']) ? $_POST['slug'] : sanitize($_POST['title'])));

            Flash::notice(__("Page updated.").' <a href="'.$page->url().'">'.__("View page &rarr;").'</a>',
                          "/admin/?action=manage_pages");
        }

        /**
         * Function: delete_page
         * Page deletion (confirm page).
         */
        public function delete_page() {
            if (!Visitor::current()->group->can("delete_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete pages."));

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a page."), null, 400);

            $page = new Page($_GET['id']);

            if ($page->no_results)
                Flash::warning(__("Page not found."), "/admin/?action=manage_pages");

            $this->display("delete_page", array("page" => $page));
        }

        /**
         * Function: destroy_page
         * Destroys a page.
         */
        public function destroy_page() {
            if (!Visitor::current()->group->can("delete_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete pages."));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a page."), null, 400);

            if ($_POST['destroy'] != "indubitably")
                redirect("/admin/?action=manage_pages");

            $page = new Page($_POST['id']);

            if ($page->no_results)
                show_404(__("Not Found"), __("Page not found."));

            foreach ($page->children as $child)
                if (isset($_POST['destroy_children']))
                    Page::delete($child->id, true);
                else
                    $child->update($child->title,
                                   $child->body,
                                   null,
                                   0,
                                   $child->public,
                                   $child->show_in_list,
                                   $child->list_order,
                                   null,
                                   $child->url);

            Page::delete($page->id);

            Flash::notice(__("Page deleted."), "/admin/?action=manage_pages");
        }

        /**
         * Function: manage_pages
         * Page managing.
         */
        public function manage_pages() {
            $visitor = Visitor::current();

            if (!$visitor->group->can("edit_page") and !$visitor->group->can("delete_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage pages."));

            fallback($_GET['query'], "");
            list($where, $params) = keywords($_GET['query'], "title LIKE :query OR body LIKE :query", "pages");

            $this->display("manage_pages",
                           array("pages" => new Paginator(Page::find(array("placeholders" => true,
                                                                           "where" => $where,
                                                                           "params" => $params)),
                                                          Config::current()->admin_per_page)));
        }

        /**
         * Function: new_user
         * User creation.
         */
        public function new_user() {
            if (!Visitor::current()->group->can("add_user"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to add users."));

            $config = Config::current();

            $this->display("new_user",
                           array("default_group" => new Group($config->default_group),
                                 "groups" => Group::find(array("where" => array("id not" => array($config->guest_group,
                                                                                                  $config->default_group)),
                                                               "order" => "id DESC"))));
        }

        /**
         * Function: add_user
         * Add a user when the form is submitted.
         */
        public function add_user() {
            if (!Visitor::current()->group->can("add_user"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to add users."));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['login']))
                error(__("Error"), __("Please enter a username for the account."), null, 422);

            $check = new User(array("login" => $_POST['login']));

            if (!$check->no_results)
                error(__("Error"), __("That username is already in use."), null, 409);

            if (empty($_POST['password1']))
                error(__("Error"), __("Password cannot be blank."), null, 422);
            elseif ($_POST['password1'] != $_POST['password2'])
                error(__("Error"), __("Passwords do not match."), null, 422);
            elseif (password_strength($_POST['password1']) < 100)
                Flash::message(__("Please consider setting a stronger password for this user."));

            if (empty($_POST['email']))
                error(__("Error"), __("Email address cannot be blank."), null, 422);
            elseif (!is_email($_POST['email']))
                error(__("Error"), __("Invalid email address."), null, 422);

            if (!empty($_POST['website']) and !is_url($_POST['website']))
                error(__("Error"), __("Invalid website URL."), null, 422);

            if (!empty($_POST['website']))
                $_POST['website'] = add_scheme($_POST['website']);

            $config = Config::current();

            if ($config->email_activation) {
                $user = User::add($_POST['login'],
                                  $_POST['password1'],
                                  $_POST['email'],
                                  $_POST['full_name'],
                                  $_POST['website'],
                                  $_POST['group'],
                                  false);

                correspond("activate", array("login" => $user->login,
                                             "to"    => $user->email,
                                             "link"  => $config->url."/?action=activate&login=".fix($user->login).
                                                        "&token=".token(array($user->login, $user->email))));

                Flash::notice(_f("User &#8220;%s&#8221; added and activation email sent.", $user->login), "/admin/?action=manage_users");
            } else {
                $user = User::add($_POST['login'],
                                  $_POST['password1'],
                                  $_POST['email'],
                                  $_POST['full_name'],
                                  $_POST['website'],
                                  $_POST['group']);

              Flash::notice(_f("User &#8220;%s&#8221; added.", $user->login), "/admin/?action=manage_users");
            }
        }

        /**
         * Function: edit_user
         * User editing.
         */
        public function edit_user() {
            if (!Visitor::current()->group->can("edit_user"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this user."));

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to edit a user."), null, 400);

            $user = new User($_GET['id']);

            if ($user->no_results)
                Flash::warning(__("User not found."), "/admin/?action=manage_users");

            $this->display("edit_user",
                           array("user" => $user,
                                 "groups" => Group::find(array("order" => "id ASC",
                                                               "where" => array("id not" => Config::current()->guest_group)))));
        }

        /**
         * Function: update_user
         * Updates a user when the form is submitted.
         */
        public function update_user() {
            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to edit a user."), null, 400);

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            $visitor = Visitor::current();
            $config = Config::current();

            if (!$visitor->group->can("edit_user"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit users."));

            $check_name = new User(null, array("where" => array("login" => $_POST['login'],
                                                                "id not" => $_POST['id'])));

            if (!$check_name->no_results)
                error(__("Error"), __("That username is already in use."), null, 409);

            $user = new User($_POST['id']);

            if ($user->no_results)
                show_404(__("Not Found"), __("User not found."));

            if (!empty($_POST['new_password1']) and $_POST['new_password1'] != $_POST['new_password2'])
                error(__("Error"), __("Passwords do not match."), null, 422);
            elseif (!empty($_POST['new_password1']) and password_strength($_POST['new_password1']) < 100)
                Flash::message(__("Please consider setting a stronger password for this user."));

            $password = (!empty($_POST['new_password1'])) ? User::hashPassword($_POST['new_password1']) : $user->password ;

            if (empty($_POST['email']))
                error(__("Error"), __("Email address cannot be blank."), null, 422);
            elseif (!is_email($_POST['email']))
                error(__("Error"), __("Invalid email address."), null, 422);

            if (!empty($_POST['website']) and !is_url($_POST['website']))
                error(__("Error"), __("Invalid website URL."), null, 422);

            if (!empty($_POST['website']))
                $_POST['website'] = add_scheme($_POST['website']);

            $user->update($_POST['login'],
                          $password,
                          $_POST['email'],
                          $_POST['full_name'],
                          $_POST['website'],
                          $_POST['group']);

            if ($_POST['id'] == $visitor->id)
                $_SESSION['password'] = $password;

            if (!$user->approved and $config->email_activation)
                correspond("activate", array("login" => $user->login,
                                             "to"    => $user->email,
                                             "link"  => $config->url."/?action=activate&login=".fix($user->login).
                                                        "&token=".token(array($user->login, $user->email))));

            Flash::notice(__("User updated."), "/admin/?action=manage_users");
        }

        /**
         * Function: delete_user
         * User deletion.
         */
        public function delete_user() {
            if (!Visitor::current()->group->can("delete_user"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete users."));

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a user."), null, 400);

            $this->display("delete_user",
                           array("user" => new User($_GET['id']),
                                 "users" => User::find(array("where" => array("id not" => $_GET['id'])))));
        }

        /**
         * Function: destroy_user
         * Destroys a user.
         */
        public function destroy_user() {
            if (!Visitor::current()->group->can("delete_user"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete users."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a user."), null, 400);

            if ($_POST['destroy'] != "indubitably")
                redirect("/admin/?action=manage_users");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            $sql = SQL::current();
            $user = new User($_POST['id']);

            if (isset($_POST['posts'])) {
                if ($_POST['posts'] == "delete")
                    foreach ($user->post as $post)
                        Post::delete($post->id);
                elseif ($_POST['posts'] == "move")
                    $sql->update("posts",
                                 array("user_id" => $user->id),
                                 array("user_id" => $_POST['move_posts']));
            }

            if (isset($_POST['pages'])) {
                if ($_POST['pages'] == "delete")
                    foreach ($user->page as $page)
                        Page::delete($page->id);
                elseif ($_POST['pages'] == "move")
                    $sql->update("pages",
                                 array("user_id" => $user->id),
                                 array("user_id" => $_POST['move_pages']));
            }

            User::delete($_POST['id']);

            Flash::notice(__("User deleted."), "/admin/?action=manage_users");
        }

        /**
         * Function: manage_users
         * User managing.
         */
        public function manage_users() {
            $visitor = Visitor::current();

            if (!$visitor->group->can("edit_user") and !$visitor->group->can("delete_user") and !$visitor->group->can("add_user"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage users."));

            fallback($_GET['query'], "");
            list($where, $params) = keywords($_GET['query'], "login LIKE :query OR full_name LIKE :query OR email LIKE :query OR website LIKE :query", "users");

            $this->display("manage_users",
                           array("users" => new Paginator(User::find(array("placeholders" => true,
                                                                           "where" => $where,
                                                                           "params" => $params)),
                                                          Config::current()->admin_per_page)));
        }

        /**
         * Function: new_group
         * Group creation.
         */
        public function new_group() {
            if (!Visitor::current()->group->can("add_group"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to create groups."));

            $this->display("new_group",
                           array("permissions" => SQL::current()->select("permissions", "*", array("group_id" => 0))->fetchAll()));
        }

        /**
         * Function: add_group
         * Adds a group when the form is submitted.
         */
        public function add_group() {
            if (!Visitor::current()->group->can("add_group"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to create groups."));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            fallback($_POST['permissions'], array());

            $check = new Group(null, array("where" => array("name" => $_POST['name'])));

            if (!$check->no_results)
                error(__("Error"), __("That group name is already in use."), null, 409);

            Group::add($_POST['name'], array_keys($_POST['permissions']));

            Flash::notice(__("Group added."), "/admin/?action=manage_groups");
        }

        /**
         * Function: edit_group
         * Group editing.
         */
        public function edit_group() {
            if (!Visitor::current()->group->can("edit_group"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit groups."));

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to edit a group."), null, 400);

            $group = new Group($_GET['id']);

            if ($group->no_results)
                Flash::warning(__("Group not found."), "/admin/?action=manage_groups");

            $this->display("edit_group",
                           array("group" => $group,
                                 "permissions" => SQL::current()->select("permissions", "*", array("group_id" => 0))->fetchAll()));
        }

        /**
         * Function: update_group
         * Updates a group when the form is submitted.
         */
        public function update_group() {
            if (!Visitor::current()->group->can("edit_group"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit groups."));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            fallback($_POST['permissions'], array());

            $check_name = new Group(null, array("where" => array("name" => $_POST['name'],
                                                                 "id not" => $_POST['id'])));

            if (!$check_name->no_results)
                error(__("Error"), __("That group name is already in use."), null, 409);

            $group = new Group($_POST['id']);

            if ($group->no_results)
                show_404(__("Not Found"), __("Group not found."));

            $group->update($_POST['name'], array_keys($_POST['permissions']));

            Flash::notice(__("Group updated."), "/admin/?action=manage_groups");
        }

        /**
         * Function: delete_group
         * Group deletion (confirm page).
         */
        public function delete_group() {
            if (!Visitor::current()->group->can("delete_group"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete groups."));

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a group."), null, 400);

            $this->display("delete_group",
                           array("group" => new Group($_GET['id']),
                                 "groups" => Group::find(array("where" => array("id not" => $_GET['id']),
                                                               "order" => "id ASC"))));
        }

        /**
         * Function: destroy_group
         * Destroys a group.
         */
        public function destroy_group() {
            if (!Visitor::current()->group->can("delete_group"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete groups."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a group."), null, 400);

            if ($_POST['destroy'] != "indubitably")
                redirect("/admin/?action=manage_groups");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            $group = new Group($_POST['id']);

            foreach ($group->users as $user)
                $user->update($user->login, $user->password, $user->email, $user->full_name, $user->website, $_POST['move_group']);

            $config = Config::current();

            if (!empty($_POST['default_group']))
                $config->set("default_group", $_POST['default_group']);
            if (!empty($_POST['guest_group']))
                $config->set("guest_group", $_POST['guest_group']);

            Group::delete($_POST['id']);

            Flash::notice(__("Group deleted."), "/admin/?action=manage_groups");
        }

        /**
         * Function: manage_groups
         * Group managing.
         */
        public function manage_groups() {
            $visitor = Visitor::current();

            if (!$visitor->group->can("edit_group") and !$visitor->group->can("delete_group") and !$visitor->group->can("add_group"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage groups."));

            if (!empty($_GET['search'])) {
                $user = new User(array("login" => $_GET['search']));

                if (!$user->no_results)
                    $groups = new Paginator(array($user->group));
                else
                    $groups = new Paginator(array());
            } else
                $groups = new Paginator(Group::find(array("placeholders" => true, "order" => "id ASC")),
                                        Config::current()->admin_per_page);

            $this->display("manage_groups",
                           array("groups" => $groups));
        }

        /**
         * Function: export
         * Export posts, pages, etc.
         */
        public function export() {
            if (!Visitor::current()->group->can("add_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to export content."));

            if (empty($_POST)) {
                if (!class_exists("ZipArchive"))
                    Flash::message(__("Multiple exports are not possible because the ZipArchive extension is not available."));

                return $this->display("export");
            }

            $config = Config::current();
            $trigger = Trigger::current();
            $route = Route::current();
            $exports = array();

            if (isset($_POST['posts'])) {
                list($where, $params) = keywords($_POST['filter_posts'], "post_attributes.value LIKE :query OR url LIKE :query", "post_attributes");

                if (!empty($_GET['month']))
                    $where["created_at like"] = $_GET['month']."-%";

                $visitor = Visitor::current();

                if (!$visitor->group->can("view_draft", "edit_draft", "edit_post", "delete_draft", "delete_post"))
                    $where["user_id"] = $visitor->id;

                $results = Post::find(array("placeholders" => true,
                                            "drafts" => true,
                                            "where" => $where,
                                            "params" => $params));

                $ids = array();
                foreach ($results[0] as $result)
                    $ids[] = $result["id"];

                if (!empty($ids))
                    $posts = Post::find(array("drafts" => true,
                                              "where" => array("id" => $ids),
                                              "order" => "id ASC"),
                                        array("filter" => false));
                else
                    $posts = new Paginator(array());

                $latest_timestamp = 0;

                foreach ($posts as $post)
                    if (strtotime($post->created_at) > $latest_timestamp)
                        $latest_timestamp = strtotime($post->created_at);

                $id = substr(strstr($config->url, "//"), 2);
                $id = str_replace("#", "/", $id);
                $id = preg_replace("/(".preg_quote(parse_url($config->url, PHP_URL_HOST)).")/", "\\1,".date("Y", $latest_timestamp).":", $id, 1);

                $posts_atom = '<?xml version="1.0" encoding="UTF-8"?>'."\r";
                $posts_atom.= '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:chyrp="http://chyrp.net/export/1.0/">'."\r";
                $posts_atom.= '    <title>'.fix($config->name).' Posts</title>'."\r";
                $posts_atom.= '    <subtitle>'.fix($config->description).'</subtitle>'."\r";
                $posts_atom.= '    <id>tag:'.parse_url($config->url, PHP_URL_HOST).','.date("Y", $latest_timestamp).':Chyrp</id>'."\r";
                $posts_atom.= '    <updated>'.date("c", $latest_timestamp).'</updated>'."\r";
                $posts_atom.= '    <link href="'.$config->url.'" rel="self" type="application/atom+xml" />'."\r";
                $posts_atom.= '    <generator uri="http://chyrp.net/" version="'.CHYRP_VERSION.'">Chyrp</generator>'."\r";

                foreach ($posts as $post) {
                    $title = fix($post->title(), false);
                    fallback($title, ucfirst($post->feather)." Post #".$post->id);

                    $updated = ($post->updated) ? $post->updated_at : $post->created_at ;

                    $tagged = substr(strstr(url("id/".$post->id), "//"), 2);
                    $tagged = str_replace("#", "/", $tagged);
                    $tagged = preg_replace("/(".preg_quote(parse_url($post->url(), PHP_URL_HOST)).")/", "\\1,".when("Y-m-d", $updated).":", $tagged, 1);

                    $url = $post->url();
                    $posts_atom.= '    <entry xml:base="'.fix($url).'">'."\r";
                    $posts_atom.= '        <title type="html">'.$title.'</title>'."\r";
                    $posts_atom.= '        <id>tag:'.$tagged.'</id>'."\r";
                    $posts_atom.= '        <updated>'.when("c", $updated).'</updated>'."\r";
                    $posts_atom.= '        <published>'.when("c", $post->created_at).'</published>'."\r";
                    $posts_atom.= '        <link href="'.fix($trigger->filter($url, "post_export_url", $post)).'" />'."\r";
                    $posts_atom.= '        <author chyrp:user_id="'.$post->user_id.'">'."\r";
                    $posts_atom.= '            <name>'.fix(oneof($post->user->full_name, $post->user->login)).'</name>'."\r";

                    if (!empty($post->user->website))
                        $posts_atom.= '            <uri>'.fix($post->user->website).'</uri>'."\r";

                    $posts_atom.= '            <chyrp:login>'.fix($post->user->login).'</chyrp:login>'."\r";
                    $posts_atom.= '        </author>'."\r";
                    $posts_atom.= '        <content>'."\r";

                    foreach ($post->attributes as $key => $val)
                        $posts_atom.= '            <'.$key.'>'.fix($val).'</'.$key.'>'."\r";

                    $posts_atom.= '        </content>'."\r";

                    foreach (array("feather", "clean", "url", "pinned", "status") as $attr)
                        $posts_atom.= '        <chyrp:'.$attr.'>'.fix($post->$attr).'</chyrp:'.$attr.'>'."\r";

                    $trigger->filter($posts_atom, "posts_export", $post);

                    $posts_atom.= '    </entry>'."\r";

                }
                $posts_atom.= '</feed>'."\r";

                $exports["posts.atom"] = $posts_atom;
            }

            if (isset($_POST['pages'])) {
                list($where, $params) = keywords($_POST['filter_pages'], "title LIKE :query OR body LIKE :query", "pages");

                $pages = Page::find(array("where" => $where, "params" => $params, "order" => "id ASC"),
                                    array("filter" => false));

                $latest_timestamp = 0;

                foreach ($pages as $page)
                    if (strtotime($page->created_at) > $latest_timestamp)
                        $latest_timestamp = strtotime($page->created_at);

                $pages_atom = '<?xml version="1.0" encoding="UTF-8"?>'."\r";
                $pages_atom.= '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:chyrp="http://chyrp.net/export/1.0/">'."\r";
                $pages_atom.= '    <title>'.fix($config->name).' Pages</title>'."\r";
                $pages_atom.= '    <subtitle>'.fix($config->description).'</subtitle>'."\r";
                $pages_atom.= '    <id>tag:'.parse_url($config->url, PHP_URL_HOST).','.date("Y", $latest_timestamp).':Chyrp</id>'."\r";
                $pages_atom.= '    <updated>'.date("c", $latest_timestamp).'</updated>'."\r";
                $pages_atom.= '    <link href="'.$config->url.'" rel="self" type="application/atom+xml" />'."\r";
                $pages_atom.= '    <generator uri="http://chyrp.net/" version="'.CHYRP_VERSION.'">Chyrp</generator>'."\r";

                foreach ($pages as $page) {
                    $updated = ($page->updated) ? $page->updated_at : $page->created_at ;

                    $tagged = substr(strstr($page->url(), "//"), 2);
                    $tagged = str_replace("#", "/", $tagged);
                    $tagged = preg_replace("/(".preg_quote(parse_url($page->url(), PHP_URL_HOST)).")/", "\\1,".when("Y-m-d", $updated).":", $tagged, 1);

                    $url = $page->url();
                    $pages_atom.= '    <entry xml:base="'.fix($url).'" chyrp:parent_id="'.$page->parent_id.'">'."\r";
                    $pages_atom.= '        <title type="html">'.fix($page->title).'</title>'."\r";
                    $pages_atom.= '        <id>tag:'.$tagged.'</id>'."\r";
                    $pages_atom.= '        <updated>'.when("c", $updated).'</updated>'."\r";
                    $pages_atom.= '        <published>'.when("c", $page->created_at).'</published>'."\r";
                    $pages_atom.= '        <link href="'.fix($trigger->filter($url, "page_export_url", $page)).'" />'."\r";
                    $pages_atom.= '        <author chyrp:user_id="'.fix($page->user_id).'">'."\r";
                    $pages_atom.= '            <name>'.fix(oneof($page->user->full_name, $page->user->login)).'</name>'."\r";

                    if (!empty($page->user->website))
                        $pages_atom.= '            <uri>'.fix($page->user->website).'</uri>'."\r";

                    $pages_atom.= '            <chyrp:login>'.fix($page->user->login).'</chyrp:login>'."\r";
                    $pages_atom.= '        </author>'."\r";
                    $pages_atom.= '        <content type="html">'.fix($page->body).'</content>'."\r";

                    foreach (array("public", "show_in_list", "list_order", "clean", "url") as $attr)
                        $pages_atom.= '        <chyrp:'.$attr.'>'.fix($page->$attr).'</chyrp:'.$attr.'>'."\r";


                    $trigger->filter($pages_atom, "pages_export", $page);

                    $pages_atom.= '    </entry>'."\r";
                }
                $pages_atom.= '</feed>'."\r";

                $exports["pages.atom"] = $pages_atom;
            }

            if (isset($_POST['groups'])) {
                list($where, $params) = keywords($_POST['filter_groups'], "name LIKE :query", "groups");

                $groups = Group::find(array("where" => $where, "params" => $params, "order" => "id ASC"));

                $groups_json = array("groups" => array(),
                                     "permissions" => array());

                foreach (SQL::current()->select("permissions", "*", array("group_id" => 0))->fetchAll() as $permission)
                    $groups_json["permissions"][$permission["id"]] = $permission["name"];

                foreach ($groups as $index => $group)
                    $groups_json["groups"][$group->name] = $group->permissions;

                $exports["groups.json"] = json_set($groups_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            if (isset($_POST['users'])) {
                list($where, $params) = keywords($_POST['filter_users'], "login LIKE :query OR full_name LIKE :query OR email LIKE :query OR website LIKE :query", "users");

                $users = User::find(array("where" => $where, "params" => $params, "order" => "id ASC"));

                $users_json = array();

                foreach ($users as $user) {
                    $users_json[$user->login] = array();

                    foreach ($user as $name => $attr)
                        if (!in_array($name, array("no_results", "group_id", "group", "id", "login", "belongs_to", "has_many", "has_one", "queryString")))
                            $users_json[$user->login][$name] = $attr;
                        elseif ($name == "group_id")
                            $users_json[$user->login]["group"] = $user->group->name;
                }

                $exports["users.json"] = json_set($users_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            $trigger->filter($exports, "export");

            if (empty($exports))
                Flash::warning(__("You did not select anything to export."), "/admin/?action=export");

            $filename = sanitize(camelize($config->name), false, true)."_Export_".date("Y-m-d");
            $filepath = tempnam(sys_get_temp_dir(), "zip");

            if (class_exists("ZipArchive")) {
                $zip = new ZipArchive;
                $err = $zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

                if ($err === true) {
                    foreach ($exports as $name => $contents)
                        $zip->addFromString($name, $contents);

                    $zip->close();
                    $bitstream = file_get_contents($filepath);
                    unlink($filepath);
                    file_attachment($bitstream, $filename.".zip");
                } else
                    error(__("Error"), _f("Failed to export files because of ZipArchive error %d.", $err));
            } else
                file_attachment(reset($exports), key($exports)); # ZipArchive not installed: send the first export item.
        }

        /**
         * Function: import
         * Importing content from other systems.
         */
        public function import() {
            if (!Visitor::current()->group->can("add_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to import content."));

            $this->display("import");
        }

        /**
         * Function: import_chyrp
         * Chyrp importing.
         */
        public function import_chyrp() {
            $config  = Config::current();
            $trigger = Trigger::current();
            $visitor = Visitor::current();
            $sql = SQL::current();

            if (!$visitor->group->can("add_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to import content."));

            if (empty($_POST))
                redirect("/admin/?action=import");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (isset($_FILES['posts_file']) and upload_tester($_FILES['posts_file']))
                if (!$posts = simplexml_load_file($_FILES['posts_file']['tmp_name']) or $posts->generator != "Chyrp")
                    Flash::warning(__("Chyrp Posts export file is invalid."), "/admin/?action=import");

            if (isset($_FILES['pages_file']) and upload_tester($_FILES['pages_file']))
                if (!$pages = simplexml_load_file($_FILES['pages_file']['tmp_name']) or $pages->generator != "Chyrp")
                    Flash::warning(__("Chyrp Pages export file is invalid."), "/admin/?action=import");

            if (ini_get("memory_limit") < 20)
                ini_set("memory_limit", "20M");

            if (ini_get("max_execution_time") !== 0)
                set_time_limit(300);

            function media_url_scan(&$value) {
                $config = Config::current();

                $regexp_url = preg_quote($_POST['media_url'], "/");

                if (preg_match_all("/{$regexp_url}([^\.\!,\?;\"\'<>\(\)\[\]\{\}\s\t ]+)\.([a-zA-Z0-9]+)/", $value, $media))
                    foreach ($media[0] as $matched_url) {
                        $filename = upload_from_url($matched_url);
                        $value = str_replace($matched_url, uploaded($filename), $value);
                    }
            }

            if (isset($_FILES['groups_file']) and upload_tester($_FILES['groups_file'])) {
                $import = json_get(file_get_contents($_FILES['groups_file']['tmp_name']), true);

                foreach ($import["groups"] as $name => $permissions)
                    if (!$sql->count("groups", array("name" => $name)))
                        $trigger->call("import_chyrp_group", Group::add($name, (array) $permissions));

                foreach ($import["permissions"] as $id => $name)
                    if (!$sql->count("permissions", array("id" => $id)))
                        $sql->insert("permissions", array("id" => $id, "name" => $name));
            }

            if (isset($_FILES['users_file']) and upload_tester($_FILES['users_file'])) {
                $users = json_get(file_get_contents($_FILES['users_file']['tmp_name']), true);

                foreach ($users as $login => $user) {
                    $group_id = $sql->select("groups", "id", array("name" => $user["group"]), "id DESC")->fetchColumn();

                    $group = ($group_id) ? $group_id : $config->default_group ;

                    if (!$sql->count("users", array("login" => $login)))
                        $user = User::add($login,
                                          $user["password"],
                                          $user["email"],
                                          $user["full_name"],
                                          $user["website"],
                                          $group,
                                          $user["joined_at"]);

                    $trigger->call("import_chyrp_user", $user);
                }
            }

            if (isset($_FILES['posts_file']) and upload_tester($_FILES['posts_file']))
                foreach ($posts->entry as $entry) {
                    $chyrp = $entry->children("http://chyrp.net/export/1.0/");

                    $login = $entry->author->children("http://chyrp.net/export/1.0/")->login;
                    $user_id = $sql->select("users", "id", array("login" => $login), "id DESC")->fetchColumn();

                    $data = xml2arr($entry->content);
                    $data["imported_from"] = "chyrp";

                    if (!empty($_POST['media_url']))
                        array_walk_recursive($data, "media_url_scan");

                    $post = Post::add($data,
                                      $chyrp->clean,
                                      Post::check_url($chyrp->url),
                                      $chyrp->feather,
                                      ($user_id ? $user_id : $visitor->id),
                                      (bool) (int) $chyrp->pinned,
                                      $chyrp->status,
                                      datetime($entry->published),
                                      ($entry->updated == $entry->published) ? null : datetime($entry->updated),
                                      false);

                    $trigger->call("import_chyrp_post", $entry, $post);
                }

            if (isset($_FILES['pages_file']) and upload_tester($_FILES['pages_file']))
                foreach ($pages->entry as $entry) {
                    $chyrp = $entry->children("http://chyrp.net/export/1.0/");
                    $attr  = $entry->attributes("http://chyrp.net/export/1.0/");

                    $login = $entry->author->children("http://chyrp.net/export/1.0/")->login;
                    $user_id = $sql->select("users", "id", array("login" => $login), "id DESC")->fetchColumn();

                    $page = Page::add($entry->title,
                                      $entry->content,
                                      ($user_id ? $user_id : $visitor->id),
                                      $attr->parent_id,
                                      (bool) (int) $chyrp->public,
                                      (bool) (int) $chyrp->show_in_list,
                                      $chyrp->list_order,
                                      $chyrp->clean,
                                      Page::check_url($chyrp->url),
                                      datetime($entry->published),
                                      ($entry->updated == $entry->published) ? null : datetime($entry->updated));

                    $trigger->call("import_chyrp_page", $entry, $page);
                }

            Flash::notice(__("Chyrp content successfully imported!"), "/admin/?action=import");
        }

        /**
         * Function: modules
         * Module enabling/disabling.
         */
        public function modules() {
            if (!Visitor::current()->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to toggle extensions."));

            $config = Config::current();

            $this->context["enabled_modules"]  = array();
            $this->context["disabled_modules"] = array();

            if (!$open = @opendir(MODULES_DIR))
                error(__("Error"), __("Could not read modules directory."));

            $classes = array();

            while (($folder = readdir($open)) !== false) {
                if (!file_exists(MODULES_DIR.DIR.$folder.DIR.$folder.".php") or
                    !file_exists(MODULES_DIR.DIR.$folder.DIR."info.php"))
                    continue;

                load_translator($folder, MODULES_DIR.DIR.$folder.DIR."locale".DIR.$config->locale.".mo");

                if (!isset($classes[$folder]))
                    $classes[$folder] = array($folder);
                else
                    array_unshift($classes[$folder], $folder);

                $info = include MODULES_DIR.DIR.$folder.DIR."info.php";

                if (gettype($info) != "array")
                    continue;

                $conflicting_modules = array();

                if (!empty($info["conflicts"])) {
                    $classes[$folder][] = "conflicts";

                    foreach ((array) $info["conflicts"] as $conflict)
                        if (file_exists(MODULES_DIR.DIR.$conflict.DIR.$conflict.".php")) {
                            $classes[$folder][] = "conflict_".$conflict;
                            $conflicting_modules[] = $conflict; # Shortlist of conflicting installed modules

                            if (in_array($conflict, $config->enabled_modules))
                                if (!in_array("error", $classes[$folder]))
                                    $classes[$folder][] = "error";
                        }
                }

                $dependencies_needed = array();

                if (!empty($info["dependencies"])) {
                    $classes[$folder][] = "dependencies";

                    foreach ((array) $info["dependencies"] as $dependency) {
                        if (!file_exists(MODULES_DIR.DIR.$dependency.DIR.$dependency.".php")) {
                            if (!in_array("missing_dependency", $classes[$folder]))
                                $classes[$folder][] = "missing_dependency"; # Dependency is not installed

                            if (!in_array("error", $classes[$folder]))
                                $classes[$folder][] = "error";
                        } else {
                            if (!in_array($dependency, $config->enabled_modules))
                                if (!in_array("error", $classes[$folder]))
                                    $classes[$folder][] = "error";

                            fallback($classes[$dependency], array());
                            $classes[$dependency][] = "needed_by_".$folder;
                        }

                        $classes[$folder][] = "needs_".$dependency;
                        $dependencies_needed[] = $dependency;
                    }
                }

                fallback($info["name"], $folder);
                fallback($info["version"], "0");
                fallback($info["url"]);
                fallback($info["description"], __("No description."));
                fallback($info["author"], array("name" => "", "url" => ""));
                fallback($info["help"]);
                fallback($info["confirm"]);

                $info["description"] = __($info["description"], $folder);

                $info["description"] = preg_replace_callback("/<code>(.+?)<\/code>/s",
                                                             function ($matches) {
                                                                 return "<code>".fix($matches[1])."</code>";
                                                             },
                                                             $info["description"]);
                $info["description"] = preg_replace_callback("/<pre>(.+?)<\/pre>/s",
                                                             function ($matches) {
                                                                 return "<pre>".fix($matches[1])."</pre>";
                                                             },
                                                             $info["description"]);

                $info["author"]["link"] = !empty($info["author"]["url"]) ?
                    '<a href="'.fix($info["author"]["url"]).'">'.fix($info["author"]["name"]).'</a>' : $info["author"]["name"] ;

                # We don't use the module_enabled() helper function to allow for disabling cancelled modules.
                $category = (!empty(Modules::$instances[$folder])) ? "enabled_modules" : "disabled_modules" ;

                $this->context[$category][$folder] = array("name" => $info["name"],
                                                           "version" => $info["version"],
                                                           "url" => $info["url"],
                                                           "description" => $info["description"],
                                                           "author" => $info["author"],
                                                           "help" => $info["help"],
                                                           "confirm" => $info["confirm"],
                                                           "classes" => $classes[$folder],
                                                           "conflicting_modules" => $conflicting_modules,
                                                           "dependencies_needed" => $dependencies_needed);
            }

            foreach ($this->context["enabled_modules"] as $module => &$attrs)
                $attrs["classes"] = $classes[$module];

            foreach ($this->context["disabled_modules"] as $module => &$attrs)
                $attrs["classes"] = $classes[$module];

            $this->display("modules");
        }

        /**
         * Function: feathers
         * Feather enabling/disabling.
         */
        public function feathers() {
            if (!Visitor::current()->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to toggle extensions."));

            $config = Config::current();

            $this->context["enabled_feathers"]  = array();
            $this->context["disabled_feathers"] = array();

            if (!$open = @opendir(FEATHERS_DIR))
                error(__("Error"), __("Could not read feathers directory."));

            while (($folder = readdir($open)) !== false) {
                if (!file_exists(FEATHERS_DIR.DIR.$folder.DIR.$folder.".php") or
                    !file_exists(FEATHERS_DIR.DIR.$folder.DIR."info.php"))
                    continue;

                load_translator($folder, FEATHERS_DIR.DIR.$folder.DIR."locale".DIR.$config->locale.".mo");

                $info = include FEATHERS_DIR.DIR.$folder.DIR."info.php";

                if (gettype($info) != "array")
                    continue;

                fallback($info["name"], $folder);
                fallback($info["version"], "0");
                fallback($info["url"]);
                fallback($info["description"], __("No description."));
                fallback($info["author"], array("name" => "", "url" => ""));
                fallback($info["help"]);
                fallback($info["confirm"]);

                $info["description"] = __($info["description"], $folder);

                $info["description"] = preg_replace_callback("/<code>(.+?)<\/code>/s",
                                                             function ($matches) {
                                                                 return "<code>".fix($matches[1])."</code>";
                                                             },
                                                             $info["description"]);
                $info["description"] = preg_replace_callback("/<pre>(.+?)<\/pre>/s",
                                                             function ($matches) {
                                                                 return "<pre>".fix($matches[1])."</pre>";
                                                             },
                                                             $info["description"]);

                $info["author"]["link"] = !empty($info["author"]["url"]) ?
                    '<a href="'.fix($info["author"]["url"]).'">'.fix($info["author"]["name"]).'</a>' : $info["author"]["name"] ;

                # We don't use the feather_enabled() helper function to allow for disabling cancelled feathers.
                $category = (!empty(Feathers::$instances[$folder])) ? "enabled_feathers" : "disabled_feathers" ;

                $this->context[$category][$folder] = array("name" => $info["name"],
                                                           "version" => $info["version"],
                                                           "url" => $info["url"],
                                                           "description" => $info["description"],
                                                           "author" => $info["author"],
                                                           "help" => $info["help"],
                                                           "confirm" => $info["confirm"]);
            }

            $this->display("feathers");
        }

        /**
         * Function: themes
         * Theme switching/previewing.
         */
        public function themes() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (!empty($_SESSION['theme']))
                Flash::message(__("You are currently previewing a theme.").
                                 ' <a href="'.admin_url("preview_theme").'">'.__("Stop &rarr;").'</a>');

            $config = Config::current();
            $this->context["themes"] = array();

            if (!$open = @opendir(THEMES_DIR))
                error(__("Error"), __("Could not read themes directory."));

            while (($folder = readdir($open)) !== false) {
                if (!file_exists(THEMES_DIR.DIR.$folder.DIR."info.php"))
                    continue;

                load_translator($folder, THEMES_DIR.DIR.$folder.DIR."locale".DIR.$config->locale.".mo");

                $info = include THEMES_DIR.DIR.$folder.DIR."info.php";

                if (gettype($info) != "array")
                  continue;

                fallback($info["name"], $folder);
                fallback($info["version"], "0");
                fallback($info["url"]);
                fallback($info["description"]);
                fallback($info["author"], array("name" => "", "url" => ""));

                $info["author"]["link"] = !empty($info["author"]["url"]) ?
                    '<a href="'.$info["author"]["url"].'">'.$info["author"]["name"].'</a>' : $info["author"]["name"] ;

                $info["description"] = preg_replace_callback("/<code>(.+?)<\/code>/s",
                                                             function ($matches) {
                                                                 return "<code>".fix($matches[1])."</code>";
                                                             },
                                                             $info["description"]);
                $info["description"] = preg_replace_callback("/<pre>(.+?)<\/pre>/s",
                                                             function ($matches) {
                                                                 return "<pre>".fix($matches[1])."</pre>";
                                                             },
                                                             $info["description"]);

                $this->context["themes"][] = array("name" => $folder,
                                                   "screenshot" => (file_exists(THEMES_DIR.DIR.$folder.DIR."screenshot.png") ?
                                                        $config->chyrp_url."/themes/".$folder."/screenshot.png" : ""),
                                                   "info" => $info);
            }

            closedir($open);
            $this->display("themes");
        }

        /**
         * Function: enable
         * Enables a module or feather.
         */
        public function enable() {
            $config  = Config::current();
            $visitor = Visitor::current();

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to enable extensions."));

            $type = (isset($_POST['module'])) ? "module" : "feather" ;

            if (empty($_POST[$type]))
                error(__("No Extension Specified"), __("You did not specify an extension to enable."), null, 400);

            $name          = $_POST[$type];
            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $updated_array = $config->$enabled_array;
            $folder        = ($type == "module") ? MODULES_DIR : FEATHERS_DIR ;
            $class_name    = camelize($name);

            # We don't use the module_enabled() helper function because we want to include cancelled modules.
            if ($type == "module" and !empty(Modules::$instances[$name]))
                error(__("Error"), __("Module already enabled."), null, 409);

            # We don't use the feather_enabled() helper function because we want to include cancelled feathers.
            if ($type == "feather" and !empty(Feathers::$instances[$name]))
                error(__("Error"), __("Feather already enabled."), null, 409);

            if (!file_exists($folder.DIR.$name.DIR.$name.".php"))
                show_404(__("Not Found"), __("Extension not found."));

            require $folder.DIR.$name.DIR.$name.".php";

            if (!is_subclass_of($class_name, camelize(pluralize($type))))
                show_404(__("Not Found"), __("Extension not found."));

            load_translator($name, $folder.DIR.$name.DIR."locale".DIR.$config->locale.".mo");

            if (method_exists($class_name, "__install"))
                call_user_func(array($class_name, "__install"));

            if (!in_array($name, $updated_array))
                $updated_array[] = $name;

            $config->set($enabled_array, $updated_array);

            $info = include $folder.DIR.$name.DIR."info.php";
            fallback($info["uploader"], false);
            fallback($info["notifications"], array());

            if ($info["uploader"])
                if (!file_exists(MAIN_DIR.$config->uploads_path))
                    $info["notifications"][] = _f("Please create the directory <em>%s</em> in your install directory.", $config->uploads_path);
                elseif (!is_writable(MAIN_DIR.$config->uploads_path))
                    $info["notifications"][] = _f("Please make <em>%s</em> writable by the server.", $config->uploads_path);

            foreach ($info["notifications"] as $message)
                Flash::message($message);

            Flash::notice(__("Extension enabled."), "/admin/?action=".pluralize($type));
        }

        /**
         * Function: disable
         * Disables a module or feather.
         */
        public function disable() {
            $config  = Config::current();
            $visitor = Visitor::current();

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!$visitor->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to disable extensions."));

            $type = (isset($_POST['module'])) ? "module" : "feather" ;

            if (empty($_POST[$type]))
                error(__("No Extension Specified"), __("You did not specify an extension to disable."), null, 400);

            $name          = $_POST[$type];
            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $updated_array = array();
            $class_name    = camelize($name);

            # We don't use the module_enabled() helper function because we want to exclude cancelled modules.
            if ($type == "module" and empty(Modules::$instances[$name]))
                error(__("Error"), __("Module already disabled."), null, 409);

            # We don't use the feather_enabled() helper function because we want to exclude cancelled feathers.
            if ($type == "feather" and empty(Feathers::$instances[$name]))
                error(__("Error"), __("Feather already disabled."), null, 409);

            if ($type == "module" and !is_subclass_of($class_name, "Modules"))
                show_404(__("Not Found"), __("Module not found."));

            if ($type == "feather" and !is_subclass_of($class_name, "Feathers"))
                show_404(__("Not Found"), __("Feather not found."));

            if (method_exists($class_name, "__uninstall"))
                call_user_func(array($class_name, "__uninstall"), !empty($_POST['confirm']));

            foreach ($config->$enabled_array as $extension)
                if ($extension != $name)
                    $updated_array[] = $extension;

            $config->set($enabled_array, $updated_array);

            if ($type == "feather" and isset($_SESSION['latest_feather']) and $_SESSION['latest_feather'] == $name)
                unset($_SESSION['latest_feather']);

            Flash::notice(__("Extension disabled."), "/admin/?action=".pluralize($type));
        }

        /**
         * Function: change_theme
         * Changes the theme.
         */
        public function change_theme() {
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (empty($_POST['theme']))
                error(__("No Theme Specified"), __("You did not specify a theme to select or preview."), null, 400);

            if ($_POST['change'] != "indubitably")
                self::preview_theme();

            $config = Config::current();
            $theme = $_POST['theme'];
            $config->set("theme", $theme);

            load_translator($theme, THEMES_DIR.DIR.$theme.DIR."locale".DIR.$config->locale.".mo");

            $info = include THEMES_DIR.DIR.$theme.DIR."info.php";
            fallback($info["notifications"], array());

            foreach ($info["notifications"] as $message)
                Flash::message($message);

            Flash::notice(_f("Theme changed to &#8220;%s&#8221;.", fix($info["name"])), "/admin/?action=themes");
        }

        /**
         * Function: preview_theme
         * Previews the theme.
         */
        public function preview_theme() {
            Trigger::current()->call("preview_theme", !empty($_POST['theme']));

            if (empty($_POST['theme'])) {
                unset($_SESSION['theme']);
                Flash::notice(__("Preview stopped."), "/admin/?action=themes");
            }

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to preview themes."));

            $_SESSION['theme'] = $_POST['theme'];
            Flash::notice(__("Preview started."), "/");
        }

        /**
         * Function: general_settings
         * General Settings page.
         */
        public function general_settings() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            $locales = array();
            $locales[] = array("code" => "en_US", "name" => lang_code("en_US")); # Default locale.

            if ($open = opendir(INCLUDES_DIR.DIR."locale".DIR)) {
                 while (($folder = readdir($open)) !== false) {
                    $split = explode(".", $folder);

                    if (end($split) == "mo" and $split[0] != "en_US")
                        $locales[] = array("code" => $split[0], "name" => lang_code($split[0]));
                }
                closedir($open);
            }

            if (empty($_POST))
                return $this->display("general_settings", array("locales" => $locales,
                                                                "timezones" => timezones()));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!empty($_POST['email']) and !is_email($_POST['email']))
                error(__("Error"), __("Invalid email address."), null, 422);

            if (!is_url($_POST['chyrp_url']))
                error(__("Error"), __("Invalid Chyrp URL."), null, 422);

            if (!empty($_POST['url']) and !is_url($_POST['url']))
                error(__("Error"), __("Invalid canonical URL."), null, 422);

            $config = Config::current();

            $check_updates_last = (empty($_POST['check_updates'])) ? 0 : $config->check_updates_last ;

            $set = array($config->set("name", $_POST['name']),
                         $config->set("description", $_POST['description']),
                         $config->set("chyrp_url", rtrim(add_scheme($_POST['chyrp_url']), "/")),
                         $config->set("url", rtrim(add_scheme(oneof($_POST['url'], $_POST['chyrp_url'])), "/")),
                         $config->set("email", $_POST['email']),
                         $config->set("timezone", $_POST['timezone']),
                         $config->set("locale", $_POST['locale']),
                         $config->set("cookies_notification", !empty($_POST['cookies_notification'])),
                         $config->set("check_updates", !empty($_POST['check_updates'])),
                         $config->set("check_updates_last", $check_updates_last));

            if (!in_array(false, $set))
                Flash::notice(__("Settings updated."), "/admin/?action=general_settings");
        }

        /**
         * Function: content_settings
         * Content Settings page.
         */
        public function content_settings() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (empty($_POST))
                return $this->display("content_settings");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!empty($_POST['feed_url']) and !is_url($_POST['feed_url']))
                error(__("Error"), __("Invalid feed URL."), null, 422);

            $separator = preg_quote(DIR, "~");
            preg_match("~^(".$separator.")?(.*?)(".$separator.")?$~", $_POST['uploads_path'], $matches);

            fallback($matches[1], DIR);
            fallback($matches[2], "uploads");
            fallback($matches[3], DIR);

            $config = Config::current();
            $set = array($config->set("posts_per_page", (int) $_POST['posts_per_page']),
                         $config->set("feed_items", (int) $_POST['feed_items']),
                         $config->set("admin_per_page", (int) $_POST['admin_per_page']),
                         $config->set("feed_url", $_POST['feed_url']),
                         $config->set("uploads_path", $matches[1].$matches[2].$matches[3]),
                         $config->set("uploads_limit", (int) $_POST['uploads_limit']),
                         $config->set("send_pingbacks", !empty($_POST['send_pingbacks'])),
                         $config->set("enable_xmlrpc", !empty($_POST['enable_xmlrpc'])),
                         $config->set("enable_ajax", !empty($_POST['enable_ajax'])),
                         $config->set("enable_emoji", !empty($_POST['enable_emoji'])),
                         $config->set("enable_markdown", !empty($_POST['enable_markdown'])));

            if (!in_array(false, $set))
                Flash::notice(__("Settings updated."), "/admin/?action=content_settings");
        }

        /**
         * Function: user_settings
         * User Settings page.
         */
        public function user_settings() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (empty($_POST))
                return $this->display("user_settings", array("groups" => Group::find(array("order" => "id DESC"))));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            $correspond = (!empty($_POST['email_activation']) or !empty($_POST['email_correspondence'])) ? true : false ;

            $config = Config::current();
            $set = array($config->set("can_register", !empty($_POST['can_register'])),
                         $config->set("email_activation", !empty($_POST['email_activation'])),
                         $config->set("email_correspondence", $correspond),
                         $config->set("enable_captcha", !empty($_POST['enable_captcha'])),
                         $config->set("default_group", (int) $_POST['default_group']),
                         $config->set("guest_group", (int) $_POST['guest_group']));

            if (!in_array(false, $set))
                Flash::notice(__("Settings updated."), "/admin/?action=user_settings");
        }

        /**
         * Function: route_settings
         * Route Settings page.
         */
        public function route_settings() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (empty($_POST))
                return $this->display("route_settings");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            $route = Route::current();
            $config = Config::current();

            if (!empty($_POST['clean_urls']) and !$config->clean_urls and htaccess_conf() === false) {
                Flash::warning(__("Clean URLs cannot be enabled because the <em>.htaccess</em> file is not configured."));
                unset($_POST['clean_urls']);
            }

            if (!empty($_POST['enable_homepage']) and !$config->enable_homepage) {
                $route->add("/", "page;url=home");

                if (Page::check_url("home") == "home" ) {
                    $page = Page::add(__("My Awesome Homepage"),
                                      __("Nothing here yet!"),
                                      null,
                                      0,
                                      true,
                                      true,
                                      0,
                                      "home");
                    Flash::notice(__("Page created.").' <a href="'.$page->url().'">'.__("View page &rarr;").'</a>');
                }
            }

            if (empty($_POST['enable_homepage']) and $config->enable_homepage)
                $route->remove("/");

            $set = array($config->set("clean_urls", !empty($_POST['clean_urls'])),
                         $config->set("post_url", $_POST['post_url']),
                         $config->set("enable_homepage", !empty($_POST['enable_homepage'])));

            if (!in_array(false, $set))
                Flash::notice(__("Settings updated."), "/admin/?action=route_settings");
        }

        /**
         * Function: login
         * Mask for MainController->login().
         */
        public function login() {
            if (logged_in())
                Flash::notice(__("You are already logged in."), "/admin/");

            $_SESSION['redirect_to'] = "/admin/";

            $config = Config::current();
            redirect($config->url.(($config->clean_urls) ? "/login/" : "/?action=login"));
        }

        /**
         * Function: logout
         * Mask for MainController->logout().
         */
        public function logout() {
            $config = Config::current();
            redirect($config->url.(($config->clean_urls) ? "/logout/" : "/?action=logout"));
        }

        /**
         * Function: help
         * Serves help pages for core and extensions.
         */
        public function help() {
            if (empty($_GET['id']))
                error(__("Error"), __("Missing argument."), null, 400);

            $template = oneof(trim($_GET['id']), DIR);

            if (substr_count($template, DIR))
                error(__("Error"), __("Malformed URI."), null, 400);

            return $this->display($template, array(), __("Help"), "help");
        }

        /**
         * Function: subnav_context
         * Generates the context variables for the subnav.
         */
        public function subnav_context($action) {
            $trigger = Trigger::current();
            $visitor = Visitor::current();

            $this->context["subnav"] = array();
            $subnav =& $this->context["subnav"];

            $subnav["write"] = array();
            $pages = array("manage" => array());

            foreach (Config::current()->enabled_feathers as $index => $feather) {
                $selected = ((isset($_GET['feather']) and $_GET['feather'] == $feather) or
                            (!isset($_GET['feather']) and $action == "write_post" and !$index)) ? "write_post" : false ;

                $info = include FEATHERS_DIR.DIR.$feather.DIR."info.php";
                $subnav["write"]["write_post&feather=".$feather] = array("title" => $info["name"],
                                                                         "show" => $visitor->group->can("add_draft", "add_post"),
                                                                         "attributes" => ' id="feathers['.$feather.']"',
                                                                         "selected" => $selected);
            }

            # Write navs
            $subnav["write"]["write_page"] = array("title" => __("Page"),
                                                   "show" => $visitor->group->can("add_page"));
            $trigger->filter($subnav["write"], array("admin_write_nav", "write_nav"));
            $pages["write"] = array_merge(array("write_post"), array_keys($subnav["write"]));;

            # Manage navs
            $subnav["manage"] = array("manage_posts"  => array("title" => __("Posts"),
                                                               "show" => (Post::any_editable() or Post::any_deletable()),
                                                               "selected" => array("edit_post", "delete_post")),
                                      "manage_pages"  => array("title" => __("Pages"),
                                                               "show" => ($visitor->group->can("edit_page", "delete_page")),
                                                               "selected" => array("edit_page", "delete_page")),
                                      "manage_users"  => array("title" => __("Users"),
                                                               "show" => ($visitor->group->can("add_user",
                                                                                               "edit_user",
                                                                                               "delete_user")),
                                                               "selected" => array("edit_user", "delete_user", "new_user")),
                                      "manage_groups" => array("title" => __("Groups"),
                                                               "show" => ($visitor->group->can("add_group",
                                                                                               "edit_group",
                                                                                               "delete_group")),
                                                               "selected" => array("edit_group", "delete_group", "new_group")));
            $trigger->filter($subnav["manage"], "manage_nav");

            $subnav["manage"]["import"] = array("title" => __("Import"),
                                                "show" => ($visitor->group->can("add_post")));
            $subnav["manage"]["export"] = array("title" => __("Export"),
                                                "show" => ($visitor->group->can("add_post")));

            $pages["manage"][] = "new_user";
            $pages["manage"][] = "new_group";

            foreach (array_keys($subnav["manage"]) as $manage)
                $pages["manage"] = array_merge($pages["manage"], array($manage,
                                                                       preg_replace_callback("/manage_(.+)/",
                                                                            function($m) {
                                                                                return "edit_".depluralize($m[1]);
                                                                            }, $manage),
                                                                       preg_replace_callback("/manage_(.+)/",
                                                                            function($m) {
                                                                                return "delete_".depluralize($m[1]);
                                                                            }, $manage)));

            # Settings navs
            $subnav["settings"] = array("general_settings" => array("title" => __("General"),
                                                                    "show" => $visitor->group->can("change_settings")),
                                        "content_settings" => array("title" => __("Content"),
                                                                    "show" => $visitor->group->can("change_settings")),
                                        "user_settings"    => array("title" => __("Users"),
                                                                    "show" => $visitor->group->can("change_settings")),
                                        "route_settings"   => array("title" => __("Routes"),
                                                                    "show" => $visitor->group->can("change_settings")));
            $trigger->filter($subnav["settings"], "settings_nav");
            $pages["settings"] = array_keys($subnav["settings"]);

            # Extend navs
            $subnav["extend"] = array("modules"  => array("title" => __("Modules"),
                                                          "show" => $visitor->group->can("toggle_extensions")),
                                      "feathers" => array("title" => __("Feathers"),
                                                          "show" => $visitor->group->can("toggle_extensions")),
                                      "themes"   => array("title" => __("Themes"),
                                                          "show" => $visitor->group->can("toggle_extensions")));
            $trigger->filter($subnav["extend"], "extend_nav");
            $pages["extend"] = array_keys($subnav["extend"]);

            foreach (array_keys($subnav) as $main_nav)
                foreach ($trigger->filter($pages[$main_nav], $main_nav."_nav_pages") as $extend)
                    $subnav[$extend] =& $subnav[$main_nav];

            foreach ($subnav as $main_nav => &$sub_nav)
                foreach ($sub_nav as &$nav)
                    $nav["show"] = (!isset($nav["show"]) or $nav["show"]);

            $trigger->filter($subnav, "admin_subnav");
        }

        /**
         * Function: display
         * Renders the page.
         *
         * Parameters:
         *     $action - The template file to display (sans ".twig") relative to /admin/ for core and extensions.
         *     $context - The context to be supplied to Twig.
         *     $title - The title for the page. Defaults to a camlelization of the action, e.g. foo_bar -> Foo Bar.
         *     $path - The path to the template, usually "pages".
         */
        public function display($action, $context = array(), $title = "", $path = "pages") {
            $this->displayed = true;
            fallback($title, camelize($action, true));
            $this->context = array_merge($context, $this->context);

            $config = Config::current();
            $visitor = Visitor::current();
            $route = Route::current();
            $trigger = Trigger::current();

            $trigger->filter($this->context, array("admin_context", "admin_context_".str_replace(DIR, "_", $action)));

            # Are there any extension-added pages?
            foreach (array("write" => array(),
                           "manage" => array("import", "export"),
                           "settings" => array(),
                           "extend" => array("modules", "feathers", "themes")) as $main_nav => $val) {
                $$main_nav = $val;
                $trigger->filter($$main_nav, $main_nav."_pages");
            }

            $this->context["ip"]         = $_SERVER["REMOTE_ADDR"];
            $this->context["DIR"]        = DIR;
            $this->context["theme"]      = Theme::current();
            $this->context["flash"]      = Flash::current();
            $this->context["trigger"]    = $trigger;
            $this->context["title"]      = $title;
            $this->context["site"]       = $config;
            $this->context["visitor"]    = $visitor;
            $this->context["logged_in"]  = logged_in();
            $this->context["route"]      = $route;
            $this->context["now"]        = time();
            $this->context["version"]    = CHYRP_VERSION;
            $this->context["codename"]   = CHYRP_CODENAME;
            $this->context["debug"]      = DEBUG;
            $this->context["feathers"]   = Feathers::$instances;
            $this->context["modules"]    = Modules::$instances;
            $this->context["POST"]       = $_POST;
            $this->context["GET"]        = $_GET;

            $this->context["navigation"] = array();

            $show = array("write" => array($visitor->group->can("add_draft", "add_post", "add_page")),
                          "manage" => array($visitor->group->can("view_own_draft",
                                                                 "view_draft",
                                                                 "edit_own_draft",
                                                                 "edit_own_post",
                                                                 "edit_post",
                                                                 "delete_own_draft",
                                                                 "delete_own_post",
                                                                 "delete_post",
                                                                 "add_page",
                                                                 "edit_page",
                                                                 "delete_page",
                                                                 "add_user",
                                                                 "edit_user",
                                                                 "delete_user",
                                                                 "add_group",
                                                                 "edit_group",
                                                                 "delete_group")),
                          "settings" => array($visitor->group->can("change_settings")),
                          "extend" => array($visitor->group->can("toggle_extensions")));

            foreach ($show as $name => &$arr)
                $trigger->filter($arr, $name."_nav_show");

            $this->context["navigation"]["write"] = array("title" => __("Write"),
                                                          "show" => in_array(true, $show["write"]),
                                                          "selected" => (in_array($action, $write) or
                                                                        match("/^write_/", $action)));

            $this->context["navigation"]["manage"] = array("title" => __("Manage"),
                                                           "show" => in_array(true, $show["manage"]),
                                                           "selected" => (in_array($action, $manage) or
                                                                         match(array("/^manage_/",
                                                                                     "/^edit_/",
                                                                                     "/^delete_/",
                                                                                     "/^new_/"), $action)));

            $this->context["navigation"]["settings"] = array("title" => __("Settings"),
                                                             "show" => in_array(true, $show["settings"]),
                                                             "selected" => (in_array($action, $settings) or
                                                                           match("/_settings$/", $action)));

            $this->context["navigation"]["extend"] = array("title" => __("Extend"),
                                                           "show" => in_array(true, $show["extend"]),
                                                           "selected" => (in_array($action, $extend) or
                                                                         match("/_extend$/", $action)));

            $this->subnav_context($route->action);
            $trigger->filter($this->context["selected"], "nav_selected");
            $this->context["sql_debug"] = SQL::current()->debug;
            $template = $path.DIR.$action.".twig";

            if ($config->check_updates and (time() - $config->check_updates_last) > UPDATE_INTERVAL)
                Update::check();

            try {
                $this->twig->display($template, $this->context);
            } catch (Exception $e) {
                $prettify = preg_replace("/([^:]+): (.+)/", "\\1: <code>\\2</code>", $e->getMessage());
                error(__("Twig Error"), $prettify, debug_backtrace());
            }
        }

        /**
         * Function: current
         * Returns a singleton reference to the current class.
         */
        public static function & current() {
            static $instance = null;
            $instance = (empty($instance)) ? new self() : $instance ;
            return $instance;
        }
    }
