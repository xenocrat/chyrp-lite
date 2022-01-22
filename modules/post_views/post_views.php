<?php
    require_once "model".DIR."View.php";

    class PostViews extends Modules {
        # Array: $caches
        # Query caches for methods.
        private $caches = array();

        static function __install() {
            View::install();
        }

        static function __uninstall($confirm) {
            if ($confirm)
                View::uninstall();
        }

        public function twig_context_main($context) {
            if (isset($context["post"]) and ($context["post"] instanceof Post) and !$context["post"]->no_results)
                View::add($context["post"]->id, Visitor::current()->id);
        }

        public function manage_posts_column_header() {
            echo '<th class="post_views value">'.__("View Count", "post_views").'</th>';
        }

        public function manage_posts_column($post) {
            if ($post->view_count > 0)
                echo '<td class="post_views value">'.'<a href="'.url("download_views/id/".$post->id).'" title="'.
                        fix(_f("Download view count for &#8220;%s&#8221;", $post->title(), "post_views"), true).'">'.
                        $post->view_count.'</a></td>';
            else
                echo '<td class="post_views value">'.$post->view_count.'</td>';
        }

        public function post($post) {
            $post->has_many[] = "views";
        }

        static function delete_post($post) {
            SQL::current()->delete("views", array("post_id" => $post->id));
        }

        public function admin_download_views() {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"),
                      __("An ID is required to download a view count.", "post_views"), null, 400);

            $post = new Post($_GET['id'], array("drafts" => true));

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            if (!$post->editable() and !$post->deletable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to download this view count.", "post_views"));

            $data = View::find(array("where" => array("post_id" => $post->id)));

            $filename = sanitize(camelize($post->title()), false, true)."_View_Count_".date("Y-m-d");
            $filedata = "id,post_id,user_id,created_at\r\n";

            foreach ($data as $datum)
                $filedata.= $datum->id.",".$datum->post_id.",".$datum->user_id.",".$datum->created_at."\r\n";

            file_attachment($filedata, $filename.".csv");
        }

        private function get_post_view_count($post_id) {
            if (!isset($this->caches["post_view_counts"])) {
                $counts = SQL::current()->select("views",
                                                 "COUNT(post_id) AS total, post_id as post_id",
                                                 null,
                                                 null,
                                                 array(),
                                                 null,
                                                 null,
                                                 "post_id")->fetchAll();

                $this->caches["post_view_counts"] = array();

                foreach ($counts as $count)
                    $this->caches["post_view_counts"][$count["post_id"]] = (int) $count["total"];
            }

            return fallback($this->caches["post_view_counts"][$post_id], 0);
        }

        public function post_view_count_attr($attr, $post) {
            if ($post->no_results)
                return 0;

            return $this->get_post_view_count($post->id);
        }
    }
