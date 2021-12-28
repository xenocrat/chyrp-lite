<?php
    /**
     * Class: Post
     * The Post model.
     *
     * See Also:
     *     <Model>
     */
    class Post extends Model {
        const STATUS_PUBLIC    = "public";
        const STATUS_DRAFT     = "draft";
        const STATUS_REG_ONLY  = "registered_only";
        const STATUS_PRIVATE   = "private";
        const STATUS_SCHEDULED = "scheduled";

        public $belongs_to = "user";

        # Array: $url_attrs
        # The translation array of the post URL setting to regular expressions.
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
            if (!isset($post_id) and empty($options))
                return false;

            if (isset($options["where"]) and !is_array($options["where"]))
                $options["where"] = array($options["where"]);
            elseif (!isset($options["where"]))
                $options["where"] = array();

            $has_status = false;
            $skip_where = (isset($options["skip_where"]) and $options["skip_where"]);

            foreach ($options["where"] as $key => $val)
                if (is_int($key) and substr_count($val, "status") or $key == "status")
                    $has_status = true;

            # Construct SQL query "chunks" for enabled feathers and user privileges.
            if (!XML_RPC and !$skip_where) {
                $options["where"][] = self::feathers();

                if (!$has_status) {
                    $visitor = Visitor::current();
                    $private = (isset($options["drafts"]) and
                                $options["drafts"] and
                                $visitor->group->can("view_draft")) ?
                                   self::statuses(array(self::STATUS_DRAFT)) :
                                   self::statuses() ;

                    if (isset($options["drafts"]) and
                        $options["drafts"] and
                        $visitor->group->can("view_own_draft")) {
                            $private.= " OR (status = '".self::STATUS_DRAFT."' AND user_id = :visitor_id)";
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
                                             fallback($options["select"], array()));
            $options["ignore_dupes"] = array("attribute_names", "attribute_values");

            parent::grab($this, $post_id, $options);

            if ($this->no_results)
                return false;

            $this->slug = $this->url;

            $this->filtered = (!isset($options["filter"]) or $options["filter"]);

            $this->attribute_values = (array) $this->attribute_values;
            $this->attribute_names  = (array) $this->attribute_names;

            $this->attributes = ($this->attribute_names) ?
                array_combine($this->attribute_names, $this->attribute_values) : array() ;

            foreach($this->attributes as $key => $val)
                if (!empty($key))
                    $this->$key = $val;

            Trigger::current()->filter($this, "post");

            if ($this->filtered)
                $this->filter();
        }

        /**
         * Function: find
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array()) {
            if (isset($options["where"]) and !is_array($options["where"]))
                $options["where"] = array($options["where"]);
            elseif (!isset($options["where"]))
                $options["where"] = array();

            $has_status = false;
            $skip_where = (isset($options["skip_where"]) and $options["skip_where"]);

            foreach ($options["where"] as $key => $val)
                if ((is_int($key) and substr_count($val, "status")) or $key === "status")
                    $has_status = true;

            # Construct SQL query "chunks" for enabled feathers and user privileges.
            if (!XML_RPC and !$skip_where) {
                $options["where"][] = self::feathers();

                if (!$has_status) {
                    $visitor = Visitor::current();
                    $private = (isset($options["drafts"]) and
                                $options["drafts"] and
                                $visitor->group->can("view_draft")) ?
                                   self::statuses(array(self::STATUS_DRAFT)) :
                                   self::statuses() ;

                    if (isset($options["drafts"]) and
                        $options["drafts"] and
                        $visitor->group->can("view_own_draft")) {
                            $private.= " OR (status = '".self::STATUS_DRAFT."' AND user_id = :visitor_id)";
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
                                             fallback($options["select"], array()));
            $options["ignore_dupes"] = array("attribute_names", "attribute_values");

            fallback($options["order"], "pinned DESC, created_at DESC, id DESC");

            return parent::search(get_class(), $options, $options_for_object);
        }

        /**
         * Function: add
         * Adds a post to the database.
         *
         * Parameters:
         *     $values - The data to insert.
         *     $clean - The slug for this post.
         *     $url - The unique sanitised URL (created from $clean by default).
         *     $feather - The feather to post as.
         *     $user - <User> to set as the post's author.
         *     $pinned - Pin the post?
         *     $status - Post status
         *     $created_at - New @created_at@ timestamp for the post.
         *     $updated_at - New @updated_at@ timestamp for the post.
         *     $pingbacks - Send pingbacks?
         *     $options - Options for the post.
         *
         * Returns:
         *     The newly created <Post>.
         *
         * Notes:
         *     The caller is responsible for validating all supplied values.
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
                            $pingbacks  = true,
                            $options    = array()) {
            $user_id = ($user instanceof User) ? $user->id : $user ;

            fallback($clean,        slug(8));
            fallback($url,          self::check_url($clean));
            fallback($feather,      "undefined");
            fallback($user_id,      Visitor::current()->id);
            fallback($pinned,       false);
            fallback($status,       self::STATUS_DRAFT);
            fallback($created_at,   datetime());
            fallback($updated_at,   "0001-01-01 00:00:00"); # Model->updated will check this.
            fallback($options,      array());

            $sql = SQL::current();
            $config = Config::current();
            $trigger = Trigger::current();

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

            $attributes = array_merge($values, $options);

            $trigger->filter($attributes, "before_add_post_attributes");

            $attribute_values = array_values($attributes);
            $attribute_names = array_keys($attributes);

            # Insert the post attributes.
            foreach ($attributes as $name => $value)
                $sql->insert("post_attributes",
                             array("post_id" => $id,
                                   "name"    => $name,
                                   "value"   => $value));

            $post = new self($id, array("skip_where" => true));

            # Attempt to send pingbacks to URLs discovered in post attribute values.
            if ($config->send_pingbacks and $pingbacks and $post->status == self::STATUS_PUBLIC)
                foreach ($post->attribute_values as $value)
                    if (is_string($value))
                        send_pingbacks($value, $post);

            $trigger->call("add_post", $post, $options);

            return $post;
        }

        /**
         * Function: update
         * Updates a post with the given attributes.
         *
         * Parameters:
         *     $values - An array of data to set for the post.
         *     $user - <User> to set as the post's author.
         *     $pinned - Pin the post?
         *     $status - Post status
         *     $clean - A new slug for the post.
         *     $url - A new unique URL for the post.
         *     $created_at - New @created_at@ timestamp for the post.
         *     $updated_at - New @updated_at@ timestamp for the post.
         *     $options - Options for the post.
         *     $pingbacks - Send pingbacks?
         *
         * Returns:
         *     The updated <Post>.
         *
         * Notes:
         *     The caller is responsible for validating all supplied values.
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
                               $options    = null,
                               $pingbacks  = true) {
            if ($this->no_results)
                return false;

            $user_id = ($user instanceof User) ? $user->id : $user ;

            fallback($values,       $this->attributes);
            fallback($user_id,      $this->user_id);
            fallback($pinned,       $this->pinned);
            fallback($status,       $this->status);
            fallback($clean,        $this->clean);
            fallback($url,          ($clean != $this->clean) ? self::check_url($clean) : $this->url);
            fallback($created_at,   $this->created_at);
            fallback($updated_at,   datetime());
            fallback($options,      array());

            $sql = SQL::current();
            $config = Config::current();
            $trigger = Trigger::current();

            $new_values = array("user_id"    => $user_id,
                                "pinned"     => $pinned,
                                "status"     => $status,
                                "clean"      => $clean,
                                "url"        => $url,
                                "created_at" => $created_at,
                                "updated_at" => $updated_at);

            $trigger->filter($new_values, "before_update_post");

            $sql->update("posts",
                         array("id" => $this->id),
                         $new_values);

            $attributes = array_merge($values, $options);

            $trigger->filter($attributes, "before_update_post_attributes");

            $attribute_values = array_values($attributes);
            $attribute_names = array_keys($attributes);

            # Replace the post attributes.
            foreach ($attributes as $name => $value)
                $sql->replace("post_attributes",
                              array("post_id", "name"),
                              array("post_id" => $this->id,
                                    "name" => $name,
                                    "value" => $value));

            $post = new self(null,
                             array("read_from" => array_merge($new_values,
                                                  array("id"               => $this->id,
                                                        "feather"          => $this->feather,
                                                        "attribute_names"  => $attribute_names,
                                                        "attribute_values" => $attribute_values))));

            # Attempt to send pingbacks to URLs discovered in post attribute values.
            if ($config->send_pingbacks and $pingbacks and $post->status == self::STATUS_PUBLIC)
                foreach ($post->attribute_values as $value)
                    if (is_string($value))
                        send_pingbacks($value, $post);

            if ($this->status == self::STATUS_SCHEDULED and $post->status == self::STATUS_PUBLIC)
                $trigger->call("publish_post", $post, $this, $options);
            else
                $trigger->call("update_post", $post, $this, $options);

            return $post;
        }

        /**
         * Function: delete
         * Deletes a post from the database.
         *
         * See Also:
         *     <Model::destroy>
         */
        static function delete($id) {
            parent::destroy(get_class(), $id, array("skip_where" => true));
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

            return ($this->status == self::STATUS_DRAFT and $user->group->can("delete_draft")) or
                   ($user->group->can("delete_own_post") and $this->user_id == $user->id) or
                   (($user->group->can("delete_own_draft") and $this->status == self::STATUS_DRAFT) and
                    $this->user_id == $user->id);
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

            return ($this->status == self::STATUS_DRAFT and $user->group->can("edit_draft")) or
                   ($user->group->can("edit_own_post") and $this->user_id == $user->id) or
                   (($user->group->can("edit_own_draft") and $this->status == self::STATUS_DRAFT) and
                    $this->user_id == $user->id);
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
                $sql->count("posts", array("status" => self::STATUS_DRAFT)))
                return true;

            # Can they edit their own posts, and do they have any?
            if ($visitor->group->can("edit_own_post") and
                $sql->count("posts", array("user_id" => $visitor->id)))
                return true;

            # Can they edit their own drafts, and do they have any?
            if ($visitor->group->can("edit_own_draft") and
                $sql->count("posts", array("status" => self::STATUS_DRAFT, "user_id" => $visitor->id)))
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
                $sql->count("posts", array("status" => self::STATUS_DRAFT)))
                    return true;

            # Can they delete their own posts, and do they have any?
            if ($visitor->group->can("delete_own_post") and
                $sql->count("posts", array("user_id" => $visitor->id)))
                    return true;

            # Can they delete their own drafts, and do they have any?
            if ($visitor->group->can("delete_own_draft") and
                $sql->count("posts", array("status" => self::STATUS_DRAFT, "user_id" => $visitor->id)))
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
         * Checks if a given URL value is already being used as another post's URL.
         *
         * Parameters:
         *     $url - The URL to check.
         *
         * Returns:
         *     The unique version of the URL value.
         *     If unused, it's the same as $url. If used, a number is appended to it.
         */
        static function check_url($url) {
            if (empty($url))
                return $url;

            $count = 1;
            $unique = substr($url, 0, 128);

            while (SQL::current()->count("posts", array("url" => $unique))) {
                $count++;
                $unique = substr($url, 0, (127 - strlen($count)))."-".$count;
            }

            return $unique;
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
                return fix($config->url."/?action=view&url=".urlencode($this->url), true);

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

            return fix($config->url."/".
                       str_replace(array_keys(self::$url_attrs), $vals, $config->post_url), true);
        }

        /**
         * Function: title_from_excerpt
         * Generates an acceptable title from the post's excerpt.
         *
         * Returns:
         *     The post's excerpt:
         *     filtered -> first line -> ftags stripped -> truncated to 75 characters -> normalized.
         */
        public function title_from_excerpt() {
            if ($this->no_results)
                return false;

            # Excerpts are likely to have some sort of markup module applied to them;
            # if the current instantiation is not filtered, make one that is.
            $post = ($this->filtered) ? $this : new self($this->id, array("skip_where" => true)) ;

            $excerpt = $post->excerpt();
            Trigger::current()->filter($excerpt, "title_from_excerpt");

            $split_lines = explode("\n", $excerpt);
            $first_line = $split_lines[0];

            $stripped = strip_tags($first_line); # Strip tags from the first line.
            $truncated = truncate($stripped, 75); # Truncate the line to 75 characters.
            $normalized = normalize($truncated); # Trim and normalize whitespace.

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
            $post = ($this->filtered) ? $this : new self($this->id, array("skip_where" => true)) ;

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
            $post = ($this->filtered) ? $this : new self($this->id, array("skip_where" => true)) ;

            $excerpt = Feathers::$instances[$this->feather]->excerpt($post);
            return Trigger::current()->filter($excerpt, "excerpt", $post);
        }


        /**
         * Function: feed_content
         * Returns the given post's feed content, provided by its Feather.
         */
        public function feed_content() {
            if ($this->no_results)
                return false;

            # Excerpts are likely to have some sort of markup module applied to them;
            # if the current instantiation is not filtered, make one that is.
            $post = ($this->filtered) ? $this : new self($this->id, array("skip_where" => true)) ;

            $feed_content = Feathers::$instances[$this->feather]->feed_content($post);
            return Trigger::current()->filter($feed_content, "feed_content", $post);
        }

        /**
         * Function: next
         *
         * Returns:
         *     The next post (the post made before this one).
         */
        public function next() {
            if ($this->no_results)
                return false;

            if (isset($this->next))
                return $this->next;

            return $this->next = new self(null,
                                          array("where" => array("created_at <" => $this->created_at,
                                                                 ($this->status == self::STATUS_DRAFT ?
                                                                    self::statuses(array(self::STATUS_DRAFT)) :
                                                                    self::statuses())),
                                                "order" => "created_at DESC, id DESC"));
        }

        /**
         * Function: prev
         *
         * Returns:
         *     The previous post (the post made after this one).
         */
        public function prev() {
            if ($this->no_results)
                return false;

            if (isset($this->prev))
                return $this->prev;

            return $this->prev = new self(null,
                                          array("where" => array("created_at >" => $this->created_at,
                                                                ($this->status == self::STATUS_DRAFT ?
                                                                    self::statuses(array(self::STATUS_DRAFT)) :
                                                                    self::statuses())),
                                                "order" => "created_at ASC, id ASC"));
        }

        /**
         * Function: theme_exists
         * Checks if the current post's feather theme file exists.
         */
        public function theme_exists() {
            return !$this->no_results and Theme::current()->file_exists("feathers".DIR.$this->feather);
        }

        /**
         * Function: filter
         * Filters the post attributes through filter_post and any Feather filters.
         */
        private function filter() {
            $class = camelize($this->feather);
            $touched = array();

            $trigger = Trigger::current();
            $trigger->filter($this, "filter_post");

            # Custom filters.
            if (isset(Feathers::$custom_filters[$class]))
                foreach (Feathers::$custom_filters[$class] as $custom_filter) {
                    $field = $custom_filter["field"];
                    $field_unfiltered = $field."_unfiltered";

                    if (!in_array($field_unfiltered, $touched)) {
                        $this->$field_unfiltered = isset($this->$field) ? $this->$field : null ;
                        $touched[] = $field_unfiltered;
                    }

                    $this->$field = call_user_func_array(array(Feathers::$instances[$this->feather],
                                                               $custom_filter["name"]),
                                                         array($this->$field, $this));
                }

            # Trigger filters.
            if (isset(Feathers::$filters[$class]))
                foreach (Feathers::$filters[$class] as $filter) {
                    $field = $filter["field"];
                    $field_unfiltered = $field."_unfiltered";

                    if (!in_array($field_unfiltered, $touched)) {
                        $this->$field_unfiltered = isset($this->$field) ? $this->$field : null ;
                        $touched[] = $field_unfiltered;
                    }

                    if (isset($this->$field) and !empty($this->$field))
                        $trigger->filter($this->$field, $filter["name"], $this);
                }
        }

        /**
         * Function: from_url
         * Attempts to grab a post from its clean URL.
         *
         * Parameters:
         *     $request - The request URI to parse, or an array of matches already found.
         *     $route - The route object to respond to, or null to return a Post object.
         *     $options - Additional options for the Post object (optional).
         */
        static function from_url($request, $route = null, $options = array()) {
            $config = Config::current();

            $found = is_array($request) ? $request : array() ;

            if (empty($found)) {
                $regex = "";      # Request validity is tested with this.
                $attrs = array(); # Post attributes present in post_url.
                $parts = preg_split("|(\([^)]+\))|",
                                    $config->post_url,
                                    0,
                                    PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

                # Differentiate between post attributes and junk in post_url.
                foreach ($parts as $part)
                    if (isset(self::$url_attrs[$part])) {
                        $regex .= self::$url_attrs[$part];
                        $attrs[] = trim($part, "()");
                    } else {
                        $regex .= preg_quote($part, "|");
                    }

                # Test the request and return false if it isn't valid.
                if (!preg_match("|^$regex|", ltrim(str_replace($config->url, "/", $request), "/"), $matches))
                    return false;

                # Populate $found using the array of sub-pattern matches.
                for ($i = 0; $i < count($attrs); $i++)
                    $found[$attrs[$i]] = urldecode($matches[$i + 1]);

                # If a route was provided, respond to it and return.
                if (isset($route))
                    return $route->try["view"] = array($found, $route->arg);
            }

            $where = array();
            $dates = array("year", "month", "day", "hour", "minute", "second");

            $created_at = array("year"   => "____",
                                "month"  => "__",
                                "day"    => "__",
                                "hour"   => "__",
                                "minute" => "__",
                                "second" => "__");

            # Conversions of some attributes.
            foreach ($found as $part => $value)
                if (in_array($part, $dates)) {
                    # Filter by date/time of creation.
                    $created_at[$part] = $value;
                    $where["created_at LIKE"] = $created_at["year"]."-".
                                                $created_at["month"]."-".
                                                $created_at["day"]." ".
                                                $created_at["hour"].":".
                                                $created_at["minute"].":".
                                                $created_at["second"]."%";
                } elseif ($part == "author") {
                    # Filter by "author" (login).
                    $user = new User(array("login" => $value));
                    $where["user_id"] = ($user->no_results) ? 0 : $user->id ;
                } elseif ($part == "feathers") {
                    # Filter by feather.
                    $where["feather"] = depluralize($value);
                } else {
                    # Key => Val expression.
                    $where[$part] = $value;
                }

            return new self(null, array_merge($options, array("where" => $where)));
        }

        /**
         * Function: statuses
         * Returns a SQL query "chunk" for the "status" column permissions of the current user.
         *
         * Parameters:
         *     $start - An array of additional statuses to allow;
         *              "registered_only", "private" and "scheduled" are added deterministically.
         */
        static function statuses($start = array()) {
            $visitor = Visitor::current();

            $statuses = array_merge(array(self::STATUS_PUBLIC), $start);

            if (logged_in())
                $statuses[] = self::STATUS_REG_ONLY;

            if ($visitor->group->can("view_private"))
                $statuses[] = self::STATUS_PRIVATE;

            if ($visitor->group->can("view_scheduled"))
                $statuses[] = self::STATUS_SCHEDULED;

            return "(posts.status IN ('".implode("', '", $statuses)."')".
                   " OR posts.status LIKE '%{".$visitor->group->id."}%')".
                   " OR (posts.status LIKE '%{%' AND posts.user_id = ".$visitor->id.")";
        }

        /**
         * Function: feathers
         * Returns a SQL query "chunk" for the "feather" column so that it matches enabled feathers.
         */
        static function feathers() {
            $feathers = array();

            foreach (Config::current()->enabled_feathers as $feather)
                if (feather_enabled($feather))
                    $feathers[] = $feather;

            return "posts.feather IN ('".implode("', '", $feathers)."')";
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
         * Function: groups
         * Returns the IDs of any groups given viewing permission in the post's status.
         */
        public function groups() {
            if ($this->no_results)
                return false;

            preg_match_all("/\{([0-9]+)\}/", $this->status, $groups, PREG_PATTERN_ORDER);

            return empty($groups[1]) ? false : $groups[1] ;
        }

        /**
         * Function: publish_scheduled
         * Searches for and publishes scheduled posts.
         *
         * Calls the @publish_post@ trigger with the updated <Post>.
         */
        static function publish_scheduled($pingbacks = true) {
            $sql = SQL::current();
            $ids = $sql->select("posts",
                                "id",
                                array("created_at <=" => datetime(),
                                      "status" => self::STATUS_SCHEDULED))->fetchAll();

            foreach ($ids as $id) {
                $post = new self($id, array("skip_where" => true,
                                            "filter" => false));

                $post->update(null, null, null, self::STATUS_PUBLIC);
            }
        }
    }
