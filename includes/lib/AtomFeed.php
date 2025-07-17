<?php
    /**
     * Class: AtomFeed
     * Generates an Atom feed piece by piece.
     *
     * See Also:
     *     https://datatracker.ietf.org/doc/html/rfc4287
     *     https://datatracker.ietf.org/doc/html/rfc5005
     */
    class AtomFeed implements FeedGenerator {
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
            return "application/atom+xml";
        }

        /**
         * Function: open
         * Adds the opening feed element and top-level elements.
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
            $updated = null,
            $prev_page = null,
            $next_page = null,
            $first_page = null,
            $last_page = null
        ): bool {
            if ($this->open)
                return false;

            $this->open = true;

            $language = lang_base(Config::current()->locale);

            $feed = '<?xml version="1.0" encoding="UTF-8"?>'."\n";

            $feed.= '<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="'.
                    fix($language, true).'">'.
                    "\n";

            $feed.= '<title>'.fix($title).'</title>'."\n";

            if (!empty($subtitle))
                $feed.= '<subtitle>'.fix($subtitle).'</subtitle>'."\n";

            $feed.= '<id>'.fix(oneof($id, self_url())).'</id>'."\n";

            $feed.= '<updated>'.
                    when(DATE_ATOM, oneof($updated, time())).
                    '</updated>'.
                    "\n";

            $feed.= '<link href="'.
                    self_url().
                    '" rel="self" type="application/atom+xml" />'.
                    "\n";

            if (isset($prev_page) and is_url($prev_page))
                $feed.= '<link href="'.
                        fix($prev_page, true).
                        '" rel="previous" type="application/atom+xml" />'.
                        "\n";

            if (isset($next_page) and is_url($next_page))
                $feed.= '<link href="'.
                        fix($next_page, true).
                        '" rel="next" type="application/atom+xml" />'.
                        "\n";

            if (isset($first_page) and is_url($first_page))
                $feed.= '<link href="'.
                        fix($first_page, true).
                        '" rel="first" type="application/atom+xml" />'.
                        "\n";

            if (isset($last_page) and is_url($last_page))
                $feed.= '<link href="'.
                        fix($last_page, true).
                        '" rel="last" type="application/atom+xml" />'.
                        "\n";

            $feed.= '<generator uri="http://chyrplite.net/" version="'.
                    CHYRP_VERSION.
                    '">'.
                    CHYRP_IDENTITY.
                    '</generator>'.
                    "\n";

            $this->xml = array(
                "feed" => $feed,
                "items" => array()
            );

            return $this->open = true;
        }

        /**
         * Function: entry
         * Adds an individual feed entry.
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
        ): bool {
            if (!$this->open)
                return false;

            $this->count++;

            $entry = '<title type="html">'.
                     fix($title, false, true).
                     '</title>'.
                     "\n";

            $entry.= '<id>'.fix($id).'</id>'."\n";

            $entry.= '<updated>'.
                     when(DATE_ATOM, oneof($updated, $published)).
                     '</updated>'.
                     "\n";

            $entry.= '<published>'.
                     when(DATE_ATOM, $published).
                     '</published>'.
                     "\n";

            $entry.= '<link rel="alternate" type="text/html" href="'.
                     fix($link, true).
                     '" />'.
                     "\n";

            $entry.= '<author>'."\n";

            $entry.= '<name>'.
                     fix(oneof($name, __("Guest"))).
                     '</name>'.
                     "\n";

            if (!empty($uri) and is_url($uri))
                $entry.= '<uri>'.fix($uri).'</uri>'."\n";

            if (!empty($email) and is_email($email))
                $entry.= '<email>'.fix($email).'</email>'."\n";

            $entry.= '</author>'."\n";

            $entry.= '<content type="html">'.
                     fix($content, false, true).
                     '</content>'.
                     "\n";

            $item = $this->count - 1;
            $this->xml["items"][$item] = $entry;
            return true;
        }

        /**
         * Function: category
         * Adds a category element for an entry or feed.
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

            $category = '<category term="'.
                        fix($term, true).
                        '"';

            if (!empty($scheme))
                $category.= ' scheme="'.fix($scheme, true).'"';

            if (!empty($label))
                $category.= ' label="'.fix($label, true).'"';

            $category.= ' />'."\n";

            if (!$this->count) {
                $this->xml["feed"].= $category;
            } else {
                $item = $this->count - 1;
                $this->xml["items"][$item].= $category;
            }

            return true;
        }

        /**
         * Function: rights
         * Adds a rights element for an entry or feed.
         *
         * Parameters:
         *     $text - Human-readable licensing information.
         */
        public function rights(
            $text
        ): bool {
            if (!$this->open)
                return false;

            $rights = '<rights>'.
                      fix($text, false, true).
                      '</rights>'.
                      "\n";

            if (!$this->count) {
                $this->xml["feed"].= $rights;
            } else {
                $item = $this->count - 1;
                $this->xml["items"][$item].= $rights;
            }

            return true;
        }

        /**
         * Function: enclosure
         * Adds a link element for a resource that is potentially large in size.
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

            $enclosure = '<link rel="enclosure" href="'.
                         fix($link, true).
                         '"';

            if (!empty($length))
                $enclosure.= ' length="'.fix($length, true).'"';

            if (!empty($type))
                $enclosure.= ' type="'.fix($type, true).'"';

            if (!empty($title))
                $enclosure.= ' title="'.fix($title, true).'"';

            $enclosure.= ' />'."\n";

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
         * Adds a link element for a resource related to an entry or feed.
         *
         * Parameters:
         *     $link - The URL to the resource.
         */
        public function related(
            $link
        ): bool {
            if (!$this->open)
                return false;

            if (empty($link) or !is_url($link))
                return false;

            $related = '<link rel="related" href="'.
                       fix($link, true).
                       '" />'.
                       "\n";

            if (!$this->count) {
                $this->xml["feed"].= $related;
            } else {
                $item = $this->count - 1;
                $this->xml["items"][$item].= $related;
            }

            return true;
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
                $feed.= '<entry>'."\n".
                        $item.
                        '</entry>'."\n";
            }

            $feed.= '</feed>'."\n";
            return $feed;
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
