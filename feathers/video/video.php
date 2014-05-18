<?php
    class Video extends Feathers implements Feather {
        public function __init() {
            $this->setField(array("attr" => "title",
                                  "type" => "text",
                                  "label" => __("Title", "video"),
                                  "optional" => true));
            $this->setField(array("attr" => "video",
                                  "type" => "file",
                                  "label" => __("Video File", "video"),
                                  "note" => _f("<small>(Max. file size: %s)</small>", array(ini_get('upload_max_filesize')))));
            $this->setField(array("attr" => "description",
                                  "type" => "text_block",
                                  "label" => __("Description"),
                                  "optional" => true));

            $this->setFilter("title", array("markup_title", "markup_post_title"));
            $this->setFilter("description", array("markup_text", "markup_post_text"));

            $this->respondTo("delete_post", "delete_file");
            $this->respondTo("feed_item", "enclose_video");
            $this->respondTo("filter_post", "filter_post");
            $this->respondTo("post_options", "add_option");
        }

        public function add_jplayer_script($scripts) {
            $scripts[] = Config::current()->chyrp_url."/feathers/video/jplayer/jquery.jplayer.js";
            return $scripts;
        }

        public function submit() {
            if (!isset($_POST['filename'])) {
                if (isset($_FILES['video']) and $_FILES['video']['error'] == 0)
                    $filename = upload($_FILES['video'], array("mp4", "ogv", "webm", "3gp", "mkv"));
                else
                    error(__("Error"), __("Couldn't upload video file.", "video"));
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
                if (isset($_FILES['video']) and $_FILES['video']['error'] == 0) {
                    $this->delete_file($post);
                    $filename = upload($_FILES['video'], array("mp4", "ogv", "webm", "3gp", "mkv"));
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
            if ($post->feather != "video") return;
            unlink(MAIN_DIR.Config::current()->uploads_path.$post->filename);
        }

        public function filter_post($post) {
            if ($post->feather != "video") return;
            $post->video_player = $this->video_player($post->filename, array(), $post);
        }

        public function video_type($filename) {
            $file_split = explode(".", $filename);
            $file_ext = strtolower(end($file_split));
            switch($file_ext) {
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
                default:
                    return "application/octet-stream";
            }
        }

        public function enclose_video($post) {
            $config = Config::current();
            if ($post->feather != "video" or !file_exists(uploaded($post->filename, false)))
                return;

            $length = filesize(uploaded($post->filename, false));

            echo '<link rel="enclosure" href="'.uploaded($post->filename).'" type="'.$this->video_type($post->filename).'" title="'.truncate(strip_tags($post->description)).'" length="'.$length.'" />';
        }

        public function video_player($filename, $params = array(), $post) {
            $player = "\n".'<video controls>';
            $player.= "\n\t".__("Your web browser does not support the <code>video</code> element.", "video");
            $player.= "\n\t".'<source src="'.uploaded($post->filename).'" type="'.$this->video_type($post->filename).'">';
            $player.= "\n".'</video>'."\n";

            return $player;
        }
    }
