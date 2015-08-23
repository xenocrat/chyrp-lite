<?php
    require_once "lib".DIR."MarkdownInterface.php";
    require_once "lib".DIR."Markdown.php";
    require_once "lib".DIR."MarkdownExtra.php";

    class MarkdownLib extends Modules {
        public function __init() {
            $this->addAlias("markup_text", "markdownify", 8);
        }
        static function markdownify($text) {
            return \Michelf\MarkdownExtra::defaultTransform($text);
        }
    }
