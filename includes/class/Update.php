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

            if (!$config->check_updates)
                return;

            if ((time() - $config->check_updates_last) < UPDATE_INTERVAL )
                return; # Check for updates once per day

            $xml = simplexml_load_file(UPDATE_XML);

            if ($xml == false) {
                Flash::warning(__("Unable to check for updates."));
                return;
            }

            $curver = CHYRP_VERSION;
            $return = false;

            foreach ($xml->channel->item as $item) {
                $newver = $item->version;

                if (version_compare($curver, $newver, "<")) {
                    $updateurl = $item->updateurl;
                    $downloadurl = $item->downloadurl;

                    Flash::message(_f("Chyrp Lite v%s is available. You can <a href='%s'>learn more</a> or <a href='%s'>download</a> it.",
                                      array($newver, $updateurl, $downloadurl)));

                    $return = array("version" => (string) $item->version,
                                    "url" => (string) $item->updateurl,
                                    "download" => (string) $item->downloadurl);

                    break;
                }
            }

            $config->set("check_updates_last", time());

            return $return;
        }
    }
