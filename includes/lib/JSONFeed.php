<?php
    /**
     * Class: JSONFeed
     * Generates a JSON feed and outputs it on closing.
     *
     * See Also:
     *     https://jsonfeed.org/version/1.1
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
            header("Content-Type: ".self::type()."; charset=UTF-8");
        }

        /**
         * Function: type
         * Returns the content type of the feed.
         */
        static function type() {
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
        public function open($title, $subtitle = "", $id = "", $updated = null) {
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
        public function entry($title,
                              $id,
                              $content,
                              $link,
                              $published,
                              $updated = null,
                              $name = "",
                              $uri = "",
                              $email = "") {
            $this->count++;

            $item = array(
                "id"             => $id,
                "url"            => $link,
                "title"          => strip_tags($title),
                "content_html"   => $content,
                "date_published" => $published,
                "date_modified"  => when("c", oneof($updated, $published)),
                "authors"        => array(array("name" => oneof($name, __("Guest"))))
            );

            if (!empty($uri) and is_url($uri))
                $item["author"]["url"] = $uri;

            $this->json["items"][($this->count - 1)] = $item;
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

            $this->json["items"][($this->count - 1)]["tags"][] = $term;
        }

        /**
         * Function: rights
         * Not implemented in JSON Feed version 1.
         */
        public function rights($text) {
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
         * Function: related
         * Generates an external_url attribute for an item.
         *
         * Parameters:
         *     $link - The external URL.
         */
        public function related($link) {
            if ($this->count == 0)
                return;

            if (!empty($link) and is_url($link))
                $this->json["items"][($this->count - 1)]["external_url"] = $link;
        }

        /**
         * Function: close
         * Encodes and outputs the feed.
         */
        public function close() {
            echo json_set($this->json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }
