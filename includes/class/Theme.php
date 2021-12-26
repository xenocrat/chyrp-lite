<?php
    /**
     * Class: Theme
     * Various helper functions for blog themes.
     */
    class Theme {
        # String: $safename
        # The theme's non-camelized name.
        public $safename = "";

        # String: $url
        # The theme's absolute URL.
        public $url = "";

        # String: $title
        # The title for the current page.
        public $title = "";

        # Array: $caches
        # Query caches for methods.
        private $caches = array();

        /**
         * Function: __construct
         * Populates useful attributes.
         */
        private function __construct() {
            $this->url = THEME_URL;
            $this->safename = PREVIEWING ? $_SESSION['theme'] : Config::current()->theme ;
        }

        /**
         * Function: pages_list
         * Returns a simple array of pages with @depth@ and @children@ attributes.
         *
         * Parameters:
         *     $page_id - Page ID to use as the basis.
         *     $exclude - Page ID to exclude from the list.
         */
        public function pages_list($page_id = 0, $exclude = null) {
            $cache_id = serialize(array($page_id, $exclude));

            if (isset($this->caches["pages_list"][$cache_id]))
                return $this->caches["pages_list"][$cache_id];

            $this->caches["pages"]["flat"] = array();
            $this->caches["pages"]["children"] = array();

            $where = array("id not" => $exclude);

            if (MAIN)
                $where["show_in_list"] = true;

            $pages = Page::find(array("where" => $where,
                                      "order" => "list_order ASC"));

            if (empty($pages))
                return $this->caches["pages_list"][$cache_id] = array();

            foreach ($pages as $page)
                if ($page->parent_id != 0)
                    $this->caches["pages"]["children"][$page->parent_id][] = $page;

            foreach ($pages as $page)
                if (($page_id == 0 and $page->parent_id == 0) or ($page->id == $page_id))
                    $this->recurse_pages($page);

            return $this->caches["pages_list"][$cache_id] = $this->caches["pages"]["flat"];
        }

        /**
         * Function: recurse_pages
         * Populates the page cache and gives each page @depth@ and @children@ attributes.
         *
         * Parameters:
         *     $page - Page to start recursion at.
         */
        private function recurse_pages($page) {
            $page->depth    = isset($page->depth) ? $page->depth : 1 ;
            $page->children = isset($this->caches["pages"]["children"][$page->id]);

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
            if (isset($this->caches["archives_list"][$limit]))
                return $this->caches["archives_list"][$limit];

            $main = MainController::current();
            $sql = SQL::current();
            $feathers = Post::feathers();
            $statuses = Post::statuses();

            $array = array();
            $month = strtotime("midnight first day of this month");

            for ($i = 0; $i < $limit; $i++) {
                $count = $sql->count("posts",
                                     array("created_at LIKE" => when("Y-m-%", $month),
                                           $feathers,
                                           $statuses));

                if (!empty($count))
                    $array[] = array("when"  => $month,
                                     "url"   => url("archive/".when("Y/m/", $month), $main),
                                     "count" => $count);

                $month = strtotime("midnight first day of last month", $month);
            }

            return $this->caches["archives_list"][$limit] = $array;
        }

        /**
         * Function: recent_posts
         * Generates an array of recent posts.
         *
         * Parameters:
         *     $limit - Number of posts to list.
         */
        public function recent_posts($limit = 5) {
            if (isset($this->caches["recent_posts"][$limit]))
                return $this->caches["recent_posts"][$limit];

            $results = Post::find(array("placeholders" => true,
                                        "where" => array("status" => "public"),
                                        "order" => "created_at DESC, id DESC"));

            $posts = array();

            for ($i = 0; $i < $limit; $i++)
                if (isset($results[0][$i]))
                    $posts[] = new Post(null, array("read_from" => $results[0][$i]));

            return $this->caches["recent_posts"][$limit] = $posts;
        }

        /**
         * Function: related_posts
         * Ask modules to contribute to a list of related posts.
         *
         * Parameters:
         *     $post - The post to use as the basis.
         *     $limit - Number of related posts to list.
         */
        public function related_posts($post, $limit = 5) {
            if ($post->no_results)
                return;

            if (isset($this->caches["related_posts"][$post->id][$limit]))
                return $this->caches["related_posts"][$post->id][$limit];

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

            return $this->caches["related_posts"][$post->id][$limit] = $posts;
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
                $tags[] = '<link rel="stylesheet" href="'.fix($stylesheet, true).'" type="text/css" media="all">';

            if (is_dir(THEME_DIR.DIR."stylesheets") or is_dir(THEME_DIR.DIR."css")) {
                foreach(array_merge((array) glob(THEME_DIR.DIR."stylesheets".DIR."*.css"),
                                    (array) glob(THEME_DIR.DIR."css".DIR."*.css")) as $filepath) {

                    $filename = basename($filepath);

                    if (empty($filename) or substr_count($filename, ".inc.css"))
                        continue;

                    $qdir = preg_quote(DIR, "/");
                    $path = preg_replace("/(.+)".$qdir."themes".$qdir."(.+)/", "$2", $filepath);
                    $href = $config->chyrp_url."/themes/".str_replace(DIR, "/", $path);
                    $tags[] = '<link rel="stylesheet" href="'.fix($href, true).'" type="text/css" media="all">';
                }
            }

            return implode("\n", $tags);
        }

        /**
         * Function: javascripts
         * Outputs the JavaScript tags.
         */
        public function javascripts() {
            $config = Config::current();
            $route = Route::current();

            $scripts = array();

            # Ask extensions to provide additional scripts.
            Trigger::current()->filter($scripts, "scripts");

            # Generate <script> tags:
            $tags = array();

            foreach ($scripts as $script)
                $tags[] = '<script src="'.fix($script, true).'" type="text/javascript" charset="UTF-8"></script>';

            if (is_dir(THEME_DIR.DIR."javascripts") or is_dir(THEME_DIR.DIR."js")) {
                foreach(array_merge((array) glob(THEME_DIR.DIR."javascripts".DIR."*.js"),
                                    (array) glob(THEME_DIR.DIR."js".DIR."*.js")) as $filepath) {

                    $filename = basename($filepath);

                    if (empty($filename) or substr_count($filename, ".inc.js"))
                        continue;

                    $qdir = preg_quote(DIR, "/");
                    $path = preg_replace("/(.+)".$qdir."themes".$qdir."(.+)/", "$2", $filepath);
                    $href = $config->chyrp_url."/themes/".str_replace(DIR, "/", $path);
                    $tags[] = '<script src="'.fix($href, true).'" type="text/javascript" charset="UTF-8"></script>';
                }
            }

            return javascripts().implode("\n", $tags);
        }

        /**
         * Function: feeds
         * Outputs the feeds and other general purpose <link> tags.
         */
        public function feeds() {
            $config = Config::current();
            $route = Route::current();
            $main = MainController::current();

            # Generate the main feed that appears everywhere.
            $links = array(array("href" => url("feed", $main),
                                 "type" => BlogFeed::type(),
                                 "title" => $config->name));

            # Generate a feed for this route action if it seems appropriate.
            if ($route->action != "index" and !$main->feed and !empty($main->context["posts"]) and
                !($main->context["posts"] instanceof Paginator and $main->context["posts"]->total == 0)) {
                    # Rewind to page 1 (most recent) if the posts are paginated.
                    $page_url = ($main->context["posts"] instanceof Paginator) ?
                        $main->context["posts"]->prev_page_url(1) : self_url() ;

                    $feed_url = ($config->clean_urls) ?
                        rtrim($page_url, "/")."/feed/" :
                        $page_url.(substr_count($page_url, "?") ? "&feed" : "?feed") ;

                        $links[] = array("href" => $feed_url,
                                         "type" => BlogFeed::type(),
                                         "title" => oneof($this->title, $config->name));
            }

            # Ask extensions to provide additional links.
            Trigger::current()->filter($links, "links");

            # Generate <link> tags:
            $tags = array();

            foreach ($links as $link) {
                if (!isset($link["href"]))
                    continue;

                fallback($link["rel"], "alternate");
                fallback($link["type"]);
                fallback($link["title"]);

                $tags[] = '<link rel="'.fix($link["rel"], true).'" href="'.fix($link["href"], true).'"'.
                            (!empty($link["type"]) ? ' type="'.fix($link["type"], true).'"' : "").
                            (!empty($link["title"]) ? ' title="'.fix($link["title"], true).'"' : "").'>';
            }

            return implode("\n", $tags);
        }

        /**
         * Function: load_time
         * Returns the total elapsed time for this page load.
         */
        public function load_time() {
            return timer_stop();
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
