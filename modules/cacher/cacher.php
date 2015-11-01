<?php
    require_once "filecacher.php";

    class Cacher extends Modules {
        public function __init() {
            $config = Config::current();
            $this->cacher = new FileCacher(self_url(), $config);
            $this->prepare_cache_updaters();
        }

        static function __install() {
            $config = Config::current();
            $config->set("cache_expire", 3600);
            $config->set("cache_exclude", array());
        }

        static function __uninstall() {
            $config = Config::current();
            $config->remove("cache_expire");
            $config->remove("cache_exclude");
        }

        public function route_init($route) {
            if (!empty($_POST) or
                !($route->controller instanceof MainController) or
                in_array($this->cacher->url, Config::current()->cache_exclude) or
                $this->cancelled or
                !$this->cacher->url_available() or
                Flash::exists())
                return;

            $cache = $this->cacher->get($route);
        
            foreach($cache['headers'] as $header)
                header($header);
        
            exit($cache['contents']);
        }

        public function end($route) {
            if (!($route->controller instanceof MainController) or
                in_array($this->cacher->url, Config::current()->cache_exclude) or
                $this->cancelled or
                $this->cacher->url_available() or
                Flash::exists())
                return;

              $this->cacher->set(ob_get_contents());
        }

        public function prepare_cache_updaters() {
            $regenerate = array("add_post",
                                "add_page",
                                "update_post",
                                "update_page",
                                "delete_post",
                                "delete_page",
                                "change_setting");

            Trigger::current()->filter($regenerate, "cacher_regenerate_triggers");

            foreach ($regenerate as $action)
                $this->addAlias($action, "regenerate");

            $regenerate_posts = array();

            Trigger::current()->filter($regenerate_posts, "cacher_regenerate_posts_triggers");

            foreach ($regenerate_posts as $action)
                $this->addAlias($action, "remove_post_cache");
        }

        public function regenerate() {
            $this->cacher->regenerate();
        }

        public function regenerate_local($user = null) {
            $this->cacher->regenerate_local($user);
        }

        public function remove_caches_for($url) {
            $this->cacher->remove_caches_for($url);
        }

        public function remove_post_cache($id) {
            $post = new Post($id);
            $this->remove_caches_for(htmlspecialchars_decode($post->url()));
        }

        public function update_user($user) {
            $this->regenerate_local(sanitize($user->login));
        }

        public function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["cache_settings"] = array("title" => __("Cache", "cacher"));

            return $navs;
        }

        public function admin_cache_settings($admin) {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (empty($_POST))
                return $admin->display("cache_settings");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            $exclude = (empty($_POST['cache_exclude'])) ?
                            array() :
                            explode(",", str_replace(array("\n", "\r", " "), "", $_POST['cache_exclude'])) ;

            $config = Config::current();
            $set = array($config->set("cache_expire", (int) $_POST['cache_expire']),
                         $config->set("cache_exclude", $exclude));

            if (!in_array(false, $set))
                Flash::notice(__("Settings updated."), "/admin/?action=cache_settings");
        }

        public function admin_clear_cache() {
            if (!isset($_GET['hash']) or $_GET['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            $this->regenerate();

            Flash::notice(__("Cache cleared.", "cacher"), "/admin/?action=cache_settings");
        }
    }

