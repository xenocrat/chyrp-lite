<?php
    class Notify extends Modules {
        const NTFY_URL     = "https://ntfy.sh/";
        const PUSHOVER_URL = "https://api.pushover.net/1/messages.json";

        private function hooks(
        ): array {
            static $hooks;

            if (!isset($hooks)) {
                $hooks = array(
                    "add_post"     => array(
                        "class" => Post::class,
                        "label" => __("New Post", "notify"),
                        "message" => __("Post created.", "notify"),
                    ),
                    "add_page"     => array(
                        "class" => Page::class,
                        "label" => __("New Page", "notify"),
                        "message" => __("Page created.", "notify"),
                    ),
                    "add_user"     => array(
                        "class" => User::class,
                        "label" => __("New User", "notify"),
                        "message" => __("User created.", "notify"),
                    ),
                    "add_comment"  => array(
                        "class" => Comment::class,
                        "label" => __("Comment Added", "notify"),
                        "message" => __("Someone commented on a post.", "notify"),
                    ),
                    "add_like"     => array(
                        "class" => Like::class,
                        "label" => __("Post Liked", "notify"),
                        "message" => __("Someone liked a post.", "notify"),
                    ),
                    "add_pingback" => array(
                        "class" => Pingback::class,
                        "label" => __("Webmention Received", "notify"),
                        "message" => __("Someone sent a webmention.", "notify"),
                    ),
                );

                Trigger::current()->filter($hooks, "notify_define_hooks");
            }

            return $hooks;
        }

        public static function __install(
        ): void {
            $config = Config::current();

            $config->set(
                "module_notify",
                array(
                    "hooks"             => [],
                    "ntfy_enabled"      => false,
                    "ntfy_topic"        => "",
                    "pushover_enabled"  => false,
                    "pushover_token"    => "",
                    "pushover_user_key" => "",
                )
            );
        }

        public static function __uninstall(
        ): void {
            $config = Config::current();
            $config->remove("module_notify");
        }

        public function __init(
        ): void {
            foreach (array_keys($this->hooks()) as $hook) {
                if ($this->want_message($hook)) {
                    $this->addAlias($hook, "add_model", 99);
                }
            }
        }

        public function admin_notify_settings(
            AdminController $admin
        ): void {
            $config = Config::current();

            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            if (empty($_POST)) {
                $admin->display(
                    "pages".DIR."notify_settings",
                    array("hook_list" => $this->hooks()),
                );

                return;
            }

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            $ntfy_enabled = !empty($_POST['ntfy_enabled']);
            $ntfy_topic = trim($_POST['ntfy_topic'] ?? "");

            if ($ntfy_enabled) {
                if (!preg_match("/^[\-_a-zA-Z0-9]{4,64}$/", $ntfy_topic)) {
                    error(
                        __("Error"),
                        __("Invalid ntfy topic.", "notify"),
                        code:422
                    );
                }
            }

            $pushover_enabled = !empty($_POST['pushover_enabled']);
            $pushover_token = trim($_POST['pushover_token'] ?? "");
            $pushover_user_key = trim($_POST['pushover_user_key'] ?? "");

            if ($pushover_enabled) {
                if (!preg_match("/^[A-Za-z0-9]{30}$/", $pushover_token)) {
                    error(
                        __("Error"),
                        __("Invalid Pushover token.", "notify"),
                        code:422
                    );
                }

                if (!preg_match("/^[A-Za-z0-9]{30}$/", $pushover_user_key)) {
                    error(
                        __("Error"),
                        __("Invalid Pushover user key.", "notify"),
                        code:422
                    );
                }
            }

            $hooks_selected = $_POST['hooks'] ?? array();
            $hooks_config = array();

            foreach (array_keys($this->hooks()) as $hook) {
                if (!empty($hooks_selected[$hook])) {
                    $hooks_config[$hook] = true;
                }
            }

            $config->set(
                "module_notify",
                array(
                    "hooks"             => $hooks_config,
                    "ntfy_enabled"      => $ntfy_enabled,
                    "ntfy_topic"        => $ntfy_topic,
                    "pushover_enabled"  => $pushover_enabled,
                    "pushover_token"    => $pushover_token,
                    "pushover_user_key" => $pushover_user_key,
                )
            );

            Flash::notice(
                __("Settings updated."),
                "notify_settings"
            );
        }

        public function add_model(
            Model $model
        ): void {
            foreach ($this->hooks() as $hook => $data) {
                if (get_class($model) === $data["class"]) {
                    $url = method_exists($model, "url") ?
                        unfix($model->url(), true) :
                        false ;

                    $this->send_message($data["message"], $url);
                }
            }
        }

        private function send_message(
            string $message,
            string|false $url = false
        ): void {
            $config = Config::current()->module_notify;

            if ($config["ntfy_enabled"])
                $this->send_ntfy($message, $url);

            if ($config["pushover_enabled"])
                $this->send_pushover($message, $url);
        }

        private function send_ntfy(
            string $message,
            string|false $url = false
        ): void {
            $site = Config::current();
            $config = $site->module_notify;

            if (empty($config["ntfy_topic"]))
                return;

            $req_headers = ($url === false) ?
                array() :
                array("X-Click: ".$url) ;

            $text = _f("[%s]: %s", array($site->name, $message), "notify");

            get_remote(
                self::NTFY_URL.$config["ntfy_topic"],
                post: true,
                data: urlencode($text),
                req_headers: $req_headers
            );
        }

        private function send_pushover(
            string $message,
            string|false $url = false
        ): void {
            $site = Config::current();
            $config = $site->module_notify;

            if (empty($config["pushover_token"]))
                return;

            if (empty($config["pushover_user_key"]))
                return;

            $data = array(
                "token" => $config["pushover_token"],
                "user" => $config["pushover_user_key"],
                "message" => _f("[%s]: %s", array($site->name, $message), "notify")
            );

            if ($url !== false)
                $data["url"] = $url;

            get_remote(
                self::PUSHOVER_URL,
                post: true,
                data: $data
            );
        }

        public function settings_nav(
            array $navs
        ): array {
            if (Visitor::current()->group->can("change_settings"))
                $navs["notify_settings"] = array(
                    "title" => __("Notifications", "notify")
                );

            return $navs;
        }

        private function want_message(
            string $hook
        ): bool {
            $config = Config::current()->module_notify;

            if (isset($config["hooks"][$hook]))
                return (bool) $config["hooks"][$hook];

            return false;
        }
    }
