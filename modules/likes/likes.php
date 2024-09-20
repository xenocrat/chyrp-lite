<?php
    require_once "model".DIR."Like.php";

    class Likes extends Modules {
        # Array: $caches
        # Query caches for methods.
        private $caches = array();

        public static function __install(): void {
            $config = Config::current();

            Like::install();

            Group::add_permission("like_post", "Like Posts");
            Group::add_permission("unlike_post", "Unlike Posts");

            $config->set(
                "module_likes",
                array(
                    "show_on_index" => true,
                    "like_with_text" => false,
                    "like_image" => "pink.svg"
                )
            );
        }

        public static function __uninstall(
            $confirm
        ): void {
            if ($confirm)
                Like::uninstall();

            Group::remove_permission("like_post");
            Group::remove_permission("unlike_post");
            Config::current()->remove("module_likes");
        }

        public function user_logged_in(
            $user
        ): void {
            $_SESSION['likes'] = array();
        }

        public function user(
            $user
        ): void {
            $user->has_many[] = "likes";
        }

        public function post(
            $post
        ): void {
            $post->has_many[] = "likes";
        }

        public function list_permissions(
            $names = array()
        ): array {
            $names["like_post"]   = __("Like Posts", "likes");
            $names["unlike_post"] = __("Unlike Posts", "likes");
            return $names;
        }

        public function admin_like_settings(
            $admin
        ): void {
            $config = Config::current();

            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            if (empty($_POST)) {
                $admin->display(
                    "pages".DIR."like_settings",
                    array("like_images" => $this->list_images())
                );

                return;
            }

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            fallback($_POST['like_image'], "pink.svg");

            $config->set(
                "module_likes",
                array(
                    "show_on_index" => isset($_POST['show_on_index']),
                    "like_with_text" => isset($_POST['like_with_text']),
                    "like_image" => $_POST['like_image']
                )
            );

            Flash::notice(
                __("Settings updated."),
                "like_settings"
            );
        }

        public function settings_nav(
            $navs
        ): array {
            if (Visitor::current()->group->can("change_settings"))
                $navs["like_settings"] = array(
                    "title" => __("Likes", "likes")
                );

            return $navs;
        }

        public function main_most_likes(
            $main
        ): void {
            $posts = Post::find(array("placeholders" => true));

            usort($posts[0], function ($a, $b) {
                $count_a = $this->get_post_like_count($a["id"]);
                $count_b = $this->get_post_like_count($b["id"]);

                if ($count_a == $count_b)
                    return 0;

                return ($count_a > $count_b) ? -1 : 1 ;
            });

            $main->display(
                array("pages".DIR."most_likes", "pages".DIR."index"),
                array("posts" => new Paginator($posts, $main->post_limit)),
                __("Most liked posts", "likes")
            );
        }

        public function main_like(): never {
            if (empty($_GET['post_id']) or !is_numeric($_GET['post_id']))
                error(
                    __("Error"),
                    __("An ID is required to like a post.", "likes"),
                    code:400
                );

            if (BOT_UA or !Visitor::current()->group->can("like_post"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to like posts.", "likes")
                );

            $post = new Post($_GET['post_id']);

            if ($post->no_results)
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            Like::create($post->id);
            Flash::notice(
                __("Post liked.", "likes"),
                $post->url()."#likes_".$post->id
            );
        }

        public function main_unlike(): never {
            if (empty($_GET['post_id']) or !is_numeric($_GET['post_id']))
                error(
                    __("Error"),
                    __("An ID is required to unlike a post.", "likes"),
                    code:400
                );

            if (BOT_UA or !Visitor::current()->group->can("unlike_post"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to unlike posts.", "likes")
                );

            $post = new Post($_GET['post_id']);

            if ($post->no_results)
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            Like::remove($post->id);
            Flash::notice(
                __("Post unliked.", "likes"),
                $post->url()."#likes_".$post->id
            );
        }

        public function ajax_like(): void {
            if (empty($_POST['post_id']) or !is_numeric($_POST['post_id']))
                error(
                    __("Error"),
                    __("An ID is required to like a post.", "likes"),
                    code:400
                );

            # JavaScript can't know if this is allowed, so don't throw an error here.
            if (BOT_UA or !Visitor::current()->group->can("like_post")) {
                json_response(
                    __("You do not have sufficient privileges to like posts.", "likes"),
                    false
                );
                return;
            }

            $post = new Post($_POST['post_id']);

            if ($post->no_results)
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            $count = $post->like_count;
            Like::create($post->id);

            if ($count <= 0) {
                $text = __("You like this.", "likes");
            } else {
                $p = _p("You and %d person like this.", "You and %d people like this.", $count, "likes");
                $text = sprintf($p, $count);
            }

            json_response($text, true);
        }

        public function ajax_unlike(): void {
            if (empty($_POST['post_id']) or !is_numeric($_POST['post_id']))
                error(
                    __("Error"),
                    __("An ID is required to unlike a post.", "likes"),
                    code:400
                );

            # JavaScript can't know if this is allowed, so don't throw an error here.
            if (BOT_UA or !Visitor::current()->group->can("unlike_post")) {
                json_response(
                    __("You do not have sufficient privileges to unlike posts.", "likes"),
                    false
                );
                return;
            }

            $post = new Post($_POST['post_id']);

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            $count = $post->like_count - 1;
            Like::remove($post->id);

            if ($count <= 0) {
                $text = __("No likes yet.", "likes");
            } else {
                $p = _p("%d person likes this.", "%d people like this.", $count, "likes");
                $text = sprintf($p, $count);
            }

            json_response($text, true);
        }

        public function delete_post(
            $post
        ): void {
            SQL::current()->delete(
                table:"likes",
                conds:array("post_id" => $post->id)
            );
        }

        public function delete_user(
            $user
        ): void {
            SQL::current()->update(
                table:"likes",
                conds:array("user_id" => $user->id),
                data:array("user_id" => 0)
            );
        }

        private function get_post_like_count(
            $post_id
        ): int {
            if (!isset($this->caches["post_like_counts"])) {
                $counts = SQL::current()->select(
                    tables:"likes",
                    fields:array("COUNT(post_id) AS total", "post_id AS post_id"),
                    group:"post_id"
                )->fetchAll();

                $this->caches["post_like_counts"] = array();

                foreach ($counts as $count) {
                    $id = $count["post_id"];
                    $total = (int) $count["total"];
                    $this->caches["post_like_counts"][$id] = $total;
                }
            }

            return fallback($this->caches["post_like_counts"][$post_id], 0);
        }

        public function post_like_count_attr(
            $attr,
            $post
        ): int {
            if ($post->no_results)
                return 0;

            return $this->get_post_like_count($post->id);
        }

        public function get_user_like_count(
            $user_id
        ): int {
            if (!isset($this->caches["user_like_counts"])) {
                $counts = SQL::current()->select(
                    tables:"likes",
                    fields:array("COUNT(user_id) AS total", "user_id AS user_id"),
                    group:"user_id"
                )->fetchAll();

                $this->caches["user_like_counts"] = array();

                foreach ($counts as $count) {
                    $id = $count["user_id"];
                    $total = (int) $count["total"];
                    $this->caches["user_like_counts"][$id] = $total;
                }
            }

            return fallback($this->caches["user_like_counts"][$user_id], 0);
        }

        public function user_like_count_attr(
            $attr,
            $user
        ): int {
            if ($user->no_results)
                return 0;

            return $this->get_user_like_count($user->id);
        }

        public function visitor_like_count_attr(
            $attr,
            $visitor
        ): int {
            return ($visitor->id == 0) ?
                count(fallback($_SESSION['likes'], array())) :
                $this->user_like_count_attr($attr, $visitor) ;
        }

        public function post_like_link_attr(
            $attr,
            $post
        ): ?string {
            $config = Config::current();
            $route = Route::current();
            $main = MainController::current();
            $visitor = Visitor::current();
            $settings = $config->module_likes;

            if ($post->no_results or !isset($route))
                return null;

            if ($settings["show_on_index"] == false and $route->action == "index")
                return null;

            $html = '<div class="likes" id="likes_'.$post->id.'">';

            if (!Like::exists($post->id)) {
                if ($visitor->group->can("like_post")) {
                    $html.= '<a class="likes like" href="'.
                            url("/?action=like&post_id=".$post->id, $main).
                            '" data-post_id="'.$post->id.'">'.
                            $this->get_image($settings["like_image"]);

                    if ($settings["like_with_text"]) {
                        $html.= ' <span class="like">'.
                                __("Like!", "likes").
                                '</span>';

                        $html.= ' <span class="unlike">'.
                                __("Unlike!", "likes").
                                '</span>';
                    }

                    $html.= '</a>';
                }

                $html.= ' <span class="like_text">';
                $count = $post->like_count;

                if ($count > 0) {
                    $p = _p("%d person likes this.", "%d people like this.", $count, "likes");
                    $html.= sprintf($p, $count);
                } else {
                    $html.= __("No likes yet.", "likes");
                }

                $html.= '</span>';
            } else {
                if ($visitor->group->can("unlike_post")) {
                    $html.= '<a class="likes liked" href="'.
                            url("/?action=unlike&post_id=".$post->id, $main).
                            '" data-post_id="'.$post->id.'">'.
                            $this->get_image($settings["like_image"]);

                    if ($settings["like_with_text"]) {
                        $html.= ' <span class="like">'.
                                __("Like!", "likes").
                                '</span>';

                        $html.= ' <span class="unlike">'.
                                __("Unlike!", "likes").
                                '</span>';
                    }

                    $html.= '</a>';
                }

                $html.= ' <span class="like_text">';
                $count = $post->like_count - 1;

                if ($count > 0) {
                    $p = _p("You and %d person like this.", "You and %d people like this.", $count, "likes");
                    $html.= sprintf($p, $count);
                } else {
                    $html.= __("You like this.", "likes");
                }

                $html.= '</span>';
            }

            $html.= '</div>';
            return $html;
        }

        private function get_image(
            $filename
        ): string {
            if (str_ends_with($filename, ".svg")) {
                $filename = str_replace(array(DIR, "/"), "", $filename);
                $id = serialize($filename);
                $path = MODULES_DIR.DIR."likes".DIR."images";

                static $cache = array();

                if (isset($cache[$id]))
                    return $cache[$id];

                $svg = @file_get_contents($path.DIR.$filename);

                if ($svg === false)
                    return "&#x2764;";

                return $cache[$id] = $svg;
            } else {
                $url = Config::current()->chyrp_url.
                       "/modules/likes/images/".urlencode($filename);

                return '<img src="'.fix($url, true).'" alt="&#x2764;">';
            }
        }

        private function list_images(): array {
            $images = array();
            $dir = new DirectoryIterator(MODULES_DIR.DIR."likes".DIR."images");

            foreach ($dir as $item) {
                if ($item->isFile()) {
                    $filename = $item->getFilename();

                    if (preg_match("/.+\.(jpg|jpeg|png|gif|svg)$/i", $filename))
                        $images[] = $filename;
                }
            }

            return $images;
        }

        public function manage_posts_column_header(): string {
            return '<th class="post_likes value">'.__("Likes", "tags").'</th>';
        }

        public function manage_posts_column(
            $post
        ): string {
            return '<td class="post_likes value">'.$post->like_count.'</td>';
        }

        public function import_chyrp_post(
            $entry,
            $post
        ): void {
            $chyrp = $entry->children("http://chyrp.net/export/1.0/");

            if (!isset($chyrp->like))
                return;

            foreach ($chyrp->like as $like) {
                $timestamp = $like->children(
                    "http://www.w3.org/2005/Atom"
                )->published;

                $login = $like->children(
                    "http://chyrp.net/export/1.0/"
                )->login;

                $user = new User(
                    array("login" => unfix((string) $login))
                );

                Like::add(
                    post_id:$post->id,
                    user_id:(!$user->no_results) ? $user->id : 0,
                    timestamp:datetime((string) $timestamp),
                    session_hash:uniqid("imported_", true)
                );
            }
        }

        public function posts_export(
            $atom,
            $post
        ): string {
            $likes = Like::find(
                array("where" => array("post_id" => $post->id))
            );

            foreach ($likes as $like) {
                $atom.= '<chyrp:like>'."\n".
                    '<chyrp:login>'.
                    fix($like->user->login, false, true).
                    '</chyrp:login>'."\n".
                    '<published>'.
                    when(DATE_ATOM, $like->timestamp).
                    '</published>'."\n".
                    '<chyrp:etag>'.
                    fix($like->etag(), false, true).
                    '</chyrp:etag>'."\n".
                    '</chyrp:like>'."\n";
            }

            return $atom;
        }

        public function stylesheets(
            $styles
        ): array {
            $styles[] = Config::current()->chyrp_url.
                        "/modules/likes/likes.css";

            return $styles;
        }

        public function javascript(): void {
            include MODULES_DIR.DIR."likes".DIR."javascript.php";
        }
    }
