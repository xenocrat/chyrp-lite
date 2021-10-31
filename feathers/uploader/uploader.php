<?php
    class Uploader extends Feathers implements Feather {
        public function __init() {
            $maximum = Config::current()->uploads_limit;

            $this->setField(array("attr" => "title",
                                  "type"=> "text",
                                  "label" => __("Title", "uploader"),
                                  "optional" => true));

            $this->setField(array("attr" => "uploads",
                                  "type" => "file",
                                  "label" => __("Files", "uploader"),
                                  "multiple" => true,
                                  "accept" => ".".implode(",.", upload_filter_whitelist()),
                                  "note" => _f("(Max. file size: %d Megabytes)", $maximum, "uploader")));

            $this->setField(array("attr" => "caption",
                                  "type" => "text_block",
                                  "label" => __("Caption", "uploader"),
                                  "optional" => true,
                                  "preview" => true));

            $this->setFilter("title", array("markup_post_title", "markup_title"));
            $this->setFilter("caption", array("markup_post_text", "markup_text"));

            $this->respondTo("delete_post", "delete_files");
            $this->respondTo("feed_item", "enclose_uploaded");
            $this->respondTo("post","post");
            $this->respondTo("filter_post","filter_post");
            $this->respondTo("post_options", "add_option");
            $this->respondTo("metaWeblog_getPost", "metaWeblog_getValues");
            $this->respondTo("metaWeblog_before_editPost", "metaWeblog_setValues");
        }

        private function filenames_serialize($files) {
            return json_set($files, JSON_UNESCAPED_SLASHES);
        }

        private function filenames_unserialize($filenames) {
            return json_get($filenames, true);
        }

        public function submit() {
            if (isset($_FILES['uploads']) and upload_tester($_FILES['uploads'])) {
                $filenames = array();

                if (is_array($_FILES['uploads']['name'])) {
                    for ($i = 0; $i < count($_FILES['uploads']['name']); $i++)
                        $filenames[] = upload(array(
                            'name' => $_FILES['uploads']['name'][$i],
                            'type' => $_FILES['uploads']['type'][$i],
                            'tmp_name' => $_FILES['uploads']['tmp_name'][$i],
                            'error' => $_FILES['uploads']['error'][$i],
                            'size' => $_FILES['uploads']['size'][$i]
                        ));
                } else {
                    $filenames[] = upload($_FILES['uploads']);
                }
            }

            if (empty($filenames))
                error(__("Error"), __("You did not select any files to upload.", "uploader"), null, 422);

            if (!empty($_POST['option']['source']) and is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            fallback($_POST['title'], "");
            fallback($_POST['caption'], "");
            fallback($_POST['slug'], $_POST['title']);
            fallback($_POST['status'], "public");
            fallback($_POST['created_at'], datetime());
            fallback($_POST['option'], array());

            return Post::add(array("filenames" => $this->filenames_serialize($filenames),
                                   "caption" => $_POST['caption'],
                                   "title" => $_POST['title']),
                             sanitize($_POST['slug']),
                             "",
                             "uploader",
                             null,
                             !empty($_POST['pinned']),
                             $_POST['status'],
                             datetime($_POST['created_at']),
                             null,
                             true,
                             $_POST['option']);
        }

        public function update($post) {
            if (isset($_FILES['uploads']) and upload_tester($_FILES['uploads'])) {
                $filenames = array();

                if (is_array($_FILES['uploads']['name'])) {
                    for($i=0; $i < count($_FILES['uploads']['name']); $i++)
                        $filenames[] = upload(array(
                            'name' => $_FILES['uploads']['name'][$i],
                            'type' => $_FILES['uploads']['type'][$i],
                            'tmp_name' => $_FILES['uploads']['tmp_name'][$i],
                            'error' => $_FILES['uploads']['error'][$i],
                            'size' => $_FILES['uploads']['size'][$i]
                        ));
                } else {
                    $filenames[] = upload($_FILES['uploads']);
                }

                $this->delete_files($post);
            } else {
                $filenames = $post->filenames;
            }

            if (!empty($_POST['option']['source']) and is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            fallback($_POST['title'], "");
            fallback($_POST['caption'], "");
            fallback($_POST['slug'], $post->clean);
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());

            return $post->update(array("filenames" => $this->filenames_serialize($filenames),
                                       "caption" => $_POST['caption'],
                                       "title" => $_POST['title']),
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
            return $post->caption;
        }

        public function enclose_uploaded($post, $feed) {
            $config = Config::current();

            if ($post->feather != "uploader")
                return;

            foreach ($post->filenames as $filename) {
                $filepath = uploaded($filename, false);

                if (!file_exists($filepath))
                    continue;

                $feed->enclosure(uploaded($filename), filesize($filepath));
            }
        }

        public function delete_files($post) {
            if ($post->feather != "uploader")
                return;

            $trigger = Trigger::current();

            foreach ($post->filenames as $filename) {
                $filepath = uploaded($filename, false);

                if (file_exists($filepath)) {
                    $trigger->call("delete_upload", $filename);
                    unlink($filepath);
                }
            }
        }

        public function post($post) {
            if ($post->feather != "uploader")
                return;

            $post->filenames = $this->filenames_unserialize($post->filenames);
        }

        public function filter_post($post) {
            if ($post->feather != "uploader")
                return;

            $post->files = $this->list_files($post->filenames);

            foreach ($post->files as $file)
                if (in_array($file["type"], array("jpg", "jpeg", "png", "gif", "webp", "avif"))) {
                    $post->image = $file["name"];
                    break;
                }
        }

        public function add_option($options, $post = null, $feather = null) {
            if ($feather != "uploader")
                return;

            $options[] = array("attr" => "option[source]",
                               "label" => __("Source", "uploader"),
                               "help" => "uploader_source",
                               "type" => "text",
                               "value" => (isset($post) ? $post->source : ""));

            return $options;
        }

        public function metaWeblog_getValues($struct, $post) {
            if ($post->feather != "uploader")
                return;

            $struct["title"] = $post->title;
            $struct["description"] = $post->caption;

            return $struct;
        }

        public function metaWeblog_setValues($values, $struct, $post) {
            if ($post->feather != "uploader")
                return;

            $values["title"] = $struct["title"];
            $values["caption"] = $struct["description"];

            return $values;
        }

        private function list_files($filenames) {
            $list = array();

            foreach ($filenames as $filename) {
                $filepath = uploaded($filename, false);
                $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                $list[] = array("name" => $filename,
                                "type" => $filetype,
                                "size" => (file_exists($filepath) ? filesize($filepath) : 0 ));
            }

            return $list;
        }
    }
