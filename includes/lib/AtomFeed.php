<?php
    /**
     * Class: AtomFeed
     * Generate an Atom feed and output it piece by piece.
     */
    class AtomFeed {
        # Variable: $count
        # The number of entries outputted.
        public $count = 0;

        /**
         * Function: __construct
         * Sets the Atom feed header.
         */
        public function __construct() {
            header("Content-Type: application/atom+xml; charset=UTF-8");
        }

        /**
         * Function: open
         * Output the opening feed tag and top-level elements.
         *
         * Parameters:
         *     $title - Title for this feed.
         *     $subtitle - Subtitle (optional).
         *     $id - Feed ID (optional).
         *     $updated - Time of update (optional).
         *
         * See Also:
         *     https://tools.ietf.org/html/rfc4287
         */
        public function open($title, $subtitle = "", $id = "", $updated = 0) {
            $chyrp_id = "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")";

            echo        '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo        '<feed xmlns="http://www.w3.org/2005/Atom">'."\n";
            echo        "    <title>".fix($title)."</title>\n";

            if (!empty($subtitle))
                echo    "    <subtitle>".fix($subtitle)."</subtitle>\n";

            echo        "    <id>".fix(oneof($id, self_url()))."</id>\n";
            echo        "    <updated>".when("c", oneof($updated, time()))."</updated>\n";
            echo        '    <link href="'.fix(self_url(), true).'" rel="self" type="application/atom+xml" />'."\n";
            echo        '    <generator uri="http://chyrplite.net/" version="'.CHYRP_VERSION.'">'.$chyrp_id."</generator>\n";
        }

        /**
         * Function: entry
         * Output an individual feed entry for the supplied item.
         *
         * Parameters:
         *     $title - Title for this entry.
         *     $id - The unique ID.
         *     $content - Content for this entry.
         *     $published - Time of creation.
         *     $updated - Time of update (optional).
         *     $name - Name of the author (optional).
         *     $uri - URI of the author (optional).
         *     $email - Email address of the author (optional).
         *
         * See Also:
         *     https://tools.ietf.org/html/rfc4287
         *
         * Notes:
         *     The entry remains open to allow triggered insertions.
         */
        public function entry($title, $id, $content, $link, $published, $updated = 0, $name = "", $uri = "", $email = "") {
            self::split();

            echo        "    <entry>\n";
            echo        '        <title type="html">'.fix($title)."</title>\n";
            echo        "        <id>tag:".fix($id)."</id>\n";
            echo        "        <updated>".when("c", oneof($updated, $published))."</updated>\n";
            echo        "        <published>".when("c", $published)."</published>\n";
            echo        '        <link rel="alternate" type="text/html" href="'.fix($link, true).'" />'."\n";
            echo        "        <author>\n";
            echo        "            <name>".fix(oneof($name, __("Guest")))."</name>\n";

            if (!empty($uri) and is_url($uri))
                echo    "            <uri>".fix($uri)."</uri>\n";

            if (!empty($email) and is_email($email))
                echo    "            <email>".fix($email)."</email>\n";

            echo        "        </author>\n";
            echo        '        <content type="html">'.fix($content)."</content>\n";

            $this->count++;
        }

        /**
         * Function: split
         * Output a closing entry tag if appropriate.
         */
        private function split() {
            if ($this->count > 0)
                echo    "    </entry>\n";
        }

        /**
         * Function: close
         * Output the closing feed tag.
         */
        public function close() {
            self::split();
            echo        "</feed>\n";
        }
    }
