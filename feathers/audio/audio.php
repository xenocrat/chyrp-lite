<?php
    class Audio extends Feathers implements Feather {
        public function __init() {
            $this->setField(array("attr" => "title",
                                  "type" => "text",
                                  "label" => __("Title", "audio"),
                                  "optional" => true));
            $this->setField(array("attr" => "audio",
                                  "type" => "file",
                                  "label" => __("Audio File", "audio"),
                                  "multiple" => false,
                                  "note" => _f("(Max. file size: %s Megabytes)", Config::current()->uploads_limit, "audio")));
            $this->setField(array("attr" => "description",
                                  "type" => "text_block",
                                  "label" => __("Description", "audio"),
                                  "optional" => true,
                                  "preview" => "markup_text"));

            $this->setFilter("title", array("markup_title", "markup_post_title"));
            $this->setFilter("description", array("markup_text", "markup_post_text"));

            $this->respondTo("delete_post", "delete_file");
            $this->respondTo("feed_item", "enclose_audio");
            $this->respondTo("filter_post", "filter_post");
        }

        public function submit() {
            if (isset($_FILES['audio']) and upload_tester($_FILES['audio']))
                $filename = upload($_FILES['audio'], array("mp3", "m4a", "mp4", "oga", "ogg", "webm", "mka"));
            else
                error(__("Error"), __("You did not select any audio to upload.", "audio"));

            return Post::add(array("title" => $_POST['title'],
                                   "filename" => $filename,
                                   "description" => $_POST['description']),
                             $_POST['slug'],
                             Post::check_url($_POST['slug']));
        }

        public function update($post) {
            if (isset($_FILES['audio']) and upload_tester($_FILES['audio'])) {
                $this->delete_file($post);
                $filename = upload($_FILES['audio'], array("mp3", "m4a", "mp4", "oga", "ogg", "webm", "mka"));
            } else
                $filename = $post->filename;

            $post->update(array("title" => $_POST['title'],
                                "filename" => $filename,
                                "description" => $_POST['description']));
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

        public function delete_file($post) {
            if ($post->feather != "audio")
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
            if ($post->feather != "audio")
                return;

            $post->audio_player = $this->audio_player($post->filename, array(), $post);
        }

        public function audio_type($filename) {
            $file_split = explode(".", $filename);
            $file_ext = strtolower(end($file_split));

            switch($file_ext) {
                case "mp3":
                    return "audio/mpeg";
                case "m4a":
                    return "audio/mp4";
                case "mp4":
                    return "audio/mp4";
                case "oga":
                    return "audio/ogg";
                case "ogg":
                    return "audio/ogg";
                case "webm":
                    return "audio/webm";
                case "mka":
                    return "audio/x-matroska";
                default:
                    return "application/octet-stream";
            }
        }

        public function enclose_audio($post) {
            $config = Config::current();

            if ($post->feather != "audio" or !file_exists(uploaded($post->filename, false)))
                return;

            $length = filesize(uploaded($post->filename, false));

            echo '        <link rel="enclosure" href="'.uploaded($post->filename).
                        '" type="'.$this->audio_type($post->filename).
                        '" title="'.truncate(strip_tags($post->title())).
                        '" length="'.$length.'" />'."\n";
        }

        public function audio_player($filename, $params = array(), $post) {
            $trigger = Trigger::current();

            if ($trigger->exists("audio_player"))
                return $trigger->call("audio_player", $filename, $params, $post);

            $player = "\n".'<audio controls>';
            $player.= "\n".__("Your web browser does not support the <code>audio</code> element.", "audio");
            $player.= "\n".'<source src="'.uploaded($filename).'" type="'.$this->audio_type($filename).'">';
            $player.= "\n".'</audio>'."\n";

            return $player;
        }
    }
