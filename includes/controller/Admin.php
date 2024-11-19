<?php
    /**
     * Class: AdminController
     * The logic controlling the administration console.
     */
    class AdminController extends Controllers implements Controller {
        # Array: $urls
        # An array of clean URL => dirty URL translations.
        public $urls = array(
            '|/([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)/$|'
                => '/?action=$1&amp;$2=$3&amp;$4=$5',

            '|/([^/]+)/([^/]+)/([^/]+)/([^/]+)/$|'
                => '/?action=$1&amp;$2=$3&amp;$4',

            '|/([^/]+)/([^/]+)/([^/]+)/$|'
                => '/?action=$1&amp;$2=$3',

            '|/([^/]+)/([^/]+)/$|'
                => '/?action=$1&amp;$2'
        );

        # String: $base
        # The base path for this controller.
        public $base = "admin";

        # Boolean: $feed
        # Serve a syndication feed?
        public $feed = false;

        # Variable: $twig
        # Environment for the Twig template engine.
        private $twig;

        /**
         * Function: __construct
         * Loads the Twig parser and sets up the l10n domain.
         */
        private function __construct() {
            $config = Config::current();

            $chain = array(
                new \Twig\Loader\FilesystemLoader(MAIN_DIR.DIR."admin")
            );

            foreach ($config->enabled_modules as $module) {
                if (is_dir(MODULES_DIR.DIR.$module.DIR."admin"))
                    $chain[] = new \Twig\Loader\FilesystemLoader(
                        MODULES_DIR.DIR.$module.DIR."admin"
                    );
            }

            foreach ($config->enabled_feathers as $feather) {
                if (is_dir(FEATHERS_DIR.DIR.$feather.DIR."admin"))
                    $chain[] = new \Twig\Loader\FilesystemLoader(
                        FEATHERS_DIR.DIR.$feather.DIR."admin"
                    );
            }

            $loader = new \Twig\Loader\ChainLoader($chain);

            $this->twig = new \Twig\Environment(
                $loader,
                array(
                    "debug" => DEBUG,
                    "strict_variables" => DEBUG,
                    "charset" => "UTF-8",
                    "cache" => CACHES_DIR.DIR."twig",
                    "autoescape" => false,
                    "use_yield" => true
                )
            );

            $this->twig->addExtension(
                new Leaf()
            );

            $this->twig->registerUndefinedFunctionCallback(
                "twig_callback_missing_function"
            );

            $this->twig->registerUndefinedFilterCallback(
                "twig_callback_missing_filter"
            );

            # Load the theme translator.
            load_translator("admin", MAIN_DIR.DIR."admin".DIR."locale");

            # Set the limit for pagination.
            $this->post_limit = $config->admin_per_page;
        }

        /**
         * Function: parse
         * Route constructor calls this to interpret clean URLs and determine the action.
         */
        public function parse(
            $route
        ): ?string {
            $visitor = Visitor::current();
            $config = Config::current();

            # Interpret clean URLs.
            if (!empty($route->arg[0]) and strpos($route->arg[0], "?") !== 0) {
                $route->action = $route->arg[0];

                if (!empty($route->arg[1]) and !empty($route->arg[2]))
                    $_GET[$route->arg[1]] = $route->arg[2];

                if (!empty($route->arg[3]) and !empty($route->arg[4]))
                    $_GET[$route->arg[3]] = $route->arg[4];
            }

            # Discover pagination.
            if (preg_match_all("/\/((([^_\/]+)_)?page)\/([0-9]+)/", $route->request, $pages)) {
                foreach ($pages[1] as $index => $variable)
                    $_GET[$variable] = (int) $pages[4][$index];
            }

            if (empty($route->action) or $route->action == "write") {
                # Can they add posts or drafts and is at least one feather enabled?
                if (
                    !empty($config->enabled_feathers) and
                    $visitor->group->can("add_post", "add_draft")
                )
                    return $route->action = "write_post";

                # Can they add pages?
                if ($visitor->group->can("add_page"))
                    return $route->action = "write_page";
            }

            if (empty($route->action) or $route->action == "manage") {
                # Can they manage any posts?
                if (Post::any_editable() or Post::any_deletable())
                    return $route->action = "manage_posts";

                # Can they manage pages?
                if ($visitor->group->can("edit_page", "delete_page"))
                    return $route->action = "manage_pages";

                # Can they manage users?
                if ($visitor->group->can("add_user", "edit_user", "delete_user"))
                    return $route->action = "manage_users";

                # Can they manage groups?
                if ($visitor->group->can("add_group", "edit_group", "delete_group"))
                    return $route->action = "manage_groups";

                # Can they import content?
                if ($visitor->group->can("import_content"))
                    return $route->action = "import";

                # Can they export content?
                if ($visitor->group->can("export_content"))
                    return $route->action = "export";
            }

            if (empty($route->action) or $route->action == "settings") {
                # Can they change settings?
                if ($visitor->group->can("change_settings"))
                    return $route->action = "general_settings";
            }

            if (empty($route->action) or $route->action == "extend") {
                # Can they enable/disable extensions?
                if ($visitor->group->can("toggle_extensions"))
                    return $route->action = "modules";
            }

            Trigger::current()->filter($route->action, "admin_determine_action");

            # Return 403 if we can't determine an allowed action for the visitor.
            if (!isset($route->action))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to view this area.")
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
            $exemptions = array("login", "logout");
            return in_array($action, $exemptions);
        }

        /**
         * Function: admin_write_post
         * Post writing.
         */
        public function admin_write_post(
        ): void {
            $visitor = Visitor::current();
            $config = Config::current();
            $trigger = Trigger::current();

            if (!Visitor::current()->group->can("add_post", "add_draft"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to add posts.")
                );

            $enabled_feathers = $config->enabled_feathers;

            if (empty($enabled_feathers))
                Flash::notice(
                    __("You must enable at least one feather in order to write a post."),
                    "feathers"
                );

            if (!isset($_SESSION['latest_feather']))
                $_SESSION['latest_feather'] = reset($enabled_feathers);

            if (!feather_enabled($_SESSION['latest_feather']))
                $_SESSION['latest_feather'] = reset($enabled_feathers);

            $feather = fallback($_GET['feather'], $_SESSION['latest_feather']);

            if (!feather_enabled($feather))
                show_404(
                    __("Not Found"),
                    __("Feather not found.")
                );

            $_SESSION['latest_feather'] = $feather;

            $options = array();
            $trigger->filter($options, "write_post_options", null, $feather);
            $trigger->filter($options, "post_options", null, $feather);

            $this->display(
                "pages".DIR."write_post",
                array(
                    "groups" => Group::find(array("order" => "id ASC")),
                    "options" => $options,
                    "feathers" => Feathers::$instances,
                    "feather" => Feathers::$instances[$feather]
                )
            );
        }

        /**
         * Function: admin_add_post
         * Adds a post when the form is submitted.
         */
        public function admin_add_post(
        ): never {
            $visitor = Visitor::current();

            if (!$visitor->group->can("add_post", "add_draft"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to add posts.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (!feather_enabled($_POST['feather']))
                show_404(
                    __("Not Found"),
                    __("Feather not found.")
                );

            if (isset($_POST['draft']))
                $_POST['status'] = Post::STATUS_DRAFT;

            if (!$visitor->group->can("add_post"))
                $_POST['status'] = Post::STATUS_DRAFT;

            $post = Feathers::$instances[$_POST['feather']]->submit();

            $post_redirect = (Post::any_editable() or Post::any_deletable()) ?
                "manage_posts" :
                "/" ;

            Flash::notice(
                __("Post created!").' <a href="'.$post->url().'">'.
                __("View post!").'</a>',
                $post_redirect
            );
        }

        /**
         * Function: admin_edit_post
         * Post editing.
         */
        public function admin_edit_post(
        ): void {
            $trigger = Trigger::current();

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to edit a post."),
                    code:400
                );

            $post = new Post(
                $_GET['id'],
                array("drafts" => true, "filter" => false)
            );

            if ($post->no_results)
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            if (!$post->editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit this post.")
                );

            if (!empty($_SESSION['redirect_to']))
                $_SESSION['post_redirect'] = $_SESSION['redirect_to'];

            $options = array();
            $trigger->filter($options, "edit_post_options", $post, $post->feather);
            $trigger->filter($options, "post_options", $post, $post->feather);

            $this->display(
                "pages".DIR."edit_post",
                array(
                    "post" => $post,
                    "groups" => Group::find(array("order" => "id ASC")),
                    "options" => $options,
                    "feather" => Feathers::$instances[$post->feather]
                )
            );
        }

        /**
         * Function: admin_update_post
         * Updates a post when the form is submitted.
         */
        public function admin_update_post(
        ): never {
            $visitor = Visitor::current();

            $post_redirect = (Post::any_editable() or Post::any_deletable()) ?
                "manage_posts" :
                "/" ;

            fallback($_SESSION['post_redirect'], $post_redirect);

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (isset($_POST['cancel']))
                redirect($_SESSION['post_redirect']);

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to update a post."),
                    code:400
                );

            $post = new Post(
                $_POST['id'],
                array("drafts" => true)
            );

            if ($post->no_results)
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            if (!$post->editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit this post.")
                );

            if (isset($_POST['publish']))
                $_POST['status'] = Post::STATUS_PUBLIC;

            if (!$visitor->group->can("add_post"))
                $_POST['status'] = $post->status;

            $post = Feathers::$instances[$post->feather]->update($post);

            Flash::notice(
                __("Post updated.").' <a href="'.$post->url().'">'.
                __("View post!").'</a>',
                $_SESSION['post_redirect']
            );
        }

        /**
         * Function: admin_delete_post
         * Post deletion (confirm page).
         */
        public function admin_delete_post(
        ): void {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a post."),
                    code:400
                );

            $post = new Post(
                $_GET['id'],
                array("drafts" => true)
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

            $this->display(
                "pages".DIR."delete_post",
                array("post" => $post)
            );
        }

        /**
         * Function: admin_destroy_post
         * Destroys a post.
         */
        public function admin_destroy_post(
        ): never {
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

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_posts");

            $post = new Post($_POST['id'], array("drafts" => true));

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

            Flash::notice(
                __("Post deleted."),
                "manage_posts"
            );
        }

        /**
         * Function: admin_manage_posts
         * Post management.
         */
        public function admin_manage_posts(
        ): void {
            if (!Post::any_editable() and !Post::any_deletable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to manage any posts.")
                );

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect(
                    "manage_posts/query/".
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
                "post_attributes.value LIKE :query OR url LIKE :query",
                "posts"
            );

            $visitor = Visitor::current();

            if (!$visitor->group->can("edit_draft", "edit_post", "delete_draft", "delete_post"))
                $where["user_id"] = $visitor->id;

            $results = Post::find(
                array(
                    "placeholders" => true,
                    "drafts" => true,
                    "where" => $where,
                    "params" => $params
                )
            );

            $ids = array();

            foreach ($results[0] as $result)
                $ids[] = $result["id"];

            if (!empty($ids)) {
                $posts = new Paginator(
                    Post::find(
                        array(
                            "placeholders" => true,
                            "drafts" => true,
                            "where" => array("id" => $ids),
                            "order" => $order
                        )
                    ),
                    $this->post_limit
                );
            } else {
                $posts = new Paginator(array());
            }

            $post_statuses = array();

            foreach ($posts->paginated as $post) {
                $name = "";
                $groups = array();
                $classes = array();

                if ($group_ids = $post->groups()) {
                    foreach ($group_ids as $group_id) {
                        $group = new Group($group_id);

                        if (!$group->no_results) {
                            $groups[] = $group;
                            $classes[] = "group-".$group->id;
                        }
                    }
                } else {
                    switch ($post->status) {
                        case Post::STATUS_DRAFT:
                            $name = __("Draft", "admin");
                            break;

                        case Post::STATUS_PUBLIC:
                            $name = __("Public", "admin");
                            break;

                        case Post::STATUS_PRIVATE:
                            $name = __("Private", "admin");
                            break;

                        case Post::STATUS_REG_ONLY:
                            $name = __("All registered users", "admin");
                            break;

                        case Post::STATUS_SCHEDULED:
                            $name = __("Scheduled", "admin");
                            break;

                        default:
                            $name = camelize($post->status, true);
                    }

                    $classes[] = $post->status;
                }

                $post_statuses[$post->id] = array(
                    "name" => $name,
                    "groups" => $groups,
                    "classes" => $classes
                );
            }

            $this->display(
                "pages".DIR."manage_posts",
                array(
                    "posts" => $posts,
                    "post_statuses" => $post_statuses
                )
            );
        }

        /**
         * Function: admin_write_page
         * Page creation.
         */
        public function admin_write_page(
        ): void {
            if (!Visitor::current()->group->can("add_page"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to add pages.")
                );

            $this->display(
                "pages".DIR."write_page",
                array("pages" => Page::find())
            );
        }

        /**
         * Function: admin_add_page
         * Adds a page when the form is submitted.
         */
        public function admin_add_page(
        ): never {
            $visitor = Visitor::current();

            if (!$visitor->group->can("add_page"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to add pages.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['title']))
                error(
                    __("Error"),
                    __("Title cannot be blank."),
                    code:422
                );

            if (empty($_POST['body']))
                error(
                    __("Error"),
                    __("Body cannot be blank."),
                    code:422
                );

            fallback($_POST['parent_id'], 0);
            fallback($_POST['status'], "public");
            fallback($_POST['list_priority'], 0);
            fallback($_POST['slug'], $_POST['title']);

            $public = in_array($_POST['status'], array("listed", "public"));
            $listed = in_array($_POST['status'], array("listed", "teased"));

            if (isset($_POST['private'])) {
                $public = false;
                $listed = false;
            }

            $list_order = empty($_POST['list_order']) ?
                (int) $_POST['list_priority'] :
                (int) $_POST['list_order'] ;

            $page = Page::add(
                title:$_POST['title'],
                body:$_POST['body'],
                parent_id:$_POST['parent_id'],
                public:$public,
                show_in_list:$listed,
                list_order:$list_order,
                clean:sanitize($_POST['slug'], true, true, 128)
            );

            $page_redirect = ($visitor->group->can("edit_page", "delete_page")) ?
                "manage_pages" :
                "/" ;

            Flash::notice(
                __("Page created!").' <a href="'.$page->url().'">'.
                __("View page!").'</a>',
                $page_redirect
            );
        }

        /**
         * Function: admin_edit_page
         * Page editing.
         */
        public function admin_edit_page(
        ): void {
            if (!Visitor::current()->group->can("edit_page"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit this page.")
                );

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to edit a page."),
                    code:400
                );

            $page = new Page(
                $_GET['id'],
                array("filter" => false)
            );

            if ($page->no_results)
                show_404(
                    __("Not Found"),
                    __("Page not found.")
                );

            if (!empty($_SESSION['redirect_to']))
                $_SESSION['page_redirect'] = $_SESSION['redirect_to'];

            $this->display(
                "pages".DIR."edit_page",
                array(
                    "page" => $page,
                    "pages" => Page::find(
                        array("where" => array("id not" => $page->id))
                    )
                )
            );
        }

        /**
         * Function: admin_update_page
         * Updates a page when the form is submitted.
         */
        public function admin_update_page(
        ): never {
            $visitor = Visitor::current();

            $page_redirect = ($visitor->group->can("edit_page", "delete_page")) ?
                "manage_pages" :
                "/" ;

            fallback($_SESSION['page_redirect'], $page_redirect);

            if (!$visitor->group->can("edit_page"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit pages.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (isset($_POST['cancel']))
                redirect($_SESSION['page_redirect']);

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to edit a page."),
                    code:400
                );

            if (empty($_POST['title']))
                error(
                    __("Error"),
                    __("Title cannot be blank."),
                    code:422
                );

            if (empty($_POST['body']))
                error(
                    __("Error"),
                    __("Body cannot be blank."),
                    code:422
                );

            $page = new Page($_POST['id']);

            if ($page->no_results)
                show_404(
                    __("Not Found"),
                    __("Page not found.")
                );

            fallback($_POST['parent_id'], 0);
            fallback($_POST['status'], "public");
            fallback($_POST['list_priority'], 0);
            fallback($_POST['slug'], "");

            $public = in_array(
                $_POST['status'],
                array(
                    Page::STATUS_LISTED,
                    Page::STATUS_PUBLIC
                )
            );

            $listed = in_array(
                $_POST['status'],
                array(
                    Page::STATUS_LISTED,
                    Page::STATUS_TEASED
                )
            );

            $list_order = empty($_POST['list_order']) ?
                (int) $_POST['list_priority'] :
                (int) $_POST['list_order'] ;

            $page = $page->update(
                title:$_POST['title'],
                body:$_POST['body'],
                parent_id:$_POST['parent_id'],
                public:$public,
                show_in_list:$listed,
                list_order:$list_order,
                clean:sanitize($_POST['slug'], true, true, 128)
            );

            Flash::notice(
                __("Page updated.").' <a href="'.$page->url().'">'.
                __("View page!").'</a>',
                $_SESSION['page_redirect']
            );
        }

        /**
         * Function: admin_delete_page
         * Page deletion (confirm page).
         */
        public function admin_delete_page(
        ): void {
            if (!Visitor::current()->group->can("delete_page"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete pages.")
                );

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a page."),
                    code:400
                );

            $page = new Page($_GET['id']);

            if ($page->no_results)
                show_404(
                    __("Not Found"),
                    __("Page not found.")
                );

            $this->display(
                "pages".DIR."delete_page",
                array("page" => $page)
            );
        }

        /**
         * Function: admin_destroy_page
         * Destroys a page.
         */
        public function admin_destroy_page(
        ): never {
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

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_pages");

            $page = new Page($_POST['id']);

            if ($page->no_results)
                show_404(
                    __("Not Found"),
                    __("Page not found.")
                );

            foreach ($page->children as $child)
                if (isset($_POST['destroy_children']))
                    Page::delete($child->id, true);
                else
                    $child->update(
                        title:$child->title,
                        body:$child->body,
                        parent_id:0,
                        public:$child->public,
                        show_in_list:$child->show_in_list,
                        list_order:$child->list_order,
                        url:$child->url
                    );

            Page::delete($page->id);

            Flash::notice(
                __("Page deleted."),
                "manage_pages"
            );
        }

        /**
         * Function: admin_manage_pages
         * Page management.
         */
        public function admin_manage_pages(
        ): void {
            $visitor = Visitor::current();

            if (!$visitor->group->can("edit_page", "delete_page"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to manage pages.")
                );

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect(
                    "manage_pages/query/".
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
                "title LIKE :query OR body LIKE :query",
                "pages"
            );

            $this->display(
                "pages".DIR."manage_pages",
                array("pages" => new Paginator(
                    Page::find(
                        array(
                            "placeholders" => true,
                            "where" => $where,
                            "params" => $params,
                            "order" => $order
                        )
                    ),
                    $this->post_limit))
            );
        }

        /**
         * Function: admin_new_user
         * User creation.
         */
        public function admin_new_user(
        ): void {
            if (!Visitor::current()->group->can("add_user"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to add users.")
                );

            $config = Config::current();
            $options = array(
                "where" => array(
                    "id not" => array($config->guest_group, $config->default_group)
                ),
                "order" => "id DESC"
            );

            $this->display(
                "pages".DIR."new_user",
                array(
                    "default_group" => new Group($config->default_group),
                    "groups" => Group::find($options)
                )
            );
        }

        /**
         * Function: admin_add_user
         * Add a user when the form is submitted.
         */
        public function admin_add_user(
        ): never {
            if (!Visitor::current()->group->can("add_user"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to add users.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['login']) or derezz($_POST['login']))
                error(
                    __("Error"),
                    __("Please enter a username for the account."),
                    code:422
                );

            $check = new User(array("login" => $_POST['login']));

            if (!$check->no_results)
                error(
                    __("Error"),
                    __("That username is already in use."),
                    code:409
                );

            if (empty($_POST['password1']) or empty($_POST['password2']))
                error(
                    __("Error"),
                    __("Passwords cannot be blank."),
                    code:422
                );

            if ($_POST['password1'] != $_POST['password2'])
                error(
                    __("Error"),
                    __("Passwords do not match."),
                    code:422
                );

            if (password_strength($_POST['password1']) < 100)
                Flash::message(
                    __("Please consider setting a stronger password for this user.")
                );

            if (empty($_POST['email']))
                error(
                    __("Error"),
                    __("Email address cannot be blank."),
                    code:422
                );

            if (!is_email($_POST['email']))
                error(
                    __("Error"),
                    __("Invalid email address."),
                    code:422
                );

            if (!empty($_POST['website']) and !is_url($_POST['website']))
                error(
                    __("Error"),
                    __("Invalid website URL."),
                    code:422
                );

            if (!empty($_POST['website']))
                $_POST['website'] = add_scheme($_POST['website']);

            $config = Config::current();

            fallback($_POST['full_name'], "");
            fallback($_POST['website'], "");
            fallback($_POST['group'], $config->default_group);

            $group = new Group($_POST['group']);

            if ($group->no_results)
                show_404(
                    __("Not Found"),
                    __("Group not found.")
                );

            $approved = ($config->email_activation and empty($_POST['activated'])) ?
                false :
                true ;

            $user = User::add(
                login:$_POST['login'],
                password:User::hash_password($_POST['password1']),
                email:$_POST['email'],
                full_name:$_POST['full_name'],
                website:$_POST['website'],
                group_id:$group->id,
                approved:$approved
            );

            if (!$user->approved)
                email_activate_account($user);

            Flash::notice(
                __("User added."),
                "manage_users"
            );
        }

        /**
         * Function: admin_edit_user
         * User editing.
         */
        public function admin_edit_user(
        ): void {
            if (!Visitor::current()->group->can("edit_user"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit users.")
                );

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to edit a user."),
                    code:400
                );

            $user = new User($_GET['id']);

            if ($user->no_results)
                show_404(
                    __("Not Found"),
                    __("User not found.")
                );

            $options = array(
                "order" => "id ASC",
                "where" => array("id not" => Config::current()->guest_group)
            );

            $this->display(
                "pages".DIR."edit_user",
                array("user" => $user, "groups" => Group::find($options))
            );
        }

        /**
         * Function: admin_update_user
         * Updates a user when the form is submitted.
         */
        public function admin_update_user(
        ): never {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to edit a user."),
                    code:400
                );

            $visitor = Visitor::current();
            $config = Config::current();

            if (!$visitor->group->can("edit_user"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit users.")
                );

            if (empty($_POST['login']) or derezz($_POST['login']))
                error(
                    __("Error"),
                    __("Please enter a username for the account."),
                    code:422
                );

            $check = new User(
                array(
                    "login" => $_POST['login'],
                    "id not" => $_POST['id']
                )
            );

            if (!$check->no_results)
                error(
                    __("Error"),
                    __("That username is already in use."),
                    code:409
                );

            $user = new User($_POST['id']);

            if ($user->no_results)
                show_404(
                    __("Not Found"),
                    __("User not found.")
                );

            if (!empty($_POST['new_password1'])) {
                if (
                    empty($_POST['new_password2']) or
                    $_POST['new_password1'] != $_POST['new_password2']
                ) {
                    error(
                        __("Error"),
                        __("Passwords do not match."),
                        code:422
                    );
                } elseif (
                    password_strength($_POST['new_password1']) < 100
                ) {
                    Flash::message(
                        __("Please consider setting a stronger password for this user.")
                    );
                }
            }

            $password = (!empty($_POST['new_password1'])) ?
                User::hash_password($_POST['new_password1']) :
                $user->password ;

            if (empty($_POST['email']))
                error(
                    __("Error"),
                    __("Email address cannot be blank."),
                    code:422
                );

            if (!is_email($_POST['email']))
                error(
                    __("Error"),
                    __("Invalid email address."),
                    code:422
                );

            if (!empty($_POST['website']) and !is_url($_POST['website']))
                error(
                    __("Error"),
                    __("Invalid website URL."),
                    code:422
                );

            if (!empty($_POST['website']))
                $_POST['website'] = add_scheme($_POST['website']);

            fallback($_POST['full_name'], "");
            fallback($_POST['website'], "");
            fallback($_POST['group'], $config->default_group);

            $group = new Group($_POST['group']);

            if ($group->no_results)
                show_404(
                    __("Not Found"),
                    __("Group not found.")
                );

            $approved = ($config->email_activation and empty($_POST['activated'])) ?
                false :
                true ;

            $user = $user->update(
                login:$_POST['login'],
                password:$password,
                email:$_POST['email'],
                full_name:$_POST['full_name'],
                website:$_POST['website'],
                group_id:$group->id,
                approved:$approved
            );

            if (!$user->approved)
                email_activate_account($user);

            Flash::notice(
                __("User updated."),
                "manage_users"
            );
        }

        /**
         * Function: admin_delete_user
         * User deletion (confirm page).
         */
        public function admin_delete_user(
        ): void {
            if (!Visitor::current()->group->can("delete_user"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete users.")
                );

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a user."),
                    code:400
                );

            $user = new User($_GET['id']);

            if ($user->no_results)
                show_404(
                    __("Not Found"),
                    __("User not found.")
                );

            if ($user->id == Visitor::current()->id)
                Flash::warning(
                    __("You cannot delete your own account."),
                    "manage_users"
                );

            $options = array("where" => array("id not" => $user->id));

            $this->display(
                "pages".DIR."delete_user",
                array("user" => $user, "users" => User::find($options))
            );
        }

        /**
         * Function: admin_destroy_user
         * Destroys a user.
         */
        public function admin_destroy_user(
        ): never {
            if (!Visitor::current()->group->can("delete_user"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete users.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a user."),
                    code:400
                );

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_users");

            $user = new User($_POST['id']);

            if ($user->no_results)
                show_404(
                    __("Not Found"),
                    __("User not found.")
                );

            $sql = SQL::current();

            if (!empty($user->posts))
                if (!empty($_POST['move_posts'])) {
                    $posts_user = new User($_POST['move_posts']);

                    if ($posts_user->no_results)
                        error(
                            __("Gone"),
                            __("New owner for posts does not exist."),
                            code:410
                        );

                    foreach ($user->posts as $post)
                        $sql->update(
                            table:"posts",
                            conds:array("id" => $post->id),
                            data:array("user_id" => $posts_user->id)
                        );
                } else {
                    foreach ($user->posts as $post)
                        Post::delete($post->id);
                }

            if (!empty($user->pages))
                if (!empty($_POST['move_pages'])) {
                    $pages_user = new User($_POST['move_pages']);

                    if ($pages_user->no_results)
                        error(
                            __("Gone"),
                            __("New owner for pages does not exist."),
                            code:410
                        );

                    foreach ($user->pages as $page)
                        $sql->update(
                            table:"pages",
                            conds:array("id" => $page->id),
                            data:array("user_id" => $pages_user->id)
                        );
                } else {
                    foreach ($user->pages as $page)
                        Page::delete($page->id);
                }

            User::delete($user->id);

            Flash::notice(
                __("User deleted."),
                "manage_users"
            );
        }

        /**
         * Function: admin_manage_users
         * User management.
         */
        public function admin_manage_users(
        ): void {
            $visitor = Visitor::current();

            if (!$visitor->group->can("add_user", "edit_user", "delete_user"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to manage users.")
                );

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect(
                    "manage_users/query/".
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
                "login LIKE :query OR full_name LIKE :query OR email LIKE :query OR website LIKE :query",
                "users"
            );

            $this->display(
                "pages".DIR."manage_users",
                array(
                    "users" => new Paginator(
                        User::find(
                            array(
                                "placeholders" => true,
                                "where" => $where,
                                "params" => $params,
                                "order" => $order
                            )
                        ),
                        $this->post_limit
                    )
                )
            );
        }

        /**
         * Function: admin_new_group
         * Group creation.
         */
        public function admin_new_group(
        ): void {
            if (!Visitor::current()->group->can("add_group"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to add groups.")
                );

            $this->display(
                "pages".DIR."new_group",
                array("permissions" => Group::list_permissions())
            );
        }

        /**
         * Function: admin_add_group
         * Adds a group when the form is submitted.
         */
        public function admin_add_group(
        ): never {
            if (!Visitor::current()->group->can("add_group"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to add groups.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['name']) or derezz($_POST['name']))
                error(
                    __("Error"),
                    __("Please enter a name for the group."),
                    code:422
                );

            fallback($_POST['permissions'], array());

            $check = new Group(
                array("name" => $_POST['name'])
            );

            if (!$check->no_results)
                error(
                    __("Error"),
                    __("That group name is already in use."),
                    code:409
                );

            Group::add(
                $_POST['name'],
                array_keys($_POST['permissions'])
            );

            Flash::notice(
                __("Group added."),
                "manage_groups"
            );
        }

        /**
         * Function: admin_edit_group
         * Group editing.
         */
        public function admin_edit_group(
        ): void {
            if (!Visitor::current()->group->can("edit_group"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit groups.")
                );

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to edit a group."),
                    code:400
                );

            $group = new Group($_GET['id']);

            if ($group->no_results)
                show_404(
                    __("Not Found"),
                    __("Group not found.")
                );

            $this->display(
                "pages".DIR."edit_group",
                array(
                    "group" => $group,
                    "permissions" => Group::list_permissions()
                )
            );
        }

        /**
         * Function: admin_update_group
         * Updates a group when the form is submitted.
         */
        public function admin_update_group(
        ): never {
            if (!Visitor::current()->group->can("edit_group"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit groups.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to edit a group."),
                    code:400
                );

            if (empty($_POST['name']) or derezz($_POST['name']))
                error(
                    __("Error"),
                    __("Please enter a name for the group."),
                    code:422
                );

            fallback($_POST['permissions'], array());

            $check = new Group(
                array(
                    "name" => $_POST['name'],
                    "id not" => $_POST['id']
                )
            );

            if (!$check->no_results)
                error(
                    __("Error"),
                    __("That group name is already in use."),
                    code:409
                );

            $group = new Group($_POST['id']);

            if ($group->no_results)
                show_404(
                    __("Not Found"),
                    __("Group not found.")
                );

            $group = $group->update(
                $_POST['name'],
                array_keys($_POST['permissions'])
            );

            Flash::notice(
                __("Group updated."),
                "manage_groups"
            );
        }

        /**
         * Function: admin_delete_group
         * Group deletion (confirm page).
         */
        public function admin_delete_group(
        ): void {
            if (!Visitor::current()->group->can("delete_group"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete groups.")
                );

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a group."),
                    code:400
                );

            $group = new Group($_GET['id']);

            if ($group->no_results)
                show_404(
                    __("Not Found"),
                    __("Group not found.")
                );

            if ($group->id == Visitor::current()->group->id)
                Flash::warning(
                    __("You cannot delete your own group."),
                    "manage_groups"
                );

            $options = array(
                "where" => array("id not" => $group->id),
                "order" => "id ASC"
            );

            $this->display(
                "pages".DIR."delete_group",
                array(
                    "group" => $group,
                    "groups" => Group::find($options)
                )
            );
        }

        /**
         * Function: admin_destroy_group
         * Destroys a group.
         */
        public function admin_destroy_group(
        ): never {
            if (!Visitor::current()->group->can("delete_group"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete groups.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a group."),
                    code:400
                );

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_groups");

            $group = new Group($_POST['id']);

            if ($group->no_results)
                show_404(
                    __("Not Found"),
                    __("Group not found.")
                );

            # Assign users to new member group.
            if (!empty($group->users))
                if (!empty($_POST['move_group'])) {
                    $member_group = new Group($_POST['move_group']);

                    if ($member_group->no_results)
                        error(
                            __("Gone"),
                            __("New member group does not exist."),
                            code:410
                        );

                    foreach ($group->users as $user)
                        $user->update(group_id:$member_group->id);

                } else {
                    error(
                        __("Error"),
                        __("New member group must be specified."),
                        code:422
                    );
                }

            $config = Config::current();

            # Set new default group.
            if ($config->default_group == $group->id)
                if (!empty($_POST['default_group'])) {
                    $default_group = new Group($_POST['default_group']);

                    if ($default_group->no_results)
                        error(
                            __("Gone"),
                            __("New default group does not exist."),
                            code:410
                        );

                    $config->set("default_group", $default_group->id);
                } else {
                    error(
                        __("Error"),
                        __("New default group must be specified."),
                        code:422
                    );
                }

            # Set new guest group.
            if ($config->guest_group == $group->id)
                if (!empty($_POST['guest_group'])) {
                    $guest_group = new Group($_POST['guest_group']);

                    if ($guest_group->no_results)
                        error(
                            __("Gone"),
                            __("New guest group does not exist."),
                            code:410
                        );

                    $config->set("guest_group", $guest_group->id);
                } else {
                    error(
                        __("Error"),
                        __("New guest group must be specified."),
                        code:422
                    );
                }

            $sql = SQL::current();

            # Set group-specific posts to private status.
            foreach ($sql->select(
                tables:"posts",
                fields:"id",
                conds:array("status LIKE" => "%{".$group->id."}%")
            )->fetchAll() as $post) {

                $sql->update(
                    table:"posts",
                    conds:array("id" => $post["id"]),
                    data:array("status" => Post::STATUS_PRIVATE)
                );
            }

            Group::delete($group->id);

            Flash::notice(
                __("Group deleted."),
                "manage_groups"
            );
        }

        /**
         * Function: admin_manage_groups
         * Group management.
         */
        public function admin_manage_groups(
        ): void {
            $visitor = Visitor::current();

            if (!$visitor->group->can("add_group", "edit_group", "delete_group"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to manage groups.")
                );

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['search']))
                redirect(
                    "manage_groups/search/".
                    str_ireplace(
                        array("%2F", "%5C"),
                        "%5F",
                        urlencode($_POST['search'])
                    ).
                    "/"
                );

            if (isset($_GET['search']) and $_GET['search'] != "") {
                $user = new User(
                    array("login" => $_GET['search'])
                );

                $groups = ($user->no_results) ?
                    new Paginator(array()) :
                    new Paginator(array($user->group)) ;
            } else {
                $groups = new Paginator(
                    Group::find(
                        array(
                            "placeholders" => true,
                            "order" => "id ASC"
                        )
                    ),
                    $this->post_limit
                );
            }

            $this->display(
                "pages".DIR."manage_groups",
                array("groups" => $groups)
            );
        }

        /**
         * Function: admin_delete_upload
         * Upload deletion (confirm page).
         */
        public function admin_delete_upload(
        ): void {
            $sql = SQL::current();

            if (!Visitor::current()->group->can("edit_post", "edit_page", true))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete uploads.")
                );

            fallback($_GET['file'], "");
            $filename = str_replace(array(DIR, "/"), "", $_GET['file']);
            $filepath = uploaded($filename, false);

            if (!is_readable($filepath) or !is_file($filepath))
                show_404(
                    __("Not Found"),
                    __("File not found.")
                );

            $post_count = $sql->count(
                "post_attributes",
                "value LIKE :query",
                array(":query" => "%".$filename."%")
            );

            $page_count = $sql->count(
                "pages",
                "body LIKE :query",
                array(":query" => "%".$filename."%")
            );

            if ($post_count > 0)
                Flash::message(
                    __("A post is using this upload.").' <a href="'.
                    url("manage_posts/query/".urlencode($filename)).'">'.
                    __("View post!").'</a>'
                );

            if ($page_count > 0)
                Flash::message(
                    __("A page is using this upload.").' <a href="'.
                    url("manage_pages/query/".urlencode($filename)).'">'.
                    __("View page!").'</a>'
                );

            $this->display(
                "pages".DIR."delete_upload",
                array("filename" => $filename)
            );
        }

        /**
         * Function: admin_destroy_upload
         * Destroys a post.
         */
        public function admin_destroy_upload(
        ): never {
            if (!Visitor::current()->group->can("edit_post", "edit_page", true))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete uploads.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_uploads");

            fallback($_POST['file'], "");
            $filename = str_replace(array(DIR, "/"), "", $_POST['file']);
            $filepath = uploaded($filename, false);

            if (!is_readable($filepath) or !is_file($filepath))
                show_404(
                    __("Not Found"),
                    __("File not found.")
                );

            if (!delete_upload($filename))
                Flash::warning(
                    __("Failed to delete upload."),
                    "manage_uploads"
                );

            Flash::notice(
                __("Upload deleted."),
                "manage_uploads"
            );
        }

        /**
         * Function: admin_manage_uploads
         * Upload management.
         */
        public function admin_manage_uploads(
        ): void {
            if (!Visitor::current()->group->can("edit_post", "edit_page", true))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to manage uploads.")
                );

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['search']))
                redirect(
                    "manage_uploads/search/".
                    str_ireplace(
                        array("%2F", "%5C"),
                        "%5F",
                        urlencode($_POST['search'])
                    ).
                    "/"
                );

            if (isset($_POST['sort']))
                $_SESSION['uploads_sort'] = $_POST['sort'];

            $search = isset($_GET['search']) ? $_GET['search'] : "" ;
            $sort = fallback($_SESSION['uploads_sort'], "name");

            $columns = array(
                "name" => __("Name", "admin"),
                "size" => __("Size", "admin"),
                "type" => __("Type", "admin"),
                "modified" => __("Last Modified", "admin")
            );

            $uploads = new Paginator(
                    uploaded_search(
                        search:$search,
                        sort:$sort
                    )
                );

            $this->display(
                "pages".DIR."manage_uploads",
                array(
                    "uploads" => $uploads,
                    "uploads_sort" => $sort,
                    "uploads_columns" => $columns
                )
            );
        }

        /**
         * Function: admin_export
         * Export content from this installation.
         */
        public function admin_export(
        ): void {
            $config  = Config::current();
            $trigger = Trigger::current();
            $visitor = Visitor::current();
            $exports = array(); # Use this to store export data.

            if (!$visitor->group->can("export_content"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to export content.")
                );

            if (empty($_POST)) {
                $this->display("pages".DIR."export");
                return;
            }

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            $trigger->call("before_export");

            if (isset($_POST['posts'])) {
                fallback($_POST['filter_posts'], "");
                list($where, $params) = keywords(
                    $_POST['filter_posts'],
                    "post_attributes.value LIKE :query OR url LIKE :query",
                    "posts"
                );

                $results = Post::find(
                    array(
                        "placeholders" => true,
                        "drafts" => true,
                        "where" => $where,
                        "params" => $params
                    )
                );

                $ids = array();

                foreach ($results[0] as $result)
                    $ids[] = $result["id"];

                if (!empty($ids)) {
                    $posts = Post::find(
                        array(
                            "drafts" => true,
                            "where" => array("id" => $ids),
                            "order" => "id ASC"),
                        array("filter" => false)
                    );
                } else {
                    $posts = array();
                }

                $posts_atom = '<?xml version="1.0" encoding="UTF-8"?>'."\n".
                    '<feed xmlns="http://www.w3.org/2005/Atom"'.
                    ' xmlns:chyrp="http://chyrp.net/export/1.0/">'."\n".
                    '<title>'.
                    fix($config->name).
                    '</title>'."\n".
                    '<subtitle>'.
                    fix($config->description).
                    '</subtitle>'."\n".
                    '<id>'.
                    fix($config->url).
                    '</id>'."\n".
                    '<updated>'.
                    date(DATE_ATOM).
                    '</updated>'."\n".
                    '<link href="'.
                    fix($config->url, true).
                    '" rel="alternate" type="text/html" />'."\n".
                    '<generator uri="https://chyrplite.net/" version="'.
                    CHYRP_VERSION.
                    '">Chyrp</generator>'."\n";

                foreach ($posts as $post) {
                    $updated = ($post->updated) ?
                        $post->updated_at :
                        $post->created_at ;

                    $title = oneof($post->title(), ucfirst($post->feather));

                    $posts_atom.= '<entry xml:base="'.$post->url().'">'."\n".
                        '<title type="html">'.
                        fix($title, false, true).
                        '</title>'."\n".
                        '<id>'.
                        fix(url("id/post/".$post->id, MainController::current())).
                        '</id>'."\n".
                        '<updated>'.
                        when(DATE_ATOM, $updated).
                        '</updated>'."\n".
                        '<published>'.
                        when(DATE_ATOM, $post->created_at).
                        '</published>'."\n".
                        '<chyrp:etag>'.
                        fix($post->etag(), false, true).
                        '</chyrp:etag>'."\n".
                        '<author chyrp:user_id="'.$post->user_id.'">'."\n".
                        '<name>'.
                        fix(oneof($post->user->full_name, $post->user->login)).
                        '</name>'."\n";

                    if (!empty($post->user->website))
                        $posts_atom.= '<uri>'.fix($post->user->website).'</uri>'."\n";

                    $posts_atom.= '<chyrp:login>'.
                        fix($post->user->login, false, true).
                        '</chyrp:login>'."\n".
                        '</author>'."\n".
                        '<content type="application/xml">'."\n";

                    foreach ($post->attributes as $key => $val)
                        $posts_atom.= '<'.$key.'>'.
                                      fix($val, false, true).
                                      '</'.$key.'>'."\n";

                    $posts_atom.= '</content>'."\n";

                    foreach (
                        array(
                            "feather",
                            "clean",
                            "url",
                            "pinned",
                            "status"
                        ) as $attr
                    ) {
                        $posts_atom.= '<chyrp:'.$attr.'>'.
                                      fix($post->$attr, false, true).
                                      '</chyrp:'.$attr.'>'."\n";
                    }

                    $trigger->filter($posts_atom, "posts_export", $post);
                    $posts_atom.= '</entry>'."\n";
                }

                $posts_atom.= '</feed>'."\n";
                $exports["posts.atom"] = $posts_atom;
            }

            if (isset($_POST['pages'])) {
                fallback($_POST['filter_pages'], "");
                list($where, $params) = keywords(
                    $_POST['filter_pages'],
                    "title LIKE :query OR body LIKE :query",
                    "pages"
                );

                $pages = Page::find(
                    array(
                        "where" => $where,
                        "params" => $params,
                        "order" => "id ASC"
                    ),
                    array("filter" => false)
                );

                $pages_atom = '<?xml version="1.0" encoding="UTF-8"?>'."\n".
                    '<feed xmlns="http://www.w3.org/2005/Atom"'.
                    ' xmlns:chyrp="http://chyrp.net/export/1.0/">'."\n".
                    '<title>'.
                    fix($config->name).
                    '</title>'."\n".
                    '<subtitle>'.
                    fix($config->description).
                    '</subtitle>'."\n".
                    '<id>'.
                    fix($config->url).
                    '</id>'."\n".
                    '<updated>'.
                    date(DATE_ATOM).
                    '</updated>'."\n".
                    '<link href="'.
                    fix($config->url, true).
                    '" rel="alternate" type="text/html" />'."\n".
                    '<generator uri="https://chyrplite.net/" version="'.
                    CHYRP_VERSION.
                    '">Chyrp</generator>'."\n";

                foreach ($pages as $page) {
                    $updated = ($page->updated) ?
                        $page->updated_at :
                        $page->created_at ;

                    $pages_atom.= '<entry xml:base="'.$page->url().
                        '" chyrp:parent_id="'.$page->parent_id.'">'."\n".
                        '<title type="html">'.
                        fix($page->title, false, true).
                        '</title>'."\n".
                        '<id>'.
                        fix(url("id/page/".$page->id, MainController::current())).
                        '</id>'."\n".
                        '<updated>'.
                        when(DATE_ATOM, $updated).
                        '</updated>'."\n".
                        '<published>'.
                        when(DATE_ATOM, $page->created_at).
                        '</published>'."\n".
                        '<chyrp:etag>'.
                        fix($page->etag(), false, true).
                        '</chyrp:etag>'."\n".
                        '<author chyrp:user_id="'.fix($page->user_id).'">'."\n".
                        '<name>'.
                        fix(oneof($page->user->full_name, $page->user->login)).
                        '</name>'."\n";

                    if (!empty($page->user->website))
                        $pages_atom.= '<uri>'.
                                      fix($page->user->website).
                                      '</uri>'."\n";

                    $pages_atom.= '<chyrp:login>'.
                                  fix($page->user->login, false, true).
                                  '</chyrp:login>'."\n".
                                  '</author>'."\n".
                                  '<content type="html">'.
                                  fix($page->body, false, true).
                                  '</content>'."\n";

                    foreach (
                        array(
                            "public",
                            "show_in_list",
                            "list_order",
                            "clean",
                            "url"
                        ) as $attr
                    ) {
                        $pages_atom.= '<chyrp:'.$attr.'>'.
                                      fix($page->$attr, false, true).
                                      '</chyrp:'.$attr.'>'."\n";
                    }


                    $trigger->filter($pages_atom, "pages_export", $page);
                    $pages_atom.= '</entry>'."\n";
                }

                $pages_atom.= '</feed>'."\n";
                $exports["pages.atom"] = $pages_atom;
            }

            if (isset($_POST['groups'])) {
                fallback($_POST['filter_groups'], "");
                list($where, $params) = keywords(
                    $_POST['filter_groups'],
                    "name LIKE :query",
                    "groups"
                );

                $groups = Group::find(
                    array(
                        "where" => $where,
                        "params" => $params,
                        "order" => "id ASC"
                    )
                );

                $groups_json = array();

                foreach ($groups as $index => $group)
                    $groups_json[$group->name] = $group->permissions;

                $exports["groups.json"] = json_set(
                    $groups_json,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                );
            }

            if (isset($_POST['users'])) {
                fallback($_POST['filter_users'], "");
                list($where, $params) = keywords(
                    $_POST['filter_users'],
                    "login LIKE :query OR full_name LIKE :query OR email LIKE :query OR website LIKE :query",
                    "users"
                );

                $users = User::find(
                    array(
                        "where" => $where,
                        "params" => $params,
                        "order" => "id ASC"
                    )
                );

                $users_json = array();

                $include = array(
                    "password",
                    "full_name",
                    "email",
                    "website",
                    "approved",
                    "joined_at"
                );

                foreach ($users as $user) {
                    $users_json[$user->login] = array();
                    $users_json[$user->login]["group"] = $user->group->name;

                    foreach ($include as $attr)
                        $users_json[$user->login][$attr] = $user->$attr;
                }

                $exports["users.json"] = json_set(
                    $users_json,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                );
            }

            if (isset($_POST['uploads'])) {
                fallback($_POST['filter_uploads'], "");

                $uploads = uploaded_search($_POST['filter_uploads']);
                $exports["uploads.json"] = json_set(
                    $uploads,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                );
            }

            $trigger->filter($exports, "export");

            if (empty($exports))
                Flash::warning(
                    __("You did not select anything to export."),
                    "export"
                );

            $filename = sanitize(
                camelize($config->name),
                false,
                true
            )."_export_".date("Y-m-d");

            $archived = zip_archive($exports);
            file_attachment($archived, $filename.".zip");
        }

        /**
         * Function: admin_import
         * Import content to this installation.
         */
        public function admin_import(
        ): void {
            $config  = Config::current();
            $trigger = Trigger::current();
            $visitor = Visitor::current();
            $sql = SQL::current();
            $imports = array(); # This array will be tested to determine if anything was selected.

            if (!$visitor->group->can("import_content"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to import content.")
                );

            if (empty($_POST)) {
                $this->display("pages".DIR."import");
                return;
            }

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (isset($_FILES['posts_file']) and upload_tester($_FILES['posts_file'])) {
                $imports["posts"] = simplexml_load_file($_FILES['posts_file']['tmp_name']);

                if ($imports["posts"]->generator != "Chyrp")
                    Flash::warning(
                        __("Posts export file is invalid."),
                        "import"
                    );
            }

            if (isset($_FILES['pages_file']) and upload_tester($_FILES['pages_file'])) {
                $imports["pages"] = simplexml_load_file($_FILES['pages_file']['tmp_name']);

                if ($imports["pages"]->generator != "Chyrp")
                    Flash::warning(
                        __("Pages export file is invalid."),
                        "import"
                    );
            }

            if (isset($_FILES['groups_file']) and upload_tester($_FILES['groups_file'])) {
                $imports["groups"] = json_get(
                    file_get_contents($_FILES['groups_file']['tmp_name']),
                    true
                );

                if (!is_array($imports["groups"]))
                    Flash::warning(
                        __("Groups export file is invalid."),
                        "import"
                    );
            }

            if (isset($_FILES['users_file']) and upload_tester($_FILES['users_file'])) {
                $imports["users"] = json_get(
                    file_get_contents($_FILES['users_file']['tmp_name']),
                    true
                );

                if (!is_array($imports["users"]))
                    Flash::warning(
                        __("Users export file is invalid."),
                        "import"
                    );
            }

            if (isset($_FILES['uploads']) and upload_tester($_FILES['uploads'])) {
                $imports["uploads"] = array();

                if (is_array($_FILES['uploads']['name'])) {
                    for ($i = 0; $i < count($_FILES['uploads']['name']); $i++)
                        $imports["uploads"][] = upload(
                            array(
                                'name' => $_FILES['uploads']['name'][$i],
                                'type' => $_FILES['uploads']['type'][$i],
                                'tmp_name' => $_FILES['uploads']['tmp_name'][$i],
                                'error' => $_FILES['uploads']['error'][$i],
                                'size' => $_FILES['uploads']['size'][$i]
                            )
                        );
                } else {
                    $imports["uploads"][] = upload($_FILES['uploads']);
                }
            }

            $trigger->filter($imports, "before_import");

            if (empty($imports))
                Flash::warning(
                    __("You did not select anything to import."),
                    "import"
                );

            set_max_time();
            set_max_memory();

            if (!empty($_POST['media_url'])) {
                $match_url = preg_quote($_POST['media_url'], "/");
                $media_url = fix(
                    $config->chyrp_url.
                    str_replace(DIR, "/", $config->uploads_path)
                );
                $media_exp = "/{$match_url}([^\.\!,\?;\"\'<>\(\)\[\]\{\}\s\t ]+)\.([a-zA-Z0-9]+)/";
            }

            if (isset($imports["groups"])) {
                foreach ($imports["groups"] as $name => $permissions) {
                    $group = new Group(
                        array("name" => (string) $name)
                    );

                    if ($group->no_results) {
                        $group = Group::add($name, $permissions);
                        $trigger->call("import_chyrp_group", $group);
                    }
                }
            }

            if (isset($imports["users"])) {
                foreach ($imports["users"] as $login => $attributes) {
                    $user = new User(
                        array("login" => (string) $login)
                    );

                    if ($user->no_results) {
                        $group = new Group(
                            array("name" => (string) fallback($attributes["group"]))
                        );

                        fallback($attributes["password"], User::hash_password(random(8)));
                        fallback($attributes["email"], "");
                        fallback($attributes["full_name"], "");
                        fallback($attributes["website"], "");
                        fallback($attributes["approved"], false);
                        fallback($attributes["joined_at"], datetime());

                        $group_id = (!$group->no_results) ?
                            $group->id :
                            $config->default_group ;

                        $user = User::add(
                            login:$login,
                            password:$attributes["password"],
                            email:$attributes["email"],
                            full_name:$attributes["full_name"],
                            website:$attributes["website"],
                            group_id:$group_id,
                            approved:$attributes["approved"],
                            joined_at:$attributes["joined_at"]
                        );

                        $trigger->call("import_chyrp_user", $user);
                    }
                }
            }

            if (isset($imports["posts"])) {
                foreach ($imports["posts"]->entry as $entry) {
                    $chyrp = $entry->children("http://chyrp.net/export/1.0/");

                    $login = $entry->author->children(
                        "http://chyrp.net/export/1.0/"
                    )->login;

                    $user = new User(
                        array("login" => unfix((string) $login))
                    );

                    $values = array();

                    foreach ($entry->content->children() as $value)
                        $values[$value->getName()] = unfix((string) $value);

                    if (!empty($_POST['media_url']))
                        array_walk_recursive(
                            $values,
                            function (&$value) use ($media_exp, $media_url) {
                                $value = preg_replace(
                                    $media_exp,
                                    $media_url."$1.$2",
                                    $value
                                );
                            }
                        );

                    $values["imported_from"] = "chyrp";

                    $updated = (
                        (string) $entry->updated != (string) $entry->published
                    );

                    $post = Post::add(
                        values:$values,
                        clean:unfix((string) $chyrp->clean),
                        url:Post::check_url(unfix((string) $chyrp->url)),
                        feather:unfix((string) $chyrp->feather),
                        user:(!$user->no_results) ? $user->id : $visitor->id,
                        pinned:(bool) unfix((string) $chyrp->pinned),
                        status:unfix((string) $chyrp->status),
                        created_at:datetime((string) $entry->published),
                        updated_at:($updated) ? datetime((string) $entry->updated) : null,
                        pingbacks:false
                    );

                    $trigger->call("import_chyrp_post", $entry, $post);
                }
            }

            if (isset($imports["pages"])) {
                foreach ($imports["pages"]->entry as $entry) {
                    $chyrp = $entry->children(
                        "http://chyrp.net/export/1.0/"
                    );

                    $attr  = $entry->attributes(
                        "http://chyrp.net/export/1.0/"
                    );

                    $login = $entry->author->children(
                        "http://chyrp.net/export/1.0/"
                    )->login;

                    $user = new User(
                        array("login" => unfix((string) $login))
                    );

                    $body = unfix((string) $entry->content);

                    if (!empty($_POST['media_url']))
                        $body = preg_replace(
                            $media_exp,
                            $media_url."$1.$2",
                            $body
                        );

                    $updated = (
                        (string) $entry->updated != (string) $entry->published
                    );

                    $page = Page::add(
                        title:unfix((string) $entry->title),
                        body:$body,
                        user:(!$user->no_results) ? $user->id : $visitor->id,
                        parent_id:(int) unfix((string) $attr->parent_id),
                        public:(bool) unfix((string) $chyrp->public),
                        show_in_list:(bool) unfix((string) $chyrp->show_in_list),
                        list_order:(int) unfix((string) $chyrp->list_order),
                        clean:unfix((string) $chyrp->clean),
                        url:Page::check_url(unfix((string) $chyrp->url)),
                        created_at:datetime((string) $entry->published),
                        updated_at:($updated) ? datetime((string) $entry->updated) : null
                    );

                    $trigger->call("import_chyrp_page", $entry, $page);
                }
            }

            $trigger->call("import", $imports);

            Flash::notice(
                __("Chyrp Lite content successfully imported!"),
                "import"
            );
        }

        /**
         * Function: admin_modules
         * Module enabling/disabling.
         */
        public function admin_modules(
        ): void {
            if (!Visitor::current()->group->can("toggle_extensions"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to toggle extensions.")
                );

            $config = Config::current();

            $this->context["enabled_modules"]  = array();
            $this->context["disabled_modules"] = array();
            $folder = new DirectoryIterator(MODULES_DIR);
            $classes = array();

            foreach ($folder as $item) {
                if ($item->isDot() or !$item->isDir())
                    continue;

                $name = $item->getFilename();

                if (!is_file(MODULES_DIR.DIR.$name.DIR.$name.".php"))
                    continue;

                load_translator($name, MODULES_DIR.DIR.$name.DIR."locale");

                if (!isset($classes[$name]))
                    $classes[$name] = array($name);
                else
                    array_unshift($classes[$name], $name);

                $info = load_info(MODULES_DIR.DIR.$name.DIR."info.php");

                # List of modules conflicting with this one (installed or not).
                if (!empty($info["conflicts"])) {
                    $classes[$name][] = "conflicts";

                    foreach ($info["conflicts"] as $conflict)
                        if (file_exists(MODULES_DIR.DIR.$conflict.DIR.$conflict.".php")) {
                            $classes[$name][] = "conflict_".$conflict;

                            if (module_enabled($conflict)) {
                                if (!in_array("error", $classes[$name]))
                                    $classes[$name][] = "error";
                            }
                        }
                }

                # List of modules depended on by this one (installed or not).
                if (!empty($info["dependencies"])) {
                    $classes[$name][] = "dependencies";

                    foreach ($info["dependencies"] as $dependency) {
                        if (!file_exists(MODULES_DIR.DIR.$dependency.DIR.$dependency.".php")) {
                            if (!in_array("missing_dependency", $classes[$name]))
                                $classes[$name][] = "missing_dependency";

                            if (!in_array("error", $classes[$name]))
                                $classes[$name][] = "error";
                        } else {
                            if (!module_enabled($dependency)) {
                                if (!in_array("error", $classes[$name]))
                                    $classes[$name][] = "error";
                            }

                            fallback($classes[$dependency], array());
                            $classes[$dependency][] = "needed_by_".$name;
                        }

                        $classes[$name][] = "needs_".$dependency;
                    }
                }

                # We don't use the module_enabled() helper function - 
                # this allows for disabling a module that has been cancelled.
                $category = (in_array($name, $config->enabled_modules)) ?
                    "enabled_modules" :
                    "disabled_modules" ;

                $this->context[$category][$name] = array_merge(
                    $info,
                    array("classes" => $classes[$name])
                );
            }

            $this->display(
                "pages".DIR."modules"
            );
        }

        /**
         * Function: admin_feathers
         * Feather enabling/disabling.
         */
        public function admin_feathers(
        ): void {
            if (!Visitor::current()->group->can("toggle_extensions"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to toggle extensions.")
                );

            $config = Config::current();

            $this->context["enabled_feathers"]  = array();
            $this->context["disabled_feathers"] = array();
            $folder = new DirectoryIterator(FEATHERS_DIR);

            foreach ($folder as $item) {
                if ($item->isDot() or !$item->isDir())
                    continue;

                $name = $item->getFilename();

                if (!is_file(FEATHERS_DIR.DIR.$name.DIR.$name.".php"))
                    continue;

                load_translator($name, FEATHERS_DIR.DIR.$name.DIR."locale");

                # We don't use the feather_enabled() helper function - 
                # this allows for disabling a feather that has been cancelled.
                $category = (in_array($name, $config->enabled_feathers)) ?
                    "enabled_feathers" :
                    "disabled_feathers" ;

                $this->context[$category][$name] = load_info(
                    FEATHERS_DIR.DIR.$name.DIR."info.php"
                );
            }

            $this->display(
                "pages".DIR."feathers"
            );
        }

        /**
         * Function: admin_themes
         * Theme switching/previewing.
         */
        public function admin_themes(
        ): void {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            if (!empty($_SESSION['theme']))
                Flash::message(
                    __("You are currently previewing a theme.").
                    ' <a href="'.url("preview_theme").'">'.__("Stop!").'</a>'
                );

            $this->context["themes"] = array();
            $folder = new DirectoryIterator(THEMES_DIR);

            foreach ($folder as $item) {
                if ($item->isDot() or !$item->isDir())
                    continue;

                $name = $item->getFilename();

                load_translator($name, THEMES_DIR.DIR.$name.DIR."locale");

                $this->context["themes"][$name] = load_info(
                    THEMES_DIR.DIR.$name.DIR."info.php"
                );
            }

            $this->display(
                "pages".DIR."themes"
            );
        }

        /**
         * Function: admin_enable
         * Enables a module or feather.
         */
        public function admin_enable(
        ): never {
            $config  = Config::current();
            $visitor = Visitor::current();

            if (!$visitor->group->can("toggle_extensions"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to toggle extensions.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['extension']) or empty($_POST['type']))
                error(
                    __("No Extension Specified"),
                    __("You did not specify an extension to enable."),
                    code:400
                );

            $type = ($_POST['type'] == "module") ?
                "module" :
                "feather" ;

            $array = ($type == "module") ?
                "enabled_modules" :
                "enabled_feathers" ;

            $folder = ($type == "module") ?
                MODULES_DIR :
                FEATHERS_DIR ;

            $name = str_replace(array(".", DIR), "", $_POST['extension']);
            $class = camelize($name);

            if (in_array($name, $config->$array))
                error(
                    __("Error"),
                    __("Extension already enabled."),
                    code:409
                );

            if (!file_exists($folder.DIR.$name.DIR.$name.".php"))
                show_404(
                    __("Not Found"),
                    __("Extension not found.")
                );

            load_translator($name, $folder.DIR.$name.DIR."locale");

            require $folder.DIR.$name.DIR.$name.".php";

            if (method_exists($class, "__install"))
                call_user_func(array($class, "__install"));

            $config->set(
                $array,
                array_merge($config->$array,array($name))
            );

            $info = load_info($folder.DIR.$name.DIR."info.php");

            foreach ($info["notifications"] as $notification)
                Flash::message($notification);

            Flash::notice(
                __("Extension enabled."),
                pluralize($type)
            );
        }

        /**
         * Function: admin_disable
         * Disables a module or feather.
         */
        public function admin_disable(
        ): never {
            $config  = Config::current();
            $visitor = Visitor::current();

            if (!$visitor->group->can("toggle_extensions"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to toggle extensions.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['extension']) or empty($_POST['type']))
                error(
                    __("No Extension Specified"),
                    __("You did not specify an extension to disable."),
                    code:400
                );

            $type = ($_POST['type'] == "module") ?
                "module" :
                "feather" ;

            $array = ($type == "module") ?
                "enabled_modules" :
                "enabled_feathers" ;

            $folder = ($type == "module") ?
                MODULES_DIR :
                FEATHERS_DIR ;

            $name = str_replace(array(".", DIR), "", $_POST['extension']);
            $class = camelize($name);

            if (!in_array($name, $config->$array))
                error(
                    __("Error"),
                    __("Extension already disabled."),
                    code:409
                );

            if (!file_exists($folder.DIR.$name.DIR.$name.".php"))
                show_404(
                    __("Not Found"),
                    __("Extension not found.")
                );

            if (method_exists($class, "__uninstall"))
                call_user_func(array($class, "__uninstall"), !empty($_POST['confirm']));

            # Cancel the extension to prevent trigger responses after __uninstall().
            if ($type == "module")
                cancel_module($name);
            else
                cancel_feather($name);

            $config->set(
                $array,
                array_diff($config->$array, array($name))
            );

            Flash::notice(
                __("Extension disabled."),
                pluralize($type)
            );
        }

        /**
         * Function: admin_change_theme
         * Changes the theme.
         */
        public function admin_change_theme(
        ): never {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['theme']))
                error(
                    __("No Theme Specified"),
                    __("You did not specify which theme to select."),
                    code:400
                );

            if (!isset($_POST['change']) or $_POST['change'] != "indubitably")
                $this->admin_preview_theme();

            $theme = str_replace(array(".", DIR), "", $_POST['theme']);
            Config::current()->set("theme", $theme);

            load_translator($theme, THEMES_DIR.DIR.$theme.DIR."locale");

            $info = load_info(THEMES_DIR.DIR.$theme.DIR."info.php");

            foreach ($info["notifications"] as $notification)
                Flash::message($notification);

            Flash::notice(
                __("Theme changed."),
                "themes"
            );
        }

        /**
         * Function: admin_preview_theme
         * Previews the theme.
         */
        public function admin_preview_theme(
        ): never {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            $trigger = Trigger::current();

            if (empty($_POST['theme'])) {
                unset($_SESSION['theme']);
                $trigger->call("preview_theme_stopped");

                Flash::notice(
                    __("Preview stopped."),
                    "themes"
                );
            }

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            $_SESSION['theme'] = str_replace(array(".", DIR), "", $_POST['theme']);
            $trigger->call("preview_theme_started");

            Flash::notice(
                __("Preview started."),
                Config::current()->url
            );
        }

        /**
         * Function: admin_general_settings
         * General Settings page.
         */
        public function admin_general_settings(
        ): void {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            if (empty($_POST)) {
                $this->display(
                    "pages".DIR."general_settings",
                    array(
                        "locales" => locales(),
                        "timezones" => timezones()
                    )
                );

                return;
            }

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['email']))
                error(
                    __("Error"),
                    __("Email address cannot be blank."),
                    code:422
                );

            if (!is_email($_POST['email']))
                error(
                    __("Error"),
                    __("Invalid email address."),
                    code:422
                );

            if (!empty($_POST['url']) and !is_url($_POST['url']))
                error(
                    __("Error"),
                    __("Invalid canonical URL."),
                    code:422
                );

            $config = Config::current();

            fallback($_POST['name'], "");
            fallback($_POST['description'], "");
            fallback($_POST['url'], "");
            fallback($_POST['timezone'], "Atlantic/Reykjavik");
            fallback($_POST['locale'], "en_US");

            $check_updates_last = (empty($_POST['check_updates'])) ?
                0 :
                $config->check_updates_last ;

            $url = rtrim(
                add_scheme(
                    oneof($_POST['url'], $config->chyrp_url)
                ),
                "/"
            );

            $config->set("name", strip_tags($_POST['name']));
            $config->set("description", strip_tags($_POST['description']));
            $config->set("url", $url);
            $config->set("email", $_POST['email']);
            $config->set("timezone", $_POST['timezone']);
            $config->set("locale", $_POST['locale']);
            $config->set("monospace_font", !empty($_POST['monospace_font']));
            $config->set("check_updates", !empty($_POST['check_updates']));
            $config->set("check_updates_last", $check_updates_last);

            Flash::notice(
                __("Settings updated."),
                "general_settings"
            );
        }

        /**
         * Function: admin_content_settings
         * Content Settings page.
         */
        public function admin_content_settings(
        ): void {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            $post_statuses = array(
                array(
                    "id" => Post::STATUS_DRAFT,
                    "name" => __("Draft", "admin")
                ),
                array(
                    "id" => Post::STATUS_PUBLIC,
                    "name" => __("Public", "admin")
                ),
                array(
                    "id" => Post::STATUS_PRIVATE,
                    "name" => __("Private", "admin")
                ),
                array(
                    "id" => Post::STATUS_REG_ONLY,
                    "name" => __("All registered users", "admin")
                ),
                array(
                    "id" => Post::STATUS_SCHEDULED,
                    "name" => __("Scheduled", "admin")
                )
            );

            $page_statuses = array(
                array(
                    "id" => Page::STATUS_LISTED,
                    "name" => __("Public and visible in pages list", "admin")
                ),
                array(
                    "id" => Page::STATUS_PUBLIC,
                    "name" => __("Public", "admin")
                ),
                array(
                    "id" => Page::STATUS_TEASED,
                    "name" => __("Private and visible in pages list", "admin")
                ),
                array(
                    "id" => Page::STATUS_PRIVATE,
                    "name" => __("Private", "admin")
                )
            );

            $feed_formats = array(
                array(
                    "name" => "Atom",
                    "class" => "AtomFeed"
                ),
                array(
                    "name" => "RSS",
                    "class" => "RSSFeed"
                ),
                array(
                    "name" => "JSON",
                    "class" => "JSONFeed"
                )
            );

            if (empty($_POST)) {
                $this->display(
                    "pages".DIR."content_settings",
                    array(
                        "post_statuses" => $post_statuses,
                        "page_statuses" => $page_statuses,
                        "feed_formats" => $feed_formats
                    )
                );

                return;
            }

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            fallback($_POST['posts_per_page'], 5);
            fallback($_POST['admin_per_page'], 25);
            fallback($_POST['default_post_status'], "public");
            fallback($_POST['default_page_status'], "listed");
            fallback($_POST['feed_items'], 20);
            fallback($_POST['feed_format'], "AtomFeed");
            fallback($_POST['uploads_path'], "");
            fallback($_POST['uploads_limit'], 10);

            $separator = preg_quote(DIR, "~");

            preg_match(
                "~^(".$separator.")?(.*?)(".$separator.")?$~",
                $_POST['uploads_path'],
                $matches
            );

            fallback($matches[1], DIR);
            fallback($matches[2], "uploads");
            fallback($matches[3], DIR);

            $config = Config::current();
            $config->set("posts_per_page", abs((int) $_POST['posts_per_page']));
            $config->set("admin_per_page", abs((int) $_POST['admin_per_page']));
            $config->set("default_post_status", $_POST['default_post_status']);
            $config->set("default_page_status", $_POST['default_page_status']);
            $config->set("feed_items", abs((int) $_POST['feed_items']));
            $config->set("feed_format", $_POST['feed_format']);
            $config->set("uploads_path", $matches[1].$matches[2].$matches[3]);
            $config->set("uploads_limit", (int) $_POST['uploads_limit']);
            $config->set("search_pages", !empty($_POST['search_pages']));
            $config->set("send_pingbacks", !empty($_POST['send_pingbacks']));
            $config->set("enable_emoji", !empty($_POST['enable_emoji']));
            $config->set("enable_markdown", !empty($_POST['enable_markdown']));

            Flash::notice(
                __("Settings updated."),
                "content_settings"
            );
        }

        /**
         * Function: admin_user_settings
         * User Settings page.
         */
        public function admin_user_settings(
        ): void {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            if (empty($_POST)) {
                $this->display(
                    "pages".DIR."user_settings",
                    array("groups" => Group::find(array("order" => "id DESC")))
                );

                return;
            }

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            fallback($_POST['default_group'], 0);
            fallback($_POST['guest_group'], 0);

            $default_group = new Group($_POST['default_group']);

            if ($default_group->no_results)
                error(
                    __("Gone"),
                    __("New default group does not exist."),
                    code:410
                );

            $guest_group = new Group($_POST['guest_group']);

            if ($guest_group->no_results)
                error(
                    __("Gone"),
                    __("New guest group does not exist."),
                    code:410
                );

            $correspond = (
                !empty($_POST['email_activation']) or
                !empty($_POST['email_correspondence'])
            ) ?
                true :
                false ;

            $config = Config::current();
            $config->set("can_register", !empty($_POST['can_register']));
            $config->set("email_activation", !empty($_POST['email_activation']));
            $config->set("email_correspondence", $correspond);
            $config->set("default_group", (int) $default_group->id);
            $config->set("guest_group", (int) $guest_group->id);

            Flash::notice(
                __("Settings updated."),
                "user_settings"
            );
        }

        /**
         * Function: admin_route_settings
         * Route Settings page.
         */
        public function admin_route_settings(
        ): void {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            if (empty($_POST)) {
                $this->display(
                    "pages".DIR."route_settings"
                );

                return;
            }

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            $route = Route::current();
            $config = Config::current();

            # If clean URLs are already active then rewrite support must be ok.
            $rewrites_tested = $config->clean_urls;

            if (!empty($_POST['enable_homepage']) and !$config->enable_homepage) {
                $route->add("/", "page;url=home");

                if (Page::check_url("home") == "home" ) {
                    $page = Page::add(
                        title:__("My Awesome Homepage"),
                        body:__("Nothing here yet!"),
                        parent_id:0,
                        public:true,
                        show_in_list:true,
                        clean:"home"
                    );

                    Flash::notice(
                        __("Page created.").' <a href="'.$page->url().'">'.
                        __("View page!").'</a>'
                    );
                }
            }

            if (empty($_POST['enable_homepage']) and $config->enable_homepage)
                $route->remove("/");

            fallback($_POST['post_url'], "(year)/(month)/(day)/(url)/");

            $config->set("clean_urls", !empty($_POST['clean_urls']));
            $config->set("post_url", trim($_POST['post_url'], "/ ")."/");
            $config->set("enable_homepage", !empty($_POST['enable_homepage']));

            # Test URL rewrite support and disable clean URLs if not detected.
            if ($config->clean_urls and !$rewrites_tested) {
                $dirty_test = get_remote($config->url."/?feed");
                $clean_test = get_remote($config->url."/feed/");

                if ($dirty_test !== false and $clean_test === false) {
                    $config->set("clean_urls", false);

                    Flash::warning(
                        __("Clean URLs have been disabled because URL rewriting is not active.")
                    );
                }
            }

            Flash::notice(
                __("Settings updated."),
                "route_settings"
            );
        }

        /**
         * Function: admin_download_rewrites
         * Downloads the files required for URL rewrite support.
         */
        public function admin_download_rewrites(
        ): void {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            $conf = array(
                ".htaccess" => htaccess_conf(),
                "caddyfile" => caddyfile_conf(),
                "include.conf" => nginx_conf()
            );

            $archived = zip_archive(array_filter($conf));
            file_attachment($archived, "URL_rewrite_files.zip");
        }

        /**
         * Function: admin_login
         * Mask for MainController->login().
         */
        public function admin_login(
        ): never {
            if (logged_in())
                Flash::notice(
                    __("You are already logged in."),
                    "/"
                );

            $_SESSION['redirect_to'] = url("/");
            redirect(url("login", MainController::current()));
        }

        /**
         * Function: admin_logout
         * Mask for MainController->logout().
         */
        public function admin_logout(
        ): never {
            redirect(url("logout", MainController::current()));
        }

        /**
         * Function: admin_help
         * Serves help pages for core and extensions.
         */
        public function admin_help(
        ): void {
            if (empty($_GET['id']))
                error(
                    __("Error"),
                    __("Missing argument."),
                    code:400
                );

            $template = str_replace(
                array(DIR, "/", "<", ">"),
                "",
                $_GET['id']
            );

            $this->display(
                "help".DIR.$template,
                array(),
                __("Help")
            );
        }

        /**
         * Function: navigation_context
         * Returns the navigation context for Twig.
         */
        private function navigation_context(
            $action
        ): array {
            $trigger = Trigger::current();
            $visitor = Visitor::current();

            $navigation = array();

            $navigation["write"] = array(
                "children" => array(),
                "selected" => false,
                "title" => __("Write")
            );
            $navigation["manage"] = array(
                "children" => array(),
                "selected" => false,
                "title" => __("Manage")
            );
            $navigation["settings"] = array(
                "children" => array(),
                "selected" => false,
                "title" => __("Settings")
            );
            $navigation["extend"] = array(
                "children" => array(),
                "selected" => false,
                "title" => __("Extend")
            );

            $write    =& $navigation["write"]["children"];
            $manage   =& $navigation["manage"]["children"];
            $settings =& $navigation["settings"]["children"];
            $extend   =& $navigation["extend"]["children"];

            # Write:

            if ($visitor->group->can("add_page"))
                $write["write_page"] = array("title" => __("Page"));

            if ($visitor->group->can("add_draft", "add_post")) {
                foreach (Config::current()->enabled_feathers as $feather) {
                    if (!feather_enabled($feather))
                        continue;

                    $info = load_info(FEATHERS_DIR.DIR.$feather.DIR."info.php");

                    $write["write_post/feather/".$feather] = array(
                        "title" => $info["name"],
                        "feather" => $feather
                    );
                }
            }

            $trigger->filter($write, "write_nav");

            foreach ($write as $child => &$attributes) {
                $attributes["selected"] = (
                    $action == $child or
                    (
                        isset($attributes["selected"]) and
                        in_array($action, (array) $attributes["selected"])
                    ) or
                    (
                        isset($_GET['feather']) and
                        isset($attributes["feather"]) and
                        $_GET['feather'] == $attributes["feather"]
                    )
                );

                if ($attributes["selected"] == true)
                    $navigation["write"]["selected"] = true;
            }

            # Manage:

            if (Post::any_editable() or Post::any_deletable())
                $manage["manage_posts"] = array(
                    "title" => __("Posts"),
                    "selected" => array(
                        "edit_post",
                        "delete_post"
                    )
                );

            if ($visitor->group->can("edit_page", "delete_page"))
                $manage["manage_pages"] = array(
                    "title" => __("Pages"),
                    "selected" => array(
                        "edit_page",
                        "delete_page"
                    )
                );

            if ($visitor->group->can("add_user", "edit_user", "delete_user"))
                $manage["manage_users"] = array(
                    "title" => __("Users"),
                    "selected" => array(
                        "edit_user",
                        "delete_user",
                        "new_user"
                    )
                );

            if ($visitor->group->can("add_group", "edit_group", "delete_group"))
                $manage["manage_groups"] = array(
                    "title" => __("Groups"),
                    "selected" => array(
                        "edit_group",
                        "delete_group",
                        "new_group"
                    )
                );

            if ($visitor->group->can("edit_post", "edit_page", true))
                $manage["manage_uploads"] = array("title" => __("Uploads"));

            $trigger->filter($manage, "manage_nav");

            if ($visitor->group->can("import_content"))
                $manage["import"] = array("title" => __("Import"));

            if ($visitor->group->can("export_content"))
                $manage["export"] = array("title" => __("Export"));

            foreach ($manage as $child => &$attributes) {
                $attributes["selected"] = (
                    $action == $child or
                    (
                        isset($attributes["selected"]) and
                        in_array($action, (array) $attributes["selected"])
                    )
                );

                if ($attributes["selected"] == true)
                    $navigation["manage"]["selected"] = true;
            }

            # Settings:

            if ($visitor->group->can("change_settings")) {
                $settings["general_settings"] = array("title" => __("General"));
                $settings["content_settings"] = array("title" => __("Content"));
                $settings["user_settings"] = array("title" => __("Users"));
                $settings["route_settings"] = array("title" => __("Routes"));
            }

            $trigger->filter($settings, "settings_nav");

            foreach ($settings as $child => &$attributes) {
                $attributes["selected"] = (
                    $action == $child or
                    (
                        isset($attributes["selected"]) and
                        in_array($action, (array) $attributes["selected"])
                    )
                );

                if ($attributes["selected"] == true)
                    $navigation["settings"]["selected"] = true;
            }

            # Extend:

            if ($visitor->group->can("toggle_extensions")) {
                $extend["modules"] = array("title" => __("Modules"));
                $extend["feathers"] = array("title" => __("Feathers"));
                $extend["themes"] = array("title" => __("Themes"));
            }

            $trigger->filter($extend, "extend_nav");

            foreach ($extend as $child => &$attributes) {
                $attributes["selected"] = (
                    $action == $child or
                    (
                        isset($attributes["selected"]) and
                        in_array($action, (array) $attributes["selected"])
                    )
                );

                if ($attributes["selected"] == true)
                    $navigation["extend"]["selected"] = true;
            }

            return $navigation;
        }

        /**
         * Function: display
         * Displays the page.
         *
         * Parameters:
         *     $template - The template file to display.
         *     $context - The context to be supplied to Twig.
         *     $title - The title for the page (optional).
         *     $pagination - <Paginator> instance (optional).
         *
         * Notes:
         *     $template is supplied sans ".twig" and relative to /admin/
         *     for core and extensions.
         *
         *     $title defaults to a camelization of the template filename,
         *     e.g. foo_bar -> Foo Bar.
         *
         *     $pagination will be inferred from the context if not supplied.
         */
        public function display(
            $template,
            $context = array(),
            $title = "",
            $pagination = null
        ): void {
            $config = Config::current();
            $route = Route::current();
            $trigger = Trigger::current();

            if ($this->displayed == true)
                return;

            $this->displayed = true;

            # Discover pagination in the context.
            if (!isset($pagination)) {
                foreach ($context as $item) {
                    if ($item instanceof Paginator) {
                        $pagination = $item;
                        break;
                    }
                }
            }

            $this->context                       = array_merge($context, $this->context);
            $this->context["ip"]                 = $_SERVER['REMOTE_ADDR'];
            $this->context["DIR"]                = DIR;
            $this->context["version"]            = CHYRP_VERSION;
            $this->context["codename"]           = CHYRP_CODENAME;
            $this->context["debug"]              = DEBUG;
            $this->context["now"]                = time();
            $this->context["site"]               = $config;
            $this->context["flash"]              = Flash::current();
            $this->context["theme"]              = Theme::current();
            $this->context["trigger"]            = $trigger;
            $this->context["route"]              = $route;
            $this->context["visitor"]            = Visitor::current();
            $this->context["visitor"]->logged_in = logged_in();
            $this->context["title"]              = fallback($title, camelize($template, true));
            $this->context["pagination"]         = $pagination;
            $this->context["navigation"]         = $this->navigation_context($route->action);
            $this->context["feathers"]           = Feathers::$instances;
            $this->context["modules"]            = Modules::$instances;
            $this->context["POST"]               = $_POST;
            $this->context["GET"]                = $_GET;
            $this->context["sql_queries"]        =& SQL::current()->queries;
            $this->context["sql_debug"]          =& SQL::current()->debug;

            Update::check();

            $trigger->filter($this->context, "twig_context_admin");
            $this->twig->display($template.".twig", $this->context);
        }

        /**
         * Function: current
         * Returns a singleton reference to the current class.
         */
        public static function & current(
        ): self {
            static $instance = null;
            $instance = (empty($instance)) ? new self() : $instance ;
            return $instance;
        }
    }
