<?php
    /**
     * Class: AtomFeed
     * Generate an Atom feed and output it piece by piece.
     */
    class AtomFeed {
        # Variable: $count
        # The number of entries outputted.
        static $count = 0;

        /**
         * Function: open
         * Output the opening feed tag and top-level elements.
         *
         * Parameters:
         *     $title - Feed title.
         *     $subtitle - Feed subtitle (optional).
         *     $id - Feed ID (optional).
         *     $updated - Time of the latest update (optional).
         *     $links - An array of links (optional).
         */
        static function open($title, $subtitle = "", $id = "", $updated = 0, $links = array()) {
            if (!headers_sent())
                header("Content-Type: application/atom+xml; charset=UTF-8");

            $generator = "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")";

            echo            '<?xml version="1.0" encoding="utf-8"?>'."\r";
            echo            '<feed xmlns="http://www.w3.org/2005/Atom">'."\n";
            echo            "    <title>".fix($title)."</title>\n";

            if (!empty($subtitle))
                echo        "    <subtitle>".fix($subtitle)."</subtitle>\n";

            echo            "    <id>".fix(oneof($id, self_url()))."</id>\n";
            echo            "    <updated>".when("c", oneof($updated, time()))."</updated>\n";
            echo            '    <link href="'.fix(self_url(), true).'" rel="self" type="application/atom+xml" />'."\n";

            foreach ($links as $link)
                echo        '    <link href="'.fix($link, true).'" />'."\n";

            echo            '    <generator uri="http://chyrplite.net/" version="'.CHYRP_VERSION.'">'.$generator."</generator>\n";
        }

        /**
         * Function: entry
         * Output an individual feed entry for the supplied item.
         *
         * Parameters:
         *     $title - Entry title.
         *     $tagged - The unique tag ID.
         *     $content - Entry content.
         *     $published - Time of creation.
         *     $updated - Time of the latest updated (optional).
         *     $author_name - Name of the author (optional).
         *     $author_uri - URI of the author (optional).
         *
         * Notes:
         *     The entry is left open to allow additional output.
         */
        static function entry($title, $tagged, $content, $link, $published, $updated = 0, $author_name = "", $author_uri = "") {
            self::split();
            self::$count++;

            echo            "    <entry>\n";
            echo            '        <title type="html">'.fix($title)."</title>\n";
            echo            "        <id>tag:".fix($tagged)."</id>\n";
            echo            "        <updated>".when("c", oneof($updated, $published))."</updated>\n";
            echo            "        <published>".when("c", $published)."</published>\n";
            echo            '        <link rel="alternate" type="text/html" href="'.fix($link, true).'" />'."\n";

            if (!empty($author_name) or !empty($author_uri)) {
                echo        "        <author>\n";

                if (!empty($author_name))
                    echo    "            <name>".fix($author_name)."</name>\n";

                if (!empty($author_uri))
                    echo    "            <uri>".fix($author_uri)."</uri>\n";

                echo        "        </author>\n";
            }

            echo            '        <content type="html">'.fix($content)."</content>\n";
        }

        /**
         * Function: split
         * Output a closing entry tag if appropriate.
         */
        private static function split() {
            if (self::$count > 0)
                echo        "    </entry>\n";
        }

        /**
         * Function: close
         * Output the closing feed tag.
         */
        static function close() {
            self::split();
            echo            "</feed>\n";
        }
    }
