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
        static function add($title,
                            $body,
                            $user         = null,
                            $parent_id    = 0,
                            $public       = true,
                            $show_in_list = true,
                            $list_order   = 0,
                            $clean        = "",
                            $url          = "",
                            $created_at   = null,
                            $updated_at   = null) {
            $user_id = ($user instanceof User) ? $user->id : $user ;

            fallback($user_id,      Visitor::current()->id);
            fallback($parent_id,    0);
            fallback($public,       true);
            fallback($show_in_list, true);
            fallback($list_order,   0);
            fallback($clean,        sanitize(@$_POST['slug'], true, true, 80), slug(8));
            fallback($url,          self::check_url($clean));
            fallback($created_at,   datetime());
            fallback($updated_at,   "0000-00-00 00:00:00"); # Model->updated will check this.

            $sql = SQL::current();
            $trigger = Trigger::current();

            $new_values = array("title" =>        $title,
                                "body" =>         $body,
                                "user_id" =>      $user_id,
                                "parent_id" =>    $parent_id,
                                "public" =>       $public,
                                "show_in_list" => $show_in_list,
                                "list_order" =>   $list_order,
                                "clean" =>        $clean,
                                "url" =>          $url,
                                "created_at" =>   $created_at,
                                "updated_at" =>   $updated_at);

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
         *     $public - Whether the page can be viewed without permission.
         *     $show_in_list - Whether or not to show it in the pages list.
         *     $clean - A new slug for the page.
         *     $url - A new unique URL for the page (created from $clean by default).
         *     $created_at - The page's "created" timestamp.
         *     $updated_at - The page's "last updated" timestamp.
         *
         * Notes:
         *     The caller is responsible for validating all supplied values.
         */
        public function update($title        = null,
                               $body         = null,
                               $user         = null,
                               $parent_id    = null,
                               $public       = null,
                               $show_in_list = null,
                               $list_order   = null,
                               $clean        = null,
                               $url          = null,
                               $created_at   = null,
                               $updated_at   = null) {
            if ($this->no_results)
                return false;

            $old = clone $this;
            $user_id = ($user instanceof User) ? $user->id : $user ;

            fallback($title,        $this->title);
            fallback($body,         $this->body);
            fallback($user_id,      $this->user_id);
            fallback($parent_id,    $this->parent_id);
            fallback($public,       $this->public);
            fallback($show_in_list, $this->show_in_list);
            fallback($list_order,   $this->list_order);
            fallback($clean,        (!empty($_POST['slug']) and $_POST['slug'] != $this->clean) ?
                                        oneof(sanitize($_POST['slug'], true, true, 80), slug(8)) :
                                        $this->clean);
            fallback($url,          ($clean != $this->clean) ?
                                        self::check_url($clean) :
                                        $this->url);
            fallback($created_at,   $this->created_at);
            fallback($updated_at,   datetime());

            $sql = SQL::current();
            $trigger = Trigger::current();

            # Update all values of this page.
            foreach (array("title", "body", "user_id", "parent_id", "public", "show_in_list",
                           "list_order", "clean", "url", "created_at", "updated_at") as $attr)
                $this->$attr = $$attr;

            $new_values = array("title" =>        $title,
                                "body" =>         $body,
                                "user_id" =>      $user_id,
                                "parent_id" =>    $parent_id,
                                "public" =>       $public,
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
         *     The unique version of the passed clean URL.
         *     If it's not used, it's the same as $clean. If it is, a number is appended.
         */
        static function check_url($clean) {
            $count = SQL::current()->count("pages", array("clean" => $clean));
            return (!$count or empty($clean)) ? $clean : $clean."-".($count + 1) ;
        }

        /**
         * Function: from_url
         * Attempts to grab a page from its clean URL.
         *
         * Parameters:
         *     $request - The request URI to parse.
         *     $route - The route object to respond to, or null to return a Page object.
         */
        static function from_url($request, $route = null) {
            $hierarchy = explode("/", trim(str_replace(Config::current()->url, "/", $request), "/"));
            $pages = self::find(array("where" => array("url" => $hierarchy)));

            # One of the URLs in the page hierarchy is invalid.
            if (!(count($pages) == count($hierarchy)))
                return false;

            # Loop over the pages until we find the one we want.
            foreach ($pages as $page)
                if ($page->url == end($hierarchy))
                    return isset($route) ? $route->try["page"] = array($page->url, $hierarchy) : $page ;
        }

        /**
         * Function: url
         * Returns a page's URL. We can cheat because we know the inner workings of MainController.
         */
        public function url() {
            if ($this->no_results)
                return false;

            $config = Config::current();

            if (!$config->clean_urls)
                return $config->url."/?action=page&amp;url=".urlencode($this->url);

            $url = array("", urlencode($this->url));

            $page = $this;

            while (isset($page->parent_id) and $page->parent_id > 0) {
                $url[] = urlencode($page->parent->url);
                $page = $page->parent;
            }

            return $config->url."/".implode("/", array_reverse($url));
        }

        /**
         * Function: author
         * Returns a page's author. Example: $page->author->name
         */
        public function author() {
            if ($this->no_results)
                return false;

            $author = array("nick"    => $this->user->login,
                            "name"    => oneof($this->user->full_name, $this->user->login),
                            "website" => $this->user->website,
                            "email"   => $this->user->email,
                            "joined"  => $this->user->joined_at,
                            "group"   => $this->user->group->name);

            return (object) $author;
        }
    }
