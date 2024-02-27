<?php
    /**
     * Class: JSONFeed
     * Generates a JSON feed and outputs it on closing.
     *
     * See Also:
     *     https://jsonfeed.org/version/1.1
     */
    class JSONFeed implements FeedGenerator {
        # Boolean: $open
        # Has the feed been opened?
        private $open = false;

        # Variable: $count
        # The number of items generated.
        private $count = 0;

        # Array: $json
        # Holds the feed as a $key => $val array.
        private $json = array();

        /**
         * Function: type
         * Returns the content type of the feed.
         */
        public static function type(): string {
            return "application/feed+json";
        }

        /**
         * Function: open
         * Generates the top-level feed objects.
         *
         * Parameters:
         *     $title - Title for this feed.
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

            $this->json = array(
                "version"       => "https://jsonfeed.org/version/1.1",
                "language"      => $language,
                "title"         => strip_tags($title),
                "home_page_url" => url("/", MainController::current()),
                "feed_url"      => unfix(self_url())
            );

            if (!empty($subtitle))
                $this->json["description"] = strip_tags($subtitle);

            $this->json["items"] = array();
            $this->open = true;
        }

        /**
         * Function: entry
         * Generates an individual feed item.
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

            $this->count++;

            $entry = array(
                "id"             => $id,
                "url"            => $link,
                "title"          => strip_tags($title),
                "content_html"   => $content,
                "date_published" => when(DATE_RFC3339, $published),
                "date_modified"  => when(DATE_RFC3339, oneof($updated, $published)),
                "authors"        => array(array("name" => oneof($name, __("Guest"))))
            );

            if (!empty($uri) and is_url($uri))
                $entry["author"]["url"] = $uri;

            $item = $this->count - 1;
            $this->json["items"][$item] = $entry;
        }

        /**
         * Function: category
         * Generates a tag object for an item.
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
        ): void {
            if (!$this->open)
                return;

            if ($this->count == 0)
                return;

            $item = $this->count - 1;

            fallback(
                $this->json["items"][$item]["tags"],
                array()
            );

            $this->json["items"][$item]["tags"][] = $term;
        }

        /**
         * Function: rights
         * Not implemented in JSON Feed version 1.
         */
        public function rights($text): void {
            return;
        }

        /**
         * Function: enclosure
         * Generates an attachment object for an item.
         *
         * Parameters:
         *     $link - The URL to the resource.
         *     $length - Size in bytes of the resource (optional).
         *     $type - The media type of the resource (optional).
         *     $title - Title for the resource (optional).
         */
        public function enclosure(
            $link,
            $length = null,
            $type = "",
            $title = ""
        ): void {
            if (!$this->open)
                return;

            if ($this->count == 0)
                return;

            $item = $this->count - 1;

            fallback(
                $this->json["items"][$item]["attachments"],
                array()
            );

            $attachment = array(
                "url"       => $link,
                "mime_type" => oneof($type, "application/octet-stream")
            );

            if (!empty($length))
                $attachment["size_in_bytes"] = $length;

            if (!empty($title))
                $attachment["title"] = $title;

            $this->json["items"][$item]["attachments"][] = $attachment;
        }

        /**
         * Function: related
         * Generates an external_url attribute for an item.
         *
         * Parameters:
         *     $link - The external URL.
         */
        public function related($link): void {
            if (!$this->open)
                return;

            if ($this->count == 0)
                return;

            $item = $this->count - 1;

            if (!empty($link) and is_url($link))
                $this->json["items"][$item]["external_url"] = $link;
        }

        /**
         * Function: close
         * Closes the feed.
         */
        public function close(): void {
            $this->open = false;
        }

        /**
         * Function: feed
         * Returns the rendered feed.
         */
        public function feed(): string {
            $encoded = json_set(
                $this->json,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );

            return ($encoded === false) ? "" : $encoded ;
        }

        /**
         * Function: output
         * Displays the rendered feed.
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
