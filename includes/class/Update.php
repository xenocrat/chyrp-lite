<?php
    /**
     * Class: Update
     * Alerts administrators to new Chyrp updates.
     */
    class Update {
        /**
         * Function: check_update
         * Checks if the a new version of Chyrp is available.
         */
        public static function check_update() {
            $config = Config::current();

            $xml = simplexml_load_string(get_remote(UPDATE_XML, 3));
            $config->set("check_updates_last", time());

            if ($xml == false) {
                Flash::warning(_f("Unable to check for updates. Please visit <a href=\"%s\">GitHub</a> to see a list of releases.",
                                  "https://github.com/xenocrat/chyrp-lite/releases"));
                return;
            }

            foreach ($xml->channel->item as $item) {
                if (version_compare(CHYRP_VERSION, $item->version, "<")) {
                    Flash::message(_f("Chyrp Lite v%s is available. You can <a href=\"%s\">learn more</a> or <a href=\"%s\">download it</a>.",
                                      array($item->version, $item->updateurl, $item->downloadurl)));
                    break;
                }
            }
        }
    }
