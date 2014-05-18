<?php
    class Cascade extends Modules {
        static function __install() {
            Config::current()->set("ajax_scroll_auto", true);
        }

        static function __uninstall() {
            Config::current()->remove("ajax_scroll_auto");
        }

        static function admin_cascade_settings($admin) {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));
    
            if (empty($_POST))
                return $admin->display("cascade_settings");
    
            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));
    
            $set = array( Config::current()->set("ajax_scroll_auto", isset($_POST['auto'])) );

            if (!in_array(false, $set))
                Flash::notice(__("Settings updated."), "/admin/?action=cascade_settings");
        }

        static function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["cascade_settings"] = array("title" => __("Cascade", "cascade"));
            return $navs;
        }


        static function scripts($scripts) {
            if (in_array(Route::current()->action, array("index",
                                                         "archive",
                                                         "search",
                                                         "tag",
                                                         "category",
                                                         "alphabetical"))
            ) {
                $scripts[] = Config::current()->chyrp_url."/modules/cascade/javascript.php";
                return $scripts;
            }
        }
    }
