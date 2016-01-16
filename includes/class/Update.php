<?php
    /**
     * Class: Update
     * Handles updates to Chyrp Lite.
     */
    class Update {
        /**
         * Function: check
         * Checks if a newer version of Chyrp Lite is available.
         */
        public static function check() {
            $xml = simplexml_load_string(get_remote(UPDATE_XML, 3));
            Config::current()->set("check_updates_last", time());

            if ($xml == false) {
                Flash::warning(__("Unable to check for updates.").
                                  ' <a href="'.UPDATE_PAGE.'" target="_blank">'.__("Go to GitHub &rarr;").'</a>');
                return;
            }

            foreach ($xml->channel->item as $item) {
                if (version_compare(CHYRP_VERSION, $item->version, "<")) {
                    Flash::message(_f("Chyrp Lite v%s is available.", $item->version).
                                   ' <a href="'.$item->updateurl.'" target="_blank">'.__("Go to GitHub &rarr;").'</a>');
                    break;
                }
            }
        }
    }
