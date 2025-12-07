<?php
    class Cacher extends Modules {
        private $lastmod = 0;
        private $caching = false;
        private $lastmod_updated = false;
        private $excluded_from_cache = false;
        private $callback_registered = false;

        public function __init(
        ): void {
            $config = Config::current();

            $this->lastmod = $config->module_cacher["cache_lastmod"];
            $this->prepare_cache_triggers();
            $this->setPriority("route_init", 8);
            $this->setPriority("route_done", 8);
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

        public function runtime(
        ) {
            if (
                header_register_callback(
                    array($this, "remove_cookie")
                )
            ) {
                $this->callback_registered = true;
            }
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
                    $this->caching = true;
                    header_remove();
                    header($_SERVER['SERVER_PROTOCOL']." 304 Not Modified");
                    header("ETag: ".$this->generate_etag());
                    header("Vary: Accept-Encoding, Cookie, Save-Data, ETag");

                    if ($this->is_public_cacheable()) {
                        header("Cache-Control: public, must-revalidate, stale-if-error");
                        header("Expires: ".date("r", now("+15 minutes")));
                    } else {
                        header("Cache-Control: no-cache, private");
                        header("Expires: ".date("r", now("+60 minutes")));
                    }

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
                $this->caching = true;
                header_remove("Pragma");
                header("Last-Modified: ".date("r", $this->lastmod));
                header("ETag: ".$this->generate_etag());
                header("Vary: Accept-Encoding, Cookie, Save-Data, ETag");

                if ($this->is_public_cacheable()) {
                    header("Cache-Control: public, must-revalidate, stale-if-error");
                    header("Expires: ".date("r", now("+15 minutes")));
                } else {
                    header("Cache-Control: no-cache, private");
                    header("Expires: ".date("r", now("+60 minutes")));
                }
            }
        }

        public function remove_cookie(
        ): void {
            if ($this->is_cacheable()) {
                header_remove("Set-Cookie");

                if ($this->is_public_cacheable())
                    Session::discard(true);
            }
        }

        private function is_cacheable(
        ): bool {
            if ($this->caching)
                return true;

            if (!$this->callback_registered)
                return false;

            if ($this->excluded_from_cache)
                return false;

            if (PREVIEWING)
                return false;

            if (!empty($_POST))
                return false;

            if (Flash::exists())
                return false;

            return true;
        }

        private function is_public_cacheable(
        ): bool {
            if (isset($_SERVER['HTTP_COOKIE']))
                return false;

            if (logged_in())
                return false;

            static $count;

            if (!isset($count)) {
                $count = count(
                    array_diff(
                        Visitor::current()->group->permissions,
                        array("view_site")
                    )
                );
            }

            return (bool) $count;
        }

        private function generate_etag(
        ): string {
            $items = array(
                Visitor::current()->id,
                Route::current()->request,
                $this->is_public_cacheable()
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

            $invalidate = array(
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
                "add_category",
                "update_category",
                "delete_category",
                "add_comment",
                "update_comment",
                "delete_comment",
                "add_like",
                "delete_like",
                "add_pingback",
                "update_pingback",
                "delete_pingback"
            );

            $trigger->filter($invalidate, "cache_invalidate_triggers");

            foreach ($invalidate as $action)
                $this->addAlias($action, "cache_invalidate");

            $exclude = array(
                "flash_message",
                "flash_notice",
                "flash_warning",
                "main_login",
                "main_logout",
                "main_controls",
                "main_register",
                "main_activate",
                "main_lost_password",
                "main_reset_password",
                "user_logged_in",
                "user_logged_out",
                "before_generate_captcha"
            );

            $trigger->filter($exclude, "cache_exclude_triggers");

            foreach ($exclude as $action)
                $this->addAlias($action, "cache_exclude");
        }

        public function cache_invalidate(
        ): void {
            if ($this->lastmod_updated)
                return;

            $this->lastmod_updated = true;

            $config = Config::current();
            $settings = $config->module_cacher;
            $settings["cache_lastmod"] = time();
            $config->set("module_cacher", $settings);
        }

        public function cache_exclude(
        ): bool {
            $this->excluded_from_cache = true;
            return false;
        }
    }
