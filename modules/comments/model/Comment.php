<?php
    /**
     * Class: Comment
     * The model for the Comments SQL table.
     *
     * See Also:
     *     <Model>
     */
    class Comment extends Model {
        const STATUS_APPROVED = "approved";
        const STATUS_DENIED   = "denied";
        const STATUS_SPAM     = "spam";
        const STATUS_PINGBACK = "pingback";
        const OPTION_OPEN     = "open";
        const OPTION_CLOSED   = "closed";
        const OPTION_PRIVATE  = "private";
        const OPTION_REG_ONLY = "registered_only";

        public $no_results = false;

        public $belongs_to = array("post", "user", "parent" => array("model" => "comment"));

        public $has_many = array("children" => array("model" => "comment", "by" => "parent"));

        /**
         * Function: __construct
         *
         * See Also:
         *     <Model::grab>
         */
        public function __construct($comment_id, $options = array()) {
            $skip_where = (ADMIN or (isset($options["skip_where"]) and $options["skip_where"]));

            if (!$skip_where) {
                $options["where"]["status not"] = self::STATUS_SPAM;
                $options["where"][] = self::redactions();
            }

            parent::grab($this, $comment_id, $options);

            if ($this->no_results)
                return false;

            $this->filtered = !isset($options["filter"]) or $options["filter"];

            Trigger::current()->filter($this, "comment");

            if ($this->filtered)
                $this->filter();
        }

        /**
         * Function: find
         *
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array()) {
            $skip_where = (ADMIN or (isset($options["skip_where"]) and $options["skip_where"]));

            if (!$skip_where) {
                $options["where"]["status not"] = self::STATUS_SPAM;
                $options["where"][] = self::redactions();
            }

            fallback($options["order"], "created_at ASC");
            return parent::search(get_class(), $options, $options_for_object);
        }

        /**
         * Function: create
         * Attempts to create a new comment that will be attributed to the current visitor.
         *
         * Parameters:
         *     $body - The comment.
         *     $author - The name of the commenter.
         *     $author_url - The commenter's website.
         *     $author_email - The commenter's email.
         *     $post - The <Post> they're commenting on.
         *     $parent - The id of the <Comment> they're replying to.
         *     $notify - Send correspondence if additional comments are added?
         *     $status - A string describing the comment status (optional).
         */
        static function create($body,
                               $author,
                               $author_url,
                               $author_email,
                               $post,
                               $parent,
                               $notify,
                               $status = null) {
            $config = Config::current();
            $visitor = Visitor::current();
            $trigger = Trigger::current();
            $values = array("body"         => $body,
                            "author"       => $author,
                            "author_url"   => $author_url,
                            "author_email" => $author_email);

            fallback($status, ($post->user_id == $visitor->id) ?
                                    self::STATUS_APPROVED :
                                    $config->module_comments["default_comment_status"]);

            $spam = ($status == self::STATUS_SPAM);
            $trigger->filter($spam, "comment_is_spam", $values);

            if ($spam)
                $status = self::STATUS_SPAM;

            if (!logged_in() or !$config->email_correspondence)
                $notify = false;

            $comment = self::add($body,
                                 $author,
                                 $author_url,
                                 $author_email,
                                 crc32($_SERVER['REMOTE_ADDR']),
                                 isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "",
                                 $status,
                                 $post->id,
                                 $visitor->id,
                                 $parent,
                                 $notify);

            $_SESSION['comments'][] = $comment->id;
            return $comment;
        }

        /**
         * Function: add
         * Adds a comment to the database.
         *
         * Parameters:
         *     $body - The comment.
         *     $author - The name of the commenter.
         *     $author_url - The commenter's website.
         *     $author_email - The commenter's email.
         *     $ip - CRC32 polynomial of the commenter's IP address.
         *     $agent - The commenter's user agent.
         *     $status - The new comment's status.
         *     $post_id - The ID of the <Post> they're commenting on.
         *     $user_id - The ID of the <User> this comment was made by.
         *     $parent - The <Comment> they're replying to.
         *     $notify - Notification on follow-up comments.
         *     $created_at - The new comment's @created_at@ timestamp.
         *     $updated_at - The new comment's @updated_at@ timestamp.
         *
         * Returns:
         *     The newly created <Comment>.
         *
         * See Also:
         *     <update>
         */
        static function add($body,
                            $author,
                            $author_url,
                            $author_email,
                            $ip,
                            $agent,
                            $status,
                            $post_id,
                            $user_id,
                            $parent = null,
                            $notify = null,
                            $created_at = null,
                            $updated_at = null) {
            $sql = SQL::current();
            $config = Config::current();

            $sql->insert("comments",
                         array("body"         => $body,
                               "author"       => strip_tags($author),
                               "author_url"   => strip_tags($author_url),
                               "author_email" => strip_tags($author_email),
                               "author_ip"    => $ip,
                               "author_agent" => $agent,
                               "status"       => $status,
                               "post_id"      => $post_id,
                               "user_id"      => $user_id,
                               "parent_id"    => fallback($parent, 0),
                               "notify"       => fallback($notify, false),
                               "created_at"   => fallback($created_at, datetime()),
                               "updated_at"   => fallback($updated_at, "0001-01-01 00:00:00")));

            $new = new self($sql->latest("comments"), array("skip_where" => true));

            if ($new->status == self::STATUS_APPROVED) {
                $comments = self::find(array("skip_where" => true,
                                             "where"      => array("post_id"    => $new->post_id,
                                                                   "user_id !=" => $new->user_id,
                                                                   "status"     => self::STATUS_APPROVED,
                                                                   "notify"     => true)),
                                       array("filter" => false));

                $notifications = array();

                foreach ($comments as $comment)
                    $notifications[$comment->author_email] = $comment->user_id;

                foreach ($notifications as $to => $id)
                    correspond("comment", array("to"      => $to,
                                                "user_id" => $id,
                                                "post_id" => $new->post_id,
                                                "author"  => $new->author,
                                                "body"    => $new->body,
                                                "link1"   => $new->url(),
                                                "link2"   => $config->url.
                                                             "/?action=unsubscribe&amp;email=".
                                                             urlencode($to).
                                                             "&amp;id=".$new->post_id.
                                                             "&amp;token=".token($to)));
            }

            Trigger::current()->call("add_comment", $new);

            return $new;
        }

        /**
         * Function: update
         * Updates a comment with the given attributes.
         *
         * Parameters:
         *     $body - The comment.
         *     $author - The name of the commenter.
         *     $author_url - The commenter's website.
         *     $author_email - The commenter's email.
         *     $status - The comment's status.
         *     $notify - Notification on follow-up comments.
         *     $created_at - New @created_at@ timestamp for the comment.
         *     $updated_at - New @updated_at@ timestamp for the comment.
         *
         * Returns:
         *     The updated <Comment>.
         */
        public function update($body,
                               $author,
                               $author_url,
                               $author_email,
                               $status = null,
                               $notify = null,
                               $created_at = null,
                               $updated_at = null) {
            if ($this->no_results)
                return false;

            if ($this->status == self::STATUS_PINGBACK)
                $status = $this->status;

            $new_values = array("body"         => $body,
                                "author"       => strip_tags($author),
                                "author_url"   => strip_tags($author_url),
                                "author_email" => strip_tags($author_email),
                                "status"       => fallback($status, $this->status),
                                "notify"       => fallback($notify, $this->notify),
                                "created_at"   => fallback($created_at, $this->created_at),
                                "updated_at"   => fallback($updated_at, datetime()));

            SQL::current()->update("comments",
                                   array("id" => $this->id),
                                   $new_values);

            $comment = new self(null,
                                array("read_from" => array_merge($new_values,
                                                     array("id"           => $this->id,
                                                           "author_ip"    => $this->author_ip,
                                                           "author_agent" => $this->author_agent,
                                                           "post_id"      => $this->post_id,
                                                           "user_id"      => $this->user_id,
                                                           "parent_id"    => $this->parent_id))));

            Trigger::current()->call("update_comment", $comment, $this);

            return $comment;
        }

        /**
         * Function: delete
         * Deletes a comment from the database.
         *
         * See Also:
         *     <Model::destroy>
         */
        static function delete($comment_id) {
            parent::destroy(get_class(), $comment_id, array("skip_where" => true));
        }

        /**
         * Function: editable
         * Checks if the <User> can edit the comment.
         */
        public function editable($user = null) {
            if ($this->no_results)
                return false;

            fallback($user, Visitor::current());
            return ($user->group->can("edit_comment") or
                    (logged_in() and $user->group->can("edit_own_comment") and $user->id == $this->user_id));
        }

        /**
         * Function: deletable
         * Checks if the <User> can delete the comment.
         */
        public function deletable($user = null) {
            if ($this->no_results)
                return false;

            fallback($user, Visitor::current());
            return ($user->group->can("delete_comment") or
                    (logged_in() and $user->group->can("delete_own_comment") and $user->id == $this->user_id));
        }

        /**
         * Function: any_editable
         * Checks if the <Visitor> can edit any comments.
         */
        static function any_editable() {
            $visitor = Visitor::current();

            # Can they edit comments?
            if ($visitor->group->can("edit_comment"))
                return true;

            # Can they edit their own comments, and do they have any?
            if ($visitor->group->can("edit_own_comment") and
                SQL::current()->count("comments", array("user_id" => $visitor->id)))
                return true;

            return false;
        }

        /**
         * Function: any_deletable
         * Checks if the <Visitor> can delete any comments.
         */
        static function any_deletable() {
            $visitor = Visitor::current();

            # Can they delete comments?
            if ($visitor->group->can("delete_comment"))
                return true;

            # Can they delete their own comments, and do they have any?
            if ($visitor->group->can("delete_own_comment") and
                SQL::current()->count("comments", array("user_id" => $visitor->id)))
                return true;

            return false;
        }

        /**
         * Function: creatable
         * Checks if the <Visitor> can comment on a post.
         */
        static function creatable($post) {
            $visitor = Visitor::current();
            
            if (!$visitor->group->can("add_comment"))
                return false;

            # Assume allowed comments by default.
            return empty($post->comment_status) or
                       !($post->comment_status == self::OPTION_CLOSED or
                        ($post->comment_status == self::OPTION_REG_ONLY and !logged_in()) or
                        ($post->comment_status == self::OPTION_PRIVATE and !$visitor->group->can("add_comment_private")));
        }

        /**
         * Function: redactions
         * Returns a SQL query "chunk" that hides some comments from the <Visitor>.
         */
        static function redactions() {
            $user_id = (int) Visitor::current()->id;
            $list = empty($_SESSION['comments']) ?
                "(0)" : QueryBuilder::build_list(SQL::current(), $_SESSION['comments']) ;

            return "status != '".self::STATUS_DENIED.
                   "' OR ((user_id != 0 AND user_id = ".$user_id.") OR (id IN ".$list."))";
        }

        /**
         * Function: url
         * Returns a comment's URL.
         */
        public function url() {
            if ($this->no_results)
                return false;

            return url("comment/".$this->id, MainController::current());
        }

        /**
         * Function: author_link
         * Returns the commenter's name enclosed in a hyperlink to their website.
         */
        public function author_link() {
            if ($this->no_results)
                return false;

            if (empty($this->author))
                return __("Anon", "comments");

            if (is_url($this->author_url))
                return '<a href="'.fix($this->author_url, true).'">'.$this->author.'</a>';
            else
                return $this->author;
        }

        /**
         * Function: filter
         * Filters the comment through filter_comment and markup filters.
         */
        private function filter() {
            $trigger = Trigger::current();
            $trigger->filter($this, "filter_comment");

            $this->body_unfiltered = $this->body;
            $trigger->filter($this->body, array("markup_comment_text", "markup_text"));

            $config = Config::current();

            $group = (!empty($this->user_id) and !$this->user->no_results) ?
                $this->user->group :
                new Group($config->guest_group) ;

            if ($this->status != "pingback" and !$group->can("code_in_comments")) {
                $allowed = $config->module_comments["allowed_comment_html"];
                $this->body = strip_tags($this->body, "<".implode("><", $allowed).">");
            }

            $this->body = sanitize_html($this->body);
        }

        /**
         * Function: install
         * Creates the database table.
         */
        static function install() {
            SQL::current()->create("comments",
                                   array("id INTEGER PRIMARY KEY AUTO_INCREMENT",
                                         "body LONGTEXT",
                                         "author VARCHAR(250) DEFAULT ''",
                                         "author_url VARCHAR(2048) DEFAULT ''",
                                         "author_email VARCHAR(128) DEFAULT ''",
                                         "author_ip INTEGER DEFAULT 0",
                                         "author_agent VARCHAR(255) DEFAULT ''",
                                         "status VARCHAR(32) default '".self::STATUS_DENIED."'",
                                         "post_id INTEGER DEFAULT 0",
                                         "user_id INTEGER DEFAULT 0",
                                         "parent_id INTEGER DEFAULT 0",
                                         "notify BOOLEAN DEFAULT FALSE",
                                         "created_at DATETIME DEFAULT NULL",
                                         "updated_at DATETIME DEFAULT NULL"));
        }

        /**
         * Function: uninstall
         * Drops the database table.
         */
        static function uninstall() {
            $sql = SQL::current();

            $sql->drop("comments");
            $sql->delete("post_attributes", array("name" => "comment_status"));
        }
    }
