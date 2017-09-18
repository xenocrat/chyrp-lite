<?php
    class Cacher extends Modules {
        public function __init() {
            $this->exclude = Config::current()->module_cacher["cache_exclude"];
            $this->url     = rawurldecode(self_url());
            $this->cachers = array(new PageCacher($this->url),
                                   new FeedCacher($this->url));

            $this->prepare_cache_regenerators();
        }

        static function __install() {
            Config::current()->set("module_cacher",
                                   array("cache_expire" => 3600,
                                         "cache_exclude" => array()));
        }

        static function __uninstall() {
            Config::current()->remove("module_cacher");
        }

        public function route_init($route) {
            if (!$this->cancelled and !in_array($this->url, $this->exclude))
                foreach ($this->cachers as $cacher)
                    $cacher->get($route);
        }

        public function end($route) {
            if (!$this->cancelled and !in_array($this->url, $this->exclude))
                foreach ($this->cachers as $cacher)
                    $cacher->set($route);
        }

        public function prepare_cache_regenerators() {
            $trigger = Trigger::current();

            $regenerate = array(
                "add_post",
                "add_page",
                "update_post",
                "update_page",
                "delete_post",
                "delete_page",
                "publish_post",
                "change_setting"
            );

            $trigger->filter($regenerate, "cacher_regenerate_triggers");

            foreach ($regenerate as $action)
                $this->addAlias($action, "regenerate");

            $regenerate_users = array(
                "update_user",
                "preview_theme_started",
                "preview_theme_stopped"
            );

            $trigger->filter($regenerate_users, "cacher_regenerate_users_triggers");

            foreach ($regenerate_users as $action)
                $this->addAlias($action, "regenerate_users");

            $regenerate_posts = array();

            $trigger->filter($regenerate_posts, "cacher_regenerate_posts_triggers");

            foreach ($regenerate_posts as $action)
                $this->addAlias($action, "regenerate_posts");

            $exclude_urls = array("before_generate_captcha");

            $trigger->filter($exclude_urls, "cacher_exclude_urls_triggers");

            foreach ($exclude_urls as $action)
                $this->addAlias($action, "exclude_urls");
        }

        public function regenerate() {
            foreach ($this->cachers as $cacher)
                $cacher->regenerate();
        }

        public function regenerate_users($user = null) {
            $id = (($user instanceof User) and !$user->no_results) ? $user->id : Visitor::current()->id ;

            foreach ($this->cachers as $cacher)
                $cacher->regenerate_user($id);
        }

        public function regenerate_posts($model) {
            $post = ($model instanceof Post) ? $model : new Post($model->post_id, array("skip_where" => true)) ;

            if ($post->no_results)
                return;

            $url = rawurldecode($post->url());

            foreach ($this->cachers as $cacher)
                $cacher->regenerate_url($url);
        }

        public function exclude_urls($url = null) {
            $this->exclude[] = rawurldecode(is_url($url) ? $url : self_url());
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
                return $admin->display("pages".DIR."cache_settings");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (isset($_POST['clear_cache']) and $_POST['clear_cache'] == "indubitably")
                $this->admin_clear_cache();

            fallback($_POST['cache_expire'], 3600);
            fallback($_POST['cache_exclude'], array());

            Config::current()->set("module_cacher",
                                   array("cache_expire" => (int) $_POST['cache_expire'],
                                         "cache_exclude" => array_filter($_POST['cache_exclude'])));

            Flash::notice(__("Settings updated."), "cache_settings");
        }

        public function admin_clear_cache() {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            $this->regenerate();

            Flash::notice(__("Cache cleared.", "cacher"), "cache_settings");
        }
    }
