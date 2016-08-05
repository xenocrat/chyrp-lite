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
                             '|/search/([^/]+)/|'                           => '/?action=search&query=$1',
                             '|/search/|'                                   => '/?action=search',
                             '|/archive/([0-9]{4})/([0-9]{2})/([0-9]{2})/|' => '/?action=archive&year=$1&month=$2&day=$3',
                             '|/archive/([0-9]{4})/([0-9]{2})/|'            => '/?action=archive&year=$1&month=$2',
                             '|/archive/([0-9]{4})/|'                       => '/?action=archive&year=$1',
                             '|/random/([^/]+)/|'                           => '/?action=random&feather=$1',
                             '|/random/|'                                   => '/?action=random',
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
         * Loads the Twig parser. Theme class sets up the l10n domain.
         */
        private function __construct() {
            $this->feed = (isset($_GET['feed']) or (isset($_GET['action']) and $_GET['action'] == "feed"));
            $this->post_limit = Config::current()->posts_per_page;

            $cache = (is_writable(CACHES_DIR.DIR."twig") and !PREVIEWING and (!DEBUG or CACHE_TWIG)) ?
                CACHES_DIR.DIR."twig" : false ;

            if (defined('THEME_DIR')) {
                $loader = new Twig_Loader_Filesystem(THEME_DIR);
                $this->twig = new Twig_Environment($loader, array("debug" => DEBUG,
                                                                  "strict_variables" => DEBUG,
                                                                  "charset" => "UTF-8",
                                                                  "cache" => $cache,
                                                                  "autoescape" => false));
                $this->twig->addExtension(new Leaf());
                $this->twig->registerUndefinedFunctionCallback("twig_callback_missing_function");
                $this->twig->registerUndefinedFilterCallback("twig_callback_missing_filter");
            }
        }

        /**
         * Function: parse
         * Route constructor calls this to interpret clean URLs and determine the action.
         */
        public function parse($route) {
            $config = Config::current();

            # If they're just at / and that's not a custom route, don't bother with all this.
            if (empty($route->arg[0]) and !isset($config->routes["/"]))
                return $route->action = "index";

            # Protect non-responder functions.
            if (in_array($route->arg[0], array("__construct", "parse", "post_from_url", "display", "current")))
                show_404();

            # Discover feeds.
            if (preg_match("/\/feed\/?$/", $route->request)) {
                $this->feed = true;
                $this->post_limit = $config->feed_items;

                # Don't set $route->action to "feed" (bottom of this function).
                if ($route->arg[0] == "feed")
                    return $route->action = "index";
            }

            # Discover pagination.
            if (preg_match_all("/\/((([^_\/]+)_)?page)\/([0-9]+)/", $route->request, $page_matches)) {
                foreach ($page_matches[1] as $key => $page_var)
                    $_GET[$page_var] = (int) $page_matches[4][$key];

                # Don't fool ourselves into thinking we're viewing a page.
                if ($route->arg[0] == $page_matches[1][0])
                    return $route->action = (isset($config->routes["/"])) ? $config->routes["/"] : "index" ;
            }

            # Viewing a post by its ID.
            if ($route->arg[0] == "id") {
                $_GET['id'] = $route->arg[1];
                return $route->action = "id";
            }

            # Archive.
            if ($route->arg[0] == "archive") {
                # Make sure they're numeric; could be a "/page/" in there.
                if (isset($route->arg[1]) and is_numeric($route->arg[1]))
                    $_GET['year'] = $route->arg[1];
                if (isset($route->arg[2]) and is_numeric($route->arg[2]))
                    $_GET['month'] = $route->arg[2];
                if (isset($route->arg[3]) and is_numeric($route->arg[3]))
                    $_GET['day'] = $route->arg[3];

                return $route->action = "archive";
            }

            # Search.
            if ($route->arg[0] == "search") {
                if (isset($route->arg[1]))
                    $_GET['query'] = $route->arg[1];

                return $route->action = "search";
            }

            # Test custom routes and populate $_GET parameters if the route expression matches.
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
         *     $route - The route object to respond to.
         *     $request - The request URI to parse.
         *     $return_post - Return a post instead of responding to the route?
         */
        public function post_from_url($route, $request, $return_post = false) {
            $config = Config::current();

            $post_url_regex = "";
            $url_parameters = array();
            $post_url_parts = preg_split("!(\([^)]+\))!",
                                         $config->post_url,
                                         null,
                                         PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            Trigger::current()->filter(Post::$url_attrs, "url_code");

            foreach ($post_url_parts as $part)
                if (isset(Post::$url_attrs[$part])) {
                    $post_url_regex .= Post::$url_attrs[$part];
                    $url_parameters[] = trim($part, "()");
                } else
                    $post_url_regex .= preg_quote($part, "/");

            if (preg_match("/^$post_url_regex/", ltrim($request, "/"), $matches)) {
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
                                              "month" => when("%B", $month, true),
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
                    error(__("Error"), __("Please enter a valid year and month."), null, 422);

                $timestamp = mktime(0, 0, 0, $_GET['month'], oneof(@$_GET['day'], 1), $_GET['year']);

                $depth = isset($_GET['day']) ?
                    "day" : (isset($_GET['month']) ?
                        "month" : (isset($_GET['year']) ?
                            "year" : ""));

                $this->display("pages".DIR."archive",
                               array("posts" => $posts,
                                     "archive" => array("year" => $_GET['year'],
                                                        "month" => when("%B", $timestamp, true),
                                                        "day" => when("%d", $timestamp, true),
                                                        "timestamp" => $timestamp,
                                                        "depth" => $depth),
                                     "preceding" => $preceding),
                               _f("Archive of %s", when("%B %Y", $timestamp, true)));
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
                !substr_count($_SERVER['REQUEST_URI'], "%2F")) # Searches with / and clean URLs = server 404.
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
                           fix(_f("Search results for \"%s\"", $_GET['query'])));
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
                error(__("Error"),
                      __("The post cannot be displayed because the template for this feather was not found."), null, 501);

            if ($post->status == "draft")
                Flash::message(__("This post is a draft."));

            if ($post->status == "scheduled")
                Flash::message(_f("This post is scheduled to be published %s.", when("%c", $post->created_at, true)));

            if ($post->groups() and !substr_count($post->status, "{".Visitor::current()->group->id."}"))
                Flash::message(_f("This post is only visible to the following groups: %s.", $post->groups()));

            $this->display(array("pages".DIR."view", "pages".DIR."index"),
                           array("post" => $post,
                                 "posts" => array($post)),
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
                    return false; # A "link in the chain" is broken.
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
         * Function: id
         * Views a post by its static ID.
         */
        public function id() {
            $post = new Post(fallback($_GET['id']));

            if ($post->no_results)
                return false;

            redirect($post->url());
        }

        /**
         * Function: register
         * Register a visitor as a new user.
         */
        public function register() {
            $config = Config::current();

            if (!$config->can_register)
                Flash::notice(__("This site does not allow registration."), "/");

            if (logged_in())
                Flash::notice(__("You are already logged in."), "/");

            if (!empty($_POST)) {
                if (empty($_POST['login']))
                    Flash::warning(__("Please enter a username for your account."));

                $check = new User(array("login" => $_POST['login']));

                if (!$check->no_results)
                    Flash::warning(__("That username is already in use."));

                if (empty($_POST['password1']) or empty($_POST['password2']))
                    Flash::warning(__("Passwords cannot be blank."));
                elseif ($_POST['password1'] != $_POST['password2'])
                    Flash::warning(__("Passwords do not match."));

                if (empty($_POST['email']))
                    Flash::warning(__("Email address cannot be blank."));
                elseif (!is_email($_POST['email']))
                    Flash::warning(__("Invalid email address."));

                if ($config->enable_captcha and !check_captcha())
                    Flash::warning(__("Incorrect captcha code."));

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
                                                     "to"    => $user->email,
                                                     "link"  => $config->url.
                                                                "/?action=activate&login=".urlencode($user->login).
                                                                "&token=".token(array($user->login, $user->email))));

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
                Flash::notice(__("You cannot activate an account because you are already logged in."), "/");

            if (empty($_GET['token']))
                error(__("Missing Token"), __("You must supply an authentication token."), null, 400);

            $user = new User(array("login" => strip_tags(urldecode(fallback($_GET['login'])))));

            if ($user->no_results)
                show_404(__("Unknown User"), __("That username isn't in our database."));

            if (token(array($user->login, $user->email)) != $_GET['token'])
                error(__("Invalid Token"), __("The authentication token is not valid."), null, 422);

            if (!$user->approved) {
                SQL::current()->update("users",
                                 array("login" => $user->login),
                                 array("approved" => true));

                Flash::notice(__("Your account is now active and you may log in."), "login");
            } else
                Flash::notice(__("Your account has already been activated."), "/");
        }

        /**
         * Function: reset
         * Reset a user password for a given login.
         */
        public function reset() {
            if (logged_in())
                Flash::notice(__("You cannot reset your password because you are already logged in."), "/");

            if (empty($_GET['token']))
                error(__("Missing Token"), __("You must supply an authentication token."), null, 400);

            $user = new User(array("login" => strip_tags(urldecode(fallback($_GET['login'])))));

            if ($user->no_results)
                show_404(__("Unknown User"), __("That username isn't in our database."));

            if (token(array($user->login, $user->email)) != $_GET['token'])
                error(__("Invalid Token"), __("The authentication token is not valid."), null, 422);

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

            Flash::notice(__("We have emailed you a new password."), "login");
        }

        /**
         * Function: login
         * Logs in a user if they provide the username and password.
         */
        public function login() {
            if (logged_in())
                Flash::notice(__("You are already logged in."), "/");

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
                        Flash::notice(__("You must activate your account before you log in."), "/");

                    $_SESSION['user_id'] = $user->id;
                    $_SESSION['cookies_notified'] = true;

                    Trigger::current()->call("user_logged_in", $user);
                    Flash::notice(__("Logged in."), oneof(@$_SESSION['redirect_to'], "/"));
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
                Flash::notice(__("You aren't logged in."), "/");

            session_destroy();
            session();

            $_SESSION['cookies_notified'] = true;
            Flash::notice(__("Logged out."), "/");
        }

        /**
         * Function: controls
         * Updates the current user when the form is submitted.
         */
        public function controls() {
            $_SESSION['redirect_to'] = "controls"; # They'll come here after login if necessary.

            if (!logged_in())
                Flash::notice(__("You must be logged in to access user controls."), "login");

            if (!empty($_POST)) {
                $visitor = Visitor::current();

                if (!empty($_POST['new_password1']))
                    if (empty($_POST['new_password2']) or $_POST['new_password1'] != $_POST['new_password2'])
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
                    $password = (!empty($_POST['new_password1'])) ? User::hashPassword($_POST['new_password1']) : $visitor->password ;

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
                Flash::notice(__("You cannot reset your password because you are already logged in."), "/");

            $config = Config::current();

            if (!$config->email_correspondence)
                Flash::notice(__("Please contact the blog administrator to request a new password."), "/");

            if (!empty($_POST)) {
                $user = new User(array("login" => fallback($_POST['login'])));

                if (!$user->no_results)
                    correspond("reset", array("login" => $user->login,
                                              "to"    => $user->email,
                                              "link"  => $config->url.
                                                         "/?action=reset&login=".urlencode($user->login).
                                                         "&token=".token(array($user->login, $user->email))));

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
            $config = Config::current();
            $trigger = Trigger::current();

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
            else
                fallback($posts, array());

            if (!is_array($posts))
                $posts = $posts->paginated;

            $latest_timestamp = 0;

            foreach ($posts as $post)
                if ($latest_timestamp < strtotime($post->created_at))
                    $latest_timestamp = strtotime($post->created_at);

            $atom = new AtomFeed();

            $atom->open($config->name,
                        $config->description,
                        null,
                        $latest_timestamp);

            foreach ($posts as $post) {
                $updated = ($post->updated) ? $post->updated_at : $post->created_at ;

                $tagged = substr(strstr(url("id/".$post->id), "//"), 2);
                $tagged = str_replace("#", "/", $tagged);
                $tagged = preg_replace("/(".preg_quote(parse_url($post->url(), PHP_URL_HOST)).")/",
                                       "\\1,".when("Y-m-d", $updated).":", $tagged, 1);

                $url = $post->url();
                $trigger->filter($url, "feed_url", $post);

                $atom->entry(oneof($post->title(), ucfirst($post->feather)),
                             $tagged,
                             $post->feed_content(),
                             $url,
                             $post->created_at,
                             $updated,
                             ((!$post->user->no_results) ? oneof($post->user->full_name, $post->user->login) : null),
                             ((!$post->user->no_results) ? $post->user->website : null));

                $trigger->call("feed_item", $post);
            }

            $atom->close();
        }

        /**
         * Function: display
         * Display the page.
         *
         * If "posts" is in the context and the visitor requested a feed, they will be served.
         *
         * Parameters:
         *     $template - The template file or array of fallbacks to display (sans ".twig") relative to THEME_DIR.
         *     $context - The context to be supplied to Twig.
         *     $title - The title for the page.
         */
        public function display($template, $context = array(), $title = "") {
            $config = Config::current();
            $route = Route::current();
            $trigger = Trigger::current();
            $theme = Theme::current();

            if (is_array($template)) {
                foreach ($template as $try)
                    if ($theme->file_exists($try))
                        return $this->display($try, $context, $title);

                error(__("Twig Error"),
                      __("No template files exist in the supplied array of fallbacks."),
                      debug_backtrace());
            }

            $this->displayed = true;

            # Serve feeds.
            if ($this->feed) {
                if ($trigger->exists($route->action."_feed"))
                    return $trigger->call($route->action."_feed", $context);

                if (isset($context["posts"]))
                    return $this->feed($context["posts"]);
            }

            $this->context                       = array_merge($context, $this->context);
            $this->context["ip"]                 = $_SERVER["REMOTE_ADDR"];
            $this->context["DIR"]                = DIR;
            $this->context["theme"]              = $theme;
            $this->context["flash"]              = Flash::current();
            $this->context["trigger"]            = $trigger;
            $this->context["modules"]            = Modules::$instances;
            $this->context["feathers"]           = Feathers::$instances;
            $this->context["title"]              = $theme->title = $title;
            $this->context["site"]               = $config;
            $this->context["visitor"]            = Visitor::current();
            $this->context["route"]              = Route::current();
            $this->context["version"]            = CHYRP_VERSION;
            $this->context["codename"]           = CHYRP_CODENAME;
            $this->context["now"]                = time();
            $this->context["debug"]              = DEBUG;
            $this->context["POST"]               = $_POST;
            $this->context["GET"]                = $_GET;
            $this->context["captcha"]            = generate_captcha();
            $this->context["sql_queries"]        =& SQL::current()->queries;
            $this->context["sql_debug"]          =& SQL::current()->debug;
            $this->context["visitor"]->logged_in = logged_in();

            $trigger->filter($this->context, array("main_context",
                                                   "main_context_".str_replace(DIR, "_", $template)));

            if ($config->cookies_notification and empty($_SESSION['cookies_notified']))
                $theme->cookies_notification();

            try {
                return $this->twig->display($template.".twig", $this->context);
            } catch (Exception $e) {
                $prettify = preg_replace("/([^:]+): (.+)/", "\\1: <code>\\2</code>", $e->getMessage());
                error(__("Twig Error"), $prettify, debug_backtrace());
            }
        }

        /**
         * Function: resort
         * Queue a failpage in the event that none of the routes are successful.
         */
        public function resort($template, $context, $title = null) {
            $this->fallback = array($template, $context, $title);
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
