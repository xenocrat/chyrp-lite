<?php

class Notify extends Modules {
    public static function __install(
    ): void
    {
        $config = Config::current();

        $config->set('module_notify', array(
            'ntfy_host'  => 'https://ntfy.sh',
            'ntfy_topic' => '',
            'hooks' => [],
        ));
    }

    public static function __uninstall(
    ): void
    {
        $config = Config::current();

        $config->remove('module_notify');
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
                $config->module_notify
            );

            return;
        }

        if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
            show_403(
                __("Access Denied"),
                __("Invalid authentication token.")
            );


        $config->set('module_notify', array(
            'ntfy_host'  => $_POST['ntfy_host'],
            'ntfy_topic' => $_POST['ntfy_topic'],
        ));

        Flash::notice(
            __("Settings updated."),
            "notify_settings"
        );
    }

    public function add_comment(
        Comment $comment
    ): void
    {
        if ($this->want_message("add_comment")) {
            $this->enqueue_message(__("New Comment", "notify"));
        }
    }

    public function add_pingback(
        Pingback $pingback
    ): void
    {
        if ($this->want_message("add_pingback")) {
            $this->enqueue_message(__("New Pingback", "notify"));
        }
    }

    public function add_user(
        User $user
    ): void
    {
        if ($this->want_message("add_user")) {
            $this->enqueue_message(__("New User registered", "notify"));
        }
    }

    public function end(): void
    {
        if (empty($this->messages)) {
            return;
        }

        $site = Config::current();
        $config = Config::current()->module_notify;

        if (empty($config['ntfy_topic']) || empty($config['ntfy_host'])) {
            return;
        }

        $message = implode("\n", $this->messages);

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

    private function enqueue_message(string $message): void
    {
        $this->messages[] = $message;
    }

    private array $messages = [];
}