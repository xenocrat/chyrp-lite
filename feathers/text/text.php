<?php
    class Text extends Feathers implements Feather {
        public function __init() {
            $this->setField(array("attr" => "title",
                                  "type" => "text",
                                  "label" => __("Title", "text"),
                                  "optional" => true));
            $this->setField(array("attr" => "body",
                                  "type" => "text_block",
                                  "label" => __("Body", "text")));

            $this->setFilter("title", array("markup_title", "markup_post_title"));
            $this->setFilter("body", array("markup_text", "markup_post_text"));
        }

        public function submit() {
            if (empty($_POST['body']))
                error(__("Error"), __("Body can't be blank."));

            fallback($_POST['slug'], sanitize($_POST['title']));

            return Post::add(array("title" => $_POST['title'],
                                   "body" => $_POST['body']),
                             $_POST['slug'],
                             Post::check_url($_POST['slug']));
        }

        public function update($post) {
            if (empty($_POST['body']))
                error(__("Error"), __("Body can't be blank."));

            $post->update(array("title" => $_POST['title'],
                                "body" => $_POST['body']));
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
    }
