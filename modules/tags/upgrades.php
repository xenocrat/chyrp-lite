<?php
    function update_tags_structure() {
        if (SQL::current()->query("SELECT tags FROM __tags"))
            return;

        $tags = array();
        $get_tags = SQL::current()->query("SELECT * FROM __tags");
        if (!$get_tags) return;

        while ($tag = $get_tags->fetchObject()) {
            if (!isset($tags[$tag->post_id]))
                $tags[$tag->post_id] = array("normal" => array(), "clean" => array());

            $tags[$tag->post_id]["normal"][] = "{{".$tag->name."}}";
            $tags[$tag->post_id]["clean"][] = "{{".$tag->clean."}}";
        }

        # Drop the old table.
        $delete_tags = SQL::current()->query("DROP TABLE __tags");
        echo __("Dropping old tags table...", "tags").test($delete_tags);
        if (!$delete_tags) return;

        # Create the new table.
        $tags_table = SQL::current()->query("CREATE TABLE IF NOT EXISTS __tags (
                                                 id INTEGER PRIMARY KEY AUTO_INCREMENT,
                                                 tags VARCHAR(250) DEFAULT '',
                                                 clean VARCHAR(250) DEFAULT '',
                                                 post_id INTEGER DEFAULT '0'
                                             ) DEFAULT CHARSET=utf8");
        echo __("Creating new tags table...", "tags").test($tags_table);
        if (!$tags_table) return;

        foreach ($tags as $post => $tag)
            echo _f("Inserting tags for post #%s...", array($post), "tags").
                 test(SQL::current()->insert("tags",
                                             array("tags" => implode(",", $tag["normal"]),
                                                   "clean" => implode(",", $tag["clean"]),
                                                   "post_id" => $post)));
    }

    function move_to_post_attributes() {
        $sql = SQL::current();
        if (!$tags = $sql->select("tags"))
            return;

        foreach ($tags->fetchAll() as $tag) {
            echo _f("Relocating tags for post #%d...", array($tag["post_id"]), "tags");
            $dirty = $sql->replace("post_attributes",
                                   array("post_id", "name"),
                                   array("name" => "unclean_tags",
                                         "value" => $tag["tags"],
                                         "post_id" => $tag["post_id"]));
            $clean = $sql->replace("post_attributes",
                                   array("post_id", "name"),
                                   array("name" => "clean_tags",
                                         "value" => $tag["clean"],
                                         "post_id" => $tag["post_id"]));
            echo test($dirty and $clean);

            if (!$dirty or !$clean)
                return;
        }

        echo __("Removing `tags` table...", "tags").
             test($sql->query("DROP TABLE __tags"));
    }

    function move_to_yaml() {
        $sql = SQL::current();
        if (!$attrs = $sql->select("post_attributes", "*", array("name" => array("unclean_tags", "clean_tags"))))
            return;

        function parseTags($tags, $clean) {
            $tags = explode(",", preg_replace("/\{\{([^\}]+)\}\}/", "\\1", $tags));
            $clean = explode(",", preg_replace("/\{\{([^\}]+)\}\}/", "\\1", $clean));
            return array_combine($tags, $clean);
        }

        $tags = array();
        foreach ($attrs->fetchAll() as $attr)
            if ($attr["name"] == "unclean_tags")
                $tags[$attr["post_id"]]["unclean"] = $attr["value"];
            else
                $tags[$attr["post_id"]]["clean"] = $attr["value"];

        if (empty($tags))
            return;
        
        foreach ($tags as $post_id => $tags) {
            $yaml = YAML::dump(parseTags($tags["unclean"], $tags["clean"]));

            echo _f("Relocating tags for post #%d...", array($post_id), "tags");

            echo test($insert = $sql->replace("post_attributes",
                                              array("post_id", "name"),
                                              array("name" => "tags",
                                                    "value" => $yaml,
                                                    "post_id" => $post_id)),
                      _f("Backup written to %s.", array("./_tags.bak.txt")));

            if (!$insert)
                return file_put_contents("./_tags.bak.txt", var_export($tags, true));
        }

        echo __("Removing old post attributes...", "tags").
             test($sql->delete("post_attributes", array("name" => array("unclean_tags", "clean_tags"))));
    }

    # This task just updates all of the attributes in the database
    # so they are in sync with the current YAML lib's quoting convention.
    function fix_quotes() {
        $sql = SQL::current();
        if (!$tags = $sql->select("post_attributes", array("post_id", "value"), array("name" => "tags")))
            return;

        foreach ($tags->fetchAll() as $attr)
            $sql->replace("post_attributes",
                          array("post_id", "name"),
                          array("post_id" => $attr["post_id"],
                                "name" => "tags",
                                "value" => YAML::dump(YAML::load($attr["value"]))));
    }

    update_tags_structure();
    move_to_post_attributes();
    move_to_yaml();
    fix_quotes();
