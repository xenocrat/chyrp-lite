<?php
    require_once "model".DIR."Pingback.php";

    class Pingable extends Modules {
        static function __install() {
            Pingback::install();

            Group::add_permission("delete_pingback", "Delete Pingbacks");
        }

        static function __uninstall($confirm) {
            if ($confirm)
                Pingback::uninstall();

            Group::remove_permission("delete_pingback");
        }

        public function list_permissions($names = array()) {
            $names["delete_pingback"] = __("Delete Pingbacks", "pingable");
            return $names;
        }

        public function pingback($post, $to, $from, $title, $excerpt) {
            $count = SQL::current()->count("pingbacks",
                                           array("post_id" => $post->id,
                                                 "source" => $from));

            if (!empty($count))
                return new IXR_Error(48, __("A ping from your URL is already registered.", "pingable"));

            if (strlen($from) > 2048)
                return new IXR_Error(0, __("Your URL is too long to be stored in our database.", "pingable"));

            Pingback::add($post->id, $from, $title);

            return __("Pingback registered!", "pingable");
        }

        public function admin_delete_pingback($admin) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a pingback.", "pingable"), null, 400);

            $pingback = new Pingback($_GET['id']);

            if ($pingback->no_results)
                Flash::warning(__("Pingback not found.", "pingable"), "manage_pingbacks");

            if (!$pingback->deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this pingback.", "pingable"));

            $admin->display("pages".DIR."delete_pingback", array("pingback" => $pingback));
        }

        public function admin_destroy_pingback() {
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a pingback.", "pingable"), null, 400);

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_pingbacks");

            $pingback = new Pingback($_POST['id']);

            if ($pingback->no_results)
                show_404(__("Not Found"), __("Pingback not found.", "pingable"));

            if (!$pingback->deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this pingback.", "pingable"));

            Pingback::delete($pingback->id);

            Flash::notice(__("Pingback deleted.", "pingable"), "manage_pingbacks");
        }

        public function admin_manage_pingbacks($admin) {
            if (!Visitor::current()->group->can("delete_pingback"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage pingbacks.", "pingable"));

            fallback($_GET['query'], "");
            list($where, $params) = keywords($_GET['query'], "title LIKE :query", "pingbacks");

            $admin->display("pages".DIR."manage_pingbacks",
                            array("pingbacks" => new Paginator(Pingback::find(array("placeholders" => true,
                                                                                    "where" => $where,
                                                                                    "params" => $params)),
                                                               Config::current()->admin_per_page)));
        }

        public function manage_nav($navs) {
            if (Visitor::current()->group->can("delete_pingback"))
                $navs["manage_pingbacks"] = array("title" => __("Pingbacks", "pingable"),
                                                  "selected" => array("delete_pingback"));

            return $navs;
        }

        public function admin_determine_action($action) {
            if ($action == "manage" and Visitor::current()->group->can("delete_pingback"))
                return "manage_pingbacks";
        }

        public function manage_posts_column_header() {
            echo '<th class="post_pingbacks value">'.__("Pingbacks", "pingable").'</th>';
        }

        public function manage_posts_column($post) {
            echo '<td class="post_pingbacks value"><a href="'.$post->url().'#pingbacks">'.$post->pingback_count.'</a></td>';
        }

        public function post($post) {
            $post->has_many[] = "pingbacks";
        }

        static function delete_post($post) {
            SQL::current()->delete("pingbacks", array("post_id" => $post->id));
        }

        public function post_pingback_count_attr($attr, $post) {
            if (isset($this->post_pingback_counts))
                return fallback($this->post_pingback_counts[$post->id], 0);

            $counts = SQL::current()->select("pingbacks",
                                             "COUNT(post_id) AS total, post_id as post_id",
                                             null,
                                             null,
                                             array(),
                                             null,
                                             null,
                                             "post_id")->fetchAll();

            foreach ($counts as $count)
                $this->post_pingback_counts[$count["post_id"]] = (int) $count["total"];

            return fallback($this->post_pingback_counts[$post->id], 0);
        }

        public function import_chyrp_post($entry, $post) {
            $chyrp = $entry->children("http://chyrp.net/export/1.0/");

            if (!isset($chyrp->pingback))
                return;

            foreach ($chyrp->pingback as $pingback) {
                $title = $pingback->children("http://www.w3.org/2005/Atom")->title;
                $source = $pingback->children("http://www.w3.org/2005/Atom")->link["href"];
                $created_at = $pingback->children("http://www.w3.org/2005/Atom")->published;

                Pingback::add($post->id,
                              (string) $source,
                              (string) $title,
                              datetime((string) $created_at));
            }
        }

        public function posts_export($atom, $post) {
            $pingbacks = SQL::current()->select("pingbacks",
                                                "*",
                                                array("post_id" => $post->id))->fetchAll();

            foreach ($pingbacks as $pingback) {
                $atom.= "        <chyrp:pingback>\r";
                $atom.= '            <title type="html">'.$pingback["title"].'</title>'."\r";
                $atom.= '            <link href="'.fix($pingback["source"], true).'" />'."\r";
                $atom.= '            <published>'.$pingback["created_at"].'</published>'."\r";
                $atom.= "        </chyrp:pingback>\r";
            }

            return $atom;
        }

        public function cacher_regenerate_posts_triggers($regenerate_posts) {
            $triggers = array("add_pingback", "delete_pingback");
            return array_merge($regenerate_posts, $triggers);
        }
    }
