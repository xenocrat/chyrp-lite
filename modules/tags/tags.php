<?php
    class Tags extends Modules {
        public function __init() {
            $this->addAlias("metaWeblog_newPost_preQuery", "metaWeblog_editPost_preQuery");
        }

        static function __install() {
            Route::current()->add("tag/(name)/", "tag");
        }

        static function __uninstall($confirm) {
            Route::current()->remove("tag/(name)/");

            if ($confirm)
                SQL::current()->delete("post_attributes", array("name" => "tags"));
        }

        private function tags_serialize($tags) {
            return json_set($tags);
        }

        private function tags_unserialize($tags) {
            return json_get($tags, true);
        }

        private function sort_tags_asc($a, $b) {
            return $this->mb_strcasecmp($a, $b);
        }

        private function sort_tags_desc($a, $b) {
            return $this->mb_strcasecmp($b, $a);
        }

        private function sort_tags_name_asc($a, $b) {
            return $this->mb_strcasecmp($a["name"], $b["name"]);
        }

        private function sort_tags_name_desc($a, $b) {
            return $this->mb_strcasecmp($b["name"], $a["name"]);
        }

        private function sort_tags_popularity_asc($a, $b) {
            return $a["popularity"] > $b["popularity"];
        }

        private function sort_tags_popularity_desc($a, $b) {
            return $a["popularity"] < $b["popularity"];
        }

        private function mb_strcasecmp($str1, $str2, $encoding = "UTF-8") {
            $str1 = preg_replace("/[[:punct:]]+/", "", $str1);
            $str2 = preg_replace("/[[:punct:]]+/", "", $str2);

            if (!function_exists("mb_strtoupper"))
                return substr_compare(strtoupper($str1), strtoupper($str2), 0);

            return substr_compare(mb_strtoupper($str1, $encoding), mb_strtoupper($str2, $encoding), 0);
        }

        private function tags_name_match($name) {
            # Serialized notation of key for SQL queries.
            return "%\"".self::tags_encoded($name)."\":%";
        }

        private function tags_clean_match($clean) {
            # Serialized notation of value for SQL queries.
            return "%:\"".self::tags_encoded($clean)."\"%";
        }

        private function tags_encoded($text) {
            # Recreate JSON encoding and do SQL double-escaping for the search term.
            return SQL::current()->escape(trim(json_set((string) $text), "\""), false);
        }

        private function prepare_tags($tags) {
            # Split at the comma.
            $names = explode(",", $tags);

            # Remove HTML.
            $names = array_map("strip_tags", $names);

            # Remove whitespace.
            $names = array_map("trim", $names);

            # Prevent numbers from being type-juggled to numeric keys.
            foreach ($names as &$name)
                $name = is_numeric($name) ? "'".$name."'" : $name ;

            # Remove duplicates.
            $names = array_unique($names);

            # Remove empties.
            $names = array_diff($names, array(""));

            # Build an array containing a sanitized slug for each tag.
            $clean = array_map(function($value) { return sanitize($value, true, true); }, $names);

            # Build an associative array with tags as the keys and slugs as the values.
            $assoc = array_combine($names, $clean);

            # Remove any entries with slugs that have been sanitized into nothingness.
            $assoc = array_filter($assoc, function($value) { return preg_match('/[^\-]+/', $value); });

            return $assoc;
        }

        public function post_options($fields, $post = null) {
            $cloud = self::list_tags(0);
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
                              "help" => "tagging_posts",
                              "note" => __("(comma separated)", "tags"),
                              "type" => "text",
                              "value" => fix(implode(", ", $tags), true),
                              "extra" => $selector);

            return $fields;
        }

        public function add_post($post) {
            if (empty($_POST['tags']))
                return;

            $tags = self::prepare_tags($_POST['tags']);

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

            $tags = self::prepare_tags($_POST['tags']);

            SQL::current()->replace("post_attributes",
                                    array("post_id", "name"),
                                    array("name" => "tags",
                                          "value" => self::tags_serialize($tags),
                                          "post_id" => $post->id));
        }

        public function parse_urls($urls) {
            $urls["|/tag/([^/]+)/|"] = "/?action=tag&amp;name=$1";
            return $urls;
        }

        public function manage_posts_column_header() {
            echo '<th class="post_tags list">'.__("Tags", "tags").'</th>';
        }

        public function manage_posts_column($post) {
            echo '<td class="post_tags list">'.implode(" ", $post->tags_link).'</td>';
        }

        public function manage_nav($navs) {
            if (Post::any_editable())
                $navs["manage_tags"] = array("title" => __("Tags", "tags"),
                                             "selected" => array("rename_tag", "delete_tag", "edit_tags"));

            return $navs;
        }

        public function admin_manage_tags($admin) {
            if (!Post::any_editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to manage tags.", "tags"));

            $sql = SQL::current();
            $visitor = Visitor::current();

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
                                     "title" => sprintf(_p("%s post tagged with &quot;%s&quot;",
                                                           "%s posts tagged with &quot;%s&quot;", $count, "tags"),
                                                        $count, fix($tag, true)),
                                     "clean" => $tags[$tag],
                                     "url" => url("tag/".$tags[$tag], MainController::current()));

                usort($cloud, array($this, "sort_tags_name_asc"));
            }

            fallback($_GET['query'], "");
            list($where, $params) = keywords(self::tags_encoded($_GET['query']),
                                             "post_attributes.name = 'tags' AND post_attributes.value LIKE :query");

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
                                       Config::current()->admin_per_page);
            else
                $posts = new Paginator(array());

            $admin->display("pages".DIR."manage_tags", array("tag_cloud" => $cloud, "posts" => $posts));
        }

        public function admin_rename_tag($admin) {
            if (!Visitor::current()->group->can("edit_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to rename tags.", "tags"));

            if (empty($_GET['clean']))
                error(__("No Tag Specified", "tags"), __("Please specify the tag you want to rename.", "tags"), null, 400);

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
                Flash::warning(__("Tag not found.", "tags"), "manage_tags");

            $admin->display("pages".DIR."rename_tag", array("tag" => $tag));
        }

        public function admin_edit_tags($admin) {
            if (empty($_GET['id']) or !is_numeric($_GET['id']))
                error(__("No ID Specified"), __("An ID is required to edit tags.", "tags"), null, 400);

            $post = new Post($_GET['id']);

            if ($post->no_results)
                Flash::warning(__("Post not found."), "manage_tags");

            if (!$post->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this post."));

            $admin->display("pages".DIR."edit_tags", array("post" => $post));
        }

        public function admin_update_tags($admin) {
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to update tags.", "tags"), null, 400);

            $post = new Post($_POST['id']);

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            if (!$post->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this post."));

            $this->update_post($post);

            Flash::notice(__("Tags updated.", "tags"), "manage_tags");
        }

        public function admin_update_tag($admin) {
            if (!Visitor::current()->group->can("edit_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to rename tags.", "tags"));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['original']))
                error(__("No Tag Specified", "tags"), __("Please specify the tag you want to rename.", "tags"), null, 400);

            if (empty($_POST['name']))
                error(__("Error"), __("Name cannot be blank.", "tags"), null, 422);

            $sql = SQL::current();
            $new = self::prepare_tags(str_replace(",", " ", $_POST['name']));

            foreach($sql->select("post_attributes",
                                 "*",
                                 array("name" => "tags",
                                       "value like" => self::tags_name_match($_POST['original'])))->fetchAll() as $tag) {

                $old = self::tags_unserialize($tag["value"]);
                unset($old[$_POST['original']]);

                $sql->update("post_attributes",
                             array("name" => "tags",
                                   "post_id" => $tag["post_id"]),
                             array("value" => self::tags_serialize(array_merge($old, $new))));
            }

            Flash::notice(__("Tag renamed.", "tags"), "manage_tags");
        }

        public function admin_delete_tag($admin) {
            if (empty($_GET['clean']))
                error(__("No Tag Specified", "tags"), __("Please specify the tag you want to delete.", "tags"), null, 400);

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
                Flash::warning(__("Tag not found.", "tags"), "manage_tags");

            $admin->display("pages".DIR."delete_tag", array("tag" => $tag));
        }

        public function admin_destroy_tag() {
            if (!Visitor::current()->group->can("edit_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to delete tags.", "tags"));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['name']))
                error(__("No Tag Specified", "tags"), __("Please specify the tag you want to delete.", "tags"), null, 400);

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_tags");

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

            Flash::notice(__("Tag deleted.", "tags"), "manage_tags");
        }

        public function admin_bulk_tag($admin) {
            if (!Visitor::current()->group->can("edit_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to add tags.", "tags"));

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['post']))
                Flash::warning(__("No posts selected.", "tags"), "manage_tags");

            if (empty($_POST['name']))
                Flash::warning(__("No tags specified.", "tags"), "manage_tags");

            $sql = SQL::current();
            $new = self::prepare_tags($_POST['name']);

            foreach ($_POST['post'] as $post_id) {
                $post = new Post($post_id);

                if (!$post->editable())
                    continue;

                $tags = $sql->select("post_attributes",
                                     "value",
                                     array("name" => "tags",
                                           "post_id" => $post_id));

                if ($tags and $value = $tags->fetchColumn())
                    $old = self::tags_unserialize($value);
                else
                    $old = array();

                $sql->replace("post_attributes",
                              array("post_id", "name"),
                              array("name" => "tags",
                                    "value" => self::tags_serialize(array_merge($old, $new)),
                                    "post_id" => $post_id));
            }

            Flash::notice(__("Posts tagged.", "tags"), "manage_tags");
        }

        public function main_context($context) {
            $context["tags"] = self::list_tags();
            return $context;
        }

        public function main_tag($main) {
            if (!isset($_GET['name']))
                return $main->resort(array("pages".DIR."tag", "pages".DIR."index"),
                                     array("reason" => __("You did not specify a tag.", "tags")),
                                     __("No Tag", "tags"));

            $sql = SQL::current();
            $tags = explode(" ", $_GET['name']); # Detect multiple tags (clean tag names have no spaces).
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
                return $main->resort(array("pages".DIR."tag", "pages".DIR."index"),
                                     array("reason" => __("There are no posts with the tag you specified.", "tags")),
                                     __("Invalid Tag", "tags"));

            $posts = new Paginator(Post::find(array("placeholders" => true,
                                                    "where" => array("id" => $ids))),
                                   Config::current()->posts_per_page);

            if (empty($posts))
                return false;

            $main->display(array("pages".DIR."tag", "pages".DIR."index"),
                           array("posts" => $posts,
                                 "tag" => $tag, "tags" => $tags),
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
                                     null,
                                     null,
                                     null,
                                     array(array("table" => "post_attributes",
                                                 "where" => "post_id = posts.id")))->fetchAll() as $tag) {

                    $post_tags = self::tags_unserialize($tag["value"]);
                    $tags = array_merge($tags, $post_tags);

                    foreach ($post_tags as $name => $clean)
                        $names[] = $name;
                }

                $popularity = array_count_values($names);

                if (empty($popularity))
                    return $main->resort("pages".DIR."tags",
                                         array("tag_cloud" => array()),
                                         __("No Tags", "tags"));

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
                                       "title" => sprintf(_p("%s post tagged with &quot;%s&quot;",
                                                             "%s posts tagged with &quot;%s&quot;", $count, "tags"),
                                                          $count, fix($tag, true)),
                                       "clean" => $tags[$tag],
                                       "url" => url("tag/".$tags[$tag], $main));

                usort($context, array($this, "sort_tags_name_asc"));
                $main->display("pages".DIR."tags", array("tag_cloud" => $context), __("Tags", "tags"));
            }
        }

        public function metaWeblog_getPost($struct, $post) {
            if (!empty($post->tags))
                $struct['mt_keywords'] = array_keys($post->tags);

            return $struct;
        }

        public function metaWeblog_editPost_preQuery($struct, $post = null) {
            if (isset($struct["mt_keywords"]))
                $_POST['tags'] = implode(", ", (array) $struct["mt_keywords"]);
            else
                $_POST['tags'] = isset($post->tags) ? implode(", ", array_keys($post->tags)) : "" ;
        }

        public function post_tags_link_attr($attr, $post) {
            if (empty($post->tags))
                return array();

            $linked = array();

            foreach ($post->tags as $tag => $clean)
                $linked[] = '<a class="tag" href="'.url("tag/".urlencode($clean), MainController::current()).'" rel="tag">'.$tag.'</a>';

            return $linked;
        }

        public function related_posts($ids, $post, $limit) {
            if (empty($post->tags))
                return $ids;

            foreach ($post->tags as $key => $tag) {
                $results = SQL::current()->select("post_attributes",
                                                  array("post_id"),
                                                  array("name" => "tags",
                                                        "value like" => self::tags_name_match($key),
                                                        "post_id !=" => $post->id),
                                                  array("ORDER BY" => "post_id DESC"),
                                                  array(),
                                                  $limit)->fetchAll();

                foreach ($results as $result)
                    if (isset($result["post_id"]))
                        $ids[] = $result["post_id"];
            }

            return $ids;
        }

        public function post($post) {
            $tags = !empty($post->tags) ? self::tags_unserialize($post->tags) : array() ;
            uksort($tags, array($this, "sort_tags_asc"));
            $post->tags = $tags;
        }

        public function list_tags($limit = 10, $order_by = "popularity", $order = "desc") {
            $sql = SQL::current();

            $attrs = $sql->select("posts",
                                  "post_attributes.value",
                                  array("post_attributes.name" => "tags", Post::statuses(), Post::feathers()),
                                  null,
                                  array(),
                                  null,
                                  null,
                                  null,
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

        public function feed_item($post) {
            $config = Config::current();

            foreach ($post->tags as $tag => $clean)
                echo "        <category scheme=\"".$config->url."/tag/\" term=\"".$clean."\" label=\"".fix($tag)."\" />\n";
        }

        public function admin_javascript() {
            include MODULES_DIR.DIR."tags".DIR."javascript.php";
        }
    }
