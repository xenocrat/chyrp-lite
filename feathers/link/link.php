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
            $this->respondTo("metaWeblog_editValues", "metaWeblog_setValues");
        }

        public function submit() {
            if (empty($_POST['source']))
                error(__("Error"), __("URL can't be empty.", "link"), null, 422);

            if (!is_url($_POST['source']))
                error(__("Error"), __("Invalid URL.", "link"));

            fallback($_POST['name'], "");
            fallback($_POST['description'], "");
            fallback($_POST['slug'], $_POST['name']);

            $_POST['source'] = add_scheme($_POST['source']);

            return Post::add(array("name" => $_POST['name'],
                                   "source" => $_POST['source'],
                                   "description" => $_POST['description']));
        }

        public function update($post) {
            if (empty($_POST['source']))
                error(__("Error"), __("URL can't be empty.", "link"), null, 422);

            if (!is_url($_POST['source']))
                error(__("Error"), __("Invalid URL.", "link"));

            fallback($_POST['name'], "");
            fallback($_POST['description'], "");

            $_POST['source'] = add_scheme($_POST['source']);

            return $post->update(array("name" => $_POST['name'],
                                       "source" => $_POST['source'],
                                       "description" => $_POST['description']));
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
