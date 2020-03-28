<?php
    class Text extends Feathers implements Feather {
        public function __init() {
            $this->setField(array("attr" => "title",
                                  "type" => "text",
                                  "label" => __("Title", "text"),
                                  "optional" => true));

            $this->setField(array("attr" => "body",
                                  "type" => "text_block",
                                  "label" => __("Body", "text"),
                                  "preview" => true));

            $this->setFilter("title", array("markup_post_title", "markup_title"));
            $this->setFilter("body", array("markup_post_text", "markup_text"));

            $this->respondTo("metaWeblog_getPost", "metaWeblog_getValues");
            $this->respondTo("metaWeblog_before_editPost", "metaWeblog_setValues");
        }

        public function submit() {
            if (empty($_POST['body']))
                error(__("Error"), __("Body can't be blank."), null, 422);

            fallback($_POST['title'], "");
            fallback($_POST['slug'], $_POST['title']);
            fallback($_POST['status'], "public");
            fallback($_POST['created_at'], datetime());
            fallback($_POST['option'], array());

            return Post::add(array("title" => $_POST['title'],
                                   "body" => $_POST['body']),
                             sanitize($_POST['slug']),
                             "",
                             "text",
                             null,
                             !empty($_POST['pinned']),
                             $_POST['status'],
                             datetime($_POST['created_at']),
                             null,
                             true,
                             $_POST['option']);
        }

        public function update($post) {
            if (empty($_POST['body']))
                error(__("Error"), __("Body can't be blank.", "text"), null, 422);

            fallback($_POST['title'], "");
            fallback($_POST['slug'], $post->clean);
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());

            return $post->update(array("title" => $_POST['title'],
                                       "body" => $_POST['body']),
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
            return oneof($post->title, $post->title_from_excerpt());
        }

        public function excerpt($post) {
            return $post->body;
        }

        public function feed_content($post) {
            return $post->body;
        }

        public function metaWeblog_getValues($struct, $post) {
            if ($post->feather != "text")
                return;

            $struct["title"] = $post->title;
            $struct["description"] = $post->body;

            return $struct;
        }

        public function metaWeblog_setValues($values, $struct, $post) {
            if ($post->feather != "text")
                return;

            if ($struct["description"] != "") {
                $values["title"] = $struct["title"];
                $values["body"] = $struct["description"];
            }

            return $values;
        }
    }
