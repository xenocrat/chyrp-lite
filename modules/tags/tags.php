<?php
    class Tags extends Modules {
        # Array: $caches
        # Query caches for methods.
        private $caches = array();

        public static function __install(
        ): void {
            Route::current()->add("tag/(name)/", "tag");
        }

        public static function __uninstall(
            $confirm
        ): void {
            Route::current()->remove("tag/(name)/");

            if ($confirm)
                SQL::current()->delete(
                    table:"post_attributes",
                    conds:array("name" => "tags")
                );
        }

        private function tags_serialize(
            $tags
        ) {
            return json_set($tags);
        }

        private function tags_unserialize(
            $tags
        ) {
            return json_get($tags, true);
        }

        private function sort_tags_name_asc(
            $a,
            $b
        ): int {
            return $this->mb_strcasecmp(
                $a["name"],
                $b["name"]
            );
        }

        private function sort_tags_name_desc(
            $a,
            $b
        ): int {
            return $this->mb_strcasecmp(
                $b["name"],
                $a["name"]
            );
        }

        private function sort_tags_popularity_asc(
            $a,
            $b
        ): int {
            if ($a["popularity"] == $b["popularity"])
                return 0;

            return ($a["popularity"] < $b["popularity"]) ? -1 : 1 ;
        }

        private function sort_tags_popularity_desc(
            $a,
            $b
        ): int {
            if ($a["popularity"] == $b["popularity"])
                return 0;

            return ($a["popularity"] > $b["popularity"]) ? -1 : 1 ;
        }

        private function mb_strcasecmp(
            $str1,
            $str2,
            $encoding = "UTF-8"
        ): int {
            $str1 = preg_replace("/[[:punct:]]+/", "", $str1);
            $str2 = preg_replace("/[[:punct:]]+/", "", $str2);

            return substr_compare(
                mb_strtoupper($str1, $encoding),
                mb_strtoupper($str2, $encoding),
                0
            );
        }

        private function tags_name_match(
            $name
        ): string {
            # Serialized notation of key for SQL queries.
            return "%\"".$this->tags_encoded($name)."\":%";
        }

        private function tags_clean_match(
            $clean
        ): string {
            # Serialized notation of value for SQL queries.
            return "%:\"".$this->tags_encoded($clean)."\"%";
        }

        private function tags_encoded(
            $text
        ): string {
            # Recreate JSON encoding for SQL queries.
            $json = trim(json_set((string) $text), "\"");
            # See: QueryBuilder::build_conditions().
            return str_replace("|", "||", $json);
        }

        private function prepare_tags(
            $tags
        ): array {
            $tags = strip_tags($tags);
            $names = explode_clean($tags, sort:false);

            # Prevent numbers type-juggling to numeric keys.
            foreach ($names as &$name) {
                if (is_numeric($name))
                    $name = "\u{FE0E}".$name;
            }

            # Build an array of sanitized slugs.
            $clean = array_map(
                function($value) {
                    $slug = sanitize($value, true, SLUG_STRICT, 0);

                    return preg_match("/[^\-0-9]+/", $slug) ?
                        $slug :
                        md5($value) ;
                },
                $names
            );

            # Combine into an associative array.
            $assoc = array_combine($names, $clean);

            return $assoc;
        }

        public function before_add_post_attributes(
            $attributes
        ): array {
            if (!isset($_POST['tags']))
                return $attributes;

            $tags = $this->prepare_tags($_POST['tags']);
            $attributes["tags"] = $this->tags_serialize($tags);
            return $attributes;
        }

        public function before_update_post_attributes(
            $attributes
        ): array {
            if (!isset($_POST['tags']))
                return $attributes;

            $tags = $this->prepare_tags($_POST['tags']);
            $attributes["tags"] = $this->tags_serialize($tags);
            return $attributes;
        }

        public function post_options(
            $fields,
            $post = null
        ): array {
            $cloud = $this->tag_cloud(false, "name_asc");
            $names = isset($post->tags) ?
                array_keys($post->tags) :
                array();

            $selector = "\n".'<span class="options_extra tags_select">';

            foreach ($cloud as $tag) {
                $selected = (in_array($tag["name"], $names)) ?
                    " tag_added" :
                    "" ;

                $selector.= '<a class="tag'.
                            $selected.
                            '" href="#" role="button">'.
                            $tag["name"].
                            '</a> ';
            }

            $selector.= "</span>"."\n";

            $fields[] = array(
                "attr" => "tags",
                "label" => __("Tags", "tags"),
                "help" => "tagging_posts",
                "note" => __("(comma separated)", "tags"),
                "type" => "text",
                "value" => implode(", ", $names),
                "extra" => $selector
            );

            return $fields;
        }

        public function post(
            $post
        ): void {
            $post->tags = empty($post->tags) ?
                array() :
                $this->tags_unserialize($post->tags) ;

            uksort($post->tags, function($a, $b) {
                return $this->mb_strcasecmp($a, $b);
            });
        }

        public function post_tags_link_attr(
            $attr,
            $post
        ): array {
            $urls = array();

            if ($post->no_results)
                return $urls;

            foreach ($post->tags as $name => $clean) {
                $tag = $this->tag_find_by_name($name);

                if (!empty($tag)) {
                    $urls[] = '<a class="tag" href="'.
                              $tag["url"].
                              '" rel="tag">'.
                              $tag["name"].
                              '</a>';
                }
            }

            return $urls;
        }

        public function twig_function_tag_cloud(
            $limit = false,
            $sort = "name_asc",
            $scale = 300
        ): array {
            return $this->tag_cloud($limit, $sort, $scale);
        }

        public function parse_urls(
            $urls
        ): array {
            $urls['|/tag/([^/]+)/|'] = '/?action=tag&amp;name=$1';
            return $urls;
        }

        public function manage_nav(
            $navs
        ): array {
            if (Post::any_editable())
                $navs["manage_tags"] = array(
                    "title" => __("Tags", "tags"),
                    "selected" => array(
                        "rename_tag",
                        "delete_tag",
                        "edit_tags",
                        "posts_tagged"
                    )
                );

            return $navs;
        }

        public function manage_posts_column_header(
        ): string {
            return '<th class="post_tags list">'.
                   __("Tags", "tags").
                   '</th>';
        }

        public function manage_posts_column(
            $post
        ): string {
            $tags = array();

            foreach ($post->tags as $name => $clean)
                $tags[] = '<a class="tag" href="'.
                          url("posts_tagged/clean/".urlencode($clean)).
                          '">'.
                          $name.
                          '</a>';

            return '<td class="post_tags list">'.
                   implode(" ", $tags).
                   '</td>';
        }

        public function admin_manage_tags(
            $admin
        ): void {
            if (!Post::any_editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to manage tags.", "tags")
                );

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['search']))
                redirect(
                    "manage_tags/search/".
                    str_ireplace(
                        array("%2F", "%5C"),
                        "%5F",
                        urlencode($_POST['search'])
                    ).
                    "/"
                );

            if (isset($_POST['sort']))
                $_SESSION['tags_sort'] = $_POST['sort'];

            $search = isset($_GET['search']) ? $_GET['search'] : "" ;
            $sort = fallback($_SESSION['tags_sort'], "popularity_desc");

            $columns = array(
                "name_asc" => __("Name", "tags"),
                "popularity_desc" => __("Posts Tagged", "tags")
            );

            $tag_cloud = $this->tag_cloud(sort:$sort);

            if ($search != "") {
                $tags = $tag_cloud;
                $tag_cloud = array();
                $encoded = $this->tags_encoded($search);

                foreach ($tags as $tag) {
                    if (
                        substr_count($tag["name"], $encoded) or
                        substr_count($tag["clean"], $encoded)
                    )
                        $tag_cloud[] = $tag;
                }
            }

            $admin->display(
                "pages".DIR."manage_tags",
                array(
                    "tag_cloud" => $tag_cloud,
                    "tags_sort" => $sort,
                    "tags_columns" => $columns
                )
            );
        }

        public function admin_posts_tagged(
            $admin
        ): void {
            if (!Post::any_editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to manage tags.", "tags")
                );

            # Redirect searches to a clean URL or dirty GET depending on configuration.
            if (isset($_POST['query']))
                redirect(
                    "posts_tagged/query/".
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
                "post_attributes.value LIKE :query OR url LIKE :query",
                "posts"
            );

            if (isset($_GET['clean']) and $_GET['clean'] != "") {
                $tag = $this->tag_find_by_clean($_GET['clean']);

                if (empty($tag))
                    show_404(
                        __("Not Found"),
                        __("Tag not found.", "tags")
                    );

                $where["post_attributes.name"] = "tags";
                $where["posts.id"] = $tag["post_ids"];
            } else {
                $tag = false;
            }

            $visitor = Visitor::current();

            if (!$visitor->group->can("edit_draft", "edit_post", "delete_draft", "delete_post"))
                $where["user_id"] = $visitor->id;

            $results = Post::find(
                array(
                    "placeholders" => true,
                    "drafts" => true,
                    "where" => $where,
                    "params" => $params
                )
            );

            $ids = array();

            foreach ($results[0] as $result)
                $ids[] = $result["id"];

            if (!empty($ids)) {
                $posts = new Paginator(
                    Post::find(
                        array(
                            "placeholders" => true,
                            "drafts" => true,
                            "where" => array("id" => $ids),
                            "order" => $order
                        )
                    ),
                    $admin->post_limit
                );
            } else {
                $posts = new Paginator(array());
            }

            $post_statuses = array();

            foreach ($posts->paginated as $post) {
                $name = "";
                $groups = array();
                $classes = array();

                if ($group_ids = $post->groups()) {
                    foreach ($group_ids as $group_id) {
                        $group = new Group($group_id);

                        if (!$group->no_results) {
                            $groups[] = $group;
                            $classes[] = "group-".$group->id;
                        }
                    }
                } else {
                    switch ($post->status) {
                        case Post::STATUS_DRAFT:
                            $name = __("Draft", "admin");
                            break;

                        case Post::STATUS_PUBLIC:
                            $name = __("Public", "admin");
                            break;

                        case Post::STATUS_PRIVATE:
                            $name = __("Private", "admin");
                            break;

                        case Post::STATUS_REG_ONLY:
                            $name = __("All registered users", "admin");
                            break;

                        case Post::STATUS_SCHEDULED:
                            $name = __("Scheduled", "admin");
                            break;

                        default:
                            $name = camelize($post->status, true);
                    }

                    $classes[] = $post->status;
                }

                $post_statuses[$post->id] = array(
                    "name" => $name,
                    "groups" => $groups,
                    "classes" => $classes
                );
            }

            $admin->display(
                "pages".DIR."posts_tagged",
                array(
                    "posts" => $posts,
                    "tag" => $tag,
                    "post_statuses" => $post_statuses
                )
            );
        }

        public function admin_edit_tags(
            $admin
        ): void {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to edit tags.", "tags"),
                    code:400
                );

            $post = new Post(
                $_GET['id'],
                array("drafts" => true)
            );

            if ($post->no_results)
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            if (!$post->editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit this post.")
                );

            $admin->display(
                "pages".DIR."edit_tags",
                array("post" => $post)
            );
        }

        public function admin_update_tags(
            $admin
        ): never {
            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(
                    __("No ID Specified"),
                    __("An ID is required to update tags.", "tags"),
                    code:400
                );

            $post = new Post(
                $_POST['id'],
                array("drafts" => true)
            );

            if ($post->no_results)
                show_404(
                    __("Not Found"),
                    __("Post not found.")
                );

            if (!$post->editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to edit this post.")
                );

            $post->update();

            Flash::notice(
                __("Tags updated.", "tags"),
                "posts_tagged"
            );
        }

        public function admin_rename_tag(
            $admin
        ): void {
            if (!Post::any_editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to rename tags.", "tags")
                );

            if (empty($_GET['clean']))
                error(
                    __("No Tag Specified", "tags"),
                    __("Please specify the tag you want to rename.", "tags"),
                    code:400
                );

            $tag = $this->tag_find_by_clean($_GET['clean']);

            if (empty($tag))
                show_404(
                    __("Not Found"),
                    __("Tag not found.", "tags")
                );

            $admin->display(
                "pages".DIR."rename_tag",
                array("tag" => $tag)
            );
        }

        public function admin_update_tag(
            $admin
        ): never {
            if (!Post::any_editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to rename tags.", "tags")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['original']))
                error(
                    __("No Tag Specified", "tags"),
                    __("Please specify the tag you want to rename.", "tags"),
                    code:400
                );

            if (!isset($_POST['name']) or $_POST['name'] == "")
                error(
                    __("Error"),
                    __("Name cannot be blank.", "tags"),
                    code:422
                );

            $tag = $this->tag_find_by_name($_POST['original']);

            if (empty($tag))
                show_404(
                    __("Not Found"),
                    __("Tag not found.", "tags")
                );

            foreach ($tag["post_ids"] as $post_id) {
                $post = new Post(
                    $post_id,
                    array("drafts" => true)
                );

                if (!$post->editable())
                    continue;

                unset($post->tags[$_POST['original']]);
                $post_tags = array_keys($post->tags);
                $post_tags[] = $_POST['name'];
                $_POST['tags'] = implode(", ", $post_tags);

                $post->update();
            }

            Flash::notice(
                __("Tag renamed.", "tags"),
                "manage_tags"
            );
        }

        public function admin_delete_tag(
            $admin
        ): void {
            if (!Post::any_editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete tags.", "tags")
                );

            if (empty($_GET['clean']))
                error(
                    __("No Tag Specified", "tags"),
                    __("Please specify the tag you want to delete.", "tags"),
                    code:400
                );

            $tag = $this->tag_find_by_clean($_GET['clean']);

            if (empty($tag))
                show_404(
                    __("Not Found"),
                    __("Tag not found.", "tags")
                );

            $admin->display(
                "pages".DIR."delete_tag",
                array("tag" => $tag)
            );
        }

        public function admin_destroy_tag(
        ): never {
            if (!Post::any_editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to delete tags.", "tags")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['name']))
                error(
                    __("No Tag Specified", "tags"),
                    __("Please specify the tag you want to delete.", "tags"),
                    code:400
                );

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_tags");

            $tag = $this->tag_find_by_name($_POST['name']);

            if (empty($tag))
                show_404(
                    __("Not Found"),
                    __("Tag not found.", "tags")
                );

            foreach ($tag["post_ids"] as $post_id)  {
                $post = new Post(
                    $post_id,
                    array("drafts" => true)
                );

                if (!$post->editable())
                    continue;

                unset($post->tags[$_POST['name']]);
                $post_tags = array_keys($post->tags);
                $_POST['tags'] = implode(", ", $post_tags);

                $post->update();
            }

            Flash::notice(
                __("Tag deleted.", "tags"),
                "manage_tags"
            );
        }

        public function admin_bulk_tag(
            $admin
        ): never {
            if (!Post::any_editable())
                show_403(
                    __("Access Denied"),
                    __("You do not have sufficient privileges to add tags.", "tags")
                );

            if (!isset($_POST['hash']) or !Session::check_token($_POST['hash']))
                show_403(
                    __("Access Denied"),
                    __("Invalid authentication token.")
                );

            if (empty($_POST['post']))
                Flash::warning(
                    __("No posts selected.", "tags"),
                    "posts_tagged"
                );

            if (!isset($_POST['name']) or $_POST['name'] == "")
                Flash::warning(
                    __("No tags specified.", "tags"),
                    "posts_tagged"
                );

            foreach ($_POST['post'] as $post_id) {
                $post = new Post(
                    $post_id,
                    array("drafts" => true)
                );

                if (!$post->editable())
                    continue;

                $post_tags = array_keys($post->tags);
                $post_tags[] = $_POST['name'];
                $_POST['tags'] = implode(", ", $post_tags);

                $post->update();
            }

            Flash::notice(
                __("Posts tagged.", "tags"),
                "posts_tagged"
            );
        }

        public function main_tag(
            $main
        ): void {
            if (!isset($_GET['name']))
                Flash::warning(
                    __("You did not specify a tag.", "tags"),
                    "/"
                );

            $tag = $this->tag_find_by_clean($_GET['name']);

            if (empty($tag))
                show_404(
                    __("Not Found"),
                    __("The tag you specified was not found.", "tags")
                );

            $posts = new Paginator(
                Post::find(
                    array(
                        "placeholders" => true,
                        "where" => array("id" => $tag["post_ids"])
                    )
                ),
                $main->post_limit
            );

            if (!$posts->total)
                show_404(
                    __("Not Found"),
                    __("There are no posts with the tag you specified.", "tags")
                );

            $main->display(
                array("pages".DIR."tag", "pages".DIR."index"),
                array("posts" => $posts, "tag" => $tag),
                _f("Posts tagged with &#8220;%s&#8221;", array($tag["name"]), "tags")
            );
        }

        public function main_tags(
            $main
        ): void {
            $main->display(
                "pages".DIR."tags",
                array("tag_cloud" => $this->tag_cloud(false, "name_asc")),
                __("Tags", "tags")
            );
        }

        public function related_posts(
            $ids,
            $post,
            $limit
        ): array {
            if (empty($post->tags))
                return $ids;

            foreach ($post->tags as $name => $clean) {
                $tag = $this->tag_find_by_name($name);

                if (!empty($tag)) {
                    $ids = array_merge(
                        $ids,
                        array_diff(
                            $tag["post_ids"],
                            array($post->id)
                        )
                    );
                }
            }

            return $ids;
        }

        public function tag_cloud(
            $limit = false,
            $sort = "popularity_desc",
            $scale = 300
        ): array {
            $sql = SQL::current();
            $visitor = Visitor::current();

            switch ($sort) {
                case 'name_asc':
                    $method = array($this, "sort_tags_name_asc");
                    break;
                case 'name_desc':
                    $method = array($this, "sort_tags_name_desc");
                    break;
                case 'popularity_asc':
                    $method = array($this, "sort_tags_popularity_asc");
                    break;
                default:
                    $method = array($this, "sort_tags_popularity_desc");
            }

            if (!isset($this->caches["tag_cloud"])) {
                $results = $sql->select(
                    tables:"posts",
                    fields:"post_attributes.*",
                    conds:array(
                        "post_attributes.name" => "tags",
                        (
                            $visitor->group->can("view_draft") ?
                                Post::statuses(array(Post::STATUS_DRAFT)) :
                                Post::statuses()
                        ),
                        Post::feathers()
                    ),
                    left_join:array(
                        array(
                            "table" => "post_attributes",
                            "where" => "post_id = posts.id"
                        )
                    )
                )->fetchAll();

                $found = array();
                $names = array();
                $ids = array();
                $cloud = array();

                foreach ($results as $result) {
                    $these = $this->tags_unserialize($result["value"]);
                    $found = array_merge($found, $these);

                    foreach ($these as $name => $clean) {
                        $names[] = $name;
                        $ids[$name][] = $result["post_id"];
                    }
                }

                if (!empty($found)) {
                    $popularity = array_count_values($names);
                    $min = min($popularity);
                    $max = max($popularity);

                    $step = (int) $scale / (
                        ($min === $max) ? 1 : ($max - $min)
                    );

                    foreach ($popularity as $name => $count) {
                        $size = floor($step * ($count - $min));
                        $title = $this->tag_cloud_title($name, $count);

                        $url = url(
                            "tag/".urlencode($found[$name]),
                            MainController::current()
                        );

                        $cloud[] = array(
                            "size" => $size,
                            "popularity" => $count,
                            "name" => $name,
                            "title" => $title,
                            "clean" => $found[$name],
                            "url" => $url,
                            "post_ids" => $ids[$name]
                        );
                    }
                }

                $this->caches["tag_cloud"] = $cloud;
            }

            $array = $this->caches["tag_cloud"];
            usort($array, $method);
            return ($limit) ?
                array_slice($array, 0, $limit) :
                $array ;
        }

        private function tag_cloud_title(
            $name,
            $count
        ) {
            $p = _p("%d post tagged with &#8220;%s&#8221;", "%d posts tagged with &#8220;%s&#8221;", $count, "tags");
            $title = sprintf($p, $count, fix($name, true));
            return $title;
        }

        public function tag_find_by_clean(
            $clean
        ): array|false {
            $cloud = $this->tag_cloud();

            foreach ($cloud as $tag) {
                if ($tag["clean"] === $clean)
                    return $tag;
            }

            return false;
        }

        public function tag_find_by_name(
            $name
        ): array|false {
            $cloud = $this->tag_cloud();

            foreach ($cloud as $tag) {
                if ($tag["name"] === $name)
                    return $tag;
            }

            return false;
        }

        public function feed_item(
            $post,
            $feed
        ): void {
            $scheme = url("tags", MainController::current());

            foreach ($post->tags as $tag => $clean)
                $feed->category($clean, $scheme, $tag);
        }

        public function admin_javascript(
        ): void {
            include MODULES_DIR.DIR."tags".DIR."javascript.php";
        }
    }
