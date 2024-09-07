<?php
    class Lightbox extends Modules {
        public static function __install(): void {
            Config::current()->set(
                "module_lightbox",
                array(
                    "background" => "grey",
                    "spacing" => 24,
                    "protect" => true
                )
            );
        }

        public static function __uninstall(): void {
            Config::current()->remove("module_lightbox");
        }

        public function admin_lightbox_settings(
            $admin
        ): void {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );
    
            if (empty($_POST)) {
                $admin->display(
                    "pages".DIR."lightbox_settings",
                    array(
                        "lightbox_background" => array(
                            "black"   => __("Black", "lightbox"),
                            "grey"    => __("Gray", "lightbox"),
                            "white"   => __("White", "lightbox"),
                            "inherit" => __("Inherit", "lightbox")
                        )
                    )
                );

                return;
            }

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            fallback($_POST['background'], "grey");
            fallback($_POST['spacing'], 24);

            Config::current()->set(
                "module_lightbox",
                array(
                    "background" => $_POST['background'],
                    "spacing" => abs((int) $_POST['spacing']),
                    "protect" => isset($_POST['protect'])
                )
            );

            Flash::notice(
                __("Settings updated."),
                "lightbox_settings"
            );
        }

        public function settings_nav(
            $navs
        ): array {
            if (Visitor::current()->group->can("change_settings"))
                $navs["lightbox_settings"] = array(
                    "title" => __("Lightbox", "lightbox")
                );

            return $navs;
        }

        public function javascript(): void {
            $config = Config::current();
            include MODULES_DIR.DIR."lightbox".DIR."javascript.php";
        }
    }
