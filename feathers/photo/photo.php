<?php
    class Photo extends Feathers implements Feather {
        public function __init() {
            $this->setField(array("attr" => "title",
                                  "type" => "text",
                                  "label" => __("Title", "photo"),
                                  "optional" => true));
            $this->setField(array("attr" => "photo",
                                  "type" => "file",
                                  "label" => __("Photo", "photo"),
                                  "multiple" => false,
                                  "note" => _f("(Max. file size: %d Megabytes)", Config::current()->uploads_limit, "photo")));
            $this->setField(array("attr" => "caption",
                                  "type" => "text_block",
                                  "label" => __("Caption", "photo"),
                                  "optional" => true,
                                  "preview" => true));

            $this->setFilter("title", array("markup_title", "markup_post_title"));
            $this->setFilter("caption", array("markup_text", "markup_post_text"));

            $this->respondTo("delete_post", "delete_file");
            $this->respondTo("filter_post", "filter_post");
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

            $post->update(array("title" => $_POST['title'],
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
            return self::image_tag($post, 500, 500)."<p>".$post->caption."</p>";
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

        public function filter_post($post) {
            if ($post->feather != "photo")
                return;

            $post->image = $this->image_tag($post);
        }

        public function image_tag($post, $max_width = 640, $max_height = null, $more_args = "quality=100", $sizes = "100vw") {
            $config = Config::current();
            $safename = urlencode($post->filename);
            $alt = !empty($post->alt_text) ? fix($post->alt_text, true) : $post->filename ;

            # Source set for responsive images.
            $srcset = array($config->chyrp_url.'/includes/thumb.php?file='.$safename.'&amp;max_width='.$max_width.'&amp;max_height='.$max_height.'&amp;'.$more_args.' 1x',
                            $config->chyrp_url.'/includes/thumb.php?file='.$safename.'&amp;max_width=960&amp;'.$more_args.' 960w',
                            $config->chyrp_url.'/includes/thumb.php?file='.$safename.'&amp;max_width=640&amp;'.$more_args.' 640w',
                            $config->chyrp_url.'/includes/thumb.php?file='.$safename.'&amp;max_width=320&amp;'.$more_args.' 320w');

            $tag = '<img srcset="'.implode(", ", $srcset).'" sizes="'.$sizes.'"';
            $tag.= ' src="'.$config->chyrp_url.'/includes/thumb.php?file='.$safename;
            $tag.= '&amp;max_width='.$max_width.'&amp;max_height='.$max_height.'&amp;'.$more_args.'"';
            $tag.= ' alt="'.$alt.'" class="image">';

            return $tag;
        }

        public function image_link($post, $max_width = 640, $max_height = null, $more_args = "quality=100", $sizes = "100vw") {
            $source = !empty($post->source) ? $post->source : uploaded($post->filename) ;
            return '<a href="'.fix($source, true).'" class="image_link">'.$this->image_tag($post, $max_width, $max_height, $more_args, $sizes).'</a>';
        }

        public function add_option($options, $post = null) {
            if (isset($post) and $post->feather != "photo")
                return;

            if (Route::current()->action == "write_post" and $_GET['feather'] != "photo")
                return;

            $options[] = array("attr" => "option[alt_text]",
                               "label" => __("Alt-Text", "photo"),
                               "type" => "text",
                               "value" => oneof(@$post->alt_text, ""));

            $options[] = array("attr" => "option[source]",
                               "label" => __("Source", "photo"),
                               "type" => "text",
                               "value" => oneof(@$post->source, ""));

            return $options;
        }
    }
