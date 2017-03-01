<?php
    /**
     * Class: Theme
     * Various helper functions for the theming engine.
     */
    class Theme {
        # String: $title
        # The title for the current page.
        public $title = "";

        # Array: $caches
        # Query caches for methods.
        private $caches = array();

        /**
         * Function: __construct
         * Loads the theme's info and l10n domain.
         */
        private function __construct() {
            # Load the theme translator.
            load_translator(Config::current()->theme, THEME_DIR.DIR."locale");

            # Load the theme's info into the Theme class.
            foreach (load_info(THEME_DIR.DIR."info.php") as $key => $val)
                $this->$key = $val;

            $this->url = THEME_URL;
        }

        /**
         * Function: pages_list
         * Returns a simple array of pages with @depth@ and @children@ attributes.
         *
         * Parameters:
         *     $start - Page ID or slug to start at.
         *     $exclude - Page ID to exclude from the list.
         */
        public function pages_list($start = 0, $exclude = null) {
            if (isset($this->caches["pages_list"][$start]))
                return $this->caches["pages_list"][$start];

            $this->caches["pages"]["flat"] = array();
            $this->caches["pages"]["children"] = array();

            if (!empty($start) and !is_numeric($start)) {
                $from = new Page(array("url" => $start));

                if (!$from->no_results)
                    $start = $from->id;
                else
                    $start = (int) $start;
            }

            $where = ADMIN ? array("id not" => $exclude) : array("show_in_list" => true) ;
            $pages = Page::find(array("where" => $where, "order" => "list_order ASC"));

            if (empty($pages))
                return $this->caches["pages_list"][$start] = array();

            foreach ($pages as $page)
                if ($page->parent_id != 0)
                    $this->caches["pages"]["children"][$page->parent_id][] = $page;

            foreach ($pages as $page)
                if ((!$start and $page->parent_id == 0) or ($start and $page->id == $start))
                    $this->recurse_pages($page);

            if (!isset($exclude))
                return $this->caches["pages_list"][$start] = $this->caches["pages"]["flat"];
            else
                return $this->caches["pages"]["flat"];
        }

        /**
         * Function: recurse_pages
         * Populates the page cache and gives each page @depth@ and @children@ attributes.
         *
         * Parameters:
         *     $page - Page to start recursion at.
         */
        private function recurse_pages($page) {
            $page->depth    = (isset($page->depth)) ? $page->depth : 1 ;
            $page->children = (isset($this->caches["pages"]["children"][$page->id])) ? true : false ;

            $this->caches["pages"]["flat"][] = $page;

            if (isset($this->caches["pages"]["children"][$page->id]))
                foreach ($this->caches["pages"]["children"][$page->id] as $child) {
                    $child->depth = $page->depth + 1;
                    $this->recurse_pages($child);
                }
        }

        /**
         * Function: archive_list
         * Generates an array listing each month with entries in the archives.
         *
         * Parameters:
         *     $limit - Number of months to list.
         */
        public function archives_list($limit = 12) {
            if (isset($this->caches["archives_list"]["$limit"]))
                return $this->caches["archives_list"]["$limit"];

            $array = array();
            $month = strtotime("midnight first day of this month");

            for ($i = 0; $i < $limit; $i++) {
                $posts = Post::find(array("placeholders" => true,
                                          "where" => array("created_at >= :from AND created_at < :upto",
                                                           "status" => "public"),
                                          "params" => array(":from" => datetime("@$month"),
                                                            ":upto" => datetime(
                                                            strtotime("midnight first day of next month", $month)))));

                if (!empty($posts[0]))
                    $array[] = array("when"  => $month,
                                     "url"   => url("archive/".when("Y/m/", $month)),
                                     "count" => count($posts[0]));

                $month = strtotime("midnight first day of last month", $month);
            }

            return $this->caches["archives_list"]["$limit"] = $array;
        }

        /**
         * Function: recent_posts
         * Generates an array of recent posts.
         *
         * Parameters:
         *     $limit - Number of posts to list.
         */
        public function recent_posts($limit = 5) {
            if (isset($this->caches["recent_posts"]["$limit"]))
                return $this->caches["recent_posts"]["$limit"];

            $results = Post::find(array("placeholders" => true,
                                        "where" => array("status" => "public"),
                                        "order" => "created_at DESC, id DESC"));

            $posts = array();

            for ($i = 0; $i < $limit; $i++)
                if (isset($results[0][$i]))
                    $posts[] = new Post(null, array("read_from" => $results[0][$i]));

            return $this->caches["recent_posts"]["$limit"] = $posts;
        }

        /**
         * Function: related_posts
         * Ask modules to contribute to a list of related posts.
         *
         * Parameters:
         *     $post - List posts related to this post.
         *     $limit - Number of related posts to list.
         */
        public function related_posts($post, $limit = 5) {
            if ($post->no_results)
                return;

            if (isset($this->caches["related_posts"]["$post->id"]["$limit"]))
                return $this->caches["related_posts"]["$post->id"]["$limit"];

            $ids = array();

            Trigger::current()->filter($ids, "related_posts", $post, $limit);

            if (empty($ids))
                return;

            $results = Post::find(array("placeholders" => true,
                                        "where" => array("id" => $ids),
                                        "order" => "created_at DESC, id DESC"));

            $posts = array();

            for ($i = 0; $i < $limit; $i++)
                if (isset($results[0][$i]))
                    $posts[] = new Post(null, array("read_from" => $results[0][$i]));

            return $this->caches["related_posts"]["$post->id"]["$limit"] = $posts;
        }

        /**
         * Function: file_exists
         * Returns whether the specified Twig template file exists or not.
         *
         * Parameters:
         *     $name - The filename.
         */
        public function file_exists($name) {
            return file_exists(THEME_DIR.DIR.$name.".twig");
        }

        /**
         * Function: stylesheets
         * Outputs the stylesheet tags.
         */
        public function stylesheets() {
            $config = Config::current();

            $stylesheets = array();

            # Ask extensions to provide additional stylesheets.
            Trigger::current()->filter($stylesheets, "stylesheets");

            # Generate <link> tags:
            $tags = array();

            foreach ($stylesheets as $stylesheet)
                $tags[] = '<link rel="stylesheet" href="'.fix($stylesheet, true).'" type="text/css" media="all" charset="UTF-8">';

            if (is_dir(THEME_DIR.DIR."stylesheets") or is_dir(THEME_DIR.DIR."css")) {
                foreach(array_merge((array) glob(THEME_DIR.DIR."stylesheets".DIR."*.css"),
                                    (array) glob(THEME_DIR.DIR."stylesheets".DIR."*.css.php"),
                                    (array) glob(THEME_DIR.DIR."css".DIR."*.css"),
                                    (array) glob(THEME_DIR.DIR."css".DIR."*.css.php")) as $filepath) {

                    $filename = basename($filepath);

                    if (empty($filename) or substr_count($filename, ".inc.css"))
                        continue;

                    $path = preg_replace("/(.+)".preg_quote(DIR, "/")."themes".preg_quote(DIR, "/")."(.+)/", "$2", $filepath);
                    $href = $config->chyrp_url."/themes/".str_replace(DIR, "/", $path);
                    $tags[] = '<link rel="stylesheet" href="'.fix($href, true).'" type="text/css" media="all" charset="UTF-8">';
                }
            }

            return !empty($tags) ? "<!-- StyleSheets -->\n".implode("\n", $tags) : "" ;
        }

        /**
         * Function: javascripts
         * Outputs the JavaScript tags.
         */
        public function javascripts() {
            $config = Config::current();
            $route = Route::current();

            $scripts = array($config->chyrp_url."/includes/common.js",
                             $config->chyrp_url."/includes/javascript.php?action=".$route->action);

            # Ask extensions to provide additional scripts.
            Trigger::current()->filter($scripts, "scripts");

            # Generate <script> tags:
            $tags = array();

            foreach ($scripts as $script)
                $tags[] = '<script src="'.fix($script, true).'" type="text/javascript" charset="UTF-8"></script>';

            if (is_dir(THEME_DIR.DIR."javascripts") or is_dir(THEME_DIR.DIR."js")) {
                foreach(array_merge((array) glob(THEME_DIR.DIR."javascripts".DIR."*.js"),
                                    (array) glob(THEME_DIR.DIR."javascripts".DIR."*.js.php"),
                                    (array) glob(THEME_DIR.DIR."js".DIR."*.js"),
                                    (array) glob(THEME_DIR.DIR."js".DIR."*.js.php")) as $filepath) {

                    $filename = basename($filepath);

                    if (empty($filename) or substr_count($filename, ".inc.js"))
                        continue;

                    $path = preg_replace("/(.+)".preg_quote(DIR, "/")."themes".preg_quote(DIR, "/")."(.+)/", "$2", $filepath);
                    $href = $config->chyrp_url."/themes/".str_replace(DIR, "/", $path);
                    $tags[] = '<script src="'.fix($href, true).'" type="text/javascript" charset="UTF-8"></script>';
                }
            }

            return !empty($tags) ? "<!-- JavaScripts -->\n".implode("\n", $tags) : "" ;
        }

        /**
         * Function: feeds
         * Outputs the feeds and other general purpose <link> tags.
         */
        public function feeds() {
            $config = Config::current();
            $route = Route::current();

            # Generate site and page Atom feeds.
            $mainfeedurl = oneof($config->feed_url, url("feed"));
            $pagefeedurl = ($config->clean_urls) ?
                $config->url.rtrim($route->request, "/")."/feed/" :
                $config->url.$route->request.(substr_count($route->request, "?") ? "&amp;feed" : "?feed") ;

            # Add the site feed.
            $links = array(array("href" => $mainfeedurl,
                                 "type" => "application/atom+xml",
                                 "title" => $config->name));

            # Add the page feed if it's different from the site feed and there are posts in MainController's context.
            if (($pagefeedurl != $mainfeedurl) and array_key_exists("posts", MainController::current()->context))
                $links[] = array("href" => $pagefeedurl,
                                 "type" => "application/atom+xml",
                                 "title" => $config->name);

            # Ask extensions to provide additional links.
            Trigger::current()->filter($links, "links");
            
            # Generate <link> tags:
            $tags = array();

            foreach ($links as $link) {
                if (!isset($link["href"]))
                    continue;

                fallback($link["rel"], "alternate");
                fallback($link["type"], false);
                fallback($link["title"], false);

                $tags[] = '<link rel="'.fix($link["rel"], true).'" href="'.fix($link["href"], true).'"'.
                            (!empty($link["type"]) ? ' type="'.fix($link["type"], true).'"' : "").
                            (!empty($link["title"]) ? ' title="'.fix($link["title"], true).'"' : "").'>';
            }

            return !empty($tags) ? "<!-- Feeds -->\n".implode("\n", $tags) : "" ;
        }

        /**
         * Function: load_time
         * Returns the total elapsed time for this page load.
         */
        public function load_time() {
            return timer_stop();
        }

        /**
         * Function: cookies_notification
         * Flashes a notification about cookies to new visitors.
         */
        public function cookies_notification() {
            if (Config::current()->cookies_notification and empty($_SESSION['cookies_notified']))
                Flash::notice(__("By browsing this website you are agreeing to our use of cookies."));

            $_SESSION['cookies_notified'] = true;
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
