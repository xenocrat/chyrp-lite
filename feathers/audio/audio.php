<?php
    class Audio extends Feathers implements Feather {
        public function __init() {
            $maximum = Config::current()->uploads_limit;

            $this->setField(
                array(
                    "attr" => "title",
                    "type" => "text",
                    "label" => __("Title", "audio"),
                    "optional" => true
                )
            );
            $this->setField(
                array(
                    "attr" => "filename",
                    "type" => "file",
                    "label" => __("Audio File", "audio"),
                    "multiple" => false,
                    "accept" => ".".implode(",.", $this->audio_extensions())
                )
            );
            $this->setField(
                array(
                    "attr" => "captions",
                    "type" => "file",
                    "label" => __("Captions", "audio"),
                    "optional" => true,
                    "multiple" => false,
                    "accept" => ".vtt"
                )
            );
            $this->setField(
                array(
                    "attr" => "description",
                    "type" => "text_block",
                    "label" => __("Description", "audio"),
                    "optional" => true,
                    "preview"  => true
                )
            );
            $this->setFilter(
                "title",
                array("markup_post_title", "markup_title")
            );
            $this->setFilter(
                "description",
                array("markup_post_text", "markup_text")
            );
            $this->respondTo("feed_item", "enclose_audio");
            $this->respondTo("filter_post", "filter_post");
        }

        public function submit(
        ): Post {
            if (
                isset($_FILES['filename']) and
                upload_tester($_FILES['filename'])
            ) {
                if (!Visitor::current()->group->can("add_upload"))
                    show_403(
                        __("Access Denied"),
                        __("You do not have sufficient privileges to add uploads.")
                    );

                $filename = upload(
                    $_FILES['filename'],
                    $this->audio_extensions()
                );
            } elseif (
                !empty($_POST['filename']) and
                !is_fakepath($_POST['filename'])
            ) {
                $filename = $_POST['filename'];
            }

            if (!isset($filename))
                error(
                    __("Error"),
                    __("You did not select any audio to upload.", "audio"),
                    code:422
                );

            if (
                isset($_FILES['captions']) and
                upload_tester($_FILES['captions'])
            ) {
                $captions = upload(
                    $_FILES['captions'],
                    array("vtt")
                );
            } elseif (
                !empty($_POST['captions']) and
                !is_fakepath($_POST['captions'])
            ) {
                $captions = $_POST['captions'];
            }

            fallback($_POST['title'], "");
            fallback($_POST['description'], "");
            fallback($_POST['slug'], $_POST['title']);
            fallback($_POST['status'], "public");
            fallback($_POST['created_at'], datetime());
            fallback($_POST['option'], array());

            return Post::add(
                values:array(
                    "title" => $_POST['title'],
                    "filename" => $filename,
                    "captions" => fallback($captions, ""),
                    "description" => $_POST['description']
                ),
                clean:sanitize($_POST['slug'], true, SLUG_STRICT, 128),
                feather:"audio",
                pinned:!empty($_POST['pinned']),
                status:$_POST['status'],
                created_at:datetime($_POST['created_at']),
                pingbacks:true,
                options:$_POST['option']
            );
        }

        public function update(
            $post
        ): Post|false {
            fallback($_POST['title'], "");
            fallback($_POST['description'], "");
            fallback($_POST['slug'], "");
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());
            $filename = $post->filename;
            $captions = $post->captions;

            if (
                isset($_FILES['filename']) and
                upload_tester($_FILES['filename'])
            ) {
                if (!Visitor::current()->group->can("add_upload"))
                    show_403(
                        __("Access Denied"),
                        __("You do not have sufficient privileges to add uploads.")
                    );

                $filename = upload(
                    $_FILES['filename'],
                    $this->audio_extensions()
                );
            } elseif (
                !empty($_POST['filename']) and
                !is_fakepath($_POST['filename'])
            ) {
                $filename = $_POST['filename'];
            }

            if (
                isset($_FILES['captions']) and
                upload_tester($_FILES['captions'])
            ) {
                $captions = upload(
                    $_FILES['captions'],
                    array("vtt")
                );
            } elseif (
                !empty($_POST['captions']) and
                !is_fakepath($_POST['captions'])
            ) {
                $captions = $_POST['captions'];
            }

            return $post->update(
                values:array(
                    "title" => $_POST['title'],
                    "filename" => $filename,
                    "captions" => fallback($captions, ""),
                    "description" => $_POST['description']
                ),
                pinned:!empty($_POST['pinned']),
                status:$_POST['status'],
                clean:sanitize($_POST['slug'], true, SLUG_STRICT, 128),
                created_at:datetime($_POST['created_at']),
                options:$_POST['option']
            );
        }

        public function title(
            $post
        ): string {
            return oneof(
                $post->title,
                $post->title_from_excerpt()
            );
        }

        public function excerpt(
            $post
        ): string {
            return $post->description;
        }

        public function feed_content(
            $post
        ): string {
            return $post->description;
        }

        public function enclose_audio(
            $post,
            $feed
        ) {
            if ($post->feather != "audio")
                return;

            $filepath = uploaded($post->filename, false);

            if (file_exists($filepath))
                $feed->enclosure(
                    uploaded($post->filename),
                    filesize($filepath),
                    $this->audio_type($post->filename)
                );

            if (empty($post->captions))
                return;

            $filepath = uploaded($post->captions, false);

            if (file_exists($filepath))
                $feed->enclosure(
                    uploaded($post->captions),
                    filesize($filepath),
                    $this->audio_type($post->captions)
                );
        }

        public function filter_post(
            $post
        ): void {
            if ($post->feather != "audio")
                return;

            $post->audio_player = $this->audio_player($post);
        }

        private function audio_player(
            $post
        ): string {
            $config = Config::current();
            $trigger = Trigger::current();

            if ($trigger->exists("audio_player"))
                return $trigger->call("audio_player", $post);

            $player = '<audio controls>'.
                      "\n".
                      __("Your web browser does not support the <code>audio</code> element.", "audio").
                      "\n".
                      '<source src="'.
                      uploaded($post->filename).
                      '" type="'.
                      $this->audio_type($post->filename).
                      '">'.
                      "\n";

            if (!empty($post->captions))
                $player.= '<track kind="captions" src="'.
                          uploaded($post->captions).
                          '" srclang="'.
                          lang_base($config->locale).
                          '" label="'.
                          lang_code($config->locale).
                          '">'."\n";

            $player.= '</audio>'."\n";

            return $player;
        }

        private function audio_type(
            $filename
        ): string {
            $extension = strtolower(
                pathinfo($filename, PATHINFO_EXTENSION)
            );

            switch($extension) {
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

        private function audio_extensions(
        ): array {
            return array("mp3", "m4a", "mp4", "oga", "ogg", "webm", "mka");
        }
    }
