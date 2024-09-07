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
        public static function type(): string;

        /**
         * Function: open
         * Opens the feed.
         */
        public function open(
            $title,
            $subtitle,
            $id,
            $updated
        ): bool;

        /**
         * Function: entry
         * Adds an individual entry to the feed.
         */
        public function entry(
            $title,
            $id,
            $content,
            $link,
            $published,
            $updated,
            $name,
            $uri,
            $email
        ): bool;

        /**
         * Function: category
         * Adds a category to an entry or feed.
         */
        public function category(
            $term,
            $scheme,
            $label
        ): bool;

        /**
         * Function: rights
         * Adds human-readable licensing information to an entry or feed.
         */
        public function rights(
            $text
        ): bool;

        /**
         * Function: enclosure
         * Adds a link for a resource that is potentially large in size.
         */
        public function enclosure(
            $link,
            $length,
            $type,
            $title
        ): bool;

        /**
         * Function: related
         * Adds a link for a resource related to an entry or feed.
         */
        public function related(
            $link
        ): bool;

        /**
         * Function: feed
         * Returns the generated feed.
         */
        public function feed(): string;

        /**
         * Function: display
         * Displays the generated feed.
         */
        public function display(): bool;
    }
