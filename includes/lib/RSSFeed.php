<?php
    /**
     * Class: RSSFeed
     * Generates an RSS feed piece by piece.
     *
     * See Also:
     *     http://www.rssboard.org/rss-2-0-11
     */
    class RSSFeed implements FeedGenerator {
        # Boolean: $open
        # Has the feed been opened?
        protected $open = false;

        # Variable: $count
        # The number of entries generated.
        protected $count = 0;

        # Array: $xml
        # Holds the feed as an array.
        protected $xml = array();

        /**
         * Function: type
         * Returns the content type of the feed.
         */
        public static function type(
        ): string {
            return "application/rss+xml";
        }

        /**
         * Function: open
         * Adds the opening channel element and top-level elements.
         *
         * Parameters:
         *     $title - Title for this channel.
         *     $subtitle - Subtitle (optional).
         *     $id - Feed ID (optional).
         *     $updated - Time of update (optional).
         */
        public function open(
            $title,
            $subtitle = "",
            $id = "",
            $updated = null
        ): bool {
            if ($this->open)
                return false;

            $language = lang_base(Config::current()->locale);
            $link = url("/", MainController::current());

            $feed = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            $feed.= '<rss version="2.0">'."\n";
            $feed.= '<channel>'."\n";
            $feed.= '<language>'.fix($language).'</language>'."\n";
            $feed.= '<title>'.strip_tags($title).'</title>'."\n";

            if (!empty($subtitle))
                $feed.= '<description>'.
                        strip_tags($subtitle).
                        '</description>'.
                        "\n";

            $feed.= '<lastBuildDate>'.
                    when(DATE_RSS, oneof($updated, time())).
                    '</lastBuildDate>'.
                    "\n";

            $feed.= '<link>'.$link.'</link>'."\n";
            $feed.= '<generator>'.CHYRP_IDENTITY.'</generator>'."\n";

            $this->xml = array(
                "feed" => $feed,
                "items" => array()
            );

            return $this->open = true;
        }

        /**
         * Function: entry
         * Adds an individual feed item.
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
        public function entry(
            $title,
            $id,
            $content,
            $link,
            $published,
            $updated = null,
            $name = "",
            $uri = "",
            $email = ""
        ): bool {
            if (!$this->open)
                return false;

            $this->count++;

            $entry = '<title>'.strip_tags($title).'</title>'."\n";
            $entry.= '<guid>'.fix($id).'</guid>'."\n";

            $entry.= '<pubDate>'.
                         when(DATE_RSS, $published).
                         '</pubDate>'.
                         "\n";

            $entry.= '<link>'.fix($link).'</link>'."\n";

            $entry.= '<description>'.
                         fix($content, false, true).
                         '</description>'.
                         "\n";

            if (!empty($email) and is_email($email))
                $entry.= '<author>'.fix($email).'</author>'."\n";

            $item = $this->count - 1;
            $this->xml["items"][$item] = $entry;
            return true;
        }

        /**
         * Function: category
         * Adds a category element for an item.
         *
         * Parameters:
         *     $term - String that identifies the category.
         *     $scheme - URI for the categorization scheme (optional).
         *     $label - Human-readable label for the category (optional).
         */
        public function category(
            $term,
            $scheme = "",
            $label = ""
        ): bool {
            if (!$this->open)
                return false;

            if (!$this->count)
                return false;

            $category = '<category';

            if (!empty($scheme))
                $category.= ' domain="'.fix($scheme, true).'"';

            $category.= '>'.fix($term, true).'</category>'."\n";

            $item = $this->count - 1;
            $this->xml["items"][$item].= $category;
            return true;
        }

        /**
         * Function: rights
         * Not implemented in RSS 2.0.11.
         */
        public function rights(
            $text
        ): bool {
            return false;
        }

        /**
         * Function: enclosure
         * Adds an enclosure element for a resource that is potentially large in size.
         *
         * Parameters:
         *     $link - The URL to the resource.
         *     $length - Size in bytes of the resource (optional).
         *     $type - The media type of the resource (optional).
         *     $title - Title for the resource (optional).
         */
        public function enclosure(
            $link,
            $length = 0,
            $type = "",
            $title = ""
        ): bool {
            if (!$this->open)
                return false;

            $enclosure = '<enclosure url="'.fix($link, true).'"'.
                         ' length="'.fix($length, true).'"'.
                         ' type="'.
                         fix(oneof($type, "application/octet-stream"), true).
                         '" />'.
                         "\n";

            if (!$this->count) {
                $this->xml["feed"].= $enclosure;
            } else {
                $item = $this->count - 1;
                $this->xml["items"][$item].= $enclosure;
            }

            return true;
        }

        /**
         * Function: related
         * Not implemented in RSS 2.0.11.
         */
        public function related(
            $link
        ): bool {
            return false;
        }

        /**
         * Function: feed
         * Returns the generated feed.
         */
        public function feed(
        ): string {
            $feed = $this->xml["feed"];
            $items = $this->xml["items"];

            foreach ($items as $item) {
                $feed.= '<item>'."\n".
                        $item.
                        '</item>'."\n";
            }

            $feed.= '</channel>'."\n";
            $feed.= '</rss>'."\n";
            return $feed;
        }

        /**
         * Function: output
         * Displays the generated feed.
         */
        public function display(
        ): bool {
            if (headers_sent())
                return false;

            header("Content-Type: ".self::type()."; charset=UTF-8");
            echo $this->feed();
            return true;
        }
    }
