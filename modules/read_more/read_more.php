<?php
    class ReadMore extends Modules {
        public function __init() {
            # Replace comment codes before markup modules filters them.
            $this->setPriority("markup_post_text", 4);
        }

        public function markup_post_text($text, $post = null) {
            if (!is_string($text) or !preg_match("/<!-- *more(.+?)?-->/i", $text, $matches))
                return $text;

            $route = Route::current();
            $controller = $route->controller;

            if ($route->action == "view" or ($controller instanceof MainController and $controller->feed))
                return preg_replace("/<!-- *more(.+?)?-->/i", "", $text);

            $more = oneof(trim(fallback($matches[1])), __("&hellip;more", "read_more"));
            $url = (isset($post) and !$post->no_results) ? $post->url() : "#" ;
            $split = preg_split("/<!-- *more(.+?)?-->/i", $text, -1, PREG_SPLIT_NO_EMPTY);

            return $split[0].'<a class="read_more" href="'.$url.'">'.$more.'</a>';
        }

        public function title_from_excerpt($text) {
            $split = preg_split('/<a class="read_more"/', $text);
            return $split[0];
        }
    }
