<?php
    /**
     * Class: Post
     * The Post model.
     *
     * See Also:
     *     <Model>
     */
    class Post extends Model {
        public $belongs_to = "user";

        # Array: $url_attrs
        # The translation array of the post URL setting to regular expressions.
        # Passed through the route_code filter.
        static $url_attrs = array('(year)'     => '([0-9]{4})',
                                  '(month)'    => '([0-9]{1,2})',
                                  '(day)'      => '([0-9]{1,2})',
                                  '(hour)'     => '([0-9]{1,2})',
                                  '(minute)'   => '([0-9]{1,2})',
                                  '(second)'   => '([0-9]{1,2})',
                                  '(id)'       => '([0-9]+)',
                                  '(author)'   => '([^\/]+)',
                                  '(clean)'    => '([^\/]+)',
                                  '(url)'      => '([^\/]+)',
                                  '(feather)'  => '([^\/]+)',
                                  '(feathers)' => '([^\/]+)');

        /**
         * Function: __construct
         * See Also:
         *     <Model::grab>
         */
        public function __construct($post_id = null, $options = array()) {
            if (!isset($post_id) and empty($options)) return;

            if (isset($options["where"]) and !is_array($options["where"]))
                $options["where"] = array($options["where"]);
            elseif (!isset($options["where"]))
                $options["where"] = array();

            $has_status = false;
            foreach ($options["where"] as $key => $val)
                if (is_int($key) and substr_count($val, "status") or $key == "status")
                    $has_status = true;

            if (!XML_RPC) {
                $options["where"][] = self::feathers();

                if (!$has_status) {
                    $visitor = Visitor::current();
                    $private = (isset($options["drafts"]) and $options["drafts"] and $visitor->group->can("view_draft")) ?
                                   self::statuses(array("draft")) :
                                   self::statuses() ;

                    if (isset($options["drafts"]) and $options["drafts"] and $visitor->group->can("view_own_draft")) {
                        $private.= " OR (status = 'draft' AND user_id = :visitor_id)";
                        $options["params"][":visitor_id"] = $visitor->id;
                    }

                    $options["where"][] = $private;
                }
            }

            $options["left_join"][] = array("table" => "post_attributes",
                                            "where" => "post_id = posts.id");
            $options["select"] = array_merge(array("posts.*",
                                                   "post_attributes.name AS attribute_names",
                                                   "post_attributes.value AS attribute_values"),
                                             oneof(@$options["select"], array()));
            $options["ignore_dupes"] = array("attribute_names", "attribute_values");

            parent::grab($this, $post_id, $options);

            if ($this->no_results)
                return false;

            $this->attribute_values = (array) $this->attribute_values;
            $this->attribute_names  = (array) $this->attribute_names;

            $this->attributes = ($this->attribute_names) ?
                                    array_combine($this->attribute_names, $this->attribute_values) :
                                    array() ;

            $this->filtered = (!isset($options["filter"]) or $options["filter"]) and !XML_RPC;
            $this->slug = $this->url;

            fallback($this->clean, $this->url);

            foreach($this->attributes as $key => $val)
                if (!empty($key)) {
                     $keys = array("body", "caption", "description", "dialogue");
                     if ( in_array( $key, $keys ) and Config::current()->enable_emoji)
                         $this->$key =  emote($val);
                     else
                         $this->$key =  $val;
                 }

            Trigger::current()->filter($this, "post");

            if ($this->filtered)
                $this->filter();
        }

        /**
         * Function: find
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array(), $debug = false) {
            if (isset($options["where"]) and !is_array($options["where"]))
                $options["where"] = array($options["where"]);
            elseif (!isset($options["where"]))
                $options["where"] = array();

            $has_status = false;
            foreach ($options["where"] as $key => $val)
                if ((is_int($key) and substr_count($val, "status")) or $key === "status")
                    $has_status = true;

            if (!XML_RPC) {
                $options["where"][] = self::feathers();

                if (!$has_status) {
                    $visitor = Visitor::current();
                    $private = (isset($options["drafts"]) and $options["drafts"] and $visitor->group->can("view_draft")) ?
                                   self::statuses(array("draft")) :
                                   self::statuses() ;

                    if (isset($options["drafts"]) and $options["drafts"] and $visitor->group->can("view_own_draft")) {
                        $private.= " OR (status = 'draft' AND user_id = :visitor_id)";
                        $options["params"][":visitor_id"] = $visitor->id;
                    }

                    $options["where"][] = $private;
                }
            }

            $options["left_join"][] = array("table" => "post_attributes",
                                            "where" => "post_id = posts.id");
            $options["select"] = array_merge(array("posts.*",
                                                   "post_attributes.name AS attribute_names",
                                                   "post_attributes.value AS attribute_values"),
                                             oneof(@$options["select"], array()));
            $options["ignore_dupes"] = array("attribute_names", "attribute_values");

            fallback($options["order"], "pinned DESC, created_at DESC, id DESC");

            return parent::search(get_class(), $options, $options_for_object);
        }

        /**
         * Function: add
         * Adds a post to the database.
         *
         * Most of the function arguments will fall back to various POST values.
         *
         * Calls the @add_post@ trigger with the inserted post and extra options.
         *
         * Note: The default parameter values are empty here so that the fallbacks work properly.
         *
         * Parameters:
         *     $values - The data to insert.
         *     $clean - The sanitized URL (or empty to default to "(feather).(new post's id)").
         *     $url - The unique URL (or empty to default to "(feather).(new post's id)").
         *     $feather - The feather to post as.
         *     $user - <User> to set as the post's author.
         *     $pinned - Pin the post?
         *     $status - Post status
         *     $created_at - New @created_at@ timestamp for the post.
         *     $updated_at - New @updated_at@ timestamp for the post, or @false@ to not updated it.
         *     $trackbacks - URLs separated by " " to send trackbacks to.
         *     $pingbacks - Send pingbacks?
         *     $options - Options for the post.
         *
         * Returns:
         *     The newly created <Post>.
         *
         * See Also:
         *     <update>
         */
        static function add($values     = array(),
                            $clean      = "",
                            $url        = "",
                            $feather    = null,
                            $user       = null,
                            $pinned     = null,
                            $status     = "",
                            $created_at = null,
                            $updated_at = null,
                            $trackbacks = "",
                            $pingbacks  = true,
                            $options    = array()) {
            $user_id = ($user instanceof User) ? $user->id : $user ;

            $sql = SQL::current();
            $visitor = Visitor::current();
            $trigger = Trigger::current();

            fallback($feather,    oneof(@$_POST['feather'], ""));
            fallback($user_id,    oneof(@$_POST['user_id'], Visitor::current()->id));
            fallback($pinned,     (int) !empty($_POST['pinned']));
            fallback($status,     (isset($_POST['draft'])) ? "draft" : oneof(@$_POST['status'], "public"));
            fallback($created_at, (!empty($_POST['created_at']) and
                                  (!isset($_POST['original_time']) or $_POST['created_at'] != $_POST['original_time'])) ?
                                      datetime($_POST['created_at']) :
                                      datetime());
            fallback($updated_at, oneof(@$_POST['updated_at'], $created_at));
            fallback($trackbacks, oneof(@$_POST['trackbacks'], ""));
            fallback($options,    oneof(@$_POST['option'], array()));

            if (isset($clean) and !isset($url))
                $url = self::check_url($clean);

            $new_values = array("feather"    => $feather,
                                "user_id"    => $user_id,
                                "pinned"     => $pinned,
                                "status"     => $status,
                                "clean"      => $clean,
                                "url"        => $url,
                                "created_at" => $created_at,
                                "updated_at" => $updated_at);

            $trigger->filter($new_values, "before_add_post");

            $sql->insert("posts", $new_values);

            $id = $sql->latest("posts");

            if (empty($clean) or empty($url))
                $sql->update("posts",
                             array("id"    => $id),
                             array("clean" => $feather.".".$id,
                                   "url"   => $feather.".".$id));

            # Insert the post attributes.
            foreach (array_merge($values, $options) as $name => $value)
                $sql->insert("post_attributes",
                             array("post_id" => $id,
                                   "name"    => $name,
                                   "value"   => $value));

            $post = new self($id, array("drafts" => true));

            if ($trackbacks !== "") {
                $trackbacks = explode(",", $trackbacks);
                $trackbacks = array_map("trim", $trackbacks);
                $trackbacks = array_map("strip_tags", $trackbacks);
                $trackbacks = array_unique($trackbacks);
                $trackbacks = array_diff($trackbacks, array(""));

                foreach ($trackbacks as $url)
                    trackback_send($post, $url);
            }

            if (Config::current()->send_pingbacks and $pingbacks)
                foreach ($values as $key => $value)
                    send_pingbacks($value, $post);

            $post->redirect = $post->url();

            $trigger->call("add_post", $post, $options);

            return $post;
        }

        /**
         * Function: update
         * Updates a post with the given attributes.
         *
         * Most of the function arguments will fall back to various POST values.
         *
         * Parameters:
         *     $values - An array of data to set for the post.
         *     $user - <User> to set as the post's author.
         *     $pinned - Pin the post?
         *     $status - Post status
         *     $clean - A new clean URL for the post.
         *     $url - A new URL for the post.
         *     $created_at - New @created_at@ timestamp for the post.
         *     $updated_at - New @updated_at@ timestamp for the post, or @false@ to not updated it.
         *     $options - Options for the post.
         *
         * See Also:
         *     <add>
         */
        public function update($values     = null,
                               $user       = null,
                               $pinned     = null,
                               $status     = null,
                               $clean      = null,
                               $url        = null,
                               $created_at = null,
                               $updated_at = null,
                               $options    = null) {
            if ($this->no_results)
                return false;

            $trigger = Trigger::current();

            $user_id = ($user instanceof User) ? $user->id : $user ;

            fallback($values,     array_combine($this->attribute_names, $this->attribute_values));
            fallback($user_id,    oneof(@$_POST['user_id'], $this->user_id));
            fallback($pinned,     (int) !empty($_POST['pinned']));
            fallback($status,     (isset($_POST['draft'])) ? "draft" : oneof(@$_POST['status'], $this->status));
            fallback($clean,      $this->clean);
            fallback($url,        oneof(@$_POST['slug'], $this->feather.".".$this->id));
            fallback($created_at, (!empty($_POST['created_at'])) ? datetime($_POST['created_at']) : $this->created_at);
            fallback($updated_at, ($updated_at === false ?
                                      $this->updated_at :
                                      oneof($updated_at, @$_POST['updated_at'], datetime())));
            fallback($options,    oneof(@$_POST['option'], array()));

            if ($url != $this->url) # If they edited the slug, the clean URL should change too.
                $clean = $url;

            $old = clone $this;

            # Update all values of this post.
            foreach (array("user_id", "pinned", "status", "url", "created_at", "updated_at") as $attr)
                $this->$attr = $$attr;

            $new_values = array("pinned"     => $pinned,
                                "status"     => $status,
                                "clean"      => $clean,
                                "url"        => $url,
                                "created_at" => $created_at,
                                "updated_at" => $updated_at);

            $trigger->filter($new_values, "before_update_post");

            $sql = SQL::current();
            $sql->update("posts",
                         array("id" => $this->id),
                         $new_values);

            # Insert the post attributes.
            foreach (array_merge($values, $options) as $name => $value)
                if ($sql->count("post_attributes", array("post_id" => $this->id, "name" => $name)))
                    $sql->update("post_attributes",
                                 array("post_id" => $this->id,
                                       "name" => $name),
                                 array("value" => $this->$name = $value));
                else
                    $sql->insert("post_attributes",
                                 array("post_id" => $this->id,
                                       "name" => $name,
                                       "value" => $this->$name = $value));

            $trigger->call("update_post", $this, $old, $options);
        }

        /**
         * Function: delete
         * See Also:
         *     <Model::destroy>
         */
        static function delete($id) {
            parent::destroy(get_class(), $id);
            SQL::current()->delete("post_attributes", array("post_id" => $id));
        }

        /**
         * Function: deletable
         * Checks if the <User> can delete the post.
         */
        public function deletable($user = null) {
            if ($this->no_results)
                return false;

            fallback($user, Visitor::current());
            if ($user->group->can("delete_post"))
                return true;

            return ($this->status == "draft" and $user->group->can("delete_draft")) or
                   ($user->group->can("delete_own_post") and $this->user_id == $user->id) or
                   (($user->group->can("delete_own_draft") and $this->status == "draft") and $this->user_id == $user->id);
        }

        /**
         * Function: editable
         * Checks if the <User> can edit the post.
         */
        public function editable($user = null) {
            if ($this->no_results)
                return false;

            fallback($user, Visitor::current());
            if ($user->group->can("edit_post"))
                return true;

            return ($this->status == "draft" and $user->group->can("edit_draft")) or
                   ($user->group->can("edit_own_post") and $this->user_id == $user->id) or
                   (($user->group->can("edit_own_draft") and $this->status == "draft") and $this->user_id == $user->id);
        }

        /**
         * Function: any_editable
         * Checks if the <Visitor> can edit any posts.
         */
        static function any_editable() {
            $visitor = Visitor::current();
            $sql = SQL::current();

            # Can they edit posts?
            if ($visitor->group->can("edit_post"))
                return true;

            # Can they edit drafts?
            if ($visitor->group->can("edit_draft") and
                $sql->count("posts", array("status" => "draft")))
                return true;

            # Can they edit their own posts, and do they have any?
            if ($visitor->group->can("edit_own_post") and
                $sql->count("posts", array("user_id" => $visitor->id)))
                return true;

            # Can they edit their own drafts, and do they have any?
            if ($visitor->group->can("edit_own_draft") and
                $sql->count("posts", array("status" => "draft", "user_id" => $visitor->id)))
                return true;

            return false;
        }

        /**
         * Function: any_deletable
         * Checks if the <Visitor> can delete any posts.
         */
        static function any_deletable() {
            $visitor = Visitor::current();
            $sql = SQL::current();

            # Can they delete posts?
            if ($visitor->group->can("delete_post"))
                return true;

            # Can they delete drafts?
            if ($visitor->group->can("delete_draft") and
                $sql->count("posts", array("status" => "draft")))
                return true;

            # Can they delete their own posts, and do they have any?
            if ($visitor->group->can("delete_own_post") and
                $sql->count("posts", array("user_id" => $visitor->id)))
                return true;

            # Can they delete their own drafts, and do they have any?
            if ($visitor->group->can("delete_own_draft") and
                $sql->count("posts", array("status" => "draft", "user_id" => $visitor->id)))
                return true;

            return false;
        }

        /**
         * Function: exists
         * Checks if a post exists.
         *
         * Parameters:
         *     $post_id - The post ID to check
         *
         * Returns:
         *     true - if a post with that ID is in the database.
         */
        static function exists($post_id) {
            return SQL::current()->count("posts", array("id" => $post_id)) == 1;
        }

        /**
         * Function: check_url
         * Checks if a given clean URL is already being used as another post's URL.
         *
         * Parameters:
         *     $clean - The clean URL to check.
         *
         * Returns:
         *     The unique version of the passed clean URL. If it's not used, it's the same as $clean. If it is, a number is appended.
         */
        static function check_url($clean) {
            $count = SQL::current()->count("posts", array("clean" => $clean));
            return (!$count or empty($clean)) ? $clean : $clean."-".($count + 1) ;
        }

        /**
         * Function: url
         * Returns a post's URL.
         */
        public function url() {
            if ($this->no_results)
                return false;

            $config = Config::current();
            $visitor = Visitor::current();

            if (!$config->clean_urls)
                return $config->url."/?action=view&amp;url=".urlencode($this->url);

            $login = (strpos($config->post_url, "(author)") !== false) ? $this->user->login : null ;
            $vals = array(when("Y", $this->created_at),
                          when("m", $this->created_at),
                          when("d", $this->created_at),
                          when("H", $this->created_at),
                          when("i", $this->created_at),
                          when("s", $this->created_at),
                          $this->id,
                          urlencode($login),
                          urlencode($this->clean),
                          urlencode($this->url),
                          urlencode($this->feather),
                          urlencode(pluralize($this->feather)));

            Trigger::current()->filter($vals, "url_vals", $this);
            return $config->url."/".str_replace(array_keys(self::$url_attrs), $vals, $config->post_url);
        }

        /**
         * Function: title_from_excerpt
         * Generates an acceptable Title from the post's excerpt.
         *
         * Returns:
         *     The post's excerpt. filtered -> first line -> ftags stripped -> truncated to 75 characters -> normalized.
         */
        public function title_from_excerpt() {
            if ($this->no_results)
                return false;

            # Excerpts are likely to have some sort of markup module applied to them;
            # if the current instantiation is not filtered, make one that is.
            $post = ($this->filtered) ? $this : new Post($this->id) ;

            $excerpt = $post->excerpt();
            Trigger::current()->filter($excerpt, "title_from_excerpt");

            $split_lines = explode("\n", $excerpt);
            $first_line = $split_lines[0];

            $stripped = strip_tags($first_line); # Strip all HTML
            $truncated = truncate($stripped, 75); # Truncate the excerpt to 75 characters
            $normalized = normalize($truncated); # Trim and normalize whitespace

            return $normalized;
        }

        /**
         * Function: title
         * Returns the given post's title, provided by its Feather.
         */
        public function title() {
            if ($this->no_results)
                return false;

            # Excerpts are likely to have some sort of markup module applied to them;
            # if the current instantiation is not filtered, make one that is.
            $post = ($this->filtered) ? $this : new Post($this->id) ;

            $title = Feathers::$instances[$this->feather]->title($post);
            return Trigger::current()->filter($title, "title", $post);
        }


        /**
         * Function: excerpt
         * Returns the given post's excerpt, provided by its Feather.
         */
        public function excerpt() {
            if ($this->no_results)
                return false;

            # Excerpts are likely to have some sort of markup module applied to them;
            # if the current instantiation is not filtered, make one that is.
            $post = ($this->filtered) ? $this : new Post($this->id) ;

            $excerpt = Feathers::$instances[$this->feather]->excerpt($post);
            return Trigger::current()->filter($excerpt, "excerpt", $post);
        }


        /**
         * Function: feed_content
         * Returns the given post's Feed content, provided by its Feather.
         */
        public function feed_content() {
            if ($this->no_results)
                return false;

            # Excerpts are likely to have some sort of markup module applied to them;
            # if the current instantiation is not filtered, make one that is.
            $post = ($this->filtered) ? $this : new Post($this->id) ;

            $feed_content = Feathers::$instances[$this->feather]->feed_content($post);
            return Trigger::current()->filter($feed_content, "feed_content", $post);
        }

        /**
         * Function: next
         * Returns:
         *     The next post (the post made before this one).
         */
        public function next() {
            if ($this->no_results)
                return false;

            if (isset($this->next))
                return $this->next;

            return $this->next = new self(null, array("where" => array("created_at <" => $this->created_at,
                                                                       $this->status == "draft" ?
                                                                           self::statuses(array("draft")) :
                                                                           self::statuses()),
                                                      "order" => "created_at DESC, id DESC"));
        }

        /**
         * Function: prev
         * Returns:
         *     The previous post (the post made after this one).
         */
        public function prev() {
            if ($this->no_results)
                return false;

            if (isset($this->prev))
                return $this->prev;

            return $this->prev = new self(null, array("where" => array("created_at >" => $this->created_at,
                                                                       ($this->status == "draft" ?
                                                                           self::statuses(array("draft")) :
                                                                           self::statuses())),
                                                      "order" => "created_at ASC, id ASC"));
        }

        /**
         * Function: theme_exists
         * Checks if the current post's feather theme file exists.
         */
        public function theme_exists() {
            return !$this->no_results and Theme::current()->file_exists("feathers/".$this->feather);
        }

        /**
         * Function: filter
         * Filters the post attributes through filter_post and any Feather filters.
         */
        private function filter() {
            $trigger = Trigger::current();
            $class = camelize($this->feather);

            $trigger->filter($this, "filter_post");

            if (isset(Feathers::$custom_filters[$class])) # Run through feather-specified filters, first.
                foreach (Feathers::$custom_filters[$class] as $custom_filter) {
                    $varname = $custom_filter["field"]."_unfiltered";
                    if (!isset($this->$varname))
                        $this->$varname = @$this->$custom_filter["field"];

                    $this->$custom_filter["field"] = call_user_func_array(array(Feathers::$instances[$this->feather], $custom_filter["name"]),
                                                                          array($this->$custom_filter["field"], $this));
                }

            if (isset(Feathers::$filters[$class])) # Now actually filter it.
                foreach (Feathers::$filters[$class] as $filter) {
                    $varname = $filter["field"]."_unfiltered";
                    if (!isset($this->$varname))
                        $this->$varname = @$this->$filter["field"];

                    if (isset($this->$filter["field"]) and !empty($this->$filter["field"]))
                        $trigger->filter($this->$filter["field"], $filter["name"], $this);
                }
        }

        /**
         * Function: trackback_url
         * Returns the posts trackback URL.
         */
        public function trackback_url() {
            if ($this->no_results) return
                false;

            return Config::current()->chyrp_url."/includes/trackback.php?id=".$this->id;
        }

        /**
         * Function: from_url
         * Attempts to grab a post from its clean URL.
         */
        static function from_url($attrs = null, $options = array()) {
            fallback($attrs, $_GET);

            $where = array();
            $times = array("year", "month", "day", "hour", "minute", "second");

            preg_match_all("/\(([^\)]+)\)/", Config::current()->post_url, $matches);
            $params = array();
            foreach ($matches[1] as $attr)
                if (in_array($attr, $times))
                    $where[strtoupper($attr)."(created_at)"] = $attrs[$attr];
                elseif ($attr == "author") {
                    $user = new User(array("login" => $attrs['author']));
                    $where["user_id"] = $user->id;
                } elseif ($attr == "feathers")
                    $where["feather"] = depluralize($attrs['feathers']);
                else {
                    $tokens = array($where, $params, $attr);
                    Trigger::current()->filter($tokens, "post_url_token");
                    list($where, $params, $attr) = $tokens;

                    if ($attr !== null) {
                        if (!isset($attrs[$attr]))
                            continue;

                        $where[$attr] = $attrs[$attr];
                    }
                }

            return new self(null, array_merge($options, array("where" => $where, "params" => $params)));
        }

        /**
         * Function: statuses
         * Returns a SQL query "chunk" for the "status" column permissions of the current user.
         *
         * Parameters:
         *     $start - An array of additional statuses to allow; "registered_only", "private" and "scheduled" are added deterministically.
         */
        static function statuses($start = array()) {
            $visitor = Visitor::current();

            $statuses = array_merge(array("public"), $start);

            if (logged_in())
                $statuses[] = "registered_only";

            if ($visitor->group->can("view_private"))
                $statuses[] = "private";

            if ($visitor->group->can("view_scheduled"))
                $statuses[] = "scheduled";

            return "(posts.status IN ('".implode("', '", $statuses)."') OR posts.status LIKE '%{".$visitor->group->id."}%') OR (posts.status LIKE '%{%' AND posts.user_id = ".$visitor->id.")";
        }

        /**
         * Function: enabled_feathers
         * Returns a SQL query "chunk" for the "feather" column so that it matches enabled feathers.
         */
        static function feathers() {
            return "posts.feather IN ('".implode("', '", Config::current()->enabled_feathers)."')";
        }

        /**
         * Function: author
         * Returns a post's author. Example: $post->author->name
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

        /**
         * Function: featured_image
         * Returns:
         *     A selected post image. Usage: $post->featured_image
         */
        public function featured_image($width = 210, $order = 0, $html = true) {
            $config = Config::current();

            $pattern = '/<img[^>]+src=[\'"]' . preg_quote($config->chyrp_url.$config->uploads_path, '/') . '([^\'"]+)[\'"][^>]*>/i';
            $output = preg_match_all($pattern, $this->body, $matches);

            $image = $matches[1][$order];
            if (empty($image)) return;

            if (!$html) return $config->chyrp_url.'/includes/thumb.php?file=..'.$config->uploads_path.urlencode($image).'&amp;max_width='.$width;
            else return '<img src="'.$config->chyrp_url.'/includes/thumb.php?file=..'.$config->uploads_path.urlencode($image).'&amp;max_width='.$width.'" alt="'.$this->title.'" class="featured_image" />';
        }

        /**
         * Function: user
         * Returns a post's user. Example: $post->user->login
         * 
         * !! DEPRECATED AFTER 2.0 !!
         */
        public function user() {
            deprecated("\$post.user", "2.0", "\$post.author", debug_backtrace());
            return self::author();
        }

        /**
         * Function: groups
         * Lists the groups who can view the post if the post's status is specific to certain groups.
         */
        public function groups() {
            if ($this->no_results)
                return false;

            preg_match_all("/\{([0-9]+)\}/", $this->status, $groups, PREG_PATTERN_ORDER);
            if (empty($groups[1]))
                return false;

            $names = array();
            foreach ($groups[1] as $group_id) {
                $group = new Group($group_id);
                $names[] = $group->name;
            }

            return list_notate($names);
        }
    }
