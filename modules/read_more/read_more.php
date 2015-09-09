<?php
    class ReadMore extends Modules {
        public function __init() {
            $this->addAlias("markup_post_text", "makesafe", 4); # Replace "<!--more-->" before markup modules filter it.
        }

        static function makesafe($text, $post = null) {
            if (!is_string($text) or strpos($text, "<!--more-->") === false)
                return $text;

            $controller = Route::current()->controller;
            if ($controller instanceof MainController and $controller->feed)
                return str_replace("<!--more-->", "", $text);

            $url = (isset($post) and !$post->no_results) ? $post->url() : "#" ;

            return str_replace("<!--more-->", '<a class="read_more" href="'.$url.'">e51b2b9a58824dd068d8777ec6e97e4d</a>', $text);
        }

        static function read_more($text, $string = null) {
            if (!substr_count($text, "e51b2b9a58824dd068d8777ec6e97e4d")) # md5 hash of "replace me!"
                return $text;

            if (Route::current()->action == "view" or (isset($_POST['context']) and $_POST['context'] == "view"))
                return preg_replace('/(<p>)?<a class="read_more" href="([^"]+)">e51b2b9a58824dd068d8777ec6e97e4d<\/a>(<\/p>)?/', "", $text);

            $plaintext = preg_replace("/<[^>]+>/", "", preg_replace("/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i", " ", $text));

            $breakpoint = strpos($plaintext, "e51b2b9a58824dd068d8777ec6e97e4d") + strlen("e51b2b9a58824dd068d8777ec6e97e4d");

            $body = truncate($text, $breakpoint, "", true, true); # Truncation will retain the closing tag of the read_more link.

            return str_replace("e51b2b9a58824dd068d8777ec6e97e4d",
                               fix((!isset($string) or $string instanceof Post) ? __("…more", "read_more") : $string),
                               $body);
        }

        static function title_from_excerpt($text) {
            $split = preg_split('/(<p>)?<a class="read_more" href="([^"]+)">e51b2b9a58824dd068d8777ec6e97e4d<\/a>(<\/p>)?/', $text);
            return $split[0];
        }
    }
