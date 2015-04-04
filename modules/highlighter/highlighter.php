<?php
    class Highlighter extends Modules {
        static function scripts($scripts) {
            $scripts[] = Config::current()->chyrp_url."/modules/highlighter/highlighter.js";
            return $scripts;
        }

        static function stylesheets($stylesheets) {
            $stylesheets[] = Config::current()->chyrp_url."/modules/highlighter/highlighter.css";
            return $stylesheets;
        }
    }
