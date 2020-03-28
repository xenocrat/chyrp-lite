<?php
    class Quote extends Feathers implements Feather {
        public function __init() {
            $this->setField(array("attr" => "quote",
                                  "type" => "text_block",
                                  "rows" => 5,
                                  "label" => __("Quote", "quote"),
                                  "preview" => true));

            $this->setField(array("attr" => "source",
                                  "type" => "text_block",
                                  "rows" => 5,
                                  "label" => __("Source", "quote"),
                                  "optional" => true,
                                  "preview" => true));

            $this->setFilter("quote", array("markup_post_text", "markup_text"));
            $this->setFilter("source", array("markup_post_text", "markup_text"));

            $this->respondTo("metaWeblog_getPost", "metaWeblog_getValues");
            $this->respondTo("metaWeblog_before_editPost", "metaWeblog_setValues");
        }

        public function submit() {
            if (empty($_POST['quote']))
                error(__("Error"), __("Quote can't be empty.", "quote"), null, 422);

            fallback($_POST['source'], "");
            fallback($_POST['slug'], $_POST['quote']);
            fallback($_POST['status'], "public");
            fallback($_POST['created_at'], datetime());
            fallback($_POST['option'], array());

            return Post::add(array("quote" => $_POST['quote'],
                                   "source" => $_POST['source']),
                             sanitize($_POST['slug']),
                             "",
                             "quote",
                             null,
                             !empty($_POST['pinned']),
                             $_POST['status'],
                             datetime($_POST['created_at']),
                             null,
                             true,
                             $_POST['option']);
        }

        public function update($post) {
            if (empty($_POST['quote']))
                error(__("Error"), __("Quote can't be empty."), null, 422);

            fallback($_POST['source'], "");
            fallback($_POST['slug'], $post->clean);
            fallback($_POST['status'], $post->status);
            fallback($_POST['created_at'], $post->created_at);
            fallback($_POST['option'], array());

            return $post->update(array("quote" => $_POST['quote'],
                                       "source" => $_POST['source']),
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
            return $post->title_from_excerpt();
        }

        public function excerpt($post) {
            return $post->quote;
        }

        public function feed_content($post) {
            $content = '<blockquote>'.$post->quote.'</blockquote>';

            if (!empty($post->source))
                $content.= '<cite>'.$post->source.'</cite>';

            return $content;
        }

        public function metaWeblog_getValues($struct, $post) {
            if ($post->feather != "quote")
                return;

            $struct["title"] = $post->source;
            $struct["description"] = $post->quote;

            return $struct;
        }

        public function metaWeblog_setValues($values, $struct, $post) {
            if ($post->feather != "quote")
                return;

            if ($struct["description"] != "") {
                $values["source"] = $struct["title"];
                $values["quote"] = $struct["description"];
            }

            return $values;
        }
    }
