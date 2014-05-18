<?php
    /**
     * Class: Page
     * The Page model.
     *
     * See Also:
     *     <Model>
     */
    class Page extends Model {
        public $belongs_to = array("user", "parent" => array("model" => "page"));

        public $has_many = array("children" => array("model" => "page", "by" => "parent"));

        /**
         * Function: __construct
         * See Also:
         *     <Model::grab>
         */
        public function __construct($page_id, $options = array()) {
            if (!isset($page_id) and empty($options)) return;
            parent::grab($this, $page_id, $options);

            if ($this->no_results)
                return false;

            $this->slug = $this->url;

            $this->filtered = !isset($options["filter"]) or $options["filter"];

            $trigger = Trigger::current();

            $trigger->filter($this, "page");

            if ($this->filtered) {
                $this->title_unfiltered = $this->title;
                $this->body_unfiltered = $this->body = (Config::current()->enable_emoji) ? emote($this->body) : $this->body ;

                $trigger->filter($this->title, array("markup_title", "markup_page_title"), $this);
                $trigger->filter($this->body, array("markup_text", "markup_page_text"), $this);

                $trigger->filter($this, "filter_page");
            }
        }

        /**
         * Function: find
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array()) {
            return parent::search(get_class(), $options, $options_for_object);
        }

        /**
         * Function: add
         * Adds a page to the database.
         *
         * Calls the @add_page@ trigger with the new <Page>.
         *
         * Parameters:
         *     $title - The Title for the new page.
         *     $body - The Body for the new page.
         *     $user - The <User> or <User.id> of the page's author.
         *     $parent_id - The ID of the new page's parent page (0 for none).
         *     $show_in_list - Whether or not to show it in the pages list.
         *     $list_order - The order of the page in the list.
         *     $clean - The clean URL.
         *     $url - The unique URL.
         *     $created_at - The new page's "created" timestamp.
         *     $updated_at - The new page's "last updated" timestamp.
         *
         * Returns:
         *     The newly created <Page>.
         *
         * See Also:
         *     <update>
         */
        static function add($title,
                            $body,
                            $user         = null,
                            $parent_id    = 0,
                            $show_in_list = true,
                            $list_order   = 0,
                            $clean        = "",
                            $url          = "",
                            $created_at   = null,
                            $updated_at   = null) {
            $user_id = ($user instanceof User) ? $user->id : $user ;

            $sql = SQL::current();
            $visitor = Visitor::current();
            $trigger = Trigger::current();

            $new_values = array("title" =>        $title,
                                "body" =>         $body,
                                "user_id" =>      oneof($user_id,      $visitor->id),
                                "parent_id" =>    oneof($parent_id,    0),
                                "show_in_list" => oneof($show_in_list, true),
                                "list_order" =>   oneof($list_order,   0),
                                "clean" =>        oneof($clean,        sanitize($title)),
                                "url" =>          oneof($url,          self::check_url($clean)),
                                "created_at" =>   oneof($created_at,   datetime()),
                                "updated_at" =>   oneof($updated_at,   datetime()));

            $trigger->filter($new_values, "before_add_page");

            $sql->insert("pages", $new_values);

            $page = new self($sql->latest("pages"));

            $trigger->call("add_page", $page);

            return $page;
        }

        /**
         * Function: update
         * Updates the page.
         * 
         * Calls the @update_page@ trigger with the updated <Page> and the original <Page>.
         *
         * Parameters:
         *     $title - The new Title.
         *     $body - The new Body.
         *     $user - The <User> or <User.id> of the page's author.
         *     $parent_id - The new parent ID.
         *     $show_in_list - Whether or not to show it in the pages list.
         *     $clean - The page's clean URL.
         *     $url - The page's unique URL.
         *     $created_at - The page's "created" timestamp.
         *     $updated_at - The page's "last updated" timestamp.
         */
        public function update($title        = null,
                               $body         = null,
                               $user         = null,
                               $parent_id    = null,
                               $show_in_list = null,
                               $list_order   = null,
                               $clean        = null,
                               $url          = null,
                               $created_at   = null,
                               $updated_at   = null) {
            if ($this->no_results)
                return false;

            $user_id = ($user instanceof User) ? $user->id : $user ;

            $sql = SQL::current();
            $trigger = Trigger::current();

            $old = clone $this;

            foreach (array("title", "body", "user_id", "parent_id", "show_in_list",
                           "list_order", "clean", "url", "created_at", "updated_at") as $attr)
                if ($attr == "updated_at" and $$attr === null)
                    $this->$attr = $$attr = datetime();
                else
                    $this->$attr = $$attr = ($$attr !== null ? $$attr : $this->$attr);

            $new_values = array("title" =>        $title,
                                "body" =>         $body,
                                "user_id" =>      $user_id,
                                "parent_id" =>    $parent_id,
                                "show_in_list" => $show_in_list,
                                "list_order" =>   $list_order,
                                "clean" =>        $clean,
                                "url" =>          $url,
                                "created_at" =>   $created_at,
                                "updated_at" =>   $updated_at);

            $trigger->filter($new_values, "before_update_page");

            $sql->update("pages",
                         array("id" => $this->id),
                         $new_values);

            $trigger->call("update_page", $this, $old);
        }

        /**
         * Function: delete
         * Deletes the given page.
         * 
         * Calls the @delete_page@ trigger with the <Page> to delete.
         *
         * Parameters:
         *     $page_id - The page to delete.
         *     $recursive - Should the page's children be deleted? (default: false)
         */
        static function delete($page_id, $recursive = false) {
            if ($recursive) {
                $page = new self($page_id);
                foreach ($page->children as $child)
                    self::delete($child->id);
            }

            parent::destroy(get_class(), $page_id);
        }

        /**
         * Function: exists
         * Checks if a page exists.
         *
         * Parameters:
         *     $page_id - The page ID to check
         */
        static function exists($page_id) {
            return SQL::current()->count("pages", array("id" => $page_id)) == 1;
        }

        /**
         * Function: check_url
         * Checks if a given clean URL is already being used as another page's URL.
         *
         * Parameters:
         *     $clean - The clean URL to check.
         *
         * Returns:
         *     The unique version of the passed clean URL. If it's not used, it's the same as $clean. If it is, a number is appended.
         */
        static function check_url($clean) {
            $count = SQL::current()->count("pages", array("clean" => $clean));
            return (!$count or empty($clean)) ? $clean : $clean."-".($count + 1) ;
        }

        /**
         * Function: url
         * Returns a page's URL.
         */
        public function url() {
            if ($this->no_results)
                return false;

            $config = Config::current();
            if (!$config->clean_urls)
                return $config->url."/?action=page&amp;url=".urlencode($this->url);

            $url = array("", urlencode($this->url));

            $page = $this;
            while (isset($page->parent_id) and $page->parent_id) {
                $url[] = urlencode($page->parent->url);
                $page = $page->parent;
            }

            return url("page/".implode("/", array_reverse($url)), MainController::current());
        }

        /**
         * Function: parent
         * Returns a page's parent. Example: $page->parent()->parent()->title
         * 
         * !! DEPRECATED AFTER 2.0 !!
         */
        public function parent() {
            if ($this->no_results or !$this->parent_id)
                return false;

            return new self($this->parent_id);
        }

        /**
         * Function: children
         * Returns a page's children.
         * 
         * !! DEPRECATED AFTER 2.0 !!
         */
        public function children() {
            if ($this->no_results)
                return false;

            return self::find(array("where" => array("parent_id" => $this->id)));
        }

        /**
         * Function: user
         * Returns a page's creator. Example: $page->user->full_name
         * 
         * !! DEPRECATED AFTER 2.0 !!
         */
        public function user() {
            if ($this->no_results)
                return false;

            return new User($this->user_id);
        }
    }
