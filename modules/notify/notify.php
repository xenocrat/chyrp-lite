<?php

class Notify extends Modules {
    public static function __install(
    ): void
    {
        $config = Config::current();

        $config->set('module_notify', array(
            'ntfy_host'  => 'https://ntfy.sh',
            'ntfy_topic' => '',
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
        $config = Config::current();

        $site = $config->name;
        $notify = $config->module_notify;

        if (empty($notify['ntfy_topic']) || empty($notify['ntfy_host'])) {
            return;
        }

        $url = $notify['ntfy_host'] . '/' . $notify['ntfy_topic'];

        $message = "[{$site}]: New Comment";
        get_remote($url, post: true, data: $message);
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
}