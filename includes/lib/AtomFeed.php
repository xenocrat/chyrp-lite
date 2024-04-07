<?php
    /**
     * Class: AtomFeed
     * Generates an Atom feed and outputs it piece by piece.
     *
     * See Also:
     *     https://tools.ietf.org/html/rfc4287
     */
    class AtomFeed implements FeedGenerator {
        # Boolean: $open
        # Has the feed been opened?
        private $open = false;

        # Variable: $count
        # The number of entries rendered.
        private $count = 0;

        # String: $xml
        # The rendered XML.
        private $xml = "";

        /**
         * Function: type
         * Returns the content type of the feed.
         */
        public static function type(): string {
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
        public function open(
            $title,
            $subtitle = "",
            $id = "",
            $updated = null
        ): void {
            if ($this->open)
                return;

            $language = lang_base(Config::current()->locale);

            $this->xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";

            $this->xml.= '<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="'.
                         fix($language, true).'">'.
                         "\n";

            $this->xml.= '<title>'.fix($title).'</title>'."\n";

            if (!empty($subtitle))
                $this->xml.= '<subtitle>'.fix($subtitle).'</subtitle>'."\n";

            $this->xml.= '<id>'.fix(oneof($id, self_url())).'</id>'."\n";

            $this->xml.= '<updated>'.
                         when(DATE_ATOM, oneof($updated, time())).
                         '</updated>'.
                         "\n";

            $this->xml.= '<link href="'.
                         self_url().
                         '" rel="self" type="application/atom+xml" />'.
                         "\n";

            $this->xml.= '<generator uri="http://chyrplite.net/" version="'.
                         CHYRP_VERSION.
                         '">'.
                         CHYRP_IDENTITY.
                         '</generator>'.
                         "\n";

            $this->open = true;
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

            $this->xml.= '<entry>'."\n";

            $this->xml.= '<title type="html">'.
                         fix($title, false, true).
                         '</title>'.
                         "\n";

            $this->xml.= '<id>'.fix($id).'</id>'."\n";

            $this->xml.= '<updated>'.
                         when(DATE_ATOM, oneof($updated, $published)).
                         '</updated>'.
                         "\n";

            $this->xml.= '<published>'.
                         when(DATE_ATOM, $published).
                         '</published>'.
                         "\n";

            $this->xml.= '<link rel="alternate" type="text/html" href="'.
                         fix($link, true).
                         '" />'.
                         "\n";

            $this->xml.= '<author>'."\n";

            $this->xml.= '<name>'.
                         fix(oneof($name, __("Guest"))).
                         '</name>'.
                         "\n";

            if (!empty($uri) and is_url($uri))
                $this->xml.= '<uri>'.fix($uri).'</uri>'."\n";

            if (!empty($email) and is_email($email))
                $this->xml.= '<email>'.fix($email).'</email>'."\n";

            $this->xml.= '</author>'."\n";

            $this->xml.= '<content type="html">'.
                         fix($content, false, true).
                         '</content>'.
                         "\n";

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
        public function category(
            $term,
            $scheme = "",
            $label = ""
        ): void {
            if (!$this->open)
                return;

            $category = '<category term="'.
                        fix($term, true).
                        '"';

            if (!empty($scheme))
                $category.= ' scheme="'.fix($scheme, true).'"';

            if (!empty($label))
                $category.= ' label="'.fix($label, true).'"';

            $this->xml.= $category.' />'."\n";
        }

        /**
         * Function: rights
         * Outputs a rights element for an entry or feed.
         *
         * Parameters:
         *     $text - Human-readable licensing information.
         */
        public function rights(
            $text
        ): void {
            if (!$this->open)
                return;

            $this->xml.= '<rights>'.
                         fix($text, false, true).
                         '</rights>'.
                         "\n";
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
        public function enclosure(
            $link,
            $length = null,
            $type = "",
            $title = ""
        ): void {
            if (!$this->open)
                return;

            $enclosure = '<link rel="enclosure" href="'.
                         fix($link, true).
                         '"';

            if (!empty($length))
                $enclosure.= ' length="'.fix($length, true).'"';

            if (!empty($type))
                $enclosure.= ' type="'.fix($type, true).'"';

            if (!empty($title))
                $enclosure.= ' title="'.fix($title, true).'"';

            $this->xml.= $enclosure.' />'."\n";
        }

        /**
         * Function: related
         * Outputs a link element for a resource related to an entry or feed.
         *
         * Parameters:
         *     $link - The URL to the resource.
         */
        public function related(
            $link
        ): void {
            if (!$this->open)
                return;

            if (!empty($link) and is_url($link)) {
                $this->xml.= '<link rel="related" href="'.
                             fix($link, true).
                             '" />'.
                             "\n";
            }
        }

        /**
         * Function: split
         * Outputs a closing entry element.
         */
        private function split(): void {
            if (!$this->open)
                return;

            if ($this->count > 0)
                $this->xml.= '</entry>'."\n";
        }

        /**
         * Function: close
         * Outputs the closing feed element.
         */
        public function close(): void {
            if (!$this->open)
                return;

            $this->split();
            $this->xml.= '</feed>'."\n";
            $this->open = false;
        }

        /**
         * Function: feed
         * Returns the rendered feed.
         */
        public function feed(): string {
            return $this->xml;
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
