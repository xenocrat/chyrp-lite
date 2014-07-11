<?php
    /**
     * Class: Update
     * Alerts administrators to new Chyrp updates.
     */
    class Update {
        /**
         * Function: xml
         * Loads the update XML file.
         */
        private static function xml() {
            $xml = simplexml_load_string(get_remote("http://pimley.net/projects/downloads/chyrp-lite.xml"));
            return $xml;
        }

        /**
         * Function: check_update
         * Checks if the a new version of Chyrp is available.
         */
        public static function check_update() {
            $config = Config::current();

            if (!$config->check_updates)
                return;

            if ((time() - $config->check_updates_last) < 86400 )
                return; # Check for updates once per day

            $xml = self::xml();
            $curver = CHYRP_VERSION;

            foreach ($xml->channel->item as $item) {
                $newver = $item->version;

                if (version_compare($curver, $newver, ">="))
                    $return = false;
                else {
                    $updateurl = $item->updateurl;
                    $downloadurl = $item->downloadurl;
                    $return = _f("<p role='alert' class='message'>Chyrp Lite v%s is available. You can <a href='%s'>learn more</a> or <a href='%s'>download</a> it.</p>", array($newver, $updateurl, $downloadurl));
                    break;
                }
            }

            $config->set("check_updates_last", time());

            return $return;
        }
    }
