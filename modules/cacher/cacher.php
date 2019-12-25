<?php
    class Cacher extends Modules {
        private $exclude = array();
        private $url = "";
        private $regenerated = false;
        private $id;
        private $cachers;

        public function __init() {
            $config = Config::current();
            $visitor = Visitor::current();

            $seed          = $config->module_cacher["cache_seed"];
            $life          = $config->module_cacher["cache_expire"];
            $this->exclude = $config->module_cacher["cache_exclude"];
            $this->url     = rawurldecode(unfix(self_url()));
            $this->id      = token(array($seed,
                                         USE_ZLIB,
                                         HTTP_ACCEPT_DEFLATE,
                                         HTTP_ACCEPT_GZIP,
                                         session_id(),
                                         $visitor->id,
                                         $this->url));

            $this->cachers = array(new HTMLCacher($this->id, $life),
                                   new FeedCacher($this->id, $life));

            $this->prepare_cache_regenerators();
        }

        static function __install() {
            Config::current()->set("module_cacher",
                                   array("cache_seed" => random(8),
                                         "cache_expire" => 3600,
                                         "cache_exclude" => array()));
        }

        static function __uninstall() {
            Config::current()->remove("module_cacher");
        }

        public function route_init($route) {
            if (!(USE_OB and OB_BASE_LEVEL == ob_get_level()))
                return;

            if (!$this->cancelled and !in_array($this->url, $this->exclude))
                foreach ($this->cachers as $cacher)
                    $cacher->get($route);
        }

        public function end($route) {
            if (!(USE_OB and OB_BASE_LEVEL == ob_get_level()))
                return;

            if (!$this->cancelled and !in_array($this->url, $this->exclude))
                foreach ($this->cachers as $cacher)
                    $cacher->set($route);
        }

        private function prepare_cache_regenerators() {
            $trigger = Trigger::current();

            $regenerate = array(
                "add_post",
                "add_page",
                "update_post",
                "update_page",
                "update_user",
                "delete_post",
                "delete_page",
                "delete_user",
                "publish_post",
                "import_chyrp_post",
                "import_chyrp_page",
                "user_logged_in",
                "user_logged_out",
                "change_setting"
            );

            $trigger->filter($regenerate, "cache_regenerate_triggers");

            foreach ($regenerate as $action)
                $this->addAlias($action, "cache_regenerate");

            $exclude_urls = array(
                "before_generate_captcha"
            );

            $trigger->filter($exclude_urls, "cache_exclude_url_triggers");

            foreach ($exclude_urls as $action)
                $this->addAlias($action, "cache_exclude_url");
        }

        public function cache_regenerate() {
            if ($this->regenerated)
                return;

            $this->regenerated = true;

            foreach ($this->cachers as $cacher)
                $cacher->regenerate();

            $config = Config::current();
            $settings = $config->module_cacher;
            $settings["cache_seed"] = random(8);
            $config->set("module_cacher", $settings);
        }

        public function cache_exclude_url($url = null) {
            $raw = rawurldecode(unfix(is_url($url) ? $url : self_url()));

            if (!in_array($raw, $this->exclude))
                $this->exclude[] = $raw;
        }

        public function cache_id() {
            return $this->id;
        }

        public function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["cache_settings"] = array("title" => __("Cache", "cacher"));

            return $navs;
        }

        public function admin_cache_settings($admin) {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to change settings."));

            if (empty($_POST))
                return $admin->display("pages".DIR."cache_settings");

            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (isset($_POST['clear_cache']) and $_POST['clear_cache'] == "indubitably")
                $this->admin_clear_cache();

            fallback($_POST['cache_expire'], 3600);

            $config = Config::current();
            $settings = $config->module_cacher;
            $settings["cache_expire"] = (int) $_POST['cache_expire'];
            $config->set("module_cacher", $settings);

            Flash::notice(__("Settings updated."), "cache_settings");
        }

        public function admin_clear_cache() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to change settings."));

            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            $this->cache_regenerate();

            Flash::notice(__("Cache cleared.", "cacher"), "cache_settings");
        }
    }
