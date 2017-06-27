<?php
    /**
     * Class: JSONFeed
     * Generates a JSON feed and outputs it on closing.
     *
     * See Also:
     *     https://jsonfeed.org/version/1
     */
    class JSONFeed implements FeedGenerator {
        # Variable: $count
        # The number of items generated.
        private $count = 0;

        # Array: $json
        # Holds the feed as a $key => $val array.
        private $json = array();

        /**
         * Function: __construct
         * Sets the JSON feed header.
         */
        public function __construct() {
            header("Content-Type: application/json; charset=UTF-8");
        }

        /**
         * Function: type
         * Returns the content type of the feed.
         */
        static function type() {
            return "application/json";
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
        public function open($title, $subtitle = "", $id = "", $updated = 0) {
            $this->json = array(
                "version"       => "https://jsonfeed.org/version/1",
                "title"         => $title,
                "home_page_url" => Config::current()->url,
                "feed_url"      => self_url()
            );

            if (!empty($subtitle))
                $this->json["description"] = $subtitle;

            $this->json["items"] = array();
        }

        /**
         * Function: entry
         * Generates an individual feed item.
         *
         * Parameters:
         *     $title - Title for this entry.
         *     $id - The unique ID.
         *     $content - Content for this entry.
         *     $link - The URL to the resource.
         *     $published - Time of creation.
         *     $updated - Time of update (optional).
         *     $name - Name of the author (optional).
         *     $uri - URI of the author (optional).
         *     $email - Email address of the author (optional).
         */
        public function entry($title, $id, $content, $link, $published, $updated = 0, $name = "", $uri = "", $email = "") {
            $this->count++;

            $this->json["items"][($this->count - 1)] = array(
                "id"             => $id,
                "url"            => $link,
                "title"          => $title,
                "content_html"   => $content,
                "date_published" => $published,
                "date_modified"  => when("c", oneof($updated, $published)),
                "author"         => array("name" => oneof($name, __("Guest")))
            );

            if (!empty($uri) and is_url($uri))
                $this->json["items"][($this->count - 1)]["author"]["url"] = $uri;
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
        public function category($term, $scheme = "", $label = "") {
            if ($this->count == 0)
                return;

            fallback($this->json["items"][($this->count - 1)]["tags"], array());

            $this->json["items"][($this->count - 1)]["tags"][] = oneof($label, $term);
        }

        /**
         * Function: rights
         * Generates a rights object for an item.
         *
         * Parameters:
         *     $text - Human-readable licensing information.
         */
        public function rights($text) {
            # Not implemented in JSON Feed version 1.
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
        public function enclosure($link, $length = null, $type = "", $title = "") {
            if ($this->count == 0)
                return;

            fallback($this->json["items"][($this->count - 1)]["attachments"], array());

            $attachment = array(
                "url"       => $link,
                "mime_type" => oneof($type, "application/octet-stream")
            );

            if (!empty($length))
                $attachment["size_in_bytes"] = $length;

            if (!empty($title))
                $attachment["title"] = $title;

            $this->json["items"][($this->count - 1)]["attachments"][] = $attachment;
        }

        /**
         * Function: close
         * Encodes and outputs the feed.
         */
        public function close() {
            echo json_set($this->json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }
