<?php
    require_once "model".DIR."Like.php";

    class Likes extends Modules {
        static function __install() {
            $config = Config::current();

            Like::install();

            Group::add_permission("like_post", "Like Posts");
            Group::add_permission("unlike_post", "Unlike Posts");

            $config->set("module_like",
                         array("showOnFront" => true,
                               "likeWithText" => false,
                               "likeImage" => $config->chyrp_url."/modules/likes/images/pink.svg"));
        }

        static function __uninstall($confirm) {
            if ($confirm)
                Like::uninstall();

            Group::remove_permission("like_post");
            Group::remove_permission("unlike_post");
            Config::current()->remove("module_like");
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
                return $admin->display("pages".DIR."like_settings");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            fallback($_POST['likeImage'], $config->chyrp_url."/modules/likes/images/pink.svg");

            $config->set("module_like",
                         array("showOnFront" => isset($_POST['showOnFront']),
                               "likeWithText" => isset($_POST['likeWithText']),
                               "likeImage" => $_POST['likeImage']));

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

            $new = new Like($post->id);
            $new->like();

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

            $new = new Like($post->id);
            $new->unlike();

            Flash::notice(__("Post unliked.", "likes"), $post->url()."#likes_".$post->id);
        }

        public function stylesheets($styles) {
            $styles[] = Config::current()->chyrp_url."/modules/likes/style.css";
            return $styles;
        }

        public function javascript() {
            include MODULES_DIR.DIR."likes".DIR."javascript.php";
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

            $new = new Like($post->id);
            $new->like();
            $count = $new->fetchCount() - 1;

            if ($count <= 0)
                $text = __("You like this.", "likes");
            else
                $text = sprintf(_p("You and %d person like this.", "You and %d people like this.", $count, "likes"), $count);

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

            $new = new Like($post->id);
            $new->unlike();
            $count = $new->fetchCount();

            if ($count <= 0)
                $text = __("No likes yet.", "likes");
            else
                $text = sprintf(_p("%d person likes this.", "%d people like this.", $count, "likes"), $count);

            json_response($text, true);
        }

        public function delete_post($post) {
            SQL::current()->delete("likes", array("post_id" => $post->id));
        }

        public function delete_user($user) {
            SQL::current()->update("likes", array("user_id" => $user->id), array("user_id" => 0));
        }

        public function post($post) {
            $post->has_many[] = "likes";
            $post->get_likes = self::get_likes($post);
        }

        public function get_likes($post) {
            $config = Config::current();
            $route = Route::current();
            $visitor = Visitor::current();
            $module_like = $config->module_like;

            if ($module_like["showOnFront"] == false and $route->action == "index")
                return;

            $like = new Like($post->id, $visitor->id);
            $html = '<div class="likes" id="likes_'.$post->id.'">';

            if (!$like->resolve()) {
                if ($visitor->group->can("like_post")) {
                    $html.= "<a class=\"likes like\" href=\"".
                                $config->url."/?action=like&amp;post_id=".
                                $post->id."\" data-post_id=\"".
                                $post->id."\">".
                                "<img src=\"".$module_like["likeImage"]."\" alt='Likes icon'>";

                    if ($module_like["likeWithText"]) {
                        $html.= " <span class='like'>".__("Like!", "likes")."</span>";
                        $html.= " <span class='unlike'>".__("Unlike!", "likes")."</span>";
                    }

                    $html.= "</a>";
                }

                $html.= " <span class='like_text'>";

                $count = $like->fetchCount();

                if ($count <= 0)
                    $html.= __("No likes yet.", "likes");
                else
                    $html.= sprintf(_p("%d person likes this.", "%d people like this.", $count, "likes"), $count);

                $html.= "</span>";
            } else {
                if ($visitor->group->can("unlike_post")) {
                    $html.= "<a class=\"likes liked\" href=\"".
                                $config->url."/?action=unlike&amp;post_id=".
                                $post->id."\" data-post_id=\"".
                                $post->id."\">".
                                "<img src=\"".$module_like["likeImage"]."\" alt='Likes icon'>";

                    if ($module_like["likeWithText"]) {
                        $html.= " <span class='like'>".__("Like!", "likes")."</span>";
                        $html.= " <span class='unlike'>".__("Unlike!", "likes")."</span>";
                    }

                    $html.= "</a>";
                }

                $html.= " <span class='like_text'>";

                $count = $like->fetchCount() - 1;

                if ($count <= 0)
                    $html.= __("You like this.", "likes");
                else
                    $html.= sprintf(_p("You and %d person like this.", "You and %d people like this.", $count, "likes"), $count);

                $html.= "</span>";
            }

            $html.= "</div>";
            return $post->get_likes = $html;
        }

        public function get_like_images() {
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
            $like = new Like(array("post_id" => $post->id));
            echo '<td class="post_likes value">'.$like->fetchCount().'</td>';
        }

        public function import_chyrp_post($entry, $post) {
            $chyrp = $entry->children("http://chyrp.net/export/1.0/");

            if (!isset($chyrp->like))
                return;

            foreach ($chyrp->like as $like) {
                $sql = SQL::current();

                $timestamp = $like->children("http://www.w3.org/2005/Atom")->published;
                $session_hash = $like->children("http://chyrp.net/export/1.0/")->hash;
                $login = $like->children("http://chyrp.net/export/1.0/")->login;

                $user = new User(array("login" => (string) $login));

                Like::import($post->id,
                             ((!$user->no_results) ? $user->id : 0),
                             oneof($timestamp, datetime()),
                             $session_hash);
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
            # Remember likes in the visitor's session for attribution.
            $_SESSION["likes"] = array();
        }
    }
