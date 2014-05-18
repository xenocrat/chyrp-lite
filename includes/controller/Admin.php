<?php
    /**
     * Class: Admin Controller
     * The logic behind the Admin area.
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
         * Prepares Twig.
         */
        private function __construct() {
            $this->admin_theme = fallback(Config::current()->admin_theme, "default");

            $this->theme = new Twig_Loader(MAIN_DIR."/admin/themes/".$this->admin_theme,
                                            (is_writable(INCLUDES_DIR."/caches") and !DEBUG) ?
                                                INCLUDES_DIR."/caches" :
                                                null);

            $this->default = new Twig_Loader(MAIN_DIR."/admin/themes/default",
                                            (is_writable(INCLUDES_DIR."/caches") and !DEBUG) ?
                                                INCLUDES_DIR."/caches" :
                                                null);
        }

        /**
         * Function: parse
         * Determines the action.
         */
        public function parse($route) {
            $visitor = Visitor::current();

            # Protect non-responder functions.
            if (in_array($route->action, array("__construct", "parse", "subnav_context", "display", "current")))
                show_404();

            if (empty($route->action) or $route->action == "write") {
                # "Write > Post", if they can add posts or drafts.
                if (($visitor->group->can("add_post") or $visitor->group->can("add_draft")) and
                    !empty(Config::current()->enabled_feathers))
                    return $route->action = "write_post";

                # "Write > Page", if they can add pages.
                if ($visitor->group->can("add_page"))
                    return $route->action = "write_page";
                else
                    show_403(__("Access Denied"), __("You do not have sufficient privileges to view this area."));
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
                else
                    show_403(__("Access Denied"), __("You do not have sufficient privileges to view this area."));
            }

            if (empty($route->action) or $route->action == "settings") {
                # "General Settings", if they can configure the installation.
                if ($visitor->group->can("change_settings"))
                    return $route->action = "general_settings";
                else
                    show_403(__("Access Denied"), __("You do not have sufficient privileges to view this area."));
            }

            if (empty($route->action) or $route->action == "extend") {
                # "Modules", if they can can enable/disable extensions.
                if ($visitor->group->can("toggle_extensions"))
                    return $route->action = "modules";
                else
                    show_403(__("Access Denied"), __("You do not have sufficient privileges to view this area."));
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
                error(__("No Feathers"), __("Please install a feather or two in order to add a post."));

            Trigger::current()->filter($options, array("write_post_options", "post_options"));

            fallback($_GET['feather'], reset($config->enabled_feathers));

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

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

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
            if (empty($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to edit a post."));

            $post = new Post($_GET['id'], array("drafts" => true, "filter" => false));

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
            $post = new Post($_POST['id'], array("drafts" => true));

            if (isset($_POST['publish']))
                $_POST['status'] = "public";

            if ($post->no_results)
                Flash::warning(__("Post not found."), "/admin/?action=manage_posts");

            if (!$post->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this post."));

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            Feathers::$instances[$post->feather]->update($post);

            if (!isset($_POST['ajax']))
                Flash::notice(_f("Post updated. <a href=\"%s\">View Post &rarr;</a>",
                                 array($post->url())),
                              "/admin/?action=manage_posts");
            else
                exit((string) $_POST['id']);
        }

        /**
         * Function: delete_post
         * Post deletion (confirm page).
         */
        public function delete_post() {
            if (empty($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a post."));

            $post = new Post($_GET['id'], array("drafts" => true));

            if (!$post->deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this post."));

            $this->display("delete_post", array("post" => $post));
        }

        /**
         * Function: destroy_post
         * Destroys a post (the real deal).
         */
        public function destroy_post() {
            if (empty($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a post."));

            if ($_POST['destroy'] == "bollocks")
                redirect("/admin/?action=manage_posts");

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            $post = new Post($_POST['id'], array("drafts" => true));
            if (!$post->deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this post."));

            Post::delete($_POST['id']);

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

            list($where, $params) = keywords($_GET['query'], "post_attributes.value LIKE :query OR url LIKE :query", "post_attributes");

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
                                       25);
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

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['title']) and empty($_POST['slug']))
                error(__("Error"), __("Title and slug cannot be blank."));

            $page = Page::add($_POST['title'],
                              $_POST['body'],
                              null,
                              $_POST['parent_id'],
                              !empty($_POST['show_in_list']),
                              0,
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

            if (empty($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to edit a page."));

            $this->display("edit_page",
                           array("page" => new Page($_GET['id'], array("filter" => false)),
                                 "pages" => Page::find(array("where" => array("id not" => $_GET['id'])))));
        }

        /**
         * Function: update_page
         * Updates a page when the form is submitted.
         */
        public function update_page() {
            if (!Visitor::current()->group->can("edit_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit pages."));

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['title']) and empty($_POST['slug']))
                error(__("Error"), __("Title and slug cannot be blank."));

            $page = new Page($_POST['id']);

            if ($page->no_results)
                Flash::warning(__("Page not found."), "/admin/?action=manage_pages");

            $page->update($_POST['title'], $_POST['body'], null, $_POST['parent_id'], !empty($_POST['show_in_list']), $page->list_order, null, $_POST['slug']);

            if (!isset($_POST['ajax']))
                Flash::notice(_f("Page updated. <a href=\"%s\">View Page &rarr;</a>",
                                 array($page->url())),
                              "/admin/?action=manage_pages");
        }

        /**
         * Function: reorder_pages
         * Reorders pages.
         */
        public function reorder_pages() {
            foreach ($_POST['list_order'] as $id => $order) {
                $page = new Page($id);
                $page->update($page->title, $page->body, null, $page->parent_id, $page->show_in_list, $order, null, $page->url);
            }

            Flash::notice(__("Pages reordered."), "/admin/?action=manage_pages");
        }

        /**
         * Function: delete_page
         * Page deletion (confirm page).
         */
        public function delete_page() {
            if (!Visitor::current()->group->can("delete_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete pages."));

            if (empty($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a page."));

            $this->display("delete_page", array("page" => new Page($_GET['id'])));
        }

        /**
         * Function: destroy_page
         * Destroys a page.
         */
        public function destroy_page() {
            if (!Visitor::current()->group->can("delete_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete pages."));

            if (empty($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a post."));

            if ($_POST['destroy'] == "bollocks")
                redirect("/admin/?action=manage_pages");

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            $page = new Page($_POST['id']);

            if (!$page->no_results)
                foreach ($page->children as $child)
                    if (isset($_POST['destroy_children']))
                        Page::delete($child->id, true);
                    else
                        $child->update($child->title, $child->body, 0, $child->show_in_list, $child->list_order, $child->url);

            Page::delete($_POST['id']);

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
                                                                           "params" => $params)), 25)));
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

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['login']))
                error(__("Error"), __("Please enter a username for your account."));

            $check = new User(array("login" => $_POST['login']));
            if (!$check->no_results)
                error(__("Error"), __("That username is already in use."));

            if (empty($_POST['password1']) or empty($_POST['password2']))
                error(__("Error"), __("Password cannot be blank."));
            elseif ($_POST['password1'] != $_POST['password2'])
                error(__("Error"), __("Passwords do not match."));

            if (empty($_POST['email']))
                error(__("Error"), __("E-mail address cannot be blank."));
            elseif (!preg_match("/^[_A-z0-9-]+((\.|\+)[_A-z0-9-]+)*@[A-z0-9-]+(\.[A-z0-9-]+)*(\.[A-z]{2,4})$/", $_POST['email']))
                error(__("Error"), __("Invalid e-mail address."));

            if (!empty($_POST['website']) and strpos($_POST['website'], '://') === false) {
                $_POST['website'] = 'http://' . $_POST['website'];
            }

            User::add($_POST['login'],
                      $_POST['password1'],
                      $_POST['email'],
                      $_POST['full_name'],
                      $_POST['website'],
                      $_POST['group']);

            Flash::notice(__("User added."), "/admin/?action=manage_users");
        }

        /**
         * Function: edit_user
         * User editing.
         */
        public function edit_user() {
            if (!Visitor::current()->group->can("edit_user"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this user."));

            if (empty($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to edit a user."));

            $this->display("edit_user",
                           array("user" => new User($_GET['id']),
                                 "groups" => Group::find(array("order" => "id ASC",
                                                               "where" => array("id not" => Config::current()->guest_group)))));
        }

        /**
         * Function: update_user
         * Updates a user when the form is submitted.
         */
        public function update_user() {
            if (empty($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to edit a user."));

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            $visitor = Visitor::current();

            if (!$visitor->group->can("edit_user"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit users."));

            $check_name = new User(null, array("where" => array("login" => $_POST['login'],
                                                                "id not" => $_POST['id'])));

            if (!$check_name->no_results)
                Flash::notice(_f("Login &#8220;%s&#8221; is already in use.", array($_POST['login'])),
                              "/admin/?action=edit_user&id=".$_POST['id']);

            $user = new User($_POST['id']);

            if ($user->no_results)
                Flash::warning(__("User not found."), "/admin/?action=manage_user");

            $password = (!empty($_POST['new_password1']) and $_POST['new_password1'] == $_POST['new_password2']) ?
                            User::hashPassword($_POST['new_password1']) :
                            $user->password ;

            $website = (!empty($_POST['website']) and strpos($_POST['website'], '://') === false) ?
                           $_POST['website'] = 'http://' . $_POST['website'] :
                           $_POST['website'] ;

            $user->update($_POST['login'], $password, $_POST['email'], $_POST['full_name'], $website, $_POST['group']);

            if ($_POST['id'] == $visitor->id)
                $_SESSION['password'] = $password;

            Flash::notice(__("User updated."), "/admin/?action=manage_users");
        }

        /**
         * Function: delete_user
         * User deletion.
         */
        public function delete_user() {
            if (!Visitor::current()->group->can("delete_user"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete users."));

            if (empty($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a user."));

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

            if (empty($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a user."));

            if ($_POST['destroy'] == "bollocks")
                redirect("/admin/?action=manage_users");

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
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
                                                          25)));
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

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

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

            if (empty($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to edit a group."));

            $this->display("edit_group",
                           array("group" => new Group($_GET['id']),
                                 "permissions" => SQL::current()->select("permissions", "*", array("group_id" => 0))->fetchAll()));
        }

        /**
         * Function: update_group
         * Updates a group when the form is submitted.
         */
        public function update_group() {
            if (!Visitor::current()->group->can("edit_group"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit groups."));

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            $permissions = array_keys($_POST['permissions']);

            $check_name = new Group(null, array("where" => array("name" => $_POST['name'],
                                                                 "id not" => $_POST['id'])));

            if (!$check_name->no_results)
                Flash::notice(_f("Group name &#8220;%s&#8221; is already in use.", array($_POST['name'])),
                              "/admin/?action=edit_group&id=".$_POST['id']);

            $group = new Group($_POST['id']);

            if ($group->no_results)
                Flash::warning(__("Group not found."), "/admin/?action=manage_groups");

            $group->update($_POST['name'], $permissions);

            Flash::notice(__("Group updated."), "/admin/?action=manage_groups");
        }

        /**
         * Function: delete_group
         * Group deletion (confirm page).
         */
        public function delete_group() {
            if (!Visitor::current()->group->can("delete_group"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete groups."));

            if (empty($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a group."));

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

            if (!isset($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a group."));

            if ($_POST['destroy'] == "bollocks")
                redirect("/admin/?action=manage_groups");

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
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
                    $groups = new Paginator(array($user->group), 10);
                else
                    $groups = new Paginator(array(), 10);
            } else
                $groups = new Paginator(Group::find(array("placeholders" => true, "order" => "id ASC")), 10);

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

            if (empty($_POST))
                return $this->display("export");

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

                $posts_atom = '<?xml version="1.0" encoding="utf-8"?>'."\r";
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

                $pages_atom = '<?xml version="1.0" encoding="utf-8"?>'."\r";
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

                    foreach (array("show_in_list", "list_order", "clean", "url") as $attr)
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

                $groups_yaml = array("groups" => array(),
                                     "permissions" => array());

                foreach (SQL::current()->select("permissions", "*", array("group_id" => 0))->fetchAll() as $permission)
                    $groups_yaml["permissions"][$permission["id"]] = $permission["name"];

                foreach ($groups as $index => $group)
                    $groups_yaml["groups"][$group->name] = $group->permissions;

                $exports["groups.yaml"] = YAML::dump($groups_yaml);
            }

            if (isset($_POST['users'])) {
                list($where, $params) = keywords($_POST['filter_users'], "login LIKE :query OR full_name LIKE :query OR email LIKE :query OR website LIKE :query", "users");

                $users = User::find(array("where" => $where, "params" => $params, "order" => "id ASC"));

                $users_yaml = array();
                foreach ($users as $user) {
                    $users_yaml[$user->login] = array();

                    foreach ($user as $name => $attr)
                        if (!in_array($name, array("no_results", "group_id", "group", "id", "login", "belongs_to", "has_many", "has_one", "queryString")))
                            $users_yaml[$user->login][$name] = $attr;
                        elseif ($name == "group_id")
                            $users_yaml[$user->login]["group"] = $user->group->name;
                }

                $exports["users.yaml"] = YAML::dump($users_yaml);
            }

            $trigger->filter($exports, "export");

            require INCLUDES_DIR."/lib/zip.php";

            $zip = new ZipFile();
            foreach ($exports as $filename => $content)
                $zip->addFile($content, $filename);

            $zip_contents = $zip->file();

            $filename = sanitize(camelize($config->name), false, true)."_Export_".date("Y-m-d");
            header("Content-type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"".$filename.".zip\"");
            header("Content-length: ".strlen($zip_contents)."\n\n");

            echo $zip_contents;
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
            if (empty($_POST))
                redirect("/admin/?action=import");

            if (!Visitor::current()->group->can("add_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to import content."));

            if (isset($_FILES['posts_file']) and $_FILES['posts_file']['error'] == 0)
                if (!$posts = simplexml_load_file($_FILES['posts_file']['tmp_name']) or $posts->generator != "Chyrp")
                    Flash::warning(__("Chyrp Posts export file is invalid."), "/admin/?action=import");

            if (isset($_FILES['pages_file']) and $_FILES['pages_file']['error'] == 0)
                if (!$pages = simplexml_load_file($_FILES['pages_file']['tmp_name']) or $pages->generator != "Chyrp")
                    Flash::warning(__("Chyrp Pages export file is invalid."), "/admin/?action=import");

            if (ini_get("memory_limit") < 20)
                ini_set("memory_limit", "20M");

            $trigger = Trigger::current();
            $visitor = Visitor::current();
            $sql = SQL::current();

            function media_url_scan(&$value) {
                $config = Config::current();

                $regexp_url = preg_quote($_POST['media_url'], "/");
                if (preg_match_all("/{$regexp_url}([^\.\!,\?;\"\'<>\(\)\[\]\{\}\s\t ]+)\.([a-zA-Z0-9]+)/", $value, $media))
                    foreach ($media[0] as $matched_url) {
                        $filename = upload_from_url($matched_url);
                        $value = str_replace($matched_url, $config->url.$config->uploads_path.$filename, $value);
                    }
            }

            if (isset($_FILES['groups_file']) and $_FILES['groups_file']['error'] == 0) {
                $import = YAML::load($_FILES['groups_file']['tmp_name']);

                foreach ($import["groups"] as $name => $permissions)
                    if (!$sql->count("groups", array("name" => $name)))
                        $trigger->call("import_chyrp_group", Group::add($name, (array) $permissions));

                foreach ($import["permissions"] as $id => $name)
                    if (!$sql->count("permissions", array("id" => $id)))
                        $sql->insert("permissions", array("id" => $id, "name" => $name));
            }

            if (isset($_FILES['users_file']) and $_FILES['users_file']['error'] == 0) {
                $users = YAML::load($_FILES['users_file']['tmp_name']);

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

            if (isset($_FILES['posts_file']) and $_FILES['posts_file']['error'] == 0)
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
                                      ($entry->updated == $entry->published) ?
                                          null :
                                          datetime($entry->updated),
                                      "",
                                      false);

                    $trigger->call("import_chyrp_post", $entry, $post);
                }

            if (isset($_FILES['pages_file']) and $_FILES['pages_file']['error'] == 0)
                foreach ($pages->entry as $entry) {
                    $chyrp = $entry->children("http://chyrp.net/export/1.0/");
                    $attr  = $entry->attributes("http://chyrp.net/export/1.0/");

                    $login = $entry->author->children("http://chyrp.net/export/1.0/")->login;
                    $user_id = $sql->select("users", "id", array("login" => $login), "id DESC")->fetchColumn();

                    $page = Page::add($entry->title,
                                      $entry->content,
                                      ($user_id ? $user_id : $visitor->id),
                                      $attr->parent_id,
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
         * Function: import_wordpress
         * WordPress importing.
         */
        public function import_wordpress() {
            if (empty($_POST))
                redirect("/admin/?action=import");

            if (!Visitor::current()->group->can("add_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to import content."));

            $config = Config::current();

            if (!in_array("text", $config->enabled_feathers))
                error(__("Missing Feather"), __("Importing from WordPress requires the Text feather to be installed and enabled."));

            if (ini_get("memory_limit") < 20)
                ini_set("memory_limit", "20M");

            $trigger = Trigger::current();

            $stupid_xml = file_get_contents($_FILES['xml_file']['tmp_name']);
            $sane_xml = preg_replace(array("/<wp:comment_content>/", "/<\/wp:comment_content>/"),
                                     array("<wp:comment_content><![CDATA[", "]]></wp:comment_content>"),
                                     $stupid_xml);

            $sane_xml = str_replace(array("<![CDATA[<![CDATA[", "]]>]]>"),
                                    array("<![CDATA[", "]]>"),
                                    $sane_xml);

            $sane_xml = str_replace(array("xmlns:excerpt=\"http://wordpress.org/excerpt/1.0/\"",
                                          "xmlns:excerpt=\"http://wordpress.org/export/1.1/excerpt/\""),
                                    "xmlns:excerpt=\"http://wordpress.org/export/1.2/excerpt/\"",
                                    $sane_xml);
            $sane_xml = str_replace(array("xmlns:wp=\"http://wordpress.org/export/1.0/\"",
                                          "xmlns:wp=\"http://wordpress.org/export/1.1/\""),
                                    "xmlns:wp=\"http://wordpress.org/export/1.2/\"",
                                    $sane_xml);

            if (!substr_count($sane_xml, "xmlns:excerpt"))
                $sane_xml = preg_replace("/xmlns:content=\"([^\"]+)\"(\s+)/m",
                                         "xmlns:content=\"\\1\"\\2xmlns:excerpt=\"http://wordpress.org/export/1.2/excerpt/\"\\2",
                                         $sane_xml);

            $fix_amps_count = 1;
            while ($fix_amps_count)
                $sane_xml = preg_replace("/<wp:meta_value>(.+)&(?!amp;)(.+)<\/wp:meta_value>/m",
                                         "<wp:meta_value>\\1&amp;\\2</wp:meta_value>",
                                         $sane_xml, -1, $fix_amps_count);

            # Remove null (x00) characters
            $sane_xml = str_replace("", "", $sane_xml);

            $xml = simplexml_load_string($sane_xml, "SimpleXMLElement", LIBXML_NOCDATA);

            if (!$xml or !substr_count($xml->channel->generator, "wordpress.org"))
                Flash::warning(__("File does not seem to be a valid WordPress export file, or could not be parsed. Please check your PHP error log."),
                               "/admin/?action=import");

            foreach ($xml->channel->item as $item) {
                $wordpress = $item->children("http://wordpress.org/export/1.2/");
                $content   = $item->children("http://purl.org/rss/1.0/modules/content/");
                if ($wordpress->status == "attachment" or $item->title == "zz_placeholder")
                    continue;

                $regexp_url = preg_quote($_POST['media_url'], "/");
                if (!empty($_POST['media_url']) and
                    preg_match_all("/{$regexp_url}([^\.\!,\?;\"\'<>\(\)\[\]\{\}\s\t ]+)\.([a-zA-Z0-9]+)/",
                                   $content->encoded,
                                   $media))
                    foreach ($media[0] as $matched_url) {
                        $filename = upload_from_url($matched_url);
                        $content->encoded = str_replace($matched_url, $config->url.$config->uploads_path.$filename, $content->encoded);
                    }

                $clean = (isset($wordpress->post_name)) ? $wordpress->post_name : sanitize($item->title) ;

                $pinned = (isset($wordpress->is_sticky)) ? $wordpress->is_sticky : 0 ;

                if (empty($wordpress->post_type) or $wordpress->post_type == "post") {
                    $status_translate = array("publish" => "public",
                                              "draft"   => "draft",
                                              "private" => "private",
                                              "static"  => "public",
                                              "object"  => "public",
                                              "inherit" => "public",
                                              "future"  => "draft",
                                              "pending" => "draft");

                    $data = array("title" => trim($item->title),
                                  "body" => trim($content->encoded),
                                  "imported_from" => "wordpress");

                    $post = Post::add($data,
                                      $clean,
                                      Post::check_url($clean),
                                      "text",
                                      null,
                                      $pinned,
                                      $status_translate[(string) $wordpress->status],
                                      (string) ($wordpress->post_date == "0000-00-00 00:00:00" ? datetime() : $wordpress->post_date),
                                      null,
                                      "",
                                      false);

                    $trigger->call("import_wordpress_post", $item, $post);
                } elseif ($wordpress->post_type == "page") {
                    $page = Page::add(trim($item->title),
                                      trim($content->encoded),
                                      null,
                                      0,
                                      true,
                                      0,
                                      $clean,
                                      Page::check_url($clean),
                                      (string) ($wordpress->post_date == "0000-00-00 00:00:00" ? datetime() : $wordpress->post_date));

                    $trigger->call("import_wordpress_page", $item, $page);
                }
            }

            Flash::notice(__("WordPress content successfully imported!"), "/admin/?action=import");
        }

        /**
         * Function: import_tumblr
         * Tumblr importing.
         */
        public function import_tumblr() {
            if (empty($_POST))
                redirect("/admin/?action=import");

            if (!Visitor::current()->group->can("add_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to import content."));

            $config = Config::current();
            if (!in_array("text", $config->enabled_feathers) or
                !in_array("video", $config->enabled_feathers) or
                !in_array("audio", $config->enabled_feathers) or
                !in_array("chat", $config->enabled_feathers) or
                !in_array("photo", $config->enabled_feathers) or
                !in_array("quote", $config->enabled_feathers) or
                !in_array("link", $config->enabled_feathers))
                error(__("Missing Feather"), __("Importing from Tumblr requires the Text, Video, Audio, Chat, Photo, Quote, and Link feathers to be installed and enabled."));

            if (ini_get("memory_limit") < 20)
                ini_set("memory_limit", "20M");

            if (!parse_url($_POST['tumblr_url'], PHP_URL_SCHEME))
                $_POST['tumblr_url'] = "http://".$_POST['tumblr_url'];

            set_time_limit(3600);
            $url = rtrim($_POST['tumblr_url'], "/")."/api/read?num=50";
            $api = preg_replace("/<(\/?)([a-z]+)\-([a-z]+)/", "<\\1\\2_\\3", get_remote($url));
            $api = preg_replace("/ ([a-z]+)\-([a-z]+)=/", " \\1_\\2=", $api);
            $xml = simplexml_load_string($api);

            if (!isset($xml->tumblelog))
                Flash::warning(_f("Content could not be retrieved from the given URL. ". get_remote($url)),
                                  "/admin/?action=import");

            $already_in = $posts = array();
            foreach ($xml->posts->post as $post) {
                $posts[] = $post;
                $already_in[] = $post->attributes()->id;
            }

            while ($xml->posts->attributes()->total > count($posts)) {
                set_time_limit(3600);
                $api = preg_replace("/<(\/?)([a-z]+)\-([a-z]+)/", "<\\1\\2_\\3", get_remote($url."&start=".count($posts)));
                $api = preg_replace("/ ([a-z]+)\-([a-z]+)=/", " \\1_\\2=", $api);
                $xml = simplexml_load_string($api, "SimpleXMLElement", LIBXML_NOCDATA);
                foreach ($xml->posts->post as $post)
                    if (!in_array($post->attributes()->id, $already_in)) {
                        $posts[] = $post;
                        $already_in[] = $post->attributes()->id;
                    }
            }

            function reverse($a, $b) {
                if (empty($a) or empty($b)) return 0;
                return (strtotime($a->attributes()->date) < strtotime($b->attributes()->date)) ? -1 : 1 ;
            }

            set_time_limit(3600);
            usort($posts, "reverse");

            foreach ($posts as $key => $post) {
                set_time_limit(3600);
                if ($post->attributes()->type == "audio")
                    break; # Can't import Audio posts since Tumblr has the files locked in to Amazon.

                $translate_types = array("regular" => "text", "conversation" => "chat");

                $clean = "";
                switch($post->attributes()->type) {
                    case "regular":
                        $title = fallback($post->regular_title);
                        $values = array("title" => $title,
                                        "body" => $post->regular_body);
                        $clean = sanitize($title);
                        break;
                    case "video":
                        $values = array("embed" => $post->video_player,
                                        "caption" => fallback($post->video_caption));
                        break;
                    case "conversation":
                        $title = fallback($post->conversation_title);

                        $lines = array();
                        foreach ($post->conversation_line as $line)
                            $lines[] = $line->attributes()->label." ".$line;

                        $values = array("title" => $title,
                                        "dialogue" => implode("\n", $lines));
                        $clean = sanitize($title);
                        break;
                    case "photo":
                        $values = array("filename" => upload_from_url($post->photo_url[0]),
                                        "caption" => fallback($post->photo_caption));
                        break;
                    case "quote":
                        $values = array("quote" => $post->quote_text,
                                        "source" => preg_replace("/^&mdash; /", "",
                                                                 fallback($post->quote_source)));
                        break;
                    case "link":
                        $name = fallback($post->link_text);
                        $values = array("name" => $name,
                                        "source" => $post->link_url,
                                        "description" => fallback($post->link_description));
                        $clean = sanitize($name);
                        break;
                }

                $values["imported_from"] = "tumblr";

                $new_post = Post::add($values,
                                      $clean,
                                      Post::check_url($clean),
                                      fallback($translate_types[(string) $post->attributes()->type], (string) $post->attributes()->type),
                                      null,
                                      null,
                                      "public",
                                      datetime((int) $post->attributes()->unix_timestamp),
                                      null,
                                      "",
                                      false);

                Trigger::current()->call("import_tumble", $post, $new_post);
            }

            Flash::notice(__("Tumblr content successfully imported!"), "/admin/?action=import");
        }

        /**
         * Function: import_textpattern
         * TextPattern importing.
         */
        public function import_textpattern() {
            if (empty($_POST))
                redirect("/admin/?action=import");

            if (!Visitor::current()->group->can("add_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to import content."));

            $config = Config::current();
            $trigger = Trigger::current();

            $dbcon = $dbsel = false;
            if ($link = @mysql_connect($_POST['host'], $_POST['username'], $_POST['password'])) {
                $dbcon = true;
                $dbsel = @mysql_select_db($_POST['database'], $link);
            }

            if (!$dbcon or !$dbsel)
                Flash::warning(__("Could not connect to the specified TextPattern database."),
                               "/admin/?action=import");

            mysql_query("SET NAMES 'utf8'");

            $get_posts = mysql_query("SELECT * FROM {$_POST['prefix']}textpattern ORDER BY ID ASC", $link) or error(__("Database Error"), mysql_error());
            $posts = array();
            while ($post = mysql_fetch_array($get_posts))
                $posts[$post["ID"]] = $post;

            foreach ($posts as $post) {
                $regexp_url = preg_quote($_POST['media_url'], "/");
                if (!empty($_POST['media_url']) and
                    preg_match_all("/{$regexp_url}([^\.\!,\?;\"\'<>\(\)\[\]\{\}\s\t ]+)\.([a-zA-Z0-9]+)/",
                                   $post["Body"],
                                   $media))
                    foreach ($media[0] as $matched_url) {
                        $filename = upload_from_url($matched_url);
                        $post["Body"] = str_replace($matched_url, $config->url.$config->uploads_path.$filename, $post["Body"]);
                    }

                $status_translate = array(1 => "draft",
                                          2 => "private",
                                          3 => "draft",
                                          4 => "public",
                                          5 => "public");

                $clean = fallback($post["url_title"], sanitize($post["Title"]));

                $new_post = Post::add(array("title" => $post["Title"],
                                            "body" => $post["Body"],
                                            "imported_from" => "textpattern"),
                                      $clean,
                                      Post::check_url($clean),
                                      "text",
                                      null,
                                      ($post["Status"] == "5"),
                                      $status_translate[$post["Status"]],
                                      $post["Posted"],
                                      null,
                                      "",
                                      false);

                $trigger->call("import_textpattern_post", $post, $new_post);
            }

            mysql_close($link);

            Flash::notice(__("TextPattern content successfully imported!"), "/admin/?action=import");
        }

        /**
         * Function: import_movabletype
         * MovableType importing.
         */
        public function import_movabletype() {
            if (empty($_POST))
                redirect("/admin/?action=import");

            if (!Visitor::current()->group->can("add_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to import content."));

            $config = Config::current();
            $trigger = Trigger::current();

            $dbcon = $dbsel = false;
            if ($link = @mysql_connect($_POST['host'], $_POST['username'], $_POST['password'])) {
                $dbcon = true;
                $dbsel = @mysql_select_db($_POST['database'], $link);
            }

            if (!$dbcon or !$dbsel)
                Flash::warning(__("Could not connect to the specified MovableType database."),
                               "/admin/?action=import");

            mysql_query("SET NAMES 'utf8'");

            $get_authors = mysql_query("SELECT * FROM mt_author ORDER BY author_id ASC", $link) or error(__("Database Error"), mysql_error());
            $users = array();
            while ($author = mysql_fetch_array($get_authors)) {
                # Try to figure out if this author is the same as the person doing the import.
                if ($author["author_name"] == Visitor::current()->login or
                    $author["author_nickname"] == Visitor::current()->login or
                    $author["author_nickname"] == Visitor::current()->full_name or
                    $author["author_url"] == Visitor::current()->website or
                    $author["author_email"] == Visitor::current()->email)
                    $users[$author["author_id"]] = Visitor::current();
                else
                    $users[$author["author_id"]] = User::add($author["author_name"],
                                                             $author["author_password"],
                                                             $author["author_email"],
                                                             ($author["author_nickname"] != $author["author_name"] ?
                                                                 $author["author_nickname"] :
                                                                 ""),
                                                             $author["author_url"],
                                                             ($author["author_can_create_blog"] == "1" ?
                                                                 Visitor::current()->group :
                                                                 null),
                                                             $author["author_created_on"],
                                                             false);
            }

            $get_posts = mysql_query("SELECT * FROM mt_entry ORDER BY entry_id ASC", $link) or error(__("Database Error"), mysql_error());
            $posts = array();
            while ($post = mysql_fetch_array($get_posts))
                $posts[$post["entry_id"]] = $post;

            foreach ($posts as $post) {
                $body = $post["entry_text"];

                if (!empty($post["entry_text_more"]))
                    $body.= "\n\n<!--more-->\n\n".$post["entry_text_more"];

                $regexp_url = preg_quote($_POST['media_url'], "/");
                if (!empty($_POST['media_url']) and
                    preg_match_all("/{$regexp_url}([^\.\!,\?;\"\'<>\(\)\[\]\{\}\s\t ]+)\.([a-zA-Z0-9]+)/",
                                   $body,
                                   $media))
                    foreach ($media[0] as $matched_url) {
                        $filename = upload_from_url($matched_url);
                        $body = str_replace($matched_url, $config->url.$config->uploads_path.$filename, $body);
                    }

                $status_translate = array(1 => "draft",
                                          2 => "public",
                                          3 => "draft",
                                          4 => "draft");

                $clean = oneof($post["entry_basename"], sanitize($post["entry_title"]));

                if (empty($post["entry_class"]) or $post["entry_class"] == "entry") {
                    $new_post = Post::add(array("title" => $post["entry_title"],
                                                "body" => $body,
                                                "imported_from" => "movabletype"),
                                          $clean,
                                          Post::check_url($clean),
                                          "text",
                                          @$users[$post["entry_author_id"]],
                                          false,
                                          $status_translate[$post["entry_status"]],
                                          oneof(@$post["entry_authored_on"], @$post["entry_created_on"], datetime()),
                                          $post["entry_modified_on"],
                                          "",
                                          false);
                    $trigger->call("import_movabletype_post", $post, $new_post, $link);
                } elseif (@$post["entry_class"] == "page") {
                    $new_page = Page::add($post["entry_title"], $body, null, 0, true, 0, $clean, Page::check_url($clean));
                    $trigger->call("import_movabletype_page", $post, $new_page, $link);
                }
            }

            mysql_close($link);

            Flash::notice(__("MovableType content successfully imported!"), "/admin/?action=import");
        }

        /**
         * Function: modules
         * Module enabling/disabling.
         */
        public function modules() {
            if (!Visitor::current()->group->can("toggle_extensions"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable modules."));

            $config = Config::current();

            $this->context["enabled_modules"] = $this->context["disabled_modules"] = array();

            if (!$open = @opendir(MODULES_DIR))
                return Flash::warning(__("Could not read modules directory."));

            $classes = array();

            while (($folder = readdir($open)) !== false) {
                if (!file_exists(MODULES_DIR."/".$folder."/".$folder.".php") or !file_exists(MODULES_DIR."/".$folder."/info.yaml")) continue;

                if (file_exists(MODULES_DIR."/".$folder."/locale/".$config->locale.".mo"))
                    load_translator($folder, MODULES_DIR."/".$folder."/locale/".$config->locale.".mo");

                if (!isset($classes[$folder]))
                    $classes[$folder] = array($folder);
                else
                    array_unshift($classes[$folder], $folder);

                $info = YAML::load(MODULES_DIR."/".$folder."/info.yaml");

                $info["conflicts_true"] = array();
                $info["depends_true"] = array();

                if (!empty($info["conflicts"])) {
                    $classes[$folder][] = "conflict";

                    foreach ((array) $info["conflicts"] as $conflict)
                        if (file_exists(MODULES_DIR."/".$conflict."/".$conflict.".php"))
                            $classes[$folder][] = "conflict_".$conflict;
                }

                $dependencies_needed = array();
                if (!empty($info["depends"])) {
                    $classes[$folder][] = "depends";

                    foreach ((array) $info["depends"] as $dependency) {
                        if (!module_enabled($dependency)) {
                            if (!in_array("missing_dependency", $classes[$folder]))
                                $classes[$folder][] = "missing_dependency";

                            $classes[$folder][] = "needs_".$dependency;

                            $dependencies_needed[] = $dependency;
                        }

                        $classes[$folder][] = "depends_".$dependency;

                        fallback($classes[$dependency], array());
                        $classes[$dependency][] = "depended_by_".$folder;
                    }
                }

                fallback($info["name"], $folder);
                fallback($info["version"], "0");
                fallback($info["url"]);
                fallback($info["description"]);
                fallback($info["author"], array("name" => "", "url" => ""));
                fallback($info["help"]);

                $info["description"] = __($info["description"], $folder);

                $info["description"] = preg_replace_callback("/<code>(.+)<\/code>/s",
                                                             function ($matches) {
                                                                 return "<code>".fix($matches[1])."</code>";
                                                             },
                                                             $info["description"]);
                $info["description"] = preg_replace_callback("/<pre>(.+)<\/pre>/s",
                                                             function ($matches) {
                                                                 return "<pre>".fix($matches[1])."</pre>";
                                                             },
                                                             $info["description"]);

                $info["author"]["link"] = !empty($info["author"]["url"]) ?
                                              '<a href="'.fix($info["author"]["url"]).'">'.fix($info["author"]["name"]).'</a>' :
                                              $info["author"]["name"] ;

                $category = (module_enabled($folder)) ? "enabled_modules" : "disabled_modules" ;
                $this->context[$category][$folder] = array("name" => $info["name"],
                                                           "version" => $info["version"],
                                                           "url" => $info["url"],
                                                           "description" => $info["description"],
                                                           "author" => $info["author"],
                                                           "help" => $info["help"],
                                                           "classes" => $classes[$folder],
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
                show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable feathers."));

            $config = Config::current();

            $this->context["enabled_feathers"] = $this->context["disabled_feathers"] = array();

            if (!$open = @opendir(FEATHERS_DIR))
                return Flash::warning(__("Could not read feathers directory."));

            while (($folder = readdir($open)) !== false) {
                if (!file_exists(FEATHERS_DIR."/".$folder."/".$folder.".php") or !file_exists(FEATHERS_DIR."/".$folder."/info.yaml"))
                    continue;

                if (file_exists(FEATHERS_DIR."/".$folder."/locale/".$config->locale.".mo"))
                    load_translator($folder, FEATHERS_DIR."/".$folder."/locale/".$config->locale.".mo");

                $info = YAML::load(FEATHERS_DIR."/".$folder."/info.yaml");

                fallback($info["name"], $folder);
                fallback($info["version"], "0");
                fallback($info["url"]);
                fallback($info["description"]);
                fallback($info["author"], array("name" => "", "url" => ""));
                fallback($info["help"]);

                $info["description"] = __($info["description"], $folder);

                $info["description"] = preg_replace_callback("/<code>(.+)<\/code>/s",
                                                             function ($matches) {
                                                                 return "<code>".fix($matches[1])."</code>";
                                                             },
                                                             $info["description"]);
                $info["description"] = preg_replace_callback("/<pre>(.+)<\/pre>/s",
                                                             function ($matches) {
                                                                 return "<pre>".fix($matches[1])."</pre>";
                                                             },
                                                             $info["description"]);

                $info["author"]["link"] = !empty($info["author"]["url"]) ?
                                              '<a href="'.fix($info["author"]["url"]).'">'.fix($info["author"]["name"]).'</a>' :
                                              $info["author"]["name"] ;

                $category = (feather_enabled($folder)) ? "enabled_feathers" : "disabled_feathers" ;
                $this->context[$category][$folder] = array("name" => $info["name"],
                                                           "version" => $info["version"],
                                                           "url" => $info["url"],
                                                           "description" => $info["description"],
                                                           "author" => $info["author"],
                                                           "help" => $info["help"]);
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

            $config = Config::current();

            $this->context["preview"] = !empty($_SESSION['theme']) ? $_SESSION['theme'] : "" ;

            $this->context["themes"] = array();

            if (!$open = @opendir(THEMES_DIR))
                return Flash::warning(__("Could not read themes directory."));

            while (($folder = readdir($open)) !== false) {
                if (!file_exists(THEMES_DIR."/".$folder."/info.yaml"))
                    continue;

                if (file_exists(THEMES_DIR."/".$folder."/locale/".$config->locale.".mo"))
                    load_translator($folder, THEMES_DIR."/".$folder."/locale/".$config->locale.".mo");

                $info = YAML::load(THEMES_DIR."/".$folder."/info.yaml");

                fallback($info["name"], $folder);
                fallback($info["version"], "0");
                fallback($info["url"]);
                fallback($info["description"]);
                fallback($info["author"], array("name" => "", "url" => ""));

                $info["author"]["link"] = !empty($info["author"]["url"]) ?
                    '<a href="'.$info["author"]["url"].'">'.$info["author"]["name"].'</a>' :
                    $info["author"]["name"] ;

                $info["description"] = preg_replace_callback("/<code>(.+)<\/code>/s",
                                                             function ($matches) {
                                                                 return "<code>".fix($matches[1])."</code>";
                                                             },
                                                             $info["description"]);
                $info["description"] = preg_replace_callback("/<pre>(.+)<\/pre>/s",
                                                             function ($matches) {
                                                                 return "<pre>".fix($matches[1])."</pre>";
                                                             },
                                                             $info["description"]);

                $this->context["themes"][] = array("name" => $folder,
                                                   "screenshot" => (file_exists(THEMES_DIR."/".$folder."/screenshot.png") ?
                                                                        $config->chyrp_url."/themes/".$folder."/screenshot.png" :
                                                                        ""),
                                                   "info" => $info);
            }

            if (!$open = @opendir(ADMIN_THEMES_DIR))
                return Flash::warning(__("Could not read themes directory."));

            while (($folder = readdir($open)) !== false) {
                if (!file_exists(ADMIN_THEMES_DIR."/".$folder."/info.yaml"))
                    continue;

                if (file_exists(ADMIN_THEMES_DIR."/".$folder."/locale/".$config->locale.".mo"))
                    load_translator($folder, ADMIN_THEMES_DIR."/".$folder."/locale/".$config->locale.".mo");

                $info = YAML::load(ADMIN_THEMES_DIR."/".$folder."/info.yaml");

                fallback($info["name"], $folder);
                fallback($info["version"], "0");
                fallback($info["url"]);
                fallback($info["description"]);
                fallback($info["author"], array("name" => "", "url" => ""));

                $info["author"]["link"] = !empty($info["author"]["url"]) ?
                    '<a href="'.$info["author"]["url"].'">'.$info["author"]["name"].'</a>' :
                    $info["author"]["name"] ;
                $info["description"] = preg_replace("/<code>(.+)<\/code>/se",
                                                    "'<code>'.fix('\\1').'</code>'",
                                                    $info["description"]);

                $info["description"] = preg_replace("/<pre>(.+)<\/pre>/se",
                                                    "'<pre>'.fix('\\1').'</pre>'",
                                                    $info["description"]);

                $this->context["admin_themes"][] = array("name" => $folder,
                                                         "screenshot" => (file_exists(ADMIN_THEMES_DIR."/".$folder."/screenshot.png") ?
                                                         $config->chyrp_url."/admin/themes/".$folder."/screenshot.png" :
                                                         ""),
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

            $type = (isset($_GET['module'])) ? "module" : "feather" ;

            if (!$visitor->group->can("toggle_extensions"))
                if ($type == "module")
                    show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable modules."));
                else
                    show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable feathers."));

            if ($type == "module" and module_enabled($_GET[$type]))
                Flash::warning(__("Module already enabled."), "/admin/?action=modules");

            if ($type == "feather" and feather_enabled($_GET[$type]))
                Flash::warning(__("Feather already enabled."), "/admin/?action=feathers");

            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $folder        = ($type == "module") ? MODULES_DIR : FEATHERS_DIR ;

            require $folder."/".$_GET[$type]."/".$_GET[$type].".php";

            $class_name = camelize($_GET[$type]);

            if ($type == "module" and !is_subclass_of($class_name, "Modules"))
                Flash::warning(__("Item is not a module."), "/admin/?action=modules");

            if ($type == "feather" and !is_subclass_of($class_name, "Feathers"))
                Flash::warning(__("Item is not a feather."), "/admin/?action=feathers");

            if (method_exists($class_name, "__install"))
                call_user_func(array($class_name, "__install"));

            $new = $config->$enabled_array;
            array_push($new, $_GET[$type]);
            $config->set($enabled_array, $new);

            if (file_exists($folder."/".$_GET[$type]."/locale/".$config->locale.".mo"))
                load_translator($_GET[$type], $folder."/".$_GET[$type]."/locale/".$config->locale.".mo");

            $info = YAML::load($folder."/".$_GET[$type]."/info.yaml");
            fallback($info["uploader"], false);
            fallback($info["notifications"], array());

            foreach ($info["notifications"] as &$notification)
                $notification = __($notification, $_GET[$type]);

            if ($info["uploader"])
                if (!file_exists(MAIN_DIR.$config->uploads_path))
                    $info["notifications"][] = _f("Please create the <code>%s</code> directory at your Chyrp install's root and CHMOD it to 777.", array($config->uploads_path));
                elseif (!is_writable(MAIN_DIR.$config->uploads_path))
                    $info["notifications"][] = _f("Please CHMOD <code>%s</code> to 777.", array($config->uploads_path));

            foreach ($info["notifications"] as $message)
                Flash::message($message);

            if ($type == "module")
                Flash::notice(_f("&#8220;%s&#8221; module enabled.",
                                 array($info["name"])),
                              "/admin/?action=".pluralize($type));
            elseif ($type == "feather")
                Flash::notice(_f("&#8220;%s&#8221; feather enabled.",
                                 array($info["name"])),
                              "/admin/?action=".pluralize($type));
        }

        /**
         * Function: disable
         * Disables a module or feather.
         */
        public function disable() {
            $config  = Config::current();
            $visitor = Visitor::current();

            $type = (isset($_GET['module'])) ? "module" : "feather" ;

            if (!$visitor->group->can("toggle_extensions"))
                if ($type == "module")
                    show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable modules."));
                else
                    show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable feathers."));

            if ($type == "module" and !module_enabled($_GET[$type]))
                Flash::warning(__("Module already disabled."), "/admin/?action=modules");

            if ($type == "feather" and !feather_enabled($_GET[$type]))
                Flash::warning(__("Feather already disabled."), "/admin/?action=feathers");

            $enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
            $folder        = ($type == "module") ? MODULES_DIR : FEATHERS_DIR ;

            $class_name = camelize($_GET[$type]);
            if (method_exists($class_name, "__uninstall"))
                call_user_func(array($class_name, "__uninstall"), false);

            $config->set(($type == "module" ? "enabled_modules" : "enabled_feathers"),
                         array_diff($config->$enabled_array, array($_GET[$type])));

            $info = YAML::load($folder."/".$_GET[$type]."/info.yaml");
            if ($type == "module")
                Flash::notice(_f("&#8220;%s&#8221; module disabled.",
                                 array($info["name"])),
                              "/admin/?action=".pluralize($type));
            elseif ($type == "feather")
                Flash::notice(_f("&#8220;%s&#8221; feather disabled.",
                                 array($info["name"])),
                              "/admin/?action=".pluralize($type));
        }

        /**
         * Function: change_theme
         * Changes the theme.
         */
        public function change_theme() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (empty($_GET['theme']))
                error(__("No Theme Specified"), __("You did not specify a theme to switch to."));

            $config = Config::current();
            $config->set("theme", $_GET['theme']);

            if (file_exists(THEMES_DIR."/".$_GET['theme']."/locale/".$config->locale.".mo"))
                load_translator($_GET['theme'], THEMES_DIR."/".$_GET['theme']."/locale/".$config->locale.".mo");

            $info = YAML::load(THEMES_DIR."/".$_GET['theme']."/info.yaml");
            fallback($info["notifications"], array());

            foreach ($info["notifications"] as &$notification)
                $notification = __($notification, $_GET['theme']);

            foreach ($info["notifications"] as $message)
                Flash::message($message);

            # Clear the caches made by the previous theme.
            foreach ((array) glob(INCLUDES_DIR."/caches/*.cache") as $cache)
                @unlink($cache);

            Flash::notice(_f("Theme changed to &#8220;%s&#8221;.", array($info["name"])), "/admin/?action=themes");
        }

        /**
         * Function: change_admin_theme
         * Changes the admin theme.
         */
        public function change_admin_theme() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (empty($_GET['theme']))
                error(__("No Theme Specified"), __("You did not specify a theme to switch to."));

            $config = Config::current();
            $config->set("admin_theme", $_GET['theme']);

            if (file_exists(ADMIN_THEMES_DIR."/".$_GET['theme']."/locale/".$config->locale.".mo"))
                load_translator($_GET['theme'], ADMIN_THEMES_DIR."/".$_GET['theme']."/locale/".$config->locale.".mo");

            $info = YAML::load(ADMIN_THEMES_DIR."/".$_GET['theme']."/info.yaml");
            fallback($info["notifications"], array());

            foreach ($info["notifications"] as &$notification)
                $notification = __($notification, $_GET['theme']);

            foreach ($info["notifications"] as $message)
                Flash::message($message);

            # Clear the caches made by the previous theme.
            foreach (glob(INCLUDES_DIR."/caches/*.cache") as $cache)
                @unlink($cache);

            Flash::notice(_f("Admin theme changed to &#8220;%s&#8221;.", array($info["name"])), "/admin/?action=themes");
        }

        /**
         * Function: preview_theme
         * Previews the theme.
         */
        public function preview_theme() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to preview themes."));

            if (empty($_GET['theme']))
                error(__("No Theme Specified"), __("You did not specify a theme to preview."));

            $info = YAML::load(THEMES_DIR."/".$_GET['theme']."/info.yaml");

            # Clear the caches made by the previous theme.
            foreach (glob(INCLUDES_DIR."/caches/*.cache") as $cache)
                @unlink($cache);

            if (!empty($_SESSION['theme'])) {
                unset($_SESSION['theme']);
                Flash::notice(_f("Stopped previewing &#8220;%s&#8221;.", array($info["name"])), "/admin/?action=themes");
            } else {
                $_SESSION['theme'] = $_GET['theme'];
                Flash::notice(_f("Previewing theme &#8220;%s&#8221;. Press the theme's &#8220;Preview&#8221; button again to stop previewing.", array($info["name"])), "/");
            }
        }

        /**
         * Function: general_settings
         * General Settings page.
         */
        public function general_settings() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            $locales = array();

            if ($open = opendir(INCLUDES_DIR."/locale/")) {
                 while (($folder = readdir($open)) !== false) {
                    $split = explode(".", $folder);
                    if (end($split) == "mo")
                        $locales[] = array("code" => $split[0], "name" => lang_code($split[0]));
                }
                closedir($open);
            }

            if (empty($_POST))
                return $this->display("general_settings", array("locales" => $locales,
                                                                "timezones" => timezones()));

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            $config = Config::current();
            $set = array($config->set("name", $_POST['name']),
                         $config->set("description", $_POST['description']),
                         $config->set("chyrp_url", rtrim($_POST['chyrp_url'], "/")),
                         $config->set("url", rtrim(oneof($_POST['url'], $_POST['chyrp_url']), "/")),
                         $config->set("email", $_POST['email']),
                         $config->set("timezone", $_POST['timezone']),
                         $config->set("locale", $_POST['locale']),
                         $config->set("check_updates", !empty($_POST['check_updates'])));

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

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            $config = Config::current();
            $set = array($config->set("posts_per_page", $_POST['posts_per_page']),
                         $config->set("feed_items", $_POST['feed_items']),
                         $config->set("feed_url", $_POST['feed_url']),
                         $config->set("uploads_path", $_POST['uploads_path']),
                         $config->set("enable_trackbacking", !empty($_POST['enable_trackbacking'])),
                         $config->set("send_pingbacks", !empty($_POST['send_pingbacks'])),
                         $config->set("enable_xmlrpc", !empty($_POST['enable_xmlrpc'])),
                         $config->set("enable_ajax", !empty($_POST['enable_ajax'])),
                         $config->set("enable_emoji", !empty($_POST['enable_emoji'])));

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

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            $config = Config::current();
            $set = array($config->set("can_register", !empty($_POST['can_register'])),
                         $config->set("email_activation", !empty($_POST['email_activation'])),
                         $config->set("enable_recaptcha", !empty($_POST['enable_recaptcha'])),
                         $config->set("default_group", $_POST['default_group']),
                         $config->set("guest_group", $_POST['guest_group']));

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

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            $config = Config::current();
            $set = array($config->set("clean_urls", !empty($_POST['clean_urls'])),
                         $config->set("post_url", $_POST['post_url']));

            if (!in_array(false, $set))
                Flash::notice(__("Settings updated."), "/admin/?action=route_settings");
        }

        /**
         * Function: update
         * Chyrp Update.
         */
        public function update() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to perform the update."));

            if (isset($_GET['get_update']))
                return $this->display("update",
                                array("updating" => Update::get_update()));
            else
                return $this->display("update",
                                array("changelog" => Update::get_changelog()));
        }

        /**
         * Function: help
         * Sets the $title and $body for various help IDs.
         */
        public function help() {
            list($title, $body) = Trigger::current()->call("help_".$_GET['id']);

            switch($_GET['id']) {
                case "filtering_results":
                    $title = __("Filtering Results");
                    $body = "<p>".__("Use this to search for specific items. You can either enter plain text to match the item with, or use keywords:")."</pre>";
                    $body.= "<h2>".__("Keywords")."</h2>";
                    $body.= "<cite><strong>".__("Usage")."</strong>: <code>attr:val</code></cite>\n".__("Use this syntax to quickly match specific results. Keywords will modify the query to match items where <code>attr</code> is equal to <code>val</code> (case insensitive).");
                    break;
                case "slugs":
                    $title = __("Post Slugs");
                    $body = __("Post slugs are strings to use for the URL of a post. They are directly responsible for the <code>(url)</code> attribute in a post's clean URL, or the <code>/?action=view&amp;url=<strong>foo</strong></code> in a post's dirty URL. A post slug should not contain any special characters other than hyphens.");
                    break;
                case "trackbacks":
                    $title = __("Trackbacks");
                    $body = __("Trackbacks are special urls to posts from other blogs that your post is related to or references. The other blog will be notified of your post, and in some cases a comment will automatically be added to the post in question linking back to your post. It's basically a way to network between blogs via posts.");
                    break;
                case "alternate_urls":
                    $title = __("Alternate URL");
                    $body = "<p>".__("An alternate URL will allow you to keep Chyrp in its own directory, while having your site URLs point to someplace else. For example, you could have Chyrp in a <code>/chyrp</code> directory, and have your site at <code>/</code>. There are two requirements for this to work.")."</p>\n\n";
                    $body.= "<ol>\n\t<li>".__("Create an <code>index.php</code> file in your destination directory with the following in it:")."\n\n";
                    $body.= "<pre><code>&lt;?php
    require \"path/to/chyrp/index.php\";
?&gt;</code></pre>";
                    $body.= "</li>\n\t<li>".__("Move the .htaccess file from the original Chyrp directory, and change the <code>RewriteBase</code> line to reflect the new website location.")."</li>\n</ol>";
            }

            require "help.php";
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
                $info = YAML::load(FEATHERS_DIR."/".$feather."/info.yaml");
                $subnav["write"]["write_post&feather=".$feather] = array("title" => __($info["name"], $feather),
                                                                         "show" => $visitor->group->can("add_draft", "add_post"),
                                                                         "attributes" => ' id="list_feathers['.$feather.']"',
                                                                         "selected" => (isset($_GET['feather']) and $_GET['feather'] == $feather) or
                                                                                       (!isset($_GET['feather']) and $action == "write_post" and !$index));
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
         *     $action - The template file to display, in (theme dir)/pages.
         *     $context - Context for the template.
         *     $title - The title for the page. Defaults to a camlelization of the action, e.g. foo_bar -> Foo Bar.
         */
        public function display($action, $context = array(), $title = "") {
            $this->displayed = true;

            fallback($title, camelize($action, true));

            $this->context = array_merge($context, $this->context);

            $trigger = Trigger::current();

            $trigger->filter($this->context, array("admin_context", "admin_context_".str_replace("/", "_", $action)));

            # Are there any extension-added pages?
            foreach (array("write" => array(),
                           "manage" => array("import", "export"),
                           "settings" => array(),
                           "extend" => array("modules", "feathers", "themes")) as $main_nav => $val) {
                $$main_nav = $val;
                $trigger->filter($$main_nav, $main_nav."_pages");
            }

            $visitor = Visitor::current();
            $route   = Route::current();

            $this->context["theme"]      = Theme::current();
            $this->context["flash"]      = Flash::current();
            $this->context["trigger"]    = $trigger;
            $this->context["title"]      = $title;
            $this->context["site"]       = Config::current();
            $this->context["visitor"]    = $visitor;
            $this->context["logged_in"]  = logged_in();
            $this->context["new_update"] = Update::check_update();
            $this->context["route"]      = $route;
            $this->context["hide_admin"] = isset($_SESSION["hide_admin"]);
            $this->context["now"]        = time();
            $this->context["version"]    = CHYRP_VERSION;
            $this->context["debug"]      = DEBUG;
            $this->context["feathers"]   = Feathers::$instances;
            $this->context["modules"]    = Modules::$instances;
            $this->context["admin_theme"] = $this->admin_theme;
            $this->context["theme_url"]  = Config::current()->chyrp_url."/admin/themes/".$this->admin_theme;
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
                                                                         match(array("/_extend$/",
                                                                                     "/_editor$/"), $action)));

            $this->subnav_context($route->action);

            $trigger->filter($this->context["selected"], "nav_selected");

            $this->context["sql_debug"]  = SQL::current()->debug;

            $file = MAIN_DIR."/admin/themes/%s/pages/".$action.".twig";
            $template = file_exists(sprintf($file, $this->admin_theme)) ?
                sprintf($file, $this->admin_theme) :
                sprintf($file, "default");

            $config = Config::current();
            if (!file_exists($template)) {
                foreach (array(MODULES_DIR => $config->enabled_modules,
                               FEATHERS_DIR => $config->enabled_feathers) as $path => $try)
                    foreach ($try as $extension)
                        if (file_exists($path."/".$extension."/pages/admin/".$action.".twig"))
                            $template = $path."/".$extension."/pages/admin/".$action.".twig";

                if (!file_exists($template))
                    error(__("Template Missing"), _f("Couldn't load template: <code>%s</code>", array($template)));
            }

            # Try the theme first
            try {
                $this->theme->getTemplate($template)->display($this->context);
            } catch (Exception $t) {
                # Fallback to the default
                try {
                    $this->default->getTemplate($template)->display($this->context);
                } catch (Exception $e) {
                    $prettify = preg_replace("/([^:]+): (.+)/", "\\1: <code>\\2</code>", $e->getMessage());
                    $trace = debug_backtrace();
                    $twig = array("file" => $e->filename, "line" => $e->lineno);
                    array_unshift($trace, $twig);
                    error(__("Error"), $prettify, $trace);
                }
            }
        }

        /**
         * Function: current
         * Returns a singleton reference to the current class.
         */
        public static function & current() {
            static $instance = null;
            return $instance = (empty($instance)) ? new self() : $instance ;
        }
    }
