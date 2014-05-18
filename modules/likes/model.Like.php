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
        public $action;
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
        public function __construct($req = null, $user_id = null) {
            $this->action = isset($req["action"]) ? ($req["action"] == "unlike" ? "unlike" : "like") : null ;

            # user info
            $this->user_id = isset($user_id) ? $user_id : Visitor::current()->id ;
            $this->user_name = null;

            # post info
            $this->total_count = 0;
            $this->post_id = isset($req["post_id"]) ? (int)(fix($req["post_id"])) : null ;

            # inits
            $this->cookieInit();
        }

        /**
         * Function: like
         * Adds a like to the database.
         */
        public function like() {
        	if ($this->action == "like" and $this->post_id > 0) {
            	SQL::current()->insert("likes",
                                 array("post_id" => $this->post_id,
                                       "user_id" => $this->user_id,
                                       "timestamp" => datetime(),
                                       "session_hash" => $this->session_hash));
        	}
        	else throw new Exception("invalid params- action = $this->action and post_id = $this->post_id");
        }

        /**
         * Function: unlike
         * Removes a like from the database.
         */
        public function unlike() {
            if ($this->action == "unlike" and $this->post_id > 0) {
            	SQL::current()->delete("likes", array("post_id" => $this->post_id,
                                                      "session_hash" => $this->session_hash),
                                                array("LIMIT" => 1));
        	}
        	else throw new Exception("invalid params");
        }

        public function fetchPeople() {
        	$people = SQL::current()->select("likes",
        	                                 "session_hash",
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
            if(!isset($_COOKIE["likes_sh"]))    
                # cookie not there 
                # set null session if action is null
                if ($this->action == null)
                    $this->session_hash = null;
                else {
                    $time = time();	
                    setcookie("likes_sh", md5($this->getIP().$time), $time + 31104000, "/");
                    # print($_SERVER["REMOTE_ADDR"]);
                    # print(md5($_SERVER["REMOTE_ADDR"]));
                    $this->session_hash = md5($this->getIP().$time);
                }
            else $this->session_hash = fix($_COOKIE["likes_sh"]);
        }

        private function getIP() {
        	if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) === TRUE)
            	return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        	elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) === TRUE)
            	return $_SERVER['HTTP_X_FORWARDED_FOR'];
        	else return $_SERVER['REMOTE_ADDR'];
        
        }

        public function getText($count, $text) {
        	global $likes_replace_count;
        	$likes_replace_count = $count;
        	if (!function_exists('likes_preg_cb')) {
        		function likes_preg_cb($matches) {
		        	global $likes_replace_count;
        			$new_count = $likes_replace_count;
        			if (isset($matches[2])) {
        				$operator = $matches[3];
        				$num = $matches[4];
        				$new_count = $operator == "+" ? $likes_replace_count + $num : $likes_replace_count - $num;
        			}
        			return "<b>$new_count</b>";
        		}
        	}

        	$text = preg_replace_callback('/(%NUM(([+-])([0-9]+))?%)/', "likes_preg_cb", $text);
        	return $text;
        }

        static function install() {
            SQL::current()->query("CREATE TABLE IF NOT EXISTS __likes (
                                     id INTEGER(10) NOT NULL AUTO_INCREMENT,
                                     post_id INTEGER NOT NULL,
                                     user_id INTEGER NOT NULL,
                                     timestamp DATETIME DEFAULT NULL,
                                     session_hash VARCHAR(32) NOT NULL,
                                     PRIMARY KEY (id),
                                     KEY key_post_id (post_id),
                                     UNIQUE key_post_id_sh_pair (post_id, session_hash)
                                   ) DEFAULT CHARSET=utf8");

            Group::add_permission("like_post", "Like Posts");
            Group::add_permission("unlike_post", "Unlike Posts");

            $likeText = array(0 => "You like this.",
                              1 => "You and 1 other like this.",
                              2 => "You and %NUM% other like this.",
                              3 => "Be the first to like.",
                              4 => "1 person likes this.",
                              5 => "%NUM% people like this.",
                              6 => "Like",
                              7 => "Unlike");

            $config = Config::current();
            $set = array($config->set("module_like",
                                array("showOnFront" => true,
                                      "likeWithText" => false,
                                      "likeImage" => $config->chyrp_url."/modules/likes/images/like.png",
                                      "likeText" => $likeText)));
        }

        static function uninstall() {
            SQL::current()->query("DROP TABLE __likes");

            Group::remove_permission("like_post");
            Group::remove_permission("unlike_post");

            Config::current()->remove("module_like");
        }

    }
