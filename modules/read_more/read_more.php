<?php
    class ReadMore extends Modules {
        public function __init() {
            $this->addAlias("markup_post_text", "more", 4); # Replace "<!--more-->" before markup modules filter it.
        }

        static function more($text, $post = null) {
            if (!is_string($text) or preg_match("/<!--more(.+?)?-->/i", $text, $matches) === 0)
                return $text;

            $route = Route::current();
            $controller = $route->controller;

            if ($route->action == "view" or $route->action == "preview" or
                ($controller instanceof MainController and $controller->feed))
                return preg_replace("/<!--more(.+?)?-->/i", "", $text);

            $more = oneof(trim(fallback($matches[1])), __("&hellip;more", "read_more"));
            $url = (isset($post) and !$post->no_results) ? $post->url() : "#" ;
            $split = preg_split("/<!--more(.+?)?-->/i", $text, -1, PREG_SPLIT_NO_EMPTY);

            return $split[0].'<a class="read_more" href="'.$url.'">'.$more.'</a>';
        }

        static function title_from_excerpt($text) {
            $split = preg_split('/<a class="read_more"/', $text);
            return $split[0];
        }
    }
