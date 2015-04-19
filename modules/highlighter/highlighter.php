<?php
    class Highlighter extends Modules {
        static function scripts($scripts) {
            $scripts[] = Config::current()->chyrp_url."/modules/highlighter/highlight.js";
            return $scripts;
        }

        static function stylesheets($stylesheets) {
            $stylesheets[] = Config::current()->chyrp_url."/modules/highlighter/highlight.css";
            return $stylesheets;
        }
    }
