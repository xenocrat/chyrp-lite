<?php
    require_once "model".DIR."View.php";

    class PostViews extends Modules {
        # Array: $caches
        # Query caches for methods.
        private $caches = array();

        public static function __install(): void {
            View::install();
        }

        public static function __uninstall($confirm): void {
            if ($confirm)
                View::uninstall();
        }

        public function twig_context_main($context): void {
            $visitor = Visitor::current();

            if (
                !isset($context["post"]) or
                !($context["post"] instanceof Post)
            )
                return;

            $post = $context["post"];

            if (
                $post->no_results or
                $post->user->id == $visitor->id
            )
                return;

            View::add(
                post_id:$context["post"]->id,
                user_id:$visitor->id
            );
        }

        public function manage_posts_column_header(): string {
            return '<th class="post_views value">'.
                   __("View Count", "post_views").
                   '</th>';
        }

        public function manage_posts_column($post): string {
            $text = _f("Download view count for &#8220;%s&#8221;", $post->title(), "post_views");

            if ($post->view_count > 0)
                return '<td class="post_views value">'.
                       '<a href="'.
                       url("download_views/id/".
                       $post->id).
                       '" title="'.
                       fix($text, true).
                       '">'.
                       $post->view_count.
                       '</a></td>';
            else
                return '<td class="post_views value">'.$post->view_count.'</td>';
        }

        public function user($user): void {
            $user->has_many[] = "views";
        }

        public function post($post): void {
            $post->has_many[] = "views";
        }

        public function post_options($fields, $post = null): array {
            if (isset($post))
                $fields[] = array(
                    "attr" => "reset_views",
                    "label" => __("Reset View Count?", "post_views"),
                    "type" => "checkbox",
                    "checked" => false
                );

            return $fields;
        }

        public function update_post($post): void {
            if (isset($_POST['reset_views']))
                SQL::current()->delete(
                    table:"views",
                    conds:array("post_id" => $post->id)
                );
        }

        public function delete_post($post): void {
            SQL::current()->delete(
                table:"views",
                conds:array("post_id" => $post->id)
            );
        }

        public function admin_download_views(): void {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to download a view count.", "post_views"),
                    code:400
                );

            $post = new Post($_GET['id'], array("drafts" => true));

            if ($post->no_results)
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            if (!$post->editable() and !$post->deletable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to download this view count.", "post_views")
                );

            $data = View::find(
                array("where" => array("post_id" => $post->id))
            );

            $filename = sanitize(
                camelize($post->title()),
                false,
                true
            )."_View_Count_".date("Y-m-d");

            $filedata = "id,post_id,user_id,created_at\r\n";

            foreach ($data as $datum)
                $filedata.= $datum->id.",".
                            $datum->post_id.",".
                            $datum->user_id.",".
                            $datum->created_at."\r\n";

            file_attachment($filedata, $filename.".csv");
        }

        private function get_post_view_count($post_id): int {
            if (!isset($this->caches["post_view_counts"])) {
                $counts = SQL::current()->select(
                    tables:"views",
                    fields:array("COUNT(post_id) AS total", "post_id AS post_id"),
                    group:"post_id"
                )->fetchAll();

                $this->caches["post_view_counts"] = array();

                foreach ($counts as $count) {
                    $id = $count["post_id"];
                    $total = (int) $count["total"];
                    $this->caches["post_view_counts"][$id] = $total;
                }
            }

            return fallback($this->caches["post_view_counts"][$post_id], 0);
        }

        public function post_view_count_attr($attr, $post): int {
            if ($post->no_results)
                return 0;

            return $this->get_post_view_count($post->id);
        }

        private function get_user_view_count($user_id): int {
            if (!isset($this->caches["user_view_counts"])) {
                $counts = SQL::current()->select(
                    tables:"views",
                    fields:array("COUNT(user_id) AS total", "user_id AS user_id"),
                    group:"user_id"
                )->fetchAll();

                $this->caches["user_view_counts"] = array();

                foreach ($counts as $count) {
                    $id = $count["user_id"];
                    $total = (int) $count["total"];
                    $this->caches["user_view_counts"][$id] = $total;
                }
            }

            return fallback($this->caches["user_view_counts"][$user_id], 0);
        }

        public function user_view_count_attr($attr, $user): int {
            if ($user->no_results)
                return 0;

            return $this->get_user_view_count($user->id);
        }

        public function visitor_view_count_attr($attr, $visitor): int {
            return ($visitor->id == 0) ?
                0 :
                $this->user_view_count_attr($attr, $visitor) ;
        }

        public function import_chyrp_post($entry, $post): void {
            $chyrp = $entry->children("http://chyrp.net/export/1.0/");

            if (!isset($chyrp->view))
                return;

            foreach ($chyrp->view as $view) {
                $created_at = $view->children(
                    "http://www.w3.org/2005/Atom"
                )->published;

                $login = $view->children(
                    "http://chyrp.net/export/1.0/"
                )->login;

                $user = new User(
                    array("login" => unfix((string) $login))
                );

                View::add(
                    post_id:$post->id,
                    user_id:(!$user->no_results) ? $user->id : 0,
                    created_at:datetime((string) $created_at),
                );
            }
        }

        public function posts_export($atom, $post): string {
            $views = View::find(
                array("where" => array("post_id" => $post->id))
            );

            foreach ($views as $view) {
                $atom.= '<chyrp:view>'."\n".
                    '<chyrp:login>'.
                    fix($view->user->login, false, true).
                    '</chyrp:login>'."\n".
                    '<published>'.
                    when(DATE_ATOM, $view->created_at).
                    '</published>'."\n".
                    '<chyrp:etag>'.
                    fix($view->etag(), false, true).
                    '</chyrp:etag>'."\n".
                    '</chyrp:view>'."\n";
            }

            return $atom;
        }
    }
