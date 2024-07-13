<?php
    class Text extends Feathers implements Feather {
        public function __init() {
            $this->setField(
                array(
                    "attr" => "title",
                    "type" => "text",
                    "label" => __("Title", "text"),
                    "optional" => true
                )
            );
            $this->setField(
                array(
                    "attr" => "body",
                    "type" => "text_block",
                    "label" => __("Body", "text"),
                    "preview" => true
                )
            );
            $this->setFilter(
                "title",
                array("markup_post_title", "markup_title")
            );
            $this->setFilter(
                "body",
                array("markup_post_text", "markup_text")
            );
        }

        public function submit(): Post {
            if (empty($_POST['body']))
                error(
                    __("Error"),
                    __("Body can't be blank.", "text"),
                    code:422
                );

            fallback($_POST['title'], "");
            fallback($_POST['slug'], $_POST['title']);
            fallback($_POST['status'], "public");
            fallback($_POST['created_at'], datetime());
            fallback($_POST['option'], array());

            return Post::add(
                values:array(
                    "title" => $_POST['title'],
                    "body" => $_POST['body']
                ),
                clean:sanitize($_POST['slug'], true, SLUG_STRICT, 128),
                feather:"text",
                pinned:!empty($_POST['pinned']),
                status:$_POST['status'],
                created_at:datetime($_POST['created_at']),
                pingbacks:true,
                options:$_POST['option']
            );
        }

        public function update($post): Post|false {
            if (empty($_POST['body']))
                error(
                    __("Error"),
                    __("Body can't be blank.", "text"),
                    code:422
                );

            fallback($_POST['title'], "");
            fallback($_POST['slug'], "");
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());

            return $post->update(
                values:array(
                    "title" => $_POST['title'],
                    "body" => $_POST['body']
                ),
                pinned:!empty($_POST['pinned']),
                status:$_POST['status'],
                clean:sanitize($_POST['slug'], true, SLUG_STRICT, 128),
                created_at:datetime($_POST['created_at']),
                options:$_POST['option']
            );
        }

        public function title($post): string {
            return oneof(
                $post->title,
                $post->title_from_excerpt()
            );
        }

        public function excerpt($post): string {
            return $post->body;
        }

        public function feed_content($post): string {
            return $post->body;
        }
    }
