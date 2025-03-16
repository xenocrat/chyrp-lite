<?php
    class Highlighter extends Modules {
        public static function __install(
        ): void {
            $config = Config::current();

            $config->set(
                "module_highlighter",
                array(
                    "stylesheet" => "default.min.css",
                    "copy_to_clipboard" => true
                )
            );
        }

        public static function __uninstall(
        ): void {
            Config::current()->remove("module_highlighter");
        }

        public function scripts(
            $scripts
        ): array {
            $scripts[] = Config::current()->chyrp_url.
                         "/modules/highlighter/highlight.min.js";

            return $scripts;
        }

        public function javascript(
        ): void {
            $config  = Config::current();
            $icon = $this->get_svg("copy.svg");
            include MODULES_DIR.DIR."highlighter".DIR."javascript.php";
        }

        public function stylesheets(
            $stylesheets
        ): array {
            $config = Config::current();
            $stylesheet = $config->module_highlighter["stylesheet"];

            $path = $config->chyrp_url.
                    "/modules/highlighter/styles/".$stylesheet;

            $stylesheets[] = $path;
            return $stylesheets;
        }

        public function admin_highlighter_settings(
            $admin
        ): void {
            $config = Config::current();

            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            if (empty($_POST)) {
                $admin->display(
                    "pages".DIR."highlighter_settings",
                    array(
                        "highlighter_stylesheets" => $this->highlighter_stylesheets()
                    )
                );

                return;
            }

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            fallback($_POST['stylesheet'], "default.min.css");

            $config->set(
                "module_highlighter",
                array(
                    "stylesheet" => $_POST['stylesheet'],
                    "copy_to_clipboard" => !empty($_POST['copy_to_clipboard'])
                )
            );

            Flash::notice(
                __("Settings updated."),
                "highlighter_settings"
            );
        }

        public function settings_nav(
            $navs
        ): array {
            if (Visitor::current()->group->can("change_settings"))
                $navs["highlighter_settings"] = array(
                    "title" => __("Syntax Highlighting", "highlighter")
                );

            return $navs;
        }

        private function get_svg(
            $filename
        ): string {
            $filename = str_replace(array(DIR, "/"), "", $filename);
            $id = serialize($filename);
            $path = MODULES_DIR.DIR."highlighter".DIR."images";

            static $cache = array();

            if (isset($cache[$id]))
                return $cache[$id];

            $svg = @file_get_contents($path.DIR.$filename);

            if ($svg === false)
                return "";

            return $cache[$id] = $svg;
        }

        private function highlighter_stylesheets(
            $base = null,
            $prefix = ""
        ): array {
            fallback($base, MODULES_DIR.DIR."highlighter".DIR."styles");
            $styles = array();
            $dir = new DirectoryIterator($base);

            foreach ($dir as $item) {
                if (!$item->isDot()) {
                    switch ($item->getType()) {
                        case "file":
                            $filename = $item->getFilename();

                            if (preg_match("/.+\.(css)$/i", $filename))
                                $styles[] = $prefix.$filename;

                            break;

                        case "dir":
                            $filename = $item->getFilename();
                            $pathname = $item->getPathname();
                            $addprefix = $prefix.$filename."/";
                            $addstyles = $this->highlighter_stylesheets(
                                $pathname, $addprefix
                            );
                            $styles = array_merge($styles, $addstyles);

                            break;
                    }
                }
            }

            return $styles;
        }
    }
