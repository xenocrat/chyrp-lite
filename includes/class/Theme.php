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

            $this->safename = PREVIEWING ?
                $_SESSION['theme'] :
                Config::current()->theme ;
        }

        /**
         * Function: pages_list
         * Returns an array of pages with @depth@ and @children@ attributes.
         *
         * Parameters:
         *     $page_id - Page ID to start from, or zero to return all pages.
         *     $exclude - Page ID/s to exclude, integer or array of integers.
         */
        public function pages_list(
            $page_id = 0,
            $exclude = null
        ): array {
            $cache_id = serialize(array($page_id, $exclude));

            if (
                isset($this->caches["pages_list"][$cache_id])
            ) {
                return $this->caches["pages_list"][$cache_id];
            }

            $this->caches["pages"]["flat"] = array();
            $this->caches["pages"]["children"] = array();

            $where = array("id not" => $exclude);

            if (MAIN)
                $where["show_in_list"] = true;

            $pages = Page::find(
                array(
                    "where" => $where,
                    "order" => "list_order ASC, title ASC"
                )
            );

            if (empty($pages))
                return $this->caches["pages_list"][$cache_id] = array();

            foreach ($pages as $page) {
                if ($page->parent_id != 0)
                    $this->caches["pages"]["children"][$page->parent_id][] = $page;
            }

            foreach ($pages as $page) {
                if (
                    ($page_id == 0 and $page->parent_id == 0) or
                    ($page->id == $page_id)
                )
                    $this->recurse_pages($page);
            }

            $list = $this->caches["pages"]["flat"];
            return $this->caches["pages_list"][$cache_id] = $list;
        }

        /**
         * Function: recurse_pages
         * Populates the page cache and gives each page the attributes
         * of @depth@ (integer, 1 or greater) and @children@ (boolean).
         *
         * Parameters:
         *     $page - Page to start recursion at.
         */
        private function recurse_pages(
            $page
        ): void {
            if (!isset($page->depth))
                $page->depth = 1;

            $page->children = isset(
                $this->caches["pages"]["children"][$page->id]
            );

            $this->caches["pages"]["flat"][] = $page;

            if ($page->children) {
                foreach (
                    $this->caches["pages"]["children"][$page->id] as $child
                ) {
                    $child->depth = $page->depth + 1;
                    $this->recurse_pages($child);
                }
            }
        }

        /**
         * Function: archive_list
         * Generates an array listing each month with entries in the archives.
         *
         * Parameters:
         *     $limit - Maximum number of months to list.
         */
        public function archives_list(
            $limit = 12
        ): array {
            if (
                isset($this->caches["archives_list"][$limit])
            ) {
                return $this->caches["archives_list"][$limit];
            }

            $main = MainController::current();
            $sql = SQL::current();
            $feathers = Post::feathers();
            $statuses = Post::statuses();

            $results = $sql->select(
                tables:"posts",
                fields:array("created_at"),
                conds:array($feathers, $statuses),
                order:"created_at DESC"
            )->fetchAll();

            $nums = array();

            foreach ($results as $result) {
                $created_at = strtotime($result["created_at"]);
                $this_month = strtotime(
                    "midnight first day of this month",
                    $created_at
                );

                if (!isset($nums[$this_month])) {
                    if (count($nums) == $limit)
                        break;

                    $nums[$this_month] = 0;
                }

                $nums[$this_month]++;
            }

            $list = array();

            foreach ($nums as $when => $count) {
                $list[] = array(
                    "when"  => $when,
                    "url"   => url("archive/".when("Y/m/", $when), $main),
                    "count" => $count
                );
            }

            return $this->caches["archives_list"][$limit] = $list;
        }

        /**
         * Function: recent_posts
         * Generates an array of recent posts.
         *
         * Parameters:
         *     $limit - Maximum number of recent posts to list.
         */
        public function recent_posts(
            $limit = 5
        ): array {
            if (
                isset($this->caches["recent_posts"][$limit])
            ) {
                return $this->caches["recent_posts"][$limit];
            }

            $results = Post::find(
                array(
                    "placeholders" => true,
                    "where" => array("status" => "public"),
                    "order" => "created_at DESC, id DESC"
                )
            );

            $posts = array();

            for ($i = 0; $i < $limit; $i++) {
                if (isset($results[0][$i]))
                    $posts[] = new Post(
                        null,
                        array("read_from" => $results[0][$i])
                    );
            }

            return $this->caches["recent_posts"][$limit] = $posts;
        }

        /**
         * Function: related_posts
         * Ask modules to contribute to a list of related posts.
         *
         * Parameters:
         *     $post - The post to use as the basis.
         *     $limit - Maximum number of related posts to list.
         */
        public function related_posts(
            $post,
            $limit = 5
        ): array {
            if ($post->no_results)
                return array();

            if (
                isset($this->caches["related_posts"][$post->id][$limit])
            ) {
                return $this->caches["related_posts"][$post->id][$limit];
            }

            $ids = array();

            Trigger::current()->filter($ids, "related_posts", $post, $limit);

            if (empty($ids))
                return array();

            $results = Post::find(
                array(
                    "placeholders" => true,
                    "where" => array("id" => array_unique($ids)),
                    "order" => "created_at DESC, id DESC")
            );

            $posts = array();

            for ($i = 0; $i < $limit; $i++)
                if (isset($results[0][$i]))
                    $posts[] = new Post(
                        null,
                        array("read_from" => $results[0][$i])
                    );

            return $this->caches["related_posts"][$post->id][$limit] = $posts;
        }

        /**
         * Function: file_exists
         * Returns whether the specified Twig template file exists or not.
         *
         * Parameters:
         *     $name - The filename.
         */
        public function file_exists(
            $name
        ): bool {
            return file_exists(THEME_DIR.DIR.$name.".twig");
        }

        /**
         * Function: stylesheets
         * Outputs the stylesheet tags.
         */
        public function stylesheets(
        ): string {
            $config = Config::current();
            $trigger = Trigger::current();

            $stylesheets = array();

            if (ADMIN) {
                $stylesheets[] = $config->chyrp_url.
                                 "/admin/stylesheets/all.css";

                # Ask extensions to provide additional admin stylesheets.
                $trigger->filter($stylesheets, "admin_stylesheets");
            } else {
                # Discover stylesheets provided by the theme.
                foreach (
                    array_merge(
                        (array) glob(THEME_DIR.DIR."stylesheets".DIR."*.css"),
                        (array) glob(THEME_DIR.DIR."css".DIR."*.css")
                    ) as $filepath
                ) {
                    $filename = basename($filepath);

                    if (empty($filename))
                        continue;

                    if (str_ends_with($filename, ".inc.css"))
                        continue;

                    $qdir = preg_quote(DIR, "/");

                    $path = preg_replace(
                        "/(.+)".$qdir."themes".$qdir."(.+)/",
                        "$2",
                        $filepath
                    );

                    $stylesheets[] = $config->chyrp_url.
                                     "/themes/".
                                     str_replace(DIR, "/", $path);
                }

                # Ask extensions to provide additional blog stylesheets.
                $trigger->filter($stylesheets, "stylesheets");
            }

            # Generate <link> tags:
            $tags = array();

            foreach ($stylesheets as $stylesheet) {
                $tags[] = '<link rel="stylesheet" href="'.
                          fix($stylesheet, true).
                          '" type="text/css" media="all">';
            }

            return implode("\n", $tags);
        }

        /**
         * Function: javascripts
         * Outputs the JavaScript tags.
         */
        public function javascripts(
        ): string {
            $config = Config::current();
            $trigger = Trigger::current();

            $scripts = array();

            if (ADMIN) {
                # Ask extensions to provide additional admin scripts.
                $trigger->filter($scripts, "admin_scripts");
            } else {
                # Discover scripts provided by the theme.
                foreach (
                    array_merge(
                        (array) glob(THEME_DIR.DIR."javascripts".DIR."*.js"),
                        (array) glob(THEME_DIR.DIR."js".DIR."*.js")
                    ) as $filepath
                ) {
                    $filename = basename($filepath);

                    if (empty($filename))
                        continue;

                    if (str_ends_with($filename, ".inc.js"))
                        continue;

                    $qdir = preg_quote(DIR, "/");

                    $path = preg_replace(
                        "/(.+)".$qdir."themes".$qdir."(.+)/",
                        "$2",
                        $filepath
                    );

                    $scripts[] = $config->chyrp_url.
                                 "/themes/".
                                 str_replace(DIR, "/", $path);
                }

                # Ask extensions to provide additional blog scripts.
                $trigger->filter($scripts, "scripts");
            }

            # Generate <script> tags:
            $tags = array();

            foreach ($scripts as $script) {
                $tags[] = '<script src="'.
                          fix($script, true).
                          '"></script>';
            }

            return javascripts().implode("\n", $tags);
        }

        /**
         * Function: feeds
         * Outputs the feeds and other general purpose <link> tags.
         */
        public function feeds(
        ): string {
            $config = Config::current();
            $route = Route::current();
            $trigger = Trigger::current();
            $main = MainController::current();

            # This action is a feed; return an empty string.
            if ($route->controller->feed)
                return "";

            # Generate the main feed that appears everywhere.
            $links = array(
                array(
                    "href" => url("feed", $main),
                    "type" => BlogFeed::type(),
                    "title" => $config->name
                )
            );

            if (ADMIN) {
                # Ask extensions to provide additional admin links.
                $trigger->filter($links, "admin_links");
            } else {
                $posts =
                    $route->controller->context["posts"] ??
                    false ;

                # Automatically provide feeds based on context.
                if ($route->action != "index") {
                    if ($posts instanceof Paginator) {
                        $page_url = $posts->canonical_url();
                        $feed_url = $this->feed_url($page_url);

                        $links[] = array(
                            "href" => $feed_url,
                            "type" => BlogFeed::type(),
                            "title" => $this->title
                        );
                    }
                }

                # Ask extensions to provide additional blog links.
                $trigger->filter($links, "links");
            }

            # Generate <link> tags:
            $tags = array();

            foreach ($links as $link) {
                if (!isset($link["href"]))
                    continue;

                fallback($link["rel"], "alternate");
                fallback($link["type"]);
                fallback($link["title"]);

                $tag = '<link rel="'.fix($link["rel"], true).
                       '" href="'.fix($link["href"], true).'"';

                if (!empty($link["type"]))
                    $tag.= ' type="'.fix($link["type"], true).'"';

                if (!empty($link["title"]))
                    $tag.= ' title="'.fix($link["title"], true).'"';

                $tag.= '>';

                $tags[] = $tag;
            }

            return implode("\n", $tags);
        }

        /**
         * Function: feed_url
         * Constructs a clean or dirty feed URL.
         */
        public function feed_url(
            $url
        ): string {
            $config = Config::current();
            $route = Route::current();
            $request = unfix($url);

            $clean = (
                $config->clean_urls and
                $route->controller->clean_urls
            );

            $feed_url = ($clean) ?
                rtrim($request, "/")."/feed/"
                :
                $request.
                (
                    str_contains($request, "?") ?
                        "&feed" :
                        "?feed"
                )
                ;

            return fix($feed_url, true);
        }

        /**
         * Function: load_time
         * Returns the total elapsed time for this page load.
         */
        public function load_time(
        ): string {
            return timer_stop();
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
