<?php
    require_once "model.Like.php";

    class Likes extends Modules {
        static function __install() {
            Like::install();
        }

        static function __uninstall($confirm) {
            if ($confirm) Like::uninstall();
        }

        public function admin_head() {
            $config = Config::current();
?>
        <link rel="stylesheet" href="<?php echo $config->chyrp_url; ?>/modules/likes/admin.css" type="text/css" media="screen" />
<?php
        }

        static function admin_like_settings($admin) {
            $config = Config::current();

            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (empty($_POST))
                return $admin->display("like_settings");

            if (!isset($_POST['hash']) or $_POST['hash'] != $config->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            $likeText = array();
            foreach($_POST as $key => $value) {
            	if (strstr($key, "likeText-")) {
            		$exploded_array = explode("-", $key, 2);
            		$likeText[$exploded_array[1]] = strip_tags(stripslashes($value));
            	}
            }

            $set = array($config->set("module_like",
                                array("showOnFront" => isset($_POST['showOnFront']),
                                      "likeWithText" => isset($_POST['likeWithText']),
                                      "likeImage" => $_POST['likeImage'],
                                      "likeText" => $likeText)));

            if (!in_array(false, $set))
                Flash::notice(__("Settings updated."), "/admin/?action=like_settings");
        }

        static function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["like_settings"] = array("title" => __("Like", "like"));

            return $navs;
        }

        static function route_like() {
            $request["action"] = $_GET['action'];
            $request["post_id"] = $_GET['post_id'];

            $like = new Like($request, Visitor::current()->id);
        }

        static function stylesheets($styles) {
            $styles[] = Config::current()->chyrp_url."/modules/likes/style.css";
            return $styles;
        }

        static function scripts($scripts) {
            $scripts[] = Config::current()->chyrp_url."/modules/likes/javascript.php";
            return $scripts;
        }

        static function ajax_like() {
            header("Content-type: text/json");
            header("Content-Type: application/x-javascript", true);

            if (!isset($_REQUEST["action"]) or !isset($_REQUEST["post_id"])) exit();
            
            $user_id = Visitor::current()->id;
            $likeSetting = Config::current()->module_like;
            $responseObj = array();
            $responseObj["uid"] = $user_id;
            $responseObj["success"] = true;
            
            try {
                $like = new Like($_REQUEST, $user_id);
                $likeText = "";
                switch ($like->action) {
                	case "like":
                        header("Content-type: text/json");
                        header("Content-Type: application/x-javascript", true);
                        $like->like();
                        $like->fetchCount();
                        if ($like->total_count == 1)
                        	# $this->text_default[0] = "You like this post.";
                            $likeText = $like->getText($like->total_count, $likeSetting["likeText"][0]);
                        elseif ($like->total_count == 2)
                        	# $this->text_default[1] = "You and 1 person like this post.";
                        	$likeText = $like->getText(1, $likeSetting["likeText"][1]);
                        else {
                            $like->total_count--;
                        	# $this->text_default[2] = "You and %NUM% people like this post.";
                        	$likeText = $like->getText($like->total_count, $likeSetting["likeText"][2]);
                        }
                	break;
                	default: throw new Exception("invalid action");
                }

                $responseObj["likeText"] = $likeText;
            }
            catch(Exception $e) {
                $responseObj["success"] = false;
                $responseObj["error_txt"] = $e->getMessage();
            }
            echo json_encode($responseObj);
        }

        static function ajax_unlike() {
            header("Content-type: text/json");
            header("Content-Type: application/x-javascript", true);

            if (!isset($_REQUEST["action"]) or !isset($_REQUEST["post_id"])) exit();
            
            $user_id = Visitor::current()->id;
            $likeSetting = Config::current()->module_like;
            $responseObj = array();
            $responseObj["uid"] = $user_id;
            $responseObj["success"] = true;
            
            try {
                $like = new Like($_REQUEST, $user_id);
                $likeText = "";
                switch ($like->action) {
                    case "unlike":
                        $like->unlike();
                        $like->fetchCount();
                        if ($like->total_count > 1) {
                            # $this->text_default[5] = "%NUM% people like this post.";
                            $likeText = $like->getText($like->total_count, $likeSetting["likeText"][5]);
                        } elseif ($like->total_count == 1) {
                            # $this->text_default[4] = "1 person likes this post.";
                            $likeText = $like->getText($like->total_count, $likeSetting["likeText"][4]);
                        } elseif ($like->total_count == 0)
                            $likeText = $like->getText($like->total_count, $likeSetting["likeText"][3]);
                    break;
                    default: throw new Exception("invalid action");
                }

                $responseObj["likeText"] = $likeText;
            }
            catch(Exception $e) {
                $responseObj["success"] = false;
                $responseObj["error_txt"] = $e->getMessage();
            }
            echo json_encode($responseObj);
        }

        static function delete_post($post) {
            SQL::current()->delete("likes", array("post_id" => $post->id));
        }

        static function delete_user($user) {
            SQL::current()->update("likes", array("user_id" => $user->id), array("user_id" => 0));
        }

        public function post($post) {
            $post->has_many[] = "likes";
            $post->get_likes = self::get_likes($post);
        }

        static function get_likes($post) {
            $config = Config::current();
            $route = Route::current();
            $visitor = Visitor::current();
            $likeSetting = $config->module_like;

            if (!$visitor->group->can("like_post")) return;
            if ($likeSetting["showOnFront"] == false and $route->action == "index") return;

            $request["action"] = $route->action;
            $request["post_id"] = $post->id;
            $like = new Like($request, $visitor->id);
            $like->cookieInit();
            $hasPersonLiked = false;

            if ($like->session_hash != null) {
                $people = $like->fetchPeople();
                if (count($people) != 0)
                    foreach ($people as $person)
                        if ($person["session_hash"] == $like->session_hash) {
                            $hasPersonLiked = true;
                            break;
                        }
            } else $like->fetchCount();

            $returnStr = "<div class='likes' id='likes_post-$post->id'>";

            if (!$hasPersonLiked) {
                $returnStr.= "<a class='like' href=\"javascript:likes.like($post->id);\" title='".
                    ($like->total_count ? $likeSetting["likeText"][6] : "")."' >";
                $returnStr.= "<img src=\"".$likeSetting["likeImage"]."\" alt='Like Post-$post->id' />";
                if ($likeSetting["likeWithText"]) # $this->text_default[6] = "Like";
                    $returnStr.= "(".$likeSetting["likeText"][6].") ";
                $returnStr.= "</a><span class='text'>";
                if ($like->total_count == 0)
                    # $this->text_default[3] = "Be the first to like.";
                    $returnStr.= $like->getText($like->total_count, $likeSetting["likeText"][3]);
                elseif ($like->total_count == 1)
                    # $this->text_default[4] = "1 person likes this post.";
                    $returnStr= $returnStr.$like->getText($like->total_count, $likeSetting["likeText"][4]);
                elseif ($like->total_count > 1)
                    # $this->text_default[5] = "%NUM% people like this post.";
                    $returnStr.= $like->getText($like->total_count, $likeSetting["likeText"][5]);
                $returnStr.= "</span>";
            } else {
                # $this->text_default[7] = "Unlike";
                if ($likeSetting["likeWithText"] and $visitor->group->can("unlike_post") and $hasPersonLiked)
                    $returnStr.= "<a class='liked' href=\"javascript:likes.unlike($post->id);\"><img src=\"".$likeSetting["likeImage"]."\" alt='Like Post-$post->id' />(".$likeSetting["likeText"][7].") </a><span class='text'>";
                else
                    $returnStr.= "<a class='liked'><img src=\"".$likeSetting["likeImage"]."\" alt='Like Post-$post->id' /></a><span class='text'>";
                if ($like->total_count == 1)
                    # $this->text_default[0] = "You like this post.";
                    $returnStr.= $like->getText($like->total_count, $likeSetting["likeText"][0]);
                elseif ($like->total_count == 2)
                    # $this->text_default[1] = "You and 1 person like this post.";
                    $returnStr.= $like->getText(1, $likeSetting["likeText"][1]);
                else {
                    $like->total_count--;
                    # $this->text_default[2] = "You and %NUM% people like this post.";
                    $returnStr.= $like->getText($like->total_count, $likeSetting["likeText"][2]);
                }

                $returnStr.= "</span>";
            }

            $returnStr.= "</div>";
            return $post->get_likes = $returnStr;
        }

/*
        public function post_likes_count_attr($attr, $post) {
                $req["post_id"] = $post->id;
                $like = new Like($req);
                return $count = $like->fetchCount();
        }
*/

        public function get_like_images() {
            $imagesDir = MODULES_DIR."/likes/images/";
            $images = glob($imagesDir . "*.{jpg,jpeg,png,gif,svg}", GLOB_BRACE);

            foreach ($images as $image) {
                $pattern = "/\/(\w.*)\/images\//";
                $image = preg_replace($pattern, "", $images);
                while (list($key, $val) = each($image))
                    $arr[] = Config::current()->chyrp_url."/modules/likes/images/$val";

                return array_combine($image, $arr);
            }
        }
    }
