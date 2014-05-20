<?php
    class Uploader extends Feathers implements Feather {
        public function __init() {
            $this->setField(array("attr" => "filenames",
                                  "type" => "file",
                                  "multiple" => "true",
                                  "label" => __("Files", "uploader"),
                                  "note" => "<small>(Max. file size: ".ini_get('upload_max_filesize').")</small>"));
            $this->setField(array("attr" => "title",
                                  "type"=> "text",
                                  "label" => __("Title", "uploader"),
                                  "optional" => true));         
            $this->setField(array("attr" => "caption",
                                  "type" => "text_block",
                                  "label" => __("Caption", "uploader")));

            $this->setFilter("caption", array("markup_text", "markup_post_text"));

            $this->respondTo("delete_post", "delete_file");
            $this->respondTo("filter_post","filter_post");
            $this->respondTo("post_options", "add_option");
        }

        public function submit() {
            if (isset($_FILES['filenames'])) {
                $files = array();
                if (is_array($_FILES['filenames']['name'])) {
                    for($i=0; $i < count($_FILES['filenames']['name']); $i++) {
                        if ($_FILES['filenames']['error'][$i] == 0)
                            $files[] = upload(array('name' => $_FILES['filenames']['name'][$i],
                                                    'type' => $_FILES['filenames']['type'][$i],
                                                    'tmp_name' => $_FILES['filenames']['tmp_name'][$i],
                                                    'error' => $_FILES['filenames']['error'][$i],
                                                    'size' => $_FILES['filenames']['size'][$i]));
                        else
                            error(__("Error"), __("Failed to upload file."));
                    }
                } else {
                    if ($_FILES['filenames']['error'] == 0)
                        $files[] = upload($_FILES['filenames']);
                    else
                        error(__("Error"), __("Failed to upload file."));
                }
            } else {
                error(__("Error"), __("Failed to upload file."));
            }

            # Prepend scheme if a URL is detected in the source text
            if (preg_match('~^((([a-z]|[0-9]|\-)+)\.)+([a-z]){2,6}/~', @$_POST['option']['source']))
                $_POST['option']['source'] = "http://".$_POST['option']['source'];

            fallback($_POST['slug'], sanitize($_POST['title']));

            return Post::add(array("filenames" => serialize($files),
                                   "caption" => $_POST['caption'],
                                   "title" => $_POST['title']),
                             $_POST['slug'],
                             Post::check_url($_POST['slug']));
        }

        public function update($post) {
            if (isset($_FILES['filenames'])) {
                $this->delete_file($post);
                $files = array();
                if (is_array($_FILES['filenames']['name'])) {
                    for($i=0; $i < count($_FILES['filenames']['name']); $i++) {
                        if ($_FILES['filenames']['error'][$i] == 0)
                            $files[] = upload(array('name' => $_FILES['filenames']['name'][$i],
                                                    'type' => $_FILES['filenames']['type'][$i],
                                                    'tmp_name' => $_FILES['filenames']['tmp_name'][$i],
                                                    'error' => $_FILES['filenames']['error'][$i],
                                                    'size' => $_FILES['filenames']['size'][$i]));
                        else
                            error(__("Error"), __("Failed to upload file."));
                    }
                } else {
                    if ($_FILES['filenames']['error'] == 0)
                        $files[] = upload($_FILES['filenames']);
                    else
                        error(__("Error"), __("Failed to upload file."));
                }
            } else {
                $files = unserialize($post->filenames);
            }

            # Prepend scheme if a URL is detected in the source text
            if (preg_match('~^((([a-z]|[0-9]|\-)+)\.)+([a-z]){2,6}/~', @$_POST['option']['source']))
                $_POST['option']['source'] = "http://".$_POST['option']['source'];

            $post->update(array("filenames" => serialize($files),
                                "caption" => $_POST['caption'],
                                "title" => $_POST['title']));
        }

        public function title($post) {
            return oneof($post->title,$post->title_from_excerpt());
        }

        public function excerpt($post) {
            return $post->caption;
        }

        public function feed_content($post) {
            return $post->caption;
        }

        public function delete_file($post) {
            if ($post->feather != "uploader") return;
            $files = unserialize($post->filenames);
            for ($i=0; $i < count($files); $i++) {
                unlink(MAIN_DIR.Config::current()->uploads_path.$files[$i]);
            }
        }

        public function filter_post($post) {
            if ($post->feather != "uploader") return;
            $post->files = $this->list_files($post->filenames, array(), $post);
        }

        public function list_files($files, $params = array(), $post) {
            $files = unserialize($files);
            $list = array();
            for ($i=0; $i < count($files); $i++) {
                $list[$i]['name'] = $files[$i];
                $list[$i]['link'] = uploaded($files[$i]);
            }

            return $list;
        }

        public function add_option($options, $post = null) {
            if (isset($post) and $post->feather != "uploader") return;
            elseif (Route::current()->action == "write_post")
                if (!isset($_GET['feather']) and Config::current()->enabled_feathers[0] != "uploader" or
                    isset($_GET['feather']) and $_GET['feather'] != "uploader") return;

            $options[] = array("attr" => "option[source]",
                               "label" => __("Source", "uploader"),
                               "type" => "text",
                               "value" => oneof(@$post->source, ""));

            return $options;
        }

    }
