<?php
    require_once "lib/Parser.php";
    require_once "lib/DataBag.php";
    require_once "lib/Tag.php";

    class TextileLib extends Modules {
        public function __init() {
            $this->addAlias("markup_text", "textilize", 8);
        }
        static function textilize($text) {
            $parser = new \Netcarver\Textile\Parser('html5');
            return $parser->textileThis($text);
        }
    }
