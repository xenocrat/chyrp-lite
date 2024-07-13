<?php
    require_once "model".DIR."Pingback.php";

    class Pingable extends Modules {
        # Array: $caches
        # Query caches for methods.
        private $caches = array();

        public static function __install(): void {
            Pingback::install();

            Group::add_permission("edit_pingback", "Edit Webmentions");
            Group::add_permission("delete_pingback", "Delete Webmentions");
        }

        public static function __uninstall($confirm): void {
            if ($confirm)
                Pingback::uninstall();

            Group::remove_permission("edit_pingback");
            Group::remove_permission("delete_pingback");
        }

        public function list_permissions($names = array()): array {
            $names["edit_pingback"] = __("Edit Webmentions", "pingable");
            $names["delete_pingback"] = __("Delete Webmentions", "pingable");
            return $names;
        }

        public function webmention($post, $from, $to): void {
            $count = SQL::current()->count(
                tables:"pingbacks",
                conds:array(
                    "post_id" => $post->id,
                    "source" => $from
                )
            );

            if (!empty($count))
                error(
                    __("Error"),
                    __("A webmention from your URL is already registered.", "pingable"),
                    code:422
                );

            if (strlen($from) > 2048)
                error(
                    __("Error"),
                    __("Your URL is too long to be stored in our database.", "pingable"),
                    code:413
                );

            Pingback::add(
                post_id:$post->id,
                source:$from,
                title:preg_replace("~(https?://|^)([^/:]+).*~i", "$2", $from)
            );
        }

        public function admin_edit_pingback($admin): void {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to edit a webmention.", "pingable"),
                    code:400
                );

            $pingback = new Pingback($_GET['id']);

            if ($pingback->no_results)
                show_404(
                    __("Not Found"),
                    __("Webmention not found.", "pingable")
                );

            if (!$pingback->editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit this webmention.", "pingable")
                );

            $admin->display(
                "pages".DIR."edit_pingback",
                array("pingback" => $pingback)
            );
        }

        public function admin_update_pingback($admin)/*: never */{
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to update a webmention.", "pingable"),
                    code:400
                );

            if (empty($_POST['title']))
                error(
                    __("No Title Specified", "pingable"),
                    __("A title is required to update a webmention.", "pingable"),
                    code:400
                );

            $pingback = new Pingback($_POST['id']);

            if ($pingback->no_results)
                show_404(
                    __("Not Found"),
                    __("Webmention not found.", "pingable")
                );

            if (!$pingback->editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit this webmention.", "pingable")
                );

            $pingback = $pingback->update($_POST['title']);

            Flash::notice(
                __("Webmention updated.", "pingable"),
                "manage_pingbacks"
            );
        }

        public function admin_delete_pingback($admin): void {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a webmention.", "pingable"),
                    code:400
                );

            $pingback = new Pingback($_GET['id']);

            if ($pingback->no_results)
                show_404(
                    __("Not Found"),
                    __("Webmention not found.", "pingable")
                );

            if (!$pingback->deletable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete this webmention.", "pingable")
                );

            $admin->display(
                "pages".DIR."delete_pingback",
                array("pingback" => $pingback)
            );
        }

        public function admin_destroy_pingback()/*: never */{
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to delete a webmention.", "pingable"),
                    code:400
                );

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_pingbacks");

            $pingback = new Pingback($_POST['id']);

            if ($pingback->no_results)
                show_404(
                    __("Not Found"),
                    __("Webmention not found.", "pingable")
                );

            if (!$pingback->deletable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete this webmention.", "pingable")
                );

            Pingback::delete($pingback->id);

            Flash::notice(
                __("Webmention deleted.", "pingable"),
                "manage_pingbacks"
            );
        }

        public function admin_manage_pingbacks($admin): void {
            if (!Visitor::current()->group->can("edit_pingback", "delete_pingback"))
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to manage webmentions.", "pingable")
                );

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect(
                    "manage_pingbacks/query/".
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
                "title LIKE :query",
                "pingbacks"
            );

            $admin->display(
                "pages".DIR."manage_pingbacks",
                array(
                    "pingbacks" => new Paginator(
                        Pingback::find(
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

        public function manage_nav($navs): array {
            if (Visitor::current()->group->can("edit_pingback", "delete_pingback"))
                $navs["manage_pingbacks"] = array(
                    "title" => __("Webmentions", "pingable"),
                    "selected" => array(
                        "edit_pingback",
                        "delete_pingback"
                    )
                );

            return $navs;
        }

        public function admin_determine_action($action): ?string {
            $visitor = Visitor::current();

            if (
                $action == "manage" and 
                $visitor->group->can("edit_pingback", "delete_pingback")
            )
                return "manage_pingbacks";

            return null;
        }

        public function manage_posts_column_header(): string {
            return '<th class="post_pingbacks value">'.
                   __("Webmentions", "pingable").
                   '</th>';
        }

        public function manage_posts_column($post): string {
            return '<td class="post_pingbacks value"><a href="'.
                   url("manage_pingbacks/query/".urlencode("post_id:".$post->id)).
                   '">'.
                   $post->pingback_count.
                   '</a></td>';
        }

        public function post($post): void {
            $post->has_many[] = "pingbacks";
        }

        public function delete_post($post): void {
            SQL::current()->delete(
                table:"pingbacks",
                conds:array("post_id" => $post->id)
            );
        }

        private function get_post_pingback_count($post_id): int {
            if (!isset($this->caches["post_pingback_counts"])) {
                $counts = SQL::current()->select(
                    tables:"pingbacks",
                    fields:array("COUNT(post_id) AS total", "post_id AS post_id"),
                    group:"post_id"
                )->fetchAll();

                $this->caches["post_pingback_counts"] = array();

                foreach ($counts as $count) {
                    $id = $count["post_id"];
                    $total = (int) $count["total"];
                    $this->caches["post_pingback_counts"][$id] = $total;
                }
            }

            return fallback($this->caches["post_pingback_counts"][$post_id], 0);
        }

        public function post_pingback_count_attr($attr, $post): int {
            if ($post->no_results)
                return 0;

            return $this->get_post_pingback_count($post->id);
        }

        public function import_chyrp_post($entry, $post): void {
            $chyrp = $entry->children(
                "http://chyrp.net/export/1.0/"
            );

            if (!isset($chyrp->pingback))
                return;

            foreach ($chyrp->pingback as $pingback) {
                $title = $pingback->children(
                    "http://www.w3.org/2005/Atom"
                )->title;

                $source = $pingback->children(
                    "http://www.w3.org/2005/Atom"
                )->link["href"];

                $created_at = $pingback->children(
                    "http://www.w3.org/2005/Atom"
                )->published;

                Pingback::add(
                    post_id:$post->id,
                    source:unfix((string) $source),
                    title:unfix((string) $title),
                    created_at:datetime((string) $created_at)
                );
            }
        }

        public function posts_export($atom, $post): string {
            $pingbacks = Pingback::find(
                array("where" => array("post_id" => $post->id))
            );

            foreach ($pingbacks as $pingback) {
                $atom.= '<chyrp:pingback>'."\n".
                    '<title type="html">'.
                    fix($pingback->title, false, true).
                    '</title>'."\n".
                    '<link rel="via" href="'.
                    fix($pingback->source, true).
                    '" />'."\n".
                    '<published>'.
                    when(DATE_ATOM, $pingback->created_at).
                    '</published>'."\n".
                    '<chyrp:etag>'.
                    fix($pingback->etag(), false, true).
                    '</chyrp:etag>'."\n".
                    '</chyrp:pingback>'."\n";
            }

            return $atom;
        }
    }
