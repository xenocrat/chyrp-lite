<?php
    /**
     * Class: Page
     * The Page model.
     *
     * See Also:
     *     <Model>
     */
    class Page extends Model {
        const STATUS_LISTED  = "listed";
        const STATUS_PUBLIC  = "public";
        const STATUS_TEASED  = "teased";
        const STATUS_PRIVATE = "private";

        public $belongs_to = array(
            "user",
            "parent" => array("model" => "page")
        );

        public $has_many = array(
            "children" => array("model" => "page", "by" => "parent")
        );

        /**
         * Function: __construct
         *
         * See Also:
         *     <Model::grab>
         */
        public function __construct($page_id, $options = array()) {
            if (!isset($page_id) and empty($options))
                return;

            parent::grab($this, $page_id, $options);

            if ($this->no_results)
                return;

            $this->slug = $this->url;
            $this->filtered = (!isset($options["filter"]) or $options["filter"]);

            if ($this->public) {
                $this->status = ($this->show_in_list) ?
                    self::STATUS_LISTED :
                    self::STATUS_PUBLIC ;
            } else {
                $this->status = ($this->show_in_list) ?
                    self::STATUS_TEASED :
                    self::STATUS_PRIVATE ;
            }

            Trigger::current()->filter($this, "page");

            if ($this->filtered)
                $this->filter();
        }

        /**
         * Function: find
         *
         * See Also:
         *     <Model::search>
         */
        static function find(
            $options = array(),
            $options_for_object = array()
        ): array {
            return parent::search(
                self::class,
                $options,
                $options_for_object
            );
        }

        /**
         * Function: add
         * Adds a page to the database.
         *
         * Parameters:
         *     $title - The Title for the new page.
         *     $body - The Body for the new page.
         *     $user - The <User> or <User.id> of the page's author.
         *     $parent_id - The ID of the new page's parent page (0 for none).
         *     $public - Whether the page can be viewed without permission.
         *     $show_in_list - Whether or not to show it in the pages list.
         *     $list_order - The order of the page in the list.
         *     $clean - The slug for this page.
         *     $url - The unique URL (created from $clean by default).
         *     $created_at - The new page's "created" timestamp.
         *     $updated_at - The new page's "last updated" timestamp.
         *
         * Returns:
         *     The newly created <Page>.
         *
         * Notes:
         *     The caller is responsible for validating all supplied values.
         *
         * See Also:
         *     <update>
         */
        static function add(
            $title,
            $body,
            $user         = null,
            $parent_id    = 0,
            $public       = true,
            $show_in_list = true,
            $list_order   = 0,
            $clean        = "",
            $url          = "",
            $created_at   = null,
            $updated_at   = null
        ): self {
            $user_id = ($user instanceof User) ? $user->id : $user ;

            fallback($user_id,      Visitor::current()->id);
            fallback($parent_id,    0);
            fallback($public,       true);
            fallback($show_in_list, true);
            fallback($list_order,   0);
            fallback($clean,        slug(8));
            fallback($url,          self::check_url($clean));
            fallback($created_at,   datetime());
            fallback($updated_at,   SQL_DATETIME_ZERO); # Model->updated will check this.

            $sql = SQL::current();
            $trigger = Trigger::current();

            $new_values = array(
                "title"        => $title,
                "body"         => $body,
                "user_id"      => $user_id,
                "parent_id"    => $parent_id,
                "public"       => $public,
                "show_in_list" => $show_in_list,
                "list_order"   => $list_order,
                "clean"        => $clean,
                "url"          => $url,
                "created_at"   => $created_at,
                "updated_at"   => $updated_at
            );

            $trigger->filter($new_values, "before_add_page");
            $sql->insert(table:"pages", data:$new_values);
            $page = new self($sql->latest("pages"));
            $trigger->call("add_page", $page);
            return $page;
        }

        /**
         * Function: update
         * Updates the page.
         *
         * Parameters:
         *     $title - The new Title.
         *     $body - The new Body.
         *     $user - The <User> or <User.id> of the page's author.
         *     $parent_id - The new parent ID.
         *     $public - Whether the page can be viewed without permission.
         *     $show_in_list - Whether or not to show it in the pages list.
         *     $clean - A new slug for the page.
         *     $url - A new unique URL for the page (created from $clean by default).
         *     $created_at - The page's "created" timestamp.
         *     $updated_at - The page's "last updated" timestamp.
         *
         * Returns:
         *     The updated <Page>.
         *
         * Notes:
         *     The caller is responsible for validating all supplied values.
         */
        public function update(
            $title        = null,
            $body         = null,
            $user         = null,
            $parent_id    = null,
            $public       = null,
            $show_in_list = null,
            $list_order   = null,
            $clean        = null,
            $url          = null,
            $created_at   = null,
            $updated_at   = null
        ): self|false {
            if ($this->no_results)
                return false;

            $user_id = ($user instanceof User) ? $user->id : $user ;

            fallback(
                $title,
                ($this->filtered) ?
                                    $this->title_unfiltered :
                                    $this->title
            );
            fallback(
                $body,
                ($this->filtered) ?
                                    $this->body_unfiltered :
                                    $this->body
            );
            fallback($user_id,      $this->user_id);
            fallback($parent_id,    $this->parent_id);
            fallback($public,       $this->public);
            fallback($show_in_list, $this->show_in_list);
            fallback($list_order,   $this->list_order);
            fallback($clean,        $this->clean);
            fallback(
                $url,
                ($clean != $this->clean) ?
                                    self::check_url($clean) :
                                    $this->url
            );
            fallback($created_at,   $this->created_at);
            fallback($updated_at,   datetime());

            $sql = SQL::current();
            $trigger = Trigger::current();

            $new_values = array(
                "title"        => $title,
                "body"         => $body,
                "user_id"      => $user_id,
                "parent_id"    => $parent_id,
                "public"       => $public,
                "show_in_list" => $show_in_list,
                "list_order"   => $list_order,
                "clean"        => $clean,
                "url"          => $url,
                "created_at"   => $created_at,
                "updated_at"   => $updated_at
            );

            $trigger->filter($new_values, "before_update_page");

            $sql->update(
                table:"pages",
                conds:array("id" => $this->id),
                data:$new_values
            );

            $page = new self(
                null,
                array(
                    "read_from" => array_merge(
                        $new_values,
                        array("id" => $this->id)
                    )
                )
            );

            $trigger->call("update_page", $page, $this);
            return $page;
        }

        /**
         * Function: delete
         * Deletes the given page.
         *
         * Parameters:
         *     $page_id - The ID of the page to delete.
         *     $recursive - Should the page's children be deleted? (default: false)
         *
         * See Also:
         *     <Model::destroy>
         */
        static function delete($page_id, $recursive = false): void {
            if ($recursive) {
                $page = new self($page_id);

                foreach ($page->children as $child)
                    self::delete($child->id);
            }

            parent::destroy(self::class, $page_id);
        }

        /**
         * Function: exists
         * Checks if a page exists.
         *
         * Parameters:
         *     $page_id - The page ID to check
         */
        static function exists($page_id): bool {
            return SQL::current()->count(
                tables:"pages",
                conds:array("id" => $page_id)
            ) == 1;
        }

        /**
         * Function: check_url
         * Checks if a given URL value is already being used as another page's URL.
         *
         * Parameters:
         *     $url - The URL to check.
         *
         * Returns:
         *     The unique version of $url.
         *     If unused, it's the same as $url. If used, a number is appended to it.
         */
        static function check_url($url): string {
            if (empty($url))
                return $url;

            $count = 1;
            $unique = substr($url, 0, 128);

            while (
                SQL::current()->count(
                    tables:"pages",
                    conds:array("url" => $unique)
                )
            ) {
                $count++;
                $unique = substr($url, 0, (127 - strlen($count)))."-".$count;
            }

            return $unique;
        }

        /**
         * Function: filter
         * Filters the page attributes through filter_page and markup filters.
         */
        private function filter(): void {
            $trigger = Trigger::current();
            $trigger->filter($this, "filter_page");

            $this->title_unfiltered = $this->title;
            $this->body_unfiltered = $this->body;

            $trigger->filter($this->title, array("markup_page_title", "markup_title"), $this);
            $trigger->filter($this->body, array("markup_page_text", "markup_text"), $this);
        }

        /**
         * Function: from_url
         * Attempts to grab a page from its clean or dirty  URL.
         *
         * Parameters:
         *     $request - The request URI to parse.
         *     $route - The route to respond to, or null to return a Page.
         */
        static function from_url($request, $route = null): self|array|false {
            # Dirty URL?
            if (preg_match("/(\?|&)url=([^&#]+)/", $request, $slug)) {
                $page = new self(array("url" => $slug[2]));

                return isset($route) ?
                    $route->try["page"] = array($page) : $page ;
            }

            $hierarchy = explode(
                "/",
                trim(str_replace(Config::current()->url, "/", $request), "/")
            );

            $pages = self::find(
                array("where" => array("url" => $hierarchy))
            );

            # One of the URLs in the page hierarchy is invalid.
            if (!(count($pages) == count($hierarchy)))
                return false;

            # Loop over the pages until we find the one we want.
            foreach ($pages as $page) {
                if ($page->url == end($hierarchy))
                    return isset($route) ?
                        $route->try["page"] = array($page) : $page ;
            }
        }

        /**
         * Function: url
         * Returns a page's URL.
         */
        public function url(): string|false {
            if ($this->no_results)
                return false;

            $config = Config::current();

            if (!$config->clean_urls)
                return fix(
                    $config->url."/?action=page&url=".urlencode($this->url),
                    true
                );

            $url = array("", urlencode($this->url));

            $page = $this;

            while (isset($page->parent_id) and $page->parent_id > 0) {
                $url[] = urlencode($page->parent->url);
                $page = $page->parent;
            }

            return fix(
                $config->url."/".implode("/", array_reverse($url)),
                true
            );
        }

        /**
         * Function: author
         * Returns a page's author. Example: $page->author->name
         */
        public function author(): object|false {
            if ($this->no_results)
                return false;

            $author = (!$this->user->no_results) ?
                array(
                    "id"        => $this->user->id,
                    "name"      => oneof($this->user->full_name, $this->user->login),
                    "website"   => $this->user->website,
                    "email"     => $this->user->email,
                    "joined"    => $this->user->joined_at
                ) :
                array(
                    "id"      => 0,
                    "name"    => __("[Guest]"),
                    "website" => "",
                    "email"   => "",
                    "joined"  => $this->created_at
                ) ;

            return (object) $author;
        }
    }
