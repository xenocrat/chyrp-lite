<?php
    class ReadMore extends Modules {
        private $routing = false;

        public static function __install(): void {
            Config::current()->set(
                "module_read_more",
                array(
                    "apply_to_feeds" => false,
                    "default_text" => ""
                )
            );
        }

        public static function __uninstall(): void {
            Config::current()->remove("module_read_more");
        }

        public function __init(): void {
            # Truncate in "markup_post_text"
            # before Markdown filtering in "markup_text".
            $this->setPriority("markup_post_text", 1);
        }

        public function admin_read_more_settings($admin): void {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );
    
            if (empty($_POST)) {
                $admin->display("pages".DIR."read_more_settings");
                return;
            }
    
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );
    
            fallback($_POST['default_text'], "");

            Config::current()->set(
                "module_read_more",
                array(
                    "apply_to_feeds" => isset($_POST['apply_to_feeds']),
                    "default_text" => $_POST['default_text']
                )
            );

            Flash::notice(
                __("Settings updated."),
                "read_more_settings"
            );
        }

        public function settings_nav($navs): array {
            if (Visitor::current()->group->can("change_settings"))
                $navs["read_more_settings"] = array(
                    "title" => __("Read More", "read_more")
                );

            return $navs;
        }

        public function markup_post_text($text, $post = null): string {
            if (!preg_match("/<!-- *more([^>]*)?-->/i", $text, $matches))
                return $text;

            if (!isset($post) or !$this->eligible())
                return preg_replace("/<!-- *more([^>]*)?-->/i", "", $text, 1);

            $settings = Config::current()->module_read_more;

            $more = oneof(
                trim(fallback($matches[1], "")),
                $settings["default_text"],
                __("&hellip;more", "read_more")
            );

            $url = (!$post->no_results) ? $post->url() : "#" ;
            $split = preg_split("/<!-- *more([^>]*)?-->/i", $text, 2);

            return $split[0].
                   '<a class="read_more" href="'.$url.'">'.
                   fix($more).
                   '</a>';
        }

        public function title_from_excerpt($text): string {
            $split = preg_split('/<a class="read_more"/', $text, 2);
            return $split[0];
        }

        public function route_init() {
            $this->routing = true;
        }

        public function route_done() {
            $this->routing = false;
        }

        private function eligible(): bool {
            $route = Route::current();
            $settings = Config::current()->module_read_more;

            if (!isset($route))
                return false;

            if (!$this->routing)
                return false;

            if (!$route->controller instanceof MainController)
                return false;

            if ($route->action == "view")
                return false;

            if ($route->controller->feed and !$settings["apply_to_feeds"])
                return false;

            return true;
        }
    }
