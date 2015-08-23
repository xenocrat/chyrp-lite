<?php
    require_once "lib".DIR."Parser.php";
    require_once "lib".DIR."DataBag.php";
    require_once "lib".DIR."Tag.php";

    class TextileLib extends Modules {
        public function __init() {
            $this->addAlias("markup_text", "textilize", 8);
        }
        static function textilize($text) {
            $parser = new \Netcarver\Textile\Parser('html5');
            return $parser->textileThis($text);
        }
    }
