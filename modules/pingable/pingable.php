<?php
    require_once "model".DIR."Pingback.php";

    class Pingable extends Modules {
        static function __install() {
            Pingback::install();

            Group::add_permission("delete_pingbacks", "Delete Pingbacks");
        }

        static function __uninstall($confirm) {
            if ($confirm)
                Pingback::uninstall();

            Group::remove_permission("delete_pingbacks");
        }

        static function admin_delete_pingback($admin) {
            if (!Visitor::current()->group->can("delete_pingbacks"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete pingbacks.", "pingable"));

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a pingback.", "pingable"), null, 400);

            $pingback = new Pingback($_GET['id']);

            if ($pingback->no_results)
                Flash::warning(__("Pingback not found.", "pingable"), "/admin/?action=manage_pingbacks");

            $admin->display("delete_pingback", array("pingback" => $pingback));
        }

        static function admin_destroy_pingback() {
            if (!Visitor::current()->group->can("delete_pingbacks"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete pingbacks.", "pingable"));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a pingback.", "pingable"), null, 400);

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("/admin/?action=manage_pingbacks");

            $pingback = new Pingback($_POST['id']);

            if ($pingback->no_results)
                show_404(__("Not Found"), __("Pingback not found.", "pingable"));

            Pingback::delete($pingback->id);

            Flash::notice(__("Pingback deleted.", "pingable"), "/admin/?action=manage_pingbacks");
        }

        public function pingback($post, $to, $from, $title, $excerpt) {
            $sql = SQL::current();
            $count = $sql->count("pingbacks",
                                 array("post_id" => $post->id,
                                       "source" => $from));

            if ($count)
                return new IXR_Error(48, __("A ping from your URL is already registered.", "pingable"));

            Pingback::add($post->id,
                          $from,
                          $title);

            return __("Pingback registered!", "pingable");
        }

        static function delete_post($post) {
            SQL::current()->delete("pingbacks", array("post_id" => $post->id));
        }

        static function manage_nav($navs) {
            if (!Visitor::current()->group->can("delete_pingbacks"))
                return $navs;

            $navs["manage_pingbacks"] = array("title" => __("Pingbacks", "pingable"),
                                              "selected" => array("delete_pingback"));

            return $navs;
        }

        static function manage_nav_pages($pages) {
            array_push($pages, "manage_pingbacks", "delete_pingback");
            return $pages;
        }

        static function admin_manage_pingbacks($admin) {
            if (!Visitor::current()->group->can("delete_pingbacks"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage pingbacks.", "pingable"));

            fallback($_GET['query'], "");
            list($where, $params) = keywords($_GET['query'], "title LIKE :query", "pingbacks");

            $admin->display("manage_pingbacks",
                            array("pingbacks" => new Paginator(Pingback::find(array("placeholders" => true,
                                                                                    "where" => $where,
                                                                                    "params" => $params)),
                                                               Config::current()->admin_per_page)));
        }

        static function manage_posts_column_header() {
            echo '<th class="post_pingbacks">'.__("Pingbacks", "pingable").'</th>';
        }

        static function manage_posts_column($post) {
            echo '<td class="post_pingbacks"><a href="'.$post->url().'#pingbacks">'.$post->pingback_count.'</a></td>';
        }

        public function post($post) {
            $post->has_many[] = "pingbacks";
        }

        public function post_pingback_count_attr($attr, $post) {
            if (isset($this->pingback_counts))
                return oneof(@$this->pingback_counts[$post->id], 0);

            $counts = SQL::current()->select("pingbacks",
                                             array("COUNT(post_id) AS total", "post_id as post_id"));

            foreach ($counts->fetchAll() as $count)
                $this->pingback_counts[$count["post_id"]] = (int) $count["total"];

            return oneof(@$this->pingback_counts[$post->id], 0);
        }

        public function manage_nav_show($possibilities) {
            $possibilities[] = (Visitor::current()->group->can("delete_pingbacks"));
            return $possibilities;
        }

        public function determine_action($action) {
            if ($action != "manage")
                return;

            if (Visitor::current()->group->can("delete_pingbacks"))
                return "manage_pingbacks";
        }

        static function cacher_regenerate_posts_triggers($regenerate_posts) {
            $triggers = array("pingback", "delete_pingback");
            return array_merge($regenerate_posts, $triggers);
        }
    }
