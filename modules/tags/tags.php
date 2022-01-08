<?php
    class Tags extends Modules {
        public function __init() {
            $this->addAlias("metaWeblog_before_newPost", "metaWeblog_before_editPost");
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

        private function sort_tags_name_asc($a, $b) {
            return $this->mb_strcasecmp($a["name"], $b["name"]);
        }

        private function sort_tags_name_desc($a, $b) {
            return $this->mb_strcasecmp($b["name"], $a["name"]);
        }

        private function sort_tags_popularity_asc($a, $b) {
            if ($a["popularity"] == $b["popularity"])
                return 0;

            return ($a["popularity"] < $b["popularity"]) ? -1 : 1 ;
        }

        private function sort_tags_popularity_desc($a, $b) {
            if ($a["popularity"] == $b["popularity"])
                return 0;

            return ($a["popularity"] > $b["popularity"]) ? -1 : 1 ;
        }

        private function mb_strcasecmp($str1, $str2, $encoding = "UTF-8") {
            $str1 = preg_replace("/[[:punct:]]+/", "", $str1);
            $str2 = preg_replace("/[[:punct:]]+/", "", $str2);

            if (!function_exists("mb_strtoupper"))
                return substr_compare(strtoupper($str1), strtoupper($str2), 0);

            return substr_compare(mb_strtoupper($str1, $encoding),
                                  mb_strtoupper($str2, $encoding), 0);
        }

        private function tags_name_match($name) {
            # Serialized notation of key for SQL queries.
            return "%\"".$this->tags_encoded($name)."\":%";
        }

        private function tags_clean_match($clean) {
            # Serialized notation of value for SQL queries.
            return "%:\"".$this->tags_encoded($clean)."\"%";
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
            $clean = array_map(function($value) {
                return sanitize($value, true, true);
            }, $names);

            # Build an associative array with tags as the keys and slugs as the values.
            $assoc = array_combine($names, $clean);

            # Replace any slugs that have been sanitized into nothingness with a hash.
            foreach ($assoc as $name => &$slug)
                $slug = preg_match("/[^\-0-9]+/", $slug) ? $slug : md5($name) ;

            return $assoc;
        }

        public function before_add_post_attributes($attributes) {
            if (!isset($_POST['tags']))
                return;

            $tags = $this->prepare_tags($_POST['tags']);
            $attributes["tags"] = $this->tags_serialize($tags);
            return $attributes;
        }

        public function before_update_post_attributes($attributes) {
            if (!isset($_POST['tags']))
                return;

            $tags = $this->prepare_tags($_POST['tags']);
            $attributes["tags"] = $this->tags_serialize($tags);
            return $attributes;
        }

        public function post_options($fields, $post = null) {
            $cloud = $this->tag_cloud(false, "name_asc");
            $names = isset($post->tags) ? array_keys($post->tags) : array();

            $selector = "\n".'<span class="options_extra tags_select">';

            foreach ($cloud as $tag) {
                $selected = (in_array($tag["name"], $names)) ? " tag_added" : "" ;

                $selector.= '<a class="tag'.$selected.
                            '" href="#" role="button">'.$tag["name"].'</a> ';
            }

            $selector.= "</span>"."\n";

            $fields[] = array("attr" => "tags",
                              "label" => __("Tags", "tags"),
                              "help" => "tagging_posts",
                              "note" => __("(comma separated)", "tags"),
                              "type" => "text",
                              "value" => implode(", ", $names),
                              "extra" => $selector);

            return $fields;
        }

        public function post($post) {
            $post->tags = !empty($post->tags) ? $this->tags_unserialize($post->tags) : array() ;
            uksort($post->tags, function($a, $b) {
                return $this->mb_strcasecmp($a, $b);
            });
        }

        public function post_tags_link_attr($attr, $post) {
            $main = MainController::current();
            $tags = array();

            if ($post->no_results)
                return $tags;

            foreach ($post->tags as $tag => $clean)
                $tags[] = '<a class="tag" href="'.
                          url("tag/".urlencode($clean), $main).'" rel="tag">'.$tag.'</a>';

            return $tags;
        }

        public function twig_context_main($context) {
            if (!isset($context["tag_cloud"]))
                $context["tag_cloud"] = $this->tag_cloud(10);

            return $context;
        }

        public function parse_urls($urls) {
            $urls['|/tag/([^/]+)/|'] = '/?action=tag&amp;name=$1';
            return $urls;
        }

        public function manage_nav($navs) {
            if (Post::any_editable())
                $navs["manage_tags"] = array("title" => __("Tags", "tags"),
                                             "selected" => array("rename_tag", "delete_tag", "edit_tags"));

            return $navs;
        }

        public function manage_posts_column_header() {
            echo '<th class="post_tags list">'.__("Tags", "tags").'</th>';
        }

        public function manage_posts_column($post) {
            $tags = !empty($post->tags_link) ? implode(" ", $post->tags_link) : "" ;
            echo '<td class="post_tags list">'.$tags.'</td>';
        }

        public function admin_manage_tags($admin) {
            if (!Post::any_editable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to manage tags.", "tags"));

            fallback($_GET['query'], "");
            list($where, $params) = keywords($this->tags_encoded($_GET['query']),
                                    "post_attributes.name = 'tags' AND post_attributes.value LIKE :query");

            $visitor = Visitor::current();

            if (!$visitor->group->can("edit_draft", "edit_post", "delete_draft", "delete_post"))
                $where["user_id"] = $visitor->id;

            $results = Post::find(array("placeholders" => true,
                                        "where" => $where,
                                        "params" => $params));

            $ids = array();

            foreach ($results[0] as $result)
                $ids[] = $result["id"];

            if (!empty($ids)) {
                $posts = new Paginator(
                    Post::find(array("placeholders" => true,
                                     "drafts" => true,
                                     "where" => array("id" => $ids))), $admin->post_limit);
            } else {
                $posts = new Paginator(array());
            }

            $admin->display("pages".DIR."manage_tags",
                            array("tag_cloud" => $this->tag_cloud(), "posts" => $posts));
        }

        public function admin_rename_tag($admin) {
            if (!Post::any_editable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to rename tags.", "tags"));

            if (empty($_GET['clean']))
                error(__("No Tag Specified", "tags"),
                      __("Please specify the tag you want to rename.", "tags"), null, 400);

            $tag = $this->tag_find($_GET['clean']);

            if (empty($tag))
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
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to edit this post."));

            $admin->display("pages".DIR."edit_tags", array("post" => $post));
        }

        public function admin_update_tags($admin) {
            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['id']) or !is_numeric($_POST['id']))
                error(__("No ID Specified"), __("An ID is required to update tags.", "tags"), null, 400);

            $post = new Post($_POST['id']);

            if ($post->no_results)
                show_404(__("Not Found"), __("Post not found."));

            if (!$post->editable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to edit this post."));

            $post->update();

            Flash::notice(__("Tags updated.", "tags"), "manage_tags");
        }

        public function admin_update_tag($admin) {
            if (!Post::any_editable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to rename tags.", "tags"));

            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['original']))
                error(__("No Tag Specified", "tags"),
                      __("Please specify the tag you want to rename.", "tags"), null, 400);

            if (empty($_POST['name']))
                error(__("Error"), __("Name cannot be blank.", "tags"), null, 422);

            $results = SQL::current()->select(
                "post_attributes",
                "post_id",
                array("name" => "tags",
                      "value LIKE" => $this->tags_name_match($_POST['original'])))->fetchAll();

            foreach ($results as $result) {
                $post = new Post($result["post_id"]);

                if (!$post->editable())
                    continue;

                unset($post->tags[$_POST['original']]);

                $_POST['tags'] = implode(", ", array_keys($post->tags)).", ".$_POST['name'];
                $post->update();
            }

            Flash::notice(__("Tag renamed.", "tags"), "manage_tags");
        }

        public function admin_delete_tag($admin) {
            if (!Post::any_editable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to delete tags.", "tags"));

            if (empty($_GET['clean']))
                error(__("No Tag Specified", "tags"),
                      __("Please specify the tag you want to delete.", "tags"), null, 400);

            $tag = $this->tag_find($_GET['clean']);

            if (empty($tag))
                Flash::warning(__("Tag not found.", "tags"), "manage_tags");

            $admin->display("pages".DIR."delete_tag", array("tag" => $tag));
        }

        public function admin_destroy_tag() {
            if (!Post::any_editable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to delete tags.", "tags"));

            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['name']))
                error(__("No Tag Specified", "tags"),
                      __("Please specify the tag you want to delete.", "tags"), null, 400);

            if (!isset($_POST['destroy']) or $_POST['destroy'] != "indubitably")
                redirect("manage_tags");

            $results = SQL::current()->select(
                "post_attributes",
                "post_id",
                array("name" => "tags",
                      "value LIKE" => $this->tags_name_match($_POST['name'])))->fetchAll();

            foreach ($results as $result)  {
                $post = new Post($result["post_id"]);

                if (!$post->editable())
                    continue;

                unset($post->tags[$_POST['name']]);

                $_POST['tags'] = implode(", ", array_keys($post->tags));
                $post->update();
            }

            Flash::notice(__("Tag deleted.", "tags"), "manage_tags");
        }

        public function admin_bulk_tag($admin) {
            if (!Post::any_editable())
                show_403(__("Access Denied"),
                         __("You do not have sufficient privileges to add tags.", "tags"));

            if (!isset($_POST['hash']) or !authenticate($_POST['hash']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            if (empty($_POST['post']))
                Flash::warning(__("No posts selected.", "tags"), "manage_tags");

            if (empty($_POST['name']))
                Flash::warning(__("No tags specified.", "tags"), "manage_tags");

            foreach ($_POST['post'] as $post_id) {
                $post = new Post($post_id);

                if (!$post->editable())
                    continue;

                $_POST['tags'] = implode(", ", array_keys($post->tags)).", ".$_POST['name'];
                $post->update();
            }

            Flash::notice(__("Posts tagged.", "tags"), "manage_tags");
        }

        public function main_tag($main) {
            if (!isset($_GET['name']))
                return $main->display(array("pages".DIR."tag", "pages".DIR."index"),
                    array("reason" => __("You did not specify a tag.", "tags")),
                    __("Invalid Tag", "tags"));

            $tag = $this->tag_find($_GET['name']);

            if (empty($tag))
                return $main->display(array("pages".DIR."tag", "pages".DIR."index"),
                    array("reason" => __("The tag you specified was not found.", "tags")),
                    __("Invalid Tag", "tags"));

            $results = SQL::current()->select(
                "post_attributes",
                array("value", "post_id"),
                array("name" => "tags",
                      "value LIKE" => $this->tags_clean_match($_GET['name'])))->fetchAll();

            $ids = array();

            foreach ($results as $result)
                $ids[] = $result["post_id"];

            if (empty($ids))
                return $main->display(array("pages".DIR."tag", "pages".DIR."index"),
                    array("reason" => __("There are no posts with the tag you specified.", "tags")),
                    __("Invalid Tag", "tags"));

            $posts = new Paginator(
                Post::find(array("placeholders" => true,
                                 "where" => array("id" => $ids))), $main->post_limit);

            if (empty($posts))
                return false;

            $main->display(array("pages".DIR."tag", "pages".DIR."index"),
                           array("posts" => $posts, "tag" => $tag),
                           _f("Posts tagged with &#8220;%s&#8221;", array($tag["name"]), "tags"));
        }

        public function main_tags($main) {
            $nonce = "";
            Trigger::current()->filter($nonce, "stylesheets_nonce");

            $main->display("pages".DIR."tags",
                           array("tag_cloud" => $this->tag_cloud(false, "name_asc"),
                                 "tag_nonce" => $nonce),
                           __("Tags", "tags"));
        }

        public function metaWeblog_getPost($struct, $post) {
            if (!empty($post->tags))
                $struct['mt_keywords'] = implode(", ", array_keys($post->tags));

            return $struct;
        }

        public function metaWeblog_before_editPost($values, $struct) {
            if (isset($struct["mt_keywords"])) {
                $tags = $this->prepare_tags($struct["mt_keywords"]);
                $values["tags"] = $this->tags_serialize($tags);
            }

            return $values;
        }

        public function related_posts($ids, $post, $limit) {
            if (empty($post->tags))
                return $ids;

            if (count($ids) >= $limit)
                return $ids;

            foreach ($post->tags as $name => $clean) {
                $results = SQL::current()->select(
                    "post_attributes",
                    array("post_id"),
                    array("name" => "tags",
                          "value LIKE" => $this->tags_name_match($name),
                          "post_id !=" => $post->id),
                    array("post_id DESC"),
                    array(),
                    $limit)->fetchAll();

                foreach ($results as $result)
                    $ids[] = $result["post_id"];
            }

            return $ids;
        }

        public function tag_cloud($limit = false, $sort = "popularity_desc", $scale = 400) {
            if (isset($this->tag_cache))
                $cloud = $this->tag_cache;

            if (empty($cloud)) {
                $results = SQL::current()->select(
                    "posts",
                    "post_attributes.*",
                    array("post_attributes.name" => "tags",
                          Post::statuses(),
                          Post::feathers()),
                    null,
                    array(),
                    null,
                    null,
                    null,
                    array(array("table" => "post_attributes",
                                "where" => "post_id = posts.id")))->fetchAll();

                $found = array();
                $names = array();
                $cloud = array();

                foreach ($results as $result) {
                    $these = $this->tags_unserialize($result["value"]);
                    $found = array_merge($found, $these);

                    foreach ($these as $name => $clean)
                        $names[] = $name;
                }

                if (empty($found))
                    return $cloud;

                $popularity = array_count_values($names);
                $min = min($popularity);
                $max = max($popularity);
                $dif = (int) $scale / (($min === $max) ? 1 : ($max - $min));

                $main = MainController::current();

                foreach ($popularity as $tag => $count) {
                    $title = $this->tag_title_post_count($tag, $count);

                    $cloud[] = array("size" => floor($dif * ($count - $min)),
                                     "popularity" => $count,
                                     "name" => $tag,
                                     "title" => $title,
                                     "clean" => $found[$tag],
                                     "url" => url("tag/".$found[$tag], $main));
                }

                $this->tag_cache = $cloud;
            }

            usort($cloud, array($this, "sort_tags_".$sort));
            return ($limit) ? array_slice($cloud, 0, $limit) : $cloud ;
        }

        private function tag_title_post_count($tag, $count) {
            $str = _p("%d post tagged with &#8220;%s&#8221;", "%d posts tagged with &#8220;%s&#8221;", $count, "tags");
            return sprintf($str, $count, fix($tag, true));
        }

        public function tag_find($clean) {
            $cloud = $this->tag_cloud();

            foreach ($cloud as $tag)
                if ($tag["clean"] === $clean)
                    return $tag;

            return false;
        }

        public function feed_item($post, $feed) {
            $scheme = url("tags", MainController::current());

            foreach ($post->tags as $tag => $clean)
                $feed->category($clean, $scheme, $tag);
        }

        public function admin_javascript() {
            include MODULES_DIR.DIR."tags".DIR."javascript.php";
        }
    }
