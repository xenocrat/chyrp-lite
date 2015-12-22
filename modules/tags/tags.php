<?php
    class Tags extends Modules {
        public function __init() {
            $this->addAlias("metaWeblog_newPost_preQuery", "metaWeblog_editPost_preQuery");
            $this->addAlias("javascript", "tagsJS");
            $this->addAlias("admin_javascript", "tagsJS");
        }

        static function __install() {
            Route::current()->add("tag/(name)/", "tag");
        }

        static function __uninstall($confirm) {
            Route::current()->remove("tag/(name)/");

            if ($confirm) {
                $sql = SQL::current();

                foreach($sql->select("post_attributes",
                                     "*",
                                     array("name" => "tags"))->fetchAll() as $post)  {
                    $sql->delete("post_attributes", array("name" => "tags", "post_id" => $post["post_id"]));
                }
            }
        }

        private function tags_serialize($tags) {
            $serialized = json_encode($tags);

            if (json_last_error())
                error(__("Error"), _f("Failed to serialize tags because of JSON error: <code>%s</code>", json_last_error_msg(), "tags"));

            return $serialized;
        }

        private function tags_unserialize($tags) {
            $unserialized = json_decode($tags, true);

            if (json_last_error() and DEBUG)
                error(__("Error"), _f("Failed to unserialize tags because of JSON error: <code>%s</code>", json_last_error_msg(), "tags"));

            return $unserialized;
        }

        public function post_options($fields, $post = null) {
            $cloud = self::list_tags(false);
            usort($cloud, array($this, "sort_tags_name_asc"));

            if (isset($post->tags))
                $tags = array_keys($post->tags);
            else
                $tags = array();

            $selector = "\n".'<span class="tags_select">'."\n";

            foreach ($cloud as $tag) {
                $selected = (in_array($tag["name"], $tags)) ? " tag_added" : "" ;
                $selector.= '<a class="tag'.$selected.'" href="#tags">'.$tag["name"].'</a>'."\n";
            }

            $selector.= "</span>"."\n";

            $fields[] = array("attr" => "tags",
                              "label" => __("Tags", "tags"),
                              "note" => __("(comma separated)", "tags"),
                              "type" => "text",
                              "value" => fix(implode(", ", $tags), true),
                              "extra" => $selector);

            return $fields;
        }

        public function add_post($post) {
            if (empty($_POST['tags']))
                return;

            $tags = explode(",", $_POST['tags']); # Split at the comma.
            $tags = array_map("trim", $tags); # Remove whitespace.
            $tags = array_map("strip_tags", $tags); # Remove HTML.

            foreach ($tags as &$name)
                $name = is_numeric($name) ? "'".$name."'" : $name ;

            $tags = array_unique($tags); # Remove duplicates.
            $tags = array_diff($tags, array("")); # Remove empties.
            $tags_cleaned = array_map("sanitize", $tags);

            $tags = array_combine($tags, $tags_cleaned);

            SQL::current()->insert("post_attributes",
                                   array("name" => "tags",
                                         "value" => self::tags_serialize($tags),
                                         "post_id" => $post->id));
        }

        public function update_post($post) {
            if (empty($_POST['tags'])) {
                SQL::current()->delete("post_attributes",
                                       array("name" => "tags",
                                             "post_id" => $post->id));
                return;
            }

            $tags = explode(",", $_POST['tags']); # Split at the comma.
            $tags = array_map("trim", $tags); # Remove whitespace.
            $tags = array_map("strip_tags", $tags); # Remove HTML.

            foreach ($tags as &$name)
                $name = is_numeric($name) ? "'".$name."'" : $name ;

            $tags = array_unique($tags); # Remove duplicates.
            $tags = array_diff($tags, array("")); # Remove empties.
            $tags_cleaned = array_map("sanitize", $tags);

            $tags = array_combine($tags, $tags_cleaned);

            SQL::current()->replace("post_attributes",
                                    array("post_id", "name"),
                                    array("name" => "tags",
                                          "value" => self::tags_serialize($tags),
                                          "post_id" => $post->id));
        }

        public function parse_urls($urls) {
            $urls["|/tag/([^/]+)/|"] = "/?action=tag&name=$1";
            return $urls;
        }

        public function manage_posts_column_header() {
            echo '<th class="post_tags">'.__("Tags", "tags").'</th>';
        }

        public function manage_posts_column($post) {
            echo '<td class="post_tags">'.implode(" ", $post->linked_tags).'</td>';
        }

        static function manage_nav($navs) {
            if (!Post::any_editable())
                return $navs;

            $navs["manage_tags"] = array("title" => __("Tags", "tags"),
                                         "selected" => array("rename_tag", "delete_tag", "edit_tags"));

            return $navs;
        }

        static function manage_nav_pages($pages) {
            array_push($pages, "manage_tags", "rename_tag", "delete_tag", "edit_tags");
            return $pages;
        }

        public function admin_manage_tags($admin) {
            if (!Post::any_editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage tags.", "tags"));

            $sql = SQL::current();

            $tags = array();
            $names = array();
            foreach($sql->select("post_attributes",
                                 "*",
                                 array("name" => "tags"))->fetchAll() as $tag) {
                $post_tags = self::tags_unserialize($tag["value"]);

                $tags = array_merge($tags, $post_tags);

                foreach ($post_tags as $name => $clean)
                    $names[] = $name;
            }

            $popularity = array_count_values($names);

            $cloud = array();
            if (!empty($popularity)) {
                $max_qty = max($popularity);
                $min_qty = min($popularity);

                $spread = $max_qty - $min_qty;
                if ($spread == 0)
                    $spread = 1;

                $step = 60 / $spread;

                foreach ($popularity as $tag => $count)
                    $cloud[] = array("size" => ceil(100 + (($count - $min_qty) * $step)),
                                     "popularity" => $count,
                                     "name" => $tag,
                                     "title" => sprintf(_p("%s post tagged with &quot;%s&quot;", "%s posts tagged with &quot;%s&quot;", $count, "tags"), $count, $tag),
                                     "clean" => $tags[$tag],
                                     "url" => url("tag/".$tags[$tag], MainController::current()));
            }

            fallback($_GET['query'], "");
            list($where, $params) = keywords(self::tags_safe($_GET['query']), "post_attributes.value LIKE :query");

            $visitor = Visitor::current();
            if (!$visitor->group->can("view_draft", "edit_draft", "edit_post", "delete_draft", "delete_post"))
                $where["user_id"] = $visitor->id;

            $results = Post::find(array("placeholders" => true,
                                        "where" => $where,
                                        "params" => $params));

            $ids = array();

            foreach ($results[0] as $result)
                $ids[] = $result["id"];

            if (!empty($ids))
                $posts = new Paginator(Post::find(array("placeholders" => true,
                                                        "drafts" => true,
                                                        "where" => array("id" => $ids))),
                                       25);
            else
                $posts = new Paginator(array());

            $admin->display("manage_tags", array("tag_cloud" => $cloud,
                                                 "posts" => $posts));
        }

        public function admin_rename_tag($admin) {
            if (!Visitor::current()->group->can("edit_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to rename tags.", "tags"));

            if (empty($_GET['clean']))
                error(__("No Tag Specified", "tags"), __("Please specify the tag you want to rename.", "tags"));

            $sql = SQL::current();

            $tags = array();
            $names = array();

            foreach($sql->select("post_attributes",
                                 "*",
                                 array("name" => "tags",
                                       "value like" => self::tags_clean_match($_GET['clean'])))->fetchAll() as $tag) {
                $post_tags = self::tags_unserialize($tag["value"]);

                $tags = array_merge($tags, $post_tags);

                foreach ($post_tags as $name => $clean)
                    $names[] = $name;
            }

            $popularity = array_count_values($names);

            foreach ($popularity as $tag => $count)
                if ($tags[$tag] == $_GET['clean']) {
                    $tag = array("name" => $tag, "clean" => $tags[$tag]);
                    break;
                }

            if (!isset($tag))
                Flash::warning(__("Tag not found.", "tags"), "/admin/?action=manage_tags");

            $admin->display("rename_tag", array("tag" => $tag));
        }

        public function admin_edit_tags($admin) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified", "tags"), __("An ID is required to edit tags.", "tags"));

            $post = new Post($_GET['id']);

            if ($post->no_results)
                Flash::warning(__("Post not found."), "/admin/?action=manage_tags");

            if (!$post->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this post."));

            $admin->display("edit_tags", array("post" => $post));
        }

        public function admin_update_tags($admin) {
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified", "tags"), __("An ID is required to update tags.", "tags"));

            $post = new Post($_POST['id']);

            if ($post->no_results)
                Flash::warning(__("Post not found."), "/admin/?action=manage_tags");

            if (!$post->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this post."));

            $this->update_post($post);

            Flash::notice(__("Tags updated.", "tags"), "/admin/?action=manage_tags");
        }

        public function admin_update_tag($admin) {
            if (!Visitor::current()->group->can("edit_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to rename tags.", "tags"));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['original']) or empty($_POST['name']))
                error(__("No Tag Specified", "tags"), __("Please specify the tag you want to rename.", "tags"));

            $_POST['name'] = str_replace(",", " ", $_POST['name']);
            $_POST['name'] = is_numeric($_POST['name']) ? "'".$_POST['name']."'" : $_POST['name'] ;

            $sql = SQL::current();

            $tags = array();
            $clean = array();

            foreach($sql->select("post_attributes",
                                 "*",
                                 array("name" => "tags",
                                       "value like" => self::tags_name_match($_POST['original'])))->fetchAll() as $tag) {
                $tags = self::tags_unserialize($tag["value"]);
                unset($tags[$_POST['original']]);

                $tags[$_POST['name']] = sanitize($_POST['name']);

                $sql->update("post_attributes",
                             array("name" => "tags",
                                   "post_id" => $tag["post_id"]),
                             array("value" => self::tags_serialize($tags)));
            }

            Flash::notice(__("Tag renamed.", "tags"), "/admin/?action=manage_tags");
        }

        public function admin_delete_tag($admin) {
            if (empty($_GET['clean']))
                error(__("No Tag Specified", "tags"), __("Please specify the tag you want to delete.", "tags"));

            $sql = SQL::current();

            $tags = array();
            $names = array();

            foreach($sql->select("post_attributes",
                                 "*",
                                 array("name" => "tags",
                                       "value like" => self::tags_clean_match($_GET['clean'])))->fetchAll() as $tag) {
                $post_tags = self::tags_unserialize($tag["value"]);

                $tags = array_merge($tags, $post_tags);

                foreach ($post_tags as $name => $clean)
                    $names[] = $name;
            }

            $popularity = array_count_values($names);

            foreach ($popularity as $tag => $count)
                if ($tags[$tag] == $_GET['clean']) {
                    $tag = array("name" => $tag, "clean" => $tags[$tag]);
                    break;
                }

            if (!isset($tag))
                Flash::warning(__("Tag not found.", "tags"), "/admin/?action=manage_tags");

            $admin->display("delete_tag", array("tag" => $tag));
        }

        public function admin_destroy_tag() {
            if (!Visitor::current()->group->can("edit_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete tags.", "tags"));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['name']))
                error(__("No Tag Specified", "tags"), __("Please specify the tag you want to delete.", "tags"));

            if ($_POST['destroy'] != "indubitably")
                redirect("/admin/?action=manage_tags");

            $sql = SQL::current();

            foreach($sql->select("post_attributes",
                                 "*",
                                 array("name" => "tags",
                                       "value like" => self::tags_name_match($_POST['name'])))->fetchAll() as $tag)  {
                $tags = self::tags_unserialize($tag["value"]);
                unset($tags[$_POST['name']]);

                if (empty($tags))
                    $sql->delete("post_attributes", array("name" => "tags", "post_id" => $tag["post_id"]));
                else
                    $sql->update("post_attributes",
                                 array("name" => "tags",
                                       "post_id" => $tag["post_id"]),
                                 array("value" => self::tags_serialize($tags)));
            }

            Flash::notice(__("Tag deleted.", "tags"), "/admin/?action=manage_tags");
        }

        public function admin_bulk_tag($admin) {
            if (!Visitor::current()->group->can("edit_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to add tags.", "tags"));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['post']))
                Flash::warning(__("No posts selected.", "tags"), "/admin/?action=manage_tags");

            if (empty($_POST['name']))
                Flash::warning(__("No tag specified.", "tags"), "/admin/?action=manage_tags");

            $sql = SQL::current();

            foreach (array_map("trim", explode(",", $_POST['name'])) as $tag)
                $tag = is_numeric($tag) ? "'".$tag."'" : $tag ;

                foreach ($_POST['post'] as $post_id) {
                    $post = new Post($post_id);

                    if (!$post->editable())
                        continue;

                    $tags = $sql->select("post_attributes",
                                         "value",
                                         array("name" => "tags",
                                               "post_id" => $post_id));
                    if ($tags and $value = $tags->fetchColumn())
                        $tags = self::tags_unserialize($value);
                    else
                        $tags = array();

                    $tags[$tag] = sanitize($tag);

                    $sql->replace("post_attributes",
                                  array("post_id", "name"),
                                  array("name" => "tags",
                                        "value" => self::tags_serialize($tags),
                                        "post_id" => $post_id));
                }

            Flash::notice(__("Posts tagged.", "tags"), "/admin/?action=manage_tags");
        }

        public function main_context($context) {
            $context["tags"] = self::list_tags();
            return $context;
        }

        public function main_tag($main) {
            if (!isset($_GET['name']))
                return $main->resort(array("pages/tag", "pages/index"),
                                     array("reason" => "no_tag_specified"),
                                        __("No Tag", "tags"));

            $sql = SQL::current();

            $tags = explode(" ", $_GET['name']);

            $likes = array();

            foreach ($tags as $name)
                $likes[] = self::tags_clean_match($name);

            $attributes = $sql->select("post_attributes",
                                       array("value", "post_id"),
                                       array("name" => "tags",
                                             "value like all" => $likes));

            $ids = array();

            foreach ($attributes->fetchAll() as $index => $row) {
                foreach ($tags as &$tag) {
                    $search = array_search($tag, self::tags_unserialize($row["value"]));
                    $tag = ($search) ? $search : $tag;
                }

                $ids[] = $row["post_id"];
            }

            $tag = list_notate($tags, true);

            if (empty($ids))
                return $main->resort(array("pages/tag", "pages/index"),
                                     array("reason" => "tag_not_found"),
                                        __("Invalid Tag", "tags"));

            $posts = new Paginator(Post::find(array("placeholders" => true,
                                                    "where" => array("id" => $ids))),
                                   Config::current()->posts_per_page);

            if (empty($posts))
                return false;

            $main->display(array("pages".DIR."tag", "pages".DIR."index"),
                           array("posts" => $posts, "tag" => $tag, "tags" => $tags),
                           _f("Posts tagged with %s", array($tag), "tags"));
        }

        public function main_tags($main) {
            $sql = SQL::current();

            if ($sql->count("post_attributes", array("name" => "tags")) > 0) {
                $tags = array();
                $names = array();
                foreach($sql->select("posts",
                                     "post_attributes.*",
                                     array("post_attributes.name" => "tags", Post::statuses(), Post::feathers()),
                                     null,
                                     array(),
                                     null, null, null,
                                     array(array("table" => "post_attributes",
                                                 "where" => "post_id = posts.id")))->fetchAll() as $tag) {
                    $post_tags = self::tags_unserialize($tag["value"]);

                    $tags = array_merge($tags, $post_tags);

                    foreach ($post_tags as $name => $clean)
                        $names[] = $name;
                }

                $popularity = array_count_values($names);

                if (empty($popularity))
                    return $main->resort("pages/tags", array("tag_cloud" => array()), __("No Tags", "tags"));

                $max_qty = max($popularity);
                $min_qty = min($popularity);

                $spread = $max_qty - $min_qty;

                if ($spread == 0)
                    $spread = 1;

                $step = 250 / $spread; # Increase for bigger difference.

                $context = array();

                foreach ($popularity as $tag => $count)
                    $context[] = array("size" => ceil(100 + (($count - $min_qty) * $step)),
                                       "popularity" => $count,
                                       "name" => $tag,
                                       "title" => sprintf(_p("%s post tagged with &quot;%s&quot;", "%s posts tagged with &quot;%s&quot;", $count, "tags"), $count, $tag),
                                       "clean" => $tags[$tag],
                                       "url" => url("tag/".$tags[$tag], $main));

                $main->display("pages".DIR."tags", array("tag_cloud" => $context), __("Tags", "tags"));
            }
        }

        public function metaWeblog_getPost($struct, $post) {
            if (!isset($post->tags))
                $struct['mt_tags'] = "";
            else
                $struct['mt_tags'] = implode(", ", array_keys($post->tags));

            return $struct;
        }

        public function metaWeblog_editPost_preQuery($struct, $post = null) {
            if (isset($struct['mt_tags']))
                $_POST['tags'] = $struct['mt_tags'];
            else if (isset($post->tags))
                $_POST['tags'] = $post->unlinked_tags;
            else
                $_POST['tags'] = '';
        }

        static function linked_tags($tags) {
            if (empty($tags))
                return array();

            $linked = array();
            foreach ($tags as $tag => $clean)
                $linked[] = '<a class="tag" href="'.url("tag/".urlencode($clean), MainController::current()).'" rel="tag">'.$tag.'</a>';

            return $linked;
        }

        public function related_posts($ids, $post, $limit) {
            foreach ($post->tags as $key => $tag) {
                $like = self::tags_name_match($key);
                $results = SQL::current()->query("SELECT DISTINCT __posts.id
                                                  FROM __posts
                                                  LEFT JOIN __post_attributes ON __posts.id = __post_attributes.post_id
                                                    AND __post_attributes.name = 'tags'
                                                    AND __posts.id != $post->id
                                                  WHERE __post_attributes.value LIKE '$like'
                                                  GROUP BY __posts.id
                                                  ORDER BY __posts.created_at DESC
                                                  LIMIT $limit")->fetchAll();

                foreach ($results as $result)
                    if (isset($result["id"]))
                        $ids[] = $result["id"];
            }

            return $ids;
        }

        public function post($post) {
            $tags = !empty($post->tags) ? self::tags_unserialize($post->tags) : array() ;
            uksort($tags, array($this, "sort_tags_asc"));
            $post->tags = $tags;
            $post->linked_tags = self::linked_tags($post->tags);
        }

        private function sort_tags_asc($a, $b) {
            return $this->mb_strcasecmp($a, $b, "UTF-8");
        }

        private function sort_tags_desc($a, $b) {
            return $this->mb_strcasecmp($b, $a, "UTF-8");
        }

        private function sort_tags_name_asc($a, $b) {
            return $this->mb_strcasecmp($a["name"], $b["name"], "UTF-8");
        }

        private function sort_tags_name_desc($a, $b) {
            return $this->mb_strcasecmp($b["name"], $a["name"], "UTF-8");
        }

        private function sort_tags_popularity_asc($a, $b) {
            return $a["popularity"] > $b["popularity"];
        }

        private function sort_tags_popularity_desc($a, $b) {
            return $a["popularity"] < $b["popularity"];
        }

        private function mb_strcasecmp($str1, $str2, $encoding = null) {
            if (null === $encoding)
                $encoding = mb_internal_encoding();

            $str1 = preg_replace("/[[:punct:]]+/", "", $str1);
            $str2 = preg_replace("/[[:punct:]]+/", "", $str2);
            return substr_compare(mb_strtoupper($str1, $encoding), mb_strtoupper($str2, $encoding), 0);
        }

        public function list_tags($limit = 10, $order_by = "popularity", $order = "desc") {
            $sql = SQL::current();

            $attrs = $sql->select("posts",
                                  "post_attributes.value",
                                  array("post_attributes.name" => "tags", Post::statuses(), Post::feathers()),
                                  null,
                                  array(),
                                  null, null, null,
                                  array(array("table" => "post_attributes",
                                              "where" => "post_id = posts.id")));

            $tags = array();
            $names = array();

            while ($attr = $attrs->fetchObject()) {
                $post_tags = self::tags_unserialize($attr->value);

                $tags = array_merge($tags, $post_tags);

                foreach ($post_tags as $name => $clean)
                    $names[] = $name;
            }

            if (empty($tags))
                return array();

            $popularity = array_count_values($names);

            $list = array();

            foreach ($popularity as $name => $number)
                $list[$name] = array("name" => $name,
                                     "popularity" => $number,
                                     "percentage" => $number / array_sum($popularity),
                                     "url" => urlencode($tags[$name]),
                                     "clean" => $tags[$name]);

            usort($list, array($this, "sort_tags_".$order_by."_".$order));

            return ($limit) ? array_slice($list, 0, $limit) : $list ;
        }

        private function tags_name_match($name) {
            # Serialized notation of key
            return "%\"".self::tags_safe($name)."\":%";
        }

        private function tags_clean_match($clean) {
            # Serialized notation of value
            return "%:\"".self::tags_safe($clean)."\"%";
        }

        private function tags_safe($text) {
            # Match escaping of JSON encoded data
            $text = trim(json_encode($text), "\"");

            # Return string escaped for SQL query
            return SQL::current()->escape($text, false);
        }

        public function ajax_tag_post() {
            if (empty($_POST['name']) or empty($_POST['post']) or !is_numeric($_POST['post']))
                exit("{}");

            $sql = SQL::current();

            $post = new Post($_POST['post']);
            $tag = $_POST['name'];

            if (!$post->editable())
                exit("{}");

            $tags = $sql->select("post_attributes",
                                 "value",
                                 array("name" => "tags",
                                       "post_id" => $post->id));

            if ($tags and $value = $tags->fetchColumn())
                $tags = self::tags_unserialize($value);
            else
                $tags = array();

            $tags[$tag] = sanitize($tag);

            $sql->replace("post_attributes",
                          array("post_id", "name"),
                          array("name" => "tags",
                                "value" => self::tags_serialize($tags),
                                "post_id" => $post->id));

            exit("{ \"url\": \"".url("tag/".$tags[$tag], MainController::current())."\", \"tag\": \"".$_POST['name']."\" }");
        }

        function feed_item($post) {
            $config = Config::current();

            foreach ($post->tags as $tag => $clean)
                echo "        <category scheme=\"".$config->url."/tag/\" term=\"".$clean."\" label=\"".fix($tag)."\" />\n";
        }

        public function tagsJS() {
            include MODULES_DIR.DIR."tags".DIR."javascript.php";
        }
    }
