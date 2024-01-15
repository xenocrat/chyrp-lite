<?php
    class Cascade extends Modules {
        public static function __install(): void {
            Config::current()->set(
                "module_cascade",
                array("ajax_scroll_auto" => true)
            );
        }

        public static function __uninstall(): void {
            Config::current()->remove("module_cascade");
        }

        public function admin_cascade_settings($admin): void {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );
    
            if (empty($_POST)) {
                $admin->display("pages".DIR."cascade_settings");
                return;
            }
    
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );
    
            Config::current()->set(
                "module_cascade",
                array("ajax_scroll_auto" => isset($_POST['auto']))
            );

            Flash::notice(
                __("Settings updated."),
                "cascade_settings"
            );
        }

        public function settings_nav($navs): array {
            if (Visitor::current()->group->can("change_settings"))
                $navs["cascade_settings"] = array(
                    "title" => __("Cascade", "cascade")
                );

            return $navs;
        }

        public function javascript(): void {
            include MODULES_DIR.DIR."cascade".DIR."javascript.php";
        }
    }
