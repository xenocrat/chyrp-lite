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
                Flash::warning(__("Update check failed.").
                                  ' <a href="'.UPDATE_PAGE.'">'.__("Go to GitHub &rarr;").'</a>');
                return;
            }

            foreach ($xml->channel->item as $item) {
                if (version_compare(CHYRP_VERSION, $item->version, "<")) {
                    Flash::message(_f("Chyrp Lite v%s is available.", $item->version).
                                   ' <a href="'.$item->updateurl.'">'.__("Go to GitHub &rarr;").'</a>');
                    break;
                }
            }
        }
    }
