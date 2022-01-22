<?php
    require_once "model".DIR."Comment.php";

    class Comments extends Modules {
        # Array: $caches
        # Query caches for methods.
        private $caches = array();

        public function __init() {
            fallback($_SESSION['comments'], array());

            $this->addAlias("metaWeblog_before_newPost", "metaWeblog_before_editPost");
        }

        static function __install() {
            Comment::install();

            Config::current()->set("module_comments",
                                   array("default_comment_status" => Comment::STATUS_DENIED,
                                         "comments_per_page" => 25,
                                         "auto_reload_comments" => 30,
                                         "enable_reload_comments" => false,
                                         "allowed_comment_html" => array("strong",
                                                                         "em",
                                                                         "blockquote",
                                                                         "code",
                                                                         "pre",
                                                                         "a")));

            Group::add_permission("add_comment", "Add Comments");
            Group::add_permission("add_comment_private", "Add Comments to Private Posts");
            Group::add_permission("edit_comment", "Edit Comments");
            Group::add_permission("edit_own_comment", "Edit Own Comments");
            Group::add_permission("delete_comment", "Delete Comments");
            Group::add_permission("delete_own_comment", "Delete Own Comments");
            Group::add_permission("code_in_comments", "Can Use HTML in Comments");

            Route::current()->add("comment/(id)/", "comment");
        }

        static function __uninstall($confirm) {
            if ($confirm)
                Comment::uninstall();

            Config::current()->remove("module_comments");

            Group::remove_permission("add_comment");
            Group::remove_permission("add_comment_private");
            Group::remove_permission("edit_comment");
            Group::remove_permission("edit_own_comment");
            Group::remove_permission("delete_comment");
            Group::remove_permission("delete_own_comment");
            Group::remove_permission("code_in_comments");

            Route::current()->remove("comment/(id)/");
        }

        public function list_permissions($names = array()) {
            $names["add_comment"]         = __("Add Comments", "comments");
            $names["add_comment_private"] = __("Add Comments to Private Posts", "comments");
            $names["edit_comment"]        = __("Edit Comments", "comments");
            $names["edit_own_comment"]    = __("Edit Own Comments", "comments");
            $names["delete_comment"]      = __("Delete Comments", "comments");
            $names["delete_own_comment"]  = __("Delete Own Comments", "comments");
            $names["code_in_comments"]    = __("Can Use HTML in Comments", "comments");
            return $names;
        }

        public function main_comment($main) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                Flash::warning(__("Please enter an ID to find a comment.", "comments"), "/");

            $comment = new Comment($_GET['id']);

            if ($comment->no_results)
                return false;

            redirect($comment->post->url()."#comment_".$comment->id);
        }

        public function main_most_comments($main) {
            $posts = Post::find(array("placeholders" => true));

            usort($posts[0], function ($a, $b) {
                $count_a = $this->get_post_comment_count($a["id"]);
                $count_b = $this->get_post_comment_count($b["id"]);

                if ($count_a == $count_b)
                    return 0;

                return ($count_a > $count_b) ? -1 : 1 ;
            });

            $main->display(array("pages".DIR."most_comments", "pages".DIR."index"),
                           array("posts" => new Paginator($posts, $main->post_limit)),
                           __("Most commented on posts", "comments"));
        }

        public function parse_urls($urls) {
            $urls['|/comment/([0-9]+)/|'] = '/?action=comment&amp;id=$1';
            return $urls;
        }

        private function add_comment() {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['post_id']) or !is_numeric($_POST['post_id']))
                error(__("No ID Specified"),
                      __("An ID is required to add a comment.", "comments"), null, 400);

            $post = new Post($_POST['post_id'], array("drafts" => true));

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            if (!Comment::creatable($post))
                show_403(__("Access Denied"), __("You cannot comment on this post.", "comments"));

            if (empty($_POST['body']))
                return array($post, false, __("Message can't be blank.", "comments"));

            if (empty($_POST['author']) or derezz($_POST['author']))
                return array($post, false, __("Author can't be blank.", "comments"));

            if (empty($_POST['author_email']))
                return array($post, false, __("Email address can't be blank.", "comments"));

            if (!is_email($_POST['author_email']))
                return array($post, false, __("Invalid email address.", "comments"));

            if (!empty($_POST['author_url']) and !is_url($_POST['author_url']))
                return array($post, false, __("Invalid website URL.", "comments"));

            if (!empty($_POST['author_url']))
                $_POST['author_url'] = add_scheme($_POST['author_url']);

            if (!logged_in() and !check_captcha())
                return array($post, false, __("Incorrect captcha response.", "comments"));

            fallback($_POST['author_url'], "");
            fallback($parent, (int) $_POST['parent_id'], 0);
            $notify = (!empty($_POST['notify']) and logged_in());

            $comment = Comment::create($_POST['body'],
                                       $_POST['author'],
                                       $_POST['author_url'],
                                       $_POST['author_email'],
                                       $post,
                                       $parent,
                                       $notify);

            return array($comment, true, (($comment->status == Comment::STATUS_APPROVED) ?
                                        __("Comment added.", "comments") :
                                        __("Your comment is awaiting moderation.", "comments")));
        }

        private function update_comment() {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"),
                      __("An ID is required to update a comment.", "comments"), null, 400);

            $comment = new Comment($_POST['id']);

            if ($comment->no_results)
                show_404(__("Not Found"), __("Comment not found.", "comments"));

            if (!$comment->editable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to edit this comment.", "comments"));

            fallback($_POST['created_at']);
            fallback($_POST['status'], $comment->status);
            fallback($_POST['author_email'], $comment->author_email);
            fallback($_POST['author_url'], $comment->author_url);

            if (empty($_POST['body']))
                return array($comment, false, __("Message can't be blank.", "comments"));

            if (empty($_POST['author']) or derezz($_POST['author']))
                return array($comment, false, __("Author can't be blank.", "comments"));

            if (empty($_POST['author_email']) and $_POST['status'] != Comment::STATUS_PINGBACK)
                return array($comment, false, __("Email address can't be blank.", "comments"));

            if (!empty($_POST['author_email']) and !is_email($_POST['author_email']))
                return array($comment, false, __("Invalid email address.", "comments"));

            if (!empty($_POST['author_url']) and !is_url($_POST['author_url']))
                return array($comment, false, __("Invalid website URL.", "comments"));

            if (!empty($_POST['author_url']))
                $_POST['author_url'] = add_scheme($_POST['author_url']);

            $editor = Visitor::current()->group->can("edit_comment");
            $status = ($editor) ? $_POST['status'] : $comment->status ;
            $notify = (!empty($_POST['notify']) and logged_in());
            $created_at = ($editor) ? datetime($_POST['created_at']) : $comment->created_at ;

            $comment = $comment->update($_POST['body'],
                                        $_POST['author'],
                                        $_POST['author_url'],
                                        $_POST['author_email'],
                                        $status,
                                        $notify,
                                        $created_at);

            return array($comment, true, __("Comment updated.", "comments"));
        }

        public function main_update_comment() {
            list($comment, $success, $message) = $this->update_comment();
            $type = ($success) ? "notice" : "warning" ;
            Flash::$type($message, $comment->post->url());
        }

        public function admin_update_comment() {
            list($comment, $success, $message) = $this->update_comment();

            if (!$success)
                error(__("Error"), $message, null, 422);

            Flash::notice($message, "manage_comments");
        }

        public function ajax_add_comment() {
            list($comment, $success, $message) = $this->add_comment();
            json_response($message, $success);
        }

        public function ajax_update_comment() {
            list($comment, $success, $message) = $this->update_comment();
            json_response($message, $success);
        }

        public function admin_edit_comment($admin) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"),
                      __("An ID is required to edit a comment.", "comments"), null, 400);

            $comment = new Comment($_GET['id'], array("filter" => false));

            if ($comment->no_results)
                Flash::warning(__("Comment not found.", "comments"), "manage_comments");

            if (!$comment->editable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to edit this comment.", "comments"));

            $admin->display("pages".DIR."edit_comment", array("comment" => $comment));
        }

        public function admin_delete_comment($admin) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"),
                      __("An ID is required to delete a comment.", "comments"), null, 400);

            $comment = new Comment($_GET['id']);

            if ($comment->no_results)
                Flash::warning(__("Comment not found.", "comments"), "manage_comments");

            if (!$comment->deletable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to delete this comment.", "comments"));

            $admin->display("pages".DIR."delete_comment", array("comment" => $comment));
        }

        public function admin_destroy_comment() {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"),
                      __("An ID is required to delete a comment.", "comments"), null, 400);

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_comments");

            $comment = new Comment($_POST['id']);

            if ($comment->no_results)
                show_404(__("Not Found"), __("Comment not found.", "comments"));

            if (!$comment->deletable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to delete this comment.", "comments"));

            Comment::delete($comment->id);

            Flash::notice(__("Comment deleted.", "comments"));
            redirect("manage_".(($comment->status == Comment::STATUS_SPAM) ? "spam" : "comments"));
        }

        public function admin_manage_comments($admin) {
            if (!Comment::any_editable() and !Comment::any_deletable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to manage any comments.", "comments"));

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect("manage_comments/query/".str_ireplace("%2F", "", urlencode($_POST['query']))."/");

            fallback($_GET['query'], "");
            list($where, $params) = keywords($_GET['query'], "body LIKE :query", "comments");

            $where["status not"] = Comment::STATUS_SPAM;

            $visitor = Visitor::current();

            if (!$visitor->group->can("edit_comment", "delete_comment", true))
                $where["user_id"] = $visitor->id;

            $admin->display("pages".DIR."manage_comments", array("comments" => new Paginator(
                Comment::find(array("placeholders" => true,
                                    "where" => $where,
                                    "params" => $params,
                                    "order" => "post_id DESC, created_at ASC")), $admin->post_limit)));
        }

        public function admin_manage_spam($admin) {
            if (!Visitor::current()->group->can("edit_comment", "delete_comment", true))
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to manage any comments.", "comments"));

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect("manage_spam/query/".str_ireplace("%2F", "", urlencode($_POST['query']))."/");

            fallback($_GET['query'], "");
            list($where, $params) = keywords($_GET['query'], "body LIKE :query", "comments");

            $where["status"] = Comment::STATUS_SPAM;

            $admin->display("pages".DIR."manage_spam", array("comments" => new Paginator(
                Comment::find(array("placeholders" => true,
                                    "where" => $where,
                                    "params" => $params,
                                    "order" => "post_id DESC, created_at ASC")), $admin->post_limit)));
        }

        public function admin_bulk_comments() {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (!isset($_POST['comment']))
                Flash::warning(__("No comments selected."), "manage_comments");

            $comments = array_keys($_POST['comment']);

            if (isset($_POST['delete'])) {
                $count_delete = 0;

                foreach ($comments as $comment) {
                    $comment = new Comment($comment, array("filter" => false));

                    if (!$comment->deletable())
                        continue;

                    Comment::delete($comment->id);
                    $count_delete++;
                }

                if (!empty($count_delete))
                    Flash::notice(__("Selected comments deleted.", "comments"));
            }

            $false_positives = array();
            $false_negatives = array();

            $sql = SQL::current();

            if (isset($_POST['deny'])) {
                $count_deny = 0;

                foreach ($comments as $comment) {
                    $comment = new Comment($comment, array("filter" => false));

                    if (!$comment->editable())
                        continue;

                    if ($comment->status == Comment::STATUS_PINGBACK)
                        continue;

                    if ($comment->status == Comment::STATUS_SPAM)
                        $false_positives[] = $comment;

                    $comment->update($comment->body,
                                     $comment->author,
                                     $comment->author_url,
                                     $comment->author_email,
                                     Comment::STATUS_DENIED);

                    $count_deny++;
                }

                if (!empty($count_deny))
                    Flash::notice(__("Selected comments denied.", "comments"));
            }

            if (isset($_POST['approve'])) {
                $count_approve = 0;

                foreach ($comments as $comment) {
                    $comment = new Comment($comment, array("filter" => false));

                    if (!$comment->editable())
                        continue;

                    if ($comment->status == Comment::STATUS_PINGBACK)
                        continue;

                    if ($comment->status == Comment::STATUS_SPAM)
                        $false_positives[] = $comment;

                    $comment->update($comment->body,
                                     $comment->author,
                                     $comment->author_url,
                                     $comment->author_email,
                                     Comment::STATUS_APPROVED);

                    $count_approve++;
                }

                if (!empty($count_approve))
                    Flash::notice(__("Selected comments approved.", "comments"));
            }

            if (isset($_POST['spam'])) {
                $count_spam = 0;

                foreach ($comments as $comment) {
                    $comment = new Comment($comment, array("filter" => false));

                    if (!$comment->editable())
                        continue;

                    if ($comment->status == Comment::STATUS_PINGBACK)
                        continue;

                    $comment->update($comment->body,
                                     $comment->author,
                                     $comment->author_url,
                                     $comment->author_email,
                                     Comment::STATUS_SPAM);

                    $count_spam++;
                    $false_negatives[] = $comment;
                }

                if (!empty($count_spam))
                    Flash::notice(__("Selected comments marked as spam.", "comments"));
            }

            $trigger = Trigger::current();

            if (!empty($false_positives))
                $trigger->call("comments_false_positives", $false_positives);

            if (!empty($false_negatives))
                $trigger->call("comments_false_negatives", $false_negatives);

            redirect("manage_comments");
        }

        public function admin_comment_settings($admin) {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to change settings."));

            $config = Config::current();
            $comments_html = implode(", ", $config->module_comments["allowed_comment_html"]);
            $comments_status = array(Comment::STATUS_APPROVED => __("Approved", "comments"),
                                     Comment::STATUS_DENIED   => __("Denied", "comments"),
                                     Comment::STATUS_SPAM     => __("Spam", "comments"));

            if (empty($_POST))
                return $admin->display("pages".DIR."comment_settings",
                                       array("comments_html" => $comments_html,
                                             "comments_status" => $comments_status));

            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            fallback($_POST['default_comment_status'], Comment::STATUS_DENIED);
            fallback($_POST['allowed_comment_html'], "");
            fallback($_POST['comments_per_page'], 25);
            fallback($_POST['auto_reload_comments'], 30);

            # Split at the comma.
            $allowed_comment_html = explode(",", $_POST['allowed_comment_html']);

            # Remove whitespace.
            $allowed_comment_html = array_map("trim", $allowed_comment_html);

            # Remove duplicates.
            $allowed_comment_html = array_unique($allowed_comment_html);

            # Remove empties.
            $allowed_comment_html = array_diff($allowed_comment_html, array(""));

            $config = Config::current();
            $config->set("module_comments",
                         array("default_comment_status" => $_POST['default_comment_status'],
                               "allowed_comment_html" => $allowed_comment_html,
                               "comments_per_page" => (int) $_POST['comments_per_page'],
                               "auto_reload_comments" => (int) $_POST['auto_reload_comments'],
                               "enable_reload_comments" => isset($_POST['enable_reload_comments'])));

            Flash::notice(__("Settings updated."), "comment_settings");
        }

        public function admin_determine_action($action) {
            if ($action == "manage" and (Comment::any_editable() or Comment::any_deletable()))
                return "manage_comments";
        }

        public function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["comment_settings"] = array("title" => __("Comments", "comments"));

            return $navs;
        }

        public function manage_nav($navs) {
            if (!Comment::any_editable() and !Comment::any_deletable())
                return $navs;

            $sql = SQL::current();
            $comment_count = $sql->count("comments", array("status not" => Comment::STATUS_SPAM));
            $spam_count = $sql->count("comments", array("status" => Comment::STATUS_SPAM));

            $navs["manage_comments"] = array("title" => _f("Comments (%d)", $comment_count, "comments"),
                                             "selected" => array("edit_comment", "delete_comment"));

            if (Visitor::current()->group->can("edit_comment", "delete_comment"))
                $navs["manage_spam"] = array("title" => _f("Spam (%d)", $spam_count, "comments"));

            return $navs;
        }

        public function manage_posts_column_header() {
            echo '<th class="post_comments value">'.__("Comments", "comments").'</th>';
        }

        public function manage_posts_column($post) {
            echo '<td class="post_comments value"><a href="'.$post->url().
                 '#comments">'.$post->comment_count.'</a></td>';
        }

        public function manage_users_column_header() {
            echo '<th class="user_comments value">'.__("Comments", "comments").'</th>';
        }

        public function manage_users_column($user) {
            echo '<td class="user_comments value">'.$user->comment_count.'</td>';
        }

        public function ajax_reload_comments() {
            if (empty($_POST['post_id']) or !is_numeric($_POST['post_id']))
                error(__("No ID Specified"),
                      __("An ID is required to reload comments.", "comments"), null, 400);

            $post = new Post($_POST['post_id'], array("drafts" => true));

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            $last = (empty($_POST['last_comment'])) ? $post->created_at : $_POST['last_comment'] ;
            $text = _f("Comments added since %s", when("%c", $last, true), "comments");

            $ids = array();

            if ($post->latest_comment > $last) {
                $times = SQL::current()->select(
                    "comments",
                    array("id", "created_at"),
                    array("post_id" => $post->id,
                          "created_at >" => $last,
                          "status not" => Comment::STATUS_SPAM,
                          Comment::redactions()),
                    array("created_at ASC"));

                while ($row = $times->fetchObject()) {
                    $ids[] = $row->id;
                    $last = $row->created_at;
                }
            }

            json_response($text, array("comment_ids" => $ids, "last_comment" => $last));
        }

        public function ajax_show_comment() {
            if (empty($_POST['comment_id']) or !is_numeric($_POST['comment_id']))
                error(__("Error"),
                      __("An ID is required to show a comment.", "comments"), null, 400);

            $comment = new Comment($_POST['comment_id']);

            if ($comment->no_results)
                show_404(__("Not Found"), __("Comment not found.", "comments"));

            $main = MainController::current();
            $main->display("content".DIR."comment", array("comment" => $comment));
        }

        public function ajax_edit_comment() {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['comment_id']) or !is_numeric($_POST['comment_id']))
                error(__("Error"),
                      __("An ID is required to edit a comment.", "comments"), null, 400);

            $comment = new Comment($_POST['comment_id'], array("filter" => false));

            if ($comment->no_results)
                show_404(__("Not Found"), __("Comment not found.", "comments"));

            if (!$comment->editable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to edit this comment.", "comments"));

            $main = MainController::current();
            $main->display("forms".DIR."comment".DIR."edit", array("comment" => $comment));
        }

        public function ajax_destroy_comment() {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("Error"),
                      __("An ID is required to delete a comment.", "comments"), null, 400);

            $comment = new Comment($_POST['id']);

            if ($comment->no_results)
                show_404(__("Not Found"), __("Comment not found.", "comments"));

            if (!$comment->deletable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to delete this comment.", "comments"));

            Comment::delete($comment->id);
            json_response(__("Comment deleted.", "comments"), true);
        }

        public function links($links) {
            $config = Config::current();
            $route = Route::current();
            $main = MainController::current();

            if ($route->action == "view" and !empty($main->context["post"])) {
                $post = $main->context["post"];

                if (!$post->no_results) {
                    $feed_url = ($config->clean_urls) ?
                        rtrim($post->url(), "/")."/feed/" : $post->url()."&amp;feed" ;

                    $text = oneof($post->title(), ucfirst($post->feather));
                    $title = _f("Comments on &#8220;%s&#8221;", $text, "comments");

                    $links[] = array("href" => $feed_url,
                                     "type" => BlogFeed::type(),
                                     "title" => $title);
                }
            }

            return $links;
        }

        public function main_view() {
            if (isset($_POST['action']) and $_POST['action'] == "add_comment") {
                list($comment, $success, $message) = $this->add_comment();
                $type = ($success) ? "notice" : "warning" ;
                Flash::$type($message);

                if ($success) {
                    unset($_POST['body']);
                    unset($_POST['author']);
                    unset($_POST['author_email']);
                    unset($_POST['author_url']);
                }
            }

            return false;
        }

        public function main_unsubscribe($main) {
            fallback($_GET['email']);
            fallback($_GET['token']);

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                Flash::warning(__("Post not found."), "/");

            $post = new Post($_GET['id']);

            if ($post->no_results)
                Flash::warning(__("Post not found."), "/");

            if (!is_email($_GET['email']))
                Flash::warning(__("Invalid email address."), "/");

            if ($_GET['token'] != token($_GET['email']))
                Flash::notice(__("Invalid authentication token."), "/");

            SQL::current()->update("comments",
                                   array("post_id" => $post->id,
                                         "author_email" => $_GET['email']),
                                   array("notify" => false));

            Flash::notice(__("You have unsubscribed from the conversation.", "comments"), $post->url());
        }

        public function view_feed($context) {
            $trigger = Trigger::current();

            if (!isset($context["post"]))
                show_404(__("Not Found"), __("Post not found."));

            $post = $context["post"];
            $comments = $post->comments;
            $latest_timestamp = 0;
            $text = oneof($post->title(), ucfirst($post->feather));
            $title = _f("Comments on &#8220;%s&#8221;", $text, "comments");

            foreach ($comments as $comment)
                if (strtotime($comment->created_at) > $latest_timestamp)
                    $latest_timestamp = strtotime($comment->created_at);

            $feed = new BlogFeed();

            $feed->open($title,
                        Config::current()->description,
                        null,
                        $latest_timestamp);

            foreach ($comments as $comment) {
                $updated = ($comment->updated) ? $comment->updated_at : $comment->created_at ;

                $feed->entry(_f("Comment #%d", $comment->id, "comments"),
                             url("comment/".$comment->id),
                             $comment->body,
                             $comment->post->url()."#comment_".$comment->id,
                             $comment->created_at,
                             $updated,
                             $comment->author,
                             $comment->author_url);

                $trigger->call("comments_feed_item", $comment, $feed);
            }

            $feed->close();
        }

        public function metaWeblog_getPost($struct, $post) {
            $struct["mt_allow_comments"] = isset($post->comment_status) ? intval($post->comment_status == "open") : 1 ;
            return $struct;
        }

        public function metaWeblog_before_editPost($values, $struct) {
            if (isset($struct["mt_allow_comments"]))
                $values['comment_status'] = ($struct["mt_allow_comments"] == "open") ? "open" : "closed" ;
            else
                $values['comment_status'] = "closed";

            return $values;
        }

        public function pingback($post, $to, $from, $title, $excerpt) {
            $count = SQL::current()->count("comments",
                                           array("post_id" => $post->id,
                                                 "status" => Comment::STATUS_PINGBACK,
                                                 "author_url" => $from));

            if (!empty($count))
                return new IXR_Error(48, __("A ping from your URL is already registered.", "comments"));

            if (strlen($from) > 2048)
                return new IXR_Error(0, __("Your URL is too long to be stored in our database.", "comments"));

            Comment::create($excerpt,
                            $title,
                            $from,
                            "",
                            $post,
                            0,
                            0,
                            Comment::STATUS_PINGBACK);

            return __("Pingback registered!", "comments");
        }

        public function javascript() {
            $config  = Config::current();
            include MODULES_DIR.DIR."comments".DIR."javascript.php";
        }

        public function post_options($fields, $post = null) {
            $statuses = array(
                array("name" => __("Open", "comments"),
                      "value" => Comment::OPTION_OPEN,
                      "selected" => ($post ? $post->comment_status == "open" : true)),
                array("name" => __("Closed", "comments"),
                      "value" => Comment::OPTION_CLOSED,
                      "selected" => ($post ? $post->comment_status == "closed" : false)),
                array("name" => __("Private", "comments"),
                      "value" => Comment::OPTION_PRIVATE,
                      "selected" => ($post ? $post->comment_status == "private" : false)),
                array("name" => __("Registered Only", "comments"),
                      "value" => Comment::OPTION_REG_ONLY,
                      "selected" => ($post ? $post->comment_status == "registered_only" : false))
            );

            $fields[] = array("attr" => "option[comment_status]",
                              "label" => __("Comment Status", "comments"),
                              "type" => "select",
                              "options" => $statuses);

            return $fields;
        }

        public function delete_post($post) {
            SQL::current()->delete("comments", array("post_id" => $post->id));
        }

        public function delete_user($user) {
            SQL::current()->update("comments", array("user_id" => $user->id), array("user_id" => 0));
        }

        public function post($post) {
            $post->has_many[] = "comments";
        }

        private function get_post_comment_count($post_id) {
            if (!isset($this->caches["post_comment_counts"])) {
                $counts = SQL::current()->select("comments",
                                                 array("COUNT(post_id) AS total", "post_id as post_id"),
                                                 array("status not" => Comment::STATUS_SPAM,
                                                       Comment::redactions()),
                                                 null,
                                                 array(),
                                                 null,
                                                 null,
                                                 "post_id");

                $this->caches["post_comment_counts"] = array();

                foreach ($counts->fetchAll() as $count)
                    $this->caches["post_comment_counts"][$count["post_id"]] = (int) $count["total"];
            }

            return fallback($this->caches["post_comment_counts"][$post_id], 0);
        }

        public function post_comment_count_attr($attr, $post) {
            if ($post->no_results)
                return 0;

            return $this->get_post_comment_count($post->id);
        }

        private function get_latest_comments($post_id) {
            if (!isset($this->caches["latest_comments"])) {
                $times = SQL::current()->select("comments",
                                                array("MAX(created_at) AS latest", "post_id"),
                                                array("status not" => Comment::STATUS_SPAM,
                                                      Comment::redactions()),
                                                null,
                                                array(),
                                                null,
                                                null,
                                                "post_id");

                $this->caches["latest_comments"] = array();

                foreach ($times->fetchAll() as $row)
                    $this->caches["latest_comments"][$row["post_id"]] = $row["latest"];
            }

            return fallback($this->caches["latest_comments"][$post_id], null);
        }

        public function post_latest_comment_attr($attr, $post) {
            if ($post->no_results)
                return null;

            return $this->get_latest_comments($post->id);
        }

        private function get_user_comment_count($user_id) {
            if (!isset($this->caches["user_comment_counts"])) {
                $this->caches["user_comment_counts"] = array();

                $counts = SQL::current()->select("comments",
                                                 array("COUNT(user_id) AS total", "user_id as user_id"),
                                                 array("status not" => Comment::STATUS_SPAM,
                                                       Comment::redactions()),
                                                 null,
                                                 array(),
                                                 null,
                                                 null,
                                                 "user_id");

                foreach ($counts->fetchAll() as $count)
                    $this->caches["user_comment_counts"][$count["user_id"]] = (int) $count["total"];
            }

            return fallback($this->caches["user_comment_counts"][$user_id], 0);
        }

        public function user_comment_count_attr($attr, $user) {
            if ($user->no_results)
                return 0;

            return $this->get_user_comment_count($user->id);
        }

        public function visitor_comment_count_attr($attr, $visitor) {
            return ($visitor->id == 0) ?
                count($_SESSION['comments']) : $this->user_comment_count_attr($attr, $visitor) ;
        }

        public function post_commentable_attr($attr, $post) {
            if ($post->no_results)
                return false;

            return Comment::creatable($post);
        }

        public function import_chyrp_post($entry, $post) {
            $chyrp = $entry->children("http://chyrp.net/export/1.0/");

            if (!isset($chyrp->comment))
                return;

            foreach ($chyrp->comment as $comment) {
                $chyrp = $comment->children("http://chyrp.net/export/1.0/");
                $comment = $comment->children("http://www.w3.org/2005/Atom");
                $login = $comment->author->children("http://chyrp.net/export/1.0/")->login;

                $user = new User(array("login" => unfix((string) $login)));

                $updated = ((string) $comment->updated != (string) $comment->published);

                Comment::add(unfix((string) $comment->content),
                             unfix((string) $comment->author->name),
                             unfix((string) $comment->author->uri),
                             unfix((string) $comment->author->email),
                             0,
                             "",
                             unfix((string) $chyrp->status),
                             $post->id,
                             (!$user->no_results) ? $user->id : 0,
                             0,
                             false,
                             datetime((string) $comment->published),
                             ($updated) ? datetime((string) $comment->updated) : null);
            }
        }

        public function posts_export($atom, $post) {
            $comments = Comment::find(array("where" => array("post_id" => $post->id)),
                                      array("filter" => false));

            foreach ($comments as $comment) {
                $updated = ($comment->updated) ? $comment->updated_at : $comment->created_at ;

                $atom.= '<chyrp:comment>'."\n".
                        '<updated>'.when("c", $updated).'</updated>'."\n".
                        '<published>'.when("c", $comment->created_at).'</published>'."\n".
                        '<author chyrp:user_id="'.$comment->user_id.'">'."\n".
                        '<name>'.fix($comment->author, false, true).'</name>'."\n".
                        '<uri>'.fix($comment->author_url, false, true).'</uri>'."\n".
                        '<email>'.fix($comment->author_email, false, true).'</email>'."\n".
                        '<chyrp:login>'.($comment->user->no_results ?
                            "" :
                            fix($comment->user->login, false, true)).'</chyrp:login>'."\n".
                        '</author>'."\n".
                        '<content type="html">'.fix($comment->body, false, true).'</content>'."\n".
                        '<chyrp:status>'.fix($comment->status, false, true).'</chyrp:status>'."\n".
                        '</chyrp:comment>'."\n";
            }

            return $atom;
        }

        public function correspond_comment($params) {
            $params["subject"] = _f("New Comment at %s", Config::current()->name, "comments");
            $params["message"] = _f("%s commented on a blog post:", $params["author"], "comments").
                                 "\r\n".
                                 unfix($params["link1"]).
                                 "\r\n".
                                 "\r\n".
                                 truncate(strip_tags($params["body"]), 60).
                                 "\r\n".
                                 "\r\n".
                                 __("Unsubscribe from this conversation:", "comments").
                                 "\r\n".
                                 unfix($params["link2"]);

            return $params;
        }
    }
