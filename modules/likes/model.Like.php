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
        public $user_name;
        public $session_hash;
        public $total_count;

        /**
         * Function: __construct
         * See Also:
         *     <Model::grab>
         */
        public function __construct($post_id = null, $user_id = null) {
            # user info
            $this->user_id = isset($user_id) ? $user_id : Visitor::current()->id ;
            $this->user_name = null;

            # post info
            $this->total_count = 0;
            $this->post_id = !empty($post_id) ? $post_id : null ;

            # inits
            $this->cookieInit();
        }

        /**
         * Function: like
         * Adds a like to the database.
         */
        public function like() {
            if (!Visitor::current()->group->can("like_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to like posts.", "likes"));

            if (!empty($this->post_id)) {
                SQL::current()->insert("likes",
                                       array("post_id" => $this->post_id,
                                             "user_id" => $this->user_id,
                                             "timestamp" => datetime(),
                                             "session_hash" => $this->session_hash));
            } else
                error(__("Error"), __("An ID is required to like a post.", "likes"));
        }

        /**
         * Function: unlike
         * Removes a like from the database.
         */
        public function unlike() {
            if (!Visitor::current()->group->can("unlike_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to unlike posts.", "likes"));

            if (!empty($this->post_id)) {
                if (!empty($this->user_id))
                    SQL::current()->delete("likes",
                                           array("post_id" => $this->post_id,
                                                 "user_id" => $this->user_id),
                                           array("LIMIT" => 1));
                else
                    SQL::current()->delete("likes",
                                           array("post_id" => $this->post_id,
                                                 "session_hash" => $this->session_hash),
                                           array("LIMIT" => 1));
            } else
                error(__("Error"), __("An ID is required to unlike a post.", "likes"));
        }

        public function fetchPeople() {
            $people = SQL::current()->select("likes",
                                             "session_hash, user_id",
                                             array("post_id" => $this->post_id))->fetchAll();

            $this->total_count = count($people);
            return $people;
        }

        public function fetchCount(){
            $count = SQL::current()->count("likes",
                                     array("post_id" => $this->post_id));

            $this->total_count = $count;
            return $count;
        }

        public function cookieInit() {
            if (!isset($_COOKIE["likes_sh"])) {
                $this->session_hash = md5($this->user_id.$this->getIP());
                setcookie("likes_sh", $this->session_hash, time() + 31104000, "/");
            } else
                $this->session_hash = fix($_COOKIE["likes_sh"]);
        }

        private function getIP() {
            if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) === TRUE)
                return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
            elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) === TRUE)
                return $_SERVER['HTTP_X_FORWARDED_FOR'];
            else
                return $_SERVER['REMOTE_ADDR'];
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
                              UNIQUE key_post_id_sh_pair (post_id, session_hash)
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
                $sql->query("CREATE UNIQUE INDEX IF NOT EXISTS key_post_id_sh_pair ON __likes (post_id, session_hash)");
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
