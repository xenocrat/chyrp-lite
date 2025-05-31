<?php
    require_once "model".DIR."Comment.php";

    class Comments extends Modules {
        # Array: $caches
        # Query caches for methods.
        private $caches = array();

        public static function __install() {
            Comment::install();

            Config::current()->set(
                "module_comments",
                array(
                    "notify_site_contact" => false,
                    "notify_post_author" => false,
                    "code_in_comments" => true,
                    "default_comment_status" => Comment::STATUS_DENIED,
                    "allowed_comment_html" => array(
                        "a",
                        "blockquote",
                        "code",
                        "em",
                        "li",
                        "ol",
                        "pre",
                        "strong",
                        "ul"
                    ),
                    "comments_per_page" => 25,
                    "enable_reload_comments" => false,
                    "auto_reload_comments" => 30
                )
            );

            Group::add_permission("add_comment", "Add Comments");
            Group::add_permission("add_comment_private", "Add Comments to Private Posts");
            Group::add_permission("edit_comment", "Edit Comments");
            Group::add_permission("edit_own_comment", "Edit Own Comments");
            Group::add_permission("delete_comment", "Delete Comments");
            Group::add_permission("delete_own_comment", "Delete Own Comments");
            Group::add_permission("code_in_comments", "Use HTML in Comments");

            Route::current()->add("comment/(id)/", "comment");
        }

        public static function __uninstall(
            $confirm
        ): void {
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

        public function user_logged_in(
            $user
        ): void {
            unset($_SESSION['commenter']);
            $_SESSION['comments'] = array();
        }

        public function user(
            $user
        ): void {
            $user->has_many[] = "comments";
        }

        public function post(
            $post
        ): void {
            $post->has_many[] = "comments";
        }

        public function list_permissions(
            $names = array()
        ): array {
            $names["add_comment"]         = __("Add Comments", "comments");
            $names["add_comment_private"] = __("Add Comments to Private Posts", "comments");
            $names["edit_comment"]        = __("Edit Comments", "comments");
            $names["edit_own_comment"]    = __("Edit Own Comments", "comments");
            $names["delete_comment"]      = __("Delete Comments", "comments");
            $names["delete_own_comment"]  = __("Delete Own Comments", "comments");
            $names["code_in_comments"]    = __("Use HTML in Comments", "comments");
            return $names;
        }

        public function main_comment(
            $main
        ): bool {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                Flash::warning(
                    __("Please enter an ID to find a comment.", "comments"),
                    "/"
                );

            $comment = new Comment($_GET['id']);

            if ($comment->no_results)
                return false;

            if ($comment->post->no_results)
                return false;

            redirect($comment->post->url()."#comment_".$comment->id);
        }

        public function main_most_comments(
            $main
        ): void {
            $posts = Post::find(array("placeholders" => true));

            usort($posts[0], function ($a, $b) {
                $count_a = $this->get_post_comment_count($a["id"]);
                $count_b = $this->get_post_comment_count($b["id"]);

                if ($count_a == $count_b)
                    return 0;

                return ($count_a > $count_b) ? -1 : 1 ;
            });

            $main->display(
                array("pages".DIR."most_comments", "pages".DIR."index"),
                array("posts" => new Paginator($posts, $main->post_limit)),
                __("Most commented on posts", "comments")
            );
        }

        public function parse_urls(
            $urls
        ): array {
            $urls['|/comment/([0-9]+)/|'] = '/?action=comment&amp;id=$1';
            return $urls;
        }

        private function add_comment(
        ): array {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['post_id']) or !is_numeric($_POST['post_id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to add a comment.", "comments"),
                    code:400
                );

            $post = new Post(
                $_POST['post_id'], array("drafts" => true)
            );

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            if (!Comment::creatable($post))
                show_403(
                    __("Access Denied"),
                    __("You cannot comment on this post.", "comments")
                );

            if (empty($_POST['body']))
                return array(
                    false,
                    __("Message can't be blank.", "comments")
                );

            if (empty($_POST['author']))
                return array(
                    false,
                    __("Author can't be blank.", "comments")
                );

            if (empty($_POST['author_email']))
                return array(
                    false,
                    __("Email address can't be blank.", "comments")
                );

            if (!is_email($_POST['author_email']))
                return array(
                    false,
                    __("Invalid email address.", "comments")
                );

            if (!empty($_POST['author_url']) and !is_url($_POST['author_url']))
                return array(
                    false,
                    __("Invalid website URL.", "comments")
                );

            if (!empty($_POST['author_url']))
                $_POST['author_url'] = add_scheme($_POST['author_url']);

            if (!logged_in() and !check_captcha())
                return array(
                    false,
                    __("Incorrect captcha response.", "comments")
                );

            fallback($_POST['author_url'], "");
            $parent = (int) fallback($_POST['parent_id'], 0);
            $notify = (!empty($_POST['notify']) and logged_in());

            $comment = Comment::create(
                body:$_POST['body'],
                author:$_POST['author'],
                author_url:$_POST['author_url'],
                author_email:$_POST['author_email'],
                post:$post,
                parent:$parent,
                notify:$notify
            );

            if (!logged_in()) {
                if (!empty($_POST['remember_me'])) {
                    $_SESSION['commenter'] = array(
                        "author"       => $_POST['author'],
                        "author_email" => $_POST['author_email'],
                        "author_url"   => $_POST['author_url']
                    );
                } else {
                    unset($_SESSION['commenter']);
                }
            }

            return array(
                true,
                ($comment->status == Comment::STATUS_APPROVED) ?
                    __("Comment added.", "comments") :
                    __("Your comment is awaiting moderation.", "comments")
            );
        }

        private function update_comment(
        ): array {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to update a comment.", "comments"),
                    code:400
                );

            $comment = new Comment($_POST['id']);

            if ($comment->no_results)
                show_404(
                    __("Not Found"),
                    __("Comment not found.", "comments")
                );

            if (!$comment->editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit this comment.", "comments")
                );

            fallback($_POST['created_at']);
            fallback($_POST['status'], $comment->status);
            fallback($_POST['author_email'], $comment->author_email);
            fallback($_POST['author_url'], $comment->author_url);

            if (empty($_POST['body']))
                return array(
                    false,
                    __("Message can't be blank.", "comments")
                );

            if (empty($_POST['author']))
                return array(
                    false,
                    __("Author can't be blank.", "comments")
                );

            if (empty($_POST['author_email']) and $_POST['status'] != Comment::STATUS_PINGBACK)
                return array(
                    false,
                    __("Email address can't be blank.", "comments")
                );

            if (!empty($_POST['author_email']) and !is_email($_POST['author_email']))
                return array(
                    false,
                    __("Invalid email address.", "comments")
                );

            if (!empty($_POST['author_url']) and !is_url($_POST['author_url']))
                return array(
                    false,
                    __("Invalid website URL.", "comments")
                );

            if (!empty($_POST['author_url']))
                $_POST['author_url'] = add_scheme($_POST['author_url']);

            $notify = (!empty($_POST['notify']) and logged_in());
            $can_edit_comment = Visitor::current()->group->can("edit_comment");

            $status = ($can_edit_comment) ?
                $_POST['status'] :
                $comment->status ;


            $created_at = ($can_edit_comment) ?
                datetime($_POST['created_at']) :
                $comment->created_at ;

            $comment = $comment->update(
                body:$_POST['body'],
                author:$_POST['author'],
                author_url:$_POST['author_url'],
                author_email:$_POST['author_email'],
                status:$status,
                notify:$notify,
                created_at:$created_at
            );

            return array(
                true,
                __("Comment updated.", "comments")
            );
        }

        public function admin_update_comment(
        ): never {
            list($success, $message) = $this->update_comment();

            if (!$success)
                error(
                    __("Error"),
                    $message,
                    code:422
                );

            Flash::notice(
                $message,
                "manage_comments"
            );
        }

        public function ajax_add_comment(
        ): void {
            list($success, $message) = $this->add_comment();
            json_response($message, $success);
        }

        public function ajax_update_comment(
        ): void {
            list($success, $message) = $this->update_comment();
            json_response($message, $success);
        }

        public function admin_edit_comment(
            $admin
        ): void {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to edit a comment.", "comments"),
                    code:400
                );

            $comment = new Comment(
                $_GET['id'],
                array("filter" => false)
            );

            if ($comment->no_results)
                show_404(
                    __("Not Found"),
                    __("Comment not found.", "comments")
                );

            if (!$comment->editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit this comment.", "comments")
                );

            $admin->display(
                "pages".DIR."edit_comment",
                array("comment" => $comment)
            );
        }

        public function admin_delete_comment(
            $admin
        ): void {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a comment.", "comments"),
                    code:400
                );

            $comment = new Comment($_GET['id']);

            if ($comment->no_results)
                show_404(
                    __("Not Found"),
                    __("Comment not found.", "comments")
                );

            if (!$comment->deletable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete this comment.", "comments")
                );

            $admin->display(
                "pages".DIR."delete_comment",
                array("comment" => $comment)
            );
        }

        public function admin_destroy_comment(
        ): never {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a comment.", "comments"),
                    code:400
                );

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_comments");

            $comment = new Comment($_POST['id']);

            if ($comment->no_results)
                show_404(
                    __("Not Found"),
                    __("Comment not found.", "comments")
                );

            if (!$comment->deletable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete this comment.", "comments")
                );

            Comment::delete($comment->id);

            $redirect = ($comment->status == Comment::STATUS_SPAM) ?
                "manage_spam" :
                "manage_comments" ;

            Flash::notice(__("Comment deleted.", "comments"), $redirect);
        }

        public function admin_manage_comments(
            $admin
        ): void {
            if (!Comment::any_editable() and !Comment::any_deletable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to manage any comments.", "comments")
                );

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect(
                    "manage_comments/query/".
                    str_ireplace(
                        array("%2F", "%5C"),
                        "%5F",
                        urlencode($_POST['query'])
                    ).
                    "/"
                );

            fallback($_GET['query'], "");
            list($where, $params, $order) = keywords(
                $_GET['query'],
                "body LIKE :query",
                "comments"
            );

            $where[] = "status != '".Comment::STATUS_SPAM."'";
            fallback($order, "post_id DESC, created_at ASC");

            $visitor = Visitor::current();

            if (!$visitor->group->can("edit_comment", "delete_comment", true))
                $where["user_id"] = $visitor->id;

            $admin->display(
                "pages".DIR."manage_comments",
                array(
                    "comments" => new Paginator(
                        Comment::find(
                            array(
                                "placeholders" => true,
                                "where" => $where,
                                "params" => $params,
                                "order" => $order
                            )
                        ),
                        $admin->post_limit
                    )
                )
            );
        }

        public function admin_manage_spam(
            $admin
        ): void {
            if (!Visitor::current()->group->can("edit_comment", "delete_comment", true))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to manage any comments.", "comments")
                );

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect(
                    "manage_spam/query/".
                    str_ireplace(
                        array("%2F", "%5C"),
                        "%5F",
                        urlencode($_POST['query'])
                    ).
                    "/"
                );

            fallback($_GET['query'], "");
            list($where, $params, $order) = keywords(
                $_GET['query'],
                "body LIKE :query",
                "comments"
            );

            $where[] = "status = '".Comment::STATUS_SPAM."'";
            fallback($order, "post_id DESC, created_at ASC");

            $admin->display(
                "pages".DIR."manage_spam",
                array(
                    "comments" => new Paginator(
                        Comment::find(
                            array(
                                "placeholders" => true,
                                "where" => $where,
                                "params" => $params,
                                "order" => $order
                            )
                        ),
                        $admin->post_limit
                    )
                )
            );
        }

        public function admin_bulk_comments(
        ): never {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (!isset($_POST['comment']))
                Flash::warning(
                    __("No comments selected."),
                    "manage_comments"
                );

            $trigger = Trigger::current();
            $false_positives = array();
            $false_negatives = array();
            $comments = array_keys($_POST['comment']);

            switch (fallback($_POST['task'])) {
                case "delete":
                    $count_delete = 0;

                    foreach ($comments as $comment) {
                        $comment = new Comment(
                            $comment,
                            array("filter" => false)
                        );

                        if (!$comment->deletable())
                            continue;

                        Comment::delete($comment->id);
                        $count_delete++;
                    }

                    if (!empty($count_delete))
                        Flash::notice(
                            __("Selected comments deleted.", "comments")
                        );

                    break;

                case "deny":
                    $count_deny = 0;

                    foreach ($comments as $comment) {
                        $comment = new Comment(
                            $comment,
                            array("filter" => false)
                        );

                        if (!$comment->editable())
                            continue;

                        if ($comment->status == Comment::STATUS_PINGBACK)
                            continue;

                        if ($comment->status == Comment::STATUS_SPAM)
                            $false_positives[] = $comment;

                        $comment->update(
                            body:$comment->body,
                            author:$comment->author,
                            author_url:$comment->author_url,
                            author_email:$comment->author_email,
                            status:Comment::STATUS_DENIED
                        );

                        $count_deny++;
                    }

                    if (!empty($count_deny))
                        Flash::notice(
                            __("Selected comments denied.", "comments")
                        );

                    break;

                case "approve":
                    $count_approve = 0;

                    foreach ($comments as $comment) {
                        $comment = new Comment(
                            $comment,
                            array("filter" => false)
                        );

                        if (!$comment->editable())
                            continue;

                        if ($comment->status == Comment::STATUS_PINGBACK)
                            continue;

                        if ($comment->status == Comment::STATUS_SPAM)
                            $false_positives[] = $comment;

                        $comment->update(
                            body:$comment->body,
                            author:$comment->author,
                            author_url:$comment->author_url,
                            author_email:$comment->author_email,
                            status:Comment::STATUS_APPROVED
                        );

                        $count_approve++;
                    }

                    if (!empty($count_approve))
                        Flash::notice(
                            __("Selected comments approved.", "comments")
                        );

                    break;

                case "spam":
                    $count_spam = 0;

                    foreach ($comments as $comment) {
                        $comment = new Comment(
                            $comment,
                            array("filter" => false)
                        );

                        if (!$comment->editable())
                            continue;

                        if ($comment->status == Comment::STATUS_PINGBACK)
                            continue;

                        $comment->update(
                            body:$comment->body,
                            author:$comment->author,
                            author_url:$comment->author_url,
                            author_email:$comment->author_email,
                            status:Comment::STATUS_SPAM
                        );

                        $count_spam++;
                        $false_negatives[] = $comment;
                    }

                    if (!empty($count_spam))
                        Flash::notice(
                            __("Selected comments marked as spam.", "comments")
                        );

                    break;
            }

            if (!empty($false_positives))
                $trigger->call("comments_false_positives", $false_positives);

            if (!empty($false_negatives))
                $trigger->call("comments_false_negatives", $false_negatives);

            redirect("manage_comments");
        }

        public function admin_comment_settings(
            $admin
        ): void {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to change settings.")
                );

            $config = Config::current();

            if (empty($_POST)) {
                $comments_html = implode(
                    ", ",
                    $config->module_comments["allowed_comment_html"]
                );

                $comments_status = array(
                    Comment::STATUS_APPROVED => __("Approved", "comments"),
                    Comment::STATUS_DENIED   => __("Denied", "comments"),
                    Comment::STATUS_SPAM     => __("Spam", "comments")
                );

                $admin->display(
                    "pages".DIR."comment_settings",
                    array(
                        "comments_html" => $comments_html,
                        "comments_status" => $comments_status
                    )
                );

                return;
            }

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            fallback($_POST['default_comment_status'], Comment::STATUS_DENIED);
            fallback($_POST['allowed_comment_html'], "");
            fallback($_POST['comments_per_page'], 25);
            fallback($_POST['auto_reload_comments'], 30);

            $comments_html = str_replace(
                array("/", "<", ">"),
                "",
                $_POST['allowed_comment_html']
            );

            $config = Config::current();
            $config->set(
                "module_comments",
                array(
                    "notify_site_contact" => isset($_POST['notify_site_contact']),
                    "notify_post_author" => isset($_POST['notify_post_author']),
                    "code_in_comments" => isset($_POST['code_in_comments']),
                    "default_comment_status" => $_POST['default_comment_status'],
                    "allowed_comment_html" => explode_clean($comments_html),
                    "comments_per_page" => abs((int) $_POST['comments_per_page']),
                    "enable_reload_comments" => isset($_POST['enable_reload_comments']),
                    "auto_reload_comments" => (int) $_POST['auto_reload_comments']
                )
            );

            Flash::notice(
                __("Settings updated."),
                "comment_settings"
            );
        }

        public function admin_determine_action(
            $action
        ): ?string {
            if (
                $action == "manage" and
                (Comment::any_editable() or Comment::any_deletable())
            )
                return "manage_comments";

            return null;
        }

        public function settings_nav(
            $navs
        ): array {
            if (Visitor::current()->group->can("change_settings"))
                $navs["comment_settings"] = array(
                    "title" => __("Comments", "comments")
                );

            return $navs;
        }

        public function manage_nav(
            $navs
        ): array {
            if (!Comment::any_editable() and !Comment::any_deletable())
                return $navs;

            $sql = SQL::current();
            $comment_count = $sql->count(
                "comments",
                array("status not" => Comment::STATUS_SPAM)
            );
            $spam_count = $sql->count(
                "comments",
                array("status" => Comment::STATUS_SPAM)
            );

            $navs["manage_comments"] = array(
                "title" => _f("Comments (%d)", $comment_count, "comments"),
                "selected" => array("edit_comment", "delete_comment")
            );

            if (Visitor::current()->group->can("edit_comment", "delete_comment"))
                $navs["manage_spam"] = array(
                    "title" => _f("Spam (%d)", $spam_count, "comments")
                );

            return $navs;
        }

        public function manage_posts_column_header(
        ): string {
            return '<th class="post_comments value">'.
                   __("Comments", "comments").
                   '</th>';
        }

        public function manage_posts_column(
            $post
        ): string {
            return '<td class="post_comments value"><a href="'.
                   url("manage_comments/query/".urlencode("post_id:".$post->id)).
                   '">'.
                   $post->comment_count.
                   '</a></td>';
        }

        public function manage_users_column_header(
        ): string {
            return '<th class="user_comments value">'.
                   __("Comments", "comments").
                   '</th>';
        }

        public function manage_users_column(
            $user
        ): string {
            return '<td class="user_comments value"><a href="'.
                   url("manage_comments/query/".urlencode("user_id:".$user->id)).
                   '">'.
                   $user->comment_count.
                   '</a></td>';
        }

        public function ajax_reload_comments(
        ): void {
            if (empty($_POST['post_id']) or !is_numeric($_POST['post_id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to reload comments.", "comments"),
                    code:400
                );

            $post = new Post(
                $_POST['post_id'], array("drafts" => true)
            );

            if ($post->no_results)
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            $last = (empty($_POST['last_comment'])) ?
                $post->created_at :
                $_POST['last_comment'] ;

            $text = _f("Comments added since %s", when("%c", $last, true), "comments");

            $ids = array();

            if ($post->latest_comment > $last) {
                $times = SQL::current()->select(
                    tables:"comments",
                    fields:array("id", "created_at"),
                    conds:array(
                        "post_id" => $post->id,
                        "created_at >" => $last,
                        Comment::redactions()
                    ),
                    order:array("created_at ASC")
                );

                while ($row = $times->fetchObject()) {
                    $ids[] = $row->id;
                    $last = $row->created_at;
                }
            }

            json_response(
                $text,
                array("comment_ids" => $ids, "last_comment" => $last)
            );
        }

        public function ajax_show_comment(
        ): void {
            if (empty($_POST['comment_id']) or !is_numeric($_POST['comment_id']))
                error(
                    __("Error"),
                    __("An ID is required to show a comment.", "comments"),
                    code:400
                );

            $comment = new Comment($_POST['comment_id']);

            if ($comment->no_results)
                show_404(
                    __("Not Found"),
                    __("Comment not found.", "comments")
                );

            $main = MainController::current();
            $main->display(
                "content".DIR."comment",
                array("comment" => $comment)
            );
        }

        public function ajax_edit_comment(
        ): void {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['comment_id']) or !is_numeric($_POST['comment_id']))
                error(
                    __("Error"),
                    __("An ID is required to edit a comment.", "comments"),
                    code:400
                );

            $comment = new Comment(
                $_POST['comment_id'],
                array("filter" => false)
            );

            if ($comment->no_results)
                show_404(
                    __("Not Found"),
                    __("Comment not found.", "comments")
                );

            if (!$comment->editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit this comment.", "comments")
                );

            $main = MainController::current();
            $main->display(
                "forms".DIR."comment".DIR."edit",
                array("comment" => $comment)
            );
        }

        public function ajax_destroy_comment(
        ): void {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("Error"),
                    __("An ID is required to delete a comment.", "comments"),
                    code:400
                );

            $comment = new Comment($_POST['id']);

            if ($comment->no_results)
                show_404(
                    __("Not Found"),
                    __("Comment not found.", "comments")
                );

            if (!$comment->deletable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete this comment.", "comments")
                );

            Comment::delete($comment->id);
            json_response(
                __("Comment deleted.", "comments"),
                true
            );
        }

        public function ajax_preview_comment(
        ): void {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (!Visitor::current()->group->can("edit_comment", "edit_own_comment"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to preview content.")
                );

            $config = Config::current();
            $trigger = Trigger::current();
            $main = MainController::current();

            if (
                !isset($_POST['field']) or
                !isset($_POST['context']) or
                !preg_match(
                    "/(^|;)user_id:([0-9]+)(;|$)/i",
                    $_POST['context'],
                    $match
                )
            ) {
                error(
                    __("Error"),
                    __("Missing argument."),
                    code:400
                );
            }

            $user_id = intval($match[2]);
            $field = $_POST['field'];
            $content = fallback($_POST['content'], "");

            $user = empty($user_id) ?
                null :
                new User($user_id);

            $group = (isset($user) and !$user->no_results) ?
                $user->group :
                new Group($config->guest_group) ;

            if ($field == "body") {
                if (!$config->module_comments["code_in_comments"])
                    $content = fix($content);

                $trigger->filter($content, array("markup_comment_text", "markup_text"));

                $allowed_basic_html = array("br", "p");

                $allowed_extra_html = array_merge(
                    $allowed_basic_html,
                    $config->module_comments["allowed_comment_html"]
                );

                $content = strip_tags(
                    $content,
                    $group->can("code_in_comments") ?
                        $allowed_extra_html :
                        $allowed_basic_html
                );

                $content = sanitize_html($content);
            }

            header("Cache-Control: no-store");

            $main->display(
                "content".DIR."preview",
                array("content" => $content),
                __("Preview")
            );
        }

        public function links(
            $links
        ): array {
            $config = Config::current();
            $route = Route::current();
            $main = MainController::current();

            if ($route->action == "view" and !empty($main->context["post"])) {
                $post = $main->context["post"];

                if (!$post->no_results) {
                    $feed_url = ($config->clean_urls) ?
                        rtrim($post->url(), "/")."/feed/" :
                        $post->url()."&amp;feed" ;

                    $text = oneof($post->title(), ucfirst($post->feather));
                    $title = _f("Comments on &#8220;%s&#8221;", $text, "comments");

                    $links[] = array(
                        "href" => $feed_url,
                        "type" => BlogFeed::type(),
                        "title" => $title
                    );
                }
            }

            return $links;
        }

        public function main_view(
        ): bool {
            if (isset($_POST['action'])) {
                if ($_POST['action'] == "add_comment") {
                    list($success, $message) = $this->add_comment();
                    $type = ($success) ? "notice" : "warning" ;
                    Flash::$type($message);

                    if ($success) {
                        unset($_POST['body']);
                        unset($_POST['author']);
                        unset($_POST['author_email']);
                        unset($_POST['author_url']);
                    }
                }

                if ($_POST['action'] == "update_comment") {
                    list($success, $message) = $this->update_comment();
                    $type = ($success) ? "notice" : "warning" ;
                    Flash::$type($message);

                    if ($success) {
                        unset($_POST['body']);
                        unset($_POST['author']);
                        unset($_POST['author_email']);
                        unset($_POST['author_url']);
                    }
                }
            } else {
                if (!logged_in() and isset($_SESSION['commenter'])) {
                    $commenter = $_SESSION['commenter'];
                    $_POST['author']       = $commenter['author'];
                    $_POST['author_email'] = $commenter['author_email'];
                    $_POST['author_url']   = $commenter['author_url'];
                    $_POST['remember_me']  = "on";
                }
            }

            return false;
        }

        public function main_unsubscribe(
            $main
        ): never {
            fallback($_GET['email']);
            fallback($_GET['token']);

            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            $post = new Post($_GET['id']);

            if ($post->no_results)
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            if (!is_email($_GET['email']))
                Flash::warning(
                    __("Invalid email address."),
                    "/"
                );

            $hash = token($_GET['email']);

            if (!hash_equals($hash, $_GET['token']))
                Flash::warning(
                    __("Invalid authentication token."),
                    "/"
                );

            SQL::current()->update(
                table:"comments",
                conds:array(
                    "post_id" => $post->id,
                    "author_email" => $_GET['email']
                ),
                data:array("notify" => false)
            );

            Flash::notice(
                __("You have unsubscribed from the conversation.", "comments"),
                $post->url()
            );
        }

        public function view_feed(
            $context
        ): void {
            $trigger = Trigger::current();

            if (!isset($context["post"]))
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            $post = $context["post"];
            $comments = $post->comments;
            $latest_timestamp = 0;
            $text = oneof($post->title(), ucfirst($post->feather));
            $title = _f("Comments on &#8220;%s&#8221;", $text, "comments");

            foreach ($comments as $comment) {
                $created_at = strtotime($comment->created_at);

                if ($latest_timestamp < $created_at)
                    $latest_timestamp = $created_at;
            }

            $feed = new BlogFeed();

            $feed->open(
                title:$title,
                subtitle:Config::current()->description,
                updated:$latest_timestamp
            );

            foreach ($comments as $comment) {
                $updated = ($comment->updated) ?
                    $comment->updated_at :
                    $comment->created_at ;

                $feed->entry(
                    title:_f("Comment #%d", $comment->id, "comments"),
                    id:url("comment/".$comment->id),
                    content:$comment->body,
                    link:$comment->post->url()."#comment_".$comment->id,
                    published:$comment->created_at,
                    updated:$updated,
                    name:$comment->author,
                    uri:$comment->author_url
                );

                $trigger->call("comments_feed_item", $comment, $feed);
            }

            $feed->display();
        }

        public function webmention(
            $post,
            $from,
            $to
        ): void {
            $count = SQL::current()->count(
                tables:"comments",
                conds:array(
                    "post_id" => $post->id,
                    "status" => Comment::STATUS_PINGBACK,
                    "author_url" => $from
                )
            );

            if (!empty($count))
                error(
                    __("Error"),
                    __("A ping from your URL is already registered.", "comments"),
                    code:422
                );

            if (strlen($from) > 2048)
                error(
                    __("Error"),
                    __("Your URL is too long to be stored in our database.", "comments"),
                    code:413
                );

            Comment::create(
                body:__("Mentioned this post.", "comments"),
                author:preg_replace("~(https?://|^)([^/:]+).*~", "$2", $from),
                author_url:$from,
                author_email:"",
                post:$post,
                parent:0,
                notify:false,
                status:Comment::STATUS_PINGBACK
            );
        }

        public function javascript(
        ): void {
            $config  = Config::current();
            include MODULES_DIR.DIR."comments".DIR."javascript.php";
        }

        public function post_options(
            $fields,
            $post = null
        ): array {
            $statuses = array(
                array(
                    "name" => __("Open", "comments"),
                    "value" => Comment::OPTION_OPEN,
                    "selected" => isset($post) ?
                        $post->comment_status == "open" :
                        true
                ),
                array(
                    "name" => __("Closed", "comments"),
                    "value" => Comment::OPTION_CLOSED,
                    "selected" => isset($post) ?
                        $post->comment_status == "closed" :
                        false
                ),
                array(
                    "name" => __("Private", "comments"),
                    "value" => Comment::OPTION_PRIVATE,
                    "selected" => isset($post) ?
                        $post->comment_status == "private" :
                        false
                ),
                array(
                    "name" => __("Registered Only", "comments"),
                    "value" => Comment::OPTION_REG_ONLY,
                    "selected" => isset($post) ?
                        $post->comment_status == "registered_only" :
                        false
                )
            );

            $fields[] = array(
                "attr" => "option[comment_status]",
                "label" => __("Comment Status", "comments"),
                "type" => "select",
                "options" => $statuses
            );

            return $fields;
        }

        public function delete_post(
            $post
        ): void {
            SQL::current()->delete(
                table:"comments",
                conds:array("post_id" => $post->id)
            );
        }

        public function delete_user(
            $user
        ): void {
            SQL::current()->update(
                table:"comments",
                conds:array("user_id" => $user->id),
                data:array("user_id" => 0)
            );
        }

        private function get_post_comment_count(
            $post_id
        ): int {
            if (!isset($this->caches["post_comment_counts"])) {
                $counts = SQL::current()->select(
                    tables:"comments",
                    fields:array("COUNT(post_id) AS total", "post_id AS post_id"),
                    conds:array(Comment::redactions()),
                    group:"post_id"
                );

                $this->caches["post_comment_counts"] = array();

                foreach ($counts->fetchAll() as $count) {
                    $id = $count["post_id"];
                    $total = (int) $count["total"];
                    $this->caches["post_comment_counts"][$id] = $total;
                }
            }

            return fallback($this->caches["post_comment_counts"][$post_id], 0);
        }

        public function post_comment_count_attr(
            $attr,
            $post
        ): int {
            if ($post->no_results)
                return 0;

            return $this->get_post_comment_count($post->id);
        }

        private function get_latest_comments(
            $post_id
        ): ?string {
            if (!isset($this->caches["latest_comments"])) {
                $times = SQL::current()->select(
                    tables:"comments",
                    fields:array("MAX(created_at) AS latest", "post_id"),
                    conds:array(Comment::redactions()),
                    group:"post_id"
                );

                $this->caches["latest_comments"] = array();

                foreach ($times->fetchAll() as $row) {
                    $id = $row["post_id"];
                    $latest = $row["latest"];
                    $this->caches["latest_comments"][$id] = $latest;
                }
            }

            return fallback($this->caches["latest_comments"][$post_id], null);
        }

        public function post_latest_comment_attr(
            $attr,
            $post
        ): ?string {
            if ($post->no_results)
                return null;

            return $this->get_latest_comments($post->id);
        }

        private function get_user_comment_count(
            $user_id
        ): int {
            if (!isset($this->caches["user_comment_counts"])) {
                $this->caches["user_comment_counts"] = array();

                $counts = SQL::current()->select(
                    tables:"comments",
                    fields:array("COUNT(user_id) AS total", "user_id as user_id"),
                    conds:array(Comment::redactions()),
                    group:"user_id"
                );

                foreach ($counts->fetchAll() as $count) {
                    $id = $count["user_id"];
                    $total = (int) $count["total"];
                    $this->caches["user_comment_counts"][$id] = $total;
                }
            }

            return fallback($this->caches["user_comment_counts"][$user_id], 0);
        }

        public function user_comment_count_attr(
            $attr,
            $user
        ): int {
            if ($user->no_results)
                return 0;

            return $this->get_user_comment_count($user->id);
        }

        public function visitor_comment_count_attr(
            $attr,
            $visitor
        ): int {
            return ($visitor->id == 0) ?
                count(fallback($_SESSION['comments'], array())) :
                $this->user_comment_count_attr($attr, $visitor) ;
        }

        public function post_commentable_attr(
            $attr,
            $post
        ): bool {
            if ($post->no_results)
                return false;

            return Comment::creatable($post);
        }

        public function import_chyrp_post(
            $entry,
            $post
        ): void {
            $chyrp = $entry->children("http://chyrp.net/export/1.0/");

            if (!isset($chyrp->comment))
                return;

            foreach ($chyrp->comment as $comment) {
                $chyrp = $comment->children("http://chyrp.net/export/1.0/");
                $comment = $comment->children("http://www.w3.org/2005/Atom");

                $login = $comment->author->children(
                    "http://chyrp.net/export/1.0/"
                )->login;

                $user = new User(
                    array("login" => unfix((string) $login))
                );

                $updated = (
                    (string) $comment->updated != (string) $comment->published
                );

                Comment::add(
                    body:unfix((string) $comment->content),
                    author:unfix((string) $comment->author->name),
                    author_url:unfix((string) $comment->author->uri),
                    author_email:unfix((string) $comment->author->email),
                    ip:0,
                    agent:"",
                    status:unfix((string) $chyrp->status),
                    post_id:$post->id,
                    user_id:(!$user->no_results) ? $user->id : 0,
                    parent:0,
                    notify:false,
                    created_at:datetime((string) $comment->published),
                    updated_at:($updated) ? datetime((string) $comment->updated) : null
                );
            }
        }

        public function posts_export(
            $atom,
            $post
        ): string {
            $comments = Comment::find(
                array("where" => array("post_id" => $post->id)),
                array("filter" => false)
            );

            foreach ($comments as $comment) {
                $updated = ($comment->updated) ?
                    $comment->updated_at :
                    $comment->created_at ;

                $atom.= '<chyrp:comment>'."\n".
                    '<updated>'.
                    when(DATE_ATOM, $updated).
                    '</updated>'."\n".
                    '<published>'.
                    when(DATE_ATOM, $comment->created_at).
                    '</published>'."\n".
                    '<chyrp:etag>'.
                    fix($comment->etag(), false, true).
                    '</chyrp:etag>'."\n".
                    '<author chyrp:user_id="'.$comment->user_id.'">'."\n".
                    '<name>'.
                    fix($comment->author, false, true).
                    '</name>'."\n".
                    '<uri>'.
                    fix($comment->author_url, false, true).
                    '</uri>'."\n".
                    '<email>'.
                    fix($comment->author_email, false, true).
                    '</email>'."\n".
                    '<chyrp:login>'.
                    ($comment->user->no_results ? 
                        "" :
                        fix($comment->user->login, false, true)
                    ).
                    '</chyrp:login>'."\n".
                    '</author>'."\n".
                    '<content type="html">'.
                    fix($comment->body, false, true).
                    '</content>'."\n".
                    '<chyrp:status>'.
                    fix($comment->status, false, true).
                    '</chyrp:status>'."\n".
                    '</chyrp:comment>'."\n";
            }

            return $atom;
        }

        public static function email_site_new_comment(
            $comment
        ): bool {
            $config = Config::current();
            $trigger = Trigger::current();
            $mailto = $config->email;

            if ($trigger->exists("correspond_site_new_comment"))
                return $trigger->call("correspond_site_new_comment", $comment);

            $headers = array(
                "Content-Type" => "text/plain; charset=UTF-8",
                "From" => $config->email,
                "X-Mailer" => CHYRP_IDENTITY
            );

            $subject = _f("New Comment at %s", $config->name, "comments");
            $message = _f("%s commented on a blog post:", $comment->author, "comments").
                       "\r\n".
                       unfix($comment->url()).
                       "\r\n".
                       "\r\n".
                       truncate(strip_tags($comment->body), 998);

            return email($mailto, $subject, $message, $headers);
        }

        public static function email_user_new_comment(
            $comment,
            $user
        ): bool {
            $config = Config::current();
            $trigger = Trigger::current();
            $mailto = $user->email;

            if ($trigger->exists("correspond_user_new_comment"))
                return $trigger->call("correspond_user_new_comment", $comment, $user);

            $headers = array(
                "Content-Type" => "text/plain; charset=UTF-8",
                "From" => $config->email,
                "X-Mailer" => CHYRP_IDENTITY
            );

            $subject = _f("New Comment at %s", $config->name, "comments");
            $message = _f("%s commented on a blog post:", $comment->author, "comments").
                       "\r\n".
                       unfix($comment->url()).
                       "\r\n".
                       "\r\n".
                       truncate(strip_tags($comment->body), 998);

            return email($mailto, $subject, $message, $headers);
        }

        public static function email_peer_new_comment(
            $comment,
            $peer
        ): bool {
            $config = Config::current();
            $trigger = Trigger::current();
            $mailto = $peer->author_email;

            $url = $config->url."/?action=unsubscribe".
                   "&amp;email=".urlencode($mailto).
                   "&amp;id=".$comment->post_id.
                   "&amp;token=".token($mailto);

            if ($trigger->exists("correspond_peer_new_comment"))
                return $trigger->call("correspond_peer_new_comment", $comment, $peer, $url);

            $headers = array(
                "Content-Type" => "text/plain; charset=UTF-8",
                "From" => $config->email,
                "X-Mailer" => CHYRP_IDENTITY
            );

            $subject = _f("New Comment at %s", $config->name, "comments");
            $message = _f("%s commented on a blog post:", $comment->author, "comments").
                       "\r\n".
                       unfix($comment->url()).
                       "\r\n".
                       "\r\n".
                       truncate(strip_tags($comment->body), 998).
                       "\r\n".
                       "\r\n".
                       __("Unsubscribe from this conversation:", "comments").
                       "\r\n".
                       unfix($url);

            return email($mailto, $subject, $message, $headers);
        }
    }
