<?php 
    class Sitemap extends Modules {

        public $change_frequency = array();

        public function __init() {                      # Add these strings to the .pot file.
            $this->change_frequency = array("hourly",   # __("hourly", "sitemap");
                                            "daily",    # __("daily", "sitemap");
                                            "weekly",   # __("weekly", "sitemap");
                                            "monthly",  # __("monthly", "sitemap");
                                            "yearly",   # __("yearly", "sitemap");
                                            "never");   # __("never", "sitemap");

            $this->addAlias("add_post", "make_sitemap", 8);
            $this->addAlias("update_post", "make_sitemap", 8);
        }

        static function __install() {
            $set = array(Config::current()->set("module_sitemap",
                                                array("blog_changefreq" => "daily",
                                                      "archives_changefreq" => "weekly",
                                                      "pages_changefreq" => "yearly",
                                                      "posts_changefreq" => "monthly")));
        }

        static function __uninstall() {
            Config::current()->remove("module_sitemap");
        }

        static function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["sitemap_settings"] = array("title" => __("Sitemap", "sitemap"));

            return $navs;
        }

        static function admin_sitemap_settings($admin) {
            $config = Config::current();

            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (empty($_POST))
                return $admin->display("sitemap_settings");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            $set = array($config->set("module_sitemap",
                                array("blog_changefreq" => $_POST['blog_changefreq'],
                                      "archives_changefreq" => $_POST['archives_changefreq'],
                                      "pages_changefreq" => $_POST['pages_changefreq'],
                                      "posts_changefreq" => $_POST['posts_changefreq'])));

            if (!in_array(false, $set))
                Flash::notice(__("Settings updated."), "/admin/?action=sitemap_settings");
        }

        /**
         * Function: make_sitemap
         * Displays a sitemap of the blog.
         */
        public static function make_sitemap()
        {
            $result = SQL::current()->select("posts",
                                             "posts.id",
                                             array("posts.status" => "public"),
                                             array("posts.id DESC"),
                                             array())->fetchAll();

            $ids = array();
            foreach ($result as $index => $row)
                $ids[] = $row["id"];

            if (!empty($ids))
                fallback($posts, Post::find(array("where" => array("id" => $ids))));
            else
                $posts = array();
          
            if (!is_array($posts))
                $posts = $posts->paginated;

            $pages = Page::find(array("where" => array("show_in_list" => true),
                                      "order" => "list_order ASC"));

            $config = Config::current();
            $sitemap_settings = Config::current()->module_sitemap;

            $output = "<?xml version='1.0' encoding='UTF-8'?>".PHP_EOL;
            $output.= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            $output.= "  <url>\n".
                      "    <loc>$config->url/</loc>\n".
                      "    <lastmod>".$posts[0]->updated_at."</lastmod>\n".
                      "    <changefreq>".$sitemap_settings["blog_changefreq"]."</changefreq>\n".
                      "  </url>\n".
                      "  <url>\n".
                      "    <loc>$config->url/archive/</loc>\n".
                      "    <changefreq>".$sitemap_settings["archives_changefreq"]."</changefreq>\n".
                      "  </url>\n";

            foreach ($posts as $post) {
                $updated = ($post->updated) ? $post->updated_at : $post->created_at ;
                $priority = ($post->pinned) ? "    <priority>1.0</priority>\n" : "" ;
                $url = $post->url();

                $output.= "  <url>\n".
                          "    <loc>$url</loc>\n".
                          "    <lastmod>$updated</lastmod>\n".
                          "    <changefreq>".$sitemap_settings["posts_changefreq"]."</changefreq>\n".$priority.
                          "  </url>\n";
            }

            foreach ($pages as $page) {
                $updated = ($page->updated) ? $page->updated_at : $page->created_at ;
                $url = $page->url();

                $output.= "  <url>\n".
                          "    <loc>$url</loc>\n".
                          "    <lastmod>$updated</lastmod>\n".
                          "    <changefreq>".$sitemap_settings["pages_changefreq"]."</changefreq>\n".
                          "  </url>\n";
            }

            $output.= "</urlset>";
            file_put_contents($_SERVER["DOCUMENT_ROOT"].DIR."sitemap.xml", $output);
        }
    }
