<?php
    class Inject extends Modules {
        const FREQUENCY_NEVER  = "never";
        const FREQUENCY_ONCE   = "once";
        const FREQUENCY_ODD    = "odd";
        const FREQUENCY_EVEN   = "even";
        const FREQUENCY_ALWAYS = "always";

        const TYPE_END_HEAD    = "head";
        const TYPE_END_MAIN    = "main";
        const TYPE_END_CONTENT = "foot";
        const TYPE_MARKUP_POST = "post";
        const TYPE_MARKUP_PAGE = "page";

        private $counts = array();
        private $injectors = array();

        public static function __install(
        ): void {
            Config::current()->set(
                "module_inject",
                array("injectors" => array())
            );
        }

        public static function __uninstall(
        ): void {
            Config::current()->remove("module_inject");
        }

        public function __init(
        ): void {
            $settings = Config::current()->module_inject;
            $this->injectors = $settings["injectors"];

            foreach ($this->injectors as $id => $injector)
                $this->counts[$id] = 0;

            $this->setPriority("markup_post_text", 5);
            $this->setPriority("markup_page_text", 5);
        }

        public function end_head(
        ): string {
            return $this->inject_trigger(self::TYPE_END_HEAD);
        }

        public function end_main(
        ): string {
            return $this->inject_trigger(self::TYPE_END_MAIN);
        }

        public function end_content(
        ): string {
            return $this->inject_trigger(self::TYPE_END_CONTENT);
        }

        public function markup_post_text(
            $text,
            $post = null
        ): string {
            return preg_replace_callback(
                "/<!-- *inject +([^<>]+) *-->/i",
                array($this, "inject_post"),
                $text
            );
        }

        public function markup_page_text(
            $text,
            $page = null
        ): string {
            return preg_replace_callback(
                "/<!-- *inject +([^<>]+) *-->/i",
                array($this, "inject_page"),
                $text
            );
        }

        public function inject_post(
            $matches
        ): string {
            return $this->inject_markup(
                $matches[1],
                self::TYPE_MARKUP_POST
            );
        }

        public function inject_page(
            $matches
        ): string {
            return $this->inject_markup(
                $matches[1],
                self::TYPE_MARKUP_PAGE
            );
        }

        private function inject_trigger(
            $type
        ): string {
            $content = "";

            foreach ($this->injectors as $id => $injector) {
                if ($injector["type"] != $type)
                    continue;

                $this->counts[$id]++;

                switch ($injector["frequency"]) {
                    case self::FREQUENCY_ONCE:
                        if ($injector["count"] == 1)
                            $content.= $injector["payload"];

                        break;

                    case self::FREQUENCY_ODD:
                        if ($injector["count"] % 2 == 1)
                            $content.= $injector["payload"];

                        break;

                    case self::FREQUENCY_EVEN:
                        if ($injector["count"] % 2 == 0)
                            $content.= $injector["payload"];

                        break;

                    case self::FREQUENCY_ALWAYS:
                        $content.= $injector["payload"];
                        break;
                }
            }

            return $content;
        }

        private function inject_markup(
            $label,
            $type
        ): string {
            $content = "";

            foreach ($this->injectors as $id => $injector) {
                if ($injector["type"] != $type)
                    continue;

                if ($injector["label"] != $label)
                    continue;

                $this->counts[$id]++;

                switch ($injector["frequency"]) {
                    case self::FREQUENCY_ONCE:
                        if ($injector["count"] == 1)
                            $content.= $injector["payload"];

                        break;

                    case self::FREQUENCY_ODD:
                        if ($injector["count"] % 2 == 1)
                            $content.= $injector["payload"];

                        break;

                    case self::FREQUENCY_EVEN:
                        if ($injector["count"] % 2 == 0)
                            $content.= $injector["payload"];

                        break;

                    case self::FREQUENCY_ALWAYS:
                        $content.= $injector["payload"];
                        break;
                }
            }

            return $content;
        }

        private function list_types(
        ): array {
            return array(
                self::TYPE_END_HEAD => __("HTML head", "inject"),
                self::TYPE_END_MAIN => __("End of main section", "inject"),
                self::TYPE_END_HEAD => __("After other content", "inject"),
                self::TYPE_MARKUP_POST => __("Post filter", "inject"),
                self::TYPE_MARKUP_PAGE => __("Page filter", "inject")
            );
        }

        private function list_frequencies(
        ): array {
            return array(
                self::FREQUENCY_NEVER => __("Never", "inject"),
                self::FREQUENCY_ONCE => __("Once", "inject"),
                self::FREQUENCY_ODD => __("Odd", "inject"),
                self::FREQUENCY_EVEN => __("Even", "inject"),
                self::FREQUENCY_ALWAYS => __("Always", "inject")
            );
        }

        public function admin_manage_injectors(
            $admin
        ): void {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['search']))
                redirect(
                    "manage_injectors/search/".
                    str_ireplace(
                        array("%2F", "%5C"),
                        "%5F",
                        urlencode($_POST['search'])
                    ).
                    "/"
                );

            $injectors = $this->injectors;

            if (isset($_GET['search']) and $_GET['search'] != "") {
                $injectors = array_filter(
                    $injectors,
                    function ($injector, $id) {
                        return (
                            str_contains(
                                $id,
                                $_GET['search']
                            )
                            or
                            str_contains(
                                $injector["label"],
                                $_GET['search']
                            )
                            or
                            str_contains(
                                $injector["payload"],
                                $_GET['search']
                            )
                        );
                    },
                    ARRAY_FILTER_USE_BOTH
                );
            }

            $admin->display(
                "pages".DIR."manage_injectors",
                array(
                    "injectors" => $injectors,
                    "injection_types" => $this->list_types(),
                    "injection_frequencies" => $this->list_frequencies()
                )
            );
        }

        public function admin_new_injector(
            $admin
        ): void {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            $admin->display(
                "pages".DIR."new_injector",
                array(
                    "injection_types" => $this->list_types(),
                    "injection_frequencies" => $this->list_frequencies()
                )
            );
        }

        public function admin_add_injector(
            $admin
        ): never {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['label']))
                error(
                    __("Error"),
                    __("Label cannot be blank.", "inject"),
                    code:422
                );

            do {
                $id = "ij" . random(8);
            } while (
                isset($this->injectors[$id])
            );

            fallback($_POST['payload'], "");
            fallback($_POST['type'], self::TYPE_MARKUP_POST);
            fallback($_POST['frequency'], self::FREQUENCY_NEVER);
            $label = normalize(strip_tags($_POST['label']));

            $this->injectors[$id] = array(
                "label" => $label,
                "payload" => $_POST['payload'],
                "type" => $_POST['type'],
                "frequency" => $_POST['frequency']
            );

            Config::current()->set(
                "module_inject",
                array("injectors" => $this->injectors)
            );

            Flash::notice(
                __("Injector added.", "inject"),
                "manage_injectors"
            );
        }

        public function admin_edit_injector(
            $admin
        ): void {
            if (empty($_GET['id']) or !str_starts_with($_GET['id'], "ij"))
                error(
                    __("No ID Specified"),
                    __("An ID is required to edit an injector.", "inject"),
                    code:400
                );

            $id = $_GET['id'];

            if (!isset($this->injectors[$id]))
                show_404(
                    __("Not Found"),
                    __("Injector not found.", "inject")
                );

            $admin->display(
                "pages".DIR."edit_injector",
                array(
                    "injector" => $this->injectors[$id],
                    "injector_id" => $id,
                    "injection_types" => $this->list_types(),
                    "injection_frequencies" => $this->list_frequencies()
                )
            );
        }

        public function admin_update_injector(
            $admin
        ): never {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !str_starts_with($_POST['id'], "ij"))
                error(
                    __("No ID Specified"),
                    __("An ID is required to update an injector.", "inject"),
                    code:422
                );

            if (empty($_POST['label']))
                error(
                    __("Error"),
                    __("Label cannot be blank.", "inject"),
                    code:422
                );

            $id = $_POST['id'];

            if (!isset($this->injectors[$id]))
                show_404(
                    __("Not Found"),
                    __("Injector not found.", "inject")
                );

            fallback($_POST['payload'], "");
            fallback($_POST['type'], self::TYPE_MARKUP_POST);
            fallback($_POST['frequency'], self::FREQUENCY_NEVER);
            $label = normalize(strip_tags($_POST['label']));

            $this->injectors[$id] = array(
                "label" => $label,
                "payload" => $_POST['payload'],
                "type" => $_POST['type'],
                "frequency" => $_POST['frequency']
            );

            Config::current()->set(
                "module_inject",
                array("injectors" => $this->injectors)
            );

            Flash::notice(
                __("Injector updated.", "inject"),
                "manage_injectors"
            );
        }

        public function admin_delete_injector(
            $admin
        ): void {
            if (empty($_GET['id']) or !str_starts_with($_GET['id'], "ij"))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete an injector.", "inject"),
                    code:400
                );

            $id = $_GET['id'];

            if (!isset($this->injectors[$id]))
                show_404(
                    __("Not Found"),
                    __("Injector not found.", "inject")
                );

            $admin->display(
                "pages".DIR."delete_injector",
                array(
                    "injector" => $this->injectors[$id],
                    "injector_id" => $id
                )
            );
        }

        public function admin_destroy_injector(
        ): never {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !str_starts_with($_POST['id'], "ij"))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete an injector.", "inject"),
                    code:400
                );

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_injectors");

            $id = $_POST['id'];

            if (!isset($this->injectors[$id]))
                show_404(
                    __("Not Found"),
                    __("Injector not found.", "inject")
                );

            unset($this->injectors[$id]);

            Config::current()->set(
                "module_inject",
                array("injectors" => $this->injectors)
            );

            Flash::notice(
                __("Injector deleted.", "inject"),
                "manage_injectors"
            );
        }

        public function manage_nav(
            $navs
        ): array {
            if (Visitor::current()->group->can("change_settings"))
                $navs["manage_injectors"] = array(
                    "title" => __("Injectors", "inject"),
                    "selected" => array(
                        "new_injector",
                        "delete_injector",
                        "edit_injector"
                    )
                );

            return $navs;
        }
    }
