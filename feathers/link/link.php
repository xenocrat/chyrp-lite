<?php
    class Link extends Feathers implements Feather {
        public function __init() {
            $this->setField(
                array(
                    "attr" => "name",
                    "type" => "text",
                    "label" => __("Title", "link"),
                    "optional" => true
                )
            );
            $this->setField(
                array(
                    "attr" => "source",
                    "type" => "text",
                    "label" => __("URL", "link")
                )
            );
            $this->setField(
                array(
                    "attr" => "description",
                    "type" => "text_block",
                    "label" => __("Description", "link"),
                    "optional" => true,
                    "preview" => true
                )
            );
            $this->setFilter(
                "name",
                array("markup_post_title", "markup_title")
            );
            $this->setFilter(
                "description",
                array("markup_post_text", "markup_text")
            );
            $this->respondTo("feed_item", "link_related");
        }

        public function submit(): Post {
            if (empty($_POST['source']))
                error(
                    __("Error"),
                    __("URL can't be empty.", "link"),
                    code:422
                );

            if (!is_url($_POST['source']))
                error(
                    __("Error"),
                    __("Invalid URL.", "link")
                );

            fallback($_POST['name'], "");
            fallback($_POST['description'], "");
            fallback($_POST['slug'], $_POST['name']);
            fallback($_POST['status'], "public");
            fallback($_POST['created_at'], datetime());
            fallback($_POST['option'], array());

            $_POST['source'] = add_scheme($_POST['source']);

            return Post::add(
                values:array(
                    "name" => $_POST['name'],
                    "source" => $_POST['source'],
                    "description" => $_POST['description']
                ),
                clean:sanitize($_POST['slug'], true, SLUG_STRICT, 128),
                feather:"link",
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
            if (empty($_POST['source']))
                error(
                    __("Error"),
                    __("URL can't be empty.", "link"),
                    code:422
                );

            if (!is_url($_POST['source']))
                error(
                    __("Error"),
                    __("Invalid URL.", "link")
                );

            fallback($_POST['name'], "");
            fallback($_POST['description'], "");
            fallback($_POST['slug'], "");
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());

            $_POST['source'] = add_scheme($_POST['source']);

            return $post->update(
                values:array(
                    "name" => $_POST['name'],
                    "source" => $_POST['source'],
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
                $post->name,
                $post->title_from_excerpt(),
                $post->source
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
            $content = '<a rel="external" href="'.
                       fix($post->source, true).'">'.
                       oneof($post->name, $post->source).'</a>';

            if (!empty($post->description))
                $content.= '<figcaption>'.
                           $post->description.
                           '</figcaption>';

            return '<figure>'.$content.'</figure>';
        }

        public function link_related(
            $post,
            $feed
        ): void {
            if ($post->feather != "link")
                return;

            $feed->related($post->source);
        }
    }
