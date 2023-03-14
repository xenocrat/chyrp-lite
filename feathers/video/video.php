<?php
    class Video extends Feathers implements Feather {
        public function __init() {
            $maximum = Config::current()->uploads_limit;

            $this->setField(
                array(
                    "attr" => "title",
                    "type" => "text",
                    "label" => __("Title", "video"),
                    "optional" => true
                )
            );
            $this->setField(
                array(
                    "attr" => "filename",
                    "type" => "file",
                    "label" => __("Video File", "video"),
                    "multiple" => false,
                    "accept" => ".".implode(",.", $this->video_extensions()),
                    "note" => _f("(Max. file size: %d Megabytes)", $maximum, "video")
                )
            );
            $this->setField(
                array(
                    "attr" => "description",
                    "type" => "text_block",
                    "label" => __("Description", "video"),
                    "optional" => true,
                    "preview" => true
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
            $this->respondTo("feed_item", "enclose_video");
            $this->respondTo("filter_post", "filter_post");
        }

        public function submit(): Post {
            if (isset($_FILES['filename']) and upload_tester($_FILES['filename']))
                $filename = upload(
                    $_FILES['filename'],
                    $this->video_extensions()
                );

            if (!isset($filename))
                error(
                    __("Error"),
                    __("You did not select a video to upload.", "video"),
                    code:422
                );

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
                    "description" => $_POST['description']
                ),
                clean:sanitize($_POST['slug']),
                feather:"video",
                pinned:!empty($_POST['pinned']),
                status:$_POST['status'],
                created_at:datetime($_POST['created_at']),
                pingbacks:true,
                options:$_POST['option']
            );
        }

        public function update($post): Post|false {
            fallback($_POST['title'], "");
            fallback($_POST['description'], "");
            fallback($_POST['slug'], $post->clean);
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());
            $filename = $post->filename;

            if (isset($_FILES['filename']) and upload_tester($_FILES['filename']))
                $filename = upload(
                    $_FILES['filename'],
                    $this->video_extensions()
                );

            return $post->update(
                values:array(
                    "title" => $_POST['title'],
                    "filename" => $filename,
                    "description" => $_POST['description']
                ),
                pinned:!empty($_POST['pinned']),
                status:$_POST['status'],
                clean:sanitize($_POST['slug']),
                created_at:datetime($_POST['created_at']),
                options:$_POST['option']
            );
        }

        public function title($post): string {
            return oneof($post->title, $post->title_from_excerpt());
        }

        public function excerpt($post): string {
            return $post->description;
        }

        public function feed_content($post): string {
            return $post->description;
        }

        public function enclose_video($post, $feed): void {
            $config = Config::current();
            $filepath = uploaded($post->filename, false);

            if ($post->feather != "video" or !file_exists($filepath))
                return;

            $feed->enclosure(
                uploaded($post->filename),
                filesize($filepath),
                $this->video_type($post->filename)
            );
        }

        public function filter_post($post): void {
            if ($post->feather != "video")
                return;

            $post->video_player = $this->video_player($post);
        }

        private function video_player($post): string {
            $trigger = Trigger::current();

            if ($trigger->exists("video_player"))
                return $trigger->call("video_player", $post);

            return '<video controls>'.
                   "\n".
                   __("Your web browser does not support the <code>video</code> element.", "video").
                   "\n".
                   '<source src="'.
                   uploaded($post->filename).
                   '" type="'.
                   $this->video_type($post->filename).
                   '">'.
                   "\n".
                   '</video>'.
                   "\n";
        }

        private function video_type($filename): string {
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

        private function video_extensions(): array {
            return array("mp4", "ogv", "webm", "3gp", "mkv", "mov");
        }
    }
