<?php
    /**
     * Function: yaml_to_serialize
     * Replaces YAML serializer with PHP's faster serialize() function.
     *
     * Versions: 2.1 => 2.2
     */
    function yaml_to_serialize() {
        $sql = SQL::current();
        if (!$tags = $sql->select("post_attributes", array("post_id", "value"), array("name" => "tags")))
            return;

        $sql->error = "";
        $yaml = 0;

        foreach ($tags->fetchAll() as $attr) {
            if (!unserialize($attr["value"])) {
              $yaml++;
              $sql->replace("post_attributes",
                            array("post_id", "name"),
                            array("post_id" => $attr["post_id"],
                                  "name" => "tags",
                                  "value" => serialize(YAML::load($attr["value"]))));
            }
        }

        if ($yaml > 0)
          echo __("Updating tag serialization...", "tags").
            test(empty($sql->error));
    }

    yaml_to_serialize();
