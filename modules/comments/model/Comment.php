<?php
    /**
     * Class: Comment
     * The model for the Comments SQL table.
     *
     * See Also:
     *     <Model>
     */
    class Comment extends Model {
        public $no_results = false;

        public $belongs_to = array("post", "user", "parent" => array("model" => "comment"));

        public $has_many = array("children" => array("model" => "comment", "by" => "parent"));

        /**
         * Function: __construct
         * See Also:
         *     <Model::grab>
         */
        public function __construct($comment_id, $options = array()) {
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
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array()) {
            return parent::search(get_class(), $options, $options_for_object);
        }

        /**
         * Function: create
         * Attempts to create a comment using the passed information. If the Akismet API key is present, it will check it.
         *
         * Parameters:
         *     $body - The comment.
         *     $author - The name of the commenter.
         *     $author_url - The commenter's website.
         *     $author_email - The commenter's email.
         *     $post - The <Post> they're commenting on.
         *     $parent - The <Comment> they're replying to.
         *     $notify - Notification on follow-up comments.
         *     $type - The type of comment (optional).
         */
        static function create($body, $author, $author_url, $author_email, $post, $parent, $notify, $type = null) {
            if (!self::user_can($post) and $type != "pingback")
                return;

            $config = Config::current();
            $route = Route::current();
            $visitor = Visitor::current();

            if (!$type) {
                $status = ($post->user_id == $visitor->id) ? "approved" : $config->default_comment_status ;
                $type = "comment";
            } else
                $status = $type;

            if (!logged_in())
                $notify = 0; # Only logged-in users can request notifications.

            $HTTP_REFERER = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "" ;
            $HTTP_USER_AGENT = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "" ;

            if (!empty($config->akismet_api_key)) {
                $akismet = new Akismet($config->url, $config->akismet_api_key);

                $akismet->setCommentContent($body);
                $akismet->setCommentAuthor($author);
                $akismet->setCommentAuthorURL($author_url);
                $akismet->setCommentAuthorEmail($author_email);
                $akismet->setPermalink($post->url());
                $akismet->setCommentType($type);
                $akismet->setReferrer($HTTP_REFERER);
                $akismet->setUserIP($_SERVER['REMOTE_ADDR']);

                if ($akismet->isCommentSpam()) {
                    $comment = self::add($body,
                                         $author,
                                         $author_url,
                                         $author_email,
                                         $_SERVER['REMOTE_ADDR'],
                                         $HTTP_USER_AGENT,
                                         "spam",
                                         $post->id,
                                         $visitor->id,
                                         $parent,
                                         $notify);
                    return $comment;
                } else {
                    $comment = self::add($body,
                                         $author,
                                         $author_url,
                                         $author_email,
                                         $_SERVER['REMOTE_ADDR'],
                                         $HTTP_USER_AGENT,
                                         $status,
                                         $post->id,
                                         $visitor->id,
                                         $parent,
                                         $notify);

                    fallback($_SESSION['comments'], array());
                    $_SESSION['comments'][] = $comment->id;
                    return $comment;
                }
            } else {
                $comment = self::add($body,
                                     $author,
                                     $author_url,
                                     $author_email,
                                     $_SERVER['REMOTE_ADDR'],
                                     $HTTP_USER_AGENT,
                                     $status,
                                     $post->id,
                                     $visitor->id,
                                     $parent,
                                     $notify);

                fallback($_SESSION['comments'], array());
                $_SESSION['comments'][] = $comment->id;
                return $comment;
            }
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
         *     $ip - The commenter's IP address.
         *     $agent - The commenter's user agent.
         *     $status - The new comment's status.
         *     $post_id - The ID of the <Post> they're commenting on.
         *     $user_id - The ID of the <User> this comment was made by.
         *     $parent - The <Comment> they're replying to.
         *     $notify - Notification on follow-up comments.
         *     $created_at - The new comment's @created_at@ timestamp.
         *     $updated_at - The new comment's @updated_at@ timestamp.
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
                            $parent,
                            $notify,
                            $created_at = null,
                            $updated_at = null) {
            $ip = ip2long($ip);

            if ($ip === false)
                $ip = 0;

            $sql = SQL::current();

            $sql->insert("comments",
                         array("body" => sanitize_html($body),
                               "author" => strip_tags($author),
                               "author_url" => strip_tags($author_url),
                               "author_email" => strip_tags($author_email),
                               "author_ip" => $ip,
                               "author_agent" => $agent,
                               "status" => $status,
                               "post_id" => $post_id,
                               "user_id"=> $user_id,
                               "parent_id" => $parent,
                               "notify" => $notify,
                               "created_at" => oneof($created_at, datetime()),
                               "updated_at" => oneof($updated_at, "0000-00-00 00:00:00")));

            $new = new self($sql->latest("comments"));
            Trigger::current()->call("add_comment", $new);

            if ($new->status == "approved")
                foreach ($sql->select("comments",
                                      "author_email",
                                      array("post_id" => $new->post_id,
                                            "user_id !=" => $new->user_id,
                                            "status" => "approved",
                                            "notify" => 1))->fetchAll() as $notification) {

                    correspond("comment", array("post_id" => $new->post_id,
                                                "author" => $new->author,
                                                "body" => $new->body,
                                                "to" => $notification["author_email"]));
                }

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
         */
        public function update($body, $author, $author_url, $author_email, $status, $notify, $created_at = null, $updated_at = null) {
            fallback($created_at, $this->created_at);
            fallback($updated_at, datetime());
            $body = sanitize_html($body);
            $author = strip_tags($author);
            $author_url = strip_tags($author_url);
            $author_email = strip_tags($author_email);

            # Update all values of this comment.
            foreach (array("body", "author", "author_url", "author_email", "status", "notify", "created_at", "updated_at") as $attr)
                $this->$attr = $$attr;

            SQL::current()->update("comments",
                                   array("id" => $this->id),
                                   array("body" => $body,
                                         "author" => $author,
                                         "author_url" => $author_url,
                                         "author_email" => $author_email,
                                         "status" => $status,
                                         "notify" => $notify,
                                         "created_at" => $created_at,
                                         "updated_at" => $updated_at));

            if ($this->filtered)
                $this->filter();

            Trigger::current()->call("update_comment", $this);
        }

        /**
         * Function: delete
         * Deletes a comment from the database.
         *
         * See Also:
         *     <Model::destroy>
         */
        static function delete($comment_id) {
            parent::destroy(get_class(), $comment_id);
        }

        /**
         * Function: editable
         * Checks if the <User> can edit the comment.
         */
        public function editable($user = null) {
            fallback($user, Visitor::current());
            return ($user->group->can("edit_comment") or
                    (logged_in() and $user->group->can("edit_own_comment") and $user->id == $this->user_id));
        }

        /**
         * Function: deletable
         * Checks if the <User> can delete the comment.
         */
        public function deletable($user = null) {
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
         * Function: author_link
         * Returns the commenter's name enclosed in a hyperlink to their website.
         */
        public function author_link() {
            if (!isset($this->id))
                return __("Anon", "comments");

            if (is_url($this->author_url))
                return '<a href="'.fix($this->author_url, true).'">'.$this->author.'</a>';
            else
                return $this->author;
        }

        /**
         * Function: user_can
         * Checks if the <Visitor> can comment on a post.
         */
        static function user_can($post) {
            $visitor = Visitor::current();
            
            if (!$visitor->group->can("add_comment"))
                return false;

            # Assume allowed comments by default.
            return empty($post->comment_status) or
                       !($post->comment_status == "closed" or
                        ($post->comment_status == "registered_only" and !logged_in()) or
                        ($post->comment_status == "private" and !$visitor->group->can("add_comment_private")));
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

            if ($this->status != "pingback" and !$group->can("code_in_comments"))
                $this->body = strip_tags($this->body, "<".implode("><", $config->allowed_comment_html).">");
        }

        /**
         * Function: install
         * Creates the database table.
         */
        static function install() {
            SQL::current()->query("CREATE TABLE IF NOT EXISTS __comments (
                                       id INTEGER PRIMARY KEY AUTO_INCREMENT,
                                       body LONGTEXT,
                                       author VARCHAR(250) DEFAULT '',
                                       author_url VARCHAR(2048) DEFAULT '',
                                       author_email VARCHAR(128) DEFAULT '',
                                       author_ip INTEGER DEFAULT '0',
                                       author_agent VARCHAR(255) DEFAULT '',
                                       status VARCHAR(32) default 'denied',
                                       post_id INTEGER DEFAULT 0,
                                       user_id INTEGER DEFAULT 0,
                                       parent_id INTEGER DEFAULT 0,
                                       notify INTEGER DEFAULT 0,
                                       created_at DATETIME DEFAULT NULL,
                                       updated_at DATETIME DEFAULT NULL
                                   ) DEFAULT CHARSET=utf8");
        }

        /**
         * Function: uninstall
         * Drops the database table.
         */
        static function uninstall() {
            $sql = SQL::current();

            $sql->query("DROP TABLE __comments");
            $sql->delete("post_attributes", array("name" => "comment_status"));
        }
    }
