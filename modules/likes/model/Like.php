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
         */
        static function add($post_id, $user_id, $timestamp, $session_hash) {
            $sql = SQL::current();

            # Test for an existing key_session_hash pair.
            $old = new self(array("post_id" => $post_id,
                                  "session_hash" => $session_hash));

            if (!$old->no_results)
                return $old;

            $sql->insert("likes",
                         array("post_id" => $post_id,
                               "user_id" => $user_id,
                               "timestamp" => $timestamp,
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

            fallback($user, Visitor::current());
            return ($user->group->can("unlike_post") and (logged_in() and $user->id == $this->user_id));
        }

        /**
         * Function: create
         * Creates a like in the visitor's session values.
         *
         * Parameters:
         *     $post_id - The ID of the blog post that was liked.
         */
        static function create($post_id) {
            self::discover($post_id);

            if (!array_key_exists($post_id, $_SESSION["likes"]))
                $new = self::add($post_id,
                                 Visitor::current()->id,
                                 datetime(),
                                 self::session_hash());

            # Set the session value so that we remember this like.
            $_SESSION["likes"][$post_id] = isset($new) ? $new->id : 0 ;

            Trigger::current()->call("like_post", $post_id);
        }

        /**
         * Function: remove
         * Removes a like from the visitor's session values.
         *
         * Parameters:
         *     $post_id - The ID of the blog post that was unliked.
         */
        static function remove($post_id) {
            self::discover($post_id);

            if (!empty($_SESSION["likes"][$post_id]))
                self::delete($_SESSION["likes"][$post_id]);

            # Unset the session value so that we forget this like.
            unset($_SESSION["likes"][$post_id]);

            Trigger::current()->call("unlike_post", $post_id);
        }

        /**
         * Function: discover
         * Determines if a visitor has liked a post and sets the session value.
         */
        static function discover($post_id) {
            fallback($_SESSION["likes"], array());

            if (!array_key_exists($post_id, $_SESSION["likes"]))
                if (logged_in()) {
                    $check = new self(array("post_id" => $post_id,
                                            "user_id" => Visitor::current()->id));

                    if (!$check->no_results)
                        $_SESSION["likes"][$post_id] = $check->id;
                } else {
                    $check = new self(array("post_id" => $post_id,
                                            "user_id" => Visitor::current()->id,
                                            "session_hash" => self::session_hash()));

                    if (!$check->no_results)
                        $_SESSION["likes"][$post_id] = null;
                }

            # A non-null value will attribute a like to this visitor.
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
                # SQLite does not support KEY or UNIQUE in CREATE.
                $sql->query("CREATE TABLE IF NOT EXISTS __likes (
                                 id INTEGER PRIMARY KEY AUTO_INCREMENT,
                                 post_id INTEGER NOT NULL,
                                 user_id INTEGER NOT NULL,
                                 timestamp DATETIME DEFAULT NULL,
                                 session_hash VARCHAR(32) NOT NULL,
                                 KEY key_post_id (post_id),
                                 KEY key_user_id (post_id, user_id),
                                 UNIQUE key_session_hash (post_id, session_hash)
                             ) DEFAULT CHARSET=utf8");
            } else {
                # MySQL does not support CREATE INDEX IF NOT EXISTS.
                $sql->query("CREATE TABLE IF NOT EXISTS __likes (
                                 id INTEGER PRIMARY KEY AUTO_INCREMENT,
                                 post_id INTEGER NOT NULL,
                                 user_id INTEGER NOT NULL,
                                 timestamp DATETIME DEFAULT NULL,
                                 session_hash VARCHAR(32) NOT NULL
                             )");
                $sql->query("CREATE INDEX IF NOT EXISTS key_post_id ON __likes (post_id)");
                $sql->query("CREATE INDEX IF NOT EXISTS key_user_id ON __likes (post_id, user_id)");
                $sql->query("CREATE UNIQUE INDEX IF NOT EXISTS key_session_hash ON __likes (post_id, session_hash)");
            }
        }

        /**
         * Function: uninstall
         * Drops the database table.
         */
        static function uninstall() {
            SQL::current()->query("DROP TABLE __likes");
        }
    }
