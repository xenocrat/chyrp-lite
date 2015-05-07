<?php
    class Photo extends Feathers implements Feather {
        public function __init() {
            $this->setField(array("attr" => "title",
                                  "type" => "text",
                                  "label" => __("Title", "photo"),
                                  "optional" => true,
                                  "preview" => "markup_title"));
            $this->setField(array("attr" => "photo",
                                  "type" => "file",
                                  "label" => __("Photo", "photo"),
                                  "note" => _f("(Max. file size: %s)", ini_get('upload_max_filesize'), "photo")));
            $this->setField(array("attr" => "caption",
                                  "type" => "text_block",
                                  "label" => __("Caption", "photo"),
                                  "optional" => true,
                                  "preview" => "markup_text"));

            $this->setFilter("title", array("markup_title", "markup_post_title"));
            $this->setFilter("caption", array("markup_text", "markup_post_text"));

            $this->respondTo("delete_post", "delete_file");
            $this->respondTo("filter_post", "filter_post");
            $this->respondTo("post_options", "add_option");
        }

        public function submit() {
            if (!isset($_POST['filename']))
                if (isset($_FILES['photo']) and upload_tester($_FILES['photo']['error']))
                    $filename = upload($_FILES['photo'], array("jpg", "jpeg", "png", "gif", "bmp"));
                else
                    error(__("Error"), __("You did not select a photo to upload.", "photo"));
            else
                $filename = $_POST['filename'];
                
            # Prepend scheme if a URL is detected in the source text
            if (preg_match('~^((([a-z]|[0-9]|\-)+)\.)+([a-z]){2,6}/~', @$_POST['option']['source']))
                $_POST['option']['source'] = "http://".$_POST['option']['source'];
                
            fallback($_POST['slug'], sanitize($_POST['title']));

            return Post::add(array("title" => $_POST['title'],
                                   "filename" => $filename,
                                   "caption" => $_POST['caption']),
                             $_POST['slug'],
                             Post::check_url($_POST['slug']));
        }

        public function update($post) {
            if (!isset($_POST['filename']))
                if (isset($_FILES['photo']) and upload_tester($_FILES['photo']['error'])) {
                    $this->delete_file($post);
                    $filename = upload($_FILES['photo'], array("jpg", "jpeg", "png", "gif", "tiff", "bmp"));
                } else
                    $filename = $post->filename;
            else
                $filename = $_POST['filename'];
            
            # Prepend scheme if a URL is detected in the source text
            if (preg_match('~^((([a-z]|[0-9]|\-)+)\.)+([a-z]){2,6}/~', @$_POST['option']['source']))
                $_POST['option']['source'] = "http://".$_POST['option']['source'];
            
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

            unlink(MAIN_DIR.Config::current()->uploads_path.$post->filename);
        }

        public function filter_post($post) {
            if ($post->feather != "photo")
                return;

            $post->image = $this->image_tag($post);
        }

        public function image_tag($post, $max_width = 500, $max_height = null, $more_args = "quality=100") {
            $config = Config::current();
            $alt = !empty($post->alt_text) ? fix($post->alt_text, true) : $post->filename ;

            # Source set for responsive images
            $srcset = array($config->chyrp_url.'/includes/thumb.php?file=..'.$config->uploads_path.urlencode($post->filename).'&amp;max_width='.$max_width.'&amp;max_height='.$max_height.'&amp;'.$more_args.' 1x',
                            $config->chyrp_url.'/includes/thumb.php?file=..'.$config->uploads_path.urlencode($post->filename).'&amp;max_width=960&amp;'.$more_args.' 960w',
                            $config->chyrp_url.'/includes/thumb.php?file=..'.$config->uploads_path.urlencode($post->filename).'&amp;max_width=640&amp;'.$more_args.' 640w',
                            $config->chyrp_url.'/includes/thumb.php?file=..'.$config->uploads_path.urlencode($post->filename).'&amp;max_width=320&amp;'.$more_args.' 320w');

            $tag = '<img srcset="'.implode(", ", $srcset).'" sizes="80vw"';
            $tag.= ' src="'.$config->chyrp_url.'/includes/thumb.php?file=..'.$config->uploads_path.urlencode($post->filename);
            $tag.= '&amp;max_width='.$max_width.'&amp;max_height='.$max_height.'&amp;'.$more_args.'"';
            $tag.= ' alt="'.$alt.'" class="image">';

            return $tag;
        }

        public function image_link($post, $max_width = 500, $max_height = null, $more_args = "quality=100") {
            $source = !empty($post->source) ? $post->source : uploaded($post->filename) ;
            return '<a href="'.$source.'" class="image_link">'.$this->image_tag($post, $max_width, $max_height, $more_args).'</a>';
        }

        public function add_option($options, $post = null) {
            if (isset($post) and $post->feather != "photo")
                return;
            elseif (Route::current()->action == "write_post")
                if (!isset($_GET['feather']) and Config::current()->enabled_feathers[0] != "photo" or
                    isset($_GET['feather']) and $_GET['feather'] != "photo")
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
