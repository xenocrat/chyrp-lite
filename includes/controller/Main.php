<?php
    /**
     * Class: MainController
     * The logic controlling the blog.
     */
    class MainController implements Controller {
        # Array: $urls
        # An array of clean URL => dirty URL translations.
        public $urls = array(
            '|/id/post/([0-9]+)/|'                         => '/?action=id&amp;post=$1',
            '|/id/page/([0-9]+)/|'                         => '/?action=id&amp;page=$1',
            '|/random/([^/]+)/|'                           => '/?action=random&amp;feather=$1',
            '|/search/([^/]+)/|'                           => '/?action=search&amp;query=$1',
            '|/archive/([0-9]{4})/([0-9]{2})/([0-9]{2})/|' => '/?action=archive&amp;year=$1&amp;month=$2&amp;day=$3',
            '|/archive/([0-9]{4})/([0-9]{2})/|'            => '/?action=archive&amp;year=$1&amp;month=$2',
            '|/archive/([0-9]{4})/|'                       => '/?action=archive&amp;year=$1',
            '|/([^/]+)/feed/|'                             => '/?action=$1&amp;feed'
        );

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
        # Is the current page a feed?
        public $feed = null;

        # Integer: $post_limit
        # Item limit for pagination.
        public $post_limit = 10;

        # Array: $view_site_exempt
        # Methods that are exempt from the "view_site" permission.
        public $view_site_exempt = array("login", "logout", "register", "activate", "lost_password", "reset");

        # Variable: $twig
        # Environment for the Twig template engine.
        private $twig;

        # Array: $resort_to
        # Arguments for a page of last resort.
        private $resort_to;

        /**
         * Function: __construct
         * Loads the Twig parser and sets up the l10n domain.
         */
        private function __construct() {
            $loader = new \Twig\Loader\FilesystemLoader(THEME_DIR);

            $this->twig = new \Twig\Environment($loader,
                                                array("debug" => DEBUG,
                                                      "strict_variables" => DEBUG,
                                                      "charset" => "UTF-8",
                                                      "cache" => (CACHE_TWIG ? CACHES_DIR.DIR."twig" : false),
                                                      "autoescape" => false));

            $this->twig->addExtension(new Leaf());
            $this->twig->registerUndefinedFunctionCallback("twig_callback_missing_function");
            $this->twig->registerUndefinedFilterCallback("twig_callback_missing_filter");

            # Load the theme translator.
            load_translator(Theme::current()->safename, THEME_DIR.DIR."locale");

            # Set the limit for pagination.
            $this->post_limit = Config::current()->posts_per_page;
        }

        /**
         * Function: parse
         * Route constructor calls this to interpret clean URLs and determine the action.
         */
        public function parse($route) {
            $config = Config::current();

            # Serve the index if the first arg is empty and / is not a route.
            if (empty($route->arg[0]) and !isset($config->routes["/"]))
                return $route->action = "index";

            # Serve the index if the first arg is a query and action is unset.
            if (empty($route->action) and strpos($route->arg[0], "?") === 0)
                return $route->action = "index";

            # Discover feed requests.
            if ($route->action == "feed" or preg_match("/\/feed\/?$/", $route->request))
                $this->feed = true;

            # Discover pagination.
            if (preg_match_all("/\/((([^_\/]+)_)?page)\/([0-9]+)/", $route->request, $pages)) {
                foreach ($pages[1] as $index => $variable)
                    $_GET[$variable] = (int) $pages[4][$index];

                # Looks like pagination of the index.
                if ($route->arg[0] == $pages[1][0])
                    return $route->action = "index";
            }

            # Archive.
            if ($route->arg[0] == "archive") {
                # Make sure they're numeric; could be a "/page/" in there.
                if (isset($route->arg[1]) and is_numeric($route->arg[1])) {
                    $_GET['year'] = $route->arg[1];

                    if (isset($route->arg[2]) and is_numeric($route->arg[2])) {
                        $_GET['month'] = $route->arg[2];

                        if (isset($route->arg[3]) and is_numeric($route->arg[3]))
                            $_GET['day'] = $route->arg[3];
                    }
                }

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

            # Static ID of a post or page.
            if ($route->arg[0] == "id") {
                if (isset($route->arg[1]) and isset($route->arg[2]))
                    $_GET[$route->arg[1]] = $route->arg[2];

                return $route->action = "id";
            }

            # Custom route?
            $route->custom();

            # Are we viewing a post?
            Post::from_url($route->request, $route);

            # Are we viewing a page?
            Page::from_url($route->request, $route);
        }

        /**
         * Function: index
         * Grabs the posts for the main index.
         */
        public function main_index() {
            $this->display("pages".DIR."index",
                           array("posts" => new Paginator(Post::find(array("placeholders" => true)), $this->post_limit)));
        }

        /**
         * Function: archive
         * Grabs the posts for the archive page.
         */
        public function main_archive() {
            $sql = SQL::current();
            $statuses = Post::statuses();
            $feathers = Post::feathers();

            $months = array();
            $posts = new Paginator(array());

            fallback($_GET['year']);
            fallback($_GET['month']);
            fallback($_GET['day']);

            # Default to either the year of the latest post or the current year.
            if (!isset($_GET['year'])) {
                $latest = $sql->select("posts",
                                       "created_at",
                                       array($feathers,
                                             $statuses),
                                       array("created_at DESC"))->fetch();

                $_GET['year'] = when("Y", fallback($latest["created_at"], time()));
            }

            $timestamp = mktime(0, 0, 0,
                                (is_numeric($_GET['month']) ? (int) $_GET['month'] : 1),
                                (is_numeric($_GET['day']) ? (int) $_GET['day'] : 1),
                                (is_numeric($_GET['year']) ? (int) $_GET['year'] : 1991));

            if (is_numeric($_GET['day'])) {
                $depth = "day";
                $limit = strtotime("tomorrow", $timestamp);
                $title = _f("Archive of %s", when("%d %B %Y", $timestamp, true));
                $posts = new Paginator(Post::find(array("placeholders" => true,
                                                        "where" => array("created_at LIKE" => when("Y-m-d%", $timestamp)),
                                                        "order" => "created_at DESC, id DESC")),
                                       $this->post_limit);
            } elseif (is_numeric($_GET['month'])) {
                $depth = "month";
                $limit = strtotime("midnight first day of next month", $timestamp);
                $title = _f("Archive of %s", when("%B %Y", $timestamp, true));
                $posts = new Paginator(Post::find(array("placeholders" => true,
                                                        "where" => array("created_at LIKE" => when("Y-m-%", $timestamp)),
                                                        "order" => "created_at DESC, id DESC")),
                                       $this->post_limit);
            } else {
                $depth = "year";
                $limit = strtotime("midnight first day of next year", $timestamp);
                $title = _f("Archive of %s", when("%Y", $timestamp, true));
                $month = $timestamp;

                while ($month < $limit) {
                    $vals = Post::find(array("where" => array("created_at LIKE" => when("Y-m-%", $month)),
                                             "order" => "created_at DESC, id DESC"));

                    if (!empty($vals))
                        $months[$month] = $vals;

                    $month = strtotime("midnight first day of next month", $month);
                }
            }

            # Are there posts older than those displayed?
            $next = $sql->select("posts",
                                 "created_at",
                                 array("created_at <" => datetime($timestamp),
                                       $statuses,
                                       $feathers),
                                 array("created_at DESC"))->fetch();

            # Are there posts newer than those displayed?
            $prev = $sql->select("posts",
                                 "created_at",
                                 array("created_at >=" => datetime($limit),
                                       $statuses,
                                       $feathers),
                                 array("created_at ASC"))->fetch();

            $this->display("pages".DIR."archive",
                           array("posts" => $posts,
                                 "months" => array_reverse($months, true),
                                 "archive" => array("when"  => $timestamp,
                                                    "depth" => $depth,
                                                    "next"  => strtotime(fallback($next["created_at"])),
                                                    "prev"  => strtotime(fallback($prev["created_at"])))),
                           $title);
        }

        /**
         * Function: search
         * Grabs the posts for a search query.
         */
        public function main_search() {
            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect("search/".str_ireplace("%2F", "", urlencode($_POST['query']))."/");

            if (empty($_GET['query']))
                Flash::warning(__("Please enter a search term."), "/");

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
        public function main_drafts() {
            $visitor = Visitor::current();

            if (!$visitor->group->can("view_own_draft", "view_draft"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to view drafts."));

            $posts = new Paginator(Post::find(array("placeholders" => true,
                                                    "where" => array("status" => "draft",
                                                                     "user_id" => $visitor->id))),
                                   $this->post_limit);

            $this->display(array("pages".DIR."drafts", "pages".DIR."index"), array("posts" => $posts), __("Drafts"));
        }

        /**
         * Function: view
         * Handles post viewing via dirty URL or clean URL e.g. /year/month/day/url/.
         */
        public function main_view($attrs = array(), $arg = array()) {
            $post = (!empty($attrs)) ?
                Post::from_url($attrs, null, array("drafts" => true)) :
                new Post(array("url" => fallback($_GET['url'])), array("drafts" => true)) ;

            if ($post->no_results)
                return false;

            # Don't fool ourselves into thinking a feed was requested because of a "feed" attribute.
            if (!isset($_GET['feed']) and !(count($arg) > count($attrs) and end($arg) == "feed"))
                $this->feed = false;

            if ($post->status == "draft")
                Flash::message(__("This post is not published."));

            if ($post->status == "scheduled")
                Flash::message(__("This post is scheduled to be published."));

            $this->display(array("pages".DIR."view", "pages".DIR."index"), array("post" => $post), $post->title());
        }

        /**
         * Function: page
         * Handles page viewing via dirty URL or clean URL e.g. /parent/child/child-of-child/.
         */
        public function main_page($url = null, $hierarchy = array()) {
            $trigger = Trigger::current();
            $visitor = Visitor::current();

            $page = (isset($url)) ?
                new Page(array("url" => $url)) :
                new Page(array("url" => fallback($_GET['url']))) ;

            if ($page->no_results)
                return false;

            # Don't fool ourselves into thinking a feed was requested because of a "feed" page URL.
            if (!isset($_GET['feed']) and end($hierarchy) == "feed")
                $this->feed = false;

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
        public function main_id() {
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
         * Function: random
         * Grabs a random post and redirects to it.
         */
        public function main_random() {
            $conds = array(Post::statuses());

            if (isset($_GET['feather']))
                $conds["feather"] = preg_replace("|[^a-z_\-]|i", "", $_GET['feather']);
            else
                $conds[] = Post::feathers();

            $results = SQL::current()->select("posts",
                                              "id",
                                              $conds)->fetchAll();

            if (!empty($results)) {
                $ids = array();

                foreach ($results as $result)
                    $ids[] = $result["id"];

                shuffle($ids);

                $post = new Post(reset($ids));

                if ($post->no_results)
                    return false;

                redirect($post->url());
            }

            Flash::warning(__("There aren't enough posts for random selection."), "/");
        }

        /**
         * Function: register
         * Register a visitor as a new user.
         */
        public function main_register() {
            $config = Config::current();

            if (!$config->can_register)
                Flash::notice(__("This site does not allow registration."), "/");

            if (logged_in())
                Flash::notice(__("You cannot register an account because you are already logged in."), "/");

            if (!empty($_POST)) {
                if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                    Flash::warning(__("Invalid authentication token."));

                if (empty($_POST['login']) or derezz($_POST['login']))
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

                if (!check_captcha())
                    Flash::warning(__("Incorrect captcha response."));

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

                    if (!$user->approved) {
                        correspond("activate", array("to"      => $user->email,
                                                     "user_id" => $user->id,
                                                     "login"   => $user->login,
                                                     "link"    => fix($config->url, true).
                                                                  "/?action=activate&amp;login=".
                                                                  urlencode($user->login).
                                                                  "&amp;token=".
                                                                  token(array($user->login, $user->email))));

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
        public function main_activate() {
            if (logged_in())
                Flash::notice(__("You cannot activate an account because you are already logged in."), "/");

            $user = new User(array("login" => urldecode(fallback($_GET['login']))));

            if ($user->no_results or empty($_GET['token']) or
                $_GET['token'] != token(array($user->login, $user->email)))
                Flash::notice(__("Please contact the blog administrator for help with your account."), "/");

            if ($user->approved)
                Flash::notice(__("Your account has already been activated."), "/");

            $user = $user->update(null, null, null, null, null, null, true);

            $_SESSION['user_id'] = $user->id;

            Flash::notice(__("Your account is now active."), "/");
        }

        /**
         * Function: reset
         * Resets the password for a given login.
         */
        public function main_reset() {
            if (logged_in())
                Flash::notice(__("You cannot reset your password because you are already logged in."), "/");

            $user = new User(array("login" => urldecode(fallback($_GET['login']))));

            if ($user->no_results or empty($_GET['token']) or
                $_GET['token'] != token(array($user->login, $user->email)))
                Flash::notice(__("Please contact the blog administrator for help with your account."), "/");

            $new_password = random(8);

            correspond("password", array("to"       => $user->email,
                                         "user_id"  => $user->id,
                                         "login"    => $user->login,
                                         "password" => $new_password));

            $user = $user->update(null, User::hashPassword($new_password));

            Flash::notice(__("We have emailed you a new password."), "login");
        }

        /**
         * Function: login
         * Logs in a user if they provide the username and password.
         */
        public function main_login() {
            $trigger = Trigger::current();

            if (logged_in())
                Flash::notice(__("You are already logged in."), "/");

            if (!empty($_POST)) {
                if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                    Flash::warning(__("Invalid authentication token."));

                fallback($_POST['login']);
                fallback($_POST['password']);

                # You can block the login process by creating a Flash::warning().
                $trigger->call("user_authenticate");

                if (!User::authenticate($_POST['login'], $_POST['password']))
                    Flash::warning(__("Incorrect username and/or password."));

                if (!Flash::exists("warning")) {
                    $user = new User(array("login" => $_POST['login']));

                    if (!$user->approved)
                        Flash::notice(__("You must activate your account before you log in."), "/");

                    $_SESSION['user_id'] = $user->id;
                    $trigger->call("user_logged_in", $user);

                    Flash::notice(__("Logged in."), fallback($_SESSION['redirect_to'], "/"));
                }
            }

            $this->display("forms".DIR."user".DIR."login", array(), __("Log in"));
        }

        /**
         * Function: logout
         * Logs out the current user.
         */
        public function main_logout() {
            $trigger = Trigger::current();

            if (!logged_in())
                Flash::notice(__("You aren't logged in."), "/");

            $user = new User($_SESSION['user_id']);
            session_destroy();
            session();
            $trigger->call("user_logged_out", $user);

            Flash::notice(__("Logged out."), "/");
        }

        /**
         * Function: controls
         * Updates the current user when the form is submitted.
         */
        public function main_controls() {
            $visitor = Visitor::current();

            if (!logged_in())
                Flash::notice(__("You must be logged in to access user controls."), "login");

            if (!empty($_POST)) {
                if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                    Flash::warning(__("Invalid authentication token."));

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

                    $visitor = $visitor->update($visitor->login,
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
        public function main_lost_password() {
            $config = Config::current();

            if (logged_in())
                Flash::notice(__("You cannot reset your password because you are already logged in."), "/");

            if (!$config->email_correspondence)
                Flash::notice(__("Please contact the blog administrator for help with your account."), "/");

            if (!empty($_POST)) {
                if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                    Flash::warning(__("Invalid authentication token."));

                if (empty($_POST['login']))
                    Flash::warning(__("Please enter your username."));

                if (!Flash::exists("warning")) {
                    $user = new User(array("login" => $_POST['login']));

                    if (!$user->no_results)
                        correspond("reset", array("to"      => $user->email,
                                                  "user_id" => $user->id,
                                                  "login"   => $user->login,
                                                  "link"    => fix($config->url, true).
                                                               "/?action=reset&amp;login=".
                                                               urlencode($user->login).
                                                               "&amp;token=".
                                                               token(array($user->login, $user->email))));

                    Flash::notice(__("If that username is in our database, we will email you a password reset link."), "/");
                }
            }

            $this->display("forms".DIR."user".DIR."lost_password", array(), __("Lost password"));
        }

        /**
         * Function: feed
         * Grabs posts and serves a feed.
         */
        public function main_feed($posts = null) {
            $config = Config::current();
            $trigger = Trigger::current();
            $theme = Theme::current();

            # Fetch posts if we are being called as a responder.
            if (!isset($posts)) {
                $results = SQL::current()->select("posts",
                                                  "id",
                                                  array("status" => "public"),
                                                  array("id DESC"),
                                                  array(),
                                                  $config->feed_items)->fetchAll();

                $ids = array();

                foreach ($results as $result)
                    $ids[] = $result["id"];

                if (!empty($ids))
                    $posts = Post::find(array("where" => array("id" => $ids),
                                              "order" => "created_at DESC, id DESC"));
                else
                    $posts = array();
            }

            if ($posts instanceof Paginator)
                $posts = $posts->paginated;

            $latest_timestamp = 0;

            foreach ($posts as $post)
                if ($latest_timestamp < strtotime($post->created_at))
                    $latest_timestamp = strtotime($post->created_at);

            $feed = new BlogFeed();

            $feed->open(oneof($theme->title, $config->name),
                        $config->description,
                        null,
                        $latest_timestamp);

            foreach ($posts as $post) {
                $updated = ($post->updated) ? $post->updated_at : $post->created_at ;

                $feed->entry(oneof($post->title(), ucfirst($post->feather)),
                             url("id/post/".$post->id),
                             $post->feed_content(),
                             $post->url(),
                             $post->created_at,
                             $updated,
                             ((!$post->user->no_results) ? oneof($post->user->full_name, $post->user->login) : null),
                             ((!$post->user->no_results) ? $post->user->website : null));

                $trigger->call("feed_item", $post, $feed);
            }

            $feed->close();
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
                    return $this->main_feed($context["posts"]);
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
            $this->context["theme"]              = $theme;
            $this->context["trigger"]            = $trigger;
            $this->context["route"]              = $route;
            $this->context["visitor"]            = Visitor::current();
            $this->context["visitor"]->logged_in = logged_in();
            $this->context["title"]              = $theme->title;
            $this->context["modules"]            = Modules::$instances;
            $this->context["feathers"]           = Feathers::$instances;
            $this->context["POST"]               = $_POST;
            $this->context["GET"]                = $_GET;
            $this->context["sql_queries"]        =& SQL::current()->queries;
            $this->context["sql_debug"]          =& SQL::current()->debug;

            $trigger->filter($this->context, "twig_context_main");
            $this->twig->display($template.".twig", $this->context);
        }

        /**
         * Function: resort
         * Queue a page in the event that none of the actions are successful.
         */
        public function resort($template, $context = array(), $title = "") {
            $this->resort_to = array($template, $context, $title);
            return false;
        }

        /**
         * Function: failed
         * Serve a page in the event that none of the actions are successful.
         */
        public function failed($route) {
            if (!empty($this->resort_to))
                call_user_func_array(array($this, "display"), $this->resort_to);
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
