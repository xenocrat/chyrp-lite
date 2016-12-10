<?php
    require_once "model".DIR."View.php";

    class PostViews extends Modules {
        static function __install() {
            View::install();
        }

        static function __uninstall($confirm) {
            if ($confirm)
                View::uninstall();
        }

        public function main_context($context) {
            if (isset($context["post"]) and ($context["post"] instanceof Post) and !$context["post"]->no_results)
                View::add($context["post"]->id);
        }

        public function manage_posts_column_header() {
            echo '<th class="post_views value">'.__("Views", "post_views").'</th>';
        }

        public function manage_posts_column($post) {
            echo '<td class="post_views value">'.$post->view_count.'</td>';
        }

        public function post($post) {
            $post->has_many[] = "views";
        }

        static function delete_post($post) {
            SQL::current()->delete("views", array("post_id" => $post->id));
        }

        public function post_view_count_attr($attr, $post) {
            if (isset($this->view_counts))
                return oneof(@$this->view_counts[$post->id], 0);

            $counts = SQL::current()->select("views",
                                             "COUNT(post_id) AS total, post_id as post_id",
                                             null,
                                             null,
                                             array(),
                                             null,
                                             null,
                                             "post_id")->fetchAll();

            foreach ($counts as $count)
                $this->view_counts[$count["post_id"]] = (int) $count["total"];

            return oneof(@$this->view_counts[$post->id], 0);
        }
    }
