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

        public function pingback($post, $to, $from, $title, $excerpt) {
            $count = SQL::current()->count("pingbacks",
                                           array("post_id" => $post->id,
                                                 "source" => $from));

            if (!empty($count))
                return new IXR_Error(48, __("A ping from your URL is already registered.", "pingable"));

            Pingback::add($post->id,
                          $from,
                          $title);

            return __("Pingback registered!", "pingable");
        }

        public function admin_delete_pingback($admin) {
            if (!Visitor::current()->group->can("delete_pingbacks"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete pingbacks.", "pingable"));

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a pingback.", "pingable"), null, 400);

            $pingback = new Pingback($_GET['id']);

            if ($pingback->no_results)
                Flash::warning(__("Pingback not found.", "pingable"), "/admin/?action=manage_pingbacks");

            $admin->display("delete_pingback", array("pingback" => $pingback));
        }

        public function admin_destroy_pingback() {
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

        public function admin_manage_pingbacks($admin) {
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

        public function manage_nav($navs) {
            if (Visitor::current()->group->can("delete_pingbacks"))
                $navs["manage_pingbacks"] = array("title" => __("Pingbacks", "pingable"),
                                                  "selected" => array("delete_pingback"));

            return $navs;
        }

        public function determine_action($action) {
            if ($action == "manage" and Visitor::current()->group->can("delete_pingbacks"))
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
            if (isset($this->pingback_counts))
                return oneof(@$this->pingback_counts[$post->id], 0);

            $counts = SQL::current()->select("pingbacks",
                                             array("COUNT(post_id) AS total", "post_id as post_id"));

            foreach ($counts->fetchAll() as $count)
                $this->pingback_counts[$count["post_id"]] = (int) $count["total"];

            return oneof(@$this->pingback_counts[$post->id], 0);
        }

        public function import_chyrp_post($entry, $post) {
            $chyrp = $entry->children("http://chyrp.net/export/1.0/");

            if (!isset($chyrp->pingback))
                return;

            foreach ($chyrp->pingback as $pingback) {
                $title = $pingback->children("http://www.w3.org/2005/Atom")->title;
                $source = $pingback->children("http://www.w3.org/2005/Atom")->link["href"];
                $created_at = $pingback->children("http://www.w3.org/2005/Atom")->published;

                SQL::current()->insert("pingbacks",
                                 array("post_id"    => $post->id,
                                       "source"     => $source,
                                       "title"      => $title,
                                       "created_at" => $created_at));
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
            $triggers = array("pingback", "delete_pingback");
            return array_merge($regenerate_posts, $triggers);
        }
    }
