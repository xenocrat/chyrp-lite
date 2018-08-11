<?php
   /**
    * Class: XMLRPC
    * Extensible XML-RPC interface for remotely controlling your Chyrp install.
    */
    class XMLRPC extends IXR_Server {
       /**
        * Function: __construct
        * Sets appropriate error and exception handlers and registers the methods.
        */
        public function __construct() {
            set_error_handler("XMLRPC::error_handler");
            set_exception_handler("XMLRPC::exception_handler");

            $methods = array(
                # Pingbacks:
                "pingback.ping"             => "this:pingback_ping",

                # MetaWeblog:
                "metaWeblog.getRecentPosts" => "this:metaWeblog_getRecentPosts",
                "metaWeblog.getCategories"  => "this:metaWeblog_getCategories",
                "metaWeblog.newMediaObject" => "this:metaWeblog_newMediaObject",
                "metaWeblog.getPost"        => "this:metaWeblog_getPost",
                "metaWeblog.newPost"        => "this:metaWeblog_newPost",
                "metaWeblog.editPost"       => "this:metaWeblog_editPost",
                "metaWeblog.deletePost"     => "this:metaWeblog_deletePost",
                "metaWeblog.getUsersBlogs"  => "this:metaWeblog_getUsersBlogs"
            );

            Trigger::current()->filter($methods, "xmlrpc_methods");
            parent::__construct($methods);
        }

       /**
        * Function: pingback_ping
        * Receive and register pingbacks. Calls the @pingback@ trigger.
        */
        public function pingback_ping($args) {
            $trigger    = Trigger::current();
            $source     = add_scheme(str_replace("&amp;", "&", fallback($args[0], "")));
            $target     = add_scheme(str_replace("&amp;", "&", fallback($args[1], "")));
            $chyrp_host = str_replace(array("http://www.",
                                            "http://",
                                            "https://www.",
                                            "https://"), "", Config::current()->url);

            # No need to continue without a responder for the pingback trigger.
            if (!$trigger->exists("pingback"))
                return new IXR_Error(49, __("Pingback support is disabled for this site."));

            if ($target == $source)
                return new IXR_Error(0, __("The from and to URLs cannot be the same."));

            if (!is_url($target) or !substr_count($target, $chyrp_host))
                return new IXR_Error(32, __("The URL for our page is not valid."));

            if (!is_url($source))
                return new IXR_Error(16, __("The URL for your page is not valid."));

            if (preg_match("/url=([^&#]+)/", $target, $url))
                $post = new Post(array("url" => $url[1]));
            else
                $post = Post::from_url($target);

            if ($post->no_results)
                return new IXR_Error(33, __("We have not published at that URL."));

            # Grab the page that linked here.
            $content = get_remote($source);

            if (empty($content))
                return new IXR_Error(16, __("You have not published at that URL."));

            # Get the title and body of the page.
            preg_match("/<title[^>]*>([^<]+)<\/title>/i", $content, $title);
            preg_match("/<body[^>]*>(.+)<\/body>/is", $content, $body);
            preg_match("/<meta charset=[\"\']?([^ \"\'\/>]+)/i", $content, $charset);

            if (empty($title[1]) or empty($body[1]))
                return new IXR_Error(0, __("Your page could not be parsed."));

            $title = trim(fix($title[1]));
            $body = strip_tags($body[1], "<a>");
            $charset = oneof($charset[1], "UTF-8");
            $url = preg_quote($target, "/");

            # Convert the source encoding to UTF-8 if possible to ensure we render it correctly.
            if (function_exists("mb_convert_encoding")) {
                $title = mb_convert_encoding($title, "UTF-8", $charset);
                $body = mb_convert_encoding($body, "UTF-8", $charset);
            }

            # Search the page for our link.
            if (!preg_match("/<a[^>]*{$url}[^>]*>([^>]+)<\/a>/i", $body, $context)) {
                $url = preg_quote(str_replace("&", "&amp;", $target), "/");

                if (!preg_match("/<a[^>]*{$url}[^>]*>([^>]+)<\/a>/i", $body, $context)) {
                    $url = preg_quote(str_replace("&", "&#038;", $target), "/");

                    if (!preg_match("/<a[^>]*{$url}[^>]*>([^>]+)<\/a>/i", $body, $context))
                        return new IXR_Error(17, __("Your page does not link to our page."));
                }
            }

            # Build an excerpt of up to 200 characters. Tries to start with the sentence containing the link.
            $regex = "/.*?([^\.>]{0,100}".preg_quote($context[0], "/")."[^<]*)/s";
            $excerpt = truncate(normalize(strip_tags(preg_replace($regex, "$1", $body))), 200);

            # Pingback responder must return a single string on success or IXR_Error on failure.
            return $trigger->call("pingback", $post, $target, $source, $title, $excerpt);
        }

       /**
        * Function: metaWeblog_getRecentPosts
        * Returns a list of recent posts that the user can edit/delete.
        */
        public function metaWeblog_getRecentPosts($args) {
            $this->auth(fallback($args[1]), fallback($args[2]));
            global $user;

            $trigger = Trigger::current();
            $where = array("feather" => XML_RPC_FEATHER);
            $limit = (int) fallback($args[3], Config::current()->posts_per_page);

            if ($user->group->can("view_own_draft", "edit_own_draft"))
                $where["status"] = array("public", "draft");
            else
                $where["status"] = "public";

            if (!$user->group->can("edit_draft", "edit_post", "delete_draft", "delete_post"))
                $where["user_id"] = $user->id;

            $results = Post::find(array("placeholders" => true,
                                        "where" => $where,
                                        "order" => "created_at DESC, id DESC"));

            $posts = array();

            for ($i = 0; $i < $limit; $i++)
                if (isset($results[0][$i]))
                    $posts[] = new Post(null, array("read_from" => $results[0][$i], "filter" => false));

            $array = array();

            $title = XML_RPC_TITLE;
            $description = XML_RPC_DESCRIPTION;

            foreach ($posts as $post) {
                $struct = array("postid"      => $post->id,
                                "userid"      => $post->user_id,
                                "title"       => $post->$title,
                                "dateCreated" => new IXR_Date(when("Ymd\TH:i:se", $post->created_at)),
                                "description" => $post->$description,
                                "link"        => unfix($post->url()),
                                "permaLink"   => unfix(url("id/post/".$post->id, MainController::current())),
                                "mt_basename" => $post->clean);

                $array[] = $trigger->filter($struct, "metaWeblog_getPost", $post);
            }

            return $array;
        }

       /**
        * Function: metaWeblog_getCategories
        * Returns a list of available categories.
        */
        public function metaWeblog_getCategories($args) {
            $this->auth(fallback($args[1]), fallback($args[2]));

            $categories = array();
            return Trigger::current()->filter($categories, "metaWeblog_getCategories");
        }

       /**
        * Function: metaWeblog_newMediaObject
        * Uploads a file to the server.
        */
        public function metaWeblog_newMediaObject($args) {
            $this->auth(fallback($args[1]), fallback($args[2]));
            global $user;

            if (!$user->group->can("add_post", "add_draft"))
                return new IXR_Error(403, __("You do not have sufficient privileges to add posts."));

            fallback($args[3], array());
            fallback($args[3]["name"]);
            fallback($args[3]["bits"]);

            $uploads_path = MAIN_DIR.Config::current()->uploads_path;
            $filename = upload_filename($args[3]["name"]);
            $contents = base64_decode($args[3]["bits"]);

            if (!is_dir($uploads_path))
                throw new Exception(__("Upload path does not exist."), 500);

            if (!is_writable($uploads_path))
                throw new Exception(__("Upload path is not writable."), 500);

            if (!@file_put_contents($uploads_path.$filename, $contents))
                throw new Exception(__("Failed to write file to disk."), 500);

            return array("file" => $filename, "url" => uploaded($filename));
        }

       /**
        * Function: metaWeblog_getPost
        * Retrieves a specified post.
        */
        public function metaWeblog_getPost($args) {
            $this->auth(fallback($args[1]), fallback($args[2]));
            global $user;

            $post = new Post(fallback($args[0]), array("filter" => false,
                                                       "where" => array("feather" => XML_RPC_FEATHER)));

            if ($post->no_results)
                return new IXR_Error(404, __("Post not found."));

            if (!$post->editable($user))
                return new IXR_Error(403, __("You do not have sufficient privileges to edit this post."));

            $title = XML_RPC_TITLE;
            $description = XML_RPC_DESCRIPTION;

            $struct = array("postid"      => $post->id,
                            "userid"      => $post->user_id,
                            "title"       => $post->$title,
                            "dateCreated" => new IXR_Date(when("Ymd\TH:i:se", $post->created_at)),
                            "description" => $post->$description,
                            "link"        => unfix($post->url()),
                            "permaLink"   => unfix(url("id/post/".$post->id, MainController::current())),
                            "mt_basename" => $post->clean);

            Trigger::current()->filter($struct, "metaWeblog_getPost", $post);
            return $struct;
        }

       /**
        * Function: metaWeblog_newPost
        * Creates a new post.
        */
        public function metaWeblog_newPost($args) {
            $this->auth(fallback($args[1]), fallback($args[2]));
            global $user;

            if (!$user->group->can("add_post", "add_draft"))
                return new IXR_Error(403, __("You do not have sufficient privileges to add posts."));

            fallback($args[3], array());
            fallback($args[3]["description"], "");
            fallback($args[3]["mt_basename"], "");
            fallback($args[3]["title"], "");
            fallback($args[3]["post_status"]);
            fallback($args[3]["mt_allow_pings"], "open");

            $trigger = Trigger::current();
            $content = $args[3]["description"];

            # Support for extended content.
            if (!empty($args[3]["mt_text_more"]))
                $content .= "<!--more-->".$args[3]["mt_text_more"];

            # Add excerpt to content so it isn't lost.
            if (!empty($args[3]["mt_excerpt"]))
                $content = $args[3]["mt_excerpt"]."\n\n".$content;

            # Convert statuses from WordPress to Chyrp equivalents.
            switch ($args[3]["post_status"]) {
                case "draft":
                    $status = "draft";
                    break;
                case "future":
                    $status = "scheduled";
                    break;
                case "private":
                    $status = "registered_only";
                    break;
                default:
                    $status = "public";
            }

            $trigger->call("metaWeblog_newPost_preQuery", $args[3]);

            $post = Post::add(array(XML_RPC_TITLE => $args[3]["title"],
                                    XML_RPC_DESCRIPTION => $content),
                              sanitize(oneof($args[3]["mt_basename"], $args[3]["title"]), true, true, 80),
                              "",
                              XML_RPC_FEATHER,
                              $user->id,
                              null,
                              ($user->group->can("add_post")) ? $status : "draft",
                              oneof($this->convertFromDateCreated($args[3]), datetime()),
                              null,
                              ($args[3]["mt_allow_pings"] == "open"));

            $trigger->call("metaWeblog_newPost", $args[3], $post);
            return $post->id;
        }

       /**
        * Function: metaWeblog_editPost
        * Updates a specified post.
        */
        public function metaWeblog_editPost($args) {
            $this->auth(fallback($args[1]), fallback($args[2]));
            global $user;

            fallback($args[0]);
            fallback($args[3], array());
            fallback($args[3]["description"], "");
            fallback($args[3]["mt_basename"], "");
            fallback($args[3]["title"], "");
            fallback($args[3]["post_status"]);

            $trigger = Trigger::current();
            $content = $args[3]["description"];

            # Support for extended content.
            if (!empty($args[3]["mt_text_more"]))
                $content .= "<!--more-->".$args[3]["mt_text_more"];

            # Add excerpt to content so it isn't lost.
            if (!empty($args[3]["mt_excerpt"]))
                $content = $args[3]["mt_excerpt"]."\n\n".$content;

            $post = new Post($args[0], array("filter" => false,
                                             "where" => array("feather" => XML_RPC_FEATHER)));

            if ($post->no_results)
                return new IXR_Error(404, __("Post not found."));

            if (!$post->editable($user))
                return new IXR_Error(403, __("You do not have sufficient privileges to edit this post."));

            # Convert statuses from WordPress to Chyrp equivalents.
            switch ($args[3]["post_status"]) {
                case "publish":
                    $status = "public";
                    break;
                case "draft":
                    $status = "draft";
                    break;
                case "future":
                    $status = "scheduled";
                    break;
                case "private":
                    $status = "registered_only";
                    break;
                default:
                    $status = $post->status;
            }

            $trigger->call("metaWeblog_editPost_preQuery", $args[3], $post);

            $post = $post->update(array(XML_RPC_TITLE => $args[3]["title"],
                                        XML_RPC_DESCRIPTION => $content),
                                  null,
                                  $post->pinned,
                                  ($user->group->can("edit_own_post", "edit_post")) ? $status : $post->status,
                                  oneof(sanitize($args[3]["mt_basename"], true, true, 80), $post->clean),
                                  null,
                                  oneof($this->convertFromDateCreated($args[3]), $post->created_at));

            $trigger->call("metaWeblog_editPost", $args[3], $post);
            return true;
        }

       /**
        * Function: metaWeblog_deletePost
        * Deletes a specified post.
        */
        public function metaWeblog_deletePost($args) {
            $this->auth(fallback($args[2]), fallback($args[3]));
            global $user;

            $post = new Post(fallback($args[1]), array("filter" => false,
                                                       "where" => array("feather" => XML_RPC_FEATHER)));

            if ($post->no_results)
                return new IXR_Error(404, __("Post not found."));

            if (!$post->deletable($user))
                return new IXR_Error(403, __("You do not have sufficient privileges to delete this post."));

            Post::delete($post->id);
            return true;
        }

       /**
        * Function: metaWeblog_getUsersBlogs
        * Returns information about the blog.
        */
        public function metaWeblog_getUsersBlogs($args) {
            $this->auth(fallback($args[1]), fallback($args[2]));

            return array(array("url"      => unfix(url("/", MainController::current())),
                               "blogName" => Config::current()->name,
                               "blogid"   => "1"));
        }

       /**
        * Function: convertFromDateCreated
        * Converts an IXR_Date (in $args["dateCreated"]) to SQL date format.
        */
        private function convertFromDateCreated($args) {
            if (array_key_exists("dateCreated", $args))
                return when("Y-m-d H:i:s", $args["dateCreated"]->getIso());
            else
                return null;
        }

       /**
        * Function: auth
        * Authenticates a user's login and password.
        */
        private function auth($login, $password) {
            if (!Config::current()->enable_xmlrpc)
                throw new Exception(__("XML-RPC support is disabled for this site."), 501);

            if (!User::authenticate($login, $password))
                throw new Exception(__("Incorrect username and/or password."), 403);

            global $user;
            $user = new User(null, array("where" => array("login" => $login)));
        }

       /**
        * Function: error_handler
        */
        public static function error_handler($errno, $message, $file, $line) {
            # Test for suppressed errors and excluded error levels.
            if (!(error_reporting() & $errno))
                return true;

            $normalized = str_replace(array("\t", "\n", "\r", "\0", "\x0B"), " ", $message);

            if (DEBUG)
                error_log("ERROR: ".$errno." ".strip_tags($normalized)." (".$file." on line ".$line.")");

            throw new Exception($message, 500);
        }

       /**
        * Function: exception_handler
        */
        public static function exception_handler($e) {
            $err = new IXR_Error($e->getCode(), $e->getMessage());
            echo $err->getXml();
        }
    }
