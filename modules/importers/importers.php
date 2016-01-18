<?php
    class Importers extends Modules {
        /**
         * Function: route_import_wordpress
         * WordPress importing.
         */
        public function route_import_wordpress() {
            $config = Config::current();

            if (!Visitor::current()->group->can("add_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to import content.", "importers"));

            if (empty($_POST))
                redirect("/admin/?action=import");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!in_array("text", $config->enabled_feathers))
                error(__("Missing Feather", "importers"), __("Importing from WordPress requires the Text feather to be installed and enabled.", "importers"));

            if (empty($_FILES['xml_file']) or !upload_tester($_FILES['xml_file']))
                error(__("Error"), __("You must select a WordPress export file.", "importers"));

            if (ini_get("memory_limit") < 20)
                ini_set("memory_limit", "20M");

            $trigger = Trigger::current();

            $stupid_xml = file_get_contents($_FILES['xml_file']['tmp_name']);
            $sane_xml = preg_replace(array("/<wp:comment_content>/", "/<\/wp:comment_content>/"),
                                     array("<wp:comment_content><![CDATA[", "]]></wp:comment_content>"),
                                     $stupid_xml);

            $sane_xml = str_replace(array("<![CDATA[<![CDATA[", "]]>]]>"),
                                    array("<![CDATA[", "]]>"),
                                    $sane_xml);

            $sane_xml = str_replace(array("xmlns:excerpt=\"http://wordpress.org/excerpt/1.0/\"",
                                          "xmlns:excerpt=\"http://wordpress.org/export/1.1/excerpt/\""),
                                    "xmlns:excerpt=\"http://wordpress.org/export/1.2/excerpt/\"",
                                    $sane_xml);
            $sane_xml = str_replace(array("xmlns:wp=\"http://wordpress.org/export/1.0/\"",
                                          "xmlns:wp=\"http://wordpress.org/export/1.1/\""),
                                    "xmlns:wp=\"http://wordpress.org/export/1.2/\"",
                                    $sane_xml);

            if (!substr_count($sane_xml, "xmlns:excerpt"))
                $sane_xml = preg_replace("/xmlns:content=\"([^\"]+)\"(\s+)/m",
                                         "xmlns:content=\"\\1\"\\2xmlns:excerpt=\"http://wordpress.org/export/1.2/excerpt/\"\\2",
                                         $sane_xml);

            $fix_amps_count = 1;
            while ($fix_amps_count)
                $sane_xml = preg_replace("/<wp:meta_value>(.+)&(?!amp;)(.+)<\/wp:meta_value>/m",
                                         "<wp:meta_value>\\1&amp;\\2</wp:meta_value>",
                                         $sane_xml, -1, $fix_amps_count);

            # Remove null (x00) characters
            $sane_xml = str_replace("", "", $sane_xml);

            $xml = simplexml_load_string($sane_xml, "SimpleXMLElement", LIBXML_NOCDATA);

            if (!$xml or !(substr_count($xml->channel->generator, "wordpress.org") or
                           substr_count($xml->channel->generator, "wordpress.com"))) {
                Flash::warning(__("The file does not seem to be a valid WordPress export file.", "importers"), "/admin/?action=import");
                return;
            }

            foreach ($xml->channel->item as $item) {
                $wordpress = $item->children("http://wordpress.org/export/1.2/");
                $content   = $item->children("http://purl.org/rss/1.0/modules/content/");
                $contentencoded = $content->encoded;
                if ($wordpress->post_type == "attachment" or $wordpress->status == "attachment" or $item->title == "zz_placeholder")
                    continue;

                $media = array();

                $regexp_url = preg_quote($_POST['media_url'], "/");
                if (!empty($_POST['media_url']) and
                    preg_match_all("/{$regexp_url}([^\.\!,\?;\"\'<>\(\)\[\]\{\}\s\t ]+)\.([a-zA-Z0-9]+)/",
                                   $contentencoded,
                                   $media)) {
                    $media_uris = array_unique($media[0]);
                    foreach ($media_uris as $matched_url) {
                        $filename = upload_from_url($matched_url);
                        $contentencoded = str_replace($matched_url, uploaded($filename), $contentencoded);
                    }
                }
                
                $clean = (isset($wordpress->post_name) && $wordpress->post_name != '') ? $wordpress->post_name : sanitize($item->title) ;

                $pinned = (isset($wordpress->is_sticky)) ? $wordpress->is_sticky : 0 ;

                if (empty($wordpress->post_type) or $wordpress->post_type == "post") {
                    $status_translate = array("publish" => "public",
                                              "draft"   => "draft",
                                              "private" => "private",
                                              "static"  => "public",
                                              "object"  => "public",
                                              "inherit" => "public",
                                              "future"  => "draft",
                                              "pending" => "draft");

                    $data = array("content" => array("title" => trim($item->title),
                                                     "body" => trim($contentencoded),
                                                     "imported_from" => "wordpress"),
                                  "feather" => "text");
                    
                    $wp_post_format = null;
                    if (isset($item->category)) {
                        foreach ($item->category as $category) {
                            if (!empty($category) and
                                isset($category->attributes()->domain) and
                                (substr_count($category->attributes()->domain, "post_format") > 0) and
                                isset($category->attributes()->nicename)
                            ) {
                                $wp_post_format = (string) $category->attributes()->nicename;
                                break;
                            }
                        }
                    }
                    
                    if ($wp_post_format) {
                        $trigger->filter($data,
                                         "import_wordpress_post_".str_replace('post-format-', '', $wp_post_format),
                                         $item);
                    }
                    
                    $post = Post::add($data["content"],
                                      $clean,
                                      Post::check_url($clean),
                                      $data["feather"],
                                      null,
                                      $pinned,
                                      $status_translate[(string) $wordpress->status],
                                      (string) ($wordpress->post_date == "0000-00-00 00:00:00" ? datetime() : $wordpress->post_date),
                                      null,
                                      "",
                                      false);

                    $trigger->call("import_wordpress_post", $item, $post);

                } elseif ($wordpress->post_type == "page") {
                    $page = Page::add(trim($item->title),
                                      trim($content->encoded),
                                      null,
                                      0,
                                      true,
                                      0,
                                      $clean,
                                      Page::check_url($clean),
                                      (string) ($wordpress->post_date == "0000-00-00 00:00:00" ? datetime() : $wordpress->post_date));

                    $trigger->call("import_wordpress_page", $item, $page);
                }
            }

            Flash::notice(__("WordPress content successfully imported!", "importers"), "/admin/?action=import");
        }

        /**
         * Function: route_import_tumblr
         * Tumblr importing.
         */
        public function route_import_tumblr() {
            $config = Config::current();

            if (!Visitor::current()->group->can("add_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to import content.", "importers"));

            if (empty($_POST))
                redirect("/admin/?action=import");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (!in_array("text", $config->enabled_feathers) or
                !in_array("video", $config->enabled_feathers) or
                !in_array("photo", $config->enabled_feathers) or
                !in_array("quote", $config->enabled_feathers) or
                !in_array("link", $config->enabled_feathers))
                error(__("Missing Feather", "importers"), __("Importing from Tumblr requires the Text, Video, Photo, Quote, and Link feathers to be installed and enabled.", "importers"));

            if (empty($_POST['tumblr_url']) or !is_url($_POST['tumblr_url']))
                error(__("Error"), __("Invalid Tumblr URL.", "importers"));

            $_POST['tumblr_url'] = add_scheme($_POST['tumblr_url']);

            if (ini_get("memory_limit") < 20)
                ini_set("memory_limit", "20M");

            set_time_limit(3600);
            $url = rtrim($_POST['tumblr_url'], "/")."/api/read?num=50";
            $api = preg_replace("/<(\/?)([a-z]+)\-([a-z]+)/", "<\\1\\2_\\3", get_remote($url));
            $api = preg_replace("/ ([a-z]+)\-([a-z]+)=/", " \\1_\\2=", $api);
            $xml = simplexml_load_string($api);

            if (!isset($xml->tumblelog)) {
                Flash::warning(__("Could not retrieve content from the Tumblr URL. ", "importers"), "/admin/?action=import");
                return;
            }

            $already_in = $posts = array();
            foreach ($xml->posts->post as $post) {
                $posts[] = $post;
                $already_in[] = $post->attributes()->id;
            }

            while ($xml->posts->attributes()->total > count($posts)) {
                set_time_limit(3600);
                $api = preg_replace("/<(\/?)([a-z]+)\-([a-z]+)/", "<\\1\\2_\\3", get_remote($url."&start=".count($posts)));
                $api = preg_replace("/ ([a-z]+)\-([a-z]+)=/", " \\1_\\2=", $api);
                $xml = simplexml_load_string($api, "SimpleXMLElement", LIBXML_NOCDATA);
                foreach ($xml->posts->post as $post)
                    if (!in_array($post->attributes()->id, $already_in)) {
                        $posts[] = $post;
                        $already_in[] = $post->attributes()->id;
                    }
            }

            function reverse($a, $b) {
                if (empty($a) or empty($b)) return 0;
                return (strtotime($a->attributes()->date) < strtotime($b->attributes()->date)) ? -1 : 1 ;
            }

            set_time_limit(3600);
            usort($posts, "reverse");

            foreach ($posts as $key => $post) {
                set_time_limit(3600);
                if ($post->attributes()->type == "audio")
                    break; # Can't import Audio posts since Tumblr has the files locked in to Amazon.

                $translate_types = array("regular" => "text", "conversation" => "text");

                $clean = "";
                switch($post->attributes()->type) {
                    case "regular":
                        $title = fallback($post->regular_title);
                        $values = array("title" => $title,
                                        "body" => $post->regular_body);
                        $clean = sanitize($title);
                        break;
                    case "video":
                        $values = array("embed" => $post->video_player,
                                        "description" => fallback($post->video_caption));
                        break;
                    case "conversation":
                        $title = fallback($post->conversation_title);

                        $lines = array();
                        foreach ($post->conversation_line as $line)
                            $lines[] = $line->attributes()->label." ".$line;

                        $values = array("title" => $title,
                                        "body" => implode("<br>", $lines));
                        $clean = sanitize($title);
                        break;
                    case "photo":
                        $values = array("filename" => upload_from_url($post->photo_url[0]),
                                        "caption" => fallback($post->photo_caption));
                        break;
                    case "quote":
                        $values = array("quote" => $post->quote_text,
                                        "source" => preg_replace("/^&mdash; /", "",
                                                                 fallback($post->quote_source)));
                        break;
                    case "link":
                        $name = fallback($post->link_text);
                        $values = array("name" => $name,
                                        "source" => $post->link_url,
                                        "description" => fallback($post->link_description));
                        $clean = sanitize($name);
                        break;
                }

                $values["imported_from"] = "tumblr";

                $new_post = Post::add($values,
                                      $clean,
                                      Post::check_url($clean),
                                      fallback($translate_types[(string) $post->attributes()->type], (string) $post->attributes()->type),
                                      null,
                                      null,
                                      "public",
                                      datetime((int) $post->attributes()->unix_timestamp),
                                      null,
                                      "",
                                      false);

                Trigger::current()->call("import_tumble", $post, $new_post);
            }

            Flash::notice(__("Tumblr content successfully imported!", "importers"), "/admin/?action=import");
        }

        /**
         * Function: route_import_textpattern
         * TextPattern importing.
         */
        public function route_import_textpattern() {
            $config  = Config::current();
            $trigger = Trigger::current();

            if (!Visitor::current()->group->can("add_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to import content.", "importers"));

            if (empty($_POST))
                redirect("/admin/?action=import");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['host']))
                error(__("Error"), __("Host cannot be empty.", "importers"));

            if (empty($_POST['username']))
                error(__("Error"), __("Username cannot be empty.", "importers"));

            if (empty($_POST['password']))
                error(__("Error"), __("Password cannot be empty.", "importers"));

            if (empty($_POST['database']))
                error(__("Error"), __("Database cannot be empty.", "importers"));

            @$mysqli = new mysqli($_POST['host'], $_POST['username'], $_POST['password'], $_POST['database']);

            if ($mysqli->connect_errno) {
                Flash::warning(__("Could not connect to the TextPattern database.", "importers"), "/admin/?action=import");
                return;
            }

            $mysqli->query("SET NAMES 'utf8'");

            $prefix = $mysqli->real_escape_string($_POST['prefix']);
            $result = $mysqli->query("SELECT * FROM {$prefix}textpattern ORDER BY ID ASC") or error(__("Database Error", "importers"), $mysqli->error);

            $posts = array();
            while ($post = $result->fetch_assoc())
                $posts[$post["ID"]] = $post;

            $mysqli->close();

            foreach ($posts as $post) {
                $regexp_url = preg_quote($_POST['media_url'], "/");
                if (!empty($_POST['media_url']) and
                    preg_match_all("/{$regexp_url}([^\.\!,\?;\"\'<>\(\)\[\]\{\}\s\t ]+)\.([a-zA-Z0-9]+)/",
                                   $post["Body"],
                                   $media))
                    foreach ($media[0] as $matched_url) {
                        $filename = upload_from_url($matched_url);
                        $post["Body"] = str_replace($matched_url, uploaded($filename), $post["Body"]);
                    }

                $status_translate = array(1 => "draft",
                                          2 => "private",
                                          3 => "draft",
                                          4 => "public",
                                          5 => "public");

                $clean = fallback($post["url_title"], sanitize($post["Title"]));

                $new_post = Post::add(array("title" => $post["Title"],
                                            "body" => $post["Body"],
                                            "imported_from" => "textpattern"),
                                      $clean,
                                      Post::check_url($clean),
                                      "text",
                                      null,
                                      ($post["Status"] == "5"),
                                      $status_translate[$post["Status"]],
                                      $post["Posted"],
                                      null,
                                      "",
                                      false);

                $trigger->call("import_textpattern_post", $post, $new_post);
            }

            Flash::notice(__("TextPattern content successfully imported!", "importers"), "/admin/?action=import");
        }

        /**
         * Function: route_import_movabletype
         * MovableType importing.
         */
        public function route_import_movabletype() {
            $config  = Config::current();
            $trigger = Trigger::current();
            $visitor = Visitor::current();

            if (!$visitor->group->can("add_post"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to import content.", "importers"));

            if (empty($_POST))
                redirect("/admin/?action=import");

            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER["REMOTE_ADDR"]))
                show_403(__("Access Denied"), __("Invalid security key."));

            if (empty($_POST['host']))
                error(__("Error"), __("Host cannot be empty.", "importers"));

            if (empty($_POST['username']))
                error(__("Error"), __("Username cannot be empty.", "importers"));

            if (empty($_POST['password']))
                error(__("Error"), __("Password cannot be empty.", "importers"));

            if (empty($_POST['database']))
                error(__("Error"), __("Database cannot be empty.", "importers"));

            @$mysqli = new mysqli($_POST['host'], $_POST['username'], $_POST['password'], $_POST['database']);

            if ($mysqli->connect_errno) {
                Flash::warning(__("Could not connect to the MovableType database.", "importers"), "/admin/?action=import");
                return;
            }

            $mysqli->query("SET NAMES 'utf8'");

            $authors = array();
            $result = $mysqli->query("SELECT * FROM mt_author ORDER BY author_id ASC") or error(__("Database Error", "importers"), $mysqli->error);

            while ($author = $result->fetch_assoc()) {
                # Try to figure out if this author is the same as the person doing the import.
                if ($author["author_name"] == $visitor->login
                    || $author["author_nickname"] == $visitor->login
                    || $author["author_nickname"] == $visitor->full_name
                    || $author["author_url"]      == $visitor->website
                    || $author["author_email"]    == $visitor->email) {
                    $users[$author["author_id"]] = $visitor;
                } else {
                    $users[$author["author_id"]] = User::add(
                        $author["author_name"],
                        $author["author_password"],
                        $author["author_email"],
                        ($author["author_nickname"] != $author["author_name"] ?
                                                       $author["author_nickname"] : ""),
                        $author["author_url"],
                        ($author["author_can_create_blog"] == "1" ? $visitor->group : null),
                        $author["author_created_on"],
                        false
                    );
                }
            }

            $result = $mysqli->query("SELECT * FROM mt_entry ORDER BY entry_id ASC") or error(__("Database Error", "importers"), $mysqli->error);

            $posts = array();
            while ($post = $result->fetch_assoc())
                $posts[$post["entry_id"]] = $post;

            $mysqli->close();

            foreach ($posts as $post) {
                $body = $post["entry_text"];

                if (!empty($post["entry_text_more"]))
                    $body.= "\n\n<!--more-->\n\n".$post["entry_text_more"];

                $regexp_url = preg_quote($_POST['media_url'], "/");
                if (!empty($_POST['media_url']) and
                    preg_match_all("/{$regexp_url}([^\.\!,\?;\"\'<>\(\)\[\]\{\}\s\t ]+)\.([a-zA-Z0-9]+)/",
                                   $body,
                                   $media))
                    foreach ($media[0] as $matched_url) {
                        $filename = upload_from_url($matched_url);
                        $body = str_replace($matched_url, uploaded($filename), $body);
                    }

                $status_translate = array(1 => "draft",
                                          2 => "public",
                                          3 => "draft",
                                          4 => "draft");

                $clean = oneof($post["entry_basename"], sanitize($post["entry_title"]));

                if (empty($post["entry_class"]) or $post["entry_class"] == "entry") {
                    $new_post = Post::add(array("title" => $post["entry_title"],
                                                "body" => $body,
                                                "imported_from" => "movabletype"),
                                          $clean,
                                          Post::check_url($clean),
                                          "text",
                                          @$users[$post["entry_author_id"]],
                                          false,
                                          $status_translate[$post["entry_status"]],
                                          oneof(@$post["entry_authored_on"], @$post["entry_created_on"], datetime()),
                                          $post["entry_modified_on"],
                                          "",
                                          false);
                    $trigger->call("import_movabletype_post", $post, $new_post, $link);
                } elseif (@$post["entry_class"] == "page") {
                    $new_page = Page::add($post["entry_title"], $body, null, 0, true, 0, $clean, Page::check_url($clean));
                    $trigger->call("import_movabletype_page", $post, $new_page, $link);
                }
            }

            Flash::notice(__("MovableType content successfully imported!", "importers"), "/admin/?action=import");
        }

        public function import_choose() {
            $config = Config::current();
?>
<hr>
<h2>WordPress</h2>
<form id="import_wordpress_form" class="split" action="<?php echo $config->chyrp_url."/?action=import_wordpress"; ?>" method="post" accept-charset="utf-8" enctype="multipart/form-data">
<fieldset>
<p>
<label for="xml_file"><?php echo __("eXtended .XML File", "importers"); ?></label>
<input type="file" name="xml_file" value="" id="xml_file">
</p>
<p>
<label for="media_url"><?php echo __("What URL is used for embedded media?", "importers"); ?></label>
<input class="text" type="text" name="media_url" value="" id="media_url">
<span class="sub"><?php echo __("(optional)", "importers"); ?></span>
<small>
<?php echo __("Usually something like <code>http://example.com/wp-content/uploads/</code>.", "importers"); ?>
</small>
</p>
<p class="buttons">
<button type="submit" class="yay"><img src="<?php echo $config->chyrp_url."/admin/images/icons/success.svg"; ?>" alt="success"><?php echo __("Import", "importers"); ?></button>
</p>
<input type="hidden" name="hash" value="<?php echo token($_SERVER["REMOTE_ADDR"]); ?>" id="hash">
</fieldset>
</form>
<hr>
<h2>Tumblr</h2>
<form id="import_tumblr_form" class="split" action="<?php echo $config->chyrp_url."/?action=import_tumblr"; ?>" method="post" accept-charset="utf-8">
<fieldset>
<p>
<label for="tumblr_url"><?php echo __("Your Tumblr URL", "importers"); ?></label>
<input class="text" type="text" name="tumblr_url" value="" id="tumblr_url">
<small><?php echo __("Note: Audio tumbles cannot be imported.", "importers"); ?></small>
</p>
<p class="buttons">
<button type="submit" class="yay"><img src="<?php echo $config->chyrp_url."/admin/images/icons/success.svg"; ?>" alt="success"><?php echo __("Import", "importers"); ?></button>
</p>
<input type="hidden" name="hash" value="<?php echo token($_SERVER["REMOTE_ADDR"]); ?>" id="hash">
</fieldset>
</form>
<hr>
<h2>TextPattern</h2>
<form id="import_textpattern_form" class="split" action="<?php echo $config->chyrp_url."/?action=import_textpattern"; ?>" method="post" accept-charset="utf-8">
<fieldset>
<p>
<label for="host"><?php echo __("Host", "importers"); ?></label>
<input class="text" type="text" name="host" value="localhost" id="host">
</p>
<p>
<label for="username"><?php echo __("Username", "importers"); ?></label>
<input class="text" type="text" name="username" value="" id="username">
</p>
<p>
<label for="password"><?php echo __("Password", "importers"); ?></label>
<input class="text" type="password" name="password" value="" id="password">
</p>
<p>
<label for="database"><?php echo __("Database", "importers"); ?></label>
<input class="text" type="text" name="database" value="" id="database">
</p>
<p>
<label for="prefix"><?php echo __("Table Prefix", "importers"); ?></label>
<input class="text" type="text" name="prefix" value="" id="prefix">
<span class="sub"><?php echo __("(if any)", "importers"); ?></span>
</p>
<p>
<label for="media_url"><?php echo __("What URL is used for embedded media?", "importers"); ?></label>
<input class="text" type="text" name="media_url" value="" id="media_url">
<span class="sub"><?php echo __("(optional)", "importers"); ?></span>
<small>
<?php echo __("Usually something like <code>http://example.com/images/</code>.", "importers"); ?>
</small>
</p>
<p class="buttons">
<button type="submit" class="yay"><img src="<?php echo $config->chyrp_url."/admin/images/icons/success.svg"; ?>" alt="success"><?php echo __("Import", "importers"); ?></button>
</p>
<input type="hidden" name="hash" value="<?php echo token($_SERVER["REMOTE_ADDR"]); ?>" id="hash">
</fieldset>
</form>
<hr>
<h2>MovableType</h2>
<form id="import_movabletype_form" class="split" action="<?php echo $config->chyrp_url."/?action=import_movabletype"; ?>" method="post" accept-charset="utf-8">
<fieldset>
<p>
<label for="host"><?php echo __("Host", "importers"); ?></label>
<input class="text" type="text" name="host" value="localhost" id="host">
</p>
<p>
<label for="username"><?php echo __("Username", "importers"); ?></label>
<input class="text" type="text" name="username" value="" id="username">
</p>
<p>
<label for="password"><?php echo __("Password", "importers"); ?></label>
<input class="text" type="password" name="password" value="" id="password">
</p>
<p>
<label for="database"><?php echo __("Database", "importers"); ?></label>
<input class="text" type="text" name="database" value="" id="database">
</p>
<p>
<label for="media_url"><?php echo __("What URL is used for embedded media?", "importers"); ?></label>
<input class="text" type="text" name="media_url" value="" id="media_url">
<span class="sub"><?php echo __("(optional)", "importers"); ?></span>
<small>
<?php echo __("Usually something like <code>http://example.com/images/</code>.", "importers"); ?>
</small>
</p>
<p class="buttons">
<button type="submit" class="yay"><img src="<?php echo $config->chyrp_url."/admin/images/icons/success.svg"; ?>" alt="success"><?php echo __("Import", "importers"); ?></button>
</p>
<input type="hidden" name="hash" value="<?php echo token($_SERVER["REMOTE_ADDR"]); ?>" id="hash">
</fieldset>
</form>
<?php
        }

    }
