<?php
    /**
     * Class: JSONFeed
     * Generates a JSON feed piece by piece.
     *
     * See Also:
     *     https://jsonfeed.org/version/1.1
     */
    class JSONFeed implements FeedGenerator {
        # Boolean: $open
        # Has the feed been opened?
        protected $open = false;

        # Variable: $count
        # The number of items generated.
        protected $count = 0;

        # Array: $json
        # Holds the feed as a $key => $val array.
        protected $json = array();

        /**
         * Function: type
         * Returns the content type of the feed.
         */
        public static function type(
        ): string {
            return "application/feed+json";
        }

        /**
         * Function: open
         * Adds the top-level feed objects.
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
        ): bool {
            if ($this->open)
                return false;

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
            return true;
        }

        /**
         * Function: category
         * Adds a tag object for an item.
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

            $item = $this->count - 1;

            fallback(
                $this->json["items"][$item]["tags"],
                array()
            );

            $this->json["items"][$item]["tags"][] = $term;
            return true;
        }

        /**
         * Function: rights
         * Not implemented in JSON Feed version 1.
         */
        public function rights(
            $text
        ): bool {
            return false;
        }

        /**
         * Function: enclosure
         * Adds an attachment object for an item.
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
        ): bool {
            if (!$this->open)
                return false;

            if (!$this->count)
                return false;

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
            return true;
        }

        /**
         * Function: related
         * Adds an external_url attribute for an item.
         *
         * Parameters:
         *     $link - The external URL.
         */
        public function related(
            $link
        ): bool {
            if (!$this->open)
                return false;

            if (!$this->count)
                return false;

            if (empty($link) or !is_url($link))
                return false;

            $item = $this->count - 1;
            $this->json["items"][$item]["external_url"] = $link;
            return true;
        }

        /**
         * Function: feed
         * Returns the generated feed.
         */
        public function feed(
        ): string {
            $encoded = json_set(
                $this->json,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );

            return ($encoded === false) ? "" : $encoded ;
        }

        /**
         * Function: display
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
