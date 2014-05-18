<?php
    require_once "filecacher.php";
    require_once "memcacher.php";
    class Cacher extends Modules {
        public function __init() {
            # Prepare actions that should result in new cache files.
            $this->prepare_cache_updaters();

            $config = Config::current();
            $config->cache_exclude = (array) $config->cache_exclude;

            if (!empty($config->cache_exclude))
                foreach ($config->cache_exclude as &$exclude)
                    if (substr($exclude, 7) != "http://")
                        $exclude = $config->url."/".ltrim($exclude, "/");
        
            if(count((array)$config->cache_memcached_hosts) > 0){
              $this->cacher = new MemCacher(self_url(), $config);
            }else{
              $this->cacher = new FileCacher(self_url(), $config);
            }
        
            # Prepare actions that should result in new cache files.
            $this->prepare_cache_updaters();
        }

        static function __install() {
            $config = Config::current();
            $config->set("cache_expire", 1800);
            $config->set("cache_exclude", array());
            $config->set("cache_memcached_hosts", array());
        }

        static function __uninstall() {
            $config = Config::current();
            $config->remove("cache_expire");
            $config->remove("cache_exclude");
            $config->remove("cache_memcached_hosts");
        }

        public function route_init($route) {
            if (!empty($_POST) or
                !($route->controller instanceof MainController) or
                in_array($this->url, Config::current()->cache_exclude) or
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
                in_array($this->url, Config::current()->cache_exclude) or
                $this->cancelled or
                $this->cacher->url_available() or
                Flash::exists())
                return;

              $this->cacher->set(ob_get_contents());
        }

        public function prepare_cache_updaters() {
            $regenerate = array("add_post",    "add_page",
                                "update_post", "update_page",
                                "delete_post", "delete_page",
                                "change_setting");

            Trigger::current()->filter($regenerate, "cacher_regenerate_triggers");
            foreach ($regenerate as $action)
                $this->addAlias($action, "regenerate");

            $post_triggers = array();
            foreach (Trigger::current()->filter($post_triggers, "cacher_regenerate_posts_triggers") as $action)
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

        public function remove_post_cache($thing) {
            $this->remove_caches_for(htmlspecialchars_decode($thing->post()->url()));
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

            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            $exclude = (empty($_POST['cache_exclude']) ? array() : explode(", ", $_POST['cache_exclude']));
        
            $memcached_hosts = empty($_POST['cache_memcached_hosts']) ? array() : explode(", ", $_POST['cache_memcached_hosts']);

            $config = Config::current();
            if ($config->set("cache_expire", $_POST['cache_expire']) and $config->set("cache_exclude", $exclude) and $config->set("cache_memcached_hosts", $memcached_hosts))
                Flash::notice(__("Settings updated."), "/admin/?action=cache_settings");
        }

        public function admin_clear_cache() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            $this->regenerate();

            Flash::notice(__("Cache cleared.", "cacher"), "/admin/?action=cache_settings");
        }
    }

