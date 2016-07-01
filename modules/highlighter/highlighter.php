<?php
    class Highlighter extends Modules {
        static function scripts($scripts) {
            $scripts[] = Config::current()->chyrp_url."/modules/highlighter/highlight.js";
            return $scripts;
        }

        static function javascript() {
            include MODULES_DIR.DIR."highlighter".DIR."javascript.php";
        }

        static function stylesheets($stylesheets) {
            $stylesheets[] = Config::current()->chyrp_url."/modules/highlighter/highlight.css";
            return $stylesheets;
        }
    }
