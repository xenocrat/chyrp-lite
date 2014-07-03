<?php
    /**
     * Class: Theme
     * Various helper functions for the theming engine.
     */
    class Theme {
        # String: $title
        # The title for the current page.
        public $title = "";

        /**
         * Function: __construct
         * Loads the Twig parser into <Theme>, and sets up the theme l10n domain.
         */
        private function __construct() {
            $config = Config::current();

            # Load the theme translator
            if (file_exists(THEME_DIR."/locale/".$config->locale.".mo"))
                load_translator("theme", THEME_DIR."/locale/".$config->locale.".mo");

            # Load the theme's info into the Theme class.
            foreach (YAML::load(THEME_DIR."/info.yaml") as $key => $val)
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
            if (isset($this->pages_list[$start]))
                return $this->pages_list[$start];

            $this->linear_children = array();
            $this->pages_flat = array();
            $this->children = array();
            $this->end_tags_for = array();

            if ($start and !is_numeric($start))
                $begin_page = new Page(array("url" => $start));

            $start = ($start and !is_numeric($start)) ? $begin_page->id : $start ;

            $where = ADMIN ? array("id not" => $exclude) : array("show_in_list" => true) ;
            $pages = Page::find(array("where" => $where, "order" => "list_order ASC"));

            if (empty($pages))
                return $this->pages_list[$start] = array();

            foreach ($pages as $page)
                $this->end_tags_for[$page->id] = $this->children[$page->id] = array();

            foreach ($pages as $page)
                if ($page->parent_id != 0)
                    $this->children[$page->parent_id][] = $page;

            foreach ($pages as $page)
                if ((!$start and $page->parent_id == 0) or ($start and $page->id == $start))
                    $this->recurse_pages($page);

            $array = array();
            foreach ($this->pages_flat as $page) {
                $array[$page->id]["has_children"] = !empty($this->children[$page->id]);

                if ($array[$page->id]["has_children"])
                    $this->end_tags_for[$this->get_last_linear_child($page->id)][] = array("</ul>", "</li>");

                $array[$page->id]["end_tags"] =& $this->end_tags_for[$page->id];
                $array[$page->id]["page"] = $page;
            }

            if (!isset($exclude))
                return $this->pages_list[$start] = $array;
            else
                return $array;
        }

        /**
         * Function: get_last_linear_child
         * Gets the last linear child of a page.
         *
         * Parameters:
         *     $page - Page to get the last linear child of.
         *     $origin - Where to start.
         */
        public function get_last_linear_child($page, $origin = null) {
            fallback($origin, $page);

            $this->linear_children[$origin] = $page;
            foreach ($this->children[$page] as $child)
                $this->get_last_linear_child($child->id, $origin);

            return $this->linear_children[$origin];
        }

        /**
         * Function: recurse_pages
         * Prepares the pages into <Theme.$pages_flat>.
         *
         * Parameters:
         *     $page - Page to start recursion at.
         */
        public function recurse_pages($page) {
            $page->depth = oneof(@$page->depth, 1);

            $this->pages_flat[] = $page;

            foreach ($this->children[$page->id] as $child) {
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
            if (isset($this->archives_list["$limit,$order_by,$order"]))
                return $this->archives_list["$limit,$order_by,$order"];

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

            return $this->archives_list["$limit,$order_by,$order"] = $archives;
        }

        /**
         * Function: recent_posts
         * Generates an array of recent posts.
         *
         * Parameters:
         *     $limit - Number of posts to list
         */
        public function recent_posts($limit = 5) {
            if (isset($this->recent_posts["$limit"]))
                return $this->recent_posts["$limit"];

            $results = Post::find(array("placeholders" => true));
            $posts = array();

            for ($i = 0; $i < $limit; $i++)
                if (isset($results[0][$i]))
                    $posts[] = new Post(null, array("read_from" => $results[0][$i]));

            return $this->recent_posts["$limit"] = $posts;
        }

        /**
         * Function: file_exists
         * Returns whether the specified Twig file exists or not.
         *
         * Parameters:
         *     $file - The file's name
         */
        public function file_exists($file) {
            return file_exists(THEME_DIR."/".$file.".twig");
        }

        /**
         * Function: stylesheets
         * Outputs the default stylesheet links.
         */
        public function stylesheets() {
            $config = Config::current();

            $stylesheets = array();
            Trigger::current()->filter($stylesheets, "stylesheets");

            if (!empty($stylesheets))
                $stylesheets = '<link rel="stylesheet" href="'.
                               implode('" type="text/css" media="screen" charset="utf-8" />'."\n\t".'<link rel="stylesheet" href="', $stylesheets).
                               '" type="text/css" media="screen" charset="utf-8" />';
            else
                $stylesheets = "";

            if (file_exists(THEME_DIR."/style.css"))
                $stylesheets = '<link rel="stylesheet" href="'.THEME_URL.'/style.css" type="text/css" media="screen" charset="utf-8" />'."\n\t";

            if (!file_exists(THEME_DIR."/stylesheets/") and !file_exists(THEME_DIR."/css/"))
                return $stylesheets;

            $long  = (array) glob(THEME_DIR."/stylesheets/*");
            $short = (array) glob(THEME_DIR."/css/*");

            $total = array_merge($long, $short);
            foreach($total as $file) {
                $path = preg_replace("/(.+)\/themes\/(.+)/", "/themes/\\2", $file);
                $file = basename($file);

                if (substr_count($file, ".inc.css") or (substr($file, -4) != ".css" and substr($file, -4) != ".php"))
                    continue;

                if ($file == "ie.css")
                    $stylesheets.= "<!--[if IE]>";
                if (preg_match("/^ie([0-9\.]+)\.css/", $file, $matches))
                    $stylesheets.= "<!--[if IE ".$matches[1]."]>";
                elseif (preg_match("/(lte?|gte?)ie([0-9\.]+)\.css/", $file, $matches))
                    $stylesheets.= "<!--[if ".$matches[1]." IE ".$matches[2]."]>";

                $stylesheets.= '<link rel="stylesheet" href="'.$config->chyrp_url.$path.'" type="text/css" media="'.($file == "print.css" ? "print" : "screen").'" charset="utf-8" />';

                if ($file == "ie.css" or preg_match("/(lt|gt)?ie([0-9\.]+)\.css/", $file))
                    $stylesheets.= "<![endif]-->";

                $stylesheets.= "\n\t";
            }

            return $stylesheets;
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

            $javascripts = array($config->chyrp_url."/includes/lib/gz.php?file=jquery.js",
                                 $config->chyrp_url."/includes/lib/gz.php?file=plugins.js",
                                 $config->chyrp_url.'/includes/javascript.php?action='.$route->action.$args);
            Trigger::current()->filter($javascripts, "scripts");

            $javascripts = '<script src="'.
                           implode('" type="text/javascript" charset="utf-8"></script>'."\n\t".'<script src="', $javascripts).
                           '" type="text/javascript" charset="utf-8"></script>';

            if (file_exists(THEME_DIR."/javascripts/") or file_exists(THEME_DIR."/js/")) {
                $long  = (array) glob(THEME_DIR."/javascripts/*.js");
                $short = (array) glob(THEME_DIR."/js/*.js");

                foreach(array_merge($long, $short) as $file)
                    if ($file and !substr_count($file, ".inc.js"))
                        $javascripts.= "\n\t".'<script src="'.$config->chyrp_url.'/includes/lib/gz.php?file='.preg_replace("/(.+)\/themes\/(.+)/", "/themes/\\2", $file).'" type="text/javascript" charset="utf-8"></script>';

                $long  = (array) glob(THEME_DIR."/javascripts/*.php");
                $short = (array) glob(THEME_DIR."/js/*.php");
                foreach(array_merge($long, $short) as $file)
                    if ($file)
                        $javascripts.= "\n\t".'<script src="'.$config->chyrp_url.preg_replace("/(.+)\/themes\/(.+)/", "/themes/\\2", $file).'" type="text/javascript" charset="utf-8"></script>';
            }

            return $javascripts;
        }

        /**
         * Function: feeds
         * Outputs the Feed references.
         */
        public function feeds() {
            # Compute the URL of the per-page feed (if any):
            $config = Config::current();
            $request = ($config->clean_urls) ? rtrim(Route::current()->request, "/") : fix(Route::current()->request) ;
            $append = $config->clean_urls ?
                          "/feed" :
                          ((count($_GET) == 1 and Route::current()->action == "index") ?
                               "/?feed" :
                               "&amp;feed") ;
            $append.= $config->clean_urls ?
                          "/".urlencode($this->title) :
                          "&amp;title=".urlencode($this->title) ;

            # Create basic list of links (site and page Atom feeds):
            $feedurl = oneof(@$config->feed_url, url("feed"));
            $pagefeedurl = $config->url.$request.$append;
            $links = array(array("href" => $feedurl, "type" => "application/atom+xml", "title" => $config->name));

            if ($request !== "/")
                if ($pagefeedurl != $feedurl)
                    $links[] = array("href" => $pagefeedurl, "type" => "application/atom+xml", "title" => "$this->title");

            # Ask modules to pitch in by adding their own <link> tag items to $links.
            # Each item must be an array with "href" and "rel" properties (and optionally "title" and "type"):
            Trigger::current()->filter($links, "links");
            
            # Generate <link> tags:
            $tags = array();
            foreach ($links as $link) {
                $rel = oneof(@$link["rel"], "alternate");
                $href = $link["href"];
                $type = @$link["type"];
                $title = @$link["title"];
                $tag = '<link rel="'.$rel.'" href="'.$link["href"].'"';

                if ($type)
                    $tag.= ' type="'.$type.'"';

                if ($title)
                    $tag.= ' title="'.$title.'"';

                $tags[] = $tag.' />';
            }

            return implode("\n\t", $tags);
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
            return $instance = (empty($instance)) ? new self() : $instance ;
        }
    }
