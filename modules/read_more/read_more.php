<?php
    class ReadMore extends Modules {
        public function __init() {
            $this->addAlias("markup_post_text", "makesafe", 8);
        }

        # Replace the "read more" indicator before markup modules get to it.
        static function makesafe($text, $post = null) {
            # Catch posts created with the WYSIWYG editor
            if (is_string($text)) $text = str_replace("&lt;!--more--&gt;", "<!--more-->", $text);

            if (!is_string($text) or !preg_match("/<!--more(\((.+)\))?-->/", $text)) return $text;

            $controller = Route::current()->controller;
            if ($controller instanceof MainController and $controller->feed)
                return str_replace("<!--more-->", "", $text);

            $url = (isset($post) and !$post->no_results) ? $post->url() : "#" ;

            # For the curious: e51b2b9a58824dd068d8777ec6e97e4d is a md5 of "replace me!"
            return preg_replace("/<!--more(\((.+)\))?-->/", '<a class="read_more" href="'.$url.'">e51b2b9a58824dd068d8777ec6e97e4d</a>(((more\\1)))', $text);
        }

        # To be used in the Twig template as ${ post.body | read_more("Read more...") }
        static function read_more($text, $string = null) {
            if (!substr_count($text, "e51b2b9a58824dd068d8777ec6e97e4d"))
                return $text;

            if (Route::current()->action == "view")
                return preg_replace('/(<p>)?<a class="read_more" href="([^"]+)">e51b2b9a58824dd068d8777ec6e97e4d<\/a>\(\(\(more(\((.+)\))?\)\)\)(<\/p>(\n\n<\/p>(\n\n)?)?)?/', "", $text);

            if (module_enabled('smartypants')) {
                preg_match_all("/e51b2b9a58824dd068d8777ec6e97e4d(\(\(\(more(\((.+)\))?\)\)\))/",
                               preg_replace("/<[^>]+>/", "", html_entity_decode(Smartypants::stupify($text), ENT_QUOTES, 'UTF-8')),
                               $more, PREG_OFFSET_CAPTURE);
                $body = truncate(html_entity_decode(Smartypants::stupify($text), ENT_QUOTES, 'UTF-8'), $more[1][0][1], "", true, true);
            } else {
                preg_match_all("/e51b2b9a58824dd068d8777ec6e97e4d(\(\(\(more(\((.+)\))?\)\)\))/",
                               preg_replace("/<[^>]+>/", "", html_entity_decode(str_replace("&nbsp;", " ", $text), ENT_QUOTES, 'UTF-8')),
                               $more, PREG_OFFSET_CAPTURE);
                $body = truncate($text, $more[1][0][1], "", true, true);
            }
            $body.= @$more[3][0];

            if (!empty($more[2][0]))
                $string = $more[2][0];
            elseif (!isset($string) or $string instanceof Post) # If it's called from anywhere but Twig the post will be passed as a second argument.
                $string = __("Read More &raquo;", "theme");

            return str_replace("e51b2b9a58824dd068d8777ec6e97e4d", $string, $body);
        }

        static function title_from_excerpt($text) {
            $split = preg_split("/(<p>)?<a class=\"read_more\" href=\"([^\"]+)\">e51b2b9a58824dd068d8777ec6e97e4d<\/a>(<\/p>(\n\n<\/p>(\n\n)?)?|<br \/>)?/", $text);
            return $split[0];
        }

        public function preview($text) {
            return preg_replace("/<!--more(\(([^\)]+)\))?-->/", "<hr />", $text);
        }
    }
