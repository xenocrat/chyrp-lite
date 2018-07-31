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
        }

        public function submit() {
            if (isset($_FILES['photo']) and upload_tester($_FILES['photo']))
                $filename = upload($_FILES['photo'], array("jpg", "jpeg", "png", "gif", "tif", "tiff", "bmp"));
            else
                error(__("Error"), __("You did not select a photo to upload.", "photo"), null, 422);
                
            if (!empty($_POST['option']['source']) and is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            fallback($_POST['title'], "");
            fallback($_POST['caption'], "");
            fallback($_POST['slug'], $_POST['title']);

            return Post::add(array("title" => $_POST['title'],
                                   "filename" => $filename,
                                   "caption" => $_POST['caption']));
        }

        public function update($post) {
            if (isset($_FILES['photo']) and upload_tester($_FILES['photo'])) {
                $this->delete_file($post);
                $filename = upload($_FILES['photo'], array("jpg", "jpeg", "png", "gif", "tif", "tiff", "bmp"));
            } else
                $filename = $post->filename;

            if (!empty($_POST['option']['source']) and is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            fallback($_POST['title'], "");
            fallback($_POST['caption'], "");

            return $post->update(array("title" => $_POST['title'],
                                       "filename" => $filename,
                                       "caption" => $_POST['caption']));
        }

        public function title($post) {
            return oneof($post->title, $post->title_from_excerpt());
        }

        public function excerpt($post) {
            return $post->caption;
        }

        public function feed_content($post) {
            $content = '<img src="'.Config::current()->chyrp_url.
                       "/includes/thumb.php?file=".urlencode($post->filename).
                       '" alt="'.fix(oneof($post->alt_text, $post->filename), true).'">';

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
                if ($trigger->exists("delete_upload"))
                    $trigger->call("delete_upload", $post->filename);
                else
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
    }
