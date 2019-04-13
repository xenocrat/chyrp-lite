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

                if (is_array($_FILES['uploads']['name']))
                    for($i=0; $i < count($_FILES['uploads']['name']); $i++)
                        $filenames[] = upload(array('name' => $_FILES['uploads']['name'][$i],
                                                    'type' => $_FILES['uploads']['type'][$i],
                                                    'tmp_name' => $_FILES['uploads']['tmp_name'][$i],
                                                    'error' => $_FILES['uploads']['error'][$i],
                                                    'size' => $_FILES['uploads']['size'][$i]));
                else
                    $filenames[] = upload($_FILES['uploads']);
            } else {
                error(__("Error"), __("You did not select any files to upload.", "uploader"), null, 422);
            }

            if (!empty($_POST['option']['source']) and is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            fallback($_POST['title'], "");
            fallback($_POST['caption'], "");
            fallback($_POST['slug'], $_POST['title']);

            return Post::add(array("filenames" => self::filenames_serialize($filenames),
                                   "caption" => $_POST['caption'],
                                   "title" => $_POST['title']));
        }

        public function update($post) {
            if (isset($_FILES['uploads']) and upload_tester($_FILES['uploads'])) {
                $this->delete_files($post);
                $filenames = array();

                if (is_array($_FILES['uploads']['name']))
                    for($i=0; $i < count($_FILES['uploads']['name']); $i++)
                        $filenames[] = upload(array('name' => $_FILES['uploads']['name'][$i],
                                                    'type' => $_FILES['uploads']['type'][$i],
                                                    'tmp_name' => $_FILES['uploads']['tmp_name'][$i],
                                                    'error' => $_FILES['uploads']['error'][$i],
                                                    'size' => $_FILES['uploads']['size'][$i]));
                else
                    $filenames[] = upload($_FILES['uploads']);
            } else {
                $filenames = $post->filenames;
            }

            if (!empty($_POST['option']['source']) and is_url($_POST['option']['source']))
                $_POST['option']['source'] = add_scheme($_POST['option']['source']);

            fallback($_POST['title'], "");
            fallback($_POST['caption'], "");

            return $post->update(array("filenames" => self::filenames_serialize($filenames),
                                       "caption" => $_POST['caption'],
                                       "title" => $_POST['title']));
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
                if (!file_exists(uploaded($filename, false)))
                    continue;

                $feed->enclosure(uploaded($filename), filesize(uploaded($filename, false)));
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

            $post->filenames = self::filenames_unserialize($post->filenames);
        }

        public function filter_post($post) {
            if ($post->feather != "uploader")
                return;

            $post->files = self::list_files($post->filenames);

            foreach ($post->files as $file)
                if (in_array($file["type"], array("jpg", "jpeg", "png", "gif", "tif", "tiff", "bmp"))) {
                    $post->thumb = $file["name"];
                    break;
                }
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
    }
