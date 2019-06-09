<?php
    /**
     * Class: Update
     * Informs the user if a newer version of Chyrp Lite is available.
     */
    class Update {
        /**
         * Function: check
         * Checks the update channel.
         */
        public static function check() {
            $config = Config::current();
            $visitor = Visitor::current();

            if (!$config->check_updates)
                return;

            if (!$visitor->group->can("change_settings"))
                return;

            # Return unless elapsed time is greater than the update interval.
            if (!((time() - $config->check_updates_last) > UPDATE_INTERVAL))
                return;

            $config->set("check_updates_last", time());

            $rss = get_remote(UPDATE_XML, 3);
            $xml = @simplexml_load_string($rss);

            if (!self::validate($xml))
                return self::warning();

            foreach ($xml->channel->item as $item)
                if (version_compare(CHYRP_VERSION, $item->guid, "<"))
                    return self::message($item);
        }

        /**
         * Function: validate
         * Validates the XML dataset.
         */
        private static function validate($xml) {
            if (!$xml instanceof SimpleXMLElement)
                return false;

            if (!isset($xml->channel->item))
                return false;

            foreach ($xml->channel->item as $item)
                if (!isset($item->guid) or
                    !isset($item->title) or
                    !isset($item->link) or !is_url($item->link))
                        return false;

            return true;
        }

        /**
         * Function: message
         * Flash the user about the newer version.
         */
        private static function message($item) {
            Flash::message(_f("Chyrp Lite &#8220;%s&#8221; is available.", fix($item->title)).
                           ' <a href="'.fix($item->link, true).'" target="_blank">'.
                           __("Go to GitHub &rarr;").'</a>');
        }

        /**
         * Function: warning
         * Flash the user about the failed check.
         */
        private static function warning() {
            Flash::warning(__("Unable to check for new Chyrp Lite versions.").
                           ' <a href="'.fix(UPDATE_PAGE, true).'" target="_blank">'.
                           __("Go to GitHub &rarr;").'</a>');
        }
    }
