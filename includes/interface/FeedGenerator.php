<?php
    /**
     * Interface: FeedGenerator
     * Describes the functions required by FeedGenerator implementations.
     */
    interface FeedGenerator {
        /**
         * Function: type
         * Returns the content type of the feed.
         */
        static function type();

        /**
         * Function: open
         * Opens the feed.
         */
        public function open($title, $subtitle, $id, $updated);

        /**
         * Function: entry
         * Adds an individual entry to the feed.
         */
        public function entry($title, $id, $content, $link, $published, $updated, $name, $uri, $email);

        /**
         * Function: category
         * Adds a category to an entry or feed.
         */
        public function category($term, $scheme, $label);

        /**
         * Function: rights
         * Adds human-readable licensing information to an entry or feed.
         */
        public function rights($text);

        /**
         * Function: enclosure
         * Adds a link to a resource that is potentially large in size.
         */
        public function enclosure($link, $length, $type, $title);

        /**
         * Function: close
         * Closes the feed.
         */
        public function close();
    }
