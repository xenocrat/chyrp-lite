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
            $xml = simplexml_load_string(get_remote(UPDATE_XML, 3));
            Config::current()->set("check_updates_last", time());

            if (!self::validate($xml))
                return Flash::warning(__("Unable to check for new Chyrp Lite versions.").
                                         ' <a href="'.UPDATE_PAGE.'" target="_blank">'.
                                         __("Go to GitHub &rarr;").'</a>');

            foreach ($xml->channel->item as $item)
                if (version_compare(CHYRP_VERSION, $item->version, "<"))
                    return Flash::message(_f("Chyrp Lite &#8220;%s&#8221; is available.", fix($item->codename, true)).
                                             ' <a href="'.fix($item->updateurl, true).'" target="_blank">'.
                                             __("Go to GitHub &rarr;").'</a>');
        }

        /**
         * Function: validate
         * Validates the XML dataset.
         */
        private static function validate($xml) {
            if ($xml === false or !isset($xml->channel->item))
                return false;

            foreach ($xml->channel->item as $item)
                if (!isset($item->version) or
                    !isset($item->codename) or
                    !isset($item->updateurl) or
                    !preg_match("~^".preg_quote(UPDATE_PAGE, "~")."~", $item->updateurl))
                    return false;

            return true;
        }
    }
