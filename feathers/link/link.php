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
                                  "optional" => true));

            $this->setFilter("name", array("markup_title", "markup_post_title"));
            $this->setFilter("description", array("markup_text", "markup_post_text"));

            $this->respondTo("feed_url", "set_feed_url");
        }

        public function submit() {
            if (empty($_POST['source']))
                error(__("Error"), __("URL can't be empty."));

            if (!@parse_url($_POST['source'], PHP_URL_SCHEME))
                $_POST['source'] = "http://".$_POST['source'];

            fallback($_POST['slug'], sanitize($_POST['name']));

            return Post::add(array("name" => $_POST['name'],
                                   "source" => $_POST['source'],
                                   "description" => $_POST['description']),
                             $_POST['slug'],
                             Post::check_url($_POST['slug']));
        }

        public function update($post) {
            if (empty($_POST['source']))
                error(__("Error"), __("URL can't be empty."));

            if (!@parse_url($_POST['source'], PHP_URL_SCHEME))
                $_POST['source'] = "http://".$_POST['source'];

            $post->update(array("name" => $_POST['name'],
                                "source" => $_POST['source'],
                                "description" => $_POST['description']));
        }

        public function title($post) {
            $return = $post->name;
            fallback($return, $post->title_from_excerpt());
            fallback($return, $post->source);
            return $return;
        }

        public function excerpt($post) {
            return $post->description;
        }

        public function feed_content($post) {
            return $post->description;
        }

        public function set_feed_url($url, $post) {
            if ($post->feather != "link") return;
            return $url = $post->source;
        }
    }
