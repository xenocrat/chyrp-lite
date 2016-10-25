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
        public $post_id;
        public $user_id;
        public $session_hash;

        /**
         * Function: __construct
         * See Also:
         *     <Model::grab>
         */
        public function __construct($post_id = null, $user_id = null) {
            $this->user_id = isset($user_id) ? $user_id : Visitor::current()->id ;
            $this->session_hash = md5($this->user_id.$_SERVER['REMOTE_ADDR']);
            $this->post_id = !empty($post_id) ? $post_id : null ;

            # Remember likes in the visitor's session for attribution.
            fallback($_SESSION["likes"], array());
        }

        /**
         * Function: resolve
         * Determine if a visitor has liked a post.
         */
        public function resolve() {
            if (empty($this->post_id))
                return null;

            # Set a fallback session value if we find this person in the database.
            if (!array_key_exists($this->post_id, $_SESSION["likes"]))
                foreach (self::fetchPeople() as $person) {
                    if ($person["session_hash"] == $this->session_hash)
                        $_SESSION["likes"][$this->post_id] = null;

                    if (!empty($this->user_id) and $person["user_id"] == $this->user_id)
                        $_SESSION["likes"][$this->post_id] = true;
                }

            # Session value of true or false will attribute a like to this person.
            return isset($_SESSION["likes"][$this->post_id]);
        }

        /**
         * Function: like
         * Adds a like to the database.
         */
        public function like() {
            if (empty($this->post_id))
                return;

            # Add the like only if it will be unique (no session entry of any value).
            if (!array_key_exists($this->post_id, $_SESSION["likes"]))
                SQL::current()->insert("likes",
                                       array("post_id" => $this->post_id,
                                             "user_id" => $this->user_id,
                                             "timestamp" => datetime(),
                                             "session_hash" => $this->session_hash));

            # Set the session value so that we remember this like.
            $_SESSION["likes"][$this->post_id] = !empty($this->user_id);

            Trigger::current()->call("like_post", $this->post_id, $this->user_id);
        }

        /**
         * Function: unlike
         * Removes a like from the database.
         */
        public function unlike() {
            if (empty($this->post_id))
                return;

            # Delete the like only if the person is registered (session value is true).
            if ($_SESSION["likes"][$this->post_id])
                SQL::current()->delete("likes",
                                       array("post_id" => $this->post_id,
                                             "user_id" => $this->user_id),
                                       array("LIMIT" => 1));

            # Unset the session value so that we forget this like.
            unset($_SESSION["likes"][$this->post_id]);

            Trigger::current()->call("unlike_post", $this->post_id, $this->user_id);
        }

        /**
         * Function: fetchPeople
         * Returns an array of user IDs and hashes attributed to the current post.
         */
        public function fetchPeople() {
            if (empty($this->post_id))
                return array();

            return SQL::current()->select("likes",
                                          "session_hash, user_id",
                                          array("post_id" => $this->post_id))->fetchAll();
        }

        /**
         * Function: fetchPeople
         * Returns the count of database entries attributed to the current post.
         */
        public function fetchCount(){
            if (empty($this->post_id))
                return 0;

            return SQL::current()->count("likes",
                                         array("post_id" => $this->post_id));
        }

        /**
         * Function: import
         * Adds a like to the database without affecting the session values.
         */
        static function import($post_id, $user_id, $timestamp, $session_hash) {
            $sql = SQL::current();

            $count = $sql->count("likes",
                                 array("post_id" => $post_id,
                                       "session_hash" => $session_hash));

            # Add the like only if it will not cause a key_session_hash conflict.
            if (!$count) {
                $sql->insert("likes",
                             array("post_id" => $post_id,
                                   "user_id" => $user_id,
                                   "timestamp" => $timestamp,
                                   "session_hash" => $session_hash));

                Trigger::current()->call("like_post", $post_id, $user_id);
            }
        }

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

        static function uninstall() {
            SQL::current()->query("DROP TABLE __likes");
        }
    }
