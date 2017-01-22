<?php
    class Rights extends Modules {
        static function __uninstall($confirm) {
            if ($confirm) {
                $sql = SQL::current();

                $sql->delete("post_attributes", array("name" => "rights_title"));
                $sql->delete("post_attributes", array("name" => "rights_holder"));
                $sql->delete("post_attributes", array("name" => "rights_licence"));
            }
        }

        public function post_options($fields, $post = null) {
            $licences = array(array("name" => __("All rights reserved", "rights"),
                                    "value" => "All rights reserved",
                                    "selected" => ($post ? $post->rights_licence == "All rights reserved" : true)),
                              array("name" => __("Creative Commons BY", "rights"),
                                    "value" => "Creative Commons BY",
                                    "selected" => ($post ? $post->rights_licence == "Creative Commons BY" : false)),
                              array("name" => __("Creative Commons BY-ND", "rights"),
                                    "value" => "Creative Commons BY-ND",
                                    "selected" => ($post ? $post->rights_licence == "Creative Commons BY-ND" : false)),
                              array("name" => __("Creative Commons BY-SA", "rights"),
                                    "value" => "Creative Commons BY-SA",
                                    "selected" => ($post ? $post->rights_licence == "Creative Commons BY-SA" : false)),
                              array("name" => __("Creative Commons BY-NC", "rights"),
                                    "value" => "Creative Commons BY-NC",
                                    "selected" => ($post ? $post->rights_licence == "Creative Commons BY-NC" : false)),
                              array("name" => __("Creative Commons BY-NC-ND", "rights"),
                                    "value" => "Creative Commons BY-NC-ND",
                                    "selected" => ($post ? $post->rights_licence == "Creative Commons BY-NC-ND" : false)),
                              array("name" => __("Creative Commons BY-NC-SA", "rights"),
                                    "value" => "Creative Commons BY-NC-SA",
                                    "selected" => ($post ? $post->rights_licence == "Creative Commons BY-NC-SA" : false)),
                              array("name" => __("Creative Commons CC0", "rights"),
                                    "value" => "Creative Commons CC0",
                                    "selected" => ($post ? $post->rights_licence == "Creative Commons CC0" : false)),
                              array("name" => __("Orphan Work", "rights"),
                                    "value" => "Orphan Work",
                                    "selected" => ($post ? $post->rights_licence == "Orphan Work" : false)),
                              array("name" => __("Public Domain", "rights"),
                                    "value" => "Public Domain",
                                    "selected" => ($post ? $post->rights_licence == "Public Domain" : false)),
                              array("name" => __("Crown Copyright", "rights"),
                                    "value" => "Crown Copyright",
                                    "selected" => ($post ? $post->rights_licence == "Crown Copyright" : false)));

            $fields[] = array("attr" => "option[rights_title]",
                              "label" => __("Original Work", "rights"),
                              "type" => "text",
                              "value" => oneof(@$post->rights_title, ""));

            $fields[] = array("attr" => "option[rights_holder]",
                              "label" => __("Rights Holder", "rights"),
                              "type" => "text",
                              "value" => oneof(@$post->rights_holder, ""));

            $fields[] = array("attr" => "option[rights_licence]",
                              "label" => __("License", "rights"),
                              "help" => "choosing_a_licence",
                              "type" => "select",
                              "options" => $licences);

            return $fields;
        }

        public function post($post) {
            $post->licence_link = self::licence_link($post);
        }

        public function feed_item($post) {
            if (!empty($post->rights_licence))
               printf("        <rights>%s</rights>\n", $post->rights_licence);
        }

        public function licence_link($post) {
            switch ($post->rights_licence) {
                case "Creative Commons BY":
                $mark = '<a rel="license" href="http://creativecommons.org/licenses/by/4.0" class="rights_licence_link">'.
                        __("Creative Commons BY", "rights").
                        '</a>';
                break;
                case "Creative Commons BY-ND":
                $mark = '<a rel="license" href="http://creativecommons.org/licenses/by-nd/4.0" class="rights_licence_link">'.
                        __("Creative Commons BY-ND", "rights").
                        '</a>';
                break;
                case "Creative Commons BY-SA":
                $mark = '<a rel="license" href="http://creativecommons.org/licenses/by-sa/4.0" class="rights_licence_link">'.
                        __("Creative Commons BY-SA", "rights").
                        '</a>';
                break;
                case "Creative Commons BY-NC":
                $mark = '<a rel="license" href="http://creativecommons.org/licenses/by-nc/4.0" class="rights_licence_link">'.
                        __("Creative Commons BY-NC", "rights").
                        '</a>';
                break;
                case "Creative Commons BY-NC-ND":
                $mark = '<a rel="license" href="http://creativecommons.org/licenses/by-nc-nd/4.0" class="rights_licence_link">'.
                        __("Creative Commons BY-NC-ND", "rights").
                        '</a>';
                break;
                case "Creative Commons BY-NC-SA":
                $mark = '<a rel="license" href="http://creativecommons.org/licenses/by-nc-sa/4.0" class="rights_licence_link">'.
                        __("Creative Commons BY-NC-SA", "rights").
                        '</a>';
                break;
                case "Creative Commons CC0":
                $mark = '<a rel="license" href="http://creativecommons.org/publicdomain/zero/1.0" class="rights_licence_link">'.
                        __("Creative Commons CC0", "rights").
                        '</a>';
                break;
                case "Public Domain":
                $mark = '<a rel="license" href="http://wiki.creativecommons.org/Public_domain" class="rights_licence_link">'.
                        __("Public Domain", "rights").
                        '</a>';
                break;
                default:
                $mark = __("All rights reserved", "rights");
            }

            return $mark;
        }
    }
