<?php
    require_once "model.Comment.php";

    class Comments extends Modules {
        public function __init() {
            $this->addAlias("metaWeblog_newPost_preQuery", "metaWeblog_editPost_preQuery");
            $this->addAlias("comment_grab", "comments_get");
        }

        static function __install() {
            $sql = SQL::current();
            $sql->query("CREATE TABLE IF NOT EXISTS __comments (
                             id INTEGER PRIMARY KEY AUTO_INCREMENT,
                             body LONGTEXT,
                             author VARCHAR(250) DEFAULT '',
                             author_url VARCHAR(128) DEFAULT '',
                             author_email VARCHAR(128) DEFAULT '',
                             author_ip INTEGER DEFAULT '0',
                             author_agent VARCHAR(255) DEFAULT '',
                             status VARCHAR(32) default 'denied',
                             post_id INTEGER DEFAULT 0,
                             user_id INTEGER DEFAULT 0,
                             parent_id INTEGER DEFAULT 0,
                             notify INTEGER DEFAULT 0,
                             created_at DATETIME DEFAULT NULL,
                             updated_at DATETIME DEFAULT NULL
                         ) DEFAULT CHARSET=utf8");

            $config = Config::current();
            $config->set("default_comment_status", "denied");
            $config->set("allowed_comment_html", array("strong", "em", "blockquote", "code", "pre", "a"));
            $config->set("comments_per_page", 25);
            $config->set("akismet_api_key", null);
            $config->set("auto_reload_comments", 30);
            $config->set("enable_reload_comments", false);
                                                                                            # Add these strings to the .pot file:
            Group::add_permission("add_comment", "Add Comments");                           # __("Add Comments");
            Group::add_permission("add_comment_private", "Add Comments to Private Posts");  # __("Add Comments to Private Posts");
            Group::add_permission("edit_comment", "Edit Comments");                         # __("Edit Comments");
            Group::add_permission("edit_own_comment", "Edit Own Comments");                 # __("Edit Own Comments");
            Group::add_permission("delete_comment", "Delete Comments");                     # __("Delete Comments");
            Group::add_permission("delete_own_comment", "Delete Own Comments");             # __("Delete Own Comments");
            Group::add_permission("code_in_comments", "Can Use HTML in Comments");          # __("Can Use HTML in Comments");

            Route::current()->add("comment/(id)/", "comment");
        }

        static function __uninstall($confirm) {
            if ($confirm)
                SQL::current()->query("DROP TABLE __comments");

            $config = Config::current();
            $config->remove("default_comment_status");
            $config->remove("allowed_comment_html");
            $config->remove("comments_per_page");
            $config->remove("akismet_api_key");
            $config->remove("auto_reload_comments");
            $config->remove("enable_reload_comments");

            Group::remove_permission("add_comment");
            Group::remove_permission("add_comment_private");
            Group::remove_permission("edit_comment");
            Group::remove_permission("edit_own_comment");
            Group::remove_permission("delete_comment");
            Group::remove_permission("delete_own_comment");
            Group::remove_permission("code_in_comments");

            Route::current()->remove("comment/(id)/");
        }

        public function main_comment($main) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                Flash::warning(__("Please enter an ID to search for a comment.", "comments"), "/");

            $parent_id = (int) $_GET['id'];
            $comment = new Comment($parent_id);

            if ($comment->no_results)
                show_404(__("Not Found"), __("Comment not found.", "comments"));

            $post = new Post($comment->post_id, array("drafts" => true));

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            if (!$post->theme_exists())
                error(__("Error"),
                      __("The post cannot be displayed because the template for this feather was not found."), null, 501);

            if ($post->status == "draft")
                Flash::message(__("This post is a draft."));

            if ($post->status == "scheduled")
                Flash::message(_f("This post is scheduled to be published %s.", when("%c", $post->created_at, true)));

            if ($post->groups() and !substr_count($post->status, "{".Visitor::current()->group->id."}"))
                Flash::message(_f("This post is only visible to the following groups: %s.", $post->groups()));

            $main->display(array("pages".DIR."view", "pages".DIR."index"),
                           array("post" => $post,
                                 "posts" => array($post),
                                 "parent_id" => $parent_id),
                           $post->title());
        }

        public function parse_urls($urls) {
            $urls["|/comment/([0-9]+)/|"] = "/?action=comment&id=$1";
            return $urls;
        }

        static function route_add_comment() {
            if (empty($_POST['post_id']) or !is_numeric($_POST['post_id']))
                error(__("No ID Specified"), __("An ID is required to add a comment.", "comments"), null, 400);

            $post = new Post($_POST['post_id'], array("drafts" => true));

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            if (!Comment::user_can($post))
                show_403(__("Access Denied"), __("You cannot comment on this post.", "comments"));

            if (empty($_POST['body']))
                Flash::warning(__("Message can't be blank.", "comments"));

            if (empty($_POST['author']))
                Flash::warning(__("Author can't be blank.", "comments"));

            if (empty($_POST['author_email']))
                Flash::warning(__("Email address can't be blank.", "comments"));
            elseif (!is_email($_POST['author_email']))
                Flash::warning(__("Invalid email address.", "comments"));

            if (!empty($_POST['author_url']) and !is_url($_POST['author_url']))
                Flash::warning(__("Invalid website URL.", "comments"));

            if (!logged_in() and Config::current()->enable_captcha and !check_captcha())
                Flash::warning(__("Incorrect captcha code.", "comments"));

            fallback($parent, (int) $_POST['parent_id'], 0);
            fallback($notify, (int) (!empty($_POST['notify']) and logged_in()));

            if (Flash::exists("warning"))
                redirect($post->url());

            Flash::notice(Comment::create($_POST['body'],
                                          $_POST['author'],
                                          $_POST['author_url'],
                                          $_POST['author_email'],
                                          $post,
                                          $parent,
                                          $notify), $post->url());
        }

        static function admin_update_comment() {
            if (empty($_POST))
                redirect("/admin/?action=manage_comments");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to update a comment.", "comments"), null, 400);

            $comment = new Comment($_POST['id']);

            if ($comment->no_results)
                show_404(__("Not Found"), __("Comment not found.", "comments"));

            if (!$comment->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this comment.", "comments"));

            if (empty($_POST['body']))
                error(__("Error"), __("Message can't be blank.", "comments"), null, 422);

            if (empty($_POST['author']))
                error(__("Error"), __("Author can't be blank.", "comments"), null, 422);

            if (empty($_POST['author_email']))
                error(__("Error"), __("Email address can't be blank.", "comments"), null, 422);

            if (!is_email($_POST['author_email']))
                error(__("Error"), __("Invalid email address.", "comments"), null, 422);

            if (!empty($_POST['author_url']) and !is_url($_POST['author_url']))
                error(__("Error"), __("Invalid website URL.", "comments"), null, 422);

            if (!empty($_POST['author_url']))
                $_POST['author_url'] = add_scheme($_POST['author_url']);

            fallback($notify, (int) (!empty($_POST['notify']) and logged_in()));

            $visitor = Visitor::current();
            $status = ($visitor->group->can("edit_comment")) ? $_POST['status'] : $comment->status ;
            $created_at = ($visitor->group->can("edit_comment")) ? datetime($_POST['created_at']) : $comment->created_at ;

            $comment->update($_POST['body'],
                             $_POST['author'],
                             $_POST['author_url'],
                             $_POST['author_email'],
                             $status,
                             $notify,
                             $created_at);

            if (!empty($_POST['ajax']))
                exit(__("Comment updated.", "comments"));

            if (!$visitor->group->can("edit_comment", "delete_comment"))
                Flash::notice(__("Comment updated.", "comments"), $comment->post->url());

            Flash::notice(__("Comment updated.", "comments"), "/admin/?action=manage_comments");
        }

        static function admin_delete_comment($admin) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to delete a comment.", "comments"), null, 400);

            $comment = new Comment($_GET['id']);

            if ($comment->no_results)
                Flash::warning(__("Comment not found.", "comments"), "/admin/?action=manage_comments");

            if (!$comment->deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this comment.", "comments"));

            $admin->display("delete_comment", array("comment" => $comment));
        }

        static function admin_destroy_comment() {
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to delete a comment.", "comments"), null, 400);

            if ($_POST['destroy'] != "indubitably")
                redirect("/admin/?action=manage_comments");

            $comment = new Comment($_POST['id']);

            if ($comment->no_results)
                show_404(__("Not Found"), __("Comment not found.", "comments"));

            if (!$comment->deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this comment.", "comments"));

            Comment::delete($comment->id);

            Flash::notice(__("Comment deleted.", "comments"));
            redirect("/admin/?action=manage_".(($comment->status == "spam") ? "spam" : "comments"));
        }

        static function admin_manage_spam($admin) {
            if (!Visitor::current()->group->can("edit_comment", "delete_comment", true))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage any comments.", "comments"));

            fallback($_GET['query'], "");
            list($where, $params) = keywords($_GET['query'], "body LIKE :query", "comments");

            $where["status"] = "spam";

            $admin->display("manage_spam",
                            array("comments" => new Paginator(Comment::find(array("placeholders" => true,
                                                                                  "where" => $where,
                                                                                  "params" => $params)),
                                                              Config::current()->admin_per_page)));
        }

        static function admin_purge_spam() {
            if (!Visitor::current()->group->can("delete_comment"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete comments.", "comments"));

            SQL::current()->delete("comments", "status = 'spam'");

            Flash::notice(__("All spam deleted.", "comments"), "/admin/?action=manage_spam");
        }

        public function post_options($fields, $post = null) {
            if ($post)
                $post->comment_status = oneof(@$post->comment_status, "open");

            $fields[] = array("attr" => "option[comment_status]",
                              "label" => __("Comment Status", "comments"),
                              "type" => "select",
                              "options" => array(array("name" => __("Open", "comments"),
                                                       "value" => "open",
                                                       "selected" => ($post ? $post->comment_status == "open" : true)),
                                                 array("name" => __("Closed", "comments"),
                                                       "value" => "closed",
                                                       "selected" => ($post ? $post->comment_status == "closed" : false)),
                                                 array("name" => __("Private", "comments"),
                                                       "value" => "private",
                                                       "selected" => ($post ? $post->comment_status == "private" : false)),
                                                 array("name" => __("Registered Only", "comments"),
                                                       "value" => "registered_only",
                                                       "selected" => ($post ? $post->comment_status == "registered_only" : false))));

            return $fields;
        }

        public function pingback($post, $to, $from, $title, $excerpt) {
            $sql = SQL::current();
            $count = $sql->count("comments",
                                 array("post_id" => $post->id,
                                       "status" => "pingback",
                                       "author_url" => $from));

            if ($count)
                return new IXR_Error(48, __("A ping from your URL is already registered.", "comments"));

            Comment::create($excerpt,
                            $title,
                            $from,
                            "",
                            $post,
                            0,
                            0,
                            "pingback");

            return __("Pingback registered!", "comments");
        }

        static function delete_post($post) {
            SQL::current()->delete("comments", array("post_id" => $post->id));
        }

        static function delete_user($user) {
            SQL::current()->update("comments", array("user_id" => $user->id), array("user_id" => 0));
        }

        static function admin_comment_settings($admin) {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (empty($_POST))
                return $admin->display("comment_settings");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            $config = Config::current();
            $set = array($config->set("allowed_comment_html", explode(", ", $_POST['allowed_comment_html'])),
                         $config->set("default_comment_status", $_POST['default_comment_status']),
                         $config->set("comments_per_page", (int) $_POST['comments_per_page']),
                         $config->set("auto_reload_comments", (int) $_POST['auto_reload_comments']),
                         $config->set("enable_reload_comments", isset($_POST['enable_reload_comments'])));

            if (!empty($_POST['akismet_api_key'])) {
                $_POST['akismet_api_key'] = trim($_POST['akismet_api_key']);
                $akismet = new Akismet($config->url, $_POST['akismet_api_key']);

                if (!$akismet->isKeyValid()) {
                    Flash::warning(__("Invalid Akismet API key."), "/admin/?action=comment_settings");
                    $set[] = false;
                } else
                    $set[] = $config->set("akismet_api_key", $_POST['akismet_api_key']);
            }

            if (!in_array(false, $set))
                Flash::notice(__("Settings updated."), "/admin/?action=comment_settings");
        }

        static function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["comment_settings"] = array("title" => __("Comments", "comments"));

            return $navs;
        }

        static function manage_nav($navs) {
            if (!Comment::any_editable() and !Comment::any_deletable())
                return $navs;

            $sql = SQL::current();
            $comment_count = $sql->count("comments", array("status not" => "spam"));
            $spam_count = $sql->count("comments", array("status" => "spam"));
            $navs["manage_comments"] = array("title" => _f("Comments (%d)", $comment_count, "comments"),
                                             "selected" => array("edit_comment", "delete_comment"));

            if (Visitor::current()->group->can("edit_comment", "delete_comment"))
                $navs["manage_spam"]     = array("title" => _f("Spam (%d)", $spam_count, "comments"));

            return $navs;
        }

        static function manage_nav_pages($pages) {
            array_push($pages, "manage_comments", "manage_spam", "edit_comment", "delete_comment");
            return $pages;
        }

        public function admin_edit_comment($admin) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to edit a comment.", "comments"), null, 400);

            $comment = new Comment($_GET['id'], array("filter" => false));

            if ($comment->no_results)
                Flash::warning(__("Comment not found.", "comments"), "/admin/?action=manage_comments");

            if (!$comment->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this comment.", "comments"));

            $admin->display("edit_comment", array("comment" => $comment));
        }

        static function admin_manage_comments($admin) {
            if (!Comment::any_editable() and !Comment::any_deletable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage any comments.", "comments"));

            fallback($_GET['query'], "");
            list($where, $params) = keywords($_GET['query'], "body LIKE :query", "comments");

            $where[] = "status != 'spam'";

            $visitor = Visitor::current();
            if (!$visitor->group->can("edit_comment", "delete_comment", true))
                $where["user_id"] = $visitor->id;

            $admin->display("manage_comments",
                            array("comments" => new Paginator(Comment::find(array("placeholders" => true,
                                                                                  "where" => $where,
                                                                                  "params" => $params)),
                                                              Config::current()->admin_per_page)));
        }

        static function admin_bulk_comments() {
            $from = (!isset($_GET['from'])) ? "manage_comments" : "manage_spam" ;

            if (!isset($_POST['comment']))
                Flash::warning(__("No comments selected."), "/admin/?action=".$from);

            $comments = array_keys($_POST['comment']);

            if (isset($_POST['delete'])) {
                foreach ($comments as $comment) {
                    $comment = new Comment($comment);
                    if ($comment->deletable())
                        Comment::delete($comment->id);
                }

                Flash::notice(__("Selected comments deleted.", "comments"));
            }

            $false_positives = array();
            $false_negatives = array();

            $sql = SQL::current();
            $config = Config::current();

            if (isset($_POST['deny'])) {
                foreach ($comments as $comment) {
                    $comment = new Comment($comment);
                    if (!$comment->editable())
                        continue;

                    if ($comment->status == "spam")
                        $false_positives[] = $comment;

                    $sql->update("comments", array("id" => $comment->id), array("status" => "denied"));
                }

                Flash::notice(__("Selected comments denied.", "comments"));
            }

            if (isset($_POST['approve'])) {
                foreach ($comments as $comment) {
                    $comment = new Comment($comment);
                    if (!$comment->editable())
                        continue;

                    if ($comment->status == "spam")
                        $false_positives[] = $comment;

                    $sql->update("comments", array("id" => $comment->id), array("status" => "approved"));
                }

                Flash::notice(__("Selected comments approved.", "comments"));
            }

            if (isset($_POST['spam'])) {
                foreach ($comments as $comment) {
                    $comment = new Comment($comment);
                    if (!$comment->editable())
                        continue;

                    $sql->update("comments", array("id" => $comment->id), array("status" => "spam"));

                    $false_negatives[] = $comment;
                }

                Flash::notice(__("Selected comments marked as spam.", "comments"));
            }

            if (!empty($config->akismet_api_key)) {
                if (!empty($false_positives))
                    self::reportHam($false_positives);
                if (!empty($false_negatives))
                    self::reportSpam($false_negatives);
            }

            redirect("/admin/?action=".$from);
        }

        static function reportHam($comments) {
            $config = Config::current();
            foreach($comments as $comment) {
                $akismet = new Akismet($config->url, $config->akismet_api_key);
                $akismet->setCommentAuthor($comment->author);
                $akismet->setCommentAuthorEmail($comment->author_email);
                $akismet->setCommentAuthorURL($comment->author_url);
                $akismet->setCommentContent($comment->body);
                $akismet->setPermalink($comment->post_id);
                $akismet->setReferrer($comment->author_agent);
                $akismet->setUserIP($comment->author_ip);
                $akismet->submitHam();
            }
        }

        static function reportSpam($comments) {
            $config = Config::current();
            foreach($comments as $comment) {
                $akismet = new Akismet($config->url, $config->akismet_api_key);
                $akismet->setCommentAuthor($comment->author);
                $akismet->setCommentAuthorEmail($comment->author_email);
                $akismet->setCommentAuthorURL($comment->author_url);
                $akismet->setCommentContent($comment->body);
                $akismet->setPermalink($comment->post_id);
                $akismet->setReferrer($comment->author_agent);
                $akismet->setUserIP($comment->author_ip);
                $akismet->submitSpam();
            }
        }

        static function manage_posts_column_header() {
            echo '<th class="post_comments">'.__("Comments", "comments").'</th>';
        }

        static function manage_posts_column($post) {
            echo '<td class="post_comments"><a href="'.$post->url().'#comments">'.$post->comment_count.'</a></td>';
        }

        static function javascript() {
            include MODULES_DIR.DIR."comments".DIR."javascript.php";
        }

        static function ajax() {
            $config  = Config::current();
            $sql     = SQL::current();
            $trigger = Trigger::current();
            $visitor = Visitor::current();
            $theme   = Theme::current();
            $main    = MainController::current();

            switch($_POST['action']) {
                case "reload_comments":
                    if (empty($_POST['post_id']) or !is_numeric($_POST['post_id']))
                        error(__("No ID Specified"), __("An ID is required to reload comments.", "comments"), null, 400);

                    $post = new Post($_POST['post_id'], array("drafts" => true));
                    $last_comment = (empty($_POST['last_comment'])) ? $post->created_at : $_POST['last_comment'] ;
                    $added_since = when(__("Comments added since %I:%M %p on %B %d, %Y", "comments"), $last_comment, true);

                    if ($post->no_results)
                        show_404(__("Not Found"), __("Post not found."));

                    $ids = array();

                    if ($post->latest_comment > $last_comment) {
                        $new_comments = $sql->select("comments",
                                                     "id, created_at",
                                                     array("post_id" => $post->id,
                                                           "created_at >" => $last_comment,
                                                           "status not" => "spam", "status != 'denied' OR (
                                                              (user_id != 0 AND user_id = :visitor_id) OR (
                                                                    id IN ".self::visitor_comments()."))"
                                                           ),
                                                     "created_at ASC",
                                                     array(":visitor_id" => $visitor->id));

                        while ($the_comment = $new_comments->fetchObject()) {
                            $ids[] = $the_comment->id;

                            if (strtotime($last_comment) < strtotime($the_comment->created_at))
                                $last_comment = $the_comment->created_at;
                        }
                    }

                    json_response($added_since, array("comment_ids" => $ids, "last_comment" => $last_comment));
                case "show_comment":
                    if (empty($_POST['comment_id']) or !is_numeric($_POST['comment_id']))
                        error(__("Error"), __("An ID is required to show a comment.", "comments"), null, 400);

                    $comment = new Comment($_POST['comment_id']);

                    if ($comment->no_results)
                        show_404(__("Not Found"), __("Comment not found.", "comments"));

                    $main->display("content".DIR."comment", array("comment" => $comment));
                    exit;
                case "destroy_comment":
                    if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                        show_403(__("Access Denied"), __("Invalid security key."));

                    if (empty($_POST['id']) or !is_numeric($_POST['id']))
                        error(__("Error"), __("An ID is required to delete a comment.", "comments"), null, 400);

                    $comment = new Comment($_POST['id']);

                    if ($comment->no_results)
                        show_404(__("Not Found"), __("Comment not found.", "comments"));

                    if (!$comment->deletable())
                        show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this comment.", "comments"));

                    Comment::delete($comment->id);
                    json_response(__("Comment deleted.", "comments"));
                case "edit_comment":
                    if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                        show_403(__("Access Denied"), __("Invalid security key."));

                    if (empty($_POST['comment_id']) or !is_numeric($_POST['comment_id']))
                        error(__("Error"), __("An ID is required to edit a comment.", "comments"), null, 400);

                    $comment = new Comment($_POST['comment_id'], array("filter" => false));

                    if ($comment->no_results)
                        show_404(__("Not Found"), __("Comment not found.", "comments"));

                    if (!$comment->editable())
                        show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this comment.", "comments"));

                    $main->display("forms".DIR."comment".DIR."edit", array("comment" => $comment));
                    exit;
                case "validate_comment":
                    if (empty($_POST['body']))
                        json_response(__("Message can't be blank.", "comments"), false);

                    if (empty($_POST['author']))
                        json_response(__("Author can't be blank.", "comments"), false);

                    if (empty($_POST['author_email']))
                        json_response(__("Email address can't be blank.", "comments"), false);
                    elseif (!is_email($_POST['author_email']))
                        json_response(__("Invalid email address.", "comments"), false);

                    if (!empty($_POST['author_url']) and !is_url($_POST['author_url']))
                        json_response(__("Invalid website URL.", "comments"), false);

                    if (!logged_in() and Config::current()->enable_captcha and !check_captcha())
                        json_response(__("Incorrect captcha code.", "comments"), false);

                    json_response(__("Comment validated.", "comments"), true);
            }
        }

        public function import_chyrp_post($entry, $post) {
            $chyrp = $entry->children("http://chyrp.net/export/1.0/");

            if (!isset($chyrp->comment))
                return;

            $sql = SQL::current();

            foreach ($chyrp->comment as $comment) {
                $chyrp = $comment->children("http://chyrp.net/export/1.0/");
                $comment = $comment->children("http://www.w3.org/2005/Atom");

                $login = $comment->author->children("http://chyrp.net/export/1.0/")->login;
                $user_id = $sql->select("users", "id", array("login" => $login), "id DESC")->fetchColumn();

                Comment::add(unfix($comment->content),
                             unfix($comment->author->name),
                             unfix($comment->author->uri),
                             unfix($comment->author->email),
                             $chyrp->author->ip,
                             unfix($chyrp->author->agent),
                             $chyrp->status,
                             datetime($comment->published),
                             ($comment->published == $comment->updated) ? null : datetime($comment->updated),
                             $post,
                             ($user_id ? $user_id : 0));
            }
        }

        static function view_feed($context) {
            $config = Config::current();
            $trigger = Trigger::current();

            $post = $context["post"];
            $comments = $post->comments;
            $latest_timestamp = 0;
            $title = _f("Comments on &#8220;%s&#8221;", fix(oneof($post->title(), ucfirst($post->feather))), "comments");

            foreach ($comments as $comment)
                if (strtotime($comment->created_at) > $latest_timestamp)
                    $latest_timestamp = strtotime($comment->created_at);

            $atom = new AtomFeed();

            $atom->open($title,
                        null,
                        null,
                        $latest_timestamp);

            foreach ($comments as $comment) {
                $trigger->call("feed_comment", $comment);

                $updated = ($comment->updated) ? $comment->updated_at : $comment->created_at ;

                $tagged = substr(strstr(url("id/".$comment->post->id)."#comment_".$comment->id, "//"), 2);
                $tagged = str_replace("#", "/", $tagged);
                $tagged = preg_replace("/(".preg_quote(parse_url($comment->post->url(), PHP_URL_HOST)).")/",
                                       "\\1,".when("Y-m-d", $updated).":", $tagged, 1);

                $atom->entry(_f("Comment #%d", $comment->id, "comments"),
                             $tagged,
                             $comment->body,
                             $comment->post->url()."#comment_".$comment->id,
                             $comment->created_at,
                             $updated,
                             $comment->author,
                             $comment->author_url);

                $trigger->call("comments_feed_item", $comment->id);
            }

            $atom->close();
        }

        static function metaWeblog_getPost($struct, $post) {
            if (isset($post->comment_status))
                $struct['mt_allow_comments'] = intval($post->comment_status == 'open');
            else
                $struct['mt_allow_comments'] = 1;

            return $struct;
        }

        static function metaWeblog_editPost_preQuery($struct, $post = null) {
            if (isset($struct['mt_allow_comments']))
                $_POST['option']['comment_status'] = ($struct['mt_allow_comments'] == 1) ? 'open' : 'closed';
        }

        public function post($post) {
            $post->has_many[] = "comments";
        }

        public function post_comment_count_attr($attr, $post) {
            if (isset($this->comment_counts))
                return oneof(@$this->comment_counts[$post->id], 0);

            $counts = SQL::current()->select("comments",
                                             array("COUNT(post_id) AS total", "post_id as post_id"),
                                             array("status not" => "spam", "status != 'denied' OR (
                                                      (user_id != 0 AND user_id = :visitor_id) OR (
                                                            id IN ".self::visitor_comments()."))"
                                                  ),
                                             null,
                                             array(":visitor_id" => Visitor::current()->id),
                                             null,
                                             null,
                                             "post_id");

            foreach ($counts->fetchAll() as $count)
                $this->comment_counts[$count["post_id"]] = (int) $count["total"];

            return oneof(@$this->comment_counts[$post->id], 0);
        }

        public function post_latest_comment_attr($attr, $post) {
            if (isset($this->latest_comments))
                return fallback($this->latest_comments[$post->id], null);

            $times = SQL::current()->select("comments",
                                            array("MAX(created_at) AS latest", "post_id"),
                                            array("status not" => "spam", "status != 'denied' OR (
                                                     (user_id != 0 AND user_id = :visitor_id) OR (
                                                           id IN ".self::visitor_comments()."))"
                                                 ),
                                            null,
                                            array(":visitor_id" => Visitor::current()->id),
                                            null,
                                            null,
                                            "post_id");

            foreach ($times->fetchAll() as $row)
                $this->latest_comments[$row["post_id"]] = $row["latest"];

            return fallback($this->latest_comments[$post->id], null);
        }

        public function comments_get(&$options) {
            if (ADMIN)
                return;

            $options["where"]["status not"] = "spam";
            $options["where"][] = "status != 'denied' OR (
                                 (user_id != 0 AND user_id = :visitor_id) OR (
                                       id IN ".self::visitor_comments()."))";
            $options["order"] = "created_at ASC";
            $options["params"][":visitor_id"] = Visitor::current()->id;
        }

        public function post_commentable_attr($attr, $post) {
            return Comment::user_can($post);
        }

        public function posts_export($atom, $post) {
            $comments = Comment::find(array("where" => array("post_id" => $post->id)),
                                      array("filter" => false));

            foreach ($comments as $comment) {
                $updated = ($comment->updated) ? $comment->updated_at : $comment->created_at ;

                $atom.= "        <chyrp:comment>\r".
                        '            <updated>'.when("c", $updated).'</updated>'."\r".
                        '            <published>'.when("c", $comment->created_at).'</published>'."\r".
                        '            <author chyrp:user_id="'.$comment->user_id.'">'."\r".
                        "                <name>".fix($comment->author)."</name>\r".
                        (!empty($comment->author_url) ?
                        "                <uri>".fix($comment->author_url)."</uri>\r" : "").
                        "                <email>".fix($comment->author_email)."</email>\r".
                        "                <chyrp:login>".fix(@$comment->user->login)."</chyrp:login>\r".
                        "                <chyrp:ip>".long2ip($comment->author_ip)."</chyrp:ip>\r".
                        "                <chyrp:agent>".fix($comment->author_agent)."</chyrp:agent>\r".
                        "            </author>\r".
                        "            <content>".fix($comment->body)."</content>\r".
                        "                <chyrp:status>".fix($comment->status)."</chyrp:status>\r".
                        "        </chyrp:comment>\r";
            }

            return $atom;
        }

        public function manage_nav_show($possibilities) {
            $possibilities[] = (Comment::any_editable() or Comment::any_deletable());
            return $possibilities;
        }

        public function determine_action($action) {
            if ($action != "manage")
                return;

            if (Comment::any_editable() or Comment::any_deletable())
                return "manage_comments";
        }

        static function visitor_comments() {
            if (empty($_SESSION['comments']))
                return "(0)";
            else
                return QueryBuilder::build_list($_SESSION['comments']);
        }

        public function correspond_comment($params) {
            $post = new Post($params["post"], array("drafts" => true));

            $params["subject"] = _f("New Comment at %s", Config::current()->name);
            $params["message"] = _f("%s commented on a blog post:", fix($params["author"])).
                                 PHP_EOL.
                                 $post->url().
                                 PHP_EOL.PHP_EOL.
                                 '"'.truncate(strip_tags($params["body"])).'"';
            return $params;
        }

        static function cacher_regenerate_posts_triggers($regenerate_posts) {
            $triggers = array("add_comment", "update_comment", "delete_comment");
            return array_merge($regenerate_posts, $triggers);
        }
    }
