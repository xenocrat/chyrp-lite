<?php
    /**
     * Class: Main Controller
     * The logic controlling the blog.
     */
    class MainController {
        # Array: $urls
        # An array of clean URL => dirty URL translations.
        public $urls = array('|/id/([0-9]+)/|'                              => '/?action=view&id=$1',
                             '|/page/(([^/]+)/)+|'                          => '/?action=page&url=$2',
                             '|/search/|'                                   => '/?action=search',
                             '|/search/([^/]+)/|'                           => '/?action=search&query=$1',
                             '|/archive/([0-9]{4})/([0-9]{2})/([0-9]{2})/|' => '/?action=archive&year=$1&month=$2&day=$3',
                             '|/archive/([0-9]{4})/([0-9]{2})/|'            => '/?action=archive&year=$1&month=$2',
                             '|/archive/([0-9]{4})/|'                       => '/?action=archive&year=$1',
                             '|/([^/]+)/feed/|'                             => '/?action=$1&feed');

        # Boolean: $displayed
        # Has anything been displayed?
        public $displayed = false;

        # Array: $context
        # Context for displaying pages.
        public $context = array();

        # Boolean: $feed
        # Is the visitor requesting a feed?
        public $feed = false;

        /**
         * Function: __construct
         * Loads the Twig parser into <Theme>, and sets up the theme l10n domain.
         */
        private function __construct() {
            $this->feed = (isset($_GET['feed']) or (isset($_GET['action']) and $_GET['action'] == "feed"));
            $this->post_limit = Config::current()->posts_per_page;

            $cache = (is_writable(INCLUDES_DIR.DIR."caches") and
                      !DEBUG and
                      !PREVIEWING and
                      !defined('CACHE_TWIG') or CACHE_TWIG);

            if (defined('THEME_DIR'))
                $this->twig = new Twig_Loader(THEME_DIR,
                                              $cache ? INCLUDES_DIR.DIR."caches" : null,
                                              "UTF-8");
        }

        /**
         * Function: parse
         * Determines the action.
         */
        public function parse($route) {
            $config = Config::current();

            if (empty($route->arg[0]) and !isset($config->routes["/"])) # If they're just at /, don't bother with all this.
                return $route->action = "index";

            # Protect non-responder functions.
            if (in_array($route->arg[0], array("__construct", "parse", "post_from_url", "display", "current")))
                show_404();

            # Feed
            if (preg_match("/\/feed\/?$/", $route->request)) {
                $this->feed = true;
                $this->post_limit = $config->feed_items;

                if ($route->arg[0] == "feed") # Don't set $route->action to "feed" (bottom of this function).
                    return $route->action = "index";
            }

            # Paginator
            if (preg_match_all("/\/((([^_\/]+)_)?page)\/([0-9]+)/", $route->request, $page_matches)) {
                foreach ($page_matches[1] as $key => $page_var)
                    $_GET[$page_var] = (int) $page_matches[4][$key];

                if ($route->arg[0] == $page_matches[1][0]) # Don't fool ourselves into thinking we're viewing a page.
                    return $route->action = (isset($config->routes["/"])) ? $config->routes["/"] : "index" ;
            }

            # Viewing a post by its ID
            if ($route->arg[0] == "id") {
                $_GET['id'] = $route->arg[1];
                return $route->action = "id";
            }

            # Archive
            if ($route->arg[0] == "archive") {
                # Make sure they're numeric; there might be a /page/ in there.
                if (isset($route->arg[1]) and is_numeric($route->arg[1]))
                    $_GET['year'] = $route->arg[1];
                if (isset($route->arg[2]) and is_numeric($route->arg[2]))
                    $_GET['month'] = $route->arg[2];
                if (isset($route->arg[3]) and is_numeric($route->arg[3]))
                    $_GET['day'] = $route->arg[3];

                return $route->action = "archive";
            }

            # Searching
            if ($route->arg[0] == "search") {
                if (isset($route->arg[1]))
                    $_GET['query'] = $route->arg[1];

                return $route->action = "search";
            }

            # Custom pages added by Modules, Feathers, Themes, etc.
            foreach ($config->routes as $path => $action) {
                if (is_numeric($action))
                    $action = $route->arg[0];

                preg_match_all("/\(([^\)]+)\)/", $path, $matches);

                if ($path != "/")
                    $path = trim($path, "/");

                $escape = preg_quote($path, "/");
                $to_regexp = preg_replace("/\\\\\(([^\)]+)\\\\\)/", "([^\/]+)", $escape);

                if ($path == "/")
                    $to_regexp = "\$";

                if (preg_match("/^\/{$to_regexp}/", $route->request, $url_matches)) {
                    array_shift($url_matches);

                    if (isset($matches[1]))
                        foreach ($matches[1] as $index => $parameter)
                            $_GET[$parameter] = urldecode($url_matches[$index]);

                    $params = explode(";", $action);
                    $action = $params[0];

                    array_shift($params);
                    foreach ($params as $param) {
                        $split = explode("=", $param);
                        $_GET[$split[0]] = oneof(@$split[1], "");
                    }

                    $route->action = $action;
                }
            }

            # Are we viewing a post?
            $this->post_from_url($route, $route->request);

            # Try viewing a page.
            $route->try["page"] = array($route->arg);
        }

        /**
         * Function: post_from_url
         * Check to see if we're viewing a post, and if it is, handle it.
         *
         * This can also be used for grabbing a Post from a given URL.
         *
         * Parameters:
         *     $route - The route to respond to.
         *     $url - If this argument is passed, it will attempt to grab a post from a given URL.
         *            If a post is found by that URL, it will be returned.
         *     $return_post - Return a Post?
         */
        public function post_from_url($route, $request, $return_post = false) {
            $config = Config::current();
            $post_url_parts = preg_split("!(\([^)]+\))!", $config->post_url, null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
            $url_regex = "";
            $url_parameters = array();
            Trigger::current()->filter(Post::$url_attrs, "url_code");

            foreach ($post_url_parts as $part)
                if (isset(Post::$url_attrs[$part])) {
                    $url_regex .= Post::$url_attrs[$part];
                    $url_parameters[] = trim($part, "()");
                } else
                    $url_regex .= preg_quote($part, "/");

            if (preg_match("/^$url_regex/", ltrim($request, "/"), $matches)) {
                $post_url_attrs = array();
                for ($i = 0; $i < count($url_parameters); $i++)
                    $post_url_attrs[$url_parameters[$i]] = urldecode($matches[$i + 1]);

                if ($return_post)
                    return Post::from_url($post_url_attrs);
                else
                    $route->try["view"] = array($post_url_attrs);
            }
        }

        /**
         * Function: key_regexp
         * Converts the values in $config->post_url to regular expressions.
         *
         * Parameters:
         *     $key - Input URL with the keys from <Post.$url_attrs>.
         *
         * Returns:
         *     $key values replaced with their regular expressions from <Routes->$code>.
         */
        private function key_regexp($key) {
            Trigger::current()->filter(Post::$url_attrs, "url_code");
            return str_replace(array_keys(Post::$url_attrs), array_values(Post::$url_attrs), str_replace("/", "\\/", $key));
        }

        /**
         * Function: index
         * Grabs the posts for the main page.
         */
        public function index() {
            $sql = SQL::current();
            $posts = $sql->select("posts",
                                  "posts.id",
                                  array("posts.created_at <=" => datetime(),
                                        "posts.status" => "scheduled"))->fetchAll();

            if (!empty($posts))
                foreach ($posts as $post)
                    $sql->update("posts",
                                 array("id" => $post),
                                 array("status" => "public"));

            $this->display("pages".DIR."index",
                           array("posts" => new Paginator(Post::find(array("placeholders" => true)),
                                                          $this->post_limit)));
        }

        /**
         * Function: archive
         * Grabs the posts for the Archive page when viewing a year or a month.
         */
        public function archive() {
            fallback($_GET['year']);
            fallback($_GET['month']);
            fallback($_GET['day']);

            $lower_bound = mktime(0, 0, 0,
                                  is_numeric($_GET['month']) ? (int) $_GET['month'] : 1 ,
                                  is_numeric($_GET['day']) ? (int) $_GET['day'] : 1 ,
                                  is_numeric($_GET['year']) ? (int) $_GET['year'] : 1970 );

            $preceding = new Post(null, array("where" => array("created_at <" => datetime($lower_bound),
                                                               "status" => "public"),
                                              "order" => "created_at DESC, id DESC"));

            if (isset($_GET['year']) and isset($_GET['month']) and isset($_GET['day']))
                $posts = new Paginator(Post::find(array("placeholders" => true,
                                                        "where" => array("YEAR(created_at)" => $_GET['year'],
                                                                         "MONTH(created_at)" => $_GET['month'],
                                                                         "DAY(created_at)" => $_GET['day'],
                                                                         "status" => "public"))),
                                       $this->post_limit);
            elseif (isset($_GET['year']) and isset($_GET['month']))
                $posts = new Paginator(Post::find(array("placeholders" => true,
                                                        "where" => array("YEAR(created_at)" => $_GET['year'],
                                                                         "MONTH(created_at)" => $_GET['month'],
                                                                         "status" => "public"))),
                                       $this->post_limit);

            $sql = SQL::current();

            if (empty($_GET['year']) or empty($_GET['month'])) {
                if (!empty($_GET['year']))
                    $timestamps = $sql->select("posts",
                                               array("DISTINCT YEAR(created_at) AS year",
                                                     "MONTH(created_at) AS month",
                                                     "created_at AS created_at"),
                                               array("YEAR(created_at)" => $_GET['year'], "status" => "public"),
                                               array("created_at DESC"),
                                               array(),
                                               null,
                                               null,
                                               array("YEAR(created_at)", "MONTH(created_at)"));
                else
                    $timestamps = $sql->select("posts",
                                               array("DISTINCT YEAR(created_at) AS year",
                                                     "MONTH(created_at) AS month",
                                                     "created_at AS created_at"),
                                               array("status" => "public"),
                                               array("created_at DESC"),
                                               array(),
                                               null,
                                               null,
                                               array("YEAR(created_at)", "MONTH(created_at)"));

                $archives = array();
                $archive_hierarchy = array();
                while ($time = $timestamps->fetchObject()) {
                    $year = mktime(0, 0, 0, 1, 0, $time->year);
                    $month = mktime(0, 0, 0, $time->month + 1, 0, $time->year);

                    $posts = Post::find(array("where" => array("YEAR(created_at)" => when("Y", $time->created_at),
                                                               "MONTH(created_at)" => when("m", $time->created_at),
                                                               "status" => "public")));

                    $archives[$month] = array("posts" => $posts,
                                              "year" => $time->year,
                                              "month" => strftime("%B", $month),
                                              "timestamp" => $month,
                                              "url" => url("archive/".when("Y/m/", $time->created_at)));

                    $archive_hierarchy[$year][$month] = $posts;
                }

                $this->display("pages/archive",
                               array("archives" => $archives,
                                     "preceding" => $preceding, # The post preceding the date range chronologically.
                                     "archive_hierarchy" => $archive_hierarchy),
                               __("Archive"));
            } else {
                if (!is_numeric($_GET['year']) or !is_numeric($_GET['month']))
                    error(__("Error"), __("Please enter a valid year and month."));

                $timestamp = mktime(0, 0, 0, $_GET['month'], oneof(@$_GET['day'], 1), $_GET['year']);
                $depth = isset($_GET['day']) ? "day" : (isset($_GET['month']) ? "month" : (isset($_GET['year']) ? "year" : ""));

                $this->display("pages".DIR."archive",
                               array("posts" => $posts,
                                     "archive" => array("year" => $_GET['year'],
                                                        "month" => strftime("%B", $timestamp),
                                                        "day" => strftime("%d", $timestamp),
                                                        "timestamp" => $timestamp,
                                                        "depth" => $depth),
                                     "preceding" => $preceding),
                               _f("Archive of %s", array(strftime("%B %Y", $timestamp))));
            }
        }

        /**
         * Function: search
         * Grabs the posts for a search query.
         */
        public function search() {
            fallback($_GET['query'], "");
            $config = Config::current();
            $_GET['query'] = strip_tags($_GET['query']);

            if ($config->clean_urls and
                substr_count($_SERVER['REQUEST_URI'], "?") and
                !substr_count($_SERVER['REQUEST_URI'], "%2F")) # Searches with / and clean URLs = server 404
                redirect("search/".urlencode($_GET['query'])."/");

            if (empty($_GET['query']))
                Flash::warning(__("Please enter a search term."));

            list($where, $params) = keywords($_GET['query'], "post_attributes.value LIKE :query OR url LIKE :query", "posts");

            $results = Post::find(array("placeholders" => true,
                                        "where" => $where,
                                        "params" => $params));

            $ids = array();
            foreach ($results[0] as $result)
                $ids[] = $result["id"];

            if (!empty($ids))
                $posts = new Paginator(Post::find(array("placeholders" => true,
                                                        "where" => array("id" => $ids))),
                                       $this->post_limit);
            else
                $posts = new Paginator(array());

            $this->display(array("pages".DIR."search", "pages".DIR."index"),
                           array("posts" => $posts,
                                 "search" => $_GET['query']),
                           fix(_f("Search results for \"%s\"", array($_GET['query']))));
        }

        /**
         * Function: drafts
         * Grabs the posts for viewing the Drafts lists.
         */
        public function drafts() {
            $visitor = Visitor::current();

            if (!$visitor->group->can("view_own_draft", "view_draft"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to view drafts."));

            $posts = new Paginator(Post::find(array("placeholders" => true,
                                                    "where" => array("status" => "draft",
                                                                     "user_id" => $visitor->id))),
                                   $this->post_limit);

            $this->display(array("pages".DIR."drafts", "pages".DIR."index"),
                           array("posts" => $posts),
                           __("Drafts"));
        }

        /**
         * Function: view
         * Views a post.
         */
        public function view($attrs = null, $args = array()) {
            if (isset($attrs))
                $post = Post::from_url($attrs, array("drafts" => true));
            else
                $post = new Post(array("url" => @$_GET['url']), array("drafts" => true));

            if ($post->no_results)
                return false;

            if ((oneof(@$attrs["url"], @$attrs["clean"]) == "feed") and # do some checking to see if they're trying
                (count(explode("/", trim($post_url, "/"))) > count($args) or # to view the post or the post's feed.
                 end($args) != "feed"))
                $this->feed = false;

            if (!$post->theme_exists())
                error(__("Error"), __("The feather theme file for this post does not exist. The post cannot be displayed."));

            if ($post->status == "draft")
                Flash::message(__("This post is a draft."));

            if ($post->status == "scheduled")
                Flash::message(_f("This post is scheduled to be published ".relative_time($post->created_at)));

            if ($post->groups() and !substr_count($post->status, "{".Visitor::current()->group->id."}"))
                Flash::message(_f("This post is only visible by the following groups: %s.", $post->groups()));

            $this->display(array("pages".DIR."view", "pages".DIR."index"),
                           array("post" => $post, "posts" => array($post)),
                           $post->title());
        }

        /**
         * Function: page
         * Handles page viewing.
         */
        public function page($urls = null) {
            if (isset($urls)) { # Viewing with clean URLs, e.g. /parent/child/child-of-child/
                $valids = Page::find(array("where" => array("url" => $urls)));

                if (count($valids) == count($urls)) { # Make sure all page slugs are valid.
                    foreach ($valids as $page)
                        if ($page->url == end($urls)) # Loop until we reach the last one.
                            break;
                } else
                    return false; # A "link in the chain" is broken
            } else
                $page = new Page(array("url" => $_GET['url']));

            if ($page->no_results)
                return false; # Page not found; the 404 handling is handled externally.

            $visitor = Visitor::current();

            if (!$page->public and !$visitor->group->can("view_page"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to view this page."));

            $this->display(array("pages".DIR.$page->url, "pages".DIR."page"), array("page" => $page), $page->title);
        }

        /**
         * Function: rss
         * Redirects to /feed (backwards compatibility).
         */
        public function rss() {
            header($_SERVER["SERVER_PROTOCOL"]." 301 Moved Permanently");
            redirect(oneof(@Config::current()->feed_url, url("feed")));
        }

        /**
         * Function: id
         * Views a post by its static ID.
         */
        public function id() {
            $post = new Post($_GET['id']);
            redirect($post->url());
        }

        /**
         * Function: cookies_notification
         * Deliver a notification to comply with EU Directive 2002/58 on Privacy and Electronic Communications.
         */
        public function cookies_notification() {
            if (!isset($_SESSION['cookies_notified']) and Config::current()->cookies_notification) {
                Flash::notice(__("By browsing this website you are agreeing to our use of cookies."));
                $_SESSION['cookies_notified'] = true;
            }
        }

        /**
         * Function: register
         * Register a visitor as a new user.
         */
        public function register() {
            $config = Config::current();
            if (!$config->can_register)
                error(__("Registration Disabled"), __("This site does not allow registration."));

            if (logged_in())
                error(__("Error"), __("You are already logged in."));

            if (!empty($_POST)) {
                if (empty($_POST['login']))
                    Flash::warning(__("Please enter a username for your account."));
                elseif (count(User::find(array("where" => array("login" => $_POST['login'])))))
                    Flash::warning(__("That username is already in use."));

                if (empty($_POST['password1']))
                    Flash::warning(__("Password cannot be blank."));
                elseif ($_POST['password1'] != $_POST['password2'])
                    Flash::warning(__("Passwords do not match."));

                if (empty($_POST['email']))
                    Flash::warning(__("Email address cannot be blank."));
                elseif (!is_email($_POST['email']))
                    Flash::warning(__("Invalid email address."));

                if ($config->enable_captcha and !check_captcha())
                    Flash::warning(__("Incorrect captcha code. Please try again."));

                if (!Flash::exists("warning")) {
                    if ($config->email_activation) {
                        $user = User::add($_POST['login'],
                                          $_POST['password1'],
                                          $_POST['email'],
                                          "",
                                          "",
                                          $config->default_group,
                                          false);
                        correspond("activate", array("login" => $user->login, 
                                                     "to" => $user->email));
                        Flash::notice(__("We have emailed you an activation link."), "/");
                    } else {
                        $user = User::add($_POST['login'],
                                          $_POST['password1'],
                                          $_POST['email']);
                        $_SESSION['user_id'] = $user->id;
                        Flash::notice(__("Registration successful."), "/");
                    }

                    Trigger::current()->call("user_registered", $user);
                }
            }

            $this->display("forms".DIR."user".DIR."register", array(), __("Register"));
        }

        /**
         * Function: activate
         * Approves a user registration for a given login.
         */
        public function activate() {
            if (logged_in())
                error(__("Error"), __("You are already logged in."));

            if (empty($_GET['token']))
                error(__("Error"), __("No token found."));

            $user = new User(array("login" => strip_tags(unfix($_GET['login']))));

            if ($user->no_results)
                error(__("Error"), __("That username isn't in our database."));

            if (token(array($user->login, $user->email)) != $_GET['token'])
                error(__("Error"), __("Invalid token."));

            if (!$user->approved) {
                SQL::current()->update("users",
                                 array("login" => $user->login),
                                 array("approved" => true));

                Flash::notice(__("Your account is now active and you may log in."), "/?action=login");
            } else
                Flash::notice(__("Your account has already been activated."), "/");
        }

        /**
         * Function: reset
         * Reset a user password for a given login.
         */
        public function reset() {
            if (logged_in())
                error(__("Error"), __("You are already logged in."));

            if (empty($_GET['token']))
                error(__("Error"), __("No token found."));

            $user = new User(array("login" => strip_tags(unfix($_GET['login']))));

            if ($user->no_results)
                error(__("Error"), __("That username isn't in our database."));

            if (token(array($user->login, $user->email)) != $_GET['token'])
                error(__("Error"), __("Invalid token."));

            $new_password = random(8);

            correspond("password", array("login" => $user->login,
                                         "to" => $user->email,
                                         "password" => $new_password));

            $user->update($user->login,
                          User::hashPassword($new_password),
                          $user->email,
                          $user->full_name,
                          $user->website,
                          $user->group_id);

            Flash::notice(__("We have emailed you a new password."), "/?action=login");
        }

        /**
         * Function: login
         * Logs in a user if they provide the username and password.
         */
        public function login() {
            if (logged_in())
                error(__("Error"), __("You are already logged in."));

            if (!empty($_POST)) {
                fallback($_POST['login']);
                fallback($_POST['password']);

                # Modules can implement "user_login and "user_authenticate" to offer two-factor authentication.
                # "user_authenticate" trigger function can block the login process by creating a Flash::warning().
                Trigger::current()->call("user_authenticate");

                if (!User::authenticate($_POST['login'], $_POST['password']))
                    Flash::warning(__("Incorrect username and/or password."));

                if (!Flash::exists("warning")) {
                    $user = new User(array("login" => $_POST['login']));

                    if (!$user->approved)
                        error(__("Error"), __("You must activate your account before you log in."));

                    $_SESSION['user_id'] = $user->id;

                    if (!isset($redirect)) {
                        $redirect = oneof(@$_SESSION['redirect_to'], "/");
                        unset($_SESSION['redirect_to']);
                    }

                    Flash::notice(__("Logged in."), $redirect);
                }
            }

            $this->display("forms".DIR."user".DIR."login", array(), __("Log In"));
        }

        /**
         * Function: logout
         * Logs out the current user.
         */
        public function logout() {
            if (!logged_in())
                error(__("Error"), __("You aren't logged in."));

            session_destroy();

            session();

            Flash::notice(__("Logged out."), "/");
        }

        /**
         * Function: controls
         * Updates the current user when the form is submitted.
         */
        public function controls() {
            if (!logged_in())
                error(__("Error"), __("You must be logged in to access this area."));

            if (!empty($_POST)) {
                $visitor = Visitor::current();

                if (!empty($_POST['new_password1']) and $_POST['new_password1'] != $_POST['new_password2'])
                    Flash::warning(__("Passwords do not match."));

                if (empty($_POST['email']))
                    Flash::warning(__("Email address cannot be blank."));
                elseif (!is_email($_POST['email']))
                    Flash::warning(__("Invalid email address."));

                if (!empty($_POST['website']) and !is_url($_POST['website']))
                    Flash::warning(__("Invalid website URL."));

                if (!empty($_POST['website']))
                    $_POST['website'] = add_scheme($_POST['website']);

                if (!Flash::exists("warning")) {
                    $password = (!empty($_POST['new_password1']) and $_POST['new_password1'] == $_POST['new_password2']) ?
                                    User::hashPassword($_POST['new_password1']) :
                                    $visitor->password ;

                    $visitor->update($visitor->login,
                                     $password,
                                     $_POST['email'],
                                     $_POST['full_name'],
                                     $_POST['website'],
                                     $visitor->group->id);

                    Flash::notice(__("Your profile has been updated."), "/");
                }
            }

            $this->display("forms".DIR."user".DIR."controls", array(), __("Controls"));
        }

        /**
         * Function: lost_password
         * Email a password reset link to the registered address of a user.
         */
        public function lost_password() {
            if (logged_in())
                error(__("Error"), __("You are already logged in."));

            if (!Config::current()->email_correspondence) {
                Flash::notice(__("Please contact the blog administrator to request a new password."), "/");
                return;
            }

            if (!empty($_POST)) {
                $user = new User(array("login" => $_POST['login']));

                if (!$user->no_results)
                    correspond("reset", array("login" => $user->login,
                                              "to" => $user->email));

                Flash::notice(__("If that username is in our database, we will email you a password reset link."), "/");
            }

            $this->display("forms".DIR."user".DIR."lost_password", array(), __("Lost Password"));
        }

        /**
         * Function: random
         * Grabs a random post and redirects to it.
         */
        public function random() {
            $sql = SQL::current();
            if (isset($_GET['feather'])) {
                $feather = preg_replace( '|[^a-z]|i', '', $_GET['feather'] );
                $random = $sql->select("posts",
                                       "posts.url",
                                       array("posts.feather" => $feather,
                                             "posts.status" => "public"),
                                       array("ORDER BY" => "RAND()"),
                                       array("LIMIT" => 1))->fetchObject();
                $post = new Post(array("url" => $random->url));
        	} else {
                $random = $sql->select("posts",
                                       "posts.url",
                                       array("posts.status" => "public"),
                                       array("ORDER BY" => "RAND()"),
                                       array("LIMIT" => 1))->fetchObject();
                $post = new Post(array("url" => $random->url));
        	}

            redirect($post->url());
        }

        /**
         * Function: feed
         * Grabs posts for the feed.
         */
        public function feed($posts = null) {
            $result = SQL::current()->select("posts",
                                             "posts.id",
                                             array("posts.status" => "public"),
                                             array("posts.id DESC"),
                                             array(),
                                             Config::current()->feed_items);
            $ids = array();
            foreach ($result->fetchAll() as $index => $row)
                $ids[] = $row["id"];

            if (!empty($ids))
                fallback($posts, Post::find(array("where" => array("id" => $ids))));

            header("Content-Type: application/atom+xml; charset=UTF-8");

            if (!is_array($posts))
                $posts = $posts->paginated;

            $latest_timestamp = 0;
            foreach ($posts as $post)
                if ($latest_timestamp < strtotime($post->created_at))
                    $latest_timestamp = strtotime($post->created_at);

            require INCLUDES_DIR.DIR."feed.php";
        }

        /**
         * Function: display
         * Display the page.
         *
         * If "posts" is in the context and the visitor requested a feed, they will be served.
         *
         * Parameters:
         *     $file - The theme file to display (relative to THEME_DIR).
         *     $context - The context for the file.
         *     $title - The title for the page.
         */
        public function display($file, $context = array(), $title = "") {
            if (is_array($file))
                for ($i = 0; $i < count($file); $i++) {
                    if (file_exists(THEME_DIR.DIR.$file[$i].".twig") or ($i + 1) == count($file))
                        return $this->display($file[$i], $context, $title);
                }

            $this->displayed = true;

            $route = Route::current();
            $trigger = Trigger::current();

            # Serve feeds.
            if ($this->feed) {
                if ($trigger->exists($route->action."_feed"))
                    return $trigger->call($route->action."_feed", $context);

                if (isset($context["posts"]))
                    return $this->feed($context["posts"]);
            }

            $this->cookies_notification();

            $this->context = array_merge($context, $this->context);

            $visitor = Visitor::current();
            $config = Config::current();
            $theme = Theme::current();

            $theme->title = $title;

            $this->context["ip"]           = $_SERVER["REMOTE_ADDR"];
            $this->context["theme"]        = $theme;
            $this->context["flash"]        = Flash::current();
            $this->context["trigger"]      = $trigger;
            $this->context["modules"]      = Modules::$instances;
            $this->context["feathers"]     = Feathers::$instances;
            $this->context["title"]        = $title;
            $this->context["site"]         = $config;
            $this->context["visitor"]      = $visitor;
            $this->context["route"]        = Route::current();
            $this->context["version"]      = CHYRP_VERSION;
            $this->context["now"]          = time();
            $this->context["debug"]        = DEBUG;
            $this->context["POST"]         = $_POST;
            $this->context["GET"]          = $_GET;
            $this->context["sql_queries"] =& SQL::current()->queries;
            $this->context["captcha"]      = generate_captcha();

            $this->context["visitor"]->logged_in = logged_in();

            $this->context["enabled_modules"] = array();
            foreach ($config->enabled_modules as $module)
                $this->context["enabled_modules"][$module] = true;

            $context["enabled_feathers"] = array();
            foreach ($config->enabled_feathers as $feather)
                $this->context["enabled_feathers"][$feather] = true;

            $this->context["sql_debug"] =& SQL::current()->debug;

            $trigger->filter($this->context, array("main_context", "main_context_".str_replace(DIR, "_", $file)));

            try {
                return $this->twig->getTemplate($file.".twig")->display($this->context);
            } catch (Exception $e) {
                $prettify = preg_replace("/([^:]+): (.+)/", "\\1: <code>\\2</code>", $e->getMessage());
                $trace = debug_backtrace();

                if (property_exists($e, "filename") and property_exists($e, "lineno")) {
                    $twig = array("file" => $e->filename, "line" => $e->lineno);
                    array_unshift($trace, $twig);
                }

                error(__("Error"), $prettify, $trace);
            }
        }

        /**
         * Function: resort
         * Queue a failpage in the event that none of the routes are successful.
         */
        public function resort($file, $context, $title = null) {
            $this->fallback = array($file, $context, $title);
            return false;
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

