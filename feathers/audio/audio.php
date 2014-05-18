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
                                  "note" => _f("<small>(Max. file size: %s)</small>", array(ini_get('upload_max_filesize')))));
            $this->setField(array("attr" => "description",
                                  "type" => "text_block",
                                  "label" => __("Description"),
                                  "optional" => true));

            $this->setFilter("title", array("markup_title", "markup_post_title"));
            $this->setFilter("description", array("markup_text", "markup_post_text"));

            $this->respondTo("delete_post", "delete_file");
            $this->respondTo("feed_item", "enclose_audio");
            $this->respondTo("filter_post", "filter_post");
            $this->respondTo("post_options", "add_option");
        }

        public function add_jplayer_script($scripts) {
            $scripts[] = Config::current()->chyrp_url."/feathers/audio/jplayer/jquery.jplayer.js";
            return $scripts;
        }

        public function submit() {
            if (!isset($_POST['filename'])) {
                if (isset($_FILES['audio']) and $_FILES['audio']['error'] == 0)
                    $filename = upload($_FILES['audio'], array("mp3", "m4a", "mp4", "oga", "ogg", "webm", "mka"));
                else
                    error(__("Error"), __("Couldn't upload audio file.", "audio"));
            } else
                $filename = $_POST['filename'];

            return Post::add(array("title" => $_POST['title'],
                                   "filename" => $filename,
                                   "description" => $_POST['description']),
                             $_POST['slug'],
                             Post::check_url($_POST['slug']));
        }

        public function update($post) {
            if (!isset($_POST['filename']))
                if (isset($_FILES['audio']) and $_FILES['audio']['error'] == 0) {
                    $this->delete_file($post);
                    $filename = upload($_FILES['audio'], array("mp3", "m4a", "mp4", "oga", "ogg", "webm", "mka"));
                } else
                    $filename = $post->filename;
            else {
                $this->delete_file($post);
                $filename = $_POST['filename'];
            }

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
            if ($post->feather != "audio") return;
            unlink(MAIN_DIR.Config::current()->uploads_path.$post->filename);
        }

        public function filter_post($post) {
            if ($post->feather != "audio") return;
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

            echo '<link rel="enclosure" href="'.uploaded($post->filename).'" type="'.$this->audio_type($post->filename).'" title="'.truncate(strip_tags($post->description)).'" length="'.$length.'" />';
        }

        public function audio_player($filename, $params = array(), $post) {
            $player = "\n".'<audio controls>';
            $player.= "\n\t".__("Your web browser does not support the <code>audio</code> element.", "audio");
            $player.= "\n\t".'<source src="'.uploaded($post->filename).'" type="'.$this->audio_type($post->filename).'">';
            $player.= "\n".'</audio>'."\n";

            return $player;
        }
    }
