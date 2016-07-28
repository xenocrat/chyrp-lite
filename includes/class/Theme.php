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
            $config = Config::current();

            # Load the theme translator.
            load_translator("theme", THEME_DIR.DIR."locale".DIR.$config->locale.".mo");

            # Load the theme's info into the Theme class.
            foreach (include THEME_DIR.DIR."info.php" as $key => $val)
                $this->$key = $val;

            $this->url = THEME_URL;
        }

        /**
         * Function: pages_list
         * Returns a simple array of list items to be used by the theme to generate a recursive array of pages.
         *
         * Parameters:
         *     $start - Page ID or slug to start at.
         *     $exclude - Page ID to exclude from the list. Used in the admin area.
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
            $page->depth = oneof(@$page->depth, 1);
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
         * Generates an array of all of the archives, by month.
         *
         * Parameters:
         *     $limit - Amount of months to list
         *     $order_by - What to sort it by
         *     $order - "asc" or "desc"
         *
         * Returns:
         *     The array. Each entry as "month", "year", and "url" values, stored as an array.
         */
        public function archives_list($limit = 0, $order_by = "created_at", $order = "desc") {
            if (isset($this->caches["archives_list"]["$limit,$order_by,$order"]))
                return $this->caches["archives_list"]["$limit,$order_by,$order"];

            $sql = SQL::current();
            $dates = $sql->select("posts",
                                  array("DISTINCT YEAR(created_at) AS year",
                                        "MONTH(created_at) AS month",
                                        "created_at AS created_at",
                                        "COUNT(id) AS posts"),
                                  array("status" => "public", Post::feathers()),
                                  $order_by." ".strtoupper($order),
                                  array(),
                                  ($limit == 0) ? null : $limit,
                                  null,
                                  array("created_at"));

            $archives = array();
            $grouped = array();

            while ($date = $dates->fetchObject())
                if (isset($grouped[$date->month." ".$date->year]))
                    $archives[$grouped[$date->month." ".$date->year]]["count"]++;
                else {
                    $grouped[$date->month." ".$date->year] = count($archives);
                    $archives[] = array("month" => $date->month,
                                        "year"  => $date->year,
                                        "when"  => $date->created_at,
                                        "url"   => url("archive/".when("Y/m/", $date->created_at)),
                                        "count" => $date->posts);
                }

            return $this->caches["archives_list"]["$limit,$order_by,$order"] = $archives;
        }

        /**
         * Function: recent_posts
         * Generates an array of recent posts.
         *
         * Parameters:
         *     $limit - Number of posts to list
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
         *     $post - List posts related to this post
         *     $limit - Number of related posts to list
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
         * Returns whether the specified Twig file exists or not.
         *
         * Parameters:
         *     $file - The file's name
         */
        public function file_exists($file) {
            return file_exists(THEME_DIR.DIR.$file.".twig");
        }

        /**
         * Function: stylesheets
         * Outputs the default stylesheet links.
         */
        public function stylesheets() {
            $config = Config::current();

            $stylesheets = array();
            Trigger::current()->filter($stylesheets, "stylesheets");

            $elements = "<!-- Styles -->";
            if (!empty($stylesheets))
                foreach ($stylesheets as $stylesheet)
                    $elements.= "\n".'<link rel="stylesheet" href="'.$stylesheet.'" type="text/css" media="all" charset="UTF-8">';

            if (!file_exists(THEME_DIR.DIR."stylesheets".DIR) and !file_exists(THEME_DIR.DIR."css".DIR))
                return $elements;

            foreach(array_merge((array) glob(THEME_DIR.DIR."stylesheets".DIR."*.css"),
                                (array) glob(THEME_DIR.DIR."stylesheets".DIR."*.css.php"),
                                (array) glob(THEME_DIR.DIR."css".DIR."*.css"),
                                (array) glob(THEME_DIR.DIR."css".DIR."*.css.php")) as $file) {

                $path = preg_replace("/(.+)".preg_quote(DIR, "/")."themes".preg_quote(DIR, "/")."(.+)/", "/themes/\\2", $file);
                $file = basename($file);

                if (!$file or substr_count($file, ".inc.css"))
                    continue;

                $name = substr($file, 0, strpos($file, "."));
                switch ($name) {
                    case "print":
                        $media = "print";
                        break;
                    case "screen":
                        $media = "screen";
                        break;
                    case "speech":
                        $media = "speech";
                        break;
                    default:
                        $media = "all";
                        break;
                }

                $elements.= "\n".'<link rel="stylesheet" href="'.$config->chyrp_url.$path.'" type="text/css" media="'.$media.'">';
            }

            return $elements;
        }

        /**
         * Function: javascripts
         * Outputs the default JavaScript script references.
         */
        public function javascripts() {
            $config = Config::current();
            $route = Route::current();

            $args = "";

            foreach ($_GET as $key => $val)
                if (!empty($val) and $val != $route->action)
                    $args.= "&amp;".$key."=".urlencode($val);

            $javascripts = array($config->chyrp_url."/includes/common.js",
                                 $config->chyrp_url.'/includes/javascript.php?action='.$route->action.$args);

            Trigger::current()->filter($javascripts, "scripts");

            $elements = "<!-- JavaScripts -->";

            foreach ($javascripts as $javascript)
                $elements.= "\n".'<script src="'.$javascript.'" type="text/javascript" charset="UTF-8"></script>';

            if (file_exists(THEME_DIR.DIR."javascripts".DIR) or file_exists(THEME_DIR.DIR."js".DIR)) {
                foreach(array_merge((array) glob(THEME_DIR.DIR."javascripts".DIR."*.js"),
                                    (array) glob(THEME_DIR.DIR."javascripts".DIR."*.js.php"),
                                    (array) glob(THEME_DIR.DIR."js".DIR."*.js"),
                                    (array) glob(THEME_DIR.DIR."js".DIR."*.js.php")) as $file) {

                    if (substr_count($file, ".inc.js"))
                        continue;

                    $path = preg_replace("/(.+)".preg_quote(DIR, "/")."themes".preg_quote(DIR, "/")."(.+)/", "/themes/\\2", $file);
                    $elements.= "\n".'<script src="'.$config->chyrp_url.$path.'" type="text/javascript" charset="UTF-8"></script>';
                }
            }

            return $elements;
        }

        /**
         * Function: feeds
         * Outputs the Feed references.
         */
        public function feeds() {
            # Compute the URL of the per-page feed (if any):
            $config = Config::current();
            $route = Route::current();
            $request = ($config->clean_urls) ? rtrim($route->request, "/") : fix($route->request) ;
            $append = $config->clean_urls ?
                          "/feed/" :
                          ((count($_GET) == 1 and $route->action == "index") ?
                               "/?feed" :
                               "&amp;feed") ;

            # Create basic list of links (site and page Atom feeds):
            $mainfeedurl = oneof($config->feed_url, url("feed"));
            $pagefeedurl = $config->url.$request.$append;
            $links = array(array("href" => $mainfeedurl, "type" => "application/atom+xml", "title" => $config->name));

            if (array_key_exists("posts", MainController::current()->context) and ($pagefeedurl != $mainfeedurl))
                $links[] = array("href" => $pagefeedurl, "type" => "application/atom+xml");

            # Ask modules to pitch in by adding their own <link> tag items to $links.
            # Each item must be an array with "href" and "rel" properties (and optionally "title" and "type"):
            Trigger::current()->filter($links, "links");
            
            # Generate <link> tags:
            $tags = array();

            foreach ($links as $link) {
                $rel = oneof(fallback($link["rel"], ""), "alternate");
                $href = $link["href"];
                $type = fallback($link["type"], false);
                $title = fallback($link["title"], false);
                $tag = '<link rel="'.$rel.'" href="'.$link["href"].'"';

                if ($type)
                    $tag.= ' type="'.$type.'"';

                if ($title)
                    $tag.= ' title="'.$title.'"';

                $tags[] = $tag.'>';
            }

            return "<!-- Feeds -->\n".implode("\n", $tags);
        }

        public function load_time() {
            return timer_stop();
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
