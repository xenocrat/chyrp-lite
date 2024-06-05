<?php
    class Quote extends Feathers implements Feather {
        public function __init() {
            $this->setField(
                array(
                    "attr" => "quote",
                    "type" => "text_block",
                    "label" => __("Quote", "quote"),
                    "preview" => true
                )
            );
            $this->setField(
                array(
                    "attr" => "source",
                    "type" => "text_block",
                    "label" => __("Source", "quote"),
                    "optional" => true,
                    "preview" => true
                )
            );
            $this->setFilter(
                "quote",
                array("markup_post_text", "markup_text")
            );
            $this->setFilter(
                "source",
                array("markup_post_text", "markup_text")
            );
        }

        public function submit(): Post {
            if (empty($_POST['quote']))
                error(
                    __("Error"),
                    __("Quote can't be empty.", "quote"),
                    code:422
                );

            fallback($_POST['source'], "");
            fallback($_POST['slug'], $_POST['quote']);
            fallback($_POST['status'], "public");
            fallback($_POST['created_at'], datetime());
            fallback($_POST['option'], array());

            return Post::add(
                values:array(
                    "quote" => $_POST['quote'],
                    "source" => $_POST['source']
                ),
                clean:sanitize($_POST['slug'], true, true, 128),
                feather:"quote",
                pinned:!empty($_POST['pinned']),
                status:$_POST['status'],
                created_at:datetime($_POST['created_at']),
                pingbacks:true,
                options:$_POST['option']
            );
        }

        public function update($post): Post|false {
            if (empty($_POST['quote']))
                error(
                    __("Error"),
                    __("Quote can't be empty.", "quote"),
                    code:422
                );

            fallback($_POST['source'], "");
            fallback($_POST['slug'], $post->clean);
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());

            return $post->update(
                values:array(
                    "quote" => $_POST['quote'],
                    "source" => $_POST['source']
                ),
                pinned:!empty($_POST['pinned']),
                status:$_POST['status'],
                clean:sanitize($_POST['slug'], true, true, 128),
                created_at:datetime($_POST['created_at']),
                options:$_POST['option']
            );
        }

        public function title($post): string {
            return $post->title_from_excerpt();
        }

        public function excerpt($post): string {
            return $post->quote;
        }

        public function feed_content($post): string {
            $content = '<blockquote>'.
                       $post->quote.
                       '</blockquote>';

            if (!empty($post->source))
                $content.= '<figcaption><cite>'.
                           $post->source.
                           '</cite></figcaption>';

            return '<figure>'.$content.'</figure>';
        }
    }
