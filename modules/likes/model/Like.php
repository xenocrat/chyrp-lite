<?php
    /**
     * Class: Like
     * The model for the Likes SQL table.
     *
     * See Also:
     *     <Model>
     */
    class Like extends Model {
        public $belongs_to = array("post", "user");

        /**
         * Function: __construct
         *
         * See Also:
         *     <Model::grab>
         */
        public function __construct($like_id, $options = array()) {
            parent::grab($this, $like_id, $options);

            if ($this->no_results)
                return false;
        }

        /**
         * Function: find
         *
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array()) {
            return parent::search(get_class(), $options, $options_for_object);
        }

        /**
         * Function: add
         * Adds a like to the database.
         *
         * Parameters:
         *     $post_id - The ID of the blog post that was liked.
         *     $user_id - The ID of the user who liked the post.
         *     $timestamp - The datetime when the like was added.
         *     $session_hash - The session hash of the visitor.
         *
         * Returns:
         *     The newly created <Like>.
         *
         * Notes:
         *     Allows only one like per hash to avoid abuse by guests.
         */
        static function add($post_id, $user_id, $timestamp, $session_hash) {
            $sql = SQL::current();

            $old = new self(array("post_id"      => $post_id,
                                  "session_hash" => $session_hash));

            if (!$old->no_results)
                return false;

            $sql->insert("likes",
                         array("post_id"      => $post_id,
                               "user_id"      => $user_id,
                               "timestamp"    => $timestamp,
                               "session_hash" => $session_hash));

            $new = new self($sql->latest("likes"));
            Trigger::current()->call("add_like", $new);
            return $new;
        }

        /**
         * Function: delete
         * Deletes a like from the database.
         *
         * See Also:
         *     <Model::destroy>
         */
        static function delete($like_id) {
            parent::destroy(get_class(), $like_id);
        }

        /**
         * Function: deletable
         * Checks if the <User> can delete the like.
         */
        public function deletable($user = null) {
            if ($this->no_results)
                return false;

            $post = new Post($this->post_id);
            return $post->deletable($user);
        }

        /**
         * Function: editable
         * Checks if the <User> can edit the like.
         */
        public function editable($user = null) {
            if ($this->no_results)
                return false;

            $post = new Post($this->post_id);
            return $post->editable($user);
        }

        /**
         * Function: create
         * Creates a like and sets it in the visitor's session values.
         *
         * Parameters:
         *     $post_id - The ID of the blog post that was liked.
         *
         * Notes:
         *     Duplicate likes will be attributed ID 0 (non-removable).
         */
        static function create($post_id) {
            if (!isset($_SESSION["likes"][$post_id]))
                $new = self::add($post_id,
                                 Visitor::current()->id,
                                 datetime(),
                                 self::session_hash());

            $_SESSION["likes"][$post_id] = !empty($new) ? $new->id : 0 ;

            Trigger::current()->call("like_post", $post_id);
        }

        /**
         * Function: remove
         * Removes a like and unsets it in the visitor's session values.
         *
         * Parameters:
         *     $post_id - The ID of the blog post that was unliked.
         *
         * Notes:
         *     Guests' likes are removable until the session is destroyed.
         */
        static function remove($post_id) {
            if (!empty($_SESSION["likes"][$post_id]))
                self::delete($_SESSION["likes"][$post_id]);

            unset($_SESSION["likes"][$post_id]);

            Trigger::current()->call("unlike_post", $post_id);
        }

        /**
         * Function: discover
         * Determines if a visitor has liked a post and sets the session value.
         */
        static function discover($post_id) {
            if (logged_in()) {
                $check = new self(array("post_id" => $post_id,
                                        "user_id" => Visitor::current()->id));

                if (!$check->no_results)
                    $_SESSION["likes"][$post_id] = $check->id;
            }

            return isset($_SESSION["likes"][$post_id]);
        }

        /**
         * Function: session_hash
         * Returns a hash generated from the visitor's ID and IP address.
         */
        private static function session_hash() {
            return md5(Visitor::current()->id.$_SERVER['REMOTE_ADDR']);
        }

        /**
         * Function: install
         * Creates the database table.
         */
        static function install() {
            $sql = SQL::current();

            if ($sql->adapter == "mysql") {
                # SQLite does not support the KEY column definition.
                $sql->create("likes",
                             array("id INTEGER PRIMARY KEY AUTO_INCREMENT",
                                   "post_id INTEGER NOT NULL",
                                   "user_id INTEGER NOT NULL",
                                   "timestamp DATETIME DEFAULT NULL",
                                   "session_hash VARCHAR(32) NOT NULL",
                                   "KEY key_post_user (post_id, user_id)"));
            } else {
                # MySQL does not support CREATE INDEX IF NOT EXISTS.
                $sql->create("likes",
                             array("id INTEGER PRIMARY KEY AUTO_INCREMENT",
                                   "post_id INTEGER NOT NULL",
                                   "user_id INTEGER NOT NULL",
                                   "timestamp DATETIME DEFAULT NULL",
                                   "session_hash VARCHAR(32) NOT NULL"));
                $sql->query("CREATE INDEX IF NOT EXISTS key_post_user ON \"__likes\" (post_id, user_id)");
            }
        }

        /**
         * Function: uninstall
         * Drops the database table.
         */
        static function uninstall() {
            SQL::current()->drop("likes");
        }
    }
