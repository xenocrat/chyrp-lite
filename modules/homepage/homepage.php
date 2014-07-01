<?php
    
    class Homepage extends Modules {
        static function __install() {
            Route::current()->add("/", "home");

            $home = Page::check_url("home");
            if ($home == "home" ) {
                $page = Page::add("My Awesome Homepage",
                              "Nothing here yet!",
                              null,
                              0,
                              true,
                              0,
                              "home");
            }
        }

        static function __uninstall($confirm) {
            Route::current()->remove("/");

            if ($confirm) {
                $home = new Page(array("url" => "home"));
                Page::delete($home->id);
            }
        }

        public function parse_urls($urls) {
            $urls["/\//"] = "/?action=home";
            return $urls;
        }

        public function main_home($main) {
            $page = new Page(array("url" => "home"));
            $main->display(array("pages/page", "pages/".$page->url), array("page" => $page), $page->title);
        }
    }
