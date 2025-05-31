<?php
    class Cacher extends Modules {
        private $lastmod = 0;
        private $trigger_exclude = false;
        private $config_modified = false;

        public function __init(
        ): void {
            $config = Config::current();

            $this->lastmod = $config->module_cacher["cache_lastmod"];
            $this->prepare_cache_triggers();
            $this->setPriority("route_init", 5);
            $this->setPriority("route_done", 5);
        }

        public static function __install(
        ): void {
            Config::current()->set(
                "module_cacher",
                array("cache_lastmod" => time())
            );
        }

        public static function __uninstall(
        ): void {
            Config::current()->remove("module_cacher");
        }

        public function route_init(
            $route
        ): void {
            if (!$this->is_cacheable())
                return;

            if (!$this->validate_etag())
                return;

            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                $lastmod = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);

                if ($lastmod >= $this->lastmod) {
                    header_remove();
                    header($_SERVER['SERVER_PROTOCOL']." 304 Not Modified");
                    header("Cache-Control: no-cache, private");
                    header("Expires: ".date("r", now("+30 days")));
                    header("ETag: ".$this->generate_etag());
                    header("Vary: Accept-Encoding, Cookie, Save-Data, ETag");
                    exit;
                }
            }
        }

        public function route_done(
            $route
        ): void {
            # Prevent erroneous redirections.
            unset($_SESSION['redirect_to']);
            unset($_SESSION['post_redirect']);
            unset($_SESSION['page_redirect']);

            if (!$this->is_cacheable())
                return;

            if (!$route->success)
                return;

            if (!headers_sent()) {
                header_remove("Pragma");
                header("Last-Modified: ".date("r", $this->lastmod));
                header("Cache-Control: no-cache, private");
                header("Expires: ".date("r", now("+30 days")));
                header("ETag: ".$this->generate_etag());
                header("Vary: Accept-Encoding, Cookie, Save-Data, ETag");
            }
        }

        private function is_cacheable(
        ): bool {
            if (PREVIEWING)
                return false;

            if (!empty($_POST))
                return false;

            if (Flash::exists())
                return false;

            if ($this->trigger_exclude)
                return false;

            return true;
        }

        private function generate_etag(
        ): string {
            $items = array(
                Visitor::current()->id,
                Route::current()->request
            );

            return 'W/"'.token($items).'"';
        }

        private function validate_etag(
        ): bool {
            if (!isset($_SERVER['HTTP_IF_NONE_MATCH']))
                return false;

            return str_contains(
                $_SERVER['HTTP_IF_NONE_MATCH'],
                $this->generate_etag()
            );
        }

        private function prepare_cache_triggers(
        ): void {
            $trigger = Trigger::current();

            $regenerate = array(
                "add_post",
                "add_page",
                "update_post",
                "update_page",
                "update_user",
                "update_group",
                "delete_post",
                "delete_page",
                "delete_user",
                "delete_upload",
                "publish_post",
                "import",
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
                "user_logged_in",
                "user_logged_out",
                "before_generate_captcha"
            );

            $trigger->filter($exclude, "cache_exclude_triggers");

            foreach ($exclude as $action)
                $this->addAlias($action, "cache_exclude");
        }

        public function cache_regenerate(
        ): void {
            if ($this->modified)
                return;

            $this->config_modified = true;

            $config = Config::current();
            $settings = $config->module_cacher;
            $settings["cache_lastmod"] = time();
            $config->set("module_cacher", $settings);
        }

        public function cache_exclude(
        ): void {
            $this->trigger_exclude = true;
        }
    }
