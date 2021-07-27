<?php
    class Cacher extends Modules {
        private $lastmod = 0;
        private $excluded = false;
        private $modified = false;

        public function __init() {
            $config = Config::current();

            $this->lastmod = $config->module_cacher["cache_lastmod"];
            $this->prepare_cache_triggers();
        }

        static function __install() {
            Config::current()->set("module_cacher",
                                   array("cache_lastmod" => time()));
        }

        static function __uninstall() {
            Config::current()->remove("module_cacher");
        }

        public function route_init($route) {
            if (!$this->eligible())
                return;

            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                $lastmod = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);

                if ($lastmod >= $this->lastmod) {
                    header_remove();
                    header($_SERVER['SERVER_PROTOCOL']." 304 Not Modified");
                    header("Cache-Control: no-cache");
                    header("Pragma: no-cache");
                    header("Expires: ".date("r", now("+30 days")));
                    exit;
                }
            }
        }

        public function route_done($route) {
            # Prevent erroneous redirections.
            unset($_SESSION['redirect_to']);
            unset($_SESSION['post_redirect']);
            unset($_SESSION['page_redirect']);

            if (!$this->eligible())
                return;

            if (!$route->success)
                return;

            if (!headers_sent()) {
                header("Last-Modified: ".date("r", $this->lastmod));
                header("Cache-Control: no-cache");
                header("Pragma: no-cache");
                header("Expires: ".date("r", now("+30 days")));
            }
        }

        private function eligible() {
            if (PREVIEWING)
                return false;

            if (!empty($_POST))
                return false;

            if (Flash::exists())
                return false;

            if ($this->excluded)
                return false;

            return true;
        }

        private function prepare_cache_triggers() {
            $trigger = Trigger::current();

            $regenerate = array(
                "add_post",
                "add_page",
                "update_post",
                "update_page",
                "update_user",
                "delete_post",
                "delete_page",
                "update_group",
                "delete_user",
                "publish_post",
                "import",
                "user_logged_in",
                "user_logged_out",
                "change_setting",

                # Categorize module:
                "add_category",
                "update_category",
                "delete_category",

                # Comments module:
                "add_comment",
                "update_comment",
                "delete_comment",

                # Likes module:
                "add_like",
                "delete_like",

                # Pingable module:
                "add_pingback",
                "update_pingback",
                "delete_pingback"
            );

            $trigger->filter($regenerate, "cache_regenerate_triggers");

            foreach ($regenerate as $action)
                $this->addAlias($action, "cache_regenerate");

            $exclude = array(
                "before_generate_captcha"
            );

            $trigger->filter($exclude, "cache_exclude_triggers");

            foreach ($exclude as $action)
                $this->addAlias($action, "cache_exclude");
        }

        public function cache_regenerate() {
            if ($this->modified)
                return;

            $this->modified = true;

            $config = Config::current();
            $settings = $config->module_cacher;
            $settings["cache_lastmod"] = time();
            $config->set("module_cacher", $settings);
        }

        public function cache_exclude() {
            $this->excluded = true;
        }
    }
