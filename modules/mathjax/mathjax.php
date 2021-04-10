<?php
    class Mathjax extends Modules {
        static function __install() {
            $config = Config::current();

            $config->set("module_mathjax",
                         array("enable_latex" => true,
                               "enable_mathml" => true));
        }

        static function __uninstall() {
            Config::current()->remove("module_mathjax");
        }

        public function scripts($scripts) {
            $config = Config::current();
            $script = "";

            if ($config->module_mathjax["enable_latex"])
                $script.= "tex-";

            if ($config->module_mathjax["enable_mathml"])
                $script.= "mml-";

            if (!empty($script))
                $scripts[] = $config->chyrp_url."/modules/mathjax/es5/".$script."chtml.js";

            return $scripts;
        }

        public function javascript() {
            include MODULES_DIR.DIR."mathjax".DIR."javascript.php";
        }

        public function admin_mathjax_settings($admin) {
            $config = Config::current();

            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to change settings."));

            if (empty($_POST))
                return $admin->display("pages".DIR."mathjax_settings");

            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            $config->set("module_mathjax",
                         array("enable_latex" => !empty($_POST['enable_latex']),
                               "enable_mathml" => !empty($_POST['enable_mathml'])));

            Flash::notice(__("Settings updated."), "mathjax_settings");
        }

        public function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["mathjax_settings"] = array("title" => __("MathJax", "mathjax"));

            return $navs;
        }
    }
