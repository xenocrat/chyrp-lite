<?php
    class Uploader extends Feathers implements Feather {
        public function __init() {
            $maximum = Config::current()->uploads_limit;

            $this->setField(
                array(
                    "attr" => "title",
                    "type"=> "text",
                    "label" => __("Title", "uploader"),
                    "optional" => true
                )
            );
            $this->setField(
                array(
                    "attr" => "filenames",
                    "type" => "file",
                    "label" => __("Files", "uploader"),
                    "multiple" => true,
                    "accept" => ".".implode(",.", upload_filter_whitelist())
                )
            );
            $this->setField(
                array(
                    "attr" => "caption",
                    "type" => "text_block",
                    "label" => __("Caption", "uploader"),
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
            $this->respondTo("feed_item", "enclose_uploaded");
            $this->respondTo("post","post");
            $this->respondTo("filter_post","filter_post");
            $this->respondTo("post_options", "add_option");
        }

        private function filenames_serialize($files) {
            return json_set($files, JSON_UNESCAPED_SLASHES);
        }

        private function filenames_unserialize($filenames) {
            return json_get($filenames, true);
        }

        public function submit(): Post {
            if (isset($_FILES['filenames']) and upload_tester($_FILES['filenames'])) {
                $filenames = array();

                if (is_array($_FILES['filenames']['name'])) {
                    for ($i = 0; $i < count($_FILES['filenames']['name']); $i++)
                        $filenames[] = upload(
                            array(
                                'name' => $_FILES['filenames']['name'][$i],
                                'type' => $_FILES['filenames']['type'][$i],
                                'tmp_name' => $_FILES['filenames']['tmp_name'][$i],
                                'error' => $_FILES['filenames']['error'][$i],
                                'size' => $_FILES['filenames']['size'][$i]
                            )
                        );
                } else {
                    $filenames[] = upload($_FILES['filenames']);
                }
            }

            if (empty($filenames))
                error(
                    __("Error"),
                    __("You did not select any files to upload.", "uploader"),
                    code:422
                );

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
                    "filenames" => $this->filenames_serialize($filenames),
                    "caption" => $_POST['caption'],
                    "title" => $_POST['title']
                ),
                clean:sanitize($_POST['slug'], true, SLUG_STRICT, 128),
                feather:"uploader",
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
            $filenames = $post->filenames;

            if (is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            if (isset($_FILES['filenames']) and upload_tester($_FILES['filenames'])) {
                $filenames = array();

                if (is_array($_FILES['filenames']['name'])) {
                    for($i=0; $i < count($_FILES['filenames']['name']); $i++)
                        $filenames[] = upload(
                            array(
                                'name' => $_FILES['filenames']['name'][$i],
                                'type' => $_FILES['filenames']['type'][$i],
                                'tmp_name' => $_FILES['filenames']['tmp_name'][$i],
                                'error' => $_FILES['filenames']['error'][$i],
                                'size' => $_FILES['filenames']['size'][$i]
                            )
                        );
                } else {
                    $filenames[] = upload($_FILES['filenames']);
                }
            }

            return $post->update(
                values:array(
                    "filenames" => $this->filenames_serialize($filenames),
                    "caption" => $_POST['caption'],
                    "title" => $_POST['title']
                ),
                pinned:!empty($_POST['pinned']),
                status:$_POST['status'],
                clean:sanitize($_POST['slug'], true, SLUG_STRICT, 128),
                created_at:datetime($_POST['created_at']),
                options:$_POST['option']
            );
        }

        public function title($post): string {
            return oneof($post->title, $post->title_from_excerpt());
        }

        public function excerpt($post): string {
            return $post->caption;
        }

        public function feed_content($post): string {
            return $post->caption;
        }

        public function enclose_uploaded($post, $feed): void {
            $config = Config::current();

            if ($post->feather != "uploader")
                return;

            foreach ($post->filenames as $filename) {
                $filepath = uploaded($filename, false);

                if (!file_exists($filepath))
                    continue;

                $feed->enclosure(
                    uploaded($filename),
                    filesize($filepath)
                );
            }
        }

        public function post($post): void {
            if ($post->feather != "uploader")
                return;

            $post->filenames = $this->filenames_unserialize($post->filenames);
        }

        public function filter_post($post): void {
            if ($post->feather != "uploader")
                return;

            $post->files = $this->list_files($post->filenames);

            foreach ($post->files as $file)
                if (
                    in_array(
                        $file["type"],
                        array("jpg", "jpeg", "png", "gif", "webp", "avif")
                    )
                ) {
                    $post->image = $file["name"];
                    break;
                }
        }

        public function add_option($options, $post = null, $feather = null): array {
            if ($feather != "uploader")
                return $options;

            $options[] = array(
                "attr" => "option[source]",
                "label" => __("Source", "uploader"),
                "help" => "uploader_source",
                "type" => "url",
                "value" => (isset($post) ? $post->source : "")
            );

            return $options;
        }

        private function list_files($filenames): array {
            $list = array();

            foreach ($filenames as $filename) {
                $filepath = uploaded($filename, false);
                $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $exists = file_exists($filepath);

                $list[] = array(
                    "name" => $filename,
                    "type" => $filetype,
                    "size" => ($exists) ? filesize($filepath) : 0,
                    "modified" => ($exists) ? filemtime($filepath) : 0
                );
            }

            return $list;
        }
    }
