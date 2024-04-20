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
        private $open = false;

        # Variable: $count
        # The number of entries generated.
        private $count = 0;

        # String: $xml
        # The generated XML.
        private $xml = "";

        /**
         * Function: type
         * Returns the content type of the feed.
         */
        public static function type(): string {
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
        ): void {
            if ($this->open)
                return;

            $language = lang_base(Config::current()->locale);
            $link = url("/", MainController::current());

            $this->open = true;
            $this->count = 0;

            $this->xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            $this->xml.= '<rss version="2.0">'."\n";
            $this->xml.= '<channel>'."\n";
            $this->xml.= '<language>'.fix($language).'</language>'."\n";
            $this->xml.= '<title>'.strip_tags($title).'</title>'."\n";

            if (!empty($subtitle))
                $this->xml.= '<description>'.
                             strip_tags($subtitle).
                             '</description>'.
                             "\n";

            $this->xml.= '<lastBuildDate>'.
                         when(DATE_RSS, oneof($updated, time())).
                         '</lastBuildDate>'.
                         "\n";

            $this->xml.= '<link>'.$link.'</link>'."\n";
            $this->xml.= '<generator>'.CHYRP_IDENTITY.'</generator>'."\n";
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
        ): void {
            if (!$this->open)
                return;

            $this->split();

            $this->xml.= '<item>'."\n";
            $this->xml.= '<title>'.strip_tags($title).'</title>'."\n";
            $this->xml.= '<guid>'.fix($id).'</guid>'."\n";

            $this->xml.= '<pubDate>'.
                         when(DATE_RSS, $published).
                         '</pubDate>'.
                         "\n";

            $this->xml.= '<link>'.fix($link).'</link>'."\n";

            $this->xml.= '<description>'.
                         fix($content, false, true).
                         '</description>'.
                         "\n";

            if (!empty($email) and is_email($email))
                $this->xml.= '<author>'.fix($email).'</author>'."\n";

            $this->count++;
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
        public function category($term, $scheme = "", $label = ""): void {
            if (!$this->open)
                return;

            if ($this->count == 0)
                return;

            $category = '<category';

            if (!empty($scheme))
                $category.= ' domain="'.fix($scheme, true).'"';

            $this->xml.= $category.'>'.fix($term, true).'</category>'."\n";
        }

        /**
         * Function: rights
         * Not implemented in RSS 2.0.11.
         */
        public function rights($text): void {
            return;
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
        ): void {
            if (!$this->open)
                return;

            $this->xml.= '<enclosure url="'.fix($link, true).'"'.
                         ' length="'.fix($length, true).'"'.
                         ' type="'.
                         fix(oneof($type, "application/octet-stream"), true).
                         '" />'.
                         "\n";
        }

        /**
         * Function: related
         * Not implemented in RSS 2.0.11.
         */
        public function related($link): void {
            return;
        }

        /**
         * Function: split
         * Adds a closing item element.
         */
        private function split(): void {
            if (!$this->open)
                return;

            if ($this->count > 0)
                $this->xml.= '</item>'."\n";
        }

        /**
         * Function: close
         * Adds the closing channel element.
         */
        public function close(): void {
            if (!$this->open)
                return;

            $this->split();
            $this->xml.= '</channel>'."\n";
            $this->xml.= '</rss>'."\n";
            $this->open = false;
        }

        /**
         * Function: feed
         * Returns the generated feed.
         */
        public function feed(): string {
            return $this->xml;
        }

        /**
         * Function: output
         * Displays the generated feed.
         */
        public function display(): bool {
            if (headers_sent())
                return false;

            if ($this->open)
                $this->close();

            header("Content-Type: ".self::type()."; charset=UTF-8");
            echo $this->feed();
            return true;
        }
    }
