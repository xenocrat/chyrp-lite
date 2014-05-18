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

            $this->body_unfiltered = $this->body;
            $group = ($this->user_id and !$this->user->no_results) ?
                         $this->user->group :
                         new Group(Config::current()->guest_group) ;

            $this->filtered = !isset($options["filter"]) or $options["filter"];

            $trigger = Trigger::current();

            $trigger->filter($this, "comment");

            if ($this->filtered) {
                if (($this->status != "pingback" and $this->status != "trackback") and !$group->can("code_in_comments"))
                    $this->body = strip_tags($this->body, "<".join("><", Config::current()->allowed_comment_html).">");

                $this->body_unfiltered = $this->body;

                $trigger->filter($this->body, array("markup_text", "markup_comment_text"));

                $trigger->filter($this, "filter_comment");
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
         * Function: create
         * Attempts to create a comment using the passed information. If the Akismet API key is present, it will check it.
         *
         * Parameters:
         *     $body - The comment.
         *     $author - The name of the commenter.
         *     $url - The commenter's website.
         *     $email - The commenter's email.
         *     $post - The <Post> they're commenting on.
         *     $parent - The <Comment> they're replying to.
         *     $notify - Notification on follow-up comments.
         *     $type - The type of comment. Optional, used for trackbacks/pingbacks.
         */
        static function create($body, $author, $url, $email, $post, $parent, $notify, $type = null) {
            if (!self::user_can($post->id) and !in_array($type, array("trackback", "pingback")))
                return;

            $config = Config::current();
            $route = Route::current();
            $visitor = Visitor::current();

            if (!$type) {
                $status = ($post->user_id == $visitor->id) ? "approved" : $config->default_comment_status ;
                $type = "comment";
            } else
                $status = $type;

            if (!empty($config->akismet_api_key)) {
                $akismet = new Akismet($config->url, $config->akismet_api_key);

                $akismet->setCommentContent($body);
                $akismet->setCommentAuthor($author);
                $akismet->setCommentAuthorURL($url);
                $akismet->setCommentAuthorEmail($email);
                $akismet->setPermalink($post->url());
                $akismet->setCommentType($type);
                $akismet->setReferrer($_SERVER['HTTP_REFERER']);
                $akismet->setUserIP($_SERVER['REMOTE_ADDR']);

                if ($akismet->isCommentSpam()) {
                    self::add($body,
                              $author,
                              $url,
                              $email,
                              $_SERVER['REMOTE_ADDR'],
                              $_SERVER['HTTP_USER_AGENT'],
                              "spam",
                              $post->id,
                              $visitor->id,
                              $parent,
                              $notify);
                    error(__("Spam Comment"), __("Your comment has been marked as spam. It has to be reviewed and/or approved by an admin.", "comments"));
                } else {
                    $comment = self::add($body,
                                         $author,
                                         $url,
                                         $email,
                                         $_SERVER['REMOTE_ADDR'],
                                         $_SERVER['HTTP_USER_AGENT'],
                                         $status,
                                         $post->id,
                                         $visitor->id,
                                         $parent,
                                         $notify);

                    fallback($_SESSION['comments'], array());
                    $_SESSION['comments'][] = $comment->id;

                    if (isset($_POST['ajax']))
                        exit("{ \"comment_id\": \"".$comment->id."\", \"comment_timestamp\": \"".$comment->created_at."\" }");

                    Flash::notice(__("Comment added."), $post->url()."#comments");
                }
            } else {
                $comment = self::add($body,
                                     $author,
                                     $url,
                                     $email,
                                     $_SERVER['REMOTE_ADDR'],
                                     $_SERVER['HTTP_USER_AGENT'],
                                     $status,
                                     $post->id,
                                     $visitor->id,
                                     $parent,
                                     $notify);

                fallback($_SESSION['comments'], array());
                $_SESSION['comments'][] = $comment->id;

                if (isset($_POST['ajax']))
                    exit("{ \"comment_id\": \"".$comment->id."\", \"comment_timestamp\": \"".$comment->created_at."\" }");

                Flash::notice(__("Comment added."), $post->url()."#comment");
            }
        }

        /**
         * Function: add
         * Adds a comment to the database.
         *
         * Parameters:
         *     $body - The comment.
         *     $author - The name of the commenter.
         *     $url - The commenter's website.
         *     $email - The commenter's email.
         *     $ip - The commenter's IP address.
         *     $agent - The commenter's user agent.
         *     $status - The new comment's status.
         *     $post - The <Post> they're commenting on.
         *     $user_id - The ID of this <User> this comment was made by.
         *     $parent - The <Comment> they're replying to.
         *     $notify - Notification on follow-up comments.
         *     $created_at - The new comment's "created" timestamp.
         *     $updated_at - The new comment's "last updated" timestamp.
         */
        static function add($body, $author, $url, $email, $ip, $agent, $status, $post, $user_id, $parent, $notify, $created_at = null, $updated_at = null) {
            # Strip <script> tags
            $body = str_replace("<script", "&lt;script", $body);
            $body = str_replace("</script", "&lt;/script", $body);

            if (!empty($url)) # Add the http:// if it isn't there.
                if (!@parse_url($url, PHP_URL_SCHEME))
                    $url = "http://".$url;

            $ip = ip2long($ip);
            if ($ip === false)
                $ip = 0;

            $sql = SQL::current();
            $sql->insert("comments",
                         array("body" => $body,
                               "author" => strip_tags($author),
                               "author_url" => strip_tags($url),
                               "author_email" => strip_tags($email),
                               "author_ip" => $ip,
                               "author_agent" => $agent,
                               "status" => $status,
                               "post_id" => $post,
                               "user_id"=> $user_id,
                               "parent_id" => $parent,
                               "notify" => $notify,
                               "created_at" => oneof($created_at, datetime()),
                               "updated_at" => oneof($updated_at, "0000-00-00 00:00:00")));

            $new = new self($sql->latest("comments"));
            Trigger::current()->call("add_comment", $new);
            self::notify(strip_tags($author), $body, $post);
            return $new;
        }

        public function update($body, $author, $url, $email, $status, $notify, $timestamp, $update_timestamp = true) {
            # Strip <script> tags
            $body = str_replace("<script", "&lt;script", $body);
            $body = str_replace("</script", "&lt;/script", $body);

            $sql = SQL::current();
            $sql->update("comments",
                         array("id" => $this->id),
                         array("body" => $body,
                               "author" => strip_tags($author),
                               "author_url" => strip_tags($url),
                               "author_email" => strip_tags($email),
                               "status" => $status,
                               "notify" => $notify,
                               "created_at" => $timestamp,
                               "updated_at" => ($update_timestamp) ? datetime() : $this->updated_at));

            Trigger::current()->call("update_comment", $this, $body, $author, $url, $email, $status, $notify, $timestamp, $update_timestamp);
        }

        static function delete($comment_id) {
            $trigger = Trigger::current();
            if ($trigger->exists("delete_comment"))
                $trigger->call("delete_comment", new self($comment_id));

            SQL::current()->delete("comments", array("id" => $comment_id));
        }

        public function editable($user = null) {
            fallback($user, Visitor::current());
            return ($user->group->can("edit_comment") or ($user->group->can("edit_own_comment") and $user->id == $this->user_id));
        }

        public function deletable($user = null) {
            fallback($user, Visitor::current());
            return ($user->group->can("delete_comment") or ($user->group->can("delete_own_comment") and $user->id == $this->user_id));
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

        public function author_link() {
            if ($this->author_url != "") # If a URL is set
                return '<a href="'.$this->author_url.'">'.$this->author.'</a>';
            else # If not, just return their name
                return $this->author;
        }

        static function user_can($post) {
            $visitor = Visitor::current();
            if (!$visitor->group->can("add_comment")) return false;

            # assume allowed comments by default
            return empty($post->comment_status) or
                   !($post->comment_status == "closed" or
                    ($post->comment_status == "registered_only" and !logged_in()) or
                    ($post->comment_status == "private" and !$visitor->group->can("add_comment_private")));
        }

        static function user_count($user_id) {
            $count = SQL::current()->count("comments", array("user_id" => $user_id));
            return $count;
        }

        /**
         * Function: reply_to_link
         * Outputs a Reply to comment link, if the <User.can> add comment and allow_nested_comments is checked.
         *
         * Parameters:
         *     $text - The text to show for the link.
         *     $before - If the link can be shown, show this before it.
         *     $after - If the link can be shown, show this after it.
         *     $classes - Extra CSS classes for the link, space-delimited.
         */
        public function replyto_link($text = null, $before = null, $after = null, $classes = "") {
            if (!Config::current()->allow_nested_comments) return;

            fallback($text, __("Reply"));

            $name = strtolower(get_class($this));

            echo $before.'<a href="'.self_url().'&amp;replyto'.$name.'='.$this->id.'#add_comment" title="Reply to '.$this->author.'" class="'.($classes ? $classes." " : '').$name.'_reply_link replyto" id="comment_reply">'.$text.'</a>'.$after;
        }

        /**
         * Function: notify
         * Emails everyone that wants to be notified for a new comment
         *
         * Parameters:
         *     $author - The new comment author
         *     $body - The new comment message
         *     $post - The new comment post ID
         */
        static function notify($author, $body, $post) {
            $post = new Post($post);
            $emails = SQL::current()->select("comments",
                                             "author_email",
                                             array("notify" => 1, "post_id" => $post->id))->fetchAll();

            $list = array();
            foreach ($emails as $email)
                $list[] = $email["author_email"];

            $config = Config::current();

            $to = implode(", ", $list);
            $subject = $config->name.__("New Comment");
            $message = __("There is a new comment at ").$post->url()."\n Poster: ".fix($author)."\n Message: ".fix($body);
            $headers = "From:".$config->email."\r\n" .
                                   "Reply-To:".$config->email."\r\n".
                                   "X-Mailer: PHP/".phpversion();
            $sent = email($to, $subject, $message, $headers);
        }

        # !! DEPRECATED AFTER 2.0 !!
        public function post() {
            return new Post($this->post_id);
        }

        # !! DEPRECATED AFTER 2.0 !!
        public function user() {
            if ($this->user_id)
                return new User($this->user_id);
            else
                return false;
        }
    }

    class Threaded extends Comment {
    
        public $parents  = array();
        public $children = array();

        function __construct($comments) {
            foreach ($comments as $comment) {
                if ($comment['parent_id'] === 0)
                    $this->parents[$comment['id']][] = $comment;
                else
                    $this->children[$comment['parent_id']][] = $comment;
            }
        }
    
        private function format_comment($comment, $depth) {
            for ($depth; $depth > 0; $depth--)
                echo "\t";
    
            echo $comment['text'];
            echo "\n";
        }

        private function print_parent($comment, $depth = 0) {
            foreach ($comment as $c) {
                $this->format_comment($c, $depth);
    
                if (isset($this->children[$c['id']]))
                    $this->print_parent($this->children[$c['id']], $depth + 1);
            }
        }
    
        public function print_comments() {
            foreach ($this->parents as $c)
                $this->print_parent($c);
        }
    
    }
