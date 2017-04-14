<?php
    require_once "model".DIR."Like.php";

    class Likes extends Modules {
        public function __init() {
            fallback($_SESSION["likes"], array());
        }

        static function __install() {
            $config = Config::current();

            Like::install();

            Group::add_permission("like_post", "Like Posts");
            Group::add_permission("unlike_post", "Unlike Posts");

            $config->set("module_likes",
                         array("show_on_index" => true,
                               "like_with_text" => false,
                               "like_image" => $config->chyrp_url."/modules/likes/images/pink.svg"));
        }

        static function __uninstall($confirm) {
            if ($confirm)
                Like::uninstall();

            Group::remove_permission("like_post");
            Group::remove_permission("unlike_post");
            Config::current()->remove("module_likes");
        }

        public function list_permissions($names = array()) {
            $names["like_post"]   = __("Like Posts", "likes");
            $names["unlike_post"] = __("Unlike Posts", "likes");
            return $names;
        }

        public function admin_like_settings($admin) {
            $config = Config::current();

            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (empty($_POST))
                return $admin->display("pages".DIR."like_settings",
                                       array("like_images" => $this->like_images()));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
                show_403(__("Access Denied"), __("Invalid security key."));

            fallback($_POST['like_image'], $config->chyrp_url."/modules/likes/images/pink.svg");

            $config->set("module_likes",
                         array("show_on_index" => isset($_POST['show_on_index']),
                               "like_with_text" => isset($_POST['like_with_text']),
                               "like_image" => $_POST['like_image']));

            Flash::notice(__("Settings updated."), "like_settings");
        }

        public function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["like_settings"] = array("title" => __("Likes", "likes"));

            return $navs;
        }

        public function route_like() {
            if (empty($_GET['post_id']) or !is_numeric($_GET['post_id']))
                error(__("Error"), __("An ID is required to like a post.", "likes"), null, 400);

            if (!Visitor::current()->group->can("like_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to like posts.", "likes"));

            $post = new Post($_GET['post_id']);

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            Like::create($post->id);
            Flash::notice(__("Post liked.", "likes"), $post->url()."#likes_".$post->id);
        }

        public function route_unlike() {
            if (empty($_GET['post_id']) or !is_numeric($_GET['post_id']))
                error(__("Error"), __("An ID is required to unlike a post.", "likes"), null, 400);

            if (!Visitor::current()->group->can("unlike_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to unlike posts.", "likes"));

            $post = new Post($_GET['post_id']);

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            Like::remove($post->id);
            Flash::notice(__("Post unliked.", "likes"), $post->url()."#likes_".$post->id);
        }

        public function ajax_like() {
            if (empty($_POST["post_id"]) or !is_numeric($_POST['post_id']))
                error(__("Error"), __("An ID is required to like a post.", "likes"), null, 400);

            # JavaScript can't know if this is allowed, so don't throw an error here.
            if (!Visitor::current()->group->can("like_post"))
                json_response(__("You do not have sufficient privileges to like posts.", "likes"), false);

            $post = new Post($_POST['post_id']);

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            Like::create($post->id);

            $count = $post->like_count - 1;

            $text = ($count <= 0) ?
                __("You like this.", "likes") :
                sprintf(_p("You and %d person like this.", "You and %d people like this.", $count, "likes"), $count) ;

            json_response($text, true);
        }

        public function ajax_unlike() {
            if (empty($_POST["post_id"]) or !is_numeric($_POST['post_id']))
                error(__("Error"), __("An ID is required to unlike a post.", "likes"), null, 400);

            # JavaScript can't know if this is allowed, so don't throw an error here.
            if (!Visitor::current()->group->can("unlike_post"))
                json_response(__("You do not have sufficient privileges to unlike posts.", "likes"), false);

            $post = new Post($_POST['post_id']);

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            Like::remove($post->id);

            $count = $post->like_count;

            $text = ($count <= 0) ?
                __("No likes yet.", "likes") :
                sprintf(_p("%d person likes this.", "%d people like this.", $count, "likes"), $count) ;

            json_response($text, true);
        }

        public function post($post) {
            $post->has_many[] = "likes";
        }

        public function delete_post($post) {
            SQL::current()->delete("likes", array("post_id" => $post->id));
        }

        public function delete_user($user) {
            SQL::current()->update("likes", array("user_id" => $user->id), array("user_id" => 0));
        }

        public function post_like_count_attr($attr, $post) {
            if (isset($this->post_like_counts))
                return fallback($this->post_like_counts[$post->id], 0);

            $counts = SQL::current()->select("likes",
                                             "COUNT(post_id) AS total, post_id as post_id",
                                             null,
                                             null,
                                             array(),
                                             null,
                                             null,
                                             "post_id")->fetchAll();

            foreach ($counts as $count)
                $this->post_like_counts[$count["post_id"]] = (int) $count["total"];

            return fallback($this->post_like_counts[$post->id], 0);
        }

        public function user_like_count_attr($attr, $user) {
            if (isset($this->user_like_counts))
                return fallback($this->user_like_counts[$user->id], 0);

            $counts = SQL::current()->select("likes",
                                             "COUNT(user_id) AS total, user_id as user_id",
                                             null,
                                             null,
                                             array(),
                                             null,
                                             null,
                                             "user_id")->fetchAll();

            foreach ($counts as $count)
                $this->user_like_counts[$count["user_id"]] = (int) $count["total"];

            return fallback($this->user_like_counts[$user->id], 0);
        }

        public function visitor_like_count_attr($attr, $visitor) {
            return ($visitor->id == 0) ?
                count(array_diff($_SESSION["likes"], array(null))) : self::user_like_count_attr($attr, $visitor) ;
        }

        public function post_like_link_attr($attr, $post) {
            $config = Config::current();
            $route = Route::current();
            $visitor = Visitor::current();
            $settings = $config->module_likes;

            if (($settings["show_on_index"] == false and $route->action == "index") or $post->no_results)
                return;

            $html = '<div class="likes" id="likes_'.$post->id.'">';

            if (!Like::discover($post->id)) {
                if ($visitor->group->can("like_post")) {
                    $html.= "<a class=\"likes like\" href=\"".
                                $config->url."/?action=like&amp;post_id=".
                                $post->id."\" data-post_id=\"".
                                $post->id."\">".
                                "<img src=\"".$settings["like_image"]."\" alt='Likes icon'>";

                    if ($settings["like_with_text"]) {
                        $html.= " <span class='like'>".__("Like!", "likes")."</span>";
                        $html.= " <span class='unlike'>".__("Unlike!", "likes")."</span>";
                    }

                    $html.= "</a>";
                }

                $html.= " <span class='like_text'>";

                $count = $post->like_count;

                $html.= ($count <= 0) ?
                    __("No likes yet.", "likes") :
                    sprintf(_p("%d person likes this.", "%d people like this.", $count, "likes"), $count) ;

                $html.= "</span>";
            } else {
                if ($visitor->group->can("unlike_post")) {
                    $html.= "<a class=\"likes liked\" href=\"".
                                $config->url."/?action=unlike&amp;post_id=".
                                $post->id."\" data-post_id=\"".
                                $post->id."\">".
                                "<img src=\"".$settings["like_image"]."\" alt='Likes icon'>";

                    if ($settings["like_with_text"]) {
                        $html.= " <span class='like'>".__("Like!", "likes")."</span>";
                        $html.= " <span class='unlike'>".__("Unlike!", "likes")."</span>";
                    }

                    $html.= "</a>";
                }

                $html.= " <span class='like_text'>";

                $count = $post->like_count - 1;

                $html.= ($count <= 0) ?
                    __("You like this.", "likes") :
                    sprintf(_p("You and %d person like this.", "You and %d people like this.", $count, "likes"), $count) ;

                $html.= "</span>";
            }

            $html.= "</div>";
            return $html;
        }

        private function like_images() {
            $images = array();
            $filepaths = glob(MODULES_DIR.DIR."likes".DIR."images".DIR."*.{jpg,jpeg,png,gif,svg}", GLOB_BRACE);

            foreach ($filepaths as $filepath) {
                $filename = basename($filepath);
                $images[$filename] = Config::current()->chyrp_url."/modules/likes/images/".urlencode($filename);
            }

            return $images;
        }

        public function manage_posts_column_header() {
            echo '<th class="post_likes value">'.__("Likes", "tags").'</th>';
        }

        public function manage_posts_column($post) {
            echo '<td class="post_likes value">'.$post->like_count.'</td>';
        }

        public function import_chyrp_post($entry, $post) {
            $chyrp = $entry->children("http://chyrp.net/export/1.0/");

            if (!isset($chyrp->like))
                return;

            foreach ($chyrp->like as $like) {
                $timestamp = $like->children("http://www.w3.org/2005/Atom")->published;
                $session_hash = $like->children("http://chyrp.net/export/1.0/")->hash;
                $login = $like->children("http://chyrp.net/export/1.0/")->login;

                $user = new User(array("login" => (string) $login));

                Like::add($post->id,
                          ((!$user->no_results) ? $user->id : 0),
                          (string) oneof($timestamp, datetime()),
                          (string) oneof($session_hash, md5("import_chyrp_post")));
            }
        }

        public function posts_export($atom, $post) {
            $likes = SQL::current()->select("likes",
                                             "*",
                                             array("post_id" => $post->id))->fetchAll();

            foreach ($likes as $like) {
                $user = new User($like["user_id"]);
                $login = (!$user->no_results) ? $user->login : "" ;

                $atom.= "        <chyrp:like>\r";
                $atom.= '            <chyrp:login>'.$login.'</chyrp:login>'."\r";
                $atom.= '            <published>'.$like["timestamp"].'</published>'."\r";
                $atom.= '            <chyrp:hash>'.$like["session_hash"].'</chyrp:hash>'."\r";
                $atom.= "        </chyrp:like>\r";
            }

            return $atom;
        }

        public function cacher_regenerate_triggers($regenerate) {
            $triggers = array("like_post", "unlike_post");
            return array_merge($regenerate, $triggers);
        }

        public function user_logged_in($user) {
            # Erase the visitor's session values to avoid misattribution.
            $_SESSION["likes"] = array();
        }

        public function stylesheets($styles) {
            $styles[] = Config::current()->chyrp_url."/modules/likes/style.css";
            return $styles;
        }

        public function javascript() {
            include MODULES_DIR.DIR."likes".DIR."javascript.php";
        }
    }
