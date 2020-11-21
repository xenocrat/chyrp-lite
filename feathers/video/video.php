<?php
    class Video extends Feathers implements Feather {
        public function __init() {
            $maximum = Config::current()->uploads_limit;

            $this->setField(array("attr" => "title",
                                  "type" => "text",
                                  "label" => __("Title", "video"),
                                  "optional" => true));

            $this->setField(array("attr" => "video",
                                  "type" => "file",
                                  "label" => __("Video File", "video"),
                                  "multiple" => false,
                                  "accept" => ".".implode(",.", $this->video_extensions()),
                                  "note" => _f("(Max. file size: %d Megabytes)", $maximum, "video")));

            $this->setField(array("attr" => "description",
                                  "type" => "text_block",
                                  "label" => __("Description", "video"),
                                  "optional" => true,
                                  "preview" => true));

            $this->setFilter("title", array("markup_post_title", "markup_title"));
            $this->setFilter("description", array("markup_post_text", "markup_text"));

            $this->respondTo("delete_post", "delete_file");
            $this->respondTo("feed_item", "enclose_video");
            $this->respondTo("filter_post", "filter_post");
            $this->respondTo("metaWeblog_getPost", "metaWeblog_getValues");
            $this->respondTo("metaWeblog_before_editPost", "metaWeblog_setValues");
        }

        public function submit() {
            if (isset($_FILES['video']) and upload_tester($_FILES['video']))
                $filename = upload($_FILES['video'], $this->video_extensions());

            if (!isset($filename))
                error(__("Error"), __("You did not select a video to upload.", "video"), null, 422);

            fallback($_POST['title'], "");
            fallback($_POST['description'], "");
            fallback($_POST['slug'], $_POST['title']);
            fallback($_POST['status'], "public");
            fallback($_POST['created_at'], datetime());
            fallback($_POST['option'], array());

            return Post::add(array("title" => $_POST['title'],
                                   "filename" => $filename,
                                   "description" => $_POST['description']),
                             sanitize($_POST['slug']),
                             "",
                             "video",
                             null,
                             !empty($_POST['pinned']),
                             $_POST['status'],
                             datetime($_POST['created_at']),
                             null,
                             true,
                             $_POST['option']);
        }

        public function update($post) {
            if (isset($_FILES['video']) and upload_tester($_FILES['video'])) {
                $filename = upload($_FILES['video'], $this->video_extensions());
                $this->delete_file($post);
            } else {
                $filename = $post->filename;
            }

            fallback($_POST['title'], "");
            fallback($_POST['description'], "");
            fallback($_POST['slug'], $post->clean);
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());

            return $post->update(array("title" => $_POST['title'],
                                       "filename" => $filename,
                                       "description" => $_POST['description']),
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
            return $post->description;
        }

        public function feed_content($post) {
            return $post->description;
        }

        public function enclose_video($post, $feed) {
            $config = Config::current();
            $filepath = uploaded($post->filename, false);

            if ($post->feather != "video" or !file_exists($filepath))
                return;

            $feed->enclosure(uploaded($post->filename),
                             filesize($filepath),
                             $this->video_type($post->filename));
        }

        public function delete_file($post) {
            if ($post->feather != "video")
                return;

            $trigger = Trigger::current();
            $filepath = uploaded($post->filename, false);

            if (file_exists($filepath)) {
                $trigger->call("delete_upload", $post->filename);
                unlink($filepath);
            }
        }

        public function filter_post($post) {
            if ($post->feather != "video")
                return;

            $post->video_player = $this->video_player($post);
        }

        public function metaWeblog_getValues($struct, $post) {
            if ($post->feather != "audio")
                return;

            $struct["title"] = $post->title;
            $struct["description"] = $post->description;

            return $struct;
        }

        public function metaWeblog_setValues($values, $struct, $post) {
            if ($post->feather != "audio")
                return;

            $values["title"] = $struct["title"];
            $values["description"] = $struct["description"];

            return $values;
        }

        private function video_player($post) {
            $trigger = Trigger::current();

            if ($trigger->exists("video_player"))
                return $trigger->call("video_player", $post);

            return '<video controls>'."\n".
                   __("Your web browser does not support the <code>video</code> element.", "video")."\n".
                   '<source src="'.uploaded($post->filename).'" type="'.$this->video_type($post->filename).
                   '">'."\n".'</video>'."\n";
        }

        private function video_type($filename) {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            switch($extension) {
                case "mp4":
                    return "video/mp4";
                case "ogv":
                    return "video/ogg";
                case "webm":
                    return "video/webm";
                case "3gp":
                    return "video/3gpp";
                case "mkv":
                    return "video/x-matroska";
                case "mov":
                    return "video/quicktime";
                default:
                    return "application/octet-stream";
            }
        }

        private function video_extensions() {
            return array("mp4", "ogv", "webm", "3gp", "mkv", "mov");
        }
    }
