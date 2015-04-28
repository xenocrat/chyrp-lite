<?php
    /**
     * Function: tags_yaml_to_json
     * Replaces YAML serializer with JSON.
     *
     * Versions: 2.1 => 2.2
     */
    function tags_yaml_to_json() {
        $sql = SQL::current();
        if (!$tags = $sql->select("post_attributes", array("post_id", "value"), array("name" => "tags")))
            return;

        $sql->error = "";
        $json_error = 0;
        $yaml_count = 0;
        $conversion = false;

        foreach ($tags->fetchAll() as $attr) {
            if (!json_decode($attr["value"])) {
              $yaml_count++;

              $serialized = json_encode(YAML::load($attr["value"]), JSON_UNESCAPED_SLASHES);

              if (!$serialized)
                $json_error++;

              $sql->replace("post_attributes",
                            array("post_id", "name"),
                            array("post_id" => $attr["post_id"],
                                  "name" => "tags",
                                  "value" => $serialized));
            }
        }

        if (empty($sql->error) and empty($json_error))
          $conversion = true;

        if ($yaml_count > 0)
          echo __("Updating tag serialization...", "tags").
            test($conversion);
    }

    tags_yaml_to_json();
