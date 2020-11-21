<?php
    /**
     * Class: RSSFeed
     * Generates an RSS feed and outputs it piece by piece.
     *
     * See Also:
     *     http://www.rssboard.org/rss-2-0-11
     */
    class RSSFeed implements FeedGenerator {
        # Variable: $count
        # The number of entries outputted.
        private $count = 0;

        /**
         * Function: __construct
         * Sets the RSS feed header.
         */
        public function __construct() {
            header("Content-Type: ".self::type()."; charset=UTF-8");
        }

        /**
         * Function: type
         * Returns the content type of the feed.
         */
        static function type() {
            return "application/rss+xml";
        }

        /**
         * Function: open
         * Outputs the opening channel element and top-level elements.
         *
         * Parameters:
         *     $title - Title for this channel.
         *     $subtitle - Subtitle (optional).
         *     $id - Feed ID (optional).
         *     $updated - Time of update (optional).
         */
        public function open($title, $subtitle = "", $id = "", $updated = null) {
            $language = lang_base(Config::current()->locale);

            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<rss version="2.0">'."\n";
            echo '<channel>'."\n";
            echo '<language>'.fix($language).'</language>'."\n";
            echo '<title>'.strip_tags($title).'</title>'."\n";

            if (!empty($subtitle))
                echo '<description>'.strip_tags($subtitle).'</description>'."\n";

            echo '<lastBuildDate>'.when("r", oneof($updated, time())).'</lastBuildDate>'."\n";
            echo '<link>'.url("/", MainController::current()).'</link>'."\n";
            echo '<generator>'.CHYRP_IDENTITY.'</generator>'."\n";
        }

        /**
         * Function: entry
         * Outputs an individual feed item.
         *
         * Parameters:
         *     $title - Title for this item.
         *     $id - The unique ID.
         *     $content - Content for this item.
         *     $link - The URL to the resource.
         *     $published - Time of creation.
         *     $updated - Time of update (optional).
         *     $name - Name of the author (optional).
         *     $uri - URI of the author (optional).
         *     $email - Email address of the author (optional).
         *
         * Notes:
         *     The item remains open to allow triggered insertions.
         */
        public function entry($title,
                              $id,
                              $content,
                              $link,
                              $published,
                              $updated = null,
                              $name = "",
                              $uri = "",
                              $email = "") {
            $this->split();

            echo '<item>'."\n";
            echo '<title>'.strip_tags($title).'</title>'."\n";
            echo '<guid>'.fix($id).'</guid>'."\n";
            echo '<pubDate>'.when("r", $published).'</pubDate>'."\n";
            echo '<link>'.fix($link).'</link>'."\n";
            echo '<description>'.fix($content, false, true).'</description>'."\n";

            if (!empty($email) and is_email($email))
                echo '<author>'.fix($email).'</author>'."\n";

            $this->count++;
        }

        /**
         * Function: category
         * Outputs a category element for an item.
         *
         * Parameters:
         *     $term - String that identifies the category.
         *     $scheme - URI for the categorization scheme (optional).
         *     $label - Human-readable label for the category (optional).
         */
        public function category($term, $scheme = "", $label = "") {
            if ($this->count == 0)
                return;

            $category = '<category';

            if (!empty($scheme))
                $category.= ' domain="'.fix($scheme, true).'"';

            echo $category.'>'.fix($term, true).'</category>'."\n";
        }

        /**
         * Function: rights
         * Not implemented in RSS 2.0.11.
         */
        public function rights($text) {
            return;
        }

        /**
         * Function: enclosure
         * Outputs an enclosure element for a resource that is potentially large in size.
         *
         * Parameters:
         *     $link - The URL to the resource.
         *     $length - Size in bytes of the resource (optional).
         *     $type - The media type of the resource (optional).
         *     $title - Title for the resource (optional).
         */
        public function enclosure($link, $length = 0, $type = "", $title = "") {
            echo '<enclosure url="'.fix($link, true).'"'.
                 ' length="'.fix($length, true).'"'.
                 ' type="'.fix(oneof($type, "application/octet-stream"), true).'" />'."\n";
        }

        /**
         * Function: related
         * Not implemented in RSS 2.0.11.
         */
        public function related($link) {
            return;
        }

        /**
         * Function: split
         * Outputs a closing item element.
         */
        private function split() {
            if ($this->count > 0)
                echo '</item>'."\n";
        }

        /**
         * Function: close
         * Outputs the closing channel element.
         */
        public function close() {
            $this->split();
            echo '</channel>'."\n";
            echo '</rss>'."\n";
        }
    }
