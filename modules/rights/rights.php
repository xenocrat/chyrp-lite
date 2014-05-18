<?php
    class Rights extends Modules {
        public function post_options($fields, $post = null) {

            $fields[] = array("attr" => "option[rights_title]",
                              "label" => __("Original Work", "rights"),
                              "type" => "text",
                              "value" => oneof(@$post->rights_title, ""));

            $fields[] = array("attr" => "option[rights_holder]",
                              "label" => __("Rights Holder", "rights"),
                              "type" => "text",
                              "value" => oneof(@$post->rights_holder, ""));

            $fields[] = array("attr" => "option[rights_licence]",
                              "label" => __("Licence", "rights"),
                              "type" => "select",
                              "options" => array(array("name" => __("All rights reserved", "rights"),
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
                                                       "selected" => ($post ? $post->rights_licence == "Crown Copyright" : false))));

            return $fields;
        }

        public function post($post) {
            $post->licence_mark = self::licence_mark($post);
        }

        static function licence_mark($post) {
            $chyrp = Config::current()->chyrp_url;

            switch ($post->rights_licence) {
                case "Creative Commons BY":
                $mark = '<a rel="license" href="http://creativecommons.org/licenses/by/3.0" class="rights_licence_link"><img class="rights_licence_mark" src="'.$chyrp.'/modules/rights/images/by.svg" alt="Creative Commons BY" /></a>';
                break;
                case "Creative Commons BY-ND":
                $mark = '<a rel="license" href="http://creativecommons.org/licenses/by-nd/3.0" class="rights_licence_link"><img class="rights_licence_mark" src="'.$chyrp.'/modules/rights/images/by-nd.svg" alt="Creative Commons BY-ND" /></a>';
                break;
                case "Creative Commons BY-SA":
                $mark = '<a rel="license" href="http://creativecommons.org/licenses/by-sa/3.0" class="rights_licence_link"><img class="rights_licence_mark" src="'.$chyrp.'/modules/rights/images/by-sa.svg" alt="Creative Commons BY-SA" /></a>';
                break;
                case "Creative Commons BY-NC":
                $mark = '<a rel="license" href="http://creativecommons.org/licenses/by-nc/3.0" class="rights_licence_link"><img class="rights_licence_mark" src="'.$chyrp.'/modules/rights/images/by-nc.svg" alt="Creative Commons BY-NC" /></a>';
                break;
                case "Creative Commons BY-NC-ND":
                $mark = '<a rel="license" href="http://creativecommons.org/licenses/by-nc-nd/3.0" class="rights_licence_link"><img class="rights_licence_mark" src="'.$chyrp.'/modules/rights/images/by-nc-nd.svg" alt="Creative Commons BY-NC-ND" /></a>';
                break;
                case "Creative Commons BY-NC-SA":
                $mark = '<a rel="license" href="http://creativecommons.org/licenses/by-nc-sa/3.0" class="rights_licence_link"><img class="rights_licence_mark" src="'.$chyrp.'/modules/rights/images/by-nc-sa.svg" alt="Creative Commons BY-NC-SA" /></a>';
                break;
                case "Creative Commons CC0":
                $mark = '<a rel="license" href="http://creativecommons.org/publicdomain/zero/1.0" class="rights_licence_link"><img class="rights_licence_mark" src="'.$chyrp.'/modules/rights/images/cc-zero.svg" alt="Creative Commons CC0" /></a>';
                break;
                case "Public Domain":
                $mark = '<a rel="license" href="http://wiki.creativecommons.org/Public_domain" class="rights_licence_link"><img class="rights_licence_mark" src="'.$chyrp.'/modules/rights/images/publicdomain.svg" alt="Public Domain" /></a>';
                break;
                default:
                $mark = "";
            }

            return $mark;
        }

    }
