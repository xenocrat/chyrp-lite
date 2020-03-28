<?php
    class Link extends Feathers implements Feather {
        public function __init() {
            $this->setField(array("attr" => "name",
                                  "type" => "text",
                                  "label" => __("Title", "link"),
                                  "optional" => true));

            $this->setField(array("attr" => "source",
                                  "type" => "text",
                                  "label" => __("URL", "link")));

            $this->setField(array("attr" => "description",
                                  "type" => "text_block",
                                  "label" => __("Description", "link"),
                                  "optional" => true,
                                  "preview" => true));

            $this->setFilter("name", array("markup_post_title", "markup_title"));
            $this->setFilter("description", array("markup_post_text", "markup_text"));

            $this->respondTo("feed_item", "link_related");
            $this->respondTo("metaWeblog_getPost", "metaWeblog_getValues");
            $this->respondTo("metaWeblog_before_editPost", "metaWeblog_setValues");
        }

        public function submit() {
            if (empty($_POST['source']))
                error(__("Error"), __("URL can't be empty.", "link"), null, 422);

            if (!is_url($_POST['source']))
                error(__("Error"), __("Invalid URL.", "link"));

            fallback($_POST['name'], "");
            fallback($_POST['description'], "");
            fallback($_POST['slug'], $_POST['name']);
            fallback($_POST['status'], "public");
            fallback($_POST['created_at'], datetime());
            fallback($_POST['option'], array());

            $_POST['source'] = add_scheme($_POST['source']);

            return Post::add(array("name" => $_POST['name'],
                                   "source" => $_POST['source'],
                                   "description" => $_POST['description']),
                             sanitize($_POST['slug']),
                             "",
                             "link",
                             null,
                             !empty($_POST['pinned']),
                             $_POST['status'],
                             datetime($_POST['created_at']),
                             null,
                             true,
                             $_POST['option']);
        }

        public function update($post) {
            if (empty($_POST['source']))
                error(__("Error"), __("URL can't be empty.", "link"), null, 422);

            if (!is_url($_POST['source']))
                error(__("Error"), __("Invalid URL.", "link"));

            fallback($_POST['name'], "");
            fallback($_POST['description'], "");
            fallback($_POST['slug'], $post->clean);
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());

            $_POST['source'] = add_scheme($_POST['source']);

            return $post->update(array("name" => $_POST['name'],
                                       "source" => $_POST['source'],
                                       "description" => $_POST['description']),
                                 null,
                                 !empty($_POST['pinned']),
                                 $_POST['status'],
                                 sanitize($_POST['slug']),
                                 "",
                                 datetime($_POST['created_at']),
                                 null,
                                 $_POST['option']);
        }

        public function title($post) {
            return oneof($post->name, $post->title_from_excerpt(), $post->source);
        }

        public function excerpt($post) {
            return $post->description;
        }

        public function feed_content($post) {
            return $post->description;
        }

        public function link_related($post, $feed) {
            if ($post->feather != "link")
                return;

            $feed->related($post->source);
        }

        public function metaWeblog_getValues($struct, $post) {
            if ($post->feather != "link")
                return;

            $struct["title"] = $post->name;
            $struct["description"] = $post->description;

            return $struct;
        }

        public function metaWeblog_setValues($values, $struct, $post) {
            if ($post->feather != "link")
                return;

            $values["name"] = $struct["title"];
            $values["description"] = $struct["description"];

            return $values;
        }
    }
