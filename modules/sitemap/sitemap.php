<?php 
    class Sitemap extends Modules {

        public $change_frequency = array();

        public function __init() {
            $this->change_frequency = array("daily", "weekly", "monthly", "yearly");

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

            if (!isset($_POST['hash']) or $_POST['hash'] != $config->secure_hashkey)
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
     
            // header("Content-Type: application/atom+xml; charset=UTF-8");
     
            if (!is_array($posts))
                $posts = $posts->paginated;
     
            $config = Config::current();
            $sitemap_settings = Config::current()->module_sitemap;
      
            $title = (!empty($_GET['title'])) ? ": ".html_entity_decode($_GET['title']) : "" ;
            $output = '<?xml version=\'1.0\' encoding=\'UTF-8\'?>'.PHP_EOL;
            $output.= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">'."\n";
            // echo $output;
     
            $output.= <<<EOD
    <url>
      <loc>$config->url/</loc>
      <lastmod>{$posts[0]->updated_at}</lastmod>
      <changefreq>{$sitemap_settings["blog_changefreq"]}</changefreq>
    </url>
    <url>
      <loc>$config->url/archive/</loc>
      <changefreq>{$sitemap_settings["archives_changefreq"]}</changefreq>
    </url>\n
EOD;
     
            foreach ($posts as $post) {
                $updated = ($post->updated) ? $post->updated_at : $post->created_at ;
                $url = $post->url();
     
            $output.= <<<EOD
    <url>
      <loc>$url</loc>
      <lastmod>$updated</lastmod>
      <changefreq>{$sitemap_settings["posts_changefreq"]}</changefreq>
    </url>\n
EOD;
            }
            $output.= "</urlset>";

            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/sitemap.xml", $output);
            // Flash::notice(__("Sitemap generated successfully!"), "/");
        }
    }
