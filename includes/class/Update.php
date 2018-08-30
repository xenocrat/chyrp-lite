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

            if (!Visitor::current()->group->can("change_settings"))
                return;

            if (!$config->check_updates)
                return;

            if (!((time() - $config->check_updates_last) > UPDATE_INTERVAL))
                return;

            $config->set("check_updates_last", time());

            $rss = get_remote(UPDATE_XML, 3);
            $xml = @simplexml_load_string($rss);

            if (!self::validate($xml))
                return Flash::warning(__("Unable to check for new Chyrp Lite versions.").
                                         ' <a href="'.fix(UPDATE_PAGE, true).'" target="_blank">'.
                                         __("Go to GitHub &rarr;").'</a>');

            foreach ($xml->channel->item as $item)
                if (version_compare(CHYRP_VERSION, $item->guid, "<"))
                    return Flash::message(_f("Chyrp Lite &#8220;%s&#8221; is available.", fix($item->title)).
                                             ' <a href="'.fix($item->link, true).'" target="_blank">'.
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
                if (!isset($item->guid) or
                    !isset($item->title) or
                    !isset($item->link) or !is_url($item->link))
                    return false;

            return true;
        }
    }
