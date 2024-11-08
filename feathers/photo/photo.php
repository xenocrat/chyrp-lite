<?php
    class Photo extends Feathers implements Feather {
        public function __init() {
            $maximum = Config::current()->uploads_limit;

            $this->setField(
                array(
                    "attr" => "title",
                    "type" => "text",
                    "label" => __("Title", "photo"),
                    "optional" => true
                )
            );
            $this->setField(
                array(
                    "attr" => "filename",
                    "type" => "file",
                    "label" => __("Photo", "photo"),
                    "multiple" => false,
                    "accept" => ".".implode(",.", $this->image_extensions())
                )
            );
            $this->setField(
                array(
                    "attr" => "caption",
                    "type" => "text_block",
                    "label" => __("Caption", "photo"),
                    "optional" => true,
                    "preview" => true
                )
            );
            $this->setFilter(
                "title",
                array("markup_post_title", "markup_title")
            );
            $this->setFilter(
                "caption",
                array("markup_post_text", "markup_text")
            );
            $this->respondTo("filter_post","filter_post");
            $this->respondTo("post_options", "add_option");
        }

        public function submit(): Post {
            if (isset($_FILES['filename']) and upload_tester($_FILES['filename']))
                $filename = upload(
                    $_FILES['filename'],
                    $this->image_extensions()
                );

            if (!isset($filename))
                error(
                    __("Error"),
                    __("You did not select a photo to upload.", "photo"),
                    code:422
                );

            $this->fix_jpg_orientation($filename);

            fallback($_POST['title'], "");
            fallback($_POST['caption'], "");
            fallback($_POST['slug'], $_POST['title']);
            fallback($_POST['status'], "public");
            fallback($_POST['created_at'], datetime());
            fallback($_POST['option'], array());
            fallback($_POST['option']['source'], "");

            if (is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            return Post::add(
                values:array(
                    "title" => $_POST['title'],
                    "filename" => $filename,
                    "caption" => $_POST['caption']
                ),
                clean:sanitize($_POST['slug'], true, SLUG_STRICT, 128),
                feather:"photo",
                pinned:!empty($_POST['pinned']),
                status:$_POST['status'],
                created_at:datetime($_POST['created_at']),
                pingbacks:true,
                options:$_POST['option']
            );
        }

        public function update($post): Post|false {
            fallback($_POST['title'], "");
            fallback($_POST['caption'], "");
            fallback($_POST['slug'], "");
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());
            fallback($_POST['option']['source'], "");
            $filename = $post->filename;

            if (is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            if (isset($_FILES['filename']) and upload_tester($_FILES['filename']))
                $filename = upload(
                    $_FILES['filename'],
                    $this->image_extensions()
                );

            return $post->update(
                values:array(
                    "title" => $_POST['title'],
                    "filename" => $filename,
                    "caption" => $_POST['caption']
                ),
                pinned:!empty($_POST['pinned']),
                status:$_POST['status'],
                clean:sanitize($_POST['slug'], true, SLUG_STRICT, 128),
                created_at:datetime($_POST['created_at']),
                options:$_POST['option']
            );
        }

        public function title($post): string {
            return oneof(
                $post->title,
                $post->title_from_excerpt()
            );
        }

        public function excerpt($post): string {
            return $post->caption;
        }

        public function feed_content($post): string {
            $content = '<img src="'.Config::current()->chyrp_url.
                       "/includes/thumbnail.php?file=".
                       urlencode($post->filename).
                       '" alt="'.fix($post->alt_text, true).'">';

            if (!empty($post->caption))
                $content.= '<figcaption>'.
                           $post->caption.
                           '</figcaption>';

            return '<figure>'.$content.'</figure>';
        }

        public function filter_post($post): void {
            if ($post->feather != "photo")
                return;

            $post->image = $post->filename;
        }

        public function add_option($options, $post = null, $feather = null): array {
            if ($feather != "photo")
                return $options;

            $options[] = array(
                "attr" => "option[alt_text]",
                "label" => __("Alternative Text", "photo"),
                "help" => "photo_alt_text",
                "type" => "text",
                "value" => (isset($post) ? $post->alt_text : "")
            );

            $options[] = array(
                "attr" => "option[source]",
                "label" => __("Source", "photo"),
                "help" => "photo_source",
                "type" => "url",
                "value" => (isset($post) ? $post->source : "")
            );

            return $options;
        }

        private function image_extensions(): array {
            return array("jpg", "jpeg", "png", "gif", "webp", "avif");
        }

        private function fix_jpg_orientation($filename): void {
            $filepath = uploaded($filename, false);
            $image_info = getimagesize($filepath, $source_info);
            $source_type = $image_info[2];

            // only do it for JPG (because only JPG got EXIF)
            if ($source_type != IMAGETYPE_JPEG) {
                return;
            }

            // from https://www.php.net/manual/en/function.imagecreatefromjpeg.php#112902
            $img = imagecreatefromjpeg($filepath);
            $exif = exif_read_data($filepath);

            if ($img && $exif && isset($exif['Orientation'])) {
                $ort = $exif['Orientation'];

                if ($ort == 6 || $ort == 5)
                    $img = imagerotate($img, 270, 0);
                if ($ort == 3 || $ort == 4)
                    $img = imagerotate($img, 180, 0);
                if ($ort == 8 || $ort == 7)
                    $img = imagerotate($img, 90, 0);

                if ($ort == 5 || $ort == 4 || $ort == 7)
                    imageflip($img, IMG_FLIP_HORIZONTAL);
            }

            // replace uploaded file
            imagejpeg($img, $filepath, 100);
        }
    }
