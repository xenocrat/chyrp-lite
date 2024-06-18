<?php
    /**
     * Class: MainController
     * The logic controlling the blog.
     */
    class MainController extends Controllers implements Controller {
        # Array: $urls
        # An array of clean URL => dirty URL translations.
        public $urls = array(
            '|/id/post/([0-9]+)/|'
                => '/?action=id&amp;post=$1',

            '|/id/page/([0-9]+)/|'
                => '/?action=id&amp;page=$1',

            '|/author/([0-9]+)/|'
                => '/?action=author&amp;id=$1',

            '|/random/([^/]+)/|'
                => '/?action=random&amp;feather=$1',

            '|/matter/([^/]+)/|'
                => '/?action=matter&amp;url=$1',

            '|/search/([^/]+)/|'
                => '/?action=search&amp;query=$1',

            '|/archive/([0-9]{4})/([0-9]{2})/([0-9]{2})/|'
                => '/?action=archive&amp;year=$1&amp;month=$2&amp;day=$3',

            '|/archive/([0-9]{4})/([0-9]{2})/|'
                => '/?action=archive&amp;year=$1&amp;month=$2',

            '|/archive/([0-9]{4})/|'
                => '/?action=archive&amp;year=$1',

            '|/([^/]+)/feed/|'
                => '/?action=$1&amp;feed'
        );

        # Variable: $twig
        # Environment for the Twig template engine.
        private $twig;

        /**
         * Function: __construct
         * Loads the Twig parser and sets up the l10n domain.
         */
        private function __construct() {
            $loader = new \Twig\Loader\FilesystemLoader(THEME_DIR);
            $config = Config::current();
            $theme = Theme::current();

            $this->twig = new \Twig\Environment(
                $loader,
                array(
                    "debug" => DEBUG,
                    "strict_variables" => DEBUG,
                    "charset" => "UTF-8",
                    "cache" => CACHES_DIR.DIR."twig",
                    "autoescape" => false)
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
            load_translator($theme->safename, THEME_DIR.DIR."locale");

            # Set the limit for pagination.
            $this->post_limit = $config->posts_per_page;
        }

        /**
         * Function: parse
         * Route constructor calls this to interpret clean URLs and determine the action.
         */
        public function parse($route): ?string {
            $config = Config::current();

            # Serve the index if the first arg is empty and / is not a route.
            if (empty($route->arg[0]) and !isset($config->routes["/"]))
                return $route->action = "index";

            # Serve the index if the first arg is a query and action is unset.
            if (empty($route->action) and str_starts_with($route->arg[0], "?"))
                return $route->action = "index";

            # Discover feed requests.
            if (
                $route->action == "feed" or
                preg_match("/\/feed\/?$/", $route->request)
            )
                $this->feed = true;

            # Discover pagination.
            if (
                preg_match_all(
                    "/\/((([^_\/]+)_)?page)\/([0-9]+)/",
                    $route->request,
                    $pages
                )
            ) {
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

            # Author.
            if ($route->arg[0] == "author") {
                if (isset($route->arg[1]))
                    $_GET['id'] = $route->arg[1];

                return $route->action = "author";
            }

            # Random.
            if ($route->arg[0] == "random") {
                if (isset($route->arg[1]))
                    $_GET['feather'] = $route->arg[1];

                return $route->action = "random";
            }

            # Matter.
            if ($route->arg[0] == "matter") {
                if (isset($route->arg[1]))
                    $_GET['url'] = $route->arg[1];

                return $route->action = "matter";
            }

            # Static ID of a post or page.
            if ($route->arg[0] == "id") {
                if (isset($route->arg[1]) and isset($route->arg[2])) {
                    switch ($route->arg[1]) {
                        case "post":
                            $_GET["post"] = $route->arg[2];
                            break;
                        case "page":
                            $_GET["page"] = $route->arg[2];
                            break;
                    }
                }

                return $route->action = "id";
            }

            # Custom route?
            $route->custom();

            # Are we viewing a post?
            Post::from_url(
                $route->request,
                $route,
                array("drafts" => true)
            );

            # Are we viewing a page?
            Page::from_url(
                $route->request,
                $route
            );

            return null;
        }

        /**
         * Function: exempt
         * Route constructor calls this to determine "view_site" exemptions.
         */
        public function exempt($action): bool {
            $exemptions = array(
                "login",
                "logout",
                "register",
                "activate",
                "lost_password",
                "reset_password"
            );
            return in_array($action, $exemptions);
        }

        /**
         * Function: main_index
         * Grabs the posts for the main index.
         */
        public function main_index(): void {
            $this->display(
                "pages".DIR."index",
                array(
                    "posts" => new Paginator(
                        Post::find(array("placeholders" => true)),
                        $this->post_limit
                    )
                )
            );
        }

        /**
         * Function: main_updated
         * Grabs the posts that have been updated.
         */
        public function main_updated(): void {
            $this->display(
                array("pages".DIR."updated", "pages".DIR."index"),
                array(
                    "posts" => new Paginator(
                        Post::find(
                            array(
                                "placeholders" => true,
                                "where" => array("updated_at >" => SQL_DATETIME_ZERO),
                                "order" => "updated_at DESC, created_at DESC, id DESC"
                            )
                        ),
                        $this->post_limit
                    )
                ),
                __("Updated posts")
            );
        }

        /**
         * Function: main_author
         * Grabs the posts created by a user.
         */
        public function main_author(): void {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                Flash::warning(
                    __("You did not specify a user ID."),
                    "/"
                );

            $user = new User($_GET['id']);

            if ($user->no_results)
                Flash::warning(
                    __("User not found."),
                    "/"
                );

            $author = (object) array(
                "id"      => $user->id,
                "name"    => oneof($user->full_name, $user->login),
                "website" => $user->website,
                "email"   => $user->email,
                "joined"  => $user->joined_at
            );

            $this->display(
                array("pages".DIR."author", "pages".DIR."index"),
                array(
                    "author" => $author,
                    "posts" => new Paginator(
                        Post::find(
                            array(
                                "placeholders" => true,
                                "where" => array("user_id" => $user->id)
                            )
                        ),
                        $this->post_limit
                    )
                ),
                _f("Posts created by &#8220;%s&#8221;", fix($author->name))
            );
        }

        /**
         * Function: main_archive
         * Grabs the posts for the archive page.
         */
        public function main_archive(): void {
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
                $latest = $sql->select(
                    tables:"posts",
                    fields:"created_at",
                    conds:array($feathers, $statuses),
                    order:array("created_at DESC")
                )->fetch();

                $latest = ($latest === false) ?
                    time() :
                    $latest["created_at"] ;
            }

            $start = mktime(
                0, 0, 0,
                (
                    is_numeric($_GET['month']) ?
                        (int) $_GET['month'] :
                        1
                ),
                (
                    is_numeric($_GET['day']) ?
                        (int) $_GET['day'] :
                        1
                ),
                (
                    is_numeric($_GET['year']) ?
                        (int) $_GET['year'] :
                        (int) when("Y", $latest)
                )
            );

            if (is_numeric($_GET['day'])) {
                $depth = "day";
                $limit = strtotime(
                    "tomorrow",
                    $start
                );
                $title = _f("Archive of %s", _w("d F Y", $start));
                $posts = new Paginator(
                    Post::find(
                        array(
                            "placeholders" => true,
                            "where" => array(
                                "created_at LIKE" => when("Y-m-d%", $start)
                            ),
                            "order" => "created_at DESC, id DESC"
                        )
                    ),
                    $this->post_limit
                );
            } elseif (is_numeric($_GET['month'])) {
                $depth = "month";
                $limit = strtotime(
                    "midnight first day of next month",
                    $start
                );
                $title = _f("Archive of %s", _w("F Y", $start));
                $posts = new Paginator(
                    Post::find(
                        array(
                            "placeholders" => true,
                            "where" => array(
                                "created_at LIKE" => when("Y-m-%", $start)
                            ),
                            "order" => "created_at DESC, id DESC"
                        )
                    ),
                    $this->post_limit
                );
            } else {
                $depth = "year";
                $limit = strtotime(
                    "midnight first day of next year",
                    $start
                );
                $title = _f("Archive of %s", _w("Y", $start));

                $results = Post::find(
                    array(
                        "where" => array(
                            "created_at LIKE" => when("Y-%-%", $start)
                        ),
                        "order" => "created_at DESC, id DESC"
                    )
                );

                foreach ($results as $result) {
                    $created_at = strtotime($result->created_at);
                    $this_month = strtotime(
                        "midnight first day of this month",
                        $created_at
                    );

                    if (!isset($months[$this_month]))
                        $months[$this_month] = array();

                    $months[$this_month][] = $result;
                }
            }

            # Are there posts older than those displayed?
            $next = $sql->select(
                tables:"posts",
                fields:"created_at",
                conds:array(
                    "created_at <" => datetime($start),
                    $statuses,
                    $feathers
                ),
                order:array("created_at DESC")
            )->fetchColumn();

            # Are there posts newer than those displayed?
            $prev = $sql->select(
                tables:"posts",
                fields:"created_at",
                conds:array(
                    "created_at >=" => datetime($limit),
                    $statuses,
                    $feathers
                ),
                order:array("created_at ASC")
            )->fetchColumn();

            $prev = ($prev === false) ?
                null :
                strtotime($prev) ;
            $next = ($next === false) ?
                null :
                strtotime($next) ;

            $this->display(
                "pages".DIR."archive",
                array(
                    "posts" => $posts,
                    "months" => $months,
                    "archive" => array(
                        "when"  => $start,
                        "depth" => $depth,
                        "next"  => $next,
                        "prev"  => $prev
                    )
                ),
                $title
            );
        }

        /**
         * Function: main_search
         * Grabs the posts and pages for a search query.
         */
        public function main_search(): void {
            $config = Config::current();
            $visitor = Visitor::current();

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect(
                    "search/".
                    str_ireplace(array("%2F", "%5C"), "%5F", urlencode($_POST['query'])).
                    "/"
                );

            if (!isset($_GET['query']) or $_GET['query'] == "")
                Flash::warning(
                    __("Please enter a search term."),
                    "/"
                );

            list($where, $params) = keywords(
                $_GET['query'],
                "post_attributes.value LIKE :query OR url LIKE :query",
                "posts"
            );

            $results = Post::find(
                array(
                    "placeholders" => true,
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
                            "where" => array("id" => $ids)
                        )
                    ),
                    $this->post_limit
                );
            } else {
                $posts = new Paginator(array());
            }

            if ($config->search_pages) {
                list($where, $params) = keywords(
                    $_GET['query'],
                    "title LIKE :query OR body LIKE :query",
                    "pages"
                );

                if (!$visitor->group->can("view_page"))
                    $where["public"] = true;

                $pages = Page::find(
                    array(
                        "where" => $where,
                        "params" => $params
                    )
                );
            } else {
                $pages = array();
            }

            $this->display(
                array("pages".DIR."search", "pages".DIR."index"),
                array(
                    "posts" => $posts,
                    "pages" => $pages,
                    "search" => $_GET['query']
                ),
                _f("Search results for &#8220;%s&#8221;", fix($_GET['query']))
            );
        }

        /**
         * Function: main_drafts
         * Grabs the posts with draft status created by this user.
         */
        public function main_drafts(): void {
            $visitor = Visitor::current();

            if (!$visitor->group->can("view_own_draft", "view_draft"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to view drafts.")
                );

            $posts = new Paginator(
                Post::find(
                    array(
                        "placeholders" => true,
                        "where" => array(
                            "status" => Post::STATUS_DRAFT,
                            "user_id" => $visitor->id)
                    )
                ),
                $this->post_limit
            );

            $this->display(
                array("pages".DIR."drafts", "pages".DIR."index"),
                array("posts" => $posts),
                __("Drafts")
            );
        }

        /**
         * Function: main_view
         * Handles post viewing via dirty URL or clean URL.
         * E.g. /year/month/day/url/.
         */
        public function main_view($post = null): bool {
            if (!isset($post))
                $post = new Post(
                    array("url" => fallback($_GET['url'])),
                    array("drafts" => true)
                );

            if ($post->no_results)
                return false;

            if ($post->status == Post::STATUS_DRAFT)
                Flash::message(
                    __("This post is not published.")
                );

            if ($post->status == Post::STATUS_SCHEDULED)
                Flash::message(
                    __("This post is scheduled to be published.")
                );

            $this->display(
                array("pages".DIR."view", "pages".DIR."index"),
                array("post" => $post),
                $post->title()
            );

            return true;
        }

        /**
         * Function: main_page
         * Handles page viewing via dirty URL or clean URL.
         * E.g. /parent/child/child-of-child/.
         */
        public function main_page($page = null): bool {
            $trigger = Trigger::current();
            $visitor = Visitor::current();

            if (!isset($page))
                $page = new Page(
                    array("url" => fallback($_GET['url']))
                );

            if ($page->no_results)
                return false;

            if (
                !$page->public and
                !$visitor->group->can("view_page") and
                $page->user_id != $visitor->id
            ) {
                $trigger->call("can_not_view_page");
                show_403(
                    __("Access Denied"),
                    __("You are not allowed to view this page.")
                );
            }

            $this->display(
                array("pages".DIR."page_".$page->url, "pages".DIR."page"),
                array("page" => $page),
                $page->title
            );

            return true;
        }

        /**
         * Function: main_id
         * Views a post or page by its static ID.
         */
        public function main_id(): bool {
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
         * Function: main_random
         * Grabs a random post and redirects to it.
         */
        public function main_random(): bool {
            $conds = array(Post::statuses());

            if (isset($_GET['feather']))
                $conds["feather"] = preg_replace(
                    "|[^a-z_\-]|i",
                    "",
                    $_GET['feather']
                );
            else
                $conds[] = Post::feathers();

            $results = SQL::current()->select(
                tables:"posts",
                fields:"id",
                conds:$conds
            )->fetchAll();

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

            Flash::warning(
                __("There aren't enough posts for random selection."),
                "/"
            );
        }

        /**
         * Function: main_matter
         * Displays a standalone Twig template from the "pages" directory.
         */
        public function main_matter(): bool {
            $theme = Theme::current();

            if (!isset($_GET['url']))
                return false;

            $matter = str_replace(array(DIR, "/"), "", $_GET['url']);

            if ($matter == "")
                return false;

            $template = "pages".DIR."matter_".$matter;

            if (!$theme->file_exists($template))
                return false;

            $this->display(
                $template,
                array(),
                camelize($matter, true)
            );

            return true;
        }

        /**
         * Function: main_register
         * Register a visitor as a new user.
         */
        public function main_register(): void {
            $config = Config::current();

            if (!$config->can_register)
                Flash::notice(
                    __("This site does not allow registration."),
                    "/"
                );

            if (logged_in())
                Flash::notice(
                    __("You cannot register an account because you are already logged in."),
                    "/"
                );

            if (!empty($_POST)) {
                if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                    Flash::warning(
                        __("Invalid authentication token.")
                    );

                if (empty($_POST['login']) or derezz($_POST['login']))
                    Flash::warning(
                        __("Please enter a username for your account.")
                    );

                $check = new User(
                    array("login" => $_POST['login'])
                );

                if (!$check->no_results)
                    Flash::warning(
                        __("That username is already in use.")
                    );

                if (empty($_POST['password1']) or empty($_POST['password2']))
                    Flash::warning(
                        __("Passwords cannot be blank.")
                    );
                elseif ($_POST['password1'] != $_POST['password2'])
                    Flash::warning(
                        __("Passwords do not match.")
                    );
                elseif (password_strength($_POST['password1']) < 100)
                    Flash::message(
                        __("Please consider setting a stronger password for your account.")
                    );

                if (empty($_POST['email']))
                    Flash::warning(
                        __("Email address cannot be blank.")
                    );
                elseif (!is_email($_POST['email']))
                    Flash::warning(
                        __("Invalid email address.")
                    );

                if (!check_captcha())
                    Flash::warning(
                        __("Incorrect captcha response.")
                    );

                if (!empty($_POST['website']) and !is_url($_POST['website']))
                    Flash::warning(
                        __("Invalid website URL.")
                    );

                if (!empty($_POST['website']))
                    $_POST['website'] = add_scheme($_POST['website']);

                fallback($_POST['full_name'], "");
                fallback($_POST['website'], "");

                if (!Flash::exists("warning")) {
                    $user = User::add(
                        login:$_POST['login'],
                        password:User::hash_password($_POST['password1']),
                        email:$_POST['email'],
                        full_name:$_POST['full_name'],
                        website:$_POST['website'],
                        group_id:$config->default_group,
                        approved:($config->email_activation) ? false : true
                    );

                    if (!$user->approved) {
                        email_activate_account($user);
                        Flash::notice(
                            __("We have emailed you an activation link."),
                            "/"
                        );
                    }

                    Visitor::log_in($user);

                    Flash::notice(
                        __("Your account is now active."),
                        "/"
                    );
                }
            }

            $this->display(
                "forms".DIR."user".DIR."register",
                array(),
                __("Register")
            );
        }

        /**
         * Function: main_activate
         * Activates (approves) a given login.
         */
        public function main_activate()/*: never */{
            if (logged_in())
                Flash::notice(
                    __("You cannot activate an account because you are already logged in."),
                    "/"
                );

            fallback($_GET['login']);
            fallback($_GET['token']);

            $user = new User(
                array("login" => $_GET['login'])
            );

            if ($user->no_results)
                Flash::notice(
                    __("Please contact the blog administrator for help with your account."),
                    "/"
                );

            $hash = token($user->login);

            if (!hash_equals($hash, $_GET['token']))
                Flash::warning(
                    __("Invalid authentication token."),
                    "/"
                );

            if ($user->approved)
                Flash::notice(
                    __("Your account has already been activated."),
                    "/"
                );

            $user = $user->update(approved:true);

            Flash::notice(
                __("Your account is now active."),
                "login"
            );
        }

        /**
         * Function: main_login
         * Logs in a user if they provide the username and password.
         */
        public function main_login(): void {
            $config = Config::current();
            $trigger = Trigger::current();

            if (logged_in())
                Flash::notice(
                    __("You are already logged in."),
                    "/"
                );

            if (!empty($_POST)) {
                if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                    Flash::warning(
                        __("Invalid authentication token.")
                    );

                fallback($_POST['login']);
                fallback($_POST['password']);

                # You can block the login process by creating a Flash::warning().
                $trigger->call("user_authenticate");

                if (!User::authenticate($_POST['login'], $_POST['password']))
                    Flash::warning(
                        __("Incorrect username and/or password.")
                    );

                if (!Flash::exists("warning")) {
                    $user = new User(
                        array("login" => $_POST['login'])
                    );

                    if (!$user->approved and $config->email_activation)
                        Flash::notice(
                            __("You must activate your account before you log in."),
                            "/"
                        );

                    Visitor::log_in($user);

                    Flash::notice(
                        __("Logged in."),
                        fallback($_SESSION['redirect_to'], "/")
                    );
                }
            }

            $this->display(
                "forms".DIR."user".DIR."login",
                array(),
                __("Log in")
            );
        }

        /**
         * Function: main_logout
         * Logs out the current user.
         */
        public function main_logout()/*: never */{
            if (!logged_in())
                Flash::notice(
                    __("You aren't logged in."),
                    "/"
                );

            Visitor::log_out();

            Flash::notice(
                __("Logged out."),
                "/"
            );
        }

        /**
         * Function: main_controls
         * Updates the current user when the form is submitted.
         */
        public function main_controls(): void {
            $visitor = Visitor::current();

            if (!logged_in())
                Flash::notice(
                    __("You must be logged in to access user controls."),
                    "login"
                );

            if (!empty($_POST)) {
                if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                    Flash::warning(
                        __("Invalid authentication token.")
                    );

                if (!empty($_POST['new_password1'])) {
                    if (
                        empty($_POST['new_password2']) or
                        $_POST['new_password1'] != $_POST['new_password2']
                    ) {
                        Flash::warning(
                            __("Passwords do not match.")
                        );
                    } elseif (
                        password_strength($_POST['new_password1']) < 100
                    ) {
                        Flash::message(
                            __("Please consider setting a stronger password for your account.")
                        );
                    }
                }

                if (empty($_POST['email']))
                    Flash::warning(
                        __("Email address cannot be blank.")
                    );
                elseif (!is_email($_POST['email']))
                    Flash::warning(
                        __("Invalid email address.")
                    );

                if (!empty($_POST['website']) and !is_url($_POST['website']))
                    Flash::warning(
                        __("Invalid website URL.")
                    );

                if (!empty($_POST['website']))
                    $_POST['website'] = add_scheme($_POST['website']);

                fallback($_POST['full_name'], "");
                fallback($_POST['website'], "");

                if (!Flash::exists("warning")) {
                    $password = (!empty($_POST['new_password1'])) ?
                        User::hash_password($_POST['new_password1']) :
                        $visitor->password ;

                    $visitor = $visitor->update(
                        login:$visitor->login,
                        password:$password,
                        email:$_POST['email'],
                        full_name:$_POST['full_name'],
                        website:$_POST['website'],
                        group_id:$visitor->group->id
                    );

                    Flash::notice(
                        __("Your profile has been updated."),
                        "/"
                    );
                }
            }

            $this->display(
                "forms".DIR."user".DIR."controls",
                array(),
                __("Controls")
            );
        }

        /**
         * Function: main_lost_password
         * Emails a password reset link to the registered address of a user.
         */
        public function main_lost_password(): void {
            $config = Config::current();

            if (logged_in())
                Flash::notice(
                    __("You cannot reset your password because you are already logged in."),
                    "/"
                );

            if (!$config->email_correspondence)
                Flash::notice(
                    __("Please contact the blog administrator for help with your account."),
                    "/"
                );

            if (!empty($_POST)) {
                if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                    Flash::warning(
                        __("Invalid authentication token.")
                    );

                if (empty($_POST['login']))
                    Flash::warning(
                        __("Please enter your username.")
                    );

                if (!Flash::exists("warning")) {
                    $user = new User(
                        array("login" => $_POST['login'])
                    );

                    if (!$user->no_results)
                        email_reset_password($user);

                    Flash::notice(
                        __("We have emailed you a password reset link."),
                        "/"
                    );
                }
            }

            $this->display(
                "forms".DIR."user".DIR."lost_password",
                array(),
                __("Lost password")
            );
        }

        /**
         * Function: main_reset_password
         * Resets the password for a given login.
         */
        public function main_reset_password(): void {
            $config = Config::current();

            if (logged_in())
                Flash::notice(
                    __("You cannot reset your password because you are already logged in."),
                    "/"
                );

            fallback($_REQUEST['issue']);
            fallback($_REQUEST['login']);
            fallback($_REQUEST['token']);

            $age = time() - intval($_REQUEST['issue']);

            if ($age > PASSWORD_RESET_TOKEN_LIFETIME)
                Flash::notice(
                    __("The link has expired. Please try again."),
                    "lost_password"
                );

            $user = new User(
                array("login" => $_REQUEST['login'])
            );

            if ($user->no_results)
                Flash::notice(
                    __("Please contact the blog administrator for help with your account."),
                    "/"
                );

            $hash = token(array($_REQUEST['issue'], $user->login));

            if (!hash_equals($hash, $_REQUEST['token']))
                Flash::warning(
                    __("Invalid authentication token."),
                    "/"
                );

            if (!empty($_POST)) {
                if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                    Flash::warning(
                        __("Invalid authentication token.")
                    );

                if (empty($_POST['new_password1']) or empty($_POST['new_password2']))
                    Flash::warning(
                        __("Passwords cannot be blank.")
                    );
                elseif ($_POST['new_password1'] != $_POST['new_password2'])
                    Flash::warning(
                        __("Passwords do not match.")
                    );
                elseif (password_strength($_POST['new_password1']) < 100)
                    Flash::message(
                        __("Please consider setting a stronger password for your account.")
                    );

                if (!Flash::exists("warning")) {
                    $user->update(
                        password:User::hash_password($_POST['new_password1'])
                    );

                    Flash::notice(
                        __("Your profile has been updated."),
                        "login"
                    );
                }
            }

            $this->display(
                "forms".DIR."user".DIR."reset_password",
                array(
                    "issue" => $_REQUEST['issue'],
                    "login" => $_REQUEST['login'],
                    "token" => $_REQUEST['token']
                ),
                __("Reset password")
            );
        }

        /**
         * Function: main_webmention
         * Webmention receiver endpoint.
         */
        public function main_webmention(): void {
            if (!Config::current()->send_pingbacks)
                error(
                    __("Error"),
                    __("Webmention support is disabled for this site."),
                    code:503
                );

            webmention_receive(
                fallback($_POST['source']),
                fallback($_POST['target'])
            );
        }

        /**
         * Function: main_feed
         * Grabs posts and serves a feed.
         */
        public function main_feed($posts = null): void {
            $config = Config::current();
            $trigger = Trigger::current();
            $theme = Theme::current();

            # Fetch posts if we are being called as a responder.
            if (!isset($posts)) {
                $results = SQL::current()->select(
                    tables:"posts",
                    fields:"id",
                    conds:array("status" => Post::STATUS_PUBLIC),
                    order:array("id DESC"),
                    limit:$config->feed_items
                )->fetchAll();

                $ids = array();

                foreach ($results as $result)
                    $ids[] = $result["id"];

                if (!empty($ids)) {
                    $posts = Post::find(
                        array(
                            "where" => array("id" => $ids),
                            "order" => "created_at DESC, id DESC"
                        )
                    );
                } else {
                    $posts = array();
                }
            }

            if ($posts instanceof Paginator) {
                $posts = $posts->reslice($config->feed_items);
                $posts = $posts->paginated;
            }

            $latest_timestamp = 0;

            foreach ($posts as $post) {
                $created_at = strtotime($post->created_at);

                if ($latest_timestamp < $created_at)
                    $latest_timestamp = $created_at;
            }

            if (strlen($theme->title)) {
                $title = $theme->title;
                $subtitle = "";
            } else {
                $title = $config->name;
                $subtitle = $config->description;
            }

            $feed = new BlogFeed();

            $feed->open(
                title:$title,
                subtitle:$subtitle,
                updated:$latest_timestamp
            );

            foreach ($posts as $post) {
                $updated = ($post->updated) ?
                    $post->updated_at :
                    $post->created_at ;

                if (!$post->user->no_results) {
                    $author = oneof(
                        $post->user->full_name,
                        $post->user->login
                    );
                    $website = $post->user->website;
                } else {
                    $author = null;
                    $website = null;
                }

                $feed->entry(
                    title:oneof($post->title(), $post->slug),
                    id:url("id/post/".$post->id),
                    content:$post->feed_content(),
                    link:$post->url(),
                    published:$post->created_at,
                    updated:$updated,
                    name:$author,
                    uri:$website
                );

                $trigger->call("feed_item", $post, $feed);
            }

            $feed->display();
        }

        /**
         * Function: display
         * Displays the page, or serves a feed if requested.
         *
         * Parameters:
         *     $template - The template file to display.
         *     $context - The context to be supplied to Twig.
         *     $title - The title for the page (optional).
         *     $pagination - <Paginator> instance (optional).
         *
         * Notes:
         *     $template is supplied sans ".twig" and relative to THEME_DIR.
         *     $template can be an array of fallback template filenames to try.
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
            $theme = Theme::current();

            if ($this->displayed == true)
                return;

            # Try fallback template filenames.
            if (is_array($template)) {
                foreach (array_values($template) as $index => $try) {
                    if (
                        $theme->file_exists($try) or
                        ($index + 1) == count($template)
                    ) {
                        $this->display($try, $context, $title);
                        return;
                    }
                }
            }

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

            # Populate the theme title attribute.
            $theme->title = $title;

            # Serve a feed request if detected for this action.
            if ($this->feed) {
                if ($trigger->exists($route->action."_feed")) {
                    $trigger->call($route->action."_feed", $context);
                    return;
                }

                if (isset($context["posts"])) {
                    $this->main_feed($context["posts"]);
                    return;
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
            $this->context["theme"]              = $theme;
            $this->context["trigger"]            = $trigger;
            $this->context["route"]              = $route;
            $this->context["visitor"]            = Visitor::current();
            $this->context["visitor"]->logged_in = logged_in();
            $this->context["title"]              = $title;
            $this->context["pagination"]         = $pagination;
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
         * Function: current
         * Returns a singleton reference to the current class.
         */
        public static function & current(): self {
            static $instance = null;
            $instance = (empty($instance)) ? new self() : $instance ;
            return $instance;
        }
    }
