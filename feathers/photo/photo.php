<?php
    class Photo extends Feathers implements Feather {
        public function __init() {
            $maximum = Config::current()->uploads_limit;

            $this->setField(array("attr" => "title",
                                  "type" => "text",
                                  "label" => __("Title", "photo"),
                                  "optional" => true));

            $this->setField(array("attr" => "photo",
                                  "type" => "file",
                                  "label" => __("Photo", "photo"),
                                  "multiple" => false,
                                  "accept" => ".".implode(",.", $this->photo_extensions()),
                                  "note" => _f("(Max. file size: %d Megabytes)", $maximum, "photo")));

            $this->setField(array("attr" => "caption",
                                  "type" => "text_block",
                                  "label" => __("Caption", "photo"),
                                  "optional" => true,
                                  "preview" => true));

            $this->setFilter("title", array("markup_post_title", "markup_title"));
            $this->setFilter("caption", array("markup_post_text", "markup_text"));

            $this->respondTo("delete_post", "delete_file");
            $this->respondTo("post_options", "add_option");
            $this->respondTo("metaWeblog_getPost", "metaWeblog_getValues");
            $this->respondTo("metaWeblog_before_editPost", "metaWeblog_setValues");
        }

        public function submit() {
            if (isset($_FILES['photo']) and upload_tester($_FILES['photo']))
                $filename = upload($_FILES['photo'], $this->photo_extensions());

            if (!isset($filename))
                error(__("Error"), __("You did not select a photo to upload.", "photo"), null, 422);

            if (!empty($_POST['option']['source']) and is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            fallback($_POST['title'], "");
            fallback($_POST['caption'], "");
            fallback($_POST['slug'], $_POST['title']);
            fallback($_POST['status'], "public");
            fallback($_POST['created_at'], datetime());
            fallback($_POST['option'], array());

            return Post::add(array("title" => $_POST['title'],
                                   "filename" => $filename,
                                   "caption" => $_POST['caption']),
                             sanitize($_POST['slug']),
                             "",
                             "photo",
                             null,
                             !empty($_POST['pinned']),
                             $_POST['status'],
                             datetime($_POST['created_at']),
                             null,
                             true,
                             $_POST['option']);
        }

        public function update($post) {
            if (isset($_FILES['photo']) and upload_tester($_FILES['photo'])) {
                $filename = upload($_FILES['photo'], $this->photo_extensions());
                $this->delete_file($post);
            } else {
                $filename = $post->filename;
            }

            if (!empty($_POST['option']['source']) and is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            fallback($_POST['title'], "");
            fallback($_POST['caption'], "");
            fallback($_POST['slug'], $post->clean);
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());

            return $post->update(array("title" => $_POST['title'],
                                       "filename" => $filename,
                                       "caption" => $_POST['caption']),
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
            return $post->caption;
        }

        public function feed_content($post) {
            $content = '<img src="'.Config::current()->chyrp_url.
                       "/includes/thumbnail.php?file=".urlencode($post->filename).
                       '" alt="'.fix($post->alt_text, true).'">';

            if (!empty($post->caption))
                $content.= '<figcaption>'.$post->caption.'</figcaption>';

            return '<figure>'.$content.'</figure>';
        }

        public function delete_file($post) {
            if ($post->feather != "photo")
                return;

            $trigger = Trigger::current();
            $filepath = uploaded($post->filename, false);

            if (file_exists($filepath)) {
                $trigger->call("delete_upload", $post->filename);
                unlink($filepath);
            }
        }

        public function add_option($options, $post = null, $feather = null) {
            if ($feather != "photo")
                return;

            $options[] = array("attr" => "option[alt_text]",
                               "label" => __("Alternative Text", "photo"),
                               "help" => "photo_alt_text",
                               "type" => "text",
                               "value" => (isset($post) ? $post->alt_text : ""));

            $options[] = array("attr" => "option[source]",
                               "label" => __("Source", "photo"),
                               "help" => "photo_source",
                               "type" => "text",
                               "value" => (isset($post) ? $post->source : ""));

            return $options;
        }

        public function metaWeblog_getValues($struct, $post) {
            if ($post->feather != "photo")
                return;

            $struct["title"] = $post->title;
            $struct["description"] = $post->caption;

            return $struct;
        }

        public function metaWeblog_setValues($values, $struct, $post) {
            if ($post->feather != "photo")
                return;

            $values["title"] = $struct["title"];
            $values["caption"] = $struct["description"];

            return $values;
        }

        private function photo_extensions() {
            return array("jpg", "jpeg", "png", "gif", "webp", "avif");
        }
    }
