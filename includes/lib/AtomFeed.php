<?php
    /**
     * Class: AtomFeed
     * Generates an Atom feed and outputs it piece by piece.
     *
     * See Also:
     *     https://tools.ietf.org/html/rfc4287
     */
    class AtomFeed implements FeedGenerator {
        # Variable: $count
        # The number of entries outputted.
        private $count = 0;

        /**
         * Function: __construct
         * Sets the Atom feed header.
         */
        public function __construct() {
            header("Content-Type: ".self::type()."; charset=UTF-8");
        }

        /**
         * Function: type
         * Returns the content type of the feed.
         */
        static function type() {
            return "application/atom+xml";
        }

        /**
         * Function: open
         * Outputs the opening feed element and top-level elements.
         *
         * Parameters:
         *     $title - Title for this feed.
         *     $subtitle - Subtitle (optional).
         *     $id - Feed ID (optional).
         *     $updated - Time of update (optional).
         */
        public function open($title, $subtitle = "", $id = "", $updated = null) {
            $language = lang_base(Config::current()->locale);

            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="'.fix($language, true).'">'."\n";
            echo '<title>'.fix($title).'</title>'."\n";

            if (!empty($subtitle))
                echo '<subtitle>'.fix($subtitle).'</subtitle>'."\n";

            echo '<id>'.fix(oneof($id, self_url())).'</id>'."\n";
            echo '<updated>'.when("c", oneof($updated, time())).'</updated>'."\n";
            echo '<link href="'.self_url().'" rel="self" type="application/atom+xml" />'."\n";
            echo '<generator uri="http://chyrplite.net/" version="'.CHYRP_VERSION.'">'.
                 CHYRP_IDENTITY.
                 '</generator>'."\n";
        }

        /**
         * Function: entry
         * Outputs an individual feed entry.
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
         *
         * Notes:
         *     The entry remains open to allow triggered insertions.
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

            echo '<entry>'."\n";
            echo '<title type="html">'.fix($title, false, true).'</title>'."\n";
            echo '<id>'.fix($id).'</id>'."\n";
            echo '<updated>'.when("c", oneof($updated, $published)).'</updated>'."\n";
            echo '<published>'.when("c", $published).'</published>'."\n";
            echo '<link rel="alternate" type="text/html" href="'.fix($link, true).'" />'."\n";
            echo '<author>'."\n";
            echo '<name>'.fix(oneof($name, __("Guest"))).'</name>'."\n";

            if (!empty($uri) and is_url($uri))
                echo '<uri>'.fix($uri).'</uri>'."\n";

            if (!empty($email) and is_email($email))
                echo '<email>'.fix($email).'</email>'."\n";

            echo '</author>'."\n";
            echo '<content type="html">'.fix($content, false, true).'</content>'."\n";

            $this->count++;
        }

        /**
         * Function: category
         * Outputs a category element for an entry or feed.
         *
         * Parameters:
         *     $term - String that identifies the category.
         *     $scheme - URI for the categorization scheme (optional).
         *     $label - Human-readable label for the category (optional).
         */
        public function category($term, $scheme = "", $label = "") {
            $category = '<category term="'.fix($term, true).'"';

            if (!empty($scheme))
                $category.= ' scheme="'.fix($scheme, true).'"';

            if (!empty($label))
                $category.= ' label="'.fix($label, true).'"';

            echo $category.' />'."\n";
        }

        /**
         * Function: rights
         * Outputs a rights element for an entry or feed.
         *
         * Parameters:
         *     $text - Human-readable licensing information.
         */
        public function rights($text) {
            echo '<rights>'.fix($text, false, true).'</rights>'."\n";
        }

        /**
         * Function: enclosure
         * Outputs a link element for a resource that is potentially large in size.
         *
         * Parameters:
         *     $link - The URL to the resource.
         *     $length - Size in bytes of the resource (optional).
         *     $type - The media type of the resource (optional).
         *     $title - Title for the resource (optional).
         */
        public function enclosure($link, $length = null, $type = "", $title = "") {
            $enclosure = '<link rel="enclosure" href="'.fix($link, true).'"';

            if (!empty($length))
                $enclosure.= ' length="'.fix($length, true).'"';

            if (!empty($type))
                $enclosure.= ' type="'.fix($type, true).'"';

            if (!empty($title))
                $enclosure.= ' title="'.fix($title, true).'"';

            echo $enclosure.' />'."\n";
        }

        /**
         * Function: related
         * Outputs a link element for a resource related to an entry or feed.
         *
         * Parameters:
         *     $link - The URL to the resource.
         */
        public function related($link) {
            if (!empty($link) and is_url($link))
                echo '<link rel="related" href="'.fix($link, true).'" />'."\n";
        }

        /**
         * Function: split
         * Outputs a closing entry element.
         */
        private function split() {
            if ($this->count > 0)
                echo '</entry>'."\n";
        }

        /**
         * Function: close
         * Outputs the closing feed element.
         */
        public function close() {
            $this->split();
            echo '</feed>'."\n";
        }
    }
