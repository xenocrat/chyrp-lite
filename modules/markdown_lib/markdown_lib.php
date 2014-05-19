<?php
    require_once "lib/MarkdownInterface.php";
    require_once "lib/Markdown.php";
    require_once "lib/MarkdownExtra.php";

    class MarkdownLibrary extends Modules {
        public function __init() {
            $this->addAlias("markup_text", "markdownify", 8);
            $this->addAlias("preview", "markdownify", 8);
        }
        static function markdownify($text) {
            return \Michelf\MarkdownExtra::defaultTransform($text);
        }
    }
