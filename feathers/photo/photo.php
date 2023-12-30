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
                    "accept" => ".".implode(",.", $this->photo_extensions()),
                    "note" => _f("(Max. file size: %d Megabytes)", $maximum, "photo")
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
                    $this->photo_extensions()
                );

            if (!isset($filename))
                error(
                    __("Error"),
                    __("You did not select a photo to upload.", "photo"),
                    code:422
                );

            if (isset($_POST['option']['source']) and is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            fallback($_POST['title'], "");
            fallback($_POST['caption'], "");
            fallback($_POST['slug'], $_POST['title']);
            fallback($_POST['status'], "public");
            fallback($_POST['created_at'], datetime());
            fallback($_POST['option'], array());

            return Post::add(
                values:array(
                    "title" => $_POST['title'],
                    "filename" => $filename,
                    "caption" => $_POST['caption']
                ),
                clean:sanitize($_POST['slug']),
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
            fallback($_POST['slug'], $post->clean);
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());
            $filename = $post->filename;

            if (isset($_POST['option']['source']) and is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            if (isset($_FILES['filename']) and upload_tester($_FILES['filename']))
                $filename = upload(
                    $_FILES['filename'],
                    $this->photo_extensions()
                );

            return $post->update(
                values:array(
                    "title" => $_POST['title'],
                    "filename" => $filename,
                    "caption" => $_POST['caption']
                ),
                pinned:!empty($_POST['pinned']),
                status:$_POST['status'],
                clean:sanitize($_POST['slug']),
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

        private function photo_extensions(): array {
            return array("jpg", "jpeg", "png", "gif", "webp", "avif");
        }
    }
