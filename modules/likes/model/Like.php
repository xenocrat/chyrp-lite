<?php
    /**
     * Class: Like
     * The model for the Like SQL table.
     *
     * See Also:
     *     <Model>
     */
    class Like extends Model {
        public $belongs_to = array("post", "user");
        public $post_id;
        public $user_id;
        public $session_hash;
        public $total_count;

        /**
         * Function: __construct
         * See Also:
         *     <Model::grab>
         */
        public function __construct($post_id = null, $user_id = null) {
            # User attributes.
            $this->user_id = isset($user_id) ? $user_id : Visitor::current()->id ;
            $this->session_hash = md5($this->user_id.$_SERVER['REMOTE_ADDR']);

            # Post attributes.
            $this->total_count = 0;
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

            $people = self::fetchPeople();

            foreach ($people as $person) {
                if ($person["session_hash"] == $this->session_hash and !array_key_exists($this->post_id, $_SESSION["likes"]))
                    $_SESSION["likes"][$this->post_id] = null; # Their hash is in the database but nothing in their session.

                if (!empty($this->user_id) and $person["user_id"] == $this->user_id)
                    $_SESSION["likes"][$this->post_id] = true;
            }

            return isset($_SESSION["likes"][$this->post_id]); # Returns false for null entries.
        }

        /**
         * Function: like
         * Adds a like to the database.
         */
        public function like() {
            if (empty($this->post_id))
                return;

            if (!array_key_exists($this->post_id, $_SESSION["likes"]))
                SQL::current()->insert("likes",
                                       array("post_id" => $this->post_id,
                                             "user_id" => $this->user_id,
                                             "timestamp" => datetime(),
                                             "session_hash" => $this->session_hash));

            $_SESSION["likes"][$this->post_id] = !empty($this->user_id);
        }

        /**
         * Function: unlike
         * Removes a like from the database.
         */
        public function unlike() {
            if (empty($this->post_id))
                return;

            if ($_SESSION["likes"][$this->post_id])
                SQL::current()->delete("likes",
                                       array("post_id" => $this->post_id,
                                             "user_id" => $this->user_id),
                                       array("LIMIT" => 1));

            unset($_SESSION["likes"][$this->post_id]);
        }

        public function fetchPeople() {
            if (empty($this->post_id))
                return array();

            $people = SQL::current()->select("likes",
                                             "session_hash, user_id",
                                             array("post_id" => $this->post_id))->fetchAll();

            $this->total_count = count($people);
            return $people;
        }

        public function fetchCount(){
            if (empty($this->post_id))
                return 0;

            $count = SQL::current()->count("likes",
                                     array("post_id" => $this->post_id));

            $this->total_count = $count;
            return $count;
        }

        static function install() {
            $config = Config::current();
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
                                                                    # Add these strings to the .pot file:
            Group::add_permission("like_post", "Like Posts");       # __("Like Posts");
            Group::add_permission("unlike_post", "Unlike Posts");   # __("Unlike Posts");

            $set = array($config->set("module_like",
                                      array("showOnFront" => true,
                                            "likeWithText" => false,
                                            "likeImage" => $config->chyrp_url."/modules/likes/images/pink.svg")));
        }

        static function uninstall() {
            SQL::current()->query("DROP TABLE __likes");

            Group::remove_permission("like_post");
            Group::remove_permission("unlike_post");

            Config::current()->remove("module_like");
        }
    }
