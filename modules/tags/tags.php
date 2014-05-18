<?php
    class Tags extends Modules {
        public function __init() {
            $this->addAlias("metaWeblog_newPost_preQuery", "metaWeblog_editPost_preQuery");
            $this->addAlias("javascript", "tagsJS");
        }

        static function __install() {
            Route::current()->add("tag/(name)/", "tag");
        }

        static function __uninstall($confirm) {
            Route::current()->remove("tag/(name)/");
        }

        public function admin_head() {
            $config = Config::current();
?>
        <link rel="stylesheet" href="<?php echo $config->chyrp_url; ?>/modules/tags/admin.css" type="text/css" media="screen" />
        <script type="text/javascript">
        <?php $this->tagsJS(); ?>
        </script>
<?php
        }

        public function post_options($fields, $post = null) {
            $tags = self::list_tags(false);
            usort($tags, array($this, "sort_tags_name_desc"));

            $selector = '<span class="tags_select">'."\n";

            foreach (array_reverse($tags) as $tag) {
                $selected = ($post and isset($post->tags[$tag["name"]])) ?
                                ' class="tag_added"' :
                                "" ;
                $selector.= "\t\t\t\t\t\t\t\t".'<a href="javascript:add_tag(\''.addslashes($tag["name"]).'\')"'.$selected.'>'.$tag["name"].'</a>'."\n";
            }

            $selector.= "\t\t\t\t\t\t\t</span><br /><br />";

            if (isset($post->tags))
                $tags = array_keys($post->tags);
            else
                $tags = array();

            $fields[] = array("attr" => "tags",
                              "label" => __("Tags", "tags"),
                              "note" => __("(comma separated)", "tags"),
                              "type" => "text",
                              "value" => implode(", ", $tags),
                              "extra" => $selector);

            return $fields;
        }

        public function add_post($post) {
            if (empty($_POST['tags'])) return;

            $tags = explode(",", $_POST['tags']); # Split at the comma
            $tags = array_map("trim", $tags); # Remove whitespace
            $tags = array_map("strip_tags", $tags); # Remove HTML
            $tags = array_unique($tags); # Remove duplicates
            $tags = array_diff($tags, array("")); # Remove empties
            $tags_cleaned = array_map("sanitize", $tags);

            $tags = array_combine($tags, $tags_cleaned);

            SQL::current()->insert("post_attributes",
                                   array("name" => "tags",
                                         "value" => YAML::dump($tags),
                                         "post_id" => $post->id));
        }

        public function update_post($post) {
            if (empty($_POST['tags'])) {
                SQL::current()->delete("post_attributes",
                                       array("name" => "tags",
                                             "post_id" => $post->id));
                return;
            }

            $tags = explode(",", $_POST['tags']); # Split at the comma
            $tags = array_map("trim", $tags); # Remove whitespace
            $tags = array_map("strip_tags", $tags); # Remove HTML
            $tags = array_unique($tags); # Remove duplicates
            $tags = array_diff($tags, array("")); # Remove empties
            $tags_cleaned = array_map("sanitize", $tags);

            $tags = array_combine($tags, $tags_cleaned);

            SQL::current()->replace("post_attributes",
                                    array("post_id", "name"),
                                    array("name" => "tags",
                                          "value" => YAML::dump($tags),
                                          "post_id" => $post->id));
        }

        public function parse_urls($urls) {
            $urls["|/tag/([^/]+)/|"] = "/?action=tag&name=$1";
            return $urls;
        }

        public function manage_posts_column_header() {
            echo "<th>".__("Tags", "tags")."</th>";
        }

        public function manage_posts_column($post) {
            echo "<td>".implode(", ", $post->linked_tags)."</td>";
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
                $post_tags = YAML::load($tag["value"]);

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
                    $cloud[] = array("size" => (100 + (($count - $min_qty) * $step)),
                                     "popularity" => $count,
                                     "name" => $tag,
                                     "title" => sprintf(_p("%s post tagged with &quot;%s&quot;", "%s posts tagged with &quot;%s&quot;", $count, "tags"), $count, $tag),
                                     "clean" => $tags[$tag],
                                     "url" => url("tag/".$tags[$tag], MainController::current()));
            }

            fallback($_GET['query'], "");
            list($where, $params) = keywords($_GET['query'], "post_attributes.value LIKE :query OR url LIKE :query");

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
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit tags.", "tags"));

            $sql = SQL::current();

            $tags = array();
            $names = array();

            foreach($sql->select("post_attributes",
                                 "*",
                                 array("name" => "tags",
                                       "value like" => self::yaml_match($_GET['name'])))->fetchAll() as $tag) {
                $post_tags = YAML::load($tag["value"]);

                $tags = array_merge($tags, $post_tags);

                foreach ($post_tags as $name => $clean)
                    $names[] = $name;
            }

            $popularity = array_count_values($names);

            foreach ($popularity as $tag => $count)
                if ($tags[$tag] == $_GET['name']) {
                    $tag = array("name" => $tag, "clean" => $tags[$tag]);
                    break;
                }

            $admin->display("rename_tag", array("tag" => $tag));
        }

        public function admin_edit_tags($admin) {
            if (empty($_GET['id']))
                error(__("No ID Specified"), __("Please specify the ID of the post whose tags you would like to edit.", "tags"));

            $post = new Post($_GET['id']);
            if (!$post->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this post."));

            $admin->display("edit_tags", array("post" => new Post($_GET['id'])));
        }

        public function admin_update_tags($admin) {
            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!isset($_POST['id']))
                error(__("No ID Specified"), __("Please specify the ID of the post whose tags you would like to edit.", "tags"));

            $post = new Post($_POST['id']);
            if (!$post->editable())
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this post."));

            $this->update_post($post);

            Flash::notice(__("Tags updated.", "tags"), "/admin/?action=manage_tags");
        }

        public function admin_update_tag($admin) {
            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!Visitor::current()->group->can("edit_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to edit tags.", "tags"));


            $sql = SQL::current();

            $tags = array();
            $clean = array();
            foreach($sql->select("post_attributes",
                                 "*",
                                 array("name" => "tags",
                                       "value like" => "%\n".$_POST['original'].": \"%"))->fetchAll() as $tag) {
                $tags = YAML::load($tag["value"]);
                unset($tags[$_POST['original']]);

                $tags[$_POST['name']] = sanitize($_POST['name']);

                $sql->update("post_attributes",
                             array("name" => "tags",
                                   "post_id" => $tag["post_id"]),
                             array("value" => YAML::dump($tags)));
            }

            Flash::notice(__("Tag renamed.", "tags"), "/admin/?action=manage_tags");
        }

        public function admin_delete_tag($admin) {
            $sql = SQL::current();

            foreach($sql->select("post_attributes",
                                 "*",
                                 array("name" => "tags",
                                       "value like" => self::yaml_match($_GET['clean'])))->fetchAll() as $tag)  {
                $tags = YAML::load($tag["value"]);
                unset($tags[$_GET['name']]);

                if (empty($tags))
                    $sql->delete("post_attributes", array("name" => "tags", "post_id" => $tag["post_id"]));
                else
                    $sql->update("post_attributes",
                                 array("name" => "tags",
                                       "post_id" => $tag["post_id"]),
                                 array("value" => YAML::dump($tags)));
            }

            Flash::notice(__("Tag deleted.", "tags"), "/admin/?action=manage_tags");
        }

        public function admin_bulk_tag($admin) {
            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['name']) or empty($_POST['post']))
                redirect("/admin/?action=manage_tags");

            $sql = SQL::current();

            foreach (array_map("trim", explode(",", $_POST['name'])) as $tag)
                foreach ($_POST['post'] as $post_id) {
                    $post = new Post($post_id);
                    if (!$post->editable())
                        continue;

                    $tags = $sql->select("post_attributes",
                                         "value",
                                         array("name" => "tags",
                                               "post_id" => $post_id));
                    if ($tags and $value = $tags->fetchColumn())
                        $tags = YAML::load($value);
                    else
                        $tags = array();

                    $tags[$tag] = sanitize($tag);

                    $sql->replace("post_attributes",
                                  array("post_id", "name"),
                                  array("name" => "tags",
                                        "value" => YAML::dump($tags),
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
                $likes[] = self::yaml_match($name);

            $attributes = $sql->select("post_attributes",
                                       array("value", "post_id"),
                                       array("name" => "tags",
                                             "value like all" => $likes));

            $ids = array();
            foreach ($attributes->fetchAll() as $index => $row) {
                foreach ($tags as &$tag) {
                    $search = array_search($tag, YAML::load($row["value"]));
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

            $main->display(array("pages/tag", "pages/index"),
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
                    $post_tags = YAML::load($tag["value"]);

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
                    $context[] = array("size" => (100 + (($count - $min_qty) * $step)),
                                       "popularity" => $count,
                                       "name" => $tag,
                                       "title" => sprintf(_p("%s post tagged with &quot;%s&quot;", "%s posts tagged with &quot;%s&quot;", $count, "tags"), $count, $tag),
                                       "clean" => $tags[$tag],
                                       "url" => url("tag/".$tags[$tag], $main));

                $main->display("pages/tags", array("tag_cloud" => $context), __("Tags", "tags"));
            }
        }

        public function import_wordpress_post($item, $post) {
            if (!isset($item->category)) return;

            $tags = array();
            foreach ($item->category as $tag)
                if (isset($tag->attributes()->domain) and $tag->attributes()->domain == "tag" and !empty($tag) and isset($tag->attributes()->nicename))
                    $tags[strip_tags(trim($tag))] = sanitize(strip_tags(trim($tag)));

            if (!empty($tags))
                SQL::current()->insert("post_attributes",
                                       array("name" => "tags",
                                             "value" => YAML::dump($tags),
                                             "post_id" => $post->id));
        }

        public function import_movabletype_post($array, $post, $link) {
            $get_pointers = mysql_query("SELECT * FROM mt_objecttag WHERE objecttag_object_id = {$array["entry_id"]} ORDER BY objecttag_object_id ASC", $link) or error(__("Database Error"), mysql_error());
            if (!mysql_num_rows($get_pointers))
                return;

            $tags = array();
            while ($pointer = mysql_fetch_array($get_pointers)) {
                $get_dirty_tag = mysql_query("SELECT tag_name, tag_n8d_id FROM mt_tag WHERE tag_id = {$pointer["objecttag_tag_id"]}", $link) or error(__("Database Error"), mysql_error());
                if (!mysql_num_rows($get_dirty_tag)) continue;

                $dirty_tag = mysql_fetch_array($get_dirty_tag);
                $dirty = $dirty_tag["tag_name"];

                $clean_tag = mysql_query("SELECT tag_name FROM mt_tag WHERE tag_id = {$dirty_tag["tag_n8d_id"]}", $link) or error(__("Database Error"), mysql_error());
                if (mysql_num_rows($clean_tag))
                    $clean = mysql_result($clean_tag, 0);
                else
                    $clean = $dirty;

                $tags[$dirty] = $clean;
            }

            if (empty($tags))
                return;

            SQL::current()->insert("post_attributes", array("name" => "tags", "value" => YAML::dump($tags), "post_id" => $post->id));
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
                $linked[] = '<a href="'.url("tag/".urlencode($clean), MainController::current()).'" rel="tag">'.$tag.'</a>';

            return $linked;
        }

        public function post_list_related_attr($attr, $post) {
            if (Route::current()->action != "view") return;

            $posts = array();
            foreach ($post->tags as $key => $tag) {
                $posts = SQL::current()->query("SELECT DISTINCT __posts.id
                          FROM __posts
                          LEFT JOIN __post_attributes ON __posts.id = __post_attributes.post_id
                            AND __post_attributes.name = 'tags'
                            AND __posts.id != $post->id
                          WHERE __post_attributes.value LIKE '%$key: \"$tag\"%'
                          GROUP BY __posts.id
                          ORDER BY __posts.created_at DESC
                          LIMIT 5")->fetchAll();
            }

            $output = '<ul class="related_posts">
                       <h3>Related Posts:</h3>';
            foreach ($posts as $p) {
                $post = new Post($p['id']);
                $output.= '<li><h5><a href="'.$post->url().'">'.$post->title().'</a></h5></li>';
            }
            $output.= "</ul>";

            return $output;
        }

        public function post($post) {
            $tags = !empty($post->tags) ? YAML::load($post->tags) : array() ;
            ksort($tags, SORT_STRING);
            $post->tags = $tags;
            $post->linked_tags = self::linked_tags($post->tags);
        }

        public function sort_tags_name_asc($a, $b) {
            return $this->mb_strcasecmp($a["name"], $b["name"], "UTF-8");
        }

        public function sort_tags_name_desc($a, $b) {
            return $this->mb_strcasecmp($b["name"], $a["name"], "UTF-8");
        }

        public function sort_tags_popularity_asc($a, $b) {
            return $a["popularity"] > $b["popularity"];
        }

        public function sort_tags_popularity_desc($a, $b) {
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
                $post_tags = YAML::load($attr->value);

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

        public function yaml_match($name) {
            $dumped = YAML::dump(array($name));
            $quotes = preg_match("/- \"".preg_quote($name, "/")."\"/", $dumped);

            return ($quotes ? "%: \"".$name."\"\n%" : "%: ".$name."\n%");
        }

        public function tagsJS() {
            $config = Config::current();
?>//<script>
            $(function(){
                function scanTags(){
                    $(".tags_select a").each(function(){
                        regexp = new RegExp("(, ?|^)"+ $(this).text() +"(, ?|$)", "g")
                        if ($("#tags").val().match(regexp))
                            $(this).addClass("tag_added")
                        else
                            $(this).removeClass("tag_added")
                    })
                }

                scanTags()

                $("#tags").live("keyup", scanTags)

                $(".tag_cloud > span").live("mouseover", function(){
                    $(this).find(".controls").css("opacity", 1)
                }).live("mouseout", function(){
                    $(this).find(".controls").css("opacity", 0)
                })

                $(".tag_cloud span a").draggable({
                    zIndex: 100,
                    revert: true
                });

                $(".post_tags li:not(.toggler)").droppable({
                    accept: ".tag_cloud span a",
                    tolerance: "pointer",
                    activeClass: "active",
                    hoverClass: "hover",
                    drop: function(ev, ui) {
                        var post_id = $(this).attr("id").replace(/post-/, "");
                        var self = this;

                        $.ajax({
                            type: "post",
                            dataType: "json",
                            url: "<?php echo $config->chyrp_url; ?>/includes/ajax.php",
                            data: {
                                action: "tag_post",
                                post: post_id,
                                name: $(ui.draggable).text()
                            },
                            beforeSend: function(){
                                $(self).loader()
                            },
                            success: function(json){
                                $(self).loader(true)
                                $(document.createElement("a")).attr("href", json.url).addClass("tag dropped").text(json.tag).insertBefore($(self).find(".edit_tag"))
                            }
                        });
                    }
                });
            })

            function add_tag(name) {
                if ($("#tags").val().match("(, |^)"+ name +"(, |$)")) {
                    regexp = new RegExp("(, |^)"+ name +"(, |$)", "g")
                    $("#tags").val($("#tags").val().replace(regexp, function(match, before, after){
                        if (before == ", " && after == ", ")
                            return ", "
                        else
                            return ""
                    }))

                    $(".tags_select a").each(function(){
                        if ($(this).text() == name)
                            $(this).removeClass("tag_added")
                    })
                } else {
                    if ($("#tags").val() == "")
                        $("#tags").val(name)
                    else
                        $("#tags").val($("#tags").val().replace(/(, ?)?$/, ", "+ name))

                    $(".tags_select a").each(function(){
                        if ($(this).text() == name)
                            $(this).addClass("tag_added")
                    })
                }
            }
<?php
        }

        public function ajax_tag_post() {
            if (empty($_POST['name']) or empty($_POST['post']))
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
                $tags = YAML::load($value);
            else
                $tags = array();

            $tags[$tag] = sanitize($tag);

            $sql->replace("post_attributes",
                          array("post_id", "name"),
                          array("name" => "tags",
                                "value" => YAML::dump($tags),
                                "post_id" => $post->id));

            exit("{ \"url\": \"".url("tag/".$tags[$tag], MainController::current())."\", \"tag\": \"".$_POST['name']."\" }");
        }

        function feed_item($post) {
            $config = Config::current();

            foreach ($post->tags as $tag => $clean)
                echo "        <category scheme=\"".$config->url."/tag/\" term=\"".$clean."\" label=\"".fix($tag)."\" />\n";
        }
    }
