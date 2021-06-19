<?php
    class Highlighter extends Modules {
        static function __install() {
            $config = Config::current();

            $config->set("module_highlighter",
                         array("stylesheet" => "monokai-sublime.min.css"));
        }

        static function __uninstall() {
            Config::current()->remove("module_highlighter");
        }

        public function scripts($scripts) {
            $scripts[] = Config::current()->chyrp_url."/modules/highlighter/highlight.min.js";
            return $scripts;
        }

        public function javascript() {
            include MODULES_DIR.DIR."highlighter".DIR."javascript.php";
        }

        public function stylesheets($stylesheets) {
            $config = Config::current();
            $stylesheet = urlencode($config->module_highlighter["stylesheet"]);
            $path = $config->chyrp_url."/modules/highlighter/styles/".$stylesheet;

            $stylesheets[] = $path;
            return $stylesheets;
        }

        public function admin_highlighter_settings($admin) {
            $config = Config::current();

            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to change settings."));

            if (empty($_POST))
                return $admin->display("pages".DIR."highlighter_settings",
                                       array("highlighter_stylesheets" => $this->highlighter_stylesheets()));

            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            fallback($_POST['stylesheet'], "monokai-sublime.css");

            $config->set("module_highlighter",
                         array("stylesheet" => $_POST['stylesheet']));

            Flash::notice(__("Settings updated."), "highlighter_settings");
        }

        public function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["highlighter_settings"] = array("title" => __("Syntax Highlighting", "highlighter"));

            return $navs;
        }

        private function highlighter_stylesheets() {
            $config = Config::current();
            $styles = array();
            $filepaths = glob(MODULES_DIR.DIR."highlighter".DIR."styles".DIR."*.css");

            foreach ($filepaths as $filepath)
                $styles[] = basename($filepath);

            return $styles;
        }
    }
