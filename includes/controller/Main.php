<?php
    /**
     * Class: Main Controller
     * The logic controlling the blog.
     */
    class MainController implements Controller {
        # Array: $urls
        # An array of clean URL => dirty URL translations.
        public $urls = array('|/id/post/([0-9]+)/|'                         => '/?action=id&amp;post=$1',
                             '|/id/page/([0-9]+)/|'                         => '/?action=id&amp;page=$1',
                             '|/search/([^/]+)/|'                           => '/?action=search&amp;query=$1',
                             '|/search/|'                                   => '/?action=search',
                             '|/archive/([0-9]{4})/([0-9]{2})/([0-9]{2})/|' => '/?action=archive&amp;year=$1&amp;month=$2&amp;day=$3',
                             '|/archive/([0-9]{4})/([0-9]{2})/|'            => '/?action=archive&amp;year=$1&amp;month=$2',
                             '|/archive/([0-9]{4})/|'                       => '/?action=archive&amp;year=$1',
                             '|/random/([^/]+)/|'                           => '/?action=random&amp;feather=$1',
                             '|/random/|'                                   => '/?action=random',
                             '|/([^/]+)/feed/|'                             => '/?action=$1&amp;feed');

        # Boolean: $displayed
        # Has anything been displayed?
        public $displayed = false;

        # Array: $context
        # Context for displaying pages.
        public $context = array();

        # Boolean: $clean
        # Does this controller support clean URLs?
        public $clean = true;

        # Boolean: $feed
        # Is the visitor requesting a feed?
        public $feed = false;

        # Array: $protected
        # Methods that cannot respond to actions.
        public $protected = array("__construct", "parse", "feed", "display", "current");

        # Array: $permitted
        # Methods that are exempt from the "view_site" permission.
        public $permitted = array("login", "logout", "register", "activate", "lost_password", "reset");

        /**
         * Function: __construct
         * Loads the Twig parser. Theme class sets up the l10n domain.
         */
        private function __construct() {
            $this->feed = (isset($_GET['feed']) or (isset($_GET['action']) and $_GET['action'] == "feed"));
            $this->post_limit = Config::current()->posts_per_page;

            try {
                $cache = (is_dir(CACHES_DIR.DIR."twig") and is_writable(CACHES_DIR.DIR."twig") and
                                !PREVIEWING and (!DEBUG or CACHE_TWIG)) ? CACHES_DIR.DIR."twig" : false ;

                $loader = new Twig_Loader_Filesystem(THEME_DIR);

                $this->twig = new Twig_Environment($loader, array("debug" => DEBUG,
                                                                  "strict_variables" => DEBUG,
                                                                  "charset" => "UTF-8",
                                                                  "cache" => $cache,
                                                                  "autoescape" => false));
                $this->twig->addExtension(new Leaf());
                $this->twig->registerUndefinedFunctionCallback("twig_callback_missing_function");
                $this->twig->registerUndefinedFilterCallback("twig_callback_missing_filter");
            } catch (Twig_Error $e) {
                error(__("Twig Error"), $e->getMessage(), $e->getTrace());
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

            # Discover feed requests.
            if (preg_match("/\/feed\/?$/", $route->request)) {
                $this->feed = true;
                $this->post_limit = $config->feed_items;

                # Don't set $route->action to "feed" - the display() method handles feeds transparently.
                if ($route->arg[0] == "feed")
                    return $route->action = "index";
            }

            # Static ID of a post or page.
            if ($route->arg[0] == "id") {
                if (isset($route->arg[1]) and isset($route->arg[2]))
                    $_GET[$route->arg[1]] = $route->arg[2];

                return $route->action = "id";
            }

            # Discover pagination.
            if (preg_match_all("/\/((([^_\/]+)_)?page)\/([0-9]+)/", $route->request, $page_matches)) {
                foreach ($page_matches[1] as $key => $page_var)
                    $_GET[$page_var] = (int) $page_matches[4][$key];

                # Don't fool ourselves into thinking we're viewing a page if this is pagination of the "/" route.
                if ($route->arg[0] == $page_matches[1][0])
                    return $route->action = (isset($config->routes["/"])) ? $config->routes["/"] : "index" ;
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

            # Random.
            if ($route->arg[0] == "random") {
                if (isset($route->arg[1]))
                    $_GET['feather'] = $route->arg[1];

                return $route->action = "random";
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
                        fallback($split[1], "");
                        $_GET[$split[0]] = urldecode($split[1]);
                    }

                    $route->action = $action;
                }
            }

            # Are we viewing a post?
            Post::from_url($route->request, $route);

            # Are we viewing a page?
            Page::from_url($route->request, $route);
        }

        /**
         * Function: index
         * Grabs the posts for the main index.
         */
        public function index() {
            $this->display("pages".DIR."index",
                           array("posts" => new Paginator(Post::find(array("placeholders" => true)),
                                                          $this->post_limit)));
        }

        /**
         * Function: archive
         * Grabs the posts for the archive page.
         */
        public function archive() {
            $sql = SQL::current();

            fallback($_GET['year']);
            fallback($_GET['month']);
            fallback($_GET['day']);

            $months = array();
            $posts = new Paginator(array());
            $title = __("Archive");
            $conds = array("status" => "public");
            $timestamp = mktime(0, 0, 0, (is_numeric($_GET['month']) ? (int) $_GET['month'] : 1),
                                         (is_numeric($_GET['day']) ? (int) $_GET['day'] : 1),
                                         (is_numeric($_GET['year']) ? (int) $_GET['year'] : 1970));

            if (is_numeric($_GET['year']) and is_numeric($_GET['month']) and is_numeric($_GET['day']))
                $depth = "day";
            elseif (is_numeric($_GET['year']) and is_numeric($_GET['month']))
                $depth = "month";
            elseif (is_numeric($_GET['year']))
                $depth = "year";
            else
                $depth = "all";

            $next = ($depth == "all") ? array() : $sql->select("posts",
                                                               "*",
                                                               array("status" => "public",
                                                                     "posts.created_at <" => datetime($timestamp)),
                                                               array("posts.id DESC"),
                                                               array(),
                                                               1)->grab("created_at");

            $prev = ($depth == "all") ? array() : $sql->select("posts",
                                                               "*",
                                                               array("status" => "public",
                                                                     "posts.created_at >=" => datetime("@$timestamp +1 $depth")),
                                                               array("posts.id ASC"),
                                                               array(),
                                                               1)->grab("created_at");

            switch ($depth) {
                case 'day':
                    $title = _f("Archive of %s", when("%d %B %Y", $timestamp, true));
                    $posts = new Paginator(Post::find(array("placeholders" => true,
                                                            "where" => array("YEAR(created_at)" => $_GET['year'],
                                                                             "MONTH(created_at)" => $_GET['month'],
                                                                             "DAY(created_at)" => $_GET['day'],
                                                                             "status" => "public"))),
                                           $this->post_limit);
                    break;
                case 'month':
                    $title = _f("Archive of %s", when("%B %Y", $timestamp, true));
                    $posts = new Paginator(Post::find(array("placeholders" => true,
                                                            "where" => array("YEAR(created_at)" => $_GET['year'],
                                                                             "MONTH(created_at)" => $_GET['month'],
                                                                             "status" => "public"))),
                                           $this->post_limit);
                    break;
                case 'year':
                    $title = _f("Archive of %s", when("%Y", $timestamp, true));
                    $conds["YEAR(created_at)"] = $_GET['year'];
                default:
                    $times = $sql->select("posts",
                                          array("DISTINCT YEAR(created_at) AS year",
                                                "MONTH(created_at) AS month",
                                                "created_at AS created_at"),
                                          $conds,
                                          array("created_at DESC"),
                                          array(),
                                          null,
                                          null,
                                          array("YEAR(created_at)", "MONTH(created_at)"));

                    while ($time = $times->fetchObject()) {
                        $key = mktime(0, 0, 0, $time->month + 1, 0, $time->year);
                        $val = Post::find(array("where" => array("YEAR(created_at)" => when("Y", $time->created_at),
                                                                 "MONTH(created_at)" => when("m", $time->created_at),
                                                                 "status" => "public")));

                        if (!empty($val))
                            $months[$key] = $val;
                    }
            }

            $this->display("pages".DIR."archive",
                           array("posts" => $posts,
                                 "months" => $months,
                                 "archive" => array("timestamp" => $timestamp,
                                                    "depth" => $depth,
                                                    "next" => !empty($next) ? strtotime(reset($next)) : false,
                                                    "prev" => !empty($prev) ? strtotime(reset($prev)) : false)),
                           $title);
        }

        /**
         * Function: search
         * Grabs the posts for a search query.
         */
        public function search() {
            $config = Config::current();
            $_GET['query'] = strip_tags(fallback($_GET['query'], ""));

            # Redirect search form submissions to a clean URL, removing "%2F" to avoid a server 404.
            if ($config->clean_urls and substr_count($_SERVER['REQUEST_URI'], "?"))
                redirect("search/".str_ireplace("%2F", "", urlencode($_GET['query']))."/");

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
                           _f("Search results for &#8220;%s&#8221;", fix($_GET['query'])));
        }

        /**
         * Function: drafts
         * Grabs the posts with draft status created by this user.
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
         * Handles post viewing via dirty URL or clean URL e.g. /year/month/day/url/.
         */
        public function view($attrs = null, $arg = array()) {
            $post = (isset($attrs)) ?
                Post::from_url($attrs, null, array("drafts" => true)) :
                new Post(array("url" => fallback($_GET['url'])), array("drafts" => true)) ;

            if ($post->no_results)
                return false;

            # Don't fool ourselves into thinking a feed was requested because of a "feed" attribute.
            if (!isset($_GET['feed']) and !(count($arg) > count($attrs) and end($arg) == "feed"))
                $this->feed = false;

            if (!$post->theme_exists())
                error(__("Error"),
                      __("The post cannot be displayed because the template for this feather was not found."), null, 501);

            if ($post->status == "draft")
                Flash::message(__("This post is not published."));

            if ($post->status == "scheduled")
                Flash::message(_f("This post is scheduled to be published %s.", when("%c", $post->created_at, true)));

            $this->display(array("pages".DIR."view", "pages".DIR."index"),
                           array("post" => $post,
                                 "posts" => array($post)),
                           $post->title());
        }

        /**
         * Function: page
         * Handles page viewing via dirty URL or clean URL e.g. /parent/child/child-of-child/.
         */
        public function page($url = null, $hierarchy = array()) {
            $page = (isset($url)) ?
                new Page(array("url" => $url)) :
                new Page(array("url" => fallback($_GET['url']))) ;

            if ($page->no_results)
                return false;

            # Don't fool ourselves into thinking a feed was requested because of a "feed" page URL.
            if (!isset($_GET['feed']) and end($hierarchy) == "feed")
                $this->feed = false;

            $trigger = Trigger::current();
            $visitor = Visitor::current();

            if (!$page->public and !$visitor->group->can("view_page") and $page->user_id != $visitor->id) {
                $trigger->call("can_not_view_page");
                show_403(__("Access Denied"), __("You are not allowed to view this page."));
            }

            $this->display(array("pages".DIR.$page->url, "pages".DIR."page"), array("page" => $page), $page->title);
        }

        /**
         * Function: id
         * Views a post or page by its static ID.
         */
        public function id() {
            if (!empty($_GET['post']) and is_numeric($_GET['post'])) {
                $post = new Post($_GET['post']);

                if ($post->no_results)
                    return false;

                redirect($post->url());
            }

            if (!empty($_GET['page']) and is_numeric($_GET['page'])) {
                $page = new Page($_GET['page']);

                if ($page->no_results)
                    return false;

                redirect($page->url());
            }

            return false;
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
                Flash::notice(__("You cannot register an account because you are already logged in."), "/");

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
                elseif (password_strength($_POST['password1']) < 100)
                    Flash::message(__("Please consider setting a stronger password for your account."));

                if (empty($_POST['email']))
                    Flash::warning(__("Email address cannot be blank."));
                elseif (!is_email($_POST['email']))
                    Flash::warning(__("Invalid email address."));

                if ($config->enable_captcha and !check_captcha())
                    Flash::warning(__("Incorrect captcha code."));

                if (!empty($_POST['website']) and !is_url($_POST['website']))
                    Flash::warning(__("Invalid website URL."));

                if (!empty($_POST['website']))
                    $_POST['website'] = add_scheme($_POST['website']);

                fallback($_POST['full_name'], "");
                fallback($_POST['website'], "");

                if (!Flash::exists("warning")) {
                    $user = User::add($_POST['login'],
                                      User::hashPassword($_POST['password1']),
                                      $_POST['email'],
                                      $_POST['full_name'],
                                      $_POST['website'],
                                      $config->default_group,
                                      ($config->email_activation) ? false : true);

                    Trigger::current()->call("user_registered", $user);

                    if (!$user->approved) {
                        correspond("activate", array("login" => $user->login,
                                                     "to"    => $user->email,
                                                     "link"  => $config->url.
                                                                "/?action=activate&amp;login=".urlencode($user->login).
                                                                "&amp;token=".token(array($user->login, $user->email))));

                        Flash::notice(__("We have emailed you an activation link."), "/");
                    }

                    $_SESSION['user_id'] = $user->id;

                    Flash::notice(__("Your account is now active."), "/");
                }
            }

            $this->display("forms".DIR."user".DIR."register", array(), __("Register"));
        }

        /**
         * Function: activate
         * Activates (approves) a given login.
         */
        public function activate() {
            if (logged_in())
                Flash::notice(__("You cannot activate an account because you are already logged in."), "/");

            $user = new User(array("login" => strip_tags(urldecode(fallback($_GET['login'])))));

            if ($user->no_results or empty($_GET['token']) or token(array($user->login, $user->email)) != $_GET['token'])
                Flash::notice(__("Please contact the blog administrator for help with your account."), "/");

            if ($user->approved)
                Flash::notice(__("Your account has already been activated."), "/");

            $user->update(null, null, null, null, null, null, true);

            $_SESSION['user_id'] = $user->id;

            Flash::notice(__("Your account is now active."), "/");
        }

        /**
         * Function: reset
         * Resets the password for a given login.
         */
        public function reset() {
            if (logged_in())
                Flash::notice(__("You cannot reset your password because you are already logged in."), "/");

            $user = new User(array("login" => strip_tags(urldecode(fallback($_GET['login'])))));

            if ($user->no_results or empty($_GET['token']) or token(array($user->login, $user->email)) != $_GET['token'])
                Flash::notice(__("Please contact the blog administrator for help with your account."), "/");

            $new_password = random(8);

            correspond("password", array("login" => $user->login,
                                         "to" => $user->email,
                                         "password" => $new_password));

            $user->update(null, User::hashPassword($new_password));

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

                if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                    Flash::warning(__("Invalid security key."));

                if (!empty($_POST['new_password1']))
                    if (empty($_POST['new_password2']) or $_POST['new_password1'] != $_POST['new_password2'])
                        Flash::warning(__("Passwords do not match."));
                    elseif (password_strength($_POST['new_password1']) < 100)
                        Flash::message(__("Please consider setting a stronger password for your account."));

                if (empty($_POST['email']))
                    Flash::warning(__("Email address cannot be blank."));
                elseif (!is_email($_POST['email']))
                    Flash::warning(__("Invalid email address."));

                if (!empty($_POST['website']) and !is_url($_POST['website']))
                    Flash::warning(__("Invalid website URL."));

                if (!empty($_POST['website']))
                    $_POST['website'] = add_scheme($_POST['website']);

                fallback($_POST['full_name'], "");
                fallback($_POST['website'], "");

                if (!Flash::exists("warning")) {
                    $password = (!empty($_POST['new_password1'])) ?
                        User::hashPassword($_POST['new_password1']) : $visitor->password ;

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
         * Emails a password reset link to the registered address of a user.
         */
        public function lost_password() {
            if (logged_in())
                Flash::notice(__("You cannot reset your password because you are already logged in."), "/");

            $config = Config::current();

            if (!$config->email_correspondence)
                Flash::notice(__("Please contact the blog administrator for help with your account."), "/");

            if (!empty($_POST)) {
                $user = new User(array("login" => fallback($_POST['login'])));

                if (!$user->no_results)
                    correspond("reset", array("login" => $user->login,
                                              "to"    => $user->email,
                                              "link"  => $config->url.
                                                         "/?action=reset&amp;login=".urlencode($user->login).
                                                         "&amp;token=".token(array($user->login, $user->email))));

                Flash::notice(__("If that username is in our database, we will email you a password reset link."), "/");
            }

            $this->display("forms".DIR."user".DIR."lost_password", array(), __("Lost Password"));
        }

        /**
         * Function: random
         * Grabs a random post and redirects to it.
         */
        public function random() {
            $conds = array("posts.status" => "public");

            if (isset($_GET['feather']))
                $conds["posts.feather"] = preg_replace('|[^a-z]|i', '', $_GET['feather']);

            $random = SQL::current()->select("posts",
                                             "posts.url",
                                             $conds,
                                             array("ORDER BY" => "RAND()"),
                                             array("LIMIT" => 1))->fetchObject();

            $post = new Post(array("url" => $random->url));

            redirect($post->url());
        }

        /**
         * Function: feed
         * Grabs posts and serves a feed.
         */
        private function feed($posts = null) {
            $config = Config::current();
            $trigger = Trigger::current();
            $theme = Theme::current();

            # Fetch posts for fallback or if we are being called as a responder.
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
                        oneof($theme->title, $config->description),
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
         * Displays the page.
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

            if (is_array($template))
                foreach (array_values($template) as $index => $try)
                    if ($theme->file_exists($try) or ($index + 1) == count($template))
                        return $this->display($try, $context, $title);

            $this->displayed = true;

            # Populate the theme title attribute for feeds.
            $theme->title = $title;

            # Serve feeds if a feed request was detected for this action.
            if ($this->feed) {
                if ($trigger->exists($route->action."_feed"))
                    return $trigger->call($route->action."_feed", $context);

                if (isset($context["posts"]))
                    return $this->feed($context["posts"]);
            }

            $this->context                       = array_merge($context, $this->context);
            $this->context["ip"]                 = $_SERVER["REMOTE_ADDR"];
            $this->context["DIR"]                = DIR;
            $this->context["version"]            = CHYRP_VERSION;
            $this->context["codename"]           = CHYRP_CODENAME;
            $this->context["debug"]              = DEBUG;
            $this->context["now"]                = time();
            $this->context["site"]               = $config;
            $this->context["flash"]              = Flash::current();
            $this->context["theme"]              = $theme;
            $this->context["trigger"]            = $trigger;
            $this->context["route"]              = $route;
            $this->context["visitor"]            = Visitor::current();
            $this->context["visitor"]->logged_in = logged_in();
            $this->context["title"]              = $theme->title;
            $this->context["captcha"]            = generate_captcha();
            $this->context["modules"]            = Modules::$instances;
            $this->context["feathers"]           = Feathers::$instances;
            $this->context["POST"]               = $_POST;
            $this->context["GET"]                = $_GET;
            $this->context["sql_queries"]        =& SQL::current()->queries;
            $this->context["sql_debug"]          =& SQL::current()->debug;

            $trigger->filter($this->context, array("main_context", "main_context_".str_replace(DIR, "_", $template)));

            $theme->cookies_notification();

            try {
                return $this->twig->display($template.".twig", $this->context);
            } catch (Twig_Error $e) {
                error(__("Twig Error"), $e->getMessage(), $e->getTrace());
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
