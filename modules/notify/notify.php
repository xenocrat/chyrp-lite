<?php

class Notify extends Modules {
    private function hooks(
    ): array {
        static $hooks;

        if (!isset($hooks)) {
            $hooks = array(
                'add_post'     => array(
                    'class' => Post::class,
                    'label' => __('New Post', 'notify'),
                    'message' => __('Post created.', 'notify'),
                ),
                'add_page'     => array(
                    'class' => Page::class,
                    'label' => __('New Page', 'notify'),
                    'message' => __('Page created.', 'notify'),
                ),
                'add_user'     => array(
                    'class' => User::class,
                    'label' => __('New User', 'notify'),
                    'message' => __('User created.', 'notify'),
                ),
                'add_comment'  => array(
                    'class' => Comment::class,
                    'label' => __('Comment Added', 'notify'),
                    'message' => __('Someone commented on a post.', 'notify'),
                ),
                'add_like'     => array(
                    'class' => Like::class,
                    'label' => __('Post Liked', 'notify'),
                    'message' => __('Someone liked a post.', 'notify'),
                ),
                'add_pingback' => array(
                    'class' => Pingback::class,
                    'label' => __('Webmention Received', 'notify'),
                    'message' => __('Someone sent a webmention.', 'notify'),
                ),
            );

            Trigger::current()->filter($hooks, "notify_define_hooks");
        }

        return $hooks;
    }

    public static function __install(
    ): void
    {
        $config = Config::current();

        $config->set('module_notify', array(
            'ntfy_enabled' => false,
            'ntfy_host'    => 'https://ntfy.sh',
            'ntfy_topic'   => '',
            'hooks' => [],
        ));
    }

    public static function __uninstall(
    ): void
    {
        $config = Config::current();

        $config->remove('module_notify');
    }

    public function __init(
    ): void
    {
        foreach (array_keys($this->hooks()) as $hook) {
            if ($this->want_message($hook)) {
                $this->addAlias($hook, "add_model", 99);
            }
        }
    }

    public function admin_notify_settings(
        AdminController $admin
    ): void
    {
        $config = Config::current();

        if (!Visitor::current()->group->can("change_settings"))
            show_403(
                __("Access Denied"),
                __("You do not have sufficient privileges to change settings.")
            );

        if (empty($_POST)) {
            $admin->display(
                "pages".DIR."notify_settings",
                array('hook_list' => $this->hooks()),
            );

            return;
        }

        if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
            show_403(
                __("Access Denied"),
                __("Invalid authentication token.")
            );

        $ntfy_enabled = isset($_POST['ntfy_enabled']);
        $ntfy_host_selected = trim($_POST['ntfy_host'] ?? '');

        if ($ntfy_enabled && !is_url($ntfy_host_selected)) {
            error(
                __("Error"),
                __("Invalid ntfy host URL.", "notify"),
                code:422
            );
        }

        $ntfy_host_selected = add_scheme($ntfy_host_selected);
        $ntfy_topic_selected = trim($_POST['ntfy_topic'] ?? '');

        if ($ntfy_enabled && !preg_match('/^[\-_a-zA-Z0-9]{4,64}$/', $ntfy_topic_selected)) {
            error(
                __("Error"),
                __("Invalid ntfy topic.", "notify"),
                code:422
            );
        }

        $hooks_selected = $_POST['hooks'] ?? array();
        $hooks_config = array();

        foreach (array_keys($this->hooks()) as $hook) {
            if (isset($hooks_selected[$hook])) {
                $hooks_config[$hook] = true;
            }
        }

        $config->set('module_notify', array(
            'ntfy_enabled' => $ntfy_enabled,
            'ntfy_host'    => $ntfy_host_selected,
            'ntfy_topic'   => $ntfy_topic_selected,
            'hooks' => $hooks_config,
        ));

        Flash::notice(
            __("Settings updated."),
            "notify_settings"
        );
    }

    public function add_model(
        Model $model
    ): void
    {
        foreach ($this->hooks() as $hook => $data) {
            if (get_class($model) === $data['class']) {
                $url = false;
                if (method_exists($model, 'url')) {
                    $url = htmlspecialchars_decode($model->url());
                }
                $this->send_message($data['message'], $url);
            }
        }
    }

    private function send_message(
        string $message,
        string|false $url = false
    ): void
    {
        $site = Config::current();
        $config = Config::current()->module_notify;

        if (empty($config['ntfy_topic']) || empty($config['ntfy_host'])) {
            return;
        }

        $req_headers = [];
        if ($url !== false) {
            $req_headers[] = "X-Click: $url";
        }

        $ntfy_url = sprintf("%s/%s", $config['ntfy_host'], $config['ntfy_topic']);
        $text = sprintf("[%s]: %s", $site->name, $message);
        get_remote($ntfy_url, post: true, data: $text, req_headers: $req_headers);
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

    private function want_message(string $hook): bool
    {
        $config = Config::current()->module_notify;

        if (empty($config['ntfy_host']) || empty($config['ntfy_topic'])) {
            return false;
        }

        if (isset($config['hooks'][$hook])) {
            return (bool) $config['hooks'][$hook];
        }

        return false;
    }
}
